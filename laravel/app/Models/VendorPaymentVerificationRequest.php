<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPaymentVerificationRequest extends Model
{
    protected $fillable = [
        'order_id',
        'shop_id',
        'vendor_id',
        'requested_by',
        'reviewed_by',
        'payment_method',
        'amount_claimed',
        'payment_reference',
        'vendor_note',
        'admin_note',
        'status',
        'requested_at',
        'reviewed_at',
    ];

    protected $casts = [
        'amount_claimed' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
