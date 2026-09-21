<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark" aria-hidden="true">O</div>
        <div class="sidebar-logo-copy">
            <strong>OVANIE Admin</strong>
            <span>Pilotage marketplace</span>
        </div>
    </div>

    <nav class="sidebar-menu" aria-label="Navigation administration">
        <p class="sidebar-section">Vue générale</p>

        <a href="{{ route('admin.dashboard') }}"
           class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" />
            </svg>
            <span>Tableau de bord</span>
        </a>

        <p class="sidebar-section">Commerce</p>

        <a href="{{ route('admin.orders.index') }}"
           class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6M9 16h4" />
            </svg>
            <span>Commandes</span>
        </a>

        <a href="{{ route('admin.products.index') }}"
           class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 0v9m8-4.5-8 4.5-8-4.5" />
            </svg>
            <span>Produits</span>
        </a>

        <a href="{{ route('admin.gift-cards.index') }}"
           class="{{ request()->routeIs('admin.gift-cards.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="8" width="18" height="13" rx="2" />
                <path d="M12 8v13M3 12h18M7.5 8A2.5 2.5 0 1 1 12 6.5V8M16.5 8A2.5 2.5 0 1 0 12 6.5V8" />
            </svg>
            <span>Cartes cadeaux</span>
        </a>

        <a href="{{ route('admin.shops.index') }}"
           class="{{ request()->routeIs('admin.shops.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 10v10h16V10M3 10l2-6h14l2 6M8 20v-6h8v6M3 10c0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0" />
            </svg>
            <span>Boutiques</span>
        </a>

        <a href="{{ route('admin.prospecting-missions.index') }}"
           class="{{ request()->routeIs('admin.prospecting-missions.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19V5m0 0 6 3 5-3 5 3v11l-5-3-5 3-6-3M10 8v11M15 5v11" />
            </svg>
            <span>Prospection vendeurs</span>
        </a>

        <a href="{{ route('admin.categories.index') }}"
           class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" />
            </svg>
            <span>Catégories</span>
        </a>

        <a href="{{ route('admin.clients.index') }}"
           class="{{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1h5m-2.5-2.5v5" />
            </svg>
            <span>Clients</span>
        </a>

        <a href="{{ route('admin.commissions.index') }}"
           class="{{ request()->routeIs('admin.commissions.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2v20M17 6.5C17 4.6 15.2 3 12.8 3H10c-2.2 0-4 1.3-4 3s1.8 3 4 3h4c2.2 0 4 1.3 4 3s-1.8 3-4 3h-3c-2.8 0-5-1.8-5-4" />
            </svg>
            <span>Commissions</span>
        </a>

        <p class="sidebar-section">Marketing et support</p>

        <a href="{{ route('admin.promotions.index') }}"
           class="{{ request()->routeIs('admin.promotions.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 13 11 22l-9-9V4h9l9 9ZM7 8h.01" />
            </svg>
            <span>Promotions</span>
        </a>

        <a href="{{ route('admin.messages.index') }}"
           class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" />
            </svg>
            <span>Messages</span>
        </a>

        <a href="{{ route('admin.banners.index') }}"
           class="{{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 5h18v14H3V5Zm0 10 5-5 4 4 3-3 6 6M15 9h.01" />
            </svg>
            <span>Bannières</span>
        </a>

        <a href="{{ route('admin.home-ads.index') }}"
           class="{{ request()->routeIs('admin.home-ads.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 11v2l11 4V7L3 11Zm11-4 7-3v16l-7-3M6 14l1 6h4l-1-5" />
            </svg>
            <span>Pubs accueil</span>
        </a>

        <p class="sidebar-section">Administration</p>

        <a href="{{ route('admin.staff.index') }}"
           class="{{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-5h5M19.5 3.5v5" />
            </svg>
            <span>Équipe interne</span>
        </a>

        <a href="{{ route('admin.users.index') }}"
           class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9.5 0a3 3 0 1 0 0-6M23 21v-2a4 4 0 0 0-3-3.87" />
            </svg>
            <span>Utilisateurs</span>
        </a>

        <a href="{{ route('admin.submissions.indexAll') }}"
           class="{{ request()->routeIs('admin.submissions.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 2h9l5 5v15H6V2Zm9 0v6h5M9 13h8M9 17h8M9 9h2" />
            </svg>
            <span>Soumissions</span>
        </a>

        <a href="{{ route('admin.sms.index') }}"
           class="{{ request()->routeIs('admin.sms.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 4h16v12H7l-3 3V4Zm4 5h8M8 12h5" />
            </svg>
            <span>SMS</span>
        </a>

        <a href="{{ route('admin.settings.edit') }}"
           class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5a7.8 7.8 0 0 0-.1-1l2-1.6-2-3.4-2.5 1a8.6 8.6 0 0 0-1.7-1L14.7 3h-4L10 6a8.6 8.6 0 0 0-1.7 1L5.8 6l-2 3.4 2 1.6a7.8 7.8 0 0 0 0 2l-2 1.6 2 3.4 2.5-1a8.6 8.6 0 0 0 1.7 1l.7 3h4l.7-3a8.6 8.6 0 0 0 1.7-1l2.5 1 2-3.4-2-1.6c.1-.3.1-.7.1-1Z" />
            </svg>
            <span>Paramètres</span>
        </a>

        <a href="#" id="logoutBtn"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" />
            </svg>
            <span>Déconnexion</span>
        </a>

        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" hidden>
            @csrf
        </form>
    </nav>
</aside>
