<?php

namespace App\Services;

use App\Models\DeliveryCommuneRateRule;
use App\Models\DeliveryDestinationSurcharge;
use App\Models\LogisticsPricingMatrix;
use Illuminate\Support\Facades\Schema;
use App\Services\Geo\AbidjanLocationResolver;

class CommuneRelationResolver
{
    public function __construct(
        private readonly AbidjanLocationResolver $locations
    ) {}

    public function resolve(
        ?string $originCommune,
        ?string $destinationCommune,
        ?string $destinationCity = null,
        ?string $deliveryZone = null,
        ?string $vehicleCode = null
    ): array {
        $origin = $this->normalize($originCommune);
        $destination = $this->normalize($destinationCommune ?: $destinationCity);

        $relationType = $origin !== '' && $destination !== '' && $origin === $destination
            ? DeliveryCommuneRateRule::RELATION_SAME_COMMUNE
            : DeliveryCommuneRateRule::RELATION_UNKNOWN;

        // Une adresse située à Abidjan ne devient pas une livraison inter-ville
        // simplement parce que delivery_city contient « Abidjan ».
        if ($this->normalize($deliveryZone) === 'interieur') {
            $relationType = DeliveryCommuneRateRule::RELATION_INTER_CITY;
        }

        $matrixTariff = $this->matrixTariff($origin, $destination, $vehicleCode);
        $hasExplicitPair = $origin !== '' && $destination !== '';

        // Pour un vrai trajet commune -> commune, la matrice Tarification est la
        // seule source de prix fixe. Les anciennes règles OVANIE historiques ne
        // doivent jamais combler silencieusement une ligne manquante de la grille.
        $fixedVehicleRule = (! $matrixTariff && ! $hasExplicitPair)
            ? $this->fixedVehicleRule($origin, $destination, $vehicleCode, $deliveryZone)
            : null;
        $rule = $fixedVehicleRule ?: ($matrixTariff ? null : $this->matchingRule($origin, $destination, $relationType));
        $surcharge = $this->destinationSurcharge($destinationCommune, $destinationCity, $deliveryZone);
        $meta = $matrixTariff['meta'] ?? (array) ($rule?->meta ?? []);
        $isFixedVehicleTariff = $matrixTariff !== null || ((bool) ($meta['ovanie_tariff'] ?? false)
            && filled($meta['vehicle_code'] ?? null));
        $fixedTariff = $matrixTariff['price'] ?? ($isFixedVehicleTariff ? (float) ($rule?->relation_fee ?? 0) : null);

        return [
            'origin_commune' => $originCommune,
            'destination_commune' => $destinationCommune,
            'destination_city' => $destinationCity,
            'delivery_zone' => $deliveryZone,
            'relation_type' => $rule?->relation_type ?: $relationType,
            'relation_fee' => $matrixTariff ? (int) $matrixTariff['price'] : (float) ($rule?->relation_fee ?? 0),
            'relation_min_fee' => $rule?->min_fee !== null ? (float) $rule->min_fee : null,
            'destination_surcharge' => (float) ($surcharge?->surcharge_fee ?? 0),
            'rule_id' => $rule?->id,
            'surcharge_id' => $surcharge?->id,
            'vehicle_code' => $vehicleCode,
            'pricing_mode' => $isFixedVehicleTariff ? 'fixed_destination_vehicle' : 'dynamic',
            'fixed_tariff' => $fixedTariff,
            'meta' => $meta,
        ];
    }


    /**
     * La matrice créée depuis Tarification > Tarifs communes est prioritaire.
     * Elle est ainsi utilisée par le checkout web/mobile sans recopier les
     * montants dans une seconde table.
     */
    private function matrixTariff(string $origin, string $destination, ?string $vehicleCode): ?array
    {
        if ($origin === '' || $destination === '' || ! filled($vehicleCode) || ! Schema::hasTable('logistics_pricing_matrices')) {
            return null;
        }

        $matrices = LogisticsPricingMatrix::query()
            ->where('is_active', true)
            ->get()
            ->reject(fn (LogisticsPricingMatrix $row) => (bool) data_get((array) $row->meta, 'pricing_ui_seed', false));

        // Chaque sens possède son propre tarif. Cocody -> Adjamé ne sert
        // jamais automatiquement de prix pour Adjamé -> Cocody. Lorsque les
        // deux sens ont le même prix, l'interface permet de copier explicitement
        // le montant vers le trajet inverse lors de l'enregistrement en masse.
        $matrix = $matrices->first(function (LogisticsPricingMatrix $row) use ($origin, $destination) {
            return $this->normalize($row->origin_commune) === $origin
                && $this->normalize($row->destination_commune) === $destination;
        });

        if (! $matrix) {
            return null;
        }

        $price = max(0, (int) round((float) data_get((array) $matrix->vehicle_prices, $vehicleCode, 0)));
        if ($price <= 0) {
            return null;
        }

        return [
            'price' => $price,
            'meta' => [
                'pricing_source' => 'logistics_pricing_matrix',
                'matrix_id' => $matrix->id,
                'vehicle_code' => $vehicleCode,
                'estimated_delay_minutes' => $this->matrixDelayMinutes(data_get((array) $matrix->estimated_delays, $vehicleCode)),
                'reverse_fallback' => false,
            ],
        ];
    }

