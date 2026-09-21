<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportConversation extends Model
{
    protected $fillable = [
        'public_token', 'requester_user_id', 'requester_name', 'requester_email',
        'requester_phone', 'requester_match_method', 'requester_matched_at',
        'channel', 'status', 'ai_agent_id', 'assigned_to', 'support_ticket_id',
        'order_id', 'shop_id', 'payment_id', 'shipment_id', 'return_id',
        'dispute_id', 'delivery_incident_id', 'subject', 'summary', 'sentiment',
        'priority', 'ai_confidence', 'requires_human', 'last_message_at',
        'linked_at', 'resolved_at', 'closed_at', 'metadata',
    ];

    protected $casts = [
        'ai_confidence' => 'float',
        'requires_human' => 'boolean',
        'requester_matched_at' => 'datetime',
        'last_message_at' => 'datetime',
        'linked_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportConversation $conversation) {
            $conversation->public_token ??= (string) Str::uuid();
            $conversation->last_message_at ??= now();
        });
    }

    public function requester() { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function returnRequest() { return $this->belongsTo(ReturnModel::class, 'return_id'); }
    public function dispute() { return $this->belongsTo(Dispute::class); }
    public function deliveryIncident() { return $this->belongsTo(DeliveryIncident::class); }
    public function commercialLead() { return $this->hasOne(CommercialLead::class, 'support_conversation_id'); }
    public function messages() { return $this->hasMany(SupportConversationMessage::class)->oldest(); }
    public function calls() { return $this->hasMany(SupportCall::class); }
    public function handoffs() { return $this->hasMany(SupportAgentHandoff::class); }
    public function callbackRequests() { return $this->hasMany(SupportCallbackRequest::class); }
    public function auditLogs() { return $this->hasMany(SupportAiAuditLog::class); }
    public function whatsappMessages() { return $this->hasMany(WhatsAppMessage::class); }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'waiting_human', 'human']);
    }
}
