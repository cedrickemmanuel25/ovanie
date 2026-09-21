<?php

namespace App\Services\SupportAi;

use App\Models\SupportConversation;
use Illuminate\Support\Arr;

class SupportContextAuthorizationService
{
    /**
     * Deuxième barrière de sécurité après les requêtes filtrées de SupportContextResolver.
     * Aucune donnée personnelle d'un autre compte ne peut passer vers Claude.
     *
     * @return array<string,mixed>
     */
    public function authorize(SupportConversation $conversation, array $context): array
    {
        $requesterId = $conversation->requester_user_id ? (int) $conversation->requester_user_id : null;
        $matched = $requesterId !== null
            && $conversation->requester_match_method
            && $conversation->requester_match_method !== 'unmatched'
            && ! (bool) data_get($context, 'requester.account_match_requires_confirmation', false);

        $safe = [
            'requester' => Arr::only((array) ($context['requester'] ?? []), [
                'matched_account',
                'account_match_requires_confirmation',
                'match_method',
                'display_name',
                'has_shop',
            ]),
            'conversation' => [
                'channel' => $conversation->channel,
                'memory' => Arr::only((array) data_get($context, 'conversation.memory', []), [
                    'recent_messages',
                    'quoted_message',
                    'structured',
                ]),
            ],
            'knowledge' => $this->sanitizeKnowledge((array) ($context['knowledge'] ?? [])),
            'catalog' => $this->sanitizeCatalog((array) ($context['catalog'] ?? [])),
            'data_status' => (array) ($context['data_status'] ?? []),
            'orders' => [],
            'payments' => [],
            'shipments' => [],
            'shop' => null,
            'returns' => [],
            'disputes' => [],
            'tickets' => [],
        ];

        if (! $matched) {
            foreach (['orders', 'payments', 'shipments', 'shop', 'returns', 'disputes', 'tickets'] as $scope) {
                if (isset($safe['data_status'][$scope])) {
                    $safe['data_status'][$scope]['authorized'] = false;
                    $safe['data_status'][$scope]['reason'] = 'Compte OVANIE non identifié de manière fiable.';
                }
            }

            return $safe;
        }

        foreach (['orders', 'payments', 'shipments', 'returns', 'disputes', 'tickets'] as $scope) {
            $safe[$scope] = $this->filterOwnedList((array) ($context[$scope] ?? []), $requesterId);
            if (isset($safe['data_status'][$scope])) {
                $safe['data_status'][$scope]['authorized'] = true;
                $safe['data_status'][$scope]['count'] = count($safe[$scope]);
            }
        }

        $safe['shop'] = $this->filterOwnedOne(
            is_array($context['shop'] ?? null) ? $context['shop'] : null,
            $requesterId,
        );

        if (isset($safe['data_status']['shop'])) {
            $safe['data_status']['shop']['authorized'] = true;
            $safe['data_status']['shop']['count'] = $safe['shop'] ? 1 : 0;
        }

        return $safe;
    }

    /** @return list<array<string,mixed>> */
    private function filterOwnedList(array $items, int $requesterId): array
    {
        $limit = max(1, min(8, (int) config('support_ai.context.entity_limit', 4)));

        return collect($items)
            ->filter(fn ($item) => is_array($item) && (int) ($item['_owner_user_id'] ?? 0) === $requesterId)
            ->map(function (array $item) {
                unset($item['_owner_user_id']);
                return $item;
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array<string,mixed>|null */
    private function filterOwnedOne(?array $item, int $requesterId): ?array
    {
        if (! $item || (int) ($item['_owner_user_id'] ?? 0) !== $requesterId) {
            return null;
        }

        unset($item['_owner_user_id']);
        return $item;
    }

    /** @return list<array<string,mixed>> */
    private function sanitizeKnowledge(array $items): array
    {
        $limit = max(1, min(8, (int) config('support_ai.context.knowledge_limit', 5)));

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => Arr::only($item, [
                'title',
                'category',
                'content',
                'version',
                'published_at',
            ]))
            ->take($limit)
            ->values()
            ->all();
    }
    /** @return array<string,mixed> */
    private function sanitizeCatalog(array $catalog): array
    {
        $products = collect((array) ($catalog['products'] ?? []))
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => Arr::only($item, [
                'name', 'brand', 'category', 'price', 'promo_price', 'effective_price',
                'unit', 'unit_label', 'packaging', 'weight_kg', 'min_order_quantity',
                'stock', 'availability_status',
            ]))
            ->take(8)
            ->values()
            ->all();

        return [
            'query' => mb_substr(trim((string) ($catalog['query'] ?? '')), 0, 500),
            'terms' => array_values(array_slice(array_filter(array_map('strval', (array) ($catalog['terms'] ?? []))), 0, 8)),
            'categories' => array_values(array_slice(array_filter(array_map(
                fn ($value) => mb_substr(trim((string) $value), 0, 120),
                (array) ($catalog['categories'] ?? []),
            )), 0, 12)),
            'products' => $products,
        ];
    }

}
