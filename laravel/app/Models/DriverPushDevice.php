<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPushDevice extends Model
{
    protected $fillable = [
        'driver_id', 'token', 'platform', 'device_name', 'app_version', 'locale',
        'is_active', 'last_seen_at', 'last_error_at', 'last_error_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }
}
