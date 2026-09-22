        @if($promoList->isNotEmpty())
        <section class="ov-panel ov-feature-section">
            <div class="ov-feature-intro">
                <span class="ov-kicker">OFFRES EN COURS</span>
                <h2>Produits en promotion</h2>
                <p>Prix réduits sur une sélection de produits disponibles</p>
                <a href="{{ $catalogUrl }}?offer=promotion" class="ov-btn ov-btn--outline-dark">Voir toutes les offres</a>
            </div>

            <div class="ov-feature-products">
                @foreach($promoList as $product)
                    @php
                        $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
                        $productRating = $rating($product);
                        $productDiscount = $discount($product);
                    @endphp
                    <article class="ov-product-card ov-product-card--compact">
                        <a class="ov-product-card__image" href="{{ $productUrl($product) }}">
                            <img src="{{ $productImage($product) }}" alt="{{ $productName }}" loading="lazy">
                        </a>
                        <div class="ov-product-card__body">
                            <a href="{{ $productUrl($product) }}" class="ov-product-card__name">{{ $productName }}</a>
                            <div class="ov-feature-bottom">
                                <div>
                                    <strong>{{ $formatPrice($price($product)) }}</strong>
                                    @if($productDiscount)
                                        <span class="ov-promo-badge">-{{ $productDiscount }}%</span>
                                    @endif
                                    @if($productRating)
                                        <small><span class="ov-star">★</span> {{ $productRating[0] }} ({{ $productRating[1] }})</small>
                                    @endif
                                </div>
                                <a href="{{ $productUrl($product) }}" class="ov-plus-button" aria-label="Voir {{ $productName }}">+</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        @endif
