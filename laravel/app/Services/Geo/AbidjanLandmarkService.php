<?php

namespace App\Services\Geo;

use App\Models\AbidjanCommune;
use App\Models\AbidjanLandmark;
use App\Models\AbidjanQuarter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AbidjanLandmarkService
{
    public function __construct(
        private readonly AbidjanLocalityRegistry $localities,
        private readonly GeocodingService $geocoding,
    ) {}

    public function search(?string $communeName, ?string $quarterName, ?string $query = null, int $limit = 30): array
    {
        $limit = max(1, min($limit, 50));
        $communeName = $this->localities->canonicalCommune($communeName);
        $quarterName = trim((string) $quarterName);
        $query = trim((string) $query);

        if (! $communeName || $quarterName === '') {
            return [];
        }

        [$commune, $quarter] = $this->databaseLocation($communeName, $quarterName);
        $results = collect($this->storedLandmarks($commune, $quarter, $query, $limit));

        if ($query !== '' && mb_strlen($query) >= 2 && $results->count() < $limit) {
            $remote = $this->remoteLandmarks(
                $communeName,
                $quarterName,
                $query,
                $limit - $results->count(),
                $commune,
                $quarter,
            );

            $results = $results->concat($remote);
        }

        return $results
            ->unique(fn (array $item) => Str::lower(Str::ascii($item['name'])) . '|' . round((float) $item['latitude'], 5) . '|' . round((float) $item['longitude'], 5))
            ->take($limit)
            ->values()
            ->all();
    }

    private function databaseLocation(string $communeName, string $quarterName): array
    {
        if (! Schema::hasTable('abidjan_communes') || ! Schema::hasTable('abidjan_quarters')) {
            return [null, null];
        }

        try {
            $commune = AbidjanCommune::query()
                ->where('is_active', true)
                ->where('name', $communeName)
                ->first();

            if (! $commune) {
                return [null, null];
            }

            $quarter = AbidjanQuarter::query()
                ->where('commune_id', $commune->id)
                ->where('is_active', true)
                ->where(function ($builder) use ($quarterName) {
                    $builder->where('name', $quarterName)
                        ->orWhere('slug', Str::slug($quarterName));
                })
                ->first();

            return [$commune, $quarter];
        } catch (Throwable) {
            return [null, null];
        }
    }

    private function storedLandmarks(?AbidjanCommune $commune, ?AbidjanQuarter $quarter, string $query, int $limit): array
    {
        if (! $commune || ! $quarter || ! Schema::hasTable('abidjan_landmarks')) {
            return [];
        }

        try {
            return AbidjanLandmark::query()
                ->where('commune_id', $commune->id)
                ->where('quarter_id', $quarter->id)
                ->where('is_active', true)
                ->when($query !== '', function ($builder) use ($query) {
                    $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
                    $builder->where(function ($nested) use ($needle) {
                        $nested->where('name', 'like', $needle)
                            ->orWhere('address', 'like', $needle)
                            ->orWhere('category', 'like', $needle);
                    });
                })
                ->orderByDesc('is_verified')
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (AbidjanLandmark $landmark) => $this->toPayload($landmark))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function remoteLandmarks(
        string $communeName,
        string $quarterName,
        string $query,
        int $limit,
        ?AbidjanCommune $commune,
        ?AbidjanQuarter $quarter,
    ): array {
        $searchQuery = collect([$query, $quarterName, $communeName, 'Abidjan', "Côte d'Ivoire"])
            ->filter()
            ->unique(fn ($value) => Str::lower((string) $value))
            ->implode(', ');

        try {
            $rows = $this->geocoding->search($searchQuery, config('geo.country_code', 'CI'));
        } catch (Throwable) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (! $this->insideConfiguredCountry($row)) {
                continue;
            }

            $resolved = (array) ($row['resolved_location'] ?? []);
            $resolvedCommune = $this->localities->canonicalCommune($resolved['commune'] ?? null);
            if ($resolvedCommune && $resolvedCommune !== $communeName) {
                continue;
            }

            $name = $this->landmarkName($row);
            if ($name === '') {
                continue;
            }

            $landmark = $this->persistRemoteLandmark($row, $name, $commune, $quarter);
            $items[] = $landmark ? $this->toPayload($landmark) : $this->rowPayload($row, $name);

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function persistRemoteLandmark(
        array $row,
        string $name,
        ?AbidjanCommune $commune,
        ?AbidjanQuarter $quarter,
    ): ?AbidjanLandmark {
        if (! $commune || ! $quarter || ! Schema::hasTable('abidjan_landmarks')) {
            return null;
        }

        $source = Str::lower((string) ($row['source'] ?? 'geocoding'));
        $reference = $this->sourceReference($row);

        try {
            return AbidjanLandmark::query()->updateOrCreate(
                [
                    'quarter_id' => $quarter->id,
                    'slug' => Str::slug($name),
                    'source' => $source,
                ],
                [
                    'commune_id' => $commune->id,
                    'name' => $name,
                    'category' => $this->category($row),
                    'address' => trim((string) ($row['display_name'] ?? '')) ?: null,
                    'latitude' => round((float) $row['latitude'], 7),
                    'longitude' => round((float) $row['longitude'], 7),
                    'source_reference' => $reference,
                    'is_verified' => false,
                    'is_active' => true,
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function toPayload(AbidjanLandmark $landmark): array
    {
        return [
            'id' => $landmark->id,
            'name' => $landmark->name,
            'label' => $landmark->address ?: $landmark->name,
            'category' => $landmark->category,
            'latitude' => (float) $landmark->latitude,
            'longitude' => (float) $landmark->longitude,
            'source' => $landmark->source,
            'verified' => (bool) $landmark->is_verified,
        ];
    }

    private function rowPayload(array $row, string $name): array
    {
        return [
            'id' => null,
            'name' => $name,
            'label' => trim((string) ($row['display_name'] ?? '')) ?: $name,
            'category' => $this->category($row),
            'latitude' => round((float) $row['latitude'], 7),
            'longitude' => round((float) $row['longitude'], 7),
            'source' => Str::lower((string) ($row['source'] ?? 'geocoding')),
            'verified' => false,
        ];
    }

    private function landmarkName(array $row): string
    {
        $address = (array) ($row['address'] ?? []);
        foreach (['amenity', 'shop', 'tourism', 'building', 'office', 'leisure', 'road'] as $key) {
            $candidate = trim((string) ($address[$key] ?? ''));
            if ($candidate !== '') {
                return Str::limit($candidate, 180, '');
            }
        }

        $displayName = trim((string) ($row['display_name'] ?? ''));
        $first = trim((string) (explode(',', $displayName)[0] ?? ''));

        return Str::limit($first, 180, '');
    }

    private function category(array $row): ?string
    {
        $raw = (array) ($row['raw'] ?? []);
        $address = (array) ($row['address'] ?? []);

        return data_get($raw, 'properties.feature_type')
            ?? $raw['type']
            ?? $raw['category']
            ?? collect(['amenity', 'shop', 'tourism', 'building', 'office', 'leisure'])
                ->first(fn (string $key) => filled($address[$key] ?? null));
    }

    private function sourceReference(array $row): ?string
    {
        $raw = (array) ($row['raw'] ?? []);

        return isset($raw['id'])
            ? (string) $raw['id']
            : (isset($raw['place_id']) ? (string) $raw['place_id'] : null);
    }

    private function insideConfiguredCountry(array $row): bool
    {
        if (! is_numeric($row['latitude'] ?? null) || ! is_numeric($row['longitude'] ?? null)) {
            return false;
        }

        $bounds = (array) config('geo.country_bounds', []);
        $lat = (float) $row['latitude'];
        $lng = (float) $row['longitude'];

        return $lat >= (float) ($bounds['min_lat'] ?? -90)
            && $lat <= (float) ($bounds['max_lat'] ?? 90)
            && $lng >= (float) ($bounds['min_lng'] ?? -180)
            && $lng <= (float) ($bounds['max_lng'] ?? 180);
    }
}
