<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingMatrix extends Model
{
    protected $fillable = [
        'region', 'origin_commune', 'destination_commune', 'vehicle_prices',
        'estimated_delays', 'rules', 'description', 'is_active', 'meta',
    ];

    protected $casts = [
        'vehicle_prices' => 'array',
        'estimated_delays' => 'array',
        'rules' => 'array',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
