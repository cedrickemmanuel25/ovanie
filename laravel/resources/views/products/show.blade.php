@extends('layouts.guest')

@section('title', data_get($productSheet, 'name', 'Produit OVANIE') . ' - OVANIE')
@section('meta_description', \Illuminate\Support\Str::limit(data_get($productSheet, 'short_description') ?: strip_tags(data_get($productSheet, 'description', '')), 155))

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/product.css') }}?v={{ file_exists(public_path('css/product.css')) ? filemtime(public_path('css/product.css')) : time() }}">
@endsection

@php
    $sheet = collect($productSheet ?? []);
    $name = (string) $sheet->get('name', 'Produit OVANIE');
    $description = (string) $sheet->get('description', '');
    $shortDescription = (string) $sheet->get('short_description', '');
    $unit = (string) $sheet->get('display_unit', 'unité');
    $price = (float) $sheet->get('public_price', 0);
    $regularPrice = (float) $sheet->get('regular_public_price', $price);
    $stock = max(0, (int) $sheet->get('stock', 0));
    $minimum = max(1, (int) $sheet->get('min_order_quantity', 1));
    $availabilityStatus = (string) $sheet->get('availability_status', 'out_of_stock');
    $availabilityLabel = (string) $sheet->get('availability_label', 'Indisponible');
    $canAddToCart = (bool) $sheet->get('can_add_to_cart', false);
    $isOrderable = (bool) $sheet->get('is_orderable', false);
    $isNegotiable = (bool) $sheet->get('is_negotiable', false);
    $rating = $sheet->get('rating');
    $reviewsCount = (int) $sheet->get('reviews_count', 0);
    $category = data_get($productSheet, 'category');
    $parentCategory = data_get($productSheet, 'category.parent');
    $technicalSpecs = collect($sheet->get('technical_specs', []))->filter(fn ($value) => filled($value));
    $images = collect($sheet->get('images', []))
        ->pluck('url')
        ->merge($sheet->get('gallery', []))
        ->prepend($sheet->get('main_image_url'))
        ->filter()
        ->unique()
        ->values();
    if ($images->isEmpty()) $images = collect([asset('images/product-placeholder.svg')]);

    $fmtMoney = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' FCFA';
    $homeUrl = Route::has('home') ? route('home') : url('/');
    $catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $availabilityClass = match ($availabilityStatus) {
        'on_order' => 'is-order',
        'preorder' => 'is-preorder',
        'out_of_stock' => 'is-out',
        default => 'is-stock',
    };
    $availabilityIcon = match ($availabilityStatus) {
        'on_order' => 'clock-3',
        'preorder' => 'calendar-clock',
        'out_of_stock' => 'circle-x',
        default => 'circle-check',
    };

    $badge = null;
    if ((int) $sheet->get('discount_percent', 0) > 0) {
        $badge = 'Promo -' . (int) $sheet->get('discount_percent') . '%';
    } elseif ((int) $sheet->get('sales', 0) >= 5) {
        $badge = 'Meilleure vente';
    }

    $quickFacts = collect([
        'État' => $sheet->get('product_state_label', 'Neuf'),
        'Disponibilité' => $availabilityLabel,
        'Unité de vente' => $unit,
        'Quantité minimum' => $minimum . ' ' . $unit,
        'Conditionnement' => $sheet->get('packaging'),
        'Stock disponible' => $stock > 0 ? number_format($stock, 0, ',', ' ') . ' ' . $unit : $availabilityLabel,
        'Poids' => (float) $sheet->get('weight_kg', 0) > 0 ? number_format((float) $sheet->get('weight_kg'), 2, ',', ' ') . ' kg' : null,
        'Marque' => $sheet->get('brand'),
        'Origine' => $sheet->get('origin_country'),
        'Couleur' => $sheet->get('color'),
        'Utilisation' => $sheet->get('usage_area'),
    ])->filter(fn ($value) => filled($value));

    $similarCards = collect($similarProducts ?? [])->map(function ($item) use ($catalogUrl) {
        return [
            'id' => $item->id,
            'name' => ovanie_public_text((string) $item->name, (string) $item->slug),
            'url' => $item->slug && Route::has('product.show') ? route('product.show', $item->slug) : $catalogUrl,
            'image' => $item->card_image_url ?: $item->main_image_url ?: asset('images/product-placeholder.svg'),
            'price' => (float) ($item->final_price ?? $item->price ?? 0),
            'unit' => $item->display_unit ?? 'unité',
        ];
    })->take(6)->values();

    $calc = collect($calculatorProfile ?? []);
