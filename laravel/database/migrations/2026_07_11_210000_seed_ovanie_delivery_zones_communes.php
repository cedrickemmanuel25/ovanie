<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed des 13 communes d'Abidjan comme zones opérationnelles OVANIE Logistique.
 *
 * La table delivery_zones définit la disponibilité géographique des équipes OVANIE
 * (livreurs, ressources locales). Elle NE calcule PAS les prix du checkout —
 * ceux-ci proviennent de delivery_commune_rate_rules (moteur Tarification OVANIE).
 *
 * price = tarif moto de base pour la zone (indicatif, utilisé dans le tableau de bord).
 * delivery_days = délai indicatif opérationnel (1 jour pour toutes les communes d'Abidjan).
 */
return new class extends Migration
{
    private array $zones = [
        ['name' => 'Abobo',       'region' => 'Abidjan', 'price' => 3000, 'delivery_days' => 1],
        ['name' => 'Adjamé',      'region' => 'Abidjan', 'price' => 1800, 'delivery_days' => 1],
        ['name' => 'Anyama',      'region' => 'Abidjan', 'price' => 4000, 'delivery_days' => 1],
        ['name' => 'Attécoubé',   'region' => 'Abidjan', 'price' => 2200, 'delivery_days' => 1],
        ['name' => 'Bingerville', 'region' => 'Abidjan', 'price' => 3200, 'delivery_days' => 1],
        ['name' => 'Cocody',      'region' => 'Abidjan', 'price' => 2200, 'delivery_days' => 1],
        ['name' => 'Koumassi',    'region' => 'Abidjan', 'price' => 1800, 'delivery_days' => 1],
        ['name' => 'Marcory',     'region' => 'Abidjan', 'price' => 1500, 'delivery_days' => 1],
        ['name' => 'Plateau',     'region' => 'Abidjan', 'price' => 1500, 'delivery_days' => 1],
        ['name' => 'Port-Bouët',  'region' => 'Abidjan', 'price' => 2200, 'delivery_days' => 1],
        ['name' => 'Songon',      'region' => 'Abidjan', 'price' => 4500, 'delivery_days' => 1],
        ['name' => 'Treichville', 'region' => 'Abidjan', 'price' => 1500, 'delivery_days' => 1],
        ['name' => 'Yopougon',    'region' => 'Abidjan', 'price' => 2800, 'delivery_days' => 1],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('delivery_zones')) {
            return;
        }

        $now = now();

        foreach ($this->zones as $zone) {
            DB::table('delivery_zones')->updateOrInsert(
                ['name' => $zone['name']],
                [
                    'region'        => $zone['region'],
                    'price'         => $zone['price'],
                    'delivery_days' => $zone['delivery_days'],
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_zones')) {
            DB::table('delivery_zones')
                ->whereIn('name', array_column($this->zones, 'name'))
                ->delete();
        }
    }
};
