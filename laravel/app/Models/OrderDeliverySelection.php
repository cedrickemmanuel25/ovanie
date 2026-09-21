<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDeliverySelection extends Model
{
    protected $fillable = [
        'order_id',
        'shop_id',
        'delivery_service_id',
        'carrier_id',
        'service_code',
        'service_name',
        'provider_type',
        'estimated_hours',
        'delivery_fee',
        'delivery_zone',
        'delivery_city',
        'delivery_commune',
        'meta',
    ];

    protected $casts = [
        'estimated_hours' => 'integer',
        'delivery_fee' => 'float',
        'meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function service()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
