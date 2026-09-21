<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_vehicle_rate_cards')) {
            return;
        }

        $capacities = [
            'moto' => ['min' => 0.0, 'max' => 20.0],
            'tricycle' => ['min' => 20.0, 'max' => 250.0],
            'pickup' => ['min' => 250.0, 'max' => 1000.0],
            'camion_3t' => ['min' => 1000.0, 'max' => 3000.0],
            'camion_10t' => ['min' => 3000.0, 'max' => null],
        ];

        foreach ($capacities as $vehicleCode => $capacity) {
            DB::table('delivery_vehicle_rate_cards')
                ->where('vehicle_code', $vehicleCode)
                ->where(function ($query) {
                    $query->whereNull('max_weight_kg')
                        ->orWhere('max_weight_kg', '<=', 0);
                })
                ->update([
                    'min_weight_kg' => $capacity['min'],
                    'max_weight_kg' => $capacity['max'],
                    'updated_at' => now(),
                ]);

            DB::table('delivery_vehicle_rate_cards')
                ->where('vehicle_code', $vehicleCode)
                ->whereNull('min_weight_kg')
                ->update([
                    'min_weight_kg' => $capacity['min'],
                    'updated_at' => now(),
                ]);
        }

        // Une capacité volumique à 0 bloque artificiellement tous les produits
        // ayant un volume positif. On la transforme en absence de limite.
        DB::table('delivery_vehicle_rate_cards')
            ->whereNotNull('max_volume_m3')
            ->where('max_volume_m3', '<=', 0)
            ->update([
                'max_volume_m3' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Cette migration corrige des valeurs invalides existantes.
        // Aucun retour automatique vers 0 kg / 0 m³ n'est souhaitable.
    }
};
