<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryServiceRate extends Model
{
    protected $fillable = [
        'delivery_service_id',
        'delivery_service_zone_id',
        'delivery_zone',
        'city',
        'commune',
        'base_fee',
        'price_per_kg',
        'price_per_m3',
        'price_per_km',
        'fragile_fee',
        'unloading_fee',
        'urgent_fee',
        'min_fee',
        'max_fee',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'base_fee' => 'float',
        'price_per_kg' => 'float',
        'price_per_m3' => 'float',
        'price_per_km' => 'float',
        'fragile_fee' => 'float',
        'unloading_fee' => 'float',
        'urgent_fee' => 'float',
        'min_fee' => 'float',
        'max_fee' => 'float',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
    }

    public function zone()
    {
        return $this->belongsTo(DeliveryServiceZone::class, 'delivery_service_zone_id');
    }
}
