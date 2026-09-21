<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierVehicle extends Model
{
    protected $fillable = [
        'carrier_id',
        'type',
        'plate_number',
        'capacity_ton',
        'volume_m3',
        'is_active',
    ];

    protected $casts = [
        'capacity_ton' => 'float',
        'volume_m3' => 'float',
        'is_active' => 'boolean',
    ];

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
