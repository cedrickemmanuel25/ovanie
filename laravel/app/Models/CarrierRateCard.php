<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierRateCard extends Model
{
    protected $fillable = [
        'carrier_id',
        'zone',
        'base_price',
        'price_per_km',
        'price_per_ton',
        'zone_surcharge',
        'urgency_surcharge',
        'fragile_surcharge',
        'unloading_surcharge',
        'estimated_delay_hours',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'float',
        'price_per_km' => 'float',
        'price_per_ton' => 'float',
        'zone_surcharge' => 'float',
        'urgency_surcharge' => 'float',
        'fragile_surcharge' => 'float',
        'unloading_surcharge' => 'float',
        'estimated_delay_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
