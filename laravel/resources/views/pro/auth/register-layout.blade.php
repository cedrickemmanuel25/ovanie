<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') · OVANIE PRO</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pro-register.css') }}?v={{ @filemtime(public_path('css/pro-register.css')) ?: '2' }}">
</head>
<body class="opr-body @yield('page_class') {{ $errors->any() ? 'opr-has-errors' : '' }}">
<header class="opr-header">
    <div class="opr-header__inner">
        <div class="opr-header__brand-group">
            <a href="{{ route('catalog.business') }}" class="opr-logo" aria-label="Accueil OVANIE PRO">
                <img src="{{ asset('ovanie pro/logo.png') }}" alt="OVANIE PRO">
            </a>

            @hasSection('brand_note')
                <span class="opr-brand-note">
                    @yield('brand_note')
                </span>
            @endif
        </div>

        <nav class="opr-header__actions" aria-label="Aide et connexion">
            <a href="{{ route('contact.index') }}" class="opr-header-link">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M9.8 9a2.35 2.35 0 0 1 4.55.8c0 1.75-2.35 2-2.35 3.7"></path>
                    <path d="M12 17.2h.01"></path>
                </svg>
                <span>Besoin d’aide&nbsp;?</span>
            </a>

            <span class="opr-header-separator" aria-hidden="true"></span>

            <a href="{{ route('pro.login') }}" class="opr-header-link">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.2"></circle>
                    <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
                </svg>
                <span>Se connecter</span>
            </a>
        </nav>
    </div>
</header>

@yield('content')

<script src="{{ asset('js/pro-register.js') }}?v={{ @filemtime(public_path('js/pro-register.js')) ?: '2' }}" defer></script>
</body>
</html>
