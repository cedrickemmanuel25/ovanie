<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardPurchase extends Model
{
    protected $fillable = [
        'gift_card_product_id', 'buyer_user_id', 'gift_card_id', 'payment_id',
        'recipient_name', 'recipient_email', 'recipient_phone', 'personal_message',
        'amount', 'status', 'payment_method', 'provider_token', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(GiftCardProduct::class, 'gift_card_product_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
