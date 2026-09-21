<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Espace client') - OVANIE</title>
    <link rel="stylesheet" href="{{ asset('css/client-space.css') }}?v={{ @filemtime(public_path('css/client-space.css')) }}">
    @stack('styles')
</head>
@php
    $me = auth()->user();
    $hasShop = $me && method_exists($me, 'shop') && $me->shop()->exists();
    $cartCount = $me?->cart?->items()->sum('quantity') ?? 0;
    $favoriteCount = method_exists($me, 'favorites') ? $me->favorites()->count() : 0;
    try { $unreadCount = $me?->unreadNotifications()->count() ?? 0; } catch (\Exception $e) { $unreadCount = 0; }
    $activeOrders = $me?->orders()->whereIn('status', ['pending', 'paid', 'shipped'])->count() ?? 0;
    $initials = collect(explode(' ', $me->full_name ?: $me->name ?: 'Client'))->filter()->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
@endphp
<body>
<div class="cs-app" data-client-app>
    <header class="cs-topbar">
        <div class="cs-brand">
            <button class="cs-menu-button" type="button" data-sidebar-toggle aria-label="Ouvrir le menu">
                <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ Route::has('home') ? route('home') : url('/') }}" class="cs-logo" aria-label="Retour à l’accueil">
                <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE" class="cs-logo-img">
            </a>
        </div>
        <form class="cs-search" action="{{ Route::has('search') ? route('search') : route('catalog.index') }}" method="GET">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input name="search" placeholder="Rechercher un produit, une catégorie, une marque...">
            <button type="submit">Rechercher</button>
        </form>
        <div class="cs-top-actions">
            <a class="cs-top-icon" href="{{ Route::has('home') ? route('home') : url('/') }}" aria-label="Retour à l’accueil" title="Accueil">
                <svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg>
                <span>Accueil</span>
            </a>
            <a class="cs-top-icon" href="{{ route('client.notifications') }}" aria-label="Notifications" title="Notifications">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                @if($unreadCount)<b>{{ $unreadCount }}</b>@endif
            </a>
            <button class="cs-city" type="button">
                <svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg>
                <span>{{ $me->city ?: 'Abidjan' }}</span>
            </button>
            <div class="cs-account" data-account-menu>
                <button class="cs-user" type="button" data-account-toggle aria-expanded="false" aria-haspopup="true">
                    @if($me->avatar)
                        <img src="{{ asset('storage/'.$me->avatar) }}" alt="Photo de {{ $me->first_name ?: $me->name }}">
                    @else
                        <span>{{ $initials ?: 'C' }}</span>
                    @endif
                    <small>Bonjour,</small><strong>{{ $me->first_name ?: $me->name }}</strong>
                    <svg class="cs-account-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="cs-account-menu" data-account-dropdown hidden>
                    <div class="cs-account-head">
                        <strong>{{ $me->full_name ?: $me->name }}</strong>
                        <small>{{ $me->email }}</small>
                    </div>
                    <a href="{{ Route::has('home') ? route('home') : url('/') }}"><span>Retour à l’accueil</span></a>
                    <a href="{{ route('client.dashboard') }}"><span>Tableau de bord</span></a>
                    <a href="{{ route('client.settings') }}"><span>Mon profil et paramètres</span></a>
                    <a href="{{ route('client.orders') }}"><span>Mes commandes</span></a>
                    @if($hasShop)
                        <a href="{{ route('vendor.dashboard') }}"><span>Passer en mode vendeur</span></a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit">Se déconnecter</button>
                    </form>
                </div>
            </div>
            <a class="cs-icon-link" href="{{ route('client.favorites') }}" aria-label="Favoris">
                <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg>
                <span>Favoris</span><b>{{ $favoriteCount }}</b>
            </a>
            <a class="cs-icon-link" href="{{ route('cart.index') }}" aria-label="Panier">
                <svg viewBox="0 0 24 24"><path d="M3 3h2l2.5 11h10l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                <span>Panier</span><b>{{ $cartCount }}</b>
            </a>
        </div>
    </header>

    <div class="cs-overlay" data-sidebar-overlay></div>
    <aside class="cs-sidebar" data-sidebar>
        <nav>
            <a href="{{ Route::has('home') ? route('home') : url('/') }}">
                <svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg><span>Retour à l’accueil</span>
            </a>
            <a class="{{ request()->routeIs('client.dashboard') ? 'active' : '' }}" href="{{ route('client.dashboard') }}">
                <svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg><span>Tableau de bord</span>
            </a>
            <a class="{{ request()->routeIs('client.orders*') ? 'active' : '' }}" href="{{ route('client.orders') }}">
                <svg viewBox="0 0 24 24"><path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span>Mes commandes</span>@if($activeOrders)<b>{{ $activeOrders }}</b>@endif
            </a>
            <a class="{{ request()->routeIs('client.returns*') ? 'active' : '' }}" href="{{ route('client.returns') }}">
                <svg viewBox="0 0 24 24"><path d="M7 7h11a4 4 0 0 1 0 8H9"/><path d="M7 7l4-4M7 7l4 4"/><path d="M5 17h7"/><path d="M5 21h11"/></svg><span>Retour & reclamation</span>
            </a>
            <a class="{{ request()->routeIs('client.vouchers*') ? 'active' : '' }}" href="{{ route('client.vouchers') }}">
                <svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13"/><path d="M3 12h18"/><path d="M7.5 8A2.5 2.5 0 1 1 12 6.5V8"/><path d="M16.5 8A2.5 2.5 0 1 0 12 6.5V8"/></svg><span>Cartes cadeaux & avantages</span>
            </a>
            <a class="{{ request()->routeIs('client.favorites*') ? 'active' : '' }}" href="{{ route('client.favorites') }}">
                <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg><span>Mes favoris</span>@if($favoriteCount)<b>{{ $favoriteCount }}</b>@endif
            </a>
            <a class="{{ request()->routeIs('client.addresses*') ? 'active' : '' }}" href="{{ route('client.addresses') }}">
                <svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg><span>Mes adresses</span>
            </a>
            <a class="{{ request()->routeIs('client.payments*') ? 'active' : '' }}" href="{{ route('client.payments') }}">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg><span>Mes moyens de paiement</span>
            </a>
            <a class="{{ request()->routeIs('client.cgu') ? 'active' : '' }}" href="{{ route('client.cgu') }}">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8M8 9h2"/></svg><span>CGU</span>
            </a>
            <a class="{{ request()->routeIs('client.notifications*') ? 'active' : '' }}" href="{{ route('client.notifications') }}">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><span>Notifications</span>@if($unreadCount)<b class="danger">{{ $unreadCount }}</b>@endif
            </a>
            <a class="{{ request()->routeIs('client.settings*') ? 'active' : '' }}" href="{{ route('client.settings') }}">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg><span>Paramètres</span>
            </a>
            @if($hasShop)
                <a href="{{ route('vendor.dashboard') }}">
                    <svg viewBox="0 0 24 24"><path d="M3 9l2-5h14l2 5"/><path d="M5 13v8h14v-8"/><path d="M9 21v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg><span>Espace vendeur</span>
                </a>
            @endif
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="cs-logout">@csrf
            <button><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M15 3h5a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-5"/></svg>Déconnexion</button>
        </form>
    </aside>

    <main class="cs-main">
        @if(session('success'))<div class="cs-toast success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="cs-toast error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="cs-toast error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/client-space.js') }}?v={{ @filemtime(public_path('js/client-space.js')) }}"></script>
@stack('scripts')
</body>
</html>
