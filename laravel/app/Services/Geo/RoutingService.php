<?php

namespace App\Services\Geo;

use App\Models\Shipment;
use App\Services\Geo\Contracts\RoutingProviderInterface;
use App\Services\Geo\Providers\MapboxRoutingProvider;
use App\Services\Geo\Providers\OsrmRoutingProvider;
use App\Services\Geo\Providers\TomTomRoutingProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RoutingService
{
    /**
     * Calcule un véritable itinéraire routier. Une géométrie composée uniquement
     * du point de départ et du point d'arrivée est volontairement refusée pour
     * les trajets urbains : elle produit une diagonale qui traverse les bâtiments.
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        if (! $this->valid($fromLat, $fromLng) || ! $this->valid($toLat, $toLng)) {
            return ['success' => false, 'message' => 'Coordonnées manquantes ou invalides.'];
        }

        $cacheSeconds = max(5, (int) config('geo.live_routing_cache_seconds', 20));
        $cacheKey = sprintf(
            'geo:live-route:%0.5f:%0.5f:%0.5f:%0.5f:%s',
            $fromLat,
            $fromLng,
            $toLat,
            $toLng,
            implode('-', $this->providerNames()),
        );

        return Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use ($fromLat, $fromLng, $toLat, $toLng) {
            $last = ['success' => false, 'message' => 'Aucun service d’itinéraire disponible.'];

            foreach ($this->providerNames() as $providerName) {
                try {
                    $result = $this->makeProvider($providerName)->route($fromLat, $fromLng, $toLat, $toLng);
                } catch (\Throwable $exception) {
                    $result = [
                        'success' => false,
                        'provider' => $providerName,
                        'message' => 'Le service d’itinéraire a rencontré une erreur.',
                    ];

                    Log::warning('Routing provider exception', [
                        'provider' => $providerName,
                        'message' => $exception->getMessage(),
                    ]);
                }

                if (($result['success'] ?? false)
                    && $this->routeIsUsable($result, $fromLat, $fromLng, $toLat, $toLng)) {
                    $result['geometry_points'] = count($result['route_geometry']['coordinates'] ?? []);

                    return $result;
                }

                if ($result['success'] ?? false) {
                    $result = array_merge($result, [
                        'success' => false,
                        'message' => 'La géométrie reçue ne suit pas suffisamment le réseau routier.',
                    ]);
                }

                $last = $result;
            }

            return $last;
        });
    }

    public function estimateShipment(Shipment $shipment): Shipment
    {
        $result = $this->route(
            (float) $shipment->pickup_latitude,
            (float) $shipment->pickup_longitude,
            (float) $shipment->delivery_latitude,
            (float) $shipment->delivery_longitude
        );

        if ($result['success'] ?? false) {
            $shipment->forceFill([
                'distance_km' => $result['distance_km'],
                'duration_minutes' => $result['duration_minutes'],
                'routing_provider' => $result['provider'] ?? null,
                'traffic_delay_minutes' => $result['traffic_delay_minutes'] ?? null,
                'route_geometry' => is_array($result['route_geometry'] ?? null)
                    ? json_encode($result['route_geometry'])
                    : ($result['route_geometry'] ?? null),
                'routed_at' => now(),
            ])->save();
        }

        return $shipment;
    }

    private function providerNames(): array
    {
        // Le provider configuré reste prioritaire. Mapbox et OSRM sont ensuite
        // utilisés comme secours afin de ne jamais remplacer une route par une
        // simple ligne droite.
        return array_values(array_unique(array_filter([
            $this->providerName(),
            'mapbox',
            'osrm',
            $this->fallbackProviderName(),
            'tomtom',
        ])));
    }

    private function routeIsUsable(
        array $result,
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
    ): bool {
        $geometry = $result['route_geometry'] ?? null;
        $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? null) : null;

        if (! is_array($geometry)
            || ($geometry['type'] ?? null) !== 'LineString'
            || ! is_array($coordinates)) {
            return false;
        }

        $coordinates = array_values(array_filter($coordinates, fn ($coordinate) =>
            is_array($coordinate)
            && count($coordinate) >= 2
            && is_numeric($coordinate[0])
            && is_numeric($coordinate[1])
        ));

        if (count($coordinates) < 2) {
            return false;
        }

        foreach ($coordinates as $coordinate) {
            if (! $this->valid((float) $coordinate[1], (float) $coordinate[0])) {
                return false;
            }
        }

        $straightKm = $this->distanceKm($fromLat, $fromLng, $toLat, $toLng);
        $geometryKm = $this->polylineDistanceKm($coordinates);
        $routeKm = is_numeric($result['distance_km'] ?? null)
            ? (float) $result['distance_km']
            : $geometryKm;

        // À partir de 150 m, deux points seulement correspondent presque toujours
        // à une diagonale et non à une géométrie issue d'un moteur routier.
        if ($straightKm >= 0.15 && count($coordinates) < 4) {
            return false;
        }

        // La distance routière ne peut pas être sensiblement inférieure à la
        // distance à vol d'oiseau. Ce contrôle détecte aussi les coordonnées
        // inversées et les réponses corrompues.
        if ($routeKm > 0 && $straightKm > 0 && $routeKm < ($straightKm * 0.90)) {
            return false;
        }

        // Une réponse exagérément longue signale généralement un mauvais snap.
        if ($straightKm > 0.1 && $routeKm > (($straightKm * 9) + 3)) {
            return false;
        }

        // La distance annoncée par le provider ne suffit pas : une géométrie
        // corrompue avec un point très éloigné ferait dézoomer toute la carte.
        if ($straightKm > 0.1 && $geometryKm > (($straightKm * 9) + 3)) {
            return false;
        }

        $first = $coordinates[0];
        $last = $coordinates[count($coordinates) - 1];
        $startGap = $this->distanceKm($fromLat, $fromLng, (float) $first[1], (float) $first[0]);
        $endGap = $this->distanceKm($toLat, $toLng, (float) $last[1], (float) $last[0]);

        return $startGap <= 2.0 && $endGap <= 2.0;
    }

    private function polylineDistanceKm(array $coordinates): float
    {
        $distance = 0.0;
        for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
            $previous = $coordinates[$index - 1];
            $current = $coordinates[$index];
            $distance += $this->distanceKm(
                (float) $previous[1],
                (float) $previous[0],
                (float) $current[1],
                (float) $current[0],
            );
        }

        return $distance;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $latitudeDelta = deg2rad($lat2 - $lat1);
        $longitudeDelta = deg2rad($lng2 - $lng1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    private function valid(float $lat, float $lng): bool
    {
        $bounds = config('geo.country_bounds', []);

        return $lat >= (float) ($bounds['min_lat'] ?? -90)
            && $lat <= (float) ($bounds['max_lat'] ?? 90)
            && $lng >= (float) ($bounds['min_lng'] ?? -180)
            && $lng <= (float) ($bounds['max_lng'] ?? 180)
            && (abs($lat) > 0 || abs($lng) > 0);
    }

    private function makeProvider(string $provider): RoutingProviderInterface
    {
        return match ($provider) {
            'tomtom' => app(TomTomRoutingProvider::class),
            'mapbox' => app(MapboxRoutingProvider::class),
            default => app(OsrmRoutingProvider::class),
        };
    }

    private function providerName(): string
    {
        return strtolower((string) config('geo.routing_provider', 'mapbox'));
    }

    private function fallbackProviderName(): string
    {
        return strtolower((string) config('geo.fallbacks.routing_provider', 'osrm'));
    }
}
