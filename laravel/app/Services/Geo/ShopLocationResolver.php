<?php

namespace App\Services\Geo;

class ShopLocationResolver
{
    public function __construct(
        private readonly GeocodingService $geocoding
    ) {
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        $geo = $this->geocoding->reverse($latitude, $longitude);

        return $geo ? $this->toFormPayload($geo) : null;
    }

    public function search(array $payload): ?array
    {
        $country = $payload['country'] ?? "Côte d'Ivoire";
        $district = $payload['district'] ?? $payload['quarter'] ?? null;

        $candidates = collect([
            [
                $payload['address'] ?? null,
                $payload['landmark'] ?? null,
                $district,
                $payload['commune'] ?? null,
                $payload['city'] ?? null,
                $payload['region'] ?? null,
                $country,
            ],
            [
                $payload['landmark'] ?? null,
                $district,
                $payload['commune'] ?? null,
                $payload['city'] ?? null,
                $country,
            ],
            [
                $district,
                $payload['commune'] ?? null,
                $payload['city'] ?? null,
                $country,
            ],
            [
                $payload['commune'] ?? null,
                $payload['city'] ?? null,
                $payload['region'] ?? null,
                $country,
            ],
        ])->map(fn (array $parts) => collect($parts)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => mb_strtolower($value))
            ->implode(', '))
            ->filter()
            ->unique()
            ->values();

        foreach ($candidates as $attempt => $query) {
            $geo = collect($this->geocoding->search(
                $query,
                config('geo.country_code', 'CI')
            ))->first(fn (array $result) => $this->isInsideConfiguredCountry($result));

            if (! $geo) {
                continue;
            }

            $resolved = $this->toFormPayload($geo);

            // Les requêtes dégradées servent à ne pas bloquer l'ouverture de la
            // boutique, mais elles ne doivent jamais être considérées comme une
            // position d'enlèvement exacte. La publication restera donc bloquée
            // jusqu'à une confirmation GPS ou cartographique.
            if ($attempt > 0) {
                $resolved['precision'] = 'low';
                $resolved['precision_score'] = min(45, (int) ($resolved['precision_score'] ?? 35));
                $resolved['precision_label'] = 'Précision à vérifier';
            }

            return $resolved;
        }

