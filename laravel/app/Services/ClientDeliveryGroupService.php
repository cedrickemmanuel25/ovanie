<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientDeliveryGroupService
{
    public function __construct(
        private readonly OrderDeliveryStatusAggregator $statusAggregator
    ) {
    }

    /**
     * Regroupe les lignes d'une commande selon les livraisons réellement prévues.
     *
     * - OVANIE / partenaire : un groupe par expédition (shipment_id).
     * - Logistique vendeur : un groupe par boutique.
     * - Les noms des boutiques et partenaires ne sont jamais exposés au client.
     */
    public function build(Order $order): Collection
    {
        $order->loadMissing([
            'items.product',
            'items.shop',
            'items.shipment',
            'items.sellerTrackingSession',
            'deliverySelections',
            'shipments',
            'payments',
        ]);

        $items = $order->items->values();

        if ($items->isEmpty()) {
            return collect();
        }

        $groupedItems = $items->groupBy(fn (OrderItem $item) => $this->keyForItem($item));
        $grossProductsTotal = (float) $items->sum(fn (OrderItem $item) => $this->lineTotal($item));
        $totalDiscount = max(0, (float) ($order->discount ?? 0))
            + max(0, (float) ($order->loyalty_discount ?? 0));

        $groups = collect();
        $allocatedDiscount = 0.0;
        $groupCount = $groupedItems->count();
        $position = 0;

        foreach ($groupedItems as $groupKey => $groupItems) {
            $position++;
            $groupItems = $groupItems->values();
            $productAmount = round((float) $groupItems->sum(fn (OrderItem $item) => $this->lineTotal($item)), 2);

            if ($position === $groupCount) {
                $discountAmount = round(max(0, $totalDiscount - $allocatedDiscount), 2);
            } elseif ($grossProductsTotal > 0 && $totalDiscount > 0) {
                $discountAmount = round($totalDiscount * ($productAmount / $grossProductsTotal), 2);
                $allocatedDiscount += $discountAmount;
            } else {
                $discountAmount = 0.0;
            }

            $discountAmount = min($productAmount, $discountAmount);
            $productsPayable = round(max(0, $productAmount - $discountAmount), 2);
            $deliveryFee = round($this->deliveryFeeForGroup($order, (string) $groupKey, $groupItems), 2);
            $groupTotal = round($productsPayable + $deliveryFee, 2);
            $status = $this->statusAggregator->aggregate($groupItems);
            $itemIds = $groupItems->pluck('id')->map(fn ($id) => (int) $id)->values();
            $isDelivered = $groupItems->every(fn (OrderItem $item) => $item->isVendorDelivered());
            $isPaid = $this->isGroupPaid($order, (string) $groupKey, $itemIds, $groupItems);
            $isCashOnDelivery = PaymentService::normalizeMethod($order->payment_method) === PaymentService::METHOD_CASH_ON_DELIVERY;
            $deliveryDate = $this->deliveryDateForGroup($order, $groupItems);

            $groups->push([
                'key' => (string) $groupKey,
                'number' => $position,
                'label' => 'Livraison ' . $position,
                'items' => $groupItems,
                'item_ids' => $itemIds,
                'item_count' => (int) $groupItems->sum(fn (OrderItem $item) => max(1, (int) $item->quantity)),
                'line_count' => $groupItems->count(),
                'product_amount' => $productAmount,
                'discount_amount' => $discountAmount,
                'products_payable' => $productsPayable,
                'delivery_fee' => $deliveryFee,
                'total' => $groupTotal,
                'status' => $status,
                'status_label' => $this->publicStatusLabel($status),
                'is_delivered' => $isDelivered,
                'is_paid' => $isPaid,
                'amount_due' => $isPaid ? 0.0 : $groupTotal,
                'payment_label' => $this->paymentLabel($isCashOnDelivery, $isDelivered, $isPaid),
                'payment_state' => $isPaid ? 'paid' : ($isDelivered ? 'due' : 'waiting'),
                'is_cash_on_delivery' => $isCashOnDelivery,
                'can_collect' => $isCashOnDelivery && $isDelivered && ! $isPaid,
                'provider' => $this->providerForGroup($groupItems),
                'shop_ids' => $groupItems->pluck('shop_id')->filter()->map(fn ($id) => (int) $id)->unique()->values(),
                'shipment_id' => $groupItems->pluck('shipment_id')->filter()->first(),
                'estimated_delay' => $groupItems->pluck('delivery_delay')->filter()->first(),
                'date_label' => $deliveryDate['label'],
                'date_value' => $deliveryDate['value'],
                'date_state' => $deliveryDate['state'],
                'summary' => $this->publicItemsSummary($groupItems),
            ]);
        }

        // La somme des groupes doit rester alignée sur le total contractuel de la commande.
        $storedTotal = (float) ($order->total_amount ?? 0);
        $groupsTotal = round((float) $groups->sum('total'), 2);
        $difference = round($storedTotal - $groupsTotal, 2);

        if ($groups->isNotEmpty() && abs($difference) > 0.01) {
            $lastIndex = $groups->count() - 1;
            $last = $groups->get($lastIndex);
            $last['total'] = round(max(0, (float) $last['total'] + $difference), 2);
            $last['amount_due'] = $last['is_paid'] ? 0.0 : $last['total'];
            $groups->put($lastIndex, $last);
        }

        return $groups->values();
    }

    public function find(Order $order, string $groupKey): ?array
    {
        return $this->build($order)->first(fn (array $group) => hash_equals($group['key'], $groupKey));
    }

    public function findForItem(Order $order, int $orderItemId): ?array
    {
        return $this->build($order)->first(
            fn (array $group) => $group['item_ids']->contains($orderItemId)
        );
    }

    public function findForShop(Order $order, int $shopId): ?array
    {
        return $this->build($order)->first(
            fn (array $group) => $group['shop_ids']->contains($shopId)
        );
    }

    public function summary(Order $order): array
    {
        $groups = $this->build($order);

        return [
            'groups' => $groups,
            'deliveries_count' => $groups->count(),
            'products_total' => round((float) $groups->sum('product_amount'), 2),
            'discount_total' => round((float) $groups->sum('discount_amount'), 2),
            'delivery_total' => round((float) $groups->sum('delivery_fee'), 2),
            'order_total' => round((float) $groups->sum('total'), 2),
            'paid_total' => round((float) $groups->where('is_paid', true)->sum('total'), 2),
            'due_total' => round((float) $groups->where('is_paid', false)->sum('total'), 2),
        ];
    }

    private function keyForItem(OrderItem $item): string
    {
        $provider = $this->normalizeProvider($item);

        if (in_array($provider, [OrderWorkflowService::PROVIDER_OVANIE, OrderWorkflowService::PROVIDER_PARTNER], true)
            && $item->shipment_id) {
            return 'shipment-' . (int) $item->shipment_id;
        }

        if ($provider === OrderWorkflowService::PROVIDER_SELLER) {
            if ($item->seller_tracking_session_id) {
                return 'seller-session-' . (int) $item->seller_tracking_session_id;
            }

            if ($item->shop_id) {
                return 'seller-' . (int) $item->shop_id;
            }
        }

        if ($item->shipment_id) {
            return 'shipment-' . (int) $item->shipment_id;
        }

        if ($item->shop_id) {
            return 'shop-' . (int) $item->shop_id;
        }

        return 'item-' . (int) $item->id;
    }

    private function normalizeProvider(OrderItem $item): string
    {
        $provider = (string) ($item->delivery_provider ?: $item->delivery_mode ?: '');

        if (in_array($provider, [
            OrderWorkflowService::PROVIDER_OVANIE,
            OrderWorkflowService::PROVIDER_PARTNER,
            OrderWorkflowService::PROVIDER_SELLER,
            OrderWorkflowService::PROVIDER_PICKUP,
        ], true)) {
            return $provider;
        }

        return $item->shop?->usesSellerLogistics()
            ? OrderWorkflowService::PROVIDER_SELLER
            : OrderWorkflowService::PROVIDER_OVANIE;
    }

    private function providerForGroup(Collection $items): string
    {
        $providers = $items->map(fn (OrderItem $item) => $this->normalizeProvider($item))->unique();

        return $providers->count() === 1
            ? (string) $providers->first()
            : OrderWorkflowService::PROVIDER_OVANIE;
    }

    private function deliveryFeeForGroup(Order $order, string $groupKey, Collection $items): float
    {
        if (str_starts_with($groupKey, 'shipment-')) {
            $shipmentId = (int) str_replace('shipment-', '', $groupKey);
            $shipment = $order->shipments->firstWhere('id', $shipmentId)
                ?? $items->pluck('shipment')->filter()->first();

            if ($shipment && (float) ($shipment->final_price ?? 0) > 0) {
                return (float) $shipment->final_price;
            }
        }

        $shopIds = $items->pluck('shop_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $selectionFee = (float) $order->deliverySelections
            ->whereIn('shop_id', $shopIds->all())
            ->unique('id')
            ->sum('delivery_fee');

        if ($selectionFee > 0) {
            return $selectionFee;
        }

        return (float) $items
            ->groupBy(fn (OrderItem $item) => (int) ($item->shop_id ?: $item->id))
            ->sum(function (Collection $shopItems) {
                $prices = $shopItems->pluck('delivery_price')->filter(fn ($value) => (float) $value > 0);
                return $prices->isEmpty() ? 0.0 : (float) $prices->max();
            });
    }

    private function isGroupPaid(Order $order, string $groupKey, Collection $itemIds, Collection $items): bool
    {
        if ($items->isNotEmpty() && $items->every(fn (OrderItem $item) => (bool) $item->is_paid)) {
            return true;
        }

        if (in_array($order->payment_status, ['paid', 'escrow_held', 'released_to_vendor'], true)
            && PaymentService::normalizeMethod($order->payment_method) !== PaymentService::METHOD_CASH_ON_DELIVERY) {
            return true;
        }

        return $order->payments
            ->filter(fn (Payment $payment) => $payment->isPaid())
            ->contains(function (Payment $payment) use ($groupKey, $itemIds) {
                if ((string) data_get($payment->provider_payload, 'delivery_group_key') === $groupKey) {
                    return true;
                }

                $paymentItemIds = collect($payment->order_item_ids ?? [])
                    ->map(fn ($id) => (int) $id);

                return $itemIds->isNotEmpty()
                    && $itemIds->every(fn ($id) => $paymentItemIds->contains((int) $id));
            });
    }

    private function lineTotal(OrderItem $item): float
    {
        return (float) ($item->subtotal ?? ((float) $item->price * max(1, (int) $item->quantity)));
    }

    private function publicStatusLabel(?string $status): string
    {
        return match ($status) {
            OrderWorkflowService::DELIVERY_PREPARING => 'Préparation en cours',
            OrderWorkflowService::DELIVERY_READY_FOR_PICKUP => 'Prête pour la livraison',
            OrderWorkflowService::DELIVERY_ASSIGNED => 'Livraison planifiée',
            OrderWorkflowService::DELIVERY_PICKED_UP => 'Colis enlevé',
            OrderWorkflowService::DELIVERY_IN_TRANSIT => 'En livraison',
            OrderWorkflowService::DELIVERY_DELIVERED => 'Livrée',
            OrderWorkflowService::DELIVERY_LATE => 'Livraison retardée',
            OrderWorkflowService::DELIVERY_FAILED,
            OrderWorkflowService::DELIVERY_FAILED_STATUS => 'Problème de livraison',
            OrderWorkflowService::DELIVERY_CANCELLED => 'Livraison annulée',
            default => 'Préparation en cours',
        };
    }

    private function paymentLabel(bool $isCashOnDelivery, bool $isDelivered, bool $isPaid): string
    {
        if ($isPaid) {
            return 'Payée';
        }

        if (! $isCashOnDelivery) {
            return 'Paiement en cours de vérification';
        }

        return $isDelivered
            ? 'À régler maintenant'
            : 'À payer à la réception';
    }

    /**
     * Retourne une date publique compréhensible pour une livraison.
     * Les détails internes du transporteur et du calcul logistique ne sont pas exposés.
     */
    private function deliveryDateForGroup(Order $order, Collection $items): array
    {
        $deliveredAt = $items
            ->flatMap(function (OrderItem $item) {
                return [
                    $item->delivery_completed_at,
                    $item->vendor_delivered_at,
                    $item->shipment?->delivered_at,
                ];
            })
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->last();

        if ($deliveredAt) {
            return [
                'label' => 'Livrée le',
                'value' => $deliveredAt->format('d/m/Y'),
                'state' => 'delivered',
            ];
        }

        // La date manuelle/estimée de la mission vendeur est la source officielle
        // quand le chauffeur ne partage pas sa position GPS.
        $sellerEstimatedAt = $items
            ->pluck('sellerTrackingSession')
            ->filter()
            ->flatMap(fn ($session) => [$session->manual_eta_at, $session->estimated_delivery_at])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();

        if ($sellerEstimatedAt) {
            return [
                'label' => 'Livraison prévue',
                'value' => $sellerEstimatedAt->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        $estimatedAt = $items
            ->pluck('shipment')
            ->filter()
            ->pluck('estimated_delivery_at')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();

        if ($estimatedAt) {
            return [
                'label' => 'Livraison prévue',
                'value' => $estimatedAt->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        // Pour la logistique vendeur, la date renseignée par le vendeur
        // est prioritaire afin d'éviter d'afficher la fenêtre globale OVANIE.
        $sellerShipmentDate = $items
            ->pluck('vendor_shipment_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();

        if ($sellerShipmentDate) {
            return [
                'label' => 'Livraison prévue',
                'value' => $sellerShipmentDate->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        $minDate = $order->delivery_min_date ? Carbon::parse($order->delivery_min_date) : null;
        $maxDate = $order->delivery_max_date ? Carbon::parse($order->delivery_max_date) : null;

        if ($minDate && $maxDate) {
            return [
                'label' => $minDate->isSameDay($maxDate) ? 'Livraison prévue' : 'Période prévue',
                'value' => $minDate->isSameDay($maxDate)
                    ? $minDate->format('d/m/Y')
                    : $minDate->format('d/m/Y') . ' au ' . $maxDate->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        if ($minDate || $maxDate) {
            $date = $minDate ?: $maxDate;

            return [
                'label' => 'Livraison prévue',
                'value' => $date->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        $delay = (string) $items->pluck('delivery_delay')->filter()->first();
        $delayRange = $this->dateRangeFromDelay($order, $delay);

        if ($delayRange) {
            return $delayRange;
        }

        return [
            'label' => 'Date de livraison',
            'value' => 'À confirmer',
            'state' => 'pending',
        ];
    }

    private function dateRangeFromDelay(Order $order, string $delay): ?array
    {
        $delay = mb_strtolower(trim($delay));

        if ($delay === '') {
            return null;
        }

        $base = Carbon::parse($order->created_at ?: now());

        if (preg_match('/(\d+)\s*[-àa]\s*(\d+)\s*(h|heure|heures|j|jour|jours)/u', $delay, $matches)) {
            $minimum = (int) $matches[1];
            $maximum = (int) $matches[2];
            $unit = $matches[3];
            $isHours = in_array($unit, ['h', 'heure', 'heures'], true);
            $start = $isHours ? $base->copy()->addHours($minimum) : $base->copy()->addDays($minimum);
            $end = $isHours ? $base->copy()->addHours($maximum) : $base->copy()->addDays($maximum);

            return [
                'label' => $start->isSameDay($end) ? 'Livraison prévue' : 'Période prévue',
                'value' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        if (preg_match('/(\d+)\s*(h|heure|heures|j|jour|jours)/u', $delay, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            $date = in_array($unit, ['h', 'heure', 'heures'], true)
                ? $base->copy()->addHours($value)
                : $base->copy()->addDays($value);

            return [
                'label' => 'Livraison prévue',
                'value' => $date->format('d/m/Y'),
                'state' => 'estimated',
            ];
        }

        return null;
    }

    private function publicItemsSummary(Collection $items): string
    {
        $names = $items->pluck('product.name')->filter()->values();
        $visible = $names->take(2)->implode(', ');
        $remaining = max(0, $names->count() - 2);

        return $remaining > 0 ? $visible . ' +' . $remaining . ' autre(s)' : $visible;
    }
}
