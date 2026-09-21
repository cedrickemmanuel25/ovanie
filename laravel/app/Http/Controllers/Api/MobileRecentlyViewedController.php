<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileRecentlyViewedController extends Controller
{
    public function __construct(
        private readonly PublicProductController $products,
        private readonly PublicProductVisibilityService $visibility,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('recently_viewed_products')) {
            return response()->json(['data' => [], 'sync_available' => false]);
        }

        $rows = RecentlyViewedProduct::query()
            ->where('user_id', $request->user()->id)
            ->with(['product.category', 'product.images'])
            ->latest('last_viewed_at')
            ->limit(30)
            ->get();

        $visibleIds = $this->visibility->query([], true)
            ->whereIn('products.id', $rows->pluck('product_id')->all())
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $products = $rows
            ->filter(fn (RecentlyViewedProduct $row) => $row->product && in_array((int) $row->product_id, $visibleIds, true))
            ->map(function (RecentlyViewedProduct $row) {
                $product = $row->product;
                if (Schema::hasTable('reviews')) {
                    $product->loadCount('reviews')->loadAvg('reviews', 'rating');
                }

                return [
                    'last_viewed_at' => $row->last_viewed_at?->toIso8601String(),
                    'views_count' => (int) $row->views_count,
                    'product' => $this->products->publicProductPayload($product),
                ];
            })
            ->values();

        return response()->json(['data' => $products, 'sync_available' => true]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        if (! Schema::hasTable('recently_viewed_products')) {
            return response()->json(['message' => 'Synchronisation indisponible.', 'sync_available' => false], 503);
        }

        $isPublic = $this->visibility
            ->apply(Product::query()->whereKey($product->id), true)
            ->exists();
        abort_unless($isPublic, 404);

        $row = RecentlyViewedProduct::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);
        $row->views_count = max(0, (int) $row->views_count) + 1;
        $row->last_viewed_at = now();
        $row->save();

        // On conserve au plus 30 produits par compte. Cette limite est partagée
        // par Web et mobile et évite une table d'historique infinie.
        $staleIds = RecentlyViewedProduct::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_viewed_at')
            ->skip(30)
            ->take(200)
            ->pluck('id');
        if ($staleIds->isNotEmpty()) {
            RecentlyViewedProduct::whereIn('id', $staleIds)->delete();
        }

        return response()->json([
            'message' => 'Consultation synchronisée.',
            'data' => [
                'product_id' => (int) $product->id,
                'last_viewed_at' => $row->last_viewed_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (Schema::hasTable('recently_viewed_products')) {
            RecentlyViewedProduct::query()->where('user_id', $request->user()->id)->delete();
        }

        return response()->json(['message' => 'Historique des produits consultés effacé.']);
    }
}
