<?php

namespace App\Services\Geo;

use App\Models\GeocodeCache;
use App\Services\Geo\Contracts\GeocoderInterface;
use App\Services\Geo\Providers\MapboxGeocoder;
use App\Services\Geo\Providers\NominatimGeocoder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeocodingService
{
    public function __construct(
        private readonly AbidjanLocationResolver $locationResolver
    ) {}

    public function search(string $query, ?string $countryCode = 'ci'): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = $this->cacheKey($query, $countryCode);
        if (Schema::hasTable('geocode_cache')) {
            $cached = GeocodeCache::where('query', $cacheKey)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->first();

            if ($cached) {
                $raw = (array) ($cached->raw_response ?: []);

                return [$this->locationResolver->enrich([
                    'latitude' => $cached->latitude,
                    'longitude' => $cached->longitude,
                    'display_name' => $cached->display_name,
                    'raw' => $raw['raw'] ?? $raw,
                    'address' => $raw['address'] ?? [],
                    'source' => $raw['source'] ?? 'cache',
                ])];
            }
        }

        $results = [];
        foreach ($this->providerChain() as $provider) {
            try {
                $results = $this->makeGeocoder($provider)->search($query, $countryCode);
            } catch (\Throwable) {
                $results = [];
            }

            if ($results) {
                break;
            }
        }

        $results = collect($results)
            ->map(fn (array $result) => $this->locationResolver->enrich($result))
            ->values()
            ->all();

        if ($results && Schema::hasTable('geocode_cache')) {
            GeocodeCache::updateOrCreate(
                [
                    'query' => $cacheKey,
                    'provider' => $results[0]['source'] ?? 'geocoder',
                ],
                [
                    'country_code' => $countryCode,
                    'latitude' => $results[0]['latitude'],
                    'longitude' => $results[0]['longitude'],
                    'display_name' => $results[0]['display_name'],
                    'raw_response' => $results[0],
                    'expires_at' => now()->addDays(7),
                ]
            );
        }

        return $results;
    }

    public function reverse(float $lat, float $lng, bool $useCache = true): array
    {
        if (! $this->validCoordinates($lat, $lng)) {
            return [];
        }

        $cacheKey = $this->reverseCacheKey($lat, $lng);
        if ($useCache && Schema::hasTable('geocode_cache')) {
            $cached = GeocodeCache::where('query', $cacheKey)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->first();

            if ($cached) {
                $payload = (array) ($cached->raw_response ?: []);
                $payload['latitude'] = $lat;
                $payload['longitude'] = $lng;
                $payload['display_name'] = $payload['display_name'] ?? $cached->display_name;
                $payload['source'] = $payload['source'] ?? 'cache';
                $payload['input_coordinates'] = [
                    'latitude' => $lat,
                    'longitude' => $lng,
                ];

                return $this->locationResolver->enrich($payload);
            }
        }

        $resolved = [];
        $sources = [];

        // V53 : même si geo.fallbacks.* est absent ou mal configuré, Nominatim
        // reste un vrai dernier recours. Cela corrige le cas observé où Laravel
        // renvoyait seulement "Position actuelle détectée" sur Web et émulateur.
        foreach ($this->providerChain() as $provider) {
            try {
                $raw = $this->makeGeocoder($provider)->reverse($lat, $lng);
            } catch (\Throwable $error) {
                Log::warning('OVANIE reverse geocoding provider failed', [
                    'provider' => $provider,
                    'error' => $error->getMessage(),
                ]);
                $raw = [];
            }

            if (! $raw) {
                continue;
            }

            $candidate = $this->locationResolver->enrich($raw);
            $sources[] = $candidate['source'] ?? $provider;

            if (! $resolved) {
                $resolved = $candidate;
            } else {
                $resolved = $this->locationResolver->mergeAndEnrich($resolved, $candidate);
            }

            if ($this->isDetailedEnough($resolved)) {
                break;
            }
        }

        if (! $resolved) {
            return $this->coordinatesOnly($lat, $lng);
        }

        // Le reverse-geocoder NOMME le point, mais les coordonnées originales du
        // navigateur/téléphone restent la source de vérité pour la livraison.
        $resolved['geocoder_latitude'] = $resolved['latitude'] ?? null;
        $resolved['geocoder_longitude'] = $resolved['longitude'] ?? null;
        $resolved['latitude'] = $lat;
        $resolved['longitude'] = $lng;
        $resolved['input_coordinates'] = [
            'latitude' => $lat,
            'longitude' => $lng,
        ];
        $resolved['providers_used'] = array_values(array_unique(array_filter($sources)));
        $resolved['address_quality'] = $this->addressQuality($resolved);

        if (Schema::hasTable('geocode_cache') && $this->addressQuality($resolved) !== 'coordinates_only') {
            GeocodeCache::updateOrCreate(
                [
                    'query' => $cacheKey,
                    'provider' => $resolved['source'] ?? 'geocoder',
                ],
                [
                    'country_code' => config('geo.country_code', 'CI'),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'display_name' => $resolved['display_name'] ?? null,
                    'raw_response' => $resolved,
                    'expires_at' => now()->addHours(6),
                ]
            );
        }

        return $resolved;
    }

    public function normalizeAddress(array $payload): array
    {
        return [
            'address' => trim((string) ($payload['address'] ?? '')),
            'commune' => trim((string) ($payload['commune'] ?? '')),
            'quarter' => trim((string) ($payload['quarter'] ?? $payload['district'] ?? $payload['landmark'] ?? '')),
            'city' => trim((string) ($payload['city'] ?? 'Abidjan')),
            'country' => trim((string) ($payload['country'] ?? "Cote d'Ivoire")),
        ];
    }

    public function searchBestMatch(?string $address, ?string $commune, ?string $quarter, ?string $country = "Cote d'Ivoire"): ?array
    {
        $parts = collect([$address, $quarter, $commune, 'Abidjan', $country])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => Str::lower($value))
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        return $this->search($parts->implode(', '), config('geo.country_code', 'CI'))[0] ?? null;
    }

    private function providerChain(): array
    {
        return array_values(array_unique(array_filter([
            $this->providerName(),
            $this->fallbackProviderName(),
            // Nominatim est conservé en secours final même si la configuration
            // fallback n'existe pas dans un ancien projet OVANIE.
            'nominatim',
        ])));
    }

    private function makeGeocoder(string $provider): GeocoderInterface
    {
        return match (strtolower(trim($provider))) {
            'mapbox' => app(MapboxGeocoder::class),
            default => app(NominatimGeocoder::class),
        };
    }

    private function providerName(): string
    {
        $value = strtolower(trim((string) config('geo.geocoding_provider', 'mapbox')));
        return $value !== '' ? $value : 'mapbox';
    }

    private function fallbackProviderName(): string
    {
        $value = strtolower(trim((string) config('geo.fallbacks.geocoding_provider', 'nominatim')));
        return $value !== '' ? $value : 'nominatim';
    }

    private function isDetailedEnough(array $geo): bool
    {
        $address = (array) ($geo['address'] ?? []);
        $resolved = (array) ($geo['resolved_location'] ?? []);

        foreach (['house_number', 'road', 'neighbourhood', 'neighborhood', 'quarter', 'residential', 'suburb'] as $key) {
            if (filled($address[$key] ?? null)) {
                return true;
            }
        }

        if (filled($resolved['quartier'] ?? null) || filled($resolved['repere'] ?? null)) {
            return true;
        }

        $commune = trim((string) ($resolved['commune'] ?? ''));
        $display = trim((string) ($geo['display_name'] ?? ''));

        return $commune !== ''
            && Str::lower(Str::ascii($commune)) !== 'abidjan'
            && $display !== ''
            && substr_count($display, ',') >= 2;
    }

    private function addressQuality(array $geo): string
    {
        if ($this->isDetailedEnough($geo)) {
            return 'detailed';
        }

        $resolved = (array) ($geo['resolved_location'] ?? []);
        if (filled($resolved['commune'] ?? null) || filled($resolved['city'] ?? null)) {
            return 'administrative';
        }

        return 'coordinates_only';
    }

    private function coordinatesOnly(float $lat, float $lng): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'display_name' => null,
            'address' => [],
            'resolved_location' => [
                'zone' => 'interieur',
                'is_abidjan' => false,
                'commune' => null,
                'quartier' => null,
                'repere' => null,
                'city' => null,
                'confidence' => 'coordinates_only',
            ],
            'source' => 'coordinates_only',
            'providers_used' => [],
            'address_quality' => 'coordinates_only',
            'input_coordinates' => [
                'latitude' => $lat,
                'longitude' => $lng,
            ],
        ];
    }

    private function cacheKey(string $query, ?string $countryCode): string
    {
        return Str::lower(Str::ascii(trim($query))).'|'.($countryCode ?: '');
    }

    private function reverseCacheKey(float $lat, float $lng): string
    {
        return sprintf('reverse-v53|%.7f|%.7f', $lat, $lng);
    }

    private function validCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180
            && (abs($lat) > 0 || abs($lng) > 0);
    }
}
