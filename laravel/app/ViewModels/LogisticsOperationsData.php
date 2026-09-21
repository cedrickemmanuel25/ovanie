<?php

namespace App\ViewModels;

use App\Models\DeliveryTour;
use App\Services\OrderWorkflowService;
use App\Services\OvanieShipmentConsolidationService;

/** Presentation data shared by operations, route planning and assignment. */
final class LogisticsOperationsData
{
    public static function driver($driver): array
    {
        $assignmentCount = isset($driver->assignments_count)
            ? (int) $driver->assignments_count
            : ($driver->relationLoaded('assignments') ? $driver->assignments->count() : null);

        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'initials' => $driver->initials,
            'rating' => $driver->rating ? number_format($driver->rating, 1, ',', '') : '—',
            'phone' => $driver->phone,
            'vehicle' => $driver->vehicle ?: 'Non renseigné',
            'zone' => $driver->zone ?: 'Non renseignée',
            'online' => (bool) $driver->is_online,
            'available' => ! ($driver->busy ?? in_array(mb_strtolower((string) $driver->status), ['en livraison', 'en mission'], true)),
            'compatible' => $driver->compatible ?? null,
            'pickupEta' => $driver->eta_collecte_min ?? null,
            'clientEta' => $driver->eta_client_min ?? null,
            'reviews' => $assignmentCount,
            'status' => $driver->status,
            'tourVehicleId' => $driver->tour_fleet_vehicle_id ?? null,
            'tourVehicleCode' => $driver->tour_vehicle_code ?? null,
            'tourVehicleLabel' => $driver->tour_vehicle_label ?? null,
            'tourVehicleRegistration' => $driver->tour_vehicle_registration ?? null,
            'pickupDistanceKm' => $driver->eta_collecte_distance_km ?? null,
            'clientDistanceKm' => $driver->eta_client_distance_km ?? null,
            'gpsRecordedAt' => $driver->assignment_gps_recorded_at ?? null,
        ];
    }

    public static function mission(array $group): array
    {
        $order = $group['order'];
        $item = $group['representative'];
        $shop = $group['shops']->first();
        $assignedDriver = $item->latestDeliveryAssignment?->driver;
        $freshPosition = $assignedDriver && $assignedDriver->last_seen_at?->gt(now()->subSeconds((int) config('delivery.gps_weak_seconds', 180)))
            && is_numeric($assignedDriver->latitude) && is_numeric($assignedDriver->longitude);

        $effectiveStatus = (string) ($group['display_status'] ?? $group['status'] ?? OrderWorkflowService::DELIVERY_PENDING);
        $items = $group['items'];
        $assignments = $items->map(fn ($line) => $line->latestDeliveryAssignment)->filter()->values();

        // Le workflow métier (display_status) reste à 'assigned' tant que le
        // livreur n'a pas récupéré la commande — c'est correct pour les
        // transitions/filtres. Mais côté affichage, un livreur qui a accepté
        // et démarré ses collectes ('collecting') n'est plus "en attente" :
        // sans ce correctif, le badge restait bloqué sur "Affecté — en
        // attente" alors que le livreur est réellement en route vers la
        // boutique, donnant l'impression que rien ne se passe.
        if ($effectiveStatus === OrderWorkflowService::DELIVERY_ASSIGNED
            && $assignments->isNotEmpty()
            && $assignments->contains(fn ($assignment) => (string) $assignment->status === 'collecting')
        ) {
            $effectiveStatus = 'en_route_pickup';
        }

        $allAssignmentsHavePickup = $assignments->isNotEmpty()
            && $assignments->count() === $items->count()
            && $assignments->every(fn ($assignment) => (bool) $assignment->picked_up_at);
        $allAssignmentsDeparted = $assignments->isNotEmpty()
            && $assignments->count() === $items->count()
            && $assignments->every(fn ($assignment) => in_array((string) $assignment->status, ['in_transit', 'arrived', 'delivered'], true));
        $allAssignmentsDelivered = $assignments->isNotEmpty()
            && $assignments->count() === $items->count()
            && $assignments->every(fn ($assignment) => (bool) $assignment->delivered_at || (string) $assignment->status === 'delivered');

        $pickupDone = $allAssignmentsHavePickup || ($items->isNotEmpty() && $items->every(fn ($line) => in_array((string) $line->delivery_status, [
            OrderWorkflowService::DELIVERY_PICKED_UP,
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
            OrderWorkflowService::DELIVERY_DELIVERED,
        ], true)));
        $departureDone = $allAssignmentsDeparted || ($items->isNotEmpty() && $items->every(fn ($line) => in_array((string) $line->delivery_status, [
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
            OrderWorkflowService::DELIVERY_DELIVERED,
        ], true)));
        $deliveredDone = $allAssignmentsDelivered || ($items->isNotEmpty() && $items->every(
            fn ($line) => (string) $line->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED
        ));
        $arrivedAt = $assignments->pluck('arrived_at')->filter()->sortBy(fn ($at) => $at->timestamp)->last();
        $arrivedDone = $deliveredDone || $assignments->contains(
            fn ($assignment) => in_array((string) $assignment->status, ['arrived', 'delivered'], true)
        );

        $pickupAt = $assignments->pluck('picked_up_at')->filter()->sortBy(fn ($at) => $at->timestamp)->last();
        if (! $pickupAt && $pickupDone) {
            $pickupAt = $order?->picked_up_at;
        }

        // Le départ vers le client est enregistré par OrderWorkflowService au
        // passage réel à in_transit. On n'utilise pas started_at car il marque
        // le début des collectes, pas le départ de la boutique.
        $departureAt = $departureDone ? $order?->in_transit_at : null;

        $deliveredAt = $assignments->pluck('delivered_at')->filter()->sortBy(fn ($at) => $at->timestamp)->last();
        if (! $deliveredAt && $deliveredDone) {
            $deliveredAt = $order?->delivered_at;
        }

        $timeLabel = static fn ($date): ?string => $date?->format('H:i');
        $etaLabel = (string) ($group['eta_label'] ?? 'À confirmer');
        $timeline = [
            [
                'label' => 'Collecte boutique',
                'state' => $pickupDone ? 'complete' : 'pending',
                'detail' => $pickupDone
                    ? 'Effectué' . ($timeLabel($pickupAt) ? ' à ' . $timeLabel($pickupAt) : '')
                    : 'En attente',
            ],
            [
                'label' => 'Départ livraison',
                'state' => $departureDone ? 'complete' : 'pending',
                'detail' => $departureDone
                    ? 'Départ' . ($timeLabel($departureAt) ? ' à ' . $timeLabel($departureAt) : ' confirmé')
                    : ($pickupDone ? 'En attente du départ' : 'En attente'),
            ],
            [
                'label' => 'En transit',
                'state' => $deliveredDone ? 'complete' : ($departureDone ? 'active' : 'pending'),
                'detail' => $deliveredDone
                    ? 'Terminé'
                    : ($departureDone
                        ? ($arrivedDone
                            ? 'Arrivé chez le client' . ($timeLabel($arrivedAt) ? ' à ' . $timeLabel($arrivedAt) : '')
                            : 'En cours')
                        : 'En attente'),
            ],
            [
                'label' => 'Livraison client',
                'state' => $deliveredDone ? 'complete' : 'pending',
                'detail' => $deliveredDone
                    ? 'Livré' . ($timeLabel($deliveredAt) ? ' à ' . $timeLabel($deliveredAt) : '')
                    : 'ETA ' . $etaLabel,
            ],
        ];

        $progress = $deliveredDone ? 100 : ($departureDone ? 75 : ($pickupDone ? 25 : 0));

        return [
            'id' => $item->id,
            'itemIds' => $group['items']->pluck('id')->all(),
            'reference' => $group['mission_number'],
            'order' => $order?->order_number ?? '—',
            'destination' => $order?->delivery_commune ?: ($order?->delivery_zone ?: 'À préciser'),
            'client' => $group['client_name'],
            'weight' => (float) $group['weight'],
            'volume' => (float) $group['volume'],
            'productsCount' => $group['article_count'],
            'vehicle' => $group['vehicle_label'],
            'vehicleCode' => $group['vehicle_code'],
            'status' => $effectiveStatus,
            'driver' => $group['driver_name'] ?: 'Non affecté',
            'driverId' => $group['driver_id'],
            // Le tracé n'a de sens qu'une fois que le livreur a réellement
            // démarré ses collectes (bouton "Démarrer les collectes" dans
            // l'app) : avant ça, même "accepté", il ne s'est pas encore mis
            // en route, donc aucun trajet réel n'existe à afficher.
            'assignmentAccepted' => $assignments->isNotEmpty() && $assignments->every(
                fn ($assignment) => in_array((string) $assignment->status, [
                    'collecting', 'picked_up', 'in_transit', 'arrived', 'delivered',
                ], true)
            ),
            'position' => $freshPosition ? ['lat' => (float) $assignedDriver->latitude, 'lng' => (float) $assignedDriver->longitude] : null,
            'products' => $group['items']->map(fn ($line) => $line->quantity . ' × ' . ($line->product?->name ?? 'Produit'))->values()->all(),
            'pickup' => $shop?->name ?: 'Lieu à préciser',
            'pickupZone' => $shop?->commune ?: 'À préciser',
            'lat' => $order?->delivery_latitude ?? $order?->delivery_lat,
            'lng' => $order?->delivery_longitude ?? $order?->delivery_lng,
            'assignUrl' => route('logistics.shipments.assign', $item),
            'assignmentDataUrl' => route('logistics.shipments.assignment-data', $item),
            'assignmentUrl' => route('logistics.shipments.assign-page', $item),
            'detailsUrl' => route('logistics.shipments.details', $item),
            'trackingUrl' => route('logistics.tracking.mission', ['item' => $item->id]),
            'delayed' => (bool) ($group['is_delayed'] ?? false),
            'delayMinutes' => isset($group['eta_at']) && $group['eta_at']->isPast() ? (int) $group['eta_at']->diffInMinutes(now()) : null,
            'eta' => $group['eta_label'] ?? 'À confirmer',
            'timeline' => $timeline,
            'progress' => $progress,
            'pickupDone' => $pickupDone,
            'routeSegments' => $group['items']->map(fn ($line) => $line->shipment?->route_geometry)
                ->filter()
                ->map(fn ($geometry) => is_string($geometry) ? json_decode($geometry, true) : $geometry)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public static function tour(DeliveryTour $tour): array
    {
        $groupedStops = $tour->stops->groupBy('sequence')->sortKeys();
        $stops = $groupedStops->map(function ($lines, $sequence) {
            $first = $lines->first();
            $items = $lines->pluck('orderItem')->filter()->unique('id')->values();
            $type = $first->stop_type ?: 'delivery';
            $statuses = $items->pluck('delivery_status')->filter();
            $done = $type === 'pickup'
                ? ($statuses->isNotEmpty() && $statuses->every(fn ($status) => in_array($status, [
                    OrderWorkflowService::DELIVERY_PICKED_UP,
                    OrderWorkflowService::DELIVERY_IN_TRANSIT,
                    OrderWorkflowService::DELIVERY_DELIVERED,
                ], true)))
                : ($statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === OrderWorkflowService::DELIVERY_DELIVERED));

            $geometry = $first->route_segment_geometry;
            if (is_string($geometry) && $geometry !== '') {
                $geometry = json_decode($geometry, true);
            }

            return [
                'sequence' => (int) $sequence,
                'key' => $first->stop_key,
                'label' => $first->label ?: ($first->commune ?: 'Arrêt à préciser'),
                'address' => $first->address,
                'type' => $type === 'pickup' ? 'Collecte' : 'Livraison',
                'kind' => $type === 'pickup' ? 'shop' : 'delivery',
                'missions' => max(1, $items->pluck('order_id')->filter()->unique()->count()),
                'time' => $first->estimated_arrival_at?->format('H:i') ?? 'À confirmer',
                'estimatedArrivalAt' => $first->estimated_arrival_at?->toIso8601String(),
                'completedTime' => $first->completed_at?->format('H:i'),
                'status' => $done ? 'done' : 'pending',
                'lat' => $first->latitude ?? ($first->orderItem?->order?->delivery_latitude ?? $first->orderItem?->order?->delivery_lat),
                'lng' => $first->longitude ?? ($first->orderItem?->order?->delivery_longitude ?? $first->orderItem?->order?->delivery_lng),
                'distanceFromPrevious' => $first->distance_from_previous_km,
                'durationFromPrevious' => $first->duration_from_previous_minutes,
                'routeGeometry' => is_array($geometry) ? $geometry : null,
                'routingProvider' => $first->routing_provider,
                'itemIds' => $items->pluck('id')->all(),
            ];
        })->values();

        if ($tour->status === 'in_progress') {
            $current = $stops->search(fn ($stop) => $stop['status'] !== 'done');
            if ($current !== false) {
                $stops[$current] = array_merge($stops[$current], ['status' => 'in_progress']);
            }
        }

        $deliveryStops = $stops->where('type', 'Livraison')->values();
        $done = $deliveryStops->where('status', 'done')->count();
        $total = $deliveryStops->count();
        $items = $tour->stops->pluck('orderItem')->filter()->unique('id')->values();
        $weight = $items->sum(fn ($item) => $item->logistics_weight_kg ?: (($item->product?->weight_kg ?? $item->product?->weight ?? 0) * $item->quantity));
        $volume = $items->sum(fn ($item) => $item->logistics_volume_m3 ?: (($item->product?->volume_m3 ?? 0) * $item->quantity));
        $legacyCapacity = collect(OvanieShipmentConsolidationService::vehicleOptions())->firstWhere('code', $tour->vehicle_code);
        $capacityWeight = $tour->vehicle_capacity_kg ?: ($legacyCapacity['weight'] ?? null);
        $capacityVolume = $tour->vehicle_volume_m3 ?: ($legacyCapacity['volume'] ?? null);
        $duration = $tour->route_is_complete ? $tour->duration_minutes : null;
        $elapsed = $tour->started_at ? max(0, (int) $tour->started_at->diffInMinutes(now())) : null;
        $routeSegments = $stops->pluck('routeGeometry')->filter()->values()->all();
        if ($routeSegments === []) {
            $routeSegments = $items->map(fn ($item) => $item->shipment?->route_geometry)
                ->filter()
                ->map(fn ($geometry) => is_string($geometry) ? json_decode($geometry, true) : $geometry)
                ->filter()
                ->values()
                ->all();
        }

        $missions = $deliveryStops->map(function ($stop) use ($tour) {
            $lines = $tour->stops->where('sequence', $stop['sequence']);
            $items = $lines->pluck('orderItem')->filter()->unique('id')->values();
            $first = $items->first();
            $assignment = $first?->latestDeliveryAssignment;
            $reference = $assignment?->resolved_mission_number
                ?: ($first ? sprintf('OVL-%05d-%02d', (int) $first->order_id, (int) $stop['sequence']) : '—');
            $weight = $items->sum(fn ($item) => $item->logistics_weight_kg ?: (($item->product?->weight_kg ?? 0) * $item->quantity));
            $status = $items->isNotEmpty() && $items->every(fn ($item) => $item->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED)
                ? 'done'
                : ($tour->status === 'in_progress' ? 'current' : 'planned');

            return [
                'reference' => $reference,
                'destination' => $stop['label'],
                'weight' => round($weight, 1),
                'status' => $status,
                'detailsUrl' => $first ? route('logistics.shipments.details', $first) : '#',
            ];
        })->values()->all();

        $driverData = $tour->driver ? self::driver($tour->driver) : null;
        $vehicleLabel = $tour->vehicle_label ?: 'Non attribué';
        $plate = $tour->vehicle_plate ?: '—';

        return [
            'id' => $tour->id,
            'reference' => $tour->reference,
            'status' => $tour->status,
            'driver' => $driverData,
            'vehicle' => $vehicleLabel,
            'plate' => $plate,
            'fleetVehicleId' => $tour->fleet_vehicle_id,
            'date' => $tour->tour_date?->format('Y-m-d'),
            'departure' => substr($tour->departure_time ?? '', 0, 5) ?: '—',
            'zone' => $tour->zone_label,
            'communes' => $deliveryStops->pluck('label')->unique()->implode(', '),
            'stops' => $stops->all(),
            'stopOrder' => $stops->pluck('key')->filter()->values()->all(),
            'canEditOrder' => $tour->status === 'planned' && $stops->count() > 1 && $stops->every(fn ($stop) => filled($stop['key'])),
            'done' => $done,
            'total' => $total,
            'routeSegments' => $routeSegments,
            'percent' => $total ? (int) round($done / $total * 100) : 0,
            'weight' => round($weight, 1),
            'volume' => round($volume, 2),
            'products' => (int) $items->sum('quantity'),
            'capacityWeight' => $capacityWeight,
            'capacityVolume' => $capacityVolume,
            'distance' => $tour->route_is_complete ? $tour->distance_km : null,
            'duration' => $duration,
            'elapsed' => $elapsed,
            'remaining' => $duration !== null && $elapsed !== null ? max(0, $duration - $elapsed) : null,
            'fuel' => null,
            'optimized' => (bool) $tour->optimization_enabled,
            'routeComplete' => (bool) $tour->route_is_complete,
            'routingProvider' => $tour->routing_provider,
            'optimizedAt' => $tour->optimized_at?->format('d/m/Y H:i'),
            'start' => [
                'label' => $tour->start_label ?: 'Départ à confirmer',
                'lat' => $tour->start_latitude,
                'lng' => $tour->start_longitude,
                'status' => 'start',
                'kind' => 'driver',
            ],
            'detailsUrl' => route('logistics.tours.show', $tour),
            'selectUrl' => route('logistics.tours.index', ['tour' => $tour->id]),
            'trackingUrl' => route('logistics.active-deliveries', ['tour' => $tour->id]),
            'reoptimizeUrl' => route('logistics.tours.reoptimize', $tour),
            'reorderUrl' => route('logistics.tours.reorder', $tour),
            'missions' => $missions,
        ];
    }

    public static function duration($minutes): string
    {
        if ($minutes === null) {
            return 'À confirmer';
        }
        $minutes = max(0, (int) $minutes);

        return $minutes >= 60
            ? intdiv($minutes, 60) . ' h ' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) . ' min'
            : $minutes . ' min';
    }
}
