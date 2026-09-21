<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryServiceZone extends Model
{
    protected $fillable = [
        'delivery_service_id',
        'country',
        'region',
        'delivery_zone',
        'city',
        'commune',
        'district',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(DeliveryService::class, 'delivery_service_id');
    }
}
