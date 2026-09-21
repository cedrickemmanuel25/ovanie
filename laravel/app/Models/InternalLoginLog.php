<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalLoginLog extends Model
{
    public const EVENT_LOGIN_SUCCESS = 'login_success';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_LOGOUT = 'logout';

    protected $fillable = [
        'user_id',
        'event',
        'attempted_email',
        'role',
        'ip_address',
        'user_agent',
        'session_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
