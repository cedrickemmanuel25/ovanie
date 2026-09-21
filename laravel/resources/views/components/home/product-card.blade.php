@props([
    'product',
    'favoriteProductIds' => [],
    'badge' => null,
    'compact' => false,
])

@php
    $currentPrice = (float) ($product->final_price ?? $product->display_price ?? $product->promo_price ?? $product->price ?? 0);
    $basePrice = (float) ($product->normal_public_price ?? $product->price ?? $currentPrice);
    $hasDiscount = $basePrice > 0 && $currentPrice > 0 && $currentPrice < $basePrice;
    $discountPercent = $hasDiscount ? (int) round((($basePrice - $currentPrice) / $basePrice) * 100) : null;
    $imageUrl = $product->card_image_url ?? $product->main_image_url ?? $product->image_url ?? asset('images/placeholder-product.png');
    $rating = round((float) ($product->reviews_avg_rating ?? 0), 1);
    $reviews = (int) ($product->reviews_count ?? 0);
    $unit = $product->display_unit ?? $product->unit_label ?? $product->unit ?? 'unité';
    $isFavorite = in_array((int) $product->id, array_map('intval', $favoriteProductIds), true);
    $productUrl = route('product.show', $product);
@endphp

<article class="ovh-product-card {{ $compact ? 'ovh-product-card--compact' : '' }}" data-product-id="{{ $product->id }}">
    <div class="ovh-product-card__media">
        <a href="{{ $productUrl }}" class="ovh-product-card__image-link" aria-label="Voir {{ $product->name }}">
            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
        </a>

        <div class="ovh-product-card__badges">
            @if($badge)
                <span class="ovh-product-badge ovh-product-badge--accent">{{ $badge }}</span>
            @elseif($discountPercent)
                <span class="ovh-product-badge ovh-product-badge--discount">-{{ $discountPercent }}%</span>
            @elseif($product->fast_delivery ?? false)
                <span class="ovh-product-badge ovh-product-badge--delivery">Livraison rapide</span>
            @endif
        </div>

        @auth
            <form method="POST" action="{{ route('favorites.toggle', $product) }}" class="ovh-favorite-form">
                @csrf
                <button type="submit" class="ovh-icon-button {{ $isFavorite ? 'is-active' : '' }}" aria-label="{{ $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}">
                    <i data-lucide="heart"></i>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="ovh-icon-button" aria-label="Se connecter pour ajouter aux favoris">
                <i data-lucide="heart"></i>
            </a>
        @endauth
    </div>

    <div class="ovh-product-card__body">
        @if($product->category)
            <a href="{{ route('catalog.index', ['category' => $product->category->slug]) }}" class="ovh-product-card__category">
                {{ $product->category->name }}
            </a>
        @endif

        <a href="{{ $productUrl }}" class="ovh-product-card__name">{{ $product->name }}</a>

        <div class="ovh-product-card__rating" aria-label="Note produit">
            <span class="ovh-stars">
                @for($i = 1; $i <= 5; $i++)
                    <i data-lucide="star" class="{{ $rating >= $i ? 'is-filled' : '' }}"></i>
                @endfor
            </span>
            <span>{{ $reviews > 0 ? $reviews : 'Nouveau' }}</span>
        </div>

        <div class="ovh-product-card__pricing">
            <strong>{{ number_format($currentPrice, 0, ',', ' ') }} FCFA</strong>
            <span class="ovh-product-card__unit">/ {{ $unit }}</span>
            @if($hasDiscount)
                <del>{{ number_format($basePrice, 0, ',', ' ') }} FCFA</del>
            @endif
        </div>

        <div class="ovh-product-card__actions">
            <a href="{{ $productUrl }}" class="ovh-button ovh-button--secondary ovh-button--card">
                Voir le produit
            </a>

            <form method="POST" action="{{ route('cart.add', $product) }}">
                @csrf
                <button type="submit" class="ovh-button ovh-button--cart" aria-label="Ajouter {{ $product->name }} au panier">
                    <i data-lucide="shopping-cart"></i>
                </button>
            </form>
        </div>
    </div>
</article>
