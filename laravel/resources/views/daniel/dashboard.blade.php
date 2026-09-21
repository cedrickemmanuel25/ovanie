@extends('layouts.vendor')

@section('title', 'Tableau de bord vendeur | OVANIE')

@section('inline_styles')
    .seller-dashboard {
        width: 100%;
        min-width: 0;
    }
    .seller-wrap {
        width: 100%;
        max-width: 1530px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .seller-card {
        background: #fff;
        border: 1px solid #e4ebf4;
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .055);
        min-width: 0;
    }
    .seller-hero-row {
        display: grid;
        grid-template-columns: minmax(0, 2.05fr) minmax(360px, .98fr);
        gap: 14px;
        align-items: stretch;
    }
    .seller-hero {
        min-height: 220px;
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        color: #fff;
        padding: 28px 28px 24px;
        background:
            linear-gradient(90deg, rgba(4, 16, 43, .98) 0%, rgba(8, 39, 92, .94) 47%, rgba(20, 103, 235, .78) 100%),
            url("{{ asset('storage/logos/hero%20img.png') }}");
        background-size: cover, auto 108%;
        background-position: center, right center;
        box-shadow: 0 20px 44px rgba(5, 27, 70, .18);
    }
    .seller-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 84% 22%, rgba(255,255,255,.14), transparent 28%);
        pointer-events: none;
    }
    .seller-hero-content { position: relative; z-index: 1; max-width: 720px; }
    .seller-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 34px;
        padding: 0 15px;
        border-radius: 999px;
        background: rgba(255,255,255,.10);
        border: 1px solid rgba(255,255,255,.24);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .16em;
        font-weight: 900;
        color: #eaf2ff;
        margin-bottom: 20px;
    }
    .seller-pill svg { width: 17px; height: 17px; stroke-width: 2.4; }
    .seller-hero h1 {
        margin: 0;
        font-size: clamp(28px, 3vw, 38px);
        line-height: .98;
        letter-spacing: -0.055em;
        font-weight: 950;
    }
    .seller-hero p {
        margin: 14px 0 0;
        max-width: 760px;
        color: #e7efff;
        font-size: 15px;
        line-height: 1.55;
        font-weight: 600;
    }
    .seller-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 11px;
        margin-top: 21px;
    }
    .seller-btn {
        height: 43px;
        border-radius: 11px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 900;
        border: 1px solid rgba(255,255,255,.18);
        transition: transform .15s ease, box-shadow .15s ease;
        white-space: nowrap;
    }
    .seller-btn:hover { transform: translateY(-1px); }
    .seller-btn svg { width: 18px; height: 18px; stroke-width: 2.45; }
    .seller-btn.primary { background: #2563eb; color: #fff; box-shadow: 0 12px 22px rgba(37, 99, 235, .26); }
    .seller-btn.light { background: #fff; color: #071632; }
    .seller-btn.ghost { background: rgba(255,255,255,.08); color: #fff; border-color: rgba(255,255,255,.24); }

    .shop-card {
        padding: 18px;
        display: flex;
        flex-direction: column;
        min-height: 220px;
    }
    .shop-head {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    .shop-icon {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border-radius: 16px;
        background: #eef5ff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #2563eb;
    }
    .shop-icon svg { width: 26px; height: 26px; stroke-width: 2.35; }
    .shop-head h2 {
        margin: 0;
        font-size: 17px;
        font-weight: 950;
        letter-spacing: -0.03em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .shop-head p { margin: 4px 0 0; color: #64748b; font-size: 12px; font-weight: 650; }
    .score-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 16px;
        font-size: 12px;
        font-weight: 900;
    }
    .score-track { height: 9px; border-radius: 999px; background: #eaf1fb; overflow: hidden; margin-top: 8px; }
    .score-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2563eb, #22c55e); }
    .shop-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }
    .shop-mini {
        min-height: 58px;
        border: 1px solid #e4ebf4;
        background: #f8fafc;
        border-radius: 13px;
        padding: 12px;
    }
    .shop-mini span {
        display: block;
        color: #64748b;
        font-size: 10.5px;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-weight: 950;
        margin-bottom: 6px;
    }
    .shop-mini strong { font-size: 12.5px; font-weight: 950; color: #071632; }
    .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 999px; background: #22c55e; margin-right: 5px; }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .stat-card {
        height: 75px;
        padding: 15px 17px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 15px;
        flex: 0 0 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-icon svg { width: 24px; height: 24px; stroke-width: 2.45; }
    .tone-blue { background: #e8f1ff; color: #2563eb; }
    .tone-orange { background: #fff0db; color: #f97316; }
    .tone-green { background: #dcfce7; color: #16a34a; }
    .tone-purple { background: #f1e4ff; color: #9333ea; }
    .tone-red { background: #fee2e2; color: #dc2626; }
    .tone-yellow { background: #fef3c7; color: #b77900; }
    .stat-text small {
        display: block;
        color: #64748b;
        font-size: 10px;
        letter-spacing: .12em;
        font-weight: 950;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .stat-value {
        font-size: 24px;
        line-height: 1;
        font-weight: 950;
        color: #071632;
        letter-spacing: -0.05em;
        white-space: nowrap;
    }
    .stat-meta {
        color: #64748b;
        margin-top: 5px;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 750;
    }

    .finance-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(360px, 1fr);
        gap: 14px;
    }
    .panel { padding: 18px 20px; }
    .panel-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 5px;
    }
    .panel-title-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .panel-title-left svg { width: 22px; height: 22px; stroke-width: 2.45; color: #071632; flex: 0 0 auto; }
    .panel h3 {
        margin: 0;
        font-size: 19px;
        line-height: 1.05;
        font-weight: 950;
        letter-spacing: -0.045em;
    }
    .panel-subtitle { margin: 0; color: #64748b; font-size: 12.5px; font-weight: 700; line-height: 1.4; }
    .panel-link { color: #1267ff; font-size: 12px; font-weight: 950; white-space: nowrap; }
    .panel-money { color: #16a34a; font-size: 15px; font-weight: 950; white-space: nowrap; }

    .sales-chart {
        height: 170px;
        margin-top: 14px;
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        align-items: end;
        gap: 12px;
    }
    .chart-day { text-align: center; min-width: 0; }
    .chart-amount { font-size: 11px; font-weight: 950; color: #071632; margin-bottom: 6px; white-space: nowrap; }
    .chart-bar-wrap { height: 56px; display: flex; align-items: flex-end; justify-content: center; border-bottom: 2px solid #dbeafe; }
    .chart-bar { width: 72%; min-width: 36px; border-radius: 999px 999px 0 0; background: linear-gradient(180deg, #1d4ed8 0%, #60a5fa 100%); box-shadow: 0 -8px 16px rgba(37, 99, 235, .16); }
    .chart-label { margin-top: 8px; color: #475569; font-size: 11px; font-weight: 900; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-top: 16px;
    }
    .summary-item {
        min-height: 62px;
        border: 1px solid #e4ebf4;
        background: #f8fafc;
        border-radius: 13px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 13px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .summary-icon svg { width: 20px; height: 20px; stroke-width: 2.3; }
    .summary-item strong { display: block; font-size: 12px; font-weight: 950; margin-bottom: 3px; line-height: 1.18; }
    .summary-item span { font-size: 12px; font-weight: 900; color: #64748b; }

    .operations-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.48fr) minmax(220px, .72fr) minmax(220px, .82fr) minmax(240px, .9fr);
        gap: 14px;
        align-items: stretch;
    }
    .orders-table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
    .orders-table th {
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .13em;
        font-size: 9.5px;
        font-weight: 950;
        text-align: left;
        padding: 0 6px 9px 0;
        border-bottom: 1px solid #e4ebf4;
    }
    .orders-table td {
        padding: 9px 6px 9px 0;
        border-bottom: 1px solid #eef2f7;
        font-size: 11px;
        color: #334155;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .orders-table a { color: #1267ff; font-weight: 900; }
    .badge-status {
        display: inline-flex;
        align-items: center;
        height: 20px;
        padding: 0 8px;
        border-radius: 999px;
        font-size: 9.5px;
        font-weight: 950;
        white-space: nowrap;
    }
    .badge-orange { background: #fff0db; color: #ea580c; }
    .badge-blue { background: #dbeafe; color: #2563eb; }
    .badge-green { background: #dcfce7; color: #16a34a; }
    .badge-purple { background: #f3e8ff; color: #9333ea; }
    .badge-red { background: #fee2e2; color: #dc2626; }
    .empty-box {
        border: 1px dashed #cbd8e8;
        border-radius: 12px;
        min-height: 58px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 12px;
        color: #64748b;
        font-size: 12px;
        font-weight: 850;
        background: #fbfdff;
        margin-top: 12px;
    }
    .list-compact { margin-top: 13px; display: flex; flex-direction: column; gap: 8px; }
    .list-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 11.5px; font-weight: 850; color: #334155; border-bottom: 1px solid #eef2f7; padding-bottom: 7px; }
    .list-line:last-child { border-bottom: 0; padding-bottom: 0; }
    .list-line strong { color: #071632; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .list-line span { flex: 0 0 auto; color: #1267ff; font-weight: 950; }
    .list-line.danger span { color: #dc2626; }

    .bottom-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
        gap: 14px;
    }
    .product-row, .activity-row {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 8px 0;
        border-bottom: 1px solid #eef2f7;
        min-width: 0;
    }
    .product-row:last-child, .activity-row:last-child { border-bottom: 0; }
    .product-img {
        width: 44px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        background: #f1f5f9;
        border: 1px solid #e4ebf4;
        flex: 0 0 auto;
    }
    .product-row strong, .activity-row strong { display: block; font-size: 11px; font-weight: 950; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .product-row span, .activity-row span { display: block; margin-top: 2px; font-size: 10px; color: #64748b; font-weight: 700; }
    .activity-dot { width: 26px; height: 26px; border-radius: 999px; flex: 0 0 auto; display:flex; align-items:center; justify-content:center; }
    .activity-dot svg { width: 15px; height: 15px; stroke-width: 2.4; }

    @media (max-width: 1320px) {
        .seller-hero-row { grid-template-columns: 1fr; }
        .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .finance-grid { grid-template-columns: 1fr; }
        .operations-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .seller-hero { padding: 22px; min-height: 260px; }
        .seller-actions { flex-direction: column; align-items: stretch; }
        .seller-btn { width: 100%; }
        .shop-grid, .stat-grid, .summary-grid, .operations-grid, .bottom-grid { grid-template-columns: 1fr; }
        .sales-chart { gap: 5px; }
        .chart-amount { font-size: 9px; }
        .orders-table { min-width: 560px; }
        .table-scroll { overflow-x: auto; }
    }
@endsection

@section('content')
@php
    $stats = $stats ?? [];
    $shop = $shop ?? auth()->user()?->shop;
    $orders = collect($orders ?? []);
    $recentProducts = collect($recentProducts ?? []);
    $lowStockProducts = collect($lowStockProducts ?? []);
    $topProducts = collect($topProducts ?? []);
    $recentPayouts = collect($recentPayouts ?? []);
    $recentNegotiations = collect($recentNegotiations ?? []);
    $recentReturns = collect($recentReturns ?? []);
    $recentDisputes = collect($recentDisputes ?? []);
    $series = $salesSeries ?? [];

    $routeUrl = function ($name, $params = [], $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : $fallback;
    };
    $money = fn($value) => number_format((float) ($value ?? 0), 0, ',', ' ') . ' FCFA';
    $number = fn($value) => number_format((float) ($value ?? 0), 0, ',', ' ');
    $sellerName = auth()->user()?->name ?? 'vendeur';
    $shopName = $shop?->name ?: $sellerName;
    $shopCity = $shop?->city ?: 'Abidjan';
    $shopCommune = $shop?->commune ?: $shopCity;
    $shopCategory = $shop?->main_category ?: 'Matériaux & Quincaillerie';
    $shopZone = $shop?->delivery_zone ?: 'Grand Abidjan';
    $shopProcessing = $shop?->processing_time ?: '24–48h';
    $shopStatusText = ($shop?->status === 'approved' && (bool) $shop?->is_active) ? 'Approved · Active' : ucfirst($shop?->status ?? 'Pending');
    $shopScore = min(100, (int)($stats['shop_score'] ?? 92));

    if (empty($series)) {
        for ($i = 6; $i >= 0; $i--) {
            $series[] = ['label' => now()->subDays($i)->format('d/m'), 'amount' => 0];
        }
    }
    $maxSeries = max(1, collect($series)->max('amount') ?: 1);

    $statusBadgeClass = function ($status) {
        return match ($status) {
            'pending', 'accepted', 'preparing', 'ready' => 'badge-orange',
            'paid', 'validated', 'processing' => 'badge-blue',
            'shipped', 'in_delivery' => 'badge-purple',
            'delivered', 'completed' => 'badge-green',
            'cancelled', 'failed', 'rejected' => 'badge-red',
            default => 'badge-blue',
        };
    };
    $statusLabel = function ($status) {
        return match ($status) {
            'pending' => 'À préparer',
            'accepted' => 'Validée',
            'preparing' => 'En traitement',
            'ready' => 'Prête',
            'paid' => 'Payée',
            'validated' => 'Validée',
            'processing' => 'Traitement',
            'shipped', 'in_delivery' => 'Expédiée',
            'delivered', 'completed' => 'Livrée',
            'cancelled' => 'Annulée',
            default => ucfirst((string) $status ?: 'Nouveau'),
        };
    };
@endphp

<div class="seller-dashboard">
    <div class="seller-wrap">
        <div class="seller-hero-row">
            <section class="seller-hero">
                <div class="seller-hero-content">
                    <span class="seller-pill"><i data-lucide="store"></i> OVANIE SELLER CENTRAL</span>
                    <h1>Bonjour, {{ $shopName }} 👋</h1>
                    <p>{{ now()->translatedFormat('l d F Y') }} — pilotez vos ventes, commandes, produits, livraisons, reversements et alertes depuis un seul tableau de bord.</p>
                    <div class="seller-actions">
                        <a class="seller-btn primary" href="{{ $routeUrl('vendor.add_product') }}"><i data-lucide="plus-circle"></i> Ajouter un produit</a>
                        <a class="seller-btn light" href="{{ $routeUrl('vendor.orders', ['status' => 'pending']) }}"><i data-lucide="shopping-bag"></i> Traiter les commandes</a>
                        <a class="seller-btn ghost" href="{{ $routeUrl('home', [], '/') }}"><i data-lucide="globe-2"></i> Retour sur OVANIE</a>
                    </div>
                </div>
            </section>

            <a href="{{ $routeUrl('vendor.shop.profile') }}" class="seller-card shop-card">
                <div class="shop-head">
                    <div class="shop-icon"><i data-lucide="store"></i></div>
                    <div style="min-width:0;">
                        <h2>{{ $shopName }}</h2>
                        <p>{{ $shopCity }} · {{ $shopCommune }}</p>
                    </div>
                </div>
                <div class="score-row"><span>Score boutique</span><strong>{{ $shopScore }}%</strong></div>
                <div class="score-track"><div class="score-fill" style="width: {{ $shopScore }}%;"></div></div>
                <div class="shop-grid">
                    <div class="shop-mini"><span>Statut</span><strong><i class="status-dot"></i>{{ $shopStatusText }}</strong></div>
                    <div class="shop-mini"><span>Catégorie</span><strong>{{ $shopCategory }}</strong></div>
                    <div class="shop-mini"><span>Zone</span><strong>{{ $shopZone }}</strong></div>
                    <div class="shop-mini"><span>Traitement</span><strong>{{ $shopProcessing }}</strong></div>
                </div>
            </a>
        </div>

        <section class="stat-grid">
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.products') }}">
                <span class="stat-icon tone-blue"><i data-lucide="package"></i></span>
                <span class="stat-text"><small>Produits</small><span class="stat-value">{{ $number($stats['products'] ?? 0) }}</span><span class="stat-meta">{{ $number($stats['active_products'] ?? 0) }} actifs · {{ $number($stats['pending_products'] ?? 0) }} à valider · {{ $number($stats['archived_products'] ?? 0) }} archivés</span></span>
            </a>
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.orders') }}">
                <span class="stat-icon tone-orange"><i data-lucide="clipboard-list"></i></span>
                <span class="stat-text"><small>Commandes</small><span class="stat-value">{{ $number($stats['orders'] ?? 0) }}</span><span class="stat-meta">{{ $number($stats['orders_to_prepare'] ?? 0) }} à préparer · {{ $number($stats['shipped_orders'] ?? 0) }} expédiées</span></span>
            </a>
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.payouts.index') }}">
                <span class="stat-icon tone-green"><i data-lucide="wallet-cards"></i></span>
                <span class="stat-text"><small>Net vendeur</small><span class="stat-value">{{ $money($stats['net_revenue'] ?? 0) }}</span><span class="stat-meta">{{ $money($stats['pending_payouts'] ?? 0) }} à reverser</span></span>
            </a>
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.orders', ['delivery_status' => 'shipping']) }}">
                <span class="stat-icon tone-purple"><i data-lucide="truck"></i></span>
                <span class="stat-text"><small>Livraisons</small><span class="stat-value">{{ $number(($stats['shipped_orders'] ?? 0) + ($stats['delivered_orders'] ?? 0)) }}</span><span class="stat-meta">{{ $number($stats['shipped_orders'] ?? 0) }} en expédition · {{ $number($stats['delivered_orders'] ?? 0) }} livrées</span></span>
            </a>
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.returns.index') }}">
                <span class="stat-icon tone-red"><i data-lucide="rotate-ccw"></i></span>
                <span class="stat-text"><small>Retours</small><span class="stat-value">{{ $number($stats['returns_total'] ?? 0) }}</span><span class="stat-meta">{{ $number($stats['returns_pending'] ?? 0) }} demandes au total</span></span>
            </a>
            <a class="seller-card stat-card" href="{{ $routeUrl('vendor.disputes.index') }}">
                <span class="stat-icon tone-red"><i data-lucide="shield-alert"></i></span>
                <span class="stat-text"><small>Litiges</small><span class="stat-value">{{ $number($stats['disputes_open'] ?? 0) }}</span><span class="stat-meta">{{ $number($stats['disputes_escalated'] ?? 0) }} escaladé</span></span>
            </a>
            <div class="seller-card stat-card">
                <span class="stat-icon tone-yellow"><i data-lucide="star"></i></span>
                <span class="stat-text"><small>Avis clients</small><span class="stat-value">{{ number_format((float)($stats['average_rating'] ?? 0), 1) }}/5</span><span class="stat-meta">{{ $number($stats['reviews'] ?? 0) }} avis reçus</span></span>
            </div>
            <div class="seller-card stat-card">
                <span class="stat-icon tone-orange"><i data-lucide="message-circle"></i></span>
                <span class="stat-text"><small>Négociations</small><span class="stat-value">{{ $number($stats['negotiations_total'] ?? 0) }}</span><span class="stat-meta">{{ $number($stats['negotiations_open'] ?? 0) }} négociations au total</span></span>
            </div>
        </section>

        <section class="finance-grid">
            <div class="seller-card panel">
                <div class="panel-title">
                    <div>
                        <div class="panel-title-left"><i data-lucide="bar-chart-3"></i><h3>Ventes des 7 derniers jours</h3></div>
                        <p class="panel-subtitle">Aperçu rapide des ventes de votre boutique.</p>
                    </div>
                    <strong class="panel-money">{{ $money($stats['sales_7_days'] ?? 0) }}</strong>
                </div>
                <div class="sales-chart">
                    @foreach($series as $day)
                        @php $height = 10 + ((float)($day['amount'] ?? 0) / $maxSeries) * 70; @endphp
                        <div class="chart-day">
                            <div class="chart-amount">{{ $money($day['amount'] ?? 0) }}</div>
                            <div class="chart-bar-wrap"><div class="chart-bar" style="height: {{ $height }}%;"></div></div>
                            <div class="chart-label">{{ $day['label'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <a href="{{ $routeUrl('vendor.payouts.index') }}" class="seller-card panel">
                <div class="panel-title-left"><i data-lucide="activity"></i><h3>Synthèse financière</h3></div>
                <p class="panel-subtitle">Montants calculés uniquement sur vos lignes de commande.</p>
                <div class="summary-grid">
                    <div class="summary-item"><span class="summary-icon"><i data-lucide="link"></i></span><span><strong>Ventes aujourd’hui</strong><span>{{ $money($stats['sales_today'] ?? 0) }}</span></span></div>
                    <div class="summary-item"><span class="summary-icon"><i data-lucide="calendar-days"></i></span><span><strong>Ventes 30 jours</strong><span>{{ $money($stats['sales_30_days'] ?? 0) }}</span></span></div>
                    <div class="summary-item"><span class="summary-icon"><i data-lucide="percent"></i></span><span><strong>Commission OVANIE</strong><span>{{ $money($stats['commission'] ?? 0) }}</span></span></div>
                    <div class="summary-item"><span class="summary-icon"><i data-lucide="check"></i></span><span><strong>Déjà payé</strong><span>{{ $money($stats['paid_payouts'] ?? 0) }}</span></span></div>
                </div>
            </a>
        </section>

        <section class="operations-grid">
            <div class="seller-card panel">
                <div class="panel-title">
                    <div>
                        <div class="panel-title-left"><i data-lucide="clipboard-list"></i><h3>Commandes récentes</h3></div>
                        <p class="panel-subtitle">Dernières commandes contenant vos produits.</p>
                    </div>
                    <a class="panel-link" href="{{ $routeUrl('vendor.orders') }}">Voir toutes</a>
                </div>
                <div class="table-scroll">
                    <table class="orders-table">
                        <thead><tr><th>Commande</th><th>Client</th><th>Produit</th><th>Montant boutique</th><th>Statut</th></tr></thead>
                        <tbody>
                        @forelse($orders->take(4) as $order)
                            @php
                                $items = collect($order->items ?? []);
                                $firstItem = $items->first();
                                $amount = $items->sum(fn($item) => (float)($item->subtotal ?? ((float)($item->price ?? 0) * (float)($item->quantity ?? 0))));
                                $status = $firstItem->vendor_status ?? $order->status ?? 'pending';
                                $orderCode = $order->reference ?? $order->order_number ?? 'CMD-'.str_pad((string)$order->id, 5, '0', STR_PAD_LEFT);
                                $clientName = data_get($order, 'client.name') ?? data_get($order, 'user.name') ?? $order->customer_name ?? 'Client';
                                $productName = data_get($firstItem, 'product.name') ?? 'Produit';
                            @endphp
                            <tr>
                                <td><a href="{{ $routeUrl('vendor.orders.show', $order) }}">#{{ $orderCode }}</a></td>
                                <td>{{ $clientName }}</td>
                                <td>{{ $productName }}</td>
                                <td>{{ $money($amount) }}</td>
                                <td><span class="badge-status {{ $statusBadgeClass($status) }}">{{ $statusLabel($status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-box">Aucune commande pour le moment.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="seller-card panel">
                <div class="panel-title"><div class="panel-title-left"><i data-lucide="triangle-alert"></i><h3>Stock critique</h3></div><a class="panel-link" href="{{ $routeUrl('vendor.products', ['stock' => 'critical']) }}">Produits</a></div>
                <p class="panel-subtitle">Produits à réapprovisionner.</p>
                @if($lowStockProducts->isEmpty())
                    <div class="empty-box">Aucun stock critique.</div>
                @else
                    <div class="list-compact">
                        @foreach($lowStockProducts->take(4) as $product)
                            <a class="list-line danger" href="{{ $routeUrl('vendor.products.edit', $product) }}"><strong>{{ $product->name }}</strong><span>{{ $number($product->stock ?? 0) }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="seller-card panel">
                <div class="panel-title"><div class="panel-title-left"><i data-lucide="trophy"></i><h3>Meilleurs produits</h3></div><a class="panel-link" href="{{ $routeUrl('vendor.products', ['sort' => 'best_sellers']) }}">Voir</a></div>
                <p class="panel-subtitle">Produits les plus vendus par quantité.</p>
                @if($topProducts->isEmpty())
                    <div class="empty-box">Aucune vente produit enregistrée.</div>
                @else
                    <div class="list-compact">
                        @foreach($topProducts->take(4) as $item)
                            <a class="list-line" href="{{ $routeUrl('vendor.products.edit', $item->product) }}"><strong>{{ $item->product->name ?? 'Produit' }}</strong><span>{{ $number($item->sold_quantity ?? 0) }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="seller-card panel">
                <div class="panel-title"><div class="panel-title-left"><i data-lucide="wallet-cards"></i><h3>Reversements récents</h3></div><a class="panel-link" href="{{ $routeUrl('vendor.payouts.index') }}">Voir</a></div>
                <p class="panel-subtitle">Historique rapide de vos paiements vendeur.</p>
                @if($recentPayouts->isEmpty())
                    <div class="empty-box">Aucun reversement pour le moment.</div>
                @else
                    <div class="list-compact">
                        @foreach($recentPayouts->take(3) as $payout)
                            <a class="list-line" href="{{ $routeUrl('vendor.payouts.show', $payout) }}"><strong>{{ optional($payout->created_at)->format('d/m/Y') }}</strong><span>{{ $money($payout->payout_amount ?? 0) }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="bottom-grid">
            <div class="seller-card panel">
                <div class="panel-title"><div><div class="panel-title-left"><i data-lucide="boxes"></i><h3>Produits récents</h3></div><p class="panel-subtitle">Derniers produits ajoutés ou modifiés.</p></div><a class="panel-link" href="{{ $routeUrl('vendor.add_product') }}">Ajouter</a></div>
                @if($recentProducts->isEmpty())
                    <div class="empty-box">Aucun produit. Ajoutez votre premier produit.</div>
                @else
                    @foreach($recentProducts->take(3) as $product)
                        @php
                            $gallery = is_array($product->gallery ?? null) ? $product->gallery : [];
                            $image = $product->image ?? $product->main_image ?? ($gallery[0] ?? null);
                        @endphp
                        <a class="product-row" href="{{ $routeUrl('vendor.products.edit', $product) }}">
                            <img class="product-img" src="{{ $image ? asset('storage/'.$image) : asset('storage/products/placeholder.png') }}" alt="{{ $product->name }}">
                            <span style="min-width:0;"><strong>{{ $product->name }}</strong><span>Modifié le {{ optional($product->updated_at)->format('d/m/Y') }}</span></span>
                        </a>
                    @endforeach
                @endif
            </div>

            <div class="seller-card panel">
                <div class="panel-title-left"><i data-lucide="messages-square"></i><h3>Activité client</h3></div>
                <p class="panel-subtitle">Négociations, retours et litiges récents.</p>
                @php
                    $activities = collect();
                    foreach ($recentNegotiations->take(2) as $item) $activities->push(['icon' => 'message-circle', 'tone' => 'tone-orange', 'title' => 'Négociation client', 'subtitle' => data_get($item, 'product.name') ?? 'Produit']);
                    foreach ($recentReturns->take(2) as $item) $activities->push(['icon' => 'rotate-ccw', 'tone' => 'tone-red', 'title' => 'Retour demandé', 'subtitle' => optional($item->created_at)->format('d/m/Y')]);
                    foreach ($recentDisputes->take(2) as $item) $activities->push(['icon' => 'shield-alert', 'tone' => 'tone-red', 'title' => 'Litige / SAV', 'subtitle' => optional($item->created_at)->format('d/m/Y')]);
                @endphp
                @if($activities->isEmpty())
                    <div class="empty-box">Aucune activité récente.</div>
                @else
                    <div class="list-compact">
                        @foreach($activities->take(3) as $activity)
                            <div class="activity-row"><span class="activity-dot {{ $activity['tone'] }}"><i data-lucide="{{ $activity['icon'] }}"></i></span><span style="min-width:0;"><strong>{{ $activity['title'] }}</strong><span>{{ $activity['subtitle'] }}</span></span></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
