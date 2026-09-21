<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReceptionFormItem extends Model
{
    protected $fillable = [
        'order_reception_form_id',
        'order_item_id',
        'product_name',
        'shop_name',
        'courier_name',
        'courier_id_number',
        'courier_id_front_path',
        'courier_id_back_path',
        'quantity',
        'unit_price',
        'amount',
        'site_commission',
        'vendor_gain',
        'received',
        'received_date',
        'received_time',
        'payment_status',
        'paid_at',
        'payment_reference',
    ];

    protected $casts = [
        'received' => 'boolean',
        'received_date' => 'date',
        'paid_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'site_commission' => 'decimal:2',
        'vendor_gain' => 'decimal:2',
    ];

    public function form()
    {
        return $this->belongsTo(OrderReceptionForm::class, 'order_reception_form_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function getCourierFrontUrlAttribute(): ?string
    {
        return $this->courier_id_front_path ? route('admin.private-documents.reception', [$this, 'front']) : null;
    }

    public function getCourierBackUrlAttribute(): ?string
    {
        return $this->courier_id_back_path ? route('admin.private-documents.reception', [$this, 'back']) : null;
    }
}
