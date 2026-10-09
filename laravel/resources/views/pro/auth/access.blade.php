{{--
    Mise en page des écrans d'accès OVANIE PRO conformes aux maquettes :
    Connexion, Mot de passe oublié, Réinitialisation, Invitation collaborateur, Fonctions (étape 4).
--}}
<!DOCTYPE html>
<html lang="fr" class="@yield('html_class')">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · OVANIE PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    {{-- Les écrans sont dessinés sur la grille 1536 × 1024 des maquettes, puis mis à l'échelle
         de la fenêtre : rendu identique et aucune barre de défilement sur ordinateur. --}}
    <script>
        (function () {
            var root = document.documentElement;
            // Mise à l'échelle sur la grille 1536 × 1024 des maquettes. La hauteur réelle de la page est
            // mesurée : si elle dépasse (erreurs affichées, champs ouverts), la page réduit légèrement
            // au lieu d'afficher une barre de défilement ou de couper le bas.
            function fit() {
                // Formulaires denses : pas de mise à l'échelle, la page garde ses tailles réelles et défile.
                if (root.classList.contains('oa-natural')) { root.style.zoom = ''; return; }
                if (window.innerWidth < 1000 || window.innerHeight < 560) { root.style.zoom = ''; root.classList.remove('oa-fit'); return; }
                root.classList.add('oa-fit');
                // Formulaires longs (offre / besoin, vérification) : mise à l'échelle sur la largeur seulement,
                // la page défile normalement pour garder des champs lisibles.
                root.style.zoom = 1;
                root.classList.remove('oa-scroll');
                var height = Math.max(1024, document.body ? document.body.scrollHeight : 1024);
                // Page plus haute que la maquette : on garde des champs lisibles et la page défile.
                var scroll = !!document.querySelector('.op-wide') || height > 1110;
                root.classList.toggle('oa-scroll', scroll);
                var scale = scroll ? window.innerWidth / 1536 : Math.min(window.innerWidth / 1536, window.innerHeight / height);
                root.style.zoom = Math.max(.5, Math.min(scale, 1.35)).toFixed(4);
            }
            var queued = false;
            function refit() { if (queued) return; queued = true; requestAnimationFrame(function () { queued = false; fit(); }); }
            fit();
            window.addEventListener('resize', refit);
            document.addEventListener('DOMContentLoaded', function () {
                fit();
                if (window.ResizeObserver) new ResizeObserver(refit).observe(document.body);
                if (document.fonts && document.fonts.ready) document.fonts.ready.then(refit);
            });
            window.addEventListener('load', refit);
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/pro-access-auth.css') }}?v={{ @filemtime(public_path('css/pro-access-auth.css')) ?: '1' }}">
    @stack('styles')
</head>
<body class="oa-body @yield('body_class')">
    @include('pro.auth.access.header')
    <main class="oa-main">
        <div class="oa-container @yield('container_class')">
            @yield('content')
        </div>
    </main>
    <script src="{{ asset('js/pro-access-auth.js') }}?v={{ @filemtime(public_path('js/pro-access-auth.js')) ?: '1' }}" defer></script>
    <script src="{{ asset('js/pro-select.js') }}?v={{ @filemtime(public_path('js/pro-select.js')) ?: '1' }}" defer></script>
</body>
</html>
