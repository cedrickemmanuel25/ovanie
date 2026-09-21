<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Paydunya\Checkout\CheckoutInvoice;
use Paydunya\Checkout\Store;
use Paydunya\Setup;

class PayDunyaService
{
    private const LIVE_SOFTPAY_ENDPOINTS = [
        'orange' => 'https://app.paydunya.com/api/v1/softpay/orange-money-ci',
        'mtn' => 'https://app.paydunya.com/api/v1/softpay/mtn-ci',
        'moov' => 'https://app.paydunya.com/api/v1/softpay/moov-ci',
        'wave' => 'https://app.paydunya.com/api/v1/softpay/wave-ci',
    ];

    public const SOFTPAY_OPERATORS = ['orange', 'mtn', 'moov', 'wave'];

    private function configure(): void
    {
        if (! config('paydunya.enabled')
            || collect(['master_key', 'private_key', 'public_key', 'token'])
                ->contains(fn (string $key) => blank(config('paydunya.'.$key)))) {
            throw new \RuntimeException('Le paiement PayDunya est indisponible.');
        }

        Setup::setMasterKey(config('paydunya.master_key'));
        Setup::setPrivateKey(config('paydunya.private_key'));
        Setup::setPublicKey(config('paydunya.public_key'));
        Setup::setToken(config('paydunya.token'));
        Setup::setMode(config('paydunya.mode', 'test'));

        Store::setName(config('paydunya.store_name', 'OVANIE'));
        Store::setTagline(config('paydunya.store_tagline', 'Marketplace Ovanie'));

        if (config('paydunya.store_phone')) {
            Store::setPhoneNumber(config('paydunya.store_phone'));
        }

        if (config('paydunya.store_address')) {
            Store::setPostalAddress(config('paydunya.store_address'));
        }

        if (config('paydunya.store_website')) {
            Store::setWebsiteUrl(config('paydunya.store_website'));
        }

        if (config('paydunya.store_logo')) {
            Store::setLogoUrl(config('paydunya.store_logo'));
        }

        Store::setCallbackUrl(route('paydunya.webhook'));
    }

    public function createOrderInvoice(array $data): CheckoutInvoice
    {
        $this->configure();

        $invoice = new CheckoutInvoice();

        $invoice->addItem(
            $data['item_name'],
            1,
            $data['amount'],
            $data['amount'],
            $data['description']
        );

        $invoice->setTotalAmount($data['amount']);
        $invoice->setReturnUrl($data['return_url']);
        $invoice->setCancelUrl($data['cancel_url']);
        $invoice->setCallbackUrl(route('paydunya.webhook'));

        if (! empty($data['channel'])) {
            $invoice->addChannel((string) $data['channel']);
        }

        return $invoice;
    }

