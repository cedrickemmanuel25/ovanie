@php($proUser = auth('pro')->user())
<header class="pa-top">
    <div class="pa-top__inner">
        <a href="{{ route('catalog.business') }}" class="pa-brand" aria-label="Accueil OVANIE Pro">
            <img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE"><span class="pa-badge">PRO</span>
        </a>
        <div class="pa-top__user">
            <a href="{{ route('pro.account') }}" class="pa-avatar" title="Mon compte">{{ mb_strtoupper(mb_substr($proUser->first_name ?: $proUser->name, 0, 1)) }}</a>
            <span class="pa-top__name">{{ $proUser->full_name }}</span>
            <form method="POST" action="{{ route('pro.logout') }}" id="pa-logout">@csrf
                <button type="submit" class="pa-btn pa-btn--ghost pa-btn--sm">Déconnexion</button>
            </form>
        </div>
    </div>
</header>
