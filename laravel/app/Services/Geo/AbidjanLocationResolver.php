<?php

namespace App\Services\Geo;

use Illuminate\Support\Str;

class AbidjanLocationResolver
{
    public function __construct(
        private readonly AbidjanLocalityRegistry $registry
    ) {}

    /**
     * Transforme les réponses Mapbox/Nominatim en champs métier OVANIE sans
     * modifier les coordonnées GPS d'origine.
     */
    public function enrich(array $geo): array
    {
        $parts = $this->extractTextParts($geo);
        $providerQuarter = $this->providerQuarter($geo);
        $resolved = $this->registry->resolve($parts, null, $providerQuarter);
        $city = $this->detectCity($geo);

        $commune = $this->canonicalCommuneDisplay($resolved['commune'] ?? null);
        $quarter = trim((string) ($resolved['quarter'] ?? '')) ?: null;

        // Un fournisseur peut retourner « Adjamé » comme suburb/locality alors
        // qu'il s'agit déjà de la commune. Ne jamais afficher la même valeur à
        // la fois comme commune et comme quartier/repère.
        if ($this->sameLocationName($quarter, $commune) || $this->isGenericAbidjan($quarter)) {
            $quarter = null;
        }

        $landmark = $this->providerLandmark($geo);
        if ($this->sameLocationName($landmark, $commune)
            || $this->sameLocationName($landmark, $quarter)
            || $this->isGenericAbidjan($landmark)) {
            $landmark = null;
        }

        $isAbidjan = (bool) ($resolved['is_abidjan'] ?? false) || $commune !== null;

        $geo['resolved_location'] = [
            'zone' => $isAbidjan ? 'abidjan' : ($resolved['zone'] ?? 'interieur'),
            'is_abidjan' => $isAbidjan,
            'commune' => $commune,
            'commune_slug' => $commune ? Str::slug($commune) : null,
            'quartier' => $quarter,
            'quartier_slug' => $quarter ? Str::slug($quarter) : null,
            'repere' => $landmark,
            'quartier_catalogued' => (bool) ($resolved['quarter_catalogued'] ?? false),
            'locality_id' => $resolved['locality_id'] ?? null,
            'locality_type' => $resolved['locality_type'] ?? null,
            'locality_type_label' => $resolved['locality_type_label'] ?? null,
            'locality_catalogued' => $resolved['locality_catalogued'] ?? ($resolved['quarter_catalogued'] ?? false),
            'matched_alias' => $resolved['matched_alias'] ?? null,
            // Dans le District d'Abidjan, la ville d'affichage reste Abidjan ;
            // la commune est portée séparément.
            'city' => $isAbidjan ? 'Abidjan' : $city,
            'confidence' => $resolved['confidence'] ?? 'unknown',
            'locality_confidence' => $resolved['locality_confidence'] ?? ($resolved['confidence'] ?? 'unknown'),
        ];

        return $geo;
    }

    public function mergeAndEnrich(array $primary, array $secondary): array
    {
        $merged = $primary;

        // Primary conserve la priorité, mais le secondaire complète tous les
        // champs précis absents (route, quartier, bâtiment, etc.).
        $merged['address'] = array_filter(array_merge(
            (array) ($secondary['address'] ?? []),
            (array) ($primary['address'] ?? [])
        ), static fn ($value) => $value !== null && trim((string) $value) !== '');

        $merged['fallback_raw'] = $secondary['raw'] ?? null;
        $merged['fallback_source'] = $secondary['source'] ?? null;
        $merged['fallback'] = $secondary;

        // Garder le libellé humain le plus détaillé. Une simple commune comme
        // « Adjamé, Abidjan » ne doit pas écraser une rue/cité plus précise.
        $primaryName = trim((string) ($primary['display_name'] ?? ''));
        $secondaryName = trim((string) ($secondary['display_name'] ?? ''));
        if ($this->detailScore($secondaryName) > $this->detailScore($primaryName)) {
            $merged['display_name'] = $secondaryName;
        } elseif ($primaryName === '' && $secondaryName !== '') {
            $merged['display_name'] = $secondaryName;
        }

        if (blank($merged['feature_name'] ?? null) && filled($secondary['feature_name'] ?? null)) {
            $merged['feature_name'] = $secondary['feature_name'];
        }

        return $this->enrich($merged);
    }

    /**
     * Le second géocodeur est utile non seulement quand la commune manque,
     * mais aussi quand le premier résultat est trop générique pour l'utilisateur.
     */
    public function needsAdministrativeEnrichment(array $geo): bool
    {
        $resolved = (array) ($geo['resolved_location'] ?? []);

        if (! (bool) ($resolved['is_abidjan'] ?? false)) {
            return false;
        }

        if (blank($resolved['commune'] ?? null)) {
            return true;
        }

        return blank($resolved['quartier'] ?? null)
            && blank($resolved['repere'] ?? null)
            && $this->detailScore((string) ($geo['display_name'] ?? '')) < 3;
    }

