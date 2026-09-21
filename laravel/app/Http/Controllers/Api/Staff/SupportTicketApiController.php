<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketApiController extends Controller
{
    public function index(Request $request)
    {
        return SupportTicket::with(['requester:id,name,email,phone', 'assignee:id,name,email', 'order:id,order_number,status,total_amount', 'shop:id,name,status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->query('assigned_to')))
            ->latest()->paginate(50);
    }

    public function store(StoreSupportTicketRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $ticket = SupportTicket::create($data);
        return response()->json($ticket->load(['requester', 'assignee']), 201);
    }

    public function show(SupportTicket $ticket)
    {
        return $ticket->load(['requester', 'assignee', 'messages.author', 'order.items', 'shop', 'payment', 'shipment', 'returnRequest', 'dispute', 'deliveryIncident']);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket)
    {
        $ticket->update($request->validated());
        return $ticket->fresh(['requester', 'assignee']);
    }


    public function reply(StoreSupportMessageRequest $request, SupportTicket $ticket)
    {
        $message = $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'author_type' => 'staff',
            'body' => $request->validated('body'),
            'is_internal_note' => $request->boolean('is_internal_note'),
        ]);

        $updates = [];
        if (! $message->is_internal_note) {
            $updates['status'] = 'in_progress';
        }
        if (! $ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        if ($updates !== []) {
            $ticket->update($updates);
        }

        return response()->json($message->load('author'), 201);
    }
}
