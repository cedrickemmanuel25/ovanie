<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['requester', 'assignee', 'order', 'shop'])->latest();

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

        return view('support.tickets.index', [
            'tickets' => $query->paginate(25)->withQueryString(),
            'agents' => $this->agents(),
        ]);
    }

    public function create(Request $request)
    {
        return view('support.tickets.create', [
            'agents' => $this->agents(),
            'defaults' => $request->only(['requester_user_id', 'order_id', 'shop_id', 'payment_id', 'shipment_id', 'return_id', 'dispute_id', 'delivery_incident_id', 'submission_id']),
        ]);
    }

    public function store(StoreSupportTicketRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['assigned_to'] ??= $request->user()->id;

        $ticket = SupportTicket::create($data);
        $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'author_type' => 'staff',
            'body' => $ticket->description,
            'is_internal_note' => false,
        ]);

        return redirect()->route('support.tickets.show', $ticket)->with('success', 'Ticket créé avec succès.');
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load([
            'requester', 'assignee', 'creator', 'messages.author', 'order.client',
            'order.items.product', 'shop.user', 'payment', 'shipment', 'returnRequest',
            'dispute', 'deliveryIncident', 'submission',
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

        if ($data['status'] === 'resolved' && $oldStatus !== 'resolved') {
            $data['resolved_at'] = now();
        }
        if ($data['status'] === 'closed' && $oldStatus !== 'closed') {
            $data['closed_at'] = now();
        }

        $ticket->update($data);

        return back()->with('success', 'Ticket mis à jour.');
    }

    public function reply(StoreSupportMessageRequest $request, SupportTicket $ticket)
    {
        $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'author_type' => 'staff',
            'body' => $request->validated('body'),
            'is_internal_note' => (bool) $request->boolean('is_internal_note'),
        ]);

        $updates = ['status' => $request->boolean('is_internal_note') ? $ticket->status : 'in_progress'];
        if (! $ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        $ticket->update($updates);

        return back()->with('success', $request->boolean('is_internal_note') ? 'Note interne ajoutée.' : 'Réponse enregistrée.');
    }

    private function agents()
    {
        return User::query()->where('role', 'support')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }
}
