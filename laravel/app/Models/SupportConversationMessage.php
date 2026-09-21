<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversationMessage extends Model
{
    protected $fillable = [
        'support_conversation_id', 'sender_type', 'sender_user_id', 'ai_agent_id',
        'body', 'format', 'is_internal', 'confidence', 'provider',
        'provider_message_id', 'metadata',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'confidence' => 'float',
        'metadata' => 'array',
    ];

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function whatsappMessage() { return $this->hasOne(WhatsAppMessage::class, 'support_conversation_message_id'); }
}
