            @if($bestList->isNotEmpty())
            <section class="ov-panel ov-best-panel">
                <div class="ov-section-head">
                    <h2>Meilleures ventes</h2>
                    <a href="{{ $bestSellersUrl }}">Voir tout <span>→</span></a>
                </div>

                <div class="ov-best-list">
                    @foreach($bestList as $product)
                        @php
                            $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
                            $productRating = $rating($product);
                        @endphp
                        <a href="{{ $productUrl($product) }}" class="ov-best-item">
                            <span class="ov-best-item__image">
                                <img src="{{ $productImage($product) }}" alt="{{ $productName }}" loading="lazy">
                            </span>
                            <span class="ov-best-item__content">
                                <strong>{{ $productName }}</strong>
                                <b>{{ $formatPrice($price($product)) }}</b>
                                @if($productRating)
                                    <small><span class="ov-star">★</span> {{ $productRating[0] }} ({{ $productRating[1] }})</small>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
            @endif
