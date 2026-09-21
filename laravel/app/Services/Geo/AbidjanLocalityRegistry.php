<?php

namespace App\Services\Geo;

use App\Models\AbidjanCommune;
use App\Models\AbidjanLocality;
use App\Models\AbidjanQuarter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AbidjanLocalityRegistry
{
    private const CACHE_KEY = 'geo:abidjan-locality-registry:v3';

    public function communes(): array
    {
        return collect($this->catalog())
            ->map(fn (array $commune) => [
                'id' => $commune['id'] ?? null,
                'code' => $commune['code'],
                'name' => $commune['name'],
                'slug' => $commune['slug'],
                'aliases' => $commune['aliases'],
                'quarters_count' => count($commune['quarters']),
                'localities_count' => count($commune['quarters']),
            ])
            ->values()
            ->all();
    }

    /**
     * Retourne toutes les localités d'une commune : quartier, sous-quartier,
     * cité, village et carrefour. Le nom historique de la méthode est conservé
     * pour ne pas casser le checkout et les anciennes routes.
     */
    public function quartersForCommune(?string $commune, ?string $query = null, int $limit = 100): array
    {
        return $this->localitiesForCommune($commune, $query, $limit);
    }

    public function localitiesForCommune(?string $commune, ?string $query = null, int $limit = 100): array
    {
        $canonical = $this->canonicalCommune($commune);
        if (! $canonical) {
            return [];
        }

        $needle = $this->normalize((string) $query);
        $communeRow = collect($this->catalog())->firstWhere('name', $canonical);
        if (! $communeRow) {
            return [];
        }

        return collect($communeRow['quarters'])
            ->filter(function (array $locality) use ($needle) {
                if ($needle === '') {
                    return true;
                }

                $values = [
                    $locality['name'],
                    $locality['type_label'] ?? '',
                    ...($locality['aliases'] ?? []),
                ];

                return collect($values)->contains(function ($value) use ($needle) {
                    $normalized = $this->normalize((string) $value);
                    return $normalized !== '' && str_contains($normalized, $needle);
                });
            })
            ->sort(function (array $left, array $right) {
                $typeComparison = $this->typeOrder((string) ($left['type'] ?? 'quartier'))
                    <=> $this->typeOrder((string) ($right['type'] ?? 'quartier'));

                if ($typeComparison !== 0) {
                    return $typeComparison;
                }

                return strnatcasecmp(
                    Str::ascii((string) ($left['name'] ?? '')),
                    Str::ascii((string) ($right['name'] ?? ''))
                );
            })
            ->take(max(1, min($limit, 500)))
            ->values()
            ->all();
    }

    /**
     * Résout une localisation sans faire du quartier une condition de desserte.
     * Une commune d'Abidjan reconnue reste suffisante pour l'éligibilité.
     */
    public function resolve(array|string|null $parts, ?string $communeHint = null, ?string $quarterHint = null): array
    {
        $values = is_array($parts) ? $parts : [(string) $parts];
        $values = collect([$communeHint, $quarterHint, ...$values])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();

        $segments = $this->segments($values);
        $explicitCommune = $this->matchCommune($segments);
        $commune = $explicitCommune ?: $this->canonicalCommune($communeHint);
        $locality = $this->matchQuarter($segments, $commune);

        if ($locality && ! $commune) {
            $commune = $locality['commune'];
        }

        $isAbidjan = (bool) $commune || $this->containsGenericAbidjan($segments);
        $rawQuarter = filled($quarterHint) ? trim((string) $quarterHint) : null;

        return [
            'zone' => $isAbidjan ? 'abidjan' : 'interieur',
            'is_abidjan' => $isAbidjan,
            'commune' => $commune,
            'commune_slug' => $commune ? Str::slug($commune) : null,
            'quarter' => $locality['name'] ?? $rawQuarter,
            'quarter_slug' => isset($locality['name'])
                ? Str::slug($locality['name'])
                : ($rawQuarter ? Str::slug($rawQuarter) : null),
            'quarter_catalogued' => (bool) $locality,
            'locality_catalogued' => (bool) $locality,
            'locality_id' => $locality['locality_id'] ?? null,
            'locality_type' => $locality['type'] ?? null,
            'locality_type_label' => $locality['type_label'] ?? null,
            'parent_quarter' => $locality['parent_name'] ?? null,
            'matched_alias' => $locality['matched_alias'] ?? null,
            'confidence' => $locality
                ? 'quarter'
                : ($commune ? 'commune' : ($isAbidjan ? 'abidjan_generic' : 'unknown')),
        ];
    }

    public function canonicalCommune(?string $value): ?string
    {
        $needle = $this->normalize((string) $value);
        if ($needle === '') {
            return null;
        }

        foreach ($this->catalog() as $commune) {
            foreach ([$commune['name'], ...$commune['aliases']] as $alias) {
                if ($needle === $this->normalize((string) $alias)) {
                    return $commune['name'];
                }
            }
        }

        return null;
    }

    public function isGenericAbidjan(?string $value): bool
    {
        $needle = $this->normalize((string) $value);

        return collect(config('abidjan_localities.generic_aliases', []))
            ->contains(fn ($alias) => $needle === $this->normalize((string) $alias));
    }

    /**
     * Dictionnaire léger utilisé comme secours côté navigateur.
     * Les alias ambigus présents dans plusieurs communes sont exclus.
     */
    public function clientLocationMap(): array
    {
        $candidates = [];

        foreach ($this->catalog() as $commune) {
            foreach ($commune['quarters'] as $locality) {
                foreach ([$locality['name'], ...($locality['aliases'] ?? [])] as $alias) {
                    $normalized = $this->normalize((string) $alias);
                    if ($normalized === '' || mb_strlen($normalized) < 3) {
                        continue;
                    }
                    $candidates[$normalized][$commune['name']] = $alias;
                }
            }
        }

        $dictionary = [];
        foreach ($candidates as $communes) {
            if (count($communes) !== 1) {
                continue;
            }
            $commune = array_key_first($communes);
            $dictionary[(string) reset($communes)] = $commune;
        }

        uksort($dictionary, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $dictionary;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('geo:abidjan-locality-registry:v2');
        Cache::forget('geo:abidjan-locality-registry:v1');
    }

    private function catalog(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(6), function () {
            $database = $this->catalogFromDatabase();

            return $database !== [] ? $database : $this->catalogFromConfig();
        });
    }

    private function catalogFromDatabase(): array
    {
        try {
            if (! Schema::hasTable('abidjan_communes')) {
                return [];
            }

            $communes = AbidjanCommune::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($communes->isEmpty()) {
                return [];
            }

            $localitiesByCommune = collect();
            if (Schema::hasTable('abidjan_localities')) {
                $localitiesByCommune = AbidjanLocality::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->groupBy('commune_id');
            }

            $quartersByCommune = collect();
            if (Schema::hasTable('abidjan_quarters')) {
                $quartersByCommune = AbidjanQuarter::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->groupBy('commune_id');
            }

            return $communes->map(function (AbidjanCommune $commune) use ($localitiesByCommune, $quartersByCommune) {
                $quarters = collect($quartersByCommune->get($commune->id, []));
                $quartersById = $quarters->keyBy('id');

                // V72 : fusionner les deux référentiels au lieu de choisir l'un OU
                // l'autre. Certaines localités fines (sous-quartiers/cités/villages)
                // sont dans abidjan_localities, alors que des quartiers historiques
                // comme « Feh Kessé » n'existent que dans abidjan_quarters.
                $localityRows = collect($localitiesByCommune->get($commune->id, []))
                    ->map(function (AbidjanLocality $locality) use ($quartersById) {
                        $legacy = $locality->quarter_id
                            ? $quartersById->get($locality->quarter_id)
                            : null;
                        $legacyParent = $legacy?->parent_id
                            ? $quartersById->get($legacy->parent_id)
                            : null;
                        $aliases = array_values(array_unique(array_filter([
                            ...(array) $locality->alias_google_maps,
                            $locality->quartier,
                            $locality->sous_quartier,
                            $locality->cite,
                            $locality->village,
                            $locality->carrefour,
                            ...($legacy ? (array) $legacy->aliases : []),
                            ...($legacy ? (array) $legacy->search_terms : []),
                        ], fn ($value) => filled($value))));

                        return [
                            'id' => $locality->quarter_id ?: $locality->id,
                            'locality_id' => $locality->id,
                            'commune_id' => $locality->commune_id,
                            'name' => $locality->name,
                            'slug' => $locality->slug,
                            'aliases' => $aliases,
                            'type' => $this->normalizeStoredType((string) $locality->type),
                            'type_label' => $locality->typeLabel(),
                            'parent_name' => $legacyParent?->name,
                            'latitude' => $locality->latitude !== null ? (float) $locality->latitude : null,
                            'longitude' => $locality->longitude !== null ? (float) $locality->longitude : null,
                            'source' => $locality->source,
                            'verified' => (bool) $locality->is_verified,
                        ];
                    });

                $legacyRows = $quarters->map(function (AbidjanQuarter $quarter) use ($quartersById) {
                    $type = $this->normalizeStoredType((string) $quarter->type);
                    $parent = $quarter->parent_id ? $quartersById->get($quarter->parent_id) : null;

                    return [
                        'id' => $quarter->id,
                        'locality_id' => null,
                        'commune_id' => $quarter->commune_id,
                        'name' => $quarter->name,
                        'slug' => $quarter->slug,
                        'aliases' => array_values(array_unique(array_filter([
                            ...(array) $quarter->aliases,
                            ...(array) $quarter->search_terms,
                        ], fn ($value) => filled($value)))),
                        'type' => $type,
                        'type_label' => $this->typeLabel($type),
                        'parent_name' => $parent?->name,
                        'latitude' => $quarter->latitude !== null ? (float) $quarter->latitude : null,
                        'longitude' => $quarter->longitude !== null ? (float) $quarter->longitude : null,
                        'source' => $quarter->source,
                        'verified' => (bool) $quarter->is_verified,
                    ];
                });

                $rows = $localityRows
                    ->concat($legacyRows)
                    ->unique(fn (array $row) => $this->normalize((string) $row['name']).'|'.($row['type'] ?? 'quartier'))
                    ->values()
                    ->all();

                return [
                    'id' => $commune->id,
                    'code' => $commune->code,
                    'name' => $commune->name,
                    'slug' => $commune->slug,
                    'aliases' => array_values(array_unique(array_filter((array) $commune->aliases))),
                    'quarters' => $rows,
                ];
            })->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function catalogFromConfig(): array
    {
        $catalog = [];

        foreach ((array) config('abidjan_localities.communes', []) as $name => $definition) {
            $quarters = [];
            foreach ((array) ($definition['quarters'] ?? []) as $quarter) {
                $row = is_array($quarter) ? $quarter : ['name' => $quarter];
                $quarterName = trim((string) ($row['name'] ?? ''));
                if ($quarterName === '') {
                    continue;
                }

                $type = $this->normalizeStoredType((string) ($row['type'] ?? 'quartier'));
                $quarters[] = [
                    'id' => null,
                    'locality_id' => null,
                    'commune_id' => null,
                    'name' => $quarterName,
                    'slug' => Str::slug($quarterName),
                    'aliases' => array_values(array_unique(array_filter((array) ($row['aliases'] ?? [])))),
                    'type' => $type,
                    'type_label' => $this->typeLabel($type),
                    'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
                    'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
                    'source' => $row['source'] ?? 'ovanie_seed',
                    'verified' => (bool) ($row['verified'] ?? false),
                ];
            }

            $catalog[] = [
                'id' => null,
                'code' => (string) ($definition['code'] ?? Str::upper(Str::substr(Str::slug($name, ''), 0, 3))),
                'name' => (string) $name,
                'slug' => Str::slug((string) $name),
                'aliases' => array_values(array_unique(array_filter((array) ($definition['aliases'] ?? [])))),
                'quarters' => $quarters,
            ];
        }

        return $catalog;
    }

    private function matchCommune(array $segments): ?string
    {
        foreach ($this->catalog() as $commune) {
            foreach ([$commune['name'], ...$commune['aliases']] as $alias) {
                $needle = $this->normalize((string) $alias);
                if ($needle !== '' && in_array($needle, $segments, true)) {
                    return $commune['name'];
                }
            }
        }

        return null;
    }

    private function matchQuarter(array $segments, ?string $commune): ?array
    {
        $haystack = implode(' ', $segments);
        $matches = [];

        foreach ($this->catalog() as $communeRow) {
            if ($commune && $communeRow['name'] !== $commune) {
                continue;
            }

            foreach ($communeRow['quarters'] as $locality) {
                foreach ([$locality['name'], ...($locality['aliases'] ?? [])] as $alias) {
                    $needle = $this->normalize((string) $alias);
                    if ($needle === '') {
                        continue;
                    }

                    $exact = in_array($needle, $segments, true);
                    $contained = mb_strlen($needle) >= 3 && $this->containsToken($haystack, $needle);
                    if (! $exact && ! $contained) {
                        continue;
                    }

                    $matches[] = [
                        'id' => $locality['id'] ?? null,
                        'locality_id' => $locality['locality_id'] ?? null,
                        'name' => $locality['name'],
                        'commune' => $communeRow['name'],
                        'type' => $locality['type'] ?? 'quartier',
                        'type_label' => $locality['type_label'] ?? 'Quartier',
                        'parent_name' => $locality['parent_name'] ?? null,
                        'matched_alias' => (string) $alias,
                        'score' => mb_strlen($needle) + ($exact ? 1000 : 0),
                    ];
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $best = $matches[0];

        if (! $commune) {
            $sameScoreCommunes = collect($matches)
                ->where('score', $best['score'])
                ->pluck('commune')
                ->unique();

            if ($sameScoreCommunes->count() > 1) {
                return null;
            }
        }

        unset($best['score']);

        return $best;
    }

    private function segments(array $values): array
    {
        return collect($values)
            ->flatMap(function (string $value) {
                $parts = preg_split('/[,;|\/]+/u', $value) ?: [];

                return [$value, ...$parts];
            })
            ->map(fn ($value) => $this->normalize((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function containsGenericAbidjan(array $segments): bool
    {
        $haystack = implode(' ', $segments);

        foreach ((array) config('abidjan_localities.generic_aliases', []) as $alias) {
            if ($this->containsToken($haystack, $this->normalize((string) $alias))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function containsToken(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($haystack === $needle) {
            return true;
        }

        return preg_match('/(^|[^a-z0-9])'.preg_quote($needle, '/').'([^a-z0-9]|$)/u', $haystack) === 1;
    }

    private function normalizeStoredType(string $type): string
    {
        $normalized = $this->normalize($type);

        return match (true) {
            str_contains($normalized, 'carrefour') => 'carrefour',
            str_contains($normalized, 'village') => 'village',
            str_contains($normalized, 'cite') => 'cite',
            str_contains($normalized, 'sous quartier'), str_contains($normalized, 'secteur') => 'sous_quartier',
            default => 'quartier',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'sous_quartier' => 'Sous-quartier',
            'cite' => 'Cité',
            'village' => 'Village',
            'carrefour' => 'Carrefour',
            default => 'Quartier',
        };
    }

    private function typeOrder(string $type): int
    {
        return match ($type) {
            'quartier' => 1,
            'sous_quartier' => 2,
            'cite' => 3,
            'village' => 4,
            'carrefour' => 5,
            default => 9,
        };
    }
}
