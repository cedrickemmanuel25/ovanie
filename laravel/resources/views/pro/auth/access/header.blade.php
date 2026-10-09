@php
    $headerUser = auth('pro')->user();
@endphp
<header class="oa-header {{ View::hasSection('header_dark') ? 'oa-header--dark' : '' }}">
    <div class="oa-header__inner">
        <a class="oa-header__brand" href="{{ route('catalog.business') }}" aria-label="Accueil OVANIE PRO">
            @include('pro.auth.access.logo', ['size' => 'md', 'light' => View::hasSection('header_dark')])
            <span class="oa-header__tagline">La plateforme B2B du secteur BTP<br>en Afrique de l’Ouest</span>
        </a>
        <nav class="oa-header__nav" aria-label="Liens utiles">
            <a class="oa-header__link" href="{{ route('contact.index') }}">@proicon('help', 'oa-ico')<span>Besoin d’aide ?</span></a>
            <span class="oa-header__sep" aria-hidden="true"></span>
            @if($headerUser)
                <span class="oa-header__user">@proicon('user', 'oa-ico')<span>{{ $headerUser->full_name }}</span></span>
                <form method="POST" action="{{ route('pro.logout') }}" id="oa-logout">@csrf
                    <button class="oa-header__link oa-header__logout" type="submit">Déconnexion</button>
                </form>
            @else
                @hasSection('header_action')
                    @yield('header_action')
                @else
                    <a class="oa-header__link" href="{{ route('pro.login') }}">@proicon('user', 'oa-ico')<span>Se connecter</span></a>
                @endif
            @endif
        </nav>
    </div>
</header>
