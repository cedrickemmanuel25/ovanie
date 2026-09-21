@extends('layouts.vendor')

@section('title', 'Commandes clients | OVANIE')

@section('styles')
<style>
    :root {
        --oc-navy: #0b285b;
        --oc-blue: #075ee5;
        --oc-orange: #ff5a0a;
        --oc-green: #12b76a;
        --oc-purple: #7c3aed;
        --oc-red: #ef4444;
        --oc-text: #102a5c;
        --oc-muted: #64789a;
        --oc-border: #dfe6f0;
        --oc-soft: #f7f9fc;
        --oc-shadow: 0 8px 28px rgba(20, 48, 92, .06);
    }

    .oc-page,
    .oc-page * {
        box-sizing: border-box;
    }

    .oc-page {
        width: 100%;
        max-width: 1240px;
        margin: 0 auto;
        padding: 24px 16px 32px;
        color: var(--oc-text);
    }

    .oc-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 9px;
        margin: 0 0 18px;
        font-size: 12px;
        font-weight: 600;
    }

    .oc-breadcrumb a {
        color: var(--oc-blue);
        text-decoration: none;
    }

    .oc-breadcrumb span {
        color: var(--oc-text);
    }

    .oc-breadcrumb svg {
        width: 14px;
        height: 14px;
        color: #8da0bd;
    }

    .oc-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 28px;
    }

    .oc-title {
        margin: 0;
        color: var(--oc-navy);
        font-size: clamp(30px, 3vw, 39px);
        line-height: 1.05;
        letter-spacing: -.035em;
        font-weight: 900;
    }

    .oc-print {
        height: 46px;
        min-width: 132px;
        padding: 0 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border: 1.5px solid #315d9e;
        border-radius: 8px;
        background: #fff;
        color: #193d77;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: .18s ease;
    }

    .oc-print:hover {
        background: #f7faff;
        border-color: var(--oc-blue);
        color: var(--oc-blue);
    }

    .oc-print svg {
        width: 19px;
        height: 19px;
    }

    /* Status navigation */
    .oc-status-card {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        margin-bottom: 18px;
        overflow: hidden;
        border: 1px solid var(--oc-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: var(--oc-shadow);
    }

    .oc-status-tab {
        position: relative;
        min-width: 0;
        min-height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 0 14px;
        border-right: 1px solid #e6ebf2;
        color: #173766;
        text-decoration: none;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
        transition: background .15s ease, color .15s ease;
    }

    .oc-status-tab:last-child {
        border-right: 0;
    }

    .oc-status-tab:hover {
        background: #f8fbff;
    }

    .oc-status-tab::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 2px;
        background: transparent;
    }

    .oc-status-tab.active::after {
        background: var(--oc-blue);
    }

    .oc-status-tab svg {
        width: 21px;
        height: 21px;
        flex: 0 0 auto;
        stroke-width: 2;
    }

    .oc-status-tab.blue svg { color: var(--oc-blue); }
    .oc-status-tab.orange svg { color: #ff6a00; }
    .oc-status-tab.purple svg { color: #8b2ff0; }
    .oc-status-tab.green svg { color: #12b76a; }
    .oc-status-tab.red svg { color: #ef4444; }

    .oc-status-tab.active {
        color: var(--oc-navy);
        background: #fbfdff;
    }

    /* Search */
    .oc-search-card {
        margin-bottom: 14px;
        padding: 17px;
        border: 1px solid var(--oc-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: var(--oc-shadow);
    }

    .oc-search-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 145px;
        gap: 16px;
    }

    .oc-search-box {
        height: 46px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 14px;
        border: 1px solid #cfd9e8;
        border-radius: 7px;
        background: #fff;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .oc-search-box:focus-within {
        border-color: var(--oc-blue);
        box-shadow: 0 0 0 3px rgba(7, 94, 229, .08);
    }

    .oc-search-box svg {
        width: 19px;
        height: 19px;
        flex: 0 0 auto;
        color: #526f9b;
    }

    .oc-search-input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--oc-text);
        font: inherit;
        font-size: 12.5px;
        font-weight: 500;
    }

    .oc-search-input::placeholder {
        color: #8798b4;
    }

    .oc-search-button {
        height: 46px;
        border: 0;
        border-radius: 7px;
        background: linear-gradient(135deg, #102f65 0%, #071f4a 100%);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(14, 42, 92, .16);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .oc-search-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 9px 18px rgba(14, 42, 92, .2);
    }

    /* Main table card */
    .oc-table-card {
        overflow: hidden;
        border: 1px solid var(--oc-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: var(--oc-shadow);
    }

    .oc-table-wrap {
        width: 100%;
        overflow-x: hidden;
    }

    .oc-table {
        width: 100%;
        min-width: 0;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .oc-table th:nth-child(1), .oc-table td:nth-child(1) { width: 13%; }
    .oc-table th:nth-child(2), .oc-table td:nth-child(2) { width: 15%; }
    .oc-table th:nth-child(3), .oc-table td:nth-child(3) { width: 18%; }
    .oc-table th:nth-child(4), .oc-table td:nth-child(4) { width: 9%; }
    .oc-table th:nth-child(5), .oc-table td:nth-child(5) { width: 11%; }
    .oc-table th:nth-child(6), .oc-table td:nth-child(6) { width: 13%; }
    .oc-table th:nth-child(7), .oc-table td:nth-child(7) { width: 11%; }
    .oc-table th:nth-child(8), .oc-table td:nth-child(8) { width: 10%; }

    .oc-table thead tr {
        height: 64px;
    }

    .oc-table th {
        padding: 0 10px;
        border-bottom: 1px solid var(--oc-border);
        color: #213f71;
        background: #fff;
        text-align: left;
        font-size: 11.5px;
        font-weight: 800;
        white-space: nowrap;
    }

    .oc-th-inner {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .oc-sort {
        width: 13px;
        height: 13px;
        color: #5673a0;
    }

    .oc-table td {
        padding: 15px 10px;
        border-bottom: 1px solid #edf1f6;
        color: #203c6b;
        font-size: 12px;
        vertical-align: middle;
    }

    .oc-table tbody tr:not(.oc-empty-row):hover {
        background: #fbfdff;
    }

    .oc-order-number {
        font-weight: 800;
        color: #133569;
        white-space: nowrap;
    }

    .oc-client {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .oc-client > span:last-child,
    .oc-product > span:last-child {
        min-width: 0;
    }

    .oc-avatar {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #eaf2ff;
        color: var(--oc-blue);
        font-size: 10px;
        font-weight: 900;
    }

    .oc-client strong,
    .oc-product-name {
        display: block;
        max-width: 100%;
        overflow: hidden;
        color: #173766;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .oc-client small,
    .oc-product-sub,
    .oc-date small {
        display: block;
        margin-top: 2px;
        color: #7b8daa;
        font-size: 10.5px;
    }

    .oc-product {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .oc-product img {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        object-fit: contain;
        border: 1px solid #e5ebf3;
        border-radius: 6px;
        background: #f8fafc;
    }

    .oc-money {
        color: #102e61;
        font-weight: 800;
        white-space: nowrap;
    }

    .oc-status-badge {
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 9px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        white-space: nowrap;
    }

    .oc-status-badge svg {
        width: 12px;
        height: 12px;
        flex: 0 0 12px;
    }

    .oc-status-badge.pending { background: #fff5e8; color: #dc6a00; }
    .oc-status-badge.preparing { background: #f3efff; color: #7636db; }
    .oc-status-badge.ready { background: #eafbf2; color: #109653; }
    .oc-status-badge.shipped { background: #eaf3ff; color: #0b64d8; }
    .oc-status-badge.delivered { background: #eafbf2; color: #109653; }
    .oc-status-badge.cancelled { background: #fff0f1; color: #d93343; }

    .oc-date {
        white-space: nowrap;
        color: #203c6b;
        font-weight: 650;
    }

    .oc-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: 76px;
        min-width: 0;
        height: 34px;
        padding: 0 8px;
        border: 1px solid #b9c8dd;
        border-radius: 6px;
        background: #fff;
        color: #173f78;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
        transition: .15s ease;
    }

    .oc-action:hover {
        border-color: var(--oc-blue);
        color: var(--oc-blue);
        background: #f7faff;
    }

    .oc-action.primary {
        border-color: #ffb089;
        color: #f04f00;
        background: #fff7f2;
    }

    /* Empty state */
    .oc-empty-row td {
        height: 385px;
        padding: 0 !important;
        border-bottom: 0;
    }

    .oc-empty {
        height: 100%;
        min-height: 385px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 24px 52px;
        text-align: center;
    }

    .oc-empty-icon {
        width: 104px;
        height: 104px;
        display: grid;
        place-items: center;
        margin-bottom: 22px;
        border-radius: 50%;
        background: linear-gradient(145deg, #f4f8ff, #eaf1fb);
        color: #61799f;
    }

    .oc-empty-icon svg {
        width: 55px;
        height: 55px;
        stroke-width: 1.5;
    }

    .oc-empty h2 {
        margin: 0 0 10px;
        color: var(--oc-navy);
        font-size: 21px;
        line-height: 1.2;
        font-weight: 850;
    }

    .oc-empty p {
        margin: 0;
        color: #58709a;
        font-size: 13px;
        font-weight: 500;
    }

    /* Footer */
    .oc-footer {
        min-height: 74px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 0 20px;
        border-top: 1px solid var(--oc-border);
        color: #5d7295;
        font-size: 11.5px;
        font-weight: 500;
    }

    .oc-pagination {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .oc-page-btn {
        height: 42px;
        min-width: 118px;
        padding: 0 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid #d4deeb;
        border-radius: 7px;
        background: #fff;
        color: #34527d;
        text-decoration: none;
        font-size: 12px;
        font-weight: 650;
    }

    .oc-page-btn.disabled {
        color: #a9b6ca;
        background: #fafbfd;
        cursor: not-allowed;
    }

    .oc-page-btn svg {
        width: 16px;
        height: 16px;
    }

    .oc-current-page {
        width: 38px;
        height: 42px;
        display: grid;
        place-items: center;
        border: 1.5px solid var(--oc-blue);
        border-radius: 6px;
        background: #fff;
        color: var(--oc-blue);
        font-size: 12px;
        font-weight: 800;
    }

    @media (max-width: 1280px) {
        .oc-table th,
        .oc-table td {
            padding-left: 7px;
            padding-right: 7px;
        }

        .oc-avatar {
            width: 30px;
            height: 30px;
            flex-basis: 30px;
        }

        .oc-product img {
            width: 34px;
            height: 34px;
            flex-basis: 34px;
        }

        .oc-status-badge {
            padding: 5px 7px;
            font-size: 9.5px;
        }
    }

    @media (max-width: 980px) {
        .oc-table-wrap {
            overflow-x: auto;
            scrollbar-width: none;
        }

        .oc-table-wrap::-webkit-scrollbar {
            display: none;
        }

        .oc-table {
            min-width: 920px;
        }
    }

    @media (max-width: 1150px) {
        .oc-status-card {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .oc-status-tab:nth-child(4) {
            border-right: 0;
        }

        .oc-status-tab {
            border-bottom: 1px solid #e6ebf2;
        }
    }

    @media (max-width: 760px) {
        .oc-page {
            padding: 18px 10px 26px;
        }

        .oc-head {
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .oc-title {
            font-size: 29px;
        }

        .oc-print {
            min-width: 46px;
            width: 46px;
            padding: 0;
        }

        .oc-print span {
            display: none;
        }

        .oc-status-card {
            display: flex;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .oc-status-card::-webkit-scrollbar {
            display: none;
        }

        .oc-status-tab {
            flex: 0 0 auto;
            min-width: 150px;
            min-height: 62px;
            border-bottom: 0;
        }

        .oc-search-form {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .oc-search-button {
            width: 100%;
        }

        .oc-footer {
            align-items: flex-start;
            flex-direction: column;
            padding: 16px;
        }

        .oc-pagination {
            width: 100%;
            justify-content: space-between;
            gap: 8px;
        }

        .oc-page-btn {
            min-width: 105px;
        }
    }

    @media print {
        .oc-print,
        .oc-search-card,
        .oc-status-card,
        .oc-footer {
            display: none !important;
        }

        .oc-page {
            max-width: none;
            padding: 0;
        }

        .oc-table-card {
            box-shadow: none;
        }
    }
</style>
@endsection

@section('content')
@php
    $tabs = [
        'all' => ['label' => 'Tous', 'icon' => 'list-square', 'tone' => 'blue'],
        'pending' => ['label' => 'À traiter', 'icon' => 'clock-3', 'tone' => 'orange'],
        'preparing' => ['label' => 'En préparation', 'icon' => 'box', 'tone' => 'purple'],
        'ready' => ['label' => 'Prêtes', 'icon' => 'circle-check', 'tone' => 'green'],
        'shipped' => ['label' => 'En livraison', 'icon' => 'truck', 'tone' => 'blue'],
        'delivered' => ['label' => 'Livrées', 'icon' => 'circle-check', 'tone' => 'green'],
        'cancelled' => ['label' => 'Annulées', 'icon' => 'circle-x', 'tone' => 'red'],
    ];

    $statusLabels = [
        'pending' => 'À traiter',
        'preparing' => 'En préparation',
        'ready' => 'Prête',
        'shipped' => 'En livraison',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    $statusIcons = [
        'pending' => 'clock-3',
        'preparing' => 'box',
        'ready' => 'circle-check',
        'shipped' => 'truck',
        'delivered' => 'circle-check',
        'cancelled' => 'circle-x',
    ];

    $money = fn ($amount) => number_format((float) $amount, 0, ',', ' ') . ' FCFA';
@endphp

<div class="oc-page">
    <nav class="oc-breadcrumb" aria-label="Fil d'Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <span>Commandes</span>
    </nav>

    <header class="oc-head">
        <h1 class="oc-title">Commandes clients</h1>
        <button type="button" class="oc-print" onclick="window.print()">
            <i data-lucide="printer"></i>
            <span>Imprimer</span>
        </button>
    </header>

    <nav class="oc-status-card" aria-label="Statuts des commandes">
        @foreach ($tabs as $key => $tab)
            <a
                class="oc-status-tab {{ $tab['tone'] }} {{ $currentFilter === $key ? 'active' : '' }}"
                href="{{ route('vendor.orders', array_filter(['status' => $key, 'search' => $search])) }}"
            >
                <i data-lucide="{{ $tab['icon'] }}"></i>
                <span>{{ $tab['label'] }} ({{ $statusCounts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </nav>

    <section class="oc-search-card">
        <form class="oc-search-form" method="GET" action="{{ route('vendor.orders') }}">
            <input type="hidden" name="status" value="{{ $currentFilter }}">
            <label class="oc-search-box">
                <i data-lucide="search"></i>
                <input
                    class="oc-search-input"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Rechercher par n° de commande, client ou produit..."
                    autocomplete="off"
                >
            </label>
            <button class="oc-search-button" type="submit">Rechercher</button>
        </form>
    </section>

    <section class="oc-table-card">
        <div class="oc-table-wrap">
            <table class="oc-table">
                <thead>
                    <tr>
                        <th><span class="oc-th-inner">#COMMANDE <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">CLIENT <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">PRODUIT(S) <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">QUANTITÉ <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">TOTAL <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">STATUT <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="oc-th-inner">DATE <i class="oc-sort" data-lucide="chevrons-up-down"></i></span></th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $shopItems = $order->items;
                            $firstItem = $shopItems->first();
                            $product = $firstItem?->product;
                            $displayStatus = $order->vendor_display_status ?? 'pending';
                            $clientName = trim((string) ($order->client?->name ?? 'Client OVANIE'));
                            $initials = collect(preg_split('/\s+/', $clientName))
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                        @endphp
                        <tr>
                            <td>
                                <span class="oc-order-number">{{ $order->order_number ?? 'CMD-'.$order->id }}</span>
                            </td>
                            <td>
                                <div class="oc-client">
                                    <span class="oc-avatar">{{ $initials ?: 'CL' }}</span>
                                    <span>
                                        <strong>{{ $order->client?->name ?? 'Client OVANIE' }}</strong>
                                        <small>{{ $order->client?->city ?? $order->client?->phone ?? '—' }}</small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="oc-product">
                                    <img
                                        src="{{ $product?->mainImageUrl ?? asset('storage/products/placeholder.png') }}"
                                        alt="{{ $product?->name ?? 'Produit' }}"
                                        onerror="this.style.visibility='hidden'"
                                    >
                                    <span>
                                        <span class="oc-product-name">
                                            {{ \Illuminate\Support\Str::limit($product?->name ?? 'Produit supprimé', 30) }}
                                            @if ($shopItems->count() > 1)
                                                +{{ $shopItems->count() - 1 }}
                                            @endif
                                        </span>
                                        <span class="oc-product-sub">{{ $firstItem?->variant ?? '—' }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $shopItems->sum('quantity') }}</td>
                            <td><span class="oc-money">{{ $money($shopItems->sum('subtotal')) }}</span></td>
                            <td>
                                <span class="oc-status-badge {{ $displayStatus }}">
                                    <i data-lucide="{{ $statusIcons[$displayStatus] ?? 'clock-3' }}"></i>
                                    {{ $statusLabels[$displayStatus] ?? 'À traiter' }}
                                </span>
                            </td>
                            <td>
                                <span class="oc-date">
                                    {{ $order->created_at?->format('d/m/Y') }}
                                    <small>{{ $order->created_at?->format('H:i') }}</small>
                                </span>
                            </td>
                            <td>
                                <a
                                    href="{{ route('vendor.orders.show', $order->id) }}"
                                    class="oc-action {{ in_array($displayStatus, ['pending', 'preparing'], true) ? 'primary' : '' }}"
                                >
                                    {{ in_array($displayStatus, ['pending', 'preparing'], true) ? 'Préparer' : 'Voir' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="oc-empty-row">
                            <td colspan="8">
                                <div class="oc-empty">
                                    <div class="oc-empty-icon">
                                        <i data-lucide="inbox"></i>
                                    </div>
                                    <h2>Aucune commande trouvée.</h2>
                                    <p>
                                        @if ($search !== '')
                                            Aucune commande ne correspond à votre recherche.
                                        @elseif ($currentFilter !== 'all')
                                            Aucune commande dans ce statut pour le moment.
                                        @else
                                            Vous n'avez pas encore reçu de commande.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <footer class="oc-footer">
            <span>
                Affichage de {{ $orders->firstItem() ?? 0 }} à {{ $orders->lastItem() ?? 0 }}
                sur {{ $orders->total() }} commande{{ $orders->total() > 1 ? 's' : '' }}
            </span>

            <nav class="oc-pagination" aria-label="Pagination">
                @if ($orders->onFirstPage())
                    <span class="oc-page-btn disabled">
                        <i data-lucide="chevron-left"></i>
                        Précédent
                    </span>
                @else
                    <a class="oc-page-btn" href="{{ $orders->previousPageUrl() }}">
                        <i data-lucide="chevron-left"></i>
                        Précédent
                    </a>
                @endif

                <span class="oc-current-page">{{ $orders->currentPage() }}</span>

                @if ($orders->hasMorePages())
                    <a class="oc-page-btn" href="{{ $orders->nextPageUrl() }}">
                        Suivant
                        <i data-lucide="chevron-right"></i>
                    </a>
                @else
                    <span class="oc-page-btn disabled">
                        Suivant
                        <i data-lucide="chevron-right"></i>
                    </span>
                @endif
            </nav>
        </footer>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
</script>
@endsection
