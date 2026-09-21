<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDeliveryCapacity extends Model
{
    protected $fillable = [
        'shop_id',
        'seller_delivery_profile_id',
        'vehicle_type',
        'max_weight_kg',
        'max_volume_m3',
        'max_orders_per_day',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'max_orders_per_day' => 'integer',
        'is_active' => 'boolean',
    ];
}