    /**
     * Les tarifs seedés OVANIE sont des montants fixes par commune de
     * destination ET par véhicule. Ils doivent être sélectionnés avant les
     * règles relationnelles génériques et ne doivent jamais être mélangés avec
     * le tarif d'un autre véhicule.
     */
    private function matrixDelayMinutes(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/(?:(\d+)\s*h)?\s*(?:(\d+)\s*min)?/u', $value, $matches)) {
            $minutes = ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);
            return $minutes > 0 ? $minutes : null;
        }

        return null;
    }

    private function fixedVehicleRule(string $origin, string $destination, ?string $vehicleCode, ?string $deliveryZone): ?DeliveryCommuneRateRule
    {
        if ($destination === '' || ! filled($vehicleCode)) {
            return null;
        }

        $rules = DeliveryCommuneRateRule::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (DeliveryCommuneRateRule $rule) use ($origin, $vehicleCode) {
                $meta = (array) ($rule->meta ?? []);

                if (! ($meta['ovanie_tariff'] ?? false)) {
                    return false;
                }

                if (($meta['vehicle_code'] ?? null) !== $vehicleCode) {
                    return false;
                }

                $ruleOrigin = $this->normalize($rule->origin_commune);

                return $ruleOrigin === '' || $ruleOrigin === $origin;
            });

        $exact = $rules
            ->filter(fn (DeliveryCommuneRateRule $rule) => $this->normalize($rule->destination_commune) === $destination)
            ->sortByDesc(fn (DeliveryCommuneRateRule $rule) => $this->normalize($rule->origin_commune) === $origin ? 2 : 1)
            ->first();

        if ($exact) {
            return $exact;
        }

        // Dernier filet de sécurité : si le GPS confirme Abidjan mais ne fournit
        // pas la commune, la livraison reste disponible uniquement lorsque toutes
        // les communes configurées possèdent un tarif actif pour ce véhicule.
        if ($this->normalize($deliveryZone) !== 'abidjan' || ! $this->locations->isGenericAbidjan($destination)) {
            return null;
        }

        $expected = collect(config('client_space.abidjan_communes', []))
            ->map(fn ($commune) => $this->normalize((string) $commune))
            ->filter()
            ->unique()
            ->values();
        $covered = $rules
            ->map(fn (DeliveryCommuneRateRule $rule) => $this->normalize($rule->destination_commune))
            ->filter()
            ->unique()
            ->values();

        if ($expected->diff($covered)->isNotEmpty()) {
            return null;
        }

        $fallback = $rules->sortByDesc('relation_fee')->first();
        if (! $fallback) {
            return null;
        }

        $fallback = clone $fallback;
        $fallback->meta = array_merge((array) $fallback->meta, [
            'coverage_fallback' => true,
            'requested_destination' => 'abidjan',
            'fallback_strategy' => 'highest_configured_commune_tariff',
        ]);

        return $fallback;
    }

    private function matchingRule(string $origin, string $destination, string $relationType): ?DeliveryCommuneRateRule
    {
        return DeliveryCommuneRateRule::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (DeliveryCommuneRateRule $rule) use ($origin, $destination, $relationType) {
                $meta = (array) ($rule->meta ?? []);

                // Les grilles fixes par véhicule sont traitées séparément.
                if ($meta['ovanie_tariff'] ?? false) {
                    return false;
                }

                $ruleOrigin = $this->normalize($rule->origin_commune);
                $ruleDestination = $this->normalize($rule->destination_commune);
                $originMatches = $ruleOrigin === '' || $ruleOrigin === $origin;
                $destinationMatches = $ruleDestination === '' || $ruleDestination === $destination;
                $relationMatches = in_array($rule->relation_type, [
                    $relationType,
                    DeliveryCommuneRateRule::RELATION_UNKNOWN,
                ], true);

                // Une règle explicite origine → destination reste prioritaire,
                // même lorsque son type a été saisi comme voisine ou distante.
                $explicitPair = $ruleOrigin !== ''
                    && $ruleDestination !== ''
                    && $ruleOrigin === $origin
                    && $ruleDestination === $destination;

                return $originMatches && $destinationMatches && ($relationMatches || $explicitPair);
            })
            ->sortByDesc(function (DeliveryCommuneRateRule $rule) use ($origin, $destination, $relationType) {
                $score = 0;

                if ($this->normalize($rule->origin_commune) === $origin) {
                    $score += 4;
                }

                if ($this->normalize($rule->destination_commune) === $destination) {
                    $score += 4;
                }

                if ($rule->relation_type === $relationType) {
                    $score += 2;
                }

                return $score;
            })
            ->first();
    }

    private function destinationSurcharge(?string $commune, ?string $city, ?string $deliveryZone): ?DeliveryDestinationSurcharge
    {
        $commune = $this->normalize($commune);
        $city = $this->normalize($city);
        $deliveryZone = $this->normalize($deliveryZone);

        return DeliveryDestinationSurcharge::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (DeliveryDestinationSurcharge $surcharge) use ($commune, $city, $deliveryZone) {
                $zoneRule = $this->normalize($surcharge->delivery_zone);
                $cityRule = $this->normalize($surcharge->city);
                $communeRule = $this->normalize($surcharge->commune);

                return ($zoneRule === '' || $zoneRule === $deliveryZone)
                    && ($cityRule === '' || $cityRule === $city)
                    && ($communeRule === '' || $communeRule === $commune);
            })
            ->sortByDesc(function (DeliveryDestinationSurcharge $surcharge) {
                return (filled($surcharge->delivery_zone) ? 1 : 0)
                    + (filled($surcharge->city) ? 2 : 0)
                    + (filled($surcharge->commune) ? 4 : 0);
            })
            ->first();
    }

    private function normalize(?string $value): string
    {
        return $this->locations->communeSlug($value);
    }
}
