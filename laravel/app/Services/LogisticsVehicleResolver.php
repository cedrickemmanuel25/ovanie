<?php

namespace App\Services;

use App\Models\DeliveryVehicleRateCard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LogisticsVehicleResolver
{
    private const VEHICLE_HIERARCHY = [
        ['code' => 'moto', 'label' => 'Moto', 'max_weight_kg' => 20.0, 'max_volume_m3' => 0.18],
        ['code' => 'tricycle', 'label' => 'Tricycle', 'max_weight_kg' => 250.0, 'max_volume_m3' => 1.5],
        ['code' => 'pickup', 'label' => 'Pickup', 'max_weight_kg' => 1000.0, 'max_volume_m3' => 7.0],
        ['code' => 'camion_3t', 'label' => 'Camion 3T', 'max_weight_kg' => 3000.0, 'max_volume_m3' => 20.0],
        ['code' => 'camion_10t', 'label' => 'Camion 10T', 'max_weight_kg' => null, 'max_volume_m3' => 60.0],
    ];

    /**
     * Résout le plus petit véhicule actif compatible avec le poids ET le volume.
     *
     * Les capacités OVANIE par défaut sont immédiatement utilisables. Une ligne
     * delivery_vehicle_rate_cards sert uniquement d'override de capacité/statut
     * pour le module Tarification. L'absence de ligne ne désactive donc plus un
     * véhicule et n'oblige plus l'équipe à saisir un tarif base/km/kg/m³.
     */
    public function resolve(float $weightKg, float $volumeM3 = 0): array
    {
        $weightKg = max(0, $weightKg);
        $volumeM3 = max(0, $volumeM3);
        $rateCards = $this->rateCards();
        $minimumIndex = $this->minimumVehicleIndexForWeight($weightKg);

        for ($index = $minimumIndex; $index < count(self::VEHICLE_HIERARCHY); $index++) {
            $vehicle = self::VEHICLE_HIERARCHY[$index];
            $rateCard = $rateCards->get($vehicle['code']);

            // Une ligne explicitement désactivée désactive le type de véhicule.
            // Sans ligne, les valeurs métier OVANIE par défaut restent actives.
            if ($rateCard && ! $rateCard->is_active) {
                continue;
            }

            if (! $this->vehicleAccepts($vehicle, $rateCard, $weightKg, $volumeM3)) {
                continue;
            }

            return [
                'code' => $vehicle['code'],
                'label' => $rateCard?->vehicle_label ?: $vehicle['label'],
                'rate_card' => $rateCard,
                'is_active' => true,
            ];
        }

        $vehicle = self::VEHICLE_HIERARCHY[array_key_last(self::VEHICLE_HIERARCHY)];

        return [
            'code' => $vehicle['code'],
            'label' => $vehicle['label'],
            'rate_card' => null,
            'is_active' => false,
        ];
    }

    /**
     * Résout un véhicule précis pour le simulateur et les alternatives.
     * Un véhicule sans configuration enregistrée utilise ses capacités OVANIE
     * par défaut ; un véhicule explicitement inactif n'est jamais proposé.
     */
    public function resolveCode(string $code, float $weightKg = 0, float $volumeM3 = 0): ?array
    {
        $rateCards = $this->rateCards();

        foreach (self::VEHICLE_HIERARCHY as $vehicle) {
            if ($vehicle['code'] !== $code) {
                continue;
            }

            $rateCard = $rateCards->get($code);
            if ($rateCard && ! $rateCard->is_active) {
                return null;
            }

            if (! $this->vehicleAccepts($vehicle, $rateCard, max(0, $weightKg), max(0, $volumeM3))) {
                return null;
            }

            return [
                'code' => $vehicle['code'],
                'label' => $rateCard?->vehicle_label ?: $vehicle['label'],
                'rate_card' => $rateCard,
                'is_active' => true,
            ];
        }

        return null;
    }

    public function vehicleForWeight(float $weightKg): array
    {
        $vehicle = self::VEHICLE_HIERARCHY[$this->minimumVehicleIndexForWeight(max(0, $weightKg))];

        return [
            'code' => $vehicle['code'],
            'label' => $vehicle['label'],
        ];
    }

    public function labelForCode(?string $code): string
    {
        foreach (self::VEHICLE_HIERARCHY as $vehicle) {
            if ($vehicle['code'] === $code) {
                return $vehicle['label'];
            }
        }

        return 'Véhicule adapté';
    }

    public function codesFrom(string $minimumCode): array
    {
        $codes = collect(self::VEHICLE_HIERARCHY)->pluck('code')->values();
        $index = $codes->search($minimumCode, true);

        if ($index === false) {
            return $codes->all();
        }

        return $codes->slice((int) $index)->values()->all();
    }

    /**
     * Catalogue utilisé par le panier. Les prix ne sont pas exposés ici :
     * seules les capacités/statuts déterminent quel véhicule peut transporter
     * la commande. Le prix est ensuite lu dans la matrice commune -> commune.
     */
    public function catalog(): array
    {
        $rateCards = $this->rateCards();

        return collect(self::VEHICLE_HIERARCHY)
            ->map(function (array $vehicle) use ($rateCards) {
                $rateCard = $rateCards->get($vehicle['code']);

                $configuredMaxWeight = $rateCard?->max_weight_kg;
                $effectiveMaxWeight = match (true) {
                    $configuredMaxWeight !== null && $vehicle['max_weight_kg'] !== null => min((float) $configuredMaxWeight, (float) $vehicle['max_weight_kg']),
                    $configuredMaxWeight !== null => (float) $configuredMaxWeight,
                    default => $vehicle['max_weight_kg'],
                };

                $configuredMaxVolume = $rateCard?->max_volume_m3;
                $effectiveMaxVolume = $configuredMaxVolume !== null && (float) $configuredMaxVolume > 0
                    ? min((float) $configuredMaxVolume, (float) $vehicle['max_volume_m3'])
                    : $vehicle['max_volume_m3'];

                return [
                    'code' => $vehicle['code'],
                    'label' => $rateCard?->vehicle_label ?: $vehicle['label'],
                    'min_weight_kg' => $rateCard?->min_weight_kg ?? 0,
                    'max_weight_kg' => $effectiveMaxWeight,
                    'max_volume_m3' => $effectiveMaxVolume,
                    'is_active' => $rateCard ? (bool) $rateCard->is_active : true,
                    'configured' => $rateCard !== null,
                ];
            })
            ->values()
            ->all();
    }

    private function minimumVehicleIndexForWeight(float $weightKg): int
    {
        foreach (self::VEHICLE_HIERARCHY as $index => $vehicle) {
            $maxWeight = $vehicle['max_weight_kg'];

            if ($maxWeight === null || $weightKg <= $maxWeight) {
                return $index;
            }
        }

        return count(self::VEHICLE_HIERARCHY) - 1;
    }

    private function rateCards(): Collection
    {
        if (! Schema::hasTable('delivery_vehicle_rate_cards')) {
            return collect();
        }

        return DeliveryVehicleRateCard::query()
            ->get()
            ->reject(fn (DeliveryVehicleRateCard $rate) => (bool) data_get((array) $rate->meta, 'pricing_ui_seed', false))
            ->keyBy('vehicle_code');
    }

    private function vehicleAccepts(
        array $vehicle,
        ?DeliveryVehicleRateCard $rateCard,
        float $weightKg,
        float $volumeM3
    ): bool {
        $maxWeight = $vehicle['max_weight_kg'];
        $maxVolume = $vehicle['max_volume_m3'];

        if ($rateCard?->max_weight_kg !== null && (float) $rateCard->max_weight_kg > 0) {
            $maxWeight = $maxWeight === null
                ? (float) $rateCard->max_weight_kg
                : min((float) $maxWeight, (float) $rateCard->max_weight_kg);
        }

        if ($rateCard?->max_volume_m3 !== null && (float) $rateCard->max_volume_m3 > 0) {
            $maxVolume = min((float) $maxVolume, (float) $rateCard->max_volume_m3);
        }

        if ($maxWeight !== null && $weightKg > (float) $maxWeight) {
            return false;
        }

        if ($maxVolume !== null && $volumeM3 > (float) $maxVolume) {
            return false;
        }

        return true;
    }
}
