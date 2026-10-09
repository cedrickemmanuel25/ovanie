<?php

namespace App\Services\Geo;

use Illuminate\Validation\ValidationException;

class AdminGoogleMapsLocationService
{
    public function __construct(
        private readonly GeocodingService $geocoding
    ) {}

    /**
     * Résout une référence Google Maps sans exposer latitude/longitude dans le formulaire admin.
     *
     * Formats acceptés :
     * - "5.336123, -4.028456"
     * - URL Google Maps contenant @lat,lng
     * - URL Google Maps contenant !3dLAT!4dLNG
     * - URL avec q=LAT,LNG / query=LAT,LNG
     * - à défaut, une adresse textuelle géocodable.
     */
    public function resolve(
        ?string $googleMapsReference,
        ?string $address,
        ?string $commune,
        ?string $district,
        ?string $landmark = null
    ): array {
        $reference = trim((string) $googleMapsReference);
        $coordinates = $this->extractCoordinates($reference);

        if ($coordinates) {
            [$latitude, $longitude] = $coordinates;
            $reverse = $this->geocoding->reverse($latitude, $longitude);
            $form = $reverse ? $this->geocoding->toFormPayload($reverse) : [];

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $this->prefer($address, $form['address'] ?? null),
                'commune' => $this->prefer($commune, $form['commune'] ?? null),
                'district' => $this->prefer($district, $form['district'] ?? null),
                'landmark' => $this->prefer($landmark, $form['landmark'] ?? null),
                'region' => $form['region'] ?? null,
                'city' => $form['city'] ?? null,
                'source' => 'admin_google_maps',
                'precision' => 'high',
                'precision_score' => 95,
                'resolved_label' => $form['display_name'] ?? null,
            ];
        }

        $query = collect([$reference, $address, $district, $commune, "Côte d'Ivoire"])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(', ');

        if ($query === '') {
            throw ValidationException::withMessages([
                'google_maps_reference' => 'Collez une position Google Maps ou renseignez une adresse exploitable.',
            ]);
        }

        $best = $this->geocoding->search($query, 'ci')[0] ?? null;

        if (! $best || ! isset($best['latitude'], $best['longitude'])) {
            throw ValidationException::withMessages([
                'google_maps_reference' => 'OVANIE n’a pas pu localiser cette référence. Copiez les coordonnées ou un lien Google Maps contenant la position exacte.',
            ]);
        }

        $form = $this->geocoding->toFormPayload($best);

        return [
            'latitude' => (float) $best['latitude'],
            'longitude' => (float) $best['longitude'],
            'address' => $this->prefer($address, $form['address'] ?? null),
            'commune' => $this->prefer($commune, $form['commune'] ?? null),
            'district' => $this->prefer($district, $form['district'] ?? null),
            'landmark' => $this->prefer($landmark, $form['landmark'] ?? null),
            'region' => $form['region'] ?? null,
            'city' => $form['city'] ?? null,
            'source' => 'admin_geocoding',
            'precision' => $form['precision'] ?? 'medium',
            'precision_score' => (int) ($form['precision_score'] ?? 70),
            'resolved_label' => $form['display_name'] ?? null,
        ];
    }

    private function extractCoordinates(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        $decoded = urldecode($value);
        $patterns = [
            '/@\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/',
            '/!3d\s*(-?\d{1,2}(?:\.\d+)?)!4d\s*(-?\d{1,3}(?:\.\d+)?)/',
            '/(?:[?&](?:q|query|ll|center|destination)=)\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)/i',
            '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $decoded, $matches)) {
                continue;
            }

            $latitude = (float) $matches[1];
            $longitude = (float) $matches[2];

            if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
                return [$latitude, $longitude];
            }
        }

        return null;
    }

    private function prefer(?string $manual, ?string $resolved): ?string
    {
        $manual = trim((string) $manual);
        if ($manual !== '') {
            return $manual;
        }

        $resolved = trim((string) $resolved);

        return $resolved !== '' ? $resolved : null;
    }
}
