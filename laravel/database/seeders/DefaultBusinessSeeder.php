<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\BusinessCategory;
use App\Models\BusinessService;

class DefaultBusinessSeeder extends Seeder
{
    public function run(): void
    {
        // Catégories business par défaut (remet ce qui a été supprimé)
        $defaultCategories = [
            'MatZone-business',
            'BTP',
            'IMMOBILIER',
            'AUTRES(Reste des travaux',
        ];

        $categoryMap = [];

        foreach ($defaultCategories as $name) {
            $slug = Str::slug($name);
            $category = BusinessCategory::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => null, 'is_active' => true]
            );
            $categoryMap[$slug] = $category;
        }

        // Services par défaut
        $servicesData = [
            "Construction",
            "Fourniture matériel",
            "Installation",
            "Maintenance",
            "Consulting",
            "Études techniques"
        ];

        // On associe ces services à la catégorie IMOo BUSINESS si elle existe, sinon null
        $imoo = BusinessCategory::where('slug', Str::slug('IMOo BUSINESS'))->first();

        foreach ($servicesData as $serviceName) {
            $slug = Str::slug($serviceName);
            BusinessService::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $serviceName,
                    'description' => null,
                    'business_category_id' => $imoo ? $imoo->id : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
