<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportCall extends Model
{
    protected $fillable = [
        'reference', 'support_conversation_id', 'support_ticket_id',
        'requester_user_id', 'ai_agent_id', 'handled_by', 'provider',
        'provider_call_id', 'direction', 'status', 'from_number', 'to_number',
        'started_at', 'answered_at', 'ended_at', 'duration_seconds',
        'recording_url', 'transcript', 'summary', 'missed_reason',
        'transfer_target', 'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportCall $call) {
            $call->reference ??= 'CALL-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
            $call->started_at ??= now();
        });
    }

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function events() { return $this->hasMany(SupportCallEvent::class); }
    public function handoffs() { return $this->hasMany(SupportAgentHandoff::class); }
    public function callbackRequests() { return $this->hasMany(SupportCallbackRequest::class); }
}
