<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Models\Category;
use App\Models\Product;
use App\Services\HomepageService;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MobileMarketplaceController extends Controller
{
    public function __construct(
        private readonly HomepageService $homepage,
        private readonly PublicProductVisibilityService $visibility,
        private readonly PublicProductController $products,
    ) {
    }

    /**
     * Accueil mobile construit à partir de la même source métier que la page
     * d'accueil Web : HomepageService.
     */
    public function home(Request $request): JsonResponse
    {
        $payload = $this->homepage->build(null);

        $loadedCategories = Category::query()
            ->active()
            ->roots()
            ->with(['children' => fn ($children) => $children->active()->ordered()])
            ->get()
            ->keyBy('id');

        $eventProducts = collect($payload['flashProducts'] ?? []);
        $blackFridayProducts = collect($payload['blackFridayProducts'] ?? []);
        $blackFridayPayloads = $this->productPayloads($blackFridayProducts);
        $blackFridayBannerStats = $blackFridayProducts->isNotEmpty()
            ? [
                'max_discount_percent' => (int) (
                    collect($blackFridayPayloads)->max('discount_percent') ?? 0
                ),
                'product_count' => $blackFridayProducts->count(),
            ]
            : $this->blackFridayBannerStats();

        // Même source de données que le Web, sans section de remplacement.
        $sections = collect([
            [
                'key' => 'flash',
                'mode' => 'flash',
                'title' => 'VENTE FLASH',
                'catalog_offer' => 'flash-sale',
                'products' => $this->productPayloads($eventProducts),
            ],
            [
                'key' => 'latest',
                'mode' => 'latest',
                'title' => 'NOUVEAUTÉS',
                'catalog_offer' => 'new',
                'products' => $this->productPayloads(collect($payload['latestProducts'] ?? [])),
            ],
            [
                'key' => 'best_sellers',
                'mode' => 'best_sellers',
                'title' => 'MEILLEURES VENTES',
                'catalog_offer' => 'top',
                'products' => $this->productPayloads(collect($payload['bestSellers'] ?? [])),
            ],
        ])->filter(fn (array $section) => $section['products'] !== [])->values()->all();

        return response()->json([
            'data' => [
                'source' => 'homepage_service',
                'server_time' => now()->toIso8601String(),
                // Même échéance que celle utilisée par le compte à rebours Web.
                // Flutter ne crée donc aucun minuteur artificiel côté mobile.
                'flash_sale_ends_at' => $payload['flashSaleEndsAt'] ?? null,
                'flash_sale_starts_at' => $payload['flashSaleStartsAt'] ?? null,
                // Le bandeau Black Friday mobile n'affiche plus de valeur
                // marketing codée en dur. Ce pourcentage est le maximum réel
                // des remises Black Friday actives calculées par Laravel avec
                // la même sérialisation publique que le Web.
                'black_friday_max_discount_percent' => max(
                    0,
                    (int) ($blackFridayBannerStats['max_discount_percent'] ?? 0)
                ),
                'black_friday_product_count' => max(
                    0,
                    (int) ($blackFridayBannerStats['product_count'] ?? 0)
                ),
                'categories' => $this->homepageCategoryPayloads(
                    collect($payload['homepageCategoryCards'] ?? []),
                    $loadedCategories,
                ),
                'sections' => $sections,
                /*[
                    [
                        'key' => 'primary',
                        'mode' => $primaryMode,
                        'title' => $primaryMode === 'flash' ? 'OFFRES FLASH' : 'NOUVEAUX PRODUITS',
                        'catalog_offer' => $primaryMode === 'flash' ? 'vente-flash' : 'new',
                        'products' => $this->productPayloads(collect($payload['flashPanelProducts'] ?? [])),
                    ],
                    [
                        'key' => 'middle',
                        'mode' => $middleMode,
                        'title' => $middleMode === 'best_sellers'
                            ? "MEILLEURES VENTES EN CÔTE D'IVOIRE"
                            : 'SÉLECTION OVANIE',
                        'catalog_offer' => $middleMode === 'best_sellers' ? 'top' : null,
                        'products' => $this->productPayloads(collect($payload['middlePanelProducts'] ?? [])),
                    ],
                    [
                        'key' => 'right',
                        'mode' => $rightMode,
                        'title' => match ($rightMode) {
                            'black_friday' => 'BLACK FRIDAY',
                            'featured' => 'À LA UNE',
                            default => 'À DÉCOUVRIR',
                        },
                        'catalog_offer' => match ($rightMode) {
                            'black_friday' => 'black-friday',
                            'featured' => 'a-la-une',
                            default => null,
                        },
                        'products' => $this->productPayloads(collect($payload['rightPanelProducts'] ?? [])),
                    ],
                    [
                        'key' => 'event',
                        'mode' => $eventMode,
                        'title' => $isFriday ? 'BLACK FRIDAY' : 'VENTE FLASH',
                        'catalog_offer' => $isFriday ? 'black-friday' : 'flash-sale',
                        'products' => $this->productPayloads($eventProducts),
                    ],
                ],*/
            ],
        ]);
    }

    /**
     * Arborescence publique des catégories pour l’application mobile.
     * Les catégories sont lues directement depuis Laravel : aucune liste
     * parallèle n’est maintenue dans Flutter.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->roots()
            // L'écran Catégories est une navigation de catalogue BTP.
            // Les collections commerciales (carte cadeau, reconditionné, offres...)
            // restent accessibles via leurs parcours dédiés mais ne sont pas
            // présentées comme des familles techniques de produits.
            ->ordered()
            ->with(['children' => fn ($children) => $children->active()->ordered()])
            ->get();

        return response()->json([
            'data' => $categories
                ->map(fn (Category $category) => $this->categoryPayload($category))
                ->values(),
        ]);
    }

    /**
     * Métadonnées de filtres calculées depuis le même catalogue public que le Web.
     */
    public function filters(Request $request): JsonResponse
    {
        $base = $this->visibility->query([], true);

        $brands = [];
        if (Schema::hasColumn('products', 'brand')) {
            $brands = (clone $base)
                ->whereNotNull('products.brand')
                ->where('products.brand', '!=', '')
                ->select('products.brand')
                ->distinct()
                ->orderBy('products.brand')
                ->limit(100)
                ->pluck('brand')
                ->map(fn ($brand) => trim((string) $brand))
                ->filter()
                ->values()
                ->all();
        }

        $priceMin = 0;
        $priceMax = 0;
        if (Schema::hasColumn('products', 'price')) {
            $priceMin = (int) floor((float) (clone $base)->min('products.price'));
            $priceMax = (int) ceil((float) (clone $base)->max('products.price'));
        }

        return response()->json([
            'data' => [
                'brands' => $brands,
                'price' => [
                    'min' => max(0, $priceMin),
                    'max' => max(0, $priceMax),
                ],
                'availability' => [
                    ['value' => 'in-stock', 'label' => 'En stock'],
                    ['value' => 'on-order', 'label' => 'Sur commande'],
                ],
                'sorts' => [
                    ['value' => 'popular', 'label' => 'Populaires'],
                    ['value' => 'recent', 'label' => 'Plus récents'],
                    ['value' => 'price_asc', 'label' => 'Prix croissant'],
                    ['value' => 'price_desc', 'label' => 'Prix décroissant'],
                    ['value' => 'availability', 'label' => 'Disponibilité'],
                ],
            ],
        ]);
    }

    /**
     * Statistiques du bandeau Black Friday.
     *
     * Le vendredi, home() utilise d'abord les produits Black Friday actifs
     * fournis par HomepageService. Hors vendredi, ce fallback garde un vrai
     * pourcentage issu des produits publics inscrits à la campagne au lieu
     * d'afficher une valeur marketing codée en dur.
     */
    private function blackFridayBannerStats(): array
    {
        if (
            ! Schema::hasColumn('products', 'sale_type')
            || ! Schema::hasColumn('products', 'price')
            || ! Schema::hasColumn('products', 'promo_price')
        ) {
            return [
                'max_discount_percent' => 0,
                'product_count' => 0,
            ];
        }

        $query = $this->visibility
            ->query([], true)
            ->whereRaw(
                "LOWER(TRIM(COALESCE(products.sale_type, ''))) IN (?, ?)",
                ['black friday', 'black_friday']
            )
            ->whereNotNull('products.promo_price')
            ->where('products.price', '>', 0)
            ->where('products.promo_price', '>', 0)
            ->whereColumn('products.promo_price', '<', 'products.price');

        // Une campagne déjà terminée ne doit plus influencer le pourcentage
        // marketing du bandeau. Une date de début future reste pertinente :
        // elle représente une offre Black Friday programmée.
        if (Schema::hasColumn('products', 'bf_end')) {
            $query->where(function ($window) {
                $window->whereNull('products.bf_end')
                    ->orWhere('products.bf_end', '>=', now());
            });
        }

        $products = $query
            ->get([
                'products.id',
                'products.price',
                'products.promo_price',
            ]);

        $discounts = $products
            ->map(function (Product $product) {
                $price = (float) ($product->price ?? 0);
                $promoPrice = (float) ($product->promo_price ?? 0);

                if ($price <= 0 || $promoPrice <= 0 || $promoPrice >= $price) {
                    return 0;
                }

                return (int) round((($price - $promoPrice) / $price) * 100);
            })
            ->filter(fn (int $percent) => $percent > 0);

        return [
            'max_discount_percent' => (int) ($discounts->max() ?? 0),
            'product_count' => $discounts->count(),
        ];
    }

    private function productPayloads(Collection $products): array
    {
        return $products
            ->filter(fn ($product) => $product instanceof Product)
            ->map(fn (Product $product) => $this->products->publicProductPayload($product))
            ->values()
            ->all();
    }

    /**
     * Reprend les huit cartes et les visuels officiels du bloc
     * « Catégories BTP » de la page d'accueil Web.
     */
    private function homepageCategoryPayloads(Collection $cards, Collection $loadedCategories): array
    {
        $officialImages = [
            'materiaux-gros-oeuvres' => 'home-gros-oeuvre.webp',
            'materiaux-gros-oeuvre' => 'home-gros-oeuvre.webp',
            'materiaux-de-finition' => 'home-finition.webp',
            'materiaux-ecologique' => 'home-equipement.webp',
            'materiaux-ecologiques' => 'home-equipement.webp',
            'outillage-equipement' => 'home-outillage.webp',
            'electricite-plomberie' => 'home-plomberie.webp',
            'energie-solaire' => 'home-energie.webp',
            'nos-reconditionnee' => 'home-reconditionnes.webp',
            'nos-reconditionnes' => 'home-reconditionnes.webp',
            'carte-cadeau-ovanie' => 'home-carte-cadeau.webp',
        ];

        return $cards->map(function (array $card, int $index) use ($loadedCategories, $officialImages) {
            $category = ($card['category'] ?? null) instanceof Category
                ? $loadedCategories->get((int) $card['category']->id)
                : null;
            $slug = (string) ($card['slug'] ?? $category?->slug ?? 'categorie-'.$index);
            $filename = $officialImages[$slug] ?? null;

            return [
                'id' => $category ? (int) $category->id : -($index + 1),
                'parent_id' => null,
                'name' => (string) ($card['name'] ?? $category?->name ?? 'Catégorie'),
                'slug' => $slug,
                // Priorité à la photo importée pour la catégorie dans l'admin,
                // puis aux visuels officiels des catégories historiques,
                // puis à un vrai produit de la catégorie.
                'icon' => $category?->image_url
                    ?? ($filename ? asset('storage/logos/'.rawurlencode($filename)) : null)
                    ?? (string) ($card['product']?->card_image_url ?? ''),
                'children' => $category?->relationLoaded('children')
                    ? $category->children->map(fn (Category $child) => [
                        'id' => (int) $child->id,
                        'parent_id' => (int) $category->id,
                        'name' => (string) $child->name,
                        'slug' => (string) $child->slug,
                        'icon' => '',
                        'children' => [],
                    ])->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    private function categoryPayload(Category $category): array
    {
        $officialImages = [
            'materiaux-gros-oeuvres' => 'home-gros-oeuvre.webp',
            'materiaux-gros-oeuvre' => 'home-gros-oeuvre.webp',
            'materiaux-de-finition' => 'home-finition.webp',
            'materiaux-ecologique' => 'home-equipement.webp',
            'materiaux-ecologiques' => 'home-equipement.webp',
            'outillage-equipement' => 'home-outillage.webp',
            'electricite-plomberie' => 'home-plomberie.webp',
            'energie-solaire' => 'home-energie.webp',
            'nos-reconditionnee' => 'home-reconditionnes.webp',
            'nos-reconditionnes' => 'home-reconditionnes.webp',
            'carte-cadeau-ovanie' => 'home-carte-cadeau.webp',
        ];
        $icon = (string) ($category->image_url ?? $category->icon ?? '');
        if ($icon === '' && isset($officialImages[$category->slug])) {
            $icon = asset('storage/logos/'.rawurlencode($officialImages[$category->slug]));
        }

        return [
            'id' => (int) $category->id,
            'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
            'name' => ovanie_public_text((string) $category->name, (string) $category->slug),
            'slug' => (string) $category->slug,
            'icon' => $icon,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child) => [
                    'id' => (int) $child->id,
                    'parent_id' => (int) $category->id,
                    'name' => ovanie_public_text((string) $child->name, (string) $child->slug),
                    'slug' => (string) $child->slug,
                    'icon' => (string) ($child->icon ?? ''),
                    'children' => [],
                ])->values()->all()
                : [],
        ];
    }
}