    public function detectCommune(array|string|null $parts): ?string
    {
        return $this->canonicalCommuneDisplay($this->registry->resolve($parts)['commune'] ?? null);
    }

    public function isAbidjan(array|string|null $parts): bool
    {
        return (bool) $this->registry->resolve($parts)['is_abidjan'];
    }

    public function canonicalCommune(?string $value): ?string
    {
        if ($this->isGenericAbidjan($value)) {
            return 'Abidjan';
        }

        return $this->canonicalCommuneDisplay($this->registry->canonicalCommune($value) ?: $value);
    }

    public function communeSlug(?string $value): string
    {
        $canonical = $this->canonicalCommune($value);

        return $canonical ? Str::slug($canonical) : '';
    }

    public function isGenericAbidjan(?string $value): bool
    {
        return $this->registry->isGenericAbidjan($value);
    }

    private function extractTextParts(array $geo): array
    {
        $parts = [];
        $this->collectStrings($geo['display_name'] ?? null, $parts);
        $this->collectStrings($geo['address'] ?? [], $parts);
        $this->collectStrings($geo['hierarchy'] ?? [], $parts);
        $this->collectStrings($geo['feature_name'] ?? null, $parts);
        $this->collectStrings($geo['raw'] ?? [], $parts);
        $this->collectStrings($geo['fallback'] ?? [], $parts);
        $this->collectStrings($geo['fallback_raw'] ?? [], $parts);

        return array_values(array_unique(array_filter($parts)));
    }

    private function collectStrings(mixed $value, array &$parts): void
    {
        if (is_string($value) && trim($value) !== '') {
            $parts[] = trim($value);
            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $nested) {
            $this->collectStrings($nested, $parts);
        }
    }

    private function providerQuarter(array $geo): ?string
    {
        $address = (array) ($geo['address'] ?? []);

        foreach (['neighbourhood', 'neighborhood', 'quarter', 'residential', 'locality', 'suburb'] as $key) {
            $candidate = trim((string) ($address[$key] ?? ''));
            if ($candidate === '' || $this->isGenericAbidjan($candidate)) {
                continue;
            }

            if ($this->registry->canonicalCommune($candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function providerLandmark(array $geo): ?string
    {
        $address = (array) ($geo['address'] ?? []);

        foreach ([
            'amenity', 'building', 'shop', 'office', 'tourism', 'attraction',
            'commercial', 'industrial', 'road', 'pedestrian',
        ] as $key) {
            $candidate = trim((string) ($address[$key] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $featureName = trim((string) ($geo['feature_name'] ?? ''));
        return $featureName !== '' ? $featureName : null;
    }

    private function detectCity(array $geo): ?string
    {
        $address = (array) ($geo['address'] ?? []);

        foreach (['city', 'town', 'village', 'municipality', 'county'] as $key) {
            if (filled($address[$key] ?? null)) {
                return trim((string) $address[$key]);
            }
        }

        return null;
    }

    private function detailScore(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $segments = collect(explode(',', $value))
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique(fn ($part) => Str::lower(Str::ascii($part)))
            ->count();

        $score = $segments;
        if (preg_match('/\d/u', $value)) {
            $score += 2;
        }
        if (preg_match('/\b(rue|avenue|boulevard|cite|cité|residence|résidence|carrefour|route|lot|immeuble|ecole|école|hopital|hôpital|marche|marché)\b/iu', $value)) {
            $score += 2;
        }

        return $score;
    }

    private function canonicalCommuneDisplay(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $key = $this->normalizedKey($value);
        $known = [
            'abobo' => 'Abobo',
            'adjame' => 'Adjamé',
            'adjam' => 'Adjamé', // protège les données historiques mal encodées « Adjam?? »
            'anyama' => 'Anyama',
            'attecoube' => 'Attécoubé',
            'attecoub' => 'Attécoubé',
            'bingerville' => 'Bingerville',
            'cocody' => 'Cocody',
            'koumassi' => 'Koumassi',
            'marcory' => 'Marcory',
            'plateau' => 'Plateau',
            'portbouet' => 'Port-Bouët',
            'songon' => 'Songon',
            'treichville' => 'Treichville',
            'yopougon' => 'Yopougon',
        ];

        return $known[$key] ?? $value;
    }

    private function normalizedKey(?string $value): string
    {
        $ascii = Str::lower(Str::ascii(trim((string) $value)));
        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?: '';
    }

    private function sameLocationName(?string $left, ?string $right): bool
    {
        $a = $this->normalizedKey($left);
        $b = $this->normalizedKey($right);
        return $a !== '' && $a === $b;
    }
}
