@php
    use App\Models\Category;
    use Illuminate\Support\Facades\Route;

    $_homeUrl = Route::has('home') ? route('home') : url('/');
    $_catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $_searchUrl = Route::has('search') ? route('search') : $_catalogUrl;
    $_loginUrl = Route::has('login') ? route('login') : url('/login');
    $_accountUrl = auth()->check()
        ? (Route::has('client.dashboard') ? route('client.dashboard') : $_homeUrl)
        : $_loginUrl;
    $_favoriteUrl = auth()->check() && Route::has('client.favorites')
        ? route('client.favorites')
        : $_loginUrl;
    $_cartUrl = Route::has('cart.index') ? route('cart.index') : url('/cart');
    $_sellUrl = Route::has('sell.on.ovanie') ? route('sell.on.ovanie') : url('/vendre-sur-ovanie');
    $_partnersUrl = Route::has('partners.suppliers') ? route('partners.suppliers') : url('/partenaires-fournisseurs');
    $_businessUrl = Route::has('catalog.business') ? route('catalog.business') : $_catalogUrl;
    $_giftCardsUrl = Route::has('gift-cards.index') ? route('gift-cards.index') : $_catalogUrl;
    $_howToBuyUrl = Route::has('how-to-buy') ? route('how-to-buy') : url('/comment-acheter');
    $_guaranteeUrl = Route::has('buyer.guarantee') ? route('buyer.guarantee') : url('/garantie-acheteur');
    $_vendorTermsUrl = Route::has('vendor.terms') ? route('vendor.terms') : url('/conditions-vendeurs');
    $_registerUrl = Route::has('register') ? route('register') : url('/register');
    $_dashboardUrl = auth()->check() && Route::has('client.dashboard') ? route('client.dashboard') : $_loginUrl;
    $_ordersUrl = auth()->check() && Route::has('client.orders') ? route('client.orders') : $_loginUrl;
    $_returnsUrl = auth()->check() && Route::has('client.returns') ? route('client.returns') : $_loginUrl;
    $_disputesUrl = auth()->check() && Route::has('problem.report') ? route('problem.report', ['type' => 'litige']) : $_loginUrl;
    $_trackingUrl = Route::has('order.tracking.public') ? route('order.tracking.public') : url('/suivi-commande');
    $_helpUrl = Route::has('help.center') ? route('help.center') : url('/centre-aide');
    $_logoutUrl = Route::has('logout') ? route('logout') : url('/logout');
    $_cartCount = (int) ($cartCount ?? 0);
    $_currentUser = auth()->user();
    $_clientName = $_currentUser
        ? (trim((string) ($_currentUser->first_name ?? '')) ?: trim((string) ($_currentUser->name ?? 'Client')))
        : '';
    $_greeting = now('Africa/Abidjan')->hour >= 18 ? 'Bonsoir' : 'Bonjour';
    $_publicPhone = (string) config('public_contact.phone_display', '01 61 78 00 00');
    $_publicPhoneHref = (string) config('public_contact.phone_e164', '+2250161780000');

    $_catalogCategoryUrl = static function (string $slug) use ($_catalogUrl): string {
        $publicCategorySlugs = [
            'materiaux-gros-oeuvres' => 'materiaux-gros-oeuvre',
            'materiaux-de-finition' => 'materiaux-de-finition',
            'outillage-equipement' => 'outillage-equipement',
            'electricite-plomberie' => 'electricite-plomberie',
            'energie-solaire' => 'energie-solaire',
            'materiaux-ecologique' => 'materiaux-ecologiques',
            'nos-reconditionnee' => 'reconditionnes',
        ];

        if (isset($publicCategorySlugs[$slug]) && Route::has('categories.show')) {
            return route('categories.show', $publicCategorySlugs[$slug]);
        }

        return Route::has('catalog.index')
            ? route('catalog.index', ['category' => $slug])
            : $_catalogUrl . '?category=' . urlencode($slug);
    };

    // Demande utilisateur : une catégorie créée dans l'admin doit apparaître
    // automatiquement dans la barre de navigation principale, sans liste
    // figée dans le code.
    $_navCategories = Category::query()
        ->active()
        ->roots()
        ->ordered()
        ->limit(8)
        ->get(['id', 'slug', 'name']);
