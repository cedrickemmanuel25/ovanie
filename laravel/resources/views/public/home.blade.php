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

    /*
     |--------------------------------------------------------------------------
     | Catégories affichées à l'accueil : entièrement pilotées par la base.
     |--------------------------------------------------------------------------
     |
     | Demande utilisateur : les catégories de cette section doivent venir de
     | la table `categories` et une nouvelle catégorie créée dans l'admin doit
     | y apparaître automatiquement. $homepageCategoryCards vient de
     | HomepageService::homepageCategoryCards(), qui prend simplement les
     | catégories principales actives dans l'ordre configuré par l'admin.
     |
     | Priorité de l'image : 1) photo importée pour la catégorie dans l'admin,
     | 2) visuel officiel historique (storage/logos) reconnu via le nom, 3) une
     | vraie photo produit de la catégorie.
     */
    $categoryCards = collect($homepageCategoryCards ?? [])
        ->map(function (array $card) use ($storageLogoImage, $normalizeLogoName): array {
            $category = $card['category'] ?? null;
            $name = (string) ($card['name'] ?? $category?->name ?? 'Catégorie');
            $slug = (string) ($card['slug'] ?? $category?->slug ?? '');

            $keywords = collect(explode(' ', $normalizeLogoName($name . ' ' . str_replace('-', ' ', $slug))))
                ->filter(fn ($word) => mb_strlen($word) >= 3)
                ->unique()
                ->values()
                ->all();

            $image = $category?->image_url
                ?: $storageLogoImage([], $keywords)
                ?: (string) ($card['product']?->card_image_url ?? '');

            return [
                'name' => $name,
                'slug' => $slug,
                'image' => $image,
            ];
        })
        ->values();

    $newArrivalsList = collect($latestProducts ?? [])->filter()->sortByDesc('created_at')->take(3)->values();
    // Le classement ne contient que les produits effectivement les plus achetés.
    $bestList = collect($bestSellers ?? [])
        ->filter()
        ->unique(fn ($product) => $product->id ?? $product->slug ?? spl_object_id($product))
        ->take(5)
        ->values();

    // Produits réellement en promotion (promo_price actif, voir Product::getIsOnPromoAttribute()).
    $promoList = collect($promotionProducts ?? [])->filter()->take(6)->values();

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

    // Sélection toujours peuplée (écologique, puis catégorie la mieux fournie, puis fallback catalogue global).
    $featuredList = collect($featuredCategoryProducts ?? [])->filter()->take(4)->values();

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
        @include('public.home.hero')
        @include('public.home.categories')
        @include('public.home.commerce-row')
        @include('public.home.promotions')
        @include('public.home.event-offers')
        @include('public.home.featured-selection')
        @include('public.home.gift-cards')
        @include('public.home.benefits')
        @include('public.home.business-pro')
        @include('public.home.help-strip')
    </div>
</div>
@endsection
