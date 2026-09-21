<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'carrier_id',
        'shop_id',
        'delivery_service_id',
        'order_delivery_selection_id',
        'provider_type',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'distance_km',
        'duration_minutes',
        'routing_provider',
        'traffic_delay_minutes',
        'no_traffic_duration_minutes',
        'routed_at',
        'route_geometry',
        'vehicle_code',
        'vehicle_label',
        'total_weight_kg',
        'total_volume_m3',
        'internal_carrier_type',
        'internal_carrier_name',
        'waze_url',
        'optimized_route_id',
        'service_code',
        'tracking_number',
        'status',
        'estimated_delivery_at',
        'delivered_at',
        'final_price',
        'meta',
    ];

    protected $casts = [
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'final_price' => 'float',
        'pickup_latitude' => 'float',
        'pickup_longitude' => 'float',
        'delivery_latitude' => 'float',
        'delivery_longitude' => 'float',
        'distance_km' => 'float',
        'duration_minutes' => 'integer',
        'traffic_delay_minutes' => 'integer',
        'no_traffic_duration_minutes' => 'integer',
        'routed_at' => 'datetime',
        'total_weight_kg' => 'float',
        'total_volume_m3' => 'float',
        'meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }


    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }


    public function deliverySelection()
    {
        return $this->belongsTo(OrderDeliverySelection::class, 'order_delivery_selection_id');
    }

    public function deliveryService()    {
        return $this->belongsTo(DeliveryService::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    public function driverLocations()
    {
        return $this->hasMany(DriverLocation::class);
    }

    public function latestDriverLocation()
    {
        return $this->hasOne(DriverLocation::class)->latestOfMany('recorded_at');
    }

    public function deliveryAssignment()
    {
        return $this->hasOne(DeliveryAssignment::class, 'order_item_id', 'order_item_id')->latestOfMany();
    }

    public function proofs()
    {
        return $this->hasMany(DeliveryProof::class, 'order_item_id', 'order_item_id');
    }
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportConversations()
    {
        return $this->hasMany(SupportConversation::class);
    }

}
