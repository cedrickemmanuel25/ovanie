<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MarketplacePerformanceService
{
    public const CACHE_TTL_SHORT = 300;
    public const CACHE_TTL_MEDIUM = 1800;
    public const CACHE_TTL_LONG = 3600;

    public function activeCategories()
    {
        return Cache::remember('ovanie:categories:active', self::CACHE_TTL_LONG, function () {
            return Category::query()
                ->select(['id', 'name', 'slug', 'parent_id', 'status', 'is_active', 'sort_order'])
                ->active()
                ->roots()
                ->with(['children' => fn ($q) => $q
                    ->select(['id', 'name', 'slug', 'parent_id', 'status', 'is_active', 'sort_order'])
                    ->active()
                    ->ordered()])
                ->ordered()
                ->get();
        });
    }

    public function popularProducts(int $limit = 12)
    {
        $limit = max(4, min($limit, 24));

        return Cache::remember("ovanie:products:popular:{$limit}", self::CACHE_TTL_MEDIUM, function () use ($limit) {
            return $this->baseProductQuery()
                ->orderByDesc(Schema::hasColumn('products', 'sales') ? 'sales' : 'created_at')
                ->limit($limit)
                ->get();
        });
    }

    public function homeProducts(int $limit = 16)
    {
        $limit = max(4, min($limit, 32));

        return Cache::remember("ovanie:home:products:{$limit}", self::CACHE_TTL_SHORT, function () use ($limit) {
            return $this->baseProductQuery()
                ->latest('products.created_at')
                ->limit($limit)
                ->get();
        });
    }

    public function paginatedCatalogue(array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $perPage = max(12, min($perPage, 48));

        return $this->baseProductQuery()
            ->when(!empty($filters['search']), function (Builder $query) use ($filters) {
                $search = mb_strtolower(trim($filters['search']));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(products.name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(products.description) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when(!empty($filters['category_id']), fn (Builder $q) => $q->where('products.category_id', $filters['category_id']))
            ->when(!empty($filters['shop_id']), fn (Builder $q) => $q->where('products.shop_id', $filters['shop_id']))
            ->when(!empty($filters['city']) && Schema::hasColumn('products', 'city'), function (Builder $q) use ($filters) {
                $q->whereRaw('LOWER(products.city) LIKE ?', ['%' . mb_strtolower($filters['city']) . '%']);
            })
            ->when(!empty($filters['min_price']), fn (Builder $q) => $q->where('products.price', '>=', (float) $filters['min_price']))
            ->when(!empty($filters['max_price']), fn (Builder $q) => $q->where('products.price', '<=', (float) $filters['max_price']))
            ->latest('products.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function adminDashboardData(): array
    {
        return Cache::remember('ovanie:admin:dashboard:kpis', 60, function () {
            return [
                'sales_total' => (float) Order::query()->sum($this->orderTotalColumn()),
                'orders_total' => Order::query()->count(),
                'orders_pending' => $this->countByStatus(Order::query(), ['pending', 'en_attente', 'processing']),
                'products_total' => Product::query()->count(),
                'products_pending' => $this->countByStatus(Product::query(), ['pending', 'en_attente', 'draft']),
                'shops_total' => Shop::query()->count(),
                'shops_pending' => $this->countByStatus(Shop::query(), ['pending', 'en_attente']),
                'payments_pending' => class_exists(Payment::class) ? $this->countByStatus(Payment::query(), ['pending', 'en_attente']) : 0,
            ];
        });
    }

    public function recentOrdersForAdmin(int $limit = 10)
    {
        return Order::query()
            ->with(['client:id,name,email', 'user:id,name,email', 'shop:id,name,city,commune'])
            ->latest('orders.created_at')
            ->limit(max(5, min($limit, 25)))
            ->get();
    }

    public function vendorStats(int $shopId): array
    {
        return Cache::remember("ovanie:vendor:{$shopId}:stats", 120, function () use ($shopId) {
            $orderItemsQuery = \App\Models\OrderItem::query()->where('shop_id', $shopId);

            return [
                'products_total' => Product::query()->where('shop_id', $shopId)->count(),
                'products_active' => Product::query()->where('shop_id', $shopId)->where('status', 'actif')->count(),
                'orders_total' => (clone $orderItemsQuery)->distinct('order_id')->count('order_id'),
                'revenue_total' => (float) (clone $orderItemsQuery)->sum($this->orderItemTotalColumn()),
            ];
        });
    }

    public function flushMarketplaceCache(): void
    {
        foreach ([
            'ovanie:categories:active',
            'ovanie:admin:dashboard:kpis',
        ] as $key) {
            Cache::forget($key);
        }
    }

    private function baseProductQuery(): Builder
    {
        return Product::query()
            ->select($this->productColumns())
            ->with([
                'category:id,name,slug,parent_id',
                'shop:id,name,city,commune,is_active,status',
                'images:id,product_id,image_path,path,url',
            ])
            ->where('products.status', 'actif')
            ->whereHas('shop', function ($q) {
                $q->where(function ($sub) {
                    if (Schema::hasColumn('shops', 'is_active')) {
                        $sub->where('is_active', 1);
                    }

                    if (Schema::hasColumn('shops', 'status')) {
                        $sub->orWhere('status', 'approved');
                    }
                });
            });
    }

    private function productColumns(): array
    {
        $wanted = [
            'id', 'shop_id', 'category_id', 'name', 'slug', 'description', 'price', 'promo_price',
            'stock', 'status', 'city', 'unit', 'unit_label', 'min_order_quantity', 'packaging',
            'brand', 'weight_kg', 'volume_m3', 'created_at', 'updated_at', 'sales',
        ];

        return array_values(array_filter($wanted, fn ($column) => Schema::hasColumn('products', $column)));
    }

    private function countByStatus(Builder $query, array $statuses): int
    {
        $table = $query->getModel()->getTable();

        if (!Schema::hasColumn($table, 'status')) {
            return 0;
        }

        return (int) $query->whereIn('status', $statuses)->count();
    }

    private function orderTotalColumn(): string
    {
        foreach (['total_amount', 'grand_total', 'total'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                return $column;
            }
        }

        return 'id';
    }

    private function orderItemTotalColumn(): string
    {
        foreach (['total', 'subtotal', 'line_total', 'price'] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                return $column;
            }
        }

        return 'id';
    }
}
