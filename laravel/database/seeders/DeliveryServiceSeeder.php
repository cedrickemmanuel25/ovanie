<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\DeliveryService;
use App\Models\DeliveryServiceRate;
use Illuminate\Database\Seeder;

class DeliveryServiceSeeder extends Seeder
{
    public function run(): void
    {
        $ovanieCarrier = Carrier::updateOrCreate(
            ['code' => 'ovanie-logistics'],
            [
                'name' => 'OVANIE Logistics',
                'phone' => '01 61 78 18 18',
                'city' => 'Abidjan',
                'status' => 'active',
                'rating' => 4.8,
                'average_delay_hours' => 48,
                'is_active' => true,
            ]
        );

        // Les anciens services restent désactivés pour éviter des prix/choix obsolètes.
        DeliveryService::where('provider_type', DeliveryService::PROVIDER_OVANIE)
            ->whereIn('code', ['ovanie-priority', 'ovanie-standard'])
            ->update(['is_active' => false]);

        $ovanieServices = [
            [
                'code' => 'ovanie-express',
                'name' => 'OVANIE Express',
                'description' => 'Colis rapide.',
                'estimated_hours' => 24,
                'sort_order' => 10,
                'vehicle_type' => 'Colis rapide',
                'max_weight_kg' => 30,
                'max_volume_m3' => 0.5,
            ],
            [
                'code' => 'ovanie-raider',
                'name' => 'OVANIE Raider',
                'description' => 'Livraison à moto.',
                'estimated_hours' => 24,
                'sort_order' => 20,
                'vehicle_type' => 'Moto',
                'max_weight_kg' => 25,
                'max_volume_m3' => 0.3,
            ],
            [
                'code' => 'ovanie-pickup',
                'name' => 'OVANIE Pickup',
                'description' => 'Tricycle ou fourgonnette.',
                'estimated_hours' => 48,
                'sort_order' => 30,
                'vehicle_type' => 'Tricycle / Fourgonnette',
                'max_weight_kg' => 1000,
                'max_volume_m3' => 8,
            ],
            [
                'code' => 'ovanie-cargo',
                'name' => 'OVANIE Cargo',
                'description' => 'Matériaux gros volume.',
                'estimated_hours' => 72,
                'sort_order' => 40,
                'vehicle_type' => 'Cargo / Camion',
                'max_weight_kg' => 10000,
                'max_volume_m3' => 35,
            ],
            [
                'code' => 'ovanie-pro',
                'name' => 'OVANIE Pro',
                'description' => 'Livraison sur chantier.',
                'estimated_hours' => 72,
                'sort_order' => 50,
                'vehicle_type' => 'Livraison chantier',
                'max_weight_kg' => 20000,
                'max_volume_m3' => 60,
            ],
        ];

        foreach ($ovanieServices as $data) {
            $service = DeliveryService::updateOrCreate(
                ['code' => $data['code']],
                [
                    'provider_type' => DeliveryService::PROVIDER_OVANIE,
                    'carrier_id' => $ovanieCarrier->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'min_estimated_hours' => null,
                    'max_estimated_hours' => $data['estimated_hours'],
                    'sort_order' => $data['sort_order'],
                    'available_days' => [1, 2, 3, 4, 5, 6],
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                    'max_weight_kg' => $data['max_weight_kg'],
                    'max_volume_m3' => $data['max_volume_m3'],
                    'is_active' => true,
                    'meta' => [
                        'quote_required' => true,
                        'vehicle_type' => $data['vehicle_type'],
                        'public_price_label' => 'Livraison calculee a l etape suivante',
                    ],
                ]
            );

            // Pas de prix fixes tant que l'engine tarifaire réel n'est pas défini.
            // Ces lignes permettent au service d'etre selectionnable sans annoncer un tarif fixe au checkout.
            foreach (['abidjan', 'interieur'] as $zone) {
                DeliveryServiceRate::updateOrCreate(
                    [
                        'delivery_service_id' => $service->id,
                        'delivery_zone' => $zone,
                        'city' => $zone === 'abidjan' ? 'Abidjan' : null,
                        'commune' => null,
                    ],
                    [
                        'base_fee' => 0,
                        'price_per_kg' => 0,
                        'price_per_m3' => 0,
                        'price_per_km' => 0,
                        'fragile_fee' => 0,
                        'unloading_fee' => 0,
                        'urgent_fee' => 0,
                        'min_fee' => null,
                        'max_fee' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
