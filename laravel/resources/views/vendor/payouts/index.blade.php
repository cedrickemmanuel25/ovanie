@extends('layouts.vendor')

@section('title', 'Reversements vendeur')

@section('content')
@php
    $routePrefix = request()->routeIs('daniel.*') ? 'daniel' : 'vendor';
    $money = fn ($amount) => number_format((float) ($amount ?? 0), 0, ',', ' ') . ' FCFA';
    $waitingClient = (float) (($summary['waiting_payment'] ?? 0) + ($summary['waiting_reception'] ?? 0));

    $statusSummary = [
        [
            'label' => 'Attente client',
            'value' => $waitingClient,
            'icon' => 'hourglass',
            'tone' => 'neutral',
        ],
        [
            'label' => 'Programmé',
            'value' => $summary['scheduled'] ?? 0,
            'icon' => 'calendar-days',
            'tone' => 'neutral',
        ],
        [
            'label' => 'En traitement',
            'value' => $summary['processing'] ?? 0,
            'icon' => 'refresh-cw',
            'tone' => 'neutral',
        ],
        [
            'label' => 'Déjà payé',
            'value' => $summary['paid'] ?? 0,
            'icon' => 'circle-check-big',
            'tone' => 'green',
        ],
        [
            'label' => 'En attente de livraison / Échec',
            'value' => $summary['blocked'] ?? 0,
            'icon' => 'triangle-alert',
            'tone' => 'red',
        ],
        [
            'label' => 'Commission OVANIE',
            'value' => $summary['total_commission'] ?? 0,
            'icon' => 'percent',
            'tone' => 'neutral',
        ],
        [
            'label' => 'Frais de traitement',
            'value' => $summary['total_processing_fees'] ?? 0,
            'icon' => 'receipt-text',
            'tone' => 'neutral',
        ],
        [
            'label' => 'Dossiers de vente',
            'value' => (int) ($summary['sales_count'] ?? 0),
            'icon' => 'folder',
            'tone' => 'neutral',
            'count' => true,
        ],
    ];

    $chartValues = [];
    $chartStart = now()->subDays(29)->startOfDay();
    for ($i = 0; $i < 30; $i++) {
        $chartValues[$chartStart->copy()->addDays($i)->format('Y-m-d')] = 0;
    }

    foreach ($payouts->getCollection() as $payout) {
        $created = $payout->created_at;
        if (! $created || $created->lt($chartStart)) {
            continue;
        }

        $key = $created->format('Y-m-d');
        if (array_key_exists($key, $chartValues)) {
            $chartValues[$key] += (float) ($payout->payout_amount ?? 0);
        }
    }

    $chartLabels = collect(array_keys($chartValues))->map(fn ($date) => \Carbon\Carbon::parse($date)->translatedFormat('d M'))->values();
    $chartData = array_values($chartValues);
    $chartTotal = array_sum($chartData);
@endphp

