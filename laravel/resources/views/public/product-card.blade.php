@php
    $favoriteProductIds = $favoriteProductIds ?? [];
    $isFavorite = in_array((int) $product->id, array_map('intval', $favoriteProductIds), true);

    $basePrice = (float) ($product->price ?? 0);
    $promoPrice = (float) ($product->promo_price ?? 0);
    $currentPrice = $promoPrice > 0 && $promoPrice < $basePrice
        ? $promoPrice
        : (float) ($product->final_price ?? $product->display_price ?? $basePrice);

    $oldPrice = $promoPrice > 0 && $promoPrice < $basePrice ? $basePrice : null;
    $discount = $oldPrice ? max(1, round((1 - ($currentPrice / max($oldPrice, 1))) * 100)) : null;
    $badgeText = $badge ?? ($discount ? '-' . $discount . '%' : 'Nouveau');

    $imageUrl = $product->main_image_url
        ?? collect($product->gallery_urls ?? [])->first()
        ?? asset('images/product-placeholder.png');

    $compact = $compact ?? false;
    $dark = $dark ?? false;
    $productUrl = route('product.show', $product);
    $stock = (int) ($product->stock ?? 0);
    $unit = $product->display_unit ?? $product->unit_label_display ?? $product->unit ?? 'unité';
    $isNegotiable = (bool) ($product->is_negotiable ?? false);
@endphp

<article class="hm-card {{ $compact ? 'hm-card--compact' : '' }} {{ $dark ? 'hm-card--dark' : '' }} {{ $isNegotiable ? 'hm-card--negotiable' : '' }}">
    <a class="hm-card__image" href="{{ $productUrl }}" aria-label="{{ $product->name }}">
        @if($badgeText || $isNegotiable)
            <div class="hm-card__badges" aria-label="Badges produit">
                @if($badgeText)
                    <span class="hm-card__badge hm-card__badge--main">{{ $badgeText }}</span>
                @endif

            </div>
        @endif

        <img
            src="{{ $imageUrl }}"
            alt="{{ $product->name }}"
            loading="lazy"
            onerror="this.onerror=null; this.src='{{ asset('images/product-placeholder.png') }}';"
        >
    </a>

    <button
        type="button"
        class="hm-card__heart {{ $isFavorite ? 'is-active' : '' }}"
        data-favorite-button
        data-product-id="{{ $product->id }}"
        data-favorite-url="{{ auth()->check() ? route('client.favorites.toggle', $product) : route('login') }}"
        aria-label="{{ $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.8 4.6a5.4 5.4 0 0 0-7.6 0L12 5.8l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 21l8.8-8.8a5.4 5.4 0 0 0 0-7.6Z"/>
        </svg>
    </button>

    <div class="hm-card__body">
        <a class="hm-card__title" href="{{ $productUrl }}">
            {{ \Illuminate\Support\Str::limit($product->name, $compact ? 24 : 36) }}
        </a>

        @if($product->category)
            <div class="hm-card__category">{{ strtoupper($product->category->name ?? '') }}</div>
        @endif


        <div class="hm-card__rating" aria-label="Note client 5 étoiles">
            @for($i = 0; $i < 5; $i++)
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m10 1.6 2.4 5 5.5.8-4 3.9.9 5.5L10 14.2l-4.9 2.6.9-5.5-4-3.9 5.5-.8L10 1.6Z"/></svg>
            @endfor
            <small>({{ (int) ($product->reviews_count ?? 0) }})</small>
        </div>

        <div class="hm-card__prices">
            <strong>{{ number_format($currentPrice, 0, ',', ' ') }} FCFA</strong>
            <span>/ {{ $unit }}</span>
            @if($oldPrice)
                <del>{{ number_format($oldPrice, 0, ',', ' ') }} FCFA</del>
            @endif
        </div>

        <button
            type="button"
            class="hm-card__cart"
            data-cart-button
            data-product-id="{{ $product->id }}"
            data-cart-url="{{ route('cart.add', $product->id) }}"
            {{ $stock < 1 ? 'disabled' : '' }}
        >
            {{ $stock < 1 ? 'Indisponible' : 'Ajouter au panier' }}
        </button>
    </div>
</article>
