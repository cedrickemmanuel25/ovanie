<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Models\Favorite;
use App\Models\Product;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientFavoriteController extends Controller
{
    public function __construct(
        private readonly PublicProductVisibilityService $visibility,
        private readonly PublicProductController $products,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->favoriteProducts()
            ->with(['images', 'category'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        $this->visibility->apply($query->getQuery(), true);

        $favorites = $query
            ->latest('favorites.created_at')
            ->get()
            ->map(fn (Product $product) => $this->products->publicProductPayload($product))
            ->values();

        return response()->json([
            'data' => $favorites,
            'count' => $favorites->count(),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $visible = Product::query()->whereKey($product->getKey());
        $this->visibility->apply($visible, true);

        if (! $visible->exists()) {
            return response()->json([
                'message' => 'Ce produit n’est plus disponible.',
            ], 422);
        }

        Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $product->loadMissing(['images', 'category'])->loadCount('reviews')->loadAvg('reviews', 'rating');

        return response()->json([
            'data' => $this->products->publicProductPayload($product),
            'count' => $request->user()->favoriteProducts()->count(),
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'message' => 'Produit retiré des favoris.',
            'count' => $request->user()->favoriteProducts()->count(),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        Favorite::query()->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Favoris supprimés.',
            'count' => 0,
        ]);
    }
}
