<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Appels bruts à l'API de déboursement PayDunya (Mobile Money Côte d'Ivoire),
 * utilisés pour verser les gains des livreurs juste après chaque livraison.
 * Les mêmes endpoints servent aux reversements vendeurs (PayDunyaPayoutService).
 */
class PayDunyaDisbursementClient
{
    private const GET_INVOICE_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/get-invoice';
    private const SUBMIT_INVOICE_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/submit-invoice';
    private const CHECK_STATUS_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/check-status';

    /** Crée la demande de déboursement et retourne son token. */
    public function createInvoice(string $phone, float $amount, string $operator): string
    {
        $this->assertLive();

        $body = $this->json($this->request()->post(self::GET_INVOICE_ENDPOINT, [
            'account_alias' => $this->normalizePhone($phone),
            'amount' => max(1, (int) round($amount)),
            'withdraw_mode' => $this->withdrawMode($operator),
            'callback_url' => route('paydunya.payout.callback'),
        ]), 'initiation');

        $token = trim((string) ($body['disburse_token'] ?? ''));
        if (($body['response_code'] ?? null) !== '00' || $token === '') {
            throw new RuntimeException($this->message($body, 'PayDunya a refusé le virement.'));
        }

        return $token;
    }

    /** Soumet la demande ; retourne success, failed, pending ou unknown. */
    public function submit(string $token, string $reference): string
    {
        return $this->status($this->json($this->request()->post(self::SUBMIT_INVOICE_ENDPOINT, [
            'disburse_invoice' => $token,
            'disburse_id' => $reference,
        ]), 'soumission'));
    }

    public function check(string $token): string
    {
        return $this->status($this->json($this->request()->post(self::CHECK_STATUS_ENDPOINT, [
            'disburse_invoice' => $token,
        ]), 'vérification'));
    }

    public function supportsOperator(?string $operator): bool
    {
        try {
            $this->withdrawMode((string) $operator);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '00225')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '225') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }
        if (! preg_match('/^\d{10}$/', $digits)) {
            throw new RuntimeException('Numéro Mobile Money invalide : 10 chiffres attendus.');
        }

        return $digits;
    }

    private function withdrawMode(string $operator): string
    {
        return match (strtolower(trim($operator))) {
            'orange', 'orange_money', 'orange-money-ci' => 'orange-money-ci',
            'mtn', 'mtn_money', 'mtn-ci' => 'mtn-ci',
            'moov', 'moov_money', 'moov-ci' => 'moov-ci',
            'wave', 'wave-ci' => 'wave-ci',
            default => throw new RuntimeException('Opérateur Mobile Money non pris en charge.'),
        };
    }

    private function status(array $body): string
    {
        $status = strtolower(trim((string) ($body['status'] ?? '')));

        return match (true) {
            in_array($status, ['success', 'successful', 'completed'], true) => 'success',
            in_array($status, ['failed', 'failure', 'error'], true) => 'failed',
            in_array($status, ['pending', 'created'], true) => 'pending',
            (string) ($body['response_code'] ?? '') === '00' && filled($body['transaction_id'] ?? null) => 'success',
            isset($body['response_code']) && (string) $body['response_code'] !== '00' => 'failed',
            default => 'unknown',
        };
    }

    private function request(): PendingRequest
    {
        foreach (['master_key', 'private_key', 'token'] as $key) {
            if (blank(config('paydunya.' . $key))) {
                throw new RuntimeException("Clé PayDunya manquante : {$key}.");
            }
        }

        return Http::asJson()->acceptJson()->withHeaders([
            'PAYDUNYA-MASTER-KEY' => (string) config('paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => (string) config('paydunya.private_key'),
            'PAYDUNYA-TOKEN' => (string) config('paydunya.token'),
        ])->connectTimeout(10)->timeout(45);
    }

    private function json($response, string $stage): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException("Réponse PayDunya illisible pendant {$stage}.");
        }
        if (! $response->successful()) {
            throw new RuntimeException($this->message($body, "Erreur PayDunya pendant {$stage}."));
        }

        return $body;
    }

    private function message(array $body, string $fallback): string
    {
        $message = trim((string) ($body['response_text'] ?? $body['message'] ?? $body['description'] ?? ''));

        return $message !== '' ? mb_substr($message, 0, 500) : $fallback;
    }

    private function assertLive(): void
    {
        if (! config('paydunya.enabled') || strtolower((string) config('paydunya.mode', 'test')) !== 'live') {
            throw new RuntimeException('Les virements réels exigent PayDunya en mode live.');
        }
    }
}
