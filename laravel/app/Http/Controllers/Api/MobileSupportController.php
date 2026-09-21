<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnModel;
use App\Models\SupportConversation;
use App\Models\SupportKnowledgeArticle;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileSupportController extends Controller
{
    public function index(Request $request, SupportServiceStatusService $services): JsonResponse
    {
        $user = $request->user();

        $articles = Schema::hasTable('support_knowledge_articles')
            ? SupportKnowledgeArticle::query()
                ->availableToAi()
                ->latest('published_at')
                ->limit(30)
                ->get(['id', 'title', 'slug', 'category', 'content', 'published_at', 'updated_at'])
            : collect();

        $tickets = Schema::hasTable('support_tickets')
            ? SupportTicket::query()
                ->where('requester_user_id', $user->id)
                ->withCount(['messages' => fn ($query) => $query->where('is_internal_note', false)])
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $conversations = Schema::hasTable('support_conversations')
            ? SupportConversation::query()
                ->where('requester_user_id', $user->id)
                ->with('aiAgent:id,name,slug,role_key')
                ->latest('last_message_at')
                ->limit(30)
                ->get()
            : collect();

        $status = $services->all();
        $telephony = $status['telephony'] ?? ['operational' => false];
        $whatsapp = $status['whatsapp'] ?? ['operational' => false];
        $whatsappNumber = preg_replace('/\D+/', '', (string) config('support_ai.whatsapp_public_number')) ?: null;
        $whatsappAvailable = (bool) ($whatsapp['operational'] ?? false) && filled($whatsappNumber);

        return response()->json([
            'data' => [
                'services' => collect($status)->map(fn ($item) => [
                    'key' => $item['key'] ?? '',
                    'label' => $item['label'] ?? '',
                    'configured' => (bool) ($item['configured'] ?? false),
                    'operational' => (bool) ($item['operational'] ?? false),
                    'provider' => $item['provider'] ?? null,
                    'message' => $item['message'] ?? '',
                ])->values(),
                'phone' => (bool) ($telephony['operational'] ?? false)
                    ? (string) config('support_ai.support_phone')
                    : null,
                'phone_rate_notice' => (bool) ($telephony['operational'] ?? false)
                    ? (string) config('support_ai.local_rate_notice')
                    : null,
                // Aucun faux numéro : WhatsApp n'est proposé que si le canal est
                // opérationnel ET qu'un numéro public est explicitement configuré.
                'whatsapp_available' => $whatsappAvailable,
                'whatsapp_number' => $whatsappAvailable ? $whatsappNumber : null,
                'whatsapp_url' => $whatsappAvailable ? "https://wa.me/{$whatsappNumber}" : null,
                'faqs' => $articles->map(fn (SupportKnowledgeArticle $article) => [
                    'id' => (int) $article->id,
                    'title' => (string) $article->title,
                    'category' => (string) ($article->category ?? 'general'),
                    'content' => (string) $article->content,
                    'published_at' => $article->published_at?->toIso8601String(),
                    'updated_at' => $article->updated_at?->toIso8601String(),
                ])->values(),
                'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->ticketSummary($ticket))->values(),
                'conversations' => $conversations->map(fn (SupportConversation $conversation) => [
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
            ],
        ]);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('support_tickets'), 503, 'Le centre de tickets support est indisponible.');

        $data = $request->validate([
            'category' => ['required', Rule::in(['general', 'orders', 'payments', 'delivery', 'returns', 'account', 'technical'])],
            'subject' => ['required', 'string', 'min:4', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
            'return_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:4'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt', 'max:5120'],
        ]);

        $user = $request->user();
        $orderId = null;
        $returnId = null;

        if (! empty($data['order_id'])) {
            $orderId = Order::query()
                ->operational()
                ->where('client_id', $user->id)
                ->whereKey($data['order_id'])
                ->value('id');
            abort_unless($orderId, 422, 'La commande sélectionnée ne vous appartient pas.');
        }

        if (! empty($data['return_id'])) {
            $returnId = ReturnModel::query()
                ->where('client_id', $user->id)
                ->whereKey($data['return_id'])
                ->value('id');
            abort_unless($returnId, 422, 'Le dossier de retour sélectionné ne vous appartient pas.');
        }

        $ticket = SupportTicket::create([
            'requester_user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'requester_phone' => $user->phone ?: $user->whatsapp_phone,
            'channel' => 'mobile',
            'category' => $data['category'],
            'priority' => 'normal',
            'status' => 'open',
            'team' => 'support',
            'subject' => trim((string) $data['subject']),
            'description' => trim((string) $data['description']),
            'created_by' => $user->id,
            'order_id' => $orderId,
            'return_id' => $returnId,
        ]);

        if (Schema::hasTable('support_ticket_messages')) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'author_type' => 'client',
                'body' => trim((string) $data['description']),
                'is_internal_note' => false,
                'attachments' => $this->storeAttachments($request, $ticket),
            ]);
        }

        return response()->json([
            'message' => 'Votre ticket support a été créé.',
            'data' => $this->ticketSummary($ticket->fresh()->loadCount('messages')),
        ], 201);
    }

    public function showTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        $ticket->load([
            'messages' => fn ($query) => $query->where('is_internal_note', false)->oldest(),
            'order:id,order_number',
            'returnRequest:id,order_reference,product_name,status,refund_amount',
        ]);

        return response()->json([
            'data' => [
                ...$this->ticketSummary($ticket),
                'description' => (string) $ticket->description,
                'order' => $ticket->order ? [
                    'id' => (int) $ticket->order->id,
                    'order_number' => (string) $ticket->order->order_number,
                ] : null,
                'return' => $ticket->returnRequest ? [
                    'id' => (int) $ticket->returnRequest->id,
                    'order_reference' => (string) $ticket->returnRequest->order_reference,
                    'product_name' => (string) $ticket->returnRequest->product_name,
                    'status' => (string) $ticket->returnRequest->status,
                    'refund_amount' => $ticket->returnRequest->refund_amount !== null
                        ? (float) $ticket->returnRequest->refund_amount
                        : null,
                ] : null,
                'messages' => $ticket->messages->map(
                    fn (SupportTicketMessage $message) => $this->messagePayload($ticket, $message)
                )->values(),
            ],
        ]);
    }

    public function replyTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        if (in_array((string) $ticket->status, ['closed', 'cancelled'], true)) {
            return response()->json(['message' => 'Ce ticket est clôturé et ne peut plus recevoir de message.'], 409);
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:4', 'required_without:message'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt', 'max:5120'],
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'author_type' => 'client',
            'body' => trim((string) ($data['message'] ?? '')),
            'is_internal_note' => false,
            'attachments' => $this->storeAttachments($request, $ticket),
        ]);

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return response()->json([
            'message' => 'Message envoyé au support OVANIE.',
            'data' => $this->messagePayload($ticket, $message),
        ], 201);
    }

    public function downloadAttachment(
        Request $request,
        SupportTicket $ticket,
        SupportTicketMessage $message,
        int $index
    ): StreamedResponse {
        $this->authorizeTicket($request, $ticket);
        abort_unless((int) $message->support_ticket_id === (int) $ticket->id, 404);

        $attachments = is_array($message->attachments) ? array_values($message->attachments) : [];
        abort_unless(array_key_exists($index, $attachments), 404, 'Pièce jointe introuvable.');
        $attachment = $attachments[$index];
        abort_unless(is_array($attachment) && filled($attachment['path'] ?? null), 404, 'Cette pièce jointe ne possède pas de fichier privé téléchargeable.');

        $path = (string) $attachment['path'];
        abort_unless(Storage::disk('local')->exists($path), 404, 'Le fichier de cette pièce jointe est introuvable.');

        $name = trim((string) ($attachment['name'] ?? basename($path))) ?: basename($path);
        return Storage::disk('local')->download($path, $name);
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless((int) $ticket->requester_user_id === (int) $request->user()->id, 403);
    }

    private function storeAttachments(Request $request, SupportTicket $ticket): array
    {
        $stored = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store("support-ticket-attachments/{$ticket->id}", 'local');
            if (! $path) {
                continue;
            }
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }
        return $stored;
    }

    private function messagePayload(SupportTicket $ticket, SupportTicketMessage $message): array
    {
        $attachments = [];
        foreach (array_values(is_array($message->attachments) ? $message->attachments : []) as $index => $attachment) {
            if (is_string($attachment)) {
                // Compatibilité avec d'anciens tickets qui stockaient une URL.
                $attachments[] = [
                    'name' => basename(parse_url($attachment, PHP_URL_PATH) ?: $attachment),
                    'mime' => null,
                    'size' => null,
                    'url' => $attachment,
                    'download_endpoint' => null,
                ];
                continue;
            }
            if (! is_array($attachment)) {
                continue;
            }
            $attachments[] = [
                'name' => (string) ($attachment['name'] ?? 'Pièce jointe'),
                'mime' => $attachment['mime'] ?? null,
                'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                'url' => isset($attachment['url']) ? (string) $attachment['url'] : null,
                'download_endpoint' => filled($attachment['path'] ?? null)
                    ? "/mobile/client/support/tickets/{$ticket->id}/messages/{$message->id}/attachments/{$index}"
                    : null,
            ];
        }

        return [
            'id' => (int) $message->id,
            'author_type' => (string) $message->author_type,
            'body' => (string) $message->body,
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function ticketSummary(SupportTicket $ticket): array
    {
        return [
            'id' => (int) $ticket->id,
            'reference' => (string) $ticket->reference,
            'subject' => (string) $ticket->subject,
            'category' => (string) $ticket->category,
            'priority' => (string) $ticket->priority,
            'status' => (string) $ticket->status,
            'channel' => (string) $ticket->channel,
            'messages_count' => isset($ticket->messages_count) ? (int) $ticket->messages_count : null,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }
}
