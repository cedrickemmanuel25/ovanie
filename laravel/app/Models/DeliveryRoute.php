<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRoute extends Model
{
    protected $fillable = [
        'driver_id',
        'status',
        'total_distance_km',
        'total_duration_minutes',
        'route_geometry',
        'optimized_payload',
        'optimized_response',
        'created_by',
    ];

    protected $casts = [
        'total_distance_km' => 'float',
        'optimized_payload' => 'array',
        'optimized_response' => 'array',
    ];

    public function stops()
    {
        return $this->hasMany(DeliveryRouteStop::class)->orderBy('stop_order');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }
}
