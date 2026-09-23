<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $fillable = [
        'reference', 'support_requester_id', 'requester_user_id', 'requester_name', 'requester_email',
        'requester_phone', 'channel', 'source_app', 'category', 'priority', 'status', 'team',
        'subject', 'description', 'assigned_to', 'created_by', 'ai_agent_id', 'created_by_ai', 'escalation_level',
        'sla_due_at', 'first_response_at', 'resolved_at', 'closed_at', 'order_id',
        'shop_id', 'payment_id', 'shipment_id', 'return_id', 'dispute_id',
        'delivery_incident_id', 'submission_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'escalation_level' => 'integer',
        'created_by_ai' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->reference ??= 'SUP-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
            $ticket->sla_due_at ??= match ($ticket->priority) {
                'urgent' => now()->addHours(2),
                'high' => now()->addHours(6),
                'low' => now()->addDays(3),
                default => now()->addDay(),
            };
        });


        static::saving(function (SupportTicket $ticket) {
            if ($ticket->status === 'resolved' && ! $ticket->resolved_at) {
                $ticket->resolved_at = now();
            }
            if ($ticket->status === 'closed' && ! $ticket->closed_at) {
                $ticket->closed_at = now();
            }
        });
    }

    public function requester() { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function requesterProfile() { return $this->belongsTo(SupportRequester::class, 'support_requester_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function conversation() { return $this->hasOne(SupportConversation::class, 'support_ticket_id'); }
    public function conversations() { return $this->hasMany(SupportConversation::class, 'support_ticket_id'); }
    public function handoffs() { return $this->hasMany(SupportAgentHandoff::class, 'support_ticket_id'); }
    public function contextLinks() { return $this->hasMany(SupportContextLink::class, 'support_ticket_id'); }
    public function messages() { return $this->hasMany(SupportTicketMessage::class)->oldest(); }
    public function order() { return $this->belongsTo(Order::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function returnRequest() { return $this->belongsTo(ReturnModel::class, 'return_id'); }
    public function dispute() { return $this->belongsTo(Dispute::class); }
    public function deliveryIncident() { return $this->belongsTo(DeliveryIncident::class); }
    public function submission() { return $this->belongsTo(Submission::class); }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed', 'cancelled']);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->sla_due_at?->isPast() && ! in_array($this->status, ['resolved', 'closed', 'cancelled'], true);
    }
}
