<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPricingSpecialZone extends Model
{
    protected $fillable = ['name', 'type', 'surcharge_fee', 'is_active', 'meta'];

    protected $casts = [
        'surcharge_fee' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}
