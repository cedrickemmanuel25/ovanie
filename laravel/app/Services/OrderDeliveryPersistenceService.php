<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDeliverySelection;
use App\Models\Shipment;
use App\Models\Shop;
use App\Services\Geo\RoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderDeliveryPersistenceService
{
    public function persist(Order $order, array $checkout, Request $request): void
    {
        $selectionIdsByShop = [];

        foreach (($checkout['delivery_breakdown'] ?? []) as $line) {
            $selection = OrderDeliverySelection::create([
                'order_id' => $order->id,
                'shop_id' => $line['shop_id'] ?? null,
                'delivery_service_id' => $line['delivery_service_id'] ?? null,
                'carrier_id' => $line['carrier_id'] ?? null,
                'service_code' => $line['service_code'] ?? null,
                'service_name' => $line['carrier'] ?? null,
                'provider_type' => $line['provider_type'] ?? null,
                'estimated_hours' => $line['estimated_hours'] ?? null,
                'delivery_fee' => $line['delivery_fee'] ?? 0,
                'delivery_zone' => $request->input('delivery_zone'),
                'delivery_city' => $request->input('delivery_zone') === 'interieur'
                    ? $request->input('delivery_city')
                    : 'Abidjan',
                'delivery_commune' => $request->input('delivery_zone') === 'abidjan'
                    ? $request->input('delivery_commune')
                    : null,
                'meta' => array_merge($line['meta'] ?? [], [
                    'estimated_delay' => $line['estimated_delay'] ?? null,
                    'weight_kg' => $line['weight_kg'] ?? null,
                    'volume_m3' => $line['volume_m3'] ?? null,
                    'destination_type' => $request->input('delivery_destination_type'),
                    'site_name' => $request->input('delivery_site_name'),
                    'delivery_lat' => $this->coordinate($request->input('delivery_latitude')),
                    'delivery_lng' => $this->coordinate($request->input('delivery_longitude')),
                    'consolidation_code' => $line['consolidation_code'] ?? null,
                ]),
            ]);

            $selectionIdsByShop[(int) ($line['shop_id'] ?? 0)] = $selection->id;
        }

        $trackedLines = collect($checkout['delivery_breakdown'] ?? [])
            ->filter(fn (array $line) => in_array(
                $line['provider_type'] ?? null,
                [OrderWorkflowService::PROVIDER_OVANIE, OrderWorkflowService::PROVIDER_PARTNER],
                true
            ))
            ->values();

        $shipmentGroups = $trackedLines->groupBy(function (array $line) {
            $provider = (string) ($line['provider_type'] ?? OrderWorkflowService::PROVIDER_OVANIE);
            $group = $line['consolidation_code'] ?: 'shop_' . (int) ($line['shop_id'] ?? 0);

            return $provider . ':' . $group;
        });

        foreach ($shipmentGroups as $lines) {
            $shopIds = $lines->pluck('shop_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $providerType = (string) ($lines->first()['provider_type'] ?? OrderWorkflowService::PROVIDER_OVANIE);
            $orderItems = $order->items()
                ->whereIn('shop_id', $shopIds->all())
                ->where('delivery_provider', $providerType)
                ->get();

            if ($orderItems->isEmpty()) {
                continue;
            }

            $firstLine = $lines->first();
            $isConsolidated = $lines->count() > 1 && ! empty($firstLine['consolidation_code']);
            $pickupPoints = collect(data_get($firstLine, 'meta.pickup_points', []));
            $firstShop = Shop::find($shopIds->first());
            $firstSelectionId = $selectionIdsByShop[(int) $shopIds->first()] ?? null;
            $deliveryFee = (float) $lines->sum(fn (array $line) => (float) ($line['delivery_fee'] ?? 0));

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItems->first()->id,
                'shop_id' => $isConsolidated ? null : $shopIds->first(),
                'carrier_id' => $firstLine['carrier_id'] ?? null,
                'delivery_service_id' => $firstLine['delivery_service_id'] ?? null,
                'order_delivery_selection_id' => $firstSelectionId,
                'provider_type' => $providerType,
                'service_code' => $firstLine['service_code'] ?? null,
                'tracking_number' => 'TRK-' . Str::upper(Str::random(10)),
                'status' => OrderWorkflowService::DELIVERY_PENDING,
                // `estimated_hours` n'est que la durée de trajet estimée (route
                // livreur -> client), pas le délai réel avant livraison : avant
                // même qu'un livreur soit affecté, "maintenant + durée de trajet"
                // affichait une heure d'arrivée absurdement proche (souvent
                // quelques minutes après la commande). On s'aligne plutôt sur la
                // promesse client (48h pile après la commande à Abidjan, sinon
                // la fenêtre order.delivery_max_date déjà affichée par l'app
                // mobile) pour que web et mobile soient cohérents.
                'estimated_delivery_at' => $request->input('delivery_zone') === 'abidjan'
                    ? $order->created_at->copy()->addHours(48)
                    : ($order->delivery_max_date
                        ? \Carbon\Carbon::parse($order->delivery_max_date)->endOfDay()
                        : (! empty($firstLine['estimated_hours'])
                            ? now()->addHours((int) $firstLine['estimated_hours'])
                            : null)),
                'final_price' => $deliveryFee,
                'pickup_address' => $isConsolidated
                    ? 'Collecte multi-points OVANIE Logistics'
                    : collect([
                        $firstShop?->address,
                        $firstShop?->landmark,
                        $firstShop?->district,
                        $firstShop?->commune,
                        $firstShop?->city,
                    ])->filter()->implode(' - '),
                'pickup_latitude' => $isConsolidated
                    ? data_get($pickupPoints->first(), 'latitude')
                    : $firstShop?->latitude,
                'pickup_longitude' => $isConsolidated
                    ? data_get($pickupPoints->first(), 'longitude')
                    : $firstShop?->longitude,
                'delivery_address' => $request->input('address'),
                'delivery_latitude' => $this->coordinate($request->input('delivery_latitude')),
                'delivery_longitude' => $this->coordinate($request->input('delivery_longitude')),
                'vehicle_code' => $firstLine['vehicle_code'] ?? null,
                'vehicle_label' => $firstLine['vehicle_label'] ?? null,
                'total_weight_kg' => (float) $lines->sum(fn (array $line) => (float) ($line['weight_kg'] ?? 0)),
                'total_volume_m3' => (float) $lines->sum(fn (array $line) => (float) ($line['volume_m3'] ?? 0)),
                'distance_km' => data_get($firstLine, 'meta.distance_km'),
                'duration_minutes' => data_get($firstLine, 'meta.duration_minutes'),
                'routing_provider' => data_get($firstLine, 'meta.route.provider'),
                'traffic_delay_minutes' => data_get($firstLine, 'meta.traffic_delay_minutes'),
                'no_traffic_duration_minutes' => data_get($firstLine, 'meta.no_traffic_duration_minutes'),
                'meta' => [
                    'order_delivery_selection_ids' => $shopIds
                        ->map(fn ($shopId) => $selectionIdsByShop[(int) $shopId] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                    'service_name' => $firstLine['carrier'] ?? null,
                    'service_code' => $firstLine['service_code'] ?? null,
                    'provider_type' => $providerType,
                    'consolidated' => $isConsolidated,
                    'consolidation_code' => $firstLine['consolidation_code'] ?? null,
                    'pickup_points' => $pickupPoints->all(),
                    'shop_ids' => $shopIds->all(),
                    'route' => data_get($firstLine, 'meta.route'),
                    'calculation' => data_get($firstLine, 'meta.calculation'),
                ],
            ]);

            $order->items()
                ->whereIn('id', $orderItems->pluck('id')->all())
                ->update(['shipment_id' => $shipment->id]);

            if (method_exists($shipment, 'statusHistories')) {
                $shipment->statusHistories()->create([
                    'status' => OrderWorkflowService::DELIVERY_PENDING,
                    'label' => $isConsolidated ? 'Expédition OVANIE groupée créée' : 'Expédition OVANIE créée',
                    'note' => $isConsolidated
                        ? 'Tournée multi-points créée automatiquement au checkout.'
                        : 'Créée automatiquement au checkout pour une livraison coordonnée par OVANIE.',
                    'created_by' => $request->user()?->id,
                ]);
            }

            if (! $isConsolidated
                && $shipment->pickup_latitude
                && $shipment->pickup_longitude
                && $shipment->delivery_latitude
                && $shipment->delivery_longitude) {
                app(RoutingService::class)->estimateShipment($shipment);
            }
        }
    }

    private function coordinate($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 7);
    }
}
