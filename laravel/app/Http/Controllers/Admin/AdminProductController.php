<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Jobs\SendSmsJob;
use App\Models\Category;
use App\Models\Shop;
use App\Services\ProductImageNormalizer;
use App\Services\ProductVariantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminProductController extends Controller
{
    // ID de boutique par défaut pour l'administrateur
    private const ADMIN_SHOP_ID = 1;

    /**
     * Le catalogue est partagé entre l'administration et le Support Center :
     * mêmes écrans et mêmes règles, mais le Support ne peut jamais supprimer un produit.
     */
    private function inSupportSpace(): bool
    {
        return request()->routeIs('support.*');
    }

    private function productRoutes(): string
    {
        return $this->inSupportSpace() ? 'support.products' : 'admin.products';
    }

    private function shareSpaceContext(): void
    {
        view()->share([
            'productRoutes' => $this->productRoutes(),
            'catalogueLayout' => $this->inSupportSpace() ? 'layouts.staff' : 'admin.layouts.app',
            'spaceLabel' => $this->inSupportSpace() ? 'Support Center OVANIE' : 'Administration OVANIE',
            'isAdminSpace' => ! $this->inSupportSpace(),
            'canDeleteProducts' => ! $this->inSupportSpace(),
        ]);
    }

    /**
     * Afficher la liste des produits
     */
    public function index(Request $request)
    {
        $this->shareSpaceContext();
        $activeStatuses = ['actif', 'active', 'approved', 'published'];
        $productTable = (new Product())->getTable();

        $query = Product::query()
            ->with([
                'images',
                'variants',
                'category.parent',
                'shop.user',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.brand', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('parent', fn ($parentQuery) => $parentQuery
                                ->where('name', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('shop', function ($shopQuery) use ($search) {
                        $shopQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('shop_id')) {
            $query->where('products.shop_id', (int) $request->input('shop_id'));
        }

        if ($request->filled('main_category_id')) {
            $mainCategoryId = (int) $request->input('main_category_id');

            $query->whereHas('category', function ($categoryQuery) use ($mainCategoryId) {
                $categoryQuery->where(function ($builder) use ($mainCategoryId) {
                    $builder
                        ->where('categories.parent_id', $mainCategoryId)
                        ->orWhere(function ($legacyQuery) use ($mainCategoryId) {
                            $legacyQuery
                                ->where('categories.id', $mainCategoryId)
                                ->whereNull('categories.parent_id');
                        });
                });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('products.category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('logistics_type')) {
            $logisticsType = (string) $request->input('logistics_type');

            $query->whereHas('shop', function ($shopQuery) use ($logisticsType) {
                if ($logisticsType === 'seller') {
                    $shopQuery->where('logistics_type', 'seller');

                    return;
                }

                $shopQuery->where(function ($modeQuery) {
                    $modeQuery
                        ->whereNull('logistics_type')
                        ->orWhereIn('logistics_type', ['ovanie', 'ovanie_logistics'])
                        ->orWhere('logistics_type', '!=', 'seller');
                });
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === 'active') {
                $query->where('products.is_active', true)
                    ->whereIn('products.status', $activeStatuses);
            } elseif ($status === 'inactive') {
                $query->where(function ($builder) use ($activeStatuses) {
                    $builder
                        ->where('products.is_active', false)
                        ->orWhereNull('products.status')
                        ->orWhereNotIn('products.status', $activeStatuses);
                });
            } elseif ($status === 'out_of_stock') {
                $query->where('products.stock', '<=', 0);
            } elseif ($status === 'draft') {
                $query->whereIn('products.status', ['draft', 'brouillon']);
            } elseif ($status === 'pending_logistics') {
                $query->whereIn('products.status', ['pending_logistics', 'incomplete', 'logistics_incomplete']);
            } elseif ($status === 'commercial_capture') {
                $query->whereNotNull('products.created_by_commercial_id')
                    ->where('products.product_attributes->admin_completion_required', true);
            } elseif ($status === 'archived') {
                $query->where(function ($builder) use ($productTable) {
                    if (Schema::hasColumn($productTable, 'archived_at')) {
                        $builder->whereNotNull('products.archived_at')
                            ->orWhere('products.status', 'archived');

                        return;
                    }

                    $builder->where('products.status', 'archived');
                });
            } elseif ($status === 'incomplete_logistics') {
                $this->applyIncompleteLogisticsFilter($query);
            }
        }

        $sort = (string) $request->input('sort', 'latest');

        match ($sort) {
            'name' => $query->orderBy('products.name'),
            'price_asc' => $query->orderBy('products.price'),
            'price_desc' => $query->orderByDesc('products.price'),
            'stock_asc' => $query->orderBy('products.stock'),
            'stock_desc' => $query->orderByDesc('products.stock'),
            default => $query->latest('products.id'),
        };

        $products = $query
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()
            ->orderBy('name')
            ->get();
        $mainCategories = $categories->whereNull('parent_id')->values();
        $subCategories = $categories->whereNotNull('parent_id')->values();

        $shops = \App\Models\Shop::query()
            ->with('user')
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $summary = [
            'total' => Product::query()->count(),
            'published' => Product::query()
                ->where('is_active', true)
                ->whereIn('status', $activeStatuses)
                ->count(),
            'out_of_stock' => Product::query()
                ->where('stock', '<=', 0)
                ->count(),
            'incomplete_logistics' => $this->countProductsWithIncompleteLogistics(),
            'commercial_capture_incomplete' => Product::query()
                ->whereNotNull('created_by_commercial_id')
                ->where('product_attributes->admin_completion_required', true)
                ->count(),
        ];

        return view('admin.products.index', compact(
            'products',
            'mainCategories',
            'subCategories',
            'shops',
            'summary'
        ));
    }

    private function applyIncompleteLogisticsFilter($query): void
    {
        $query->where(function ($builder) {
            $builder
                ->whereNull('products.weight_kg')
                ->orWhere('products.weight_kg', '<=', 0)
                ->orWhereNull('products.length_cm')
                ->orWhere('products.length_cm', '<=', 0)
                ->orWhereNull('products.width_cm')
                ->orWhere('products.width_cm', '<=', 0)
                ->orWhereNull('products.height_cm')
                ->orWhere('products.height_cm', '<=', 0)
                ->orWhereNull('products.volume_m3')
                ->orWhere('products.volume_m3', '<=', 0);
        });
    }

    private function countProductsWithIncompleteLogistics(): int
    {
        $query = Product::query();
        $this->applyIncompleteLogisticsFilter($query);

        return $query->count();
    }

    /**
     * Afficher le formulaire de création
     */
    public function create(Request $request)
    {
        $this->shareSpaceContext();
        $categories = Category::query()->active()->ordered()->get();
        $mainCategories = $categories->whereNull('parent_id')->values();
        $subCategories = $categories->whereNotNull('parent_id')->values();
        $shops = Shop::query()->with('user')->orderBy('name')->get();
        $selectedShopId = $request->integer('shop_id') ?: null;
        $selectedShop = $selectedShopId ? $shops->firstWhere('id', $selectedShopId) : null;

        return view('admin.products.create', compact(
            'mainCategories',
            'subCategories',
            'shops',
            'selectedShopId',
            'selectedShop'
        ));
    }

    /**
     * Stocker un nouveau produit
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'name' => 'required|string|max:255',
            'main_category_id' => 'required|exists:categories,id',
            'category_id' => 'required|exists:categories,id',
            'sale_type' => 'required|string|max:255',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|integer|min:0',
            'transport' => 'nullable|numeric|min:0',
            'description' => 'required|string',
            'images' => 'required|array|min:1|max:20',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'promo_price' => 'nullable|numeric|min:0',
            'price_p1' => 'nullable|numeric|min:0',
            'price_p2' => 'nullable|numeric|min:0',
            'price_p3' => 'nullable|numeric|min:0',
            'flash_end' => 'nullable|date',
            'bf_start' => 'nullable|date',
            'bf_end' => 'nullable|date',
            'fast_delivery' => 'nullable|boolean',
            'brand' => 'nullable|string|max:255',
            'unit' => 'required|string|max:80',
            'min_order_quantity' => 'required|integer|min:1',
            'packaging' => 'nullable|string|max:255',
            'weight_kg' => 'required|numeric|min:0.01',
            'length_cm' => 'required|numeric|min:0.1',
            'width_cm' => 'required|numeric|min:0.1',
            'height_cm' => 'required|numeric|min:0.1',
            'fragile' => 'required|boolean',
            'requires_unloading' => 'required|boolean',
            'unloading_instructions' => 'nullable|required_if:requires_unloading,1|string|max:2000',
        ], [
            'main_category_id.required' => 'Sélectionnez une catégorie principale.',
            'main_category_id.exists' => 'La catégorie principale sélectionnée est invalide.',
            'category_id.required' => 'Sélectionnez une sous-catégorie.',
            'category_id.exists' => 'La sous-catégorie sélectionnée est invalide.',
            'images.max' => 'Vous pouvez ajouter au maximum 20 images par produit.',
        ]);

        $mainCategory = Category::query()->find((int) $validated['main_category_id']);
        $subCategory = Category::query()->find((int) $validated['category_id']);

        if (! $mainCategory || $mainCategory->parent_id !== null) {
            throw ValidationException::withMessages([
                'main_category_id' => 'Sélectionnez une catégorie principale valide.',
            ]);
        }

        if (! $subCategory || (int) $subCategory->parent_id !== (int) $mainCategory->id) {
            throw ValidationException::withMessages([
                'category_id' => 'La sous-catégorie doit appartenir à la catégorie principale sélectionnée.',
            ]);
        }

        $shop = Shop::query()->with('user')->findOrFail((int) $validated['shop_id']);
        $checkoutBlocked = ! $shop->canPublishProducts();
        $isNegotiable = ! empty($validated['price_p1']) && ! empty($validated['price_p2']) && ! empty($validated['price_p3']);

        $normalizedUploads = [];
        $normalizer = app(ProductImageNormalizer::class);

        try {
            foreach ($request->file('images', []) as $image) {
                $normalizedUploads[] = $normalizer->normalize($image, 'products');
            }
        } catch (Throwable $exception) {
            $this->deleteNormalizedUploads($normalizedUploads, $normalizer);
            throw ValidationException::withMessages(['images' => $exception->getMessage()]);
        }

        try {
            $product = DB::transaction(function () use ($request, $validated, $shop, $checkoutBlocked, $isNegotiable, $normalizedUploads) {
                $product = Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $validated['category_id'],
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']) . '-' . uniqid(),
                    'sale_type' => $validated['sale_type'],
                    'description' => $validated['description'],
                    'brand' => $validated['brand'] ?? null,
                    'unit' => $validated['unit'],
                    'min_order_quantity' => $validated['min_order_quantity'],
                    'packaging' => $validated['packaging'] ?? null,
                    'weight_kg' => $validated['weight_kg'],
                    'length_cm' => $validated['length_cm'],
                    'width_cm' => $validated['width_cm'],
                    'height_cm' => $validated['height_cm'],
                    'volume_m3' => round(((float) $validated['length_cm'] * (float) $validated['width_cm'] * (float) $validated['height_cm']) / 1000000, 6),
                    'fragile' => $request->boolean('fragile'),
                    'requires_unloading' => $request->boolean('requires_unloading'),
                    'unloading_instructions' => $request->boolean('requires_unloading') ? ($validated['unloading_instructions'] ?? null) : null,
                    'price' => $validated['price'],
                    'stock' => $validated['stock'],
                    'transport' => $validated['transport'] ?? 0,
                    'promo_price' => $validated['promo_price'] ?? null,
                    'price_p1' => $validated['price_p1'] ?? null,
                    'price_p2' => $validated['price_p2'] ?? null,
                    'price_p3' => $validated['price_p3'] ?? null,
                    'is_negotiable' => $isNegotiable,
                    'flash_end' => $validated['flash_end'] ?? null,
                    'bf_start' => $validated['bf_start'] ?? null,
                    'bf_end' => $validated['bf_end'] ?? null,
                    'fast_delivery' => $request->boolean('fast_delivery'),
                    // Une fiche complète créée par l'administration est publiée immédiatement.
                    // L'éligibilité à la commande reste contrôlée séparément par la boutique au checkout.
                    'is_active' => true,
                    'status' => 'actif',
                    'created_by_admin_id' => auth('admin')->id(),
                    'updated_by_admin_id' => auth('admin')->id(),
                    'created_on_behalf_of_vendor' => true,
                    'admin_last_image_update_at' => now(),
                    'product_attributes' => [
                        'admin_assistance' => true,
                        'admin_created_at' => now()->toIso8601String(),
                        'admin_created_by' => auth('admin')->id(),
                    ],
                ]);

                $this->syncProductDeliveryZones($product, $request);

                foreach ($normalizedUploads as $index => $result) {
                    $product->images()->create($this->normalizedImagePayload($result, $index === 0, $index));
                }

                return $product;
            });
        } catch (Throwable $exception) {
            $this->deleteNormalizedUploads($normalizedUploads, $normalizer);
            throw $exception;
        }

        app(ProductVariantService::class)->sync($product, $request);

        $message = $checkoutBlocked
            ? 'Produit ajouté et publié en ligne. La boutique reste toutefois bloquée au checkout tant que la logistique et le reversement ne sont pas régularisés.'
            : 'Produit ajouté pour le vendeur et publié en ligne avec les images normalisées OVANIE.';

        return redirect()
            ->route($this->productRoutes() . '.edit', $product)
            ->with($checkoutBlocked ? 'warning' : 'success', $message);
    }
    /**
     * Afficher le formulaire d'édition.
     */
    public function edit(Product $product)
    {
        $this->shareSpaceContext();
        $categories = Category::query()->active()->ordered()->get();
        $mainCategories = $categories->whereNull('parent_id')->values();
        $subCategories = $categories->whereNotNull('parent_id')->values();

        $product->load([
            'images',
            'variants',
            'category.parent',
            'shop.user',
            'masterProduct',
        ]);

        $selectedMainCategoryId = old(
            'main_category_id',
            $product->category?->parent_id ?: $product->category_id
        );
        $selectedSubCategoryId = old(
            'category_id',
            $product->category?->parent_id ? $product->category_id : null
        );

        return view('admin.products.edit', compact(
            'product',
            'mainCategories',
            'subCategories',
            'selectedMainCategoryId',
            'selectedSubCategoryId'
        ));
    }

    /**
     * Mettre à jour un produit.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'product_state' => ['required', Rule::in(['new', 'used', 'refurbished'])],
            'main_category_id' => ['required', 'exists:categories,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'sale_type' => ['required', 'string', 'max:80'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:10000'],
            'technical_details' => ['nullable', 'string', 'max:10000'],

            'price' => ['required', 'numeric', 'min:1'],
            'promo_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'availability_status' => ['required', Rule::in(['in_stock', 'out_of_stock', 'on_order', 'preorder'])],
            'unit' => ['required', 'string', 'max:80'],
            'min_order_quantity' => ['required', 'integer', 'min:1'],
            'packaging' => ['nullable', 'string', 'max:255'],

            'weight_kg' => ['required', 'numeric', 'min:0.01'],
            'length_cm' => ['required', 'numeric', 'min:0.1'],
            'width_cm' => ['required', 'numeric', 'min:0.1'],
            'height_cm' => ['required', 'numeric', 'min:0.1'],
            'fragile' => ['required', 'boolean'],
            'requires_unloading' => ['required', 'boolean'],
            'unloading_instructions' => [
                'nullable',
                Rule::requiredIf($request->boolean('requires_unloading')),
                'string',
                'max:2000',
            ],

            'fast_delivery' => ['required', 'boolean'],
            'commission_paid' => ['required', 'boolean'],
            'lock_contacts' => ['required', 'boolean'],

            'flash_start_at' => ['nullable', 'date'],
            'flash_end' => ['nullable', 'date', 'after_or_equal:flash_start_at'],
            'bf_start' => ['nullable', 'date'],
            'bf_end' => ['nullable', 'date', 'after_or_equal:bf_start'],

            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => [
                'integer',
                Rule::exists('product_images', 'id')->where(
                    fn ($query) => $query->where('product_id', $product->id)
                ),
            ],
            'main_image' => [
                'nullable',
                'integer',
                Rule::exists('product_images', 'id')->where(
                    fn ($query) => $query->where('product_id', $product->id)
                ),
            ],
        ], [
            'promo_price.lt' => 'Le prix promotionnel doit être inférieur au prix normal.',
            'unloading_instructions.required' => 'Précisez les instructions de déchargement.',
            'images.max' => 'Vous pouvez ajouter au maximum 20 nouvelles images.',
        ]);

        $mainCategory = Category::query()->find((int) $validated['main_category_id']);
        $subCategory = Category::query()->find((int) $validated['category_id']);

        if (! $mainCategory || $mainCategory->parent_id !== null) {
            throw ValidationException::withMessages([
                'main_category_id' => 'Sélectionnez une catégorie principale valide.',
            ]);
        }

        if (! $subCategory || (int) $subCategory->parent_id !== (int) $mainCategory->id) {
            throw ValidationException::withMessages([
                'category_id' => 'La sous-catégorie doit appartenir à la catégorie principale sélectionnée.',
            ]);
        }

        $validated['volume_m3'] = round(
            ((float) $validated['length_cm']
                * (float) $validated['width_cm']
                * (float) $validated['height_cm']) / 1_000_000,
            6
        );
        $validated['slug'] = Str::slug($validated['name']) . '-' . $product->id;
        $validated['unloading_instructions'] = $request->boolean('requires_unloading')
            ? ($validated['unloading_instructions'] ?? null)
            : null;
        if ((int) $validated['stock'] === 0) {
            $validated['availability_status'] = 'out_of_stock';
        }

        $normalizedUploads = [];
        $normalizer = app(ProductImageNormalizer::class);

        try {
            foreach ($request->file('images', []) as $image) {
                $normalizedUploads[] = $normalizer->normalize($image, 'products');
            }
        } catch (Throwable $exception) {
            $this->deleteNormalizedUploads($normalizedUploads, $normalizer);

            throw ValidationException::withMessages([
                'images' => $exception->getMessage(),
            ]);
        }

        $imagesToDelete = collect();

        try {
            DB::transaction(function () use (
                $validated,
                $request,
                $product,
                $normalizedUploads,
                &$imagesToDelete
            ) {
                $productUpdate = collect($validated)->except([
                    'main_category_id',
                    'images',
                    'remove_images',
                    'main_image',
                ])->all();
                $productUpdate['updated_by_admin_id'] = auth('admin')->id();
                if ($normalizedUploads !== [] || ! empty($validated['remove_images']) || ! empty($validated['main_image'])) {
                    $productUpdate['admin_last_image_update_at'] = now();
                }

                $attributes = (array) ($product->product_attributes ?? []);
                if (($attributes['commercial_capture'] ?? false) === true) {
                    $attributes['admin_completion_required'] = false;
                    $attributes['admin_completed_at'] = now()->toIso8601String();
                    $attributes['admin_completed_by'] = auth('admin')->id();
                    $productUpdate['product_attributes'] = $attributes;
                }

                $product->update($productUpdate);

                $removeIds = collect($validated['remove_images'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($removeIds->isNotEmpty()) {
                    $imagesToDelete = $product->images()
                        ->whereIn('id', $removeIds)
                        ->get();

                    $product->images()
                        ->whereIn('id', $removeIds)
                        ->delete();
                }

                $hasMainImage = $product->images()->where('is_main', true)->exists();

                foreach ($normalizedUploads as $index => $result) {
                    $isMain = ! $hasMainImage && $index === 0;
                    $payload = $this->normalizedImagePayload($result, $isMain, $index);
                    $product->images()->create($payload);

                    if ($isMain) {
                        $hasMainImage = true;
                    }
                }

                $selectedMainImage = isset($validated['main_image'])
                    ? (int) $validated['main_image']
                    : null;

                if ($selectedMainImage !== null
                    && ! collect($validated['remove_images'] ?? [])->contains($selectedMainImage)
                    && $product->images()->whereKey($selectedMainImage)->exists()) {
                    $product->images()->update(['is_main' => false]);
                    $product->images()->whereKey($selectedMainImage)->update(['is_main' => true]);
                }

                if (! $product->images()->where('is_main', true)->exists()) {
                    $fallback = $product->images()->oldest('id')->first();

                    if ($fallback) {
                        $fallback->update(['is_main' => true]);
                    }
                }

                if (! $product->images()->exists()) {
                    throw ValidationException::withMessages([
                        'images' => 'Une image produit est obligatoire avant la publication.',
                    ]);
                }

                // Toute fiche enregistrée par l'administration est remise en ligne.
                // La configuration boutique ne masque pas le produit : elle bloque la commande au checkout.
                $publishPayload = [
                    'status' => 'actif',
                    'is_active' => true,
                ];

                $productTable = $product->getTable();
                if (Schema::hasColumn($productTable, 'archived_at')) {
                    $publishPayload['archived_at'] = null;
                }
                if (Schema::hasColumn($productTable, 'archived_by')) {
                    $publishPayload['archived_by'] = null;
                }
                if (Schema::hasColumn($productTable, 'archive_reason')) {
                    $publishPayload['archive_reason'] = null;
                }

                $attributes = (array) ($product->product_attributes ?? []);
                $attributes['admin_published_at'] = now()->toIso8601String();
                $attributes['admin_published_by'] = auth('admin')->id();
                $publishPayload['product_attributes'] = $attributes;

                $product->forceFill($publishPayload)->save();

                $this->syncProductDeliveryZones($product, $request);
            });
        } catch (Throwable $exception) {
            $this->deleteNormalizedUploads($normalizedUploads, $normalizer);
            throw $exception;
        }

        app(ProductVariantService::class)->sync($product, $request);

        foreach ($imagesToDelete as $image) {
            $this->deleteProductImageFiles($image);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour et publié en ligne.',
                'product_id' => $product->id,
            ]);
        }

        $product->loadMissing('shop');
        $checkoutBlocked = ! $product->shop || ! $product->shop->canPublishProducts();

        return redirect()
            ->route($this->productRoutes() . '.edit', $product)
            ->with($checkoutBlocked ? 'warning' : 'success', $checkoutBlocked
                ? 'Les modifications sont en ligne. Le checkout reste bloqué pour cette boutique tant que la logistique et le reversement ne sont pas conformes.'
                : 'Les modifications ont été enregistrées et le produit est en ligne.');
    }

    public function show(Request $request, Product $product)
    {
        $this->shareSpaceContext();
        $product->load([
            'images',
            'variants',
            'category.parent',
            'shop.user',
            'masterProduct',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'sale_type' => $product->sale_type,
                'promo_price' => $product->promo_price,
                'stock' => $product->stock,
                'category_id' => $product->category_id,
                'commission_paid' => $product->commission_paid ?? false,
                'lock_contacts' => $product->lock_contacts ?? false,
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => $image->path,
                    ];
                })->values(),
            ]);
        }

        return view('admin.products.show', compact('product'));
    }

    /**
     * Supprimer un produit.
     *
     * Bug rapporté par l'utilisateur : la suppression ne se répercutait
     * parfois ni sur l'accueil ni sur la base de données. Cause : dès
     * qu'un produit a été commandé au sein d'une commande multi-articles,
     * il reste référencé par order_items.product_id (contrainte
     * restrictOnDelete) même si orders.product_id (cascadeOnDelete) pointe
     * vers un AUTRE produit de la même commande. Product::delete() levait
     * alors une QueryException non interceptée : la requête échouait (page
     * d'erreur), le produit restait donc bien en base et visible partout.
     *
     * Un produit ayant un historique de commandes ne doit de toute façon
     * jamais être supprimé physiquement (factures, litiges, statistiques).
     * On l'archive donc à la place (archived_at/archived_by + is_active à
     * false), ce qui le retire immédiatement du catalogue public via
     * PublicProductVisibilityService (qui exclut déjà archived_at non nul).
     * Un produit jamais commandé continue d'être supprimé physiquement.
     */
    public function destroy(Product $product)
    {
        // Le Support ajoute et modifie, mais ne supprime jamais un produit.
        abort_if($this->inSupportSpace(), 403, 'La suppression d’un produit est réservée à l’administration.');

        $hasOrderHistory = $product->orderItems()->exists();

        if ($hasOrderHistory) {
            $product->update([
                'is_active' => false,
                'status' => 'archived',
                'archived_at' => now(),
                'archived_by' => auth('admin')->id(),
                'archive_reason' => 'Archivé par un administrateur. Historique de commande conservé.',
                'is_boosted' => false,
                'boost_end_at' => now(),
            ]);

            return redirect()
                ->route('admin.products.index')
                ->with('success', 'Ce produit a des commandes associées : il a été archivé et retiré du catalogue au lieu d’être supprimé, afin de préserver l’historique des commandes.');
        }

        $product->loadMissing('images');

        foreach ($product->images as $image) {
            $this->deleteProductImageFiles($image);
            $image->delete();
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Produit supprimé avec succès.');
    }

    private function normalizedImagePayload(array $result, bool $isMain, int $sortOrder): array
    {
        $payload = [
            'path' => $result['master'],
            'is_main' => $isMain,
        ];

        $table = (new ProductImage())->getTable();

        $optionalColumns = [
            'original_path' => $result['original'] ?? null,
            'card_path' => $result['card'] ?? null,
            'thumb_path' => $result['thumb'] ?? null,
            'original_width' => $result['original_width'] ?? null,
            'original_height' => $result['original_height'] ?? null,
            'normalized_at' => now(),
            'is_primary' => $isMain,
            'sort_order' => $sortOrder,
        ];

        foreach ($optionalColumns as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    private function deleteNormalizedUploads(
        array $uploads,
        ProductImageNormalizer $normalizer
    ): void {
        foreach ($uploads as $upload) {
            $normalizer->deleteNormalizedSet($upload);

            $original = (string) ($upload['original'] ?? '');
            if ($original !== '' && Storage::disk('public')->exists($original)) {
                Storage::disk('public')->delete($original);
            }
        }
    }

    private function deleteProductImageFiles(ProductImage $image): void
    {
        $paths = collect([
            $image->path,
            $image->original_path,
            $image->card_path,
            $image->thumb_path,
            $image->image_path,
            $image->file_path,
        ])->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->unique();

        foreach ($paths as $path) {
            $cleanPath = preg_replace('#^/?storage/#', '', str_replace('\\', '/', $path));

            if (is_string($cleanPath)
                && $cleanPath !== ''
                && Storage::disk('public')->exists($cleanPath)) {
                Storage::disk('public')->delete($cleanPath);
            }
        }
    }

    private function syncProductDeliveryZones(Product $product, Request $request): void
    {
        $product->deliveryZones()->delete();
    }

    public function notifyVendor(Product $product)
    {
        $phone = $product->shop->mm_number ?? null;

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro vendeur introuvable'
            ], 400);
        }

        SendSmsJob::dispatch(
            $phone,
            "Un client souhaite des informations sur votre produit : {$product->name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'SMS envoyé au vendeur'
        ]);
    }


}
