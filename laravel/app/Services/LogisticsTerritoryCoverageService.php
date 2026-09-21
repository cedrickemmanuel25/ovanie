<?php

namespace App\Services;

use App\Models\AbidjanCommune;
use App\Models\LogisticsTerritoryZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsTerritoryCoverageService
{
    public function operationalZonesQuery(): Builder
    {
        $query = LogisticsTerritoryZone::query()->where('is_active', true);

        if (Schema::hasColumn('logistics_territory_zones', 'source')) {
            $query->where('source', '!=', 'demo');
        }

        return $query;
    }

    public function hasOperationalZones(): bool
    {
        if (! Schema::hasTable('logistics_territory_zones')) {
            return false;
        }

        return $this->operationalZonesQuery()->exists();
    }

    public function zoneForRequest(Request $request): ?LogisticsTerritoryZone
    {
        if ($request->input('delivery_destination_type') === 'pickup') {
            return null;
        }

        return $this->findActiveZone(
            $request->input('delivery_commune'),
            $request->input('delivery_city')
        );
    }

    public function findActiveZone(?string $commune, ?string $city = null): ?LogisticsTerritoryZone
    {
        if (! $this->hasOperationalZones()) {
            return null;
        }

        $commune = trim((string) $commune);
        $city = trim((string) $city);

        if ($commune !== '' && Schema::hasTable('logistics_territory_zone_communes')) {
            $communeModel = $this->findCommune($commune);
            if ($communeModel) {
                $zone = $this->operationalZonesQuery()
                    ->whereHas('communes', fn (Builder $query) => $query->whereKey($communeModel->id))
                    ->with('communes')
                    ->orderBy('code')
                    ->first();

                if ($zone) {
                    return $zone;
                }
            }
        }

        $needle = $this->normalize($commune !== '' ? $commune : $city);
        if ($needle === '') {
            return null;
        }

        return $this->operationalZonesQuery()
            ->get()
            ->first(function (LogisticsTerritoryZone $zone) use ($needle) {
                return collect($zone->covered_communes ?? [])
                    ->contains(fn ($name) => $this->normalize((string) $name) === $needle);
            });
    }

    public function summaryForRequest(Request $request): ?array
    {
        $zone = $this->zoneForRequest($request);
        if (! $zone) {
            return null;
        }

        return [
            'code' => $zone->code,
            'name' => $zone->name,
            'region' => $zone->region,
            'average_delay_hours' => (int) $zone->average_delay_hours,
            'allow_express' => (bool) $zone->allow_express,
        ];
    }

    public function assertRequestCovered(Request $request): void
    {
        if ($request->input('delivery_destination_type') === 'pickup' || ! $this->hasOperationalZones()) {
            return;
        }

        $zoneType = strtolower(trim((string) $request->input('delivery_zone')));
        $commune = trim((string) $request->input('delivery_commune'));
        $city = trim((string) $request->input('delivery_city'));

        if ($zoneType === 'abidjan' && $commune === '') {
            return;
        }
        if ($zoneType === 'interieur' && $city === '' && $commune === '') {
            return;
        }

        if (! $this->findActiveZone($commune, $city)) {
            $label = $commune !== '' ? $commune : $city;
            throw ValidationException::withMessages([
                $zoneType === 'abidjan' ? 'delivery_commune' : 'delivery_city' =>
                    "La destination « {$label} » n'est pas couverte par une zone de livraison OVANIE active. Vérifiez l'adresse ou contactez le support OVANIE.",
            ]);
        }
    }

    public function activeZonesForClient(): array
    {
        if (! $this->hasOperationalZones()) {
            return [];
        }

        return $this->operationalZonesQuery()
            ->with('communes:id,code,name,slug,latitude,longitude')
            ->orderBy('code')
            ->get()
            ->map(fn (LogisticsTerritoryZone $zone) => [
                'code' => $zone->code,
                'name' => $zone->name,
                'region' => $zone->region,
                'average_delay_hours' => (int) $zone->average_delay_hours,
                'allow_express' => (bool) $zone->allow_express,
                'coverage_geojson' => $zone->coverage_geojson,
                'communes' => $zone->communes->map(fn (AbidjanCommune $commune) => [
                    'id' => $commune->id,
                    'code' => $commune->code,
                    'name' => $commune->name,
                    'slug' => $commune->slug,
                    'latitude' => $commune->latitude !== null ? (float) $commune->latitude : null,
                    'longitude' => $commune->longitude !== null ? (float) $commune->longitude : null,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function findCommune(string $value): ?AbidjanCommune
    {
        if (! Schema::hasTable('abidjan_communes')) {
            return null;
        }

        $needle = $this->normalize($value);
        if ($needle === '') {
            return null;
        }

        return AbidjanCommune::query()
            ->where('is_active', true)
            ->get()
            ->first(function (AbidjanCommune $commune) use ($needle) {
                $labels = array_merge([$commune->name, $commune->slug, $commune->code], (array) $commune->aliases);
                return collect($labels)->contains(fn ($label) => $this->normalize((string) $label) === $needle);
            });
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim($value)))) ?: '';
    }
}
