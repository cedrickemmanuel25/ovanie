@extends('layouts.vendor')

@section('title', 'Paiements / Mes ventes | OVANIE')

@php
    $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' FCFA';

    $methodLabels = [
        'paydunya' => 'Paiement en ligne',
        'bank_transfer' => 'Virement bancaire',
        'cash_on_delivery' => 'Paiement à la livraison',
    ];

    $methodIcons = [
        'paydunya' => 'credit-card',
        'bank_transfer' => 'landmark',
        'cash_on_delivery' => 'hand-coins',
    ];

    $statusOptions = [
        'waiting_payment' => 'À confirmer',
        'waiting_reception' => 'Attente réception',
        'blocked' => 'En attente de livraison',
        'pending' => 'Programmé',
        'approved' => 'Prêt à payer',
        'processing' => 'En traitement',
        'paid' => 'Payé',
        'failed' => 'Échec',
    ];

    $waitingAmount = (float) (($summary['waiting_payment'] ?? 0) + ($summary['waiting_reception'] ?? 0));
    $confirmedAmount = (float) (($summary['scheduled'] ?? 0) + ($summary['ready'] ?? 0) + ($summary['processing'] ?? 0) + ($summary['paid'] ?? 0));
    $readyAmount = (float) ($summary['ready'] ?? 0);
    $blockedAmount = (float) ($summary['blocked'] ?? 0);
    $availableAmount = (float) (($summary['scheduled'] ?? 0) + ($summary['ready'] ?? 0));

    $dominantMethodLabel = $methodLabels[$dominantPaymentMethod ?? ''] ?? 'Aucune donnée';
    $dominantMethodIcon = $methodIcons[$dominantPaymentMethod ?? ''] ?? 'wallet-cards';

    $chartRows = collect($chartData ?? []);
    $chartValues = $chartRows->pluck('amount')->map(fn ($value) => (float) $value)->values();
    $chartMax = max(1, (float) ($chartValues->max() ?? 0));
    $chartWidth = 760;
    $chartHeight = 170;
    $chartPadX = 10;
    $chartPadY = 20;
    $chartCount = max(1, $chartValues->count());

    $chartPoints = $chartValues->map(function ($value, $index) use ($chartWidth, $chartHeight, $chartPadX, $chartPadY, $chartCount, $chartMax) {
        $x = $chartCount <= 1
            ? $chartWidth / 2
            : $chartPadX + ($index * (($chartWidth - ($chartPadX * 2)) / ($chartCount - 1)));
        $y = $chartHeight - $chartPadY - (($value / $chartMax) * ($chartHeight - ($chartPadY * 2)));

        return round($x, 2) . ',' . round($y, 2);
    })->implode(' ');

    $chartLabels = $chartRows->count() >= 5
        ? collect([0, 7, 14, 21, 29])->map(fn ($index) => $chartRows->get($index))->filter()->values()
        : $chartRows;

    $sales30Days = (float) $chartRows->sum('amount');
    $shopName = $shop->name ?? auth()->user()->name ?? 'Votre boutique';
    $lastPaymentLabel = $lastPaymentAt
        ? \Carbon\Carbon::parse($lastPaymentAt)->translatedFormat('d M Y à H:i')
        : 'Aucun paiement reçu';
@endphp

