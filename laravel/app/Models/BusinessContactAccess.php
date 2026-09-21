<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BusinessContactAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'payment_id',
        'business_request_type',
        'business_request_id',
        'granted_at',
        'expires_at',
        'access_count',
        'last_accessed_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'access_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function consumeFor(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $access = self::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if (! $access
                || (int) $access->user_id !== (int) $user->id
                || $access->revoked_at !== null
                || ($access->expires_at !== null && $access->expires_at->isPast())) {
                return false;
            }

            $access->forceFill([
                'access_count' => $access->access_count + 1,
                'last_accessed_at' => now(),
            ])->save();

            $this->setRawAttributes($access->getAttributes(), true);

            return true;
        }, 3);
    }
}
