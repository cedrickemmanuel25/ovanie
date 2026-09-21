<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryQuote extends Model
{
    protected $fillable = [
        'carrier_id',
        'order_id',
        'origin_city',
        'destination_city',
        'zone',
        'distance_km',
        'weight_ton',
        'is_urgent',
        'is_fragile',
        'requires_unloading',
        'estimated_delay_hours',
        'final_price',
        'meta',
    ];

    protected $casts = [
        'distance_km' => 'float',
        'weight_ton' => 'float',
        'is_urgent' => 'boolean',
        'is_fragile' => 'boolean',
        'requires_unloading' => 'boolean',
        'estimated_delay_hours' => 'integer',
        'final_price' => 'float',
        'meta' => 'array',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
