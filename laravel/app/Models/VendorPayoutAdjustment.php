<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayoutAdjustment extends Model
{
    protected $fillable = [
        'vendor_payout_id',
        'order_id',
        'order_item_id',
        'return_id',
        'shop_id',
        'amount',
        'type',
        'status',
        'reference',
        'note',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function payout()
    {
        return $this->belongsTo(VendorPayout::class, 'vendor_payout_id');
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnModel::class, 'return_id');
    }
}
