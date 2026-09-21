<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * PaymentService OVANIE
 *
 * Ce fichier est volontairement compatible avec deux usages existants du projet :
 * 1. Service utilitaire : constantes, statuts, libellés, formats, normalisation.
 * 2. Modèle Eloquent éventuel : certaines relations du projet peuvent pointer vers
 *    App\Services\PaymentService::class au lieu de App\Models\PaymentService::class.
 */
class PaymentService extends Model
{
    protected $table = 'payment_services';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Statuts de paiement
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PAID = 'paid';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ESCROW_HELD = 'escrow_held';
    public const STATUS_RELEASED_TO_VENDOR = 'released_to_vendor';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ERROR = 'error';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CHARGEBACK = 'chargeback';
    public const STATUS_DISPUTED = 'disputed';

    public const PAID_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_SUCCESS,
        self::STATUS_SUCCESSFUL,
        self::STATUS_COMPLETED,
        self::STATUS_VALIDATED,
        self::STATUS_CONFIRMED,
        self::STATUS_ESCROW_HELD,
        self::STATUS_RELEASED_TO_VENDOR,
    ];

    public const FAILED_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_ERROR,
        self::STATUS_CANCELLED,
        self::STATUS_CANCELED,
        self::STATUS_EXPIRED,
    ];

    public const PENDING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_WAITING,
        self::STATUS_PROCESSING,
        self::STATUS_INITIATED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Méthodes / canaux de paiement
    |--------------------------------------------------------------------------
    | Beaucoup de fichiers du projet peuvent utiliser des noms différents pour
    | la même méthode. On conserve donc les alias pour éviter les erreurs de
    | constantes manquantes en production.
    */

    public const METHOD_WAVE = 'wave';

    public const METHOD_MTN = 'mtn';
    public const METHOD_MTN_MOMO = 'mtn_momo';
    public const METHOD_MTN_MONEY = 'mtn_money';
    public const METHOD_MTN_MOBILE_MONEY = 'mtn_mobile_money';

    public const METHOD_ORANGE = 'orange';
    public const METHOD_ORANGE_MONEY = 'orange_money';
    public const METHOD_OM = 'om';

    public const METHOD_MOOV = 'moov';
    public const METHOD_MOOV_MONEY = 'moov_money';

    public const METHOD_MOBILE_MONEY = 'mobile_money';
    public const METHOD_MOMO = 'momo';

    public const METHOD_CARD = 'card';
    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_DEBIT_CARD = 'debit_card';
    public const METHOD_VISA = 'visa';
    public const METHOD_MASTERCARD = 'mastercard';

    public const METHOD_CASH = 'cash';
    public const METHOD_COD = 'cod';
    public const METHOD_CASH_ON_DELIVERY = 'cash_on_delivery';
    public const METHOD_PAY_ON_DELIVERY = 'pay_on_delivery';
    public const METHOD_POST_DELIVERY = 'post_delivery';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_BANK = 'bank';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_CHECK = 'check';

    public const METHOD_MANUAL = 'manual';
    public const METHOD_MANUAL_PROOF = 'manual_proof';
    public const METHOD_PAYMENT_PROOF = 'payment_proof';
    public const METHOD_PROOF = 'proof';
    public const METHOD_OFFLINE = 'offline';
    public const METHOD_DIRECT_PAYMENT = 'direct_payment';
    public const METHOD_ADMIN_VALIDATION = 'admin_validation';

    public const METHOD_PAYDUNYA = 'paydunya';
    public const METHOD_CINETPAY = 'cinetpay';
    public const METHOD_FEDAPAY = 'fedapay';
    public const METHOD_FLUTTERWAVE = 'flutterwave';
    public const METHOD_PAYSTACK = 'paystack';
    public const METHOD_STRIPE = 'stripe';
    public const METHOD_PAYPAL = 'paypal';

    public const MOBILE_MONEY_METHODS = [
        self::METHOD_WAVE,
        self::METHOD_MTN,
        self::METHOD_MTN_MOMO,
        self::METHOD_MTN_MONEY,
        self::METHOD_MTN_MOBILE_MONEY,
        self::METHOD_ORANGE,
        self::METHOD_ORANGE_MONEY,
        self::METHOD_OM,
        self::METHOD_MOOV,
        self::METHOD_MOOV_MONEY,
        self::METHOD_MOBILE_MONEY,
        self::METHOD_MOMO,
    ];

    public const ONLINE_GATEWAY_METHODS = [
        self::METHOD_PAYDUNYA,
        self::METHOD_CINETPAY,
        self::METHOD_FEDAPAY,
        self::METHOD_FLUTTERWAVE,
        self::METHOD_PAYSTACK,
        self::METHOD_STRIPE,
        self::METHOD_PAYPAL,
    ];

    public const CARD_METHODS = [
        self::METHOD_CARD,
        self::METHOD_CREDIT_CARD,
        self::METHOD_DEBIT_CARD,
        self::METHOD_VISA,
        self::METHOD_MASTERCARD,
    ];

    public const OFFLINE_METHODS = [
        self::METHOD_CASH,
        self::METHOD_COD,
        self::METHOD_CASH_ON_DELIVERY,
        self::METHOD_PAY_ON_DELIVERY,
        self::METHOD_POST_DELIVERY,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_TRANSFER,
        self::METHOD_BANK,
        self::METHOD_CHEQUE,
        self::METHOD_CHECK,
        self::METHOD_MANUAL,
        self::METHOD_MANUAL_PROOF,
        self::METHOD_PAYMENT_PROOF,
        self::METHOD_PROOF,
        self::METHOD_OFFLINE,
        self::METHOD_DIRECT_PAYMENT,
        self::METHOD_ADMIN_VALIDATION,
    ];

    /*
    |--------------------------------------------------------------------------
    | Normalisation
    |--------------------------------------------------------------------------
    */

    public static function normalizeAmount(mixed $amount): float
    {
        if ($amount === null || $amount === '') {
            return 0.0;
        }

        $amount = (string) $amount;
        $amount = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $amount);
        $amount = preg_replace('/[^0-9.\-]/', '', $amount) ?: '0';

        return round((float) $amount, 2);
    }

    public static function normalizeStatus(?string $status): string
    {
        $status = Str::lower(trim((string) $status));

        return match ($status) {
            'paid', 'paye', 'payé', 'success', 'successful', 'completed', 'complete',
            'validated', 'valide', 'validé', 'confirmed', 'confirme', 'confirmé' => self::STATUS_PAID,

            'escrow', 'escrow_held', 'held' => self::STATUS_ESCROW_HELD,
            'released', 'released_to_vendor', 'vendor_released' => self::STATUS_RELEASED_TO_VENDOR,

            'pending', 'waiting', 'en_attente', 'attente' => self::STATUS_PENDING,
            'processing', 'in_progress', 'en_cours' => self::STATUS_PROCESSING,
            'initiated', 'initie', 'initié' => self::STATUS_INITIATED,

            'failed', 'fail', 'error', 'erreur' => self::STATUS_FAILED,
            'cancelled', 'canceled', 'annule', 'annulé' => self::STATUS_CANCELLED,
            'expired', 'expire', 'expiré' => self::STATUS_EXPIRED,
            'refunded', 'refund', 'rembourse', 'remboursé' => self::STATUS_REFUNDED,
            'chargeback' => self::STATUS_CHARGEBACK,
            'disputed', 'litige' => self::STATUS_DISPUTED,

            default => $status !== '' ? $status : self::STATUS_PENDING,
        };
    }

    public static function normalizeMethod(?string $method): string
    {
        $method = Str::lower(trim((string) $method));
        $method = str_replace([' ', '-'], '_', $method);

        return match ($method) {
            'mtn', 'mtn_momo', 'mtn_money', 'mtn_mobile_money' => self::METHOD_MTN_MONEY,
            'orange', 'orange_money', 'om' => self::METHOD_ORANGE_MONEY,
            'moov', 'moov_money' => self::METHOD_MOOV_MONEY,
            'wave' => self::METHOD_WAVE,
            'mobile_money', 'momo' => self::METHOD_MOBILE_MONEY,

            'card', 'credit_card', 'debit_card', 'visa', 'mastercard' => self::METHOD_CARD,

            'cash', 'cod', 'cash_on_delivery', 'pay_on_delivery' => self::METHOD_CASH_ON_DELIVERY,
            'post_delivery' => self::METHOD_POST_DELIVERY,
            'bank_transfer', 'transfer', 'bank' => self::METHOD_BANK_TRANSFER,
            'cheque', 'check' => self::METHOD_CHEQUE,

            'manual', 'manual_proof', 'payment_proof', 'proof' => self::METHOD_MANUAL_PROOF,
            'offline', 'direct_payment', 'admin_validation' => self::METHOD_MANUAL_PROOF,

            'paydunya' => self::METHOD_PAYDUNYA,
            'cinetpay' => self::METHOD_CINETPAY,
            'fedapay' => self::METHOD_FEDAPAY,
            'flutterwave' => self::METHOD_FLUTTERWAVE,
            'paystack' => self::METHOD_PAYSTACK,
            'stripe' => self::METHOD_STRIPE,
            'paypal' => self::METHOD_PAYPAL,

            default => $method !== '' ? $method : self::METHOD_MANUAL_PROOF,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifications rapides
    |--------------------------------------------------------------------------
    */

    public function supportedMethods(): array
    {
        return array_values(array_unique(array_map(
            fn (string $method) => self::normalizeMethod($method),
            array_merge(
                self::MOBILE_MONEY_METHODS,
                self::CARD_METHODS,
                self::OFFLINE_METHODS,
                self::ONLINE_GATEWAY_METHODS
            )
        )));
    }

    public function statuses(): array
    {
        return array_values(array_unique(array_merge(
            self::PENDING_STATUSES,
            self::PAID_STATUSES,
            self::FAILED_STATUSES,
            [
                self::STATUS_REFUNDED,
                self::STATUS_CHARGEBACK,
                self::STATUS_DISPUTED,
            ]
        )));
    }

    public function ensureSupportedMethod(?string $method): string
    {
        $normalized = self::normalizeMethod($method);

        if (! in_array($normalized, $this->supportedMethods(), true)) {
            throw new \InvalidArgumentException("Unsupported payment method [{$method}].");
        }

        return $normalized;
    }

    public static function isPaidStatus(?string $status): bool
    {
        return in_array(self::normalizeStatus($status), self::PAID_STATUSES, true);
    }

    public static function isFailedStatus(?string $status): bool
    {
        return in_array(self::normalizeStatus($status), self::FAILED_STATUSES, true);
    }

    public static function isPendingStatus(?string $status): bool
    {
        return in_array(self::normalizeStatus($status), self::PENDING_STATUSES, true);
    }

    public static function isMobileMoneyMethod(?string $method): bool
    {
        return in_array(self::normalizeMethod($method), [
            self::METHOD_WAVE,
            self::METHOD_MTN_MONEY,
            self::METHOD_ORANGE_MONEY,
            self::METHOD_MOOV_MONEY,
            self::METHOD_MOBILE_MONEY,
        ], true);
    }

    public static function isGatewayMethod(?string $method): bool
    {
        return in_array(self::normalizeMethod($method), self::ONLINE_GATEWAY_METHODS, true);
    }

    public static function isCardMethod(?string $method): bool
    {
        return in_array(self::normalizeMethod($method), [self::METHOD_CARD], true)
            || in_array(Str::lower(trim((string) $method)), self::CARD_METHODS, true);
    }

    public static function isOfflineMethod(?string $method): bool
    {
        return in_array(self::normalizeMethod($method), [
            self::METHOD_POST_DELIVERY,
            self::METHOD_CASH_ON_DELIVERY,
            self::METHOD_BANK_TRANSFER,
            self::METHOD_CHEQUE,
            self::METHOD_MANUAL_PROOF,
        ], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Libellés
    |--------------------------------------------------------------------------
    */

    public static function formatXof(mixed $amount): string
    {
        return number_format(self::normalizeAmount($amount), 0, ',', ' ') . ' FCFA';
    }

    public static function getLabel(?string $status): string
    {
        return match (self::normalizeStatus($status)) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_WAITING => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_INITIATED => 'Initialisé',
            self::STATUS_PAID => 'Payé',
            self::STATUS_SUCCESS => 'Succès',
            self::STATUS_SUCCESSFUL => 'Succès',
            self::STATUS_COMPLETED => 'Complété',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_CONFIRMED => 'Confirmé',
            self::STATUS_ESCROW_HELD => 'En séquestre',
            self::STATUS_RELEASED_TO_VENDOR => 'Versé au vendeur',
            self::STATUS_FAILED => 'Échoué',
            self::STATUS_ERROR => 'Erreur',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_CANCELED => 'Annulé',
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_REFUNDED => 'Remboursé',
            self::STATUS_CHARGEBACK => 'Chargeback',
            self::STATUS_DISPUTED => 'Litige',
            default => ucfirst((string) $status),
        };
    }

    public static function getMethodLabel(?string $method): string
    {
        $raw = Str::lower(trim((string) $method));
        $normalized = self::normalizeMethod($method);

        return match ($normalized) {
            self::METHOD_WAVE => 'Wave',
            self::METHOD_MTN_MONEY => 'MTN Mobile Money',
            self::METHOD_ORANGE_MONEY => 'Orange Money',
            self::METHOD_MOOV_MONEY => 'Moov Money',
            self::METHOD_MOBILE_MONEY => 'Mobile Money',
            self::METHOD_CARD => match ($raw) {
                self::METHOD_VISA => 'Visa',
                self::METHOD_MASTERCARD => 'Mastercard',
                self::METHOD_CREDIT_CARD => 'Carte bancaire',
                self::METHOD_DEBIT_CARD => 'Carte bancaire',
                default => 'Carte bancaire',
            },
            self::METHOD_CASH_ON_DELIVERY => 'Paiement a la livraison',
            self::METHOD_POST_DELIVERY => 'Paiement apres livraison',
            self::METHOD_BANK_TRANSFER => 'Virement bancaire',
            self::METHOD_CHEQUE => 'Chèque',
            self::METHOD_MANUAL_PROOF => 'Preuve de paiement manuelle',
            self::METHOD_PAYDUNYA => 'PayDunya',
            self::METHOD_CINETPAY => 'CinetPay',
            self::METHOD_FEDAPAY => 'FedaPay',
            self::METHOD_FLUTTERWAVE => 'Flutterwave',
            self::METHOD_PAYSTACK => 'Paystack',
            self::METHOD_STRIPE => 'Stripe',
            self::METHOD_PAYPAL => 'PayPal',
            default => $method ? Str::headline((string) $method) : 'Paiement manuel',
        };
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match (true) {
            self::isPaidStatus($status) => 'success',
            self::isFailedStatus($status) => 'danger',
            self::isPendingStatus($status) => 'warning',
            self::normalizeStatus($status) === self::STATUS_REFUNDED => 'info',
            default => 'secondary',
        };
    }

    public static function methodIcon(?string $method): string
    {
        return match (self::normalizeMethod($method)) {
            self::METHOD_WAVE => 'wave',
            self::METHOD_MTN_MONEY => 'mtn',
            self::METHOD_ORANGE_MONEY => 'orange-money',
            self::METHOD_MOOV_MONEY => 'moov-money',
            self::METHOD_MOBILE_MONEY => 'mobile-money',
            self::METHOD_CARD => 'credit-card',
            self::METHOD_CASH_ON_DELIVERY, self::METHOD_POST_DELIVERY => 'truck',
            self::METHOD_BANK_TRANSFER => 'bank',
            self::METHOD_MANUAL_PROOF => 'file-check',
            self::METHOD_PAYDUNYA => 'paydunya',
            self::METHOD_CINETPAY => 'cinetpay',
            self::METHOD_FEDAPAY => 'fedapay',
            self::METHOD_FLUTTERWAVE => 'flutterwave',
            self::METHOD_PAYSTACK => 'paystack',
            self::METHOD_STRIPE => 'stripe',
            self::METHOD_PAYPAL => 'paypal',
            default => 'wallet',
        };
    }

    public function markAsPaid(\App\Models\Payment $payment, ?string $transactionId = null, array $payload = []): \App\Models\Payment
    {
        $providerPayload = array_merge($payment->provider_payload ?? [], [
            'confirmation_payload' => $payload,
            'confirmed_at' => now()->toDateTimeString(),
        ]);

        $payment->forceFill([
            'status' => self::STATUS_PAID,
            'transaction_id' => $transactionId ?: $payment->transaction_id,
            'provider_payload' => $providerPayload,
            'paid_at' => $payment->paid_at ?: now(),
        ])->save();

        $this->syncOrderPaymentState($payment, 'paid');

        return $payment->refresh();
    }

    public function holdEscrow(\App\Models\Payment $payment, ?string $transactionId = null, array $payload = []): \App\Models\Payment
    {
        $providerPayload = array_merge($payment->provider_payload ?? [], [
            'escrow_payload' => $payload,
            'escrow_held_at' => now()->toDateTimeString(),
        ]);

        $payment->forceFill([
            'status' => self::STATUS_ESCROW_HELD,
            'transaction_id' => $transactionId ?: $payment->transaction_id,
            'provider_payload' => $providerPayload,
            'paid_at' => $payment->paid_at ?: now(),
        ])->save();

        $this->syncOrderPaymentState($payment, 'paid');

        return $payment->refresh();
    }

    public function releaseToVendor(\App\Models\Payment $payment, ?string $reference = null): \App\Models\Payment
    {
        $providerPayload = array_merge($payment->provider_payload ?? [], [
            'release_reference' => $reference,
            'released_at' => now()->toDateTimeString(),
        ]);

        $payment->forceFill([
            'status' => self::STATUS_RELEASED_TO_VENDOR,
            'provider_payload' => $providerPayload,
            'released_at' => now(),
        ])->save();

        return $payment->refresh();
    }

    public function markAsRefunded(\App\Models\Payment $payment, ?string $reference = null, array $payload = []): \App\Models\Payment
    {
        $providerPayload = array_merge($payment->provider_payload ?? [], [
            'refund_reference' => $reference,
            'refund_payload' => $payload,
            'refunded_at' => now()->toDateTimeString(),
        ]);

        $payment->forceFill([
            'status' => self::STATUS_REFUNDED,
            'provider_payload' => $providerPayload,
        ])->save();

        return $payment->refresh();
    }

    public function shouldCreateVendorPayout(\App\Models\Payment $payment): bool
    {
        return in_array($payment->status, [self::STATUS_PAID, self::STATUS_ESCROW_HELD, self::STATUS_RELEASED_TO_VENDOR], true)
            && $payment->order_id !== null;
    }

    private function syncOrderPaymentState(\App\Models\Payment $payment, string $paymentStatus): void
    {
        $order = $payment->order;

        if (! $order) {
            return;
        }

        $order->forceFill([
            'payment_status' => $paymentStatus,
            'status' => in_array($order->status, ['pending', 'cancelled'], true) ? 'confirmed' : $order->status,
        ])->save();
    }
}
