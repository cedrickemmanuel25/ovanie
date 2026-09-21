<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CommercialQuickProductService
{
    /**
     * Retourne les statistiques du catalogue réellement consultable par le Commercial.
     * Les produits déjà en ligne mais non encore rattachés à master_products sont inclus.
     *
     * @return array{master_count:int, online_unlinked_count:int, total_available:int}
     */
    public function catalogStats(): array
    {
        $masterCount = MasterProduct::query()
            ->where('is_active', true)
            ->count();

        $onlineUnlinkedCount = $this->reusableOnlineProductsQuery()
            ->whereNull('master_product_id')
            ->count();

        return [
            'master_count' => $masterCount,
            'online_unlinked_count' => $onlineUnlinkedCount,
            'total_available' => $masterCount + $onlineUnlinkedCount,
        ];
    }

    /**
     * Recherche dans deux sources sans exposer le prix, le stock ou la boutique
     * d'une autre offre :
     * 1. les références déjà présentes dans master_products ;
     * 2. les produits actifs déjà en ligne mais non encore rattachés au catalogue maître.
     *
     * @return array{
     *     data:array<int,array<string,mixed>>,
     *     meta:array<string,mixed>
     * }
     */
    public function searchReferences(
        Shop $selectedShop,
        string $term,
        ?int $categoryId = null,
        int $limit = 20
    ): array {
        $term = trim($term);
        $limit = max(1, min($limit, 20));

        /*
         * Une recherche vide ne doit jamais parcourir ou retourner tout
         * le catalogue. Cette protection reste active même lorsque
         * l'endpoint est appelé directement sans passer par la page.
         */
        $queryLength = preg_match_all('/./us', $term, $characters) ?: strlen($term);

        if ($queryLength < 2) {
            return [
                'data' => [],
                'meta' => [
                    'query' => $term,
                    'category_id' => $categoryId,
                    'returned_count' => 0,
                    'result_limit' => $limit,
                    'requires_query' => true,
                    'min_query_length' => 2,
                    'searches_master_catalog' => true,
                    'searches_online_unlinked_products' => true,
                ],
            ];
        }

        /*
         * Même avec plusieurs millions de produits, seule une petite liste
         * de candidats est chargée et la réponse ne renvoie jamais plus
         * de 20 résultats.
         */
        $candidateLimit = max($limit * 4, 60);

        $masterQuery = MasterProduct::query()
            ->where('is_active', true)
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->with([
                'category:id,name',
                'products' => fn (Builder $query) => $query
                    ->select('id', 'master_product_id')
                    ->whereHas('images')
                    ->with('images')
                    ->latest('id')
                    ->limit(5),
            ]);

        $this->applyMasterSearch($masterQuery, $term);

        $masterProducts = $masterQuery
            ->limit($candidateLimit)
            ->get();

        $existingOffers = Product::query()
            ->where('shop_id', $selectedShop->id)
            ->whereIn('master_product_id', $masterProducts->pluck('id')->filter())
            ->get([
                'id',
                'master_product_id',
                'price',
                'promo_price',
                'stock',
                'min_order_quantity',
                'availability_status',
                'status',
                'is_active',
            ])
            ->keyBy('master_product_id');

        $masterItems = $masterProducts->map(function (MasterProduct $masterProduct) use ($existingOffers, $term) {
            $image = $this->catalogImage($masterProduct);
            $offer = $existingOffers->get($masterProduct->id);
            $missing = $this->missingMasterFields($masterProduct);

            return [
                'key' => 'master:' . $masterProduct->id,
                'source_type' => 'master',
                'source_id' => $masterProduct->id,
                'name' => $masterProduct->name,
                'brand' => $masterProduct->brand,
                'reference' => $masterProduct->reference,
                'sku' => $masterProduct->sku,
                'category' => $masterProduct->category?->name,
                'category_id' => $masterProduct->category_id,
                'unit' => $masterProduct->unit,
                'packaging' => $masterProduct->packaging,
                'weight_kg' => $masterProduct->weight_kg,
                'length_cm' => $masterProduct->length_cm,
                'width_cm' => $masterProduct->width_cm,
                'height_cm' => $masterProduct->height_cm,
                'fragile' => $masterProduct->fragile,
                'requires_unloading' => $masterProduct->requires_unloading,
                'image_url' => $image?->thumb_url,
                'has_catalog_image' => $image !== null,
                'is_complete' => $missing === [],
                'missing_fields' => $missing,
                'origin' => 'master_catalog',
                'origin_label' => 'Fiche technique OVANIE',
                'existing_offer' => $offer ? $this->safeExistingOfferPayload($offer) : null,
                '_fingerprint' => $this->masterFingerprint($masterProduct),
                '_score' => $this->relevanceScore(
                    $term,
                    $masterProduct->name,
                    $masterProduct->brand,
                    $masterProduct->sku,
                    $masterProduct->reference,
                    $masterProduct->packaging
                ),
            ];
        });

        $onlineQuery = $this->reusableOnlineProductsQuery()
            ->whereNull('master_product_id')
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->with(['category:id,name', 'images']);

        $this->applyProductSearch($onlineQuery, $term);

        $onlineCandidates = $onlineQuery
            ->limit($candidateLimit)
            ->get();

        $seenFingerprints = $masterItems
            ->pluck('_fingerprint')
            ->filter()
            ->flip();

        $onlineItems = $onlineCandidates
            ->groupBy(fn (Product $product) => $this->productFingerprint($product))
            ->map(function (Collection $group) use ($selectedShop, $term) {
                /** @var Product $product */
                $product = $group
                    ->sortByDesc(function (Product $candidate) use ($selectedShop) {
                        $sameShopBonus = (int) ($candidate->shop_id === $selectedShop->id) * 1000;
                        $imageBonus = (int) $candidate->images->isNotEmpty() * 100;
                        $completeBonus = max(0, 20 - count($this->missingProductFields($candidate)));

                        return $sameShopBonus + $imageBonus + $completeBonus + (int) $candidate->id;
                    })
                    ->first();

                $image = $this->productCatalogImage($product);
                $missing = $this->missingProductFields($product);
                $isCurrentShopOffer = $product->shop_id === $selectedShop->id;

                return [
                    'key' => 'marketplace:' . $product->id,
                    'source_type' => 'marketplace',
                    'source_id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'reference' => null,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'category_id' => $product->category_id,
                    'unit' => $product->unit,
                    'packaging' => $product->packaging,
                    'weight_kg' => $this->productWeight($product),
                    'length_cm' => $product->length_cm,
                    'width_cm' => $product->width_cm,
                    'height_cm' => $product->height_cm,
                    'fragile' => $this->rawNullableBoolean($product, 'fragile'),
                    'requires_unloading' => $this->rawNullableBoolean($product, 'requires_unloading'),
                    'image_url' => $image?->thumb_url,
                    'has_catalog_image' => $image !== null,
                    'is_complete' => $missing === [],
                    'missing_fields' => $missing,
                    'origin' => 'online_product',
                    'origin_label' => 'Produit déjà en ligne',
                    'existing_offer' => $isCurrentShopOffer
                        ? $this->safeExistingOfferPayload($product)
                        : null,
                    '_fingerprint' => $this->productFingerprint($product),
                    '_score' => $this->relevanceScore(
                        $term,
                        $product->name,
                        $product->brand,
                        $product->sku,
                        null,
                        $product->packaging
                    ) + 5,
                ];
            })
            ->reject(fn (array $item) => $seenFingerprints->has($item['_fingerprint']))
            ->values();

        $items = $masterItems
            ->concat($onlineItems)
            ->sortBy([
                ['_score', 'asc'],
                ['name', 'asc'],
            ])
            ->take($limit)
            ->map(function (array $item) {
                unset($item['_fingerprint'], $item['_score']);

                return $item;
            })
            ->values()
            ->all();

        return [
            'data' => $items,
            'meta' => [
                'query' => $term,
                'category_id' => $categoryId,
                'returned_count' => count($items),
                'result_limit' => $limit,
                'requires_query' => true,
                'min_query_length' => 2,
                'searches_master_catalog' => true,
                'searches_online_unlinked_products' => true,
            ],
        ];
    }

    /**
     * Résout la sélection faite dans l'interface.
     *
     * - source_type=master : charge la référence existante.
     * - source_type=marketplace : crée ou retrouve une référence maître à partir
     *   du produit actif déjà en ligne, puis rattache ce produit à la référence.
     */
    public function resolveMasterReference(string $sourceType, int $sourceId): MasterProduct
    {
        if ($sourceType === 'master') {
            return MasterProduct::query()
                ->where('is_active', true)
                ->with(['products.images'])
                ->findOrFail($sourceId);
        }

        if ($sourceType !== 'marketplace') {
            throw new RuntimeException('Source de catalogue non reconnue.');
        }

        return DB::transaction(function () use ($sourceId) {
            $source = Product::query()
                ->with(['shop', 'category', 'images'])
                ->lockForUpdate()
                ->findOrFail($sourceId);

            if (! $this->isReusableOnlineProduct($source)) {
                throw new RuntimeException('Ce produit n’est plus disponible comme référence. Relancez la recherche.');
            }

            if ($source->master_product_id) {
                return MasterProduct::query()
                    ->where('is_active', true)
                    ->with(['products.images'])
                    ->findOrFail($source->master_product_id);
            }

            $masterProduct = $this->findMatchingMasterProduct($source);

            if (! $masterProduct) {
                $masterProduct = MasterProduct::create($this->masterAttributesFromProduct($source));
            }

            $source->forceFill([
                'master_product_id' => $masterProduct->id,
                'is_fulfillment_enabled' => $this->missingMasterFields($masterProduct) === [],
            ])->save();

            return $masterProduct->fresh(['products.images']);
        }, 3);
    }

    /**
     * @return array<int, string>
     */
    public function missingMasterFields(MasterProduct $masterProduct): array
    {
        $missing = [];

        if (! $masterProduct->category_id) {
            $missing[] = 'catégorie';
        }
        if (! filled($masterProduct->name)) {
            $missing[] = 'nom';
        }
        if (! filled($masterProduct->description)) {
            $missing[] = 'description';
        }
        if (! filled($masterProduct->unit)) {
            $missing[] = 'unité de vente';
        }
        if ((float) $masterProduct->weight_kg <= 0) {
            $missing[] = 'poids';
        }
        if ((float) $masterProduct->length_cm <= 0) {
            $missing[] = 'longueur';
        }
        if ((float) $masterProduct->width_cm <= 0) {
            $missing[] = 'largeur';
        }
        if ((float) $masterProduct->height_cm <= 0) {
            $missing[] = 'hauteur';
        }
        if ($masterProduct->getAttribute('fragile') === null) {
            $missing[] = 'fragilité';
        }
        if ($masterProduct->getAttribute('requires_unloading') === null) {
            $missing[] = 'déchargement';
        }
        if ($masterProduct->requires_unloading && ! filled($masterProduct->unloading_instructions)) {
            $missing[] = 'instructions de déchargement';
        }

        return $missing;
    }

    /**
     * @return array<int, string>
     */
    public function missingProductFields(Product $product): array
    {
        $missing = [];

        if (! $product->category_id) {
            $missing[] = 'catégorie';
        }
        if (! filled($product->name)) {
            $missing[] = 'nom';
        }
        if (! filled($product->description)) {
            $missing[] = 'description';
        }
        if (! filled($product->unit)) {
            $missing[] = 'unité de vente';
        }
        if ($this->productWeight($product) <= 0) {
            $missing[] = 'poids';
        }
        if ((float) $product->length_cm <= 0) {
            $missing[] = 'longueur';
        }
        if ((float) $product->width_cm <= 0) {
            $missing[] = 'largeur';
        }
        if ((float) $product->height_cm <= 0) {
            $missing[] = 'hauteur';
        }
        if ($product->getAttribute('fragile') === null) {
            $missing[] = 'fragilité';
        }
        if ($product->getAttribute('requires_unloading') === null) {
            $missing[] = 'déchargement';
        }
        if ($product->requires_unloading && ! filled($product->unloading_instructions)) {
            $missing[] = 'instructions de déchargement';
        }

        return $missing;
    }

    public function catalogImage(MasterProduct $masterProduct): ?ProductImage
    {
        $products = $masterProduct->relationLoaded('products')
            ? $masterProduct->products
            : $masterProduct->products()
                ->whereHas('images')
                ->with('images')
                ->latest('id')
                ->limit(5)
                ->get();

        foreach ($products as $product) {
            $image = $this->productCatalogImage($product);

            if ($image) {
                return $image;
            }
        }

        return null;
    }

    public function productCatalogImage(Product $product): ?ProductImage
    {
        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->get();

        return $images
            ->sortByDesc(fn (ProductImage $image) => (int) ($image->is_main || $image->is_primary))
            ->first();
    }

    /**
     * Crée ou met à jour l'offre d'une boutique à partir du catalogue maître.
     *
     * @return array{
     *     product:Product,
     *     created:bool,
     *     published:bool,
     *     status:string,
     *     missing:array<int,string>,
     *     image_source:string
     * }
     */
    public function upsert(
        int $commercialId,
        Shop $shop,
        MasterProduct $masterProduct,
        array $offer,
        bool $publish,
        ?UploadedFile $photo,
        bool $useCatalogImage,
        ProductImageNormalizer $normalizer
    ): array {
        $product = Product::query()
            ->where('shop_id', $shop->id)
            ->where('master_product_id', $masterProduct->id)
            ->first();

        $created = ! $product;
        $existingImageCount = $product?->images()->count() ?? 0;
        $imagePayload = null;
        $imageSource = $existingImageCount > 0 ? 'existing' : 'none';

        if ($photo) {
            $imagePayload = $this->normalizeUploadedPhoto($photo, $normalizer);
            $imageSource = 'photo';
        } elseif ($existingImageCount === 0 && $useCatalogImage) {
            $source = $this->catalogImage($masterProduct);
            if ($source) {
                $imagePayload = $this->cloneCatalogImage($source, $normalizer);
                $imageSource = 'catalog';
            }
        }

        $hasImage = $existingImageCount > 0 || $imagePayload !== null;
        $missing = $this->missingMasterFields($masterProduct);
        if (! $hasImage) {
            $missing[] = 'photo';
        }

        $status = 'draft';
        $active = false;

        if ($publish && $missing === []) {
            if ($shop->canPublishProducts()) {
                $status = 'actif';
                $active = true;
            } else {
                $status = 'pending_logistics';
            }
        }

        try {
            $product = DB::transaction(function () use (
                $commercialId,
                $shop,
                $masterProduct,
                $offer,
                $product,
                $status,
                $active,
                $imagePayload,
                $imageSource
            ) {
                $attributes = $this->productAttributes(
                    $commercialId,
                    $shop,
                    $masterProduct,
                    $offer,
                    $status,
                    $active,
                    $product
                );

                if ($product) {
                    $product->fill($attributes)->save();
                } else {
                    $product = Product::create($attributes);
                }

                if ($imagePayload) {
                    $makeMain = $imageSource === 'photo' || ! $product->images()->exists();

                    if ($makeMain) {
                        $values = ['is_main' => false];
                        if (Schema::hasColumn('product_images', 'is_primary')) {
                            $values['is_primary'] = false;
                        }
                        ProductImage::query()->where('product_id', $product->id)->update($values);
                    }

                    $imageData = [
                        ...$imagePayload,
                        'product_id' => $product->id,
                        'is_main' => $makeMain,
                    ];

                    if (Schema::hasColumn('product_images', 'is_primary')) {
                        $imageData['is_primary'] = $makeMain;
                    }
                    if (Schema::hasColumn('product_images', 'sort_order')) {
                        $imageData['sort_order'] = ((int) $product->images()->max('sort_order')) + 1;
                    }

                    ProductImage::create($imageData);
                }

                if (Schema::hasColumn('products', 'gallery')) {
                    $product->refresh();
                    $gallery = $product->images()
                        ->get()
                        ->map(fn (ProductImage $image) => $image->path)
                        ->filter()
                        ->values()
                        ->all();
                    $product->updateQuietly(['gallery' => $gallery]);
                }

                return $product->fresh(['images', 'masterProduct']);
            });
        } catch (Throwable $exception) {
            if ($imagePayload) {
                $this->deletePayload($imagePayload, $normalizer);
            }
            throw $exception;
        }

        return [
            'product' => $product,
            'created' => $created,
            'published' => $active,
            'status' => $status,
            'missing' => array_values(array_unique($missing)),
            'image_source' => $imageSource,
        ];
    }

    /**
     * Enregistre rapidement un produit qui n'existe pas encore dans les sources
     * consultables. Il reste obligatoirement en brouillon.
     */
    public function createFieldDraft(
        int $commercialId,
        Shop $shop,
        array $data,
        UploadedFile $photo,
        ProductImageNormalizer $normalizer
    ): Product {
        $imagePayload = $this->normalizeUploadedPhoto($photo, $normalizer);

        try {
            return DB::transaction(function () use (
                $commercialId,
                $shop,
                $data,
                $imagePayload
            ) {
                $stock = (int) ($data['stock'] ?? 0);

                $product = Product::create([
                    'shop_id' => $shop->id,
                    'created_by_commercial_id' => $commercialId,
                    'master_product_id' => null,
                    'category_id' => (int) $data['category_id'],
                    'name' => trim((string) $data['name']),
                    'slug' => Str::slug((string) $data['name'])
                        . '-terrain-'
                        . Str::lower(Str::random(8)),
                    'brand' => filled($data['brand'] ?? null)
                        ? trim((string) $data['brand'])
                        : null,
                    'price' => (float) $data['price'],
                    'stock' => $stock,
                    'availability_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
                    'sale_type' => 'normal',
                    'product_state' => 'new',
                    'unit' => (string) $data['unit'],
                    'min_order_quantity' => (int) ($data['min_order_quantity'] ?? 1),
                    'status' => 'draft',
                    'is_active' => false,
                    'fragile' => null,
                    'requires_unloading' => null,
                    'is_fulfillment_enabled' => false,
                ]);

                $imageData = [
                    ...$imagePayload,
                    'product_id' => $product->id,
                    'is_main' => true,
                ];

                if (Schema::hasColumn('product_images', 'is_primary')) {
                    $imageData['is_primary'] = true;
                }
                if (Schema::hasColumn('product_images', 'sort_order')) {
                    $imageData['sort_order'] = 0;
                }

                ProductImage::create($imageData);

                if (Schema::hasColumn('products', 'gallery')) {
                    $product->updateQuietly(['gallery' => [$imagePayload['path']]]);
                }

                return $product->fresh(['images']);
            });
        } catch (Throwable $exception) {
            $this->deletePayload($imagePayload, $normalizer);
            throw $exception;
        }
    }

    private function reusableOnlineProductsQuery(): Builder
    {
        return Product::query()
            ->active()
            ->notArchived()
            ->whereHas('shop', function (Builder $query) {
                $query->where('status', 'approved')
                    ->where('is_active', true);
            });
    }

    private function isReusableOnlineProduct(Product $product): bool
    {
        if ($product->archived_at !== null || $product->status === 'archived') {
            return false;
        }

        $active = (bool) $product->is_active
            || in_array((string) $product->status, ['actif', 'active', 'approved'], true);

        return $active
            && $product->shop
            && $product->shop->status === 'approved'
            && (bool) $product->shop->is_active;
    }

    private function applyMasterSearch(Builder $query, string $term): void
    {
        $tokens = $this->searchTokens($term);

        $query->where(function (Builder $search) use ($term, $tokens) {
            $like = '%' . $term . '%';

            /*
             * Première branche : expression complète, SKU, référence ou ID.
             */
            $search->where(function (Builder $phraseSearch) use ($like, $term) {
                $phraseSearch->where('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('packaging', 'like', $like);

                if (ctype_digit($term)) {
                    $phraseSearch->orWhereKey((int) $term);
                }
            });

            /*
             * Deuxième branche : chaque mot utile doit être présent dans au
             * moins une colonne. On évite ainsi qu'un seul mot très général,
             * par exemple « ciment », retourne des dizaines de produits sans
             * rapport avec le reste de la recherche.
             */
            if ($tokens !== []) {
                $search->orWhere(function (Builder $allTokens) use ($tokens) {
                    foreach ($tokens as $token) {
                        $allTokens->where(function (Builder $tokenSearch) use ($token) {
                            $tokenLike = '%' . $token . '%';

                            $tokenSearch->where('name', 'like', $tokenLike)
                                ->orWhere('brand', 'like', $tokenLike)
                                ->orWhere('reference', 'like', $tokenLike)
                                ->orWhere('sku', 'like', $tokenLike)
                                ->orWhere('packaging', 'like', $tokenLike);
                        });
                    }
                });
            }
        });
    }

    private function applyProductSearch(Builder $query, string $term): void
    {
        $tokens = $this->searchTokens($term);

        $query->where(function (Builder $search) use ($term, $tokens) {
            $like = '%' . $term . '%';

            $search->where(function (Builder $phraseSearch) use ($like, $term) {
                $phraseSearch->where('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('packaging', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like);

                if (ctype_digit($term)) {
                    $phraseSearch->orWhereKey((int) $term);
                }
            });

            if ($tokens !== []) {
                $search->orWhere(function (Builder $allTokens) use ($tokens) {
                    foreach ($tokens as $token) {
                        $allTokens->where(function (Builder $tokenSearch) use ($token) {
                            $tokenLike = '%' . $token . '%';

                            $tokenSearch->where('name', 'like', $tokenLike)
                                ->orWhere('brand', 'like', $tokenLike)
                                ->orWhere('sku', 'like', $tokenLike)
                                ->orWhere('packaging', 'like', $tokenLike)
                                ->orWhere('short_description', 'like', $tokenLike)
                                ->orWhere('description', 'like', $tokenLike);
                        });
                    }
                });
            }
        });
    }

    /**
     * @return array<int,string>
     */
    private function searchTokens(string $term): array
    {
        $normalized = $this->normalizeSearchText($term);
        $stopWords = [
            'avec', 'dans', 'des', 'du', 'une', 'un', 'pour', 'par', 'sur',
            'sac', 'piece', 'pieces', 'produit', 'produits', 'de', 'la', 'le',
        ];

        return collect(explode(' ', $normalized))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '')
            ->filter(function (string $token) use ($stopWords) {
                if (in_array($token, $stopWords, true)) {
                    return false;
                }

                return ctype_digit($token)
                    ? strlen($token) >= 2
                    : strlen($token) >= 3;
            })
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    private function normalizeSearchText(mixed $value): string
    {
        $value = Str::ascii(Str::lower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function relevanceScore(
        string $term,
        mixed $name,
        mixed $brand = null,
        mixed $sku = null,
        mixed $reference = null,
        mixed $packaging = null
    ): float {
        if (trim($term) === '') {
            return 100;
        }

        $needle = $this->normalizeSearchText($term);
        $normalizedName = $this->normalizeSearchText($name);
        $normalizedSku = $this->normalizeSearchText($sku);
        $normalizedReference = $this->normalizeSearchText($reference);
        $haystack = $this->normalizeSearchText(implode(' ', array_filter([
            $name,
            $brand,
            $sku,
            $reference,
            $packaging,
        ], fn ($value) => filled($value))));

        if ($needle !== '' && in_array($needle, [$normalizedSku, $normalizedReference], true)) {
            return 0;
        }
        if ($needle !== '' && $normalizedName === $needle) {
            return 5;
        }
        if ($needle !== '' && Str::startsWith($normalizedName, $needle)) {
            return 10;
        }
        if ($needle !== '' && Str::contains($haystack, $needle)) {
            return 15;
        }

        $tokens = $this->searchTokens($term);
        $matchedTokens = collect($tokens)
            ->filter(fn (string $token) => Str::contains($haystack, $token))
            ->count();
        $tokenRatio = $tokens === [] ? 0 : $matchedTokens / count($tokens);

        similar_text($needle, $normalizedName, $similarity);

        return 100 - ($tokenRatio * 55) - ($similarity * 0.35);
    }

    private function masterFingerprint(MasterProduct $masterProduct): string
    {
        $code = $this->normalizeSearchText($masterProduct->sku ?: $masterProduct->reference);
        if ($code !== '') {
            return 'code:' . $code;
        }

        return 'attrs:' . hash('sha256', implode('|', [
            $this->normalizeSearchText($masterProduct->name),
            $this->normalizeSearchText($masterProduct->brand),
            (string) $masterProduct->category_id,
            $this->normalizeSearchText($masterProduct->unit),
            $this->normalizeSearchText($masterProduct->packaging),
            $this->normalizedNumber($masterProduct->weight_kg),
            $this->normalizedNumber($masterProduct->length_cm),
            $this->normalizedNumber($masterProduct->width_cm),
            $this->normalizedNumber($masterProduct->height_cm),
        ]));
    }

    private function productFingerprint(Product $product): string
    {
        $code = $this->normalizeSearchText($product->sku);
        if ($code !== '') {
            return 'code:' . $code;
        }

        return 'attrs:' . hash('sha256', implode('|', [
            $this->normalizeSearchText($product->name),
            $this->normalizeSearchText($product->brand),
            (string) $product->category_id,
            $this->normalizeSearchText($product->unit),
            $this->normalizeSearchText($product->packaging),
            $this->normalizedNumber($this->productWeight($product)),
            $this->normalizedNumber($product->length_cm),
            $this->normalizedNumber($product->width_cm),
            $this->normalizedNumber($product->height_cm),
        ]));
    }

    private function normalizedNumber(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function findMatchingMasterProduct(Product $source): ?MasterProduct
    {
        $sku = trim((string) $source->sku);

        if ($sku !== '') {
            $byCode = MasterProduct::query()
                ->where('is_active', true)
                ->where(function (Builder $query) use ($sku) {
                    $query->where('sku', $sku)
                        ->orWhere('reference', $sku);
                })
                ->first();

            if ($byCode) {
                return $byCode;
            }
        }

        $candidates = MasterProduct::query()
            ->where('is_active', true)
            ->where('category_id', $source->category_id)
            ->where('name', $source->name)
            ->limit(20)
            ->get();

        $sourceFingerprint = $this->productFingerprint($source);

        return $candidates->first(
            fn (MasterProduct $candidate) => $this->masterFingerprint($candidate) === $sourceFingerprint
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function masterAttributesFromProduct(Product $source): array
    {
        $length = (float) $source->length_cm;
        $width = (float) $source->width_cm;
        $height = (float) $source->height_cm;
        $volume = ($length > 0 && $width > 0 && $height > 0)
            ? round(($length * $width * $height) / 1_000_000, 6)
            : ((float) $source->volume_m3 ?: null);

        return [
            'name' => $source->name,
            'brand' => $source->brand,
            'reference' => $source->sku,
            'sku' => $source->sku,
            'category_id' => $source->category_id,
            'unit' => $source->unit,
            'packaging' => $source->packaging,
            'weight_kg' => $this->productWeight($source) ?: null,
            'volume_m3' => $volume,
            'length_cm' => $source->length_cm,
            'width_cm' => $source->width_cm,
            'height_cm' => $source->height_cm,
            'color' => $source->color,
            'grade' => $source->material_grade,
            'standard' => $source->standard,
            'description' => $source->description,
            'short_description' => $source->short_description,
            'technical_details' => $source->technical_details,
            'product_attributes' => $source->product_attributes ?: [],
            'fragile' => $this->rawNullableBoolean($source, 'fragile'),
            'requires_unloading' => $this->rawNullableBoolean($source, 'requires_unloading'),
            'unloading_instructions' => $source->requires_unloading
                ? $source->unloading_instructions
                : null,
            'is_active' => true,
        ];
    }

    private function productWeight(Product $product): float
    {
        $weightKg = (float) $product->weight_kg;

        return $weightKg > 0 ? $weightKg : (float) $product->weight;
    }

    private function rawNullableBoolean(Product $product, string $attribute): ?bool
    {
        $raw = $product->getAttribute($attribute);

        if ($raw === null || $raw === '') {
            return null;
        }

        return (bool) $raw;
    }

    /**
     * @return array<string,mixed>
     */
    private function safeExistingOfferPayload(Product $offer): array
    {
        return [
            'id' => $offer->id,
            'price' => $offer->price,
            'promo_price' => $offer->promo_price,
            'stock' => $offer->stock,
            'min_order_quantity' => $offer->min_order_quantity,
            'availability_status' => $offer->availability_status,
            'status' => $offer->status,
            'is_active' => $offer->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productAttributes(
        int $commercialId,
        Shop $shop,
        MasterProduct $masterProduct,
        array $offer,
        string $status,
        bool $active,
        ?Product $existingProduct
    ): array {
        $length = (float) $masterProduct->length_cm;
        $width = (float) $masterProduct->width_cm;
        $height = (float) $masterProduct->height_cm;
        $volume = ($length > 0 && $width > 0 && $height > 0)
            ? round(($length * $width * $height) / 1_000_000, 6)
            : ((float) $masterProduct->volume_m3 ?: null);

        $stock = (int) ($offer['stock'] ?? 0);
        $availability = (string) ($offer['availability_status'] ?? '');
        if ($availability === '') {
            $availability = $stock > 0 ? 'in_stock' : 'out_of_stock';
        }

        $attributes = [
            'shop_id' => $shop->id,
            'master_product_id' => $masterProduct->id,
            'created_by_commercial_id' => $existingProduct?->created_by_commercial_id ?: $commercialId,
            'category_id' => $masterProduct->category_id,
            'sku' => $masterProduct->sku ?: $masterProduct->reference,
            'name' => $masterProduct->name,
            'description' => $masterProduct->description,
            'short_description' => $masterProduct->short_description,
            'technical_details' => $masterProduct->technical_details,
            'product_attributes' => $masterProduct->product_attributes ?: [],
            'price' => $offer['price'],
            'promo_price' => $offer['promo_price'] ?? null,
            'stock' => $stock,
            'availability_status' => $availability,
            'status' => $status,
            'is_active' => $active,
            'sale_type' => 'normal',
            'product_state' => 'new',
            'unit' => $masterProduct->unit,
            'min_order_quantity' => $offer['min_order_quantity'] ?? 1,
            'packaging' => $masterProduct->packaging,
            'brand' => $masterProduct->brand,
            'material_grade' => $masterProduct->grade,
            'color' => $masterProduct->color,
            'standard' => $masterProduct->standard,
            'weight_kg' => $masterProduct->weight_kg,
            'length_cm' => $masterProduct->length_cm,
            'width_cm' => $masterProduct->width_cm,
            'height_cm' => $masterProduct->height_cm,
            'volume_m3' => $volume,
            'fragile' => $masterProduct->fragile,
            'requires_unloading' => $masterProduct->requires_unloading,
            'unloading_instructions' => $masterProduct->requires_unloading
                ? $masterProduct->unloading_instructions
                : null,
            'is_fulfillment_enabled' => $this->missingMasterFields($masterProduct) === [],
        ];

        if (! $existingProduct) {
            $attributes['slug'] = Str::slug((string) $masterProduct->name)
                . '-'
                . Str::lower(Str::random(10));
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUploadedPhoto(
        UploadedFile $photo,
        ProductImageNormalizer $normalizer
    ): array {
        $result = $normalizer->normalize($photo, 'products');

        return [
            'path' => $result['master'],
            'original_path' => $result['original'],
            'card_path' => $result['card'],
            'thumb_path' => $result['thumb'],
            'original_width' => $result['original_width'],
            'original_height' => $result['original_height'],
            'normalized_at' => now(),
        ];
    }

    /**
     * Copie une image de catalogue dans un jeu de fichiers indépendant afin
     * qu'une suppression future d'une offre ne casse pas les autres offres.
     *
     * @return array<string, mixed>
     */
    private function cloneCatalogImage(
        ProductImage $source,
        ProductImageNormalizer $normalizer
    ): array {
        $diskName = (string) config('product-images.disk', 'public');
        $disk = Storage::disk($diskName);

        $sourceOriginal = $this->firstExistingPath([
            $source->original_path,
            $source->path,
            $source->card_path,
            $source->thumb_path,
            $source->image_path,
            $source->file_path,
            $source->filename,
        ]);

        if (! $sourceOriginal) {
            throw new RuntimeException('L’image du catalogue est introuvable dans le stockage.');
        }

        $uuid = (string) Str::uuid();
        $month = now()->format('Y/m');
        $originalExtension = $this->safeExtension($sourceOriginal);
        $originalDestination = "products/originals/{$month}/{$uuid}.{$originalExtension}";

        if (! $disk->copy($sourceOriginal, $originalDestination)) {
            throw new RuntimeException('Impossible de copier l’image originale du catalogue.');
        }

        $variantSources = [
            'master' => $this->firstExistingPath([$source->path]),
            'card' => $this->firstExistingPath([$source->card_path]),
            'thumb' => $this->firstExistingPath([$source->thumb_path]),
        ];

        $allVariantsExist = collect($variantSources)->every(fn ($path) => filled($path));
        $copied = [];

        try {
            if ($allVariantsExist) {
                $base = "products/normalized/{$month}/{$uuid}";

                foreach ($variantSources as $variant => $path) {
                    $destination = "{$base}/{$variant}.{$this->safeExtension($path)}";
                    if (! $disk->copy($path, $destination)) {
                        throw new RuntimeException("Impossible de copier la variante {$variant} du catalogue.");
                    }
                    $copied[$variant] = $destination;
                }

                return [
                    'path' => $copied['master'],
                    'original_path' => $originalDestination,
                    'card_path' => $copied['card'],
                    'thumb_path' => $copied['thumb'],
                    'original_width' => $source->original_width ?: 1200,
                    'original_height' => $source->original_height ?: 1200,
                    'normalized_at' => now(),
                ];
            }

            $normalized = $normalizer->normalizeStoredPath($originalDestination, 'products');

            return [
                'path' => $normalized['master'],
                'original_path' => $originalDestination,
                'card_path' => $normalized['card'],
                'thumb_path' => $normalized['thumb'],
                'original_width' => $normalized['original_width'],
                'original_height' => $normalized['original_height'],
                'normalized_at' => now(),
            ];
        } catch (Throwable $exception) {
            $disk->delete(array_filter([
                $originalDestination,
                $copied['master'] ?? null,
                $copied['card'] ?? null,
                $copied['thumb'] ?? null,
            ]));
            throw $exception;
        }
    }

    private function deletePayload(
        array $payload,
        ProductImageNormalizer $normalizer
    ): void {
        $normalizer->deleteNormalizedSet([
            'master' => $payload['path'] ?? null,
            'card' => $payload['card_path'] ?? null,
            'thumb' => $payload['thumb_path'] ?? null,
        ]);

        Storage::disk((string) config('product-images.disk', 'public'))
            ->delete(array_filter([$payload['original_path'] ?? null]));
    }

    /**
     * @param array<int, mixed> $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        $disk = Storage::disk((string) config('product-images.disk', 'public'));

        foreach ($paths as $path) {
            $clean = $this->cleanStoragePath($path);
            if ($clean !== '' && $disk->exists($clean)) {
                return $clean;
            }
        }

        return null;
    }

    private function cleanStoragePath(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $path = trim(str_replace('\\', '/', $value));
        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return '';
        }

        if (preg_match('#(?:^|/)storage/(.+)$#i', $path, $matches) === 1) {
            return ltrim($matches[1], '/');
        }

        $path = ltrim($path, '/');
        foreach (['public/storage/', 'storage/', 'app/public/', 'public/'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                $path = Str::after($path, $prefix);
                break;
            }
        }

        return ltrim($path, '/');
    }

    private function safeExtension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : 'webp';
    }
}
