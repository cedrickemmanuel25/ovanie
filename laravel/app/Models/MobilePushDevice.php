<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePushDevice extends Model
{
    protected $fillable = [
        'user_id', 'token', 'platform', 'device_name', 'app_version', 'locale',
        'is_active', 'last_seen_at', 'last_error_at', 'last_error_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