        return null;
    }

    public function toFormPayload(array $geo): array
    {
        $address = is_array($geo['address'] ?? null) ? $geo['address'] : [];
        $displayName = trim((string) ($geo['display_name'] ?? ''));

        $road = $this->firstFilled($address, ['road', 'pedestrian', 'residential', 'path']);
        $houseNumber = $this->firstFilled($address, ['house_number']);
        $streetAddress = trim(implode(' ', array_filter([$houseNumber, $road])));

        $city = $this->firstFilled($address, [
            'city', 'town', 'village', 'municipality', 'suburb',
        ]);

        $resolvedLocation = is_array($geo['resolved_location'] ?? null)
            ? $geo['resolved_location']
            : [];

        $commune = trim((string) ($resolvedLocation['commune'] ?? ''));
        if ($commune === '') {
            $commune = $this->firstFilled($address, [
                'municipality', 'city_district', 'borough', 'suburb', 'city', 'town',
            ]);
        }

        $district = trim((string) ($resolvedLocation['locality'] ?? $resolvedLocation['quartier'] ?? ''));
        if ($district === '') {
            $district = $this->firstFilled($address, [
                'neighbourhood', 'neighborhood', 'quarter', 'suburb', 'hamlet', 'locality',
            ]);
        }

        $region = $this->firstFilled($address, [
            'state', 'region', 'county', 'state_district',
        ]);

        $landmark = $this->firstFilled($address, [
            'amenity', 'shop', 'building', 'tourism', 'leisure', 'road',
        ]);

        if ($landmark === '' && $displayName !== ''
            && ! $this->sameToken($displayNameFirstSegment = trim(explode(',', $displayName)[0] ?? ''), $commune)
            && ! $this->sameToken($displayNameFirstSegment, $district)) {
            $landmark = $displayNameFirstSegment;
        }

        // Quand le fournisseur de géocodage ne renvoie qu'un seul jeton grossier
        // (ex. simple nom de village pour une position peu précise), les chaînes
        // de repli ci-dessus retombent toutes sur cette même valeur. Afficher ce
        // même mot dans quatre champs différents donne l'impression d'un résultat
        // factice au vendeur : on désactive donc les doublons plutôt que de les
        // afficher.
        if ($this->sameToken($landmark, $commune) || $this->sameToken($landmark, $district)) {
            $landmark = '';
        }

        if ($district !== '' && $this->sameToken($district, $commune)) {
            $district = '';
        }

        $resolvedAddress = $streetAddress !== '' ? $streetAddress : $displayName;
        if ($resolvedAddress !== '' && $this->sameToken($resolvedAddress, $commune)) {
            $resolvedAddress = '';
        }

        // Une partie du référentiel des quartiers d'Abidjan contient encore des
        // libellés historiques mal encodés (ex. « Feh Kess?? » pour « Feh Kessé »),
        // reconnaissables au marqueur "??" laissé par une conversion de charset
        // ratée. Ne jamais présenter ce texte visiblement cassé au vendeur : on
        // masque le champ concerné plutôt que d'afficher une donnée illisible.
        if ($this->looksCorrupted($commune)) {
            $commune = '';
        }
        if ($this->looksCorrupted($district)) {
            $district = '';
        }
        if ($this->looksCorrupted($landmark)) {
            $landmark = '';
        }
        if ($this->looksCorrupted($resolvedAddress)) {
            $resolvedAddress = '';
        }

        $precision = $this->detectPrecision($geo);

        return [
            'success' => true,
            'latitude' => isset($geo['latitude']) ? round((float) $geo['latitude'], 7) : null,
            'longitude' => isset($geo['longitude']) ? round((float) $geo['longitude'], 7) : null,
            'display_name' => $displayName ?: null,
            'address' => $resolvedAddress ?: null,
            'region' => $region ?: null,
            'city' => $city ?: null,
            'commune' => $commune ?: null,
            'district' => $district ?: null,
            'locality_type' => $resolvedLocation['locality_type'] ?? null,
            'locality_type_label' => $resolvedLocation['locality_type_label'] ?? null,
            'locality_catalogued' => (bool) ($resolvedLocation['locality_catalogued'] ?? false),
            'matched_alias' => $resolvedLocation['matched_alias'] ?? null,
            'landmark' => $landmark ?: null,
            'country' => $this->firstFilled($address, ['country']) ?: "Côte d'Ivoire",
            'source' => $geo['source'] ?? 'geocoding',
            'precision' => $precision['level'],
            'precision_score' => $precision['score'],
            'precision_label' => $precision['label'],
        ];
    }

    /**
     * Compare deux jetons d'adresse en ignorant casse, accents et espaces
     * superflus, afin de détecter les doublons produits par les chaînes de
     * repli du géocodage (ex. "Gobelet village" répété dans plusieurs champs).
     */
    private function sameToken(?string $a, ?string $b): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);

        if ($a === '' || $b === '') {
            return false;
        }

        return $this->normalize($a) === $this->normalize($b);
    }

    private function normalize(string $value): string
    {
        return \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim($value)));
    }

    /**
     * Détecte les libellés dont l'encodage a été corrompu (accents remplacés
     * par des "?", ex. « Att??coub?? »), qu'ils viennent du fournisseur de
     * géocodage ou du référentiel legacy des quartiers d'Abidjan.
     */
    private function looksCorrupted(?string $value): bool
    {
        $value = (string) $value;
        if ($value === '') {
            return false;
        }

        return str_contains($value, '??') || str_contains($value, "\u{FFFD}");
    }

    /**
     * Retourne une estimation homogène de la précision, quel que soit le fournisseur
     * de géocodage. Cette valeur alimente le statut interne de fiabilité utilisé par
     * OVANIE Logistics ; elle n'est pas exposée au vendeur dans le formulaire.
     */
    private function detectPrecision(array $geo): array
    {
        $source = strtolower((string) ($geo['source'] ?? ''));
        $raw = is_array($geo['raw'] ?? null) ? $geo['raw'] : [];

        if ($source === 'mapbox') {
            $properties = is_array($raw['properties'] ?? null) ? $raw['properties'] : [];
            $featureType = strtolower((string) ($properties['feature_type'] ?? ''));
            $confidence = strtolower((string) data_get($properties, 'match_code.confidence', ''));

            if (in_array($confidence, ['exact', 'high'], true)) {
                return $this->precisionPayload('high', 92);
            }

            if ($confidence === 'medium') {
                return $this->precisionPayload('medium', 72);
            }

            if (in_array($featureType, ['address', 'secondary_address'], true)) {
                return $this->precisionPayload('high', 90);
            }

            if (in_array($featureType, ['street'], true)) {
                return $this->precisionPayload('medium', 76);
            }

            if (in_array($featureType, ['neighborhood', 'locality'], true)) {
                return $this->precisionPayload('medium', 62);
            }

            return $this->precisionPayload('low', 40);
        }

        $nominatimType = strtolower((string) ($raw['addresstype'] ?? $raw['type'] ?? ''));
        $nominatimClass = strtolower((string) ($raw['class'] ?? ''));

        if (in_array($nominatimType, [
            'house', 'building', 'shop', 'amenity', 'office', 'commercial',
            'industrial', 'retail', 'warehouse',
        ], true) || in_array($nominatimClass, ['building', 'shop', 'amenity', 'office'], true)) {
            return $this->precisionPayload('high', 90);
        }

        if (in_array($nominatimType, ['road', 'street', 'pedestrian', 'residential'], true)
            || $nominatimClass === 'highway') {
            return $this->precisionPayload('medium', 76);
        }

        if (in_array($nominatimType, ['neighbourhood', 'neighborhood', 'quarter', 'suburb', 'hamlet'], true)) {
            return $this->precisionPayload('medium', 64);
        }

        if (in_array($nominatimType, ['village', 'town', 'city', 'municipality'], true)) {
            return $this->precisionPayload('low', 46);
        }

        return $this->precisionPayload('low', 35);
    }

    private function isInsideConfiguredCountry(array $geo): bool
    {
        $latitude = $geo['latitude'] ?? null;
        $longitude = $geo['longitude'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $bounds = config('geo.country_bounds', []);

        return (float) $latitude >= (float) ($bounds['min_lat'] ?? -90)
            && (float) $latitude <= (float) ($bounds['max_lat'] ?? 90)
            && (float) $longitude >= (float) ($bounds['min_lng'] ?? -180)
            && (float) $longitude <= (float) ($bounds['max_lng'] ?? 180);
    }

    private function precisionPayload(string $level, int $score): array
    {
        return [
            'level' => $level,
            'score' => $score,
            'label' => match ($level) {
                'high' => 'Précision élevée',
                'medium' => 'Précision moyenne',
                default => 'Précision à vérifier',
            },
        ];
    }

    private function firstFilled(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
