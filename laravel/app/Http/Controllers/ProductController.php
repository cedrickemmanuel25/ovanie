<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\GiftCardProduct;
use App\Models\RecentlyViewedProduct;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryService;
use App\Models\Negotiation;
use App\Services\HomepageService;
use App\Services\CommissionService;
use App\Services\ProductCalculatorService;
use App\Services\ProductSheetPresenter;
use App\Services\PromotionVisibilityService;
use App\Services\PublicProductVisibilityService;
use App\Services\SearchService;
use App\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private ?array $userFavoriteProductIds = null;

    public function __construct(
        private readonly PromotionVisibilityService $promotions,
        private readonly PublicProductVisibilityService $visibility,
        private readonly ProductSheetPresenter $productSheet,
    ) {
    }

    public function index(Request $request)
    {
        if ($this->isGiftCardCatalogRequest($request)) {
            return response()->json($this->giftCardCatalogPayload($request));
        }

        $query = $this->buildCatalogQuery($request);
        $this->applyCatalogSorting($query, $request->query('sort', 'popular'), $request);

        $perPage = $this->safePerPage($request->query('per_page'));
        $products = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $products->getCollection()->map(fn ($p) => $this->serializeProduct($p))->values(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ]);
    }

    public function home(HomepageService $homepageService)
    {
        if (app()->runningUnitTests() && ! Schema::hasTable('products')) {
            return response('OK', 200);
        }

        return view('public.home', $homepageService->build(auth()->user()));
    }

    public function search(Request $request, SearchService $searchService)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:160'],
        ]);

        $term = trim((string) ($validated['search'] ?? ''));
        $category = $validated['category'] ?? null;

        if ($term !== '') {
            $searchService->recordSearch($term, $category);
        }

        $params = array_filter([
            'search' => $term !== '' ? $term : null,
            'category' => $category ?: null,
        ]);

        $url = route('catalog.index');

        return redirect($params ? $url . '?' . http_build_query($params) : $url);
    }

    public function boostClick(Product $product)
    {
        if ($product->is_boost_active) {
            $product->increment('boost_clicks');
        }

        // [MODIFIÉ] Utilise $product->slug au lieu de $product->id
        // car la route 'product.show' utilise maintenant le slug
        // comme clé de liaison (voir Product::getRouteKeyName()).
        return redirect()->route('product.show', $product->slug);
    }

    public function catalog(Request $request, $category = null)
    {
        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            if ($this->isGiftCardCatalogRequest($request, $category)) {
                return response()->json($this->giftCardCatalogPayload($request));
            }

            $query = $this->buildCatalogQuery($request, $category);
            $this->applyCatalogSorting($query, $request->query('sort', 'popular'), $request);

            $perPage = $this->safePerPage($request->query('per_page'));
            $products = $query->paginate($perPage)->appends($request->query());

            return response()->json([
                'data' => $products->getCollection()->map(fn ($p) => $this->serializeProduct($p))->values(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]);
        }

        return view('catalog.index', [
            'category' => $category,
            'categories' => $this->getCatalogFilterCategories(),
            'offerStats' => $this->getCatalogOfferStats(),
        ]);
    }

    public function categoryPage(Request $request, string $category)
    {
        $catalogSlugs = [
            'materiaux-gros-oeuvre' => 'materiaux-gros-oeuvre',
            'materiaux-de-finition' => 'materiaux-de-finition',
            'outillage-equipement' => 'outillage-equipement',
            'electricite-plomberie' => 'electricite-plomberie',
            'energie-solaire' => 'energie-solaire',
            'materiaux-ecologiques' => 'materiaux-ecologiques',
            'reconditionnes' => 'nos-reconditionnes',
        ];

        abort_unless(isset($catalogSlugs[$category]), 404);

        return view('catalog.index', [
            'category' => $catalogSlugs[$category],
            'categoryPageSlug' => $category,
            'categories' => $this->getCatalogFilterCategories(),
            'offerStats' => $this->getCatalogOfferStats(),
        ]);
    }

    public function curatedPage(Request $request, string $selection)
    {
        abort_unless(in_array($selection, ['best-sellers', 'new-arrivals'], true), 404);

        return view('catalog.index', [
            'category' => null,
            'listingPageType' => $selection,
            'categories' => $this->getCatalogFilterCategories(),
            'offerStats' => $this->getCatalogOfferStats(),
        ]);
    }

    public function apiIndex(Request $request)
    {
        $type = strtolower($request->query('type', 'normal-sale'));
        $limit = max(1, min(100, (int) $request->query('limit', 24)));

        $query = Product::with(['images', 'category']);
        $this->visibility->apply($query);

        switch ($type) {
            case 'black-friday':
                $this->promotions->applyFridayBlackFriday($query);
                break;

            case 'flash-sale':
                $this->promotions->applyActiveFlash($query);
                break;

            case 'normal-sale':
            default:
                $query->where(function ($q) {
                    $q->whereNull('sale_type')
                        ->orWhere('sale_type', '')
                        ->orWhereRaw('LOWER(sale_type) = ?', ['normal'])
                        ->orWhereRaw('LOWER(sale_type) = ?', ['promotion']);

                    // Hors vendredi, les produits inscrits à Black Friday restent
                    // achetables normalement ; seul le prix événementiel est désactivé.
                    if (! $this->promotions->isBlackFridayDay()) {
                        $q->orWhereIn(DB::raw('LOWER(sale_type)'), ['black friday', 'black_friday']);
                    }
                });
                break;
        }

        $products = $query->latest()->take($limit)->get();

        return response()->json($products->map(fn($p) => $this->serializeProduct($p)));
    }

    public function myProducts()
    {
        $products = auth()->user()
            ->products()
            ->with(['category', 'images'])
            ->latest()
            ->get();

        return view('vendor.products', compact('products'));
    }

    public function negotiate(Request $request, Product $product)
    {
        $request->validate(['offer' => 'required|numeric|min:1']);

        $offer = $request->offer;
        $minAcceptable = $product->price * 0.9;

        if ($offer >= $product->price) {
            $status = 'accepted';
            $finalPrice = $product->price;
            $counterPrice = null;
        } elseif ($offer >= $minAcceptable) {
            $status = 'counter';
            $finalPrice = null;
            $counterPrice = $product->price;
        } else {
            $status = 'rejected';
            $finalPrice = null;
            $counterPrice = null;
        }

        return response()->json(compact('status', 'finalPrice', 'counterPrice', 'minAcceptable'));
    }

    public function notifyVendor(Product $product)
    {
        if (!$product->shop || !$product->shop->mm_number) {
            return response()->json(['message' => 'Aucune boutique ou numéro trouvé'], 400);
        }

        SendSmsJob::dispatch(
            $product->shop->mm_number,
            "OVANIE : Votre produit {$product->name} a été publié."
        );

        return response()->json(['success' => true]);
    }

    /**
     * Le catalogue BTP lit normalement la table `products`.
     * Les cartes OVANIE sont volontairement stockées dans `gift_card_products`
     * car elles ont une logique de solde/PIN/recharge différente d'un produit physique.
     * Cette passerelle permet néanmoins de les afficher dans /catalog lorsque
     * la catégorie "carte-cadeau-ovanie" est sélectionnée.
     */
    private function isGiftCardCatalogRequest(Request $request, ?string $routeCategory = null): bool
    {
        $categories = collect($this->requestValues($request, 'category'));

        if ($routeCategory) {
            $categories->push($routeCategory);
        }

        $aliases = [
            'carte-cadeau-ovanie',
            'cartes-cadeaux-ovanie',
            'carte-cadeau',
            'cartes-cadeaux',
        ];

        return $categories
            ->map(fn ($value) => $this->normalizedValue((string) $value))
            ->contains(fn ($value) => in_array($value, $aliases, true));
    }

    private function giftCardCatalogPayload(Request $request): array
    {
        if (! Schema::hasTable('gift_card_products')) {
            return [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $this->safePerPage($request->query('per_page')),
                'total' => 0,
            ];
        }

        $query = GiftCardProduct::query()
            ->where('is_active', true)
            ->whereIn('slug', [
                'bon-achat-50k',
                'bon-achat-150k',
                'bon-achat-200k',
                'carte-cadeau-100k',
                'carte-cadeau-200k',
                'carte-cadeau-300k',
                'carte-cadeau-400k',
                'carte-acces-150k',
                'carte-premium-250k',
                'carte-gold-450k',
            ]);

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        $minPrice = $request->query('min_price');
        if ($minPrice !== null && $minPrice !== '' && is_numeric($minPrice)) {
            $query->where('activation_price', '>=', (float) $minPrice);
        }

        $maxPrice = $request->query('max_price');
        if ($maxPrice !== null && $maxPrice !== '' && is_numeric($maxPrice)) {
            $query->where('activation_price', '<=', (float) $maxPrice);
        }

        switch ((string) $request->query('sort', 'popular')) {
            case 'price_asc':
                $query->orderBy('activation_price')->orderBy('sort_order');
                break;
            case 'price_desc':
                $query->orderByDesc('activation_price')->orderBy('sort_order');
                break;
            case 'recent':
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
            case 'availability':
            case 'popular':
            default:
                $query->orderBy('sort_order')->orderBy('id');
                break;
        }

        $perPage = $this->safePerPage($request->query('per_page'));
        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $cards = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (GiftCardProduct $card) => $this->serializeGiftCardForCatalog($card))
            ->values();

        return [
            'data' => $cards,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    private function serializeGiftCardForCatalog(GiftCardProduct $card): array
    {
        $labels = [
            'bon-achat-50k' => ['Bon d’Achat', 'Bon d’Achat OVANIE 50 000 FCFA'],
            'bon-achat-150k' => ['Bon d’Achat', 'Bon d’Achat OVANIE 150 000 FCFA'],
            'bon-achat-200k' => ['Bon d’Achat', 'Bon d’Achat OVANIE 200 000 FCFA'],
            'carte-cadeau-100k' => ['Carte Cadeau', 'Carte Cadeau OVANIE 100 000 FCFA'],
            'carte-cadeau-200k' => ['Carte Cadeau', 'Carte Cadeau OVANIE 200 000 FCFA'],
            'carte-cadeau-300k' => ['Carte Cadeau', 'Carte Cadeau OVANIE 300 000 FCFA'],
            'carte-cadeau-400k' => ['Carte Cadeau', 'Carte Cadeau OVANIE 400 000 FCFA'],
            'carte-acces-150k' => ['Carte Virtuelle', 'Carte Virtuelle OVANIE ACCÈS'],
            'carte-premium-250k' => ['Carte Virtuelle', 'Carte Virtuelle OVANIE PREMIUM'],
            'carte-gold-450k' => ['Carte Virtuelle', 'Carte Virtuelle OVANIE GOLD'],
        ];

        [$type, $publicName] = $labels[$card->slug] ?? ['Carte OVANIE', $card->name];
        $imageUrl = $card->image_path ? url('/' . ltrim($card->image_path, '/')) : url('/images/product-placeholder.svg');

        return [
            'id' => 'gift-card-' . $card->id,
            'slug' => $card->slug,
            'url' => route('gift-cards.show', $card),
            'name' => $publicName,
            'short_description' => $card->description,
            'is_favorite' => false,
            'price' => (int) round((float) $card->activation_price),
            'promo_price' => null,
            'base_price' => (int) round((float) $card->activation_price),
            'commission' => 0,
            'final_price' => (int) round((float) $card->activation_price),
            'original_public_price' => (int) round((float) $card->activation_price),
            'sale_type' => 'gift_card',
            'is_flash_active' => false,
            'is_black_friday_active' => false,
            'is_promo_active' => false,
            'is_negotiable' => false,
            'unit' => 'carte',
            'unit_label' => 'carte digitale',
            'display_unit' => 'carte digitale',
            'packaging' => null,
            'brand' => 'OVANIE',
            'is_boost_active' => false,
            'free_boosted_by_ovanie' => false,
            'category' => $type,
            'category_name' => $type,
            'category_slug' => 'carte-cadeau-ovanie',
            'category_id' => Category::query()
                ->where('slug', 'carte-cadeau-ovanie')
                ->value('id'),
            'stock' => 999999,
            'min_order_quantity' => 1,
            'availability_status' => 'in_stock',
            'availability_label' => 'Disponible immédiatement',
            'is_orderable' => false,
            'can_add_to_cart' => false,
            'sales' => 0,
            'fast_delivery' => false,
            'average_rating' => null,
            'rating' => null,
            'reviews_count' => 0,
            'seller_label' => 'Émis par OVANIE',
            'public_partner_label' => 'Carte officielle OVANIE',
            'image' => $imageUrl,
            'main_image_url' => $imageUrl,
            'card_image_url' => $imageUrl,
            'thumb_image_url' => $imageUrl,
            'gallery' => [$imageUrl],
            'images' => [],
            'created_at' => optional($card->created_at)->toDateTimeString(),
            'tags' => ['carte-ovanie'],
            'is_gift_card' => true,
            'gift_card_type' => $type,
            'gift_card_rechargeable' => (bool) $card->is_rechargeable,
            'gift_card_validity_days' => $card->validity_days,
            'gift_card_validity_months' => $card->validity_months,
        ];
    }

    private function buildCatalogQuery(Request $request, ?string $routeCategory = null)
    {
        // La réponse publique du catalogue ne charge pas la boutique :
        // l'identité et la localisation du vendeur restent privées.
        $query = Product::with(['category', 'images']);
        $this->applyPublicProductConstraints($query);

        if (Schema::hasTable('reviews')) {
            $query->withCount('reviews');

            if (Schema::hasColumn('reviews', 'rating')) {
                $query->withAvg('reviews', 'rating');
            }
        }

        $this->applyCategoryFilter($query, $request, $routeCategory);
        $this->applySearchFilter($query, $request);
        $this->applyBrandFilter($query, $request);
        $this->applyOfferFilter($query, $request);
        $this->applyStockFilter($query, $request);
        $this->applyPriceFilter($query, $request);
        $this->applyUnitFilter($query, $request);
        $this->applyRatingFilter($query, $request);

        return $query;
    }

    private function applyPublicProductConstraints($query): void
    {
        $this->visibility->apply($query, true);
    }

    private function requestValues(Request $request, string $key): array
    {
        $value = $request->query($key, $request->input($key, []));

        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => is_string($item) ? explode(',', $item) : [$item])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replace('_', '-')
            ->replaceMatches('/\s+/', '-')
            ->trim('-')
            ->toString();
    }

    private function applyCategoryFilter($query, Request $request, ?string $routeCategory = null): void
    {
        /*
         * Une catégorie/sous-catégorie est un choix EXCLUSIF dans le catalogue.
         *
         * Ancien comportement :
         * - toutes les valeurs ?category=... étaient fusionnées ;
         * - sur /categories/{parent}, le parent était ajouté au choix de la
         *   sous-catégorie ;
         * - le résultat devenait un filtre multiple incohérent.
         *
         * Nouveau comportement :
         * - la dernière catégorie explicitement demandée gagne ;
         * - à défaut, la catégorie portée par la route est utilisée ;
         * - une catégorie principale inclut ses sous-catégories ;
         * - une sous-catégorie ne retourne que ses propres produits.
         */
        $requestedCategories = collect($this->requestValues($request, 'category'))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        $selectedCategory = $requestedCategories->last();

        if (! $selectedCategory && $routeCategory) {
            $selectedCategory = trim((string) $routeCategory);
        }

        if (! $selectedCategory) {
            return;
        }

        $selectedCategory = mb_strtolower($selectedCategory);
        $normalizedSelected = $this->normalizedValue($selectedCategory);

        $reconditionedAliases = collect([
            'reconditionnes',
            'reconditionne',
            'nos-reconditionnes',
            'nos-reconditionnee',
        ]);

        $wantsReconditioned = $reconditionedAliases->contains($normalizedSelected);
        $categoryIds = collect();

        if (Schema::hasTable('categories')) {
            $matchedCategory = Category::query()
                ->where(function ($categoryQuery) use ($selectedCategory, $normalizedSelected) {
                    $categoryQuery
                        ->whereRaw('LOWER(slug) = ?', [$selectedCategory])
                        ->orWhereRaw('LOWER(slug) = ?', [$normalizedSelected])
                        ->orWhereRaw('LOWER(name) = ?', [str_replace('-', ' ', $selectedCategory)]);
                })
                ->first();

            if ($matchedCategory) {
                $categoryIds->push((int) $matchedCategory->id);

                // Une catégorie principale représente son univers complet :
                // produits historiques encore rattachés au parent + produits
                // déjà classés dans ses sous-catégories.
                if (
                    Schema::hasColumn('categories', 'parent_id')
                    && $matchedCategory->parent_id === null
                ) {
                    $childIds = Category::query()
                        ->where('parent_id', $matchedCategory->id)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id);

                    $categoryIds = $categoryIds->merge($childIds);
                }

                $categoryIds = $categoryIds->unique()->values();
            }
        }

        $query->where(function ($categoryFilter) use (
            $selectedCategory,
            $normalizedSelected,
            $categoryIds,
            $wantsReconditioned
        ) {
            if ($wantsReconditioned && Schema::hasColumn('products', 'product_state')) {
                $categoryFilter->orWhere('product_state', 'reconditioned');
            }

            if ($categoryIds->isNotEmpty()) {
                $categoryFilter->orWhereIn('category_id', $categoryIds);
            }

            // Compatibilité avec une ancienne base où la catégorie peut être
            // retrouvée par son nom/slug mais où aucun ID n'a été résolu.
            if ($categoryIds->isEmpty() && ! $wantsReconditioned) {
                $categoryFilter->orWhereHas('category', function ($categoryQuery) use (
                    $selectedCategory,
                    $normalizedSelected
                ) {
                    $categoryQuery
                        ->whereRaw('LOWER(slug) = ?', [$selectedCategory])
                        ->orWhereRaw('LOWER(slug) = ?', [$normalizedSelected])
                        ->orWhereRaw('LOWER(name) = ?', [str_replace('-', ' ', $selectedCategory)]);
                });
            }
        });
    }

    private function applySearchFilter($query, Request $request): void
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            if (Schema::hasColumn('products', 'name')) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            }

            if (Schema::hasColumn('products', 'slug')) {
                $q->orWhereRaw('LOWER(slug) LIKE ?', ["%{$search}%"]);
            }

            foreach (['description', 'short_description', 'brand', 'material_grade', 'usage_area', 'packaging'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $q->orWhereRaw("LOWER({$column}) LIKE ?", ["%{$search}%"]);
                }
            }

            $q->orWhereHas('category', function ($categoryQuery) use ($search) {
                $categoryQuery->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$search}%"]);
            });

            // Une recherche sur une catégorie principale doit aussi retrouver
            // les produits rangés dans ses sous-catégories.
            $q->orWhereHas('category.parent', function ($parentCategoryQuery) use ($search) {
                $parentCategoryQuery->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$search}%"]);
            });
        });
    }

    private function applyBrandFilter($query, Request $request): void
    {
        if (! Schema::hasColumn('products', 'brand')) {
            return;
        }

        $brands = collect($this->requestValues($request, 'brand'))
            ->map(fn ($item) => mb_strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values();

        if ($brands->isEmpty()) {
            return;
        }

        $query->where(function ($brandQuery) use ($brands) {
            foreach ($brands as $brand) {
                $brandQuery->orWhereRaw('LOWER(brand) = ?', [$brand]);
            }
        });
    }

    private function applyOfferFilter($query, Request $request): void
    {
        $offers = collect($this->requestValues($request, 'offer'))
            ->merge($this->requestValues($request, 'type'))
            ->map(fn ($item) => $this->normalizedValue((string) $item))
            ->filter()
            ->unique()
            ->values();

        if ($offers->isEmpty()) {
            return;
        }

        $query->where(function ($q) use ($offers) {
            foreach ($offers as $offer) {
                match ($offer) {
                    'promo', 'promotion', 'promotions' => $this->orWherePromo($q),
                    'vente-flash', 'flash-sale', 'flash' => $q->orWhere(function ($flash) { $this->promotions->applyActiveFlash($flash); }),
                    'black-friday' => $q->orWhere(function ($blackFriday) { $this->promotions->applyFridayBlackFriday($blackFriday); }),
                    'top', 'top-vente', 'top-ventes', 'best-seller' => Schema::hasColumn('products', 'sales') ? $q->orWhere('sales', '>=', 5) : null,
                    'new', 'nouveau', 'nouveautes', 'nouveaute' => $q->orWhere('created_at', '>=', now()->subDays(14)),
                    'boosted', 'a-la-une' => $this->orWhereBoosted($q),
                    'normal', 'vente-normale', 'normal-sale' => $q->orWhere(function ($normalQuery) {
                        if (Schema::hasColumn('products', 'sale_type')) {
                            $normalQuery->whereNull('sale_type')
                                ->orWhere('sale_type', '')
                                ->orWhereRaw('LOWER(sale_type) IN (?, ?)', ['normal', 'promotion']);

                            if (! $this->promotions->isBlackFridayDay()) {
                                $normalQuery->orWhereIn(DB::raw('LOWER(sale_type)'), ['black friday', 'black_friday']);
                            }
                        }
                    }),
                    default => Schema::hasColumn('products', 'sale_type') ? $q->orWhereRaw('LOWER(sale_type) = ?', [str_replace('-', ' ', $offer)]) : null,
                };
            }
        });
    }

    private function orWherePromo($query): void
    {
        $query->orWhere(function ($promoQuery) {
            $this->promotions->applyActivePromotion($promoQuery);
        });
    }

    private function orWhereBoosted($query): void
    {
        $query->orWhere(function ($featuredQuery) {
            $this->promotions->applyFeatured($featuredQuery);
        });
    }

    private function applyStockFilter($query, Request $request): void
    {
        $stocks = collect($this->requestValues($request, 'stock'))
            ->map(fn ($item) => $this->normalizedValue((string) $item))
            ->filter()
            ->unique()
            ->values();

        if ($stocks->isEmpty()) {
            return;
        }

        $hasAvailability = Schema::hasColumn('products', 'availability_status');

        $query->where(function ($q) use ($stocks, $hasAvailability) {
            foreach ($stocks as $stock) {
                if ($stock === 'in-stock') {
                    $q->orWhere(function ($available) use ($hasAvailability) {
                        $available->where('stock', '>', 0);

                        if ($hasAvailability) {
                            $available->where(function ($status) {
                                $status->whereNull('availability_status')
                                    ->orWhere('availability_status', '')
                                    ->orWhere('availability_status', 'in_stock');
                            });
                        }
                    });
                }

                if (in_array($stock, ['on-order', 'out-of-stock'], true) && $hasAvailability) {
                    $q->orWhereIn('availability_status', ['on_order', 'preorder']);
                }

                if ($stock === 'fast-delivery' && Schema::hasColumn('products', 'fast_delivery')) {
                    $q->orWhere('fast_delivery', 1);
                }
            }
        });
    }

    private function applyPriceFilter($query, Request $request): void
    {
        $minPrice = $request->filled('min_price') ? (float) $request->query('min_price') : null;
        $maxPrice = $request->filled('max_price') ? (float) $request->query('max_price') : null;

        if ($minPrice === null && $maxPrice === null) {
            return;
        }

        $effectivePriceSql = $this->effectivePriceSql();

        if ($minPrice !== null) {
            $query->whereRaw("{$effectivePriceSql} >= ?", [$minPrice]);
        }

        if ($maxPrice !== null) {
            $query->whereRaw("{$effectivePriceSql} <= ?", [$maxPrice]);
        }
    }

    private function applyUnitFilter($query, Request $request): void
    {
        $units = collect($this->requestValues($request, 'unit'))
            ->map(fn ($item) => $this->normalizedValue((string) $item))
            ->flatMap(function ($unit) {
                return match ($unit) {
                    'sac' => ['sac'],
                    'tonne', 't' => ['tonne', 't'],
                    'm2', 'm²' => ['m2', 'm²'],
                    'm3', 'm³' => ['m3', 'm³'],
                    'piece', 'pieces', 'pièce', 'pièces', 'unite', 'unité' => ['piece', 'pièce', 'unite', 'unité'],
                    'rouleau' => ['rouleau'],
                    'seau' => ['seau'],
                    'palette' => ['palette'],
                    default => [$unit],
                };
            })
            ->filter()
            ->unique()
            ->values();

        if ($units->isEmpty()) {
            return;
        }

        $query->where(function ($q) use ($units) {
            foreach (['unit', 'unit_label', 'packaging'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    foreach ($units as $unit) {
                        $q->orWhereRaw("LOWER({$column}) = ?", [$unit])
                            ->orWhereRaw("LOWER({$column}) LIKE ?", ['%' . $unit . '%']);
                    }
                }
            }
        });
    }

    private function applyRatingFilter($query, Request $request): void
    {
        $ratings = collect($this->requestValues($request, 'rating'))
            ->map(fn ($item) => (float) $item)
            ->filter(fn ($item) => $item > 0)
            ->values();

        if ($ratings->isEmpty() || !Schema::hasTable('reviews') || !Schema::hasColumn('reviews', 'rating')) {
            return;
        }

        $minRating = $ratings->min();

        $query->whereIn('products.id', function ($sub) use ($minRating) {
            $sub->select('product_id')
                ->from('reviews')
                ->groupBy('product_id')
                ->havingRaw('AVG(rating) >= ?', [$minRating]);
        });
    }

    private function applyCatalogSorting($query, ?string $sort, Request $request): void
    {
        $sort = $sort ?: 'popular';
        $effectivePriceSql = $this->effectivePriceSql();

        // Les tris demandés par le client restent stricts. La priorité
        // commerciale des boosts n'est appliquée qu'au tri par défaut.
        if ($sort === 'popular' && Schema::hasColumn('products', 'is_boosted')) {
            $now = $this->promotions->now()->format('Y-m-d H:i:s');
            $boostParts = ['is_boosted = 1'];

            if (Schema::hasColumn('products', 'boost_start_at')) {
                $boostParts[] = "(boost_start_at IS NULL OR boost_start_at <= '{$now}')";
            }

            if (Schema::hasColumn('products', 'boost_end_at')) {
                $boostParts[] = "(boost_end_at IS NULL OR boost_end_at >= '{$now}')";
            }

            $query->orderByRaw('CASE WHEN ' . implode(' AND ', $boostParts) . ' THEN 0 ELSE 1 END');
        }

        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw("{$effectivePriceSql} ASC")
                    ->orderBy('products.id');
                break;

            case 'price_desc':
                $query->orderByRaw("{$effectivePriceSql} DESC")
                    ->orderBy('products.id');
                break;

            case 'recent':
                $query->orderByDesc('products.created_at')
                    ->orderByDesc('products.id');
                break;

            case 'availability':
                if (Schema::hasColumn('products', 'availability_status')) {
                    $query->orderByRaw("CASE WHEN stock > 0 AND (availability_status IS NULL OR availability_status = '' OR availability_status = 'in_stock') THEN 0 WHEN availability_status = 'on_order' THEN 1 WHEN availability_status = 'preorder' THEN 2 ELSE 3 END");
                } else {
                    $query->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END');
                }

                $query->orderByDesc('stock')
                    ->orderByDesc('products.created_at');
                break;

            case 'popular':
            default:
                if (Schema::hasColumn('products', 'sales')) {
                    $query->orderByDesc('sales');
                }

                $query->orderByDesc('products.created_at')
                    ->orderByDesc('products.id');
                break;
        }
    }

    private function effectivePriceSql(): string
    {
        $rate = (float) config('marketplace.default_commission_rate', CommissionService::DEFAULT_RATE);
        if ($rate > 1) {
            $rate /= 100;
        }
        $multiplier = 1 + min(1.0, max(0.0, $rate));

        if (! Schema::hasColumn('products', 'promo_price')) {
            return '(price * ' . $multiplier . ')';
        }

        $now = $this->promotions->now();
        $timestamp = $now->format('Y-m-d H:i:s');
        $isFriday = $this->promotions->isBlackFridayDay($now);

        $saleType = Schema::hasColumn('products', 'sale_type')
            ? "LOWER(REPLACE(REPLACE(COALESCE(sale_type, ''), '_', ' '), '-', ' '))"
            : "''";

        $promoWindow = Schema::hasColumn('products', 'promo_end')
            ? "(promo_end IS NULL OR promo_end > '{$timestamp}')"
            : '1 = 1';

        $flashWindow = Schema::hasColumn('products', 'flash_end')
            ? "flash_end IS NOT NULL AND flash_end > '{$timestamp}'"
            : '1 = 0';

        if (Schema::hasColumn('products', 'flash_start_at')) {
            $flashWindow .= " AND (flash_start_at IS NULL OR flash_start_at <= '{$timestamp}')";
        }

        $blackFridayWindow = $isFriday ? '1 = 1' : '1 = 0';
        if (Schema::hasColumn('products', 'bf_start')) {
            $blackFridayWindow .= " AND (bf_start IS NULL OR bf_start <= '{$timestamp}')";
        }
        if (Schema::hasColumn('products', 'bf_end')) {
            $blackFridayWindow .= " AND (bf_end IS NULL OR bf_end >= '{$timestamp}')";
        }

        $campaignActive = "(({$saleType} IN ('vente flash', 'flash sale', 'flash') AND {$flashWindow}) OR ({$saleType} = 'black friday' AND {$blackFridayWindow}) OR ({$saleType} NOT IN ('vente flash', 'flash sale', 'flash', 'black friday') AND {$promoWindow}))";

        return "(CASE WHEN promo_price IS NOT NULL AND promo_price > 0 AND promo_price < price AND {$campaignActive} THEN promo_price ELSE price END * {$multiplier})";
    }

    private function safePerPage($value): int
    {
        $allowed = [12, 16, 24, 32, 48];
        $requested = (int) $value;

        return in_array($requested, $allowed, true) ? $requested : 16;
    }

    private function getCatalogFilterCategories()
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        $giftCardCount = 0;
        if (Schema::hasTable('gift_card_products')) {
            $giftCardCount = GiftCardProduct::query()
                ->where('is_active', true)
                ->count();
        }

        return Category::query()
            ->when(Schema::hasColumn('categories', 'parent_id'), fn ($query) => $query->whereNull('parent_id'))
            ->withCount(['products as products_count' => function ($query) {
                $this->applyPublicProductConstraints($query);
            }])
            ->when(Schema::hasColumn('categories', 'status'), function ($query) {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('status')
                        ->orWhereIn('status', ['active', 'actif', '1', 1]);
                });
            })
            ->when(Schema::hasColumn('categories', 'sort_order'), fn ($query) => $query->orderBy('sort_order'))
            ->orderBy('name')
            ->get()
            // Le catalogue public doit afficher toutes les catégories racines actives,
            // même lorsqu'elles ne contiennent momentanément aucun produit.
            ->filter(fn ($category) => ! $this->isCommercialCategory(
                (string) ($category->name ?? ''),
                (string) ($category->slug ?? '')
            ))
            ->map(function ($category) use ($giftCardCount) {
                $slug = Str::slug($category->slug ?: $category->name);

                if ($slug === 'carte-cadeau-ovanie') {
                    $category->setAttribute('products_count', $giftCardCount);
                }

                $category->setAttribute('public_name', ovanie_category_label(
                    $category->name ?? '',
                    $category->slug ?? ''
                ));

                return $category;
            })
            ->values();
    }

    private function isCommercialCategory(string $name, string $slug): bool
    {
        $normalizedName = Str::of($name)->lower()->ascii()->toString();
        $normalizedSlug = Str::slug($slug ?: $name);

        $excludedSlugs = [
            'bonnes-affaires',
            'offres',
            'offres-du-jour',
            'materiaux-equipement',
            'materiaux-et-equipement',
            'services-ovanie',
            'univers-ovanie',
            'ovanie-business',
            'ovanie-pro',
        ];

        $excludedNames = [
            'bonnes affaires',
            'materiaux & equipement',
            'services ovanie',
            'univers ovanie',
            'ovanie business',
            'ovanie pro',
        ];

        return in_array($normalizedSlug, $excludedSlugs, true)
            || in_array($normalizedName, $excludedNames, true);
    }

    private function catalogCategoryDisplayName(?string $name, ?string $slug = null): string
    {
        return ovanie_category_label($name, $slug);
    }

    private function getCatalogOfferStats()
    {
        if (!Schema::hasTable('products')) {
            return collect();
        }

        $base = function () {
            $query = Product::query();
            $this->applyPublicProductConstraints($query);
            return $query;
        };

        return collect([
            'promo' => Schema::hasColumn('products', 'promo_price')
                ? tap((clone $base()), fn ($query) => $this->promotions->applyActivePromotion($query))->count()
                : 0,
            'vente-flash' => Schema::hasColumn('products', 'sale_type')
                ? tap((clone $base()), fn ($query) => $this->promotions->applyActiveFlash($query))->count()
                : 0,
            'black-friday' => Schema::hasColumn('products', 'sale_type')
                ? tap((clone $base()), fn ($query) => $this->promotions->applyFridayBlackFriday($query))->count()
                : 0,
            'top' => Schema::hasColumn('products', 'sales')
                ? (clone $base())->where('sales', '>=', 5)->count()
                : 0,
            'new' => (clone $base())->where('created_at', '>=', now()->subDays(14))->count(),
            'a-la-une' => tap((clone $base()), fn ($query) => $this->promotions->applyFeatured($query))->count(),
        ]);
    }

    /**
     * Payload produit public partagé avec l'API mobile.
     *
     * Le Web, le catalogue JSON et l'application mobile utilisent ainsi
     * exactement la même représentation publique d'un produit.
     */
    public function publicProductPayload(Product $product): array
    {
        return $this->serializeProduct($product);
    }

    private function serializeProduct(Product $product): array
    {
        $canonical = $this->productSheet->present($product, false);
        $saleType = PromotionVisibilityService::normalizeSaleType($product->sale_type ?? null);
        $flashActive = $this->promotions->isFlashActive($product);
        $blackFridayActive = $this->promotions->isBlackFridayActive($product);
        $promoActive = $this->promotions->isPromotionActive($product);

        // IMPORTANT : l'API Client doit exposer les mêmes prix publics que
        // la fiche Web et la fiche Vendeur. Le prix saisi par le vendeur
        // reste une donnée de gestion privée de l'espace vendeur.
        $price = (float) $canonical['regular_public_price'];
        $promoPrice = $canonical['public_promo_price'] !== null
            ? (float) $canonical['public_promo_price']
            : null;
        $basePrice = (float) $canonical['public_price'];
        $finalPrice = (float) $canonical['public_price'];
        $discountPercent = (int) $canonical['discount_percent'];

        $stock = (int) $canonical['stock'];
        $availabilityStatus = (string) $canonical['availability_status'];
        $isOrderable = (bool) $canonical['is_orderable'];
        $minOrderQuantity = (int) $canonical['min_order_quantity'];
        $canAddToCart = (bool) $canonical['can_add_to_cart'];
        $availabilityLabel = (string) $canonical['availability_label'];

        $rating = $canonical['rating'];
        $reviewsCount = (int) $canonical['reviews_count'];
        $tags = [];

        if ($blackFridayActive) {
            $tags[] = 'black-friday';
        } elseif ($flashActive) {
            $tags[] = 'vente-flash';
        } elseif ($promoActive) {
            $tags[] = 'promo';
        }

        if ((int) ($product->sales ?? 0) >= 5) {
            $tags[] = 'best-seller';
        }

        if ($isOrderable) {
            $tags[] = $availabilityStatus === 'preorder' ? 'precommande' : 'sur-commande';
        }

        if (($product->is_boost_active ?? false) || ($product->free_boosted_by_ovanie ?? false)) {
            $tags[] = 'a-la-une';
        }

        $mainImage = $canonical['main_image_url'];
        $cardImage = $canonical['card_image_url'];
        $thumbImage = $canonical['thumb_image_url'];
        $displayUnit = $canonical['display_unit'];
        $categoryName = $this->catalogCategoryDisplayName(
            $product->category->name ?? '',
            $product->category->slug ?? ''
        );

        $isFavorite = false;
        if (auth()->check()) {
            if ($this->userFavoriteProductIds === null) {
                $this->userFavoriteProductIds = auth()->user()
                    ->favoriteProducts()
                    ->pluck('products.id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();
            }

            $isFavorite = in_array((int) $product->id, $this->userFavoriteProductIds, true);
        }

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'url' => url('/product/' . ($product->slug ?: $product->id)),
            'name' => ovanie_public_text($product->name, $product->slug),
            'short_description' => $product->short_description ? ovanie_public_text($product->short_description) : null,
            'is_favorite' => $isFavorite,

            // Alias historiques conservés pour les écrans déjà existants,
            // mais ils pointent désormais tous vers les prix PUBLICS OVANIE.
            'price' => (int) round($price),
            'promo_price' => $promoPrice !== null ? (int) round($promoPrice) : null,
            'base_price' => (int) round($basePrice),
            'commission' => (int) round((float) ($product->commission_amount ?? 0)),
            'final_price' => (int) round($finalPrice),
            'original_public_price' => (int) round((float) $canonical['regular_public_price']),
            'public_price' => (int) round((float) $canonical['public_price']),
            'regular_public_price' => (int) round((float) $canonical['regular_public_price']),
            'public_promo_price' => $canonical['public_promo_price'] !== null
                ? (int) round((float) $canonical['public_promo_price'])
                : null,
            'discount_percent' => $discountPercent,

            'sale_type' => $saleType,
            'is_flash_active' => $flashActive,
            'is_black_friday_active' => $blackFridayActive,
            'is_promo_active' => $promoActive,
            'is_negotiable' => (bool) ($product->is_negotiable ?? false),
            'flash_end' => optional($product->flash_end)->toDateTimeString(),

            'unit' => $canonical['unit'],
            'unit_label' => $displayUnit,
            'display_unit' => $displayUnit,
            'packaging' => $canonical['packaging'],
            'brand' => $canonical['brand'],

            'is_boost_active' => (bool) ($product->is_boost_active ?? false),
            'free_boosted_by_ovanie' => (bool) ($product->free_boosted_by_ovanie ?? false),

            'category' => $categoryName,
            'category_name' => $categoryName,
            'category_slug' => $product->category->slug ?? null,
            'category_id' => $product->category_id,

            'stock' => $stock,
            'min_order_quantity' => $minOrderQuantity,
            'availability_status' => $availabilityStatus,
            'availability_label' => $availabilityLabel,
            'is_orderable' => $isOrderable,
            'can_add_to_cart' => $canAddToCart,
            'sales' => (int) ($product->sales ?? 0),
            'fast_delivery' => (bool) ($product->fast_delivery ?? false),

            'average_rating' => $rating ? round((float) $rating, 1) : null,
            'rating' => $rating ? round((float) $rating, 1) : null,
            'reviews_count' => $reviewsCount,

            // Aucune identité boutique/vendeur n'est exposée sur la fiche.
            'public_partner_label' => 'Produit vérifié OVANIE',

            'image' => $cardImage,
            'main_image_url' => $mainImage,
            'card_image_url' => $cardImage,
            'thumb_image_url' => $thumbImage,
            'gallery' => $canonical['gallery'],
            'images' => $canonical['images'],

            'created_at' => optional($product->created_at)->toDateTimeString(),
            'tags' => array_values(array_unique($tags)),
        ];
    }

    private function serializeProductDetail(Product $product): array
    {
        $payload = $this->serializeProduct($product);
        $detail = $this->productSheet->present($product, true);

        // Le détail enrichit le même payload catalogue sans changer les
        // alias historiques consommés par l'application Client.
        return array_merge($payload, $detail, [
            'category' => $payload['category'],
            'category_name' => $payload['category_name'],
            'category_slug' => $payload['category_slug'],
            'price' => $payload['price'],
            'promo_price' => $payload['promo_price'],
            'final_price' => $payload['final_price'],
            'original_public_price' => $payload['original_public_price'],
            'image' => $payload['image'],
            'is_favorite' => $payload['is_favorite'],
            'tags' => $payload['tags'],
        ]);
    }

    public function show(Request $request, Product $product, ProductCalculatorService $calculatorService)
    {
        $isPublic = $this->visibility
            ->apply(Product::query()->whereKey($product->getKey()), true)
            ->exists();

        abort_unless($isPublic, 404);

        $product->load([
            'images',
            'category.parent',
            'reviews' => fn ($query) => $query->with('user:id,name')->latest()->limit(4),
        ]);

        $product->loadCount('reviews')->loadAvg('reviews', 'rating');

        if (Schema::hasColumn('products', 'views')) {
            $product->increment('views');
        }

        // Historique partagé Web/mobile : lorsqu'un client Web connecté consulte
        // la fiche, on alimente la même table que l'application mobile.
        if ($request->user() && Schema::hasTable('recently_viewed_products')) {
            $recent = RecentlyViewedProduct::query()->firstOrNew([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ]);
            $recent->views_count = max(0, (int) $recent->views_count) + 1;
            $recent->last_viewed_at = now();
            $recent->save();
        }

        if ($request->is('api/*') || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'data' => $this->serializeProductDetail($product),
            ]);
        }

        $similarBase = $this->visibility
            ->query(['images', 'category'], true)
            ->whereKeyNot($product->getKey())
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        $similarProducts = (clone $similarBase)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->orderByDesc('sales')
            ->latest('id')
            ->limit(6)
            ->get();

        if ($similarProducts->count() < 6) {
            $extra = (clone $similarBase)
                ->whereNotIn('products.id', $similarProducts->pluck('id'))
                ->orderByDesc('sales')
                ->latest('id')
                ->limit(6 - $similarProducts->count())
                ->get();

            $similarProducts = $similarProducts->concat($extra)->values();
        }

        $isFavorite = false;
        if ($request->user()) {
            $isFavorite = $request->user()
                ->favoriteProducts()
                ->where('products.id', $product->id)
                ->exists();
        }

        return view('products.show', [
            'product' => $product,
            'productSheet' => $this->productSheet->present($product, true),
            'similarProducts' => $similarProducts,
            'calculatorProfile' => $calculatorService->profile($product),
            'isFavorite' => $isFavorite,
        ]);
    }

    public function cart(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load('items.product');

        return view('cart', compact('cart'));
    }

    public function addToCart(Request $request, $productId)
    {
        $request->validate(['quantity' => 'nullable|integer|min:1|max:99']);
        $user = $request->user();
        $product = Product::findOrFail($productId);
        $quantity = $request->input('quantity', 1);

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->quantity = min($quantity, 99);
            $item->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'price' => $product->final_price,
                'quantity' => $quantity
            ]);
        }

        $user->removeFavoriteProduct((int) $product->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Produit ajouté au panier.',
                'cart_count' => $cart->items()->sum('quantity'),
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Produit ajouté au panier');
    }

    public function addNegotiatedToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'negotiated_price' => 'required|numeric|min:1',
            'negotiation_id' => 'required|integer|exists:negotiations,id',
            'quantity' => 'nullable|integer|min:1|max:1000000',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être connecté pour ajouter au panier.',
            ], 401);
        }

        $product = $this->visibility
            ->query(['shop'])
            ->findOrFail((int) $validated['product_id']);

        if (! $product->is_negotiable) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n’est pas négociable.',
            ], 422);
        }

        $negotiation = Negotiation::query()
            ->whereKey((int) $validated['negotiation_id'])
            ->where('product_id', $product->id)
            ->where('buyer_id', $user->id)
            ->where('status', 'accepted')
            ->first();

        $negotiatedPrice = (float) $validated['negotiated_price'];

        if (! $negotiation || abs((float) $negotiation->proposed_price - $negotiatedPrice) >= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Le prix négocié n’est pas valide ou ne vous appartient pas.',
            ], 422);
        }

        $quantity = (int) ($validated['quantity'] ?? 1);
        $minimum = max(1, (int) ($product->min_order_quantity ?: 1));
        $stock = max(0, (int) $product->stock);

        if ($stock < $minimum) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant pour respecter le minimum de commande de {$minimum} unité(s).",
            ], 422);
        }

        if ($quantity < $minimum) {
            return response()->json([
                'success' => false,
                'message' => "La quantité minimale de commande est de {$minimum} unité(s).",
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = $item ? ((int) $item->quantity + $quantity) : $quantity;

        if ($newQuantity > $stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock insuffisant : seulement {$stock} unité(s) disponible(s).",
            ], 422);
        }

        if ($item) {
            $item->forceFill([
                'price' => $negotiatedPrice,
                'price_source' => 'negotiated',
                'negotiation_id' => $negotiation->id,
                'quantity' => $newQuantity,
            ])->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'original_product_id' => $product->id,
                'fulfillment_product_id' => $product->id,
                'original_shop_id' => $product->shop_id,
                'fulfillment_shop_id' => $product->shop_id,
                'optimization_applied' => false,
                'price' => $negotiatedPrice,
                'price_source' => 'negotiated',
                'negotiation_id' => $negotiation->id,
                'quantity' => $quantity,
            ]);
        }

        $user->removeFavoriteProduct((int) $product->id);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté au panier avec le prix négocié.',
            'cart_count' => $cart->items()->sum('quantity'),
        ]);
    }

    public function removeFromCart(Request $request, $itemId)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->where('id', $itemId)->delete();
        }
        return redirect()->route('cart.index')->with('success', 'Produit supprimé du panier');
    }

    public function clearCart(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
        }
        return redirect()->route('cart.index')->with('success', 'Panier vidé');
    }

    public function json(Request $request)
    {
        $limit = max(1, min(100, (int) $request->query('limit', 24)));

        $products = $this->visibility
            ->query(['category', 'images'], true)
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($p) => $this->serializeProduct($p));

        return response()->json($products);
    }

    public function incrementView(Product $product)
    {
        if ($product->status !== 'actif') {
            return response()->json(['ok' => false], 400);
        }

        $product->increment('views');

        return response()->json(['ok' => true, 'views' => $product->views]);
    }

    public function topSellers(Request $request)
    {
        $limit = max(1, min(100, (int) $request->query('limit', 24)));
        $items = $this->visibility
            ->query(['images', 'category'], true)
            ->orderByDesc('sales')
            ->take($limit)
            ->get()
            ->map(fn($p) => $this->serializeProduct($p));

        return response()->json($items);
    }

    public function recommendations(Request $request)
    {
        $limit = max(1, min(100, (int) $request->query('limit', 24)));

        if ($request->filled('product_id')) {
            $p = Product::find($request->product_id);
            if (!$p) {
                return response()->json([], 200);
            }

            $items = $this->visibility
                ->query(['images', 'category'], true)
                ->where('category_id', $p->category_id)
                ->where('id', '!=', $p->id)
                ->orderByDesc('sales')
                ->take($limit)
                ->get()
                ->map(fn($p) => $this->serializeProduct($p));

            if ($items->count()) {
                return response()->json($items);
            }
        }

        if ($request->filled('category')) {
            $category = strtolower($request->category);
            $items = $this->visibility
                ->query(['images', 'category'], true)
                ->whereHas('category', fn($q) => $q->whereRaw('LOWER(name)=?', [$category]))
                ->orderByDesc('sales')
                ->take($limit)
                ->get()
                ->map(fn($p) => $this->serializeProduct($p));

            return response()->json($items);
        }

        $items = $this->visibility
            ->query(['images', 'category'], true)
            ->orderByDesc('sales')
            ->take($limit)
            ->get()
            ->map(fn($p) => $this->serializeProduct($p));

        return response()->json($items);
    }
}
