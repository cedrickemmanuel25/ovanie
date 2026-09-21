<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abidjan_communes')) {
            return;
        }

        // Centres de communes utilisés uniquement pour afficher la couverture
        // géographique sélectionnée dans le modal livreur.
        $communes = [
            'ABO' => ['Abobo', 5.4188889, -4.0205556],
            'ADJ' => ['Adjamé', 5.3648000, -4.0236611],
            'ANY' => ['Anyama', 5.4944444, -4.0516667],
            'ATT' => ['Attécoubé', 5.3337320, -4.0378620],
            'BIN' => ['Bingerville', 5.3500000, -3.9000000],
            'COC' => ['Cocody', 5.3500000, -3.9666667],
            'KOU' => ['Koumassi', 5.3000000, -3.9500000],
            'MAR' => ['Marcory', 5.3000000, -3.9833333],
            'PLA' => ['Plateau', 5.3241200, -4.0205900],
            'PBO' => ['Port-Bouët', 5.2666667, -3.9000000],
            'SON' => ['Songon', 5.3166667, -4.2500000],
            'TRE' => ['Treichville', 5.3000000, -4.0000000],
            'YOP' => ['Yopougon', 5.3347194, -4.0700000],
        ];

        foreach ($communes as $code => [$name, $latitude, $longitude]) {
            DB::table('abidjan_communes')
                ->where('code', $code)
                ->update([
                    'name' => $name,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'updated_at' => now(),
                ]);
        }

        // Les anciens profils pouvaient conserver des libellés mal encodés.
        // On reconstruit profile.zones depuis les IDs officiels sélectionnés.
        if (! Schema::hasTable('delivery_drivers')) {
            return;
        }

        $registry = DB::table('abidjan_communes')
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->keyBy('id');

        foreach (DB::table('delivery_drivers')->get(['id', 'commune_id', 'zone', 'profile']) as $driver) {
            $profile = json_decode((string) ($driver->profile ?? ''), true);
            $profile = is_array($profile) ? $profile : [];

            $zoneIds = collect($profile['zone_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0);

            if ($driver->commune_id) {
                $zoneIds->prepend((int) $driver->commune_id);
            }

            $zoneIds = $zoneIds->unique()->values();
            if ($zoneIds->isEmpty()) {
                continue;
            }

            $names = $zoneIds
                ->map(fn ($id) => $registry->get($id)?->name)
                ->filter()
                ->values();

            if ($names->isEmpty()) {
                continue;
            }

            $profile['zone_ids'] = $zoneIds->all();
            $profile['zones'] = $names->all();

            DB::table('delivery_drivers')->where('id', $driver->id)->update([
                'zone' => $names->first(),
                'commune_id' => $zoneIds->first(),
                'profile' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Les coordonnées sont des données de référence et ne sont pas supprimées
        // au rollback afin de ne pas réintroduire des cartes sans marqueurs.
    }
};
