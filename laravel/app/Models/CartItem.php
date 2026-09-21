<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'original_product_id',
        'fulfillment_product_id',
        'original_shop_id',
        'fulfillment_shop_id',
        'optimization_applied',
        'optimization_savings',
        'optimization_meta',
        'price',
        'price_source',
        'negotiation_id',
        'quantity',
    ];

    protected $casts = [
        'optimization_applied' => 'boolean',
        'optimization_savings' => 'float',
        'optimization_meta' => 'array',
    ];

    /**
     * Un item appartient à un panier
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Un item appartient à un produit
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function originalProduct()
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function fulfillmentProduct()
    {
        return $this->belongsTo(Product::class, 'fulfillment_product_id');
    }

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }
}
