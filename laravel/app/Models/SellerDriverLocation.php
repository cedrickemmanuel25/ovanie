<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerDriverLocation extends Model
{
    protected $fillable = [
        'tracking_session_id',
        'shop_id',
        'order_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'battery_level',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'battery_level' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(SellerDeliveryTrackingSession::class, 'tracking_session_id');
    }
}
