<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SearchService
{
    public function __construct(
        private readonly PublicProductVisibilityService $visibility,
    ) {
    }

    public function suggestions(string $term = '', ?string $categorySlug = null, int $limit = 6): array
    {
        $term = trim($term);
        $limit = max(1, min(10, $limit));

        if ($term === '') {
            return [
                'query' => '',
                'products' => [],
                'categories' => [],
                'popular' => $this->popularSearches(6),
            ];
        }

        return [
            'query' => $term,
            'products' => $this->productSuggestions($term, $categorySlug, $limit)->values()->all(),
            'categories' => $this->categorySuggestions($term, 5)->values()->all(),
            'popular' => [],
        ];
    }

    public function recordSearch(string $term, ?string $categorySlug = null): void
    {
        $term = trim($term);
        if ($term === '' || ! Schema::hasTable('search_queries')) {
            return;
        }

        $normalized = $this->normalize($term);

        // La colonne search_queries.category_slug est NOT NULL dans la migration.
        // Une recherche globale (sans catégorie) doit donc être enregistrée
        // avec une chaîne vide et jamais avec NULL.
        $normalizedCategorySlug = trim((string) ($categorySlug ?? ''));

        $query = SearchQuery::query()->firstOrNew([
            'normalized_term' => $normalized,
            'category_slug' => $normalizedCategorySlug,
        ]);

        $query->term = Str::limit($term, 120, '');
        $query->searches_count = ((int) $query->searches_count) + 1;
        $query->last_searched_at = now();
        $query->save();
    }

    public function popularSearches(int $limit = 6): array
    {
        if (! Schema::hasTable('search_queries')) {
            return [];
        }

        return SearchQuery::query()
            ->where('searches_count', '>', 0)
            ->orderByDesc('searches_count')
            ->orderByDesc('last_searched_at')
            ->limit(max(1, min(12, $limit)))
            ->pluck('term')
            ->filter()
            ->values()
            ->all();
    }

    private function productSuggestions(string $term, ?string $categorySlug, int $limit): Collection
    {
        $lower = mb_strtolower($term);
        $tokens = collect(preg_split('/\s+/u', $lower, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $token) => mb_strlen($token) >= 2)
            ->take(5)
            ->values();

        $query = $this->visibility
            ->query(['category', 'images'])
            ->when(Schema::hasTable('reviews'), function (Builder $query) {
                $query->withCount('reviews');
                if (Schema::hasColumn('reviews', 'rating')) {
                    $query->withAvg('reviews', 'rating');
                }
            });


        if ($categorySlug) {
            $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('slug', $categorySlug));
        }

        $query->where(function (Builder $outer) use ($lower, $tokens) {
            $outer->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);

            if (Schema::hasColumn('products', 'slug')) {
                $outer->orWhereRaw('LOWER(slug) LIKE ?', ["%{$lower}%"]);
            }

            foreach (['brand', 'short_description', 'usage_area', 'material_grade', 'packaging'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $outer->orWhereRaw("LOWER({$column}) LIKE ?", ["%{$lower}%"]);
                }
            }

            $outer->orWhereHas('category', function (Builder $categoryQuery) use ($lower) {
                $categoryQuery->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$lower}%"]);
            });

            $outer->orWhereHas('category.parent', function (Builder $parentCategoryQuery) use ($lower) {
                $parentCategoryQuery->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$lower}%"]);
            });

            foreach ($tokens as $token) {
                $outer->orWhereRaw('LOWER(name) LIKE ?', ["%{$token}%"]);
            }
        });

        // Pertinence : nom exact > début du nom > nom contenant > marque > catégorie > reste.
        $query->orderByRaw(
            'CASE '
            . 'WHEN LOWER(name) = ? THEN 0 '
            . 'WHEN LOWER(name) LIKE ? THEN 1 '
            . 'WHEN LOWER(name) LIKE ? THEN 2 '
            . (Schema::hasColumn('products', 'brand') ? 'WHEN LOWER(brand) LIKE ? THEN 3 ' : '')
            . 'ELSE 4 END',
            array_values(array_filter([
                $lower,
                $lower . '%',
                '%' . $lower . '%',
                Schema::hasColumn('products', 'brand') ? '%' . $lower . '%' : null,
            ], fn ($value) => $value !== null))
        );

        if (Schema::hasColumn('products', 'sales')) {
            $query->orderByDesc('sales');
        }

        return $query->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->serializeProduct($product));
    }

    private function categorySuggestions(string $term, int $limit): Collection
    {
        if (! Schema::hasTable('categories')) {
            return collect();
        }

        $lower = mb_strtolower($term);

        $query = Category::query()
            ->where(function (Builder $query) use ($lower) {
                $query->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$lower}%"]);
            });

        if (Schema::hasColumn('categories', 'status')) {
            $query->where(function (Builder $statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhereIn('status', ['actif', 'active', 'approved', '1', 1]);
            });
        }

        return $query
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 ELSE 2 END', [$lower, $lower . '%'])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'url' => route('catalog.index', ['category' => $category->slug]),
            ]);
    }

    private function serializeProduct(Product $product): array
    {
        $price = (float) ($product->final_price ?? $product->price ?? 0);
        $basePrice = (float) ($product->normal_public_price ?? $price);

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'image_url' => $product->main_image_url ?: $product->image_url,
            'price' => $price,
            'base_price' => $basePrice,
            'is_on_promo' => (bool) ($product->is_on_promo ?? ($price > 0 && $basePrice > $price)),
            'unit' => $product->unit_label ?: $product->unit ?: 'unité',
            'reviews_count' => (int) ($product->reviews_count ?? 0),
            'rating' => round((float) ($product->reviews_avg_rating ?? 0), 1),
            'category' => $product->category ? [
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'url' => route('product.show', $product),
        ];
    }

    private function normalize(string $term): string
    {
        return Str::of($term)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }
}
