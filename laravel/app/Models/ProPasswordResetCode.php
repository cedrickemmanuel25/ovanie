<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProPasswordResetCode extends Model
{
    protected $fillable = [
        'pro_user_id',
        'challenge_hash',
        'code_hash',
        'channel',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(ProUser::class, 'pro_user_id');
    }

    public function isExpired(): bool
    {
        return (bool) $this->used_at || ! $this->expires_at || $this->expires_at->isPast() || $this->attempts >= 5;
    }
}
