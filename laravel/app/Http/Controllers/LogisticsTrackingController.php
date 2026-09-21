<?php

namespace App\Http\Controllers;

use App\Models\DriverLocation;
use App\Models\OrderItem;
use App\Models\ReturnModel;
use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\MapDataService;
use App\Services\Geo\MissionRoutingService;
use App\Services\Geo\WazeLinkService;
use App\Services\OrderWorkflowService;
use App\Services\OvanieShipmentConsolidationService;
use App\Services\SellerDeliverySupervisionService;
use App\Support\LogisticsOperationalDataScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LogisticsTrackingController extends Controller
{
    public function data(MapDataService $mapData)
    {
        return response()->json($mapData->logisticsMapData());
    }

    public function suiviLivraisons(
        WazeLinkService $waze,
        SellerDeliverySupervisionService $supervision,
        OvanieShipmentConsolidationService $consolidation,
        MissionRoutingService $routing,
        DeliveryCoordinateService $coordinates,
    ) {
        $items = OrderItem::query()
            ->with([
                'order.client',
                'product.shop',
                'shipment.latestDriverLocation',
                'latestDeliveryAssignment.driver.currentLocation',
                'latestDeliveryAssignment.latestLocation',
            ])
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE)
            ->whereHas('order', fn ($order) => LogisticsOperationalDataScope::orders($order))
            ->whereHas('latestDeliveryAssignment', fn ($assignment) => LogisticsOperationalDataScope::assignments($assignment))
            ->whereIn('delivery_status', [
                OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                OrderWorkflowService::DELIVERY_ASSIGNED,
                OrderWorkflowService::DELIVERY_PICKED_UP,
                OrderWorkflowService::DELIVERY_IN_TRANSIT,
                OrderWorkflowService::DELIVERY_FAILED,
                OrderWorkflowService::DELIVERY_DELIVERED,
            ])
            ->latest('vendor_delivery_updated_at')
            ->take(300)
            ->get();

        $ovanieRows = $this->ovanieMissionRows($items, $waze, $consolidation, $routing, $coordinates);
        $sellerRows = $supervision->trackingRows()->map(fn (array $row) => $this->sellerApiRow($row, $waze));
        $allRows = $ovanieRows
            ->concat($sellerRows)
            ->filter(function (array $row) {
                $bucket = $row['status_bucket'] ?? null;

                if (in_array($bucket, ['pickup', 'in_route', 'delay'], true)) {
                    return true;
                }

                if ($bucket !== 'delivered' || blank($row['delivered_at'] ?? null)) {
                    return false;
                }

                try {
                    return Carbon::parse($row['delivered_at'])->isToday();
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values();

        $activeReturns = Schema::hasTable('returns')
            ? ReturnModel::query()->where(function ($query) {
                $query->whereNull('order_reference')->orWhere('order_reference', 'not like', 'CMD-DEMO-RETURN-%');
            })->whereIn('logistics_status', [
                ReturnModel::LOGISTICS_PENDING_PICKUP,
                ReturnModel::LOGISTICS_PICKUP_PLANNED,
                ReturnModel::LOGISTICS_IN_TRANSIT,
                ReturnModel::LOGISTICS_RECEIVED,
            ])->count()
            : 0;

        return response()->json([
            'stats' => [
                'active' => $allRows->whereIn('status_bucket', ['pickup', 'in_route', 'delay'])->count(),
                'delivered_today' => $allRows->where('status_bucket', 'delivered')->count(),
                'late' => $allRows->where('status_bucket', 'delay')->count(),
                'returns' => $activeReturns,
                'last_update' => now()->toIso8601String(),
            ],
            'deliveries' => $allRows,
            'zones' => $allRows->pluck('commune')->filter()->unique()->sort()->values(),
        ]);
    }

    private function ovanieMissionRows(
        Collection $items,
        WazeLinkService $waze,
        OvanieShipmentConsolidationService $consolidation,
        MissionRoutingService $routing,
        DeliveryCoordinateService $coordinates,
    ): Collection {
        return $items
            ->filter(fn (OrderItem $item) => $item->latestDeliveryAssignment && filled($item->latestDeliveryAssignment->resolved_mission_number))
            ->groupBy(fn (OrderItem $item) => $item->latestDeliveryAssignment->resolved_mission_number)
            ->map(function (Collection $missionItems, string $missionNumber) use ($waze, $consolidation, $routing, $coordinates) {
                /** @var OrderItem|null $representative */
                $representative = $missionItems->first();
                if (! $representative) {
                    return null;
                }

                $group = $consolidation->groupForItem($representative);
                $groupItems = collect($group['items'] ?? $missionItems)->values();
                $order = $representative->order;
                $assignments = $groupItems->pluck('latestDeliveryAssignment')->filter()->unique('id')->values();
                $assignment = $assignments->sortByDesc('id')->first();
                $driver = $assignment?->driver;
                $driverLocation = $assignment?->latestLocation;

                if (! $driverLocation && $assignment && $driver) {
                    // Une position d'une autre mission de la même commande ne doit
                    // jamais déplacer ce livreur sur la mauvaise carte.
                    $driverLocation = DriverLocation::query()
                        ->where('driver_id', $driver->id)
                        ->where(function ($query) use ($assignment, $missionNumber) {
                            $query->where('delivery_assignment_id', $assignment->id)
                                ->orWhere('mission_number', $missionNumber);
                        })
                        ->latest('recorded_at')
                        ->latest('id')
                        ->first();
                }

                $shipment = $representative->shipment;

                $status = $this->missionStatus($groupItems->pluck('delivery_status'), $assignments->pluck('status'));
                $assignmentStatus = strtolower(trim((string) ($assignment?->status ?? '')));
                $pickupLegActive = in_array($assignmentStatus, ['accepted', 'collecting'], true);
                $deliveryLegActive = in_array($assignmentStatus, ['picked_up', 'in_transit', 'arrived', 'delivered'], true)
                    || in_array($status, [
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        OrderWorkflowService::DELIVERY_DELIVERED,
                    ], true);
                $missionOperational = $pickupLegActive || $deliveryLegActive;
                $gpsUnavailable = in_array($assignment?->gps_status, ['unavailable', 'denied', 'disabled'], true);
                $lastSeen = $gpsUnavailable ? null : ($driverLocation?->recorded_at ?: $assignment?->gps_last_seen_at);
                $signalAgeSeconds = $lastSeen ? $lastSeen->diffInSeconds(now()) : null;
                $missionMoving = $missionOperational && $status !== OrderWorkflowService::DELIVERY_DELIVERED;
                $signal = $gpsUnavailable
                    ? 'unavailable'
                    : (! $lastSeen
                        ? ($missionMoving ? 'waiting' : 'standby')
                        : ($signalAgeSeconds <= (int) config('delivery.gps_active_seconds', 90)
                            ? 'active'
                            : ($signalAgeSeconds <= (int) config('delivery.gps_weak_seconds', 180)
                                ? 'weak'
                                : (! $missionMoving
                                    ? 'standby'
                                    : ($signalAgeSeconds <= (int) config('delivery.gps_lost_seconds', 300) ? 'stale' : 'lost')))));

                $destination = $coordinates->forOrder($order);
                $destinationLat = $destination['latitude'];
                $destinationLng = $destination['longitude'];
                $destinationAddress = $this->deliveryAddress($order);
                // Les boutiques restent visibles comme repères de mission, même après
                // l'enlèvement. Seuls les arrêts encore opérationnels sont transmis au
                // moteur de routage afin d'éviter de renvoyer le chauffeur en arrière.
                $allPickupStops = collect($group['pickup_stops'] ?? [])->map(fn (array $stop) => [
                    'id' => $stop['id'] ?? $stop['shop']?->id,
                    'name' => $stop['shop']?->name ?: 'Point de collecte',
                    'address' => $stop['address'] ?? '',
                    'latitude' => $stop['latitude'] ?? null,
                    'longitude' => $stop['longitude'] ?? null,
                    'weight' => $stop['weight'] ?? 0,
                    'volume' => $stop['volume'] ?? 0,
                ])->values();

                $pickupsCompleted = $deliveryLegActive;
                $pickupStops = $pickupLegActive ? $allPickupStops->all() : [];
                $driverPoint = ['name' => $driver?->name ?: 'Livreur', 'destination_address' => $destinationAddress];
                $hasCoordinates = ! $gpsUnavailable
                    && is_numeric($driverLocation?->latitude)
                    && is_numeric($driverLocation?->longitude);
                $hasRecentPosition = $hasCoordinates
                    && $signalAgeSeconds !== null
                    && $signalAgeSeconds <= (int) config('delivery.gps_lost_seconds', 300);
                $hasRoutingPosition = $hasCoordinates
                    && $signalAgeSeconds !== null
                    && $signalAgeSeconds <= (int) config('delivery.gps_weak_seconds', 180);

                if ($missionOperational && $hasRecentPosition) {
                    $driverPoint['latitude'] = (float) $driverLocation->latitude;
                    $driverPoint['longitude'] = (float) $driverLocation->longitude;
                }

                $routePlan = [
                    'success' => false,
                    'provider' => null,
                    'message' => $missionOperational
                        ? 'Le tracé sera calculé après réception d’une position GPS récente du livreur.'
                        : 'Le tracé sera calculé uniquement après acceptation de la mission par le livreur.',
                    'geometry' => null,
                    'distance_km' => null,
                    'duration_minutes' => null,
                    'traffic_delay_minutes' => null,
                    'points' => [],
                    'route_phase' => $pickupLegActive ? 'pickup' : ($deliveryLegActive ? 'delivery' : 'waiting'),
                ];

                if ($missionOperational && $hasRoutingPosition) {
                    $routePlan = $routing->build(
                        $pickupStops,
                        $destinationLat,
                        $destinationLng,
                        (string) ($group['vehicle_code'] ?? 'pickup'),
                        $driverPoint,
                        [
                            'include_destination' => $deliveryLegActive,
                            'require_driver' => true,
                            'phase' => $pickupLegActive ? 'pickup' : 'delivery',
                        ]
                    );
                }

                $mapPoints = collect();
                if ($missionOperational && isset($driverPoint['latitude'], $driverPoint['longitude'])) {
                    $mapPoints->push([
                        'id' => 'driver',
                        'type' => 'driver',
                        'name' => $driverPoint['name'],
                        'address' => 'Position actuelle',
                        'latitude' => $driverPoint['latitude'],
                        'longitude' => $driverPoint['longitude'],
                    ]);
                }
                $allPickupStops->each(function (array $stop) use ($mapPoints, $pickupsCompleted) {
                    if (! is_numeric($stop['latitude'] ?? null) || ! is_numeric($stop['longitude'] ?? null)) {
                        return;
                    }
                    $mapPoints->push(array_merge($stop, [
                        'type' => 'pickup',
                        'completed' => $pickupsCompleted,
                        'context_only' => $pickupsCompleted,
                    ]));
                });
                if ($deliveryLegActive && is_numeric($destinationLat) && is_numeric($destinationLng)) {
                    $mapPoints->push([
                        'id' => 'destination',
                        'type' => 'destination',
                        'name' => 'Destination client',
                        'address' => $destinationAddress,
                        'latitude' => (float) $destinationLat,
                        'longitude' => (float) $destinationLng,
                    ]);
                }

                $etaAt = $assignment?->manual_eta_at
                    ?: $assignment?->estimated_delivery_at
                    ?: $groupItems->pluck('shipment.estimated_delivery_at')->filter()->sort()->first();
                $isDelivered = $status === OrderWorkflowService::DELIVERY_DELIVERED;
                $deliveredAt = $groupItems
                    ->pluck('delivery_completed_at')
                    ->filter()
                    ->sortDesc()
                    ->first()
                    ?: $groupItems->pluck('vendor_delivery_updated_at')->filter()->sortDesc()->first();
                $inRoute = $deliveryLegActive && ! $isDelivered;
                $collecting = $pickupLegActive;
                $hasOperationalIncident = $assignments->pluck('status')->contains('incident')
                    || $status === OrderWorkflowService::DELIVERY_FAILED;
                $isEtaLate = $status === OrderWorkflowService::DELIVERY_IN_TRANSIT
                    && $etaAt
                    && $etaAt->lt(now()->subMinutes(15));
                $isLate = $inRoute && ($hasOperationalIncident || $isEtaLate);
                $bucket = $isDelivered ? 'delivered' : ($isLate ? 'delay' : ($collecting ? 'pickup' : ($inRoute ? 'in_route' : 'pending')));
                $wazeUrl = is_numeric($destinationLat) && is_numeric($destinationLng)
                    ? $waze->navigationUrl((float) $destinationLat, (float) $destinationLng)
                    : null;

                return [
                    'id' => 'ovanie-mission-' . $missionNumber,
                    'mission_number' => $missionNumber,
                    'provider_type' => OrderWorkflowService::PROVIDER_OVANIE,
                    'provider_label' => 'OVANIE Logistics',
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'order_reference' => $order?->order_number,
                    'detail_url' => route('logistics.shipments.details', $representative),
                    'client_name' => $order?->client?->name ?: 'Client',
                    'commune' => $order?->delivery_commune ?: $order?->delivery_city ?: '—',
                    'address' => $destinationAddress,
                    'shop_name' => collect($group['shops'] ?? [])->pluck('name')->filter()->implode(', '),
                    'driver_name' => $driver?->name,
                    'driver_phone' => $driver?->phone,
                    'vehicle_plate' => $representative->vehicle_plate,
                    'status' => $status,
                    'status_bucket' => $bucket,
                    'assignment_status' => $assignmentStatus ?: null,
                    'tracking_phase' => $pickupLegActive ? 'to_pickup' : ($deliveryLegActive ? 'to_customer' : 'waiting_start'),
                    'route_target' => $pickupLegActive ? 'pickup' : ($deliveryLegActive ? 'destination' : null),
                    'signal_status' => $signal,
                    'signal_label' => match ($signal) {
                        'active' => 'GPS actif',
                        'weak' => 'Dernière position reçue récemment',
                        'stale' => 'Mise à jour GPS en attente',
                        'lost' => 'Position GPS non actualisée',
                        'standby' => 'GPS en attente du départ',
                        'unavailable' => 'Suivi manuel actif',
                        default => 'En attente du premier signal GPS',
                    },
                    'gps_status' => $assignment?->gps_status ?: 'unknown',
                    'gps_disabled_reason' => $assignment?->gps_disabled_reason,
                    'estimated_delivery_at' => $etaAt?->toIso8601String(),
                    'delivered_at' => $deliveredAt?->toIso8601String(),
                    'eta_label' => $isDelivered ? 'Livré' : ($etaAt?->format('d/m/Y à H:i') ?: 'À confirmer'),
                    'eta' => $isDelivered ? 'Livré' : ($etaAt?->format('d/m/Y à H:i') ?: 'À confirmer'),
                    'last_seen_at' => $lastSeen?->toIso8601String(),
                    'signal_age_seconds' => $signalAgeSeconds,
                    'last_position_label' => $lastSeen?->diffForHumans() ?: 'Aucune position reçue',
                    'gps_accuracy_m' => is_numeric($driverLocation?->accuracy) ? round((float) $driverLocation->accuracy) : null,
                    'lat' => $gpsUnavailable ? null : ($driverPoint['latitude'] ?? null),
                    'lng' => $gpsUnavailable ? null : ($driverPoint['longitude'] ?? null),
                    'driver_lat' => $gpsUnavailable ? null : ($driverPoint['latitude'] ?? null),
                    'driver_lng' => $gpsUnavailable ? null : ($driverPoint['longitude'] ?? null),
                    'pickup_lat' => data_get($allPickupStops, '0.latitude'),
                    'pickup_lng' => data_get($allPickupStops, '0.longitude'),
                    'destination_lat' => is_numeric($destinationLat) ? (float) $destinationLat : null,
                    'destination_lng' => is_numeric($destinationLng) ? (float) $destinationLng : null,
                    'collection_count' => (int) ($group['pickup_count'] ?? 0),
                    'item_count' => (int) ($group['article_count'] ?? $groupItems->sum('quantity')),
                    'route_points' => $routePlan['points'] ?? [],
                    'route_phase' => $routePlan['route_phase'] ?? ($pickupLegActive ? 'pickup' : ($deliveryLegActive ? 'delivery' : 'waiting')),
                    'map_points' => $mapPoints->values()->all(),
                    'route' => [
                        'distance_km' => $routePlan['distance_km'] ?? null,
                        'duration_minutes' => $routePlan['duration_minutes'] ?? null,
                        'traffic_delay_minutes' => $routePlan['traffic_delay_minutes'] ?? null,
                        'geometry' => $routePlan['geometry'] ?? null,
                        'provider' => $routePlan['provider'] ?? null,
                        'success' => (bool) ($routePlan['success'] ?? false),
                        'message' => $routePlan['message'] ?? null,
                    ],
                    'pickup' => [
                        'lat' => data_get($allPickupStops, '0.latitude'),
                        'lng' => data_get($allPickupStops, '0.longitude'),
                        'address' => data_get($allPickupStops, '0.address'),
                    ],
                    'destination' => [
                        'lat' => is_numeric($destinationLat) ? (float) $destinationLat : null,
                        'lng' => is_numeric($destinationLng) ? (float) $destinationLng : null,
                        'address' => $destinationAddress,
                    ],
                    'vehicle' => [
                        'driver_vehicle' => $driver?->vehicle,
                        'vehicle_code' => $group['vehicle_code'] ?? $shipment?->vehicle_code,
                        'vehicle_label' => $group['vehicle_label'] ?? $shipment?->vehicle_label,
                        'vehicle_plate' => $representative->vehicle_plate,
                        'total_weight_kg' => (float) ($group['weight'] ?? 0),
                        'total_volume_m3' => (float) ($group['volume'] ?? 0),
                    ],
                    'navigation' => ['waze_url' => $wazeUrl],
                ];
            })
            ->filter()
            ->values();
    }

    private function sellerApiRow(array $row, WazeLinkService $waze): array
    {
        $gpsUnavailable = ($row['signal_status'] ?? null) === 'unavailable';
        $destinationLat = $row['destination_lat'] ?? null;
        $destinationLng = $row['destination_lng'] ?? null;

        return array_merge($row, [
            'order_reference' => $row['order_number'] ?? null,
            'detail_url' => null,
            'lat' => $gpsUnavailable ? null : ($row['driver_lat'] ?? null),
            'lng' => $gpsUnavailable ? null : ($row['driver_lng'] ?? null),
            'pickup' => [
                'lat' => $row['pickup_lat'] ?? null,
                'lng' => $row['pickup_lng'] ?? null,
                'address' => $row['shop_name'] ?? null,
            ],
            'destination' => [
                'lat' => $destinationLat,
                'lng' => $destinationLng,
                'address' => $row['address'] ?? null,
            ],
            'vehicle' => [
                'vehicle_code' => null,
                'vehicle_label' => $row['vehicle_label'] ?? 'Véhicule vendeur',
                'vehicle_plate' => $row['vehicle_plate'] ?? null,
                'total_weight_kg' => null,
                'total_volume_m3' => null,
            ],
            'navigation' => [
                'waze_url' => is_numeric($destinationLat) && is_numeric($destinationLng)
                    ? $waze->navigationUrl((float) $destinationLat, (float) $destinationLng)
                    : null,
            ],
        ]);
    }

    private function missionStatus(Collection $deliveryStatuses, Collection $assignmentStatuses): string
    {
        if ($assignmentStatuses->contains('incident') || $deliveryStatuses->contains(OrderWorkflowService::DELIVERY_FAILED)) {
            return OrderWorkflowService::DELIVERY_FAILED;
        }
        if ($deliveryStatuses->isNotEmpty() && $deliveryStatuses->every(fn ($status) => $status === OrderWorkflowService::DELIVERY_DELIVERED)) {
            return OrderWorkflowService::DELIVERY_DELIVERED;
        }
        foreach ([
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
            OrderWorkflowService::DELIVERY_PICKED_UP,
            OrderWorkflowService::DELIVERY_ASSIGNED,
            OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
        ] as $status) {
            if ($deliveryStatuses->contains($status)) {
                return $status;
            }
        }

        return OrderWorkflowService::DELIVERY_PREPARING;
    }

    private function deliveryAddress($order): string
    {
        return collect([
            $order?->delivery_address,
            $order?->address,
            $order?->delivery_quartier,
            $order?->delivery_commune,
            $order?->delivery_city,
        ])->filter()->unique()->implode(' - ');
    }
}
