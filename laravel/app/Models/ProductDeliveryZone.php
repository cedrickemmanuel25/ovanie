<?php
// app/Models/ProductDeliveryZone.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDeliveryZone extends Model
{
    protected $fillable = [
        'product_id',
        'city',
        'district',
        'delivery_price',
        'estimated_delay',
        'is_active',
    ];

    protected $casts = [
        'delivery_price' => 'float',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}