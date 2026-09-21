<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\RoutingProviderInterface;
use Illuminate\Support\Facades\Http;

class TomTomRoutingProvider implements RoutingProviderInterface
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $key = config('geo.tomtom.api_key');
        if (! $key) {
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'Cle TomTom manquante.'];
        }

        $baseUrl = rtrim(config('geo.tomtom.routing_base_url', 'https://api.tomtom.com/routing/1'), '/');
        $path = "{$baseUrl}/calculateRoute/{$fromLat},{$fromLng}:{$toLat},{$toLng}/json";

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('geo.routing_timeout_seconds', 15))
                ->retry(1, 250)
                ->get($path, [
                'key' => $key,
                'traffic' => config('geo.tomtom.traffic', true) ? 'true' : 'false',
                'travelMode' => 'car',
                'routeType' => 'fastest',
                'computeTravelTimeFor' => 'all',
                'routeRepresentation' => 'polyline',
                'instructionsType' => 'text',
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'Service itineraire indisponible.'];
        }

        $route = $response->ok() ? ($response->json('routes.0') ?: null) : null;
        if (! $route) {
            return ['success' => false, 'provider' => 'tomtom', 'message' => 'Aucun itineraire trouve.'];
        }

        $summary = $route['summary'] ?? [];
        $trafficDelay = isset($summary['trafficDelayInSeconds'])
            ? (int) round(((float) $summary['trafficDelayInSeconds']) / 60)
            : null;
        $noTrafficDuration = isset($summary['noTrafficTravelTimeInSeconds'])
            ? (int) round(((float) $summary['noTrafficTravelTimeInSeconds']) / 60)
            : null;

        return [
            'success' => true,
            'provider' => 'tomtom',
            'distance_km' => round(((float) ($summary['lengthInMeters'] ?? 0)) / 1000, 2),
            'duration_minutes' => (int) round(((float) ($summary['travelTimeInSeconds'] ?? 0)) / 60),
            'traffic_delay_minutes' => $trafficDelay,
            'no_traffic_duration_minutes' => $noTrafficDuration,
            'route_geometry' => $this->lineString($route),
            'raw' => $route,
        ];
    }

    private function lineString(array $route): array
    {
        $points = collect($route['legs'] ?? [])
            ->flatMap(fn ($leg) => $leg['points'] ?? [])
            ->filter(fn ($point) => isset($point['latitude'], $point['longitude']))
            ->map(fn ($point) => [(float) $point['longitude'], (float) $point['latitude']])
            ->values()
            ->all();

        return [
            'type' => 'LineString',
            'coordinates' => $points,
        ];
    }
}