@section('styles')
<style>
    :root {
        --sales-navy: #0a2a62;
        --sales-blue: #0d5ee8;
        --sales-blue-2: #1f6ef5;
        --sales-orange: #ff6508;
        --sales-green: #11a94c;
        --sales-red: #e5484d;
        --sales-purple: #7158e2;
        --sales-text: #112d5e;
        --sales-muted: #61749a;
        --sales-border: #dce5f1;
        --sales-soft: #f7f9fc;
        --sales-card: #ffffff;
        --sales-shadow: 0 8px 28px rgba(14, 42, 88, .06);
    }

    .sales-page {
        width: 100%;
        color: var(--sales-text);
    }

    .sales-breadcrumbs {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        color: var(--sales-muted);
        font-size: 12px;
        font-weight: 700;
    }

    .sales-breadcrumbs a {
        color: var(--sales-blue);
        text-decoration: none;
    }

    .sales-breadcrumbs svg {
        width: 14px;
        height: 14px;
    }

    .sales-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 20px;
    }

    .sales-head h1 {
        margin: 0;
        color: var(--sales-navy);
        font-size: clamp(28px, 2.6vw, 42px);
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .sales-head p {
        margin: 9px 0 0;
        color: var(--sales-muted);
        font-size: 13px;
    }

    .sales-head-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .sales-btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 16px;
        border: 1px solid #b9c9df;
        border-radius: 7px;
        background: #fff;
        color: var(--sales-navy);
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
    }

    .sales-btn:hover {
        border-color: var(--sales-blue);
        color: var(--sales-blue);
        transform: translateY(-1px);
    }

    .sales-btn svg { width: 17px; height: 17px; }

    .sales-alert {
        margin-bottom: 14px;
        padding: 13px 15px;
        border: 1px solid #cde7d5;
        border-radius: 8px;
        background: #f4fff7;
        color: #146c36;
        font-size: 12px;
        font-weight: 700;
    }

    .sales-alert.info {
        border-color: #cfe0ff;
        background: #f4f8ff;
        color: #265aaf;
    }

    .sales-alert.error {
        border-color: #fecaca;
        background: #fff6f6;
        color: #b42318;
    }

    .sales-top-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(360px, .85fr);
        gap: 18px;
        margin-bottom: 18px;
    }

    .sales-card {
        border: 1px solid var(--sales-border);
        border-radius: 10px;
        background: var(--sales-card);
        box-shadow: var(--sales-shadow);
    }

    .sales-chart-card,
    .sales-collection-card {
        min-height: 290px;
        padding: 20px 22px;
    }

    .sales-chart-title,
    .sales-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--sales-navy);
        font-size: 13px;
        font-weight: 900;
    }

    .sales-chart-title svg,
    .sales-section-title svg {
        width: 16px;
        height: 16px;
        color: #55709d;
    }

    .sales-chart-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 8px;
    }

    .sales-total-30d {
        margin-top: 12px;
    }

    .sales-total-30d strong {
        display: block;
        color: var(--sales-navy);
        font-size: 25px;
        line-height: 1;
        font-weight: 900;
    }

    .sales-total-30d span {
        display: block;
        margin-top: 5px;
        color: var(--sales-muted);
        font-size: 11px;
    }

    .sales-range {
        height: 38px;
        padding: 0 12px;
        border: 1px solid #d4dfed;
        border-radius: 7px;
        background: #fff;
        color: var(--sales-text);
        font: inherit;
        font-size: 11px;
        font-weight: 800;
        outline: none;
    }

    .sales-chart-wrap {
        margin-top: 10px;
    }

    .sales-chart-svg {
        width: 100%;
        height: 150px;
        display: block;
        overflow: visible;
    }

    .sales-chart-labels {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 4px;
        margin-top: 2px;
        color: #6f82a2;
        font-size: 9.5px;
        text-align: center;
    }

    .sales-collection-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 24px;
    }

    .sales-collection-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sales-icon-circle {
        width: 48px;
        height: 48px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff1e7;
        color: var(--sales-orange);
        flex: 0 0 48px;
    }

    .sales-icon-circle svg { width: 23px; height: 23px; }

    .sales-collection-brand h2 {
        margin: 0;
        color: var(--sales-navy);
        font-size: 16px;
        font-weight: 900;
    }

    .sales-active-pill {
        min-height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 11px;
        border-radius: 999px;
        background: #e8f9ed;
        color: var(--sales-green);
        font-size: 10.5px;
        font-weight: 900;
    }

    .sales-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px 28px;
    }

    .sales-info-block span {
        display: block;
        color: var(--sales-muted);
        font-size: 10px;
        margin-bottom: 6px;
    }

    .sales-info-block strong {
        display: block;
        color: #24477d;
        font-size: 13px;
        font-weight: 700;
    }

    .sales-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .sales-kpi {
        min-height: 92px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 17px 18px;
    }

    .sales-kpi-icon {
        width: 46px;
        height: 46px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        flex: 0 0 46px;
        background: #edf4ff;
        color: var(--sales-blue);
    }

    .sales-kpi.green .sales-kpi-icon { background: #e8f9ed; color: var(--sales-green); }
    .sales-kpi.orange .sales-kpi-icon { background: #fff2e8; color: var(--sales-orange); }
    .sales-kpi-icon svg { width: 22px; height: 22px; }

    .sales-kpi span {
        display: block;
        color: var(--sales-muted);
        font-size: 10px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .sales-kpi strong {
        display: block;
        color: var(--sales-navy);
        font-size: 19px;
        line-height: 1.1;
        font-weight: 900;
    }

    .sales-kpi.green strong { color: var(--sales-green); }

    .sales-mini-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        gap: 0;
        margin-bottom: 18px;
        overflow: hidden;
    }

    .sales-mini {
        min-height: 104px;
        padding: 14px 12px;
        border-right: 1px solid #e7edf5;
        background: #fff;
    }

    .sales-mini:last-child { border-right: 0; }

    .sales-mini-top {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 30px;
    }

    .sales-mini-icon {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #f0f4fa;
        color: #4d6690;
        flex: 0 0 32px;
    }

    .sales-mini.blue .sales-mini-icon { background: #edf4ff; color: var(--sales-blue); }
    .sales-mini.green .sales-mini-icon { background: #e9f9ee; color: var(--sales-green); }
    .sales-mini.orange .sales-mini-icon { background: #fff2e8; color: var(--sales-orange); }
    .sales-mini.red .sales-mini-icon { background: #fff0f0; color: var(--sales-red); }
    .sales-mini.purple .sales-mini-icon { background: #f1edff; color: var(--sales-purple); }
    .sales-mini-icon svg { width: 17px; height: 17px; }

    .sales-mini span {
        color: #4c638a;
        font-size: 9px;
        line-height: 1.25;
        font-weight: 700;
    }

    .sales-mini strong {
        display: block;
        margin-top: 11px;
        color: var(--sales-navy);
        font-size: 13px;
        font-weight: 900;
    }

    .sales-mini.green strong { color: var(--sales-green); }
    .sales-mini.red strong { color: var(--sales-red); }

    .sales-filter-card {
        padding: 15px;
        margin-bottom: 18px;
    }

    .sales-filter-form {
        display: grid;
        grid-template-columns: minmax(260px, 1.4fr) 180px 180px 160px 160px auto auto;
        gap: 10px;
        align-items: end;
    }

    .sales-field label {
        display: block;
        margin: 0 0 5px;
        color: #5f7398;
        font-size: 9.5px;
        font-weight: 800;
    }

    .sales-input-wrap {
        position: relative;
    }

    .sales-input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: #5f7398;
        pointer-events: none;
    }

    .sales-input,
    .sales-select {
        width: 100%;
        height: 42px;
        border: 1px solid #d4dfed;
        border-radius: 7px;
        background: #fff;
        color: var(--sales-text);
        font: inherit;
        font-size: 10.5px;
        outline: none;
    }

    .sales-input { padding: 0 12px; }
    .sales-input.with-icon { padding-left: 38px; }
    .sales-select { padding: 0 11px; }

    .sales-input:focus,
    .sales-select:focus {
        border-color: var(--sales-blue);
        box-shadow: 0 0 0 3px rgba(13, 94, 232, .08);
    }

    .sales-filter-btn {
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 16px;
        border: 0;
        border-radius: 7px;
        background: linear-gradient(90deg, #0d5ee8, #166df1);
        color: #fff;
        font: inherit;
        font-size: 10.5px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 7px 16px rgba(13, 94, 232, .18);
    }

    .sales-filter-btn svg { width: 15px; height: 15px; }

    .sales-reset {
        height: 42px;
        display: inline-flex;
        align-items: center;
        color: var(--sales-blue);
        font-size: 10.5px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }

    .sales-table-card {
        overflow: hidden;
    }

    .sales-table-wrap {
        width: 100%;
        overflow: hidden;
    }

    .sales-table {
        width: 100%;
        min-width: 0;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .sales-table th {
        padding: 13px 10px;
        border-bottom: 1px solid #dfe7f1;
        background: #fbfcfe;
        color: #24406f;
        font-size: 9.5px;
        font-weight: 900;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sales-table td {
        padding: 13px 10px;
        border-bottom: 1px solid #edf2f7;
        color: #405980;
        font-size: 10.5px;
        vertical-align: middle;
    }

    .sales-table tbody tr:hover { background: #fbfdff; }
    .sales-table tbody tr:last-child td { border-bottom: 0; }

    .sales-order-link,
    .sales-detail-link {
        color: var(--sales-blue);
        font-weight: 900;
        text-decoration: none;
    }

    .sales-client-name {
        color: var(--sales-navy);
        font-weight: 800;
    }

    .sales-money {
        color: var(--sales-navy);
        font-weight: 800;
        white-space: nowrap;
    }

    .sales-money.green { color: var(--sales-green); }

    .sales-method {
        width: 100%;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #294b7e;
        font-weight: 700;
        line-height: 1.25;
        white-space: normal;
    }

    .sales-method svg {
        width: 15px;
        height: 15px;
        flex: 0 0 15px;
        color: var(--sales-orange);
    }

    .sales-method-label {
        min-width: 0;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sales-status {
        max-width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 25px;
        padding: 0 8px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
        white-space: nowrap;
    }

    .sales-status.green { background: #e7f8ec; color: var(--sales-green); }
    .sales-status.blue { background: #eaf2ff; color: var(--sales-blue); }
    .sales-status.orange { background: #fff0e5; color: var(--sales-orange); }
    .sales-status.red { background: #ffeaea; color: var(--sales-red); }
    .sales-status.purple { background: #efeaff; color: var(--sales-purple); }

    .sales-empty {
        min-height: 250px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px;
        text-align: center;
    }

    .sales-empty-icon {
        width: 78px;
        height: 78px;
        display: grid;
        place-items: center;
        margin-bottom: 14px;
        border-radius: 50%;
        background: #edf4ff;
        color: #55709d;
    }

    .sales-empty-icon svg { width: 42px; height: 42px; }

    .sales-empty h3 {
        margin: 0;
        color: var(--sales-navy);
        font-size: 18px;
        font-weight: 900;
    }

    .sales-empty p {
        margin: 7px 0 0;
        color: var(--sales-muted);
        font-size: 11px;
    }

    .sales-table-footer {
        min-height: 58px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 10px 16px;
        border-top: 1px solid #e7edf5;
        color: #5e7398;
        font-size: 10px;
    }

    .sales-pagination-wrap nav {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sales-pagination-wrap svg { width: 15px; }

    .sales-pagination-wrap span,
    .sales-pagination-wrap a {
        display: inline-flex;
        min-width: 34px;
        min-height: 34px;
        align-items: center;
        justify-content: center;
        padding: 0 9px;
        border: 1px solid #d6e0ec;
        border-radius: 6px;
        background: #fff;
        color: #36527e;
        text-decoration: none;
        font-size: 10px;
        font-weight: 800;
    }

    .sales-pagination-wrap span[aria-current="page"] span,
    .sales-pagination-wrap span[aria-current="page"] {
        border-color: var(--sales-blue);
        color: var(--sales-blue);
    }

    @media (max-width: 1350px) {
        .sales-filter-form {
            grid-template-columns: minmax(240px, 1.4fr) repeat(2, 160px) repeat(2, 145px) auto;
        }

        .sales-reset { grid-column: auto; }
    }

    @media (max-width: 1180px) {
        .sales-top-grid { grid-template-columns: 1fr; }
        .sales-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sales-mini-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .sales-mini:nth-child(4n) { border-right: 0; }
        .sales-mini:nth-child(-n+4) { border-bottom: 1px solid #e7edf5; }
        .sales-filter-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
        .sales-head { flex-direction: column; }
        .sales-head-actions { width: 100%; justify-content: flex-start; }
        .sales-kpis { grid-template-columns: 1fr; }
        .sales-mini-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sales-mini:nth-child(2n) { border-right: 0; }
        .sales-mini { border-bottom: 1px solid #e7edf5; }
        .sales-filter-form { grid-template-columns: 1fr; }
        .sales-info-grid { grid-template-columns: 1fr; }
        .sales-table-footer { flex-direction: column; align-items: flex-start; }
    }
</style>
@endsection

@section('content')
<div class="sales-page">
    <nav class="sales-breadcrumbs" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.payments') }}">Paiements</a>
        <i data-lucide="chevron-right"></i>
        <span>Mes ventes</span>
    </nav>

    <header class="sales-head">
        <div>
            <h1>Paiements / Mes ventes</h1>
            <p>Suivez vos ventes, paiements clients et montants disponibles.</p>
        </div>

        <div class="sales-head-actions">
            <a class="sales-btn" href="{{ route('vendor.payouts.export') }}">
                <i data-lucide="download"></i>
                Exporter CSV
            </a>
            <a class="sales-btn" href="{{ route('vendor.payouts.index') }}">
                <i data-lucide="clock-3"></i>
                Historique des reversements
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="sales-alert">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="sales-alert info">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="sales-alert error">{{ session('error') }}</div>
    @endif

    <section class="sales-top-grid">
        <article class="sales-card sales-chart-card">
            <div class="sales-chart-head">
                <div>
                    <div class="sales-chart-title">
                        Évolution des ventes — 30 derniers jours
                        <i data-lucide="circle-help"></i>
                    </div>
                    <div class="sales-total-30d">
                        <strong>{{ $money($sales30Days) }}</strong>
                        <span>Total des ventes sur 30 jours</span>
                    </div>
                </div>

                <select class="sales-range" aria-label="Période du graphique" disabled>
                    <option>30 derniers jours</option>
                </select>
            </div>

            <div class="sales-chart-wrap">
                <svg class="sales-chart-svg" viewBox="0 0 760 170" preserveAspectRatio="none" aria-label="Évolution des ventes">
                    <defs>
                        <linearGradient id="salesArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#0d5ee8" stop-opacity="0.16" />
                            <stop offset="100%" stop-color="#0d5ee8" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <line x1="0" y1="20" x2="760" y2="20" stroke="#e7edf5" stroke-width="1" />
                    <line x1="0" y1="62" x2="760" y2="62" stroke="#e7edf5" stroke-width="1" />
                    <line x1="0" y1="104" x2="760" y2="104" stroke="#e7edf5" stroke-width="1" />
                    <line x1="0" y1="146" x2="760" y2="146" stroke="#e7edf5" stroke-width="1" />

                    @if($chartValues->count() > 1)
                        <polygon points="10,150 {{ $chartPoints }} 750,150" fill="url(#salesArea)" />
                        <polyline points="{{ $chartPoints }}" fill="none" stroke="#0d5ee8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        @foreach($chartValues as $value)
                            @php
                                $index = $loop->index;
                                $x = $chartCount <= 1 ? $chartWidth / 2 : $chartPadX + ($index * (($chartWidth - ($chartPadX * 2)) / ($chartCount - 1)));
                                $y = $chartHeight - $chartPadY - (((float) $value / $chartMax) * ($chartHeight - ($chartPadY * 2)));
                            @endphp
                            <circle cx="{{ round($x, 2) }}" cy="{{ round($y, 2) }}" r="3.4" fill="#0d5ee8" stroke="#fff" stroke-width="2" />
                        @endforeach
                    @else
                        <line x1="10" y1="146" x2="750" y2="146" stroke="#0d5ee8" stroke-width="3" />
                    @endif
                </svg>

                <div class="sales-chart-labels">
                    @forelse($chartLabels as $row)
                        <span>{{ $row['label'] }}</span>
                    @empty
                        <span>—</span><span>—</span><span>—</span><span>—</span><span>—</span>
                    @endforelse
                </div>
            </div>
        </article>

        <article class="sales-card sales-collection-card">
            <div class="sales-collection-head">
                <div class="sales-collection-brand">
                    <span class="sales-icon-circle"><i data-lucide="{{ $dominantMethodIcon }}"></i></span>
                    <h2>Encaissement vendeur</h2>
                </div>
                <span class="sales-active-pill">Actif</span>
            </div>

            <div class="sales-info-grid">
                <div class="sales-info-block">
                    <span>Mode de paiement client dominant</span>
                    <strong>{{ $dominantMethodLabel }}</strong>
                </div>
                <div class="sales-info-block">
                    <span>Délai moyen de confirmation</span>
                    <strong>Selon le mode de paiement</strong>
                </div>
                <div class="sales-info-block">
                    <span>Dernier paiement reçu</span>
                    <strong>{{ $lastPaymentLabel }}</strong>
                </div>
                <div class="sales-info-block">
                    <span>Boutique</span>
                    <strong>{{ $shopName }}</strong>
                </div>
            </div>
        </article>
    </section>

    <section class="sales-kpis">
        <article class="sales-card sales-kpi">
            <span class="sales-kpi-icon"><i data-lucide="chart-no-axes-combined"></i></span>
            <div>
                <span>Chiffre d'affaires</span>
                <strong>{{ $money($summary['total_net'] ?? 0) }}</strong>
            </div>
        </article>

        <article class="sales-card sales-kpi green">
            <span class="sales-kpi-icon"><i data-lucide="circle-check-big"></i></span>
            <div>
                <span>Paiements confirmés</span>
                <strong>{{ $money($confirmedAmount) }}</strong>
            </div>
        </article>

        <article class="sales-card sales-kpi orange">
            <span class="sales-kpi-icon"><i data-lucide="clock-3"></i></span>
            <div>
                <span>En attente de confirmation</span>
                <strong>{{ $money($waitingAmount) }}</strong>
            </div>
        </article>

        <article class="sales-card sales-kpi green">
            <span class="sales-kpi-icon"><i data-lucide="wallet-cards"></i></span>
            <div>
                <span>Net vendeur estimé</span>
                <strong>{{ $money($summary['total_net'] ?? 0) }}</strong>
            </div>
        </article>
    </section>

    <section class="sales-card sales-mini-grid">
        <article class="sales-mini orange">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="clock-3"></i></span><span>À confirmer</span></div>
            <strong>{{ $money($waitingAmount) }}</strong>
        </article>
        <article class="sales-mini green">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="circle-check-big"></i></span><span>Confirmé</span></div>
            <strong>{{ $money($confirmedAmount) }}</strong>
        </article>
        <article class="sales-mini orange">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="truck"></i></span><span>En attente de livraison</span></div>
            <strong>{{ $money($blockedAmount) }}</strong>
        </article>
        <article class="sales-mini purple">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="refresh-ccw"></i></span><span>En traitement</span></div>
            <strong>{{ $money($summary['processing'] ?? 0) }}</strong>
        </article>
        <article class="sales-mini blue">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="credit-card"></i></span><span>Frais de traitement</span></div>
            <strong>{{ $money($summary['total_processing_fees'] ?? 0) }}</strong>
        </article>
        <article class="sales-mini green">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="wallet-minimal"></i></span><span>Reversement disponible</span></div>
            <strong>{{ $money($availableAmount) }}</strong>
        </article>
        <article class="sales-mini purple">
            <div class="sales-mini-top"><span class="sales-mini-icon"><i data-lucide="arrow-left-right"></i></span><span>Transactions</span></div>
            <strong>{{ number_format((int) ($summary['sales_count'] ?? 0), 0, ',', ' ') }}</strong>
        </article>
    </section>

    <section class="sales-card sales-filter-card">
        <form class="sales-filter-form" method="GET" action="{{ route('vendor.payments') }}">
            <div class="sales-field">
                <label for="salesSearch">Recherche</label>
                <div class="sales-input-wrap">
                    <i class="sales-input-icon" data-lucide="search"></i>
                    <input
                        id="salesSearch"
                        class="sales-input with-icon"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Rechercher par n° commande, client ou référence..."
                    >
                </div>
            </div>

            <div class="sales-field">
                <label for="salesStatus">Statut</label>
                <select id="salesStatus" class="sales-select" name="status">
                    <option value="">Tous les statuts</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sales-field">
                <label for="salesMethod">Méthode</label>
                <select id="salesMethod" class="sales-select" name="method">
                    <option value="">Toutes les méthodes</option>
                    @foreach($methodLabels as $key => $label)
                        <option value="{{ $key }}" @selected(($method ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sales-field">
                <label for="salesFrom">Du</label>
                <input id="salesFrom" class="sales-input" type="date" name="from" value="{{ $dateFrom }}">
            </div>

            <div class="sales-field">
                <label for="salesTo">Au</label>
                <input id="salesTo" class="sales-input" type="date" name="to" value="{{ $dateTo }}">
            </div>

            <button class="sales-filter-btn" type="submit">
                <i data-lucide="filter"></i>
                Filtrer
            </button>

            <a class="sales-reset" href="{{ route('vendor.payments') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="sales-card sales-table-card">
        <div class="sales-table-wrap">
            @if($payouts->count())
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th style="width:9%">Date</th>
                            <th style="width:13%">Commande</th>
                            <th style="width:10%">Client</th>
                            <th style="width:14%">Vente</th>
                            <th style="width:11%">Net vendeur</th>
                            <th style="width:16%">Méthode</th>
                            <th style="width:12%">Statut</th>
                            <th style="width:9%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payouts as $payout)
                            @php
                                $clientPaymentMethod = $payout->order?->payment_method;
                                $clientPaymentLabel = $methodLabels[$clientPaymentMethod] ?? ucfirst(str_replace('_', ' ', (string) ($clientPaymentMethod ?: 'Non renseigné')));
                                $clientPaymentIcon = $methodIcons[$clientPaymentMethod] ?? 'wallet-cards';
                            @endphp
                            <tr>
                                <td>{{ optional($payout->created_at)->format('d/m/Y') }}</td>
                                <td>
                                    <a class="sales-order-link" href="{{ route('vendor.orders.show', $payout->order_id) }}">
                                        {{ $payout->order?->order_number ?? ('OVN-' . $payout->order_id) }}
                                    </a>
                                </td>
                                <td><span class="sales-client-name">{{ $payout->order?->client?->name ?? 'Client OVANIE' }}</span></td>
                                <td><span class="sales-money">{{ $money($payout->total_amount) }}</span></td>
                                <td><span class="sales-money green">{{ $money($payout->payout_amount) }}</span></td>
                                <td>
                                    <span class="sales-method" title="{{ $clientPaymentLabel }}">
                                        <i data-lucide="{{ $clientPaymentIcon }}"></i>
                                        <span class="sales-method-label">{{ $clientPaymentLabel }}</span>
                                    </span>
                                </td>
                                <td><span class="sales-status {{ $payout->status_tone }}">{{ $payout->status_label }}</span></td>
                                <td><a class="sales-detail-link" href="{{ route('vendor.orders.show', $payout->order_id) }}">Voir →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="sales-empty">
                    <span class="sales-empty-icon"><i data-lucide="inbox"></i></span>
                    <h3>Aucune transaction trouvée</h3>
                    <p>Aucune vente ne correspond à vos critères pour le moment.</p>
                </div>
            @endif
        </div>

        <footer class="sales-table-footer">
            <span>
                Affichage de {{ $payouts->firstItem() ?? 0 }} à {{ $payouts->lastItem() ?? 0 }} sur {{ $payouts->total() }} transaction(s)
            </span>
            <div class="sales-pagination-wrap">{{ $payouts->links() }}</div>
        </footer>
    </section>
</div>
@endsection
