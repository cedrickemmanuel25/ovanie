<?php

namespace App\Services\Geo;

final class CoordinateSanitizer
{
    /**
     * Retourne une coordonnée fiable dans le pays configuré.
     * Les latitude/longitude inversées sont corrigées automatiquement.
     */
    public function normalize(mixed $latitude, mixed $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if (! $this->isWorldCoordinate($lat, $lng) || $this->isZeroPoint($lat, $lng)) {
            return null;
        }

        if ($this->isInsideCountry($lat, $lng)) {
            return [
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'swapped' => false,
            ];
        }

        if ($this->isWorldCoordinate($lng, $lat) && $this->isInsideCountry($lng, $lat)) {
            return [
                'latitude' => round($lng, 7),
                'longitude' => round($lat, 7),
                'swapped' => true,
            ];
        }

        return null;
    }

    public function distanceKm(array $from, array $to): float
    {
        $earthRadius = 6371.0088;
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLat = deg2rad((float) $to['latitude'] - (float) $from['latitude']);
        $deltaLng = deg2rad((float) $to['longitude'] - (float) $from['longitude']);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function defaultPoint(): array
    {
        return [
            'latitude' => (float) config('geo.default_lat', 5.359952),
            'longitude' => (float) config('geo.default_lng', -4.008256),
        ];
    }

    private function isWorldCoordinate(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    private function isZeroPoint(float $lat, float $lng): bool
    {
        return abs($lat) < 0.000001 && abs($lng) < 0.000001;
    }

    private function isInsideCountry(float $lat, float $lng): bool
    {
        $bounds = config('geo.country_bounds', []);

        $minLat = (float) ($bounds['min_lat'] ?? 4.0);
        $maxLat = (float) ($bounds['max_lat'] ?? 11.2);
        $minLng = (float) ($bounds['min_lng'] ?? -9.0);
        $maxLng = (float) ($bounds['max_lng'] ?? -2.0);

        return $lat >= $minLat && $lat <= $maxLat
            && $lng >= $minLng && $lng <= $maxLng;
    }
}