<style>
    .vp-page {
        --vp-navy: #08275f;
        --vp-blue: #0d62f2;
        --vp-orange: #ff650f;
        --vp-green: #0aae57;
        --vp-red: #ff2a2a;
        --vp-text: #163566;
        --vp-muted: #60769d;
        --vp-border: #dfe7f1;
        --vp-soft: #f7f9fc;
        --vp-bg: #f8fafc;
        min-height: calc(100vh - 64px);
        padding: 22px 24px 36px;
        background: linear-gradient(180deg, #fbfcfe 0%, #f8fafc 100%);
        color: var(--vp-text);
    }

    .vp-shell {
        width: min(1260px, 100%);
        margin: 0 auto;
    }

    .vp-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 22px;
        color: #203f74;
        font-size: 12px;
        font-weight: 700;
    }

    .vp-breadcrumb a {
        color: var(--vp-blue);
        text-decoration: none;
    }

    .vp-breadcrumb svg {
        width: 14px;
        height: 14px;
        color: #7890b4;
    }

    .vp-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 22px;
        margin-bottom: 20px;
    }

    .vp-head-copy h1 {
        margin: 0;
        color: var(--vp-navy);
        font-size: clamp(34px, 4vw, 54px);
        line-height: 1;
        letter-spacing: -1.9px;
        font-weight: 900;
    }

    .vp-head-copy p {
        margin: 17px 0 0;
        color: #506b98;
        font-size: 15px;
    }

    .vp-head-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .vp-btn {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 0 18px;
        border: 1px solid #9bb0cf;
        border-radius: 7px;
        background: #fff;
        color: var(--vp-navy);
        font: inherit;
        font-size: 12px;
        font-weight: 850;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
    }

    .vp-btn:hover {
        border-color: var(--vp-blue);
        color: var(--vp-blue);
        transform: translateY(-1px);
    }

    .vp-btn svg {
        width: 17px;
        height: 17px;
    }

    .vp-btn.primary {
        border-color: var(--vp-blue);
        background: linear-gradient(90deg, #0d62f2, #116bea);
        color: #fff;
        box-shadow: 0 10px 24px rgba(13, 98, 242, .20);
    }

    .vp-btn.primary:hover { color: #fff; }

    .vp-alert {
        margin-bottom: 16px;
        padding: 13px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 800;
    }

    .vp-alert.success {
        border: 1px solid #b7eac8;
        background: #effcf4;
        color: #087b39;
    }

    .vp-alert.error {
        border: 1px solid #ffc4c4;
        background: #fff3f3;
        color: #ba2424;
    }

    .vp-top-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(350px, .95fr);
        gap: 24px;
        margin-bottom: 24px;
    }

    .vp-card {
        border: 1px solid var(--vp-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 10px 26px rgba(18, 42, 82, .055);
    }

    .vp-chart-card {
        min-height: 326px;
        padding: 22px 24px 18px;
    }

    .vp-card-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .vp-card-title {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        color: var(--vp-navy);
        font-size: 14px;
        font-weight: 900;
    }

    .vp-card-title small {
        color: #56709b;
        font-size: 12px;
        font-weight: 600;
    }

    .vp-info-icon {
        width: 17px;
        height: 17px;
        color: #5b79ad;
    }

    .vp-period {
        height: 39px;
        padding: 0 12px;
        border: 1px solid #d5dfeb;
        border-radius: 7px;
        background: #fff;
        color: #274677;
        font: inherit;
        font-size: 11px;
        outline: none;
    }

    .vp-chart-summary {
        margin-top: 18px;
    }

    .vp-chart-summary strong {
        display: block;
        color: var(--vp-navy);
        font-size: 26px;
        line-height: 1;
        font-weight: 900;
    }

    .vp-chart-summary span {
        display: block;
        margin-top: 8px;
        color: #4d6692;
        font-size: 11.5px;
    }

    .vp-chart {
        position: relative;
        height: 170px;
        margin-top: 12px;
    }

    .vp-chart svg {
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .vp-chart-grid line {
        stroke: #dfe6ef;
        stroke-width: 1;
    }

    .vp-chart-axis-label {
        fill: #6a7ea0;
        font-size: 10px;
    }

    .vp-chart-line {
        fill: none;
        stroke: var(--vp-blue);
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .vp-chart-dot {
        fill: #fff;
        stroke: var(--vp-blue);
        stroke-width: 2;
    }

    .vp-chart-legend {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 6px;
        color: #4e6896;
        font-size: 10.5px;
    }

    .vp-chart-legend::before {
        content: '';
        width: 18px;
        height: 3px;
        border-radius: 99px;
        background: var(--vp-blue);
    }

    .vp-method-card {
        min-height: 326px;
        padding: 22px 24px;
    }

    .vp-method-header {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .vp-method-icon {
        width: 54px;
        height: 54px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff1e8;
        color: var(--vp-orange);
    }

    .vp-method-icon svg {
        width: 27px;
        height: 27px;
    }

    .vp-method-header h2 {
        margin: 0;
        color: var(--vp-navy);
        font-size: 16px;
        font-weight: 900;
    }

    .vp-method-status {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 0 11px;
        border-radius: 999px;
        background: #dcf9e5;
        color: #0f9d4d;
        font-size: 11px;
        font-weight: 850;
    }

    .vp-method-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px 36px;
        margin-top: 30px;
        padding-left: 68px;
    }

    .vp-method-item span {
        display: block;
        color: #34527f;
        font-size: 11.5px;
    }

    .vp-method-item strong {
        display: block;
        margin-top: 8px;
        color: #4a6390;
        font-size: 13px;
        line-height: 1.45;
        font-weight: 500;
    }

    .vp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
        margin-bottom: 24px;
    }

    .vp-kpi {
        min-height: 218px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 26px 24px;
    }

    .vp-kpi.green {
        border-color: #64d889;
        background: linear-gradient(140deg, #f3fff7 0%, #ffffff 82%);
    }

    .vp-kpi-label {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--vp-navy);
        font-size: 14px;
        font-weight: 900;
    }

    .vp-kpi.green .vp-kpi-label,
    .vp-kpi.green .vp-kpi-value { color: var(--vp-green); }

    .vp-kpi-value {
        margin-top: 25px;
        color: var(--vp-navy);
        font-size: 34px;
        line-height: 1;
        font-weight: 900;
    }

    .vp-kpi-note {
        margin-top: 22px;
        color: #4f6895;
        font-size: 11.5px;
        line-height: 1.55;
    }

    .vp-kpi-icon {
        width: 66px;
        height: 66px;
        display: grid;
        place-items: center;
        flex: 0 0 66px;
        border-radius: 50%;
        background: #eef4ff;
        color: var(--vp-blue);
    }

    .vp-kpi-icon.orange {
        background: #fff1e8;
        color: var(--vp-orange);
    }

    .vp-kpi-icon.green {
        background: #e4f9ea;
        color: var(--vp-green);
    }

    .vp-kpi-icon svg {
        width: 33px;
        height: 33px;
    }

    .vp-status-strip {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        margin-bottom: 24px;
        overflow: hidden;
    }

    .vp-status-item {
        min-height: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 18px 12px;
        text-align: center;
        border-right: 1px solid #e4eaf2;
    }

    .vp-status-item:last-child { border-right: 0; }

    .vp-status-icon {
        width: 58px;
        height: 58px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #f1f4f8;
        color: #62789f;
    }

    .vp-status-icon svg {
        width: 29px;
        height: 29px;
    }

    .vp-status-item.green .vp-status-icon {
        background: #e2f8e9;
        color: var(--vp-green);
    }

    .vp-status-item.red .vp-status-icon {
        background: #ffeded;
        color: var(--vp-red);
    }

    .vp-status-item h3 {
        min-height: 38px;
        display: flex;
        align-items: center;
        margin: 13px 0 0;
        color: #19396c;
        font-size: 10.5px;
        line-height: 1.4;
        font-weight: 800;
    }

    .vp-status-item.red h3 { color: var(--vp-red); }

    .vp-status-value {
        margin-top: 11px;
        color: var(--vp-navy);
        font-size: 13px;
        font-weight: 900;
    }

    .vp-status-item.green .vp-status-value { color: var(--vp-green); }
    .vp-status-item.red .vp-status-value { color: var(--vp-red); }

    .vp-filters-card {
        padding: 22px 20px;
        margin-bottom: 24px;
    }

    .vp-filters {
        display: grid;
        grid-template-columns: minmax(260px, 1.7fr) minmax(160px, .8fr) minmax(150px, .72fr) minmax(150px, .72fr) auto auto;
        align-items: end;
        gap: 14px;
    }

    .vp-field label {
        display: block;
        margin-bottom: 6px;
        color: #4d6690;
        font-size: 10px;
        font-weight: 700;
    }

    .vp-control-wrap {
        position: relative;
    }

    .vp-control-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        width: 17px;
        height: 17px;
        color: #5773a0;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .vp-input,
    .vp-select {
        width: 100%;
        height: 45px;
        border: 1px solid #d3deeb;
        border-radius: 7px;
        background: #fff;
        color: #22416f;
        padding: 0 12px;
        font: inherit;
        font-size: 11px;
        outline: none;
    }

    .vp-input.with-icon { padding-left: 40px; }

    .vp-input:focus,
    .vp-select:focus {
        border-color: var(--vp-blue);
        box-shadow: 0 0 0 3px rgba(13, 98, 242, .08);
    }

    .vp-filter-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-top: 18px;
    }

    .vp-more-btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 0 14px;
        border: 1px solid #d3deeb;
        border-radius: 7px;
        background: #fff;
        color: #264574;
        font: inherit;
        font-size: 11px;
        font-weight: 750;
        cursor: pointer;
    }

    .vp-reset {
        color: var(--vp-blue);
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
    }

    .vp-table-card {
        overflow: hidden;
    }

    .vp-table-wrap {
        width: 100%;
        overflow: hidden;
    }

    .vp-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .vp-table th:nth-child(1), .vp-table td:nth-child(1) { width: 9%; }
    .vp-table th:nth-child(2), .vp-table td:nth-child(2) { width: 19%; }
    .vp-table th:nth-child(3), .vp-table td:nth-child(3) { width: 10%; }
    .vp-table th:nth-child(4), .vp-table td:nth-child(4) { width: 11%; }
    .vp-table th:nth-child(5), .vp-table td:nth-child(5) { width: 13%; }
    .vp-table th:nth-child(6), .vp-table td:nth-child(6) { width: 17%; }
    .vp-table th:nth-child(7), .vp-table td:nth-child(7) { width: 9%; }
    .vp-table th:nth-child(8), .vp-table td:nth-child(8) { width: 12%; }

    .vp-table th {
        height: 58px;
        padding: 0 18px;
        border-bottom: 1px solid #e3e9f1;
        background: #fff;
        color: #183766;
        font-size: 10px;
        font-weight: 900;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .vp-table td {
        padding: 16px 12px;
        border-bottom: 1px solid #edf1f6;
        color: #34527e;
        font-size: 11px;
        vertical-align: middle;
        min-width: 0;
    }

    .vp-table th {
        padding-left: 12px;
        padding-right: 12px;
    }

    .vp-table td:nth-child(3),
    .vp-table td:nth-child(4),
    .vp-table td:nth-child(5),
    .vp-table td:nth-child(7),
    .vp-table td:nth-child(8) {
        white-space: nowrap;
    }

    .vp-method-inline {
        display: flex;
        align-items: center;
        gap: 7px;
        min-width: 0;
        white-space: nowrap;
    }

    .vp-method-inline .vp-method-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 700;
        color: #34527e;
    }

    .vp-method-inline .vp-method-phone {
        flex: 0 0 auto;
        color: #8190a8;
        font-size: 9.5px;
        white-space: nowrap;
    }

    .vp-sort {
        width: 14px;
        height: 14px;
        margin-left: 4px;
        vertical-align: middle;
        color: #7890b4;
    }

    .vp-money-positive { color: var(--vp-green); font-weight: 900; }
    .vp-money-negative { color: var(--vp-orange); font-weight: 850; }

    .vp-note {
        display: block;
        margin-top: 5px;
        color: #8190a8;
        font-size: 10px;
    }

    .vp-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 27px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 850;
    }

    .vp-badge.green { background: #e6f9ed; color: #0a9a4b; }
    .vp-badge.blue { background: #e8f1ff; color: #1767d8; }
    .vp-badge.purple { background: #f0eaff; color: #7650d8; }
    .vp-badge.orange { background: #fff0e6; color: #e86616; }
    .vp-badge.red { background: #ffeded; color: #e03838; }

    .vp-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    .vp-actions form {
        margin: 0;
        flex: 0 0 auto;
    }

    .vp-action-link,
    .vp-action-btn {
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 10px;
        border: 1px solid #d4deeb;
        border-radius: 6px;
        background: #fff;
        color: #264b7e;
        font: inherit;
        font-size: 10px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .vp-action-btn {
        border-color: #9eb5d5;
        color: var(--vp-navy);
    }

    .vp-empty {
        height: 310px;
        text-align: center;
    }

    .vp-empty-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 310px;
    }

    .vp-empty-icon {
        width: 72px;
        height: 72px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #eef4ff;
        color: #526e9d;
    }

    .vp-empty-icon svg {
        width: 39px;
        height: 39px;
        stroke-width: 1.5;
    }

    .vp-empty h3 {
        margin: 15px 0 0;
        color: var(--vp-navy);
        font-size: 18px;
        font-weight: 900;
    }

    .vp-empty p {
        margin: 8px 0 0;
        color: #64789d;
        font-size: 11px;
    }

    .vp-pagination {
        min-height: 70px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 18px;
        border-top: 1px solid #e5ebf2;
        background: #fff;
    }

    .vp-pagination-summary {
        color: #60769d;
        font-size: 10.5px;
    }

    .vp-pagination-nav {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .vp-page-link {
        min-width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #d6e0ec;
        border-radius: 7px;
        background: #fff;
        color: #4f6790;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
    }

    .vp-page-link.active {
        border-color: var(--vp-blue);
        color: var(--vp-blue);
    }

    .vp-page-link.disabled {
        opacity: .45;
        pointer-events: none;
    }

    .vp-per-page {
        height: 38px;
        border: 1px solid #d6e0ec;
        border-radius: 7px;
        background: #fff;
        color: #4f6790;
        padding: 0 10px;
        font: inherit;
        font-size: 10px;
    }

    @media (max-width: 1180px) {
        .vp-top-grid { grid-template-columns: 1fr; }
        .vp-method-grid { padding-left: 0; }
        .vp-status-strip { grid-template-columns: repeat(4, 1fr); }
        .vp-status-item:nth-child(4) { border-right: 0; }
        .vp-status-item:nth-child(-n+4) { border-bottom: 1px solid #e4eaf2; }
        .vp-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 800px) {
        .vp-page { padding: 18px 14px 28px; }
        .vp-head { flex-direction: column; }
        .vp-head-copy h1 { font-size: 36px; }
        .vp-kpi-grid { grid-template-columns: 1fr; }
        .vp-status-strip { grid-template-columns: repeat(2, 1fr); }
        .vp-status-item { border-bottom: 1px solid #e4eaf2; }
        .vp-status-item:nth-child(even) { border-right: 0; }
        .vp-method-grid { grid-template-columns: 1fr; gap: 18px; }
        .vp-filters { grid-template-columns: 1fr; }
        .vp-filter-foot { flex-direction: column; align-items: stretch; }
        .vp-pagination { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="vp-page">
    <div class="vp-shell">
        @if(session('success'))
            <div class="vp-alert success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="vp-alert error">{{ session('error') }}</div>
        @endif

        <div class="vp-breadcrumb">
            <a href="{{ route($routePrefix.'.dashboard') }}">Espace vendeur</a>
            <i data-lucide="chevron-right"></i>
            <a href="{{ route($routePrefix.'.payments') }}">Paiements</a>
            <i data-lucide="chevron-right"></i>
            <span>Reversements vendeur</span>
        </div>

        <header class="vp-head">
            <div class="vp-head-copy">
                <h1>Reversements vendeur</h1>
                <p>Suivez vos ventes, commissions et reversements.</p>
            </div>

            <div class="vp-head-actions">
                <a class="vp-btn" href="{{ route($routePrefix.'.payouts.export', request()->query()) }}">
                    <i data-lucide="download"></i>
                    Exporter CSV
                </a>
                <a class="vp-btn" href="{{ route($routePrefix.'.payment-method') }}">
                    <i data-lucide="credit-card"></i>
                    Méthode de paiement
                </a>
            </div>
        </header>

        <section class="vp-top-grid">
            <article class="vp-card vp-chart-card">
                <div class="vp-card-title-row">
                    <h2 class="vp-card-title">
                        Évolution des reversements
                        <small>— 30 derniers jours</small>
                        <i class="vp-info-icon" data-lucide="info"></i>
                    </h2>
                    <select class="vp-period" aria-label="Période">
                        <option>30 derniers jours</option>
                    </select>
                </div>

                <div class="vp-chart-summary">
                    <strong>{{ $money($chartTotal) }}</strong>
                    <span>Total sur 30 jours</span>
                </div>

                <div class="vp-chart" id="vpPayoutChart" data-values='@json($chartData)' data-labels='@json($chartLabels)'>
                    <svg viewBox="0 0 640 180" role="img" aria-label="Évolution des reversements sur les 30 derniers jours">
                        <g class="vp-chart-grid">
                            <line x1="48" y1="28" x2="626" y2="28"></line>
                            <line x1="48" y1="84" x2="626" y2="84"></line>
                            <line x1="48" y1="140" x2="626" y2="140"></line>
                        </g>
                        <g>
                            <text class="vp-chart-axis-label" x="8" y="32" id="vpChartTop">10K</text>
                            <text class="vp-chart-axis-label" x="14" y="88" id="vpChartMid">5K</text>
                            <text class="vp-chart-axis-label" x="28" y="144">0</text>
                        </g>
                        <polyline class="vp-chart-line" id="vpChartLine" points="48,140 626,140"></polyline>
                        <g id="vpChartDots"></g>
                        <g id="vpChartLabels"></g>
                    </svg>
                </div>

                <div class="vp-chart-legend">Reversements (FCFA)</div>
            </article>

            <aside class="vp-card vp-method-card">
                <div class="vp-method-header">
                    <span class="vp-method-icon"><i data-lucide="wallet-cards"></i></span>
                    <h2>Paiement vendeur</h2>
                    <span class="vp-method-status">{{ $paymentMethod['operator'] !== 'Non défini' ? 'Actif' : 'À configurer' }}</span>
                </div>

                <div class="vp-method-grid">
                    <div class="vp-method-item">
                        <span>Opérateur</span>
                        <strong>{{ $paymentMethod['operator'] }}</strong>
                    </div>
                    <div class="vp-method-item">
                        <span>Numéro</span>
                        <strong>{{ $paymentMethod['number'] }}</strong>
                    </div>
                    <div class="vp-method-item">
                        <span>Titulaire</span>
                        <strong>{{ $paymentMethod['holder'] }}</strong>
                    </div>
                    <div class="vp-method-item">
                        <span>Prochaine échéance</span>
                        <strong>{{ $summary['next_payment_date'] ?: 'Aucune échéance programmée' }}</strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="vp-kpi-grid">
            <article class="vp-card vp-kpi green">
                <div>
                    <div class="vp-kpi-label">
                        Net vendeur
                        <i class="vp-info-icon" data-lucide="info"></i>
                    </div>
                    <div class="vp-kpi-value">{{ $money($summary['total_net'] ?? 0) }}</div>
                    <p class="vp-kpi-note">Total net à vous reverser</p>
                </div>
                <span class="vp-kpi-icon green"><i data-lucide="wallet"></i></span>
            </article>

            <article class="vp-card vp-kpi">
                <div>
                    <div class="vp-kpi-label">
                        Ventes boutique
                        <i class="vp-info-icon" data-lucide="info"></i>
                    </div>
                    <div class="vp-kpi-value">{{ $money($summary['total_sales'] ?? 0) }}</div>
                    <p class="vp-kpi-note">Total des ventes validées</p>
                </div>
                <span class="vp-kpi-icon"><i data-lucide="shopping-bag"></i></span>
            </article>

            <article class="vp-card vp-kpi">
                <div>
                    <div class="vp-kpi-label">
                        Prêt à payer
                        <i class="vp-info-icon" data-lucide="info"></i>
                    </div>
                    <div class="vp-kpi-value">{{ $money($summary['ready'] ?? 0) }}</div>
                    <p class="vp-kpi-note">Montant prêt pour le prochain reversement</p>
                </div>
                <span class="vp-kpi-icon orange"><i data-lucide="alarm-clock"></i></span>
            </article>
        </section>

        <section class="vp-card vp-status-strip">
            @foreach($statusSummary as $item)
                <article class="vp-status-item {{ $item['tone'] }}">
                    <span class="vp-status-icon"><i data-lucide="{{ $item['icon'] }}"></i></span>
                    <h3>{{ $item['label'] }}</h3>
                    <div class="vp-status-value">
                        @if(!empty($item['count']))
                            {{ number_format((int) $item['value'], 0, ',', ' ') }}
                        @else
                            {{ $money($item['value']) }}
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <section class="vp-card vp-filters-card">
            <form method="GET" action="{{ route($routePrefix.'.payouts.index') }}">
                <div class="vp-filters">
                    <div class="vp-field">
                        <label for="vpSearch">Recherche</label>
                        <div class="vp-control-wrap">
                            <i class="vp-control-icon" data-lucide="search"></i>
                            <input id="vpSearch" class="vp-input with-icon" type="search" name="q" value="{{ $search }}" placeholder="Rechercher par n° commande, client ou référence...">
                        </div>
                    </div>

                    <div class="vp-field">
                        <label for="vpStatus">Statut</label>
                        <select id="vpStatus" class="vp-select" name="status">
                            <option value="">Tous les statuts</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="vp-field">
                        <label for="vpFrom">Du</label>
                        <input id="vpFrom" class="vp-input" type="date" name="date_from" value="{{ $dateFrom }}">
                    </div>

                    <div class="vp-field">
                        <label for="vpTo">Au</label>
                        <input id="vpTo" class="vp-input" type="date" name="date_to" value="{{ $dateTo }}">
                    </div>

                    <button class="vp-btn primary" type="submit">
                        <i data-lucide="funnel"></i>
                        Filtrer
                    </button>

                    <a class="vp-reset" href="{{ route($routePrefix.'.payouts.index') }}">Réinitialiser</a>
                </div>

                <div class="vp-filter-foot">
                    <button type="button" class="vp-more-btn">
                        Plus de filtres
                        <i data-lucide="chevron-down" style="width:15px;height:15px"></i>
                    </button>
                </div>
            </form>
        </section>

        <section class="vp-card vp-table-card">
            <div class="vp-table-wrap">
                <table class="vp-table">
                    <thead>
                        <tr>
                            <th>Date <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Commande <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Produits <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Livraison vendeur <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Commission produits <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Total à reverser <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Méthode <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Statut <i class="vp-sort" data-lucide="chevrons-up-down"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payouts as $payout)
                            <tr>
                                <td>{{ optional($payout->created_at)->format('d/m/Y') }}<span class="vp-note">{{ optional($payout->created_at)->format('H:i') }}</span></td>
                                <td>
                                    <strong>{{ $payout->order?->order_number ?: 'OVN-'.$payout->order_id }}</strong>
                                    <span class="vp-note">{{ $payout->payout_reference ?: 'Référence en attente' }}</span>
                                </td>
                                <td>{{ $money($payout->product_amount ?? data_get($payout->meta, 'financial_breakdown.product_amount', $payout->total_amount)) }}</td>
                                <td>{{ $money($payout->seller_delivery_amount ?? data_get($payout->meta, 'financial_breakdown.seller_delivery_amount', 0)) }}</td>
                                <td class="vp-money-negative">-{{ $money($payout->commission_amount) }}</td>
                                <td class="vp-money-positive">{{ $money($payout->payout_amount) }}</td>
                                <td>
                                    <div class="vp-method-inline" title="{{ $payout->payment_method_label }} — {{ $payout->phone ?: $paymentMethod['number'] }}">
                                        <span class="vp-method-label">{{ $payout->payment_method_label }}</span>
                                        <span class="vp-method-phone">{{ $payout->phone ?: $paymentMethod['number'] }}</span>
                                    </div>
                                </td>
                                <td><span class="vp-badge {{ $payout->status_tone }}">{{ $payout->status_label }}</span></td>
                                <td>
                                    <div class="vp-actions">
                                        <a class="vp-action-link" href="{{ route($routePrefix.'.payouts.show', $payout) }}">Détail</a>
                                        @if(! $payout->isPaid())
                                            <form method="POST" action="{{ route($routePrefix.'.payouts.followUp', $payout) }}">
                                                @csrf
                                                <button class="vp-action-btn" type="submit">Suivi</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="vp-empty">
                                    <div class="vp-empty-inner">
                                        <span class="vp-empty-icon"><i data-lucide="inbox"></i></span>
                                        <h3>Aucune transaction trouvée</h3>
                                        <p>Aucune transaction ne correspond à vos critères pour le moment.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="vp-pagination">
                <div class="vp-pagination-summary">
                    Affichage de {{ $payouts->firstItem() ?? 0 }} à {{ $payouts->lastItem() ?? 0 }} sur {{ $payouts->total() }} transaction{{ $payouts->total() > 1 ? 's' : '' }}
                </div>

                <div class="vp-pagination-nav">
                    <a class="vp-page-link {{ $payouts->onFirstPage() ? 'disabled' : '' }}" href="{{ $payouts->previousPageUrl() ?: '#' }}" aria-label="Page précédente">
                        <i data-lucide="chevron-left" style="width:16px;height:16px"></i>
                    </a>

                    @foreach($payouts->getUrlRange(max(1, $payouts->currentPage() - 1), min($payouts->lastPage(), $payouts->currentPage() + 1)) as $page => $url)
                        <a class="vp-page-link {{ $page === $payouts->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>
                    @endforeach

                    <a class="vp-page-link {{ $payouts->hasMorePages() ? '' : 'disabled' }}" href="{{ $payouts->nextPageUrl() ?: '#' }}" aria-label="Page suivante">
                        <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                    </a>

                    <select class="vp-per-page" aria-label="Nombre de lignes par page" disabled>
                        <option>15 / page</option>
                    </select>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chart = document.getElementById('vpPayoutChart');
    if (!chart) return;

    const values = JSON.parse(chart.dataset.values || '[]').map(Number);
    const labels = JSON.parse(chart.dataset.labels || '[]');
    const line = document.getElementById('vpChartLine');
    const dots = document.getElementById('vpChartDots');
    const labelGroup = document.getElementById('vpChartLabels');
    const topLabel = document.getElementById('vpChartTop');
    const midLabel = document.getElementById('vpChartMid');

    const left = 48;
    const right = 626;
    const top = 28;
    const bottom = 140;
    const width = right - left;
    const height = bottom - top;
    const maxValue = Math.max(1, ...values);

    const formatAxis = (value) => {
        if (value >= 1000000) return `${(value / 1000000).toFixed(value % 1000000 === 0 ? 0 : 1)}M`;
        if (value >= 1000) return `${(value / 1000).toFixed(value % 1000 === 0 ? 0 : 1)}K`;
        return Math.round(value).toString();
    };

    topLabel.textContent = formatAxis(maxValue);
    midLabel.textContent = formatAxis(maxValue / 2);

    const count = Math.max(1, values.length - 1);
    const points = values.map((value, index) => {
        const x = left + (width * index / count);
        const y = bottom - ((Number(value) / maxValue) * height);
        return [x, y];
    });

    line.setAttribute('points', points.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' '));
    dots.innerHTML = '';
    labelGroup.innerHTML = '';

    points.forEach(([x, y], index) => {
        if (values[index] <= 0) return;
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('class', 'vp-chart-dot');
        circle.setAttribute('cx', x);
        circle.setAttribute('cy', y);
        circle.setAttribute('r', 3.5);
        dots.appendChild(circle);
    });

    const labelIndexes = [0, 7, 14, 21, 29].filter((index) => index < labels.length);
    labelIndexes.forEach((index) => {
        const x = left + (width * index / count);
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('class', 'vp-chart-axis-label');
        text.setAttribute('x', x);
        text.setAttribute('y', 164);
        text.setAttribute('text-anchor', index === 0 ? 'start' : (index === labels.length - 1 ? 'end' : 'middle'));
        text.textContent = labels[index] || '';
        labelGroup.appendChild(text);
    });

    if (window.lucide) {
        window.lucide.createIcons();
    }
});
</script>
@endsection
