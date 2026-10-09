<header class="opx-header">
    <div class="opx-header__inner">
        <a class="opx-brand" href="{{ url('/') }}" aria-label="OVANIE PRO">
            <img src="{{ asset('ovanie-pro/logo.svg') }}" alt="OVANIE PRO" class="opx-brand__logo">
            <span class="opx-brand__divider" aria-hidden="true"></span>
            <span class="opx-brand__tagline">La plateforme B2B du secteur BTP<br>en Afrique de l’Ouest</span>
        </a>
        <nav class="opx-header__nav" aria-label="Navigation d'accès">
            <a class="opx-header__link" href="{{ route('contact.index') }}">
                <span class="opx-header__round">?</span>
                <span>Besoin d’aide ?</span>
            </a>
            <span class="opx-header__sep" aria-hidden="true"></span>
            @hasSection('header_action')
                @yield('header_action')
            @else
                <a class="opx-header__link" href="{{ route('pro.login') }}">@proicon('user', 'opx-svg')<span>Se connecter</span></a>
            @endif
        </nav>
    </div>
</header>
