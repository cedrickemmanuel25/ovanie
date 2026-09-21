<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
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
            ->with(['requester', 'aiAgent', 'assignee', 'ticket', 'order', 'shipment', 'commercialLead', 'deliveryIncident'])
            ->withCount('messages')
            ->latest('last_message_at');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('subject', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->orWhere('requester_phone', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('shipment', fn ($shipment) => $shipment->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('reference', 'like', "%{$search}%"));
            });
        }

        foreach (['status', 'channel', 'ai_agent_id', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return view('support.conversations.index', [
            'conversations' => $query->paginate(25)->withQueryString(),
            'agents' => \App\Models\SupportAiAgent::orderBy('name')->get(),
            'humanAgents' => $this->humanAgents(),
            'services' => $services->all(),
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
        $conversation = SupportConversation::create([
            'requester_user_id' => $requester?->id,
            'requester_name' => $data['requester_name'] ?? $requester?->name,
            'requester_email' => $data['requester_email'] ?? $requester?->email,
            'requester_phone' => $data['requester_phone'] ?? $requester?->phone,
            'channel' => $data['channel'],
            'status' => 'active',
            'subject' => $data['subject'],
            'order_id' => $data['order_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'shop_id' => $data['shop_id'] ?? null,
            'return_id' => $data['return_id'] ?? null,
            'dispute_id' => $data['dispute_id'] ?? null,
        ]);

        $conversation = $linker->link($conversation, $data['message'], $data, true, $requester);
        $orchestrator->receiveCustomerMessage($conversation, $data['message'], $conversation->requester, [
            'entered_by_staff' => $request->user('admin')->id,
            'order_reference' => $data['order_reference'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'tracking_reference' => $data['tracking_reference'] ?? null,
        ]);

        return redirect()->route('support.conversations.show', $conversation)
            ->with('success', 'Conversation créée, compte et dossiers réels recherchés automatiquement.');
    }

    public function show(SupportConversation $conversation)
    {
        $conversation->load([
            'requester', 'aiAgent', 'assignee', 'ticket', 'commercialLead.assignee',
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

        return back()->with('success', 'Vous avez pris en charge cette conversation.');
    }

    public function handoff(Request $request, SupportConversation $conversation, SupportHandoffQueueService $queue, SupportTicketFactory $tickets)
    {
        $data = $request->validate([
            'target_department' => ['required', Rule::in(['support', 'logistique', 'commercial', 'administration'])],
            'severity' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $ticket = $conversation->ticket ?: $tickets->fromConversation(
            $conversation->load('aiAgent'),
            $data['reason'],
            $data['severity'] === 'urgent' ? 'urgent' : ($data['severity'] === 'high' ? 'high' : 'normal'),
            ['team' => $data['target_department']],
        );

        $queue->enqueue(
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

        return back()->with('success', 'Le dossier a été ajouté à la file réelle du service sélectionné.');
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
                $queue->resolve($handoff, $user, 'Conversation résolue depuis la console Support.', 'conversation_resolved');
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

        return back()->with('success', 'Conversation marquée comme résolue.');
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
