<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VendorDeliverySettingsController;
use App\Http\Controllers\VendorDisputeController;
use App\Http\Controllers\VendorOrderController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\VendorReturnController;
use App\Http\Controllers\VendorShopProfileController;
use App\Models\AbidjanLandmark;
use App\Models\AbidjanQuarter;
use App\Models\Dispute;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ReturnModel;
use App\Models\SellerDeliveryTrackingSession;
use App\Models\Shipment;
use App\Models\SellerDeliveryZone;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPayout;
use App\Services\CommissionService;
use App\Services\OrderWorkflowService;
use App\Services\OvanieReferenceDataService;
use App\Services\ProductSheetPresenter;
use App\Services\VendorDashboardService;
use App\Services\VendorFinanceService;
use App\Services\VendorOrderTransitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class VendorMobileController extends Controller
{
    use VendorMenuEndpoints;
    /**
     * Ouvre une boutique pour un nouveau vendeur sans compte préalable.
     * Le compte vendeur et la boutique sont créés atomiquement par le même
     * ShopController que le parcours Web /open-shop. Aucun compte client
     * séparé n'est créé avant l'ouverture de la boutique.
     */
    public function openShop(Request $request): JsonResponse
    {
        // Cette méthode est utilisée dans les deux cas :
        // 1) nouveau vendeur sans compte connecté ;
        // 2) compte OVANIE déjà connecté mais sans boutique.
        // Dans le second cas, auth:sanctum a déjà résolu l'utilisateur.
        // La route /open-shop est publique pour permettre la création d'un
        // nouveau vendeur, mais elle accepte aussi un token Sanctum existant.
        // Ainsi Flutter utilise UN SEUL endpoint pour les deux parcours.
        $authenticatedUser = $this->resolveOptionalSanctumUser($request);
        if ($authenticatedUser) {
            $this->bindUserToAuth($request);

            if ($authenticatedUser->shop) {
                return response()->json([
                    'has_shop' => true,
                    'message' => 'Votre compte possède déjà une boutique OVANIE.',
                    'user' => $this->userPayload($authenticatedUser),
                    'shop' => $this->shopPayload($authenticatedUser->shop),
                ], 409);
            }
        }

        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('ovanie_mobile_skip_web_login', true);

        $response = $this->callController(ShopController::class, 'store', [
            'request' => $request,
        ]);

        if (! $response instanceof JsonResponse) {
            return response()->json([
                'message' => 'Impossible de finaliser l’ouverture de la boutique.',
            ], 500);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $payload = $response->getData(true);
        $shopId = (int) data_get($payload, 'shop.id', 0);
        $shop = $shopId > 0 ? Shop::query()->find($shopId) : null;

        if (! $shop && $authenticatedUser) {
            $shop = $authenticatedUser->fresh()?->shop;
        }

        if (! $shop) {
            $sellerEmail = mb_strtolower(trim((string) $request->input('sellerEmail')));
            $shop = Shop::query()
                ->whereHas('user', function (Builder $query) use ($sellerEmail) {
                    $query->whereRaw('LOWER(email) = ?', [$sellerEmail]);
                })
                ->latest('id')
                ->first();
        }

        if (! $shop) {
            return response()->json([
                'message' => 'La boutique a été créée, mais sa session vendeur n’a pas pu être initialisée.',
            ], 500);
        }

        $user = User::query()->find($shop->user_id);
        if (! $user) {
            return response()->json([
                'message' => 'Le compte vendeur associé à la boutique est introuvable.',
            ], 500);
        }

        $result = [
            'has_shop' => true,
            'message' => (string) data_get($payload, 'message', 'Votre boutique OVANIE est ouverte.'),
            'user' => $this->userPayload($user),
            'shop' => $this->shopPayload($shop->fresh()),
        ];

        // Un nouveau vendeur reçoit un token mobile immédiatement. Un vendeur
        // déjà authentifié conserve son token Sanctum actuel : aucun doublon.
        if (! $authenticatedUser) {
            $deviceName = trim((string) $request->input('device_name', 'OVANIE Vendeur Android'));
            if ($deviceName === '') {
                $deviceName = 'OVANIE Vendeur Android';
            }

            $result['token'] = $user->createToken($deviceName)->plainTextToken;
            $result['token_type'] = 'Bearer';
        }

        return response()->json($result, 201);
    }

    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        $shop = $user?->shop;

        return response()->json([
            'user' => $this->userPayload($user),
            'has_shop' => (bool) $shop,
            'requires_shop_onboarding' => ! $shop,
            'shop' => $shop ? $this->shopPayload($shop) : null,
        ]);
    }

    public function meta(Request $request, OvanieReferenceDataService $references): JsonResponse
    {
        return response()->json([
            'categories' => $references->categoriesFlat(),
            'shop_categories' => $references->shopCategories(),
            'product_units' => $references->codes('product_units'),
            'product_categories' => $references->productCategories(),
            'seller_types' => $references->codes('seller_types'),
            'identity_types' => $references->codes('identity_types'),
            'payment_modes' => $references->codes('vendor_payment_modes'),
            'mobile_money_operators' => $references->codes('mobile_money_operators'),
            'delivery_zones' => $references->codes('delivery_zones'),
            'logistics_types' => $references->codes('logistics_types'),
            'processing_times' => $references->processingTimeCodes(),
            'vehicle_types' => $references->codes('vehicle_types'),
            'communes' => $references->communes(),
        ]);
    }

    public function quarters(Request $request): JsonResponse
    {
        if (! Schema::hasTable('abidjan_quarters')) {
            return response()->json(['data' => []]);
        }

        $communeId = (int) $request->integer('commune_id');
        $query = AbidjanQuarter::query();
        if (Schema::hasColumn('abidjan_quarters', 'is_active')) {
            $query->where('is_active', true);
        }

        $items = $query
            ->when($communeId > 0, fn (Builder $builder) => $builder->where('commune_id', $communeId))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'commune_id', 'name'])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function landmarks(Request $request): JsonResponse
    {
        if (! Schema::hasTable('abidjan_landmarks')) {
            return response()->json(['data' => []]);
        }

        $quarterId = (int) $request->integer('quarter_id');
        $communeId = (int) $request->integer('commune_id');
        $query = AbidjanLandmark::query();
        if (Schema::hasColumn('abidjan_landmarks', 'is_active')) {
            $query->where('is_active', true);
        }

        $items = $query
            ->when($communeId > 0, fn (Builder $builder) => $builder->where('commune_id', $communeId))
            ->when($quarterId > 0, fn (Builder $builder) => $builder->where('quarter_id', $quarterId))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'commune_id', 'quarter_id', 'name', 'latitude', 'longitude'])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function onboardShop(Request $request): JsonResponse
    {
        // Même logique métier que l'ouverture publique, mais avec le compte
        // résolu par auth:sanctum. Cela évite deux implémentations divergentes.
        return $this->openShop($request);
    }

    public function dashboard(Request $request, VendorDashboardService $service): JsonResponse
    {
        $shop = $this->shopFor($request);
        $data = $service->build($shop);

        return response()->json([
            'shop' => $this->shopPayload($shop),
            'stats' => $data['stats'] ?? [],
            'orders' => collect($data['orders'] ?? [])->map(fn ($order) => $this->orderSummaryPayload($order, $shop))->values(),
            'recent_products' => collect($data['recentProducts'] ?? [])->map(fn ($product) => $this->productPayload($product))->values(),
            'low_stock_products' => collect($data['lowStockProducts'] ?? [])->map(fn ($product) => $this->productPayload($product))->values(),
            // VendorDashboardService::topProducts() retourne des OrderItem
            // agrégés (product_id, sold_quantity, sold_amount) avec le produit
            // associé. Ne pas les envoyer directement à productPayload(), qui
            // attend volontairement un vrai Product.
            'top_products' => collect($data['topProducts'] ?? [])
                ->map(fn ($item) => $this->topProductPayload($item))
                ->filter()
                ->values(),
            'recent_payouts' => collect($data['recentPayouts'] ?? [])->map(fn ($payout) => $this->payoutPayload($payout))->values(),
            'sales_series' => $data['salesSeries'] ?? [],
            'alerts' => $data['alerts'] ?? [],
            // Badge de la cloche : vraies notifications Laravel non lues.
            'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function shop(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request)->loadMissing([
            'sellerDeliveryProfile',
            'sellerDeliveryZones',
        ]);

        return response()->json(['shop' => $this->shopPayload($shop, true)]);
    }

    public function updateShop(Request $request): JsonResponse
    {
        $this->bindUserToAuth($request);
        $request->headers->set('Accept', 'application/json');

        $this->callController(VendorShopProfileController::class, 'update', [
            'request' => $request,
        ]);

        return response()->json([
            'message' => 'Profil boutique mis à jour.',
            'shop' => $this->shopPayload($this->shopFor($request)->fresh()),
        ]);
    }

    public function deliverySettings(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request)->loadMissing('sellerDeliveryProfile', 'sellerDeliveryZones');

        return response()->json([
            'logistics_type' => $shop->logistics_type ?: 'ovanie',
            'uses_seller_logistics' => $shop->usesSellerLogistics(),
            'logistics_status' => $shop->logistics_status,
            'profile' => $shop->usesSellerLogistics() || $request->boolean('configure') ? $shop->sellerDeliveryProfile : null,
            'zones' => $shop->usesSellerLogistics() || $request->boolean('configure') ? $shop->sellerDeliveryZones->values() : [],
        ]);
    }

    public function saveLogisticsSettings(Request $request, \App\Services\ShopLogisticsSettingsService $settings): JsonResponse
    {
        $shop = $settings->save($this->shopFor($request), $request->all());
        return response()->json(['shop' => $this->shopPayload($shop), 'message' => 'Informations logistiques enregistrées.']);
    }

    public function resolveLogisticsLocation(Request $request, \App\Services\ShopPickupLocationService $locations): JsonResponse
    {
        return response()->json($locations->resolve($this->shopFor($request), $request->all()))
            ->header('Cache-Control', 'no-store');
    }

    public function updateDeliverySettings(Request $request): JsonResponse
    {
        $this->bindUserToAuth($request);
        $this->callController(VendorDeliverySettingsController::class, 'update', ['request' => $request]);

        return response()->json([
            'message' => 'Capacités logistiques enregistrées.',
            'shop' => $this->shopPayload($this->shopFor($request)->fresh()),
        ]);
    }

    public function storeDeliveryZone(Request $request): JsonResponse
    {
        $this->bindUserToAuth($request);
        $this->callController(VendorDeliverySettingsController::class, 'storeZone', ['request' => $request]);

        return response()->json(['message' => 'Tarif de livraison ajouté.'], 201);
    }

    public function updateDeliveryZone(Request $request, SellerDeliveryZone $zone): JsonResponse
    {
        $this->bindUserToAuth($request);
        $this->callController(VendorDeliverySettingsController::class, 'updateZone', [
            'request' => $request,
            'zone' => $zone->id,
        ]);

        return response()->json(['message' => 'Tarif de livraison mis à jour.']);
    }

    public function destroyDeliveryZone(Request $request, SellerDeliveryZone $zone): JsonResponse
    {
        $this->bindUserToAuth($request);
        $this->callController(VendorDeliverySettingsController::class, 'destroyZone', [
            'zone' => $zone->id,
        ]);

        return response()->json(['message' => 'Tarif de livraison supprimé.']);
    }

    public function products(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', 'all'));
        $sort = trim((string) $request->query('sort', 'newest'));
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));

        $base = Product::query()->where('shop_id', $shop->id);

        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)
                ->where('is_active', true)
                ->whereNotIn('status', ['archived', 'draft'])
                ->where(function (Builder $q) {
                    $q->whereNull('stock')->orWhere('stock', '>', 0);
                })
                ->count(),
            'draft' => (clone $base)
                ->where('status', 'draft')
                ->count(),
            'out_of_stock' => (clone $base)
                ->whereNotIn('status', ['archived', 'draft'])
                ->where(function (Builder $q) {
                    $q->where('stock', '<=', 0)->orWhere('availability_status', 'out_of_stock');
                })
                ->count(),
            'archived' => (clone $base)->where('status', 'archived')->count(),
        ];

        $query = Product::query()
            ->where('shop_id', $shop->id)
            ->with(['category.parent', 'images'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            }))
            ->when($status !== '' && $status !== 'all', function (Builder $q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true)
                        ->whereNotIn('status', ['archived', 'draft'])
                        ->where(function (Builder $active) {
                            $active->whereNull('stock')->orWhere('stock', '>', 0);
                        });
                    return;
                }
                if ($status === 'draft') {
                    $q->where('status', 'draft');
                    return;
                }
                if (in_array($status, ['out_of_stock', 'rupture'], true)) {
                    $q->whereNotIn('status', ['archived', 'draft'])
                        ->where(function (Builder $stock) {
                            $stock->where('stock', '<=', 0)->orWhere('availability_status', 'out_of_stock');
                        });
                    return;
                }
                if (in_array($status, ['hidden', 'masked'], true)) {
                    $q->where('is_active', false)->whereNotIn('status', ['draft', 'archived']);
                    return;
                }
                $q->where('status', $status);
            });

        match ($sort) {
            'oldest' => $query->orderBy('id'),
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'stock_asc' => $query->orderBy('stock')->orderByDesc('id'),
            'stock_desc' => $query->orderByDesc('stock')->orderByDesc('id'),
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            default => $query->latest('id'),
        };

        $paginator = $query->paginate($perPage);
        $payload = $this->paginate($paginator, fn ($product) => $this->productPayload($product));
        $payload['stats'] = $stats;

        return response()->json($payload);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);

        $product->loadMissing(['category.parent', 'images', 'reviews.user']);
        $product->loadCount('reviews')->loadAvg('reviews', 'rating');

        return response()->json(['product' => $this->productPayload($product, true)]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $request->headers->set('Accept', 'application/json');

        $this->callController(VendorProductController::class, 'store', ['request' => $request]);

        return response()->json([
            'message' => $request->boolean('save_as_draft')
                ? 'Brouillon produit enregistré.'
                : 'Produit enregistré dans la boutique.',
        ], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);
        $request->headers->set('Accept', 'application/json');

        $this->callController(VendorProductController::class, 'update', [
            'request' => $request,
            'product' => $product,
        ]);

        return response()->json([
            'message' => 'Produit mis à jour.',
            'product' => $this->productPayload($product->fresh(['category', 'images']), true),
        ]);
    }

    public function toggleProduct(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);
        $this->callController(VendorProductController::class, 'toggleStatus', [
            'request' => $request,
            'product' => $product,
        ]);

        return response()->json([
            'message' => 'Statut produit mis à jour.',
            'product' => $this->productPayload($product->fresh()),
        ]);
    }

    public function archiveProduct(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);
        $this->callController(VendorProductController::class, 'destroy', [
            'request' => $request,
            'product' => $product,
        ]);

        return response()->json(['message' => 'Produit archivé.']);
    }

    public function restoreProduct(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);
        $this->callController(VendorProductController::class, 'restore', ['product' => $product]);

        return response()->json([
            'message' => 'Produit restauré.',
            'product' => $this->productPayload($product->fresh()),
        ]);
    }

    public function reorderProductImages(Request $request, Product $product): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $product->shop_id === (int) $shop->id, 403);

        $data = $request->validate([
            'image_ids' => ['required', 'array', 'min:1', 'max:10'],
            'image_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $imageIds = array_values(array_map('intval', $data['image_ids']));
        $images = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $imageIds)
            ->get()
            ->keyBy('id');

        if ($images->count() !== count($imageIds)) {
            return response()->json([
                'message' => 'Une ou plusieurs images ne correspondent pas à ce produit.',
            ], 422);
        }

        if (Schema::hasColumn('product_images', 'sort_order')) {
            DB::transaction(function () use ($imageIds, $images) {
                foreach ($imageIds as $position => $imageId) {
                    $images[$imageId]->forceFill(['sort_order' => $position])->save();
                }
            });
        }

        $product->load(['category', 'images']);

        return response()->json([
            'message' => 'Ordre des images mis à jour.',
            'product' => $this->productPayload($product, true),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', 'all'));
        $sort = trim((string) $request->query('sort', 'newest'));
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));

        // Un seul relevé léger des statuts permet d'alimenter les compteurs et
        // les filtres sans charger les produits/images de toutes les commandes.
        $statusRows = DB::table('order_items')
            ->where('shop_id', $shop->id)
            ->whereNotNull('vendor_visible_at')
            ->get(['order_id', 'vendor_status', 'delivery_status']);

        $statusByOrder = $statusRows
            ->groupBy('order_id')
            ->map(fn ($items) => $this->vendorOrderStatus($items));

        $statusCounts = [
            'total' => $statusByOrder->count(),
            'pending' => $statusByOrder->filter(fn ($value) => $value === 'pending')->count(),
            'preparing' => $statusByOrder->filter(fn ($value) => in_array($value, ['preparing', 'ready'], true))->count(),
            'ready' => $statusByOrder->filter(fn ($value) => $value === 'ready')->count(),
            'shipped' => $statusByOrder->filter(fn ($value) => $value === 'shipped')->count(),
            'delivered' => $statusByOrder->filter(fn ($value) => $value === 'delivered')->count(),
            'cancelled' => $statusByOrder->filter(fn ($value) => $value === 'cancelled')->count(),
        ];
        $statusCounts['new'] = $statusCounts['pending'];
        $statusCounts['to_process'] = $statusCounts['pending'] + $statusCounts['preparing'];

        $query = Order::query()
            ->whereHas('items', fn (Builder $item) => $this->visibleVendorItems($item, $shop))
            ->with([
                'client:id,name,email,phone',
                // La liste n'a pas besoin des relations produit/images. Ne charger
                // que les lignes nécessaires rend l'écran rapide même avec un gros historique.
                'items' => fn ($item) => $this->visibleVendorItems($item, $shop),
            ])
            ->when($search !== '', function (Builder $q) use ($search, $shop) {
                $q->where(function (Builder $inner) use ($search, $shop) {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('delivery_site_name', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $client) => $client
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('items', function (Builder $items) use ($search, $shop) {
                            $this->visibleVendorItems($items, $shop)
                                ->whereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$search}%"));
                        });
                });
            });

        if ($status !== '' && $status !== 'all') {
            $acceptedStatuses = $status === 'preparing' ? ['preparing', 'ready'] : [$status];
            $matchingIds = $statusByOrder
                ->filter(fn ($value) => in_array($value, $acceptedStatuses, true))
                ->keys()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $matchingIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $matchingIds);
        }

        $sort === 'oldest' ? $query->orderBy('id') : $query->latest('id');

        $paginator = $query->paginate($perPage);
        $payload = $this->paginate($paginator, fn ($order) => $this->orderSummaryPayload($order, $shop));
        $payload['stats'] = $statusCounts;

        return response()->json($payload);
    }

    public function order(
        Request $request,
        Order $order,
        CommissionService $commissions,
        VendorOrderTransitionService $transitions
    ): JsonResponse {
        $shop = $this->shopFor($request);
        $this->authorizeOrder($order, $shop);

        $order->load([
            'client:id,name,email,phone',
            'payments',
            'items' => fn ($item) => $this->visibleVendorItems($item, $shop)
                ->with(['product.category', 'statusHistories', 'sellerTrackingSession.latestLocation']),
        ]);

        $items = $order->items->map(function ($item) use ($commissions, $shop, $transitions) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $publicSubtotal = (float) ($item->subtotal ?? 0);
            if ($publicSubtotal <= 0) {
                $publicSubtotal = (float) ($item->price ?? 0) * $quantity;
            }
            $breakdown = $commissions->breakdown($publicSubtotal, $shop);
            $sellerSubtotal = (float) ($breakdown['vendor_amount'] ?? 0);
            $commissionAmount = (float) ($breakdown['commission_amount'] ?? 0);
            $product = $item->product;
            $stock = $product?->stock;
            $unit = $product?->display_unit ?: ($product?->unit_label ?: ($product?->unit ?: 'unité'));
            $weight = (float) ($item->logistics_weight_kg ?? $product?->logistics_weight_kg ?? $product?->weight_kg ?? $product?->weight ?? 0);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $product?->name ?: $item->product_name ?: 'Produit',
                'image_url' => $product?->card_image_url,
                'category_name' => $product?->category?->name,
                'brand' => $product?->brand,
                'unit_label' => $unit,
                'weight_label' => $weight > 0 ? rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.') . ' kg' : null,
                'quantity' => $quantity,
                'unit_price' => round($publicSubtotal / $quantity, 2),
                'public_unit_price' => round($publicSubtotal / $quantity, 2),
                'public_subtotal' => round($publicSubtotal, 2),
                'seller_unit_price' => round($sellerSubtotal / $quantity, 2),
                'seller_subtotal' => round($sellerSubtotal, 2),
                'commission_amount' => round($commissionAmount, 2),
                'vendor_status' => $item->vendor_status ?: 'pending',
                'delivery_status' => $item->delivery_status,
                'delivery_provider' => $item->delivery_provider,
                'delivery_price' => (float) ($item->delivery_price ?? 0),
                'next_action' => $transitions->nextAction($item),
                'stock_available' => $stock === null ? true : ((float) $stock >= $quantity),
                'vehicle_label' => $item->logistics_vehicle_label,
            ];
        })->values();

        $sellerProductsTotal = (float) $items->sum('seller_subtotal');
        $publicProductsTotal = (float) $items->sum('public_subtotal');
        $commissionTotal = (float) $items->sum('commission_amount');
        $deliveryTotal = (float) $items->sum('delivery_price');
        $sellerDelivery = (float) $items
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
            ->sum('delivery_price');

        $providers = $order->items->pluck('delivery_provider')->filter()->unique()->values();
        $deliveryProvider = $providers->count() === 1 ? (string) $providers->first() : ($providers->isEmpty() ? '' : 'mixed');
        $deliveryProviderLabel = $providers->count() === 1
            ? app(OrderWorkflowService::class)->providerLabel($providers->first())
            : ($providers->isEmpty() ? 'Mode de livraison à définir' : 'Livraison mixte');
        $deliveryStatus = $this->vendorOrderDeliveryStatus($order->items);
        $tracking = $this->orderTrackingPayload($order, $shop, $order->items, $deliveryProvider);

        $paidAt = $order->payments
            ->filter(fn ($payment) => in_array((string) $payment->status, ['paid', 'escrow_held', 'released_to_vendor', 'verified'], true))
            ->sortByDesc(fn ($payment) => optional($payment->paid_at ?: $payment->updated_at)->timestamp ?? 0)
            ->first()?->paid_at;

        $preparedAt = $order->items->pluck('vendor_prepared_at')->filter()->sortDesc()->first();
        $shippedAt = collect([
            $order->items->pluck('picked_up_at')->filter()->sortDesc()->first(),
            $order->items->pluck('vendor_shipped_at')->filter()->sortDesc()->first(),
            data_get($tracking, 'picked_up_at'),
        ])->filter()->first();
        $deliveredAt = collect([
            $order->items->pluck('delivery_completed_at')->filter()->sortDesc()->first(),
            $order->items->pluck('vendor_delivered_at')->filter()->sortDesc()->first(),
            $order->delivered_at,
            data_get($tracking, 'delivered_at'),
        ])->filter()->first();

        $statusHistory = $order->items
            ->flatMap(fn ($item) => $item->statusHistories)
            ->sortByDesc('created_at')
            ->map(fn ($history) => [
                'id' => $history->id,
                'status' => $history->new_status,
                'new_status' => $history->new_status,
                'label' => $history->label ?: $history->new_status,
                'message' => $history->message,
                'status_type' => $history->status_type,
                'created_at' => optional($history->created_at)->toIso8601String(),
            ])
            ->values();

        $deliveryAddressParts = collect([
            $order->delivery_address ?: $order->address,
            $order->delivery_quartier,
            $order->delivery_commune,
            $order->delivery_city,
        ])->filter(fn ($value) => filled($value))->unique()->values();

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => optional($order->created_at)->toIso8601String(),
                'payment_status' => $order->payment_status,
                'payment_status_label' => $this->paymentStatusLabel($order->payment_status),
                'payment_method' => $order->payment_method,
                'payment_method_label' => $this->paymentMethodLabel($order->payment_method),
                'client' => $order->client ? [
                    'name' => $order->client->name,
                    'phone' => $order->client->phone,
                    'email' => $order->client->email,
                ] : [
                    'name' => $order->customer_name,
                    'phone' => $order->delivery_recipient_phone ?: $order->phone,
                ],
                'delivery_address' => [
                    'address' => $order->delivery_address ?: $order->address,
                    'site_name' => $order->delivery_site_name,
                    'recipient_name' => $order->delivery_recipient_name,
                    'recipient_phone' => $order->delivery_recipient_phone,
                    'quartier' => $order->delivery_quartier,
                    'commune' => $order->delivery_commune,
                    'city' => $order->delivery_city,
                    'latitude' => $order->delivery_latitude ?? $order->delivery_lat,
                    'longitude' => $order->delivery_longitude ?? $order->delivery_lng,
                    'formatted' => $deliveryAddressParts->join(', '),
                ],
                'vendor_status' => $this->vendorOrderStatus($order->items),
                'delivery_provider' => $deliveryProvider,
                'delivery_provider_label' => $deliveryProviderLabel,
                'delivery_status' => $deliveryStatus,
                'delivery_status_label' => app(OrderWorkflowService::class)->deliveryStatusLabel($deliveryStatus),
                'estimated_delivery_label' => data_get($tracking, 'estimated_delivery_label'),
                'items' => $items,
                'public_products_total' => round($publicProductsTotal, 2),
                'seller_products_total' => round($sellerProductsTotal, 2),
                'delivery_total' => round($deliveryTotal, 2),
                'seller_delivery_total' => round($sellerDelivery, 2),
                'commission_total' => round($commissionTotal, 2),
                'public_total_for_vendor' => round($publicProductsTotal + $deliveryTotal, 2),
                'vendor_payout_total' => round($sellerProductsTotal + $sellerDelivery, 2),
                'timeline' => [
                    'received_at' => optional($order->created_at)->toIso8601String(),
                    'payment_confirmed_at' => optional($paidAt)->toIso8601String(),
                    'prepared_at' => optional($preparedAt)->toIso8601String(),
                    'shipped_at' => $this->isoDate($shippedAt),
                    'delivered_at' => $this->isoDate($deliveredAt),
                ],
                'tracking' => $tracking,
                'status_history' => $statusHistory,
            ],
        ]);
    }

    public function updateOrderPreparation(
        Request $request,
        Order $order,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow
    ): JsonResponse {
        $shop = $this->shopFor($request);
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'vendor_status' => ['required', 'in:accepted,preparing,ready'],
            'vendor_status_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $target = $validated['vendor_status'];
        $note = $validated['vendor_status_note'] ?? null;
        $rank = ['pending' => 0, 'accepted' => 1, 'preparing' => 2, 'ready' => 3];

        DB::transaction(function () use ($order, $shop, $target, $note, $rank, $transitions, $workflow, $request) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = $this->visibleVendorItems($lockedOrder->items(), $shop)->lockForUpdate()->get();

            foreach ($items as $item) {
                $current = $item->vendor_status ?: 'pending';
                while (($rank[$current] ?? 99) < ($rank[$target] ?? -1)) {
                    $next = match ($current) {
                        'pending' => 'accepted',
                        'accepted' => 'preparing',
                        'preparing' => 'ready',
                        default => null,
                    };
                    if (! $next) break;

                    $transitions->assertPreparationTransition($item, $next);
                    $oldStatus = $current;
                    $item->vendor_status = $next;
                    $item->vendor_status_note = $note;
                    $item->vendor_status_updated_at = now();

                    if ($next === 'accepted') {
                        $item->vendor_confirmed_at = $item->vendor_confirmed_at ?: now();
                    }
                    if ($next === 'preparing') {
                        $item->delivery_status = $item->delivery_provider === OrderWorkflowService::PROVIDER_SELLER
                            ? OrderWorkflowService::DELIVERY_PREPARING
                            : ($item->delivery_status ?: OrderWorkflowService::DELIVERY_PENDING);
                    }
                    if ($next === 'ready') {
                        $item->vendor_prepared_at = $item->vendor_prepared_at ?: now();
                    }
                    $item->save();

                    $workflow->recordHistory($lockedOrder, $item, 'vendor_status', $oldStatus, $next, [
                        'actor_type' => 'vendor',
                        'user_id' => $request->user()?->id,
                        'label' => match ($next) {
                            'accepted' => 'Commande acceptée',
                            'preparing' => 'Préparation en cours',
                            'ready' => 'Préparation terminée',
                            default => $next,
                        },
                        'message' => $note,
                    ]);

                    if ($next === 'ready' && $item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE) {
                        $workflow->markVendorReadyForOvanie(
                            $item->refresh(),
                            $request->user(),
                            'Le vendeur a terminé la préparation. Le colis est prêt pour enlèvement OVANIE.'
                        );
                    }
                    $current = $next;
                }
            }

            $lockedOrder->refreshGlobalStatusFromItems();
        });

        return response()->json([
            'message' => $target === 'ready' ? 'Préparation validée. La commande est prête.' : 'État de préparation mis à jour.',
            'vendor_status' => $target,
        ]);
    }

    public function shipOrder(Request $request, Order $order): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorOrderController::class, 'markShipped', [
            'request' => $request,
            'order' => $order,
        ]);

        return response()->json(['message' => 'Prise en charge de la livraison enregistrée.']);
    }

    public function updateDeliveryStatus(Request $request, Order $order): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorOrderController::class, 'updateDeliveryStatus', [
            'request' => $request,
            'order' => $order,
        ]);

        return response()->json(['message' => 'État de livraison mis à jour.']);
    }

    public function verifyDeliveryOtp(Request $request, Order $order): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorOrderController::class, 'verifyDeliveryOtp', [
            'request' => $request,
            'order' => $order,
        ]);

        return response()->json(['message' => 'Code de réception validé. Livraison confirmée.']);
    }

    public function reportCodPayment(Request $request, Order $order): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorOrderController::class, 'confirmPayment', [
            'request' => $request,
            'order' => $order,
        ]);

        return response()->json(['message' => 'Encaissement signalé à OVANIE pour vérification.']);
    }

    public function finance(Request $request, VendorFinanceService $finance): JsonResponse
    {
        $shop = $this->shopFor($request);
        $finance->syncShop($shop);

        return response()->json([
            'summary' => $finance->summary($shop, (int) $request->user()->id),
            'recent_payouts' => VendorPayout::query()
                ->where('shop_id', $shop->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn ($payout) => $this->payoutPayload($payout))
                ->values(),
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $status = trim((string) $request->query('status', ''));
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));

        $paginator = VendorPayout::query()
            ->where('shop_id', $shop->id)
            ->when($status !== '' && $status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage);

        return response()->json($this->paginate($paginator, fn ($payout) => $this->payoutPayload($payout)));
    }

    public function payout(Request $request, VendorPayout $payout): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $payout->shop_id === (int) $shop->id, 403);

        return response()->json(['payout' => $this->payoutPayload($payout, true)]);
    }

    public function payoutReceipt(Request $request, VendorPayout $payout)
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $payout->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);
        return app(\App\Http\Controllers\SensitiveDocumentController::class)->vendorPayout($payout);
    }

    public function requestPayoutFollowUp(Request $request, VendorPayout $payout): JsonResponse
    {
        $shop = $this->shopFor($request);
        abort_unless((int) $payout->shop_id === (int) $shop->id, 403);
        $this->bindUserToAuth($request);

        $this->callController(\App\Http\Controllers\VendorPayoutController::class, 'requestFollowUp', [
            'request' => $request,
            'payout' => $payout,
        ]);

        return response()->json(['message' => 'Demande de suivi envoyée à OVANIE.']);
    }

    public function returns(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));

        $query = $this->menuCases($request, ReturnModel::class)->with(['order:id,order_number,client_id', 'client:id,name,phone', 'orderItem.product'])->latest('id');

        $paginator = $query->paginate($perPage);

        return response()->json($this->paginate($paginator, fn ($return) => $this->returnPayload($return)));
    }

    public function acceptReturn(Request $request, ReturnModel $return): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorReturnController::class, 'accept', [
            'request' => $request,
            'id' => $return->id,
        ]);

        return response()->json(['message' => 'Retour accepté selon le workflow OVANIE.']);
    }

    public function rejectReturn(Request $request, ReturnModel $return): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorReturnController::class, 'reject', [
            'request' => $request,
            'id' => $return->id,
        ]);

        return response()->json(['message' => 'Décision sur le retour enregistrée.']);
    }

    public function refundReturn(Request $request, ReturnModel $return): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorReturnController::class, 'refund', [
            'request' => $request,
            'id' => $return->id,
        ]);

        return response()->json(['message' => 'Décision de remboursement transmise à OVANIE Logistics.']);
    }

    public function disputes(Request $request): JsonResponse
    {
        $shop = $this->shopFor($request);
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));

        $query = $this->menuCases($request, Dispute::class)->with(['order:id,order_number,client_id', 'client:id,name,phone', 'orderItem.product'])->latest('id');

        $paginator = $query->paginate($perPage);

        return response()->json($this->paginate($paginator, fn ($dispute) => $this->disputePayload($dispute)));
    }

    public function respondDispute(Request $request, Dispute $dispute): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorDisputeController::class, 'respond', [
            'request' => $request,
            'id' => $dispute->id,
        ]);

        return response()->json(['message' => 'Réponse envoyée au dossier de litige.']);
    }

    public function escalateDispute(Request $request, Dispute $dispute): JsonResponse
    {
        $this->shopFor($request);
        $this->bindUserToAuth($request);
        $this->callController(VendorDisputeController::class, 'escalate', [
            'id' => $dispute->id,
        ]);

        return response()->json(['message' => 'Litige transmis au niveau supérieur OVANIE.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $perPage = max(10, min(50, (int) $request->integer('per_page', 20)));
        $paginator = $request->user()->notifications()->latest()->paginate($perPage);

        return response()->json($this->paginate($paginator, fn ($notification) => $this->notificationPayload($notification)) + [
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readNotification(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['notification' => $this->notificationPayload($item->fresh())]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }

    /**
     * Exécute une méthode de contrôleur comme une vraie méthode d'instance.
     *
     * app()->call([Controller::class, 'method']) peut être interprété comme
     * un appel statique dans certaines versions/configurations du container.
     * On résout donc d'abord l'instance via le container Laravel, puis on
     * laisse app()->call() injecter les dépendances de la méthode.
     */
    private function callController(string $controllerClass, string $method, array $parameters = []): mixed
    {
        $controller = app($controllerClass);

        return app()->call([$controller, $method], $parameters);
    }

    private function resolveOptionalSanctumUser(Request $request): ?User
    {
        $current = $request->user();
        if ($current instanceof User) {
            return $current;
        }

        $plainTextToken = trim((string) $request->bearerToken());
        if ($plainTextToken === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        if (! $accessToken || ! ($accessToken->tokenable instanceof User)) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $expiration = config('sanctum.expiration');
        if (is_numeric($expiration)
            && (int) $expiration > 0
            && $accessToken->created_at
            && $accessToken->created_at->lte(now()->subMinutes((int) $expiration))) {
            return null;
        }

        $user = $accessToken->tokenable;
        $request->setUserResolver(static fn () => $user);
        Auth::setUser($user);

        return $user;
    }

    private function bindUserToAuth(Request $request): void
    {
        if ($request->user()) {
            Auth::setUser($request->user());
        }
    }

    private function shopFor(Request $request): Shop
    {
        $shop = $request->user()?->shop()->first();
        abort_unless($shop, 403, 'Créez votre boutique avant d’utiliser cet espace vendeur.');
        return $shop;
    }

    /**
     * Applique le filtre de visibilité vendeur à un Builder Eloquent OU à une
     * relation HasMany. Les callbacks de whereHas() reçoivent un Builder,
     * tandis que les callbacks de with()/load() reçoivent une Relation.
     *
     * Ne pas typer ce paramètre uniquement en Builder : cela casse les écrans
     * Commandes lors des eager-loads avec une erreur TypeError sur HasMany.
     */
    private function visibleVendorItems($query, Shop $shop)
    {
        if (Schema::hasColumn('order_items', 'vendor_visible_at')) {
            $query->whereNotNull('vendor_visible_at');
        }

        return $query->where(function (Builder $shopQuery) use ($shop) {
            if (Schema::hasColumn('order_items', 'shop_id')) {
                $shopQuery->where('shop_id', $shop->id)
                    ->orWhere(function (Builder $legacy) use ($shop) {
                        $legacy->whereNull('shop_id')
                            ->whereHas('product', fn (Builder $product) => $product->where('shop_id', $shop->id));
                    });
                return;
            }

            $shopQuery->whereHas('product', fn (Builder $product) => $product->where('shop_id', $shop->id));
        });
    }

    private function authorizeOrder(Order $order, Shop $shop): void
    {
        $query = $order->items();
        $this->visibleVendorItems($query, $shop);

        abort_unless(
            $query->exists(),
            403,
            'Cette commande n’appartient pas à votre boutique.'
        );
    }

    /**
     * Délègue à OrderWorkflowService::resolveVendorDisplayStatus(), la même
     * logique désormais partagée avec l'espace vendeur web (voir
     * VendorOrderController::resolveVendorOrderStatus()) pour que web et
     * mobile affichent toujours le même statut pour une même commande.
     */
    private function vendorOrderStatus($items): string
    {
        return app(OrderWorkflowService::class)->resolveVendorDisplayStatus($items);
    }

    private function userPayload($user): ?array
    {
        if (! $user) return null;
        return [
            'id' => $user->id,
            'name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
        ];
    }

    private function shopPayload(Shop $shop, bool $detailed = false): array
    {
        $payload = [
            'id' => $shop->id,
            'name' => $shop->name,
            'description' => $shop->description,
            'presentation' => json_decode($shop->getRawOriginal('mobile_presentation') ?? '{}', true),
            'owner_name' => $shop->user?->name,
            'logo_url' => $shop->logo ? $shop->logo_url : null,
            'status' => $shop->status,
            'kyc_status' => $shop->kyc_status,
            'logistics_status' => $shop->logistics_status,
            'logistics_type' => $shop->logistics_type ?: 'ovanie',
            'logistics_mode_label' => $shop->logistics_mode_label,
            'can_publish_products' => $shop->canPublishProducts(),
            'is_active' => (bool) $shop->is_active,
            'main_category' => $shop->main_category,
            'delivery_zone' => $shop->delivery_zone,
            'processing_time' => $shop->processing_time,
            'region' => $shop->region,
            'city' => $shop->city,
            'commune' => $shop->commune,
            'district' => $shop->district,
            'landmark' => $shop->landmark,
            'address' => $shop->address,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'geo_status' => $shop->geo_status,
            'logistics_location_ready' => \App\Services\ShopLogisticsSettingsService::hasReliableStoredLocation($shop),
            'geo_status_label' => $shop->geo_status_label,
            'whatsapp' => $shop->whatsapp,
            'business_email' => $shop->business_email,
            'payment_mode' => $shop->payment_mode,
            'payment_mode_label' => $shop->payment_mode_label,
            'mm_operator' => $shop->mm_operator,
            'mm_number' => $shop->mm_number,
            'mm_holder' => $shop->mm_holder,
        ];

        if ($detailed) {
            $payload['seller_type'] = $shop->seller_type;
            $payload['company_name'] = $shop->company_name;
            $payload['legal_form'] = $shop->legal_form;
            $payload['rccm'] = $shop->rccm;
            $payload['taxpayer_number'] = $shop->taxpayer_number;
            $payload['identity_country'] = $shop->identity_country;
            $payload['identity_type'] = $shop->identity_type;
            $payload['identity_number'] = $shop->identity_number;
            $payload['seller_delivery_profile'] = $shop->usesSellerLogistics() && $shop->relationLoaded('sellerDeliveryProfile') ? $shop->sellerDeliveryProfile : null;
            $payload['seller_delivery_zones'] = $shop->usesSellerLogistics() && $shop->relationLoaded('sellerDeliveryZones') ? $shop->sellerDeliveryZones->values() : [];
        }

        return $payload;
    }

    /**
     * Transforme une ligne agrégée renvoyée par VendorDashboardService::topProducts().
     * Ce service retourne un OrderItem agrégé, pas directement un Product.
     */
    private function topProductPayload($item): ?array
    {
        if ($item instanceof Product) {
            $item->loadMissing('category');
            return $this->productPayload($item);
        }

        $product = $item?->product ?? null;
        if (! $product instanceof Product) {
            return null;
        }

        $product->loadMissing('category');
        $payload = $this->productPayload($product);
        $payload['sold_quantity'] = (int) ($item->sold_quantity ?? 0);
        $payload['sold_amount'] = round((float) ($item->sold_amount ?? 0), 2);

        return $payload;
    }

    private function productPayload(Product $product, bool $detailed = false): array
    {
        $canonical = app(ProductSheetPresenter::class)->present($product, $detailed);

        // Les informations descriptives restent alignées avec le contrat produit
        // commun. En revanche, l'espace Vendeur doit afficher le montant saisi
        // par le vendeur et jamais le prix public OVANIE destiné aux clients.
        $sellerPrice = max(0, (float) ($product->price ?? 0));
        $sellerPromoPrice = $product->promo_price !== null
            ? max(0, (float) $product->promo_price)
            : null;
        $sellerEffectivePrice = $sellerPromoPrice !== null && $sellerPromoPrice > 0
            ? $sellerPromoPrice
            : $sellerPrice;

        $payload = array_merge($canonical, [
            'sku' => $product->sku,
            'status' => $product->status,
            'is_active' => (bool) $product->is_active,

            // Compatibilité historique + champs explicites pour l'application Vendeur.
            'price' => $sellerPrice,
            'promo_price' => $sellerPromoPrice,
            'seller_price' => $sellerPrice,
            'seller_promo_price' => $sellerPromoPrice,
            'seller_effective_price' => $sellerEffectivePrice,

            'sale_type' => $product->sale_type,
            'created_at' => optional($product->created_at)->toIso8601String(),
            'updated_at' => optional($product->updated_at)->toIso8601String(),
        ]);

        if ($detailed) {
            foreach ([
                'delivery_mode', 'pickup_city', 'pickup_commune', 'pickup_address',
                'fast_delivery', 'is_negotiable', 'price_p1', 'price_p2', 'price_p3',
                'product_video_url', 'handling_options',
            ] as $field) {
                $payload[$field] = $product->{$field};
            }
        }

        return $payload;
    }

    private function orderSummaryPayload(Order $order, Shop $shop): array
    {
        if (! $order->relationLoaded('items')) {
            $order->load(['items' => fn ($items) => $this->visibleVendorItems($items, $shop)]);
        }

        $vendorItems = $order->items;
        $publicTotal = (float) $vendorItems->sum(function ($item) {
            $subtotal = (float) ($item->subtotal ?? 0);
            return $subtotal > 0 ? $subtotal : ((float) ($item->price ?? 0) * max(1, (int) ($item->quantity ?? 1)));
        });

        $clientName = $order->relationLoaded('client') ? $order->client?->name : null;
        $displayName = trim((string) ($order->delivery_site_name ?: $clientName ?: $order->customer_name ?: 'Client OVANIE'));
        $vendorStatus = $this->vendorOrderStatus($vendorItems);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'created_at' => optional($order->created_at)->toIso8601String(),
            'payment_status' => $order->payment_status,
            'payment_status_label' => $this->paymentStatusLabel($order->payment_status),
            'payment_method' => $order->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($order->payment_method),
            'vendor_status' => $vendorStatus,
            'items_count' => $vendorItems->count(),
            'public_products_total' => round($publicTotal, 2),
            'client_name' => $clientName,
            'client_display_name' => $displayName,
            'delivery_site_name' => $order->delivery_site_name,
        ];
    }


    private function vendorOrderDeliveryStatus($items): string
    {
        $statuses = collect($items)->pluck('delivery_status')->filter()->values();
        if ($statuses->isEmpty()) return OrderWorkflowService::DELIVERY_PENDING;
        if ($statuses->every(fn ($status) => $status === OrderWorkflowService::DELIVERY_DELIVERED)) return OrderWorkflowService::DELIVERY_DELIVERED;

        $priority = [
            OrderWorkflowService::DELIVERY_CANCELLED => 0,
            OrderWorkflowService::DELIVERY_PENDING => 1,
            OrderWorkflowService::DELIVERY_PREPARING => 2,
            OrderWorkflowService::DELIVERY_READY_FOR_PICKUP => 3,
            OrderWorkflowService::DELIVERY_ASSIGNED => 4,
            OrderWorkflowService::DELIVERY_PICKED_UP => 5,
            OrderWorkflowService::DELIVERY_IN_TRANSIT => 6,
            OrderWorkflowService::DELIVERY_LATE => 7,
            OrderWorkflowService::DELIVERY_FAILED => 8,
            OrderWorkflowService::DELIVERY_DELIVERED => 9,
        ];

        return (string) $statuses->sortByDesc(fn ($status) => $priority[$status] ?? 1)->first();
    }

    private function orderTrackingPayload(Order $order, Shop $shop, $items, string $provider): array
    {
        $itemIds = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $shipment = null;
        $assignment = null;
        $sellerSession = null;

        if (Schema::hasTable('shipments') && $itemIds !== []) {
            $shipment = Shipment::query()
                ->where('order_id', $order->id)
                ->where(function (Builder $query) use ($shop, $itemIds) {
                    $query->whereIn('order_item_id', $itemIds)
                        ->orWhere('shop_id', $shop->id);
                })
                ->with('latestDriverLocation')
                ->latest('id')
                ->first();
        }

        if (Schema::hasTable('delivery_assignments') && $itemIds !== []) {
            $assignment = DeliveryAssignment::query()
                ->where('order_id', $order->id)
                ->whereIn('order_item_id', $itemIds)
                ->with(['driver.currentLocation'])
                ->latest('id')
                ->first();
        }

        if (Schema::hasTable('seller_delivery_tracking_sessions')) {
            $sellerSession = SellerDeliveryTrackingSession::query()
                ->where('order_id', $order->id)
                ->where('shop_id', $shop->id)
                ->with('latestLocation')
                ->latest('id')
                ->first();
        }

        $driverPayload = null;
        if ($assignment?->driver) {
            $location = $assignment->driver->currentLocation ?: $shipment?->latestDriverLocation;
            $driverPayload = [
                'id' => $assignment->driver->id,
                'name' => $assignment->driver->name,
                'phone' => $assignment->driver->phone,
                'rating' => $assignment->driver->rating,
                'latitude' => $location?->latitude ?? $assignment->driver->latitude,
                'longitude' => $location?->longitude ?? $assignment->driver->longitude,
                'location_updated_at' => optional($location?->recorded_at ?? $assignment->driver->last_seen_at)->toIso8601String(),
                'vehicle_label' => $shipment?->vehicle_label
                    ?: data_get($assignment->meta, 'vehicle_label')
                    ?: collect($items)->pluck('logistics_vehicle_label')->filter()->first(),
                'vehicle_plate' => data_get($assignment->meta, 'vehicle_plate')
                    ?: collect($items)->pluck('vehicle_plate')->filter()->first(),
            ];
        } elseif ($sellerSession) {
            $location = $sellerSession->latestLocation;
            $driverPayload = [
                'name' => $sellerSession->driver_name,
                'phone' => $sellerSession->driver_phone,
                'rating' => null,
                'latitude' => $location?->latitude ?? $sellerSession->last_latitude,
                'longitude' => $location?->longitude ?? $sellerSession->last_longitude,
                'location_updated_at' => optional($location?->recorded_at ?? $sellerSession->last_location_at)->toIso8601String(),
                'vehicle_label' => collect($items)->pluck('logistics_vehicle_label')->filter()->first(),
                'vehicle_plate' => $sellerSession->vehicle_plate,
            ];
        }

        $estimated = $assignment?->estimated_delivery_at
            ?: $shipment?->estimated_delivery_at
            ?: $sellerSession?->estimated_delivery_at
            ?: $order->delivery_max_date;

        $pickupLat = $shipment?->pickup_latitude ?? $shop->latitude;
        $pickupLng = $shipment?->pickup_longitude ?? $shop->longitude;
        $deliveryLat = $shipment?->delivery_latitude ?? $order->delivery_latitude ?? $order->delivery_lat;
        $deliveryLng = $shipment?->delivery_longitude ?? $order->delivery_longitude ?? $order->delivery_lng;

        return [
            'provider' => $provider,
            'mission_number' => $assignment?->resolved_mission_number,
            'tracking_number' => $shipment?->tracking_number,
            'status' => $assignment?->status ?: $shipment?->status ?: $sellerSession?->mission_status,
            'assignment_message' => $driverPayload ? null : 'En attente de l’affectation d’un livreur par OVANIE Logistics.',
            'estimated_delivery_at' => $this->isoDate($estimated),
            'estimated_delivery_label' => $estimated ? 'Livraison estimée : ' . optional($estimated)->format('d/m/Y H:i') : null,
            'pickup_address' => $shipment?->pickup_address ?: $shop->address,
            'pickup_latitude' => $pickupLat,
            'pickup_longitude' => $pickupLng,
            'delivery_address' => $shipment?->delivery_address ?: ($order->delivery_address ?: $order->address),
            'delivery_latitude' => $deliveryLat,
            'delivery_longitude' => $deliveryLng,
            'route_geometry' => $shipment?->route_geometry ?: $sellerSession?->route_geometry,
            'picked_up_at' => $this->isoDate($assignment?->picked_up_at ?: $sellerSession?->departed_at),
            'delivered_at' => $this->isoDate($assignment?->delivered_at ?: $shipment?->delivered_at ?: $sellerSession?->ended_at),
            'driver' => $driverPayload,
        ];
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'cash_on_delivery', 'cod', 'cash' => 'Paiement à la livraison',
            'wave' => 'Wave',
            'orange_money', 'orange' => 'Orange Money',
            'mtn_money', 'mtn' => 'MTN Money',
            'moov_money', 'moov' => 'Moov Money',
            'card', 'bank_card', 'paydunya', 'flutterwave' => 'Paiement en ligne',
            default => filled($method) ? (string) $method : 'Paiement',
        };
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid', 'escrow_held', 'verified', 'released_to_vendor' => 'Payée',
            'commission_paid', 'partial' => 'Partiel',
            'failed' => 'Échoué',
            'cancelled' => 'Annulé',
            default => 'À la livraison',
        };
    }

    private function isoDate($value): ?string
    {
        if (! $value) return null;
        if ($value instanceof \DateTimeInterface) return $value->format(DATE_ATOM);
        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function payoutPayload(VendorPayout $payout, bool $detailed = false): array
    {
        $payload = [
            'id' => $payout->id,
            'reference' => $payout->payout_reference ?? ('PAYOUT-'.$payout->id),
            'status' => $payout->status,
            'status_label' => $payout->status_label,
            'status_tone' => $payout->status_tone,
            'amount' => (float) ($payout->payout_amount ?? 0),
            'payment_method' => $payout->payment_method ?? $payout->payment_channel,
            'payment_method_label' => $payout->payment_method_label,
            'phone' => $payout->phone,
            'beneficiary_name' => $payout->vendor?->name,
            'period_label' => $payout->payout_period_key,
            'has_receipt' => filled($payout->transfer_receipt_path),
            'created_at' => optional($payout->created_at)->toIso8601String(),
            'scheduled_for' => optional($payout->scheduled_for)->toIso8601String(),
            'expected_payment_date' => optional($payout->expected_payment_date)->toIso8601String(),
            'approved_at' => optional($payout->approved_at)->toIso8601String(),
            'processing_at' => optional($payout->processing_at)->toIso8601String(),
            'paid_at' => optional($payout->paid_at)->toIso8601String(),
            'failed_at' => optional($payout->failed_at)->toIso8601String(),
        ];

        if ($detailed) {
            $payload['product_amount'] = (float) ($payout->product_amount ?? 0);
            $payload['seller_delivery_amount'] = (float) ($payout->seller_delivery_amount ?? 0);
            $payload['total_amount'] = (float) ($payout->total_amount ?? 0);
            $payload['commission_amount'] = (float) ($payout->commission_amount ?? 0);
            $payload['processing_fee_amount'] = (float) ($payout->processing_fee_amount ?? 0);
            $payload['payout_reference'] = $payout->payout_reference;
            $payload['batch_reference'] = $payout->batch_reference;
            $payload['updated_at'] = optional($payout->updated_at)->toIso8601String();
            $payload['vendor_note'] = $payout->vendor_note;
            $payload['admin_note'] = $payout->admin_note;
            $payload['vendor_followup_requested_at'] = optional($payout->vendor_followup_requested_at)->toIso8601String();
            $order = $payout->order;
            $payload['order'] = $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => optional($order->created_at)->toIso8601String(),
            ] : null;
        }
        return $payload;
    }

    private function returnPayload(ReturnModel $return): array
    {
        return $this->caseSummary($return) + [
            'id' => $return->id,
            'reference' => $return->order_reference ?: ('RET-'.$return->id),
            'status' => $return->status,
            'reason' => $return->reason ?? $return->motif,
            'description' => $return->vendor_response,
            'order_id' => $return->order_id,
            'order_number' => $return->order?->order_number,
            'created_at' => optional($return->created_at)->toIso8601String(),
        ];
    }

    private function disputePayload(Dispute $dispute): array
    {
        return $this->caseSummary($dispute) + [
            'id' => $dispute->id,
            'reference' => $dispute->order_reference ?: ('LIT-'.$dispute->id),
            'status' => VendorDisputeController::resolveDisplayStatus($dispute),
            'subject' => $dispute->subject ?? $dispute->title ?? $dispute->reason,
            'description' => $dispute->response,
            'order_id' => $dispute->order_id,
            'order_number' => $dispute->order?->order_number,
            'created_at' => optional($dispute->created_at)->toIso8601String(),
        ];
    }

    private function notificationPayload(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? $data['subject'] ?? 'OVANIE Vendeur',
            'message' => $data['message'] ?? $data['body'] ?? '',
            'category' => $data['category'] ?? $data['type'] ?? null,
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
            'return_id' => isset($data['return_id']) ? (int) $data['return_id'] : null,
            'dispute_id' => isset($data['dispute_id']) ? (int) $data['dispute_id'] : null,
            'read' => $notification->read_at !== null,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'details' => $data,
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    private function paginate(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
