<?php

namespace App\Services;

use App\Models\AbidjanCommune;
use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingGrid;
use App\Models\LogisticsPricingMatrix;
use App\Models\LogisticsPricingSupplement;
use App\Models\LogisticsTerritoryZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\Schema;

class LogisticsPricingWorkspaceService
{
    public const VEHICLES = [
        'moto' => ['label' => 'Moto', 'subtitle' => 'Livraison légère', 'image' => 'vendor/delivery/vehicles/moto.png', 'capacity' => '0 – 20 kg', 'max_weight_kg' => 20, 'max_volume_m3' => 0.18],
        'tricycle' => ['label' => 'Tricycle', 'subtitle' => 'Petites charges', 'image' => 'vendor/delivery/vehicles/tricycle.png', 'capacity' => '0 – 250 kg', 'max_weight_kg' => 250, 'max_volume_m3' => 1.5],
        'pickup' => ['label' => 'Pickup', 'subtitle' => 'Charges moyennes', 'image' => 'vendor/delivery/vehicles/pickup.png', 'capacity' => '0 – 1 000 kg', 'max_weight_kg' => 1000, 'max_volume_m3' => 7],
        'camion_3t' => ['label' => 'Camion 3T', 'subtitle' => 'Fret chantier', 'image' => 'vendor/delivery/vehicles/camion-3t.png', 'capacity' => '0 – 3 000 kg', 'max_weight_kg' => 3000, 'max_volume_m3' => 20],
        'camion_10t' => ['label' => 'Camion 10T', 'subtitle' => 'Charges lourdes', 'image' => 'vendor/delivery/vehicles/camion-10t.png', 'capacity' => '> 3 000 kg', 'max_weight_kg' => 10000, 'max_volume_m3' => 60],
    ];

    public function rateCards(): Collection
    {
        if (! Schema::hasTable('delivery_vehicle_rate_cards')) {
            return collect();
        }

        return DeliveryVehicleRateCard::query()->orderBy('id')->get()
            ->reject(fn (DeliveryVehicleRateCard $rate) => $this->isUiDemo($rate->meta))
            ->sortBy(fn (DeliveryVehicleRateCard $rate) => array_search($rate->vehicle_code, array_keys(self::VEHICLES), true) ?: 0)
            ->values();
    }

    /**
     * Les cartes de l'accueil Tarification utilisent la même source que le
     * checkout : delivery_vehicle_rate_cards. La table de grilles conserve le
     * nom, la description et la zone quand l'opérateur les a renseignés.
     */
    public function gridRows(): Collection
    {
        $grids = Schema::hasTable('logistics_pricing_grids')
            ? LogisticsPricingGrid::query()->orderByDesc('updated_at')->get()
                ->reject(fn (LogisticsPricingGrid $grid) => $this->isUiDemo($grid->meta))
                ->keyBy('vehicle_code')
            : collect();

        return $this->rateCards()->map(function (DeliveryVehicleRateCard $rate) use ($grids) {
            $grid = $grids->get($rate->vehicle_code);
            $vehicle = self::VEHICLES[$rate->vehicle_code] ?? ['label' => $rate->vehicle_label, 'capacity' => '—'];

            return new Fluent([
                'id' => $grid?->id,
                'name' => $grid?->name ?: 'Tarif '.$rate->vehicle_label,
                'vehicle_code' => $rate->vehicle_code,
                'vehicle_label' => $rate->vehicle_label ?: $vehicle['label'],
                'region' => $grid?->region ?: $this->regions()->first() ?: '—',
                'zone_count' => $this->zones()->count(),
                'description' => $grid?->description,
                'surcharges' => ['fragile' => $rate->fragile_fee, 'handling' => $rate->handling_fee, 'unloading' => $rate->unloading_fee, 'urgent' => $rate->urgent_fee],
                'base_fee' => (float) $rate->base_fee,
                'price_per_km' => (float) $rate->price_per_km,
                'price_per_kg' => (float) $rate->price_per_kg,
                'price_per_m3' => (float) $rate->price_per_m3,
                'max_weight_kg' => $rate->max_weight_kg,
                'max_volume_m3' => $rate->max_volume_m3,
                'is_active' => (bool) $rate->is_active,
                'updated_at' => $rate->updated_at,
            ]);
        })->values();
    }

