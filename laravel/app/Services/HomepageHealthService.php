<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class HomepageHealthService
{
    public function __construct(
        private readonly PromotionVisibilityService $promotions,
        private readonly PublicProductVisibilityService $visibility,
    ) {
    }

    public function inspect(): array
    {
        $checks = [];

        $checks['products_table'] = Schema::hasTable('products');
        $checks['categories_table'] = Schema::hasTable('categories');
        $checks['shops_table'] = Schema::hasTable('shops');
        $checks['newsletter_table'] = Schema::hasTable('newsletter_subscribers');
        $checks['search_queries_table'] = Schema::hasTable('search_queries');
        $checks['home_ads_table'] = Schema::hasTable('home_ads');
        $checks['banners_table'] = Schema::hasTable('banners');

        $routes = [
            'home',
            'catalog.index',
            'search',
            'search.suggestions',
            'homepage.status',
            'newsletter.subscribe',
            'cart.add',
            'client.favorites.toggle',
        ];

        $checks['routes'] = collect($routes)
            ->mapWithKeys(fn (string $name) => [$name => Route::has($name)])
            ->all();

        $metrics = [
            'products_total' => 0,
            'products_active_status' => 0,
            'products_in_stock' => 0,
            'public_products' => 0,
            'featured_products' => 0,
            'products_with_sales_counter' => 0,
            'active_flash' => 0,
            'flash_missing_start' => 0,
            'flash_missing_end' => 0,
            'black_friday_enrolled' => 0,
            'active_black_friday' => 0,
            'is_black_friday_day' => $this->promotions->isBlackFridayDay(),
            'active_categories' => 0,
            'active_home_ads' => 0,
            'active_banners' => 0,
            'newsletter_subscribers' => 0,
            'recorded_search_queries' => 0,
        ];

        if (Schema::hasTable('products')) {
            $metrics['products_total'] = Product::query()->count();

            $active = Product::query();
            if (Schema::hasColumn('products', 'status')) {
                $active->whereIn('status', ['actif', 'active', 'approved']);
            }
            $metrics['products_active_status'] = $active->count();

            $inStock = Product::query();
            if (Schema::hasColumn('products', 'stock')) {
                $inStock->where('stock', '>', 0);
            }
            $metrics['products_in_stock'] = $inStock->count();

            $metrics['public_products'] = $this->visibility->query([])->count();

            $featured = $this->visibility->query([]);
            $this->promotions->applyFeatured($featured);
            $metrics['featured_products'] = $featured->count();

            if (Schema::hasColumn('products', 'sales')) {
                $metrics['products_with_sales_counter'] = $this->visibility->query([])
                    ->where('sales', '>', 0)
                    ->count();
            }

            if (Schema::hasColumn('products', 'sale_type')) {
                $flash = Product::query()->whereRaw('LOWER(sale_type) = ?', ['vente flash']);
                $black = Product::query()->whereRaw('LOWER(sale_type) = ?', ['black friday']);

                if (Schema::hasColumn('products', 'flash_start_at')) {
                    $metrics['flash_missing_start'] = (clone $flash)->whereNull('flash_start_at')->count();
                }

                if (Schema::hasColumn('products', 'flash_end')) {
                    $metrics['flash_missing_end'] = (clone $flash)->whereNull('flash_end')->count();
                }

                $activeFlash = Product::query();
                $this->promotions->applyActiveFlash($activeFlash);
                $metrics['active_flash'] = $activeFlash->count();

                $metrics['black_friday_enrolled'] = $black->count();
                $activeBlack = Product::query();
                $this->promotions->applyFridayBlackFriday($activeBlack);
                $metrics['active_black_friday'] = $activeBlack->count();
            }
        }

        if (Schema::hasTable('categories')) {
            $query = DB::table('categories');
            if (Schema::hasColumn('categories', 'is_active')) {
                $query->where('is_active', 1);
            } elseif (Schema::hasColumn('categories', 'status')) {
                $query->whereIn('status', ['actif', 'active', 1, '1']);
            }
            $metrics['active_categories'] = $query->count();
        }

        if (Schema::hasTable('home_ads')) {
            $query = DB::table('home_ads');
            if (Schema::hasColumn('home_ads', 'is_active')) {
                $query->where('is_active', 1);
            }
            if (Schema::hasColumn('home_ads', 'starts_at')) {
                $query->where(function ($window) {
                    $window->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                });
            }
            if (Schema::hasColumn('home_ads', 'ends_at')) {
                $query->where(function ($window) {
                    $window->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                });
            }
            $metrics['active_home_ads'] = $query->count();
        }

        if (Schema::hasTable('banners')) {
            $query = DB::table('banners');
            if (Schema::hasColumn('banners', 'is_active')) {
                $query->where('is_active', 1);
            }
            $metrics['active_banners'] = $query->count();
        }

        if (Schema::hasTable('newsletter_subscribers')) {
            $metrics['newsletter_subscribers'] = DB::table('newsletter_subscribers')
                ->where('status', 'active')
                ->count();
        }

        if (Schema::hasTable('search_queries')) {
            $metrics['recorded_search_queries'] = DB::table('search_queries')->count();
        }

        $blocking = [];

        foreach (['products_table', 'categories_table', 'shops_table', 'newsletter_table'] as $key) {
            if (! ($checks[$key] ?? false)) {
                $blocking[] = $key;
            }
        }

        foreach ($checks['routes'] as $name => $exists) {
            if (! $exists) {
                $blocking[] = 'route:' . $name;
            }
        }

        if (($metrics['flash_missing_start'] ?? 0) > 0) {
            $blocking[] = 'flash_products_without_start_date';
        }

        if (($metrics['flash_missing_end'] ?? 0) > 0) {
            $blocking[] = 'flash_products_without_end_date';
        }

        $warnings = [];

        if (($metrics['public_products'] ?? 0) < 3) {
            $warnings[] = 'very_few_public_products_for_homepage_panels';
        }

        if (($metrics['featured_products'] ?? 0) === 0) {
            $warnings[] = 'no_active_featured_products_homepage_will_use_discovery';
        }

        if (($metrics['products_with_sales_counter'] ?? 0) === 0) {
            $warnings[] = 'no_sales_counter_products_homepage_may_use_selection';
        }

        return [
            'ok' => $blocking === [],
            'checks' => $checks,
            'metrics' => $metrics,
            'blocking' => $blocking,
            'warnings' => $warnings,
        ];
    }
}
