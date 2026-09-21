<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDistanceMatrix extends Model
{
    protected $table = 'delivery_distance_matrix';

    protected $fillable = [
        'origin_commune',
        'destination_commune',
        'distance_km',
        'estimated_duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'distance_km' => 'float',
        'estimated_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];
}
