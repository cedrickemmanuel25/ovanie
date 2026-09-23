<?php

namespace App\Services\Staff;

use App\Models\DeliveryIncident;
use App\Models\Dispute;
use App\Models\ReturnModel;
use App\Models\Submission;
use App\Models\SupportAgentHandoff;
use App\Models\SupportAiAgent;
use App\Models\SupportCall;
use App\Models\SupportCallbackRequest;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\SupportAi\SupportServiceStatusService;

class SupportDashboardService
{
    public function __construct(private readonly SupportServiceStatusService $services) {}

    public function build(User $user): array
    {
        $open = SupportTicket::open();

        return [
            'stats' => [
                'open' => (clone $open)->count(),
                'mine' => (clone $open)->where('assigned_to', $user->id)->count(),
                'urgent' => (clone $open)->where('priority', 'urgent')->count(),
                'overdue' => (clone $open)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
                'resolved_today' => SupportTicket::whereDate('resolved_at', today())->count(),
                'open_incidents' => DeliveryIncident::whereNotIn('status', ['resolved', 'closed'])->count(),
                'open_returns' => ReturnModel::whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'cancelled'])->count(),
                'escalated_disputes' => Dispute::where('escalated', true)->count(),
                'unlinked_messages' => Submission::whereDoesntHave('supportTickets')->count(),
                'waiting_human_conversations' => SupportConversation::query()->operational()->where(function ($query) {
                    $query->where('status', 'waiting_human')->orWhere('requires_human', true);
                })->whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'active_ai_conversations' => SupportConversation::query()->operational()->active()->count(),
                'waiting_calls' => SupportCall::whereIn('status', ['waiting', 'ringing', 'waiting_transfer'])->count(),
                'missed_calls_today' => SupportCall::where('status', 'missed')->whereDate('started_at', today())->count(),
                'pending_callbacks' => SupportCallbackRequest::whereIn('status', ['pending', 'scheduled'])->count(),
                'pending_handoffs' => SupportAgentHandoff::query()
                    ->operational()
                    ->whereIn('target_department', ['logistique', 'commercial', 'administration'])
                    ->open()->count(),
                'ai_created_tickets' => SupportTicket::where('created_by_ai', true)->count(),
                'active_ai_agents' => SupportAiAgent::where('status', 'active')->count(),
            ],
            'services' => $this->services->all(),
            'recentTickets' => SupportTicket::with(['requester', 'requesterProfile', 'assignee', 'order', 'shop', 'aiAgent'])
                ->latest()->limit(10)->get(),
            'myQueue' => SupportTicket::with(['requester', 'requesterProfile', 'order'])
                ->open()->where('assigned_to', $user->id)
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->orderBy('sla_due_at')->limit(8)->get(),
            'workload' => SupportTicket::query()
                ->select('assigned_to', DB::raw('count(*) as total'))
                ->with('assignee:id,name,first_name,last_name')
                ->open()->whereNotNull('assigned_to')
                ->groupBy('assigned_to')->orderByDesc('total')->limit(8)->get(),
            'recentConversations' => SupportConversation::query()->operational()->with(['requester', 'requesterProfile', 'aiAgent', 'assignee', 'ticket'])
                ->latest('last_message_at')->limit(8)->get(),
            'waitingHandoffs' => SupportAgentHandoff::query()->operational()->with(['conversation.requester', 'ticket.requesterProfile', 'aiAgent', 'assignee'])
                ->whereIn('target_department', ['logistique', 'commercial', 'administration'])
                ->open()->latest('requested_at')->limit(6)->get(),
            'recentCalls' => SupportCall::with(['requester', 'aiAgent', 'handler'])
                ->latest('started_at')->limit(6)->get(),
        ];
    }
}
