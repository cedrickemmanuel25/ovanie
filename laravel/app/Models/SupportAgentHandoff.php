<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportAgentHandoff extends Model
{
    protected $fillable = [
        'reference', 'support_conversation_id', 'support_call_id', 'support_ticket_id',
        'delivery_incident_id', 'commercial_lead_id', 'ai_agent_id',
        'requested_by_user_id', 'requested_by_type', 'assigned_to',
        'target_department', 'queue_key', 'idempotency_key', 'severity', 'status',
        'reason', 'notes', 'requested_at', 'assigned_at', 'due_at', 'accepted_at',
        'resolved_at', 'completed_by', 'resolution_code', 'lock_version',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'accepted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportAgentHandoff $handoff): void {
            $handoff->reference ??= 'TRF-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $handoff->requested_at ??= now();
        });
    }

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function call() { return $this->belongsTo(SupportCall::class, 'support_call_id'); }
    public function ticket() { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function deliveryIncident() { return $this->belongsTo(DeliveryIncident::class); }
    public function commercialLead() { return $this->belongsTo(CommercialLead::class); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }

    /**
     * Transferts logistiques réellement créés par le workflow Support.
     *
     * Les anciennes données de démonstration utilisent la file `delivery_support`
     * et des références SUP-xxxx. Le workflow réel passe par
     * SupportHandoffQueueService et produit une clé d'idempotence ainsi qu'une
     * file `logistique_incidents` ou `logistique_critique`.
     */

    /**
     * Transferts créés par les workflows actuels. Les files delivery_support
     * et delivery_support_archive proviennent de l'ancien seeder de maquette.
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->where(function ($builder) {
            $builder->whereNull('queue_key')
                ->orWhereNotIn('queue_key', ['delivery_support', 'delivery_support_archive']);
        });
    }

    public function scopeOperationalLogistics(Builder $query): Builder
    {
        return $query
            ->where('target_department', 'logistique')
            ->whereIn('queue_key', ['logistique_incidents', 'logistique_critique'])
            ->whereNotNull('idempotency_key');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'accepted', 'in_progress']);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at?->isPast() && in_array($this->status, ['pending', 'assigned', 'accepted', 'in_progress'], true);
    }
}
