<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopConfigurationAssignment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shop_id', 'commercial_id', 'assigned_by_admin_id',
        'configure_logistics', 'configure_payout', 'manage_products',
        'status', 'assigned_at', 'started_at', 'logistics_completed_at',
        'payout_completed_at', 'products_completed_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'configure_logistics' => 'boolean',
        'configure_payout' => 'boolean',
        'manage_products' => 'boolean',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'logistics_completed_at' => 'datetime',
        'payout_completed_at' => 'datetime',
        'products_completed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function shop() { return $this->belongsTo(Shop::class); }
    public function commercial() { return $this->belongsTo(User::class, 'commercial_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by_admin_id'); }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    public function requiredConfigurationComplete(): bool
    {
        return (! $this->configure_logistics || $this->logistics_completed_at !== null)
            && (! $this->configure_payout || $this->payout_completed_at !== null)
            && (! $this->manage_products || $this->products_completed_at !== null);
    }
}
