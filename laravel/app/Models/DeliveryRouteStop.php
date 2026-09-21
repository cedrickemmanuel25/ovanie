<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRouteStop extends Model
{
    protected $fillable = [
        'delivery_route_id',
        'shipment_id',
        'type',
        'stop_order',
        'address',
        'latitude',
        'longitude',
        'estimated_arrival_at',
        'arrived_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'estimated_arrival_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