@endphp

@section('content')
<section class="ov-product-page"
    data-product-page
    data-product-id="{{ $product->id }}"
    data-product-price="{{ (int) round($price) }}"
    data-product-unit="{{ $unit }}"
    data-product-min-qty="{{ $minimum }}"
    data-product-stock="{{ $stock }}"
    data-availability-status="{{ $availabilityStatus }}"
    data-can-add-to-cart="{{ $canAddToCart ? '1' : '0' }}"
    data-is-negotiable="{{ $isNegotiable ? '1' : '0' }}"
    data-negotiate-url="{{ Route::has('product.negotiate') ? route('product.negotiate', $product->id) : '' }}"
    data-cart-add-negotiated-url="{{ Route::has('cart.addNegotiated') ? route('cart.addNegotiated') : '' }}"
    data-login-url="{{ route('login') }}"
    data-coverage-per-unit-m2="{{ $calc->get('coverage_per_unit_m2', 0) }}"
    data-unit-volume-m3="{{ $calc->get('unit_volume_m3', 0) }}"
    data-unit-weight-kg="{{ $calc->get('unit_weight_kg', 0) }}"
    data-density-kg-m3="{{ $calc->get('density_kg_m3', 0) }}"
    data-unit-length-m="{{ $calc->get('unit_length_m', 0) }}">

    <div class="ov-product-container">
        <nav class="ov-product-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ $homeUrl }}">Accueil</a><i data-lucide="chevron-right"></i>
            @if($parentCategory)
                <a href="{{ route('catalog.index', data_get($parentCategory, 'slug')) }}">{{ data_get($parentCategory, 'name') }}</a><i data-lucide="chevron-right"></i>
            @endif
            @if($category)
                <a href="{{ route('catalog.index', data_get($category, 'slug')) }}">{{ data_get($category, 'name') }}</a><i data-lucide="chevron-right"></i>
            @endif
            <span>{{ $name }}</span>
        </nav>

        <section class="ov-product-hero">
            <article class="ov-product-gallery-panel">
                <div class="ov-product-thumbs" aria-label="Images du produit">
                    @foreach($images as $index => $imageUrl)
                        <button type="button" class="ov-product-thumb {{ $index === 0 ? 'is-active' : '' }} {{ $index >= 5 ? 'is-extra' : '' }}" data-product-thumb data-image="{{ $imageUrl }}" aria-label="Voir image {{ $index + 1 }}">
                            <img src="{{ $imageUrl }}" alt="{{ $name }} miniature {{ $index + 1 }}">
                        </button>
                    @endforeach
                    @if($images->count() > 5)
                        <button type="button" class="ov-product-thumb-more" data-show-more-thumbs><strong>+{{ $images->count() - 5 }}</strong><span>Voir plus</span></button>
                    @endif
                </div>

                <div class="ov-product-image-stage">
                    @if($badge)<span class="ov-product-badge">{{ $badge }}</span>@endif
                    <button type="button" class="ov-product-gallery-nav ov-product-gallery-nav--prev" data-gallery-prev aria-label="Image précédente"><i data-lucide="chevron-left"></i></button>
                    <img id="mainProductImage" class="ov-product-main-image" src="{{ $images->first() }}" alt="{{ $name }}">
                    <button type="button" class="ov-product-gallery-nav ov-product-gallery-nav--next" data-gallery-next aria-label="Image suivante"><i data-lucide="chevron-right"></i></button>
                    <button type="button" class="ov-product-favorite {{ ($isFavorite ?? false) ? 'is-active' : '' }}" data-favorite-button data-favorite-url="{{ auth()->check() ? route('client.favorites.toggle', $product) : route('login') }}" aria-label="Ajouter aux favoris"><i data-lucide="heart"></i></button>
                </div>
            </article>

            <article class="ov-product-summary">
                <div class="ov-product-summary__status"><span class="ov-product-status {{ $availabilityClass }}"><i data-lucide="{{ $availabilityIcon }}"></i>{{ $availabilityLabel }}</span>@if($sheet->get('reference'))<small>Réf. {{ $sheet->get('reference') }}</small>@endif</div>
                <h1>{{ $name }}</h1>
                <div class="ov-product-rating-row">
                    <span class="ov-stars">★★★★★</span>
                    <strong>{{ $rating !== null ? number_format((float) $rating, 1, ',', ' ') : '—' }}</strong>
                    <a href="#productReviews">({{ $reviewsCount }} avis)</a>
                </div>

                <div class="ov-product-summary__price">
                    <strong>{{ $fmtMoney($price) }}</strong><span>/ {{ $unit }}</span>
                    @if($regularPrice > $price)<del>{{ $fmtMoney($regularPrice) }}</del>@endif
                    @if($isNegotiable)<span class="ov-negotiable-badge"><i data-lucide="handshake"></i>Prix négociable</span>@endif
                </div>

                @if($shortDescription)
                    <p class="ov-product-short-description">{{ $shortDescription }}</p>
                @endif

                @if($sheet->get('brand') || $sheet->get('origin_country'))
                    <p class="ov-product-meta-line">
                        @if($sheet->get('brand'))<span>Marque : <strong>{{ $sheet->get('brand') }}</strong></span>@endif
                        @if($sheet->get('origin_country'))<span>Origine : <strong>{{ $sheet->get('origin_country') }}</strong></span>@endif
                    </p>
                @endif

                <div class="ov-product-quality-grid">
                    <div><i data-lucide="shield-check"></i><span><strong>Produit vérifié</strong><small>Contrôle OVANIE</small></span></div>
                    <div><i data-lucide="badge-check"></i><span><strong>Qualité professionnelle</strong><small>Informations produit centralisées</small></span></div>
                    <div><i data-lucide="refresh-cw"></i><span><strong>Données synchronisées</strong><small>Web & applications</small></span></div>
                </div>

                <dl class="ov-product-quick-facts">
                    @foreach($quickFacts as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <button type="button" class="ov-product-tech-link" data-tab-jump="technical">Voir toutes les caractéristiques <i data-lucide="chevron-down"></i></button>
            </article>

            <aside class="ov-product-buybox">
                <div class="ov-buybox-price"><strong>{{ $fmtMoney($price) }}</strong><span>/ {{ $unit }}</span></div>
                @if($regularPrice > $price)<div class="ov-buybox-old-price">au lieu de {{ $fmtMoney($regularPrice) }}</div>@endif
                <div class="ov-buybox-stock"><span class="ov-product-status {{ $availabilityClass }}"><i data-lucide="{{ $availabilityIcon }}"></i>{{ $availabilityLabel }}</span></div>

                <div class="ov-buybox-delivery"><i data-lucide="map-pin"></i><div><strong>Livraison selon votre adresse</strong><span>Frais et délai calculés avant validation de la commande.</span></div></div>

                @if($isNegotiable && $canAddToCart)
                    <div class="ov-negotiate-box" data-negotiate-box hidden>
                        <div class="ov-negotiate-head">
                            <i data-lucide="handshake"></i>
                            <div><strong>Proposez votre prix</strong><span>OVANIE l’accepte automatiquement s’il convient au vendeur.</span></div>
                        </div>
                        <div class="ov-negotiate-step" data-negotiate-step-label>Offre 1 sur 3</div>
                        <button type="button" class="ov-negotiate-submit" data-negotiate-submit></button>
                        <p class="ov-negotiate-message" data-negotiate-message hidden></p>
                        <button type="button" class="ov-product-btn ov-product-btn--cart" data-negotiate-add-to-cart hidden><i data-lucide="shopping-cart"></i>Ajouter au panier à ce prix</button>
                    </div>
                @endif

                @if($canAddToCart)
                    <form method="POST" action="{{ Route::has('cart.add') ? route('cart.add', $product->id) : '#' }}" class="ov-product-cart-form" data-product-form>
                        @csrf
                        <label class="ov-buybox-qty-label">Quantité</label>
                        <div class="ov-product-qty-row">
                            <button type="button" data-qty-minus aria-label="Diminuer">−</button>
                            <input type="number" name="quantity" value="{{ $minimum }}" min="{{ $minimum }}" max="{{ max($stock, $minimum) }}" step="1" data-qty-input>
                            <button type="button" data-qty-plus aria-label="Augmenter">+</button>
                            <span>{{ $unit }}</span>
                        </div>
                        <button type="submit" class="ov-product-btn ov-product-btn--cart"><i data-lucide="shopping-cart"></i>Ajouter au panier</button>
                        <button type="submit" name="action_type" value="buy_now" class="ov-product-btn ov-product-btn--buy">Acheter maintenant</button>
                    </form>
                @elseif($isOrderable)
                    <div class="ov-product-order-box"><i data-lucide="clipboard-list"></i><div><strong>{{ $availabilityLabel }}</strong><p>Le délai est confirmé avant validation.</p></div></div>
                @else
                    <div class="ov-product-order-box is-out"><i data-lucide="circle-x"></i><div><strong>Produit indisponible</strong><p>Consultez les alternatives de la même catégorie.</p></div></div>
                @endif

                @if($isNegotiable && $canAddToCart)
                    <button type="button" class="ov-product-btn ov-product-btn--quote" data-negotiate-trigger><i data-lucide="handshake"></i>Négocier</button>
                @endif

                <ul class="ov-buybox-promises">
                    <li><i data-lucide="shield-check"></i>Paiement sécurisé</li>
                    <li><i data-lucide="truck"></i>Livraison partout en Côte d’Ivoire</li>
                    <li><i data-lucide="package-check"></i>Retours selon les conditions OVANIE</li>
                    <li><i data-lucide="headphones"></i>Assistance OVANIE</li>
                </ul>
            </aside>
        </section>

        <section class="ov-product-tools-grid">
            <article class="ov-product-tool-card" id="productCalculator">
                <div class="ov-tool-card-head"><div><h2>Calculateur de besoins</h2><p>Estimez la quantité adaptée à votre chantier.</p></div><i data-lucide="calculator"></i></div>
                <div class="ov-calc-grid">
                    <label>Type de calcul<select data-calc-type><option value="surface">Surface (m²)</option><option value="volume">Volume (m³)</option><option value="linear">Longueur (m)</option><option value="piece">Nombre d’unités</option></select></label>
                    <label data-calc-field="length">Longueur (m)<input type="number" min="0" step="0.01" data-calc-length></label>
                    <label data-calc-field="width">Largeur (m)<input type="number" min="0" step="0.01" data-calc-width></label>
                    <label data-calc-field="thickness">Épaisseur / hauteur (cm)<input type="number" min="0" step="0.1" data-calc-thickness></label>
                    <label data-calc-field="pieces">Nombre d’unités<input type="number" min="0" step="1" data-calc-pieces></label>
                    <label>Marge de sécurité (%)<input type="number" min="0" step="1" value="5" data-calc-waste></label>
                </div>
                <div class="ov-calc-results">
                    <div><span>Quantité nécessaire</span><strong data-calc-qty>—</strong></div>
                    <div><span>Budget estimé</span><strong data-calc-budget>—</strong></div>
                    <div><span>Disponibilité</span><strong data-calc-stock>—</strong></div>
                </div>
                <p class="ov-calc-message" data-calc-message>Renseignez vos dimensions puis lancez le calcul.</p>
                <div class="ov-tool-actions"><button type="button" class="ov-calc-button" data-calc-button>Calculer</button><button type="button" class="ov-calc-apply" data-calc-apply disabled>Utiliser cette quantité</button></div>
            </article>

            <article class="ov-product-tool-card">
                <div class="ov-tool-card-head"><div><h2>Options de livraison</h2><p>Les informations définitives dépendent de l’adresse de livraison.</p></div><i data-lucide="truck"></i></div>
                <div class="ov-delivery-summary-list">
                    <div><i data-lucide="map-pin"></i><span><strong>Adresse de destination</strong><small>Renseignée au checkout</small></span></div>
                    <div><i data-lucide="wallet-cards"></i><span><strong>Frais de livraison</strong><small>Calculés avant le paiement</small></span></div>
                    <div><i data-lucide="clock-3"></i><span><strong>Délai estimé</strong><small>Confirmé avant validation</small></span></div>
                    @if((float) $sheet->get('weight_kg', 0) > 0)<div><i data-lucide="weight"></i><span><strong>Poids unitaire</strong><small>{{ number_format((float) $sheet->get('weight_kg'), 2, ',', ' ') }} kg</small></span></div>@endif
                </div>
                <a href="{{ route('delivery.info') }}" class="ov-delivery-more">Voir les informations de livraison <i data-lucide="chevron-right"></i></a>
            </article>
        </section>

        <section class="ov-product-tabs-card">
            <div class="ov-product-tabs" role="tablist">
                <button type="button" class="is-active" data-tab-button="description">Description</button>
                <button type="button" data-tab-button="technical">Fiche technique</button>
                <button type="button" data-tab-button="reviews">Avis clients ({{ $reviewsCount }})</button>
                <button type="button" data-tab-button="delivery">Livraison & retours</button>
            </div>

            <div class="ov-tab-panel is-active" data-tab-panel="description">
                <div class="ov-tab-two-columns">
                    <div>
                        <h2>Description du produit</h2>
                        <div class="ov-product-description">{{ $description ?: ($shortDescription ?: 'Aucune description détaillée n’a encore été ajoutée pour ce produit.') }}</div>
                    </div>
                    <div>
                        <h2>Informations essentielles</h2>
                        <dl class="ov-product-quick-facts is-tab">
                            <div><dt>État</dt><dd>{{ $sheet->get('product_state_label', 'Neuf') }}</dd></div>
                            <div><dt>Unité de vente</dt><dd>{{ $unit }}</dd></div>
                            <div><dt>Quantité minimum</dt><dd>{{ $minimum }} {{ $unit }}</dd></div>
                            @if($sheet->get('brand'))<div><dt>Marque</dt><dd>{{ $sheet->get('brand') }}</dd></div>@endif
                            @if($sheet->get('packaging'))<div><dt>Conditionnement</dt><dd>{{ $sheet->get('packaging') }}</dd></div>@endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="ov-tab-panel" data-tab-panel="technical">
                <h2>Caractéristiques techniques</h2>
                @if($sheet->get('technical_details'))<p class="ov-tab-intro">{{ $sheet->get('technical_details') }}</p>@endif
                <div class="ov-full-spec-list">
                    @forelse($technicalSpecs as $label => $value)
                        <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                    @empty
                        <p>Aucune caractéristique technique supplémentaire n’est disponible.</p>
                    @endforelse
                </div>
                @if($product->technical_sheet_url)<a href="{{ $product->technical_sheet_url }}" target="_blank" rel="noopener" class="ov-file-link"><i data-lucide="file-down"></i>Télécharger la fiche technique</a>@endif
            </div>

            <div class="ov-tab-panel" data-tab-panel="reviews" id="productReviews">
                <h2>Avis clients</h2>
                @if($reviewsCount > 0 && $rating !== null)
                    <div class="ov-review-summary-inline"><strong>{{ number_format((float) $rating, 1, ',', ' ') }}</strong><span class="ov-stars">★★★★★</span><span>{{ $reviewsCount }} avis</span></div>
                    <div class="ov-reviews-list">
                        @foreach(collect($sheet->get('reviews', []))->take(4) as $review)
                            <article><div><span class="ov-stars">{{ str_repeat('★', (int) data_get($review, 'rating', 0)) }}{{ str_repeat('☆', max(0, 5 - (int) data_get($review, 'rating', 0))) }}</span><strong>Achat vérifié</strong></div><p>{{ data_get($review, 'comment') ?: 'Avis client sans commentaire.' }}</p><small>{{ data_get($review, 'user.name', 'Client OVANIE') }}</small></article>
                        @endforeach
                    </div>
                @else
                    <p>Ce produit n’a pas encore reçu d’avis client.</p>
                @endif
            </div>

            <div class="ov-tab-panel" data-tab-panel="delivery">
                <h2>Livraison & retours</h2>
                <p>Le délai estimé et les frais applicables sont calculés à partir de l’adresse de livraison renseignée lors du checkout, puis affichés avant la validation de la commande.</p>
                <ul class="ov-check-list">
                    <li><i data-lucide="check"></i>Frais de livraison visibles avant paiement</li>
                    <li><i data-lucide="check"></i>Délai confirmé avant validation</li>
                    <li><i data-lucide="check"></i>Suivi de commande assuré par OVANIE</li>
                </ul>
                @if($sheet->get('return_policy'))<p><strong>Politique de retour :</strong> {{ $sheet->get('return_policy') }}</p>@endif
                @if($sheet->get('warranty'))<p><strong>Garantie :</strong> {{ $sheet->get('warranty') }}</p>@endif
            </div>
        </section>

        @if($similarCards->isNotEmpty())
            <section class="ov-similar-products">
                <div class="ov-similar-head"><h2>Produits similaires</h2><a href="{{ $category ? route('catalog.index', data_get($category, 'slug')) : $catalogUrl }}">Voir tout <i data-lucide="chevron-right"></i></a></div>
                <div class="ov-similar-grid">
                    @foreach($similarCards as $item)
                        <a href="{{ $item['url'] }}" class="ov-similar-card">
                            <div class="ov-similar-card__image"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"></div>
                            <strong class="ov-similar-card__name">{{ $item['name'] }}</strong>
                            <span class="ov-similar-card__price">{{ $fmtMoney($item['price']) }} <small>/ {{ $item['unit'] }}</small></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ov-product-trust-strip" aria-label="Services OVANIE">
            <div><i data-lucide="shield-check"></i><span><strong>Produit vérifié</strong><small>Informations contrôlées</small></span></div>
            <div><i data-lucide="lock-keyhole"></i><span><strong>Paiement sécurisé</strong><small>Transactions protégées</small></span></div>
            <div><i data-lucide="truck"></i><span><strong>Livraison suivie</strong><small>Partout en Côte d’Ivoire</small></span></div>
            <div><i data-lucide="headphones"></i><span><strong>Assistance OVANIE</strong><small>À votre écoute</small></span></div>
        </section>
    </div>
</section>
@endsection

@section('scripts')
    <script src="{{ asset('js/product.js') }}?v={{ file_exists(public_path('js/product.js')) ? filemtime(public_path('js/product.js')) : time() }}" defer></script>
@endsection
