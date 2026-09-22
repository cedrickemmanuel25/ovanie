<?php

namespace App\Services;

use App\Models\Product;

/**
 * Représentation canonique d'une fiche produit OVANIE.
 *
 * Le Web public, l'application Client et l'application Vendeur utilisent ce
 * même contrat pour éviter les écarts de prix, d'unité, de disponibilité et
 * de caractéristiques. Aucune donnée d'identité boutique/vendeur n'est
 * exposée dans ce contrat.
 */
class ProductSheetPresenter
{
    public function present(Product $product, bool $detailed = true): array
    {
        $stock = max(0, (int) ($product->stock ?? 0));
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $availabilityStatus = (string) ($product->availability_status ?: ($stock > 0 ? 'in_stock' : 'out_of_stock'));
        $orderable = in_array($availabilityStatus, ['on_order', 'preorder'], true);
        $canAddToCart = ! $orderable && $availabilityStatus !== 'out_of_stock' && $stock >= $minimum;

        $publicPrice = max(0, (int) round((float) ($product->final_price ?? $product->price ?? 0)));
        $regularPublicPrice = max($publicPrice, (int) round((float) ($product->normal_public_price ?? $publicPrice)));
        $publicPromoPrice = $regularPublicPrice > $publicPrice ? $publicPrice : null;
        $discountPercent = $publicPromoPrice !== null && $regularPublicPrice > 0
            ? max(1, (int) round((($regularPublicPrice - $publicPrice) / $regularPublicPrice) * 100))
            : 0;

        $rating = $product->reviews_avg_rating;
        $reviewsCount = $product->reviews_count;
        if ($rating === null && $product->relationLoaded('reviews') && $product->reviews->isNotEmpty()) {
            $rating = $product->reviews->avg('rating');
        }
        if ($reviewsCount === null && $product->relationLoaded('reviews')) {
            $reviewsCount = $product->reviews->count();
        }

        $category = $product->category;
        $parent = $category?->relationLoaded('parent') ? $category->parent : null;
        $unit = (string) ($product->display_unit ?? $product->unit_label ?? $product->unit ?? 'unité');
        $weight = (float) ($product->weight_kg ?? $product->weight ?? 0);

        $payload = [
            'id' => (int) $product->id,
            'slug' => (string) ($product->slug ?? ''),
            'reference' => (string) ($product->sku ?? ''),
            'name' => ovanie_public_text((string) $product->name, (string) $product->slug),
            'short_description' => ovanie_public_text((string) ($product->short_description ?? '')),
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'category' => $category ? [
                'id' => (int) $category->id,
                'name' => ovanie_public_text((string) $category->name, (string) $category->slug),
                'slug' => (string) ($category->slug ?? ''),
                'parent' => $parent ? [
                    'id' => (int) $parent->id,
                    'name' => ovanie_public_text((string) $parent->name, (string) $parent->slug),
                    'slug' => (string) ($parent->slug ?? ''),
                ] : null,
            ] : null,

            // Prix publics canoniques. Le prix vendeur n'est volontairement pas ici.
            'public_price' => $publicPrice,
            'regular_public_price' => $regularPublicPrice,
            'public_promo_price' => $publicPromoPrice,
            'discount_percent' => $discountPercent,

            // Le client doit savoir qu'il peut négocier, mais jamais voir les
            // seuils vendeur (price_p1/p2/p3) : voir NegotiationController::store().
            'is_negotiable' => (bool) ($product->is_negotiable ?? false),

            'unit' => (string) ($product->unit ?? ''),
            'unit_label' => $unit,
            'display_unit' => $unit,
            'stock' => $stock,
            'stock_label' => (string) $product->stock_label,
            'min_order_quantity' => $minimum,
            'availability_status' => $availabilityStatus,
            'availability_label' => (string) $product->availability_label,
            'can_add_to_cart' => $canAddToCart,
            'is_orderable' => $orderable,
            'product_state' => (string) ($product->product_state ?? 'new'),
            'product_state_label' => (string) $product->product_state_label,
            'supply_delay' => ovanie_public_text((string) ($product->supply_delay ?? '')),

            'brand' => ovanie_public_text((string) ($product->brand ?? '')),
            'packaging' => ovanie_public_text((string) ($product->packaging ?? '')),
            'content_per_unit' => $product->content_per_unit,
            'content_unit' => ovanie_public_text((string) ($product->content_unit ?? '')),
            'content_label' => ovanie_public_text((string) ($product->content_label ?? '')),
            'units_per_package' => $product->units_per_package,
            'coverage_per_unit_m2' => (float) ($product->coverage_per_unit_m2 ?? 0),
            'usage_area' => ovanie_public_text((string) ($product->usage_area ?? '')),
            'material_grade' => ovanie_public_text((string) ($product->material_grade ?? '')),
            'color' => ovanie_public_text((string) ($product->color ?? '')),
            'standard' => ovanie_public_text((string) ($product->standard ?? '')),
            'origin_country' => ovanie_public_text((string) ($product->origin_country ?? '')),
            'weight_kg' => $weight,
            'length_cm' => (float) ($product->length_cm ?? 0),
            'width_cm' => (float) ($product->width_cm ?? 0),
            'height_cm' => (float) ($product->height_cm ?? 0),
            'dimensions_label' => ovanie_public_text((string) ($product->dimensions_label ?? '')),
            'volume_m3' => (float) ($product->volume_m3 ?? 0),
            'fragile' => (bool) ($product->fragile ?? false),
            'requires_unloading' => (bool) ($product->requires_unloading ?? false),
            'unloading_instructions' => ovanie_public_text((string) ($product->unloading_instructions ?? '')),

            'rating' => $rating !== null ? round((float) $rating, 1) : null,
            'average_rating' => $rating !== null ? round((float) $rating, 1) : null,
            'reviews_count' => (int) ($reviewsCount ?? 0),
            'sales' => (int) ($product->sales ?? 0),

            'main_image_url' => (string) ($product->main_image_url ?? ''),
            'card_image_url' => (string) ($product->card_image_url ?? ''),
            'thumb_image_url' => (string) ($product->thumb_image_url ?? ''),
            'gallery' => collect($product->gallery_urls ?? [])->filter()->values()->all(),
            'images' => $this->images($product),
        ];

        if (! $detailed) {
            return $payload;
        }

        return array_merge($payload, [
            'description' => ovanie_public_text((string) ($product->description ?? '')),
            'technical_details' => ovanie_public_text((string) ($product->technical_details ?? '')),
            'product_attributes' => $this->attributes($product),
            'technical_specs' => $this->technicalSpecs($product),
            'warranty' => ovanie_public_text((string) ($product->warranty ?? '')),
            'return_policy' => ovanie_public_text((string) ($product->return_policy ?? '')),
            'reviews' => $this->reviews($product),
        ]);
    }

