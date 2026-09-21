<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingGrid extends Model
{
    protected $fillable = [
        'name', 'vehicle_code', 'vehicle_label', 'region', 'zone_count', 'description',
        'base_fee', 'price_per_km', 'price_per_kg', 'price_per_m3', 'max_weight_kg',
        'max_volume_m3', 'surcharges', 'rules', 'is_active', 'meta',
    ];

    protected $casts = [
        'zone_count' => 'integer',
        'base_fee' => 'float',
        'price_per_km' => 'float',
        'price_per_kg' => 'float',
        'price_per_m3' => 'float',
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'surcharges' => 'array',
        'rules' => 'array',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
