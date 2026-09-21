<?php

namespace App\Services;

use App\Models\DeliveryDriver;
use App\Models\DeliveryTour;
use App\Models\DeliveryTourStop;
use App\Models\OrderItem;
use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\RoutingService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Construit les tournées uniquement à partir des points réellement enregistrés
 * (boutiques, adresses client et position GPS du livreur) et des durées renvoyées
 * par le moteur routier configuré dans OVANIE.
 */
class LogisticsTourPlanningService
{
    private array $routeMemo = [];

    public function __construct(
        private readonly RoutingService $routing,
        private readonly DeliveryCoordinateService $coordinates,
    ) {
    }

    /**
     * @param Collection<int,array> $groups Groupes retournés par OvanieShipmentConsolidationService.
     * @param array<string,mixed> $vehicle Véhicule réel sélectionné depuis logistics_fleet_vehicles.
     * @param array<int,string> $manualOrder Clés de stops, dans l'ordre voulu.
     * @return array<string,mixed>
     */
    public function plan(
        Collection $groups,
        DeliveryDriver $driver,
        array $vehicle,
        CarbonInterface $departureAt,
        bool $optimize = true,
        array $manualOrder = [],
    ): array {
        $nodes = collect($this->buildNodes($groups));
        if ($nodes->isEmpty()) {
            throw ValidationException::withMessages([
                'mission_items' => 'Aucune mission réelle ne peut être utilisée pour cette tournée.',
            ]);
        }

        if ($this->normalizeVehicleCode((string) ($vehicle['type'] ?? '')) === 'moto') {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Une moto ne peut pas effectuer une tournée. Utilisez au minimum un Tricycle.',
            ]);
        }

        $this->assertVehicleCapacity($vehicle, $groups);
        $driver->loadMissing('currentLocation');
        $start = $this->resolveStart($driver, $nodes);

        if ($manualOrder !== []) {
            $ordered = $this->manualOrder($nodes, $manualOrder);
        } elseif ($optimize) {
            $ordered = $this->optimizedOrder($nodes, $start);
        } else {
            $ordered = $this->naturalOrder($nodes);
        }

        return $this->routeOrderedNodes($ordered, $start, $departureAt, $vehicle, $groups, $optimize && $manualOrder === []);
    }

    /** @param array<string,mixed> $plan */
    public function persist(DeliveryTour $tour, array $plan): void
    {
        $tour->stops()->delete();

        foreach ($plan['stops'] as $stop) {
            foreach ($stop['itemIds'] as $itemId) {
                DeliveryTourStop::create([
                    'delivery_tour_id' => $tour->id,
                    'order_item_id' => $itemId,
                    'sequence' => $stop['sequence'],
                    'commune' => $stop['commune'] ?? null,
                    'status' => 'pending',
                    'stop_key' => $stop['key'],
                    'stop_type' => $stop['type'],
                    'label' => $stop['label'],
                    'address' => $stop['address'] ?? null,
                    'latitude' => $stop['lat'],
                    'longitude' => $stop['lng'],
                    'distance_from_previous_km' => $stop['distanceFromPrevious'],
                    'duration_from_previous_minutes' => $stop['durationFromPrevious'],
                    'estimated_arrival_at' => $stop['estimatedArrivalAt'],
                    'route_segment_geometry' => $stop['routeGeometry']
                        ? json_encode($stop['routeGeometry'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : null,
                    'routing_provider' => $stop['routingProvider'] ?? null,
                ]);
            }
        }

        $tour->forceFill([
            'distance_km' => $plan['complete'] ? round((float) $plan['distance'], 2) : null,
            'duration_minutes' => $plan['complete'] ? (int) $plan['duration'] : null,
            'routing_provider' => $plan['routingProvider'],
            'route_is_complete' => (bool) $plan['complete'],
            'optimized_at' => now(),
            'start_label' => $plan['start']['label'],
            'start_latitude' => $plan['start']['lat'],
            'start_longitude' => $plan['start']['lng'],
        ])->save();
    }

    /** @return array<string,mixed> */
    public function vehicleSnapshot(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'code' => (string) ($row->code ?? ''),
            'type' => (string) ($row->vehicle_type ?? ''),
            'type_code' => $this->normalizeVehicleCode((string) ($row->vehicle_type ?? '')),
            'registration' => (string) ($row->registration ?? ''),
            'brand' => (string) ($row->brand ?? ''),
            'model' => (string) ($row->model ?? ''),
            'capacity_kg' => (float) ($row->capacity_kg ?? 0),
            'volume_m3' => (float) ($row->volume_m3 ?? 0),
            'status' => (string) ($row->status ?? ''),
            'driver_name' => (string) ($row->driver_name ?? ''),
            'zone' => (string) ($row->zone ?? ''),
        ];
    }

    /** @param Collection<int,array> $groups */
    public function assertVehicleCapacity(array $vehicle, Collection $groups): void
    {
        $weight = (float) $groups->sum('weight');
        $volume = (float) $groups->sum('volume');
        $capacity = (float) ($vehicle['capacity_kg'] ?? 0);
        $volumeCapacity = (float) ($vehicle['volume_m3'] ?? 0);

        if ($capacity <= 0 || $weight > $capacity) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => sprintf(
                    'Le véhicule sélectionné ne peut pas transporter %.1f kg (capacité enregistrée : %.1f kg).',
                    $weight,
                    $capacity,
                ),
            ]);
        }

        if ($volume > 0 && $volumeCapacity > 0 && $volume > $volumeCapacity) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => sprintf(
                    'Le volume de la tournée est de %.2f m³, supérieur aux %.2f m³ enregistrés pour ce véhicule.',
                    $volume,
                    $volumeCapacity,
                ),
            ]);
        }
    }

    /** @param Collection<int,array> $groups */
    private function buildNodes(Collection $groups): array
    {
        $nodes = [];

        foreach ($groups->values() as $groupIndex => $group) {
            /** @var OrderItem|null $representative */
            $representative = $group['representative'] ?? null;
            if (! $representative) {
                continue;
            }

            $missionKey = 'mission:' . $representative->id;
            $pickupKeys = [];

            foreach (($group['pickup_stops'] ?? collect())->values() as $pickupIndex => $pickup) {
                $shop = $pickup['shop'] ?? null;
                $pickupItems = collect($pickup['items'] ?? []);
                $shipment = $pickupItems->map(fn ($item) => $item->shipment)->filter()->first();
                $lat = $this->validPoint($shipment?->pickup_latitude, $shipment?->pickup_longitude)
                    ? (float) $shipment->pickup_latitude
                    : ($this->validPoint($shop?->latitude, $shop?->longitude) ? (float) $shop->latitude : null);
                $lng = $this->validPoint($shipment?->pickup_latitude, $shipment?->pickup_longitude)
                    ? (float) $shipment->pickup_longitude
                    : ($this->validPoint($shop?->latitude, $shop?->longitude) ? (float) $shop->longitude : null);
                $key = sprintf('pickup:%d:%s:%d', $representative->id, (string) ($pickup['id'] ?? 'shop'), $pickupIndex + 1);
                $pickupKeys[] = $key;
                $nodes[] = [
                    'key' => $key,
                    'missionKey' => $missionKey,
                    'type' => 'pickup',
                    'label' => $shop?->name ?: 'Point de collecte',
                    'address' => (string) ($pickup['address'] ?? $shipment?->pickup_address ?? ''),
                    'commune' => $shop?->commune ?: ($group['order']?->delivery_commune ?? null),
                    'lat' => $lat,
                    'lng' => $lng,
                    'itemIds' => $pickupItems->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'dependsOn' => [],
                    'natural' => ($groupIndex * 100) + $pickupIndex,
                ];
            }

            $order = $group['order'] ?? null;
            $destination = $this->coordinates->forOrder($order);
            $deliveryShipment = collect($group['items'] ?? [])->map(fn ($item) => $item->shipment)->filter()->first();
            $deliveryLat = $destination['verified'] ? $destination['latitude'] : null;
            $deliveryLng = $destination['verified'] ? $destination['longitude'] : null;
            if ($deliveryLat === null && $deliveryShipment && $this->validPoint($deliveryShipment->delivery_latitude, $deliveryShipment->delivery_longitude)) {
                $deliveryLat = (float) $deliveryShipment->delivery_latitude;
                $deliveryLng = (float) $deliveryShipment->delivery_longitude;
            }

            $destinationLabel = collect([
                $group['client_name'] ?? null,
                $order?->delivery_commune,
                $order?->delivery_quartier,
            ])->filter()->unique()->implode(' — ');

            $nodes[] = [
                'key' => 'delivery:' . $representative->id,
                'missionKey' => $missionKey,
                'type' => 'delivery',
                'label' => $destinationLabel ?: 'Livraison client',
                'address' => (string) ($group['address'] ?? $deliveryShipment?->delivery_address ?? ''),
                'commune' => $order?->delivery_commune ?: ($order?->delivery_zone ?? null),
                'lat' => $deliveryLat,
                'lng' => $deliveryLng,
                'itemIds' => collect($group['items'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'dependsOn' => $pickupKeys,
                'natural' => ($groupIndex * 100) + 90,
            ];
        }

        return $nodes;
    }

    /** @param Collection<int,array> $nodes */
    private function resolveStart(DeliveryDriver $driver, Collection $nodes): array
    {
        $location = $driver->currentLocation;
        $freshLocation = $location
            && $location->recorded_at
            && $location->recorded_at->gte(now()->subMinutes(30))
            && $this->validPoint($location->latitude, $location->longitude);

        if ($freshLocation) {
            return [
                'label' => 'Position actuelle — ' . $driver->name,
                'lat' => (float) $location->latitude,
                'lng' => (float) $location->longitude,
                'source' => 'driver_location',
            ];
        }

        if ($this->validPoint($driver->latitude, $driver->longitude)
            && $driver->last_seen_at?->gte(now()->subMinutes(30))) {
            return [
                'label' => 'Position actuelle — ' . $driver->name,
                'lat' => (float) $driver->latitude,
                'lng' => (float) $driver->longitude,
                'source' => 'delivery_drivers',
            ];
        }

        $firstPickup = $nodes->first(fn ($node) => $node['type'] === 'pickup' && $this->validPoint($node['lat'], $node['lng']));

        return [
            'label' => $firstPickup ? 'Départ — ' . $firstPickup['label'] : 'Départ à confirmer',
            'lat' => $firstPickup['lat'] ?? null,
            'lng' => $firstPickup['lng'] ?? null,
            'source' => $firstPickup ? 'first_pickup' : null,
        ];
    }

    /** @param Collection<int,array> $nodes */
    private function naturalOrder(Collection $nodes): Collection
    {
        return $nodes->sortBy('natural')->values();
    }

    /** @param Collection<int,array> $nodes */
    private function manualOrder(Collection $nodes, array $manualOrder): Collection
    {
        $manualOrder = array_values(array_unique(array_map('strval', $manualOrder)));
        $expected = $nodes->pluck('key')->sort()->values()->all();
        $actual = collect($manualOrder)->sort()->values()->all();

        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                'stop_order' => 'L’ordre des arrêts ne correspond plus aux missions sélectionnées. Recalculez le parcours.',
            ]);
        }

        $position = array_flip($manualOrder);
        $ordered = $nodes->sortBy(fn ($node) => $position[$node['key']] ?? PHP_INT_MAX)->values();
        $visited = [];

        foreach ($ordered as $node) {
            foreach ($node['dependsOn'] as $dependency) {
                if (! isset($visited[$dependency])) {
                    throw ValidationException::withMessages([
                        'stop_order' => 'Une livraison ne peut pas être placée avant son point de collecte.',
                    ]);
                }
            }
            $visited[$node['key']] = true;
        }

        return $ordered;
    }

    /** @param Collection<int,array> $nodes */
    private function optimizedOrder(Collection $nodes, array $start): Collection
    {
        $remaining = $nodes->keyBy('key');
        $visited = [];
        $ordered = collect();
        $current = $start;

        while ($remaining->isNotEmpty()) {
            $eligible = $remaining->filter(fn ($node) => collect($node['dependsOn'])->every(fn ($key) => isset($visited[$key])));
            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages([
                    'mission_items' => 'Impossible de construire un ordre cohérent entre collectes et livraisons.',
                ]);
            }

            $chosen = null;
            $bestScore = INF;
            foreach ($eligible as $candidate) {
                $route = $this->routeBetween($current, $candidate);
                $score = ($route['success'] ?? false)
                    ? (float) ($route['duration_minutes'] ?? $route['distance_km'] ?? PHP_INT_MAX)
                    : PHP_INT_MAX;
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $chosen = $candidate;
                }
            }

            $chosen ??= $eligible->sortBy('natural')->first();
            $ordered->push($chosen);
            $remaining->forget($chosen['key']);
            $visited[$chosen['key']] = true;
            if ($this->validPoint($chosen['lat'], $chosen['lng'])) {
                $current = $chosen;
            }
        }

        return $ordered->values();
    }

    /** @param Collection<int,array> $ordered */
    private function routeOrderedNodes(
        Collection $ordered,
        array $start,
        CarbonInterface $departureAt,
        array $vehicle,
        Collection $groups,
        bool $optimized,
    ): array {
        $distance = 0.0;
        $duration = 0;
        $complete = $this->validPoint($start['lat'], $start['lng']);
        $current = $start;
        $providers = [];
        $segments = [];
        $stops = [];
        $missionSchedule = [];

        foreach ($ordered->values() as $index => $node) {
            $route = $this->routeBetween($current, $node);
            $segmentDistance = null;
            $segmentDuration = null;
            $geometry = null;
            $provider = null;

            if ($route['success'] ?? false) {
                $segmentDistance = round((float) $route['distance_km'], 2);
                $segmentDuration = max(0, (int) $route['duration_minutes']);
                $geometry = $route['route_geometry'] ?? null;
                $provider = $route['provider'] ?? null;
                $distance += $segmentDistance;
                $duration += $segmentDuration;
                if ($provider) {
                    $providers[] = $provider;
                }
                if (is_array($geometry)) {
                    $segments[] = $geometry;
                }
            } elseif (! $this->samePoint($current, $node)) {
                $complete = false;
            }

            $arrival = $complete || $segmentDuration !== null
                ? Carbon::instance($departureAt instanceof Carbon ? $departureAt->copy() : Carbon::parse($departureAt))->addMinutes($duration)
                : null;

            $stop = $node + [
                'sequence' => $index + 1,
                'distanceFromPrevious' => $segmentDistance,
                'durationFromPrevious' => $segmentDuration,
                'estimatedArrivalAt' => $arrival?->toDateTimeString(),
                'estimatedArrivalLabel' => $arrival?->format('H:i') ?? 'À confirmer',
                'routeGeometry' => $geometry,
                'routingProvider' => $provider,
            ];
            $stops[] = $stop;

            $scheduleKey = $node['missionKey'];
            $missionSchedule[$scheduleKey] ??= ['pickup_at' => null, 'delivery_at' => null];
            if ($node['type'] === 'pickup' && $missionSchedule[$scheduleKey]['pickup_at'] === null) {
                $missionSchedule[$scheduleKey]['pickup_at'] = $arrival?->toDateTimeString();
            }
            if ($node['type'] === 'delivery') {
                $missionSchedule[$scheduleKey]['delivery_at'] = $arrival?->toDateTimeString();
            }

            if ($this->validPoint($node['lat'], $node['lng'])) {
                $current = $node;
            }
        }

        $weight = (float) $groups->sum('weight');
        $volume = (float) $groups->sum('volume');
        $products = (int) $groups->sum('article_count');
        $fillWeight = ($vehicle['capacity_kg'] ?? 0) > 0 ? ($weight / (float) $vehicle['capacity_kg']) * 100 : 0;
        $fillVolume = ($vehicle['volume_m3'] ?? 0) > 0 ? ($volume / (float) $vehicle['volume_m3']) * 100 : 0;

        return [
            'start' => $start,
            'stops' => $stops,
            'stopOrder' => collect($stops)->pluck('key')->all(),
            'routeSegments' => $segments,
            'distance' => $complete ? round($distance, 2) : null,
            'duration' => $complete ? $duration : null,
            'routingProvider' => collect($providers)->filter()->unique()->implode(' + ') ?: null,
            'complete' => $complete,
            'optimized' => $optimized,
            'weight' => round($weight, 2),
            'volume' => round($volume, 3),
            'products' => $products,
            'fillPercent' => (int) min(100, round(max($fillWeight, $fillVolume))),
            'missionSchedule' => $missionSchedule,
        ];
    }

    private function routeBetween(array $from, array $to): array
    {
        if ($this->samePoint($from, $to)) {
            return [
                'success' => true,
                'distance_km' => 0.0,
                'duration_minutes' => 0,
                'route_geometry' => null,
                'provider' => null,
            ];
        }

        if (! $this->validPoint($from['lat'] ?? null, $from['lng'] ?? null)
            || ! $this->validPoint($to['lat'] ?? null, $to['lng'] ?? null)) {
            return ['success' => false];
        }

        $key = implode(':', [
            round((float) $from['lat'], 5), round((float) $from['lng'], 5),
            round((float) $to['lat'], 5), round((float) $to['lng'], 5),
        ]);

        return $this->routeMemo[$key] ??= $this->routing->route(
            (float) $from['lat'],
            (float) $from['lng'],
            (float) $to['lat'],
            (float) $to['lng'],
        );
    }

    private function samePoint(array $from, array $to): bool
    {
        if (! $this->validPoint($from['lat'] ?? null, $from['lng'] ?? null)
            || ! $this->validPoint($to['lat'] ?? null, $to['lng'] ?? null)) {
            return false;
        }

        return abs((float) $from['lat'] - (float) $to['lat']) < 0.00001
            && abs((float) $from['lng'] - (float) $to['lng']) < 0.00001;
    }

    private function normalizeVehicleCode(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['-', ' ', 'é', 'è'], ['_', '_', 'e', 'e'], $value);

        return match (true) {
            str_contains($value, '10t') => 'camion_10t',
            str_contains($value, '3t') => 'camion_3t',
            str_contains($value, 'pickup'), str_contains($value, 'pick_up') => 'pickup',
            str_contains($value, 'tricycle') => 'tricycle',
            str_contains($value, 'moto') => 'moto',
            default => $value,
        };
    }

    private function validPoint($lat, $lng): bool
    {
        return is_numeric($lat) && is_numeric($lng)
            && (float) $lat >= -90 && (float) $lat <= 90
            && (float) $lng >= -180 && (float) $lng <= 180
            && (abs((float) $lat) > 0 || abs((float) $lng) > 0);
    }
}
