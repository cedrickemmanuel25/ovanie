<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\ShopConfigurationAssignment;
use App\Services\OvanieReferenceDataService;
use App\Services\ProductImageNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommercialMobileProductController extends Controller
{
    private function shops(Request $request)
    {
        $commercialId = (int) $request->user()->id;

        return Shop::query()->where(function ($query) use ($commercialId) {
            $query->where('created_by_commercial_id', $commercialId)
                ->orWhere('managed_by_commercial_id', $commercialId)
                ->orWhereHas('user', fn ($user) => $user->where('created_by_commercial_id', $commercialId));
        });
    }

    private function ownShop(Request $request, int $id): Shop
    {
        $direct = $this->shops($request)->find($id);
        if ($direct) {
            return $direct;
        }

        $missionId = (int) $request->input('mission_id', $request->query('mission_id', 0));
        if ($missionId > 0) {
            $assignment = ShopConfigurationAssignment::query()
                ->whereKey($missionId)
                ->where('commercial_id', (int) $request->user()->id)
                ->where('shop_id', $id)
                ->where('manage_products', true)
                ->whereIn('status', [ShopConfigurationAssignment::STATUS_PENDING, ShopConfigurationAssignment::STATUS_IN_PROGRESS])
                ->first();

            if ($assignment) {
                return Shop::query()->findOrFail($id);
            }
        }

        abort(404);
    }

    public function shopsIndex(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $rows = $this->shops($request)
            ->withCount('products')
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->latest()
            ->get();

        return response()->json([
            'shops' => $rows->map(fn ($shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'category' => $shop->main_category ?: 'Boutique OVANIE',
                'location' => trim(($shop->city ?: '') . ' ' . ($shop->commune ?: ''), ' ,'),
                'products_count' => (int) $shop->products_count,
                'status' => $shop->status === 'suspended' ? 'suspended' : (($shop->status === 'approved' || $shop->is_active) ? 'online' : 'pending'),
                'logistics' => $shop->logistics_mode_label,
                'logistics_configured' => $shop->hasConfirmedLogistics(),
                'payout_configured' => $shop->hasConfiguredPayout(),
                'can_publish_products' => $shop->canPublishProducts(),
                'image_url' => $shop->logo_url ?? null,
            ])->values(),
        ]);
    }

    public function meta(OvanieReferenceDataService $references): JsonResponse
    {
        $categories = collect($references->productCategories())
            ->map(fn ($category) => [
                'id' => $category['id'],
                'name' => $category['name'],
                'children' => collect($category['children'] ?? [])
                    ->map(fn ($child) => ['id' => $child['id'], 'name' => $child['name']])
                    ->values(),
            ])
            ->values();

        return response()->json([
            'categories' => $categories,
            'units' => $references->codes('product_units'),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $shop = $this->ownShop($request, (int) $request->query('shop_id'));
        $rows = DB::table('commercial_capture_sessions')
            ->where('commercial_id', $request->user()->id)
            ->where('shop_id', $shop->id)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('commercial_capture_session_products')
                    ->whereColumn('commercial_capture_session_products.session_id', 'commercial_capture_sessions.id');
            })
            ->latest()
            ->get();

        return response()->json([
            'sessions' => $rows->map(fn ($session) => $this->sessionPayload($session, $shop))->values(),
        ]);
    }

    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_id' => 'required|integer',
            'name' => 'required|string|max:150',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'weight_kg' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:40',
        ]);

        $shop = $this->ownShop($request, (int) $data['shop_id']);
        $productName = trim((string) $data['name']);
        $name = Str::limit($productName, 150, '');

        $id = DB::table('commercial_capture_sessions')->insertGetId([
            'commercial_id' => $request->user()->id,
            'shop_id' => $shop->id,
            'name' => $name,
            'product_name' => $productName,
            'product_price' => (float) $data['price'],
            'product_promo_price' => $data['promo_price'] !== null && $data['promo_price'] !== '' ? (float) $data['promo_price'] : null,
            'product_stock' => (int) $data['stock'],
            'product_weight_kg' => (float) $data['weight_kg'],
            'product_unit' => trim((string) $data['unit']),
            'keep_category' => false,
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'session' => $this->sessionPayload(DB::table('commercial_capture_sessions')->find($id), $shop),
        ], 201);
    }

    public function session(Request $request, int $id): JsonResponse
    {
        $session = $this->ownedSession($request, $id);
        $shop = $this->ownShop($request, (int) $session->shop_id);
        $products = $this->sessionProducts($session->id);

        return response()->json([
            'session' => $this->sessionPayload($session, $shop),
            'products' => $products->map(fn ($product) => $this->productPayload($product))->values(),
        ]);
    }

    public function capture(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'required|integer',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $session = $this->ownedSession($request, (int) $data['session_id']);
        $shop = $this->ownShop($request, (int) $session->shop_id);
        $imageData = $this->normalizePhotoUpload($request->file('photo'), 'products/commercial-capture');

        $product = DB::transaction(function () use ($request, $session, $shop, $imageData) {
            $count = DB::table('commercial_capture_session_products')->where('session_id', $session->id)->count() + 1;
            $productName = trim((string) ($session->product_name ?: $session->name ?: ('Produit capturé #' . $count)));
            $attrs = [
                'commercial_capture' => true,
                'capture_session_id' => $session->id,
                'admin_completion_required' => true,
                'completion_step' => 0,
                'source' => 'commercial_mobile',
                'mission_assignment_id' => (int) $request->input('mission_id', 0) ?: null,
                'captured_name' => $productName,
                'captured_price' => $session->product_price,
                'captured_promo_price' => $session->product_promo_price,
                'captured_stock' => $session->product_stock,
                'captured_weight_kg' => $session->product_weight_kg,
                'captured_unit' => $session->product_unit,
            ];

            $values = [
                'shop_id' => $shop->id,
                'name' => $productName,
                'slug' => Str::slug($shop->name . ' ' . $productName . ' ' . Str::random(5)),
                'description' => null,
                'price' => (int) round((float) ($session->product_price ?? 0)),
                'promo_price' => $session->product_promo_price !== null ? (int) round((float) $session->product_promo_price) : null,
                'stock' => max(0, (int) ($session->product_stock ?? 0)),
                'weight_kg' => (float) ($session->product_weight_kg ?? 0),
                'unit' => $session->product_unit ?: 'unité',
                'unit_label' => $session->product_unit ?: 'unité',
                'min_order_quantity' => 1,
                'is_active' => false,
            ];

            if (Schema::hasColumn('products', 'created_by_commercial_id')) {
                $values['created_by_commercial_id'] = $request->user()->id;
            }
            if (Schema::hasColumn('products', 'status')) {
                $values['status'] = 'draft';
            }
            if (Schema::hasColumn('products', 'product_attributes')) {
                $values['product_attributes'] = $attrs;
            }

            $product = Product::create(array_filter($values, static fn ($value) => $value !== null));

            $imageValues = array_merge([
                'product_id' => $product->id,
                'is_main' => true,
                'is_primary' => true,
            ], $imageData);
            if (Schema::hasColumn('product_images', 'image_path')) {
                $imageValues['image_path'] = $imageData['path'];
            }
            ProductImage::create($imageValues);

            DB::table('commercial_capture_session_products')->insert([
                'session_id' => $session->id,
                'product_id' => $product->id,
                'capture_status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('commercial_capture_sessions')
                ->where('id', $session->id)
                ->update([
                    'status' => 'finished',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return $product;
        });

        $product->load(['category', 'images']);

        return response()->json([
            'product' => $this->productPayload($product, true),
        ], 201);
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $session = $this->ownedSession($request, $id);
        DB::table('commercial_capture_sessions')->where('id', $session->id)->update([
            'status' => 'finished',
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function incomplete(Request $request): JsonResponse
    {
        $session = $this->ownedSession($request, (int) $request->query('session_id'));
        $products = $this->sessionProducts($session->id)->filter(fn ($product) => ! $product->is_active);

        return response()->json([
            'products' => $products->map(fn ($product) => $this->productPayload($product))->values(),
        ]);
    }

    public function edit(Request $request, Product $product): JsonResponse
    {
        $this->assertProduct($request, $product);
        $product->load(['category.parent', 'images']);

        return response()->json(['product' => $this->productPayload($product, true)]);
    }

    public function saveStep(Request $request, Product $product, int $step): JsonResponse
    {
        $this->assertProduct($request, $product);
        abort_unless($step >= 1 && $step <= 5, 404);

        $attributes = (array) ($product->product_attributes ?? []);
        foreach ($request->all() as $key => $value) {
            if ($value !== null) {
                $attributes[$key] = $value;
            }
        }

        $map = [];
        if ($step === 1) {
            foreach (['name', 'brand', 'short_description', 'description'] as $key) {
                if (isset($attributes[$key]) && Schema::hasColumn('products', $key)) {
                    $map[$key] = $attributes[$key];
                }
            }
            if (isset($attributes['name'])) {
                $map['name'] = $attributes['name'];
                $map['slug'] = Str::slug($attributes['name'] . '-' . $product->id);
            }
        }

        if ($step === 2) {
            foreach (['price', 'promo_price', 'stock', 'unit', 'unit_label', 'min_order_quantity', 'sale_type', 'price_p1', 'price_p2', 'price_p3'] as $key) {
                if (isset($attributes[$key]) && Schema::hasColumn('products', $key)) {
                    $value = $attributes[$key] === '' ? null : $attributes[$key];
                    if (in_array($key, ['price', 'promo_price', 'price_p1', 'price_p2', 'price_p3'], true) && $value !== null) {
                        $value = max(0, (int) round((float) str_replace(',', '.', (string) $value)));
                    }
                    $map[$key] = $value;
                }
            }
            if (isset($attributes['is_negotiable']) && Schema::hasColumn('products', 'is_negotiable')) {
                $negotiable = filter_var($attributes['is_negotiable'], FILTER_VALIDATE_BOOLEAN);
                $map['is_negotiable'] = $negotiable && ! empty($map['price_p1'] ?? null) && ! empty($map['price_p2'] ?? null) && ! empty($map['price_p3'] ?? null);
            }
        }

        if ($step === 3) {
            foreach (['usage_area', 'technical_details', 'warranty'] as $key) {
                if (isset($attributes[$key]) && Schema::hasColumn('products', $key)) {
                    $map[$key] = $attributes[$key] === '' ? null : $attributes[$key];
                }
            }
        }

        if ($step === 4) {
            foreach (['weight_kg', 'length_cm', 'width_cm', 'height_cm'] as $key) {
                if (isset($attributes[$key]) && Schema::hasColumn('products', $key)) {
                    $map[$key] = $attributes[$key] === '' ? null : $attributes[$key];
                }
            }
            if (isset($attributes['fragile']) && Schema::hasColumn('products', 'fragile')) {
                $map['fragile'] = filter_var($attributes['fragile'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($attributes['requires_unloading']) && Schema::hasColumn('products', 'requires_unloading')) {
                $map['requires_unloading'] = filter_var($attributes['requires_unloading'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($attributes['unloading_instructions']) && Schema::hasColumn('products', 'unloading_instructions')) {
                $map['unloading_instructions'] = $attributes['unloading_instructions'];
            }
        }

        $attributes['completion_step'] = max((int) ($attributes['completion_step'] ?? 0), $step);
        if (Schema::hasColumn('products', 'product_attributes')) {
            $map['product_attributes'] = $attributes;
        }

        $product->update($map);

        return response()->json([
            'ok' => true,
            'product' => $this->productPayload($product->fresh(['category', 'images']), true),
        ]);
    }

    public function media(Request $request, Product $product): JsonResponse
    {
        $this->assertProduct($request, $product);
        $request->validate([
            'kind' => 'nullable|string|max:20',
            'media' => 'required|file|max:20480',
        ]);

        $imageData = $this->normalizePhotoUpload($request->file('media'), 'products/commercial-media');
        $imageValues = array_merge([
            'product_id' => $product->id,
            'is_main' => $product->images()->count() === 0,
            'is_primary' => $product->images()->count() === 0,
        ], $imageData);
        if (Schema::hasColumn('product_images', 'image_path')) {
            $imageValues['image_path'] = $imageData['path'];
        }
        ProductImage::create($imageValues);

        return response()->json(['ok' => true, 'url' => Storage::url($imageData['path'])]);
    }

    public function publish(Request $request, Product $product): JsonResponse
    {
        $this->assertProduct($request, $product);

        if ((float) $product->price <= 0 || (int) $product->stock < 0 || empty($product->name) || $product->images()->count() === 0) {
            return response()->json(['message' => 'La fiche produit est incomplète : nom, prix et photo sont obligatoires.'], 422);
        }

        $shop = $this->ownShop($request, (int) $product->shop_id);
        $publishable = $shop->canPublishProducts();
        $product->update(array_filter([
            'is_active' => $publishable,
            'status' => Schema::hasColumn('products', 'status') ? ($publishable ? 'active' : 'pending_logistics') : null,
        ], fn ($value) => $value !== null));

        DB::table('commercial_capture_session_products')
            ->where('product_id', $product->id)
            ->update([
                'capture_status' => $publishable ? 'completed' : 'draft',
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'published' => $publishable,
            'message' => $publishable
                ? 'Produit publié.'
                : 'Produit enregistré, mais publication bloquée jusqu’à la configuration complète de la boutique.',
            'product' => $this->productPayload($product->fresh(['category', 'images']), true),
        ]);
    }

    private function normalizePhotoUpload($file, string $directory): array
    {
        try {
            $result = app(ProductImageNormalizer::class)->normalize($file, 'products');

            return [
                'path' => $result['master'],
                'original_path' => $result['original'],
                'card_path' => $result['card'],
                'thumb_path' => $result['thumb'],
                'original_width' => $result['original_width'],
                'original_height' => $result['original_height'],
                'normalized_at' => now(),
            ];
        } catch (\Throwable $e) {
            return ['path' => $file->store($directory, 'public')];
        }
    }

    private function ownedSession(Request $request, int $id)
    {
        $session = DB::table('commercial_capture_sessions')
            ->where('id', $id)
            ->where('commercial_id', $request->user()->id)
            ->first();

        abort_unless($session, 404);

        return $session;
    }

    private function sessionProducts(int $sessionId)
    {
        $ids = DB::table('commercial_capture_session_products')->where('session_id', $sessionId)->pluck('product_id');

        return Product::query()
            ->whereIn('id', $ids)
            ->with(['category.parent', 'images'])
            ->orderBy('id')
            ->get();
    }

    private function assertProduct(Request $request, Product $product): void
    {
        $shop = $this->ownShop($request, (int) $product->shop_id);
        abort_unless($shop->id === $product->shop_id, 404);
    }

    private function sessionPayload($session, Shop $shop): array
    {
        $ids = DB::table('commercial_capture_session_products')->where('session_id', $session->id)->pluck('product_id');
        $captured = $ids->count();
        $completed = $ids->isEmpty() ? 0 : Product::whereIn('id', $ids)->where('is_active', true)->count();
        $category = $session->category_id ? Category::find($session->category_id) : null;
        $subcategory = $session->subcategory_id ? Category::find($session->subcategory_id) : null;

        $productName = trim((string) ($session->product_name ?? $session->name ?? ''));
        $price = isset($session->product_price) ? (float) $session->product_price : null;
        $promoPrice = isset($session->product_promo_price) ? (float) $session->product_promo_price : null;
        $stock = isset($session->product_stock) ? (int) $session->product_stock : null;
        $weightKg = isset($session->product_weight_kg) ? (float) $session->product_weight_kg : null;
        $unit = trim((string) ($session->product_unit ?? 'unité')) ?: 'unité';

        return [
            'id' => $session->id,
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'name' => $productName,
            'status' => (string) ($session->status ?? 'in_progress'),
            'captured' => $captured,
            'completed' => $completed,
            'drafts' => max(0, $captured - $completed),
            'sync_status' => 'synced',
            'date' => ! empty($session->created_at) ? \Carbon\Carbon::parse($session->created_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
            'category_id' => $session->category_id ?? null,
            'category' => $category?->name ?? '',
            'subcategory_id' => $session->subcategory_id ?? null,
            'subcategory' => $subcategory?->name ?? '',
            'product_name' => $productName,
            'price' => $price,
            'promo_price' => $promoPrice,
            'stock' => $stock,
            'weight_kg' => $weightKg,
            'unit' => $unit,
        ];
    }

    private function productPayload(Product $product, bool $full = false): array
    {
        $product->loadMissing(['category.parent', 'images']);
        $attributes = (array) ($product->product_attributes ?? []);
        $main = $product->category?->parent ?: $product->category;
        $sub = $product->category?->parent ? $product->category : null;

        $base = [
            'id' => $product->id,
            'name' => $product->name,
            'reference' => $product->sku ?: ('PRD-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT)),
            'status' => $product->is_active ? 'completed' : 'draft',
            'image_url' => $product->images->first()?->public_url ?? '',
            'category' => $main?->name ?? '',
            'subcategory' => $sub?->name ?? '',
            'captured_at' => optional($product->created_at)->format('H:i'),
            'price' => $product->price,
            'promo_price' => $product->promo_price,
            'stock' => $product->stock,
            'unit' => $product->unit,
            'weight_kg' => $product->weight_kg,
            'attributes' => $attributes,
            'admin_completion_required' => (bool) ($attributes['admin_completion_required'] ?? false),
        ];

        if (! $full) {
            return $base;
        }

        return array_merge($base, $attributes, [
            'brand' => $product->brand,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'unit_label' => $product->unit_label,
            'min_order_quantity' => $product->min_order_quantity,
            'sale_type' => $product->sale_type,
            'is_negotiable' => (bool) $product->is_negotiable,
            'price_p1' => $product->price_p1,
            'price_p2' => $product->price_p2,
            'price_p3' => $product->price_p3,
            'content_per_unit' => $product->content_per_unit,
            'color' => $product->color,
            'standard' => $product->standard,
            'usage_area' => $product->usage_area,
            'technical_details' => $product->technical_details,
            'warranty' => $product->warranty,
            'packaging' => $product->packaging,
            'length_cm' => $product->length_cm,
            'width_cm' => $product->width_cm,
            'height_cm' => $product->height_cm,
            'fragile' => (bool) $product->fragile,
            'requires_unloading' => (bool) $product->requires_unloading,
            'unloading_instructions' => $product->unloading_instructions,
        ]);
    }
}
