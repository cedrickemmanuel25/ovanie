<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class MissionRoutingService
{
    public function __construct(
        private readonly CoordinateSanitizer $coordinates,
        private readonly GeocodingService $geocoding
    ) {
    }

    /**
     * Calcule une vraie route routière selon la phase de mission :
     * livreur → points de collecte avant chargement, puis livreur → client après
     * chargement. Aucun segment droit artificiel n'est généré.
     */
    public function build(
        array $pickupStops,
        mixed $destinationLatitude,
        mixed $destinationLongitude,
        string $vehicleCode = 'pickup',
        ?array $driverPoint = null,
        array $options = []
    ): array {
        $includeDestination = (bool) ($options['include_destination'] ?? true);
        $requireDriver = (bool) ($options['require_driver'] ?? false);
        $routePhase = (string) ($options['phase'] ?? ($includeDestination ? 'delivery' : 'pickup'));
        $destinationAddress = trim((string) ($driverPoint['destination_address'] ?? ''));
        $destination = $includeDestination
            ? $this->resolvePoint($destinationLatitude, $destinationLongitude, $destinationAddress)
            : null;
        $validStops = [];
        $invalidStops = [];

        foreach ($pickupStops as $index => $stop) {
            $point = $this->resolvePoint(
                $stop['latitude'] ?? $stop['lat'] ?? null,
                $stop['longitude'] ?? $stop['lng'] ?? null,
                (string) ($stop['address'] ?? '')
            );

            $payload = [
                'id' => $stop['id'] ?? $stop['shop_id'] ?? $index,
                'type' => 'pickup',
                'name' => trim((string) ($stop['name'] ?? 'Point de collecte')) ?: 'Point de collecte',
                'address' => trim((string) ($stop['address'] ?? 'Adresse à confirmer')) ?: 'Adresse à confirmer',
                'weight' => (float) ($stop['weight'] ?? 0),
                'volume' => (float) ($stop['volume'] ?? 0),
            ];

            if (! $point) {
                $invalidStops[] = $payload;
                continue;
            }

            $payload['latitude'] = $point['latitude'];
            $payload['longitude'] = $point['longitude'];
            $payload['coordinates_corrected'] = (bool) ($point['swapped'] ?? false);
            $payload['coordinate_source'] = $point['source'] ?? 'stored';
            $validStops[] = $payload;
        }

        $validStops = $this->deduplicate($validStops);

        if ($invalidStops !== []) {
            return $this->unavailable(
                'Au moins un point de collecte ne possède pas de position GPS vérifiée. L’itinéraire complet ne peut pas être calculé.',
                $validStops,
                $invalidStops,
                $destination
            );
        }

        if ($includeDestination && ! $destination) {
            return $this->unavailable(
                'Les coordonnées réelles de la destination client sont absentes ou invalides.',
                $validStops,
                $invalidStops
            );
        }

        $driver = $driverPoint
            ? $this->resolvePoint(
                $driverPoint['latitude'] ?? $driverPoint['lat'] ?? null,
                $driverPoint['longitude'] ?? $driverPoint['lng'] ?? null,
                ''
            )
            : null;

        if ($requireDriver && ! $driver) {
            return $this->unavailable(
                'La route sera calculée dès que le livreur aura accepté la mission et transmis une position GPS récente.',
                $validStops,
                $invalidStops,
                $destination
            );
        }

        if ($validStops === [] && ! $includeDestination) {
            return $this->unavailable(
                'Aucun point de collecte vérifié ne reste à rejoindre.',
                [],
                $invalidStops,
                null
            );
        }

        // Après le chargement, il ne reste plus de collecte à effectuer : la route
        // correcte est alors position du livreur → destination.
        if ($validStops === [] && ! $driver) {
            return $this->unavailable(
                'La position du livreur ou les coordonnées des points de collecte sont encore indisponibles.',
                [],
                $invalidStops,
                $destination,
                $includeDestination && $destination ? [[
                    'id' => 'destination',
                    'type' => 'destination',
                    'name' => 'Destination client',
                    'address' => $destinationAddress ?: 'Adresse de livraison',
                    'latitude' => $destination['latitude'],
                    'longitude' => $destination['longitude'],
                ]] : []
            );
        }

        $startReference = $driver ?: $this->coordinates->defaultPoint();
        $orderedStops = $validStops === [] ? [] : $this->nearestNeighbourOrder($validStops, $startReference);

        $routePoints = [];
        if ($driver) {
            $routePoints[] = [
                'id' => 'driver',
                'type' => 'driver',
                'name' => trim((string) ($driverPoint['name'] ?? 'Livreur')) ?: 'Livreur',
                'address' => 'Position actuelle',
                'latitude' => $driver['latitude'],
                'longitude' => $driver['longitude'],
            ];
        }

        foreach ($orderedStops as $position => $stop) {
            $stop['route_order'] = $position + 1;
            $routePoints[] = $stop;
        }

        if ($includeDestination && $destination) {
            $routePoints[] = [
                'id' => 'destination',
                'type' => 'destination',
                'name' => 'Destination client',
                'address' => trim((string) ($driverPoint['destination_address'] ?? 'Adresse de livraison')) ?: 'Adresse de livraison',
                'latitude' => $destination['latitude'],
                'longitude' => $destination['longitude'],
            ];
        }

        if (count($routePoints) < 2) {
            return $this->unavailable(
                'Deux positions opérationnelles vérifiées sont nécessaires pour calculer un itinéraire.',
                $orderedStops,
                $invalidStops,
                $destination,
                $routePoints
            );
        }

        $cacheKey = 'ovanie:mission-route:' . sha1(json_encode([
            'points' => collect($routePoints)->map(fn (array $point) => [
                round((float) $point['latitude'], 5),
                round((float) $point['longitude'], 5),
            ])->all(),
            'vehicle' => $vehicleCode,
            'phase' => $routePhase,
            'include_destination' => $includeDestination,
            'traffic' => (bool) config('geo.tomtom.traffic', true),
        ]));

        $ttl = max(1, (int) config('geo.routing_cache_minutes', 10));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['success'] ?? false)) {
            return $cached;
        }

        $route = $this->tomTomRoute($routePoints, $vehicleCode);

        if (! ($route['success'] ?? false)) {
            $route = $this->mapboxRoute($routePoints);
        }

        if (! ($route['success'] ?? false)) {
            $route = $this->osrmRoute($routePoints);
        }

        if (! ($route['success'] ?? false)) {
            return $this->unavailable(
                'Le service de calcul routier est momentanément indisponible. Aucun faux tracé n’est affiché.',
                $orderedStops,
                $invalidStops,
                $destination,
                $routePoints
            );
        }

        $result = array_merge($route, [
            'points' => $routePoints,
            'pickup_stops' => $orderedStops,
            'destination' => $destination,
            'invalid_stops' => $invalidStops,
            'has_coordinate_warning' => $invalidStops !== [],
            'route_phase' => $routePhase,
        ]);

        Cache::put($cacheKey, $result, now()->addMinutes($ttl));

        return $result;
    }

    private function tomTomRoute(array $points, string $vehicleCode): array
    {
        $apiKey = trim((string) config('geo.tomtom.api_key'));
        if ($apiKey === '') {
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'Clé TomTom absente.'];
        }

        $baseUrl = rtrim((string) config('geo.tomtom.routing_base_url', 'https://api.tomtom.com/routing/1'), '/');
        $locations = collect($points)
            ->map(fn (array $point) => sprintf('%.7F,%.7F', $point['latitude'], $point['longitude']))
            ->implode(':');
        $url = $baseUrl . '/calculateRoute/' . $locations . '/json';

        $query = [
            'key' => $apiKey,
            'traffic' => config('geo.tomtom.traffic', true) ? 'true' : 'false',
            'travelMode' => $this->tomTomTravelMode($vehicleCode),
            'routeType' => 'fastest',
            'routeRepresentation' => 'polyline',
            'computeTravelTimeFor' => 'all',
            'departAt' => 'now',
            'language' => 'fr-FR',
        ];

        if (in_array($vehicleCode, ['truck_3t', 'truck_10t', 'camion_3t', 'camion_10t', 'special'], true)) {
            $query['vehicleCommercial'] = 'true';
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('geo.routing_timeout_seconds', 15))
                ->retry(1, 250)
                ->get($url, $query);
        } catch (\Throwable $exception) {
            report($exception);
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'TomTom indisponible.'];
        }

        $route = $response->ok() ? $response->json('routes.0') : null;
        if (! is_array($route)) {
            return [
                'success' => false,
                'provider' => 'tomtom',
                'message' => (string) ($response->json('detailedError.message') ?: 'Aucun itinéraire TomTom trouvé.'),
            ];
        }

        $geometry = $this->tomTomGeometry($route);
        if (count($geometry['coordinates']) < 2) {
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'Géométrie TomTom vide.'];
        }

        $summary = $route['summary'] ?? [];
        $durationSeconds = (float) ($summary['travelTimeInSeconds'] ?? 0);
        $trafficSeconds = (float) ($summary['trafficDelayInSeconds'] ?? 0);

        return [
            'success' => true,
            'provider' => 'tomtom',
            'geometry' => $geometry,
            'distance_km' => round(((float) ($summary['lengthInMeters'] ?? 0)) / 1000, 2),
            'duration_minutes' => max(1, (int) round($durationSeconds / 60)),
            'traffic_delay_minutes' => max(0, (int) round($trafficSeconds / 60)),
            'arrival_at' => now()->addSeconds((int) $durationSeconds)->toIso8601String(),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    private function mapboxRoute(array $points): array
    {
        $token = trim((string) (config('geo.mapbox.public_token') ?: config('geo.mapbox.geocoding_token')));
        if ($token === '') {
            return ['success' => false, 'provider' => 'mapbox', 'message' => 'Jeton Mapbox absent.'];
        }

        $coordinates = collect($points)
            ->map(fn (array $point) => sprintf('%.7F,%.7F', $point['longitude'], $point['latitude']))
            ->implode(';');
        $url = 'https://api.mapbox.com/directions/v5/mapbox/driving/' . $coordinates;

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('geo.routing_timeout_seconds', 15))
                ->retry(1, 250)
                ->get($url, [
                    'access_token' => $token,
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                    'alternatives' => 'false',
                    'language' => 'fr',
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return ['success' => false, 'provider' => 'mapbox', 'message' => 'Mapbox indisponible.'];
        }

        $route = $response->ok() ? $response->json('routes.0') : null;
        $geometry = is_array($route) ? ($route['geometry'] ?? null) : null;
        if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString') {
            return ['success' => false, 'provider' => 'mapbox', 'message' => 'Aucun itinéraire Mapbox trouvé.'];
        }

        $durationSeconds = (float) ($route['duration'] ?? 0);

        return [
            'success' => true,
            'provider' => 'mapbox',
            'geometry' => $geometry,
            'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
            'duration_minutes' => max(1, (int) round($durationSeconds / 60)),
            'traffic_delay_minutes' => null,
            'arrival_at' => now()->addSeconds((int) $durationSeconds)->toIso8601String(),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    private function osrmRoute(array $points): array
    {
        $baseUrl = trim((string) config('geo.osrm_base_url', 'https://router.project-osrm.org'));
        if ($baseUrl === '') {
            return ['success' => false, 'provider' => 'osrm', 'message' => 'OSRM non configuré.'];
        }

        $coordinates = collect($points)
            ->map(fn (array $point) => sprintf('%.7F,%.7F', $point['longitude'], $point['latitude']))
            ->implode(';');
        $url = rtrim($baseUrl, '/') . '/route/v1/driving/' . $coordinates;

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('geo.routing_timeout_seconds', 15))
                ->retry(1, 250)
                ->get($url, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                    'alternatives' => 'false',
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return ['success' => false, 'provider' => 'osrm', 'message' => 'OSRM indisponible.'];
        }

        $route = $response->ok() ? $response->json('routes.0') : null;
        $geometry = is_array($route) ? ($route['geometry'] ?? null) : null;

        if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString') {
            return ['success' => false, 'provider' => 'osrm', 'message' => 'Aucun itinéraire OSRM trouvé.'];
        }

        $durationSeconds = (float) ($route['duration'] ?? 0);

        return [
            'success' => true,
            'provider' => 'osrm',
            'geometry' => $geometry,
            'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
            'duration_minutes' => max(1, (int) round($durationSeconds / 60)),
            'traffic_delay_minutes' => null,
            'arrival_at' => now()->addSeconds((int) $durationSeconds)->toIso8601String(),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    private function tomTomGeometry(array $route): array
    {
        $coordinates = [];

        foreach (($route['legs'] ?? []) as $leg) {
            foreach (($leg['points'] ?? []) as $point) {
                if (! isset($point['latitude'], $point['longitude'])) {
                    continue;
                }

                $coordinate = [(float) $point['longitude'], (float) $point['latitude']];
                $last = $coordinates === [] ? null : $coordinates[count($coordinates) - 1];

                if ($last !== $coordinate) {
                    $coordinates[] = $coordinate;
                }
            }
        }

        return ['type' => 'LineString', 'coordinates' => $coordinates];
    }

    private function resolvePoint(mixed $latitude, mixed $longitude, string $address): ?array
    {
        $stored = $this->coordinates->normalize($latitude, $longitude);
        if ($stored) {
            $stored['source'] = 'stored';
            return $stored;
        }

        // Le géocodage automatique d'une adresse peut placer un marqueur au
        // centre d'une commune et produire un faux trajet. Il reste désactivé
        // pour les opérations, sauf activation explicite de test.
        if (! (bool) config('geo.allow_operational_geocoding', false)) {
            return null;
        }

        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
            $result = $this->geocoding->search(
                $address . ', Côte d’Ivoire',
                (string) config('geo.country_code', 'CI')
            )[0] ?? null;
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }

        if (! is_array($result)) {
            return null;
        }

        $geocoded = $this->coordinates->normalize(
            $result['latitude'] ?? null,
            $result['longitude'] ?? null
        );

        if ($geocoded) {
            $geocoded['source'] = 'geocoding';
        }

        return $geocoded;
    }

    private function nearestNeighbourOrder(array $stops, array $start): array
    {
        $remaining = array_values($stops);
        $ordered = [];
        $current = $start;

        while ($remaining !== []) {
            $bestIndex = 0;
            $bestDistance = INF;

            foreach ($remaining as $index => $stop) {
                $distance = $this->coordinates->distanceKm($current, $stop);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestIndex = $index;
                }
            }

            $next = $remaining[$bestIndex];
            $ordered[] = $next;
            $current = $next;
            array_splice($remaining, $bestIndex, 1);
        }

        $count = count($ordered);
        for ($first = 0; $first < $count - 1; $first++) {
            for ($last = $first + 1; $last < $count; $last++) {
                $before = $first === 0 ? $start : $ordered[$first - 1];
                $after = $last + 1 < $count ? $ordered[$last + 1] : null;

                $currentDistance = $this->coordinates->distanceKm($before, $ordered[$first]);
                $candidateDistance = $this->coordinates->distanceKm($before, $ordered[$last]);

                if ($after) {
                    $currentDistance += $this->coordinates->distanceKm($ordered[$last], $after);
                    $candidateDistance += $this->coordinates->distanceKm($ordered[$first], $after);
                }

                if ($candidateDistance < $currentDistance) {
                    $ordered = array_merge(
                        array_slice($ordered, 0, $first),
                        array_reverse(array_slice($ordered, $first, $last - $first + 1)),
                        array_slice($ordered, $last + 1)
                    );
                }
            }
        }

        return $ordered;
    }

    private function deduplicate(array $stops): array
    {
        return collect($stops)
            ->unique(fn (array $stop) => sprintf('%.5F:%.5F', $stop['latitude'], $stop['longitude']))
            ->values()
            ->all();
    }

    private function tomTomTravelMode(string $vehicleCode): string
    {
        return match ($vehicleCode) {
            'moto' => 'motorcycle',
            'truck_3t', 'truck_10t', 'camion_3t', 'camion_10t', 'special' => 'truck',
            default => 'car',
        };
    }

    private function unavailable(
        string $message,
        array $validStops = [],
        array $invalidStops = [],
        ?array $destination = null,
        array $points = []
    ): array {
        return [
            'success' => false,
            'provider' => null,
            'message' => $message,
            'geometry' => null,
            'distance_km' => null,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'arrival_at' => null,
            'calculated_at' => null,
            'points' => $points,
            'pickup_stops' => $validStops,
            'destination' => $destination,
            'invalid_stops' => $invalidStops,
            'has_coordinate_warning' => $invalidStops !== [],
        ];
    }
}
