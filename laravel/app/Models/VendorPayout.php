<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayout extends Model
{
    protected $fillable = [
        'vendor_id',
        'shop_id',
        'order_id',
        'product_amount',
        'seller_delivery_amount',
        'ovanie_delivery_amount',
        'net_product_amount',
        'total_amount',
        'commission_amount',
        'processing_fee_amount',
        'payout_amount',
        'phone',
        'payment_method',
        'payment_mode_snapshot',
        'payment_channel',
        'payout_reference',
        'batch_reference',
        'payout_period_key',
        'status',
        'approved_at',
        'processing_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'eligible_at',
        'scheduled_for',
        'expected_payment_date',
        'vendor_followup_requested_at',
        'vendor_note',
        'admin_note',
        'transfer_receipt_path',
        'meta',
    ];

    protected $casts = [
        'product_amount' => 'decimal:2',
        'seller_delivery_amount' => 'decimal:2',
        'ovanie_delivery_amount' => 'decimal:2',
        'net_product_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'processing_fee_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'eligible_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'expected_payment_date' => 'date',
        'vendor_followup_requested_at' => 'datetime',
        'meta' => 'array',
    ];

    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_WAITING_PAYMENT = 'waiting_payment';
    public const STATUS_WAITING_RECEPTION = 'waiting_reception';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Programmé',
            self::STATUS_APPROVED => 'Prêt à payer',
            self::STATUS_PROCESSING => 'En traitement',
            self::STATUS_PAID => 'Payé',
            self::STATUS_FAILED => 'Échec',
            self::STATUS_CANCELLED => 'Annulé',
            // "Bloqué" / "en litige" donnait l'impression d'un incident alors
            // qu'il s'agit dans l'immense majorité des cas d'un état normal :
            // la commande n'est simplement pas encore livrée. Le motif exact
            // reste disponible via blocked_reason pour les cas réellement
            // litigieux (retour, réclamation).
            self::STATUS_BLOCKED => $this->blockedReasonLabel(),
            self::STATUS_WAITING_PAYMENT => 'En attente paiement client',
            self::STATUS_WAITING_RECEPTION => 'En attente réception client',
            default => 'Inconnu',
        };
    }

    private function blockedReasonLabel(): string
    {
        $reason = (string) data_get($this->meta, 'blocked_reason', '');

        return str_contains($reason, 'retour')
            || str_contains($reason, 'réclamation')
            || str_contains($reason, 'contrôle')
            ? 'En vérification'
            : 'En attente de livraison';
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'green',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_PROCESSING => 'purple',
            self::STATUS_FAILED, self::STATUS_CANCELLED => 'red',
            self::STATUS_BLOCKED => str_contains((string) data_get($this->meta, 'blocked_reason', ''), 'retour')
                || str_contains((string) data_get($this->meta, 'blocked_reason', ''), 'réclamation')
                || str_contains((string) data_get($this->meta, 'blocked_reason', ''), 'contrôle')
                ? 'red'
                : 'orange',
            default => 'orange',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        $method = $this->payment_method ?: $this->payment_channel ?: 'mobile_money';

        return match ($method) {
            'orange', 'orange_money' => 'Orange Money',
            'mtn', 'mtn_money' => 'MTN Mobile Money',
            'moov', 'moov_money' => 'Moov Money',
            'wave' => 'Wave',
            'bank', 'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            'paydunya_payout', 'vendor_payout', 'mobile_money' => 'Mobile Money vendeur',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return $this->payment_mode_snapshot === Shop::PAYMENT_POST_DELIVERY
            ? 'Paiement après livraison (72 h)'
            : 'Paiement hebdomadaire';
    }

    public function getNetRatioAttribute(): float
    {
        if ((float) $this->total_amount <= 0) {
            return 0;
        }

        return round(((float) $this->payout_amount / (float) $this->total_amount) * 100, 2);
    }
}
