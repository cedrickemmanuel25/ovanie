<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OVANIE Pro — Offres professionnelles BTP</title>
    <meta name="description" content="Offres B2B publiées sur OVANIE Pro : quantités minimales, prix professionnels et délais réels.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/catalog_business.css') }}?v={{ @filemtime(public_path('css/catalog_business.css')) }}">
</head>
<body>
@php
    $icon = function (string $name, string $class = '') {
        $paths = [
            'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
            'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
            'boxes' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'tag' => '<path d="M20 13 13 20l-9-9V4h7l9 9Z"/><circle cx="8.5" cy="8.5" r="1.2"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M18 19c0 1.1-.9 2-2 2h-3"/><rect x="3" y="13" width="4" height="6" rx="2"/><rect x="17" y="13" width="4" height="6" rx="2"/>',
            'file' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h5"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'scale' => '<path d="M12 3v18M5 7h14M7 7l-4 7h8L7 7Zm10 0-4 7h8l-4-7ZM8 21h8"/>',
            'building' => '<path d="M4 21V8l8-5 8 5v13M2 21h20M8 11h2M14 11h2M8 15h2M14 15h2"/>',
            'pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
            'truck' => '<path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        ];
        return '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };
@endphp

<header class="business-header">
    <div class="shell header-row">
        <a class="business-logo" href="{{ route('home') }}" aria-label="Accueil OVANIE">
            <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
        </a>
        <nav class="desktop-nav" aria-label="Navigation principale">
            <a class="active" href="{{ route('catalog.business') }}">OVANIE Pro</a>
            <a href="{{ route('catalog.index') }}">Catalogue OVANIE</a>
            <a href="{{ route('appel.offre') }}">Publier un appel d’offres</a>
            <a href="{{ route('devis.create') }}">Demander un devis</a>
        </nav>
        <div class="header-actions">
            @auth
                <a class="login-link" href="{{ route('dashboard') }}">{!! $icon('user') !!}<span>Mon espace</span></a>
            @else
                <a class="login-link" href="{{ route('login') }}">{!! $icon('user') !!}<span>Se connecter</span></a>
            @endauth
            <a class="header-cta" href="{{ route('appel.offre') }}">Publier un besoin</a>
        </div>
    </div>
</header>

<main>
    <section class="business-hero">
        <div class="shell hero-layout">
            <div class="hero-copy">
                <span class="kicker">OVANIE Pro</span>
                <h1>Des conditions<br>professionnelles<br>pour vos chantiers</h1>
                <p>Achetez en volume, au meilleur prix et dans les délais de vos projets. OVANIE Pro connecte les entreprises BTP à des offres professionnelles publiées.</p>
                <div class="hero-buttons">
                    <a class="button button-orange" href="#offres">Voir les offres disponibles {!! $icon('arrow') !!}</a>
                    <a class="button button-outline" href="{{ route('appel.offre') }}">Publier une demande</a>
                </div>
                <div class="hero-proof">{!! $icon('shield') !!}<span>Plateforme B2B dédiée aux professionnels du BTP</span></div>
            </div>
            <aside class="benefits-panel" aria-label="Avantages OVANIE Pro">
                <div><span>{!! $icon('boxes') !!}</span><b>Quantités minimales adaptées à vos chantiers</b></div>
                <div><span>{!! $icon('tag') !!}</span><b>Prix professionnels publiés</b></div>
                <div><span>{!! $icon('clock') !!}</span><b>Délais annoncés pour vos projets</b></div>
                <div><span>{!! $icon('headset') !!}</span><b>Accompagnement commercial dédié</b></div>
            </aside>
        </div>

        <form class="business-search shell" method="GET" action="{{ route('catalog.business') }}">
            <label class="search-main"><span>Rechercher un produit ou un besoin</span><span class="input-wrap"><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ex. : ciment, fer à béton, peinture…">{!! $icon('search') !!}</span></label>
            <label><span>Catégorie</span><select name="category"><option value="">Toutes</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category'] ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label><span>Quantité disponible</span><input type="number" min="0" step="1" name="minimum_quantity" value="{{ $filters['minimum_quantity'] ?? '' }}" placeholder="Indifférent"></label>
            <label><span>Délai de livraison</span><select name="lead_time"><option value="">Indifférent</option>@foreach($leadTimes as $leadTime)<option value="{{ $leadTime }}" @selected(($filters['lead_time'] ?? '') === $leadTime)>{{ $leadTime }}</option>@endforeach</select></label>
            <label><span>Ville / Zone</span><select name="zone"><option value="">Toutes les zones</option>@foreach($zones as $zone)<option value="{{ $zone }}" @selected(($filters['zone'] ?? '') === $zone)>{{ $zone }}</option>@endforeach</select></label>
            <label><span>Type d’offre</span><select name="offer_type"><option value="">Tous les types</option><option value="catalogue" @selected(($filters['offer_type'] ?? '') === 'catalogue')>Offre catalogue</option><option value="sur_demande" @selected(($filters['offer_type'] ?? '') === 'sur_demande')>Offre sur demande</option></select></label>
            <div class="search-submit"><button type="submit">Rechercher {!! $icon('search') !!}</button>@if(array_filter($filters))<a href="{{ route('catalog.business') }}">Réinitialiser les filtres</a>@endif</div>
        </form>
    </section>

    <section id="offres" class="offers-section">
        <div class="shell">
            <div class="section-heading">
                <div><h2>Offres professionnelles publiées</h2><span>{{ $businessOffers->total() }} {{ Str::plural('offre', $businessOffers->total()) }}</span></div>
                @if($businessOffers->hasPages())<a href="{{ route('catalog.business') }}">Voir toutes les offres {!! $icon('arrow') !!}</a>@endif
            </div>

            @if($businessOffers->isNotEmpty())
                <div class="offer-grid">
                    @foreach($businessOffers as $offer)
                        @php
                            $product = $offer->product;
                            $category = $product?->category?->name;
                            $location = $product?->shop?->commune ?: $product?->shop?->city;
                            $offerUrl = $product ? route('product.show', $product) : route('appel.offre', ['offer_id' => $offer->id]);
                        @endphp
                        <article class="offer-card">
                            <a class="offer-image" href="{{ $offerUrl }}" aria-label="Voir {{ $offer->title }}">
                                @if($product)
                                    <img src="{{ $product->card_image_url }}" alt="{{ $offer->title }}" loading="lazy">
                                @else
                                    <span class="offer-image-empty">{!! $icon('boxes') !!}</span>
                                @endif
                                @if($category)<span class="category-pill">{{ $category }}</span>@endif
                            </a>
                            <div class="offer-body">
                                <h3>{{ $offer->title }}</h3>
                                @if($product?->brand)<p class="brand-name">{{ $product->brand }}</p>@endif
                                <dl>
                                    <div><dt>Quantité minimale</dt><dd>{{ number_format((float)$offer->minimum_quantity, 0, ',', ' ') }} {{ $offer->unit }}</dd></div>
                                    <div><dt>Prix professionnel</dt><dd>{{ number_format((float)$offer->professional_price, 0, ',', ' ') }} FCFA / {{ $offer->unit }}</dd></div>
                                    <div><dt>Délai de livraison</dt><dd>{{ $offer->lead_time }}</dd></div>
                                </dl>
                                @if($location)<p class="offer-location">{!! $icon('pin') !!}<span>{{ $location }}</span></p>@endif
                                <a class="offer-link" href="{{ $offerUrl }}">Voir l’offre</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="business-pagination">{{ $businessOffers->links() }}</div>
            @else
                <div class="business-empty-state" role="status">
                    <span>{!! $icon('boxes') !!}</span>
                    <h3>Aucune offre professionnelle disponible</h3>
                    <p>Les offres publiées apparaîtront ici.</p>
                    @if(array_filter($filters))<a class="button button-orange" href="{{ route('catalog.business') }}">Afficher toutes les offres</a>@else<a class="button button-orange" href="{{ route('appel.offre') }}">Publier un besoin</a>@endif
                </div>
            @endif
        </div>
    </section>

    <section class="steps-section">
        <div class="shell steps-layout">
            <div class="steps-main">
                <h2>Déposer votre besoin <em>en 3 étapes simples</em></h2>
                <div class="steps-grid">
                    <article><span>{!! $icon('file') !!}</span><h3>1. Publiez votre besoin</h3><p>Décrivez votre projet, vos quantités et vos exigences.</p></article>
                    <article><span>{!! $icon('mail') !!}</span><h3>2. Recevez des propositions</h3><p>Les fournisseurs qualifiés peuvent répondre à votre demande.</p></article>
                    <article><span>{!! $icon('scale') !!}</span><h3>3. Comparez et commandez</h3><p>Comparez prix, délais et services avant de décider.</p></article>
                </div>
            </div>
            <aside class="publish-card"><h3>Publiez un besoin en quelques minutes</h3><p>Décrivez clairement votre chantier pour recevoir des propositions adaptées.</p><a class="button button-orange" href="{{ route('appel.offre') }}">Publier mon besoin {!! $icon('arrow') !!}</a></aside>
        </div>
    </section>

    <section class="professionals shell">
        <div class="professionals-copy"><h2>Conçue pour les professionnels du BTP</h2><div class="professional-types"><span>{!! $icon('building') !!}Entreprises de construction</span><span>{!! $icon('building') !!}Promoteurs immobiliers</span><span>{!! $icon('user') !!}Maîtres d’œuvre et bureaux d’études</span><span>{!! $icon('boxes') !!}Collectivités et projets publics</span><span>{!! $icon('tag') !!}Distributeurs et négociants</span></div></div>
    </section>

    <section class="why-section shell">
        <h2>Pourquoi choisir OVANIE Pro ?</h2>
        <div class="why-grid">
            <article>{!! $icon('tag') !!}<div><h3>Tarifs professionnels publiés</h3><p>Les prix affichés proviennent directement des offres actives.</p></div></article>
            <article>{!! $icon('truck') !!}<div><h3>Délais visibles</h3><p>Chaque offre indique son délai communiqué par le fournisseur.</p></div></article>
            <article>{!! $icon('headset') !!}<div><h3>Accompagnement commercial</h3><p>Les demandes sont suivies depuis l’espace OVANIE Pro.</p></div></article>
            <article>{!! $icon('shield') !!}<div><h3>Plateforme sécurisée</h3><p>Les coordonnées privées ne sont jamais exposées dans le catalogue.</p></div></article>
        </div>
    </section>
</main>

<footer class="business-footer">
    <div class="shell footer-grid">
        <div class="footer-brand"><img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE"><p>La place de marché B2B dédiée aux professionnels du BTP.</p></div>
        <div><h3>OVANIE Pro</h3><a href="#offres">Offres disponibles</a><a href="{{ route('appel.offre') }}">Publier un besoin</a><a href="{{ route('devis.create') }}">Demander un devis</a></div>
        <div><h3>Catalogue</h3><a href="{{ route('catalog.index') }}">Catalogue OVANIE</a><a href="{{ route('catalog.business') }}">Offres professionnelles</a></div>
        <div><h3>Support</h3><a href="{{ route('contact.index') }}">Contactez-nous</a><a href="{{ route('faq') }}">FAQ</a><a href="{{ route('cgu') }}">Conditions d’utilisation</a></div>
        <div><h3>Entreprise</h3><a href="{{ route('open-shop') }}">Devenir fournisseur</a><a href="{{ route('home') }}">À propos d’OVANIE</a></div>
    </div>
    <div class="shell footer-bottom"><span>© {{ date('Y') }} OVANIE Pro. Tous droits réservés.</span><a href="{{ route('cgu') }}">Mentions légales</a></div>
</footer>
</body>
</html>
