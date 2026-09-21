<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CheckoutSummaryService
{
    public function __construct(
        private readonly DeliveryServicePricingService $pricingService,
        private readonly DeliveryPricingEngine $pricingEngine,
        private readonly LogisticsVehicleResolver $vehicleResolver,
        private readonly CommissionService $commissions
    ) {}

    /**
     * Résumé checkout OVANIE.
     *
     * Important : une livraison non calculable bloque le checkout.
     */
    public function build(Cart $cart, ?Request $request = null): array
    {
        $cart->loadMissing('items.product.shop', 'user');

        $groups = $cart->items
            ->filter(fn ($item) => $item->product)
            ->groupBy(fn ($item) => (int) ($item->product->shop_id ?? 0))
            ->map(function (Collection $items, int $shopId) use ($request) {
                $shop = $items->first()?->product?->shop;
                $itemsSubtotal = (float) $items->sum(fn ($item) => (float) $item->price * (int) $item->quantity);
                $carrierOptions = $this->buildDeliveryOptions($items, $request);
                $selectedCarrier = $this->selectedCarrierForShop($request, $shopId, $carrierOptions);
                $selectedCarrierFee = ! empty($selectedCarrier['quote_required']) ? 0.0 : (float) ($selectedCarrier['price'] ?? 0);
                $weightKg = (float) (data_get($selectedCarrier, 'meta.calculation.weight_kg')
                    ?? data_get($selectedCarrier, 'meta.seller_delivery.weight_kg')
                    ?? $this->weightKg($items));
                $volumeM3 = (float) (data_get($selectedCarrier, 'meta.calculation.volume_m3')
                    ?? data_get($selectedCarrier, 'meta.seller_delivery.volume_m3')
                    ?? $this->volumeM3($items));

                return [
                    'shop_id' => $shopId,
                    'shop' => $shop,
                    'shop_name' => $shop?->name ?? 'Boutique',
                    'logistics_type' => $shop?->logistics_type ?? 'ovanie',
                    'vendor_score' => $shop?->rating ?? $shop?->score ?? null,
                    'items' => $items->values(),
                    'items_count' => (int) $items->sum('quantity'),
                    'subtotal' => $itemsSubtotal,
                    'delivery_fee' => $selectedCarrierFee,
                    'default_delivery_fee' => $selectedCarrierFee,
                    'carrier_options' => $carrierOptions,
                    'selected_carrier' => $selectedCarrier,
                    'origin' => $this->shopOrigin($shop),
                    'weight_kg' => $weightKg,
                    'volume_m3' => $volumeM3,
                    'delivery_available' => ! empty($selectedCarrier['available'])
                        && empty($selectedCarrier['quote_required']),
                    'delivery_quote_required' => ! empty($selectedCarrier['quote_required'])
                        || empty($selectedCarrier['available']),
                    'delivery_issue_code' => $selectedCarrier['issue_code'] ?? null,
                    'delivery_unavailable_reason' => $selectedCarrier['reason'] ?? null,
                ];
            })
            ->values();

        $groups = $this->applyOvanieConsolidation($groups, $request);

        $subtotal = (float) $groups->sum('subtotal');
        $deliveryFee = (float) $groups->sum('delivery_fee');
        $deliveryIssues = $groups
            ->filter(fn (array $group) => empty($group['delivery_available']) || ! empty($group['delivery_quote_required']))
            ->map(fn (array $group) => [
                'shop_id' => (int) $group['shop_id'],
                'issue_code' => $group['delivery_issue_code']
                    ?? data_get($group, 'selected_carrier.issue_code')
                    ?? 'delivery_unavailable',
                'reason' => $group['delivery_unavailable_reason']
                    ?? data_get($group, 'selected_carrier.reason'),
                'product_ids' => collect($group['items'] ?? [])->pluck('product_id')->filter()->values()->all(),
            ])
            ->values();
        $deliveryAvailable = $groups->isNotEmpty() && $deliveryIssues->isEmpty();
        $deliveryQuoteRequired = ! $deliveryAvailable;
        $total = $subtotal + $deliveryFee;
        $commission = $this->commissions->commissionFromPublicItems($cart->items);

        return [
            'groups' => $groups,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'delivery_available' => $deliveryAvailable,
            'delivery_quote_required' => $deliveryQuoteRequired,
            'delivery_issues' => $deliveryIssues->all(),
            'client_delivery_message' => $this->clientDeliveryMessage($deliveryIssues),
            'total' => $total,
            'commission' => $commission,
            'total_weight_kg' => (float) $groups->sum('weight_kg'),
            'total_weight_ton' => round(((float) $groups->sum('weight_kg')) / 1000, 2),
            'total_volume_m3' => round((float) $groups->sum('volume_m3'), 2),
            'recommended_vehicle' => $this->recommendedVehicleFromGroups($groups),
            'selected_carriers' => $groups->mapWithKeys(fn ($group) => [
                $group['shop_id'] => $group['selected_carrier'],
            ])->toArray(),
            'delivery_breakdown' => $groups->map(fn ($group) => [
                'shop_id' => $group['shop_id'],
                'shop_name' => $group['shop_name'],
                'provider_type' => $group['selected_carrier']['provider_type'] ?? null,
                'delivery_service_id' => $group['selected_carrier']['delivery_service_id'] ?? null,
                'carrier_id' => $group['selected_carrier']['carrier_id'] ?? null,
                'subtotal' => $group['subtotal'],
                'delivery_fee' => $group['delivery_fee'],
                'quote_required' => ! empty($group['selected_carrier']['quote_required']),
                'issue_code' => $group['selected_carrier']['issue_code'] ?? null,
                'price_label' => $group['selected_carrier']['price_label'] ?? null,
                'carrier' => $group['selected_carrier']['name'] ?? 'Service de livraison à confirmer',
                'service_code' => $group['selected_carrier']['code'] ?? null,
                'estimated_delay' => $group['selected_carrier']['delay'] ?? null,
                'estimated_hours' => $group['selected_carrier']['estimated_hours'] ?? null,
                'vehicle_code' => $group['selected_carrier']['vehicle_code'] ?? null,
                'vehicle_label' => $group['selected_carrier']['vehicle_label'] ?? null,
                'consolidation_code' => $group['selected_carrier']['consolidation_code']
                    ?? data_get($group['selected_carrier'], 'meta.consolidation_code'),
                'meta' => $group['selected_carrier']['meta'] ?? [],
                'weight_kg' => $group['weight_kg'],
                'volume_m3' => $group['volume_m3'],
            ])->toArray(),
            'logistics_groups' => $this->logisticsGroups($groups),
            'logistics_delivery_breakdown' => $groups->map(fn ($group) => [
                'shop_id'              => $group['shop_id'],
                'shop_name'            => $group['shop_name'],
                'provider_type'        => $group['selected_carrier']['provider_type'] ?? null,
                'delivery_service_id'  => $group['selected_carrier']['delivery_service_id'] ?? null,
                'carrier_id'           => $group['selected_carrier']['carrier_id'] ?? null,
                'subtotal'             => $group['subtotal'],
                'delivery_fee'         => $group['delivery_fee'],
                'quote_required'       => ! empty($group['selected_carrier']['quote_required']),
                'issue_code'           => $group['selected_carrier']['issue_code'] ?? null,
                'price_label'          => $group['selected_carrier']['price_label'] ?? null,
                'carrier'              => $group['selected_carrier']['name'] ?? 'Service de livraison a confirmer',
                'service_code'         => $group['selected_carrier']['code'] ?? null,
                'estimated_delay'      => $group['selected_carrier']['delay'] ?? null,
                'estimated_hours'      => $group['selected_carrier']['estimated_hours'] ?? null,
                'vehicle_code'         => $group['selected_carrier']['vehicle_code'] ?? null,
                'vehicle_label'        => $group['selected_carrier']['vehicle_label'] ?? null,
                'consolidation_code'   => $group['selected_carrier']['consolidation_code']
                    ?? data_get($group['selected_carrier'], 'meta.consolidation_code'),
                'meta'                 => $group['selected_carrier']['meta'] ?? [],
                'weight_kg'            => $group['weight_kg'],
                'volume_m3'            => $group['volume_m3'],
            ])->toArray(),
            // Résumé simplifié par boutique pour l'affichage frontend
            'shop_deliveries'         => $groups->map(fn ($group) => [
                'shop_id'       => $group['shop_id'],
                'shop_name'     => $group['shop_name'],
                'logistics_type'=> $group['logistics_type'],
                'weight_kg'     => $group['weight_kg'],
                'volume_m3'     => $group['volume_m3'],
                'delivery_fee'  => $group['delivery_fee'],
                'vehicle_code'  => $group['selected_carrier']['vehicle_code'] ?? null,
                'vehicle_label' => $group['selected_carrier']['vehicle_label'] ?? null,
                'delay'         => $group['selected_carrier']['delay'] ?? null,
            ])->values()->toArray(),
            'recommended_vehicle_code'=> $this->recommendedVehicleCodeFromGroups($groups),
        ];
    }

    public function calculateDeliveryFee(Cart $cart, ?Request $request = null): float
    {
        return (float) $this->build($cart, $request)['delivery_fee'];
    }

    private function buildDeliveryOptions(Collection $items, ?Request $request = null): array
    {
        return [$this->pricingEngine->quote($items, $request)];
    }

    private function serviceToOption(DeliveryService $service, Collection $items, ?Request $request = null): array
    {
        $quoteRequired = (bool) data_get($service->meta, 'quote_required', true);
        $price = $quoteRequired ? 0.0 : $this->pricingService->price($service, $items, $request);

        return [
            'delivery_service_id' => $service->id,
            'carrier_id' => $service->carrier_id,
            'code' => $service->code,
            'name' => $service->name,
            'provider_type' => $service->provider_type,
            'delay' => $this->delayText($service),
            'estimated_hours' => $service->estimated_hours,
            'price' => $price,
            'price_label' => $quoteRequired ? 'Livraison indisponible pour cette adresse' : number_format($price, 0, ',', ' ') . ' FCFA',
            'quote_required' => $quoteRequired,
            'note' => $service->description ?: $this->providerNote($service),
            'vehicle_type' => data_get($service->meta, 'vehicle_type'),
            'recommended' => (bool) data_get($service->meta, 'recommended', false),
        ];
    }

    private function pickupDeliveryOption(): array
    {
        return [
            'delivery_service_id' => null,
            'carrier_id' => null,
            'code' => 'pickup',
            'name' => 'Retrait géré par OVANIE',
            'provider_type' => DeliveryService::PROVIDER_PICKUP,
            'delay' => 'Après confirmation vendeur',
            'estimated_hours' => null,
            'price' => 0.0,
            'price_label' => '0 FCFA',
            'quote_required' => false,
            'note' => 'Aucune expédition logistique : le client récupère sa commande au point prévu.',
            'vehicle_type' => 'Retrait',
            'recommended' => true,
        ];
    }

    private function customOvanieQuoteOption(Collection $items): array
    {
        return [
            'delivery_service_id' => null,
            'carrier_id' => null,
            'code' => 'ovanie-custom-quote',
            'name' => 'OVANIE Pro',
            'provider_type' => DeliveryService::PROVIDER_OVANIE,
            'delay' => 'Livraison standard',
            'estimated_hours' => null,
            'price' => 0.0,
            'price_label' => 'Livraison indisponible pour cette adresse',
            'quote_required' => true,
            'note' => 'Livraison OVANIE.',
            'vehicle_type' => 'Chantier',
            'recommended' => true,
        ];
    }

    private function ovanieServices(): Collection
    {
        return DeliveryService::query()
            ->with('rates')
            ->active()
            ->forOvanie()
            ->orderBy('sort_order')
            ->orderBy('estimated_hours')
            ->get();
    }

    private function selectedCarrierForShop(?Request $request, int $shopId, array $carrierOptions): array
    {
        if (empty($carrierOptions)) {
            return [];
        }

        $selected = $request?->input("carrier.$shopId")
            ?? $request?->input('selected_carrier')
            ?? ($carrierOptions[0]['code'] ?? null);

        foreach ($carrierOptions as $option) {
            if (($option['code'] ?? null) === $selected) {
                return $option;
            }
        }

        return $carrierOptions[0];
    }

    private function delayText(DeliveryService $service): string
    {
        if (! $service->estimated_hours) {
            return 'Livraison standard';
        }

        if ($service->estimated_hours <= 24) {
            return 'Sous 24h après validation';
        }

        if ($service->estimated_hours <= 48) {
            return 'Sous 48h après validation';
        }

        if ($service->estimated_hours <= 72) {
            return 'Sous 72h après validation';
        }

        return 'Sur rendez-vous';
    }

    private function providerNote(DeliveryService $service): string
    {
        return 'Service OVANIE';
    }

    private function shopOrigin($shop): string
    {
        return $shop?->city
            ?? $shop?->commune
            ?? $shop?->address
            ?? 'Côte d’Ivoire';
    }

    private function weightKg(Collection $items): float
    {
        return (float) $items->sum(function ($item) {
            $weight = (float) ($item->product?->weight_kg ?? $item->product?->weight ?? 0);
            return $weight * (int) $item->quantity;
        });
    }

    private function volumeM3(Collection $items): float
    {
        return (float) $items->sum(function ($item) {
            $product = $item->product;
            $volume = (float) ($product?->volume_m3 ?? $product?->volume ?? 0);

            if ($volume <= 0 && $product?->length_cm && $product?->width_cm && $product?->height_cm) {
                $volume = ((float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm) / 1000000;
            }

            return $volume * (int) $item->quantity;
        });
    }

    private function logisticsGroups(Collection $groups): array
    {
        $ovanieGroups = $groups->filter(fn ($group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE);
        $sellerGroups = $groups->filter(fn ($group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_SELLER);
        $otherGroups = $groups->reject(fn ($group) => in_array($group['selected_carrier']['provider_type'] ?? null, [
            OrderWorkflowService::PROVIDER_OVANIE,
            OrderWorkflowService::PROVIDER_SELLER,
        ], true));

        return collect()
            ->when($ovanieGroups->isNotEmpty(), fn ($collection) => $collection->push($this->logisticsGroupLine('ovanie', 'OVANIE Logistics', $ovanieGroups)))
            ->merge($sellerGroups->map(fn ($group) => $this->logisticsGroupLine(
                'seller_' . $group['shop_id'],
                $group['shop_name'],
                collect([$group])
            )))
            ->merge($otherGroups->map(fn ($group) => $this->logisticsGroupLine(
                (string) (($group['selected_carrier']['provider_type'] ?? 'delivery') . '_' . $group['shop_id']),
                $group['shop_name'],
                collect([$group])
            )))
            ->values()
            ->all();
    }

    private function logisticsGroupLine(string $code, string $label, Collection $groups): array
    {
        $carrier = $groups->first()['selected_carrier'] ?? [];
        $items = $groups->flatMap(fn (array $group) => collect($group['items'] ?? []))->values();

        return [
            'code' => $code,
            'label' => $label,
            'provider_type' => $carrier['provider_type'] ?? null,
            'shop_ids' => $groups->pluck('shop_id')->values()->all(),
            'product_ids' => $items->pluck('product_id')->filter()->unique()->values()->all(),
            'products_total' => (float) $groups->sum('subtotal'),
            'delivery_total' => (float) $groups->sum('delivery_fee'),
            'total' => (float) $groups->sum('subtotal') + (float) $groups->sum('delivery_fee'),
            'items_count' => (int) $groups->sum('items_count'),
            'weight_kg' => (float) $groups->sum('weight_kg'),
            'volume_m3' => (float) $groups->sum('volume_m3'),
            'vehicle_code' => $carrier['vehicle_code'] ?? null,
            'vehicle_label' => $carrier['vehicle_label'] ?? null,
            'estimated_delay' => $carrier['delay'] ?? null,
            'trip_count' => (int) (data_get($carrier, 'meta.seller_delivery.trip_count') ?? 1),
            'available' => $groups->every(fn (array $group) => ! empty($group['delivery_available'])),
        ];
    }

    private function applyOvanieConsolidation(Collection $groups, ?Request $request): Collection
    {
        $ovanieGroups = $groups->filter(function (array $group) {
            return ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE
                && empty($group['selected_carrier']['quote_required']);
        })->values();

        if ($ovanieGroups->count() < 2) {
            return $groups;
        }

        // La matrice commune -> commune est désormais la référence commerciale
        // du checkout. Lorsqu'un panier comporte plusieurs boutiques OVANIE,
        // chaque groupe a déjà reçu son prix officiel selon sa commune de départ,
        // la destination et le véhicule requis. Une consolidation opérationnelle
        // peut toujours être organisée en logistique, mais elle ne doit plus
        // remplacer ces prix par l'ancienne formule base/km/kg/m³.
        $usesCommuneMatrix = $ovanieGroups->contains(function (array $group) {
            return data_get($group, 'selected_carrier.meta.calculation.rate_source') === 'logistics_pricing_matrix';
        });

        if ($usesCommuneMatrix) {
            return $groups;
        }

        $quote = $this->pricingEngine->quoteConsolidated($ovanieGroups, $request);

        if (! $quote || empty($quote['available']) || ! empty($quote['quote_required'])) {
            return $groups;
        }

        $totalFee = (float) ($quote['price'] ?? 0);
        $totalSubtotal = max(1.0, (float) $ovanieGroups->sum('subtotal'));
        $shares = [];
        $remaining = $totalFee;

        foreach ($ovanieGroups->values() as $index => $group) {
            $isLast = $index === $ovanieGroups->count() - 1;
            $share = $isLast
                ? $remaining
                : round($totalFee * ((float) $group['subtotal'] / $totalSubtotal), 0);

            $share = max(0.0, min($share, $remaining));
            $remaining = max(0.0, $remaining - $share);
            $shares[(int) $group['shop_id']] = $share;
        }

        return $groups->map(function (array $group) use ($quote, $shares, $totalFee) {
            $shopId = (int) $group['shop_id'];

            if (! array_key_exists($shopId, $shares)) {
                return $group;
            }

            $allocatedFee = (float) $shares[$shopId];
            $carrier = $quote;
            $carrier['price'] = $allocatedFee;
            $carrier['price_label'] = number_format($allocatedFee, 0, ',', ' ') . ' FCFA';
            $carrier['meta']['consolidated_total_price'] = $totalFee;
            $carrier['meta']['allocated_shop_price'] = $allocatedFee;

            $group['selected_carrier'] = $carrier;
            $group['carrier_options'] = [$carrier];
            $group['delivery_fee'] = $allocatedFee;
            $group['default_delivery_fee'] = $allocatedFee;
            $group['delivery_available'] = true;
            $group['delivery_quote_required'] = false;
            $group['delivery_unavailable_reason'] = null;

            return $group;
        })->values();
    }

    private function recommendedVehicleFromGroups(Collection $groups): ?string
    {
        $labels = $groups
            ->filter(fn (array $group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE)
            ->pluck('selected_carrier.vehicle_label')
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            // Fallback: check seller delivery as well
            $labels = $groups
                ->pluck('selected_carrier.vehicle_label')
                ->filter()
                ->values();
        }

        if ($labels->isEmpty()) {
            return null;
        }

        $consolidationCodes = $groups
            ->pluck('selected_carrier.consolidation_code')
            ->filter()
            ->unique();

        if ($consolidationCodes->count() === 1) {
            return (string) $labels->first();
        }

        return $labels->countBy()
            ->map(fn (int $count, string $label) => $count > 1 ? $count . ' × ' . $label : $label)
            ->values()
            ->implode(' + ');
    }

    private function clientDeliveryMessage(Collection $issues): ?string
    {
        if ($issues->isEmpty()) {
            return null;
        }

        $codes = $issues->pluck('issue_code')->filter()->unique();

        if ($codes->contains('product_logistics_missing')) {
            return 'La livraison de certains articles n’est pas encore configurée. Modifiez votre panier ou contactez OVANIE.';
        }

        if ($codes->intersect([
            'seller_configuration_incomplete',
            'seller_rate_missing',
            'seller_capacity_exceeded',
            'ovanie_rate_missing',
            'ovanie_reference_rate_missing',
            'ovanie_vehicle_unavailable',
            'commune_tariff_missing',
        ])->isNotEmpty()) {
            return 'Aucun tarif de livraison compatible n’est disponible pour l’ensemble de ce panier à cette adresse.';
        }

        if ($codes->contains('route_unavailable')) {
            return 'La zone de livraison n’a pas pu être confirmée. Vérifiez l’adresse ou réessayez.';
        }

        return 'La livraison n’est pas disponible pour l’ensemble de ce panier à cette adresse.';
    }

    private function recommendedVehicleCodeFromGroups(Collection $groups): ?string
    {
        $codes = $groups
            ->filter(fn (array $group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE)
            ->pluck('selected_carrier.vehicle_code')
            ->filter()
            ->values();

        if ($codes->isEmpty()) {
            $codes = $groups
                ->pluck('selected_carrier.vehicle_code')
                ->filter()
                ->values();
        }

        if ($codes->isEmpty()) {
            return null;
        }

        $consolidationCodes = $groups
            ->pluck('selected_carrier.consolidation_code')
            ->filter()
            ->unique();

        if ($consolidationCodes->count() === 1) {
            return (string) $codes->first();
        }

        return $codes->unique()->implode('_');
    }
}
