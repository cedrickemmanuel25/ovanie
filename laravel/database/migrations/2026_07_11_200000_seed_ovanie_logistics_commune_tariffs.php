<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifs fixes OVANIE Logistique par commune de livraison et type de véhicule.
 *
 * Ces prix sont des tarifs recommandés tout-compris (FCFA, TTC) applicables
 * indépendamment de la commune d'origine (expéditeur).
 *
 * Véhicules : Moto | Tricycle | Pickup | Camion 3T | Camion 10T
 */
return new class extends Migration
{
    /** Clé meta utilisée pour stocker le tarif par véhicule dans delivery_commune_rate_rules */
    private const META_KEY = 'ovanie_tariff';

    /** Correspondance vehicle_code → colonne du tableau tarifaire */
    private array $vehicles = [
        'moto'       => ['label' => 'Moto',       'min_kg' => 0.0,    'max_kg' => 20.0,   'volume_m3' => 0.18],
        'tricycle'   => ['label' => 'Tricycle',    'min_kg' => 20.0,   'max_kg' => 250.0,  'volume_m3' => 1.5],
        'pickup'     => ['label' => 'Pickup',      'min_kg' => 250.0,  'max_kg' => 1000.0, 'volume_m3' => 7.0],
        'camion_3t'  => ['label' => 'Camion 3T',  'min_kg' => 1000.0, 'max_kg' => 3000.0, 'volume_m3' => 20.0],
        'camion_10t' => ['label' => 'Camion 10T', 'min_kg' => 3000.0, 'max_kg' => null,   'volume_m3' => 60.0],
    ];

    /**
     * Tarifs recommandés par commune (FCFA).
     * Format : 'commune_slug' => [moto, tricycle, pickup, camion_3t, camion_10t]
     */
    private array $tariffs = [
        'abobo'       => [3000, 8500,  18000, 32000, 58000],
        'adjame'      => [1800, 5500,  12000, 22000, 40000],
        'anyama'      => [4000, 11000, 24000, 42000, 75000],
        'attecoube'  => [2200, 6500,  14000, 25000, 45000],
        'bingerville' => [3200, 9000,  20000, 35000, 62000],
        'cocody'      => [2200, 7000,  15000, 27000, 48000],
        'koumassi'    => [1800, 5500,  12000, 22000, 40000],
        'marcory'     => [1500, 4500,  10000, 18000, 35000],
        'plateau'     => [1500, 5000,  11000, 20000, 38000],
        'port-bouet'  => [2200, 6500,  14000, 25000, 46000],
        'songon'      => [4500, 12000, 26000, 45000, 80000],
        'treichville' => [1500, 4500,  10000, 18000, 35000],
        'yopougon'    => [2800, 8000,  17000, 30000, 55000],
    ];

    /** Labels lisibles par commune */
    private array $communeLabels = [
        'abobo'       => 'Abobo',
        'adjame'      => 'Adjamé',
        'anyama'      => 'Anyama',
        'attecoube'  => 'Attécoubé',
        'bingerville' => 'Bingerville',
        'cocody'      => 'Cocody',
        'koumassi'    => 'Koumassi',
        'marcory'     => 'Marcory',
        'plateau'     => 'Plateau',
        'port-bouet'  => 'Port-Bouët',
        'songon'      => 'Songon',
        'treichville' => 'Treichville',
        'yopougon'    => 'Yopougon',
    ];

    public function up(): void
    {
        $now = now();

        // ---------------------------------------------------------------
        // 1. Upsert delivery_vehicle_rate_cards : un enreg. par véhicule
        // ---------------------------------------------------------------
        if (Schema::hasTable('delivery_vehicle_rate_cards')) {
            foreach ($this->vehicles as $code => $v) {
                $existing = DB::table('delivery_vehicle_rate_cards')
                    ->where('vehicle_code', $code)
                    ->first();

                if ($existing) {
                    // Mise à jour des capacités seulement si non encore définies
                    DB::table('delivery_vehicle_rate_cards')
                        ->where('vehicle_code', $code)
                        ->update([
                            'vehicle_label'  => $v['label'],
                            'min_weight_kg'  => $v['min_kg'],
                            'max_weight_kg'  => $v['max_kg'],
                            'max_volume_m3'  => $v['volume_m3'],
                            'is_active'      => true,
                            'updated_at'     => $now,
                        ]);
                } else {
                    DB::table('delivery_vehicle_rate_cards')->insert([
                        'vehicle_code'   => $code,
                        'vehicle_label'  => $v['label'],
                        'min_weight_kg'  => $v['min_kg'],
                        'max_weight_kg'  => $v['max_kg'],
                        'max_volume_m3'  => $v['volume_m3'],
                        'base_fee'       => 0,
                        'price_per_km'   => 0,
                        'price_per_kg'   => 0,
                        'price_per_m3'   => 0,
                        'handling_fee'   => 0,
                        'unloading_fee'  => 0,
                        'fragile_fee'    => 0,
                        'urgent_fee'     => 0,
                        'traffic_surcharge_fee' => 0,
                        'intra_commune_min_fee' => 0,
                        'margin_type'    => 'none',
                        'margin_value'   => 0,
                        'is_active'      => true,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                }
            }
        }

        // ---------------------------------------------------------------
        // 2. Insertion des tarifs dans delivery_commune_rate_rules
        //    Un enregistrement par (commune_destination × vehicle_code).
        //    origin_commune = NULL  → tarif valable depuis n'importe quelle
        //    commune expéditeur (tarif destination-only).
        // ---------------------------------------------------------------
        if (! Schema::hasTable('delivery_commune_rate_rules')) {
            return;
        }

        $vehicleCodes = array_keys($this->vehicles);

        foreach ($this->tariffs as $communeSlug => $prices) {
            foreach ($vehicleCodes as $idx => $vehicleCode) {
                $tarif = $prices[$idx];

                // Suppression d'un éventuel enregistrement existant pour ce couple
                DB::table('delivery_commune_rate_rules')
                    ->whereNull('origin_commune')
                    ->where('destination_commune', $communeSlug)
                    ->where('meta->vehicle_code', $vehicleCode)
                    ->delete();

                DB::table('delivery_commune_rate_rules')->insert([
                    'origin_commune'      => null,
                    'destination_commune' => $communeSlug,
                    'relation_type'       => 'neighboring_commune',
                    'relation_fee'        => $tarif,
                    'min_fee'             => $tarif,
                    'is_active'           => true,
                    'meta'                => json_encode([
                        self::META_KEY  => true,
                        'vehicle_code'  => $vehicleCode,
                        'vehicle_label' => $this->vehicles[$vehicleCode]['label'],
                        'commune_label' => $this->communeLabels[$communeSlug],
                        'tarif_fcfa'    => $tarif,
                        'currency'      => 'XOF',
                        'ttc'           => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_commune_rate_rules')) {
            // Supprime uniquement les enregistrements générés par cette migration
            DB::table('delivery_commune_rate_rules')
                ->whereNull('origin_commune')
                ->whereIn('destination_commune', array_keys($this->tariffs))
                ->whereRaw("JSON_EXTRACT(meta, '$.ovanie_tariff') = true")
                ->delete();
        }

        if (Schema::hasTable('delivery_vehicle_rate_cards')) {
            // Supprime les véhicules ajoutés par cette migration (sauf moto déjà présente)
            DB::table('delivery_vehicle_rate_cards')
                ->whereIn('vehicle_code', ['tricycle', 'pickup', 'camion_3t', 'camion_10t'])
                ->delete();
        }
    }
};
