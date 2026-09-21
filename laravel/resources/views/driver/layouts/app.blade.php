@php
    $unread = $driver->unreadNotifications()->count();
    $driverNotifications = $driver->unreadNotifications()->latest()->take(8)->get();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Espace livreur | OVANIE')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <link rel="stylesheet" href="{{ asset('css/driver-space.css') }}">
    @stack('styles')
</head>
<body>
<div class="driver-app" id="driver-app">
    <div class="driver-mobile-overlay" id="driver-mobile-overlay"></div>

    <aside class="driver-sidebar" id="driver-sidebar">
        <div class="driver-sidebar__header">
            <a href="{{ route('driver.dashboard') }}" class="driver-sidebar__logo" aria-label="Tableau de bord livreur">
                <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
            </a>
            <button class="driver-sidebar__close" type="button" id="driver-sidebar-close" aria-label="Fermer le menu">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="driver-sidebar__scroll">
            <div class="driver-sidebar__section-label">Missions</div>
            <nav class="driver-sidebar__nav" aria-label="Navigation principale">
                <a href="{{ route('driver.dashboard') }}" class="{{ request()->routeIs('driver.dashboard') ? 'active' : '' }}">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="{{ route('driver.missions.index') }}" class="{{ request()->routeIs('driver.missions.*') ? 'active' : '' }}">
                    <i data-lucide="clipboard-list"></i>
                    <span>Mes missions</span>
                </a>
            </nav>

            <div class="driver-sidebar__section-label">Mon compte</div>
            <nav class="driver-sidebar__nav" aria-label="Gestion du compte">
                <a href="{{ route('driver.profile.edit') }}" class="{{ request()->routeIs('driver.profile.*') ? 'active' : '' }}">
                    <i data-lucide="user-round"></i>
                    <span>Mon profil</span>
                </a>
                <a href="tel:0161781818">
                    <i data-lucide="circle-help"></i>
                    <span>Besoin d’aide</span>
                </a>
            </nav>
        </div>

        <div class="driver-sidebar__footer">
            <div class="driver-sidebar-user">
                <div class="driver-avatar">{{ $driver->initials }}</div>
                <div class="driver-sidebar-user__info">
                    <strong>{{ $driver->name }}</strong>
                    <span>{{ $driver->vehicle ?: 'Livreur OVANIE' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('driver.logout') }}">
                @csrf
                <button class="driver-logout" type="submit">
                    <i data-lucide="log-out"></i>
                    <span>Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="driver-workspace">
        <header class="driver-topbar">
            <div class="driver-topbar__left">
                <button class="driver-menu-toggle" type="button" id="driver-menu-toggle" aria-label="Ouvrir le menu">
                    <i data-lucide="menu"></i>
                </button>
                <div class="driver-topbar__title">
                    <span>ESPACE LIVREUR</span>
                    <strong>@yield('page-title', 'Tableau de bord')</strong>
                </div>
            </div>

            <div class="driver-topbar__actions">
                <a href="tel:0161781818" class="driver-topbar-help">
                    <i data-lucide="circle-help"></i>
                    <span>Aide</span>
                </a>

                <div class="driver-popover-wrap">
                    <button type="button" class="driver-icon-button" id="driver-notification-toggle" aria-label="Notifications">
                        <i data-lucide="bell"></i>
                        @if($unread)
                            <span class="driver-notification-count">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </button>
                    <div class="driver-popover driver-notification-popover" id="driver-notification-menu">
                        <div class="driver-popover__head">
                            <div>
                                <strong>Notifications</strong>
                                <span>{{ $unread }} non lue(s)</span>
                            </div>
                            <i data-lucide="bell-ring"></i>
                        </div>
                        <div class="driver-popover__body">
                            @forelse($driverNotifications as $notification)
                                <article class="driver-notification-item">
                                    <span class="driver-notification-item__icon"><i data-lucide="truck"></i></span>
                                    <div>
                                        <strong>{{ data_get($notification->data, 'title', 'Nouvelle notification') }}</strong>
                                        <p>{{ data_get($notification->data, 'message', '') }}</p>
                                        @if(data_get($notification->data, 'url'))
                                            <a href="{{ data_get($notification->data, 'url') }}">Consulter</a>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="driver-empty driver-empty--compact">
                                    <i data-lucide="bell-off"></i>
                                    <strong>Aucune nouvelle notification</strong>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="driver-popover-wrap">
                    <button type="button" class="driver-profile-trigger" id="driver-profile-toggle">
                        <div class="driver-avatar">{{ $driver->initials }}</div>
                        <div>
                            <strong>{{ $driver->name }}</strong>
                            <span>{{ $driver->status ?: 'Disponible' }}</span>
                        </div>
                        <i data-lucide="chevron-down"></i>
                    </button>
                    <div class="driver-popover driver-profile-popover" id="driver-profile-menu">
                        <a href="{{ route('driver.profile.edit') }}"><i data-lucide="user-round"></i> Mon profil</a>
                        <a href="{{ route('driver.missions.index') }}"><i data-lucide="clipboard-list"></i> Mes missions</a>
                        <div class="driver-popover__separator"></div>
                        <form method="POST" action="{{ route('driver.logout') }}">
                            @csrf
                            <button type="submit"><i data-lucide="log-out"></i> Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="driver-main">
            <div class="driver-content">
                @if(session('success'))
                    <div class="driver-alert driver-alert--success">
                        <i data-lucide="circle-check"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if($errors->any())
                    <div class="driver-alert driver-alert--danger">
                        <i data-lucide="circle-alert"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.lucide?.createIcons();

    const app = document.getElementById('driver-app');
    const menuToggle = document.getElementById('driver-menu-toggle');
    const menuClose = document.getElementById('driver-sidebar-close');
    const overlay = document.getElementById('driver-mobile-overlay');

    const closeSidebar = () => app?.classList.remove('driver-menu-open');
    menuToggle?.addEventListener('click', () => app?.classList.add('driver-menu-open'));
    menuClose?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    const pairs = [
        ['driver-notification-toggle', 'driver-notification-menu'],
        ['driver-profile-toggle', 'driver-profile-menu'],
    ];

    pairs.forEach(([buttonId, menuId]) => {
        const button = document.getElementById(buttonId);
        const menu = document.getElementById(menuId);
        button?.addEventListener('click', event => {
            event.stopPropagation();
            pairs.forEach(([, otherMenuId]) => {
                if (otherMenuId !== menuId) document.getElementById(otherMenuId)?.classList.remove('open');
            });
            menu?.classList.toggle('open');
        });
    });

    document.addEventListener('click', event => {
        pairs.forEach(([buttonId, menuId]) => {
            const button = document.getElementById(buttonId);
            const menu = document.getElementById(menuId);
            if (menu && button && !menu.contains(event.target) && !button.contains(event.target)) {
                menu.classList.remove('open');
            }
        });
    });
});
</script>
@stack('scripts')
</body>
</html>
