<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') · OVANIE Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pro-access.css') }}?v={{ @filemtime(public_path('css/pro-access.css')) ?: '1' }}">
</head>
<body class="pa-body @yield('body_class')">
@hasSection('full_canvas')
    @yield('full_canvas')
@else
<div class="pa-split">
    <aside class="pa-aside">
        <a class="pa-aside__brand" href="{{ route('catalog.business') }}">OVANIE <b>PRO</b></a>
        <div style="position:relative;z-index:1">
            @hasSection('aside')
                @yield('aside')
            @else
                <h2>La plateforme B2B du bâtiment et de l’industrie</h2>
                <p>Fabricants, grossistes, entreprises BTP et prestataires : achetez, vendez et répondez aux appels d’offres entre professionnels.</p>
                <ul class="pa-points">
                    <li>@proicon('shield')<div><strong>Profils vérifiés</strong><span>Chaque entreprise est contrôlée par notre équipe avant d’être certifiée.</span></div></li>
                    <li>@proicon('file')<div><strong>Devis et appels d’offres</strong><span>Publiez vos besoins et recevez des propositions de fournisseurs qualifiés.</span></div></li>
                    <li>@proicon('users')<div><strong>Travail en équipe</strong><span>Invitez vos collaborateurs avec des accès adaptés à leur fonction.</span></div></li>
                </ul>
            @endif
        </div>
        <div class="pa-aside__foot">
            <span>@proicon('lock') Documents privés</span>
            <span>@proicon('shield') Vérification manuelle</span>
            <span>@proicon('globe') Afrique de l’Ouest et international</span>
        </div>
    </aside>

    <div class="pa-panel-main">
        <div class="pa-panel-main__top">
            <a href="{{ route('catalog.business') }}" class="pa-brand" aria-label="Accueil OVANIE Pro">
                <img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE"><span class="pa-badge">PRO</span>
            </a>
            <span class="pa-small">@yield('top_link')</span>
        </div>
        <main class="pa-panel-main__body">
            <div class="pa-form-wrap @yield('wrap')">
                @yield('content')
            </div>
        </main>
        <footer class="pa-legal">
            &copy; {{ date('Y') }} OVANIE Pro
            <a href="{{ route('terms') }}">Conditions d’utilisation</a>
            <a href="{{ route('privacy-policy') }}">Confidentialité</a>
        </footer>
    </div>
</div>
@endif
<script src="{{ asset('js/pro-access.js') }}?v={{ @filemtime(public_path('js/pro-access.js')) ?: '1' }}" defer></script>
</body>
</html>
