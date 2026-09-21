<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GiftCard extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'gift_card_product_id', 'purchaser_user_id', 'owner_user_id',
        'beneficiary_name', 'beneficiary_email', 'beneficiary_phone',
        'code', 'pin_hash', 'pin_encrypted', 'initial_balance', 'current_balance',
        'reserved_balance', 'total_recharged', 'currency', 'status',
        'activated_at', 'expires_at', 'last_used_at', 'meta',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
        'total_recharged' => 'decimal:2',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $hidden = ['pin_hash', 'pin_encrypted'];

    public function product()
    {
        return $this->belongsTo(GiftCardProduct::class, 'gift_card_product_id');
    }

    public function purchaser()
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    public function recharges()
    {
        return $this->hasMany(GiftCardRecharge::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'gift_card_id');
    }

    public function availableBalance(): float
    {
        return max(0, round((float) $this->current_balance - (float) $this->reserved_balance, 2));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->isExpired()
            && $this->availableBalance() > 0;
    }

    public function plainPin(): ?string
    {
        if (! $this->pin_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->pin_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
