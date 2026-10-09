<header class="opx-header">
    <div class="opx-header__inner">
        <a class="opx-brand" href="{{ route('catalog.business') }}" aria-label="Accueil OVANIE PRO">
            <img src="{{ asset('ovanie-pro/logo.svg') }}" alt="OVANIE PRO">
            <span class="opx-brand__tagline">La plateforme B2B du secteur BTP<br>en Afrique de l’Ouest</span>
        </a>
        <nav class="opx-header__actions" aria-label="Navigation du compte">
            <a href="{{ route('help.center') }}" class="opx-header__link">
                <span class="opx-header__circle">?</span>
                <span>Besoin d’aide ?</span>
            </a>
            <span class="opx-header__divider" aria-hidden="true"></span>
            @auth('pro')
                <a href="{{ route('pro.account') }}" class="opx-header__link">@proicon('user', 'opx-icon')<span>Mon espace</span></a>
            @else
                @if(($headerAction ?? 'login') === 'register')
                    <a href="{{ route('pro.register.choice') }}" class="opx-header__link">@proicon('user', 'opx-icon')<span>Créer un compte</span></a>
                @else
                    <a href="{{ route('pro.login') }}" class="opx-header__link">@proicon('user', 'opx-icon')<span>Se connecter</span></a>
                @endif
            @endauth
        </nav>
    </div>
</header>
