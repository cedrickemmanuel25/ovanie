@extends('layouts.guest')

@section('title', 'Catalogue OVANIE – Matériaux et équipements BTP')
@section('meta_description', 'Découvrez le catalogue OVANIE : matériaux de construction, équipements BTP, finition, outillage, énergie, électricité et plomberie en Côte d’Ivoire.')
@section('meta_keywords', 'catalogue OVANIE, matériaux BTP, construction, ciment, fer, finition, outillage, plomberie, électricité, Côte d’Ivoire')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}?v={{ file_exists(public_path('css/catalog.css')) ? filemtime(public_path('css/catalog.css')) : time() }}">
@endsection

@php
    use App\Models\Category;
    use App\Services\PublicProductVisibilityService;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Str;

    $homeUrl = Route::has('home') ? route('home') : url('/');
    $catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $calculatorUrl = Route::has('calculator.index')
        ? route('calculator.index')
        : (Route::has('devis.create') ? route('devis.create') : '#');
    $supportUrl = Route::has('contact.index') ? route('contact.index') : url('/contact');
    $whatsappUrl = ovanie_whatsapp_url('Bonjour OVANIE, j’ai besoin de conseils avant achat.') ?: $supportUrl;

    $toArray = function ($value) {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => is_string($item) ? explode(',', $item) : [$item])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        if ($value === null || $value === '') {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    };

    // Catégorie et sous-catégorie = sélection exclusive.
    // Si l'URL contient plusieurs anciens paramètres category, seule la
    // dernière valeur explicite est conservée. À défaut, on utilise la
    // catégorie portée par la route /categories/{category}.
    $requestedCategories = collect($toArray(request('category', [])))
        ->map(fn ($item) => Str::lower(trim((string) $item)))
        ->filter()
        ->values();

    $selectedCategory = $requestedCategories->last()
        ?: (!empty($category) ? Str::lower((string) $category) : null);

    $activeCategories = collect($selectedCategory ? [$selectedCategory] : []);

    $activeOffers = collect($toArray(request('offer', request('type', []))))
        ->map(fn ($item) => Str::lower($item))->unique()->values();
    $requiredOffer = null;
    $activeStocks = collect($toArray(request('stock', [])))
        ->map(fn ($item) => Str::lower($item))->unique()->values();
    $activeUnits = collect($toArray(request('unit', [])))
        ->map(fn ($item) => Str::lower($item))->unique()->values();
    $activeRatings = collect($toArray(request('rating', [])))->unique()->values();

    $currentSearch = trim((string) request('search', ''));
    $currentMinPrice = request('min_price', '');
    $currentMaxPrice = request('max_price', '');
    $currentSort = request('sort', ($listingPageType ?? null) === 'new-arrivals' ? 'recent' : 'popular');

    $canonicalCategoryName = function ($name, $slug = '') {
        return ovanie_category_label((string) $name, (string) $slug);
    };

    $rawCategories = collect($categories ?? []);
    $parentIds = $rawCategories
        ->map(fn ($cat) => data_get($cat, 'id'))
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->values();

    $childrenByParent = collect();

    try {
        if (
            $parentIds->isNotEmpty()
            && Schema::hasTable('categories')
            && Schema::hasColumn('categories', 'parent_id')
        ) {
            $publicVisibility = app(PublicProductVisibilityService::class);

            $childrenByParent = Category::query()
                ->whereIn('parent_id', $parentIds)
                ->when(Schema::hasColumn('categories', 'status'), function ($query) {
                    $query->where(function ($statusQuery) {
                        $statusQuery->whereNull('status')
                            ->orWhereIn('status', ['active', 'actif', '1', 1]);
                    });
                })
                ->withCount(['products as products_count' => function ($query) use ($publicVisibility) {
                    $publicVisibility->apply($query, true);
                }])
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');
        }
    } catch (\Throwable $exception) {
        report($exception);
        $childrenByParent = collect();
    }

    $catalogCategories = $rawCategories->map(function ($cat) use ($canonicalCategoryName, $childrenByParent) {
        $name = data_get($cat, 'public_name')
            ?? data_get($cat, 'name')
            ?? data_get($cat, 'nom')
            ?? data_get($cat, 'title')
            ?? 'Catégorie OVANIE';
        $slug = data_get($cat, 'slug') ?: Str::slug($name);
        $id = (int) (data_get($cat, 'id') ?? 0);

        $children = collect($childrenByParent->get($id, []))
            ->map(function ($child) {
                $childRawName = (string) (data_get($child, 'name') ?? 'Sous-catégorie');
                $childSlug = (string) (data_get($child, 'slug') ?: Str::slug($childRawName));
                $childName = ovanie_public_text($childRawName, $childSlug);

                return [
                    'name' => $childName,
                    'slug' => $childSlug,
                    'count' => (int) (data_get($child, 'products_count') ?? 0),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if (Str::contains(Str::lower($slug), 'carte-cadeau')) {
            $children = collect();
        }

        $directCount = (int) (data_get($cat, 'products_count') ?? 0);
        $childrenCount = (int) $children->sum('count');

        return [
            'id' => $id,
            'name' => $canonicalCategoryName($name, $slug),
            'slug' => $slug,
            // Une catégorie principale représente tout son univers :
            // anciens produits encore au parent + produits des sous-catégories.
            'count' => $directCount + $childrenCount,
            'sort' => (int) (data_get($cat, 'sort_order') ?? 999),
            'children' => $children,
        ];
    })->unique('slug')->sortBy(fn ($cat) => [$cat['sort'], $cat['name']])->values();

    if ($catalogCategories->isEmpty()) {
        $catalogCategories = collect([
            ['id' => 0, 'name' => 'Matériaux gros œuvre', 'slug' => 'materiaux-gros-oeuvre', 'count' => 0, 'sort' => 1, 'children' => collect()],
            ['id' => 0, 'name' => 'Matériaux écologiques', 'slug' => 'materiaux-ecologiques', 'count' => 0, 'sort' => 2, 'children' => collect()],
            ['id' => 0, 'name' => 'Outillage & Équipement', 'slug' => 'outillage-equipement', 'count' => 0, 'sort' => 3, 'children' => collect()],
            ['id' => 0, 'name' => 'Matériaux de finition', 'slug' => 'materiaux-de-finition', 'count' => 0, 'sort' => 4, 'children' => collect()],
            ['id' => 0, 'name' => 'Énergie solaire', 'slug' => 'energie-solaire', 'count' => 0, 'sort' => 5, 'children' => collect()],
            ['id' => 0, 'name' => 'Électricité & Plomberie', 'slug' => 'electricite-plomberie', 'count' => 0, 'sort' => 6, 'children' => collect()],
            ['id' => 0, 'name' => 'Nos reconditionnés', 'slug' => 'nos-reconditionnes', 'count' => 0, 'sort' => 7, 'children' => collect()],
            ['id' => 0, 'name' => 'Carte cadeau Ovanie', 'slug' => 'carte-cadeau-ovanie', 'count' => 0, 'sort' => 8, 'children' => collect()],
        ]);
    }

    // URL robuste pour le filtre latéral :
    // toujours /catalog?category=<slug>, afin que le clic fonctionne même si
    // un ancien fichier JavaScript reste en cache sur le serveur/public_html.
    // On conserve les autres filtres utiles, mais une seule catégorie.
    $catalogCategoryUrl = function (string $slug) use ($catalogUrl, $toArray) {
        $params = [];

        foreach (['search', 'min_price', 'max_price', 'sort'] as $key) {
            $value = request($key);
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }

        foreach (['offer', 'stock', 'unit', 'rating'] as $key) {
            $values = $toArray(request($key, []));
            if ($values !== []) {
                $params[$key] = $values;
            }
        }

        $params['category'] = $slug;

        return $catalogUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    };

    $categoryLabel = function ($slug) use ($catalogCategories) {
        $parent = $catalogCategories->firstWhere('slug', $slug);
        if ($parent) {
            return $parent['name'];
        }

        foreach ($catalogCategories as $catalogCategory) {
            $child = collect($catalogCategory['children'] ?? [])->firstWhere('slug', $slug);
            if ($child) {
                return $child['name'];
            }
        }

        return Str::of($slug)->replace('-', ' ')->title();
    };

    $offerStats = collect($offerStats ?? []);
    $offerLabels = [
        'promo' => 'Promotions',
        'vente-flash' => 'Vente flash',
        'black-friday' => 'Black Friday',
        'top' => 'Top ventes',
        'new' => 'Nouveautés',
        'boosted' => 'À la une',
    ];
    $stockLabels = [
        'in-stock' => 'En stock',
        'on-order' => 'Sur commande',
        'out-of-stock' => 'Sur commande',
        'fast-delivery' => 'Livraison rapide',
    ];

    $hasFilters = $currentSearch
        || $currentMinPrice
        || $currentMaxPrice
        || $activeCategories->isNotEmpty()
        || $activeOffers->isNotEmpty()
        || $activeStocks->isNotEmpty()
        || $activeUnits->isNotEmpty()
        || $activeRatings->isNotEmpty();

    $categoryPage = isset($categoryPageSlug) ? collect([
        'materiaux-gros-oeuvre' => [
            'title' => 'Matériaux gros œuvre',
            'description' => 'Ciment, fers, agglos, parpaings, agrégats et matériaux essentiels pour des fondations solides et durables.',
            'image' => 'gros œuvre & maçonnerie.png', 'accent' => '#0b66e4',
            'items' => ['Ciment', 'Fers à béton', 'Agglos & parpaings', 'Sables & graviers', 'Béton & mortier', 'Treillis soudés'],
        ],
        'materiaux-de-finition' => [
            'title' => 'Matériaux de finition',
            'description' => 'Peintures, carrelages, enduits, revêtements et finitions pour des chantiers soignés, durables et professionnels.',
            'image' => 'finition & déco.png', 'accent' => '#0868e8',
            'items' => ['Peintures', 'Carrelage', 'Enduits', 'Revêtements muraux', 'Revêtements de sol', 'Faux plafonds'],
        ],
        'outillage-equipement' => [
            'title' => 'Outillage & Équipement',
            'description' => 'Perceuses, scies, meuleuses, échelles, équipements de protection et matériel indispensable pour vos travaux.',
            'image' => 'matériel outillage.png', 'accent' => '#0868e8',
            'items' => ['Outillage électroportatif', 'Outillage manuel', 'Échelles', 'Protection & sécurité', 'Mesure & traçage'],
        ],
        'electricite-plomberie' => [
            'title' => 'Électricité & Plomberie',
            'description' => 'Câbles, disjoncteurs, prises, luminaires, robinets, sanitaires, tuyaux et équipements pour vos installations.',
            'image' => 'Électricité & Plomberie.png', 'accent' => '#0868e8',
            'items' => ['Câbles & fils', 'Tableaux électriques', 'Éclairage', 'Tuyauterie', 'Sanitaires', 'Robinetterie'],
        ],
        'energie-solaire' => [
            'title' => 'Énergie solaire',
            'description' => 'Panneaux solaires, batteries, onduleurs, kits et accessoires pour vos besoins résidentiels et professionnels.',
            'image' => 'énergie & autonomie.png', 'accent' => '#075bd8',
            'items' => ['Panneaux', 'Batteries', 'Onduleurs', 'Kits solaires', 'Accessoires'],
        ],
        'materiaux-ecologiques' => [
            'title' => 'Matériaux écologiques',
            'description' => 'Matériaux responsables, isolants naturels, peintures écologiques et solutions durables pour vos chantiers.',
            'image' => 'home-finition.webp', 'accent' => '#388b2d',
            'items' => ['Peintures écologiques', 'Isolants naturels', 'Bois certifié', 'Matériaux recyclés', 'Enduits naturels'],
        ],
        'reconditionnes' => [
            'title' => 'Produits reconditionnés',
            'description' => 'Équipements, outils et matériels remis en état, testés et proposés à prix avantageux pour vos chantiers.',
            'image' => 'Nos reconditionnés.png', 'accent' => '#168541',
            'items' => ['Outillage', 'Équipements de chantier', 'Électricité', 'Plomberie', 'Énergie solaire'],
        ],
    ])->get($categoryPageSlug) : null;

    if ($categoryPage) {
        // Même pour les catégories historiques, la photo choisie dans
        // l'administration doit remplacer immédiatement l'ancien visuel.
        $categoryPage['image_url'] = ! empty($categoryRecord?->image_url)
            ? $categoryRecord->image_url
            : asset('storage/logos/' . rawurlencode($categoryPage['image']));
    }

    // Demande utilisateur : une catégorie créée dans l'admin doit avoir la
    // même page dédiée (bannière, photo importée, sous-catégories en
    // raccourcis) que les 7 catégories historiques ci-dessus, au lieu de
    // rester sans page tant qu'elle n'est pas ajoutée à la liste figée.
    if (! $categoryPage && ! empty($categoryRecord)) {
        $categoryPage = [
            'title' => $categoryRecord->name,
            'description' => trim((string) $categoryRecord->description) !== ''
                ? $categoryRecord->description
                : 'Découvrez les produits de la catégorie ' . $categoryRecord->name . ' disponibles sur OVANIE.',
            'image_url' => $categoryRecord->image_url ?: asset('images/home/product-placeholder.svg'),
            'accent' => '#0868e8',
            'items' => $categoryRecord->children->pluck('name')->values()->all(),
        ];
    }

    $listingPage = match ($listingPageType ?? null) {
        'best-sellers' => [
            'title' => 'Meilleures ventes',
            'description' => 'Découvrez les produits les plus commandés en ce moment par nos clients sur OVANIE.',
            'eyebrow' => 'Le choix de nos clients',
            'icon' => 'award',
            'accent' => '#ff8a00',
            'image' => asset('storage/logos/home-gros-oeuvre.webp'),
        ],
        'new-arrivals' => [
            'title' => 'Nouveautés',
            'description' => 'Découvrez les dernières références ajoutées sur OVANIE.',
            'eyebrow' => 'Nouvelles références',
            'icon' => 'gift',
            'accent' => '#0868e8',
            'image' => asset('storage/logos/home-equipement.webp'),
        ],
        default => null,
    };
@endphp

@section('content')
<section class="catalog-page"
    @if($categoryPage) style="--category-accent: {{ $categoryPage['accent'] }}" @endif
    data-catalog-url="{{ $catalogUrl }}"
    @if(!empty($category)) data-required-category="{{ $category }}" @endif
    @if($listingPage) data-listing-page="{{ $listingPageType }}" style="--category-accent: {{ $listingPage['accent'] }}" @endif>
    <div class="catalog-shell">
        <nav class="catalog-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ $homeUrl }}">Accueil</a>
            <i data-lucide="chevron-right"></i>
            <a href="{{ $catalogUrl }}" class="is-current">Catalogue</a>
            @if($activeCategories->count() === 1)
                <i data-lucide="chevron-right"></i>
                <span>{{ $categoryLabel($activeCategories->first()) }}</span>
            @endif
        </nav>

        @if($listingPage)
            <section class="curated-hero">
                <div class="curated-hero__icon"><i data-lucide="{{ $listingPage['icon'] }}"></i></div>
                <div class="curated-hero__copy">
                    <span>{{ $listingPage['eyebrow'] }}</span>
                    <h1>{{ $listingPage['title'] }}</h1>
                    <p>{{ $listingPage['description'] }}</p>
                </div>
                <img src="{{ $listingPage['image'] }}" alt="{{ $listingPage['title'] }}">
            </section>
            <section class="category-benefits curated-benefits" aria-label="Avantages OVANIE">
                <div><i data-lucide="flame"></i><span><strong>{{ $listingPageType === 'best-sellers' ? 'Les plus commandés' : 'Nouveautés régulières' }}</strong><small>Données réelles OVANIE</small></span></div>
                <div><i data-lucide="shield-check"></i><span><strong>Produits de qualité</strong><small>Sélection professionnelle</small></span></div>
                <div><i data-lucide="package-check"></i><span><strong>Stock disponible</strong><small>Informations mises à jour</small></span></div>
                <div><a class="catalog-service-link" href="{{ route('delivery.info') }}" aria-label="Informations sur la livraison"></a><i data-lucide="truck"></i><span><strong>Livraison adaptée</strong><small>Partout en Côte d’Ivoire</small></span></div>
            </section>
        @endif

        @if($categoryPage)
            <section class="category-landing-hero">
                <div class="category-landing-copy">
                    <span>Catégorie OVANIE</span>
                    <h1>{{ $categoryPage['title'] }}</h1>
                    <p>{{ $categoryPage['description'] }}</p>
                </div>
                <div class="category-landing-visual">
                    <img src="{{ $categoryPage['image_url'] }}" alt="{{ $categoryPage['title'] }}">
                </div>
            </section>

            <section class="category-benefits" aria-label="Avantages OVANIE">
                <div><i data-lucide="shield-check"></i><span><strong>Produits vérifiés</strong><small>Qualité professionnelle</small></span></div>
                <div><a class="catalog-service-link" href="{{ route('delivery.info') }}" aria-label="Informations sur la livraison"></a><i data-lucide="truck"></i><span><strong>Livraison adaptée</strong><small>Partout en Côte d’Ivoire</small></span></div>
                <div><a class="catalog-service-link" href="{{ route('payment.secure') }}" aria-label="Informations sur le paiement sécurisé"></a><i data-lucide="lock-keyhole"></i><span><strong>Paiement sécurisé</strong><small>Plusieurs moyens</small></span></div>
                <div><i data-lucide="headphones"></i><span><strong>Assistance 7j/7</strong><small>À votre écoute</small></span></div>
            </section>

            @if(!empty($categoryPage['items']))
                <nav class="category-shortcuts" aria-label="Sous-catégories">
                    @foreach($categoryPage['items'] as $index => $item)
                        <button type="button" data-category-search="{{ $item }}" class="{{ $index === 0 ? 'is-active' : '' }}">
                            <i data-lucide="{{ ['package', 'hammer', 'blocks', 'paint-bucket', 'zap', 'wrench'][$index % 6] }}"></i>
                            <span>{{ $item }}</span>
                        </button>
                    @endforeach
                </nav>
            @endif
        @endif

        <section class="catalog-summary-strip" aria-label="Résumé du catalogue">
            <div class="catalog-summary-item catalog-summary-item--count">
                <span class="catalog-summary-icon"><i data-lucide="package"></i></span>
                <strong>Catalogue professionnel</strong>
            </div>
            <div class="catalog-summary-item">
                <span class="catalog-summary-icon"><i data-lucide="grid-2x2"></i></span>
                <strong>8 catégories principales</strong>
            </div>
            <div class="catalog-summary-item catalog-summary-item--accent">
                <span class="catalog-summary-icon"><i data-lucide="badge-check"></i></span>
                <strong>Parcours catalogue simplifié</strong>
            </div>
        </section>

        <header class="catalog-page-heading {{ $categoryPage || $listingPage ? 'is-category' : '' }}">
            <div>
                @unless($categoryPage || $listingPage)<h1>Catalogue</h1>@endunless
                <span id="resultCountText">Chargement…</span>
            </div>
            <p>Matériaux, équipements et solutions BTP disponibles sur OVANIE.</p>
        </header>

        <div class="catalog-toolbar">
            <div class="catalog-toolbar__filters" id="activeFilters">
                @if(!$hasFilters)
                    <span class="catalog-chip catalog-chip--neutral"><i data-lucide="package"></i> Tous les produits</span>
                @else
                    @if($currentSearch)
                        <span class="catalog-chip">{{ $currentSearch }} <i data-lucide="x"></i></span>
                    @endif
                    @foreach($activeCategories->take(3) as $catSlug)
                        <span class="catalog-chip">{{ $categoryLabel($catSlug) }} <i data-lucide="x"></i></span>
                    @endforeach
                    @foreach($activeOffers->take(3) as $offer)
                        <span class="catalog-chip">{{ $offerLabels[$offer] ?? Str::title($offer) }} <i data-lucide="x"></i></span>
                    @endforeach
                    @foreach($activeStocks->take(2) as $stock)
                        <span class="catalog-chip">{{ $stockLabels[$stock] ?? Str::title($stock) }} <i data-lucide="x"></i></span>
                    @endforeach
                    <a href="{{ $catalogUrl }}" class="catalog-clear-link">Tout effacer</a>
                @endif
            </div>

            <div class="catalog-toolbar__controls">
                <button type="button" id="mobileFilterOpen" class="catalog-mobile-filter-button">
                    <i data-lucide="sliders-horizontal"></i>
                    <span>Filtres</span>
                </button>

                <label class="catalog-sort" for="sortSelect">
                    <span>Trier par</span>
                    <select id="sortSelect" class="filter-control" name="sort">
                        <option value="popular" @selected($currentSort === 'popular')>Meilleures ventes</option>
                        <option value="recent" @selected($currentSort === 'recent')>Nouveautés</option>
                        <option value="price_asc" @selected($currentSort === 'price_asc')>Prix croissant</option>
                        <option value="price_desc" @selected($currentSort === 'price_desc')>Prix décroissant</option>
                        <option value="availability" @selected($currentSort === 'availability')>Disponibilité</option>
                    </select>
                </label>

                <div class="catalog-view-switch" aria-label="Mode d’affichage">
                    <button type="button" id="gridViewBtn" class="is-active" aria-label="Vue grille"><i data-lucide="layout-grid"></i></button>
                    <button type="button" id="listViewBtn" aria-label="Vue liste"><i data-lucide="list"></i></button>
                </div>
            </div>
        </div>

        <div class="catalog-layout">
            <div class="catalog-filter-overlay" id="catalogFilterOverlay"></div>

            <aside class="catalog-sidebar" id="catalogFilters" aria-label="Filtres du catalogue">
                <div class="catalog-sidebar__inner">
                    <div class="catalog-sidebar__head">
                        <div>
                            <span class="catalog-sidebar__eyebrow">Affiner</span>
                            <strong>Filtres</strong>
                        </div>
                        <button type="button" class="catalog-sidebar__close" id="closeCatalogFilters" aria-label="Fermer les filtres">
                            <i data-lucide="x"></i>
                        </button>
                    </div>

                    <div class="catalog-filter-group is-open" id="filterGroupSearch">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupSearch">
                            <span><i data-lucide="search"></i> Rechercher</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body">
                            <label class="catalog-search-box" for="searchInput">
                                <i data-lucide="search"></i>
                                <input id="searchInput" name="search" class="filter-control" type="search" value="{{ $currentSearch }}" placeholder="Ciment, peinture, groupe…" autocomplete="off">
                            </label>
                        </div>
                    </div>

                    <div class="catalog-filter-group is-open" id="filterGroupCategories">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupCategories">
                            <span><i data-lucide="grid-2x2"></i> Catégories</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body catalog-category-list">
                            @foreach($catalogCategories as $cat)
                                @php
                                    $children = collect($cat['children'] ?? []);
                                    $parentActive = $activeCategories->contains(Str::lower($cat['slug']));
                                    $childActive = $children->contains(fn ($child) => $activeCategories->contains(Str::lower($child['slug'])));
                                    $opened = $parentActive || $childActive;
                                    $categoryDestination = Str::contains(Str::lower($cat['slug']), 'carte-cadeau')
                                        ? (Route::has('gift-cards.index') ? route('gift-cards.index') : $catalogUrl)
                                        : $catalogCategoryUrl($cat['slug']);
                                @endphp
                                <div class="catalog-category-block {{ $opened ? 'is-open' : '' }}">
                                    <div class="catalog-category-parent">
                                        <a
                                            href="{{ $categoryDestination }}"
                                            class="catalog-check-row catalog-check-row--category"
                                            data-category-filter="{{ $cat['slug'] }}"
                                            aria-current="{{ $parentActive ? 'true' : 'false' }}"
                                            style="text-decoration:none;color:inherit"
                                        >
                                            <input type="radio" class="filter-control" name="category" value="{{ $cat['slug'] }}" @checked($parentActive) tabindex="-1" aria-hidden="true">
                                            <span class="catalog-check-ui"></span>
                                            <span class="catalog-check-label">{{ $cat['name'] }}</span>
                                            @if($cat['count'] > 0)<small>{{ $cat['count'] }}</small>@endif
                                        </a>

                                        @if($children->isNotEmpty())
                                            <button
                                                type="button"
                                                class="catalog-subcategory-toggle"
                                                aria-expanded="{{ $opened ? 'true' : 'false' }}"
                                                aria-label="Afficher les sous-catégories de {{ $cat['name'] }}"
                                            >
                                                <i data-lucide="chevron-down"></i>
                                            </button>
                                        @endif
                                    </div>

                                    @if($children->isNotEmpty())
                                        <div class="catalog-subcategory-list">
                                            @foreach($children as $child)
                                                @php
                                                    $childDestination = $catalogCategoryUrl($child['slug']);
                                                @endphp
                                                @php
                                                    $isChildActive = $activeCategories->contains(Str::lower($child['slug']));
                                                @endphp
                                                <a
                                                    href="{{ $childDestination }}"
                                                    class="catalog-check-row catalog-check-row--subcategory"
                                                    data-category-filter="{{ $child['slug'] }}"
                                                    aria-current="{{ $isChildActive ? 'true' : 'false' }}"
                                                    style="text-decoration:none;color:inherit"
                                                >
                                                    <input
                                                        type="radio"
                                                        class="filter-control"
                                                        name="category"
                                                        value="{{ $child['slug'] }}"
                                                        @checked($isChildActive)
                                                        tabindex="-1"
                                                        aria-hidden="true"
                                                    >
                                                    <span class="catalog-check-ui"></span>
                                                    <span class="catalog-check-label">{{ $child['name'] }}</span>
                                                    @if($child['count'] > 0)<small>{{ $child['count'] }}</small>@endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="catalog-filter-group is-open" id="filterGroupPrice">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupPrice">
                            <span><i data-lucide="wallet-cards"></i> Budget</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body">
                            <div class="catalog-price-inputs">
                                <label><span>Minimum</span><input id="priceMin" name="min_price" class="filter-control" type="number" value="{{ $currentMinPrice }}" min="0" step="500" placeholder="0 FCFA"></label>
                                <label><span>Maximum</span><input id="priceMax" name="max_price" class="filter-control" type="number" value="{{ $currentMaxPrice }}" min="0" step="500" placeholder="Sans limite"></label>
                            </div>
                            <button id="applyPrice" type="button" class="catalog-apply-btn">Appliquer le budget</button>
                        </div>
                    </div>

                    <div class="catalog-filter-group" id="filterGroupStock">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupStock">
                            <span><i data-lucide="package-check"></i> Disponibilité</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body">
                            <label class="catalog-check-row">
                                <input type="checkbox" class="filter-control" name="stock" value="in-stock" @checked($activeStocks->contains('in-stock'))>
                                <span class="catalog-check-ui"></span><span class="catalog-check-label">En stock immédiat</span>
                            </label>
                            <label class="catalog-check-row">
                                <input type="checkbox" class="filter-control" name="stock" value="on-order" @checked($activeStocks->contains('on-order') || $activeStocks->contains('out-of-stock'))>
                                <span class="catalog-check-ui"></span><span class="catalog-check-label">Sur commande / précommande</span>
                            </label>
                            <label class="catalog-check-row">
                                <input type="checkbox" class="filter-control" name="stock" value="fast-delivery" @checked($activeStocks->contains('fast-delivery'))>
                                <span class="catalog-check-ui"></span><span class="catalog-check-label">Livraison rapide disponible</span>
                            </label>
                        </div>
                    </div>

                    <div class="catalog-filter-group" id="filterGroupOffers">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupOffers">
                            <span><i data-lucide="badge-percent"></i> Offres & sélections</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body">
                            @php
                                $offerRows = collect([
                                    ['value' => 'promo', 'label' => 'Promotions actives', 'icon' => 'badge-percent', 'visible' => ($offerStats->get('promo', 0) > 0)],
                                    ['value' => 'vente-flash', 'label' => 'Vente flash', 'icon' => 'zap', 'visible' => ($offerStats->get('vente-flash', 0) > 0)],
                                    ['value' => 'black-friday', 'label' => 'Black Friday', 'icon' => 'tag', 'visible' => ($offerStats->get('black-friday', 0) > 0)],
                                    ['value' => 'top', 'label' => 'Top ventes', 'icon' => 'trending-up', 'visible' => true],
                                    ['value' => 'new', 'label' => 'Nouveautés', 'icon' => 'sparkles', 'visible' => true],
                                    ['value' => 'boosted', 'label' => 'À la une', 'icon' => 'star', 'visible' => ($offerStats->get('a-la-une', 0) > 0)],
                                ])->where('visible', true);
                            @endphp
                            @foreach($offerRows as $offer)
                                <label class="catalog-check-row catalog-check-row--offer">
                                    <input type="checkbox" class="filter-control" name="offer" value="{{ $offer['value'] }}" @checked($activeOffers->contains($offer['value']))>
                                    <span class="catalog-check-ui"></span>
                                    <span class="catalog-check-label"><i data-lucide="{{ $offer['icon'] }}"></i>{{ $offer['label'] }}</span>
                                    @if($offerStats->has($offer['value']))<small>{{ $offerStats->get($offer['value']) }}</small>@endif
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="catalog-filter-group" id="filterGroupUnits">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupUnits">
                            <span><i data-lucide="ruler"></i> Unités de vente</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body catalog-filter-body--two">
                            @foreach(['sac' => 'Sac', 'tonne' => 'Tonne', 'm2' => 'm²', 'm3' => 'm³', 'piece' => 'Pièce', 'rouleau' => 'Rouleau', 'seau' => 'Seau', 'palette' => 'Palette'] as $value => $label)
                                <label class="catalog-check-row">
                                    <input type="checkbox" class="filter-control" name="unit" value="{{ $value }}" @checked($activeUnits->contains($value))>
                                    <span class="catalog-check-ui"></span><span class="catalog-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="catalog-filter-group" id="filterGroupRating">
                        <button type="button" class="catalog-filter-title" data-toggle="filterGroupRating">
                            <span><i data-lucide="star"></i> Évaluation</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="catalog-filter-body">
                            <label class="catalog-check-row">
                                <input type="checkbox" class="filter-control" name="rating" value="4" @checked($activeRatings->contains('4'))>
                                <span class="catalog-check-ui"></span><span class="catalog-check-label catalog-stars">★★★★☆ <em>& plus</em></span>
                            </label>
                            <label class="catalog-check-row">
                                <input type="checkbox" class="filter-control" name="rating" value="3" @checked($activeRatings->contains('3'))>
                                <span class="catalog-check-ui"></span><span class="catalog-check-label catalog-stars">★★★☆☆ <em>& plus</em></span>
                            </label>
                        </div>
                    </div>

                    <button type="button" id="filterReset" class="catalog-reset-btn">
                        <i data-lucide="rotate-ccw"></i> Réinitialiser tous les filtres
                    </button>
                </div>
            </aside>

            <main class="catalog-main" id="catalogMain">
                <section class="catalog-trust-strip" aria-label="Garanties OVANIE">
                    <div><span><i data-lucide="shield-check"></i></span><p><strong>Produits vérifiés</strong><small>Contrôle qualité OVANIE</small></p></div>
                    <div><a class="catalog-service-link" href="{{ route('delivery.info') }}" aria-label="Informations sur la livraison"></a><span><i data-lucide="truck"></i></span><p><strong>Livraison adaptée</strong><small>Selon poids, volume et zone</small></p></div>
                    <div><a class="catalog-service-link" href="{{ route('payment.secure') }}" aria-label="Informations sur le paiement sécurisé"></a><span><i data-lucide="credit-card"></i></span><p><strong>Paiement sécurisé</strong><small>Mobile Money et carte</small></p></div>
                    <div><span><i data-lucide="headphones"></i></span><p><strong>Assistance 7j/7</strong><small>Avant et après l’achat</small></p></div>
                </section>

                <section class="products-grid" id="productsGrid" aria-live="polite">
                    <div class="catalog-empty">
                        <span><i data-lucide="loader-circle"></i></span>
                        <h3>Chargement des produits…</h3>
                        <p>Nous préparons votre catalogue OVANIE.</p>
                    </div>
                </section>

                <div class="catalog-pagination-wrap">
                    <div class="catalog-pagination" id="paginationWrapper"></div>
                </div>
            </main>
        </div>

        <section class="catalog-service-strip">
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i data-lucide="message-circle"></i><span><strong>Conseil avant achat</strong><small>Parlez à OVANIE</small></span></a>
            <a href="{{ $supportUrl }}"><i data-lucide="headphones"></i><span><strong>Centre d’assistance</strong><small>Support client</small></span></a>
            <a href="{{ $calculatorUrl }}"><i data-lucide="calculator"></i><span><strong>Calculateur chantier</strong><small>Estimez vos besoins</small></span></a>
            <a href="#"><i data-lucide="shield-check"></i><span><strong>Achat protégé</strong><small>Protection OVANIE</small></span></a>
        </section>
    </div>
</section>
@endsection

@section('scripts')
    <script type="module" src="{{ asset('js/catalog.js') }}?v={{ file_exists(public_path('js/catalog.js')) ? filemtime(public_path('js/catalog.js')) : time() }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('catalogFilters');
            const overlay = document.getElementById('catalogFilterOverlay');
            const openBtn = document.getElementById('mobileFilterOpen');
            const closeBtn = document.getElementById('closeCatalogFilters');
            const main = document.getElementById('catalogMain');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');

            const refreshIcons = () => window.lucide?.createIcons();
            const openFilters = () => {
                sidebar?.classList.add('is-mobile-open');
                overlay?.classList.add('is-active');
                document.body.classList.add('catalog-filter-open');
            };
            const closeFilters = () => {
                sidebar?.classList.remove('is-mobile-open');
                overlay?.classList.remove('is-active');
                document.body.classList.remove('catalog-filter-open');
            };

            openBtn?.addEventListener('click', openFilters);
            closeBtn?.addEventListener('click', closeFilters);
            overlay?.addEventListener('click', closeFilters);
            document.addEventListener('keydown', event => { if (event.key === 'Escape') closeFilters(); });

            document.querySelectorAll('.catalog-filter-title').forEach(button => {
                button.addEventListener('click', function () {
                    document.getElementById(button.dataset.toggle)?.classList.toggle('is-open');
                    refreshIcons();
                });
            });

            document.querySelectorAll('.catalog-subcategory-toggle').forEach(button => {
                button.addEventListener('click', function () {
                    const block = button.closest('.catalog-category-block');
                    const isOpen = block?.classList.toggle('is-open') ?? false;
                    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    refreshIcons();
                });
            });

            gridBtn?.addEventListener('click', function () {
                main?.classList.remove('catalog-main--list');
                gridBtn.classList.add('is-active');
                listBtn?.classList.remove('is-active');
            });

            listBtn?.addEventListener('click', function () {
                main?.classList.add('catalog-main--list');
                listBtn.classList.add('is-active');
                gridBtn?.classList.remove('is-active');
            });

            refreshIcons();
        });
    </script>
@endsection
