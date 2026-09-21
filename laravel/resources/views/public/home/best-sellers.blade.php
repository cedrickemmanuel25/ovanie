            @if($bestList->isNotEmpty())
            <section class="ov-panel ov-best-panel">
                <div class="ov-section-head">
                    <h2>Meilleures ventes</h2>
                    <a href="{{ $bestSellersUrl }}">Voir tout <span>→</span></a>
                </div>

                <div class="ov-product-grid ov-product-grid--3">
                    @foreach($bestList as $product)
                        @php
                            $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
                            $productRating = $rating($product);
                            $productDiscount = $discount($product);
                        @endphp
                        <article class="ov-product-card">
                            <a class="ov-product-card__image" href="{{ $productUrl($product) }}">
                                <img src="{{ $productImage($product) }}" alt="{{ $productName }}" loading="lazy">
                            </a>
                            <div class="ov-product-card__body">
                                <a href="{{ $productUrl($product) }}" class="ov-product-card__name">{{ $productName }}</a>
                                <div class="ov-product-price">
                                    <strong>{{ $formatPrice($price($product)) }}</strong>
                                    @if($productDiscount)
                                        <span>-{{ $productDiscount }}%</span>
                                    @endif
                                </div>
                                @if($productRating)
                                    <small class="ov-product-card__rating"><span class="ov-star">★</span> {{ $productRating[0] }} ({{ $productRating[1] }})</small>
                                @endif
                                <a href="{{ $productUrl($product) }}" class="ov-product-card__cta">Voir le produit</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            @endif
