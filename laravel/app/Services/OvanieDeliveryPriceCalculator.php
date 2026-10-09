<?php

namespace App\Services;

use App\Models\DeliveryServiceRate;
use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingSupplement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OvanieDeliveryPriceCalculator
{
    public function __construct(
        private readonly LogisticsVehicleResolver $vehicleResolver,
    ) {}

    /**
     * Prix d'une livraison OVANIE (formule unique, sans plancher ni plafond) :
     *
     *   livraison = tarif de base
     *             + distance (km) × prix/km
     *             + poids (kg)    × prix/kg
     *             + volume (m³)   × prix/m³
     *             + frais éventuels (suppléments automatiques)
     *
     * Les quatre tarifs sont ceux du véhicule choisi selon le poids et le
     * volume (Tarification > Véhicules & tarifs).
     */
    public function calculate(Collection $items, array $route, array $destination, ?DeliveryServiceRate $legacyRate = null): array
    {
        $weightKg = $this->weightKg($items);
        $volumeM3 = $this->volumeM3($items);

        $destination['fragile'] = (bool) ($destination['fragile'] ?? false)
            || $items->contains(fn ($item) => (bool) ($item->product?->fragile ?? false));
        $destination['unloading'] = (bool) ($destination['unloading'] ?? false)
            || $items->contains(fn ($item) => (bool) ($item->product?->requires_unloading ?? false));
        $destination['handling'] = (bool) ($destination['handling'] ?? false)
            || $items->contains(fn ($item) => filled($item->product?->handling_options));
        $destination['package_count'] = max(1, (int) $items->sum(fn ($item) => (int) $item->quantity));

        return $this->calculateMetrics($weightKg, $volumeM3, $route, $destination, $legacyRate, $items);
    }

    /**
     * Calcule un tarif à partir de mesures déjà connues. Cette méthode est
     * utilisée par le simulateur Logistique afin qu'il exécute exactement le
     * même moteur tarifaire que le checkout Web / API mobile.
     */
    public function calculateMetrics(
        float $weightKg,
        float $volumeM3,
        array $route,
        array $destination,
        ?DeliveryServiceRate $legacyRate = null,
        ?Collection $items = null,
        ?string $forcedVehicleCode = null,
    ): array {
        $items ??= collect();
        $vehicle = $forcedVehicleCode
            ? $this->vehicleResolver->resolveCode($forcedVehicleCode, $weightKg, $volumeM3)
            : $this->vehicleResolver->resolve($weightKg, $volumeM3);

        if (! $vehicle || ! ($vehicle['is_active'] ?? true)) {
            return [
                'price' => 0.0,
                'vehicle_code' => $forcedVehicleCode,
                'vehicle_label' => $this->vehicleResolver->labelForCode($forcedVehicleCode),
                'weight_kg' => round($weightKg, 3),
                'volume_m3' => round($volumeM3, 4),
                'components' => [],
                'rate_source' => 'unavailable_vehicle',
            ];
        }

        $rate = $vehicle['rate_card'] ?? null;
        if (! self::hasFormulaRates($rate)) {
            return $this->result(0.0, $vehicle, $weightKg, $volumeM3, $route, [], $rate?->id, 'missing_vehicle_rate');
        }

        $distanceKm = max(0.0, (float) ($route['distance_km'] ?? 0));
        $components = [
            'base_fee' => (float) $rate->base_fee,
            'distance_fee' => $distanceKm * (float) $rate->price_per_km,
            'weight_fee' => $weightKg * (float) $rate->price_per_kg,
            'volume_fee' => $volumeM3 * (float) $rate->price_per_m3,
        ];
        $price = array_sum($components);

        [$supplementTotal, $supplementComponents] = $this->customSupplements($price, [
            'fragile' => (bool) ($destination['fragile'] ?? false),
            'handling' => (bool) ($destination['handling'] ?? false),
            'unloading' => (bool) ($destination['unloading'] ?? false),
            'urgent' => (bool) ($destination['urgent'] ?? false),
            'weight_kg' => $weightKg,
            'volume_m3' => $volumeM3,
            'package_count' => max(1, (int) ($destination['package_count'] ?? 1)),
            'traffic_delay_minutes' => (int) ($route['traffic_delay_minutes'] ?? 0),
        ], $items);

        return $this->result(
            (float) round($price + $supplementTotal),
            $vehicle,
            $weightKg,
            $volumeM3,
            $route,
            array_merge($components, $supplementComponents),
            $rate->id,
            'vehicle_rate_card',
        );
    }

    /**
     * Un véhicule est tarifé dès qu'au moins un des quatre tarifs de la
     * formule est renseigné (un tarif de base seul est accepté).
     */
    public static function hasFormulaRates(?DeliveryVehicleRateCard $rate): bool
    {
        if (! $rate) {
            return false;
        }

        return (float) $rate->base_fee + (float) $rate->price_per_km
            + (float) $rate->price_per_kg + (float) $rate->price_per_m3 > 0;
    }

    public function weightKg(Collection $items): float
    {
        return (float) $items->sum(fn ($item) => (float) ($item->product?->weight_kg ?? $item->product?->weight ?? 0) * (int) $item->quantity);
    }

    public function volumeM3(Collection $items): float
    {
        return (float) $items->sum(function ($item) {
            $product = $item->product;
            $volume = (float) ($product?->volume_m3 ?? $product?->volume ?? 0);

            if ($volume <= 0 && $product?->length_cm && $product?->width_cm && $product?->height_cm) {
                $volume = ((float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm) / 1000000;
            }

            return $volume * (int) $item->quantity;
        });
    }


    /**
     * Applique uniquement les suppléments réellement créés par l'équipe
     * logistique. Les anciennes lignes de démonstration sont ignorées.
     */
    private function customSupplements(float $subtotal, array $context, Collection $items): array
    {
        if (! Schema::hasTable('logistics_pricing_supplements')) {
            return [0.0, []];
        }

        $candidates = collect();
        $rows = LogisticsPricingSupplement::query()
            ->where('is_active', true)
            ->where('automatic', true)
            ->get()
            ->reject(fn (LogisticsPricingSupplement $row) => (bool) data_get((array) $row->meta, 'pricing_ui_seed', false));

        foreach ($rows as $row) {
            $conditions = collect((array) $row->conditions)->filter(fn ($value) => (bool) $value)->keys();
            if ($conditions->isEmpty()) {
                continue;
            }

            $matches = $conditions->contains(fn ($condition) => match ($condition) {
                'express', 'urgent' => (bool) ($context['urgent'] ?? false),
                'fragile' => (bool) ($context['fragile'] ?? false),
                'handling' => (bool) ($context['handling'] ?? false),
                'unloading' => (bool) ($context['unloading'] ?? false),
                'bulky' => (float) ($context['weight_kg'] ?? 0) > 30 || (float) ($context['volume_m3'] ?? 0) > .5,
                'traffic' => (int) ($context['traffic_delay_minutes'] ?? 0) > 0,
                default => false,
            });

            if (! $matches || ((float) $row->minimum_threshold > 0 && $subtotal < (float) $row->minimum_threshold)) {
                continue;
            }

            $scope = mb_strtolower(trim((string) $row->scope));
            $multiplier = match ($scope) {
                'par colis' => max(1, (int) ($context['package_count'] ?? $items->sum(fn ($item) => (int) $item->quantity))),
                'par passage' => max(1, (int) ($context['passage_count'] ?? 1)),
                default => 1,
            };

            $amount = $row->calculation_type === 'percentage'
                ? $subtotal * ((float) $row->amount / 100)
                : (float) $row->amount * $multiplier;
            if ($amount <= 0) {
                continue;
            }

            $candidates->push(['row' => $row, 'amount' => $amount]);
        }

        $exclusive = $candidates
            ->filter(fn (array $candidate) => ! (bool) $candidate['row']->compatible_with_others)
            ->sortByDesc('amount')
            ->first();
        if ($exclusive) {
            $candidates = collect([$exclusive]);
        }

        $total = (float) $candidates->sum('amount');
        $components = [];
        foreach ($candidates as $candidate) {
            $components['supplement_'.$candidate['row']->code] = (float) $candidate['amount'];
        }

        return [$total, $components];
    }

    private function result(
        float $price,
        array $vehicle,
        float $weightKg,
        float $volumeM3,
        array $route,
        array $components,
        ?int $rateCardId,
        string $rateSource
    ): array {
        return [
            'price' => $price,
            'vehicle_code' => $vehicle['code'],
            'vehicle_label' => $vehicle['label'],
            'weight_kg' => round($weightKg, 3),
            'volume_m3' => round($volumeM3, 4),
            'distance_km' => (float) ($route['distance_km'] ?? 0),
            'duration_minutes' => $route['duration_minutes'] ?? null,
            'traffic_delay_minutes' => $route['traffic_delay_minutes'] ?? null,
            'no_traffic_duration_minutes' => $route['no_traffic_duration_minutes'] ?? null,
            'route_geometry' => $route['route_geometry'] ?? null,
            'routing_provider' => $route['provider'] ?? null,
            'route_source' => $route['source'] ?? null,
            'components' => $components,
            'rate_card_id' => $rateCardId,
            'rate_source' => $rateSource,
        ];
    }

    private function margin(DeliveryVehicleRateCard $rate, float $subtotal): float
    {
        return match ($rate->margin_type) {
            'percent', 'percentage' => $subtotal * ((float) $rate->margin_value / 100),
            'fixed' => (float) $rate->margin_value,
            default => 0.0,
        };
    }

    private function legacyRateCard(array $vehicle, ?DeliveryServiceRate $legacyRate): DeliveryVehicleRateCard
    {
        return new DeliveryVehicleRateCard([
            'vehicle_code' => $vehicle['code'],
            'vehicle_label' => $vehicle['label'],
            'base_fee' => (float) ($legacyRate?->base_fee ?? 0),
            'price_per_km' => (float) ($legacyRate?->price_per_km ?? 0),
            'price_per_kg' => (float) ($legacyRate?->price_per_kg ?? 0),
            'price_per_m3' => (float) ($legacyRate?->price_per_m3 ?? 0),
            'handling_fee' => 0,
            'unloading_fee' => 0,
            'fragile_fee' => 0,
            'urgent_fee' => 0,
            'traffic_surcharge_fee' => 0,
            'intra_commune_min_fee' => 0,
            'min_fee' => $legacyRate?->min_fee,
            'max_fee' => $legacyRate?->max_fee,
            'margin_type' => 'none',
            'margin_value' => 0,
        ]);
    }
}
