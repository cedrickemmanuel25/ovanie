<?php

namespace App\Services\SupportAi;

use App\Models\Category;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\SupportConversation;
use App\Models\SupportKnowledgeArticle;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ClaudeSupportToolExecutor
{
    /**
     * Exécute UNIQUEMENT l'outil demandé par Claude.
     * Aucune autre donnée de la plateforme n'est chargée implicitement.
     *
     * @return array<string,mixed>
     */
    public function execute(
        string $tool,
        array $input,
        SupportConversation $conversation,
        ?int $currentMessageId = null,
    ): array {
        $conversation->loadMissing('requester');

        return match ($tool) {
            'get_previous_exchange' => $this->previousExchange($conversation, $currentMessageId),
            'get_requester_account' => $this->requester($conversation),
            'search_knowledge' => $this->knowledge((string) ($input['query'] ?? '')),
            'search_catalog' => $this->catalog((string) ($input['query'] ?? '')),
            'get_shop' => $this->shop($conversation->requester),
            'find_orders' => $this->orders($conversation->requester, $input),
            'find_payment' => $this->payment($conversation->requester, $input),
            'find_shipment' => $this->shipment($conversation->requester, $input),
            'find_returns' => $this->returns($conversation->requester, $input),
            'find_disputes' => $this->disputes($conversation->requester, $input),
            'find_tickets' => $this->tickets($conversation->requester, $input),
            default => [
                'ok' => false,
                'error' => 'unknown_tool',
                'message' => 'Outil non autorisé.',
            ],
        };
    }

    /** @return array<string,mixed> */
    private function previousExchange(SupportConversation $conversation, ?int $currentMessageId): array
    {
        $query = $conversation->messages()->where('is_internal', false);

        if ($currentMessageId) {
            $query->where('id', '<', $currentMessageId);
        }

        $messages = $query
            ->latest('id')
            ->limit(2)
            ->get(['id', 'sender_type', 'ai_agent_id', 'body', 'created_at'])
            ->reverse()
            ->map(fn ($message) => [
                'id' => (int) $message->id,
                'role' => (string) $message->sender_type,
                'agent_id' => $message->ai_agent_id ? (int) $message->ai_agent_id : null,
                'body' => mb_substr(trim((string) $message->body), 0, 1400),
                'created_at' => $message->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'ok' => true,
            'source' => 'support_conversation_messages',
            'messages' => $messages,
        ];
    }

    /** @return array<string,mixed> */
    private function requester(SupportConversation $conversation): array
    {
        $user = $conversation->requester;
        $matched = $user && $conversation->requester_match_method !== 'unmatched';
        $accountName = $user ? trim((string) ($user->name ?: $user->full_name)) : '';
        $whatsappName = trim((string) $conversation->requester_name);

        return [
            'ok' => true,
            'source' => 'users/support_conversations',
            'matched_account' => (bool) $matched,
            'match_method' => $conversation->requester_match_method,
            'display_name' => $matched && $accountName !== '' ? $accountName : ($whatsappName ?: null),
            'email' => $matched ? $user?->email : null,
            'phone' => $conversation->requester_phone,
            'has_shop' => (bool) ($user?->shop),
        ];
    }

    /** @return array<string,mixed> */
    private function knowledge(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'ok' => true,
                'source' => 'support_knowledge_articles',
                'articles' => [],
                'message' => 'Aucune requête documentaire fournie.',
            ];
        }

        $terms = collect(preg_split('/\s+/u', Str::lower(Str::ascii($query))))
            ->map(fn ($term) => trim($term))
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->reject(fn ($term) => in_array($term, [
                'ovanie', 'bonjour', 'bonsoir', 'salut', 'merci', 'avec', 'pour', 'dans', 'vous', 'votre',
                'comment', 'quoi', 'quel', 'quelle', 'faire', 'avoir', 'etre', 'sur', 'une', 'des', 'les',
            ], true))
            ->unique()
            ->take(10)
            ->values();

        if ($terms->isEmpty()) {
            return [
                'ok' => true,
                'source' => 'support_knowledge_articles',
                'articles' => [],
                'message' => 'Aucun terme documentaire exploitable.',
            ];
        }

        $articles = SupportKnowledgeArticle::query()
            ->availableToAi()
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%");
                }
            })
            ->latest('published_at')
            ->limit(5)
            ->get(['title', 'category', 'content', 'source_type', 'source_reference', 'version', 'published_at'])
            ->map(fn ($article) => [
                'title' => $article->title,
                'category' => $article->category,
                'content' => Str::limit(trim(strip_tags((string) $article->content)), 1800),
                'source_type' => $article->source_type,
                'source_reference' => $article->source_reference,
                'version' => $article->version,
                'published_at' => $article->published_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'ok' => true,
            'source' => 'support_knowledge_articles',
            'query' => $query,
            'articles' => $articles,
            'grounded' => $articles !== [],
        ];
    }

    /** @return array<string,mixed> */
    private function catalog(string $query): array
    {
        $query = trim($query);

        $categoryQuery = Category::query()->active()->roots()->ordered()->limit(20);
        if ($query !== '') {
            $categoryQuery->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });
        }

        $categories = $categoryQuery->get(['id', 'name', 'slug', 'description'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => Str::limit((string) $category->description, 300),
            ])->values()->all();

        $products = [];
        if ($query !== '') {
            $products = Product::query()
                ->active()
                ->where(function (Builder $builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('brand', 'like', "%{$query}%")
                        ->orWhere('short_description', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->with('category:id,name')
                ->limit(8)
                ->get(['id', 'category_id', 'name', 'slug', 'brand', 'price', 'promo_price', 'unit', 'stock', 'availability_status'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'category' => $product->category?->name,
                    'price' => (float) $product->price,
                    'promo_price' => $product->promo_price !== null ? (float) $product->promo_price : null,
                    'unit' => $product->unit,
                    'stock' => (int) $product->stock,
                    'availability_status' => $product->availability_status,
                ])->values()->all();
        }

        return [
            'ok' => true,
            'source' => 'categories/products',
            'query' => $query,
            'categories' => $categories,
            'products' => $products,
        ];
    }

    /** @return array<string,mixed> */
    private function shop(?User $requester): array
    {
        if (! $requester) {
            return $this->identityRequired('shop');
        }

        $shop = $requester->shop;
        if (! $shop) {
            return [
                'ok' => true,
                'source' => 'shops',
                'shop' => null,
                'message' => 'Aucune boutique liée à ce compte.',
            ];
        }

        return [
            'ok' => true,
            'source' => 'shops',
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'status' => $shop->status,
                'kyc_status' => $shop->kyc_status,
                'logistics_status' => $shop->logistics_status,
                'is_active' => (bool) $shop->is_active,
                'city' => $shop->city,
                'commune' => $shop->commune,
                'logistics_type' => $shop->logistics_type,
                'rejection_reason' => $shop->rejection_reason,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function orders(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('orders');
        }

        $reference = trim((string) ($input['reference'] ?? ''));
        $query = Order::query()->operational()->where('client_id', $requester->id);

        if ($reference !== '') {
            $query->where(function (Builder $builder) use ($reference): void {
                $builder->where('order_number', $reference)
                    ->orWhere('invoice_number', $reference)
                    ->orWhere('tracking_number', $reference);
            });
        }

        $orders = $query->latest('id')->limit($reference !== '' ? 1 : 5)->get();

        return [
            'ok' => true,
            'source' => 'orders',
            'reference' => $reference ?: null,
            'orders' => $orders->map(fn (Order $order) => $this->orderPayload($order))->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function payment(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('payment');
        }

        $paymentReference = trim((string) ($input['payment_reference'] ?? ''));
        $orderReference = trim((string) ($input['order_reference'] ?? ''));

        $query = Payment::query()->where(function (Builder $builder) use ($requester): void {
            $builder->where('user_id', $requester->id)
                ->orWhereHas('order', fn (Builder $orders) => $orders->where('client_id', $requester->id));
        });

        if ($paymentReference !== '') {
            $query->where(function (Builder $builder) use ($paymentReference): void {
                $builder->where('reference', $paymentReference)
                    ->orWhere('transaction_id', $paymentReference);
            });
        } elseif ($orderReference !== '') {
            $query->whereHas('order', function (Builder $orders) use ($orderReference): void {
                $orders->where('order_number', $orderReference)
                    ->orWhere('invoice_number', $orderReference);
            });
        }

        $payments = $query->latest('id')->limit(3)->get();

        return [
            'ok' => true,
            'source' => 'payments',
            'payments' => $payments->map(fn (Payment $payment) => [
                'reference' => $payment->reference,
                'transaction_id' => $payment->transaction_id,
                'order_id' => $payment->order_id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->toISOString(),
                'failed_at' => $payment->failed_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function shipment(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('shipment');
        }

        $tracking = trim((string) ($input['tracking_number'] ?? ''));
        $orderReference = trim((string) ($input['order_reference'] ?? ''));

        $query = Shipment::query()->whereHas('order', fn (Builder $orders) => $orders->where('client_id', $requester->id));

        if ($tracking !== '') {
            $query->where('tracking_number', $tracking);
        } elseif ($orderReference !== '') {
            $query->whereHas('order', function (Builder $orders) use ($orderReference): void {
                $orders->where('order_number', $orderReference)
                    ->orWhere('invoice_number', $orderReference);
            });
        }

        $shipments = $query->latest('id')->limit(3)->get();

        return [
            'ok' => true,
            'source' => 'shipments',
            'shipments' => $shipments->map(fn (Shipment $shipment) => [
                'tracking_number' => $shipment->tracking_number,
                'order_id' => $shipment->order_id,
                'status' => $shipment->status,
                'estimated_delivery_at' => $shipment->estimated_delivery_at?->toISOString(),
                'delivered_at' => $shipment->delivered_at?->toISOString(),
                'vehicle_label' => $shipment->vehicle_label,
                'created_at' => $shipment->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function returns(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('returns');
        }

        $orderReference = trim((string) ($input['order_reference'] ?? ''));
        $query = ReturnModel::query()->where('client_id', $requester->id);
        if ($orderReference !== '') {
            $query->where(function (Builder $builder) use ($orderReference): void {
                $builder->where('order_reference', $orderReference)
                    ->orWhereHas('order', fn (Builder $orders) => $orders->where('order_number', $orderReference));
            });
        }

        $returns = $query->latest('id')->limit(5)->get();

        return [
            'ok' => true,
            'source' => 'returns',
            'returns' => $returns->map(fn (ReturnModel $return) => [
                'id' => $return->id,
                'order_reference' => $return->order_reference,
                'product_name' => $return->product_name,
                'reason' => $return->reason,
                'status' => $return->status,
                'logistics_status' => $return->logistics_status,
                'refund_amount' => $return->refund_amount !== null ? (float) $return->refund_amount : null,
                'created_at' => $return->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function disputes(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('disputes');
        }

        $orderReference = trim((string) ($input['order_reference'] ?? ''));
        $query = Dispute::query()->where('client_id', $requester->id);
        if ($orderReference !== '') {
            $query->where(function (Builder $builder) use ($orderReference): void {
                $builder->where('order_reference', $orderReference)
                    ->orWhereHas('order', fn (Builder $orders) => $orders->where('order_number', $orderReference));
            });
        }

        $disputes = $query->latest('id')->limit(5)->get();

        return [
            'ok' => true,
            'source' => 'disputes',
            'disputes' => $disputes->map(fn (Dispute $dispute) => [
                'id' => $dispute->id,
                'order_reference' => $dispute->order_reference,
                'reason' => $dispute->reason,
                'response' => $dispute->response,
                'status' => $dispute->status,
                'escalated' => (bool) $dispute->escalated,
                'created_at' => $dispute->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function tickets(?User $requester, array $input): array
    {
        if (! $requester) {
            return $this->identityRequired('tickets');
        }

        $reference = trim((string) ($input['reference'] ?? ''));
        $query = SupportTicket::query()->where('requester_user_id', $requester->id);
        if ($reference !== '') {
            $query->where('reference', $reference);
        } else {
            $query->open();
        }

        $tickets = $query->latest('id')->limit(5)->get();

        return [
            'ok' => true,
            'source' => 'support_tickets',
            'tickets' => $tickets->map(fn (SupportTicket $ticket) => [
                'reference' => $ticket->reference,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'team' => $ticket->team,
                'subject' => $ticket->subject,
                'created_at' => $ticket->created_at?->toISOString(),
                'resolved_at' => $ticket->resolved_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function orderPayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'invoice_number' => $order->invoice_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'delivery_status' => $order->delivery_status,
            'total_amount' => (float) $order->total_amount,
            'created_at' => $order->created_at?->toISOString(),
        ];
    }

    /** @return array<string,mixed> */
    private function identityRequired(string $resource): array
    {
        return [
            'ok' => false,
            'source' => $resource,
            'error' => 'identity_required',
            'message' => 'Aucun compte OVANIE n’est relié de manière fiable à ce numéro. Ne révèle aucune donnée personnelle.',
        ];
    }
}
