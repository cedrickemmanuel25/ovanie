<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id'];

    /**
     * Un panier appartient à un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un panier contient plusieurs items
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
