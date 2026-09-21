@php
    use Illuminate\Support\Facades\Route;

    $topbarUser = auth()->user();
    $topbarShop = $shop ?? $topbarUser?->shop;

    $topbarShopName = $topbarShop?->name
        ?: ($topbarUser?->name ?: 'Boutique OVANIE');

    $topbarInitials = collect(preg_split('/\s+/', trim($topbarShopName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $topbarNotifications = $topbarUser
        ? $topbarUser->notifications()->latest()->limit(8)->get()
        : collect();

    $topbarUnreadCount = $topbarUser
        ? $topbarUser->unreadNotifications()->count()
        : 0;

    $topbarProfileUrl = Route::has('vendor.shop.profile')
        ? route('vendor.shop.profile')
        : url('/vendeur/shop-profile');

    $topbarEditUrl = Route::has('vendor.shop.edit')
        ? route('vendor.shop.edit')
        : url('/vendeur/shop-profile/edit');

    $topbarStatusUrl = Route::has('vendor.shop-status')
        ? route('vendor.shop-status')
        : url('/vendeur/shop-status');

    $topbarDocumentsUrl = Route::has('vendor.shop.documents')
        ? route('vendor.shop.documents')
        : url('/vendeur/shop-documents');

    $topbarPaymentUrl = Route::has('vendor.payment-method')
        ? route('vendor.payment-method')
        : url('/vendeur/payment-method');

    $topbarHelpUrl = Route::has('contact.index')
        ? route('contact.index')
        : url('/contact');

    $topbarMarketplaceUrl = Route::has('home')
        ? route('home')
        : url('/');

    $topbarNotificationVisual = static function (?string $category): array {
        return match (strtolower((string) $category)) {
            'orders', 'order' => ['icon' => 'package-check', 'tone' => 'blue'],
            'payments', 'payment' => ['icon' => 'credit-card', 'tone' => 'green'],
            'payouts', 'payout' => ['icon' => 'wallet-cards', 'tone' => 'green'],
            'deliveries', 'delivery', 'logistics' => ['icon' => 'truck', 'tone' => 'purple'],
            'returns', 'return', 'refund' => ['icon' => 'rotate-ccw', 'tone' => 'orange'],
            'disputes', 'dispute' => ['icon' => 'shield-alert', 'tone' => 'red'],
            'security' => ['icon' => 'shield-check', 'tone' => 'green'],
            'support' => ['icon' => 'messages-square', 'tone' => 'blue'],
            'promotion', 'promotions', 'promo' => ['icon' => 'badge-percent', 'tone' => 'orange'],
            default => ['icon' => 'bell-ring', 'tone' => 'blue'],
        };
    };
@endphp

<style>
    /* =========================================================
       OVANIE — TOPBAR VENDEUR PROFESSIONNEL
       ========================================================= */

    .ov-pro-topbar {
        overflow: visible !important;
        padding: 0 20px !important;
        background: rgba(255,255,255,.985) !important;
        border-bottom: 1px solid #e4eaf2 !important;
        box-shadow: 0 1px 0 rgba(15,35,72,.02) !important;
        backdrop-filter: blur(14px) !important;
    }

    .ov-pro-actions {
        height: 100%;
        display: flex;
        align-items: center;
        margin-left: auto;
    }

    .ov-pro-menu {
        position: relative;
        height: 100%;
        display: flex;
        align-items: center;
    }

    .ov-pro-icon {
        position: relative;
        width: 48px;
        height: 100%;
        border: 0;
        border-left: 1px solid transparent;
        border-right: 1px solid transparent;
        background: transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #17335f;
        cursor: pointer;
        transition: background .16s ease, color .16s ease, border-color .16s ease;
    }

    .ov-pro-icon:hover,
    .ov-pro-icon[aria-expanded="true"] {
        color: #075ee8;
        background: #f7faff;
        border-left-color: #edf1f6;
        border-right-color: #edf1f6;
    }

    .ov-pro-icon svg {
        width: 19px;
        height: 19px;
        stroke-width: 1.9;
    }

    .ov-pro-badge {
        position: absolute;
        top: 8px;
        right: 3px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        color: #fff;
        background: #ef2b2d;
        border: 2px solid #fff;
        font-size: 8px;
        line-height: 1;
        font-weight: 900;
        box-sizing: content-box;
    }

    .ov-pro-help {
        height: 100%;
        min-width: 82px;
        padding: 0 17px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-left: 1px solid #e7ebf0;
        border-right: 1px solid #e7ebf0;
        color: #17335f;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        transition: background .16s ease, color .16s ease;
    }

    .ov-pro-help:hover {
        color: #075ee8;
        background: #f7faff;
    }

    .ov-pro-help svg {
        width: 18px;
        height: 18px;
        stroke-width: 1.8;
    }

    .ov-pro-account-trigger {
        height: 100%;
        min-width: 238px;
        padding: 0 12px 0 17px;
        border: 0;
        background: transparent;
        display: grid;
        grid-template-columns: 34px minmax(0,1fr) 16px;
        align-items: center;
        gap: 10px;
        color: #112f5e;
        cursor: pointer;
        text-align: left;
        transition: background .16s ease;
    }

    .ov-pro-account-trigger:hover,
    .ov-pro-account-trigger[aria-expanded="true"] {
        background: #f7faff;
    }

    .ov-pro-avatar {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(135deg,#103c93,#075ee8);
        font-size: 10px;
        font-weight: 900;
        box-shadow: 0 0 0 3px #eef4ff;
    }

    .ov-pro-account-copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ov-pro-account-copy strong {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #122e5b;
        font-size: 10.5px;
        line-height: 1.2;
        font-weight: 800;
    }

    .ov-pro-account-copy small {
        color: #7b8aa2;
        font-size: 8.5px;
        line-height: 1.2;
        font-weight: 600;
    }

    .ov-pro-chevron {
        width: 15px;
        height: 15px;
        transition: transform .18s ease;
    }

    .ov-pro-account-trigger[aria-expanded="true"] .ov-pro-chevron {
        transform: rotate(180deg);
    }

    .ov-pro-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        z-index: 250;
        display: none;
        overflow: hidden;
        border: 1px solid #dfe6f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 22px 60px rgba(16,43,91,.16);
        transform-origin: top right;
    }

    .ov-pro-menu.is-open > .ov-pro-dropdown {
        display: block;
        animation: ovProDrop .15s ease both;
    }

    @keyframes ovProDrop {
        from { opacity:0; transform:translateY(-5px) scale(.985); }
        to { opacity:1; transform:translateY(0) scale(1); }
    }

    /* Notifications */
    .ov-pro-notification-dropdown {
        width: 390px;
    }

    .ov-pro-drop-head {
        min-height: 58px;
        padding: 0 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #edf1f6;
    }

    .ov-pro-drop-head-copy {
        min-width: 0;
    }

    .ov-pro-drop-head h3 {
        margin: 0;
        color: #102f61;
        font-size: 13px;
        font-weight: 900;
    }

    .ov-pro-drop-head p {
        margin: 3px 0 0;
        color: #8492a7;
        font-size: 8.5px;
    }

    .ov-pro-read-all {
        padding: 0;
        border: 0;
        background: transparent;
        color: #075ee8;
        font-size: 8.7px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }

    .ov-pro-notification-list {
        max-height: 420px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cfd9e6 transparent;
    }

    .ov-pro-notification-form {
        margin: 0;
    }

    .ov-pro-notification-item {
        width: 100%;
        min-height: 74px;
        padding: 11px 14px;
        border: 0;
        border-bottom: 1px solid #edf1f6;
        background: #fff;
        display: grid;
        grid-template-columns: 38px minmax(0,1fr) 8px;
        gap: 10px;
        align-items: start;
        text-align: left;
        cursor: pointer;
        transition: background .16s ease;
    }

    .ov-pro-notification-item:hover {
        background: #f8fbff;
    }

    .ov-pro-notification-item.is-unread {
        background: #f5f9ff;
    }

    .ov-pro-notification-item.is-unread:hover {
        background: #eef5ff;
    }

    .ov-pro-notif-icon {
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border-radius: 10px;
    }

    .ov-pro-notif-icon svg {
        width: 17px;
        height: 17px;
    }

    .ov-pro-notif-icon.blue { color:#075ee8; background:#eaf2ff; }
    .ov-pro-notif-icon.green { color:#16a34a; background:#e8f8ed; }
    .ov-pro-notif-icon.orange { color:#f36a0a; background:#fff0e5; }
    .ov-pro-notif-icon.red { color:#e74242; background:#fff0f0; }
    .ov-pro-notif-icon.purple { color:#7048e8; background:#f1edff; }

    .ov-pro-notif-copy {
        min-width: 0;
    }

    .ov-pro-notif-copy strong {
        display: block;
        margin-bottom: 3px;
        color: #173665;
        font-size: 9.7px;
        line-height: 1.35;
        font-weight: 900;
    }

    .ov-pro-notif-copy p {
        margin: 0;
        color: #6e7f9a;
        font-size: 8.7px;
        line-height: 1.45;
    }

    .ov-pro-notif-time {
        display: block;
        margin-top: 5px;
        color: #9aa6b8;
        font-size: 7.8px;
        font-weight: 700;
    }

    .ov-pro-unread-dot {
        width: 7px;
        height: 7px;
        margin-top: 5px;
        border-radius: 50%;
        background: #075ee8;
    }

    .ov-pro-empty {
        padding: 34px 22px;
        text-align: center;
    }

    .ov-pro-empty-icon {
        width: 52px;
        height: 52px;
        margin: 0 auto 10px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #6880a6;
        background: #f1f5fb;
    }

    .ov-pro-empty-icon svg {
        width: 23px;
        height: 23px;
    }

    .ov-pro-empty strong {
        display: block;
        color: #173665;
        font-size: 10px;
        font-weight: 900;
    }

    .ov-pro-empty p {
        margin: 5px 0 0;
        color: #8795a9;
        font-size: 8.5px;
    }

    .ov-pro-drop-footer {
        min-height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fbfcfe;
    }

    .ov-pro-drop-footer span {
        color: #7c8ba2;
        font-size: 8.5px;
        font-weight: 700;
    }

    /* Account menu */
    .ov-pro-account-dropdown {
        width: 292px;
    }

    .ov-pro-account-head {
        padding: 15px;
        display: grid;
        grid-template-columns: 42px minmax(0,1fr);
        gap: 11px;
        align-items: center;
        border-bottom: 1px solid #edf1f6;
        background: linear-gradient(135deg,#f8fbff,#fff);
    }

    .ov-pro-account-head .ov-pro-avatar {
        width: 42px;
        height: 42px;
        font-size: 11px;
    }

    .ov-pro-account-head strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #102f61;
        font-size: 10.5px;
        font-weight: 900;
    }

    .ov-pro-account-head span {
        display: block;
        margin-top: 3px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #8391a6;
        font-size: 8.5px;
    }

    .ov-pro-account-links {
        padding: 7px;
    }

    .ov-pro-account-link,
    .ov-pro-logout {
        width: 100%;
        min-height: 39px;
        padding: 0 10px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #29466f;
        font-size: 9.5px;
        font-weight: 750;
        text-decoration: none;
        text-align: left;
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }

    .ov-pro-account-link:hover {
        color: #075ee8;
        background: #f2f7ff;
    }

    .ov-pro-account-link svg,
    .ov-pro-logout svg {
        width: 16px;
        height: 16px;
        color: #5d7599;
    }

    .ov-pro-account-separator {
        height: 1px;
        margin: 6px 8px;
        background: #edf1f6;
    }

    .ov-pro-logout {
        color: #d93d3d;
    }

    .ov-pro-logout:hover {
        background: #fff3f3;
    }

    .ov-pro-logout svg {
        color: #d93d3d;
    }

    @media (max-width: 720px) {
        .ov-pro-topbar {
            padding: 0 9px !important;
        }

        .ov-pro-help {
            min-width: 44px;
            width: 44px;
            padding: 0;
        }

        .ov-pro-help span {
            display: none;
        }

        .ov-pro-account-trigger {
            min-width: auto;
            width: 54px;
            padding: 0 8px;
            grid-template-columns: 34px;
        }

        .ov-pro-account-copy,
        .ov-pro-account-trigger > .ov-pro-chevron {
            display: none;
        }

        .ov-pro-notification-dropdown {
            position: fixed;
            top: calc(var(--ov-topbar-height) + 8px);
            left: 10px;
            right: 10px;
            width: auto;
        }

        .ov-pro-account-dropdown {
            position: fixed;
            top: calc(var(--ov-topbar-height) + 8px);
            right: 10px;
            width: min(292px, calc(100vw - 20px));
        }
    }
</style>

<header class="ov-topbar ov-pro-topbar">
    <button
        type="button"
        class="ov-mobile-toggle"
        data-sidebar-open
        aria-label="Ouvrir le menu vendeur"
    >
        <i data-lucide="menu"></i>
    </button>

    <div class="ov-pro-actions">
        {{-- Notifications réelles --}}
        <div class="ov-pro-menu" data-topbar-menu>
            <button
                type="button"
                class="ov-pro-icon"
                data-topbar-trigger
                aria-expanded="false"
                aria-controls="vendorNotificationsMenu"
                aria-label="Ouvrir les notifications"
            >
                <i data-lucide="bell"></i>

                @if($topbarUnreadCount > 0)
                    <span class="ov-pro-badge">
                        {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
                    </span>
                @endif
            </button>

            <div
                class="ov-pro-dropdown ov-pro-notification-dropdown"
                id="vendorNotificationsMenu"
                data-topbar-dropdown
            >
                <div class="ov-pro-drop-head">
                    <div class="ov-pro-drop-head-copy">
                        <h3>Notifications</h3>
                        <p>
                            {{ $topbarUnreadCount > 0
                                ? $topbarUnreadCount . ' non lue' . ($topbarUnreadCount > 1 ? 's' : '')
                                : 'Vous êtes à jour' }}
                        </p>
                    </div>

                    @if($topbarUnreadCount > 0)
                        <form method="POST" action="{{ route('vendor.notifications.read-all') }}">
                            @csrf
                            <button class="ov-pro-read-all" type="submit">
                                Tout marquer comme lu
                            </button>
                        </form>
                    @endif
                </div>

                <div class="ov-pro-notification-list">
                    @forelse($topbarNotifications as $notification)
                        @php
                            $notificationData = is_array($notification->data)
                                ? $notification->data
                                : [];

                            $visual = $topbarNotificationVisual(
                                data_get($notificationData, 'category')
                            );

                            $title = data_get($notificationData, 'title')
                                ?: 'Information OVANIE';

                            $message = data_get($notificationData, 'message')
                                ?: 'Une nouvelle information est disponible dans votre espace vendeur.';
                        @endphp

                        <form
                            class="ov-pro-notification-form"
                            method="POST"
                            action="{{ route('vendor.notifications.read', $notification->id) }}"
                        >
                            @csrf

                            <button
                                class="ov-pro-notification-item {{ $notification->read_at ? '' : 'is-unread' }}"
                                type="submit"
                            >
                                <span class="ov-pro-notif-icon {{ $visual['tone'] }}">
                                    <i data-lucide="{{ $visual['icon'] }}"></i>
                                </span>

                                <span class="ov-pro-notif-copy">
                                    <strong>{{ $title }}</strong>
                                    <p>{{ \Illuminate\Support\Str::limit($message, 110) }}</p>
                                    <span class="ov-pro-notif-time">
                                        {{ optional($notification->created_at)->diffForHumans() ?: 'À l’instant' }}
                                    </span>
                                </span>

                                @if(! $notification->read_at)
                                    <span class="ov-pro-unread-dot" aria-label="Non lue"></span>
                                @endif
                            </button>
                        </form>
                    @empty
                        <div class="ov-pro-empty">
                            <span class="ov-pro-empty-icon">
                                <i data-lucide="bell-off"></i>
                            </span>
                            <strong>Aucune notification</strong>
                            <p>Les nouvelles commandes, paiements et alertes apparaîtront ici.</p>
                        </div>
                    @endforelse
                </div>

                <div class="ov-pro-drop-footer">
                    <span>Les 8 notifications les plus récentes</span>
                </div>
            </div>
        </div>

        {{-- Aide réelle --}}
        <a class="ov-pro-help" href="{{ $topbarHelpUrl }}">
            <i data-lucide="circle-help"></i>
            <span>Aide</span>
        </a>

        {{-- Menu du compte --}}
        <div class="ov-pro-menu" data-topbar-menu>
            <button
                type="button"
                class="ov-pro-account-trigger"
                data-topbar-trigger
                aria-expanded="false"
                aria-controls="vendorAccountMenu"
            >
                <span class="ov-pro-avatar">
                    {{ $topbarInitials ?: 'OV' }}
                </span>

                <span class="ov-pro-account-copy">
                    <strong>{{ $topbarShopName }}</strong>
                    <small>Espace vendeur</small>
                </span>

                <i data-lucide="chevron-down" class="ov-pro-chevron"></i>
            </button>

            <div
                class="ov-pro-dropdown ov-pro-account-dropdown"
                id="vendorAccountMenu"
                data-topbar-dropdown
            >
                <div class="ov-pro-account-head">
                    <span class="ov-pro-avatar">
                        {{ $topbarInitials ?: 'OV' }}
                    </span>

                    <div style="min-width:0;">
                        <strong>{{ $topbarShopName }}</strong>
                        <span>{{ $topbarUser?->email ?: 'Compte vendeur OVANIE' }}</span>
                    </div>
                </div>

                <div class="ov-pro-account-links">
                    <a class="ov-pro-account-link" href="{{ $topbarProfileUrl }}">
                        <i data-lucide="store"></i>
                        Profil de la boutique
                    </a>

                    <a class="ov-pro-account-link" href="{{ $topbarEditUrl }}">
                        <i data-lucide="pencil-line"></i>
                        Modifier la boutique
                    </a>

                    <a class="ov-pro-account-link" href="{{ $topbarStatusUrl }}">
                        <i data-lucide="badge-check"></i>
                        Statut de la boutique
                    </a>

                    <a class="ov-pro-account-link" href="{{ $topbarDocumentsUrl }}">
                        <i data-lucide="files"></i>
                        Documents de la boutique
                    </a>

                    <a class="ov-pro-account-link" href="{{ $topbarPaymentUrl }}">
                        <i data-lucide="wallet-cards"></i>
                        Méthode de paiement
                    </a>

                    <div class="ov-pro-account-separator"></div>

                    <a class="ov-pro-account-link" href="{{ $topbarMarketplaceUrl }}">
                        <i data-lucide="globe-2"></i>
                        Retour sur OVANIE
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button class="ov-pro-logout" type="submit">
                            <i data-lucide="log-out"></i>
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menus = Array.from(document.querySelectorAll('[data-topbar-menu]'));

    function closeAll(except = null) {
        menus.forEach(function (menu) {
            if (menu === except) {
                return;
            }

            menu.classList.remove('is-open');

            const trigger = menu.querySelector('[data-topbar-trigger]');
            trigger?.setAttribute('aria-expanded', 'false');
        });
    }

    menus.forEach(function (menu) {
        const trigger = menu.querySelector('[data-topbar-trigger]');

        trigger?.addEventListener('click', function (event) {
            event.stopPropagation();

            const willOpen = !menu.classList.contains('is-open');

            closeAll(menu);
            menu.classList.toggle('is-open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', function () {
        closeAll();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
});
</script>
