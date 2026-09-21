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
use App\Services\ProductCalculatorService;
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
     * Afficher la liste des produits
     */
    public function index(Request $request)
    {
        $activeStatuses = ['actif', 'active', 'approved', 'published'];
        $productTable = (new Product())->getTable();

        $query = Product::query()
            ->with([
                'images',
                'category',
                'shop.user',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.brand', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery
                        ->where('name', 'like', "%{$search}%"))
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
        ];

        return view('admin.products.index', compact(
            'products',
            'categories',
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
    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $shops = Shop::query()->with('user')->orderBy('name')->get();

        return view('admin.products.create', compact('categories', 'shops'));
    }

    /**
     * Stocker un nouveau produit
     */
    public function store(Request $request)
    {
        $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sale_type' => 'required|string|max:255',
            'price' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'transport' => 'nullable|numeric|min:0',
            'description' => 'required|string',
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'promo_price' => 'nullable|integer|min:0',
            'price_p1' => 'nullable|integer|min:0',
            'price_p2' => 'nullable|integer|min:0',
            'price_p3' => 'nullable|integer|min:0',
            'flash_end' => 'nullable|date',
            'bf_start' => 'nullable|date',
            'bf_end' => 'nullable|date',
            'fast_delivery' => 'nullable|boolean',
            'brand' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:120',
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
        ]);

        $isNegotiable =
            !empty($request->price_p1) &&
            !empty($request->price_p2) &&
            !empty($request->price_p3);

        $product = Product::create([
            'shop_id' => $request->shop_id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . uniqid(),
            'sale_type' => $request->sale_type,
            'description' => $request->description,
            'brand' => $request->brand,
            'sku' => $request->sku,
            'unit' => $request->unit,
            'min_order_quantity' => $request->min_order_quantity,
            'packaging' => $request->packaging,
            'weight' => $request->weight_kg,
            'weight_kg' => $request->weight_kg,
            'length_cm' => $request->length_cm,
            'width_cm' => $request->width_cm,
            'height_cm' => $request->height_cm,
            'volume_m3' => round(((float) $request->length_cm * (float) $request->width_cm * (float) $request->height_cm) / 1000000, 6),
            'fragile' => $request->boolean('fragile'),
            'requires_unloading' => $request->boolean('requires_unloading'),
            'unloading_instructions' => $request->boolean('requires_unloading') ? $request->unloading_instructions : null,
            'price' => $request->price,
            'stock' => $request->stock,
            'transport' => $request->transport ?? 0,
            'promo_price' => $request->promo_price,
            'price_p1' => $request->price_p1,
            'price_p2' => $request->price_p2,
            'price_p3' => $request->price_p3,
            'is_negotiable' => $isNegotiable,
            'flash_end' => $request->flash_end,
            'bf_start' => $request->bf_start,
            'bf_end' => $request->bf_end,
            'fast_delivery' => $request->has('fast_delivery') ? 1 : 0,
            'is_active' => true,
            'status' => 'actif',
        ]);
        $this->syncProductDeliveryZones($product, $request);
        $firstImage = true;

        foreach ($request->file('images') as $imgFile) {
            $path = $imgFile->store('products', 'public');

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'is_main' => $firstImage,
            ]);

            $firstImage = false;
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Produit ajouté avec succès.');
    }
    /**
     * Afficher le formulaire d'édition.
     */
    public function edit(Product $product)
    {
        $categories = Category::query()->orderBy('name')->get();

        $product->load([
            'images',
            'category',
            'shop.user',
            'masterProduct',
        ]);

        return view('admin.products.edit', compact('product', 'categories'));
    }

    /**
     * Mettre à jour un produit.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'product_state' => ['required', Rule::in(['new', 'used', 'refurbished'])],
            'category_id' => ['required', 'exists:categories,id'],
            'sale_type' => ['required', 'string', 'max:80'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:10000'],
            'technical_details' => ['nullable', 'string', 'max:10000'],

            'price' => ['required', 'integer', 'min:1'],
            'promo_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'availability_status' => ['required', Rule::in(['in_stock', 'out_of_stock', 'on_order', 'preorder'])],
            'unit' => ['required', 'string', 'max:80'],
            'unit_label' => ['nullable', 'string', 'max:120'],
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

            'status' => ['required', Rule::in([
                'draft',
                'actif',
                'pending_logistics',
                'inactive',
                'archived',
            ])],
            'is_active' => ['required', 'boolean'],
            'fast_delivery' => ['required', 'boolean'],
            'commission_paid' => ['required', 'boolean'],
            'lock_contacts' => ['required', 'boolean'],

            'flash_start_at' => ['nullable', 'date'],
            'flash_end' => ['nullable', 'date', 'after_or_equal:flash_start_at'],
            'bf_start' => ['nullable', 'date'],
            'bf_end' => ['nullable', 'date', 'after_or_equal:bf_start'],

            'images' => ['nullable', 'array', 'max:8'],
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
            'images.max' => 'Vous pouvez ajouter au maximum 8 nouvelles images.',
        ]);

        $validated['volume_m3'] = round(
            ((float) $validated['length_cm']
                * (float) $validated['width_cm']
                * (float) $validated['height_cm']) / 1_000_000,
            6
        );
        $validated['weight'] = $validated['weight_kg'];
        $validated['slug'] = Str::slug($validated['name']) . '-' . $product->id;
        $validated['unloading_instructions'] = $request->boolean('requires_unloading')
            ? ($validated['unloading_instructions'] ?? null)
            : null;
        $validated['archived_at'] = $validated['status'] === 'archived' ? now() : null;

        if ((int) $validated['stock'] === 0) {
            $validated['availability_status'] = 'out_of_stock';
        }

        if (in_array($validated['status'], ['draft', 'pending_logistics', 'inactive', 'archived'], true)) {
            $validated['is_active'] = false;
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
                $product->update(collect($validated)->except([
                    'images',
                    'remove_images',
                    'main_image',
                ])->all());

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

                $this->syncProductDeliveryZones($product, $request);
            });
        } catch (Throwable $exception) {
            $this->deleteNormalizedUploads($normalizedUploads, $normalizer);
            throw $exception;
        }

        foreach ($imagesToDelete as $image) {
            $this->deleteProductImageFiles($image);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès.',
                'product_id' => $product->id,
            ]);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Les modifications du produit ont été enregistrées.');
    }

    public function show(Request $request, Product $product, ProductCalculatorService $calculatorService)
    {
        $product->load([
            'images',
            'category.parent',
            'shop.user',
            'masterProduct',
            'reviews' => fn ($query) => $query->with('user:id,name')->latest()->limit(4),
        ]);

        $product->loadCount('reviews')->loadAvg('reviews', 'rating');

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

        return view('products.show', [
            'product' => $product,
            'similarProducts' => collect(),
            'calculatorProfile' => $calculatorService->profile($product),
            'isFavorite' => false,
        ]);
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Product $product)
    {
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
