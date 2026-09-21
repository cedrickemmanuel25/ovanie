<?php

namespace App\Services;

use App\Models\VendorPayout;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Exécute les reversements vendeurs OVANIE via l'API de déboursement PayDunya.
 *
 * Sécurité :
 * - aucun transfert n'est lancé tant que VENDOR_PAYOUT_EXECUTION_MODE != paydunya ;
 * - l'exécution automatique est limitée au mode PAYDUNYA_MODE=live ;
 * - un token de déboursement déjà créé est toujours réutilisé afin d'éviter
 *   de créer une nouvelle demande lors d'une reprise après timeout ;
 * - un reversement n'est marqué "paid" qu'après un statut PayDunya "success".
 */
class PayDunyaPayoutService
{
    private const GET_INVOICE_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/get-invoice';
    private const SUBMIT_INVOICE_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/submit-invoice';
    private const CHECK_STATUS_ENDPOINT = 'https://app.paydunya.com/api/v2/disburse/check-status';

    public function __construct(
        private readonly VendorPayoutService $payoutService,
    ) {
    }

    /**
     * Traite les reversements approuvés/en traitement. Retourne un bilan sans
     * exposer de secret PayDunya.
     */
    public function processOpen(?int $limit = null): array
    {
        $this->assertAutomaticExecutionAllowed();

        $limit ??= (int) config('vendor_payouts.auto_batch_limit', 25);
        $limit = max(1, min(200, $limit));

        $payouts = VendorPayout::query()
            ->whereIn('status', [
                VendorPayout::STATUS_APPROVED,
                VendorPayout::STATUS_PROCESSING,
            ])
            ->orderByRaw("CASE WHEN status = 'processing' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $result = [
            'examined' => 0,
            'paid' => 0,
            'pending' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($payouts as $payout) {
            $result['examined']++;

            try {
                $before = $payout->status;
                $fresh = $this->execute($payout);

                if ($fresh->status === VendorPayout::STATUS_PAID) {
                    $result['paid']++;
                } elseif ($fresh->status === VendorPayout::STATUS_FAILED) {
                    $result['failed']++;
                } elseif ($fresh->status === VendorPayout::STATUS_PROCESSING) {
                    $result['pending']++;
                } elseif ($fresh->status === $before) {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                Log::error('Échec du traitement automatique d’un reversement PayDunya.', [
                    'payout_id' => $payout->id,
                    'reference' => $payout->payout_reference,
                    'message' => $e->getMessage(),
                ]);

                $result['failed']++;
            }
        }

        return $result;
    }

    public function execute(VendorPayout $payout): VendorPayout
    {
        $this->assertAutomaticExecutionAllowed();

        $payout = $payout->fresh(['shop', 'vendor', 'order']);

        if (! $payout) {
            throw new RuntimeException('Reversement introuvable.');
        }

        if (in_array($payout->status, VendorPayout::FINAL_STATUSES, true)) {
            return $payout;
        }

        if (! in_array($payout->status, [VendorPayout::STATUS_APPROVED, VendorPayout::STATUS_PROCESSING], true)) {
            return $payout;
        }

        $shop = $payout->shop;
        if (! $shop) {
            return $this->payoutService->fail($payout, 'Boutique introuvable pour le reversement.');
        }

        try {
            $phone = $this->normalizeBeneficiaryPhone((string) ($payout->phone ?: $shop->mm_number));
            $withdrawMode = $this->resolveWithdrawMode((string) ($payout->payment_channel ?: $shop->mm_operator));
            $providerAmount = $this->providerAmount((float) $payout->payout_amount);
        } catch (\Throwable $e) {
            return $this->payoutService->fail($payout, $e->getMessage());
        }

        $meta = $payout->meta ?? [];
        $disbursement = (array) ($meta['paydunya_disbursement'] ?? []);
        $invoiceToken = trim((string) ($disbursement['invoice_token'] ?? ''));

        // Une demande déjà créée est d'abord réconciliée. On ne recrée jamais
        // un token tant que l'ancien existe.
        if ($invoiceToken !== '') {
            $checked = $this->checkStatus($payout);

            if (in_array($checked->status, [VendorPayout::STATUS_PAID, VendorPayout::STATUS_FAILED], true)) {
                return $checked;
            }

            $checkedMeta = $checked->meta ?? [];
            $checkedDisbursement = (array) ($checkedMeta['paydunya_disbursement'] ?? []);
            $providerStatus = strtolower((string) ($checkedDisbursement['last_status'] ?? ''));

            if ($providerStatus === 'pending') {
                return $checked;
            }

            // "created" = facture créée mais pas encore soumise. Après un timeout
            // réseau, resoumettre le même token est plus sûr que recréer une facture.
            if ($providerStatus === 'created' || $providerStatus === 'unknown' || $providerStatus === '') {
                return $this->submitExistingInvoice($checked, $invoiceToken);
            }

            return $checked;
        }

        // Passer en traitement avant l'appel externe. Ce statut n'affirme pas que
        // l'argent est parti ; il indique uniquement que le fournisseur est sollicité.
        if ($payout->status === VendorPayout::STATUS_APPROVED) {
            $payout = $this->payoutService->markProcessing(
                $payout,
                $payout->batch_reference ?: ('PD-' . now()->format('YmdHis'))
            );
        }

        try {
            $invoiceResponse = $this->request()->post(self::GET_INVOICE_ENDPOINT, [
                'account_alias' => $phone,
                'amount' => $providerAmount,
                'withdraw_mode' => $withdrawMode,
                'callback_url' => route('paydunya.payout.callback'),
            ]);

            $body = $this->jsonOrThrow($invoiceResponse, 'initiation');
            $invoiceToken = trim((string) ($body['disburse_token'] ?? ''));

            if (($body['response_code'] ?? null) !== '00' || $invoiceToken === '') {
                throw new RuntimeException($this->providerMessage($body, 'PayDunya a refusé l’initiation du reversement.'));
            }

            $this->mergeDisbursementMeta($payout, [
                'invoice_token' => $invoiceToken,
                'withdraw_mode' => $withdrawMode,
                'beneficiary_phone_masked' => $this->maskPhone($phone),
                'provider_amount_xof' => $providerAmount,
                'accounting_amount' => (float) $payout->payout_amount,
                'rounding_adjustment' => round($providerAmount - (float) $payout->payout_amount, 2),
                'invoice_created_at' => now()->toDateTimeString(),
                'last_status' => 'created',
                'last_response_code' => $body['response_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Aucun token n'a été enregistré : aucun submit n'est possible. On remet
            // le dossier en "approved" afin de permettre une reprise contrôlée.
            $meta = $payout->fresh()->meta ?? [];
            $meta['paydunya_disbursement_error'] = [
                'stage' => 'get_invoice',
                'message' => $e->getMessage(),
                'at' => now()->toDateTimeString(),
            ];

            $payout->forceFill([
                'status' => VendorPayout::STATUS_APPROVED,
                'meta' => $meta,
            ])->save();

            Log::warning('Initiation PayDunya Payout impossible.', [
                'payout_id' => $payout->id,
                'reference' => $payout->payout_reference,
                'message' => $e->getMessage(),
            ]);

            return $payout->refresh();
        }

        return $this->submitExistingInvoice($payout->refresh(), $invoiceToken);
    }

    public function checkStatus(VendorPayout $payout): VendorPayout
    {
        $payout = $payout->fresh();
        $meta = $payout->meta ?? [];
        $token = trim((string) data_get($meta, 'paydunya_disbursement.invoice_token', ''));

        if ($token === '') {
            return $payout;
        }

        try {
            $response = $this->request()->post(self::CHECK_STATUS_ENDPOINT, [
                'disburse_invoice' => $token,
            ]);

            $body = $this->jsonOrThrow($response, 'vérification');
            return $this->applyProviderResult($payout, $body, 'check_status');
        } catch (\Throwable $e) {
            $this->mergeDisbursementMeta($payout, [
                'status_check_error' => $e->getMessage(),
                'status_check_error_at' => now()->toDateTimeString(),
            ]);

            Log::warning('Vérification PayDunya Payout impossible.', [
                'payout_id' => $payout->id,
                'reference' => $payout->payout_reference,
                'message' => $e->getMessage(),
            ]);

            return $payout->refresh();
        }
    }

    /**
     * Traite le callback serveur-à-serveur PayDunya.
     * Le hash attendu est SHA-512 de la MasterKey, conformément à la doc PayDunya.
     */
    public function handleCallback(array $payload): ?VendorPayout
    {
        $this->assertValidCallbackHash((string) ($payload['hash'] ?? ''));

        $disburseId = trim((string) ($payload['disburse_id'] ?? ''));
        $token = trim((string) ($payload['token'] ?? $payload['disburse_invoice'] ?? ''));

        $query = VendorPayout::query();

        if ($disburseId !== '') {
            $query->where('payout_reference', $disburseId);
        } elseif ($token !== '') {
            $query->where('meta->paydunya_disbursement->invoice_token', $token);
        } else {
            throw new RuntimeException('Callback PayDunya sans identifiant de reversement.');
        }

        $payout = $query->first();

        if (! $payout) {
            Log::warning('Callback PayDunya Payout sans reversement OVANIE correspondant.', [
                'disburse_id' => $disburseId ?: null,
                'token_prefix' => $token !== '' ? substr($token, 0, 10) : null,
            ]);

            return null;
        }

        $reportedAmount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $expectedAmount = (int) data_get(
            $payout->meta ?? [],
            'paydunya_disbursement.provider_amount_xof',
            $this->providerAmount((float) $payout->payout_amount)
        );

        if ($reportedAmount !== null && abs($reportedAmount - $expectedAmount) > 0.01) {
            throw new RuntimeException('Montant du callback PayDunya incohérent avec le reversement OVANIE.');
        }

        return $this->applyProviderResult($payout, $payload, 'callback');
    }

    private function submitExistingInvoice(VendorPayout $payout, string $invoiceToken): VendorPayout
    {
        try {
            $response = $this->request()->post(self::SUBMIT_INVOICE_ENDPOINT, [
                'disburse_invoice' => $invoiceToken,
                'disburse_id' => $payout->payout_reference,
            ]);

            $body = $this->jsonOrThrow($response, 'soumission');
            $this->mergeDisbursementMeta($payout, [
                'submitted_at' => now()->toDateTimeString(),
            ]);

            return $this->applyProviderResult($payout->refresh(), $body, 'submit_invoice');
        } catch (\Throwable $e) {
            // Un timeout au submit est un état incertain : ne jamais marquer "failed"
            // automatiquement. Le prochain passage interrogera check-status avec le
            // même token avant toute nouvelle action.
            $this->mergeDisbursementMeta($payout, [
                'submit_uncertain' => true,
                'submit_error' => $e->getMessage(),
                'submit_error_at' => now()->toDateTimeString(),
            ]);

            Log::warning('Soumission PayDunya Payout incertaine ; réconciliation requise.', [
                'payout_id' => $payout->id,
                'reference' => $payout->payout_reference,
                'message' => $e->getMessage(),
            ]);

            return $payout->fresh();
        }
    }

    private function applyProviderResult(VendorPayout $payout, array $body, string $source): VendorPayout
    {
        $status = $this->providerStatus($body);

        $this->mergeDisbursementMeta($payout, [
            'last_status' => $status,
            'last_source' => $source,
            'last_checked_at' => now()->toDateTimeString(),
            'transaction_id' => $body['transaction_id'] ?? data_get($payout->meta ?? [], 'paydunya_disbursement.transaction_id'),
            'provider_ref' => $body['provider_ref'] ?? $body['disburse_tx_id'] ?? data_get($payout->meta ?? [], 'paydunya_disbursement.provider_ref'),
            'last_response_code' => $body['response_code'] ?? null,
            'last_response_text' => $body['response_text'] ?? $body['description'] ?? null,
        ]);

        $payout = $payout->fresh();

        if ($status === 'success') {
            // payout_reference reste la référence OVANIE/disburse_id. Les références
            // PayDunya sont conservées dans meta afin que les callbacks restent idempotents.
            return $this->payoutService->markPaid($payout);
        }

        if ($status === 'failed') {
            return $this->payoutService->fail(
                $payout,
                $this->providerMessage($body, 'PayDunya a signalé l’échec du reversement.')
            );
        }

        if ($payout->status !== VendorPayout::STATUS_PROCESSING) {
            return $this->payoutService->markProcessing($payout, $payout->batch_reference);
        }

        return $payout;
    }

    private function providerStatus(array $body): string
    {
        $status = strtolower(trim((string) ($body['status'] ?? '')));

        if (in_array($status, ['success', 'successful', 'completed'], true)) {
            return 'success';
        }

        if (in_array($status, ['failed', 'failure', 'error'], true)) {
            return 'failed';
        }

        if ($status === 'pending') {
            return 'pending';
        }

        if ($status === 'created') {
            return 'created';
        }

        // Certaines réponses de soumission réussies documentées par PayDunya
        // retournent response_code=00 + transaction_id sans champ status.
        if ((string) ($body['response_code'] ?? '') === '00' && filled($body['transaction_id'] ?? null)) {
            return 'success';
        }

        if (isset($body['response_code']) && (string) $body['response_code'] !== '00') {
            return 'failed';
        }

        return 'unknown';
    }

    private function mergeDisbursementMeta(VendorPayout $payout, array $changes): void
    {
        $fresh = $payout->fresh();
        $meta = $fresh->meta ?? [];
        $current = (array) ($meta['paydunya_disbursement'] ?? []);
        $meta['paydunya_disbursement'] = array_merge($current, $changes);

        $fresh->forceFill(['meta' => $meta])->save();
    }

    private function request(): PendingRequest
    {
        $this->assertPayDunyaConfigured();

        $request = Http::asJson()
            ->acceptJson()
            ->withHeaders([
                'PAYDUNYA-MASTER-KEY' => (string) config('paydunya.master_key'),
                'PAYDUNYA-PRIVATE-KEY' => (string) config('paydunya.private_key'),
                'PAYDUNYA-TOKEN' => (string) config('paydunya.token'),
            ])
            ->connectTimeout(10)
            ->timeout(45);

        $caBundle = trim((string) config('paydunya.ca_bundle'));
        if ($caBundle !== '') {
            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new RuntimeException('Le certificat CA configuré pour PayDunya est introuvable.');
            }

            $request = $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function jsonOrThrow($response, string $stage): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException("Réponse PayDunya non JSON pendant {$stage}.");
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->providerMessage(
                $body,
                "Erreur HTTP PayDunya pendant {$stage}."
            ));
        }

        return $body;
    }

    private function assertAutomaticExecutionAllowed(): void
    {
        if ((string) config('vendor_payouts.execution_mode') !== 'paydunya') {
            throw new RuntimeException('Les reversements PayDunya automatiques ne sont pas activés.');
        }

        if (strtolower((string) config('paydunya.mode', 'test')) !== 'live') {
            throw new RuntimeException('Les reversements automatiques sont bloqués hors du mode PayDunya live.');
        }

        $this->assertPayDunyaConfigured();
    }

    private function assertPayDunyaConfigured(): void
    {
        if (! config('paydunya.enabled')) {
            throw new RuntimeException('PayDunya est désactivé dans la configuration OVANIE.');
        }

        foreach (['master_key', 'private_key', 'token'] as $key) {
            if (blank(config('paydunya.' . $key))) {
                throw new RuntimeException("Clé PayDunya manquante : {$key}.");
            }
        }
    }

    private function assertValidCallbackHash(string $receivedHash): void
    {
        $masterKey = (string) config('paydunya.master_key');

        if ($masterKey === '' || $receivedHash === '') {
            throw new RuntimeException('Signature du callback PayDunya absente.');
        }

        $expected = hash('sha512', $masterKey);

        if (! hash_equals(strtolower($expected), strtolower(trim($receivedHash)))) {
            throw new RuntimeException('Signature du callback PayDunya invalide.');
        }
    }

    private function resolveWithdrawMode(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'orange', 'orange_money', 'orange-money', 'orange-money-ci' => 'orange-money-ci',
            'mtn', 'mtn_money', 'mtn-momo', 'mtn-ci' => 'mtn-ci',
            'moov', 'moov_money', 'moov-money', 'moov-ci' => 'moov-ci',
            'wave', 'wave_money', 'wave-ci' => 'wave-ci',
            default => throw new RuntimeException('Opérateur Mobile Money vendeur non pris en charge par PayDunya Côte d’Ivoire.'),
        };
    }

    private function normalizeBeneficiaryPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '00225')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '225') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }

        if (! preg_match('/^\d{10}$/', $digits)) {
            throw new RuntimeException('Numéro Mobile Money vendeur invalide : 10 chiffres ivoiriens attendus, sans indicatif pays.');
        }

        return $digits;
    }

    private function providerAmount(float $amount): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('Le montant du reversement doit être supérieur à zéro.');
        }

        // PayDunya exige un montant XOF entier. OVANIE conserve l'éventuel
        // ajustement d'arrondi dans meta pour traçabilité comptable.
        return max(1, (int) round($amount));
    }

    private function providerMessage(array $body, string $fallback): string
    {
        $message = trim((string) ($body['response_text'] ?? $body['message'] ?? $body['description'] ?? ''));

        return $message !== '' ? mb_substr($message, 0, 500) : $fallback;
    }

    private function maskPhone(string $phone): string
    {
        return strlen($phone) >= 4
            ? str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4)
            : '****';
    }
}
