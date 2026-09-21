<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRouteCache extends Model
{
    protected $table = 'delivery_route_cache';

    protected $fillable = [
        'provider',
        'origin_lat',
        'origin_lng',
        'destination_lat',
        'destination_lng',
        'distance_km',
        'duration_minutes',
        'traffic_delay_minutes',
        'no_traffic_duration_minutes',
        'route_geometry',
        'raw_response',
        'expires_at',
    ];

    protected $casts = [
        'origin_lat' => 'float',
        'origin_lng' => 'float',
        'destination_lat' => 'float',
        'destination_lng' => 'float',
        'distance_km' => 'float',
        'duration_minutes' => 'integer',
        'traffic_delay_minutes' => 'integer',
        'no_traffic_duration_minutes' => 'integer',
        'route_geometry' => 'array',
        'raw_response' => 'array',
        'expires_at' => 'datetime',
    ];
}
