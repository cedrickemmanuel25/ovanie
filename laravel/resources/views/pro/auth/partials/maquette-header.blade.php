<header class="ovp-header">
    <a href="{{ route('catalog.business') }}" class="ovp-brand" aria-label="OVANIE PRO">
        <img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE">
        <span class="ovp-pro">PRO</span>
        <span class="ovp-brandline">La plateforme B2B du secteur BTP<br>en Afrique de l'Ouest</span>
    </a>
    <nav class="ovp-headernav" aria-label="Accès">
        <a href="{{ route('help.center') }}" class="ovp-help"><span class="ovp-round">?</span> Besoin d'aide ?</a>
        <span class="ovp-divider"></span>
        @auth('pro')
            <a href="{{ route('pro.account') }}" class="ovp-loginlink">⌾ Mon espace</a>
        @else
            @if(View::hasSection('header_action'))
                @yield('header_action')
            @else
                <a href="{{ route('pro.login') }}" class="ovp-loginlink">♙ Se connecter</a>
            @endif
        @endauth
    </nav>
</header>