    /**
     * Vérifie une facture directement auprès de PayDunya.
     *
     * Cette vérification complète le webhook. Elle est indispensable en local,
     * car PayDunya ne peut pas appeler une URL 127.0.0.1 depuis Internet.
     */
    public function confirmInvoice(string $token): array
    {
        $token = trim($token);

        if ($token === '') {
            return [
                'verified' => false,
                'status' => 'unknown',
                'amount' => null,
                'receipt_url' => null,
            ];
        }

        try {
            $this->configure();

            $invoice = new CheckoutInvoice();
            $confirmed = $invoice->confirm($token);
            $status = strtolower(trim((string) $invoice->getStatus()));

            return [
                'verified' => $confirmed && $status === 'completed',
                'status' => $status !== '' ? $status : 'unknown',
                'amount' => (float) $invoice->getTotalAmount(),
                'receipt_url' => $invoice->getReceiptUrl(),
            ];
        } catch (\Throwable $e) {
            logger()->warning('Vérification de paiement PayDunya impossible.', [
                'token_prefix' => substr($token, 0, 10),
                'message' => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'status' => 'unknown',
                'amount' => null,
                'receipt_url' => null,
            ];
        }
    }

    /**
     * Déclenche un paiement SoftPay sans exposer l'interface PayDunya au client.
     *
     * Le token de facture est d'abord créé via createOrderInvoice(), puis cette
     * méthode appelle l'endpoint SoftPay correspondant au moyen choisi.
     */
    public function startSoftPay(array $data): array
    {
        $operator = strtolower(trim((string) ($data['operator'] ?? '')));
        $paymentToken = trim((string) ($data['payment_token'] ?? ''));

        if (! array_key_exists($operator, self::LIVE_SOFTPAY_ENDPOINTS)) {
            return [
                'success' => false,
                'message' => 'Le moyen de paiement sélectionné n’est pas pris en charge.',
            ];
        }

        if ($paymentToken === '') {
            return [
                'success' => false,
                'message' => 'Le paiement n’a pas pu être initialisé.',
            ];
        }

        // PayDunya n'expose aucun endpoint SoftPay de bac à sable : les URL
        // testées (/sandbox-api/v1/softpay/checkout/make-payment et
        // /sandbox-api/v1/softpay/{operateur}-ci) renvoient toutes deux une
        // vraie page 404 PayDunya. La seule URL SoftPay qui existe est celle
        // ci-dessous ; en PAYDUNYA_MODE=test elle refuse la facture créée en
        // sandbox ("invoice inexistant"), car cette facture n'existe pas côté
        // live — et la création de facture "live" avec des clés test est elle
        // -même rejetée ("LIVE Private Key and Token combination is invalid").
        // Le paiement Mobile Money encaissé sur nos propres pages (SoftPay) ne
        // peut donc être vérifié de bout en bout qu'en PAYDUNYA_MODE=live.
        $mode = strtolower((string) config('paydunya.mode', 'test'));
        if ($mode !== 'live') {
            return [
                'success' => false,
                'message' => 'Le paiement Mobile Money direct (SoftPay) n’a pas d’équivalent de test chez '
                    . 'PayDunya : il ne peut être vérifié de bout en bout qu’en passant PAYDUNYA_MODE=live '
                    . '(avec un petit montant réel).',
            ];
        }

        $endpoint = self::LIVE_SOFTPAY_ENDPOINTS[$operator];
        $payload = $this->liveSoftPayPayload($operator, $paymentToken, $data);

        if ($operator === 'orange' && empty($payload['orange_money_ci_otp'])) {
            return [
                'success' => false,
                'message' => 'Le code de paiement Orange Money est obligatoire.',
            ];
        }

        return $this->postSoftPay($endpoint, $payload);
    }

    public function createBoostInvoice(array $data): CheckoutInvoice
    {
        $this->configure();

        $invoice = new CheckoutInvoice();

        $invoice->addItem(
            $data['item_name'],
            1,
            $data['amount'],
            $data['amount'],
            $data['description']
        );

        $invoice->setTotalAmount($data['amount']);
        $invoice->setReturnUrl($data['return_url']);
        $invoice->setCancelUrl($data['cancel_url']);
        $invoice->setCallbackUrl(route('paydunya.webhook'));

        return $invoice;
    }

    private function liveSoftPayPayload(string $operator, string $paymentToken, array $data): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = $this->normalizeCiPhone((string) ($data['phone'] ?? ''));
        $orangeOtp = preg_replace('/\D+/', '', (string) ($data['orange_otp'] ?? '')) ?: '';

        return match ($operator) {
            'orange' => [
                'orange_money_ci_customer_fullname' => $fullName,
                'orange_money_ci_email' => $email,
                'orange_money_ci_phone_number' => $phone,
                'orange_money_ci_otp' => $orangeOtp,
                'payment_token' => $paymentToken,
            ],
            'mtn' => [
                'mtn_ci_customer_fullname' => $fullName,
                'mtn_ci_email' => $email,
                'mtn_ci_phone_number' => $phone,
                'mtn_ci_wallet_provider' => 'MTNCI',
                'payment_token' => $paymentToken,
            ],
            'moov' => [
                'moov_ci_customer_fullname' => $fullName,
                'moov_ci_email' => $email,
                'moov_ci_phone_number' => $phone,
                'payment_token' => $paymentToken,
            ],
            'wave' => [
                'wave_ci_fullName' => $fullName,
                'wave_ci_email' => $email,
                // PayDunya production valide le plan ivoirien actuel à
                // 10 chiffres (ex. 0704749785), sans l'indicatif +225.
                'wave_ci_phone' => $phone,
                'wave_ci_payment_token' => $paymentToken,
            ],
        };
    }

    private function postSoftPay(string $endpoint, array $payload): array
    {
        try {
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
                    throw new \RuntimeException('Le fichier de certificats CA configuré pour PayDunya est introuvable.');
                }

                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->post($endpoint, $payload);

            $body = $response->json();

            if (! is_array($body)) {
                $rawBody = trim((string) $response->body());

                logger()->warning('PayDunya SoftPay a retourné une réponse non JSON.', [
                    'status' => $response->status(),
                    'content_type' => $response->header('Content-Type'),
                    'endpoint' => parse_url($endpoint, PHP_URL_PATH),
                    'body_excerpt' => mb_substr(strip_tags($rawBody), 0, 500),
                ]);

                $providerMessage = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');

                return [
                    'success' => false,
                    'message' => $providerMessage !== '' && mb_strlen($providerMessage) <= 250
                        ? $providerMessage
                        : 'PayDunya a retourné une réponse HTTP '.$response->status().' non exploitable.',
                ];
            }

            $nestedError = data_get($body, 'errors.message')
                ?? data_get($body, 'errors.description');

            if (! $response->successful()) {
                logger()->warning('PayDunya SoftPay a refusé la requête.', [
                    'status' => $response->status(),
                    'endpoint' => parse_url($endpoint, PHP_URL_PATH),
                    'provider_message' => (string) ($body['message'] ?? $nestedError ?? $body['response_text'] ?? ''),
                    'response_code' => (string) ($body['response_code'] ?? ''),
                ]);

                return [
                    'success' => false,
                    'message' => (string) ($body['message'] ?? $nestedError ?? $body['response_text'] ?? 'Le service de paiement est temporairement indisponible.'),
                ];
            }

            $body['message'] = (string) ($body['message'] ?? $nestedError ?? $body['response_text'] ?? '');
            $body['url'] = (string) ($body['url'] ?? $body['payment_url'] ?? $body['invoice_url'] ?? '');

            if (empty($body['success'])) {
                logger()->warning('PayDunya SoftPay a retourné un échec métier.', [
                    'status' => $response->status(),
                    'endpoint' => parse_url($endpoint, PHP_URL_PATH),
                    'provider_message' => $body['message'],
                    'response_code' => (string) ($body['response_code'] ?? ''),
                ]);
            }

            return $body;
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Le service de paiement est temporairement indisponible. Réessayez dans quelques instants.',
            ];
        }
    }

    private function normalizeCiPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '225') && strlen($digits) > 10) {
            return substr($digits, 3);
        }

        return $digits;
    }
}
