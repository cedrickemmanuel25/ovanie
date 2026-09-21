@php
    use Illuminate\Support\Facades\Route;

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */
    $ovRoute = static function (array $names, string $fallback = '/') {
        foreach ($names as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return url($fallback);
    };

    $dashboardUrl = $ovRoute(['vendor.dashboard'], '/vendeur/dashboard');
    $addProductUrl = $ovRoute(['vendor.add_product'], '/vendeur/products/create');
    $productsUrl = $ovRoute(['vendor.products'], '/vendeur/products');
    $ordersUrl = $ovRoute(['vendor.orders'], '/vendeur/orders');
    $deliveryUrl = $ovRoute(['vendor.delivery.index'], '/vendeur/livraison');

    $paymentsUrl = $ovRoute(['vendor.payments'], '/vendeur/payments');
    $payoutsUrl = $ovRoute(['vendor.payouts.index'], '/vendeur/payouts');
    $paymentMethodUrl = $ovRoute(['vendor.payment-method'], '/vendeur/payment-method');

    $returnsUrl = $ovRoute(['vendor.returns.index'], '/vendeur/returns');
    $disputesUrl = $ovRoute(['vendor.disputes.index'], '/vendeur/disputes');
    $reviewsUrl = $ovRoute(['vendor.reviews.index'], '/vendeur/reviews');

    $vendorActesUrl = $ovRoute(['vendor.vendeur-actes'], '/vendeur/vendeur-actes');
    $shopProfileUrl = $ovRoute(['vendor.shop.profile'], '/vendeur/shop-profile');
    $shopEditUrl = $ovRoute(['vendor.shop.edit'], '/vendeur/shop-profile/edit');
    $shopStatusUrl = $ovRoute(['vendor.shop-status'], '/vendeur/shop-status');
    $shopDocumentsUrl = $ovRoute(['vendor.shop.documents'], '/vendeur/shop-documents');
    $vendorCguUrl = $ovRoute(['vendor.cgu'], '/vendeur/cgu');

    $boostUrl = $ovRoute(['vendor.boosts.index'], '/vendeur/products?boost=1');
    $marketplaceUrl = $ovRoute(['home'], '/');
    $clientDashboardUrl = $ovRoute(['client.dashboard'], '/client/dashboard');
    $helpUrl = $ovRoute(['contact.index'], '/contact');

    /*
    |--------------------------------------------------------------------------
    | Utilisateur / boutique
    |--------------------------------------------------------------------------
    */
    $user = auth()->user();
    $shop = $shop ?? $user?->shop;

    $shopName = $shop?->name ?: ($user?->name ?: 'Boutique OVANIE');

    $userInitials = collect(preg_split('/\s+/', trim($shopName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $showDeliveryMenu = $shop !== null;

    /*
    |--------------------------------------------------------------------------
    | Notifications réelles Laravel
    |--------------------------------------------------------------------------
    */
    $vendorNotifications = $user
        ? $user->notifications()->latest()->limit(8)->get()
        : collect();

    $vendorUnreadCount = $user
        ? $user->unreadNotifications()->count()
        : 0;

    $notificationVisual = static function (?string $category): array {
        return match (strtolower((string) $category)) {
            'orders', 'order' => ['icon' => 'package-check', 'tone' => 'blue'],
            'payments', 'payment', 'payouts', 'payout' => ['icon' => 'wallet-cards', 'tone' => 'green'],
            'deliveries', 'delivery', 'logistics' => ['icon' => 'truck', 'tone' => 'purple'],
            'returns', 'return', 'refund' => ['icon' => 'rotate-ccw', 'tone' => 'orange'],
            'disputes', 'dispute' => ['icon' => 'shield-alert', 'tone' => 'red'],
            'security' => ['icon' => 'shield-check', 'tone' => 'green'],
            'support', 'account' => ['icon' => 'messages-square', 'tone' => 'blue'],
            'promotion', 'promotions', 'promo' => ['icon' => 'badge-percent', 'tone' => 'orange'],
            default => ['icon' => 'bell-ring', 'tone' => 'blue'],
        };
    };

    /*
    |--------------------------------------------------------------------------
    | États actifs de navigation
    |--------------------------------------------------------------------------
    */
    $isDashboard = request()->routeIs('vendor.dashboard');

    $isAddProduct = request()->routeIs('vendor.add_product')
        || request()->is('vendeur/products/create*');

    $isProducts = request()->routeIs('vendor.products', 'vendor.products.edit')
        || (
            request()->is('vendeur/products*')
            && ! $isAddProduct
        );

    $isOrders = request()->routeIs('vendor.orders', 'vendor.orders.*')
        || request()->is('vendeur/orders*');

    $isDelivery = request()->routeIs('vendor.delivery.*')
        || request()->is('vendeur/livraison*');

    $isPayments = request()->routeIs('vendor.payments', 'vendor.payments.*')
        || request()->is('vendeur/payments*');

    $isPayouts = request()->routeIs('vendor.payouts.*')
        || request()->is('vendeur/payouts*');

    $isPaymentMethod = request()->routeIs('vendor.payment-method', 'vendor.payment-method.update')
        || request()->is('vendeur/payment-method*');

    $isReturns = request()->routeIs('vendor.returns.*')
        || request()->is('vendeur/returns*');

    $isDisputes = request()->routeIs('vendor.disputes.*')
        || request()->is('vendeur/disputes*');

    $isReviews = request()->routeIs('vendor.reviews.*')
        || request()->is('vendeur/reviews*');

    $isVendorActes = request()->routeIs('vendor.vendeur-actes')
        || request()->is('vendeur/vendeur-actes');

    $isShopProfile = request()->routeIs('vendor.shop.profile')
        || request()->is('vendeur/shop-profile');

    $isShopEdit = request()->routeIs('vendor.shop.edit', 'vendor.shop.update')
        || request()->is('vendeur/shop-profile/edit');

    $isShopStatus = request()->routeIs('vendor.shop-status')
        || request()->is('vendeur/shop-status');

    $isShopDocuments = request()->routeIs('vendor.shop.documents', 'vendor.shop.documents.update')
        || request()->is('vendeur/shop-documents');

    $isShopSettings = $isShopProfile || $isShopEdit || $isShopStatus || $isShopDocuments;

    $isVendorCgu = request()->routeIs('vendor.cgu')
        || request()->is('vendeur/cgu');

    $isBoost = request()->routeIs('vendor.boosts.*')
        || request()->has('boost');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Espace vendeur | OVANIE')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>

    <style>
        :root {
            --ov-sidebar-width: 276px;
            --ov-topbar-height: 68px;
            --ov-orange: #ff5a0a;
            --ov-blue: #0d63ef;
            --ov-navy: #08275f;
            --ov-text: #112f5e;
            --ov-muted: #7283a0;
            --ov-soft: #f7f9fc;
            --ov-border: #e2e9f2;
            --ov-white: #ffffff;
            --ov-shadow: 0 18px 50px rgba(10, 39, 95, .12);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            background: var(--ov-soft);
            color: var(--ov-text);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: geometricPrecision;
        }

        /* Masque les barres visibles tout en conservant le défilement molette/tactile. */
        html,
        body,
        .ov-main,
        .ov-content {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        .ov-main::-webkit-scrollbar,
        .ov-content::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            -webkit-tap-highlight-color: transparent;
        }

        .ov-seller-layout {
            min-height: 100vh;
            background: var(--ov-soft);
        }

        /* =========================================================
           SIDEBAR
           ========================================================= */
        .ov-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 90;
            width: var(--ov-sidebar-width);
            height: 100vh;
            background: #fff;
            border-right: 1px solid var(--ov-border);
            overflow: hidden;
        }

        .ov-sidebar-inner {
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .ov-sidebar-header {
            height: var(--ov-topbar-height);
            flex: 0 0 var(--ov-topbar-height);
            display: flex;
            align-items: center;
            padding: 0 22px;
            border-bottom: 1px solid var(--ov-border);
            background: #fff;
        }

        .ov-sidebar-logo {
            display: inline-flex;
            align-items: center;
            min-width: 0;
        }

        .ov-sidebar-logo img {
            display: block;
            width: 142px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .ov-sidebar-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 18px 14px 22px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .ov-sidebar-scroll::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .ov-sidebar-section {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid #e7edf4;
        }

        .ov-sidebar-section:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
        }

        .ov-sidebar-title {
            margin: 0 8px 9px;
            color: #687b9b;
            font-size: 10px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .ov-sidebar-menu {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 4px;
        }

        .ov-sidebar-link,
        .ov-sidebar-group-trigger {
            position: relative;
            width: 100%;
            min-height: 45px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px;
            border: 0;
            border-radius: 8px;
            color: #163564;
            background: transparent;
            font-size: 12.5px;
            line-height: 1.2;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
            transition: background .16s ease, color .16s ease, transform .16s ease;
        }

        .ov-sidebar-link:hover,
        .ov-sidebar-group-trigger:hover {
            color: var(--ov-blue);
            background: #f3f7fd;
        }

        .ov-sidebar-link.is-active {
            color: var(--ov-blue);
            background: #edf4ff;
            font-weight: 800;
        }

        .ov-sidebar-link.is-active::before {
            content: '';
            position: absolute;
            left: -14px;
            top: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 7px 7px 0;
            background: var(--ov-blue);
        }

        .ov-sidebar-link svg,
        .ov-sidebar-group-trigger svg {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            stroke-width: 2;
        }

        .ov-sidebar-group-trigger .ov-sidebar-group-chevron {
            width: 15px;
            height: 15px;
            margin-left: auto;
            transition: transform .18s ease;
        }

        .ov-sidebar-group.is-open .ov-sidebar-group-chevron {
            transform: rotate(180deg);
        }

        .ov-sidebar-submenu {
            display: none;
            padding: 3px 0 3px 29px;
        }

        .ov-sidebar-group.is-open .ov-sidebar-submenu {
            display: grid;
            gap: 2px;
        }

        .ov-sidebar-sublink {
            min-height: 34px;
            display: flex;
            align-items: center;
            padding: 0 11px;
            border-radius: 7px;
            color: #516987;
            font-size: 11.2px;
            font-weight: 650;
            transition: background .15s ease, color .15s ease;
        }

        .ov-sidebar-sublink:hover {
            color: var(--ov-blue);
            background: #f5f8fd;
        }

        .ov-sidebar-sublink.is-active {
            color: var(--ov-orange);
            background: #fff4ed;
            font-weight: 800;
        }

        .ov-sidebar-bottom {
            flex: 0 0 auto;
            padding: 12px 18px 18px;
            border-top: 1px solid #eef2f7;
            background: #fff;
        }

        .ov-sidebar-collapse {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #5b7193;
            font-size: 10.5px;
            font-weight: 650;
            cursor: pointer;
            border: 0;
            background: transparent;
        }

        .ov-sidebar-collapse svg {
            width: 16px;
            height: 16px;
        }

        /* =========================================================
           TOPBAR
           ========================================================= */
        .ov-topbar {
            position: fixed;
            top: 0;
            left: var(--ov-sidebar-width);
            right: 0;
            z-index: 100;
            height: var(--ov-topbar-height);
            display: flex;
            align-items: center;
            padding: 0 18px;
            background: rgba(255,255,255,.985);
            border-bottom: 1px solid var(--ov-border);
            backdrop-filter: blur(14px);
            overflow: visible;
        }

        .ov-mobile-toggle {
            display: none;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 8px;
            color: #17335f;
            background: transparent;
            cursor: pointer;
        }

        .ov-mobile-toggle:hover {
            background: #f3f7fd;
        }

        .ov-mobile-toggle svg {
            width: 20px;
            height: 20px;
        }

        .ov-topbar-actions {
            height: 100%;
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .ov-topbar-menu {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .ov-topbar-icon {
            position: relative;
            width: 54px;
            height: 100%;
            border: 0;
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #17335f;
            cursor: pointer;
            transition: color .15s ease, background .15s ease;
        }

        .ov-topbar-icon:hover,
        .ov-topbar-icon[aria-expanded="true"] {
            color: var(--ov-blue);
            background: #f7faff;
        }

        .ov-topbar-icon svg {
            width: 20px;
            height: 20px;
            stroke-width: 1.9;
        }

        .ov-topbar-badge {
            position: absolute;
            top: 10px;
            right: 6px;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            display: grid;
            place-items: center;
            border: 2px solid #fff;
            border-radius: 999px;
            color: #fff;
            background: #ef2b2d;
            font-size: 8px;
            line-height: 1;
            font-weight: 900;
            box-sizing: content-box;
        }

        .ov-topbar-help {
            height: 100%;
            min-width: 88px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 15px;
            border-left: 1px solid #e8edf4;
            border-right: 1px solid #e8edf4;
            color: #17335f;
            font-size: 11px;
            font-weight: 700;
            transition: background .15s ease, color .15s ease;
        }

        .ov-topbar-help:hover {
            color: var(--ov-blue);
            background: #f7faff;
        }

        .ov-topbar-help svg {
            width: 18px;
            height: 18px;
        }

        .ov-account-trigger {
            height: 100%;
            min-width: 240px;
            padding: 0 11px 0 16px;
            border: 0;
            background: transparent;
            display: grid;
            grid-template-columns: 34px minmax(0,1fr) 16px;
            align-items: center;
            gap: 10px;
            color: #112f5e;
            cursor: pointer;
            text-align: left;
            transition: background .15s ease;
        }

        .ov-account-trigger:hover,
        .ov-account-trigger[aria-expanded="true"] {
            background: #f7faff;
        }

        .ov-avatar {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg,#103c93,#075ee8);
            box-shadow: 0 0 0 3px #edf4ff;
            font-size: 10px;
            font-weight: 900;
        }

        .ov-account-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ov-account-copy strong {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #122e5b;
            font-size: 10.8px;
            line-height: 1.2;
            font-weight: 800;
        }

        .ov-account-copy small {
            color: #7b8aa2;
            font-size: 8.4px;
            line-height: 1.2;
            font-weight: 600;
        }

        .ov-account-chevron {
            width: 15px;
            height: 15px;
            transition: transform .18s ease;
        }

        .ov-account-trigger[aria-expanded="true"] .ov-account-chevron {
            transform: rotate(180deg);
        }

        /* =========================================================
           DROPDOWNS TOPBAR
           ========================================================= */
        .ov-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 400;
            display: none;
            overflow: hidden;
            border: 1px solid #dfe6f0;
            border-radius: 13px;
            background: #fff;
            box-shadow: var(--ov-shadow);
            transform-origin: top right;
        }

        .ov-topbar-menu.is-open > .ov-dropdown {
            display: block;
            animation: ovDrop .15s ease both;
        }

        @keyframes ovDrop {
            from {
                opacity: 0;
                transform: translateY(-5px) scale(.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Notifications */
        .ov-notification-dropdown {
            width: 400px;
        }

        .ov-drop-head {
            min-height: 62px;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #edf1f6;
        }

        .ov-drop-head h3 {
            margin: 0;
            color: #102f61;
            font-size: 13px;
            font-weight: 900;
        }

        .ov-drop-head p {
            margin: 3px 0 0;
            color: #8492a7;
            font-size: 8.5px;
        }

        .ov-read-all {
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--ov-blue);
            font-size: 8.8px;
            font-weight: 900;
            cursor: pointer;
            white-space: nowrap;
        }

        .ov-notification-list {
            max-height: 430px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cfd9e6 transparent;
        }

        .ov-notification-form {
            margin: 0;
        }

        .ov-notification-item {
            width: 100%;
            min-height: 76px;
            padding: 11px 14px;
            border: 0;
            border-bottom: 1px solid #edf1f6;
            background: #fff;
            display: grid;
            grid-template-columns: 39px minmax(0,1fr) 8px;
            gap: 10px;
            align-items: start;
            text-align: left;
            cursor: pointer;
            transition: background .15s ease;
        }

        .ov-notification-item:hover {
            background: #f8fbff;
        }

        .ov-notification-item.is-unread {
            background: #f4f8ff;
        }

        .ov-notification-item.is-unread:hover {
            background: #edf4ff;
        }

        .ov-notif-icon {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border-radius: 10px;
        }

        .ov-notif-icon svg {
            width: 17px;
            height: 17px;
        }

        .ov-notif-icon.blue { color:#075ee8; background:#eaf2ff; }
        .ov-notif-icon.green { color:#16a34a; background:#e8f8ed; }
        .ov-notif-icon.orange { color:#f36a0a; background:#fff0e5; }
        .ov-notif-icon.red { color:#e74242; background:#fff0f0; }
        .ov-notif-icon.purple { color:#7048e8; background:#f1edff; }

        .ov-notif-copy {
            min-width: 0;
        }

        .ov-notif-copy strong {
            display: block;
            margin-bottom: 3px;
            color: #173665;
            font-size: 9.8px;
            line-height: 1.35;
            font-weight: 900;
        }

        .ov-notif-copy p {
            margin: 0;
            color: #6e7f9a;
            font-size: 8.7px;
            line-height: 1.45;
        }

        .ov-notif-time {
            display: block;
            margin-top: 5px;
            color: #9aa6b8;
            font-size: 7.8px;
            font-weight: 700;
        }

        .ov-unread-dot {
            width: 7px;
            height: 7px;
            margin-top: 5px;
            border-radius: 50%;
            background: var(--ov-blue);
        }

        .ov-notification-empty {
            padding: 35px 22px;
            text-align: center;
        }

        .ov-notification-empty-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 10px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #6880a6;
            background: #f1f5fb;
        }

        .ov-notification-empty-icon svg {
            width: 24px;
            height: 24px;
        }

        .ov-notification-empty strong {
            display: block;
            color: #173665;
            font-size: 10px;
            font-weight: 900;
        }

        .ov-notification-empty p {
            margin: 5px 0 0;
            color: #8795a9;
            font-size: 8.5px;
        }

        .ov-drop-footer {
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fbfcfe;
        }

        .ov-drop-footer span {
            color: #7c8ba2;
            font-size: 8.5px;
            font-weight: 700;
        }

        /* Compte */
        .ov-account-dropdown {
            width: 300px;
        }

        .ov-account-head {
            padding: 15px;
            display: grid;
            grid-template-columns: 44px minmax(0,1fr);
            gap: 11px;
            align-items: center;
            border-bottom: 1px solid #edf1f6;
            background: linear-gradient(135deg,#f8fbff,#fff);
        }

        .ov-account-head .ov-avatar {
            width: 44px;
            height: 44px;
            font-size: 11px;
        }

        .ov-account-head strong {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #102f61;
            font-size: 10.8px;
            font-weight: 900;
        }

        .ov-account-head span:not(.ov-avatar) {
            display: block;
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #8391a6;
            font-size: 8.5px;
        }

        .ov-account-links {
            padding: 7px;
        }

        .ov-account-link,
        .ov-logout {
            width: 100%;
            min-height: 40px;
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

        .ov-account-link:hover {
            color: var(--ov-blue);
            background: #f2f7ff;
        }

        .ov-account-link svg,
        .ov-logout svg {
            width: 16px;
            height: 16px;
            color: #5d7599;
        }

        .ov-account-separator {
            height: 1px;
            margin: 6px 8px;
            background: #edf1f6;
        }

        .ov-logout {
            color: #d93d3d;
        }

        .ov-logout:hover {
            background: #fff3f3;
        }

        .ov-logout svg {
            color: #d93d3d;
        }

        /* =========================================================
           CONTENT
           ========================================================= */
        .ov-main {
            min-height: 100vh;
            margin-left: var(--ov-sidebar-width);
            padding-top: var(--ov-topbar-height);
            background: var(--ov-soft);
        }

        .ov-content {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            padding: 14px 15px 18px;
            overflow-x: hidden;
        }

        .ov-overlay {
            position: fixed;
            inset: 0;
            z-index: 85;
            display: none;
            background: rgba(15,23,42,.38);
        }

        /* =========================================================
           RESPONSIVE
           ========================================================= */
        @media (max-width: 1180px) {
            .ov-sidebar {
                transform: translateX(-100%);
                transition: transform .22s ease;
            }

            .ov-topbar {
                left: 0;
            }

            .ov-main {
                margin-left: 0;
            }

            .ov-mobile-toggle {
                display: inline-grid;
                place-items: center;
            }

            body.ov-sidebar-open .ov-sidebar {
                transform: translateX(0);
            }

            body.ov-sidebar-open .ov-overlay {
                display: block;
            }
        }

        @media (max-width: 720px) {
            .ov-topbar {
                padding: 0 8px;
            }

            .ov-topbar-help {
                width: 48px;
                min-width: 48px;
                padding: 0;
            }

            .ov-topbar-help span {
                display: none;
            }

            .ov-account-trigger {
                width: 54px;
                min-width: 54px;
                padding: 0 9px;
                grid-template-columns: 34px;
            }

            .ov-account-copy,
            .ov-account-trigger > .ov-account-chevron {
                display: none;
            }

            .ov-notification-dropdown {
                position: fixed;
                top: calc(var(--ov-topbar-height) + 8px);
                left: 10px;
                right: 10px;
                width: auto;
            }

            .ov-account-dropdown {
                position: fixed;
                top: calc(var(--ov-topbar-height) + 8px);
                right: 10px;
                width: min(300px, calc(100vw - 20px));
            }

            .ov-content {
                padding: 10px;
            }

            .ov-sidebar {
                width: min(88vw, 276px);
            }
        }

        @yield('inline_styles')
    </style>

    @yield('styles')
    @stack('styles')
</head>

<body>
<div class="ov-seller-layout">
    {{-- =======================================================
         SIDEBAR
         ======================================================= --}}
    <aside class="ov-sidebar" id="ovVendorSidebar">
        <div class="ov-sidebar-inner">
            <div class="ov-sidebar-header">
                <a href="{{ $dashboardUrl }}" class="ov-sidebar-logo" aria-label="OVANIE">
                    <img
                        src="{{ asset('storage/logos/officiel site.png') }}"
                        alt="OVANIE"
                    >
                </a>
            </div>

            <div class="ov-sidebar-scroll">
                <nav aria-label="Navigation vendeur">
                    <section class="ov-sidebar-section">
                        <ul class="ov-sidebar-menu">
                            <li>
                                <a href="{{ $dashboardUrl }}" class="ov-sidebar-link {{ $isDashboard ? 'is-active' : '' }}">
                                    <i data-lucide="layout-dashboard"></i>
                                    <span>Tableau de bord</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $addProductUrl }}" class="ov-sidebar-link {{ $isAddProduct ? 'is-active' : '' }}">
                                    <i data-lucide="circle-plus"></i>
                                    <span>Ajouter un produit</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $productsUrl }}" class="ov-sidebar-link {{ $isProducts ? 'is-active' : '' }}">
                                    <i data-lucide="package"></i>
                                    <span>Catalogue produits</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $ordersUrl }}" class="ov-sidebar-link {{ $isOrders ? 'is-active' : '' }}">
                                    <i data-lucide="clipboard-list"></i>
                                    <span>Commandes</span>
                                </a>
                            </li>

                            @if($showDeliveryMenu)
                                <li>
                                    <a href="{{ $deliveryUrl }}" class="ov-sidebar-link {{ $isDelivery ? 'is-active' : '' }}">
                                        <i data-lucide="truck"></i>
                                        <span>Livraison</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </section>

                    <section class="ov-sidebar-section">
                        <h2 class="ov-sidebar-title">Finances</h2>

                        <ul class="ov-sidebar-menu">
                            <li>
                                <a href="{{ $paymentsUrl }}" class="ov-sidebar-link {{ $isPayments ? 'is-active' : '' }}">
                                    <i data-lucide="receipt-text"></i>
                                    <span>Paiements / Mes ventes</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $payoutsUrl }}" class="ov-sidebar-link {{ $isPayouts ? 'is-active' : '' }}">
                                    <i data-lucide="wallet-cards"></i>
                                    <span>Reversements</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $paymentMethodUrl }}" class="ov-sidebar-link {{ $isPaymentMethod ? 'is-active' : '' }}">
                                    <i data-lucide="credit-card"></i>
                                    <span>Méthode de paiement</span>
                                </a>
                            </li>
                        </ul>
                    </section>

                    <section class="ov-sidebar-section">
                        <h2 class="ov-sidebar-title">Service client</h2>

                        <ul class="ov-sidebar-menu">
                            <li>
                                <a href="{{ $returnsUrl }}" class="ov-sidebar-link {{ $isReturns ? 'is-active' : '' }}">
                                    <i data-lucide="rotate-ccw"></i>
                                    <span>Retours et remboursements</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $disputesUrl }}" class="ov-sidebar-link {{ $isDisputes ? 'is-active' : '' }}">
                                    <i data-lucide="shield-alert"></i>
                                    <span>Litiges</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $reviewsUrl }}" class="ov-sidebar-link {{ $isReviews ? 'is-active' : '' }}">
                                    <i data-lucide="star"></i>
                                    <span>Avis clients</span>
                                </a>
                            </li>
                        </ul>
                    </section>

                    <section class="ov-sidebar-section">
                        <h2 class="ov-sidebar-title">Ressources vendeur</h2>

                        <ul class="ov-sidebar-menu">
                            <li>
                                <a href="{{ $vendorActesUrl }}" class="ov-sidebar-link {{ $isVendorActes ? 'is-active' : '' }}">
                                    <i data-lucide="book-open"></i>
                                    <span>Vendeur actes</span>
                                </a>
                            </li>

                            <li class="ov-sidebar-group {{ $isShopSettings ? 'is-open' : '' }}" data-sidebar-group>
                                <button
                                    type="button"
                                    class="ov-sidebar-group-trigger"
                                    data-sidebar-group-trigger
                                    aria-expanded="{{ $isShopSettings ? 'true' : 'false' }}"
                                >
                                    <i data-lucide="settings"></i>
                                    <span>Paramètres boutique</span>
                                    <i data-lucide="chevron-down" class="ov-sidebar-group-chevron"></i>
                                </button>

                                <div class="ov-sidebar-submenu">
                                    <a
                                        href="{{ $shopProfileUrl }}"
                                        class="ov-sidebar-sublink {{ $isShopProfile ? 'is-active' : '' }}"
                                    >
                                        Profil de la boutique
                                    </a>

                                    <a
                                        href="{{ $shopStatusUrl }}"
                                        class="ov-sidebar-sublink {{ $isShopStatus ? 'is-active' : '' }}"
                                    >
                                        Statut de la boutique
                                    </a>

                                    <a
                                        href="{{ $shopDocumentsUrl }}"
                                        class="ov-sidebar-sublink {{ $isShopDocuments ? 'is-active' : '' }}"
                                    >
                                        Documents de la boutique
                                    </a>
                                </div>
                            </li>

                            <li>
                                <a href="{{ $vendorCguUrl }}" class="ov-sidebar-link {{ $isVendorCgu ? 'is-active' : '' }}">
                                    <i data-lucide="file-text"></i>
                                    <span>CGU</span>
                                </a>
                            </li>
                        </ul>
                    </section>

                    <section class="ov-sidebar-section">
                        <h2 class="ov-sidebar-title">Croissance</h2>

                        <ul class="ov-sidebar-menu">
                            <li>
                                <a href="{{ $boostUrl }}" class="ov-sidebar-link {{ $isBoost ? 'is-active' : '' }}">
                                    <i data-lucide="megaphone"></i>
                                    <span>Publicité &amp; Boost</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ $marketplaceUrl }}" class="ov-sidebar-link">
                                    <i data-lucide="globe-2"></i>
                                    <span>Retour sur OVANIE</span>
                                </a>
                            </li>
                        </ul>
                    </section>
                </nav>
            </div>

            <div class="ov-sidebar-bottom">
                <button type="button" class="ov-sidebar-collapse" data-sidebar-close>
                    <i data-lucide="panel-left-close"></i>
                    <span>Réduire le menu</span>
                </button>
            </div>
        </div>
    </aside>

    <div class="ov-overlay" data-sidebar-close></div>

    {{-- =======================================================
         TOPBAR PROFESSIONNEL
         ======================================================= --}}
    <header class="ov-topbar">
        <button
            type="button"
            class="ov-mobile-toggle"
            data-sidebar-open
            aria-label="Ouvrir le menu vendeur"
        >
            <i data-lucide="menu"></i>
        </button>

        <div class="ov-topbar-actions">
            {{-- Notifications --}}
            <div class="ov-topbar-menu" data-topbar-menu>
                <button
                    type="button"
                    class="ov-topbar-icon"
                    data-topbar-trigger
                    aria-expanded="false"
                    aria-controls="vendorNotificationsMenu"
                    aria-label="Ouvrir les notifications"
                >
                    <i data-lucide="bell"></i>

                    @if($vendorUnreadCount > 0)
                        <span class="ov-topbar-badge">
                            {{ $vendorUnreadCount > 99 ? '99+' : $vendorUnreadCount }}
                        </span>
                    @endif
                </button>

                <div
                    class="ov-dropdown ov-notification-dropdown"
                    id="vendorNotificationsMenu"
                    data-topbar-dropdown
                >
                    <div class="ov-drop-head">
                        <div>
                            <h3>Notifications</h3>
                            <p>
                                {{ $vendorUnreadCount > 0
                                    ? $vendorUnreadCount . ' non lue' . ($vendorUnreadCount > 1 ? 's' : '')
                                    : 'Vous êtes à jour' }}
                            </p>
                        </div>

                        @if(
                            $vendorUnreadCount > 0
                            && Route::has('vendor.notifications.read-all')
                        )
                            <form
                                method="POST"
                                action="{{ route('vendor.notifications.read-all') }}"
                            >
                                @csrf

                                <button class="ov-read-all" type="submit">
                                    Tout marquer comme lu
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="ov-notification-list">
                        @forelse($vendorNotifications as $notification)
                            @php
                                $notificationData = is_array($notification->data)
                                    ? $notification->data
                                    : [];

                                $visual = $notificationVisual(
                                    data_get($notificationData, 'category')
                                );

                                $notificationTitle = data_get($notificationData, 'title')
                                    ?: 'Information OVANIE';

                                $notificationMessage = data_get($notificationData, 'message')
                                    ?: 'Une nouvelle information est disponible dans votre espace vendeur.';
                            @endphp

                            @if(Route::has('vendor.notifications.read'))
                                <form
                                    class="ov-notification-form"
                                    method="POST"
                                    action="{{ route('vendor.notifications.read', $notification->id) }}"
                                >
                                    @csrf

                                    <button
                                        class="ov-notification-item {{ $notification->read_at ? '' : 'is-unread' }}"
                                        type="submit"
                                    >
                                        <span class="ov-notif-icon {{ $visual['tone'] }}">
                                            <i data-lucide="{{ $visual['icon'] }}"></i>
                                        </span>

                                        <span class="ov-notif-copy">
                                            <strong>{{ $notificationTitle }}</strong>
                                            <p>{{ \Illuminate\Support\Str::limit($notificationMessage, 115) }}</p>
                                            <span class="ov-notif-time">
                                                {{ optional($notification->created_at)->diffForHumans() ?: 'À l’instant' }}
                                            </span>
                                        </span>

                                        @if(! $notification->read_at)
                                            <span class="ov-unread-dot" aria-label="Notification non lue"></span>
                                        @endif
                                    </button>
                                </form>
                            @else
                                <div class="ov-notification-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                    <span class="ov-notif-icon {{ $visual['tone'] }}">
                                        <i data-lucide="{{ $visual['icon'] }}"></i>
                                    </span>

                                    <span class="ov-notif-copy">
                                        <strong>{{ $notificationTitle }}</strong>
                                        <p>{{ \Illuminate\Support\Str::limit($notificationMessage, 115) }}</p>
                                        <span class="ov-notif-time">
                                            {{ optional($notification->created_at)->diffForHumans() ?: 'À l’instant' }}
                                        </span>
                                    </span>

                                    @if(! $notification->read_at)
                                        <span class="ov-unread-dot" aria-label="Notification non lue"></span>
                                    @endif
                                </div>
                            @endif
                        @empty
                            <div class="ov-notification-empty">
                                <span class="ov-notification-empty-icon">
                                    <i data-lucide="bell-off"></i>
                                </span>

                                <strong>Aucune notification</strong>
                                <p>Les commandes, paiements, reversements et alertes apparaîtront ici.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="ov-drop-footer">
                        <span>Les 8 notifications les plus récentes</span>
                    </div>
                </div>
            </div>

            {{-- Aide --}}
            <a class="ov-topbar-help" href="{{ $helpUrl }}">
                <i data-lucide="circle-help"></i>
                <span>Aide</span>
            </a>

            {{-- Compte vendeur --}}
            <div class="ov-topbar-menu" data-topbar-menu>
                <button
                    type="button"
                    class="ov-account-trigger"
                    data-topbar-trigger
                    aria-expanded="false"
                    aria-controls="vendorAccountMenu"
                >
                    <span class="ov-avatar">
                        {{ $userInitials ?: 'OV' }}
                    </span>

                    <span class="ov-account-copy">
                        <strong>{{ $shopName }}</strong>
                        <small>Espace vendeur</small>
                    </span>

                    <i data-lucide="chevron-down" class="ov-account-chevron"></i>
                </button>

                <div
                    class="ov-dropdown ov-account-dropdown"
                    id="vendorAccountMenu"
                    data-topbar-dropdown
                >
                    <div class="ov-account-head">
                        <span class="ov-avatar">
                            {{ $userInitials ?: 'OV' }}
                        </span>

                        <div style="min-width:0;">
                            <strong>{{ $shopName }}</strong>
                            <span>{{ $user?->email ?: 'Compte vendeur OVANIE' }}</span>
                        </div>
                    </div>

                    <div class="ov-account-links">
                        <a class="ov-account-link" href="{{ $shopProfileUrl }}">
                            <i data-lucide="store"></i>
                            Profil de la boutique
                        </a>

                        <a class="ov-account-link" href="{{ $shopEditUrl }}">
                            <i data-lucide="pencil-line"></i>
                            Modifier la boutique
                        </a>

                        <a class="ov-account-link" href="{{ $shopStatusUrl }}">
                            <i data-lucide="badge-check"></i>
                            Statut de la boutique
                        </a>

                        <a class="ov-account-link" href="{{ $shopDocumentsUrl }}">
                            <i data-lucide="files"></i>
                            Documents de la boutique
                        </a>

                        <a class="ov-account-link" href="{{ $paymentMethodUrl }}">
                            <i data-lucide="wallet-cards"></i>
                            Méthode de paiement
                        </a>

                        <div class="ov-account-separator"></div>

                        <a class="ov-account-link" href="{{ $clientDashboardUrl }}">
                            <i data-lucide="shopping-bag"></i>
                            Passer en mode client
                        </a>

                        <a class="ov-account-link" href="{{ $marketplaceUrl }}">
                            <i data-lucide="globe-2"></i>
                            Retour sur OVANIE
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button class="ov-logout" type="submit">
                                <i data-lucide="log-out"></i>
                                Se déconnecter
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="ov-main">
        <div class="ov-content">
            @yield('content')
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) {
        window.lucide.createIcons();
    }

    const body = document.body;

    /*
    |--------------------------------------------------------------------------
    | Sidebar mobile
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('[data-sidebar-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            body.classList.add('ov-sidebar-open');
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (window.innerWidth <= 1180) {
                body.classList.remove('ov-sidebar-open');
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Sous-menu Paramètres boutique
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('[data-sidebar-group]').forEach(function (group) {
        const trigger = group.querySelector('[data-sidebar-group-trigger]');

        trigger?.addEventListener('click', function () {
            const open = !group.classList.contains('is-open');

            group.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Menus du topbar
    |--------------------------------------------------------------------------
    */
    const topbarMenus = Array.from(
        document.querySelectorAll('[data-topbar-menu]')
    );

    function closeTopbarMenus(except = null) {
        topbarMenus.forEach(function (menu) {
            if (menu === except) {
                return;
            }

            menu.classList.remove('is-open');

            const trigger = menu.querySelector('[data-topbar-trigger]');
            trigger?.setAttribute('aria-expanded', 'false');
        });
    }

    topbarMenus.forEach(function (menu) {
        const trigger = menu.querySelector('[data-topbar-trigger]');

        trigger?.addEventListener('click', function (event) {
            event.stopPropagation();

            const willOpen = !menu.classList.contains('is-open');

            closeTopbarMenus(menu);

            menu.classList.toggle('is-open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', function () {
        closeTopbarMenus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeTopbarMenus();

            if (window.innerWidth <= 1180) {
                body.classList.remove('ov-sidebar-open');
            }
        }
    });
});
</script>

@yield('scripts')
@stack('scripts')
</body>
</html>
