@extends('layouts.vendor')

@section('title', 'Tableau de bord vendeur | OVANIE')

@section('inline_styles')
    /* =========================================================
       OVANIE SELLER CENTRAL — DASHBOARD
       Reproduction de la maquette fournie
       ========================================================= */

    :root {
        --ov-sidebar-width: 224px;
        --ov-topbar-height: 55px;
        --dash-navy: #10264f;
        --dash-blue: #075ee8;
        --dash-blue-soft: #edf4ff;
        --dash-orange: #ff7900;
        --dash-green: #05a65a;
        --dash-red: #f2573c;
        --dash-muted: #66738d;
        --dash-border: #dfe5ec;
        --dash-line: #e8edf3;
        --dash-bg: #fbfcfe;
        --dash-card: #ffffff;
    }

    body {
        background: var(--dash-bg);
        color: var(--dash-navy);
    }

    .ov-seller-layout,
    .ov-main {
        background: var(--dash-bg);
    }

    /* -------------------------
       Sidebar : dimensions maquette
       ------------------------- */
    .ov-sidebar {
        width: var(--ov-sidebar-width);
        border-right: 1px solid #e2e6eb;
        box-shadow: none;
    }

    .ov-sidebar-inner {
        background: #fff;
    }

    .ov-sidebar-header {
        height: 88px;
        flex: 0 0 88px;
        padding: 16px 26px 12px;
        border-bottom: 0;
        justify-content: flex-start;
    }

    .ov-sidebar-toggle {
        display: none;
    }

    .ov-sidebar-logo img {
        width: 132px;
        max-width: 132px;
    }

    .ov-sidebar-nav {
        flex: 1 1 auto;
        padding: 4px 7px 0;
        overflow-y: auto;
        scrollbar-width: none;
    }

    .ov-sidebar-nav::-webkit-scrollbar {
        display: none;
    }

    .ov-sidebar-menu {
        gap: 3px;
    }

    .ov-sidebar-link {
        min-height: 43px;
        gap: 12px;
        padding: 0 12px;
        border-radius: 4px;
        color: #183058;
        font-size: 12.8px;
        font-weight: 600;
        transform: none !important;
    }

    .ov-sidebar-link svg,
    .ov-sidebar-link i {
        width: 18px;
        height: 18px;
        flex-basis: 18px;
        stroke-width: 1.8;
        color: #4d6489;
    }

    .ov-sidebar-link:hover {
        background: #f4f7fc;
        color: var(--dash-blue);
    }

    .ov-sidebar-link.is-active {
        color: var(--dash-blue);
        background: #eef3fd;
        box-shadow: none;
        font-weight: 700;
    }

    .ov-sidebar-link.is-active svg,
    .ov-sidebar-link.is-active i {
        color: var(--dash-blue);
    }

    .ov-sidebar-link.is-active::before {
        left: -7px;
        top: 0;
        width: 3px;
        height: 100%;
        border-radius: 0 3px 3px 0;
        background: var(--dash-blue);
    }

    .ov-sidebar-section-title {
        margin: 28px 10px 9px;
        padding-top: 18px;
        border-top: 1px solid #d9dee5;
        color: #60708d;
        font-size: 10px;
        line-height: 1;
        font-weight: 500;
        letter-spacing: .015em;
        text-transform: uppercase;
    }

    .ov-sidebar-bottom {
        margin-top: 0;
        padding: 15px 20px 34px 48px;
        flex: 0 0 auto;
        background: #fff;
    }

    .ov-sidebar-bottom::after {
        content: "↤  Réduire le menu";
        color: #536584;
        font-size: 10.5px;
        font-weight: 500;
        white-space: nowrap;
    }

    .ov-sidebar-growth,
    .ov-sidebar-art {
        display: none !important;
    }

    /* -------------------------
       Topbar maquette
       ------------------------- */
    .ov-topbar {
        left: var(--ov-sidebar-width);
        height: var(--ov-topbar-height);
        padding: 0 17px 0 12px;
        gap: 0;
        border-bottom: 1px solid #e1e5ea;
        background: rgba(255, 255, 255, .98);
        box-shadow: none;
        backdrop-filter: none;
    }

    .ov-topbar-actions {
        height: 100%;
        gap: 0;
    }

    .ov-topbar-icon {
        width: 45px;
        height: 100%;
        color: #263f69;
    }

    .ov-topbar-icon svg {
        width: 18px;
        height: 18px;
        stroke-width: 1.8;
    }

    .ov-topbar-icon:nth-child(2) {
        width: auto;
        min-width: 80px;
        padding: 0 17px;
        gap: 8px;
        border-left: 1px solid #e5e8ed;
        border-right: 1px solid #e5e8ed;
        justify-content: flex-start;
    }

    .ov-topbar-icon:nth-child(2)::after {
        content: "Aide";
        color: #263f69;
        font-size: 11.5px;
        font-weight: 600;
    }

    .ov-topbar-badge {
        top: 9px;
        right: 4px;
        min-width: 15px;
        height: 15px;
        padding: 0 3px;
        font-size: 8px;
        border-width: 1px;
        background: #ff2e2e;
    }

    .ov-topbar-account {
        height: 100%;
        padding-left: 16px;
        gap: 8px;
        color: #263f69;
        font-size: 10.5px;
        font-weight: 600;
    }

    .ov-avatar {
        width: 28px;
        height: 28px;
        font-size: 9px;
        background: #0b5de7;
        box-shadow: none;
    }

    .ov-account-chevron {
        width: 13px;
        height: 13px;
    }

    .ov-main {
        margin-left: var(--ov-sidebar-width);
        padding-top: var(--ov-topbar-height);
    }

    .ov-content {
        padding: 14px 15px 18px;
    }

    /* -------------------------
       Dashboard
       ------------------------- */
    .vendor-dashboard {
        width: 100%;
        min-width: 0;
        color: var(--dash-navy);
    }

    .vendor-dashboard *,
    .vendor-dashboard *::before,
    .vendor-dashboard *::after {
        box-sizing: border-box;
    }

    .dashboard-shell {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .dashboard-head {
        min-height: 78px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 420px;
        gap: 24px;
        align-items: center;
        padding: 0 22px;
    }

    .dashboard-greeting {
        min-width: 0;
    }

    .dashboard-title-line {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 23px;
        white-space: normal;
    }

    .dashboard-title-line strong {
        color: #142b52;
        font-size: 15px;
        line-height: 1.2;
        font-weight: 600;
    }

    .dashboard-title-line span {
        color: #60708d;
        font-size: 13px;
        line-height: 1.2;
        font-weight: 400;
    }

    .shop-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        color: #24385c;
        font-size: 10.5px;
        line-height: 1.2;
    }

    .shop-score {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        white-space: nowrap;
    }

    .shop-score-label {
        color: #61718e;
    }

    .shop-score strong {
        color: #1e2e4d;
        font-size: 10.8px;
        font-weight: 700;
    }

    .score-bar {
        width: 85px;
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e8edf5;
    }

    .score-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--dash-blue);
    }

    .meta-item {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        white-space: nowrap;
    }

    .meta-item::before {
        content: "";
        width: 3px;
        height: 3px;
        flex: 0 0 auto;
        border-radius: 999px;
        background: #10264f;
    }

    .dashboard-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px 13px;
    }

    .dashboard-action {
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 0 12px;
        border: 1px solid var(--dash-blue);
        border-radius: 5px;
        background: #fff;
        color: var(--dash-blue);
        font-size: 10.8px;
        line-height: 1;
        font-weight: 600;
        text-align: center;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .dashboard-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 14px rgba(16, 53, 110, .10);
    }

    .dashboard-action svg {
        width: 15px;
        height: 15px;
        stroke-width: 1.9;
    }

    .dashboard-action.orange {
        border-color: #ff7900;
        background: linear-gradient(180deg, #ff8a00 0%, #ff7300 100%);
        color: #fff;
        box-shadow: 0 4px 9px rgba(255, 121, 0, .20);
    }

    .dashboard-action.blue {
        background: linear-gradient(180deg, #176bf1 0%, #0759d8 100%);
        color: #fff;
        box-shadow: 0 4px 9px rgba(7, 94, 232, .18);
    }

    .dashboard-card {
        background: #fff;
        border: 1px solid #dfe4ea;
        border-radius: 6px;
        box-shadow: 0 1px 5px rgba(16, 38, 79, .045);
    }

    /* KPI top cards */
    .main-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }

    .main-kpi-card {
        min-height: 137px;
        padding: 17px 23px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .kpi-heading {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1c3765;
        font-size: 12px;
        font-weight: 600;
    }

    .kpi-info {
        width: 14px;
        height: 14px;
        color: var(--dash-blue);
        stroke-width: 2;
    }

    .kpi-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 54px;
        gap: 20px;
        align-items: center;
    }

    .kpi-number-row {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin-top: 2px;
    }

    .kpi-number {
        color: #0d2142;
        font-size: 38px;
        line-height: .95;
        font-weight: 700;
        letter-spacing: -.045em;
    }

    .kpi-unit {
        color: #1b3359;
        font-size: 11px;
        font-weight: 600;
    }

    .kpi-subline {
        margin-top: 14px;
        color: #31496f;
        font-size: 10px;
        font-weight: 500;
    }

    .kpi-subline .trend {
        margin-left: 7px;
        color: #229b53;
        font-size: 12px;
        font-weight: 700;
    }

    .kpi-icon {
        width: 52px;
        height: 60px;
        display: grid;
        place-items: center;
        align-self: center;
        border-radius: 12px;
        color: #1058c8;
        background: #edf4ff;
    }

    .kpi-icon svg {
        width: 27px;
        height: 27px;
        stroke-width: 1.8;
    }

    .order-mini-stats {
        display: flex;
        align-items: stretch;
        margin-top: 8px;
        max-width: 220px;
    }

    .order-mini-stat {
        min-width: 92px;
        padding-right: 20px;
    }

    .order-mini-stat + .order-mini-stat {
        padding-left: 20px;
        border-left: 1px solid #ccd4df;
    }

    .order-mini-stat span {
        display: block;
        color: #687590;
        font-size: 9.5px;
        margin-bottom: 3px;
    }

    .order-mini-stat strong {
        font-size: 16px;
        line-height: 1;
        font-weight: 600;
    }

    .order-mini-stat.orange strong { color: #ff7900; }
    .order-mini-stat.green strong { color: #61a528; }

    /* Operational strip */
    .operations-strip {
        min-height: 70px;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        align-items: stretch;
        padding: 10px 0;
    }

    .operation-item {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 13px;
        padding: 0 18px;
    }

    .operation-item + .operation-item {
        border-left: 1px solid #ccd3dc;
    }

    .operation-item svg {
        width: 25px;
        height: 25px;
        flex: 0 0 auto;
        color: #50678d;
        stroke-width: 1.7;
    }

    .operation-item.warning svg { color: #ff7b00; }

    .operation-copy span {
        display: block;
        margin-bottom: 3px;
        color: #5f6f8c;
        font-size: 10.5px;
        white-space: nowrap;
    }

    .operation-copy strong {
        display: block;
        color: #10264f;
        font-size: 15px;
        line-height: 1;
        font-weight: 600;
    }

    .operation-item.orange .operation-copy strong,
    .operation-item.warning .operation-copy strong {
        color: #ff7900;
    }

    /* Graph + finance */
    .finance-row {
        display: grid;
        grid-template-columns: minmax(0, 1.42fr) minmax(380px, 1fr);
        gap: 12px;
    }

    .chart-card,
    .finance-card {
        min-height: 204px;
        padding: 12px 23px 10px;
    }

    .section-head {
        min-height: 26px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
    }

    .section-head h2 {
        margin: 0;
        color: #16315d;
        font-size: 12px;
        line-height: 1.2;
        font-weight: 600;
    }

    .chart-total {
        text-align: right;
    }

    .chart-total span {
        display: block;
        color: #63728e;
        font-size: 10px;
        line-height: 1.1;
    }

    .chart-total strong {
        display: block;
        margin-top: 4px;
        color: var(--dash-blue);
        font-size: 17px;
        line-height: 1;
        font-weight: 700;
    }

    .sales-chart {
        width: 100%;
        height: 150px;
        display: block;
        margin-top: 1px;
        overflow: visible;
    }

    .sales-chart text {
        font-family: 'Inter', sans-serif;
    }

    .finance-list {
        margin-top: 5px;
    }

    .finance-line {
        min-height: 39px;
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
        border-bottom: 1px solid #e5e9ee;
    }

    .finance-line:last-child {
        border-bottom: 0;
    }

    .finance-line svg {
        width: 18px;
        height: 18px;
        color: #607497;
        stroke-width: 1.65;
    }

    .finance-line span {
        color: #263b61;
        font-size: 10.5px;
        font-weight: 500;
    }

    .finance-line strong {
        color: #203454;
        font-size: 10.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    .finance-line.paid strong {
        color: #05a65a;
    }


    .finance-mode-note {
        margin-top: 12px;
        padding: 9px 11px;
        border: 1px solid #cfe0ff;
        border-radius: 6px;
        background: #f2f7ff;
        color: #39557e;
        font-size: 10px;
        line-height: 1.45;
        font-weight: 500;
    }

    .finance-mode-note strong {
        color: #0a55c7;
        font-weight: 700;
    }

    /* Recent orders */
    .orders-card {
        min-height: 129px;
        padding: 12px 26px 10px;
    }

    .orders-card .section-head {
        align-items: center;
    }

    .section-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--dash-blue);
        font-size: 10.5px;
        line-height: 1;
        font-weight: 600;
        white-space: nowrap;
    }

    .section-link svg {
        width: 14px;
        height: 14px;
        stroke-width: 2;
    }

    .orders-table-wrap {
        width: 100%;
        overflow-x: auto;
        margin-top: 6px;
    }

    .orders-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .orders-table col:nth-child(1) { width: 17%; }
    .orders-table col:nth-child(2) { width: 20%; }
    .orders-table col:nth-child(3) { width: 26%; }
    .orders-table col:nth-child(4) { width: 24%; }
    .orders-table col:nth-child(5) { width: 13%; }

    .orders-table th {
        height: 24px;
        padding: 0 14px;
        border-bottom: 1px solid #bfc8d4;
        color: #536584;
        font-size: 9.2px;
        line-height: 1;
        font-weight: 500;
        text-align: left;
    }

    .orders-table td {
        height: 39px;
        padding: 5px 14px;
        border-bottom: 1px solid #edf0f4;
        color: #25395d;
        font-size: 10px;
        vertical-align: middle;
    }

    .empty-orders {
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 13px;
    }

    .empty-orders svg {
        width: 25px;
        height: 25px;
        color: #8593aa;
        stroke-width: 1.5;
    }

    .empty-orders strong {
        display: block;
        color: #293d60;
        font-size: 10px;
        font-weight: 600;
    }

    .empty-orders span {
        display: block;
        margin-top: 3px;
        color: #677690;
        font-size: 9.5px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        padding: 0 9px;
        border-radius: 999px;
        background: #eef3fd;
        color: #245fbd;
        font-size: 9px;
        font-weight: 600;
    }

    /* Activity card */
    .activity-card {
        min-height: 218px;
        padding: 12px 26px 8px;
    }

    .activity-list {
        margin-top: 6px;
    }

    .activity-row {
        min-height: 36px;
        display: grid;
        grid-template-columns: 28px minmax(170px, .75fr) minmax(260px, 1.5fr) minmax(120px, .55fr) 16px;
        gap: 9px;
        align-items: center;
        border-bottom: 1px solid #e3e7ec;
    }

    .activity-row:last-child {
        border-bottom: 0;
    }

    .activity-row > svg:first-child {
        width: 19px;
        height: 19px;
        color: #4d658d;
        stroke-width: 1.6;
    }

    .activity-title {
        color: #314668;
        font-size: 10.5px;
        font-weight: 500;
        white-space: nowrap;
    }

    .activity-description {
        color: #6a7890;
        font-size: 9.5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .activity-row .section-link {
        justify-self: end;
    }

    .activity-chevron {
        width: 14px;
        height: 14px;
        justify-self: end;
        color: var(--dash-blue);
        stroke-width: 2;
    }

    /* Responsive */
    @media (max-width: 1320px) {
        .dashboard-head {
            grid-template-columns: 1fr 390px;
            padding-left: 12px;
            padding-right: 12px;
        }

        .shop-meta {
            gap: 9px;
        }

        .meta-item {
            gap: 9px;
        }

        .finance-row {
            grid-template-columns: minmax(0, 1.25fr) minmax(340px, .9fr);
        }
    }


    @media (max-width: 1480px) {
        .main-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1180px) {
        .ov-topbar {
            left: 0;
        }

        .ov-main {
            margin-left: 0;
        }

        .ov-sidebar-header {
            height: var(--ov-topbar-height);
            flex-basis: var(--ov-topbar-height);
            padding: 0 18px;
        }

        .ov-sidebar-toggle {
            display: inline-flex;
        }

        .ov-sidebar-logo img {
            width: 120px;
        }

        .dashboard-head {
            grid-template-columns: 1fr;
            gap: 13px;
            padding: 8px 8px 3px;
        }

        .dashboard-title-line {
            margin-bottom: 13px;
        }

        .dashboard-actions {
            max-width: 620px;
        }

        .finance-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 860px) {
        .main-kpis {
            grid-template-columns: 1fr;
        }

        .main-kpi-card {
            min-height: 124px;
        }

        .operations-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 0;
        }

        .operation-item {
            min-height: 64px;
            border-bottom: 1px solid #e7ebf0;
        }

        .operation-item + .operation-item {
            border-left: 0;
        }

        .operation-item:nth-child(even) {
            border-left: 1px solid #e7ebf0;
        }

        .activity-row {
            grid-template-columns: 26px minmax(130px, .8fr) minmax(180px, 1.2fr) auto 14px;
        }
    }

    @media (max-width: 640px) {
        .ov-content {
            padding: 10px;
        }

        .ov-topbar-account strong,
        .ov-topbar-icon:nth-child(2)::after {
            display: none;
        }

        .ov-topbar-icon:nth-child(2) {
            min-width: 45px;
            width: 45px;
            padding: 0;
            justify-content: center;
        }

        .dashboard-actions {
            grid-template-columns: 1fr;
        }

        .shop-meta {
            align-items: flex-start;
            flex-direction: column;
        }

        .meta-item::before {
            display: none;
        }

        .operations-strip {
            grid-template-columns: 1fr;
        }

        .operation-item,
        .operation-item:nth-child(even) {
            border-left: 0;
            justify-content: flex-start;
        }

        .chart-card,
        .finance-card,
        .orders-card,
        .activity-card {
            padding-left: 14px;
            padding-right: 14px;
        }

        .activity-card {
            overflow-x: auto;
        }

        .activity-list {
            min-width: 700px;
        }
    }
@endsection

@section('content')
@php
    $stats = is_array($stats ?? null) ? $stats : [];
    $shop = $shop ?? auth()->user()?->shop;

    $orders = collect($orders ?? []);
    $recentProducts = collect($recentProducts ?? []);
    $lowStockProducts = collect($lowStockProducts ?? []);
    $topProducts = collect($topProducts ?? []);
    $recentPayouts = collect($recentPayouts ?? []);
    $recentNegotiations = collect($recentNegotiations ?? []);
    $recentReturns = collect($recentReturns ?? []);
    $recentDisputes = collect($recentDisputes ?? []);
    $series = collect($salesSeries ?? []);

    $stat = fn (string $key, $default = 0) => array_key_exists($key, $stats) ? $stats[$key] : $default;
    $money = fn ($value) => number_format((float) ($value ?? 0), 0, ',', ' ') . ' FCFA';
    $number = fn ($value) => number_format((float) ($value ?? 0), 0, ',', ' ');

    $safeRoute = function (string $name, array $parameters = [], string $fallback = '#') {
        try {
            return \Illuminate\Support\Facades\Route::has($name)
                ? route($name, $parameters)
                : url($fallback);
        } catch (\Throwable $e) {
            return url($fallback);
        }
    };

    $shopName = $shop?->name ?: (auth()->user()?->name ?: 'Ma boutique');
    $shopScore = min(100, max(0, (int) $stat('shop_score', 0)));

    $category = $shop?->main_category ?: 'Non renseignée';
    $city = trim((string) ($shop?->city ?? ''));
    $commune = trim((string) ($shop?->commune ?? ''));
    $zone = collect([$city, $commune])->filter()->implode(' - ');
    $zone = $zone !== '' ? $zone : ($shop?->delivery_zone ?: 'Non renseignée');
    $processingTime = $shop?->processing_time ?: '24–48h';

    $days = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    $months = [
        1 => 'janvier',
        2 => 'février',
        3 => 'mars',
        4 => 'avril',
        5 => 'mai',
        6 => 'juin',
        7 => 'juillet',
        8 => 'août',
        9 => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ];

    $today = now();
    $humanDate = $days[(int) $today->format('N')]
        . ' ' . $today->format('j')
        . ' ' . $months[(int) $today->format('n')]
        . ' ' . $today->format('Y');

    if ($series->isEmpty()) {
        $series = collect(range(6, 0))->map(fn ($daysAgo) => [
            'label' => now()->subDays($daysAgo)->format('d/m'),
            'amount' => 0,
        ]);
    }

    $monthShort = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.',
        5 => 'mai', 6 => 'juin', 7 => 'juil.', 8 => 'août',
        9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.',
    ];

    $series = $series->values()->map(function ($item) use ($monthShort) {
        $label = (string) data_get($item, 'label', '');
        $parts = explode('/', $label);

        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $day = (int) $parts[0];
            $month = (int) $parts[1];
            $label = $day . ' ' . ($monthShort[$month] ?? '');
        }

        return [
            'label' => $label,
            'amount' => (float) data_get($item, 'amount', 0),
        ];
    });

    $chartWidth = 720;
    $chartHeight = 150;
    $left = 52;
    $right = 704;
    $top = 10;
    $bottom = 115;
    $chartMax = max(800000, (float) $series->max('amount'));
    $count = max(1, $series->count());

    $chartPoints = $series->map(function ($item, $index) use ($count, $left, $right, $top, $bottom, $chartMax) {
        $x = $count === 1
            ? ($left + $right) / 2
            : $left + (($right - $left) / ($count - 1)) * $index;

        $amount = (float) data_get($item, 'amount', 0);
        $y = $bottom - (($amount / max(1, $chartMax)) * ($bottom - $top));

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => data_get($item, 'label', ''),
            'amount' => $amount,
        ];
    });

    $polyline = $chartPoints->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');

    $statusLabels = [
        'pending' => 'En attente',
        'accepted' => 'Acceptée',
        'preparing' => 'Préparation',
        'shipped' => 'Expédiée',
        'assigned' => 'Assignée',
        'picked_up' => 'Collectée',
        'in_transit' => 'En transit',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
        'paid' => 'Payée',
    ];

    $firstTopProduct = $topProducts->first();
    $topProductName = data_get($firstTopProduct, 'name')
        ?? data_get($firstTopProduct, 'product.name')
        ?? null;

    $firstPayout = $recentPayouts->first();
    $firstProduct = $recentProducts->first();

    $clientActivity = null;
    if ($recentNegotiations->isNotEmpty()) {
        $clientActivity = 'Une négociation client nécessite votre attention';
    } elseif ($recentReturns->isNotEmpty()) {
        $clientActivity = 'Une demande de retour a été enregistrée';
    } elseif ($recentDisputes->isNotEmpty()) {
        $clientActivity = 'Un litige client nécessite votre réponse';
    }
