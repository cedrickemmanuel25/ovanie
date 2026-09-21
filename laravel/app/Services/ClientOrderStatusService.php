<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ClientOrderStatusService
{
    /** Commandes dont le traitement commercial ou logistique n'est pas terminé. */
    public const IN_PROGRESS = ['pending', 'confirmed', 'paid', 'processing'];

    public const SHIPPING = ['shipped'];

    /** Compatibilité avec les commandes historiques. */
    public const DELIVERED = ['delivered', 'completed'];

    public const CANCELLED = ['cancelled'];

    public const RETURNED = ['returned'];

    /**
     * Un retour physique confirmé doit apparaître dans « Retournées » sur le Web
     * et sur l'application. Une simple réclamation ne déplace pas la commande.
     */
    private const RETURN_TERMINAL_STATUSES = ['closed', 'refunded', 'resolved'];

    private const RETURN_COMPLETED_LOGISTICS_STATUSES = ['return_received', 'refund_pending', 'refunded'];

    private const TRACKABLE_SHIPMENT_STATUSES = [
        'pending',
        'preparing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'in_transit',
        'in_delivery',
        'late',
        'problem',
        'delivery_failed',
        'delivered',
        'completed',
        'returned',
    ];

    private const TRACKABLE_ITEM_STATUSES = [
        'pending',
        'preparing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'in_transit',
        'in_delivery',
        'late',
        'problem',
        'delivery_failed',
        'delivered',
        'completed',
        'returned',
    ];

    public function applyFilter(Builder $query, ?string $filter): Builder
    {
        return match ($filter) {
            'pending', 'in_progress' => $query->whereIn('status', self::IN_PROGRESS),
            'shipped', 'shipping' => $query->whereIn('status', self::SHIPPING),
            'completed', 'delivered' => $this->excludeReturned(
                $query->whereIn('status', self::DELIVERED)
            ),
            'cancelled' => $query->whereIn('status', self::CANCELLED),
            'returned' => $this->onlyReturned($query),
            default => $query,
        };
    }

    /**
     * Filtre mobile : quatre vues simples adaptées à l'écran téléphone tout en
     * gardant exactement la même table orders et les mêmes règles que le Web.
     */
    public function applyMobileBucket(Builder $query, ?string $bucket): Builder
    {
        return match ($bucket) {
            'in_progress' => $query->whereIn('status', array_values(array_unique([
                ...self::IN_PROGRESS,
                ...self::SHIPPING,
            ]))),
            'delivered' => $this->excludeReturned($query->whereIn('status', self::DELIVERED)),
            'cancelled' => $query->whereIn('status', self::CANCELLED),
            'returned' => $this->onlyReturned($query),
            default => $query,
        };
    }

    public function countsForClient(int $clientId): array
    {
        $base = Order::query()->operational()->where('client_id', $clientId);

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->whereIn('status', self::IN_PROGRESS)->count(),
            'shipped' => (clone $base)->whereIn('status', self::SHIPPING)->count(),
            'completed' => $this->excludeReturned((clone $base)->whereIn('status', self::DELIVERED))->count(),
            'cancelled' => (clone $base)->whereIn('status', self::CANCELLED)->count(),
            'returned' => $this->onlyReturned(clone $base)->count(),
        ];
    }

    public function mobileCountsForClient(int $clientId): array
    {
        $base = Order::query()->operational()->where('client_id', $clientId);

        return [
            'all' => (clone $base)->count(),
            'in_progress' => (clone $base)->whereIn('status', array_values(array_unique([
                ...self::IN_PROGRESS,
                ...self::SHIPPING,
            ])))->count(),
            'delivered' => $this->excludeReturned((clone $base)->whereIn('status', self::DELIVERED))->count(),
            'cancelled' => (clone $base)->whereIn('status', self::CANCELLED)->count(),
            'returned' => $this->onlyReturned(clone $base)->count(),
        ];
    }

    /**
     * Catégorie client unique utilisée par les cartes mobile. Elle ne crée pas
     * de statut parallèle : elle traduit l'état réel de la commande.
     */
    public function bucketForOrder(Order $order): string
    {
        if (in_array((string) $order->status, self::CANCELLED, true)) {
            return 'cancelled';
        }

        if ($this->orderHasPhysicalReturn($order)) {
            return 'returned';
        }

        if (in_array((string) $order->status, self::DELIVERED, true)) {
            return 'delivered';
        }

        return 'in_progress';
    }

    public function canTrack(Order $order): bool
    {
        if ($order->status === 'cancelled') {
            return false;
        }

        if (Schema::hasTable('shipments')) {
            $shipmentQuery = $order->shipments()->whereIn('status', self::TRACKABLE_SHIPMENT_STATUSES);

            if ($order->relationLoaded('shipments')) {
                if ($order->shipments->contains(fn ($shipment) => in_array($shipment->status, self::TRACKABLE_SHIPMENT_STATUSES, true))) {
                    return true;
                }
            } elseif ($shipmentQuery->exists()) {
                return true;
            }
        }

        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        if ($items->contains(fn (OrderItem $item) => $this->isItemTrackable($item))) {
            return true;
        }

        // Une commande confirmée doit pouvoir afficher sa progression réelle même
        // avant l'affectation d'un véhicule. La page montrera uniquement le statut
        // métier (aucune carte, aucun chauffeur fictif) jusqu'à la création d'une
        // mission logistique réelle.
        if (in_array((string) $order->status, ['confirmed', 'paid', 'processing', 'shipped', 'delivered', 'completed'], true)) {
            return $items->contains(function (OrderItem $item) {
                $mode = strtolower((string) ($item->delivery_mode ?? ''));
                return ! in_array($mode, ['pickup', 'retrait'], true)
                    && ! in_array((string) $item->delivery_status, ['not_required', 'cancelled'], true);
            });
        }

        return false;
    }

    public function isItemTrackable(OrderItem $item): bool
    {
        $mode = strtolower((string) ($item->delivery_mode ?? ''));
        $status = strtolower((string) ($item->delivery_status ?: $item->vendor_delivery_status ?: 'pending'));

        if (in_array($mode, ['pickup', 'retrait'], true)) {
            return false;
        }

        if (in_array($status, ['not_required', 'cancelled'], true)) {
            return false;
        }

        // Une ligne sans prestataire/mode de livraison et encore en attente ne doit
        // pas créer un faux suivi client.
        if ($status === 'pending' && blank($item->delivery_provider) && blank($item->shipment_id)) {
            return false;
        }

        return in_array($status, self::TRACKABLE_ITEM_STATUSES, true);
    }

    public function canReorder(Order $order): bool
    {
        return in_array($order->status, self::DELIVERED, true);
    }

    public function canClientCancel(Order $order): bool
    {
        if (! in_array($order->status, self::IN_PROGRESS, true)) {
            return false;
        }

        if (in_array($order->payment_status, ['paid', 'commission_paid', 'partial', 'escrow_held'], true)) {
            return false;
        }

        $hasVendorVisibleItem = $order->relationLoaded('items')
            ? $order->items->contains(fn (OrderItem $item) => filled($item->vendor_visible_at))
            : $order->items()->whereNotNull('vendor_visible_at')->exists();
        if ($hasVendorVisibleItem) {
            return false;
        }

        if (Schema::hasTable('shipments')) {
            $shipmentAlreadyStarted = $order->relationLoaded('shipments')
                ? $order->shipments->contains(fn ($shipment) => ! in_array((string) $shipment->status, ['pending', 'cancelled', 'failed'], true))
                : $order->shipments()->whereNotIn('status', ['pending', 'cancelled', 'failed'])->exists();
            if ($shipmentAlreadyStarted) {
                return false;
            }
        }

        return true;
    }

    private function onlyReturned(Builder $query): Builder
    {
        return $query->where(function (Builder $returned) {
            $returned->whereIn('status', self::RETURNED)
                ->orWhereHas('returns', function (Builder $returns) {
                    $returns->where('return_type', 'return')
                        ->where(function (Builder $completed) {
                            $completed->whereIn('status', self::RETURN_TERMINAL_STATUSES)
                                ->orWhereIn('logistics_status', self::RETURN_COMPLETED_LOGISTICS_STATUSES);
                        });
                });
        });
    }

    private function excludeReturned(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', self::RETURNED)
            ->whereDoesntHave('returns', function (Builder $returns) {
                $returns->where('return_type', 'return')
                    ->where(function (Builder $completed) {
                        $completed->whereIn('status', self::RETURN_TERMINAL_STATUSES)
                            ->orWhereIn('logistics_status', self::RETURN_COMPLETED_LOGISTICS_STATUSES);
                    });
            });
    }

    private function orderHasPhysicalReturn(Order $order): bool
    {
        if (in_array((string) $order->status, self::RETURNED, true)) {
            return true;
        }

        if ($order->relationLoaded('returns')) {
            return $order->returns->contains(fn ($return) =>
                (string) $return->return_type === 'return'
                && (
                    in_array((string) $return->status, self::RETURN_TERMINAL_STATUSES, true)
                    || in_array((string) $return->logistics_status, self::RETURN_COMPLETED_LOGISTICS_STATUSES, true)
                )
            );
        }

        return $order->returns()
            ->where('return_type', 'return')
            ->where(function (Builder $completed) {
                $completed->whereIn('status', self::RETURN_TERMINAL_STATUSES)
                    ->orWhereIn('logistics_status', self::RETURN_COMPLETED_LOGISTICS_STATUSES);
            })
            ->exists();
    }
}
