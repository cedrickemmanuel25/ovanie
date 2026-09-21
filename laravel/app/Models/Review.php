<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'vendor_replied_at' => 'datetime',
    ];

    // Un avis appartient à un produit
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Un avis appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id', 'user_id')
            ->whereColumn('orders.product_id', 'reviews.product_id')
            ->where('orders.payment_status', 'paid');
    }
}
