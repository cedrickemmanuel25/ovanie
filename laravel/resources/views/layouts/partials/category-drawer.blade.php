@php
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Str;

    /*
    |--------------------------------------------------------------------------
    | Catégories publiques OVANIE
    |--------------------------------------------------------------------------
    |
    | Les libellés officiels ci-dessous servent de référence d'affichage.
    | Les anciennes bases ayant parfois remplacé les accents par "??", on ne
    | réaffiche jamais directement un nom corrompu provenant de la base.
    |
    */
    $categoryFallback = [
        'materiaux-gros-oeuvre' => [
            'aliases' => ['materiaux-gros-oeuvre', 'materiaux-gros-oeuvres'],
            'name' => 'Matériaux gros œuvre',
            'icon' => 'brick-wall',
            'children' => [
                'Ciment', 'Fer à béton', 'Gravier', 'Sable', 'Briques', 'Blocs béton',
                'Agglos', 'Hourdis', 'Treillis soudés', 'Chaux', 'Béton prêt à l’emploi',
                'Étanchéité gros œuvre',
            ],
        ],
        'materiaux-ecologiques' => [
            'aliases' => ['materiaux-ecologique', 'materiaux-ecologiques'],
            'name' => 'Matériaux écologiques',
            'icon' => 'leaf',
            'children' => [
                'Briques écologiques', 'Blocs de terre comprimée', 'Peintures écologiques',
                'Enduits naturels', 'Isolants écologiques', 'Bois traités écologiques',
                'Revêtements recyclés', 'Matériaux recyclés',
                'Solutions de construction durable', 'Produits basse consommation',
            ],
        ],
        'outillage-equipement' => [
            'aliases' => ['outillage-equipement'],
            'name' => 'Outillage & Équipement',
            'icon' => 'wrench',
            'children' => [
                'Outillage à main', 'Outillage électroportatif', 'Échelles & escabeaux',
                'Équipements de chantier', 'Équipements de protection (EPI)',
                'Machines de chantier', 'Mesure & traçage', 'Coupe & perçage', 'Soudure',
                'Nettoyage chantier', 'Levage & manutention', 'Quincaillerie',
            ],
        ],
        'materiaux-de-finition' => [
            'aliases' => ['materiaux-de-finition'],
            'name' => 'Matériaux de finition',
            'icon' => 'paint-roller',
            'children' => [
                'Carrelage', 'Faïence', 'Peinture', 'Enduits & plâtre', 'Revêtements muraux',
                'Revêtements de sol', 'Faux plafonds', 'Portes intérieures', 'Fenêtres',
                'Sanitaires de finition', 'Robinetterie', 'Décoration intérieure',
            ],
        ],
        'energie-solaire' => [
            'aliases' => ['energie-solaire'],
            'name' => 'Énergie solaire',
            'icon' => 'solar-panel',
            'children' => [
                'Panneaux solaires', 'Batteries solaires', 'Onduleurs solaires', 'Régulateurs',
                'Kits solaires', 'Lampadaires solaires', 'Projecteurs solaires', 'Pompes solaires',
                'Accessoires de fixation solaire', 'Câbles solaires',
                'Coffrets de protection solaire',
            ],
        ],
        'electricite-plomberie' => [
            'aliases' => ['electricite-plomberie'],
            'name' => 'Électricité & Plomberie',
            'icon' => 'plug-zap',
            'children' => [
                'Câbles & fils électriques', 'Disjoncteurs', 'Tableaux électriques',
                'Prises & interrupteurs', 'Luminaires', 'Gaines & conduits',
                'Protection électrique', 'Accessoires électriques', 'Tuyaux', 'Raccords',
                'Vannes', 'Robinets', 'Éviers & lavabos', 'WC & sanitaires', 'Pompes à eau',
                'Réservoirs', 'Accessoires plomberie',
            ],
        ],
        'nos-reconditionnes' => [
            'aliases' => ['nos-reconditionnee', 'nos-reconditionnes'],
            'name' => 'Nos reconditionnés',
            'icon' => 'refresh-cw',
            'children' => [
                'Groupes électrogènes reconditionnés', 'Outillage reconditionné',
                'Matériel électrique reconditionné', 'Équipements solaires reconditionnés',
                'Pompes reconditionnées', 'Machines de chantier reconditionnées',
                'Accessoires reconditionnés',
            ],
        ],
        'carte-cadeau-ovanie' => [
            'aliases' => ['carte-cadeau-ovanie'],
            'name' => 'Carte cadeau OVANIE',
            'icon' => 'gift',
            'children' => [],
        ],
    ];

    $dbParents = collect();

    try {
        if (Schema::hasTable('categories')) {
            $query = \App\Models\Category::query()
                ->whereNull('parent_id')
                ->with(['children' => function ($childQuery) {
                    if (Schema::hasColumn('categories', 'status')) {
                        $childQuery->where(function ($statusQuery) {
                            $statusQuery->whereNull('status')
                                ->orWhereIn('status', ['active', 'actif', '1', 1]);
                        });
                    }

                    if (Schema::hasColumn('categories', 'sort_order')) {
                        $childQuery->orderBy('sort_order');
                    }

                    $childQuery->orderBy('name');
                }]);

            if (Schema::hasColumn('categories', 'status')) {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('status')
                        ->orWhereIn('status', ['active', 'actif', '1', 1]);
                });
            }

            $dbParents = $query->get();
        }
    } catch (\Throwable $exception) {
        report($exception);
        $dbParents = collect();
    }

    $drawerCategories = collect($categoryFallback)->map(function (array $fallback, string $publicSlug) use ($dbParents) {
        $aliases = collect($fallback['aliases'] ?? [$publicSlug])->map(fn ($alias) => Str::slug($alias));

        $parent = $dbParents->first(function ($category) use ($aliases) {
            $dbSlug = Str::slug($category->slug ?: $category->name);
            return $aliases->contains($dbSlug);
        });

        $parentSlug = $parent?->slug ?: $publicSlug;
        $parentName = ovanie_category_label($parent?->name ?: $fallback['name'], $parentSlug);

        $children = $parent && $parent->children && $parent->children->isNotEmpty()
            ? $parent->children->map(function ($child) {
                $childSlug = $child->slug ?: Str::slug($child->name);
                return [
                    'name' => ovanie_public_text($child->name, $childSlug),
                    'slug' => $childSlug,
                ];
            })->values()
            : collect($fallback['children'])->map(fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

        return [
            'name' => $parentName,
            'slug' => $parentSlug,
            'icon' => $fallback['icon'],
            'children' => $children,
        ];
    })->values();

    $catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $drawerCartUrl = Route::has('cart.index') ? route('cart.index') : '#';
    $drawerBusinessUrl = Route::has('catalog.business') ? route('catalog.business') : '#';
    $drawerSellUrl = Route::has('open-shop') ? route('open-shop') : '#';
    $drawerOrdersUrl = Route::has('orders.index') ? route('orders.index') : '#';
    $drawerProfileUrl = Route::has('client.dashboard')
        ? route('client.dashboard')
        : (Route::has('profile.edit') ? route('profile.edit') : '#');
@endphp

<div class="ov-mobile-overlay ov-category-drawer-overlay" id="mobileMenuOverlay" aria-hidden="true"></div>

<aside
    class="ov-mobile-menu ov-category-drawer"
    id="mobileSideMenu"
    aria-hidden="true"
    aria-label="Catégories OVANIE"
>
    <div class="ov-category-drawer__header">
        <h2>Catégories OVANIE</h2>
        <button type="button" class="ov-category-drawer__close" id="closeMobileMenu" aria-label="Fermer le menu">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="ov-category-drawer__scroll">
        <section class="ov-category-drawer__section" aria-labelledby="drawer-account-title">
            <h3 id="drawer-account-title">Mon compte</h3>

            @guest
                @if(Route::has('login'))
                    <a href="{{ route('login') }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                        <i data-lucide="user-round"></i>
                        <span>Connexion</span>
                    </a>
                @endif

                @if(Route::has('register'))
                    <a href="{{ route('register') }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                        <i data-lucide="square-pen"></i>
                        <span>Inscription</span>
                    </a>
                @endif
            @else
                <a href="{{ $drawerProfileUrl }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                    <i data-lucide="circle-user-round"></i>
                    <span>Mon compte</span>
                </a>

                <a href="{{ $drawerOrdersUrl }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                    <i data-lucide="package-check"></i>
                    <span>Mes commandes</span>
                </a>
            @endguest
        </section>

        <section class="ov-category-drawer__section" aria-labelledby="drawer-categories-title">
            <h3 id="drawer-categories-title">Catégories</h3>

            <a href="{{ $catalogUrl }}" class="ov-category-drawer__item ov-category-drawer__item--active ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                <i data-lucide="layout-grid"></i>
                <span>Tous les produits</span>
            </a>

            @foreach($drawerCategories as $drawerCategory)
                @php
                    $panelId = 'drawer-category-' . Str::slug($drawerCategory['slug']);
                    $categoryUrl = Route::has('catalog.index')
                        ? route('catalog.index', ['category' => $drawerCategory['slug']])
                        : $catalogUrl;
                @endphp

                <div class="ov-category-drawer__accordion" data-category-accordion>
                    <div class="ov-category-drawer__accordion-head">
                        <a href="{{ $categoryUrl }}" class="ov-category-drawer__item ov-category-drawer__item--link">
                            <i data-lucide="{{ $drawerCategory['icon'] }}"></i>
                            <span>{{ $drawerCategory['name'] }}</span>
                        </a>

                        <button
                            type="button"
                            class="ov-category-drawer__toggle"
                            data-category-trigger
                            aria-expanded="false"
                            aria-controls="{{ $panelId }}"
                            aria-label="Afficher les sous-catégories de {{ $drawerCategory['name'] }}"
                        >
                            <i data-lucide="chevron-right" class="ov-category-drawer__chevron"></i>
                        </button>
                    </div>

                    <div
                        class="ov-category-drawer__submenu"
                        id="{{ $panelId }}"
                        data-category-panel
                        aria-hidden="true"
                    >
                        <a href="{{ $categoryUrl }}" class="ov-category-drawer__submenu-all">
                            <span>Voir toute la catégorie</span>
                        </a>

                        @foreach($drawerCategory['children'] as $drawerChild)
                            @php
                                $childUrl = Route::has('catalog.index')
                                    ? route('catalog.index', ['category' => $drawerChild['slug']])
                                    : $catalogUrl;
                            @endphp
                            <a href="{{ $childUrl }}" class="ov-category-drawer__subitem">
                                <span>{{ $drawerChild['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="ov-category-drawer__section" aria-labelledby="drawer-services-title">
            <h3 id="drawer-services-title">Services</h3>

            <a href="{{ Route::has('gift-cards.index') ? route('gift-cards.index') : $catalogUrl }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                <i data-lucide="badge-cent"></i>
                <span>Cartes & bons OVANIE</span>
            </a>

            <a href="{{ $drawerSellUrl }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--leaf">
                <i data-lucide="store"></i>
                <span>Vendez sur OVANIE</span>
            </a>

            <a href="{{ $drawerCartUrl }}" class="ov-category-drawer__item ov-category-drawer__item--standalone ov-category-drawer__item--cart">
                <i data-lucide="shopping-cart"></i>
                <span>Panier</span>
                @if(($cartCount ?? 0) > 0)
                    <strong class="ov-category-drawer__badge" data-cart-count>{{ $cartCount }}</strong>
                @endif
            </a>
        </section>
    </div>

    <div class="ov-category-drawer__security">
        <i data-lucide="shield-check"></i>
        <div>
            <strong>Paiement 100% sécurisé</strong>
            <span>Vos données sont protégées</span>
        </div>
    </div>
</aside>
