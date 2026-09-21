<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Contrôleur API public défensif.
 *
 * Les boutiques sont une donnée interne OVANIE : les réponses sont construites
 * depuis une liste blanche et n'exposent ni shop_id, ni vendor_id, ni lieu de
 * retrait, ni relation boutique/vendeur.
 */
class ProductController extends Controller
{
    public function __construct(private readonly PublicProductVisibilityService $visibility)
    {
    }

    public function index(Request $request)
    {
        $query = $this->visibility->query(['category', 'images'], true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('city') && Schema::hasColumn('products', 'city')) {
            $query->where('city', 'like', '%' . $request->string('city') . '%');
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(function ($search) use ($term) {
                $search->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        match ($request->get('sort', 'latest')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => Schema::hasColumn('products', 'views')
                ? $query->orderByDesc('views')
                : $query->latest(),
            default => $query->latest(),
        };

        $products = $query->paginate(max(1, min(100, (int) $request->get('per_page', 20))));
        $products->setCollection(
            $products->getCollection()->map(fn (Product $product) => $this->publicPayload($product))
        );

        return response()->json($products);
    }

    public function show(string $identifier)
    {
        $product = $this->visibility
            ->query(['category', 'images', 'reviews.user'], true)
            ->where(function ($query) use ($identifier) {
                $query->where('products.id', $identifier)
                    ->orWhere('products.slug', $identifier);
            })
            ->firstOrFail();

        return response()->json(['data' => $this->publicPayload($product, true)]);
    }

    private function publicPayload(Product $product, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $product->id,
            'name' => ovanie_public_text((string) $product->name, (string) $product->slug),
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => ovanie_public_text(
                    (string) $product->category->name,
                    (string) $product->category->slug,
                ),
                'slug' => $product->category->slug,
            ] : null,
            'price' => (float) $product->price,
            'promo_price' => $product->promo_price !== null ? (float) $product->promo_price : null,
            'final_price' => (float) $product->final_price,
            'stock' => (int) $product->stock,
            'availability_status' => $product->availability_status,
            'unit' => ovanie_public_text((string) ($product->unit ?? '')),
            'unit_label' => $product->unit_label,
            'min_order_quantity' => (int) ($product->min_order_quantity ?: 1),
            'short_description' => $product->short_description,
            'brand' => $product->brand,
            'seller_label' => 'Vendu sur OVANIE',
            'images' => $product->relationLoaded('images')
                ? $product->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_main' => (bool) $image->is_main,
                ])->values()->all()
                : [],
        ];

        if ($includeDetails) {
            $payload['description'] = $product->description;
            $payload['technical_details'] = $product->technical_details;
            $payload['warranty'] = $product->warranty;
            $payload['return_policy'] = $product->return_policy;
            $payload['reviews'] = $product->relationLoaded('reviews')
                ? $product->reviews->map(fn ($review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => optional($review->created_at)->toDateTimeString(),
                    'user' => $review->user ? ['name' => $review->user->name] : null,
                ])->values()->all()
                : [];
        }

        return $payload;
    }
}
