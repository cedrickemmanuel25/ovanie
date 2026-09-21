<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProviderInterface;
use Illuminate\Support\Facades\Http;

class OsrmRoutingProvider implements RoutingProviderInterface
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $baseUrl = rtrim((string) config('geo.osrm_base_url', 'https://router.project-osrm.org'), '/');
        if ($baseUrl === '') {
            return ['success' => false, 'provider' => 'osrm', 'message' => 'OSRM non configuré.'];
        }

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('geo.routing_timeout_seconds', 15))
                ->retry(1, 250)
                ->get(
                    $baseUrl . "/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
                    [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                        'alternatives' => 'false',
                        'continue_straight' => 'true',
                    ]
                );
        } catch (\Throwable) {
            return ['success' => false, 'provider' => 'osrm', 'message' => 'Service itinéraire indisponible.'];
        }

        $route = $response->ok() ? ($response->json('routes.0') ?: null) : null;
        if (! is_array($route)) {
            return [
                'success' => false,
                'provider' => 'osrm',
                'message' => $response->json('message') ?: 'Aucun itinéraire trouvé.',
            ];
        }

        $geometry = $route['geometry'] ?? null;
        if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString') {
            return ['success' => false, 'provider' => 'osrm', 'message' => 'Géométrie OSRM invalide.'];
        }

        return [
            'success' => true,
            'provider' => 'osrm',
            'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
            'duration_minutes' => max(1, (int) round(((float) ($route['duration'] ?? 0)) / 60)),
            'traffic_delay_minutes' => null,
            'no_traffic_duration_minutes' => null,
            'route_geometry' => $geometry,
            'raw' => $route,
        ];
    }
}
