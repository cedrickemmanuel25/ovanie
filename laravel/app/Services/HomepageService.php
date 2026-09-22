<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Cart;
use App\Models\Category;
use App\Models\GiftCardProduct;
use App\Models\HomeAd;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomepageService
{
    public function __construct(
        private readonly PromotionVisibilityService $promotions,
        private readonly PublicProductVisibilityService $visibility,
    ) {
    }

    /**
     * Construit toutes les données nécessaires à la page d'accueil.
     *
     * Cette méthode reste strictement en lecture.
     */
    public function build(?User $user = null): array
    {
        if (! Schema::hasTable('products')) {
            return $this->emptyPayload();
        }

        $ttl = max(10, (int) config('homepage.cache.public_ttl_seconds', 120));
        $version = (string) config('homepage.cache.version', 'v4-home-professional');

        $payload = Cache::remember(
            "homepage.public.{$version}",
            now()->addSeconds($ttl),
            fn (): array => $this->buildPublicPayload(),
        );

        $visibleProductIds = collect()
            ->concat($payload['flashProducts'] ?? [])
            ->concat($payload['blackFridayProducts'] ?? [])
            ->concat($payload['bestSellers'] ?? [])
            ->concat($payload['latestProducts'] ?? [])
            ->concat($payload['featuredProducts'] ?? [])
            ->concat($payload['flashPanelProducts'] ?? [])
            ->concat($payload['rightPanelProducts'] ?? [])
            ->concat($payload['middlePanelProducts'] ?? [])
            ->concat($payload['ecoProducts'] ?? [])
            ->concat($payload['promotionProducts'] ?? [])
            ->concat($payload['featuredCategoryProducts'] ?? [])
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        $payload['cartCount'] = $this->cartCount($user);
        $payload['favoriteProductIds'] = $this->favoriteProductIds($user, $visibleProductIds);

        return $payload;
    }

    private function buildPublicPayload(): array
    {
        $flashProducts = $this->flashProducts();
        $blackFridayProducts = $this->blackFridayProducts();
        $normalProducts = $this->normalProducts();
        $latestProducts = $this->latestProducts();
        $bestSellers = $this->bestSellers();
        $boostedProducts = $this->featuredProducts();
        $promotionProducts = $this->promotionProducts();
        $ecoProducts = $this->ecoProducts();
        $giftCardGroups = $this->giftCardGroups();
        $displayCategories = $this->displayCategories();
        $homepageCategoryCards = $this->homepageCategoryCards();
        $featuredCategoryBlock = $this->featuredCategoryBlock($displayCategories, $ecoProducts);
        $publicProductCount = $this->publicProductCount();
        $verifiedSellerCount = $this->verifiedSellerCount();

        $leftPanelLimit = max(1, (int) config('homepage.panel_display.left', 5));
        $middlePanelLimit = max(1, (int) config('homepage.panel_display.middle', 4));
        $rightPanelLimit = max(1, (int) config('homepage.panel_display.right', 5));

        $flashPanelMode = $flashProducts->isNotEmpty() ? 'flash' : 'latest';
        $flashPanelProducts = ($flashPanelMode === 'flash' ? $flashProducts : $latestProducts)
            ->take($leftPanelLimit)
            ->values();

        $leftIds = $flashPanelProducts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $isBlackFridayDay = $this->promotions->isBlackFridayDay();

        if ($isBlackFridayDay && $blackFridayProducts->isNotEmpty()) {
            $rightPanelMode = 'black_friday';
            $rightPanelProducts = $blackFridayProducts
                ->take($rightPanelLimit)
                ->values();
        } else {
            $minimumFeatured = $rightPanelLimit;
            $featuredWithoutLeft = $boostedProducts
                ->reject(fn (Product $product) => in_array((int) $product->id, $leftIds, true))
                ->values();

            if ($featuredWithoutLeft->count() >= $minimumFeatured) {
                $rightPanelMode = 'featured';
                $rightPanelProducts = $this->fillCollection(
                    $featuredWithoutLeft,
                    $boostedProducts,
                    $rightPanelLimit,
                );
            } else {
                $rightPanelMode = 'discovery';
                $discoveryWithoutLeft = $this->selectionProducts($leftIds, max($rightPanelLimit * 2, 10), true);

                $rightPanelProducts = $this->fillCollection(
                    $featuredWithoutLeft->concat($discoveryWithoutLeft),
                    $this->selectionProducts([], max($rightPanelLimit * 2, 10)),
                    $rightPanelLimit,
                );
            }
        }

        $reservedIds = collect($leftIds)
            ->merge($rightPanelProducts->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $bestWithoutReserved = $bestSellers
            ->reject(fn (Product $product) => in_array((int) $product->id, $reservedIds, true))
            ->values();

        if ($bestWithoutReserved->count() >= $middlePanelLimit) {
            $middlePanelMode = 'best_sellers';
            $middlePanelProducts = $bestWithoutReserved->take($middlePanelLimit)->values();
        } elseif ($bestSellers->count() >= $middlePanelLimit) {
            $middlePanelMode = 'best_sellers';
            $middlePanelProducts = $bestSellers->take($middlePanelLimit)->values();
        } else {
            $middlePanelMode = 'selection';
            $selectionWithoutReserved = $this->selectionProducts($reservedIds, max($middlePanelLimit * 2, 8));

            $middlePanelProducts = $this->fillCollection(
                $selectionWithoutReserved,
                $this->selectionProducts([], max($middlePanelLimit * 2, 8)),
                $middlePanelLimit,
            );
        }

        $sections = [
            'black-friday' => $blackFridayProducts,
            'flash-sale' => $flashProducts,
            'normal-sale' => $normalProducts,
        ];

        $nosProduits = collect()
            ->concat($flashProducts)
            ->concat($blackFridayProducts)
            ->concat($normalProducts)
            ->unique('id')
            ->values();

        $banners = $this->banners();
        $ads = $this->homeAds();
        $adsByPlacement = $ads->groupBy(fn (HomeAd $ad) => $ad->placement ?: 'home_middle');
        $seasonSale = $adsByPlacement->get('season_sale', collect())->first();
        $heroAd = $adsByPlacement->get('home_top', collect())->first();
        $middleAd = $adsByPlacement->get('home_middle', collect())->first();
        $bottomAd = $adsByPlacement->get('home_bottom', collect())->first();

        return [
            'sections' => $sections,
            'boostedProducts' => $boostedProducts,
            'nosProduits' => $nosProduits,
            'topSellers' => $bestSellers,
            'categories' => $displayCategories,
            'displayCategories' => $displayCategories,
            'homepageCategoryCards' => $homepageCategoryCards,
            'flashProducts' => $flashProducts,
            'bestSellers' => $bestSellers,
            'middlePanelMode' => $middlePanelMode,
            'middlePanelProducts' => $middlePanelProducts,
            'blackFridayProducts' => $blackFridayProducts,
            'latestProducts' => $latestProducts,
            'featuredProducts' => $boostedProducts,
            'promotionProducts' => $promotionProducts,
            'ecoProducts' => $ecoProducts,
            'giftCardGroups' => $giftCardGroups,
            'featuredCategoryTitle' => $featuredCategoryBlock['title'],
            'featuredCategoryDescription' => $featuredCategoryBlock['description'],
            'featuredCategoryProducts' => $featuredCategoryBlock['products'],
            'featuredCategory' => $featuredCategoryBlock['category'],
            'publicProductCount' => $publicProductCount,
            'verifiedSellerCount' => $verifiedSellerCount,
            'flashPanelMode' => $flashPanelMode,
            'flashPanelProducts' => $flashPanelProducts,
            'rightPanelMode' => $rightPanelMode,
            'rightPanelProducts' => $rightPanelProducts,
            'isBlackFridayDay' => $isBlackFridayDay,
            'cartCount' => 0,
            'favoriteProductIds' => [],
            'homeTopBanners' => $banners->get('home_top', collect()),
            'homeMiddleBanners' => $banners->get('home_middle', collect()),
            'homeBottomBanners' => $banners->get('home_bottom', collect()),
            'banners' => $banners->flatten(1)->values(),
            'topAds' => $ads->reject(fn (HomeAd $ad) => ($ad->placement ?? null) === 'season_sale')->values(),
            'seasonSale' => $seasonSale,
            'heroAd' => $heroAd,
            'middleAd' => $middleAd,
            'bottomAd' => $bottomAd,
            'heroBanner' => $banners->get('home_top', collect())->first(),
            'middleBanner' => $banners->get('home_middle', collect())->first(),
            'bottomBanner' => $banners->get('home_bottom', collect())->first(),
            'flashSaleEndsAt' => $this->nearestFlashEnd($flashProducts),
            'flashSaleStartsAt' => $this->nextScheduledFlashStart(),
        ];
    }

    private function flashProducts(): Collection
    {
        if (! Schema::hasColumn('products', 'sale_type')) {
            return collect();
        }

        $query = $this->publicProductsQuery();
        $this->promotions->applyActiveFlash($query);

        if (Schema::hasColumn('products', 'flash_end')) {
            $query->orderBy('flash_end');
        }

        return $query->latest('id')->limit($this->limit('flash'))->get();
    }

    private function blackFridayProducts(): Collection
    {
        if (! Schema::hasColumn('products', 'sale_type') || ! $this->promotions->isBlackFridayDay()) {
            return collect();
        }

        $query = $this->publicProductsQuery();
        $this->promotions->applyFridayBlackFriday($query);

        return $query->latest('id')->limit($this->limit('black_friday'))->get();
    }

    private function normalProducts(): Collection
    {
        $query = $this->publicProductsQuery();

        if (Schema::hasColumn('products', 'sale_type')) {
            $query->where(function (Builder $normal) {
                $normal->whereNull('sale_type')
                    ->orWhere('sale_type', '')
                    ->orWhereRaw('LOWER(sale_type) = ?', ['normal'])
                    ->orWhereRaw('LOWER(sale_type) NOT IN (?, ?)', ['black friday', 'vente flash']);
            });
        }

        return $query->latest('id')->limit($this->limit('normal'))->get();
    }

    private function bestSellers(): Collection
    {
        $limit = $this->limit('best_sellers');
        $rankedProducts = collect();

        if (
            Schema::hasTable('order_items')
            && Schema::hasTable('orders')
            && Schema::hasColumn('order_items', 'order_id')
            && Schema::hasColumn('order_items', 'product_id')
            && Schema::hasColumn('order_items', 'quantity')
        ) {
            $productExpression = Schema::hasColumn('order_items', 'fulfilled_product_id')
                ? 'COALESCE(order_items.fulfilled_product_id, order_items.product_id)'
                : 'order_items.product_id';

            $rankingQuery = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->selectRaw("{$productExpression} as ranked_product_id, SUM(order_items.quantity) as sold_qty")
                ->whereRaw("{$productExpression} IS NOT NULL");

            if (Schema::hasColumn('orders', 'payment_status') || Schema::hasColumn('orders', 'status')) {
                $rankingQuery->where(function ($paid) {
                    $hasPaymentStatus = Schema::hasColumn('orders', 'payment_status');
                    $hasOrderStatus = Schema::hasColumn('orders', 'status');

                    if ($hasPaymentStatus) {
                        $paid->where('orders.payment_status', 'paid');
                    }

                    if ($hasOrderStatus) {
                        $method = $hasPaymentStatus ? 'orWhereIn' : 'whereIn';
                        $paid->{$method}('orders.status', ['paid', 'completed']);
                    }
                });
            }

            $ranking = $rankingQuery
                ->groupBy(DB::raw($productExpression))
                ->orderByDesc('sold_qty')
                ->limit($limit * 3)
                ->pluck('sold_qty', 'ranked_product_id');

            if ($ranking->isNotEmpty()) {
                $order = $ranking->keys()->values();
                $positions = $order->flip();

                $rankedProducts = $this->publicProductsQuery()
                    ->whereIn('products.id', $order->all())
                    ->get()
                    ->each(function (Product $product) use ($ranking) {
                        $product->setAttribute('homepage_sales_count', (int) ($ranking[$product->id] ?? 0));
                    })
                    ->sortBy(fn (Product $product) => $positions[$product->id] ?? PHP_INT_MAX)
                    ->take($limit)
                    ->values();
            }
        }

        if ($rankedProducts->count() >= $limit) {
            return $rankedProducts;
        }

        if (! Schema::hasColumn('products', 'sales')) {
            return $rankedProducts->values();
        }

        $fallback = $this->publicProductsQuery()
            ->whereNotIn('products.id', $rankedProducts->pluck('id')->all())
            ->where('sales', '>', 0)
            ->orderByDesc('sales')
            ->latest('id')
            ->limit($limit - $rankedProducts->count())
            ->get();

        return $rankedProducts->concat($fallback)->take($limit)->values();
    }

    private function latestProducts(): Collection
    {
        $limit = $this->limit('latest');

        return $this->publicProductsQuery()
            // Un produit n'est une nouveauté que pendant ses sept premiers jours.
            ->where('products.created_at', '>=', now()->subDays(7))
            ->latest('products.created_at')
            ->latest('products.id')
            ->get()
            ->reject(fn (Product $product) => $this->promotions->isFlashActive($product) || $this->promotions->isBlackFridayActive($product))
            ->take($limit)
            ->values();
    }

    private function promotionProducts(): Collection
    {
        if (! Schema::hasColumn('products', 'promo_price')) {
            return collect();
        }

        return $this->publicProductsQuery()
            ->whereNotNull('promo_price')
            ->where('promo_price', '>', 0)
            ->whereColumn('promo_price', '<', 'price')
            ->latest('id')
            ->limit(max($this->limit('normal') * 3, 24))
            ->get()
            ->filter(fn (Product $product) => (bool) $product->is_on_promo)
            ->take($this->limit('normal'))
            ->values();
    }

    private function ecoProducts(): Collection
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        return $this->publicProductsQuery()
            ->whereHas('category', function (Builder $category) {
                $category->where(function (Builder $eco) {
                    $eco->whereRaw('LOWER(slug) LIKE ?', ['%ecolog%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%écolog%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%ecolog%']);
                })->orWhereHas('parent', function (Builder $parent) {
                    $parent->whereRaw('LOWER(slug) LIKE ?', ['%ecolog%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%écolog%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%ecolog%']);
                });
            })
            ->orderByDesc(Schema::hasColumn('products', 'sales') ? 'sales' : 'id')
            ->latest('id')
            ->limit(4)
            ->get()
            ->values();
    }

    private function displayCategories(): Collection
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        $products = $this->publicProductsQuery()
            ->with(['category.parent'])
            ->limit(400)
            ->get();

        $roots = [];

        foreach ($products as $product) {
            $category = $product->category;

            if (! $category) {
                continue;
            }

            $root = $category->parent ?: $category;
            $rootId = (int) $root->id;

            if (! isset($roots[$rootId])) {
                $roots[$rootId] = [
                    'category' => $root,
                    'product_count' => 0,
                ];
            }

            $roots[$rootId]['product_count']++;
        }

        return collect($roots)
            ->sortBy([
                fn (array $item) => -1 * $item['product_count'],
                fn (array $item) => (int) ($item['category']->sort_order ?? 9999),
                fn (array $item) => mb_strtolower((string) $item['category']->name),
            ])
            ->take(8)
            ->map(function (array $item) {
                /** @var Category $category */
                $category = $item['category'];
                $category->setAttribute('homepage_product_count', (int) $item['product_count']);

                return $category;
            })
            ->values();
    }

    /**
     * Cartes de catégories de l'accueil.
     *
     * Demande utilisateur : les catégories affichées à l'accueil doivent venir
     * de la base (table categories) et une nouvelle catégorie créée dans
     * l'admin doit y apparaître automatiquement, sans liste figée dans le
     * code. On prend donc simplement les catégories principales actives,
     * dans l'ordre configuré par l'admin, au lieu de faire correspondre les
     * produits à une liste de 8 catégories/mots-clés codés en dur.
     *
     * Chaque visuel provient en priorité de la photo importée pour la
     * catégorie (Category::image_url), puis d'un vrai produit public de
     * cette catégorie pour éviter les illustrations génériques.
     */
    private function homepageCategoryCards(): Collection
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        $roots = Category::query()
            ->active()
            ->roots()
            ->ordered()
            ->limit(8)
            ->get();

        if ($roots->isEmpty()) {
            return collect();
        }

        $childIdsByParent = Category::query()
            ->select(['id', 'parent_id'])
            ->whereIn('parent_id', $roots->pluck('id'))
            ->get()
            ->groupBy('parent_id');

        $findRepresentativeProduct = function (Category $root) use ($childIdsByParent): ?Product {
            $ids = collect([$root->id])
                ->merge($childIdsByParent->get($root->id, collect())->pluck('id'))
                ->all();

            $query = $this->publicProductsQuery()->whereIn('products.category_id', $ids);

            if (Schema::hasColumn('products', 'sales')) {
                $query->orderByDesc('sales');
            }

            if (Schema::hasColumn('products', 'views')) {
                $query->orderByDesc('views');
            }

            return $query->latest('products.id')->first();
        };

        return $roots
            ->map(fn (Category $root) => [
                'name' => $root->name,
                'slug' => (string) $root->slug,
                'category' => $root,
                'product' => $findRepresentativeProduct($root),
                'asset' => null,
            ])
            ->values();
    }

    private function featuredCategoryBlock(Collection $displayCategories, Collection $ecoProducts): array
    {
        if ($ecoProducts->isNotEmpty()) {
            return [
                'title' => 'Matériaux écologiques',
                'description' => 'Construisons aujourd’hui pour un avenir durable. Découvrez une sélection de matériaux responsables et performants.',
                'products' => $ecoProducts->take(4)->values(),
                'category' => $this->ecoRootCategory(),
            ];
        }

        foreach ($displayCategories as $category) {
            $products = $this->productsForRootCategory($category, 4);

            if ($products->isNotEmpty()) {
                return [
                    'title' => $category->name,
                    'description' => 'Retrouvez dans cette sélection les produits disponibles actuellement dans cette catégorie.',
                    'products' => $products,
                    'category' => $category,
                ];
            }
        }

        return [
            'title' => 'Sélection OVANIE',
            'description' => 'Retrouvez les produits actuellement disponibles sur la plateforme.',
            'products' => $this->selectionProducts([], 4),
            'category' => null,
        ];
    }

    private function ecoRootCategory(): ?Category
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        return Category::query()
            ->where(function (Builder $query) {
                $query->whereRaw('LOWER(slug) LIKE ?', ['%ecolog%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%écolog%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%ecolog%']);
            })
            ->orderBy('sort_order')
            ->first();
    }

    private function productsForRootCategory(Category $category, int $limit = 4): Collection
    {
        return $this->publicProductsQuery()
            ->whereHas('category', function (Builder $query) use ($category) {
                $query->where('categories.id', $category->id)
                    ->orWhere('categories.parent_id', $category->id);
            })
            ->orderByDesc(Schema::hasColumn('products', 'sales') ? 'sales' : 'id')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->values();
    }

    private function giftCardGroups(): Collection
    {
        if (! Schema::hasTable('gift_card_products')) {
            return $this->fallbackGiftCardGroups();
        }

        $query = GiftCardProduct::query();

        if (Schema::hasColumn('gift_card_products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('gift_card_products', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        $cards = $query->get();

        if ($cards->isEmpty()) {
            return $this->fallbackGiftCardGroups();
        }

        $groups = collect([
            [
                'key' => 'bon-achat',
                'title' => "Bon d'Achat",
                'description' => 'Idéal pour récompenser vos équipes ou partenaires professionnels.',
                'cards' => $cards->filter(fn (GiftCardProduct $card) => str_starts_with((string) $card->slug, 'bon-achat-')),
            ],
            [
                'key' => 'carte-cadeau',
                'title' => 'Carte Cadeau',
                'description' => 'Faites plaisir à vos proches avec la carte cadeau OVANIE.',
                'cards' => $cards->filter(fn (GiftCardProduct $card) => str_starts_with((string) $card->slug, 'carte-cadeau-')),
            ],
            [
                'key' => 'carte-virtuelle',
                'title' => 'Carte Virtuelle',
                'description' => 'Instantanée et envoyée par e-mail. Utilisable sur tout le site.',
                'cards' => $cards->filter(fn (GiftCardProduct $card) => (bool) $card->is_rechargeable),
            ],
        ]);

        return $groups
            ->map(function (array $group) {
                /** @var Collection $items */
                $items = $group['cards']->values();

                if ($items->isEmpty()) {
                    return null;
                }

                /** @var GiftCardProduct $representative */
                $representative = $items->first();
                $prices = $items->pluck('activation_price')->filter(fn ($price) => $price !== null && $price !== '');

                return [
                    'key' => $group['key'],
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'count' => $items->count(),
                    'min_price' => $prices->isNotEmpty() ? (float) $prices->min() : null,
                    'image_path' => $representative->image_path,
                    'representative_id' => $representative->id,
                    'representative_slug' => $representative->slug,
                ];
            })
            ->filter()
            ->values();
    }

    private function fallbackGiftCardGroups(): Collection
    {
        return collect([
            [
                'key' => 'bon-achat',
                'title' => "Bon d'Achat",
                'description' => 'Idéal pour récompenser vos équipes ou partenaires professionnels.',
                'count' => 3,
                'min_price' => 50000,
                'image_path' => null,
                'representative_slug' => 'bon-achat-50k',
            ],
            [
                'key' => 'carte-cadeau',
                'title' => 'Carte Cadeau',
                'description' => 'Faites plaisir à vos proches avec la carte cadeau OVANIE.',
                'count' => 4,
                'min_price' => 100000,
                'image_path' => null,
                'representative_slug' => 'carte-cadeau-100k',
            ],
            [
                'key' => 'carte-virtuelle',
                'title' => 'Carte Virtuelle',
                'description' => 'Instantanée et envoyée par e-mail. Utilisable sur tout le site.',
                'count' => 3,
                'min_price' => 150000,
                'image_path' => null,
                'representative_slug' => 'carte-acces-150k',
            ],
        ]);
    }

    private function publicProductCount(): int
    {
        return (int) $this->visibility->query([])->count();
    }

    private function verifiedSellerCount(): int
    {
        if (! Schema::hasTable('shops')) {
            return 0;
        }

        $query = Shop::query();

        if (
            Schema::hasColumn('shops', 'logistics_status')
            && Schema::hasColumn('shops', 'logistics_type')
            && Schema::hasColumn('shops', 'status')
            && Schema::hasColumn('shops', 'is_active')
        ) {
            return (int) $query->canPublishProducts()->count();
        }

        if (Schema::hasColumn('shops', 'status')) {
            $query->whereIn('status', ['approved', 'active', 'actif']);
        }

        if (Schema::hasColumn('shops', 'is_active')) {
            $query->where(function (Builder $active) {
                $active->whereNull('is_active')->orWhere('is_active', true);
            });
        }

        return (int) $query->count();
    }

    private function featuredProducts(): Collection
    {
        $query = $this->publicProductsQuery();
        $this->promotions->applyFeatured($query);

        if (Schema::hasColumn('products', 'boost_priority')) {
            $query->orderByDesc('boost_priority');
        }

        return $query->latest('id')->limit($this->limit('featured'))->get();
    }

    private function selectionProducts(array $excludedIds, int $limit, bool $excludeCommercialCampaigns = false): Collection
    {
        $query = $this->publicProductsQuery()
            ->when($excludedIds !== [], fn (Builder $q) => $q->whereNotIn('products.id', $excludedIds));

        if ($excludeCommercialCampaigns) {
            $this->promotions->excludeCommercialCampaigns($query);
        }

        if (Schema::hasColumn('products', 'views')) {
            $query->orderByDesc('views');
        }

        if (Schema::hasColumn('products', 'sales')) {
            $query->orderByDesc('sales');
        }

        return $query->latest('id')->limit(max($limit, 1))->get()->values();
    }

    private function fillCollection(Collection $primary, Collection $fallback, int $limit): Collection
    {
        return $primary
            ->concat($fallback)
            ->filter(fn ($product) => $product instanceof Product)
            ->unique(fn (Product $product) => (int) $product->id)
            ->take(max(1, $limit))
            ->values();
    }

    private function publicProductsQuery(): Builder
    {
        $query = $this->visibility->query(['category', 'shop', 'images']);

        if (Schema::hasTable('reviews')) {
            $query->withCount('reviews');

            if (Schema::hasColumn('reviews', 'rating')) {
                $query->withAvg('reviews', 'rating');
            }
        }

        return $query;
    }

    private function banners(): Collection
    {
        if (! Schema::hasTable('banners')) {
            return collect();
        }

        $query = Banner::query();

        if (Schema::hasColumn('banners', 'is_active')) {
            $query->where('is_active', 1);
        }

        if (Schema::hasColumn('banners', 'position')) {
            $query->orderBy('position');
        }

        $items = $query->limit($this->limit('banners'))->get();

        if (! Schema::hasColumn('banners', 'zone')) {
            return collect(['home_middle' => $items]);
        }

        return $items->groupBy(fn (Banner $banner) => $banner->zone ?: 'home_middle');
    }

    private function homeAds(): Collection
    {
        if (! Schema::hasTable('home_ads')) {
            return collect();
        }

        $query = HomeAd::query();

        if (Schema::hasColumn('home_ads', 'is_active')) {
            $query->where('is_active', 1);
        }

        if (Schema::hasColumn('home_ads', 'status')) {
            $query->where(function (Builder $status) {
                $status->whereNull('status')->orWhereIn('status', ['actif', 'active', '1', 1]);
            });
        }

        if (Schema::hasColumn('home_ads', 'starts_at')) {
            $query->where(function (Builder $window) {
                $window->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
        }

        if (Schema::hasColumn('home_ads', 'ends_at')) {
            $query->where(function (Builder $window) {
                $window->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        }

        if (Schema::hasColumn('home_ads', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->latest('id')->limit($this->limit('home_ads'))->get();
    }

    private function cartCount(?User $user): int
    {
        if (! $user || ! Schema::hasTable('carts') || ! Schema::hasTable('cart_items')) {
            return 0;
        }

        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->withSum('items as quantity_total', 'quantity')
            ->first();

        return (int) ($cart?->quantity_total ?? 0);
    }

    private function favoriteProductIds(?User $user, Collection $visibleProductIds): array
    {
        if (! $user || $visibleProductIds->isEmpty() || ! Schema::hasTable('favorites')) {
            return [];
        }

        return $user->favoriteProducts()
            ->whereIn('products.id', $visibleProductIds->all())
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function nearestFlashEnd(Collection $flashProducts): ?string
    {
        $now = now();

        $endsAt = $flashProducts
            ->pluck('flash_end')
            ->filter(fn ($date) => $date && $date->isAfter($now))
            ->sortBy(fn ($date) => $date->getTimestamp())
            ->first();

        return $endsAt?->toIso8601String();
    }

    private function nextScheduledFlashStart(): ?string
    {
        if (! Schema::hasColumn('products', 'sale_type') || ! Schema::hasColumn('products', 'flash_start_at')) {
            return null;
        }

        $query = $this->publicProductsQuery()
            ->whereIn(DB::raw('LOWER(sale_type)'), ['vente flash', 'vente_flash', 'flash sale', 'flash_sale', 'flash'])
            ->whereNotNull('flash_start_at')
            ->where('flash_start_at', '>', $this->promotions->now());

        if (Schema::hasColumn('products', 'flash_end')) {
            $query->whereNotNull('flash_end')->whereColumn('flash_end', '>', 'flash_start_at');
        }

        $start = $query->min('flash_start_at');

        return $start ? \Illuminate\Support\Carbon::parse($start)->toIso8601String() : null;
    }

    private function limit(string $key): int
    {
        return max(1, (int) config("homepage.limits.{$key}", 12));
    }

    private function emptyPayload(): array
    {
        $empty = collect();

        return [
            'sections' => [
                'black-friday' => $empty,
                'flash-sale' => $empty,
                'normal-sale' => $empty,
            ],
            'boostedProducts' => $empty,
            'nosProduits' => $empty,
            'topSellers' => $empty,
            'categories' => $empty,
            'displayCategories' => $empty,
            'homepageCategoryCards' => $empty,
            'flashProducts' => $empty,
            'bestSellers' => $empty,
            'middlePanelMode' => 'selection',
            'middlePanelProducts' => $empty,
            'blackFridayProducts' => $empty,
            'latestProducts' => $empty,
            'featuredProducts' => $empty,
            'promotionProducts' => $empty,
            'ecoProducts' => $empty,
            'giftCardGroups' => $this->fallbackGiftCardGroups(),
            'featuredCategoryTitle' => 'Sélection OVANIE',
            'featuredCategoryDescription' => 'Retrouvez les produits disponibles sur la plateforme.',
            'featuredCategoryProducts' => $empty,
            'featuredCategory' => null,
            'publicProductCount' => 0,
            'verifiedSellerCount' => 0,
            'flashPanelMode' => 'latest',
            'flashPanelProducts' => $empty,
            'rightPanelMode' => 'discovery',
            'rightPanelProducts' => $empty,
            'isBlackFridayDay' => false,
            'cartCount' => 0,
            'favoriteProductIds' => [],
            'homeTopBanners' => $empty,
            'homeMiddleBanners' => $empty,
            'homeBottomBanners' => $empty,
            'banners' => $empty,
            'topAds' => $empty,
            'seasonSale' => null,
            'heroAd' => null,
            'middleAd' => null,
            'bottomAd' => null,
            'heroBanner' => null,
            'middleBanner' => null,
            'bottomBanner' => null,
            'flashSaleEndsAt' => null,
            'flashSaleStartsAt' => null,
        ];
    }
}
