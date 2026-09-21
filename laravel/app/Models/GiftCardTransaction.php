<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardTransaction extends Model
{
    protected $fillable = [
        'gift_card_id', 'user_id', 'order_id', 'type', 'amount',
        'balance_before', 'balance_after', 'reserved_before', 'reserved_after',
        'reference', 'description', 'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'reserved_before' => 'decimal:2',
        'reserved_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