@endphp

<header class="ovn-header" data-ovanie-navbar>
    <div class="ovn-header__top">
        <div class="ovn-shell ovn-header__top-inner">
            <div class="ovn-header__brand-zone">
                <a href="{{ $_homeUrl }}" class="ovn-header__logo" aria-label="OVANIE - Accueil">
                    <img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE — Faites des profits avec nous">
                </a>
                <a href="tel:{{ $_publicPhoneHref }}" class="ovn-header__phone" aria-label="Appeler OVANIE au {{ $_publicPhone }}">
                    <span class="ovn-header__phone-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2.1Z"></path></svg></span>
                    <span><small>Appelez-nous</small><strong>{{ $_publicPhone }}</strong></span>
                </a>
            </div>

            <form action="{{ $_searchUrl }}" method="GET" class="ovn-search" role="search">
                <div class="ovn-search__category">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h6v6H4zM14 6h6v6h-6zM4 16h6v4H4zM14 16h6v4h-6z"></path></svg>
                    <select id="ovn-global-search-category" name="category" aria-label="Filtrer la recherche par catégorie">
                        <option value="">Toutes les catégories</option>
                        <option value="materiaux-gros-oeuvres" @selected(request('category') === 'materiaux-gros-oeuvres')>Gros œuvre</option>
                        <option value="materiaux-de-finition" @selected(request('category') === 'materiaux-de-finition')>Finition</option>
                        <option value="materiaux-ecologique" @selected(request('category') === 'materiaux-ecologique')>Écologiques</option>
                        <option value="outillage-equipement" @selected(request('category') === 'outillage-equipement')>Outillage</option>
                        <option value="electricite-plomberie" @selected(request('category') === 'electricite-plomberie')>Électricité & plomberie</option>
                        <option value="energie-solaire" @selected(request('category') === 'energie-solaire')>Énergie solaire</option>
                        <option value="nos-reconditionnee" @selected(request('category') === 'nos-reconditionnee')>Reconditionnés</option>
                        <option value="carte-cadeau-ovanie" @selected(request('category') === 'carte-cadeau-ovanie')>Cartes OVANIE</option>
                    </select>
                </div>

                <div class="ovn-search__field">
                    <input
                        id="ovn-global-search"
                        name="search"
                        type="search"
                        value="{{ request('search') }}"
                        placeholder="Rechercher un produit, une marque ou une référence..."
                        aria-label="Rechercher un produit, une marque ou une référence"
                        autocomplete="off"
                        enterkeyhint="search"
                    >
                </div>

                <button type="submit" aria-label="Lancer la recherche">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.6-3.6"></path></svg>
                </button>
            </form>

            <nav class="ovn-header__actions" aria-label="Actions du compte">
                <span class="ovn-header__location">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                    <span>Abidjan, Côte d’Ivoire</span>
                </span>

                <div class="ovn-account" data-account-menu>
                    <button type="button" class="ovn-header__action ovn-account__trigger" aria-expanded="false" aria-controls="ovn-account-panel">
                        <span class="ovn-account__label">
                            @auth
                                <small>{{ $_greeting }},</small><strong>{{ $_clientName }}</strong>
                            @else
                                <small>Bonjour,</small><strong>identifiez-vous</strong>
                            @endauth
                        </span>
                        <svg class="ovn-account__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"></path></svg>
                    </button>
                    <div id="ovn-account-panel" class="ovn-account__panel" role="dialog" aria-label="Menu du compte">
                        @auth
                            <div class="ovn-account__heading">
                                <span class="ovn-account__avatar">{{ mb_strtoupper(mb_substr($_clientName, 0, 1)) }}</span>
                                <div><small>{{ $_greeting }}, {{ $_clientName }}</small><h3>Mon compte OVANIE</h3></div>
                            </div>
                            <p>Gérez votre profil, vos commandes et votre activité sur OVANIE.</p>
                            <a class="ovn-account__primary" href="{{ $_dashboardUrl }}">Mon tableau de bord <span>→</span></a>
                            <div class="ovn-account__links">
                                <a href="{{ $_ordersUrl }}">Mes commandes <span>→</span></a>
                                <a href="{{ $_favoriteUrl }}">Mes favoris <span>→</span></a>
                                <a href="{{ $_disputesUrl }}">Litiges <span>→</span></a>
                                <a href="{{ $_returnsUrl }}">Retours et remboursements <span>→</span></a>
                            </div>
                            <div class="ovn-account__utilities">
                                <a href="{{ $_cartUrl }}">Mon panier</a>
                                <a href="{{ $_trackingUrl }}">Suivre une commande</a>
                                <a href="{{ $_helpUrl }}">Besoin d’aide ?</a>
                            </div>
                            <form method="POST" action="{{ $_logoutUrl }}" class="ovn-account__logout">
                                @csrf
                                <button type="submit">Se déconnecter</button>
                            </form>
                        @else
                            <h3>Bienvenue sur OVANIE</h3>
                            <p>Connectez-vous pour suivre vos commandes, gérer votre panier et accéder à vos avantages.</p>
                            <a class="ovn-account__primary ovn-account__primary--login" href="{{ $_loginUrl }}">Se connecter <span>→</span></a>
                            <div class="ovn-account__register">Nouveau client ? <a href="{{ $_registerUrl }}">Créer un compte</a></div>
                            <div class="ovn-account__utilities ovn-account__utilities--guest">
                                <a href="{{ $_trackingUrl }}">Suivre une commande</a>
                                <a href="{{ $_giftCardsUrl }}">Cartes OVANIE</a>
                                <a href="{{ $_helpUrl }}">Centre d’aide</a>
                            </div>
                        @endauth
                    </div>
                </div>

                <a href="{{ $_favoriteUrl }}" class="ovn-header__action">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.9-8.6a5.5 5.5 0 0 0-.1-7.8Z"></path></svg>
                    <span>Favoris</span>
                </a>

                <a href="{{ $_cartUrl }}" class="ovn-header__action ovn-header__cart ovn-cart-link" aria-label="Panier, {{ $_cartCount }} article(s)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle><path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"></path></svg>
                    <b data-cart-count data-count="{{ $_cartCount }}">{{ $_cartCount }}</b>
                </a>
            </nav>
        </div>
    </div>

    <div class="ovn-header__nav-row">
        <div class="ovn-shell ovn-header__nav-inner">
            <button id="desktopMenuToggle" type="button" class="ovn-all-categories" aria-label="Ouvrir toutes les catégories">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                <span>Toutes les catégories</span>
            </button>

            <nav class="ovn-primary-nav" aria-label="Navigation principale">
                @auth
                    <button type="button" class="ovn-ai-link" data-ai-panel-trigger>
                        <img src="{{ asset('images/ai-assistant/n-nan.webp') }}" alt="N’Nan" class="ovn-ai-link__avatar">
                        <span>IA</span>
                    </button>
                @endauth
                @foreach($_navCategories as $_navCategory)
                    <a href="{{ $_catalogCategoryUrl($_navCategory->slug) }}">{{ $_navCategory->name }}</a>
                @endforeach
                <a href="{{ $_giftCardsUrl }}">Cartes OVANIE</a>
                <a href="{{ $_howToBuyUrl }}">Comment acheter</a>
                <a href="{{ $_guaranteeUrl }}">Garantie acheteur</a>
                <a href="{{ $_vendorTermsUrl }}">Conditions de vente</a>
            </nav>

            <a href="{{ $_sellUrl }}" class="ovn-sell-button">Vendre sur OVANIE</a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-account-menu]').forEach(function (menu) {
        var trigger = menu.querySelector('.ovn-account__trigger');
        if (!trigger) return;
        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            var willOpen = !menu.classList.contains('is-open');
            document.querySelectorAll('[data-account-menu].is-open').forEach(function (item) {
                item.classList.remove('is-open');
                item.querySelector('.ovn-account__trigger')?.setAttribute('aria-expanded', 'false');
            });
            menu.classList.toggle('is-open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });
    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-account-menu].is-open').forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.classList.remove('is-open');
                menu.querySelector('.ovn-account__trigger')?.setAttribute('aria-expanded', 'false');
            }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-account-menu].is-open').forEach(function (menu) {
            menu.classList.remove('is-open');
            menu.querySelector('.ovn-account__trigger')?.setAttribute('aria-expanded', 'false');
        });
    });
});
</script>
