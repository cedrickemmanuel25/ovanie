<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomepageMaintenanceService
{

    /**
     * Répare les promotions actives qui n'ont pas de fenêtre temporelle.
     * Cette méthode est idempotente et destinée au Scheduler/CLI.
     */
    public function ensurePromotionWindows(): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sale_type')) {
            return ['flash_start_fixed' => 0, 'flash_end_fixed' => 0];
        }

        $flashStartFixed = 0;
        $flashEndFixed = 0;

        if (Schema::hasColumn('products', 'flash_start_at')) {
            $flashStartFixed = Product::query()
                ->whereIn(DB::raw('LOWER(sale_type)'), ['vente flash', 'vente_flash', 'flash sale', 'flash_sale', 'flash'])
                ->whereNull('flash_start_at')
                ->update([
                    'flash_start_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
                ]);
        }

        if (Schema::hasColumn('products', 'flash_end')) {
            $flashEndFixed = Product::query()
                ->whereIn(DB::raw('LOWER(sale_type)'), ['vente flash', 'vente_flash', 'flash sale', 'flash_sale', 'flash'])
                ->whereNull('flash_end')
                ->update([
                    'flash_end' => now()->addHours(
                        max(1, (int) config('homepage.promotions.flash_default_hours', 24))
                    ),
                ]);
        }

        // Black Friday est hebdomadaire. Les dates NULL signifient participation
        // récurrente chaque vendredi : on ne crée donc plus de fenêtre artificielle.
        return [
            'flash_start_fixed' => (int) $flashStartFixed,
            'flash_end_fixed' => (int) $flashEndFixed,
        ];
    }

    /**
     * Termine les promotions arrivées à expiration.
     *
     * Cette opération est destinée au Scheduler Laravel, jamais à une requête
     * publique de page d'accueil ou de catalogue.
     */
    public function normalizeExpiredSales(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sale_type')) {
            return 0;
        }

        $updated = 0;

        if (Schema::hasColumn('products', 'flash_end')) {
            $updates = ['sale_type' => 'normal'];

            foreach (['promo_price', 'flash_start_at', 'flash_end'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $updates[$column] = null;
                }
            }

            $updated += Product::query()
                ->whereIn(DB::raw('LOWER(sale_type)'), ['vente flash', 'vente_flash', 'flash sale', 'flash_sale', 'flash'])
                ->whereNotNull('flash_end')
                ->where('flash_end', '<=', now())
                ->update($updates);
        }

        // Une date bf_end explicite peut limiter l'inscription à l'événement.
        // Sans bf_end, le produit reste inscrit et redevient visible chaque vendredi.
        if (Schema::hasColumn('products', 'bf_end')) {
            $updates = ['sale_type' => 'normal'];

            foreach (['promo_price', 'bf_start', 'bf_end'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $updates[$column] = null;
                }
            }

            $updated += Product::query()
                ->whereIn(DB::raw('LOWER(sale_type)'), ['black friday', 'black_friday'])
                ->whereNotNull('bf_end')
                ->where('bf_end', '<', now())
                ->update($updates);
        }

        return $updated;
    }

    /**
     * Renouvelle le lot quotidien de boosts gratuits OVANIE.
     */
    public function refreshFreeBoosts(): int
    {
        $requiredColumns = [
            'free_boosted_by_ovanie',
            'free_boost_start_at',
            'free_boost_end_at',
            'free_boost_batch_date',
        ];

        if (! Schema::hasTable('products')) {
            return 0;
        }

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('products', $column)) {
                return 0;
            }
        }

        return DB::transaction(function () {
            $today = now()->toDateString();

            Product::query()
                ->where('free_boosted_by_ovanie', 1)
                ->where(function (Builder $expired) use ($today) {
                    $expired->whereDate('free_boost_batch_date', '<', $today)
                        ->orWhere('free_boost_end_at', '<', now());
                })
                ->update([
                    'free_boosted_by_ovanie' => 0,
                    'free_boost_start_at' => null,
                    'free_boost_end_at' => null,
                ]);

            $alreadyToday = Product::query()
                ->where('free_boosted_by_ovanie', 1)
                ->whereDate('free_boost_batch_date', $today)
                ->lockForUpdate()
                ->exists();

            if ($alreadyToday) {
                return 0;
            }

            $query = Product::query();

            if (Schema::hasColumn('products', 'status')) {
                $query->where('status', 'actif');
            }

            if (Schema::hasColumn('products', 'is_active')) {
                $query->where('is_active', 1);
            }

            if (Schema::hasColumn('products', 'stock')) {
                $query->where('stock', '>', 0);
            }

            if (Schema::hasTable('shops')) {
                $query->whereHas('shop', function (Builder $shop) {
                    if (Schema::hasColumn('shops', 'is_active')) {
                        $shop->where('is_active', 1);
                    }

                    if (Schema::hasColumn('shops', 'status')) {
                        $shop->whereIn('status', ['approved', 'active', 'actif', '1', 1]);
                    }

                    if (Schema::hasColumn('shops', 'logistics_status')) {
                        $shop->where('logistics_status', 'ready');
                    }
                });
            }

            if (Schema::hasColumn('products', 'boost_end_at')) {
                $query->where(function (Builder $paidBoost) {
                    $paidBoost->whereNull('boost_end_at')
                        ->orWhere('boost_end_at', '<', now());
                });
            }

            $productIds = $query
                ->inRandomOrder()
                ->limit(max(1, (int) config('homepage.maintenance.free_boost_count', 10)))
                ->lockForUpdate()
                ->pluck('id');

            if ($productIds->isEmpty()) {
                return 0;
            }

            Product::query()
                ->whereIn('id', $productIds)
                ->update([
                    'free_boosted_by_ovanie' => 1,
                    'free_boost_start_at' => now(),
                    'free_boost_end_at' => now()->addDays(
                        max(1, (int) config('homepage.maintenance.free_boost_duration_days', 1))
                    ),
                    'free_boost_batch_date' => $today,
                ]);

            return $productIds->count();
        });
    }
}
