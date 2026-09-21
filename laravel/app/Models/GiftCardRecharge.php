<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardRecharge extends Model
{
    protected $fillable = [
        'gift_card_id', 'user_id', 'payment_id', 'amount', 'status', 'provider_token', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
