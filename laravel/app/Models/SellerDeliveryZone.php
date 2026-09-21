<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDeliveryZone extends Model
{
    protected $fillable = [
        'shop_id',
        'seller_delivery_profile_id',
        'city',
        'commune',
        'commune_id',
        'district',
        'coverage_type',
        'vehicle_code',
        'delivery_price',
        'estimated_delay',
        'max_weight_kg',
        'max_volume_m3',
        'is_active',
    ];

    protected $casts = [
        'delivery_price' => 'float',
        'max_weight_kg' => 'float',
        'max_volume_m3' => 'float',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function profile()
    {
        return $this->belongsTo(SellerDeliveryProfile::class, 'seller_delivery_profile_id');
    }

    public function communeRegistry()
    {
        return $this->belongsTo(AbidjanCommune::class, 'commune_id');
    }
}
