<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\ClientOrderStatusService;
use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\DriverTrackingService;
use App\Services\Geo\RoutingService;
use App\Services\OrderDeliveryStatusAggregator;
use App\Services\OrderWorkflowService;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieShipmentConsolidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class ShipmentTrackingController extends Controller
{
    public function shipment(
        Request $request,
        Shipment $shipment,
        DriverTrackingService $tracking,
        ClientOrderStatusService $statusService
    ) {
        $shipment->loadMissing([
            'order.client',
            'order.items',
            'order.shipments',
            'orderItem.latestDeliveryAssignment.driver',
            'latestDriverLocation',
        ]);

        $forLogistics = $this->isLogistics($request);

        abort_unless(
            $forLogistics || (int) $shipment->order?->client_id === (int) $request->user()?->id,
            403
        );

        if (! $forLogistics) {
            abort_unless($shipment->order && $statusService->canTrack($shipment->order), 404);
            abort_if(in_array($shipment->status, ['cancelled', 'failed', 'not_required'], true), 404);
        }

        return response()->json($tracking->trackingPayload($shipment, $forLogistics));
    }

    public function order(
        Request $request,
        Order $order,
        DriverTrackingService $tracking,
        RoutingService $routing,
        OrderDeliveryStatusAggregator $aggregator,
        ClientOrderStatusService $statusService,
        OvanieShipmentConsolidationService $consolidation,
    ) {
        $forLogistics = $this->isLogistics($request);

        abort_unless(
            (int) $order->client_id === (int) $request->user()?->id || $forLogistics,
            403
        );

        $order->loadMissing([
            'items.product.shop',
            'items.shop',
            'items.shipment.orderItem',
            'items.latestDeliveryAssignment.driver',
            'items.latestDeliveryAssignment.latestLocation',
            'items.sellerTrackingSession.latestLocation',
            'shipments.orderItem.product',
            'shipments.orderItem.latestDeliveryAssignment.driver',
            'shipments.latestDriverLocation',
        ]);

        if (! $forLogistics) {
            abort_unless($statusService->canTrack($order), 404);
        }

        $trackableItems = $order->items
            ->filter(fn (OrderItem $item) => $forLogistics || $statusService->isItemTrackable($item))
            ->reject(fn (OrderItem $item) => in_array(
                strtolower((string) ($item->delivery_provider ?: $item->delivery_mode)),
                [OrderWorkflowService::PROVIDER_PICKUP, 'pickup', 'retrait'],
                true
            ))
            ->values();

        $ovanieItems = $trackableItems
            ->filter(fn (OrderItem $item) => $this->normalizedProvider($item) !== OrderWorkflowService::PROVIDER_SELLER)
            ->values();
        $sellerItems = $trackableItems
            ->filter(fn (OrderItem $item) => $this->normalizedProvider($item) === OrderWorkflowService::PROVIDER_SELLER)
            ->values();

        // Une livraison client correspond à une charge/misson réelle, jamais à
        // chaque ligne produit ni à chaque ancien enregistrement shipment.
        $ovaniePayloads = $consolidation->groups($ovanieItems)
            ->map(function (array $group) use ($order, $tracking, $routing, $forLogistics) {
                /** @var Collection $groupItems */
                $groupItems = collect($group['items'] ?? [])->values();
                if ($groupItems->isEmpty()) {
                    return null;
                }

                /** @var OrderItem $representative */
                $representative = $groupItems->first(fn (OrderItem $item) => $item->latestDeliveryAssignment)
                    ?: $groupItems->first(fn (OrderItem $item) => $item->shipment)
                    ?: $groupItems->first();

                $itemIds = $groupItems->pluck('id')->map(fn ($id) => (int) $id);
                $shipment = $representative->shipment
                    ?: $order->shipments->first(fn (Shipment $candidate) => $itemIds->contains((int) $candidate->order_item_id));

                $payload = $shipment
                    ? $tracking->trackingPayload($shipment, $forLogistics)
                    : $this->itemTrackingPayload($order, $representative, $forLogistics, $tracking, $routing, $groupItems);

                return $this->decorateOvanieGroupPayload($payload, $group, $groupItems, $tracking, $forLogistics);
            })
            ->filter()
            ->values();

        $sellerPayloads = $sellerItems
            ->groupBy(fn (OrderItem $item) => $item->seller_tracking_session_id
                ? 'seller-session-' . $item->seller_tracking_session_id
                : 'seller-shop-' . ($item->shop_id ?: $item->id))
            ->map(function (Collection $items, string $key) use ($order, $forLogistics, $tracking, $routing) {
                $payload = $this->itemTrackingPayload($order, $items->first(), $forLogistics, $tracking, $routing, $items);
                $payload['tracking_key'] = $key;
                $payload['delivery']['label'] = $this->publicItemsLabel($items);
                $payload['delivery']['item_count'] = (int) $items->sum(fn (OrderItem $item) => max(1, (int) $item->quantity));
                $payload['delivery']['reference_count'] = $items->count();
                $payload['delivery']['items'] = $this->publicItemsPayload($items);
                return $payload;
            })
            ->values();

        $payloads = $ovaniePayloads
            ->concat($sellerPayloads)
            ->sortBy(fn (array $payload) => (int) ($payload['sort_order'] ?? PHP_INT_MAX))
            ->values()
            ->map(function (array $payload, int $index) {
                $payload['delivery_number'] = $index + 1;
                $payload['delivery_label'] = 'Livraison ' . ($index + 1);
                unset($payload['sort_order']);
                return $payload;
            });

        if ($payloads->isEmpty()) {
            return response()->json(array_merge(
                $this->sharedClientTrackingMeta($order),
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'provider' => 'Livraison OVANIE',
                    'delivery_status' => $tracking->publicStatus($aggregator->aggregate($order)),
                    'last_update' => $order->updated_at?->toIso8601String(),
                    'shipment_count' => 0,
                    'shipments' => [],
                ]
            ));
        }

        $activePayload = $this->activePayload($payloads);
        $aggregateStatus = $aggregator->aggregate($order->fresh(['items']));
        $lastUpdate = $payloads->pluck('last_update')->filter()->sortDesc()->first();

        return response()->json(array_merge(
            $activePayload,
            $this->sharedClientTrackingMeta($order),
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'provider' => 'Livraison OVANIE',
                'delivery_status' => $tracking->publicStatus($aggregateStatus),
                'last_update' => $lastUpdate ?: ($activePayload['last_update'] ?? null),
                'shipment_count' => $payloads->count(),
                'shipments' => $payloads->all(),
            ]
        ));
    }

    private function itemTrackingPayload(
        Order $order,
        OrderItem $item,
        bool $forLogistics,
        DriverTrackingService $tracking,
        RoutingService $routing,
        ?Collection $groupItems = null
    ): array {
        $item->loadMissing(['sellerTrackingSession.latestLocation', 'latestDeliveryAssignment.driver', 'latestDeliveryAssignment.latestLocation', 'product']);
        $groupItems = ($groupItems ?: collect([$item]))->values();
        $groupItems->each(fn (OrderItem $groupItem) => $groupItem->loadMissing('product'));
        $session = $item->sellerTrackingSession;
        $assignment = $item->latestDeliveryAssignment;
        $hasActualAssignment = (bool) $session || (bool) $assignment;
        $destination = app(DeliveryCoordinateService::class)->forOrder($order);
        $destinationLat = $destination['latitude'];
        $destinationLng = $destination['longitude'];
        $gpsUnavailable = ($session && in_array($session->gps_status, ['unavailable', 'denied', 'disabled'], true))
            || ($assignment && in_array($assignment->gps_status, ['unavailable', 'denied', 'disabled'], true));
        $location = $session?->latestLocation ?: $assignment?->latestLocation;
        $latitude = $location?->latitude ?? $session?->last_latitude;
        $longitude = $location?->longitude ?? $session?->last_longitude;

        $status = $item->delivery_status ?: $item->vendor_delivery_status ?: 'pending';
        $missionStatus = strtolower((string) ($session?->mission_status ?: $assignment?->status ?: ''));
        if ($hasActualAssignment) {
            $status = match ($missionStatus) {
                'accepted', 'collecting' => 'assigned',
                'picked_up' => 'picked_up',
                'in_transit', 'arrived' => 'in_transit',
                'delivered' => 'delivered',
                default => $status,
            };
        }

        $publicStatus = $tracking->publicStatus($status);
        $hasStoredGps = $hasActualAssignment
            && app(DeliveryCoordinateService::class)->valid($latitude, $longitude);
        $gpsUnavailable = $gpsUnavailable && ! $hasStoredGps;
        $recordedAt = $location?->recorded_at ?: $session?->last_location_at ?: $assignment?->gps_last_seen_at;
        $age = $recordedAt?->diffInSeconds(now());
        $signalStatus = $session
            ? $this->sellerSignalStatus($session, $location, $gpsUnavailable, $hasStoredGps)
            : $this->assignmentSignalStatus($assignment, $gpsUnavailable, $hasStoredGps, $age);
        $deliveryStarted = in_array($missionStatus, ['picked_up', 'in_transit', 'arrived', 'delivered'], true)
            || in_array($publicStatus, ['in_transit', 'late', 'problem', 'delivered'], true);
        $freshForClient = $hasStoredGps
            && $age !== null
            && $age <= (int) config('delivery.client_gps_max_age_seconds', 180)
            && in_array($signalStatus, ['active', 'weak'], true);
        $freshForInternalRoute = $hasStoredGps
            && $age !== null
            && $age <= (int) config('delivery.gps_lost_seconds', 300);
        $mapVisible = $forLogistics
            ? ($deliveryStarted && $freshForInternalRoute)
            : ($deliveryStarted && $freshForClient);
        $locationVisible = $forLogistics ? $hasStoredGps : $mapVisible;

        $routePayload = [
            'distance_km' => null,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'geometry' => null,
            'provider' => null,
        ];

        if ($mapVisible
            && app(DeliveryCoordinateService::class)->valid($destinationLat, $destinationLng)) {
            $routeResult = $routing->route(
                (float) $latitude,
                (float) $longitude,
                (float) $destinationLat,
                (float) $destinationLng,
            );

            if ($routeResult['success'] ?? false) {
                $routePayload = [
                    'distance_km' => $routeResult['distance_km'] ?? null,
                    'duration_minutes' => $routeResult['duration_minutes'] ?? null,
                    'traffic_delay_minutes' => $routeResult['traffic_delay_minutes'] ?? null,
                    'geometry' => $this->geometry($routeResult['route_geometry'] ?? null),
                    'provider' => $routeResult['provider'] ?? null,
                ];
            }
        }

        $routeMinutes = is_numeric($routePayload['duration_minutes'] ?? null)
            ? max(1, (int) $routePayload['duration_minutes'])
            : null;
        $etaAt = $session?->manual_eta_at
            ?: ($assignment?->manual_eta_at)
            ?: ($mapVisible && $routeMinutes ? now()->addMinutes($routeMinutes) : null)
            ?: $session?->estimated_delivery_at
            ?: $assignment?->estimated_delivery_at
            ?: ($item->vendor_shipment_date ? \Carbon\Carbon::parse($item->vendor_shipment_date)->endOfDay() : null)
            ?: $order->delivery_max_date;
        $etaSource = ($session?->manual_eta_at || $assignment?->manual_eta_at)
            ? 'manual'
            : (($mapVisible && $routeMinutes) ? 'live_route' : ($etaAt ? 'planned' : null));
        // Les informations d'affectation peuvent être visibles avant le départ,
        // mais aucune position ou route n'est publiée avant un vrai trajet client.
        $publicDriverVisible = $hasActualAssignment;
        [$totalWeight, $totalVolume] = $this->groupWeightAndVolume($groupItems);
        $resolvedVehicle = app(LogisticsVehicleResolver::class)->resolve($totalWeight, $totalVolume);
        $assignedDriver = $assignment?->driver;
        $assignedVehiclePlate = $session?->vehicle_plate ?: $item->vehicle_plate ?: data_get($assignment?->meta, 'vehicle_plate');
        $assignedVehicleLabel = $assignedDriver?->vehicle
            ?: ($hasActualAssignment ? ($item->logistics_vehicle_label ?: $resolvedVehicle['label']) : null);

        $payload = [
            'shipment_id' => $session ? -1000000 - (int) $session->id : -1 * (int) $item->id,
            'tracking_key' => $session ? 'seller-session-' . $session->id : 'seller-item-' . $item->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_item_id' => $item->id,
            'provider' => 'Livraison OVANIE',
            'delivery_status' => $publicStatus,
            'tracking_phase' => $deliveryStarted ? 'to_customer' : ($hasActualAssignment ? 'waiting_start' : 'waiting_assignment'),
            'map_visible' => $mapVisible,
            'eta' => $etaAt?->toIso8601String(),
            'eta_source' => $etaSource,
            'tracking_mode' => $mapVisible ? 'gps' : ($gpsUnavailable ? 'manual' : 'status'),
            'gps_available' => $mapVisible,
            'gps_status' => $mapVisible ? 'active' : ($session?->gps_status ?: $assignment?->gps_status ?: 'waiting'),
            'signal_status' => $mapVisible ? $signalStatus : ($gpsUnavailable ? 'unavailable' : ($hasActualAssignment ? 'standby' : 'waiting')),
            'last_update' => ($recordedAt ?: $session?->last_manual_status_at ?: $assignment?->last_manual_status_at ?: $assignment?->updated_at ?: $item->vendor_delivery_updated_at ?: $item->updated_at)?->toIso8601String(),
            'driver' => [
                'name' => $publicDriverVisible
                    ? ($session?->driver_name ?: $assignedDriver?->name ?: $item->driver_name ?: 'Votre livreur')
                    : null,
                'phone' => $publicDriverVisible
                    ? ($session?->driver_phone ?: $assignedDriver?->phone ?: $item->driver_phone)
                    : null,
                'avatar_url' => null,
                'rating' => $publicDriverVisible && is_numeric($assignedDriver?->rating)
                    ? round((float) $assignedDriver->rating, 1)
                    : null,
                'vehicle' => $publicDriverVisible ? $assignedVehiclePlate : null,
                'location' => $locationVisible ? [
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'accuracy' => $location?->accuracy ?? $session?->last_accuracy,
                    'speed' => $location?->speed ?? $session?->last_speed,
                    'heading' => $location?->heading ?? $session?->last_heading,
                    'recorded_at' => $recordedAt?->toIso8601String(),
                ] : null,
            ],
            'destination' => [
                'latitude' => is_numeric($destinationLat) ? (float) $destinationLat : null,
                'longitude' => is_numeric($destinationLng) ? (float) $destinationLng : null,
                'label' => $order->delivery_commune ?: $order->delivery_city,
            ],
            'route' => $routePayload,
            'delivery' => [
                'label' => $this->publicItemsLabel($groupItems),
                'item_count' => $groupItems->sum(fn (OrderItem $groupItem) => max(1, (int) $groupItem->quantity)),
                'reference_count' => $groupItems->count(),
                'items' => $this->publicItemsPayload($groupItems),
            ],
            'vehicle' => [
                'assigned' => $hasActualAssignment,
                'vehicle_code' => $hasActualAssignment ? $resolvedVehicle['code'] : null,
                'vehicle_label' => $hasActualAssignment ? $assignedVehicleLabel : null,
                'vehicle_plate' => $hasActualAssignment ? $assignedVehiclePlate : null,
                'total_weight_kg' => $hasActualAssignment || $forLogistics ? $totalWeight : null,
                'total_volume_m3' => $hasActualAssignment || $forLogistics ? $totalVolume : null,
            ],
            'navigation' => ['waze_url' => null],
            'history' => [],
            'sort_order' => (int) $groupItems->min('id'),
        ];

        if ($forLogistics) {
            $payload['internal_delivery_provider'] = $item->delivery_provider;
            $payload['internal_shop_id'] = $item->shop_id;
            $payload['internal_tracking_session_id'] = $session?->id;
            $payload['driver']['name'] = $session?->driver_name ?: $assignedDriver?->name ?: $item->driver_name;
            $payload['driver']['phone'] = $session?->driver_phone ?: $assignedDriver?->phone ?: $item->driver_phone;
        }

        return $payload;
    }

    private function decorateOvanieGroupPayload(
        array $payload,
        array $group,
        Collection $groupItems,
        DriverTrackingService $tracking,
        bool $forLogistics,
    ): array {
        $trackingKey = 'ovanie-' . sha1((string) ($group['key'] ?? $group['mission_number'] ?? $groupItems->pluck('id')->implode('-')));
        $phase = (string) ($group['operational_phase'] ?? 'to_offer');
        $assignmentStatuses = collect($group['assignment_statuses'] ?? [])->map(fn ($status) => strtolower((string) $status));
        $arrivedAtCustomer = $assignmentStatuses->contains('arrived');

        // Le client suit le parcours métier réel et non les statuts techniques
        // de DeliveryAssignment. Cette traduction est commune Web + mobile.
        $publicStatus = match ($phase) {
            'waiting_acceptance' => 'waiting_driver',
            'accepted_waiting_vendor' => 'driver_reserved',
            'ready_for_pickup' => 'ready_for_pickup',
            'collecting' => 'collecting',
            'in_delivery' => $arrivedAtCustomer ? 'arrived' : 'in_transit',
            'delivered' => 'delivered',
            'incident' => 'problem',
            default => 'preparing',
        };

        $payload['tracking_key'] = $trackingKey;
        $payload['delivery_status'] = $publicStatus;
        $payload['tracking_phase'] = match ($phase) {
            'waiting_acceptance' => 'waiting_assignment',
            'accepted_waiting_vendor' => 'waiting_vendor',
            'ready_for_pickup' => 'waiting_pickup',
            'collecting' => 'to_pickup',
            'in_delivery' => $arrivedAtCustomer ? 'arrived_customer' : 'to_customer',
            'delivered' => 'completed',
            'incident' => 'problem',
            default => 'preparing',
        };

        $payload['delivery'] = [
            'label' => $this->publicItemsLabel($groupItems),
            'item_count' => (int) $groupItems->sum(fn (OrderItem $item) => max(1, (int) $item->quantity)),
            'reference_count' => $groupItems->count(),
            'items' => $this->publicItemsPayload($groupItems),
        ];

        $hasActualAssignment = $groupItems->contains(fn (OrderItem $item) => (bool) $item->latestDeliveryAssignment);
        $payload['vehicle'] = array_merge($payload['vehicle'] ?? [], [
            'assigned' => $hasActualAssignment,
            'vehicle_code' => ($forLogistics || $hasActualAssignment)
                ? ($group['vehicle_code'] ?? data_get($payload, 'vehicle.vehicle_code'))
                : null,
            'vehicle_label' => ($forLogistics || $hasActualAssignment)
                ? ($group['vehicle_label'] ?? data_get($payload, 'vehicle.vehicle_label'))
                : null,
            'vehicle_plate' => ($forLogistics || $hasActualAssignment)
                ? data_get($payload, 'vehicle.vehicle_plate')
                : null,
            'total_weight_kg' => ($forLogistics || $hasActualAssignment) ? (float) ($group['weight'] ?? 0) : null,
            'total_volume_m3' => ($forLogistics || $hasActualAssignment) ? (float) ($group['volume'] ?? 0) : null,
        ]);

        $payload['mission'] = [
            'number' => (string) ($group['mission_number'] ?? ''),
            'phase' => $phase,
            'label' => (string) ($group['operational_label'] ?? 'Préparation en cours'),
            'preparation_percent' => max(0, min(100, (int) ($group['preparation_percent'] ?? 0))),
            'pickup_count' => max(0, (int) ($group['pickup_count'] ?? 0)),
            'ready_pickup_count' => max(0, (int) ($group['ready_pickup_count'] ?? 0)),
            'driver_reserved' => filled($group['driver_id'] ?? null),
            'accepted_at' => optional($group['accepted_at'] ?? null)?->toIso8601String(),
        ];

        // Le client reçoit son code de sécurité dans ses notifications dès le
        // départ. Dans l'écran de suivi, le code n'est révélé qu'après que le
        // livreur a confirmé son arrivée chez le client.
        $otpCodes = $groupItems->pluck('delivery_otp_code')->filter()->map(fn ($code) => trim((string) $code))->unique()->values();
        $otpAvailable = $otpCodes->count() === 1;
        $otpVisible = ! $forLogistics && $arrivedAtCustomer && $otpAvailable && $publicStatus !== 'delivered';
        $payload['delivery_security'] = [
            'otp_required' => in_array($publicStatus, ['in_transit', 'arrived'], true),
            'otp_visible' => $otpVisible,
            'otp_code' => $otpVisible ? (string) $otpCodes->first() : null,
            'message' => match ($publicStatus) {
                'arrived' => 'Vérifiez tous vos articles puis communiquez le code au livreur.',
                'in_transit' => 'Gardez votre code confidentiel jusqu’à la remise complète des articles.',
                'delivered' => 'Livraison confirmée avec votre code de sécurité.',
                default => 'Le code de sécurité sera utilisé au moment de la remise au client.',
            },
        ];

        // Fenêtre lisible pour le client. L'ETA en temps réel reste la source
        // prioritaire lorsqu'une position GPS récente est disponible.
        $etaIso = $payload['eta'] ?? null;
        $payload['eta_window'] = null;
        if (filled($etaIso)) {
            try {
                $eta = \Carbon\Carbon::parse($etaIso);
                $spread = ($payload['eta_source'] ?? null) === 'live_route' ? 10 : 30;
                $payload['eta_window'] = [
                    'start' => $eta->copy()->subMinutes($spread)->toIso8601String(),
                    'end' => $eta->copy()->addMinutes($spread)->toIso8601String(),
                    'source' => $payload['eta_source'] ?? 'planned',
                ];
            } catch (\Throwable) {
                $payload['eta_window'] = null;
            }
        }

        if (isset($payload['driver']) && is_array($payload['driver'])) {
            $payload['driver']['contact_allowed'] = in_array($payload['tracking_phase'], ['to_customer', 'arrived_customer'], true);
        }

        $payload['sort_order'] = (int) $groupItems->min('id');

        if ($forLogistics) {
            $payload['mission_number'] = $group['mission_number'] ?? ($payload['mission_number'] ?? null);
            $payload['internal_group_key'] = $group['key'] ?? null;
            $payload['internal_item_ids'] = $groupItems->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        } else {
            unset($payload['pickup'], $payload['route_target'], $payload['internal_group_key'], $payload['internal_item_ids']);
        }

        return $payload;
    }

    /**
     * Contrat public partagé entre l'espace client Web et l'application mobile.
     * Les deux interfaces utilisent le même ShipmentTrackingController : ce bloc
     * garantit que le numéro, le total et la destination visibles restent
     * identiques sur les deux plateformes, même lorsque plusieurs livraisons
     * composent une seule commande.
     */
    private function sharedClientTrackingMeta(Order $order): array
    {
        return [
            'tracking_contract_version' => 4,
            'poll_seconds' => max(5, min(60, (int) config('delivery.client_poll_seconds', 12))),
            'order' => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'status' => (string) $order->status,
                'payment_status' => (string) $order->payment_status,
                'payment_method' => (string) $order->payment_method,
                'total' => (float) $order->total_amount,
                'address' => (string) ($order->delivery_address ?: $order->address ?: ''),
                'commune' => (string) ($order->delivery_commune ?: ''),
                'quartier' => (string) ($order->delivery_quartier ?: ''),
                'recipient_name' => (string) ($order->delivery_recipient_name ?: $order->customer_name ?: $order->client?->name ?: ''),
                'phone' => (string) ($order->delivery_recipient_phone ?: $order->phone ?: $order->client?->phone ?: ''),
                'delivery_min_date' => $order->delivery_min_date?->toDateString(),
                'delivery_max_date' => $order->delivery_max_date?->toDateString(),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ],
        ];
    }

    private function normalizedProvider(OrderItem $item): string
    {
        $provider = strtolower(trim((string) ($item->delivery_provider ?: $item->delivery_mode)));
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

    private function publicItemsPayload(Collection $items): array
    {
        return $items->map(fn (OrderItem $item) => [
            'order_item_id' => (int) $item->id,
            'product_id' => $item->product_id ? (int) $item->product_id : null,
            'name' => $item->product?->name ?: 'Produit OVANIE',
            'quantity' => max(1, (int) $item->quantity),
            'image_url' => $item->product?->main_image_url,
        ])->values()->all();
    }

    private function publicItemsLabel(Collection $items): string
    {
        $names = $items->pluck('product.name')->filter()->unique()->values();
        if ($names->isEmpty()) {
            return 'Articles de la commande';
        }

        $visible = $names->take(2)->implode(' · ');
        $remaining = $names->count() - min(2, $names->count());

        return $remaining > 0 ? $visible . ' · +' . $remaining : $visible;
    }

    /** @return array{0:float,1:float} */
    private function groupWeightAndVolume(Collection $items): array
    {
        $weight = $items->sum(function (OrderItem $item) {
            if ((float) $item->logistics_weight_kg > 0) {
                return (float) $item->logistics_weight_kg;
            }
            $unit = (float) ($item->product?->weight_kg ?? $item->product?->weight ?? 0);
            return max(0, $unit * max(1, (int) $item->quantity));
        });
        $volume = $items->sum(function (OrderItem $item) {
            if ((float) $item->logistics_volume_m3 > 0) {
                return (float) $item->logistics_volume_m3;
            }
            $unit = (float) ($item->product?->volume_m3 ?? 0);
            if ($unit <= 0 && $item->product) {
                $unit = ((float) $item->product->length_cm / 100)
                    * ((float) $item->product->width_cm / 100)
                    * ((float) $item->product->height_cm / 100);
            }
            return max(0, $unit * max(1, (int) $item->quantity));
        });

        return [round((float) $weight, 3), round((float) $volume, 4)];
    }

    private function assignmentSignalStatus(
        $assignment,
        bool $gpsUnavailable,
        bool $hasStoredGps,
        ?int $age
    ): string {
        if (! $assignment) {
            return 'waiting';
        }
        if ($gpsUnavailable) {
            return 'unavailable';
        }
        if (! $hasStoredGps || $age === null) {
            return 'standby';
        }

        $clientFresh = (int) config('delivery.client_gps_max_age_seconds', 180);
        $lostAfter = (int) config('delivery.gps_lost_seconds', 300);
        if ($age <= $clientFresh) {
            return 'active';
        }
        if ($age <= $lostAfter) {
            return 'weak';
        }
        return 'lost';
    }

    private function sellerSignalStatus($session, $location, bool $gpsUnavailable, bool $publicLiveTrackingAllowed): string
    {
        if ($gpsUnavailable) {
            return 'unavailable';
        }

        if (! $publicLiveTrackingAllowed) {
            return 'standby';
        }

        $recordedAt = $location?->recorded_at ?: $session?->last_location_at;
        if (! $recordedAt) {
            return 'waiting';
        }

        $age = $recordedAt->diffInSeconds(now());
        if ($age <= (int) config('delivery.gps_active_seconds', 90)) {
            return 'active';
        }
        if ($age <= (int) config('delivery.gps_weak_seconds', 180)) {
            return 'weak';
        }

        return 'lost';
    }

    private function activePayload(Collection $payloads): array
    {
        $activePayloads = $payloads->filter(fn (array $payload) => ! in_array(
            $payload['delivery_status'] ?? null,
            ['delivered', 'cancelled', 'returned', 'not_required'],
            true
        ));
        $active = $activePayloads->first(fn (array $payload) => (bool) ($payload['map_visible'] ?? false))
            ?? $activePayloads->first(fn (array $payload) => ($payload['tracking_mode'] ?? null) === 'gps')
            ?? $activePayloads->first();

        return $active ?? $payloads->last() ?? [];
    }

    private function isLogistics(Request $request): bool
    {
        if (Auth::guard('admin')->check()) {
            return true;
        }

        $user = $request->user();

        return (bool) $user && (
            (bool) $user->is_admin
            || in_array($user->role, ['logistique', 'logistics', 'admin'], true)
        );
    }

    private function geometry($geometry): ?array
    {
        if (is_array($geometry)) {
            return $geometry;
        }

        if (! is_string($geometry) || trim($geometry) === '') {
            return null;
        }

        $decoded = json_decode($geometry, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