    public function matrices(): Collection
    {
        if (! Schema::hasTable('logistics_pricing_matrices')) {
            return collect();
        }

        return LogisticsPricingMatrix::query()->orderByDesc('updated_at')->get()
            ->reject(fn (LogisticsPricingMatrix $matrix) => $this->isUiDemo($matrix->meta))
            ->values();
    }

    public function supplements(): Collection
    {
        if (! Schema::hasTable('logistics_pricing_supplements')) {
            return collect();
        }

        return LogisticsPricingSupplement::query()->orderByDesc('updated_at')->get()
            ->reject(fn (LogisticsPricingSupplement $supplement) => $this->isUiDemo($supplement->meta))
            ->values();
    }

    public function zones(bool $activeOnly = true): Collection
    {
        if (! Schema::hasTable('logistics_territory_zones')) {
            return collect();
        }

        $query = LogisticsTerritoryZone::query()->orderBy('name');
        if (Schema::hasTable('logistics_territory_zone_communes') && Schema::hasTable('abidjan_communes')) {
            $query->with('communes');
        }
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->reject(function (LogisticsTerritoryZone $zone) {
            return strtolower((string) $zone->source) === 'demo'
                || data_get($zone->meta, 'demo_seeder')
                || data_get($zone->meta, 'dashboard.active_zones');
        })->values();
    }

    public function coveredCommunes(): Collection
    {
        if (! Schema::hasTable('abidjan_communes')) {
            return collect();
        }

        $zones = $this->zones();
        if ($zones->isEmpty()) {
            return collect();
        }

        if (Schema::hasTable('logistics_territory_zone_communes')) {
            $ids = $zones->flatMap(fn (LogisticsTerritoryZone $zone) => $zone->communes->pluck('id'))
                ->filter()->unique()->values();

            if ($ids->isNotEmpty()) {
                return AbidjanCommune::query()
                    ->whereIn('id', $ids)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }

        // Compatibilité avec les zones créées avant l'introduction de la table
        // pivot : on lit uniquement les communes explicitement enregistrées sur
        // les zones opérationnelles, sans injecter de commune par défaut.
        $names = $zones->flatMap(fn (LogisticsTerritoryZone $zone) => collect((array) $zone->covered_communes))
            ->map(fn ($name) => trim((string) $name))->filter()->unique()->values();

        if ($names->isEmpty()) {
            return collect();
        }

        return AbidjanCommune::query()
            ->whereIn('name', $names)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function regions(): Collection
    {
        return $this->zones()->pluck('region')->filter()->unique()->sort()->values();
    }

    public function activeRatePercent(): int
    {
        $rates = $this->rateCards();
        if ($rates->isEmpty()) return 0;

        return (int) round($rates->where('is_active', true)->count() / $rates->count() * 100);
    }

    public function averageMatrixDelayMinutes(): ?int
    {
        $minutes = $this->matrices()->where('is_active', true)->flatMap(function (LogisticsPricingMatrix $matrix) {
            return collect((array) $matrix->estimated_delays)->map(fn ($value) => $this->delayToMinutes($value))->filter();
        });

        return $minutes->isNotEmpty() ? (int) round($minutes->avg()) : null;
    }

    public function averageSupplementPercent(): ?float
    {
        $percentages = $this->supplements()->where('is_active', true)->where('calculation_type', 'percentage')->pluck('amount');
        return $percentages->isNotEmpty() ? round((float) $percentages->avg(), 1) : null;
    }

    public function isUiDemo(mixed $meta): bool
    {
        return (bool) data_get((array) $meta, 'pricing_ui_seed', false);
    }

    private function delayToMinutes(mixed $value): ?int
    {
        if (is_numeric($value)) return (int) $value;
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') return null;
        if (preg_match('/(?:(\d+)\s*h)?\s*(?:(\d+)\s*min)?/u', $value, $m)) {
            $hours = (int) ($m[1] ?? 0);
            $minutes = (int) ($m[2] ?? 0);
            $total = $hours * 60 + $minutes;
            return $total > 0 ? $total : null;
        }
        return null;
    }
}
