<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\SupportAgentHandoff;
use App\Models\SupportConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialSupportTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = trim((string) $request->query('status'));
        $query = SupportAgentHandoff::query()
            ->where('target_department', 'commercial')
            ->with(['conversation.requesterProfile', 'conversation.requester', 'ticket', 'assignee'])
            ->latest('requested_at');
        if ($status !== '') $query->where('status', $status);
        if ($request->boolean('mine')) $query->where('assigned_to', $user->id);
        else $query->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', $user->id));

        return response()->json(['data' => $query->limit(100)->get()->map(fn ($handoff) => $this->payload($handoff, false, (int) $user->id))->values()]);
    }

    public function show(Request $request, SupportAgentHandoff $handoff): JsonResponse
    {
        $this->authorizeHandoff($request, $handoff);
        $handoff->load(['conversation.messages.aiAgent', 'conversation.requesterProfile', 'conversation.requester', 'ticket', 'commercialLead', 'assignee']);
        return response()->json(['data' => $this->payload($handoff, true, (int) $request->user()->id)]);
    }

    public function claim(Request $request, SupportAgentHandoff $handoff): JsonResponse
    {
        $this->authorizeHandoff($request, $handoff, allowUnassigned: true);
        $user = $request->user();
        DB::transaction(function () use ($handoff, $user) {
            $locked = SupportAgentHandoff::query()->lockForUpdate()->findOrFail($handoff->id);
            abort_if($locked->assigned_to && (int) $locked->assigned_to !== (int) $user->id, 409, 'Ce dossier a déjà été pris en charge.');
            abort_if(in_array($locked->status, ['resolved', 'cancelled'], true), 409, 'Ce dossier est déjà clôturé.');
            $locked->update([
                'assigned_to' => $user->id,
                'assigned_at' => $locked->assigned_at ?: now(),
                'accepted_at' => $locked->accepted_at ?: now(),
                'status' => 'in_progress',
            ]);
        });
        return response()->json(['message' => 'Dossier commercial pris en charge.', 'data' => $this->payload($handoff->fresh(['conversation', 'ticket', 'assignee']), false, (int) $request->user()->id)]);
    }

    public function message(Request $request, SupportAgentHandoff $handoff): JsonResponse
    {
        $this->authorizeHandoff($request, $handoff);
        abort_unless((int) $handoff->assigned_to === (int) $request->user()->id, 403, 'Prenez d’abord ce dossier en charge.');
        abort_unless($handoff->conversation, 422, 'Aucune conversation n’est liée à ce transfert.');
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:5000']]);
        $message = SupportConversationMessage::create([
            'support_conversation_id' => $handoff->conversation->id,
            'sender_type' => 'human',
            'sender_user_id' => $request->user()->id,
            'body' => trim($data['message']),
            'format' => 'text',
            'is_internal' => false,
            'metadata' => ['source' => 'commercial_mobile', 'handoff_id' => $handoff->id],
        ]);
        $handoff->conversation->update(['last_message_at' => now(), 'status' => 'human']);
        return response()->json(['message' => 'Message envoyé.', 'data' => ['id' => $message->id, 'body' => $message->body, 'created_at' => $message->created_at?->toIso8601String()]], 201);
    }

    public function resolve(Request $request, SupportAgentHandoff $handoff): JsonResponse
    {
        $this->authorizeHandoff($request, $handoff);
        abort_unless((int) $handoff->assigned_to === (int) $request->user()->id, 403, 'Seul le commercial en charge peut clôturer ce dossier.');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $handoff->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'completed_by' => $request->user()->id,
            'resolution_code' => 'commercial_completed',
            'notes' => trim((string) ($data['note'] ?? '')) ?: $handoff->notes,
        ]);
        return response()->json(['message' => 'Dossier commercial clôturé.', 'data' => $this->payload($handoff->fresh(['conversation', 'ticket', 'assignee']), false, (int) $request->user()->id)]);
    }

    private function authorizeHandoff(Request $request, SupportAgentHandoff $handoff, bool $allowUnassigned = false): void
    {
        abort_unless($handoff->target_department === 'commercial', 404);
        if (! $allowUnassigned || $handoff->assigned_to) {
            abort_unless(! $handoff->assigned_to || (int) $handoff->assigned_to === (int) $request->user()->id, 403);
        }
    }

    private function payload(SupportAgentHandoff $handoff, bool $withMessages = false, ?int $userId = null): array
    {
        $conversation = $handoff->conversation;
        $requester = $conversation?->requesterProfile;
        $payload = [
            'id' => (int) $handoff->id,
            'reference' => (string) $handoff->reference,
            'status' => (string) $handoff->status,
            'severity' => (string) $handoff->severity,
            'queue' => (string) $handoff->queue_key,
            'reason' => (string) $handoff->reason,
            'notes' => (string) ($handoff->notes ?? ''),
            'requested_at' => $handoff->requested_at?->toIso8601String(),
            'due_at' => $handoff->due_at?->toIso8601String(),
            'assigned_to_me' => $userId !== null && (int) $handoff->assigned_to === $userId,
            'requester' => [
                'type' => $requester?->requester_type ?: 'client',
                'name' => $requester?->name ?: $conversation?->requester?->name ?: $conversation?->requester_name,
                'email' => $requester?->email ?: $conversation?->requester_email,
                'phone' => $requester?->phone ?: $conversation?->requester_phone,
            ],
            'ticket' => $handoff->ticket ? ['id' => $handoff->ticket->id, 'reference' => $handoff->ticket->reference, 'subject' => $handoff->ticket->subject] : null,
            'conversation' => $conversation ? ['id' => $conversation->id, 'subject' => $conversation->subject, 'summary' => $conversation->summary, 'status' => $conversation->status] : null,
        ];
        if ($withMessages && $conversation) {
            $payload['messages'] = $conversation->messages->where('is_internal', false)->map(fn ($m) => [
                'id' => (int) $m->id,
                'sender_type' => (string) $m->sender_type,
                'body' => (string) $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values();
        }
        return $payload;
    }
}
