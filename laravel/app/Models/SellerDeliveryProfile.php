<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDeliveryProfile extends Model
{
    protected $fillable = [
        'shop_id',
        'is_enabled',
        'default_delay',
        'max_weight_kg',
        'max_volume_m3',
        'vehicle_types',
        'capacity_description',
        'conditions',
        'status',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'vehicle_types' => 'array',
        'validated_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function zones()
    {
        return $this->hasMany(SellerDeliveryZone::class);
    }

    public function capacities()
    {
        return $this->hasMany(SellerDeliveryCapacity::class);
    }
}
