<?php

namespace Database\Seeders;

use App\Models\DeliveryDistanceMatrix;
use Illuminate\Database\Seeder;

class DeliveryDistanceMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Cocody', 'Yopougon', 23, 55],
            ['Cocody', 'Marcory', 11, 30],
            ['Cocody', 'Treichville', 13, 35],
            ['Cocody', 'Plateau', 9, 25],
            ['Cocody', 'Adjame', 10, 30],
            ['Cocody', 'Abobo', 13, 35],
            ['Cocody', 'Koumassi', 14, 40],
            ['Cocody', 'Port-Bouet', 22, 50],
            ['Cocody', 'Bingerville', 12, 25],
            ['Yopougon', 'Plateau', 13, 35],
            ['Yopougon', 'Adjame', 12, 35],
            ['Yopougon', 'Abobo', 17, 45],
            ['Yopougon', 'Koumassi', 24, 60],
            ['Yopougon', 'Port-Bouet', 30, 70],
            ['Yopougon', 'Bingerville', 34, 75],
            ['Marcory', 'Treichville', 5, 15],
            ['Marcory', 'Plateau', 8, 20],
            ['Marcory', 'Koumassi', 5, 15],
            ['Marcory', 'Port-Bouet', 12, 30],
            ['Treichville', 'Plateau', 4, 15],
            ['Treichville', 'Koumassi', 8, 20],
            ['Treichville', 'Port-Bouet', 12, 30],
            ['Plateau', 'Adjame', 4, 15],
            ['Plateau', 'Abobo', 15, 40],
            ['Plateau', 'Koumassi', 10, 25],
            ['Adjame', 'Abobo', 10, 30],
            ['Adjame', 'Anyama', 22, 45],
            ['Abobo', 'Anyama', 12, 25],
            ['Koumassi', 'Port-Bouet', 10, 25],
            ['Bingerville', 'Grand-Bassam', 32, 45],
            ['Port-Bouet', 'Grand-Bassam', 30, 45],
            ['Yopougon', 'Songon', 18, 35],
            ['Songon', 'Plateau', 28, 65],
            ['Anyama', 'Cocody', 24, 45],
        ];

        foreach ($rows as [$origin, $destination, $distance, $duration]) {
            $this->upsert($origin, $destination, $distance, $duration);
            $this->upsert($destination, $origin, $distance, $duration);
        }
    }

    private function upsert(string $origin, string $destination, float $distance, int $duration): void
    {
        DeliveryDistanceMatrix::updateOrCreate(
            [
                'origin_commune' => $origin,
                'destination_commune' => $destination,
            ],
            [
                'distance_km' => $distance,
                'estimated_duration_minutes' => $duration,
                'is_active' => true,
            ]
        );
    }
}
