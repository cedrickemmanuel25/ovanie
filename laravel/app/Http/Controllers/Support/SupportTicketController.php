<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketRequest;
use App\Models\DeliveryDriver;
use App\Models\SupportContextLink;
use App\Models\SupportRequester;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdatedNotification;
use App\Services\Support\SupportCaseService;
use App\Services\SupportAi\SupportHandoffQueueService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with([
            'requester', 'requesterProfile', 'contextLinks', 'assignee', 'order', 'shop',
            'handoffs' => fn ($q) => $q->latest('requested_at'),
        ])->latest();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        foreach (['status', 'priority', 'category', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $scope = trim((string) $request->query('scope', 'open'));
        $currentUser = $request->user('admin');
        if ($scope === 'unassigned') {
            $query->whereNull('assigned_to')->open();
        } elseif ($scope === 'mine' && $currentUser) {
            $query->where('assigned_to', $currentUser->id)->open();
        } elseif ($scope === 'urgent') {
            $query->whereIn('priority', ['urgent', 'high'])->open();
        } elseif ($scope === 'overdue') {
            $query->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->open();
        } elseif ($scope === 'waiting') {
            $query->whereIn('status', ['waiting_customer', 'waiting_internal']);
        } elseif ($scope === 'transferred') {
            $query->whereHas('handoffs', fn ($handoffs) => $handoffs
                ->open()
                ->whereIn('target_department', ['logistique', 'commercial', 'administration']));
        } elseif ($scope === 'resolved') {
            $query->whereIn('status', ['resolved', 'closed']);
        } elseif ($scope === 'all') {
            // Tous les dossiers.
        } else {
            $scope = 'open';
            $query->open();
        }

        $base = SupportTicket::query();
        $stats = [
            'open' => (clone $base)->open()->count(),
            'unassigned' => (clone $base)->whereNull('assigned_to')->open()->count(),
            'mine' => $currentUser ? (clone $base)->where('assigned_to', $currentUser->id)->open()->count() : 0,
            'urgent' => (clone $base)->whereIn('priority', ['urgent', 'high'])->open()->count(),
            'waiting' => (clone $base)->whereIn('status', ['waiting_customer', 'waiting_internal'])->count(),
            'transferred' => (clone $base)->whereHas('handoffs', fn ($handoffs) => $handoffs
                ->open()
                ->whereIn('target_department', ['logistique', 'commercial', 'administration']))->count(),
            'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
            'all' => (clone $base)->count(),
        ];

        return view('support.tickets.index', [
            'tickets' => $query->paginate(25)->withQueryString(),
            'agents' => $this->agents(),
            'ticketStats' => $stats,
            'scope' => $scope,
        ]);
    }

    public function create(Request $request)
    {
        return view('support.tickets.create', [
            'agents' => $this->agents(),
            'defaults' => $request->only([
                'requester_type', 'requester_user_id', 'delivery_driver_id', 'context_type', 'context_id',
                'order_id', 'shop_id', 'payment_id', 'shipment_id', 'return_id', 'dispute_id',
                'delivery_incident_id', 'submission_id',
            ]),
        ]);
    }

    public function store(StoreSupportTicketRequest $request, SupportCaseService $cases)
    {
        $data = $request->validated();
        $staff = $request->user('admin') ?: $request->user();
        $data['created_by'] = $staff->id;
        $data['assigned_to'] ??= $staff->id;
        $data['source_app'] ??= 'support_web';
        $data['team'] = 'support';

        $requesterType = $data['requester_type'] ?? 'guest';

        if (! empty($data['delivery_driver_id'])) {
            $driver = DeliveryDriver::find($data['delivery_driver_id']);
            if ($driver) {
                $profile = SupportRequester::firstOrCreate(
                    ['requester_type' => 'driver', 'delivery_driver_id' => $driver->id],
                    ['name' => $driver->name, 'email' => $driver->email, 'phone' => $driver->phone]
                );
                $data['support_requester_id'] = $profile->id;
                $data['requester_name'] ??= $driver->name;
                $data['requester_email'] ??= $driver->email;
                $data['requester_phone'] ??= $driver->phone;
                $requesterType = 'driver';
            }
        } elseif (! empty($data['requester_user_id'])) {
            $requesterUser = User::find($data['requester_user_id']);
            if ($requesterUser) {
                $requesterType = $data['requester_type'] ?? ($requesterUser->role === 'vendor' ? 'vendor' : ($requesterUser->role === 'commercial' ? 'commercial' : 'client'));
                $shopId = $requesterType === 'vendor' ? $requesterUser->shop?->id : null;
                $profile = SupportRequester::firstOrCreate(
                    ['requester_type' => $requesterType, 'user_id' => $requesterUser->id, 'shop_id' => $shopId],
                    ['name' => $requesterUser->name, 'email' => $requesterUser->email, 'phone' => $requesterUser->phone]
                );
                $data['support_requester_id'] = $profile->id;
                $data['requester_name'] ??= $requesterUser->name;
                $data['requester_email'] ??= $requesterUser->email;
                $data['requester_phone'] ??= $requesterUser->phone;
            }
        } else {
            $profile = SupportRequester::create([
                'requester_type' => $requesterType,
                'name' => $data['requester_name'] ?? null,
                'email' => $data['requester_email'] ?? null,
                'phone' => $data['requester_phone'] ?? null,
                'metadata' => ['created_from' => 'support_web'],
            ]);
            $data['support_requester_id'] = $profile->id;
        }

        $manualContextType = $data['context_type'] ?? null;
        $manualContextId = isset($data['context_id']) ? (int) $data['context_id'] : null;
        unset($data['requester_type'], $data['delivery_driver_id'], $data['context_type'], $data['context_id']);

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $data['metadata'] = array_merge($metadata, [
            'requester_type' => $requesterType,
            'source_app' => $data['source_app'],
            'created_by_support' => true,
        ]);

        $ticket = SupportTicket::create($data);
        foreach ([
            'order' => $ticket->order_id, 'shop' => $ticket->shop_id, 'payment' => $ticket->payment_id,
            'shipment' => $ticket->shipment_id, 'return' => $ticket->return_id, 'dispute' => $ticket->dispute_id,
            'delivery_incident' => $ticket->delivery_incident_id,
        ] as $contextType => $contextId) {
            if ($contextId) {
                SupportContextLink::firstOrCreate([
                    'support_ticket_id' => $ticket->id,
                    'context_type' => $contextType,
                    'context_id' => (int) $contextId,
                ]);
            }
        }
        if ($manualContextType && $manualContextId) {
            SupportContextLink::firstOrCreate([
                'support_ticket_id' => $ticket->id,
                'context_type' => $manualContextType,
                'context_id' => $manualContextId,
            ], ['is_primary' => ! $ticket->contextLinks()->exists()]);
        }

        $ticket->messages()->create([
            'author_id' => $staff->id,
            'author_type' => 'staff',
            'body' => $ticket->description,
            'is_internal_note' => false,
        ]);

        $cases->ensureConversationForTicket(
            $ticket->fresh(['requesterProfile']),
            $staff,
            $requesterType,
            'human',
        );

        return redirect()->route('support.tickets.show', $ticket)
            ->with('success', 'Dossier de suivi créé. Il reste sous la responsabilité du Support et peut être transmis à l’équipe OVANIE concernée si une action métier est nécessaire.');
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load([
            'requester', 'requesterProfile', 'contextLinks', 'assignee', 'creator', 'messages.author', 'order.client',
            'order.items.product', 'shop.user', 'payment', 'shipment', 'returnRequest',
            'dispute', 'deliveryIncident', 'submission',
            'conversations' => fn ($q) => $q->latest('last_message_at'),
            'handoffs' => fn ($q) => $q->with(['assignee', 'completedBy', 'conversation'])->latest('requested_at'),
        ]);

        return view('support.tickets.show', [
            'ticket' => $ticket,
            'agents' => $this->agents(),
        ]);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket)
    {
        $data = $request->validated();
        $oldStatus = $ticket->status;
        $data['team'] = 'support';

        if ($data['status'] === 'resolved' && $oldStatus !== 'resolved') {
            $data['resolved_at'] = now();
        }
        if ($data['status'] === 'closed' && $oldStatus !== 'closed') {
            $data['closed_at'] = now();
        }

        $ticket->update($data);

        if ($ticket->wasChanged('status')) {
            $this->notifyRequester(
                $ticket,
                'Le suivi de votre dossier '.$ticket->reference.' a été mis à jour.',
            );
        }

        return back()->with('success', 'Dossier Support mis à jour.');
    }

    public function reply(StoreSupportMessageRequest $request, SupportTicket $ticket, SupportCaseService $cases)
    {
        $staff = $request->user('admin') ?: $request->user();
        $internal = (bool) $request->boolean('is_internal_note');
        $body = (string) $request->validated('body');

        $ticket->messages()->create([
            'author_id' => $staff->id,
            'author_type' => 'staff',
            'body' => $body,
            'is_internal_note' => $internal,
        ]);

        $updates = ['status' => $internal ? $ticket->status : 'in_progress'];
        if (! $ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        $ticket->update($updates);

        $cases->appendConversationMessageForTicket(
            $ticket->fresh(['requesterProfile']),
            $body,
            'human',
            $staff,
            $internal,
            ['source' => 'ticket_reply'],
        );

        if (! $internal) {
            $this->notifyRequester(
                $ticket,
                'Une nouvelle réponse du Support OVANIE est disponible pour votre dossier '.$ticket->reference.'.',
            );
        }

        return back()->with('success', $internal ? 'Note interne ajoutée.' : 'Réponse envoyée au demandeur.');
    }

    public function handoff(
        Request $request,
        SupportTicket $ticket,
        SupportCaseService $cases,
        SupportHandoffQueueService $queue,
    ) {
        $data = $request->validate([
            'target_department' => ['required', Rule::in(['logistique', 'commercial', 'administration'])],
            'severity' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
        ]);

        $staff = $request->user('admin') ?: $request->user();
        $ticket->loadMissing(['requesterProfile', 'deliveryIncident']);
        $conversation = $cases->ensureConversationForTicket(
            $ticket,
            $staff,
            $ticket->requesterProfile?->requester_type,
            'human',
        );

        if (! $conversation->assigned_to) {
            $conversation->forceFill([
                'assigned_to' => $staff->id,
                'status' => 'human',
                'requires_human' => true,
            ])->save();
        }

        $handoff = $queue->enqueue(
            $conversation,
            $data['target_department'],
            trim($data['reason']),
            $data['severity'],
            ticket: $ticket,
            incident: $ticket->deliveryIncident,
            lead: $conversation->commercialLead,
            requestedBy: $staff,
            requestedByType: 'human',
        );

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

        $ticket->messages()->create([
            'author_id' => $staff->id,
            'author_type' => 'staff',
            'body' => 'Dossier transmis à '.ucfirst($data['target_department']).".\nMotif : ".trim($data['reason']),
            'is_internal_note' => true,
        ]);

        $this->notifyRequester(
            $ticket,
            'Votre dossier '.$ticket->reference.' a été transmis à l’équipe OVANIE concernée pour vérification. Le Support reste votre point de contact.',
        );

        return back()->with('success', 'Dossier transmis à '.ucfirst($data['target_department']).'. Le Support reste responsable du suivi avec le demandeur.');
    }

    private function notifyRequester(SupportTicket $ticket, string $message): void
    {
        $ticket->loadMissing(['requesterProfile.user', 'requesterProfile.driver', 'requester']);
        $recipient = $ticket->requesterProfile?->user
            ?: $ticket->requesterProfile?->driver
            ?: $ticket->requester;

        if ($recipient && method_exists($recipient, 'notify')) {
            $recipient->notify(new SupportTicketUpdatedNotification($ticket, $message));
        }
    }

    private function agents()
    {
        return User::query()
            ->where('role', 'support')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }
}
