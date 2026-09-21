<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProviderInterface;
use Illuminate\Support\Facades\Http;

class MapboxRoutingProvider implements RoutingProviderInterface
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $token = trim((string) (config('geo.mapbox.public_token') ?: config('geo.mapbox.geocoding_token')));
        if ($token === '') {
            return ['success' => false, 'provider' => 'mapbox', 'message' => 'Jeton Mapbox manquant.'];
        }

        $coordinates = sprintf('%.7F,%.7F;%.7F,%.7F', $fromLng, $fromLat, $toLng, $toLat);
        $profile = config('geo.mapbox.directions_profile', 'driving-traffic');
        $url = 'https://api.mapbox.com/directions/v5/mapbox/' . $profile . '/' . $coordinates;

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
                    'continue_straight' => 'true',
                    'language' => 'fr',
                ]);
        } catch (\Throwable) {
            return ['success' => false, 'provider' => 'mapbox', 'message' => 'Service Mapbox indisponible.'];
        }

        $route = $response->ok() ? $response->json('routes.0') : null;
        $geometry = is_array($route) ? ($route['geometry'] ?? null) : null;

        if (! is_array($route) || ! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString') {
            return [
                'success' => false,
                'provider' => 'mapbox',
                'message' => $response->json('message') ?: 'Aucun itinéraire Mapbox trouvé.',
            ];
        }

        $durationSeconds = (float) ($route['duration'] ?? 0);
        $typicalDurationSeconds = (float) ($route['duration_typical'] ?? 0);
        $trafficDelayMinutes = $typicalDurationSeconds > 0 && $durationSeconds > $typicalDurationSeconds
            ? max(0, (int) round(($durationSeconds - $typicalDurationSeconds) / 60))
            : null;

        return [
            'success' => true,
            'provider' => 'mapbox',
            'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
            'duration_minutes' => max(1, (int) round($durationSeconds / 60)),
            'traffic_delay_minutes' => $trafficDelayMinutes,
            'no_traffic_duration_minutes' => $typicalDurationSeconds > 0
                ? max(1, (int) round($typicalDurationSeconds / 60))
                : null,
            'route_geometry' => $geometry,
            'raw' => $route,
        ];
    }
}
