<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'delivery_provider',
        'driver_id',
        'receiver_name',
        'receiver_phone',
        'proof_photo',
        'signature_path',
        'delivery_note',
        'delivered_at',
        'meta',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }
}
