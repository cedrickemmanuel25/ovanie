<?php

namespace Database\Seeders;

use App\Models\HomeAd;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OvanieHomeSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->createSeasonSale();
    }

    private function createSeasonSale(): void
    {
        if (!Schema::hasTable('home_ads')) {
            return;
        }

        $query = HomeAd::query();

        if (Schema::hasColumn('home_ads', 'placement')) {
            $query->where('placement', 'season_sale');
        } elseif (Schema::hasColumn('home_ads', 'title')) {
            $query->where('title', 'SOLDES DE SAISON');
        }

        $homeAd = $query->first() ?: new HomeAd();

        if (Schema::hasColumn('home_ads', 'placement')) {
            $homeAd->placement = 'season_sale';
        }

        if (Schema::hasColumn('home_ads', 'title')) {
            $homeAd->title = 'SOLDES DE SAISON';
        }

        if (Schema::hasColumn('home_ads', 'subtitle')) {
            $homeAd->subtitle = 'Jusqu’à -40% sur les matériaux de gros œuvre';
        }

        if (Schema::hasColumn('home_ads', 'button_text')) {
            $homeAd->button_text = 'Voir les offres';
        }

        if (Schema::hasColumn('home_ads', 'button_url')) {
            $homeAd->button_url = url('/catalog?type=vente%20flash');
        }

        if (Schema::hasColumn('home_ads', 'starts_at')) {
            $homeAd->starts_at = now()->subDay();
        }

        if (Schema::hasColumn('home_ads', 'ends_at')) {
            $homeAd->ends_at = now()->addDays(30);
        }

        if (Schema::hasColumn('home_ads', 'is_active')) {
            $homeAd->is_active = true;
        }

        if (Schema::hasColumn('home_ads', 'status')) {
            $homeAd->status = 'actif';
        }

        $homeAd->save();
    }
}