@endphp

<div class="vendor-dashboard">
    <div class="dashboard-shell">
        {{-- En-tête de la page --}}
        <section class="dashboard-head">
            <div class="dashboard-greeting">
                <div class="dashboard-title-line">
                    <strong>Bonjour, {{ $shopName }}</strong>
                    <span>— {{ $humanDate }}</span>
                </div>

                <div class="shop-meta">
                    <div class="shop-score">
                        <span class="shop-score-label">Score boutique</span>
                        <strong>{{ $shopScore }}%</strong>
                        <span class="score-bar" aria-label="Score boutique {{ $shopScore }}%">
                            <span style="width: {{ $shopScore }}%"></span>
                        </span>
                    </div>

                    <span class="meta-item">Catégorie : {{ $category }}</span>
                    <span class="meta-item">Zone : {{ $zone }}</span>
                    <span class="meta-item">Délai de traitement : {{ $processingTime }}</span>
                </div>
            </div>

            <div class="dashboard-actions">
                <a class="dashboard-action orange" href="{{ $safeRoute('vendor.add_product', [], '/vendeur/products/create') }}">
                    <i data-lucide="plus"></i>
                    Ajouter un produit
                </a>

                <a class="dashboard-action blue" href="{{ $safeRoute('vendor.orders', [], '/vendeur/orders') }}">
                    <i data-lucide="settings-2"></i>
                    Traiter les commandes
                </a>

                <a class="dashboard-action" href="{{ $safeRoute('vendor.products', [], '/vendeur/products') }}">
                    <i data-lucide="book-open"></i>
                    Voir le catalogue
                </a>

                <a class="dashboard-action" href="{{ $safeRoute('vendor.payouts.index', [], '/vendeur/payouts') }}">
                    <i data-lucide="credit-card"></i>
                    Consulter les paiements
                </a>
            </div>
        </section>

        {{-- Cartes principales : produits, commission et livraison sont séparés --}}
        <section class="main-kpis">
            <article class="dashboard-card main-kpi-card">
                <div class="kpi-heading">
                    Ventes produits aujourd'hui
                    <i class="kpi-info" data-lucide="info"></i>
                </div>
                <div class="kpi-body">
                    <div>
                        <div class="kpi-number-row">
                            <strong class="kpi-number">{{ $number($stat('sales_today')) }}</strong>
                            <span class="kpi-unit">FCFA</span>
                        </div>
                        <div class="kpi-subline">Montant payé pour vos produits</div>
                    </div>
                    <span class="kpi-icon"><i data-lucide="shopping-bag"></i></span>
                </div>
            </article>

            <article class="dashboard-card main-kpi-card">
                <div class="kpi-heading">
                    Commission OVANIE
                    <i class="kpi-info" data-lucide="info"></i>
                </div>
                <div class="kpi-body">
                    <div>
                        <div class="kpi-number-row">
                            <strong class="kpi-number">{{ $number($stat('commission_today')) }}</strong>
                            <span class="kpi-unit">FCFA</span>
                        </div>
                        <div class="kpi-subline">{{ $number($stat('commission_percent')) }} % sur les produits uniquement</div>
                    </div>
                    <span class="kpi-icon"><i data-lucide="badge-percent"></i></span>
                </div>
            </article>

            <article class="dashboard-card main-kpi-card">
                <div class="kpi-heading">
                    {{ $stat('uses_seller_logistics') ? 'Livraison vendeur' : 'Livraison OVANIE' }}
                    <i class="kpi-info" data-lucide="info"></i>
                </div>
                <div class="kpi-body">
                    <div>
                        @if($stat('uses_seller_logistics'))
                            <div class="kpi-number-row">
                                <strong class="kpi-number">{{ $number($stat('seller_delivery_today')) }}</strong>
                                <span class="kpi-unit">FCFA</span>
                            </div>
                            <div class="kpi-subline">Ajoutée intégralement à votre reversement</div>
                        @else
                            <div class="kpi-number-row">
                                <strong class="kpi-number" style="font-size:25px;">Gérée par OVANIE</strong>
                            </div>
                            <div class="kpi-subline">Non incluse dans votre reversement vendeur</div>
                        @endif
                    </div>
                    <span class="kpi-icon"><i data-lucide="truck"></i></span>
                </div>
            </article>

            <article class="dashboard-card main-kpi-card">
                <div class="kpi-heading">
                    Net généré aujourd'hui
                    <i class="kpi-info" data-lucide="info"></i>
                </div>
                <div class="kpi-body">
                    <div>
                        <div class="kpi-number-row">
                            <strong class="kpi-number">{{ $number($stat('net_today')) }}</strong>
                            <span class="kpi-unit">FCFA</span>
                        </div>
                        <div class="kpi-subline">
                            Net produits{{ $stat('uses_seller_logistics') ? ' + livraison vendeur' : '' }}
                        </div>
                    </div>
                    <span class="kpi-icon"><i data-lucide="wallet-cards"></i></span>
                </div>
            </article>

            <article class="dashboard-card main-kpi-card">
                <div class="kpi-heading">
                    Disponible au reversement
                    <i class="kpi-info" data-lucide="info"></i>
                </div>
                <div class="kpi-body">
                    <div>
                        <div class="kpi-number-row">
                            <strong class="kpi-number">{{ $number($stat('payout_available')) }}</strong>
                            <span class="kpi-unit">FCFA</span>
                        </div>
                        <div class="kpi-subline">Après livraison, réception et échéance</div>
                    </div>
                    <span class="kpi-icon"><i data-lucide="landmark"></i></span>
                </div>
            </article>
        </section>

        {{-- Indicateurs opérationnels --}}
        <section class="dashboard-card operations-strip">
            <a class="operation-item" href="{{ $safeRoute('vendor.orders', [], '/vendeur/orders') }}">
                <i data-lucide="truck"></i>
                <span class="operation-copy">
                    <span>Livraisons</span>
                    <strong>{{ $number($stat('delivered_orders')) }}</strong>
                </span>
            </a>

            <a class="operation-item orange" href="{{ $safeRoute('vendor.returns.index', [], '/vendeur/returns') }}">
                <i data-lucide="history"></i>
                <span class="operation-copy">
                    <span>Retours</span>
                    <strong>{{ $number($stat('returns_pending')) }}</strong>
                </span>
            </a>

            <a class="operation-item warning" href="{{ $safeRoute('vendor.disputes.index', [], '/vendeur/disputes') }}">
                <i data-lucide="triangle-alert"></i>
                <span class="operation-copy">
                    <span>Litiges</span>
                    <strong>{{ $number($stat('disputes_open')) }}</strong>
                </span>
            </a>

            <div class="operation-item">
                <i data-lucide="star"></i>
                <span class="operation-copy">
                    <span>Avis clients</span>
                    <strong>{{ $number($stat('reviews')) }}</strong>
                </span>
            </div>

            <div class="operation-item">
                <i data-lucide="handshake"></i>
                <span class="operation-copy">
                    <span>Négociations</span>
                    <strong>{{ $number($stat('negotiations_open')) }}</strong>
                </span>
            </div>
        </section>

        {{-- Courbe des ventes + synthèse financière --}}
        <section class="finance-row">
            <article class="dashboard-card chart-card">
                <div class="section-head">
                    <h2>Ventes des 7 derniers jours</h2>
                    <div class="chart-total">
                        <span>Total</span>
                        <strong>{{ $money($stat('sales_7_days')) }}</strong>
                    </div>
                </div>

                <svg class="sales-chart" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Évolution des ventes sur les 7 derniers jours">
                    @foreach([800000, 600000, 400000, 200000, 0] as $tick)
                        @php
                            $tickY = $bottom - (($tick / $chartMax) * ($bottom - $top));
                        @endphp
                        <line x1="{{ $left }}" y1="{{ $tickY }}" x2="{{ $right }}" y2="{{ $tickY }}" stroke="#e9edf2" stroke-width="1" />
                        <text x="0" y="{{ $tickY + 3 }}" fill="#405476" font-size="10" font-weight="500">
                            {{ $tick === 0 ? '0' : ($tick / 1000) . 'K' }}
                        </text>
                    @endforeach

                    <polyline
                        points="{{ $polyline }}"
                        fill="none"
                        stroke="#075ee8"
                        stroke-width="2.3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    @foreach($chartPoints as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#fff" stroke="#075ee8" stroke-width="2.2" />
                        <text x="{{ $point['x'] }}" y="139" text-anchor="middle" fill="#405476" font-size="9.5" font-weight="500">
                            {{ $point['label'] }}
                        </text>
                    @endforeach
                </svg>
            </article>

            <article class="dashboard-card finance-card">
                <div class="section-head">
                    <h2>Synthèse financière</h2>
                </div>

                <div class="finance-list">
                    <div class="finance-line">
                        <i data-lucide="calendar-range"></i>
                        <span>Ventes produits sur 30 jours</span>
                        <strong>{{ $money($stat('sales_30_days')) }}</strong>
                    </div>

                    <div class="finance-line">
                        <i data-lucide="badge-percent"></i>
                        <span>Commission OVANIE sur les produits</span>
                        <strong>{{ $money($stat('commission_30_days')) }}</strong>
                    </div>

                    <div class="finance-line">
                        <i data-lucide="wallet"></i>
                        <span>Net produits sur 30 jours</span>
                        <strong>{{ $money($stat('net_products_30_days')) }}</strong>
                    </div>

                    @if($stat('uses_seller_logistics'))
                        <div class="finance-line">
                            <i data-lucide="truck"></i>
                            <span>Livraison vendeur sur 30 jours</span>
                            <strong>{{ $money($stat('seller_delivery_30_days')) }}</strong>
                        </div>
                    @else
                        <div class="finance-line">
                            <i data-lucide="truck"></i>
                            <span>Livraison OVANIE</span>
                            <strong>Non reversée au vendeur</strong>
                        </div>
                    @endif

                    <div class="finance-line paid">
                        <i data-lucide="wallet-cards"></i>
                        <span>Net total généré sur 30 jours</span>
                        <strong>{{ $money($stat('net_30_days')) }}</strong>
                    </div>

                    <div class="finance-line">
                        <i data-lucide="hourglass"></i>
                        <span>En attente du paiement client</span>
                        <strong>{{ $money($stat('payout_waiting_payment')) }}</strong>
                    </div>

                    <div class="finance-line">
                        <i data-lucide="truck"></i>
                        <span>En attente de livraison ou réception</span>
                        <strong>{{ $money($stat('payout_waiting_reception') + $stat('payout_blocked')) }}</strong>
                    </div>

                    <div class="finance-line">
                        <i data-lucide="calendar-clock"></i>
                        <span>Reversements programmés</span>
                        <strong>{{ $money($stat('payout_scheduled')) }}</strong>
                    </div>

                    <div class="finance-line paid">
                        <i data-lucide="circle-dollar-sign"></i>
                        <span>Disponible au reversement</span>
                        <strong>{{ $money($stat('payout_available')) }}</strong>
                    </div>

                    <div class="finance-line paid">
                        <i data-lucide="landmark"></i>
                        <span>Déjà reversé</span>
                        <strong>{{ $money($stat('payout_paid')) }}</strong>
                    </div>
                </div>

                @if($stat('is_test_mode', false))
                    <div class="finance-mode-note">
                        <strong>Mode test :</strong> ces montants servent aux essais locaux. Aucun transfert d'argent réel n'est effectué.
                    </div>
                @endif
            </article>
        </section>

        {{-- Commandes récentes --}}
        <section class="dashboard-card orders-card">
            <div class="section-head">
                <h2>Commandes récentes</h2>
                <a class="section-link" href="{{ $safeRoute('vendor.orders', [], '/vendeur/orders') }}">
                    Voir toutes
                    <i data-lucide="chevron-right"></i>
                </a>
            </div>

            <div class="orders-table-wrap">
                <table class="orders-table">
                    <colgroup>
                        <col><col><col><col><col>
                    </colgroup>
                    <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Produit</th>
                        <th>Montant boutique</th>
                        <th>Statut</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($orders->take(4) as $order)
                        @php
                            $items = collect(data_get($order, 'items', []));
                            $firstItem = $items->first();
                            $orderCode = data_get($order, 'code')
                                ?? data_get($order, 'reference')
                                ?? data_get($order, 'order_number')
                                ?? 'CMD-' . str_pad((string) data_get($order, 'id', 0), 4, '0', STR_PAD_LEFT);
                            $clientName = data_get($order, 'user.name')
                                ?? data_get($order, 'client.name')
                                ?? data_get($order, 'customer_name')
                                ?? 'Client';
                            $productName = data_get($firstItem, 'product.name') ?? 'Produit';
                            $amount = $items->sum(fn ($item) => (float) (data_get($item, 'subtotal') ?? ((float) data_get($item, 'price', 0) * (float) data_get($item, 'quantity', 0))));
                            $status = data_get($firstItem, 'vendor_status') ?? data_get($order, 'status', 'pending');
                        @endphp
                        <tr>
                            <td>
                                <a class="section-link" href="{{ $safeRoute('vendor.orders.show', ['order' => data_get($order, 'id')], '/vendeur/orders') }}">
                                    #{{ $orderCode }}
                                </a>
                            </td>
                            <td>{{ $clientName }}</td>
                            <td>{{ $productName }}</td>
                            <td>{{ $money($amount) }}</td>
                            <td><span class="status-pill">{{ $statusLabels[$status] ?? ucfirst((string) $status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-orders">
                                    <i data-lucide="inbox"></i>
                                    <span>
                                        <strong>Aucune commande pour le moment</strong>
                                        <span>Les commandes de vos clients s'afficheront ici.</span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Activité & suivi --}}
        <section class="dashboard-card activity-card">
            <div class="section-head">
                <h2>Activité &amp; suivi</h2>
            </div>

            <div class="activity-list">
                <div class="activity-row">
                    <i data-lucide="package"></i>
                    <span class="activity-title">Stock critique</span>
                    <span class="activity-description">
                        @if($lowStockProducts->isNotEmpty())
                            {{ $lowStockProducts->count() }} produit(s) à réapprovisionner
                        @else
                            Aucun produit en stock critique
                        @endif
                    </span>
                    <a class="section-link" href="{{ $safeRoute('vendor.products', ['stock' => 'critical'], '/vendeur/products?stock=critical') }}">Voir le stock</a>
                    <i class="activity-chevron" data-lucide="chevron-right"></i>
                </div>

                <div class="activity-row">
                    <i data-lucide="chart-no-axes-column-increasing"></i>
                    <span class="activity-title">Meilleurs produits</span>
                    <span class="activity-description">{{ $topProductName ?: 'Aucune donnée pour le moment' }}</span>
                    <a class="section-link" href="{{ $safeRoute('vendor.products', ['sort' => 'best_sellers'], '/vendeur/products?sort=best_sellers') }}">Voir le classement</a>
                    <i class="activity-chevron" data-lucide="chevron-right"></i>
                </div>

                <div class="activity-row">
                    <i data-lucide="landmark"></i>
                    <span class="activity-title">Reversements récents</span>
                    <span class="activity-description">
                        @if($firstPayout)
                            {{ $money(data_get($firstPayout, 'payout_amount') ?? data_get($firstPayout, 'total_amount') ?? data_get($firstPayout, 'amount', 0)) }}
                        @else
                            Aucun reversement récent
                        @endif
                    </span>
                    <a class="section-link" href="{{ $safeRoute('vendor.payouts.index', [], '/vendeur/payouts') }}">Voir l'historique</a>
                    <i class="activity-chevron" data-lucide="chevron-right"></i>
                </div>

                <div class="activity-row">
                    <i data-lucide="tag"></i>
                    <span class="activity-title">Produits récents</span>
                    <span class="activity-description">{{ data_get($firstProduct, 'name') ?: 'Aucun produit ajouté récemment' }}</span>
                    <a class="section-link" href="{{ $safeRoute('vendor.add_product', [], '/vendeur/products/create') }}">Ajouter un produit</a>
                    <i class="activity-chevron" data-lucide="chevron-right"></i>
                </div>

                <div class="activity-row">
                    <i data-lucide="users"></i>
                    <span class="activity-title">Activité client</span>
                    <span class="activity-description">{{ $clientActivity ?: 'Aucune activité client récente' }}</span>
                    <a class="section-link" href="{{ $safeRoute('vendor.orders', [], '/vendeur/orders') }}">Voir les clients</a>
                    <i class="activity-chevron" data-lucide="chevron-right"></i>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
