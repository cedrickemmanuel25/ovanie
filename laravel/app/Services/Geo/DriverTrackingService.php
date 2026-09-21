<?php

namespace App\Services\Geo;

use App\Events\DriverLocationUpdated;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverLocation;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverTrackingService
{
    public function __construct(
        private readonly RoutingService $routing,
        private readonly WazeLinkService $waze,
        private readonly DeliveryCoordinateService $coordinates,
    ) {
    }

    public function storeLocation(User|DeliveryDriver $driver, array $payload): DriverLocation
    {
        if (! $this->coordinates->valid($payload['latitude'] ?? null, $payload['longitude'] ?? null)) {
            throw ValidationException::withMessages([
                'location' => 'La position GPS reçue est invalide ou située hors de la zone de livraison autorisée.',
            ]);
        }

        $driverModel = $driver instanceof DeliveryDriver
            ? $driver
            : DeliveryDriver::firstOrCreate(
                ['phone' => $driver->phone ?: 'user-' . $driver->id],
                [
                    'name' => $driver->name ?: 'Livreur',
                    'zone' => 'Abidjan',
                    'vehicle' => 'Véhicule',
                    'status' => 'Disponible',
                    'is_active' => true,
                ]
            );

        $previousLocation = $this->previousLocationForPayload($driverModel->id, $payload);
        $accuracy = isset($payload['accuracy']) && is_numeric($payload['accuracy'])
            ? (float) $payload['accuracy']
            : null;
        if (! $previousLocation && $accuracy !== null
            && $accuracy > (float) config('delivery.gps_max_accuracy_m', 60)) {
            throw ValidationException::withMessages([
                'accuracy' => 'La précision GPS est insuffisante. Attendez quelques secondes dans une zone dégagée.',
            ]);
        }
        [$stableLatitude, $stableLongitude] = $this->stabilizedCoordinates(
            (float) $payload['latitude'],
            (float) $payload['longitude'],
            $accuracy,
            isset($payload['speed']) && is_numeric($payload['speed']) ? (float) $payload['speed'] : null,
            $previousLocation?->latitude,
            $previousLocation?->longitude,
        );

        $resolvedHeading = $this->resolvedHeading(
            $payload['heading'] ?? null,
            $previousLocation,
            $stableLatitude,
            $stableLongitude,
        );

        $attributes = [
            'driver_id' => $driverModel->id,
            'delivery_assignment_id' => $payload['delivery_assignment_id'] ?? null,
            'mission_number' => $payload['mission_number'] ?? null,
            'shipment_id' => $payload['shipment_id'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'latitude' => $stableLatitude,
            'longitude' => $stableLongitude,
            'accuracy' => $payload['accuracy'] ?? null,
            'speed' => $payload['speed'] ?? null,
            'heading' => $resolvedHeading,
            'battery_level' => $payload['battery_level'] ?? null,
            'recorded_at' => $payload['recorded_at'] ?? now(),
        ];

        foreach (['delivery_assignment_id', 'mission_number', 'order_id'] as $optionalColumn) {
            if (! Schema::hasColumn('driver_locations', $optionalColumn)) {
                unset($attributes[$optionalColumn]);
            }
        }

        $location = DriverLocation::create($attributes);

        $driverModel->forceFill([
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'last_seen_at' => now(),
            'is_online' => true,
        ])->save();

        $this->broadcastLocation($location);

        return $location;
    }

    public function trackingPayload(Shipment $shipment, bool $forLogistics = false): array
    {
        $shipment->loadMissing([
            'order.client',
            'orderItem.latestDeliveryAssignment.driver',
            'orderItem.product.shop',
            'latestDriverLocation',
        ]);

        $order = $shipment->order;
        $item = $shipment->orderItem;
        $assignment = $item?->latestDeliveryAssignment;
        $assignmentStatus = strtolower(trim((string) ($assignment?->status ?: '')));
        $gpsUnavailable = in_array($assignment?->gps_status, ['unavailable', 'denied', 'disabled'], true);
        $status = $item?->delivery_status ?: $shipment->status;
        $publicStatus = $this->publicStatus($status);

        $latest = $this->latestLocationForShipment($shipment, $assignment);
        $hasStoredGps = $latest && $this->coordinates->valid($latest->latitude, $latest->longitude);
        $gpsUnavailable = $gpsUnavailable && ! $hasStoredGps;
        $signal = $this->signalStatus($latest, $assignment, $gpsUnavailable);
        $locationAge = $latest?->recorded_at?->diffInSeconds(now());
        $routeLocationFresh = $hasStoredGps
            && $locationAge !== null
            && $locationAge <= (int) config('delivery.gps_lost_seconds', 300);
        $clientLocationFresh = $hasStoredGps
            && $locationAge !== null
            && $locationAge <= (int) config('delivery.client_gps_max_age_seconds', 180)
            && in_array($signal, ['active', 'weak'], true);

        $pickupLegActive = $assignment
            && in_array($assignmentStatus, ['accepted', 'collecting'], true);
        $deliveryLegActive = $assignment
            && in_array($assignmentStatus, ['picked_up', 'in_transit', 'arrived', 'delivered'], true);

        // Compatibilité avec les anciennes affectations dont le statut n'a pas
        // été synchronisé mais dont la ligne de commande est déjà collectée.
        if (! $deliveryLegActive && in_array($publicStatus, ['picked_up', 'in_transit', 'late', 'problem', 'delivered'], true)) {
            $deliveryLegActive = (bool) $assignment;
        }

        $trackingPhase = match (true) {
            ! $assignment => 'waiting_assignment',
            $publicStatus === 'delivered' || $assignmentStatus === 'delivered' => 'completed',
            $deliveryLegActive => 'to_customer',
            $pickupLegActive => 'to_pickup',
            default => 'waiting_start',
        };

        // Le client ne voit jamais le trajet du chauffeur vers une boutique.
        // Son suivi cartographique commence seulement après la collecte réelle.
        $clientMapVisible = ! $forLogistics && $deliveryLegActive && $clientLocationFresh;
        $internalMapVisible = $forLogistics
            && ($pickupLegActive || $deliveryLegActive)
            && $routeLocationFresh;
        $mapVisible = $forLogistics ? $internalMapVisible : $clientMapVisible;

        $destination = $this->coordinates->forShipment($shipment);
        $destinationLat = $destination['latitude'];
        $destinationLng = $destination['longitude'];
        $pickupLat = $this->coordinate($shipment->pickup_latitude)
            ?? $this->coordinate($item?->product?->shop?->latitude);
        $pickupLng = $this->coordinate($shipment->pickup_longitude)
            ?? $this->coordinate($item?->product?->shop?->longitude);
        $pickupAddress = $shipment->pickup_address
            ?: $item?->product?->shop?->address;

        $targetLat = $pickupLegActive && $forLogistics ? $pickupLat : $destinationLat;
        $targetLng = $pickupLegActive && $forLogistics ? $pickupLng : $destinationLng;
        $routePayload = $mapVisible && ! $gpsUnavailable
            ? $this->routePayload(
                $shipment,
                $forLogistics,
                $routeLocationFresh ? $latest : null,
                $targetLat,
                $targetLng,
                ! $pickupLegActive,
            )
            : $this->emptyRoute();

        $routeMinutes = is_numeric($routePayload['duration_minutes'] ?? null)
            ? max(1, (int) $routePayload['duration_minutes'])
            : null;
        $etaAt = $assignment?->manual_eta_at
            ?: ($mapVisible && $routeMinutes ? now()->addMinutes($routeMinutes) : null)
            ?: $assignment?->estimated_delivery_at
            ?: $shipment->estimated_delivery_at;
        $etaSource = $assignment?->manual_eta_at
            ? 'manual'
            : (($mapVisible && $routeMinutes) ? 'live_route' : ($etaAt ? 'planned' : null));
        $driver = $assignment?->driver;
        // Le nom du livreur peut être montré dès qu'une affectation réelle existe,
        // mais ses coordonnées et son trajet restent masqués jusqu'au départ réel.
        $publicDriverVisible = (bool) $assignment;
        $locationVisible = $forLogistics ? $hasStoredGps : $clientMapVisible;

        $payload = [
            'shipment_id' => $shipment->id,
            'tracking_key' => 'shipment-' . $shipment->id,
            'order_id' => $shipment->order_id,
            'order_number' => $order?->order_number,
            'provider' => $forLogistics ? 'OVANIE Logistics' : 'Livraison OVANIE',
            'delivery_status' => $publicStatus,
            'tracking_phase' => $trackingPhase,
            'map_visible' => $mapVisible,
            'eta' => $etaAt?->toIso8601String(),
            'eta_source' => $etaSource,
            'tracking_mode' => $mapVisible ? 'gps' : ($gpsUnavailable ? 'manual' : 'status'),
            'gps_available' => $mapVisible,
            'gps_status' => $mapVisible ? 'active' : ($assignment?->gps_status ?: 'waiting'),
            'signal_status' => $mapVisible ? $signal : ($gpsUnavailable ? 'unavailable' : ($assignment ? 'standby' : 'waiting')),
            'last_update' => ($latest?->recorded_at
                ?: $assignment?->last_manual_status_at
                ?: $assignment?->updated_at
                ?: $shipment->updated_at)?->toIso8601String(),
            'driver' => [
                'name' => $publicDriverVisible ? ($driver?->name ?: $item?->driver_name ?: 'Votre livreur') : null,
                'phone' => $publicDriverVisible ? ($driver?->phone ?: $item?->driver_phone) : null,
                'avatar_url' => $publicDriverVisible ? $this->avatarUrl($driver?->avatar) : null,
                'rating' => $publicDriverVisible && is_numeric($driver?->rating) ? round((float) $driver->rating, 1) : null,
                'vehicle' => $publicDriverVisible ? ($driver?->vehicle ?: $item?->vehicle_plate) : null,
                'location' => $locationVisible ? [
                    'latitude' => (float) $latest->latitude,
                    'longitude' => (float) $latest->longitude,
                    'accuracy' => $latest->accuracy,
                    'speed' => $latest->speed,
                    'heading' => $latest->heading,
                    'recorded_at' => $latest->recorded_at?->toIso8601String(),
                ] : null,
            ],
            'destination' => [
                'latitude' => $destinationLat,
                'longitude' => $destinationLng,
                'label' => $order?->delivery_commune ?: $order?->delivery_city,
            ],
            'route' => $routePayload,
            'delivery' => [
                'label' => $item?->product?->name ?: 'Articles de la commande',
                'item_count' => max(1, (int) ($item?->quantity ?: 1)),
                'reference_count' => $item ? 1 : 0,
                'items' => $item ? [[
                    'order_item_id' => (int) $item->id,
                    'product_id' => $item->product_id ? (int) $item->product_id : null,
                    'name' => $item->product?->name ?: 'Produit OVANIE',
                    'quantity' => max(1, (int) ($item->quantity ?: 1)),
                    'image_url' => $item->product?->main_image_url,
                ]] : [],
            ],
            'vehicle' => $this->vehiclePayload($shipment, $assignment, $forLogistics),
            'navigation' => [
                'waze_url' => $forLogistics && $this->coordinates->valid($targetLat, $targetLng)
                    ? $this->waze->navigationUrl((float) $targetLat, (float) $targetLng)
                    : null,
            ],
            'history' => $forLogistics && ! $gpsUnavailable
                ? $this->locationHistory($shipment, $assignment)
                : [],
        ];

        if ($forLogistics) {
            $payload['mission_number'] = $assignment?->resolved_mission_number;
            $payload['pickup'] = [
                'latitude' => $pickupLat,
                'longitude' => $pickupLng,
                'address' => $pickupAddress,
                'visible' => $pickupLegActive,
            ];
            $payload['route_target'] = $pickupLegActive ? 'pickup' : 'destination';
            $payload['driver']['name'] = $assignment?->driver?->name ?? $item?->driver_name;
            $payload['driver']['vehicle'] = $assignment?->driver?->vehicle ?? $item?->vehicle_plate;
            $payload['internal_carrier'] = [
                'type' => $shipment->internal_carrier_type,
                'name' => $shipment->internal_carrier_name,
            ];
        }

        return $payload;
    }

    private function stabilizedCoordinates(
        float $latitude,
        float $longitude,
        ?float $accuracy,
        ?float $speed,
        mixed $previousLatitude,
        mixed $previousLongitude,
    ): array {
        if (! $this->coordinates->valid($previousLatitude, $previousLongitude)) {
            return [$latitude, $longitude];
        }

        if ($accuracy !== null && $accuracy > (float) config('delivery.gps_max_accuracy_m', 100)) {
            return [(float) $previousLatitude, (float) $previousLongitude];
        }

        $distance = $this->distanceMeters(
            (float) $previousLatitude,
            (float) $previousLongitude,
            $latitude,
            $longitude,
        );
        $radius = min(
            85.0,
            max(35.0, (float) ($accuracy ?? 15.0) * 2.25)
        );

        // Application Livreur : lorsque le téléphone indique une vitesse de
        // véhicule quasi nulle, la position reste verrouillée sur la dernière
        // coordonnée validée. Une dérive GPS de 20, 50 ou 100 m ne peut donc
        // plus déplacer le véhicule côté Logistique. Le mobile n'envoie une
        // vitesse > 1,2 m/s qu'après confirmation de plusieurs lectures GPS.
        if ($speed !== null && $speed <= 1.2) {
            return [(float) $previousLatitude, (float) $previousLongitude];
        }

        // Compatibilité avec les sources qui ne remontent pas la vitesse
        // (certains navigateurs) : on filtre tout de même le bruit courant sans
        // bloquer un déplacement réel pendant toute la session.
        if ($speed === null && $distance <= min(25.0, max(8.0, $radius * 0.35))) {
            return [(float) $previousLatitude, (float) $previousLongitude];
        }

        return [$latitude, $longitude];
    }

    private function previousLocationForPayload(int $driverId, array $payload): ?DriverLocation
    {
        $query = DriverLocation::query()->where('driver_id', $driverId);

        if (! empty($payload['shipment_id'])) {
            $query->where('shipment_id', $payload['shipment_id']);
        } elseif (! empty($payload['delivery_assignment_id']) && Schema::hasColumn('driver_locations', 'delivery_assignment_id')) {
            $query->where('delivery_assignment_id', $payload['delivery_assignment_id']);
        } elseif (filled($payload['mission_number'] ?? null) && Schema::hasColumn('driver_locations', 'mission_number')) {
            $query->where('mission_number', $payload['mission_number']);
        } elseif (! empty($payload['order_id']) && Schema::hasColumn('driver_locations', 'order_id')) {
            $query->where('order_id', $payload['order_id']);
        }

        return $query->latest('recorded_at')->latest('id')->first();
    }

    private function resolvedHeading(
        mixed $reportedHeading,
        ?DriverLocation $previousLocation,
        float $latitude,
        float $longitude,
    ): ?float {
        if (is_numeric($reportedHeading)) {
            $heading = fmod(((float) $reportedHeading) + 360.0, 360.0);
            return round($heading, 1);
        }

        if ($previousLocation
            && $this->coordinates->valid($previousLocation->latitude, $previousLocation->longitude)) {
            $distance = $this->distanceMeters(
                (float) $previousLocation->latitude,
                (float) $previousLocation->longitude,
                $latitude,
                $longitude,
            );

            if ($distance >= 4.0) {
                $lat1 = deg2rad((float) $previousLocation->latitude);
                $lat2 = deg2rad($latitude);
                $deltaLng = deg2rad($longitude - (float) $previousLocation->longitude);
                $y = sin($deltaLng) * cos($lat2);
                $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);
                return round(fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0), 1);
            }

            return is_numeric($previousLocation->heading) ? (float) $previousLocation->heading : null;
        }

        return null;
    }

    private function avatarUrl(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar);
        if ($avatar === '') {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        if (Str::startsWith($avatar, '/')) {
            return url($avatar);
        }

        return asset('storage/' . ltrim($avatar, '/'));
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad($fromLat);
        $lat2 = deg2rad($toLat);
        $deltaLat = deg2rad($toLat - $fromLat);
        $deltaLng = deg2rad($toLng - $fromLng);
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    public function publicStatus(?string $status): string
    {
        return match ($status) {
            'preparing' => 'preparing',
            'ready_for_pickup' => 'ready_for_pickup',
            'assigned' => 'assigned',
            'picked_up' => 'picked_up',
            'in_transit', 'in_delivery', 'arrived' => 'in_transit',
            'delivered', 'completed' => 'delivered',
            'late' => 'late',
            'delivery_failed', 'failed', 'problem', 'incident' => 'problem',
            'cancelled' => 'cancelled',
            'returned' => 'returned',
            'not_required' => 'not_required',
            default => 'pending',
        };
    }

    public function broadcastLocation(DriverLocation $location): void
    {
        broadcast(new DriverLocationUpdated($location))->toOthers();
    }

    private function latestLocationForShipment(Shipment $shipment, ?DeliveryAssignment $assignment): ?DriverLocation
    {
        if (! $assignment) {
            return null;
        }

        $missionNumber = $assignment->resolved_mission_number;
        $hasMissionColumn = Schema::hasColumn('driver_locations', 'mission_number');
        $hasAssignmentColumn = Schema::hasColumn('driver_locations', 'delivery_assignment_id');
        $latestRelation = $shipment->latestDriverLocation;

        if ($latestRelation
            && (int) $latestRelation->driver_id === (int) $assignment->driver_id
            && (
                ($hasAssignmentColumn && (int) $latestRelation->delivery_assignment_id === (int) $assignment->id)
                || ($hasMissionColumn && filled($missionNumber) && (string) $latestRelation->mission_number === (string) $missionNumber)
            )) {
            return $latestRelation;
        }

        $exact = DriverLocation::query()
            ->where('driver_id', $assignment->driver_id)
            ->where(function (Builder $query) use ($shipment, $assignment, $missionNumber, $hasMissionColumn, $hasAssignmentColumn) {
                $hasExactIdentifier = false;

                if ($hasAssignmentColumn) {
                    $query->where('delivery_assignment_id', $assignment->id);
                    $hasExactIdentifier = true;
                }
                if ($hasMissionColumn && filled($missionNumber)) {
                    $method = $hasExactIdentifier ? 'orWhere' : 'where';
                    $query->{$method}('mission_number', $missionNumber);
                    $hasExactIdentifier = true;
                }
                if (! $hasExactIdentifier) {
                    $query->where('shipment_id', $shipment->id);
                }
            })
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        if ($exact) {
            return $exact;
        }

        // Compatibilité contrôlée avec les anciennes positions : uniquement le
        // même shipment et le même livreur, jamais toute la commande.
        return DriverLocation::query()
            ->where('driver_id', $assignment->driver_id)
            ->where('shipment_id', $shipment->id)
            ->latest('recorded_at')
            ->latest('id')
            ->first();
    }

    private function locationHistory(Shipment $shipment, ?DeliveryAssignment $assignment)
    {
        $query = DriverLocation::query();

        if ($assignment) {
            $hasMissionColumn = Schema::hasColumn('driver_locations', 'mission_number');
            $hasAssignmentColumn = Schema::hasColumn('driver_locations', 'delivery_assignment_id');

            $query->where('driver_id', $assignment->driver_id)
                ->where(function (Builder $builder) use ($shipment, $assignment, $hasMissionColumn, $hasAssignmentColumn) {
                    $hasExactIdentifier = false;

                    if ($hasAssignmentColumn) {
                        $builder->where('delivery_assignment_id', $assignment->id);
                        $hasExactIdentifier = true;
                    }
                    if ($hasMissionColumn && filled($assignment->resolved_mission_number)) {
                        $method = $hasExactIdentifier ? 'orWhere' : 'where';
                        $builder->{$method}('mission_number', $assignment->resolved_mission_number);
                        $hasExactIdentifier = true;
                    }
                    if (! $hasExactIdentifier) {
                        $builder->where('shipment_id', $shipment->id);
                    }
                });
        } else {
            $query->where('shipment_id', $shipment->id);
        }

        return $query->latest('recorded_at')
            ->take(20)
            ->get(['latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at']);
    }

    private function signalStatus(?DriverLocation $location, ?DeliveryAssignment $assignment, bool $gpsUnavailable): string
    {
        if ($gpsUnavailable) {
            return 'unavailable';
        }

        if (! $location?->recorded_at) {
            return in_array($assignment?->status, ['assigned', 'accepted', 'collecting', 'picked_up'], true)
                ? 'standby'
                : 'waiting';
        }

        $age = $location->recorded_at->diffInSeconds(now());

        if ($age <= (int) config('delivery.gps_active_seconds', 90)) {
            return 'active';
        }
        if ($age <= (int) config('delivery.gps_weak_seconds', 180)) {
            return 'weak';
        }

        return 'lost';
    }

    private function routePayload(
        Shipment $shipment,
        bool $forLogistics,
        ?DriverLocation $latest,
        ?float $destinationLat,
        ?float $destinationLng,
        bool $allowStoredFallback = true,
    ): array {
        // Pour le client comme pour la supervision, le trajet restant part de la
        // dernière position réelle. Aucun tracé calculé dans le navigateur n'est utilisé.
        if ($latest
            && $this->coordinates->valid($latest->latitude, $latest->longitude)
            && $this->coordinates->valid($destinationLat, $destinationLng)) {
            $route = $this->routing->route(
                (float) $latest->latitude,
                (float) $latest->longitude,
                (float) $destinationLat,
                (float) $destinationLng,
            );

            if ($route['success'] ?? false) {
                return [
                    'distance_km' => $route['distance_km'] ?? null,
                    'duration_minutes' => $route['duration_minutes'] ?? null,
                    'traffic_delay_minutes' => $route['traffic_delay_minutes'] ?? null,
                    'geometry' => $this->geometry($route['route_geometry'] ?? null),
                    'provider' => $route['provider'] ?? config('geo.routing_provider'),
                ];
            }
        }

        if ($forLogistics && $allowStoredFallback && $shipment->distance_km) {
            return [
                'distance_km' => $shipment->distance_km,
                'duration_minutes' => $shipment->duration_minutes,
                'traffic_delay_minutes' => $shipment->traffic_delay_minutes,
                'geometry' => $this->geometry($shipment->route_geometry),
                'provider' => $shipment->routing_provider,
            ];
        }

        return $this->emptyRoute();
    }

    private function vehiclePayload(
        Shipment $shipment,
        ?DeliveryAssignment $assignment = null,
        bool $forLogistics = false
    ): array {
        $item = $shipment->orderItem;
        $hasActualAssignment = (bool) $assignment;
        $driverVehicle = trim((string) ($assignment?->driver?->vehicle ?: ''));
        $plate = trim((string) ($item?->vehicle_plate ?: data_get($assignment?->meta, 'vehicle_plate', '')));
        $label = trim((string) ($shipment->vehicle_label ?: $item?->logistics_vehicle_label ?: $driverVehicle));

        // Le poids peut déterminer un véhicule théorique avant affectation. Cette
        // information reste interne : le client ne voit un véhicule qu'après une
        // affectation réelle afin d'éviter tout camion/moto fictif dans l'app.
        if (! $forLogistics && ! $hasActualAssignment) {
            return [
                'assigned' => false,
                'vehicle_code' => null,
                'vehicle_label' => null,
                'vehicle_plate' => null,
                'driver_vehicle' => null,
                'total_weight_kg' => null,
                'total_volume_m3' => null,
            ];
        }

        return [
            'assigned' => $hasActualAssignment,
            'vehicle_code' => $shipment->vehicle_code ?: $item?->logistics_vehicle_code,
            'vehicle_label' => $label !== '' ? $label : ($driverVehicle !== '' ? $driverVehicle : null),
            'vehicle_plate' => $plate !== '' ? $plate : null,
            'driver_vehicle' => $driverVehicle !== '' ? $driverVehicle : null,
            'total_weight_kg' => $shipment->total_weight_kg ?: $item?->logistics_weight_kg,
            'total_volume_m3' => $shipment->total_volume_m3 ?: $item?->logistics_volume_m3,
        ];
    }

    private function emptyRoute(): array
    {
        return [
            'distance_km' => null,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'geometry' => null,
            'provider' => null,
        ];
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

    private function coordinate($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
