<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class AiRecommendationService
{
    /**
     * Recommande des produits complémentaires ou similaires sans fournisseur IA externe.
     */
    public function recommendForProduct(Product $product, int $limit = 8): Collection
    {
        $limit = $limit ?: (int) config('ai.recommendations.limit', 8);

        return Cache::remember("ai:recommendations:product:{$product->id}:{$limit}", now()->addMinutes(30), function () use ($product, $limit) {
            return Product::query()
                ->with(['category', 'shop'])
                ->where('id', '!=', $product->id)
                ->when($product->category_id ?? null, fn ($query) => $query->where('category_id', $product->category_id))
                ->when(isset($product->status), fn ($query) => $query->where('status', $product->status))
                ->orderByDesc('is_featured')
                ->orderByDesc('views')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Recommande des produits pour un panier/projet chantier.
     */
    public function recommendForProject(array $projectContext, int $limit = 12): Collection
    {
        $categoryNames = collect($projectContext['categories'] ?? [])->filter()->values();

        return Product::query()
            ->with(['category', 'shop'])
            ->when($categoryNames->isNotEmpty(), function ($query) use ($categoryNames) {
                $query->whereHas('category', function ($categoryQuery) use ($categoryNames) {
                    $categoryQuery->whereIn('name', $categoryNames);
                });
            })
            ->when(!empty($projectContext['city']), fn ($query) => $query->where('city', $projectContext['city']))
            ->orderByDesc('is_featured')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    public function recommendCategoriesForSearch(string $query, int $limit = 6): Collection
    {
        return Category::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }
}