    private function images(Product $product): array
    {
        if (! $product->relationLoaded('images')) {
            return [];
        }

        return $product->images
            ->sortBy(fn ($image) => [
                ! (bool) ($image->is_main ?? $image->is_primary ?? false),
                (int) ($image->sort_order ?? 0),
                (int) $image->id,
            ])
            ->map(fn ($image) => [
                'id' => (int) $image->id,
                'url' => (string) ($image->public_url ?? $image->url ?? ''),
                'card_url' => (string) ($image->card_url ?? $image->public_url ?? ''),
                'thumb_url' => (string) ($image->thumb_url ?? $image->card_url ?? $image->public_url ?? ''),
                'is_main' => (bool) ($image->is_main ?? $image->is_primary ?? false),
            ])
            ->values()
            ->all();
    }

    private function attributes(Product $product): array
    {
        $raw = is_array($product->product_attributes ?? null) ? $product->product_attributes : [];
        $result = [];

        foreach ($raw as $key => $attribute) {
            if (is_array($attribute)) {
                $label = trim((string) ($attribute['label'] ?? $attribute['name'] ?? (is_string($key) ? $key : '')));
                $value = trim((string) ($attribute['value'] ?? ''));
                $unit = trim((string) ($attribute['unit'] ?? ''));
                if ($label !== '' && $value !== '') {
                    $result[$label] = trim($value . ' ' . $unit);
                }
                continue;
            }

            $label = trim((string) $key);
            $value = trim((string) $attribute);
            if ($label !== '' && $value !== '') {
                $result[$label] = $value;
            }
        }

        return $result;
    }

    private function technicalSpecs(Product $product): array
    {
        $specs = collect($product->btp_technical_specs ?? [])
            ->filter(fn ($value) => filled($value))
            ->mapWithKeys(fn ($value, $label) => [
                ovanie_public_text((string) $label) => ovanie_public_text((string) $value),
            ]);

        foreach ($this->attributes($product) as $label => $value) {
            if (! $specs->has($label)) {
                $specs->put(ovanie_public_text((string) $label), ovanie_public_text((string) $value));
            }
        }

        return $specs->all();
    }

    private function reviews(Product $product): array
    {
        if (! $product->relationLoaded('reviews')) {
            return [];
        }

        return $product->reviews->map(fn ($review) => [
            'id' => (int) $review->id,
            'rating' => (int) ($review->rating ?? 0),
            'comment' => ovanie_public_text((string) ($review->comment ?? $review->content ?? '')),
            'created_at' => optional($review->created_at)->toDateTimeString(),
            'user' => $review->relationLoaded('user') && $review->user
                ? ['name' => ovanie_public_text((string) $review->user->name)]
                : null,
        ])->values()->all();
    }
}
