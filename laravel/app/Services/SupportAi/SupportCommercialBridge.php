<?php

namespace App\Services\SupportAi;

use App\Models\CommercialLead;
use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\User;
use Illuminate\Support\Str;

class SupportCommercialBridge
{
    public function sync(
        SupportConversation $conversation,
        SupportAiAgent $agent,
        string $message,
        ?SupportConversationMessage $sourceMessage = null,
    ): ?CommercialLead {
        if ($agent->role_key !== 'business') {
            return null;
        }

        $conversation->loadMissing('requester.shop', 'shop');
        $assignee = $this->leastLoadedCommercialAgent();

        $lead = CommercialLead::query()->firstOrNew([
            'support_conversation_id' => $conversation->id,
        ]);

        $isNew = ! $lead->exists;
        $lead->fill([
            'user_id' => $conversation->requester_user_id,
            'shop_id' => $conversation->shop_id ?: $conversation->requester?->shop?->id,
            'assigned_to' => $lead->assigned_to ?: $assignee?->id,
            'created_by' => $lead->created_by,
            'source' => 'support_ai_miss_rita',
            'lead_type' => 'business',
            'status' => $lead->status ?: 'new',
            'company_name' => $lead->company_name ?: $conversation->requester?->shop?->name,
            'contact_name' => $conversation->requester_name ?: $conversation->requester?->name ?: 'Contact OVANIE Pro',
            'email' => $conversation->requester_email ?: $conversation->requester?->email,
            'phone' => $conversation->requester_phone ?: $conversation->requester?->phone,
            'city' => $lead->city ?: $conversation->requester?->city,
            'title' => $conversation->subject ?: 'Demande OVANIE Pro reçue par Miss Rita',
            'need_summary' => $this->appendNeed($lead->need_summary, $message),
            'next_action_at' => $lead->next_action_at ?: now()->addDay(),
            'metadata' => array_merge($lead->metadata ?? [], [
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $sourceMessage?->id,
                'ai_agent_id' => $agent->id,
                'ai_agent_name' => $agent->name,
                'last_synced_at' => now()->toISOString(),
            ]),
        ]);
        $lead->save();

        if ($isNew) {
            $lead->activities()->create([
                'author_id' => null,
                'type' => 'note',
                'subject' => 'Qualification automatique par Miss Rita',
                'description' => $message,
                'outcome' => 'Opportunité créée depuis la conversation Support IA.',
                'happened_at' => now(),
                'next_follow_up_at' => $lead->next_action_at,
                'metadata' => [
                    'support_conversation_id' => $conversation->id,
                    'support_message_id' => $sourceMessage?->id,
                    'generated_by_ai' => true,
                ],
            ]);
        }

        return $lead->fresh(['assignee', 'supportConversation']);
    }

    private function leastLoadedCommercialAgent(): ?User
    {
        return User::query()
            ->where('role', 'commercial')
            ->where('status', 'active')
            ->whereHas('staffProfile', fn ($query) => $query->where('is_active', true))
            ->withCount(['commercialLeads as open_leads_count' => fn ($query) => $query->active()])
            ->orderBy('open_leads_count')
            ->orderBy('id')
            ->first();
    }

    private function appendNeed(?string $current, string $message): string
    {
        $message = trim(Str::limit($message, 2500, ''));
        if ($message === '') {
            return (string) $current;
        }

        if ($current && Str::contains($current, $message)) {
            return $current;
        }

        return trim(($current ? $current."\n\n" : '').'• '.$message);
    }
}
