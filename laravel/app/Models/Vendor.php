<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'shop_name',
        'contact_email',
        'description',
        'is_active',
        'vendor_name',
        'status',
        'user_id',
    ];

    /**
     * Vendor appartient à un utilisateur (propriétaire)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Modèle legacy : la boutique vendeuse réelle est portée par shops.user_id.
     */
    public function shop()
    {
        return $this->hasOne(Shop::class, 'user_id', 'user_id');
    }

    /**
     * Produits de la boutique liée au vendeur legacy.
     */
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Shop::class,
            'user_id',
            'shop_id',
            'user_id',
            'id'
        );
    }

    /**
     * Commandes de la boutique liée au vendeur legacy.
     */
    public function orders()
    {
        return $this->hasManyThrough(
            Order::class,
            Shop::class,
            'user_id',
            'shop_id',
            'user_id',
            'id'
        );
    }
}
