<?php

namespace App\Services\SupportAi;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\SupportConversation;
use App\Models\SupportKnowledgeArticle;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SupportContextResolver
{
    public function __construct(private readonly SupportCatalogSearchService $catalog) {}

    /**
     * Laravel rassemble le dossier AVANT l'analyse/réponse de Claude.
     * Le plan de chargement est déterministe : il dépend du message, de la mémoire
     * de cette conversation et des références explicites présentes dans l'échange.
     *
     * @return array<string,mixed>
     */
    public function build(
        SupportConversation $conversation,
        string $message,
        array $memory = [],
        array $forcedScopes = [],
    ): array {
        $conversation->loadMissing('requester');
        $requester = $conversation->requester;
        $verifiedRequester = $requester && $conversation->requester_match_method !== 'unmatched'
            ? $requester
            : null;

        $searchText = $this->contextualSearchText(
            $message,
            $memory,
            $forcedScopes !== [],
        );
        $plan = $this->retrievalPlan($searchText, $forcedScopes);

        // Une réponse comme « oui, gravier » complète la question précédente ; le mot
        // « gravier » ne constitue pas à lui seul une demande d'afficher le catalogue.
        if ($forcedScopes === []
            && (bool) ($memory['reply_to_pending_question'] ?? false)
            && ! $this->isCatalogClarificationReply($message, $memory)) {
            $plan = array_values(array_diff($plan, ['catalog']));
        }

        $context = [
            'requester' => $this->requesterContext($conversation, $requester, $message, $searchText),
            'conversation' => [
                'memory' => [
                    'recent_messages' => (array) ($memory['recent_messages'] ?? []),
                    'quoted_message' => $memory['quoted_message'] ?? null,
                    'structured' => (array) ($memory['structured'] ?? []),
                ],
                'continuation' => (bool) ($memory['continuation'] ?? false),
                'segment_reset' => (bool) ($memory['segment_reset'] ?? false),
                'reply_to_pending_question' => (bool) ($memory['reply_to_pending_question'] ?? false),
                'new_support_request' => (bool) ($memory['new_support_request'] ?? false),
                'relation' => (string) ($memory['relation'] ?? 'new_topic'),
            ],
            'data_status' => [],
            'orders' => [],
            'payments' => [],
            'shipments' => [],
            'shop' => null,
            'returns' => [],
            'disputes' => [],
            'tickets' => [],
            'knowledge' => [],
            'catalog' => [
                'query' => '',
                'terms' => [],
                'categories' => [],
                'products' => [],
            ],
        ];

        foreach (['orders', 'payments', 'shipments', 'shop', 'returns', 'disputes', 'tickets', 'knowledge', 'catalog'] as $scope) {
            $context['data_status'][$scope] = [
                'loaded' => in_array($scope, $plan, true),
                'count' => 0,
            ];
        }

        if (in_array('orders', $plan, true)) {
            $context['orders'] = $this->orders($verifiedRequester, $searchText);
            $context['data_status']['orders']['count'] = count($context['orders']);
        }

        if (in_array('payments', $plan, true)) {
            $context['payments'] = $this->payments($verifiedRequester, $searchText);
            $context['data_status']['payments']['count'] = count($context['payments']);
        }

        if (in_array('shipments', $plan, true)) {
            $context['shipments'] = $this->shipments($verifiedRequester, $searchText);
            $context['data_status']['shipments']['count'] = count($context['shipments']);
        }

        if (in_array('shop', $plan, true)) {
            $context['shop'] = $this->shop($verifiedRequester);
            $context['data_status']['shop']['count'] = $context['shop'] ? 1 : 0;
        }

        if (in_array('returns', $plan, true)) {
            $context['returns'] = $this->returns($verifiedRequester, $searchText);
            $context['data_status']['returns']['count'] = count($context['returns']);
        }

        if (in_array('disputes', $plan, true)) {
            $context['disputes'] = $this->disputes($verifiedRequester, $searchText);
            $context['data_status']['disputes']['count'] = count($context['disputes']);
        }

        if (in_array('tickets', $plan, true)) {
            $context['tickets'] = $this->tickets($verifiedRequester, $searchText);
            $context['data_status']['tickets']['count'] = count($context['tickets']);
        }

        if (in_array('knowledge', $plan, true)) {
            $context['knowledge'] = $this->knowledge($searchText);
            $context['data_status']['knowledge']['count'] = count($context['knowledge']);
        }

        if (in_array('catalog', $plan, true)) {
            $context['catalog'] = $this->catalog->search($searchText, $memory);
            $context['data_status']['catalog']['count'] = count((array) ($context['catalog']['products'] ?? []));
        }

        return $context;
    }

    /** @return array<string,mixed> */
    private function requesterContext(SupportConversation $conversation, ?User $requester, string $message, string $contextualMessage): array
    {
        $matched = $requester && $conversation->requester_match_method !== 'unmatched';
        $needsConfirmation = $matched
            && ! $this->identityExplicitlyConfirmed($message)
            && $this->identityNeedsConfirmation($contextualMessage);
        $accountName = $requester ? trim((string) ($requester->name ?: $requester->full_name)) : '';
        $whatsAppName = trim((string) data_get($conversation->metadata ?? [], 'identity.whatsapp_profile_name', $conversation->requester_name));

        return [
            'matched_account' => (bool) $matched && ! $needsConfirmation,
            'account_match_requires_confirmation' => (bool) $needsConfirmation,
            'match_method' => $conversation->requester_match_method,
            'display_name' => $matched && ! $needsConfirmation && $accountName !== '' ? $accountName : ($whatsAppName ?: null),
            'has_shop' => $needsConfirmation ? false : (bool) ($requester?->shop),
        ];
    }

    private function identityNeedsConfirmation(string $message): bool
    {
        $text = $this->normalize($message);
        return $this->containsAny($text, [
            'je suis nouveau sur', 'je suis nouvelle sur', 'nouveau sur la plateforme',
            'nouvelle sur la plateforme', 'je n ai pas de compte', "je n'ai pas de compte",
            'je ne possede pas de compte', "je ne me souviens pas si j'ai un compte",
            "je ne sais pas si j'ai un compte", 'creer un compte client', 'cree un compte client',
            "ce compte n'est pas le mien", 'ce compte ne m appartient pas', "ce n'est pas mon compte",
        ]);
    }

    private function identityExplicitlyConfirmed(string $message): bool
    {
        $text = $this->normalize($message);
        return $this->containsAny($text, [
            "oui c'est mon compte", 'oui ce compte est le mien', "ce compte m'appartient",
            'je confirme que ce compte est le mien',
        ]);
    }

    /** @return list<string> */
    private function retrievalPlan(string $text, array $forcedScopes = []): array
    {
        $normalized = $this->normalize($text);
        $allowedScopes = [
            'orders', 'payments', 'shipments', 'shop', 'returns',
            'disputes', 'tickets', 'knowledge', 'catalog',
        ];

        $scopes = array_values(array_intersect(
            array_map('strval', $forcedScopes),
            $allowedScopes,
        ));

        $vendorContext = $this->isVendorManagementContext($normalized);

        if (! $this->isOnlyGreeting($normalized)) {
            $scopes[] = 'knowledge';
        }

        if ($this->containsAny($normalized, [
            'commande', 'commandes', 'achat', 'facture', 'numero de commande', 'reference commande',
            'cmd-', 'ord-', 'com-', 'ov-',
        ])) {
            $scopes[] = 'orders';
        }

        if ($this->containsAny($normalized, [
            'paiement', 'payer', 'paye', 'payee', 'debite', 'debit', 'wave', 'orange money', 'mtn',
            'momo', 'moov', 'carte bancaire', 'transaction', 'remboursement', 'pay-', 'txn-', 'trx-',
        ])) {
            if ($vendorContext && $this->containsAny($normalized, [
                'mode de paiement', 'moyen de paiement', 'paiement vendeur', 'reversement',
                'recevoir mes gains', 'configurer paiement', 'configurer le paiement',
            ])) {
                // Paiement vendeur = configuration/reversement, pas transaction client.
                $scopes[] = 'shop';
                $scopes[] = 'knowledge';
            } else {
                $scopes[] = 'payments';
                $scopes[] = 'orders';
            }
        }

        if ($this->containsAny($normalized, [
            'livraison', 'livrer', 'livreur', 'colis', 'suivi', 'tracking', 'expedition', 'expedie',
            'retard livraison', 'adresse de livraison', 'reception', 'trk-', 'ship-', 'exp-',
        ])) {
            $scopes[] = 'shipments';
            $scopes[] = 'orders';
        }

        if ($this->containsAny($normalized, [
            'boutique', 'vendeur', 'ajout produit', 'ajouter produit', 'publication produit',
            'publier produit', 'produit rejete', 'produit bloque', 'ouvrir boutique', 'ouverture boutique',
        ])) {
            $scopes[] = 'shop';
        }

        if ($this->containsAny($normalized, [
            'retour produit', 'retourner le produit', 'retourner un produit', 'renvoyer le produit',
            'demande de retour', 'produit a retourner',
        ])) {
            $scopes[] = 'returns';
            $scopes[] = 'orders';
        }

        if ($this->containsAny($normalized, [
            'litige', 'plainte', 'contestation', 'fraude', 'reclamation', 'responsable', 'superviseur',
        ])) {
            $scopes[] = 'disputes';
            $scopes[] = 'orders';
            $scopes[] = 'tickets';
        }

        if ($this->containsAny($normalized, [
            'ticket', 'dossier support', 'dossier assistance', 'sup-',
        ])) {
            $scopes[] = 'tickets';
        }

        // Catalogue public : recherche produit/prix/disponibilité.
        // Une demande de produit n'est PAS un problème technique.
        if ($this->containsAny($normalized, [
            'catalogue', 'catalog', 'produit', 'produits', 'ciment', 'brique', 'briques',
            'carreau', 'carreaux', 'peinture', 'gravier', 'sable', 'pelle', 'robinet',
            'tole', 'toles', 'marbre', 'tuyau', 'tuyaux', 'lavabo', 'wc', 'disjoncteur', 'prise', 'projecteur',
            'panneau solaire', 'batterie solaire', 'casque', 'gilet', 'chaussure de securite',
            'prix le moins cher', 'moins cher', 'meilleur prix', 'disponible', 'disponibilite',
        ])) {
            $sellerPublication = $vendorContext && $this->containsAny($normalized, [
                'ajouter', 'publier', 'publication', 'mes produits', 'catalogue vendeur',
            ]);

            if ($sellerPublication) {
                $scopes[] = 'shop';
                $scopes[] = 'knowledge';
            } else {
                $scopes[] = 'catalog';
            }
        }

        // Une référence explicite suffit à charger le bon domaine, même si le client écrit très peu.
        if ($this->extractOrderReference($text)) {
            $scopes[] = 'orders';
        }
        if ($this->extractPaymentReference($text)) {
            $scopes[] = 'payments';
            $scopes[] = 'orders';
        }
        if ($this->extractTrackingReference($text)) {
            $scopes[] = 'shipments';
            $scopes[] = 'orders';
        }
        if ($this->extractTicketReference($text)) {
            $scopes[] = 'tickets';
        }

        return array_values(array_unique($scopes));
    }

    /** @return list<array<string,mixed>> */
    private function orders(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $limit = $this->entityLimit();
        $reference = $this->extractOrderReference($text);

        $query = Order::query()->where('client_id', $requester->id);

        if ($reference) {
            $query->where(function (Builder $builder) use ($reference) {
                $builder->where('order_number', $reference)
                    ->orWhere('invoice_number', $reference)
                    ->orWhere('tracking_number', $reference);
            });
        }

        return $query
            ->latest('id')
            ->limit($reference ? 1 : $limit)
            ->get()
            ->map(fn (Order $order) => [
                '_owner_user_id' => (int) $requester->id,
                'number' => $order->order_number,
                'invoice_number' => $order->invoice_number,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount === null ? null : (float) $order->total_amount,
                'delivery_status' => $order->delivery_status,
                'delivery_min_date' => $order->delivery_min_date?->toDateString(),
                'delivery_max_date' => $order->delivery_max_date?->toDateString(),
                'created_at' => $order->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function payments(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $limit = $this->entityLimit();
        $paymentReference = $this->extractPaymentReference($text);
        $orderReference = $this->extractOrderReference($text);

        $query = Payment::query()
            ->where(function (Builder $builder) use ($requester) {
                $builder->where('user_id', $requester->id)
                    ->orWhereHas('order', fn (Builder $orders) => $orders->where('client_id', $requester->id));
            });

        if ($paymentReference) {
            $query->where(function (Builder $builder) use ($paymentReference) {
                $builder->where('reference', $paymentReference)
                    ->orWhere('transaction_id', $paymentReference);
            });
        } elseif ($orderReference) {
            $query->whereHas('order', function (Builder $orders) use ($orderReference) {
                $orders->where(function (Builder $builder) use ($orderReference) {
                    $builder->where('order_number', $orderReference)
                        ->orWhere('invoice_number', $orderReference)
                        ->orWhere('tracking_number', $orderReference);
                });
            });
        }

        return $query
            ->with('order:id,order_number,invoice_number,client_id')
            ->latest('id')
            ->limit(($paymentReference || $orderReference) ? 2 : $limit)
            ->get()
            ->map(fn (Payment $payment) => [
                '_owner_user_id' => (int) $requester->id,
                'reference' => $payment->reference,
                'transaction_id' => $payment->transaction_id,
                'order_number' => $payment->order?->order_number,
                'status' => $payment->status,
                'amount' => $payment->amount === null ? null : (float) $payment->amount,
                'method' => $payment->method,
                'operator' => $payment->operator,
                'paid_at' => $payment->paid_at?->toISOString(),
                'failed_at' => $payment->failed_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function shipments(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $limit = $this->entityLimit();
        $trackingReference = $this->extractTrackingReference($text);
        $orderReference = $this->extractOrderReference($text);

        $query = Shipment::query()
            ->whereHas('order', fn (Builder $orders) => $orders->where('client_id', $requester->id));

        if ($trackingReference) {
            $query->where('tracking_number', $trackingReference);
        } elseif ($orderReference) {
            $query->whereHas('order', function (Builder $orders) use ($orderReference) {
                $orders->where(function (Builder $builder) use ($orderReference) {
                    $builder->where('order_number', $orderReference)
                        ->orWhere('invoice_number', $orderReference)
                        ->orWhere('tracking_number', $orderReference);
                });
            });
        }

        return $query
            ->with('order:id,order_number,client_id')
            ->latest('id')
            ->limit(($trackingReference || $orderReference) ? 2 : $limit)
            ->get()
            ->map(fn (Shipment $shipment) => [
                '_owner_user_id' => (int) $requester->id,
                'tracking_number' => $shipment->tracking_number,
                'order_number' => $shipment->order?->order_number,
                'status' => $shipment->status,
                'vehicle_label' => $shipment->vehicle_label,
                'estimated_delivery_at' => $shipment->estimated_delivery_at?->toISOString(),
                'delivered_at' => $shipment->delivered_at?->toISOString(),
                'created_at' => $shipment->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string,mixed>|null */
    private function shop(?User $requester): ?array
    {
        if (! $requester) {
            return null;
        }

        $shop = $requester->shop;
        if (! $shop) {
            return null;
        }

        return [
            '_owner_user_id' => (int) $requester->id,
            'id' => $shop->id,
            'name' => $shop->name,
            'status' => $shop->status,
            'is_active' => (bool) ($shop->is_active ?? false),
            'city' => $shop->city,
            'commune' => $shop->commune,
            'logistics_mode' => $shop->logistics_mode ?? $shop->delivery_mode ?? null,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function returns(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $orderReference = $this->extractOrderReference($text);
        $query = ReturnModel::query()->where('client_id', $requester->id);

        if ($orderReference) {
            $query->where(function (Builder $builder) use ($orderReference) {
                $builder->where('order_reference', $orderReference)
                    ->orWhereHas('order', fn (Builder $orders) => $orders->where('order_number', $orderReference));
            });
        }

        return $query
            ->latest('id')
            ->limit($this->entityLimit())
            ->get()
            ->map(fn (ReturnModel $return) => [
                '_owner_user_id' => (int) $requester->id,
                'id' => $return->id,
                'order_reference' => $return->order_reference,
                'product_name' => $return->product_name,
                'reason' => $return->reason,
                'status' => $return->status,
                'logistics_status' => $return->logistics_status,
                'refund_amount' => $return->refund_amount === null ? null : (float) $return->refund_amount,
                'created_at' => $return->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function disputes(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $orderReference = $this->extractOrderReference($text);
        $query = Dispute::query()->where('client_id', $requester->id);

        if ($orderReference) {
            $query->where(function (Builder $builder) use ($orderReference) {
                $builder->where('order_reference', $orderReference)
                    ->orWhereHas('order', fn (Builder $orders) => $orders->where('order_number', $orderReference));
            });
        }

        return $query
            ->latest('id')
            ->limit($this->entityLimit())
            ->get()
            ->map(fn (Dispute $dispute) => [
                '_owner_user_id' => (int) $requester->id,
                'id' => $dispute->id,
                'order_reference' => $dispute->order_reference,
                'reason' => $dispute->reason,
                'status' => $dispute->status,
                'escalated' => (bool) $dispute->escalated,
                'created_at' => $dispute->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function tickets(?User $requester, string $text): array
    {
        if (! $requester) {
            return [];
        }

        $reference = $this->extractTicketReference($text);
        $query = SupportTicket::query()->where('requester_user_id', $requester->id);

        if ($reference) {
            $query->where('reference', $reference);
        } else {
            $query->open();
        }

        return $query
            ->latest('id')
            ->limit($reference ? 1 : $this->entityLimit())
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                '_owner_user_id' => (int) $requester->id,
                'reference' => $ticket->reference,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'subject' => $ticket->subject,
                'team' => $ticket->team,
                'created_at' => $ticket->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function knowledge(string $queryText): array
    {
        $terms = $this->knowledgeTerms($queryText);
        if ($terms === []) {
            return [];
        }

        $articles = SupportKnowledgeArticle::query()
            ->availableToAi()
            ->where(function (Builder $builder) use ($terms) {
                foreach ($terms as $term) {
                    $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                }
            })
            ->latest('published_at')
            ->limit(20)
            ->get(['title', 'category', 'content', 'version', 'published_at']);

        return $articles
            ->map(function (SupportKnowledgeArticle $article) use ($terms) {
                $title = $this->normalize((string) $article->title);
                $category = $this->normalize((string) $article->category);
                $content = $this->normalize(strip_tags((string) $article->content));
                $score = 0;

                foreach ($terms as $term) {
                    $needle = $this->normalize($term);
                    if ($needle !== '' && str_contains($title, $needle)) {
                        $score += 6;
                    }
                    if ($needle !== '' && str_contains($category, $needle)) {
                        $score += 3;
                    }
                    if ($needle !== '' && str_contains($content, $needle)) {
                        $score += 1;
                    }
                }

                return [
                    'score' => $score,
                    'title' => $article->title,
                    'category' => $article->category,
                    'content' => Str::limit(strip_tags((string) $article->content), 1800),
                    'version' => (int) ($article->version ?? 1),
                    'published_at' => $article->published_at?->toISOString(),
                ];
            })
            ->sortByDesc('score')
            ->take(max(1, min(8, (int) config('support_ai.context.knowledge_limit', 5))))
            ->map(function (array $article) {
                unset($article['score']);
                return $article;
            })
            ->values()
            ->all();
    }

    private function contextualSearchText(
        string $message,
        array $memory,
        bool $forceStructuredContext = false,
    ): string {
        $quoted = trim((string) data_get($memory, 'quoted_message.body', ''));
        $continuation = (bool) ($memory['continuation'] ?? false);
        $structured = (array) ($memory['structured'] ?? []);

        // Aucune ancienne conversation brute n'est utilisée pour un message autonome.
        if (! $forceStructuredContext && ! $continuation && $quoted === '') {
            return trim($message);
        }

        $recent = collect((array) ($memory['recent_messages'] ?? []))
            ->take(-2)
            ->pluck('body')
            ->filter()
            ->implode(' ');

        $facts = collect((array) ($structured['collected_facts'] ?? []))
            ->map(fn ($value, $key) => $key.' '.$value)
            ->implode(' ');

        $references = implode(' ', (array) ($structured['known_references'] ?? []));
        $topic = trim((string) ($structured['active_intent'] ?? ''));

        return trim(implode(' ', array_filter([
            $message,
            $quoted,
            $recent,
            $facts,
            $references,
            $topic,
        ])));
    }

    private function isCatalogClarificationReply(string $message, array $memory): bool
    {
        $questions = (array) data_get($memory, 'structured.pending_questions', []);
        $latest = is_array(end($questions)) ? end($questions) : [];
        $intent = $this->normalize((string) ($latest['intent'] ?? data_get($memory, 'structured.active_intent', '')));
        $pending = $this->normalize((string) ($latest['body'] ?? data_get($memory, 'structured.pending_question', '')));
        $current = $this->normalize($message);

        $catalogIntent = $this->containsAny($intent, ['product', 'produit', 'catalog', 'achat']);
        $catalogQuestion = $this->containsAny($pending, ['produit', 'marque', 'type de ciment', 'catalogue']);
        $productAnswer = $this->containsAny($current, [
            'ciment', 'gravier', 'sable', 'tole', 'marbre', 'carreau', 'peinture',
            'classique', 'marque', 'peu importe',
        ]);

        return ($catalogIntent || $catalogQuestion) && $productAnswer;
    }


    /** @return list<string> */
    private function knowledgeTerms(string $text): array
    {
        $normalized = $this->normalize($text);
        $stopWords = [
            'bonjour', 'bonsoir', 'salut', 'merci', 'avec', 'pour', 'dans', 'vous', 'votre', 'vos',
            'comment', 'quoi', 'quel', 'quelle', 'cela', 'cette', 'avoir', 'faire', 'mais', 'pas',
            'mon', 'ma', 'mes', 'une', 'des', 'les', 'sur', 'est', 'suis', 'etre', 'chez', 'ovni',
            'nouveau', 'nouvelle', 'plateforme',
        ];

        $terms = collect(preg_split('/\s+/u', $normalized))
            ->map(fn ($term) => trim((string) $term, " \t\n\r\0\x0B.,;:!?()[]{}\"'"))
            ->filter(fn ($term) => mb_strlen($term) >= 4)
            ->reject(fn ($term) => in_array($term, $stopWords, true));

        // Alias métier déterministes : ils améliorent la récupération de l'article officiel
        // sans transformer Laravel en moteur de réponses préparées.
        if ($this->containsAny($normalized, ['boutique', 'vendeur', 'ouvrir boutique', 'creer boutique', 'creation boutique'])) {
            $terms = $terms->merge(['ouverture', 'boutique', 'vendeur']);
        }
        if ($this->containsAny($normalized, ['compte', 'inscription', 'inscrire', 'creer un compte'])) {
            $terms = $terms->merge(['compte', 'inscription', 'connexion']);
        }
        if ($this->containsAny($normalized, ['commande', 'acheter', 'achat', 'panier', 'checkout'])) {
            $terms = $terms->merge(['commande', 'checkout']);
        }
        if ($this->containsAny($normalized, ['paiement', 'wave', 'orange money', 'mtn', 'moov'])) {
            $terms = $terms->merge(['paiement', 'checkout']);
        }
        if ($this->containsAny($normalized, ['ajouter produit', 'ajout produit', 'publier produit', 'publication produit'])) {
            $terms = $terms->merge(['ajout', 'publication', 'produit']);
        }

        return $terms
            ->filter(fn ($term) => mb_strlen((string) $term) >= 4)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $this->normalize((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function isVendorManagementContext(string $normalized): bool
    {
        $vendorSignals = [
            'boutique', 'vendeur', 'vendor', 'ouvrir boutique', 'ouverture boutique',
            'ajouter produit', 'ajouter mes produits', 'publier produit', 'publication produit',
            'catalogue vendeur', 'paiement vendeur', 'reversement', 'recevoir mes gains',
            'configurer le paiement', 'mode de paiement',
        ];

        return $this->containsAny($normalized, $vendorSignals);
    }

    private function isOnlyGreeting(string $text): bool
    {
        $clean = trim(preg_replace('/[^a-z0-9 ]+/', ' ', $this->normalize($text)) ?? $text);
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        if ($clean === '') {
            return false;
        }

        foreach ([
            'commande', 'paiement', 'livraison', 'litige', 'devis', 'remboursement',
            'produit', 'catalogue', 'prix', 'boutique', 'vendeur', 'compte', 'inscription',
            'erreur', 'probleme', 'reclamation', 'retour', 'acheter', 'achat', 'besoin', 'cherche',
        ] as $word) {
            if (str_contains($clean, $word)) {
                return false;
            }
        }

        return (bool) preg_match(
            '/^(bonjour|bonsoir|salut|coucou|hello|hey)(?:\s+ovanie)?(?:\s+.*)?$/u',
            $clean
        ) && mb_strlen($clean) <= 160;
    }

    private function entityLimit(): int
    {
        return max(1, min(8, (int) config('support_ai.context.entity_limit', 4)));
    }

    private function extractOrderReference(string $text): ?string
    {
        return preg_match('/\b(?:CMD|ORD|OV|COM)-[A-Z0-9-]{4,}\b/i', $text, $match)
            ? Str::upper($match[0])
            : null;
    }

    private function extractPaymentReference(string $text): ?string
    {
        return preg_match('/\b(?:PAY|TXN|TRX)-[A-Z0-9-]{4,}\b/i', $text, $match)
            ? Str::upper($match[0])
            : null;
    }

    private function extractTrackingReference(string $text): ?string
    {
        return preg_match('/\b(?:TRK|SHIP|EXP)-[A-Z0-9-]{4,}\b/i', $text, $match)
            ? Str::upper($match[0])
            : null;
    }

    private function extractTicketReference(string $text): ?string
    {
        return preg_match('/\bSUP-[A-Z0-9-]{5,}\b/i', $text, $match)
            ? Str::upper($match[0])
            : null;
    }
}
