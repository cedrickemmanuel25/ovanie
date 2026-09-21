<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportCallbackRequest extends Model
{
    protected $fillable = [
        'reference', 'support_conversation_id', 'support_call_id', 'support_ticket_id',
        'requester_user_id', 'assigned_to', 'requester_name', 'phone', 'email',
        'reason', 'preferred_at', 'status', 'notes', 'completed_at',
    ];

    protected $casts = ['preferred_at' => 'datetime', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (SupportCallbackRequest $callback) {
            $callback->reference ??= 'RAP-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        });
    }

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function call() { return $this->belongsTo(SupportCall::class, 'support_call_id'); }
    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
