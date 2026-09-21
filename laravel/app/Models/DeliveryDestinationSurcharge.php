<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDestinationSurcharge extends Model
{
    protected $fillable = [
        'commune',
        'city',
        'delivery_zone',
        'surcharge_fee',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'surcharge_fee' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
