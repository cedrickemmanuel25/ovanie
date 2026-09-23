<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\SupportConversation;
use App\Models\SupportKnowledgeArticle;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportCaseService;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnifiedMobileSupportController extends Controller
{
    public function __construct(private readonly SupportCaseService $cases) {}

    public function index(Request $request, SupportServiceStatusService $services): JsonResponse
    {
        [$actor, $type] = $this->actor($request);
        $requester = Schema::hasTable('support_requesters') ? $this->cases->requesterFor($actor, $type) : null;

        $articles = Schema::hasTable('support_knowledge_articles')
            ? SupportKnowledgeArticle::query()->availableToAi()->latest('published_at')->limit(30)->get([
                'id', 'title', 'slug', 'category', 'content', 'published_at', 'updated_at',
            ])
            : collect();

        $tickets = Schema::hasTable('support_tickets')
            ? SupportTicket::query()
                ->where(function ($query) use ($actor, $requester) {
                    if ($requester) {
                        $query->where('support_requester_id', $requester->id);
                        if ($actor instanceof User) $query->orWhere('requester_user_id', $actor->id);
                        return;
                    }
                    if ($actor instanceof User) {
                        $query->where('requester_user_id', $actor->id);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->withCount(['messages' => fn ($q) => $q->where('is_internal_note', false)])
                ->latest()->limit(40)->get()
            : collect();

        $conversations = Schema::hasTable('support_conversations')
            ? SupportConversation::query()
                ->where(function ($query) use ($actor, $requester) {
                    if ($requester) {
                        $query->where('support_requester_id', $requester->id);
                        if ($actor instanceof User) $query->orWhere('requester_user_id', $actor->id);
                        return;
                    }
                    if ($actor instanceof User) {
                        $query->where('requester_user_id', $actor->id);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->with(['aiAgent:id,name,slug,role_key', 'ticket:id,reference'])
                ->latest('last_message_at')->limit(30)->get()
            : collect();

        $status = $services->all();
        $telephony = $status['telephony'] ?? ['operational' => false];
        $whatsapp = $status['whatsapp'] ?? ['operational' => false];
        $whatsappNumber = preg_replace('/\D+/', '', (string) config('support_ai.whatsapp_public_number')) ?: null;
        $whatsappAvailable = (bool) ($whatsapp['operational'] ?? false) && filled($whatsappNumber);

        return response()->json(['data' => [
            'requester' => $requester ? [
                'type' => $requester->requester_type,
                'type_label' => $requester->type_label,
                'name' => $requester->name,
                'email' => $requester->email,
                'phone' => $requester->phone,
            ] : ['type' => $type],
            'services' => collect($status)->map(fn ($item) => [
                'key' => $item['key'] ?? '', 'label' => $item['label'] ?? '',
                'configured' => (bool) ($item['configured'] ?? false),
                'operational' => (bool) ($item['operational'] ?? false),
                'provider' => $item['provider'] ?? null, 'message' => $item['message'] ?? '',
            ])->values(),
            'phone' => (bool) ($telephony['operational'] ?? false) ? (string) config('support_ai.support_phone') : null,
            'phone_rate_notice' => (bool) ($telephony['operational'] ?? false) ? (string) config('support_ai.local_rate_notice') : null,
            'whatsapp_available' => $whatsappAvailable,
            'whatsapp_number' => $whatsappAvailable ? $whatsappNumber : null,
            'whatsapp_url' => $whatsappAvailable ? "https://wa.me/{$whatsappNumber}" : null,
            'faqs' => $articles->map(fn ($article) => [
                'id' => (int) $article->id, 'title' => (string) $article->title,
                'category' => (string) ($article->category ?? 'general'), 'content' => (string) $article->content,
                'published_at' => $article->published_at?->toIso8601String(), 'updated_at' => $article->updated_at?->toIso8601String(),
            ])->values(),
            'tickets' => $tickets->map(fn ($ticket) => $this->ticketSummary($ticket))->values(),
            'conversations' => $conversations->map(fn ($conversation) => [
                'token' => (string) $conversation->public_token,
                'subject' => (string) ($conversation->subject ?? 'Assistance OVANIE'),
                'status' => (string) $conversation->status,
                'channel' => (string) $conversation->channel,
                'requires_human' => (bool) $conversation->requires_human,
                'agent' => $conversation->aiAgent ? [
                    'name' => (string) $conversation->aiAgent->name,
                    'slug' => (string) $conversation->aiAgent->slug,
                    'role_key' => (string) $conversation->aiAgent->role_key,
                ] : null,
                'ticket_reference' => $conversation->ticket?->reference,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('support_requesters') && Schema::hasTable('support_context_links'), 503, 'Le nouveau centre Support doit être migré avant utilisation.');
        [$actor, $type, $sourceApp] = $this->actor($request, true);

        $data = $request->validate([
            'category' => ['required', Rule::in([
                'general', 'orders', 'payments', 'delivery', 'returns', 'account', 'technical',
                'shop', 'products', 'payouts', 'mission', 'earnings', 'commercial',
            ])],
            'subject' => ['required', 'string', 'min:4', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'contexts' => ['nullable', 'array', 'max:8'],
            'contexts.*.type' => ['required_with:contexts', 'string', 'max:50'],
            'contexts.*.id' => ['required_with:contexts', 'integer', 'min:1'],
            // Compatibilité avec l'ancienne application Client.
            'order_id' => ['nullable', 'integer', 'min:1'],
            'return_id' => ['nullable', 'integer', 'min:1'],
            'payment_id' => ['nullable', 'integer', 'min:1'],
            'shipment_id' => ['nullable', 'integer', 'min:1'],
            'mission_number' => ['nullable', 'string', 'max:80'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt', 'max:5120'],
        ]);

        $contexts = (array) ($data['contexts'] ?? []);
        foreach (['order_id' => 'order', 'return_id' => 'return', 'payment_id' => 'payment', 'shipment_id' => 'shipment'] as $key => $contextType) {
            if (! empty($data[$key])) $contexts[] = ['type' => $contextType, 'id' => (int) $data[$key]];
        }
        if ($actor instanceof DeliveryDriver && filled($data['mission_number'] ?? null)) {
            $assignment = DeliveryAssignment::query()
                ->where('driver_id', $actor->id)
                ->where('mission_number', trim((string) $data['mission_number']))
                ->first();
            if (! $assignment) {
                return response()->json(['message' => 'Mission introuvable pour ce compte livreur.'], 422);
            }
            $contexts[] = ['type' => 'delivery_assignment', 'id' => (int) $assignment->id];
        }
        $data['contexts'] = $contexts;

        $ticket = $this->cases->createTicket($actor, $type, $sourceApp, $data, $this->storeAttachments($request));

        return response()->json([
            'message' => 'Votre demande a été transmise au Support OVANIE.',
            'data' => $this->ticketSummary($ticket),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        [$actor, $type] = $this->actor($request);
        abort_unless($this->cases->actorOwnsTicket($actor, $ticket, $type), 403);

        $ticket->load([
            'requesterProfile',
            'contextLinks' => fn ($q) => $q->orderByDesc('is_primary')->oldest(),
            'messages' => fn ($q) => $q->where('is_internal_note', false)->oldest(),
        ]);

        return response()->json(['data' => [
            ...$this->ticketSummary($ticket),
            'description' => (string) $ticket->description,
            'requester' => $ticket->requesterProfile ? [
                'type' => $ticket->requesterProfile->requester_type,
                'type_label' => $ticket->requesterProfile->type_label,
                'name' => $ticket->requesterProfile->name,
            ] : null,
            'contexts' => $ticket->contextLinks->map(fn ($link) => [
                'type' => $link->context_type,
                'id' => (int) $link->context_id,
                'primary' => (bool) $link->is_primary,
            ])->values(),
            'messages' => $ticket->messages->map(fn ($message) => $this->messagePayload($request, $ticket, $message))->values(),
        ]]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        [$actor, $type] = $this->actor($request);
        abort_unless($this->cases->actorOwnsTicket($actor, $ticket, $type), 403);
        if (in_array((string) $ticket->status, ['closed', 'cancelled'], true)) {
            return response()->json(['message' => 'Ce dossier est clôturé et ne peut plus recevoir de message.'], 409);
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:4', 'required_without:message'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt', 'max:5120'],
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'author_id' => $actor instanceof User ? $actor->id : null,
            'author_type' => $type,
            'body' => trim((string) ($data['message'] ?? '')),
            'is_internal_note' => false,
            'attachments' => $this->storeAttachments($request, $ticket),
        ]);

        $this->cases->appendConversationMessageForTicket(
            $ticket->fresh(['requesterProfile']),
            trim((string) ($data['message'] ?? '')),
            'customer',
            $actor instanceof User ? $actor : null,
            false,
            ['requester_type' => $type, 'source_app' => $ticket->source_app],
        );

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return response()->json([
            'message' => 'Message envoyé au Support OVANIE.',
            'data' => $this->messagePayload($request, $ticket, $message),
        ], 201);
    }

    public function download(Request $request, SupportTicket $ticket, SupportTicketMessage $message, int $index): StreamedResponse
    {
        [$actor, $type] = $this->actor($request);
        abort_unless($this->cases->actorOwnsTicket($actor, $ticket, $type), 403);
        abort_unless((int) $message->support_ticket_id === (int) $ticket->id, 404);
        $attachments = is_array($message->attachments) ? array_values($message->attachments) : [];
        abort_unless(array_key_exists($index, $attachments), 404, 'Pièce jointe introuvable.');
        $attachment = $attachments[$index];
        abort_unless(is_array($attachment) && filled($attachment['path'] ?? null), 404);
        $path = (string) $attachment['path'];
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path, trim((string) ($attachment['name'] ?? basename($path))) ?: basename($path));
    }

    private function actor(Request $request, bool $includeSource = false): array
    {
        $actor = $request->user();
        abort_unless($actor instanceof Authenticatable, 401);
        $type = (string) ($request->route('support_requester_type') ?: ($actor instanceof DeliveryDriver ? 'driver' : 'client'));
        $source = (string) ($request->route('support_source_app') ?: ($type.'_mobile'));
        return $includeSource ? [$actor, $type, $source] : [$actor, $type];
    }

    private function storeAttachments(Request $request, ?SupportTicket $ticket = null): array
    {
        $stored = [];
        $folder = $ticket ? "support-ticket-attachments/{$ticket->id}" : 'support-ticket-attachments/pending/'.date('Ymd');
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) continue;
            $path = $file->store($folder, 'local');
            if (! $path) continue;
            $stored[] = [
                'name' => $file->getClientOriginalName(), 'path' => $path,
                'mime' => $file->getClientMimeType(), 'size' => (int) $file->getSize(),
            ];
        }
        return $stored;
    }

    private function messagePayload(Request $request, SupportTicket $ticket, SupportTicketMessage $message): array
    {
        $type = (string) ($request->route('support_requester_type') ?: 'client');
        $base = match ($type) {
            'vendor' => '/api/mobile/v1/vendor/support',
            'commercial' => '/api/mobile/v1/commercial/support',
            'driver' => '/api/driver/support',
            default => '/api/mobile/client/support',
        };
        $attachments = [];
        foreach (array_values(is_array($message->attachments) ? $message->attachments : []) as $index => $attachment) {
            if (is_string($attachment)) {
                $attachments[] = ['name' => basename(parse_url($attachment, PHP_URL_PATH) ?: $attachment), 'mime' => null, 'size' => null, 'url' => $attachment, 'download_endpoint' => null];
                continue;
            }
            if (! is_array($attachment)) continue;
            $attachments[] = [
                'name' => (string) ($attachment['name'] ?? 'Pièce jointe'),
                'mime' => $attachment['mime'] ?? null,
                'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                'url' => isset($attachment['url']) ? (string) $attachment['url'] : null,
                'download_endpoint' => filled($attachment['path'] ?? null)
                    ? "{$base}/tickets/{$ticket->id}/messages/{$message->id}/attachments/{$index}"
                    : null,
            ];
        }
        return [
            'id' => (int) $message->id, 'author_type' => (string) $message->author_type,
            'body' => (string) $message->body, 'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function ticketSummary(SupportTicket $ticket): array
    {
        return [
            'id' => (int) $ticket->id, 'reference' => (string) $ticket->reference,
            'subject' => (string) $ticket->subject, 'category' => (string) $ticket->category,
            'priority' => (string) $ticket->priority, 'status' => (string) $ticket->status,
            'channel' => (string) $ticket->channel, 'source_app' => (string) ($ticket->source_app ?? ''),
            'messages_count' => isset($ticket->messages_count) ? (int) $ticket->messages_count : null,
            'created_at' => $ticket->created_at?->toIso8601String(), 'updated_at' => $ticket->updated_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(), 'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }
}
