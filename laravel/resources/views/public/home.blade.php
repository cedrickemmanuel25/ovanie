@extends('layouts.guest')

@section('title', 'OVANIE — Matériaux, équipements et solutions BTP')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/home-professional.css') }}?v={{ file_exists(public_path('css/home-professional.css')) ? filemtime(public_path('css/home-professional.css')) : time() }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $bestSellersUrl = Route::has('catalog.best-sellers') ? route('catalog.best-sellers') : $catalogUrl . '?sort=popular';
    $newArrivalsUrl = Route::has('catalog.new-arrivals') ? route('catalog.new-arrivals') : $catalogUrl . '?sort=recent';
    $sellUrl = Route::has('open-shop') ? route('open-shop') : url('/open-shop');
    $businessUrl = Route::has('catalog.business') ? route('catalog.business') : $catalogUrl;
    $giftCardsUrl = Route::has('gift-cards.index') ? route('gift-cards.index') : url('/cartes-cadeaux');
    $faqUrl = Route::has('faq') ? route('faq') : url('/faq');
    $supportUrl = Route::has('public.support-chat') ? route('public.support-chat') : url('/assistance');
    $whatsappUrl = (string) config('public_contact.whatsapp_url', 'https://wa.me/2250161781818?text=Bonjour%20OVANIE%2C%20j%E2%80%99ai%20besoin%20d%E2%80%99assistance.');
    $whatsappLabel = (string) config('public_contact.whatsapp_label', 'Discutez avec un conseiller');

    $categoryUrl = static function (string $slug) use ($catalogUrl): string {
        $publicCategorySlugs = [
            'materiaux-gros-oeuvre' => 'materiaux-gros-oeuvre',
            'materiaux-gros-oeuvres' => 'materiaux-gros-oeuvre',
            'materiaux-de-finition' => 'materiaux-de-finition',
            'materiaux-ecologique' => 'materiaux-ecologiques',
            'materiaux-ecologiques' => 'materiaux-ecologiques',
            'outillage-equipement' => 'outillage-equipement',
            'electricite-plomberie' => 'electricite-plomberie',
            'energie-solaire' => 'energie-solaire',
            'nos-reconditionnee' => 'reconditionnes',
            'nos-reconditionnes' => 'reconditionnes',
            'reconditionnes' => 'reconditionnes',
        ];

        if (isset($publicCategorySlugs[$slug]) && Route::has('categories.show')) {
            return route('categories.show', $publicCategorySlugs[$slug]);
        }

        return Route::has('catalog.index')
            ? route('catalog.index', ['category' => $slug])
            : $catalogUrl . '?category=' . urlencode($slug);
    };

    $productUrl = static function ($product): string {
        if (! $product) {
            return '#';
        }

        if (Route::has('product.show')) {
            return route('product.show', $product->slug ?? $product->id);
        }

        return '#';
    };

    $clean = static function ($value, $slug = null): string {
        if (function_exists('ovanie_public_text')) {
            return ovanie_public_text((string) $value, $slug ? (string) $slug : null);
        }

        $text = trim((string) $value);
        $text = str_replace(['�', '??'], ['', ''], $text);
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?: $text;
        return trim($text);
    };

    $price = static function ($product): float {
        if (isset($product->final_price) && is_numeric($product->final_price)) {
            return (float) $product->final_price;
        }

        return (float) ($product->display_price ?? $product->promo_price ?? $product->price ?? 0);
    };

    $formatPrice = static fn ($value): string => number_format((float) $value, 0, ',', ' ') . ' FCFA';

    $discount = static function ($product): ?int {
        $old = (float) ($product->price ?? 0);
        $new = (float) ($product->promo_price ?? 0);

        if ($old > 0 && $new > 0 && $new < $old) {
            return (int) round((($old - $new) / $old) * 100);
        }

        return null;
    };

    $rating = static function ($product): ?array {
        $avg = $product->reviews_avg_rating ?? null;
        $count = $product->reviews_count ?? null;

        if (! is_numeric($avg) || ! is_numeric($count) || (int) $count <= 0) {
            return null;
        }

        return [number_format((float) $avg, 1, ',', ''), (int) $count];
    };

    $productImage = static function ($product): string {
        foreach (['card_image_url', 'thumb_image_url', 'main_image_url', 'image_url'] as $field) {
            $value = $product->{$field} ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return asset('images/home/product-placeholder.svg');
    };

    /*
     |--------------------------------------------------------------------------
     | Visuels des catégories : EXCLUSIVEMENT public/storage/logos
     |--------------------------------------------------------------------------
     |
     | On ne prend plus les photos des produits ni images/home.
     | Le résolveur teste d'abord les noms connus du projet puis cherche, toujours
     | uniquement dans public/storage/logos, le fichier dont le nom correspond le
     | mieux à la catégorie.
     */
    $logosDirectory = public_path('storage/logos');

    $logoFiles = is_dir($logosDirectory)
        ? collect(scandir($logosDirectory))
            ->filter(function ($file) use ($logosDirectory) {
                if (! is_string($file) || in_array($file, ['.', '..'], true)) {
                    return false;
                }

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                return $extension === 'webp'
                    && is_file($logosDirectory . DIRECTORY_SEPARATOR . $file);
            })
            ->values()
        : collect();

    $normalizeLogoName = static function (string $value): string {
        $value = \Illuminate\Support\Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
    };

    $storageLogoImage = static function (array $preferred, array $keywords = []) use ($logosDirectory, $logoFiles, $normalizeLogoName): string {
        foreach ($preferred as $filename) {
            if (is_file($logosDirectory . DIRECTORY_SEPARATOR . $filename)) {
                return asset('storage/logos/' . rawurlencode($filename));
            }
        }

        $normalizedKeywords = collect($keywords)
            ->map(fn ($keyword) => $normalizeLogoName((string) $keyword))
            ->filter()
            ->values();

        if ($normalizedKeywords->isNotEmpty()) {
            $ranked = $logoFiles
                ->map(function ($filename) use ($normalizedKeywords, $normalizeLogoName) {
                    $normalized = $normalizeLogoName(pathinfo((string) $filename, PATHINFO_FILENAME));
                    $score = $normalizedKeywords->sum(
                        fn ($keyword) => str_contains($normalized, $keyword) ? max(1, substr_count($normalized, $keyword)) : 0
                    );

                    return ['file' => $filename, 'score' => $score];
                })
                ->filter(fn ($item) => $item['score'] > 0)
                ->sortByDesc('score')
                ->values();

            if ($ranked->isNotEmpty()) {
                return asset('storage/logos/' . rawurlencode((string) $ranked->first()['file']));
            }
        }

        return '';
    };

    $categoryCards = collect([
        [
            'name' => 'Matériaux gros œuvre',
            'slug' => 'materiaux-gros-oeuvres',
            'image' => $storageLogoImage(
                ['home-gros-oeuvre.webp'],
                ['gros oeuvre', 'maconnerie', 'ciment', 'brique']
            ),
        ],
        [
            'name' => 'Matériaux de finition',
            'slug' => 'materiaux-de-finition',
            'image' => $storageLogoImage(
                ['home-finition.webp'],
                ['finition', 'deco', 'carrelage', 'peinture']
            ),
        ],
        [
            'name' => 'Matériaux écologiques',
            'slug' => 'materiaux-ecologique',
            'image' => $storageLogoImage(
                ['home-equipement.webp'],
                ['ecolog', 'osb', 'terre', 'recycle']
            ),
        ],
        [
            'name' => 'Outillage & Équipement',
            'slug' => 'outillage-equipement',
            'image' => $storageLogoImage(
                ['home-outillage.webp'],
                ['outillage', 'materiel outillage', 'equipement chantier', 'outil']
            ),
        ],
        [
            'name' => 'Électricité & Plomberie',
            'slug' => 'electricite-plomberie',
            'image' => $storageLogoImage(
                ['home-plomberie.webp'],
                ['electricite plomberie', 'plomberie', 'electricite', 'cable', 'pvc']
            ),
        ],
        [
            'name' => 'Énergie solaire',
            'slug' => 'energie-solaire',
            'image' => $storageLogoImage(
                ['home-energie.webp'],
                ['energie autonomie', 'solaire', 'panneau', 'batterie']
            ),
        ],
        [
            'name' => 'Nos reconditionnés',
            'slug' => 'nos-reconditionnee',
            'image' => $storageLogoImage(
                ['home-reconditionnes.webp'],
                ['reconditionne', 'reconditionnes']
            ),
        ],
        [
            'name' => 'Cartes & bons OVANIE',
            'slug' => 'carte-cadeau-ovanie',
            'image' => $storageLogoImage(
                ['home-carte-cadeau.webp'],
                ['carte cadeau', 'bon achat', 'carte ovanie']
            ),
        ],
    ]);

    $newArrivalsList = collect($latestProducts ?? [])->filter()->sortByDesc('created_at')->take(3)->values();
    // Le classement ne contient que les produits effectivement les plus achetés.
    $bestList = collect($bestSellers ?? [])
        ->filter()
        ->unique(fn ($product) => $product->id ?? $product->slug ?? spl_object_id($product))
        ->take(6)
        ->values();

    $isFridayCampaign = (bool) ($isBlackFridayDay ?? now()->isFriday());
    $eventList = collect($isFridayCampaign ? ($blackFridayProducts ?? []) : ($flashProducts ?? []))
        ->filter()
        ->take(4)
        ->values();
    $eventTitle = $isFridayCampaign ? 'Black Friday' : 'Vente flash';
    $eventKicker = $isFridayCampaign ? 'OFFRES DU VENDREDI' : 'OFFRES LIMITÉES';
    $eventDescription = $isFridayCampaign
        ? 'Chaque vendredi, profitez des offres Black Friday actives sur OVANIE.'
        : 'Profitez des prix flash avant la fin du compte à rebours.';
    $eventUrl = $catalogUrl . '?offer=' . ($isFridayCampaign ? 'black-friday' : 'flash-sale');
    $eventEndsAt = $isFridayCampaign
        ? $eventList->pluck('bf_end')->filter()->sortBy(fn ($date) => $date->getTimestamp())->first()?->toIso8601String()
        : ($flashSaleEndsAt ?? null);

    if ($isFridayCampaign && ! $eventEndsAt) {
        $eventEndsAt = now()->endOfDay()->toIso8601String();
    }
    $giftGroups = collect($giftCardGroups ?? [])->take(3)->values();
    $featureTitle = $clean($featuredCategoryTitle ?? 'Sélection catalogue');
    if ($featureTitle === '') {
        $featureTitle = 'Sélection catalogue';
    }

    $featureDescription = $clean($featuredCategoryDescription ?? 'Découvrez une sélection de produits actuellement disponibles sur OVANIE.');
    if ($featureDescription === '') {
        $featureDescription = 'Découvrez une sélection de produits actuellement disponibles sur OVANIE.';
    }

    $featureUrl = isset($featuredCategory) && $featuredCategory
        ? $categoryUrl($featuredCategory->slug ?? '')
        : $catalogUrl;

    $giftImages = [
        'bon-achat' => asset('images/home/bon-achat.png'),
        'carte-cadeau' => asset('images/home/carte-cadeau.png'),
        'carte-virtuelle' => asset('images/home/carte-virtuelle.png'),
    ];

    if ($giftGroups->isEmpty()) {
        $giftGroups = collect([
            ['key' => 'bon-achat', 'title' => "Bon d'Achat", 'description' => 'Idéal pour récompenser vos équipes ou partenaires professionnels.', 'count' => null, 'min_price' => null],
            ['key' => 'carte-cadeau', 'title' => 'Carte Cadeau', 'description' => 'Faites plaisir à vos proches avec la carte cadeau OVANIE.', 'count' => null, 'min_price' => null],
            ['key' => 'carte-virtuelle', 'title' => 'Carte Virtuelle', 'description' => 'Instantanée et envoyée par e-mail. Utilisable sur tout le site.', 'count' => null, 'min_price' => null],
        ]);
    }
@endphp

<div class="ov-home">
    <div class="ov-shell">
        <section class="ov-hero" aria-label="Bienvenue sur OVANIE">
            <img class="ov-hero__background" src="{{ asset('images/home/hero-ovanie.png') }}" alt="Chantier de construction OVANIE avec deux professionnels du BTP">
            <div class="ov-hero__overlay"></div>

            <div class="ov-hero__copy">
                <span class="ov-eyebrow">Marketplace BTP en Côte d’Ivoire</span>
                <h1>Votre chantier<br>commence ici<br>avec <em>OVANIE</em></h1>
                <p>Matériaux, équipements et services BTP de qualité, livrés partout en Côte d’Ivoire. Pour les pros comme pour les particuliers.</p>

                <div class="ov-hero__actions">
                    <a href="{{ $catalogUrl }}" class="ov-btn ov-btn--orange">Acheter maintenant <span>→</span></a>
                    <a href="{{ $sellUrl }}" class="ov-btn ov-btn--outline-light">Vendre sur OVANIE <span>→</span></a>
                </div>

                <div class="ov-hero__support">
                    <a href="{{ $faqUrl }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9.8 9a2.4 2.4 0 1 1 4.1 1.7c-1 .8-1.9 1.3-1.9 2.8M12 17h.01"></path></svg>
                        Besoin d’aide ?
                    </a>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="ov-hero__whatsapp">
                        <svg class="ov-whatsapp-brand" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                            <path d="M16.04 3C9.46 3 4.1 8.35 4.1 14.93c0 2.1.55 4.15 1.6 5.96L4 27l6.27-1.64a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93C27.98 8.35 22.62 3 16.04 3Zm0 21.8h-.01a9.89 9.89 0 0 1-5.04-1.38l-.36-.21-3.72.97.99-3.63-.24-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.46 4.44-9.9 9.9-9.9a9.84 9.84 0 0 1 7 2.9 9.84 9.84 0 0 1 2.9 7c0 5.46-4.44 9.9-9.91 9.9Zm5.43-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.74-1.64-2.03-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.92 1.23 3.12c.15.2 2.13 3.24 5.15 4.54.72.31 1.28.49 1.72.63.72.23 1.38.2 1.9.12.58-.09 1.76-.72 2.01-1.41.25-.7.25-1.28.17-1.42-.07-.15-.27-.23-.57-.38Z"/>
                        </svg>
                        Écrivez-nous sur WhatsApp
                    </a>
                </div>
            </div>

            <div class="ov-hero__stats">
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5-8 4.5-8-4.5L12 3Z"></path><path d="m4 7.5 8 4.5 8-4.5M4 12l8 4.5 8-4.5M4 16.5l8 4.5 8-4.5"></path></svg></span>
                    <div><strong>+{{ number_format((int) ($publicProductCount ?? 0), 0, ',', ' ') }}</strong><small>produits disponibles</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path><path d="m18 5 1.5 1.5L22 4"></path></svg></span>
                    <div><strong>+{{ number_format((int) ($verifiedSellerCount ?? 0), 0, ',', ' ') }}</strong><small>vendeurs vérifiés</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg></span>
                    <div><strong>Livraison rapide</strong><small>partout en Côte d’Ivoire</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 3 8.4 7 10 4-1.6 7-5.3 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
                    <div><strong>Paiements sécurisés</strong><small>modes disponibles au checkout</small></div>
                </div>
            </div>
        </section>

        <section class="ov-panel ov-category-section">
            <div class="ov-section-head">
                <h2>Catégories BTP</h2>
                <a href="{{ $catalogUrl }}">Voir tout <span>→</span></a>
            </div>

            <div class="ov-category-grid">
                @foreach($categoryCards as $category)
                    <a href="{{ $categoryUrl((string) $category['slug']) }}" class="ov-category-card">
                        <span class="ov-category-visual">
                            @if(!empty($category['image']))
                                <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" loading="lazy" decoding="async">
                            @endif
                        </span>
                        <strong>{{ $category['name'] }}</strong>
                        <span class="ov-category-arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="ov-commerce-row">
            <aside class="ov-promotion-card">
                <div class="ov-promotion-card__content">
                    <span class="ov-kicker">PROMOTIONS</span>
                    <h2>Les offres du moment</h2>
                    <p>Profitez de prix avantageux sur une sélection de produits pour vos chantiers.</p>
                    <img class="ov-promotion-card__visual" src="{{ asset('images/home/promotion-ovanie.svg') }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                    <a href="{{ $catalogUrl }}?offer=promotion" class="ov-btn ov-btn--orange ov-btn--compact">Voir toutes les promotions <span>→</span></a>
                </div>
            </aside>

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
        </section>

        @if($eventList->isNotEmpty())
        <section class="ov-panel ov-feature-section ov-event-section {{ $isFridayCampaign ? 'is-black-friday' : 'is-flash-sale' }}">
                <div class="ov-feature-intro ov-event-intro">
                    <span class="ov-kicker">{{ $eventKicker }}</span>
                    <h2>{{ $eventTitle }}</h2>
                    <p>{{ $eventDescription }}</p>

                    @if($eventEndsAt)
                        <div class="ov-countdown" data-countdown data-ends-at="{{ $eventEndsAt }}" aria-label="Temps restant">
                            @foreach(['days' => 'Jours', 'hours' => 'Heures', 'minutes' => 'Minutes', 'seconds' => 'Secondes'] as $unit => $label)
                                <span><b data-countdown-unit="{{ $unit }}">00</b><small>{{ $label }}</small></span>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ $eventUrl }}" class="ov-btn ov-btn--outline-dark">Voir toutes les offres</a>
                </div>

                <div class="ov-feature-products">
                    @foreach($eventList as $product)
                        @php
                            $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
                            $productRating = $rating($product);
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

        <section class="ov-panel ov-gift-section">
            <div class="ov-section-head">
                <div class="ov-section-head__title">
                    <span class="ov-kicker">OFFREZ, PARTAGEZ, RÉCOMPENSEZ</span>
                    <h2>Cartes et bons OVANIE</h2>
                    <p>Des solutions simples pour toutes les occasions.</p>
                </div>
                <a href="{{ $giftCardsUrl }}">Voir toutes les cartes <span>→</span></a>
            </div>

            <div class="ov-gift-grid">
                @foreach($giftGroups as $gift)
                    @php
                        $key = $gift['key'] ?? 'bon-achat';
                        $giftTitle = $gift['title'] ?? 'Carte OVANIE';
                        $giftDescription = $gift['description'] ?? 'Une solution OVANIE simple et pratique.';
                        $giftHref = !empty($gift['representative_id']) && Route::has('gift-cards.show')
                            ? route('gift-cards.show', $gift['representative_id'])
                            : (Route::has('gift-cards.category') ? route('gift-cards.category', $key) : $giftCardsUrl);
                    @endphp
                    <a href="{{ $giftHref }}" class="ov-gift-card">
                        <span class="ov-gift-card__copy">
                            @if(!empty($gift['count']))
                                <small>{{ (int) $gift['count'] }} modèles actifs</small>
                            @endif
                            <h3>{{ $giftTitle }}</h3>
                            <p>{{ $giftDescription }}</p>
                            @if(!empty($gift['min_price']))
                                <strong>À partir de {{ $formatPrice($gift['min_price']) }}</strong>
                            @endif
                            <span>Découvrir →</span>
                        </span>
                        <span class="ov-gift-card__visual">
                            <img src="{{ $giftImages[$key] ?? $giftImages['bon-achat'] }}" alt="{{ $giftTitle }} OVANIE" loading="lazy">
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="ov-panel ov-benefits-section">
            <div class="ov-section-head"><h2>Les avantages OVANIE</h2></div>
            <div class="ov-benefit-grid">
                <article class="ov-benefit"><span><svg viewBox="0 0 24 24"><path d="m12 3 2 2 3-.3.8 2.8 2.7 1.3-1.1 2.8 1.1 2.8-2.7 1.3-.8 2.8-3-.3-2 2-2-2-3 .3-.8-2.8-2.7-1.3 1.1-2.8-1.1-2.8 2.7-1.3.8-2.8 3 .3 2-2Z"></path><path d="m9 12 2 2 4-4"></path></svg></span><div><strong>Produits vérifiés</strong><p>Un catalogue public respectant les règles de publication OVANIE.</p></div></article>
                <a href="{{ route('payment.secure') }}" class="ov-benefit"><span><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.7 3 8.4 7 10 4-1.6 7-5.3 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span><div><strong>Achat sécurisé</strong><p>Paiement et données protégés pendant le parcours de commande.</p></div></a>
                <a href="{{ route('delivery.info') }}" class="ov-benefit"><span><svg viewBox="0 0 24 24"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg></span><div><strong>Livraison adaptée</strong><p>La logistique est calculée selon le panier et l’adresse de livraison.</p></div></a>
                <article class="ov-benefit"><span><svg viewBox="0 0 24 24"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 13h3v6H5a1 1 0 0 1-1-1v-5ZM20 13h-3v6h2a1 1 0 0 0 1-1v-5Z"></path><path d="M17 19c0 1.1-1.8 2-4 2"></path></svg></span><div><strong>Assistance 7j/7</strong><p>Centre d’aide et WhatsApp OVANIE pour accompagner les clients.</p></div></article>
                <a href="{{ route('payment.secure') }}" class="ov-benefit"><span><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 9h18M7 15h4"></path></svg></span><div><strong>Paiement sécurisé</strong><p>Les moyens configurés sont proposés au moment du checkout.</p></div></a>
            </div>
        </section>

        <section class="ov-business" aria-label="OVANIE Pro">
            <img class="ov-business__background"
                 src="{{ asset('images/home/ovanie-business.png') }}"
                 alt=""
                 aria-hidden="true"
                 loading="lazy"
                 decoding="async">
            <div class="ov-business__overlay" aria-hidden="true"></div>

            <div class="ov-business__copy">
                <span class="ov-eyebrow">Pour les professionnels du BTP</span>
                <h2>OVANIE Pro</h2>
                <p>L’espace dédié aux professionnels du BTP : demandes de devis, achats en gros, suivi de commandes et conditions avantageuses pour vos chantiers.</p>

                <ul>
                    <li><span>✓</span> Devis sur-mesure</li>
                    <li><span>✓</span> Tarifs préférentiels</li>
                    <li><span>✓</span> Facturation & suivi dédiés</li>
                </ul>

                <a href="{{ $businessUrl }}" class="ov-btn ov-btn--white">Découvrir OVANIE Pro <span>→</span></a>
            </div>

            <div class="ov-business__cards">
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"></path><path d="M15 3v4h4M9 11h6M9 15h6"></path></svg></span>
                    <div><strong>Demande de devis</strong><small>Rapide et personnalisée</small></div>
                </a>
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="7" r="3"></circle><circle cx="17" cy="7" r="3"></circle><circle cx="12" cy="17" r="3"></circle><path d="M9.5 9 11 14M14.5 9 13 14"></path></svg></span>
                    <div><strong>Achats en gros</strong><small>Tarifs adaptés aux volumes</small></div>
                </a>
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v17H7zM9 2h6v4H9z"></path><path d="M10 11h4M10 15h4"></path></svg></span>
                    <div><strong>Gestion de chantier</strong><small>Commandes & livraisons</small></div>
                </a>
            </div>
        </section>

        <section class="ov-help-strip">
            <div class="ov-help-intro"><h2>Besoin d’aide ? Nous sommes là.</h2><p>Trouvez rapidement une réponse ou contactez notre équipe.</p></div>
            <a href="{{ $faqUrl }}" class="ov-help-card"><span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M9.8 9a2.4 2.4 0 1 1 4.1 1.7c-1 .8-1.9 1.3-1.9 2.8M12 17h.01"></path></svg></span><div><strong>FAQ rapide</strong><small>Les réponses aux questions les plus fréquentes.</small></div><b>→</b></a>
            <a href="{{ $supportUrl }}" class="ov-help-card"><span><svg viewBox="0 0 24 24"><path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M4 13h3v6H5a1 1 0 0 1-1-1v-5ZM20 13h-3v6h2a1 1 0 0 0 1-1v-5Z"></path></svg></span><div><strong>Centre d’assistance</strong><small>Articles, guides et solutions pour vous aider.</small></div><b>→</b></a>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="ov-help-card ov-help-card--whatsapp"><span class="ov-whatsapp-icon"><svg viewBox="0 0 32 32" fill="currentColor"><path d="M16.04 3C9.46 3 4.1 8.35 4.1 14.93c0 2.1.55 4.15 1.6 5.96L4 27l6.27-1.64a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93C27.98 8.35 22.62 3 16.04 3Zm0 21.8h-.01a9.89 9.89 0 0 1-5.04-1.38l-.36-.21-3.72.97.99-3.63-.24-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.46 4.44-9.9 9.9-9.9a9.84 9.84 0 0 1 7 2.9 9.84 9.84 0 0 1 2.9 7c0 5.46-4.44 9.9-9.91 9.9Zm5.43-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.74-1.64-2.03-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.92 1.23 3.12c.15.2 2.13 3.24 5.15 4.54.72.31 1.28.49 1.72.63.72.23 1.38.2 1.9.12.58-.09 1.76-.72 2.01-1.41.25-.7.25-1.28.17-1.42-.07-.15-.27-.23-.57-.38Z"/></svg></span><div><strong>WhatsApp</strong><small>{{ $whatsappLabel }}</small></div><b>→</b></a>
        </section>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-countdown]').forEach(function (countdown) {
        var end = Date.parse(countdown.dataset.endsAt || '');
        if (!Number.isFinite(end)) return;

        var units = {};
        countdown.querySelectorAll('[data-countdown-unit]').forEach(function (node) {
            units[node.dataset.countdownUnit] = node;
        });

        function renderCountdown() {
            var remaining = Math.max(0, end - Date.now());
            var totalSeconds = Math.floor(remaining / 1000);
            var values = {
                days: Math.floor(totalSeconds / 86400),
                hours: Math.floor((totalSeconds % 86400) / 3600),
                minutes: Math.floor((totalSeconds % 3600) / 60),
                seconds: totalSeconds % 60
            };

            Object.keys(values).forEach(function (unit) {
                if (units[unit]) units[unit].textContent = String(values[unit]).padStart(2, '0');
            });

            if (remaining === 0) {
                countdown.classList.add('is-finished');
                clearInterval(timer);
            }
        }

        renderCountdown();
        var timer = setInterval(renderCountdown, 1000);
    });
});
</script>
@endpush
@endsection
