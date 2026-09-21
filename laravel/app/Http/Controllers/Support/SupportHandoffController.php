<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportAgentHandoff;
use App\Models\User;
use App\Services\SupportAi\SupportHandoffQueueService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportHandoffController extends Controller
{
    public function index(Request $request, SupportHandoffQueueService $queues)
    {
        $query = SupportAgentHandoff::query()
            ->with([
                'conversation.requester', 'conversation.order', 'conversation.shipment',
                'call', 'aiAgent', 'assignee', 'ticket', 'deliveryIncident',
                'commercialLead', 'completedBy',
            ])
            ->latest('requested_at');

        foreach (['status', 'severity', 'target_department'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('conversation', fn ($conversation) => $conversation
                        ->where('requester_name', 'like', "%{$search}%")
                        ->orWhere('requester_email', 'like', "%{$search}%")
                        ->orWhere('requester_phone', 'like', "%{$search}%"))
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('reference', 'like', "%{$search}%"));
            });
        }

        return view('support.handoffs.index', [
            'handoffs' => $query->paginate(25)->withQueryString(),
            'supportAgents' => $queues->agentsForDepartment('support'),
            'queueStats' => [
                'pending' => SupportAgentHandoff::open()->where('target_department', 'support')->whereNull('assigned_to')->count(),
                'mine' => SupportAgentHandoff::open()->where('assigned_to', $request->user('admin')->id)->count(),
                'overdue' => SupportAgentHandoff::open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'all_open' => SupportAgentHandoff::open()->count(),
            ],
        ]);
    }

    public function accept(Request $request, SupportAgentHandoff $handoff, SupportHandoffQueueService $queues)
    {
        $queues->claim($handoff, $request->user('admin'));

        return back()->with('success', 'Le transfert vous a été assigné et est maintenant pris en charge.');
    }

    public function assign(Request $request, SupportAgentHandoff $handoff, SupportHandoffQueueService $queues)
    {
        abort_unless($handoff->target_department === 'support', 403, 'Cette file appartient à un autre service.');

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'support')],
        ]);

        $assignee = User::query()->whereKey($data['assigned_to'])->firstOrFail();
        $queues->assign($handoff, $assignee, $request->user('admin'));

        return back()->with('success', 'Transfert assigné à '.$assignee->name.'.');
    }

    public function resolve(Request $request, SupportAgentHandoff $handoff, SupportHandoffQueueService $queues)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'resolution_code' => ['nullable', Rule::in([
                'answered', 'ticket_follow_up', 'redirected', 'incident_resolved',
                'commercial_follow_up', 'duplicate', 'other',
            ])],
        ]);

        $queues->resolve(
            $handoff,
            $request->user('admin'),
            $data['notes'] ?? null,
            $data['resolution_code'] ?? 'answered',
        );

        return back()->with('success', 'Transfert clôturé et journalisé.');
    }
}
