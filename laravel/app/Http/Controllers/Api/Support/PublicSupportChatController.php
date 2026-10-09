<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportCallbackRequest;
use App\Models\SupportConversation;
use App\Services\Support\SupportCaseService;
use App\Services\SupportAi\AssistantShoppingService;
use App\Services\SupportAi\SupportAiOrchestrator;
use App\Services\SupportAi\SupportIdentityResolver;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicSupportChatController extends Controller
{
    /**
     * Identité publique unique montrée au client. Les relais internes vers
     * Rita/Technique/Logistique/Salomé (voir SupportAiOrchestrator) ne
     * doivent jamais transparaître ici : le client parle toujours à N'Nan.
     */
    private const PUBLIC_AGENT_IDENTITY = [
        'name' => 'Miss N’Nan',
        'slug' => 'miss-nnan',
        'role_key' => 'general',
    ];

    public function start(
        Request $request,
        SupportAiOrchestrator $orchestrator,
        SupportServiceStatusService $services,
        SupportCaseService $cases,
        AssistantShoppingService $shopping,
    ) {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', Rule::in(['chat', 'whatsapp', 'email'])],
            'service' => ['nullable', Rule::in(['client', 'commercial', 'logistique', 'technique'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'tracking_reference' => ['nullable', 'string', 'max:100'],
            'source_app' => ['nullable', Rule::in(['client_mobile', 'vendor_mobile'])],
        ]);
        $sourceApp = $data['source_app'] ?? 'client_mobile';
        $requesterType = $sourceApp === 'vendor_mobile' ? 'vendor' : 'client';

        $channel = $data['channel'] ?? 'chat';
        $channelStatus = $services->channel($channel);
        if (! $channelStatus['operational']) {
            return response()->json([
                'message' => $channelStatus['message'],
                'channel' => $channel,
            ], 422);
        }

        $user = $request->user('sanctum');
        $requester = $cases->requesterFor($user, $requesterType);
        $conversation = SupportConversation::create([
            'support_requester_id' => $requester->id,
            'requester_user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'requester_phone' => $data['phone'] ?? $user->phone,
            'channel' => $channel,
            'source_app' => $sourceApp,
            'status' => 'active',
            'subject' => $data['subject'] ?? ($requesterType === 'vendor' ? 'Assistance vendeur OVANIE' : 'Assistance '.($data['service'] ?? 'client').' OVANIE'),
            'metadata' => [
                'service' => $data['service'] ?? 'client',
                'requester_type' => $requesterType,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        if ($shop = $this->shoppingReply($shopping, $conversation, $data['message'])) {
            return response()->json(['conversation_token' => $conversation->public_token] + $shop, 201);
        }

        $reply = $orchestrator->receiveCustomerMessage($conversation, $data['message'], $user, [
            'order_reference' => $data['order_reference'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'tracking_reference' => $data['tracking_reference'] ?? null,
        ]);
        $conversation->refresh()->load(['ticket', 'commercialLead', 'deliveryIncident']);

        return response()->json([
            'products' => [],
            'conversation_token' => $conversation->public_token,
            'status' => $conversation->status,
            'agent' => self::PUBLIC_AGENT_IDENTITY,
            'reply' => $reply->body,
            'requires_human' => (bool) $conversation->requires_human,
            'account_linked' => (bool) $conversation->requester_user_id,
            'ticket_reference' => $conversation->ticket?->reference,
            'commercial_follow_up' => (bool) $conversation->commercialLead,
            'logistics_follow_up' => (bool) $conversation->deliveryIncident,
        ], 201);
    }

    /**
     * Demande utilisateur : à l'ouverture du panneau, proposer un choix
     * explicite entre "reprendre la dernière conversation" et "nouvelle
     * conversation" plutôt que de toujours repartir de zéro ou de reprendre
     * automatiquement en silence.
     */
    public function latest(Request $request)
    {
        $conversation = SupportConversation::query()
            ->where('requester_user_id', $request->user('sanctum')->id)
            ->when($request->query('source_app'), fn ($query, $app) => $query->where('source_app', $app))
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('updated_at')
            ->first();

        if (! $conversation) {
            return response()->json(['exists' => false]);
        }

        $lastMessage = $conversation->messages()
            ->where('is_internal', false)
            ->latest()
            ->first();

        return response()->json([
            'exists' => true,
            'conversation_token' => $conversation->public_token,
            'updated_at' => $conversation->updated_at,
            'preview' => $lastMessage?->body,
        ]);
    }

    public function show(Request $request, string $token)
    {
        $conversation = $this->conversation($token, $request);
        $conversation->load([
            'ticket:id,reference,status',
            'messages' => fn ($query) => $query->where('is_internal', false)->latest()->limit(50),
        ]);

        return response()->json([
            'status' => $conversation->status,
            'agent' => self::PUBLIC_AGENT_IDENTITY,
            'ticket' => $conversation->ticket,
            'requires_human' => $conversation->requires_human,
            'account_linked' => (bool) $conversation->requester_user_id,
            'messages' => $conversation->messages->sortBy('created_at')->values()->map(fn ($message) => [
                'sender' => $message->sender_type,
                'body' => $message->body,
                // Fiches produits montrées avec ce message : elles restent visibles quand la conversation est rouverte.
                'products' => array_values((array) data_get($message->metadata, 'products', [])),
                'created_at' => $message->created_at,
            ]),
        ]);
    }

    public function message(Request $request, string $token, SupportAiOrchestrator $orchestrator, AssistantShoppingService $shopping)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'order_reference' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'tracking_reference' => ['nullable', 'string', 'max:100'],
        ]);
        $conversation = $this->conversation($token, $request);

        if (in_array($conversation->status, ['resolved', 'closed'], true)) {
            return response()->json(['message' => 'Cette conversation est clôturée.'], 409);
        }

        if ($shop = $this->shoppingReply($shopping, $conversation, $data['message'])) {
            return response()->json($shop);
        }

        $reply = $orchestrator->receiveCustomerMessage($conversation, $data['message'], $request->user('sanctum'), [
            'order_reference' => $data['order_reference'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'tracking_reference' => $data['tracking_reference'] ?? null,
        ]);
        $conversation->refresh()->load(['ticket', 'commercialLead', 'deliveryIncident']);

        return response()->json([
            'products' => [],
            'status' => $conversation->status,
            'agent' => self::PUBLIC_AGENT_IDENTITY,
            'reply' => $reply->body,
            'requires_human' => $conversation->requires_human,
            'account_linked' => (bool) $conversation->requester_user_id,
            'ticket_reference' => $conversation->ticket?->reference,
            'commercial_follow_up' => (bool) $conversation->commercialLead,
            'logistics_follow_up' => (bool) $conversation->deliveryIncident,
        ]);
    }

    public function callback(Request $request, string $token, SupportIdentityResolver $identities)
    {
        $conversation = $this->conversation($token, $request);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:3000'],
            'preferred_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $match = $identities->resolve($request->user('sanctum'), $data['email'] ?? null, $data['phone']);
        $callback = SupportCallbackRequest::create([
            'support_conversation_id' => $conversation->id,
            'support_ticket_id' => $conversation->support_ticket_id,
            'requester_user_id' => $conversation->requester_user_id ?: $match['user']?->id,
            'requester_name' => $data['name'] ?? $conversation->requester_name ?? $match['user']?->name,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? $conversation->requester_email ?? $match['user']?->email,
            'reason' => $data['reason'] ?? 'Rappel demandé depuis la conversation en ligne.',
            'preferred_at' => $data['preferred_at'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'reference' => $callback->reference,
            'status' => $callback->status,
            'message' => 'Votre demande de rappel a été enregistrée.',
        ], 201);
    }

    /**
     * Achats dans la conversation : ajout au panier ou recherche d'un produit.
     * La demande et la réponse sont gardées dans la conversation, avec les
     * fiches produits affichées. L'ajout au panier lui-même est exécuté par le
     * site (panier habituel), d'après `cart_action`.
     *
     * @return array<string, mixed>|null null si le message ne concerne pas un achat
     */
    private function shoppingReply(AssistantShoppingService $shopping, SupportConversation $conversation, string $message): ?array
    {
        if (in_array($conversation->status, ['human'], true) || $conversation->requires_human) {
            return null;
        }

        $cart = $shopping->cartRequest($message, $conversation);
        $catalog = $cart ? null : $shopping->catalogRequest($message, $conversation);
        if (! $cart && ! $catalog) {
            return null;
        }

        $reply = $cart['reply'] ?? $catalog['reply'];
        $products = $cart
            ? ($cart['products'] ?? (($cart['status'] ?? null) === 'add' ? [] : (isset($cart['product']) ? [$cart['product']] : [])))
            : $catalog['products'];
        $cartAction = $cart && $cart['status'] === 'add'
            ? ['type' => 'add_to_cart', 'product_id' => $cart['product']['id'], 'quantity' => $cart['quantity'], 'name' => $cart['product']['name']]
            : null;

        $conversation->messages()->create([
            'sender_type' => 'customer',
            'sender_user_id' => $conversation->requester_user_id,
            'body' => $message,
            'provider' => $conversation->channel,
        ]);
        $conversation->messages()->create([
            'sender_type' => 'ai',
            'body' => $reply,
            'provider' => 'assistant-shopping',
            'metadata' => ['products' => $products, 'cart_action' => $cartAction],
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return [
            'status' => $conversation->status,
            'agent' => self::PUBLIC_AGENT_IDENTITY,
            'reply' => $reply,
            'products' => $products,
            'cart_action' => $cartAction,
            'requires_human' => (bool) $conversation->requires_human,
            'account_linked' => (bool) $conversation->requester_user_id,
        ];
    }

    private function conversation(string $token, Request $request): SupportConversation
    {
        return SupportConversation::query()
            ->where('public_token', $token)
            ->where('requester_user_id', $request->user('sanctum')->id)
            ->firstOrFail();
    }
}
