<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTourStop extends Model
{
    protected $fillable = [
        'delivery_tour_id',
        'order_item_id',
        'sequence',
        'commune',
        'status',
        'completed_at',
        'stop_key',
        'stop_type',
        'label',
        'address',
        'latitude',
        'longitude',
        'distance_from_previous_km',
        'duration_from_previous_minutes',
        'estimated_arrival_at',
        'route_segment_geometry',
        'routing_provider',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_from_previous_km' => 'float',
        'duration_from_previous_minutes' => 'integer',
        'estimated_arrival_at' => 'datetime',
    ];

    public function tour()
    {
        return $this->belongsTo(DeliveryTour::class, 'delivery_tour_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
