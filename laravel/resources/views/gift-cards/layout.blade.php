<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Cartes OVANIE')</title>
    <meta name="description" content="@yield('meta_description', 'Cartes cadeaux, bons d’achat et cartes virtuelles OVANIE')">
    <style>
        :root{
            --navy:#061a3a;
            --navy-2:#0a2b5f;
            --ink:#0f172a;
            --muted:#667085;
            --line:#e7ecf3;
            --soft:#f6f8fb;
            --orange:#ff5a00;
            --orange-2:#ff7b21;
            --green:#0f9d58;
            --white:#fff;
            --shadow:0 16px 42px rgba(6,26,58,.08);
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;background:#fff;color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
        a{color:inherit}
        button,input,textarea{font:inherit}
        .ov-container{width:min(1460px,94%);margin:0 auto}
        .ov-top{background:linear-gradient(90deg,#05183a,#071f4b);color:#fff}
        .ov-top-inner{min-height:88px;display:grid;grid-template-columns:230px minmax(280px,1fr) auto;gap:34px;align-items:center}
        .ov-logo{display:flex;align-items:center;text-decoration:none;min-width:0}
        .ov-logo img{height:58px;width:auto;max-width:220px;object-fit:contain;filter:drop-shadow(0 4px 10px rgba(0,0,0,.14))}
        .ov-search{position:relative}
        .ov-search input{width:100%;height:54px;border:none;border-radius:13px;padding:0 56px 0 20px;background:#fff;color:#1f2937;box-shadow:0 8px 22px rgba(0,0,0,.10);outline:none}
        .ov-search button{position:absolute;right:7px;top:7px;width:40px;height:40px;border:0;border-radius:10px;background:#fff;color:var(--navy);display:grid;place-items:center;cursor:pointer}
        .ov-actions{display:flex;gap:24px;align-items:center}
        .ov-action{display:flex;align-items:center;gap:9px;color:#fff;text-decoration:none;font-size:14px;white-space:nowrap}
        .ov-action svg{width:25px;height:25px;stroke:currentColor;fill:none;stroke-width:1.8}
        .ov-action small{display:block;color:#cad5e5;font-size:11px}
        .ov-nav{border-bottom:1px solid #e6ebf1;background:#fff;box-shadow:0 4px 14px rgba(6,26,58,.03)}
        .ov-nav-inner{min-height:58px;display:flex;align-items:center;gap:44px;overflow-x:auto;scrollbar-width:none}
        .ov-nav-inner::-webkit-scrollbar{display:none}
        .ov-nav a{font-size:14px;text-decoration:none;color:#15213b;white-space:nowrap;font-weight:600}
        .ov-nav a:hover,.ov-nav a.is-active{color:var(--orange)}
        .ov-all{display:flex!important;align-items:center;gap:9px;font-weight:800!important}
        .ov-all svg{width:20px;height:20px}
        .ov-page{background:linear-gradient(#fff,#f8fafc)}
        .ov-breadcrumb{display:flex;gap:10px;align-items:center;flex-wrap:wrap;color:#667085;font-size:13px;padding:25px 0 12px}
        .ov-breadcrumb a{text-decoration:none;color:#667085}
        .ov-breadcrumb strong{color:var(--orange)}
        .ov-footer{margin-top:70px;background:#06162f;color:#fff}
        .ov-footer-inner{display:grid;grid-template-columns:1.25fr repeat(3,1fr);gap:40px;padding:42px 0}
        .ov-footer h4{margin:0 0 12px;font-size:15px}.ov-footer p,.ov-footer a{font-size:13px;line-height:1.8;color:#cdd7e5;text-decoration:none}.ov-footer a:hover{color:#fff}
        .ov-footer-bottom{border-top:1px solid rgba(255,255,255,.10);padding:15px 0;color:#aebbd0;font-size:12px}
        @media(max-width:1050px){.ov-top-inner{grid-template-columns:190px 1fr}.ov-actions{grid-column:1/-1;justify-content:flex-end;padding-bottom:14px}.ov-top-inner{gap:16px}.ov-nav-inner{gap:26px}.ov-footer-inner{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:720px){.ov-top-inner{grid-template-columns:1fr;padding:14px 0}.ov-logo{justify-content:center}.ov-search{order:3}.ov-actions{grid-column:auto;justify-content:center;gap:18px;padding:0}.ov-action span{display:none}.ov-nav-inner{min-height:52px;gap:22px}.ov-footer-inner{grid-template-columns:1fr}.ov-container{width:min(94%,680px)}}
        @stack('styles')
    </style>
</head>
<body>
<header>
    <div class="ov-top">
        <div class="ov-container ov-top-inner">
            <a href="{{ route('home') }}" class="ov-logo" aria-label="Accueil OVANIE">
                <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
            </a>

            <form class="ov-search" action="{{ route('catalog.index') }}" method="GET" role="search">
                <input type="search" name="search" placeholder="Rechercher un produit, une marque..." aria-label="Rechercher dans OVANIE">
                <button type="submit" aria-label="Rechercher">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.6-3.6"></path></svg>
                </button>
            </form>

            <div class="ov-actions">
                <div class="ov-action" aria-label="Zone de livraison">
                    <svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                    <span>Abidjan, Côte d’Ivoire<small>Zone de livraison</small></span>
                </div>
                @auth
                    <a class="ov-action" href="{{ route('client.dashboard') }}">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                        <span>Mon compte<small>{{ auth()->user()->name }}</small></span>
                    </a>
                    <a class="ov-action" href="{{ route('cart.index') }}">
                        <svg viewBox="0 0 24 24"><path d="M3 4h2l2.3 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"></path><circle cx="10" cy="20" r="1"></circle><circle cx="18" cy="20" r="1"></circle></svg>
                        <span>Panier<small>Voir mon panier</small></span>
                    </a>
                @else
                    <a class="ov-action" href="{{ route('login') }}">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                        <span>Mon compte<small>Se connecter</small></span>
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <nav class="ov-nav" aria-label="Navigation principale">
        <div class="ov-container ov-nav-inner">
            <a class="ov-all" href="{{ route('catalog.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                Toutes les catégories
            </a>
            <a href="{{ url('/catalog?category=materiaux-gros-oeuvre') }}">Matériaux</a>
            <a href="{{ url('/catalog?category=electricite-plomberie') }}">Plomberie</a>
            <a href="{{ url('/catalog?category=outillage-equipement') }}">Outillage</a>
            <a href="{{ url('/catalog?category=electricite-plomberie') }}">Électricité</a>
            <a href="{{ url('/catalog?category=energie-solaire') }}">Énergie solaire</a>
            <a href="{{ route('gift-cards.index') }}" class="is-active">Cartes OVANIE</a>
            <a href="{{ url('/catalog?offer=promo') }}">Promotions</a>
        </div>
    </nav>
</header>

@yield('content')

<footer class="ov-footer">
    <div class="ov-container ov-footer-inner">
        <div><h4>OVANIE</h4><p>Marketplace intelligente BTP en Côte d’Ivoire. Achetez vos matériaux, équipements et solutions digitales dans un environnement sécurisé.</p></div>
        <div><h4>Cartes OVANIE</h4><p><a href="{{ route('gift-cards.category','bon-achat') }}">Bons d’Achat</a><br><a href="{{ route('gift-cards.category','carte-cadeau') }}">Cartes Cadeau</a><br><a href="{{ route('gift-cards.category','carte-virtuelle') }}">Cartes Virtuelles</a></p></div>
        <div><h4>Mon compte</h4><p>@auth<a href="{{ route('client.vouchers') }}">Mes cartes</a><br><a href="{{ route('client.dashboard') }}">Tableau de bord</a>@else<a href="{{ route('login') }}">Se connecter</a>@endauth</p></div>
        <div><h4>Besoin d’aide ?</h4><p>Support OVANIE<br><a href="tel:{{ config('public_contact.phone_e164', '+2250161780000') }}">{{ config('public_contact.phone_display', '01 61 78 00 00') }}</a><br>Assistance avant et après l’achat.</p></div>
    </div>
    <div class="ov-footer-bottom"><div class="ov-container">© {{ date('Y') }} OVANIE — Tous droits réservés.</div></div>
</footer>
@stack('scripts')
</body>
</html>
