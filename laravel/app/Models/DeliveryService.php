<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeliveryService extends Model
{
    public const PROVIDER_SELLER = 'seller';
    public const PROVIDER_OVANIE = 'ovanie';
    public const PROVIDER_PARTNER = 'partner';
    public const PROVIDER_PICKUP = 'pickup';

    protected $fillable = [
        'carrier_id',
        'provider_type',
        'code',
        'name',
        'description',
        'estimated_hours',
        'min_estimated_hours',
        'max_estimated_hours',
        'max_weight_kg',
        'max_volume_m3',
        'max_length_cm',
        'max_width_cm',
        'max_height_cm',
        'available_days',
        'start_time',
        'end_time',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'estimated_hours' => 'integer',
        'min_estimated_hours' => 'integer',
        'max_estimated_hours' => 'integer',
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'max_length_cm' => 'float',
        'max_width_cm' => 'float',
        'max_height_cm' => 'float',
        'available_days' => 'array',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function zones()
    {
        return $this->hasMany(DeliveryServiceZone::class);
    }

    public function rates()
    {
        return $this->hasMany(DeliveryServiceRate::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_delivery_service')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider_type', $provider);
    }

    public function scopeForSeller(Builder $query): Builder
    {
        return $query->forProvider(self::PROVIDER_SELLER);
    }

    public function scopeForOvanie(Builder $query): Builder
    {
        return $query->forProvider(self::PROVIDER_OVANIE);
    }

    public function getDelayLabelAttribute(): string
    {
        if ($this->estimated_hours < 24) {
            return $this->estimated_hours . 'h';
        }

        if ($this->estimated_hours % 24 === 0) {
            $days = (int) ($this->estimated_hours / 24);
            return $days === 1 ? '24h' : $days . ' jours';
        }

        return $this->estimated_hours . 'h';
    }
}
