<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardProduct extends Model
{
    protected $fillable = [
        'name', 'slug', 'family', 'face_value', 'activation_price', 'initial_balance',
        'validity_days', 'validity_months', 'is_rechargeable', 'max_total_recharge',
        'image_path', 'description', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'face_value' => 'decimal:2',
        'activation_price' => 'decimal:2',
        'initial_balance' => 'decimal:2',
        'max_total_recharge' => 'decimal:2',
        'is_rechargeable' => 'boolean',
        'is_active' => 'boolean',
        'validity_days' => 'integer',
        'validity_months' => 'integer',
        'sort_order' => 'integer',
    ];

    public function cards()
    {
        return $this->hasMany(GiftCard::class);
    }

    public function purchases()
    {
        return $this->hasMany(GiftCardPurchase::class);
    }
}
