<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReceptionForm extends Model
{
    protected $fillable = [
        'order_id',
        'client_id',
        'client_code',
        'client_name',
        'client_phone',
        'delivery_place',
        'reception_place',
        'status',
        'validated_at',
        'validated_city',
        'validated_date',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'validated_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function items()
    {
        return $this->hasMany(OrderReceptionFormItem::class);
    }
}