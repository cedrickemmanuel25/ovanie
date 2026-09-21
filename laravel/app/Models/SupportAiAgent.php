<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAiAgent extends Model
{
    protected $fillable = [
        'code', 'name', 'slug', 'role_key', 'description', 'personality',
        'system_prompt', 'channels', 'routing_keywords', 'capabilities',
        'status', 'voice_name', 'is_default', 'created_by',
    ];

    protected $casts = [
        'channels' => 'array',
        'routing_keywords' => 'array',
        'capabilities' => 'array',
        'is_default' => 'boolean',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function conversations() { return $this->hasMany(SupportConversation::class, 'ai_agent_id'); }
    public function messages() { return $this->hasMany(SupportConversationMessage::class, 'ai_agent_id'); }
    public function calls() { return $this->hasMany(SupportCall::class, 'ai_agent_id'); }
    public function handoffs() { return $this->hasMany(SupportAgentHandoff::class, 'ai_agent_id'); }
    public function tickets() { return $this->hasMany(SupportTicket::class, 'ai_agent_id'); }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function supportsChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }
}
