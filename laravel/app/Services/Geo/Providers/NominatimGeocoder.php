<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\GeocoderInterface;
use Illuminate\Support\Facades\Http;

class NominatimGeocoder implements GeocoderInterface
{
    public function search(string $query, ?string $countryCode = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $response = $this->client()->get($this->baseUrl().'/search', [
                'format' => 'jsonv2',
                'q' => $query,
                'countrycodes' => strtolower((string) ($countryCode ?: config('geo.country_code', 'CI'))),
                'limit' => 5,
                'addressdetails' => 1,
                'namedetails' => 1,
                'accept-language' => 'fr',
            ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        return collect($response->json() ?: [])
            ->filter(fn ($row) => is_array($row)
                && isset($row['lat'], $row['lon'])
                && is_numeric($row['lat'])
                && is_numeric($row['lon']))
            ->map(fn ($row) => $this->mapRow($row))
            ->values()
            ->all();
    }

    public function reverse(float $latitude, float $longitude): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/reverse', [
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'zoom' => 18,
                'addressdetails' => 1,
                'namedetails' => 1,
                'extratags' => 1,
                'accept-language' => 'fr',
            ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $row = $response->json() ?: [];
        if (! is_array($row) || empty($row)) {
            return [];
        }

        $mapped = $this->mapRow($row);
        $mapped['latitude'] = isset($row['lat']) && is_numeric($row['lat'])
            ? (float) $row['lat']
            : $latitude;
        $mapped['longitude'] = isset($row['lon']) && is_numeric($row['lon'])
            ? (float) $row['lon']
            : $longitude;

        return $mapped;
    }

    private function mapRow(array $row): array
    {
        return [
            'latitude' => isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : 0.0,
            'longitude' => isset($row['lon']) && is_numeric($row['lon']) ? (float) $row['lon'] : 0.0,
            'display_name' => $row['display_name'] ?? null,
            'address' => is_array($row['address'] ?? null) ? $row['address'] : [],
            'feature_type' => $row['type'] ?? $row['category'] ?? null,
            'feature_name' => $row['name'] ?? null,
            'namedetails' => is_array($row['namedetails'] ?? null) ? $row['namedetails'] : [],
            'extratags' => is_array($row['extratags'] ?? null) ? $row['extratags'] : [],
            'raw' => $row,
            'source' => 'nominatim',
        ];
    }

    private function client()
    {
        return Http::connectTimeout(2)
            ->timeout(10)
            ->withoutVerifying()
            ->acceptJson()
            ->withHeaders([
                // Nominatim exige une identification applicative explicite.
                'User-Agent' => (string) config(
                    'geo.nominatim_user_agent',
                    'OVANIE/1.0 (https://ovanie.com; contact@ovanie.com)'
                ),
                'Accept-Language' => 'fr',
            ]);
    }

    private function baseUrl(): string
    {
        // IMPORTANT V53 : dans plusieurs installations OVANIE, la clé
        // geo.nominatim_base_url n'existait pas. rtrim(null) produisait alors une
        // URL relative et le fallback échouait silencieusement. On possède
        // désormais toujours une vraie URL par défaut.
        $configured = trim((string) config('geo.nominatim_base_url', ''));
        if ($configured !== '') {
            $parts = parse_url($configured);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = trim((string) ($parts['host'] ?? ''));
            if (in_array($scheme, ['http', 'https'], true) && $host !== '') {
                return rtrim($configured, '/');
            }
        }

        return 'https://nominatim.openstreetmap.org';
    }
}
