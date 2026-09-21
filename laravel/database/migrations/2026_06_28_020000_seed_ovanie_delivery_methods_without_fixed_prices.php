<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_services')) {
            return;
        }

        $now = now();

        $services = [
            [
                'code' => 'ovanie-express',
                'name' => 'OVANIE Express',
                'description' => 'Colis rapide',
                'estimated_hours' => 24,
                'max_weight_kg' => 50,
                'max_volume_m3' => 0.35,
                'sort_order' => 10,
                'meta' => [
                    'vehicle_type' => 'Colis rapide',
                    'quote_required' => true,
                    'client_label' => 'Colis rapide',
                ],
            ],
            [
                'code' => 'ovanie-raider',
                'name' => 'OVANIE Raider',
                'description' => 'Moto',
                'estimated_hours' => 24,
                'max_weight_kg' => 25,
                'max_volume_m3' => 0.18,
                'sort_order' => 20,
                'meta' => [
                    'vehicle_type' => 'Moto',
                    'quote_required' => true,
                    'client_label' => 'Moto',
                ],
            ],
            [
                'code' => 'ovanie-pickup',
                'name' => 'OVANIE Pickup',
                'description' => 'Tricycle / fourgonnette',
                'estimated_hours' => 48,
                'max_weight_kg' => 1200,
                'max_volume_m3' => 7,
                'sort_order' => 30,
                'meta' => [
                    'vehicle_type' => 'Tricycle / fourgonnette',
                    'quote_required' => true,
                    'client_label' => 'Tricycle / fourgonnette',
                ],
            ],
            [
                'code' => 'ovanie-cargo',
                'name' => 'OVANIE Cargo',
                'description' => 'Matériaux gros volume',
                'estimated_hours' => 72,
                'max_weight_kg' => 10000,
                'max_volume_m3' => 35,
                'sort_order' => 40,
                'meta' => [
                    'vehicle_type' => 'Matériaux gros volume',
                    'quote_required' => true,
                    'client_label' => 'Matériaux gros volume',
                ],
            ],
            [
                'code' => 'ovanie-pro',
                'name' => 'OVANIE Pro',
                'description' => 'Livraison sur chantier',
                'estimated_hours' => 96,
                'max_weight_kg' => null,
                'max_volume_m3' => null,
                'sort_order' => 50,
                'meta' => [
                    'vehicle_type' => 'Livraison sur chantier',
                    'quote_required' => true,
                    'client_label' => 'Livraison sur chantier',
                    'recommended' => true,
                ],
            ],
        ];

        $activeCodes = array_column($services, 'code');

        DB::table('delivery_services')
            ->where('provider_type', 'ovanie')
            ->whereNotIn('code', $activeCodes)
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        foreach ($services as $service) {
            DB::table('delivery_services')->updateOrInsert(
                ['code' => $service['code']],
                [
                    'carrier_id' => null,
                    'provider_type' => 'ovanie',
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'estimated_hours' => $service['estimated_hours'],
                    'min_estimated_hours' => null,
                    'max_estimated_hours' => $service['estimated_hours'],
                    'max_weight_kg' => $service['max_weight_kg'],
                    'max_volume_m3' => $service['max_volume_m3'],
                    'max_length_cm' => null,
                    'max_width_cm' => null,
                    'max_height_cm' => null,
                    'available_days' => json_encode([1, 2, 3, 4, 5, 6], JSON_THROW_ON_ERROR),
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                    'sort_order' => $service['sort_order'],
                    'is_active' => true,
                    'meta' => json_encode($service['meta'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Les tarifs ne sont pas encore fixés : on supprime les anciens prix OVANIE
        // afin que le checkout indique clairement que la livraison sera calculee a l'etape suivante.
        if (Schema::hasTable('delivery_service_rates')) {
            $serviceIds = DB::table('delivery_services')
                ->whereIn('code', $activeCodes)
                ->pluck('id');

            DB::table('delivery_service_rates')
                ->whereIn('delivery_service_id', $serviceIds)
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_services')) {
            return;
        }

        DB::table('delivery_services')
            ->whereIn('code', [
                'ovanie-express',
                'ovanie-raider',
                'ovanie-pickup',
                'ovanie-cargo',
                'ovanie-pro',
            ])
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
