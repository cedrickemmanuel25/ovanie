            @if($newArrivalsList->isNotEmpty())
            <section class="ov-panel ov-flash-panel">
                <div class="ov-section-head">
                    <div class="ov-section-head__title">
                        <h2>Nouveautés</h2>
                        <small>Dernières références ajoutées</small>
                    </div>
                    <a href="{{ $newArrivalsUrl }}">Voir tout <span>→</span></a>
                </div>

                <div class="ov-product-grid ov-product-grid--3">
                    @foreach($newArrivalsList as $product)
                        @php
                            $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
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
                                @if($productDiscount)
                                    <small class="ov-old-price">{{ $formatPrice((float) ($product->normal_public_price ?? $product->price)) }}</small>
                                @endif
                                <a href="{{ $productUrl($product) }}" class="ov-product-card__cta">Voir le produit</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
            @endif
