<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    @php
        $siteName = function_exists('setting') ? setting('siteName', 'OVANIE') : config('app.name', 'OVANIE');

        $seoTitle = trim($__env->yieldContent('title', $siteName));
        $seoDescription = trim($__env->yieldContent(
            'meta_description',
            'OVANIE vous propose des matériaux de construction, équipements BTP et solutions adaptées en Côte d’Ivoire.'
        ));
        $seoKeywords = trim($__env->yieldContent(
            'meta_keywords',
            'OVANIE, matériaux de construction, BTP, marketplace, Côte d’Ivoire, ciment, fer, carrelage, outillage'
        ));
        $seoImage = trim($__env->yieldContent(
            'meta_image',
            asset('storage/logos/officiel site.png')
        ));
        $seoUrl = trim($__env->yieldContent('canonical', url()->current()));
    @endphp

    <title>{{ $seoTitle }}</title>

    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ $seoKeywords }}">
    <meta name="robots" content="@yield('meta_robots', 'index,follow')">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ $seoUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <link rel="stylesheet" href="{{ asset('css/baniere.css') }}?v={{ file_exists(public_path('css/baniere.css')) ? filemtime(public_path('css/baniere.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ file_exists(public_path('css/responsive.css')) ? filemtime(public_path('css/responsive.css')) : time() }}">

    <style>
        :root {
            --ov-night: #020b1c;
            --ov-night-2: #06142b;
            --ov-blue: #0797df;
            --ov-blue-2: #024fc8;
            --ov-orange: #ff6a00;
            --ov-orange-2: #ff7a1a;
            --ov-red: #e52320;
            --ov-green: #22c55e;
            --ov-bg: #eef3f8;
            --ov-card: #ffffff;
            --ov-text: #101827;
            --ov-muted: #64748b;
            --ov-border: #e5e7eb;
            --ov-shadow: 0 16px 38px rgba(2, 11, 28, .14);
            --ov-radius: 14px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            background: var(--ov-bg);
            color: var(--ov-text);
            font-family: Inter, Arial, Helvetica, sans-serif;
            overflow-x: hidden;
        }

        body.menu-open {
            overflow: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }

        img {
            max-width: 100%;
        }

        .ov-page-main {
            background: var(--ov-bg);
        }

        .ov-wrap {
            width: min(100% - 48px, 1280px);
            margin: 0 auto;
        }

        .ov-scroll-top {
            display: block;
            width: 100%;
            border: 0;
            background: #e9eef6;
            color: #0f172a;
            text-align: center;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
            transition: background .2s ease, color .2s ease;
        }

        .ov-scroll-top:hover {
            background: #dbe4ef;
            color: #020b1c;
        }

        .ov-mobile-overlay {
            position: fixed;
            inset: 0;
            z-index: 4000;
            background: rgba(2, 11, 28, .55);
            opacity: 0;
            visibility: hidden;
            transition: opacity .2s ease, visibility .2s ease;
        }

        .ov-mobile-overlay.is-active {
            opacity: 1;
            visibility: visible;
        }

        .ov-mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 4100;
            width: 330px;
            max-width: 88vw;
            background: #fff;
            color: #0f172a;
            transform: translateX(-105%);
            transition: transform .25s ease;
            box-shadow: var(--ov-shadow);
            padding: 16px;
            overflow-y: auto;
        }

        .ov-mobile-menu.is-open {
            transform: translateX(0);
        }

        .ov-mobile-menu__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }

        .ov-mobile-menu__top strong {
            font-size: 17px;
            font-weight: 950;
        }

        .ov-mobile-menu__close {
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: #fff;
            cursor: pointer;
            font-size: 18px;
            font-weight: 900;
        }

        .ov-mobile-search {
            display: grid;
            grid-template-columns: 1fr 46px;
            height: 44px;
            border: 1px solid var(--ov-border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #fff;
        }

        .ov-mobile-search input {
            min-width: 0;
            border: 0;
            outline: 0;
            padding: 0 13px;
            font-size: 14px;
        }

        .ov-mobile-search button {
            border: 0;
            background: var(--ov-orange);
            color: #fff;
            cursor: pointer;
            display: grid;
            place-items: center;
        }

        .ov-mobile-search button svg {
            width: 18px;
            height: 18px;
        }

        .ov-mobile-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 4px;
            border-bottom: 1px solid var(--ov-border);
            font-size: 14px;
            font-weight: 850;
        }

        .ov-mobile-link span:first-child {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .ov-mobile-link i {
            width: 17px;
            height: 17px;
            color: var(--ov-orange);
            stroke-width: 2.3;
        }

        .ov-footer {
            background:
                radial-gradient(circle at 8% 0%, rgba(7, 151, 223, .14), transparent 28%),
                linear-gradient(180deg, #06142b 0%, #020b1c 100%);
            color: #fff;
        }

        .ov-footer__inner {
            width: min(100% - 48px, 1280px);
            margin: 0 auto;
            padding: 24px 0 16px;
            display: grid;
            grid-template-columns: 1.35fr .8fr .95fr .95fr .9fr;
            gap: 26px;
            align-items: start;
        }

        .ov-footer__brand img {
            width: 135px;
            height: auto;
            display: block;
            margin-bottom: 10px;
        }

        .ov-footer__brand p {
            margin: 0 0 10px;
            color: #d7e1ee;
            font-size: 12.5px;
            line-height: 1.45;
            max-width: 330px;
        }

        .ov-footer__contact {
            display: grid;
            gap: 6px;
            color: #c8d4e3;
            font-size: 12.5px;
            line-height: 1.35;
        }

        .ov-footer__contact a,
        .ov-footer__contact span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .ov-footer__icon {
            width: 15px;
            height: 15px;
            stroke-width: 2.2;
            color: var(--ov-orange);
            flex: 0 0 auto;
        }

        .ov-footer__socials {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .ov-footer__socials a {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255,255,255,.08);
            color: #fff;
            transition: background .2s ease, transform .2s ease;
        }

        .ov-footer__socials a:hover {
            background: var(--ov-orange);
            transform: translateY(-2px);
        }

        .ov-footer__socials a svg {
            width: 16px;
            height: 16px;
            display: block;
            fill: currentColor;
        }

        .ov-footer__col h4 {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 950;
            color: #fff;
        }

        .ov-footer__col nav {
            display: grid;
            gap: 7px;
        }

        .ov-footer__col a {
            color: #c8d4e3;
            font-size: 12.5px;
            line-height: 1.25;
            transition: color .2s ease;
        }

        .ov-footer__col a:hover {
            color: #fff;
        }

        .ov-footer__payments {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 9px;
        }

        .ov-footer__payments img {
            height: 23px;
            width: auto;
            max-width: 52px;
            object-fit: contain;
            background: #fff;
            border-radius: 5px;
            padding: 3px 5px;
        }

        .ov-footer__bottom {
            border-top: 1px solid rgba(255,255,255,.1);
            width: min(100% - 48px, 1280px);
            margin: 0 auto;
            padding: 10px 0 12px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            color: #9caec5;
            font-size: 11.5px;
        }

        .ov-footer__bottom-links {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
        }

        .ov-footer__bottom a:hover {
            color: #fff;
        }

        .ov-whatsapp-float {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 2500;
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: #22c55e;
            color: #fff;
            box-shadow: 0 14px 34px rgba(34, 197, 94, .42);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .ov-whatsapp-float:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 18px 44px rgba(34, 197, 94, .5);
        }

        .ov-whatsapp-float svg {
            width: 28px;
            height: 28px;
            stroke-width: 2.3;
        }

        @media (max-width: 1100px) {
            .ov-footer__inner {
                grid-template-columns: 1.4fr 1fr 1fr;
            }
        }

        @media (max-width: 760px) {
            .ov-wrap,
            .ov-footer__inner,
            .ov-footer__bottom {
                width: min(100% - 28px, 680px);
            }

            .ov-footer__inner {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px 10px !important;
                padding: 14px 0 8px !important;
            }

            .ov-footer__brand {
                grid-column: 1 / -1 !important;
                text-align: center !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
            }

            .ov-footer__brand img {
                width: 95px !important;
                margin-bottom: 6px !important;
            }

            .ov-footer__brand p {
                display: none !important;
            }

            .ov-footer__contact {
                justify-content: center !important;
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 4px 10px !important;
                font-size: 9.5px !important;
            }

            .ov-footer__socials {
                justify-content: center !important;
                margin-top: 6px !important;
                gap: 6px !important;
            }

            .ov-footer__socials a {
                width: 24px !important;
                height: 24px !important;
            }

            .ov-footer__socials a svg {
                width: 12px !important;
                height: 12px !important;
            }

            .ov-footer__col {
                text-align: left !important;
            }

            .ov-footer__col h4 {
                font-size: 10px !important;
                margin-bottom: 4px !important;
            }

            .ov-footer__col nav {
                gap: 4px !important;
            }

            .ov-footer__col a {
                font-size: 9px !important;
            }

            .ov-footer__payments {
                margin-top: 4px !important;
                gap: 3px !important;
                justify-content: flex-start !important;
            }

            .ov-footer__payments img {
                height: 12px !important;
            }

            .ov-footer__bottom {
                flex-direction: column;
                text-align: center;
                align-items: center;
                font-size: 9px !important;
                padding: 6px 0 8px !important;
            }

            .ov-footer__bottom-links {
                justify-content: center;
                gap: 8px !important;
            }

            .ov-whatsapp-float {
                right: 16px;
                bottom: 16px;
                width: 50px;
                height: 50px;
            }
        }
    </style>

    @stack('head')
    @yield('styles')
    @stack('styles')
    {{-- Navbar publique unifiée : même rendu que la page d'accueil sur toutes les pages --}}
    <link rel="stylesheet" href="{{ asset('css/navbar.css') }}?v={{ file_exists(public_path('css/navbar.css')) ? filemtime(public_path('css/navbar.css')) : 1 }}">
    {{-- Normalisation universelle des images cartes produit (chargé en dernier pour priorité maximale) --}}
    <link rel="stylesheet" href="{{ asset('css/product-card-normalize.css') }}?v={{ file_exists(public_path('css/product-card-normalize.css')) ? filemtime(public_path('css/product-card-normalize.css')) : 1 }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}?v={{ file_exists(public_path('css/footer.css')) ? filemtime(public_path('css/footer.css')) : 1 }}">
    <link rel="stylesheet" href="{{ asset('css/category-drawer.css') }}?v={{ file_exists(public_path('css/category-drawer.css')) ? filemtime(public_path('css/category-drawer.css')) : 1 }}">
</head>

<body>

    @include('layouts._navbar')

    @include('layouts.partials.category-drawer')

    <main class="ov-page-main">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </main>

    <button type="button" class="ov-scroll-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        ▲ Retour en haut
    </button>

    @include('layouts._footer')

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Le bouton mobile #menuToggle est géré exclusivement par _navbar.blade.php.
            // Ici, l'ancien panneau Catégories reste réservé au bouton desktop.
            const desktopMenuToggle = document.getElementById("desktopMenuToggle");
            const closeMobileMenu = document.getElementById("closeMobileMenu");
            const mobileSideMenu = document.getElementById("mobileSideMenu");
            const mobileMenuOverlay = document.getElementById("mobileMenuOverlay");

            function openMenu() {
                if (!mobileSideMenu || !mobileMenuOverlay) return;

                mobileSideMenu.classList.add("is-open");
                mobileMenuOverlay.classList.add("is-active");
                mobileSideMenu.setAttribute("aria-hidden", "false");
                document.body.classList.add("menu-open");
            }

            function closeMenu() {
                if (!mobileSideMenu || !mobileMenuOverlay) return;

                mobileSideMenu.classList.remove("is-open");
                mobileMenuOverlay.classList.remove("is-active");
                mobileSideMenu.setAttribute("aria-hidden", "true");
                document.body.classList.remove("menu-open");
            }

            if (desktopMenuToggle) {
                desktopMenuToggle.addEventListener("click", function () {
                    // Le bouton "Toutes les catégories" ouvre le tiroir de
                    // navigation à toutes les tailles d'écran : sur mobile,
                    // c'est aussi l'unique point d'accès aux catégories une
                    // fois que le menu principal est masqué (cf. navbar.css).
                    openMenu();
                });
            }

            if (closeMobileMenu) {
                closeMobileMenu.addEventListener("click", closeMenu);
            }

            if (mobileMenuOverlay) {
                mobileMenuOverlay.addEventListener("click", closeMenu);
            }

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape") {
                    closeMenu();
                }
            });
        });
    </script>

    @yield('scripts')

    <script src="{{ asset('js/main.js') }}?v={{ file_exists(public_path('js/main.js')) ? filemtime(public_path('js/main.js')) : time() }}" defer></script>
    <script src="{{ asset('js/category-drawer.js') }}?v={{ file_exists(public_path('js/category-drawer.js')) ? filemtime(public_path('js/category-drawer.js')) : time() }}" defer></script>

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
