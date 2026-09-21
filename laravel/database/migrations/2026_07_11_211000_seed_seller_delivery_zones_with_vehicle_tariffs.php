<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les 13 communes d'Abidjan avec les tarifs par véhicule
 * pour les 6 vendeurs utilisant leur propre logistique (logistics_type = 'seller').
 *
 * Boutiques concernées : shop_id 1, 3, 4, 8, 9, 10
 * Tarifs : Moto | Tricycle | Pickup | Camion 3T | Camion 10T
 */
return new class extends Migration
{
    /** shop_id => shop_name (pour vérification visuelle uniquement) */
    private array $sellerShops = [1, 3, 4, 8, 9, 10];

    /**
     * Communes avec tarifs par véhicule (FCFA, TTC).
     * Ordre : [moto, tricycle, pickup, camion_3t, camion_10t]
     */
    private array $tariffs = [
        ['commune' => 'Abobo',       'city' => 'Abidjan', 'prices' => [3500, 10000, 21000, 37000, 67000]],
        ['commune' => 'Adjamé',      'city' => 'Abidjan', 'prices' => [2500,  6500, 14000, 25500, 46000]],
        ['commune' => 'Anyama',      'city' => 'Abidjan', 'prices' => [5000, 13000, 28000, 48500, 86500]],
        ['commune' => 'Attécoubé',   'city' => 'Abidjan', 'prices' => [3000,  7500, 16500, 29000, 52000]],
        ['commune' => 'Bingerville', 'city' => 'Abidjan', 'prices' => [4000, 10500, 23000, 40500, 71500]],
        ['commune' => 'Cocody',      'city' => 'Abidjan', 'prices' => [3000,  8500, 17500, 31500, 55500]],
        ['commune' => 'Koumassi',    'city' => 'Abidjan', 'prices' => [2500,  6500, 14000, 25500, 46000]],
        ['commune' => 'Marcory',     'city' => 'Abidjan', 'prices' => [2000,  5500, 11500, 21000, 40500]],
        ['commune' => 'Plateau',     'city' => 'Abidjan', 'prices' => [2000,  6000, 13000, 23000, 44000]],
        ['commune' => 'Port-Bouët',  'city' => 'Abidjan', 'prices' => [3000,  7500, 16500, 29000, 53000]],
        ['commune' => 'Songon',      'city' => 'Abidjan', 'prices' => [5500, 14000, 30000, 52000, 92000]],
        ['commune' => 'Treichville', 'city' => 'Abidjan', 'prices' => [2000,  5500, 11500, 21000, 40500]],
        ['commune' => 'Yopougon',    'city' => 'Abidjan', 'prices' => [3500,  9500, 20000, 34500, 63500]],
    ];

    /** Codes véhicules dans l'ordre du tableau prices[] */
    private array $vehicles = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

    /** Capacités max par véhicule [max_weight_kg, max_volume_m3] */
    private array $vehicleCapacities = [
        'moto'       => ['max_weight_kg' => 20.0,   'max_volume_m3' => 0.18],
        'tricycle'   => ['max_weight_kg' => 250.0,  'max_volume_m3' => 1.5],
        'pickup'     => ['max_weight_kg' => 1000.0, 'max_volume_m3' => 7.0],
        'camion_3t'  => ['max_weight_kg' => 3000.0, 'max_volume_m3' => 20.0],
        'camion_10t' => ['max_weight_kg' => null,   'max_volume_m3' => null],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('seller_delivery_zones')) {
            return;
        }

        $now = now();

        foreach ($this->sellerShops as $shopId) {
            if (! DB::table('shops')->where('id', $shopId)->exists()) {
                continue;
            }

            // Récupérer ou créer le profil de livraison du vendeur
            $profile = DB::table('seller_delivery_profiles')
                ->where('shop_id', $shopId)
                ->first();

            if (! $profile) {
                $profileId = DB::table('seller_delivery_profiles')->insertGetId([
                    'shop_id'    => $shopId,
                    'is_enabled' => true,
                    'status'     => 'ready',
                    'vehicle_types' => json_encode($this->vehicles),
                    'default_delay' => '24h',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $profileId = $profile->id;

                // Mettre à jour le profil avec les types de véhicules
                DB::table('seller_delivery_profiles')
                    ->where('id', $profileId)
                    ->update([
                        'vehicle_types' => json_encode($this->vehicles),
                        'is_enabled'    => true,
                        'status'        => 'ready',
                        'updated_at'    => $now,
                    ]);
            }

            // Supprimer les anciennes zones pour repartir proprement
            DB::table('seller_delivery_zones')
                ->where('shop_id', $shopId)
                ->delete();

            // Insérer une zone par commune × véhicule
            foreach ($this->tariffs as $tariff) {
                foreach ($this->vehicles as $idx => $vehicleCode) {
                    $price    = $tariff['prices'][$idx];
                    $capacity = $this->vehicleCapacities[$vehicleCode];

                    DB::table('seller_delivery_zones')->insert([
                        'shop_id'                   => $shopId,
                        'seller_delivery_profile_id' => $profileId,
                        'city'                      => $tariff['city'],
                        'commune'                   => $tariff['commune'],
                        'district'                  => null,
                        'coverage_type'             => 'commune',
                        'delivery_price'            => $price,
                        'estimated_delay'           => '24h',
                        'max_weight_kg'             => $capacity['max_weight_kg'],
                        'max_volume_m3'             => $capacity['max_volume_m3'],
                        'is_active'                 => true,
                        'created_at'                => $now,
                        'updated_at'                => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('seller_delivery_zones')) {
            return;
        }

        $communes = array_column($this->tariffs, 'commune');

        DB::table('seller_delivery_zones')
            ->whereIn('shop_id', $this->sellerShops)
            ->whereIn('commune', $communes)
            ->delete();
    }
};
