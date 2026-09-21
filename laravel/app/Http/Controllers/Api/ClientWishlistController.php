<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\PublicProductVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ClientWishlistController extends Controller
{
    public function __construct(
        private readonly PublicProductVisibilityService $visibility,
        private readonly PublicProductController $products,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get();

        $this->loadVisibleProducts($wishlists);

        return response()->json([
            'data' => $wishlists->map(fn (Wishlist $wishlist) => $this->payload($wishlist))->values(),
            'count' => $wishlists->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('wishlists', 'name')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        $wishlist = Wishlist::create([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'alerts_enabled' => false,
        ]);

        $wishlist->setRelation('products', collect());

        return response()->json([
            'data' => $this->payload($wishlist),
        ], 201);
    }

    public function update(Request $request, Wishlist $wishlist): JsonResponse
    {
        $wishlist = $this->ownedWishlist($request, $wishlist);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('wishlists', 'name')
                    ->where(fn ($query) => $query->where('user_id', $request->user()->id))
                    ->ignore($wishlist->id),
            ],
            'alerts_enabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }

        $wishlist->fill($validated)->save();
        $wishlist = $wishlist->fresh();
        $this->loadVisibleProducts(collect([$wishlist]));

        return response()->json([
            'data' => $this->payload($wishlist),
        ]);
    }

    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        $wishlist = $this->ownedWishlist($request, $wishlist);
        $wishlist->delete();

        return response()->json([
            'message' => 'Liste d’envies supprimée.',
        ]);
    }

    public function addProduct(
        Request $request,
        Wishlist $wishlist,
        Product $product,
    ): JsonResponse {
        $wishlist = $this->ownedWishlist($request, $wishlist);

        $visible = Product::query()->whereKey($product->getKey());
        $this->visibility->apply($visible, true);

        if (! $visible->exists()) {
            return response()->json([
                'message' => 'Ce produit n’est plus disponible.',
            ], 422);
        }

        $wishlist->products()->syncWithoutDetaching([$product->id]);
        $wishlist->touch();
        $this->loadVisibleProducts(collect([$wishlist]));

        return response()->json([
            'data' => $this->payload($wishlist),
        ], 201);
    }

    public function removeProduct(
        Request $request,
        Wishlist $wishlist,
        Product $product,
    ): JsonResponse {
        $wishlist = $this->ownedWishlist($request, $wishlist);
        $wishlist->products()->detach($product->id);
        $wishlist->touch();
        $this->loadVisibleProducts(collect([$wishlist]));

        return response()->json([
            'data' => $this->payload($wishlist),
            'message' => 'Produit retiré de la liste d’envies.',
        ]);
    }

    private function ownedWishlist(Request $request, Wishlist $wishlist): Wishlist
    {
        abort_unless((int) $wishlist->user_id === (int) $request->user()->id, 404);

        return $wishlist;
    }

    /**
     * Charge seulement les produits publiquement visibles afin que le mobile
     * et le Web respectent exactement les mêmes règles de publication.
     */
    private function loadVisibleProducts(Collection $wishlists): void
    {
        foreach ($wishlists as $wishlist) {
            $query = $wishlist->products()
                ->with(['images', 'category'])
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->latest('wishlist_product.created_at');

            $this->visibility->apply($query->getQuery(), true);
            $wishlist->setRelation('products', $query->get());
        }
    }

    private function payload(Wishlist $wishlist): array
    {
        $products = $wishlist->relationLoaded('products')
            ? $wishlist->getRelation('products')
            : collect();

        return [
            'id' => (int) $wishlist->id,
            'name' => $wishlist->name,
            'alerts_enabled' => (bool) $wishlist->alerts_enabled,
            'updated_at' => optional($wishlist->updated_at)->toIso8601String(),
            'products_count' => $products->count(),
            'products' => $products
                ->map(fn (Product $product) => $this->products->publicProductPayload($product))
                ->values(),
        ];
    }
}
