<?php

namespace App\Services\SupportAi;

use App\Models\SupportContextLink;
use App\Models\SupportConversation;
use App\Models\SupportTicket;

class SupportTicketFactory
{
    public function fromConversation(
        SupportConversation $conversation,
        string $reason,
        string $priority = 'normal',
        array $overrides = [],
    ): SupportTicket {
        if ($conversation->ticket) {
            $ticket = $conversation->ticket;
            $changes = array_filter([
                'delivery_incident_id' => $overrides['delivery_incident_id'] ?? $conversation->delivery_incident_id,
                'assigned_to' => $overrides['assigned_to'] ?? null,
                'team' => $overrides['team'] ?? null,
            ], fn ($value) => $value !== null);
            if ($changes !== []) {
                $ticket->forceFill($changes)->save();
            }
            return $ticket->fresh();
        }

        $ticket = SupportTicket::create(array_merge([
            'support_requester_id' => $conversation->support_requester_id,
            'requester_user_id' => $conversation->requester_user_id,
            'requester_name' => $conversation->requester_name,
            'requester_email' => $conversation->requester_email,
            'requester_phone' => $conversation->requester_phone,
            'channel' => $conversation->channel === 'phone' ? 'ai_call' : 'ai_chat',
            'source_app' => $conversation->source_app ?: 'support_ai',
            'category' => $conversation->aiAgent?->role_key === 'business' ? 'business' : 'general',
            'priority' => $priority,
            'status' => 'open',
            'team' => 'support',
            'subject' => $conversation->subject ?: 'Demande transmise par '.$conversation->aiAgent?->name,
            'description' => $reason,
            'assigned_to' => $conversation->assigned_to,
            'created_by' => null,
            'ai_agent_id' => $conversation->ai_agent_id,
            'created_by_ai' => true,
            'escalation_level' => $conversation->requires_human ? 1 : 0,
            'order_id' => $conversation->order_id,
            'shop_id' => $conversation->shop_id,
            'payment_id' => $conversation->payment_id,
            'shipment_id' => $conversation->shipment_id,
            'return_id' => $conversation->return_id,
            'dispute_id' => $conversation->dispute_id,
            'delivery_incident_id' => $conversation->delivery_incident_id,
            'metadata' => [
                'support_conversation_id' => $conversation->id,
                'created_by_ai' => true,
                'ai_agent' => $conversation->aiAgent?->name,
            ],
        ], $overrides));

        $contextMap = [
            'order' => $conversation->order_id,
            'shop' => $conversation->shop_id,
            'payment' => $conversation->payment_id,
            'shipment' => $conversation->shipment_id,
            'return' => $conversation->return_id,
            'dispute' => $conversation->dispute_id,
            'delivery_incident' => $conversation->delivery_incident_id,
        ];
        $primary = true;
        foreach ($contextMap as $type => $id) {
            if (! $id) continue;
            SupportContextLink::firstOrCreate(
                ['support_ticket_id' => $ticket->id, 'context_type' => $type, 'context_id' => (int) $id],
                ['is_primary' => $primary]
            );
            $primary = false;
        }

        $ticket->messages()->create([
            'author_id' => null,
            'author_type' => 'ai',
            'body' => $reason,
            'is_internal_note' => false,
        ]);

        $conversation->forceFill(['support_ticket_id' => $ticket->id])->save();

        return $ticket;
    }
}
