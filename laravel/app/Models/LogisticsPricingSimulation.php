<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingSimulation extends Model
{
    protected $fillable = [
        'user_id', 'origin_commune_id', 'destination_commune_id',
        'origin_label', 'destination_label', 'vehicle_code',
        'weight_kg', 'volume_m3', 'distance_km', 'duration_minutes',
        'price_total', 'pricing_source', 'components', 'options',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'volume_m3' => 'float',
        'distance_km' => 'float',
        'duration_minutes' => 'integer',
        'price_total' => 'float',
        'components' => 'array',
        'options' => 'array',
    ];
}
