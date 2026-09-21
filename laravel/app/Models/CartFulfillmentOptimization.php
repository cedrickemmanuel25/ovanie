<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartFulfillmentOptimization extends Model
{
    protected $fillable = [
        'cart_id',
        'order_id',
        'cart_item_id',
        'original_product_id',
        'fulfillment_product_id',
        'original_shop_id',
        'fulfillment_shop_id',
        'original_delivery_fee',
        'optimized_delivery_fee',
        'savings',
        'reason',
        'meta',
    ];

    protected $casts = [
        'original_delivery_fee' => 'float',
        'optimized_delivery_fee' => 'float',
        'savings' => 'float',
        'meta' => 'array',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function originalProduct()
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function fulfillmentProduct()
    {
        return $this->belongsTo(Product::class, 'fulfillment_product_id');
    }

    public function originalShop()
    {
        return $this->belongsTo(Shop::class, 'original_shop_id');
    }

    public function fulfillmentShop()
    {
        return $this->belongsTo(Shop::class, 'fulfillment_shop_id');
    }
}
