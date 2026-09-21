<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivitySector;
use App\Models\Activity;
use Illuminate\Support\Str;

class DefaultActivitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [

            'btp' => [
                'label' => 'Secteurs liés au BTP',
                'activities' => [
                    "maisons","appartements","routes","ponts","terrassement",
                    "maçonnerie","toiture","plomberie","électricité bâtiment",
                    "climatisation","carrelage","peinture","menuiserie"
                ]
            ],

            'domotique' => [
                'label' => 'Secteurs liés à la Domotique',
                'activities' => [
                    "automatisation résidentielle","éclairage","volets roulants",
                    "chauffage","climatisation","sécurité connectée",
                    "alarmes","vidéosurveillance","smart home"
                ]
            ],

            'habitat' => [
                'label' => 'Secteurs liés à l’Habitat',
                'activities' => [
                    "maisons","appartements","immeubles","rénovation",
                    "plomberie","électricité","chauffage","climatisation",
                    "menuiserie","peinture","carrelage"
                ]
            ],

            'energie' => [
                'label' => 'Secteurs liés à l’Énergie',
                'activities' => [
                    "panneaux photovoltaïques","chauffe-eau solaires",
                    "énergie solaire","énergie éolienne","biomasse",
                    "hydroélectricité","maintenance équipements énergétiques"
                ]
            ]

        ];

        foreach ($data as $slug => $sectorData) {

            $sector = ActivitySector::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $sectorData['label'],
                    'description' => $sectorData['label'],
                    'is_active' => true
                ]
            );

            foreach ($sectorData['activities'] as $activityName) {

                Activity::firstOrCreate(
                    [
                        'slug' => $slug . '-' . Str::slug($activityName)
                    ],
                    [
                        'name' => $activityName,
                        'description' => $activityName,
                        'is_active' => true,
                        'activity_sector_id' => $sector->id
                    ]
                );

            }
        }
    }
}
