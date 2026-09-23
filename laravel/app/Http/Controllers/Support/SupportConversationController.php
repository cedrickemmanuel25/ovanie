<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\SupportRequester;
use App\Models\User;
use App\Services\SupportAi\SupportAiAuditLogger;
use App\Services\SupportAi\SupportAiOrchestrator;
use App\Services\SupportAi\SupportEntityLinker;
use App\Services\SupportAi\SupportHandoffQueueService;
use App\Services\SupportAi\SupportTicketFactory;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportConversationController extends Controller
{
    public function index(Request $request, SupportServiceStatusService $services)
    {
        $query = SupportConversation::query()
            ->operational()
            ->with(['requester', 'requesterProfile', 'aiAgent', 'assignee', 'ticket.contextLinks', 'order', 'payment', 'shipment', 'shop', 'returnRequest', 'dispute', 'commercialLead', 'deliveryIncident'])
            ->withCount('messages')
            ->latest('last_message_at');

        $scope = trim((string) $request->query('scope', 'waiting'));
        $currentUser = $request->user('admin');
        if ($scope === 'waiting') {
            $query->where(function ($builder) {
                $builder->where(function ($waiting) {
                    $waiting->where('requires_human', true)->orWhere('status', 'waiting_human');
                })->whereNull('assigned_to');
            });
        } elseif ($scope === 'mine' && $currentUser) {
            $query->where('assigned_to', $currentUser->id)->whereIn('status', ['active', 'waiting_human', 'human']);
        } elseif ($scope === 'closed') {
            $query->whereIn('status', ['resolved', 'closed']);
        } elseif ($scope === 'all') {
            // Aucun filtre de statut.
        } else {
            $scope = 'open';
            $query->whereIn('status', ['active', 'waiting_human', 'human']);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('subject', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->orWhere('requester_phone', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('shipment', fn ($shipment) => $shipment->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('payment', fn ($payment) => $payment->where('reference', 'like', "%{$search}%")->orWhere('transaction_id', 'like', "%{$search}%"))
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('reference', 'like', "%{$search}%"));
            });
        }

        foreach (['status', 'channel', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $base = SupportConversation::query()->operational();
        $stats = [
            'open' => (clone $base)->whereIn('status', ['active', 'waiting_human', 'human'])->count(),
            'waiting' => (clone $base)->where(function ($builder) {
                $builder->where(function ($waiting) {
                    $waiting->where('requires_human', true)->orWhere('status', 'waiting_human');
                })->whereNull('assigned_to');
            })->count(),
            'mine' => $currentUser ? (clone $base)->where('assigned_to', $currentUser->id)->whereIn('status', ['active', 'waiting_human', 'human'])->count() : 0,
            'closed' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
            'all' => (clone $base)->count(),
        ];

        return view('support.conversations.index', [
            'conversations' => $query->paginate(25)->withQueryString(),
            'humanAgents' => $this->humanAgents(),
            'services' => $services->all(),
            'conversationStats' => $stats,
            'scope' => $scope,
        ]);
    }

    public function store(
        Request $request,
        SupportEntityLinker $linker,
        SupportAiOrchestrator $orchestrator,
        SupportServiceStatusService $services,
    ) {
        $data = $request->validate([
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:40'],
            'channel' => ['required', Rule::in(['chat', 'email', 'whatsapp', 'phone', 'internal'])],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'return_id' => ['nullable', 'exists:returns,id'],
            'dispute_id' => ['nullable', 'exists:disputes,id'],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'tracking_reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! in_array($data['channel'], ['internal', 'chat'], true)) {
            $serviceKey = $data['channel'] === 'phone' ? 'telephony' : $data['channel'];
            $service = $services->channel($serviceKey);
            if (! $service['operational']) {
                return back()->withInput()->withErrors(['channel' => $service['message']]);
            }
        }

        $requester = ! empty($data['requester_user_id']) ? User::find($data['requester_user_id']) : null;
        $supportRequester = null;
        if ($requester) {
            $requesterType = $requester->role === 'vendor' ? 'vendor' : ($requester->role === 'commercial' ? 'commercial' : 'client');
            $shopId = $requesterType === 'vendor' ? $requester->shop?->id : null;
            $supportRequester = SupportRequester::firstOrCreate(
                ['requester_type' => $requesterType, 'user_id' => $requester->id, 'shop_id' => $shopId],
                ['name' => $requester->name, 'email' => $requester->email, 'phone' => $requester->phone]
            );
        }
        $conversation = SupportConversation::create([
            'support_requester_id' => $supportRequester?->id,
            'requester_user_id' => $requester?->id,
            'requester_name' => $data['requester_name'] ?? $requester?->name,
            'requester_email' => $data['requester_email'] ?? $requester?->email,
            'requester_phone' => $data['requester_phone'] ?? $requester?->phone,
            'channel' => $data['channel'],
            'source_app' => 'support_web',
            'status' => 'human',
            'assigned_to' => $request->user('admin')->id,
            'requires_human' => true,
            'subject' => $data['subject'],
            'order_id' => $data['order_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'shop_id' => $data['shop_id'] ?? null,
            'return_id' => $data['return_id'] ?? null,
            'dispute_id' => $data['dispute_id'] ?? null,
        ]);

        $conversation = $linker->link($conversation, $data['message'], $data, true, $requester);

        SupportConversationMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_type' => 'human',
            'sender_user_id' => $request->user('admin')->id,
            'body' => trim($data['message']),
            'format' => 'text',
            'is_internal' => false,
            'provider' => 'support_web',
            'metadata' => ['outbound_follow_up' => true],
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return redirect()->route('support.conversations.show', $conversation)
            ->with('success', 'Suivi sortant créé. Il est pris en charge par vous et n’est pas renvoyé automatiquement à l’assistant IA.');
    }

    public function show(SupportConversation $conversation)
    {
        $conversation->load([
            'requester', 'requesterProfile', 'aiAgent', 'assignee', 'ticket.contextLinks', 'commercialLead.assignee',
            'order', 'payment', 'shipment', 'shop', 'returnRequest', 'dispute', 'deliveryIncident',
            'messages.sender', 'messages.aiAgent',
            'handoffs.aiAgent', 'handoffs.assignee', 'handoffs.ticket', 'handoffs.deliveryIncident', 'handoffs.commercialLead',
            'calls.aiAgent', 'callbackRequests.assignee',
        ]);

        return view('support.conversations.show', [
            'conversation' => $conversation,
            'humanAgents' => $this->humanAgents(),
        ]);
    }

    public function reply(Request $request, SupportConversation $conversation, SupportAiOrchestrator $orchestrator)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $orchestrator->sendHumanMessage(
            $conversation,
            $request->user('admin'),
            $data['body'],
            $request->boolean('is_internal'),
        );

        return back()->with('success', $request->boolean('is_internal') ? 'Note interne ajoutée.' : 'Réponse humaine envoyée.');
    }

    public function aiReply(Request $request, SupportConversation $conversation, SupportAiOrchestrator $orchestrator)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $orchestrator->receiveCustomerMessage($conversation, $data['body'], $conversation->requester, [
            'entered_by_staff' => $request->user('admin')->id,
        ]);

        return back()->with('success', 'Le message a été traité par l’agent IA à partir des données liées et des articles publiés.');
    }

    public function takeover(
        Request $request,
        SupportConversation $conversation,
        SupportHandoffQueueService $queue,
        SupportAiAuditLogger $audit,
        SupportTicketFactory $tickets,
    ) {
        $user = $request->user('admin');
        $handoff = $conversation->handoffs()->open()->where('target_department', 'support')->latest('requested_at')->first();

        if ($handoff) {
            $queue->claim($handoff, $user);
        } else {
            $conversation->forceFill([
                'assigned_to' => $user->id,
                'status' => 'human',
            ])->save();

            $audit->log([
                'support_conversation_id' => $conversation->id,
                'actor_user_id' => $user->id,
                'action' => 'conversation_taken_over',
                'decision' => 'human_control',
                'risk_level' => 'low',
            ]);
        }

        // Dès qu'un conseiller prend réellement la main, le suivi devient un
        // Dossier Support. Les simples échanges encore gérés par l'assistant
        // restent de simples conversations tant qu'aucune action humaine n'est requise.
        if (! $conversation->support_ticket_id) {
            $ticket = $tickets->fromConversation(
                $conversation->fresh(['aiAgent']),
                $conversation->summary ?: ('Prise en charge humaine : '.($conversation->subject ?: 'demande Support OVANIE')),
                $conversation->priority ?: 'normal',
                ['assigned_to' => $user->id, 'team' => 'support'],
            );
            $conversation->forceFill(['support_ticket_id' => $ticket->id])->save();
        }

        return back()->with('success', 'Conversation prise en charge et dossier Support lié automatiquement.');
    }

    public function handoff(Request $request, SupportConversation $conversation, SupportHandoffQueueService $queue, SupportTicketFactory $tickets)
    {
        $data = $request->validate([
            'target_department' => ['required', Rule::in(['logistique', 'commercial', 'administration'])],
            'severity' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $ticket = $conversation->ticket ?: $tickets->fromConversation(
            $conversation->load('aiAgent'),
            $data['reason'],
            $data['severity'] === 'urgent' ? 'urgent' : ($data['severity'] === 'high' ? 'high' : 'normal'),
            ['team' => 'support'],
        );

        $handoff = $queue->enqueue(
            $conversation,
            $data['target_department'],
            $data['reason'],
            $data['severity'],
            ticket: $ticket,
            incident: $conversation->deliveryIncident,
            lead: $conversation->commercialLead,
            requestedBy: $request->user('admin'),
            requestedByType: 'human',
        );

        if (! in_array($ticket->status, ['closed', 'cancelled'], true)) {
            $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
            $ticket->forceFill([
                'status' => 'waiting_internal',
                'team' => 'support',
                'escalation_level' => max(1, (int) $ticket->escalation_level),
                'metadata' => array_merge($metadata, [
                    'last_handoff_reference' => $handoff->reference,
                    'last_handoff_department' => $data['target_department'],
                    'last_handoff_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }

        $ticket->messages()->create([
            'author_id' => $request->user('admin')->id,
            'author_type' => 'staff',
            'body' => 'Dossier transmis à '.ucfirst($data['target_department']).".\nMotif : ".trim($data['reason']),
            'is_internal_note' => true,
        ]);

        return back()->with('success', 'Dossier transmis à '.ucfirst($data['target_department']).'. Le Support reste responsable du suivi avec le demandeur.');
    }

    public function createTicket(Request $request, SupportConversation $conversation, SupportTicketFactory $factory)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $ticket = $factory->fromConversation($conversation->load('aiAgent'), $data['reason'], $data['priority']);

        return redirect()->route('support.tickets.show', $ticket)->with('success', 'Ticket créé depuis la conversation.');
    }

    public function resolve(Request $request, SupportConversation $conversation, SupportHandoffQueueService $queue, SupportAiAuditLogger $audit)
    {
        $user = $request->user('admin');
        foreach ($conversation->handoffs()->open()->where('target_department', 'support')->get() as $handoff) {
            if (! $handoff->assigned_to || (int) $handoff->assigned_to === (int) $user->id) {
                $queue->resolve($handoff, $user, 'Suivi de conversation terminé depuis la console Support.', 'conversation_resolved');
            }
        }

        $conversation->forceFill([
            'status' => 'resolved',
            'requires_human' => false,
            'resolved_at' => now(),
        ])->save();

        $audit->log([
            'support_conversation_id' => $conversation->id,
            'actor_user_id' => $user->id,
            'action' => 'conversation_resolved',
            'decision' => 'resolved',
            'risk_level' => 'low',
        ]);

        return back()->with('success', 'Conversation terminée. Le suivi reste conservé dans l’historique.');
    }

    private function humanAgents()
    {
        return User::query()
            ->where('role', 'support')
            ->where('status', 'active')
            ->whereHas('staffProfile', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
