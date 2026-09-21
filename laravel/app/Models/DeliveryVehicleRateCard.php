<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryVehicleRateCard extends Model
{
    protected $fillable = [
        'vehicle_code',
        'vehicle_label',
        'min_weight_kg',
        'max_weight_kg',
        'max_volume_m3',
        'base_fee',
        'price_per_km',
        'price_per_kg',
        'price_per_m3',
        'handling_fee',
        'unloading_fee',
        'fragile_fee',
        'urgent_fee',
        'traffic_surcharge_fee',
        'intra_commune_min_fee',
        'min_fee',
        'max_fee',
        'margin_type',
        'margin_value',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'min_weight_kg' => 'float',
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'base_fee' => 'float',
        'price_per_km' => 'float',
        'price_per_kg' => 'float',
        'price_per_m3' => 'float',
        'handling_fee' => 'float',
        'unloading_fee' => 'float',
        'fragile_fee' => 'float',
        'urgent_fee' => 'float',
        'traffic_surcharge_fee' => 'float',
        'intra_commune_min_fee' => 'float',
        'min_fee' => 'float',
        'max_fee' => 'float',
        'margin_value' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
