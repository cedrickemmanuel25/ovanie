<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPayout extends Model
{
    public const STATUS_PENDING = 'pending';       // en attente d'envoi
    public const STATUS_PROCESSING = 'processing'; // virement en cours chez l'opérateur
    public const STATUS_PAID = 'paid';             // virement confirmé
    public const STATUS_FAILED = 'failed';         // virement refusé, nouvelle tentative possible
    public const STATUS_BLOCKED = 'blocked';       // compte Mobile Money manquant
    public const STATUS_KEPT_IN_CASH = 'kept_in_cash'; // net conservé sur les espèces encaissées

    protected $fillable = [
        'driver_id', 'mission_number', 'order_id', 'reference', 'gross_amount', 'commission_percent',
        'net_amount', 'cash_collected', 'cash_due', 'cash_settled_at', 'transfer_amount', 'status',
        'operator', 'phone', 'failure_reason', 'meta', 'paid_at',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'commission_percent' => 'float',
        'net_amount' => 'float',
        'cash_collected' => 'float',
        'cash_due' => 'float',
        'transfer_amount' => 'float',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'cash_settled_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Versé',
            self::STATUS_PROCESSING => 'Virement en cours',
            self::STATUS_BLOCKED => 'Compte Mobile Money à renseigner',
            self::STATUS_FAILED => 'Virement à relancer',
            self::STATUS_KEPT_IN_CASH => 'Conservé sur les espèces',
            default => 'En attente de virement',
        };
    }
}
