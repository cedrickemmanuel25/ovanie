<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $defaultServices = [
            "Construction",
            "Fourniture matériel",
            "Installation",
            "Maintenance",
            "Consulting",
            "Études techniques"
        ];

        foreach ($defaultServices as $service) {
            Service::firstOrCreate(['name' => $service]);
        }
    }
}
