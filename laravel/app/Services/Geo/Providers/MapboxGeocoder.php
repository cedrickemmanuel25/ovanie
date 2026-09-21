<?php

namespace App\Services\Geo\Providers;

use App\Services\Geo\Contracts\GeocoderInterface;
use Illuminate\Support\Facades\Http;

class MapboxGeocoder implements GeocoderInterface
{
    public function search(string $query, ?string $countryCode = null): array
    {
        $token = $this->token();
        if (! $token || trim($query) === '') {
            return [];
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withoutVerifying()
                ->get('https://api.mapbox.com/search/geocode/v6/forward', [
                    'q' => $query,
                    'access_token' => $token,
                    'language' => config('geo.mapbox.language', 'fr'),
                    'country' => $countryCode ?: config('geo.mapbox.search_country', 'CI'),
                    'limit' => 5,
                ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        return collect($response->json('features') ?: [])
            ->map(fn (array $feature) => $this->mapFeature($feature))
            ->filter()
            ->values()
            ->all();
    }

    public function reverse(float $latitude, float $longitude): array
    {
        $token = $this->token();
        if (! $token) {
            return [];
        }

        try {
            // Ne pas imposer limit=1 ici. Le comportement reverse par défaut
            // renvoie une hiérarchie complète (adresse/rue/quartier/localité/...)
            // dont nous extrayons ensuite le résultat le plus précis.
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withoutVerifying()
                ->get('https://api.mapbox.com/search/geocode/v6/reverse', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'access_token' => $token,
                    'language' => config('geo.mapbox.language', 'fr'),
                    'country' => config('geo.mapbox.search_country', 'CI'),
                ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $features = collect($response->json('features') ?: [])
            ->filter(fn ($feature) => is_array($feature))
            ->values();

        if ($features->isEmpty()) {
            return [];
        }

        // Mapbox ordonne le reverse du plus granulaire au plus large. On part
        // donc du premier résultat, puis on complète avec les autres niveaux.
        $primary = $this->mapFeature((array) $features->first());
        if (! $primary) {
            return [];
        }

        $address = (array) ($primary['address'] ?? []);
        $hierarchy = [];

        foreach ($features as $feature) {
            $feature = (array) $feature;
            $properties = (array) ($feature['properties'] ?? []);
            $type = trim((string) ($properties['feature_type'] ?? ''));
            $name = trim((string) ($properties['name_preferred'] ?? $properties['name'] ?? ''));
            if ($type !== '' && $name !== '') {
                $hierarchy[$type] = $name;
            }
        }

        $address = array_filter([
            'house_number' => $address['house_number'] ?? null,
            'road' => $address['road'] ?? ($hierarchy['street'] ?? null),
            'neighbourhood' => $address['neighbourhood'] ?? ($hierarchy['neighborhood'] ?? null),
            'locality' => $address['locality'] ?? ($hierarchy['locality'] ?? null),
            'suburb' => $address['suburb'] ?? ($hierarchy['neighborhood'] ?? null),
            'city' => $address['city'] ?? ($hierarchy['place'] ?? null),
            'municipality' => $address['municipality'] ?? ($hierarchy['locality'] ?? null),
            'district' => $address['district'] ?? ($hierarchy['district'] ?? null),
            'county' => $address['county'] ?? ($hierarchy['region'] ?? null),
            'country' => $address['country'] ?? ($hierarchy['country'] ?? null),
        ], static fn ($value) => $value !== null && trim((string) $value) !== '');

        $primary['address'] = $address;
        $primary['hierarchy'] = $hierarchy;
        $primary['raw_features'] = $features->all();

        return $primary;
    }

    private function token(): ?string
    {
        return config('geo.mapbox.geocoding_token') ?: config('geo.mapbox.public_token');
    }

    private function mapFeature(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;
        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $longitude = (float) $coordinates[0];
        $latitude = (float) $coordinates[1];
        $properties = (array) ($feature['properties'] ?? []);
        $context = (array) ($properties['context'] ?? []);
        $featureType = trim((string) ($properties['feature_type'] ?? ''));
        $featureName = trim((string) ($properties['name_preferred'] ?? $properties['name'] ?? ''));

        $road = null;
        if (in_array($featureType, ['address', 'street'], true) && $featureName !== '') {
            $road = $featureName;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'display_name' => $this->displayName($feature),
            'address' => array_filter([
                'house_number' => $properties['address'] ?? null,
                'road' => $road,
                'neighbourhood' => $this->contextName($context, 'neighborhood'),
                'locality' => $this->contextName($context, 'locality'),
                'suburb' => $this->contextName($context, 'neighborhood'),
                'city' => $this->contextName($context, 'place'),
                'municipality' => $this->contextName($context, 'locality'),
                'district' => $this->contextName($context, 'district'),
                'county' => $this->contextName($context, 'region'),
                'country' => $this->contextName($context, 'country'),
            ], static fn ($value) => $value !== null && trim((string) $value) !== ''),
            'feature_type' => $featureType,
            'feature_name' => $featureName,
            'raw' => $feature,
            'source' => 'mapbox',
        ];
    }

    private function displayName(array $feature): ?string
    {
        $properties = (array) ($feature['properties'] ?? []);

        return $properties['full_address']
            ?? $properties['place_formatted']
            ?? $properties['name_preferred']
            ?? $properties['name']
            ?? null;
    }

    private function contextName(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;
        if (is_array($value)) {
            $name = trim((string) ($value['name_preferred'] ?? $value['name'] ?? ''));
            return $name !== '' ? $name : null;
        }

        return null;
    }
}
