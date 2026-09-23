<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportCall;
use App\Models\SupportCallbackRequest;
use App\Services\SupportAi\SupportIdentityResolver;
use App\Services\SupportAi\SupportServiceStatusService;
use App\Services\SupportAi\SupportTelephonyManager;
use Illuminate\Http\Request;
use Throwable;

class SupportCallController extends Controller
{
    public function index(Request $request, SupportServiceStatusService $services)
    {
        $tab = trim((string) $request->query('tab', 'calls'));
        if (! in_array($tab, ['calls', 'missed', 'callbacks', 'history'], true)) {
            $tab = 'calls';
        }

        $query = SupportCall::query()
            ->with(['requester', 'aiAgent', 'handler', 'conversation.order', 'conversation.shipment', 'ticket'])
            ->latest('started_at');

        if ($tab === 'missed') {
            $query->where('status', 'missed');
        } elseif ($tab === 'history') {
            $query->whereIn('status', ['completed', 'failed', 'transferred']);
        } elseif ($tab === 'calls') {
            $query->whereIn('status', ['queued', 'waiting', 'ringing', 'in_progress', 'waiting_transfer']);
        }

        foreach (['status', 'direction'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'like', "%{$search}%")
                    ->orWhere('from_number', 'like', "%{$search}%")
                    ->orWhere('to_number', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('conversation.order', fn ($order) => $order->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('conversation.shipment', fn ($shipment) => $shipment->where('tracking_number', 'like', "%{$search}%"));
            });
        }

        $callbacks = SupportCallbackRequest::with(['requester', 'assignee', 'call', 'ticket'])
            ->latest();
        if ($request->filled('callback_status')) {
            $callbacks->where('status', $request->query('callback_status'));
        }

        return view('support.calls.index', [
            'calls' => $query->paginate(25, ['*'], 'calls_page')->withQueryString(),
            'callbacks' => $callbacks->paginate(25, ['*'], 'callbacks_page')->withQueryString(),
            'agents' => \App\Models\User::where('role', 'support')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => $services->all(),
            'telephonyConfigured' => $services->telephonyConfigured(),
            'tab' => $tab,
            'callStats' => [
                'missed' => SupportCall::where('status', 'missed')->count(),
                'callbacks' => SupportCallbackRequest::whereIn('status', ['pending', 'scheduled'])->count(),
                'active' => SupportCall::whereIn('status', ['queued', 'waiting', 'ringing', 'in_progress', 'waiting_transfer'])->count(),
                'history' => SupportCall::whereIn('status', ['completed', 'failed', 'transferred'])->count(),
            ],
        ]);
    }

    public function show(SupportCall $call, SupportServiceStatusService $services)
    {
        $call->load([
            'requester', 'aiAgent', 'handler', 'conversation.messages.aiAgent',
            'conversation.messages.sender', 'conversation.order', 'conversation.payment',
            'conversation.shipment', 'conversation.deliveryIncident', 'conversation.commercialLead',
            'ticket', 'events', 'handoffs.assignee', 'callbackRequests.assignee',
        ]);

        return view('support.calls.show', [
            'call' => $call,
            'humanAgents' => \App\Models\User::where('role', 'support')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'services' => $services->all(),
            'telephonyConfigured' => $services->telephonyConfigured(),
        ]);
    }

    public function outbound(Request $request, SupportTelephonyManager $manager, SupportServiceStatusService $services)
    {
        if (! $services->telephonyConfigured()) {
            return back()->with('error', $services->channel('telephony')['message']);
        }

        $data = $request->validate([
            'to_number' => ['required', 'string', 'max:40'],
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'tracking_reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $call = $manager->initiateOutbound($data, $request->user('admin'));
        } catch (Throwable $exception) {
            report($exception);
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('support.calls.show', $call)->with('success', 'Appel réel transmis au fournisseur téléphonique.');
    }

    public function callback(Request $request, SupportCall $call, SupportIdentityResolver $identities)
    {
        return $this->createCallback($request, $identities, $call);
    }

    public function storeCallback(Request $request, SupportIdentityResolver $identities)
    {
        return $this->createCallback($request, $identities);
    }

    private function createCallback(Request $request, SupportIdentityResolver $identities, ?SupportCall $call = null)
    {
        $data = $request->validate([
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:3000'],
            'preferred_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $explicitUser = ! empty($data['requester_user_id']) ? \App\Models\User::find($data['requester_user_id']) : null;
        $match = $identities->resolve($explicitUser, $data['email'] ?? null, $data['phone']);
        $callback = SupportCallbackRequest::create(array_merge($data, [
            'support_call_id' => $call?->id,
            'support_conversation_id' => $call?->support_conversation_id,
            'support_ticket_id' => $call?->support_ticket_id,
            'requester_user_id' => $data['requester_user_id'] ?? $call?->requester_user_id ?? $match['user']?->id,
            'requester_name' => $data['requester_name'] ?? $call?->requester?->name ?? $match['user']?->name,
            'email' => $data['email'] ?? $call?->requester?->email ?? $match['user']?->email,
            'status' => 'pending',
        ]));

        return redirect()->route('support.calls.index', ['tab' => 'callbacks'])->with('success', "Demande de rappel {$callback->reference} créée.");
    }

    public function transfer(Request $request, SupportCall $call, SupportTelephonyManager $manager, SupportServiceStatusService $services)
    {
        if (! $services->telephonyConfigured()) {
            return back()->with('error', $services->channel('telephony')['message']);
        }

        $data = $request->validate(['destination' => ['required', 'string', 'max:80']]);

        try {
            $manager->transfer($call, $data['destination'], $request->user('admin'));
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Transfert téléphonique réel demandé.');
    }
}
