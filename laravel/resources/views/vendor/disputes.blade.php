@extends('layouts.vendor')

@section('title', 'Gestion des litiges | OVANIE')

@php
    $statusLabels = [
        'all' => 'Tous',
        'new' => 'Nouveaux',
        'in_progress' => 'En cours',
        'waiting_ovanie' => 'En attente d’OVANIE',
        'resolved' => 'Résolus',
        'closed' => 'Fermés',
    ];

    $statusMeta = [
        'new' => ['label' => 'Nouveau', 'class' => 'blue', 'dot' => 'blue'],
        'in_progress' => ['label' => 'En cours', 'class' => 'orange', 'dot' => 'orange'],
        'waiting_ovanie' => ['label' => 'En attente OVANIE', 'class' => 'violet', 'dot' => 'violet'],
        'resolved' => ['label' => 'Résolu', 'class' => 'green', 'dot' => 'green'],
        'closed' => ['label' => 'Fermé', 'class' => 'gray', 'dot' => 'gray'],
    ];

    $selectedStatus = $status ?: 'all';

    $formatPhone = static function ($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 10) {
            return trim(chunk_split($digits, 2, ' '));
        }
        return $value ?: '—';
    };

    $safeText = static fn ($value, $fallback = '—') =>
        filled($value) ? $value : $fallback;
@endphp

@section('styles')
<style>
    :root {
        --ds-navy: #092b67;
        --ds-blue: #1367ee;
        --ds-orange: #ff650d;
        --ds-green: #16a34a;
        --ds-violet: #7047eb;
        --ds-red: #ef4444;
        --ds-gray: #64748b;
        --ds-line: #dfe7f2;
        --ds-muted: #6f82a2;
        --ds-card: #fff;
    }

    .ds-page {
        max-width: 1280px;
        margin: 0 auto;
        padding: 26px 28px 42px;
        color: var(--ds-navy);
    }

    .ds-breadcrumb {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:14px;
        font-size:11px;
        font-weight:800;
        color:#8190aa;
    }

    .ds-breadcrumb a {
        color:var(--ds-blue);
        text-decoration:none;
    }

    .ds-breadcrumb svg {
        width:13px;
        height:13px;
    }

    .ds-head {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:18px;
        margin-bottom:24px;
    }

    .ds-head h1 {
        margin:0;
        font-size:clamp(32px,3vw,46px);
        line-height:1.05;
        font-weight:900;
        color:var(--ds-navy);
        letter-spacing:-.035em;
    }

    .ds-head p {
        margin:8px 0 0;
        color:#617597;
        font-size:13px;
    }

    .ds-btn {
        min-height:42px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:0 17px;
        border:1px solid #b8c8dc;
        border-radius:7px;
        background:#fff;
        color:var(--ds-navy);
        font-size:11px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        transition:.18s ease;
    }

    .ds-btn:hover {
        border-color:var(--ds-blue);
        transform:translateY(-1px);
    }

    .ds-btn svg {
        width:16px;
        height:16px;
    }

    .ds-card {
        border:1px solid var(--ds-line);
        border-radius:12px;
        background:#fff;
        box-shadow:0 10px 28px rgba(17,45,90,.035);
    }

    .ds-status-tabs {
        display:grid;
        grid-template-columns:1.08fr repeat(5,minmax(0,1fr));
        margin-bottom:22px;
        overflow:hidden;
    }

    .ds-status-tab {
        min-height:72px;
        position:relative;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:14px 18px;
        color:#17366c;
        text-decoration:none;
        border-right:1px solid var(--ds-line);
        background:#fff;
    }

    .ds-status-tab:last-child {
        border-right:0;
    }

    .ds-status-tab.is-active {
        border:1px solid #ffb37c;
        background:linear-gradient(135deg,#fff8f1,#fff);
        color:#e85b0a;
    }

    .ds-tab-label {
        display:flex;
        align-items:center;
        gap:8px;
        min-width:0;
        font-size:10.5px;
        font-weight:800;
    }

    .ds-dot {
        width:7px;
        height:7px;
        flex:0 0 7px;
        border-radius:50%;
        background:#8ca0bf;
    }

    .ds-dot.blue { background:#2c7cff; }
    .ds-dot.orange { background:#ff7a11; }
    .ds-dot.violet { background:#7a42f4; }
    .ds-dot.green { background:#18a957; }
    .ds-dot.gray { background:#71809d; }

    .ds-count {
        min-width:26px;
        height:26px;
        display:grid;
        place-items:center;
        border-radius:50%;
        background:#f2f5f9;
        color:#284269;
        font-size:10px;
        font-weight:900;
    }

    .ds-filters {
        display:grid;
        grid-template-columns:minmax(250px,1.55fr) minmax(155px,.72fr) minmax(145px,.64fr) minmax(145px,.64fr) minmax(145px,.64fr) minmax(88px,.38fr);
        gap:14px;
        align-items:end;
        padding:18px 20px;
        margin-bottom:16px;
    }

    .ds-field label {
        display:block;
        margin-bottom:6px;
        color:#6d7f9e;
        font-size:9.5px;
        font-weight:800;
    }

    .ds-input-wrap {
        position:relative;
    }

    .ds-input,
    .ds-select {
        width:100%;
        min-height:44px;
        border:1px solid #cdd9e9;
        border-radius:7px;
        background:#fff;
        color:#163568;
        font:inherit;
        font-size:11px;
        outline:none;
    }

    .ds-input {
        padding:0 12px 0 40px;
    }

    .ds-select {
        padding:0 36px 0 12px;
        appearance:none;
    }

    .ds-input-wrap > svg {
        position:absolute;
        left:13px;
        top:50%;
        width:16px;
        height:16px;
        color:#4c6e9e;
        transform:translateY(-50%);
        pointer-events:none;
    }

    .ds-select-wrap {
        position:relative;
    }

    .ds-select-wrap > svg {
        position:absolute;
        right:12px;
        top:50%;
        width:15px;
        height:15px;
        color:#4f6e98;
        transform:translateY(-50%);
        pointer-events:none;
    }


    .ds-more-filters {
        min-height:44px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:0 15px;
        border:1px solid #cdd9e9;
        border-radius:7px;
        background:#fff;
        color:#17366c;
        font-size:10.5px;
        font-weight:900;
        cursor:pointer;
        white-space:nowrap;
    }

    .ds-more-filters svg {
        width:14px;
        height:14px;
    }

    .ds-more-panel {
        grid-column:1 / -1;
        display:none;
        grid-template-columns:minmax(160px,220px) auto;
        align-items:end;
        justify-content:start;
        gap:12px;
        padding-top:2px;
    }

    .ds-more-panel.is-open {
        display:grid;
    }

    .ds-reset-cell {
        min-height:44px;
        display:flex;
        align-items:center;
        justify-content:flex-end;
        padding-bottom:1px;
        overflow:visible;
    }

    .ds-reset {
        display:inline-flex;
        align-items:center;
        min-height:44px;
        color:var(--ds-blue);
        font-size:10.5px;
        font-weight:800;
        text-decoration:none;
        white-space:nowrap;
    }

    .ds-filter-btn {
        min-height:40px;
        padding:0 16px;
        border:0;
        border-radius:7px;
        background:var(--ds-blue);
        color:#fff;
        font-size:10.5px;
        font-weight:900;
        cursor:pointer;
    }

    .ds-content-grid {
        display:grid;
        grid-template-columns:minmax(0,1fr) 230px;
        gap:14px;
        align-items:start;
    }

    .ds-table-card {
        overflow:hidden;
    }

    .ds-table {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
    }

    .ds-table th,
    .ds-table td {
        padding:13px 9px;
        border-bottom:1px solid #e8eef5;
        text-align:left;
        vertical-align:middle;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .ds-table th {
        color:#6f819e;
        font-size:8.1px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:0;
        white-space:nowrap;
        overflow:visible;
        text-overflow:clip;
        background:#fbfcfe;
    }

    .ds-table th:nth-child(6),
    .ds-table th:nth-child(7),
    .ds-table th:nth-child(8) {
        font-size:7.8px;
    }

    .ds-table td {
        color:#17366b;
        font-size:9.3px;
        line-height:1.5;
    }

    .ds-table tbody tr:hover {
        background:#fbfdff;
    }

    .ds-ref {
        display:block;
        color:#103a7c;
        font-weight:900;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .ds-subtext {
        display:block;
        margin-top:4px;
        color:#70819d;
        font-size:8.5px;
        line-height:1.35;
    }

    .ds-client strong,
    .ds-order strong {
        display:block;
        color:#18376d;
        font-weight:900;
    }

    .ds-status-pill {
        display:inline-flex;
        align-items:center;
        gap:6px;
        min-height:25px;
        max-width:100%;
        padding:0 9px;
        border-radius:999px;
        font-size:8.6px;
        font-weight:900;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .ds-status-pill::before {
        content:'';
        width:7px;
        height:7px;
        flex:0 0 7px;
        border-radius:50%;
        background:currentColor;
    }

    .ds-status-pill.blue { color:#1764e8; background:#eaf2ff; }
    .ds-status-pill.orange { color:#e86712; background:#fff0e4; }
    .ds-status-pill.violet { color:#6c45e8; background:#f0ebff; }
    .ds-status-pill.green { color:#14944c; background:#e7f8ed; }
    .ds-status-pill.gray { color:#63728e; background:#eef2f7; }

    .ds-actions {
        display:flex;
        align-items:center;
        gap:6px;
        white-space:nowrap;
    }

    .ds-view {
        min-height:32px;
        padding:0 12px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border:1px solid #d2deec;
        border-radius:6px;
        background:#fff;
        color:#143b78;
        font-size:9px;
        font-weight:900;
        cursor:pointer;
    }

    .ds-more {
        width:32px;
        height:32px;
        display:grid;
        place-items:center;
        border:1px solid #d2deec;
        border-radius:6px;
        background:#fff;
        color:#60769a;
        cursor:pointer;
    }

    .ds-more svg {
        width:15px;
        height:15px;
    }

    .ds-empty {
        padding:70px 24px;
        text-align:center;
        color:#7586a2;
    }

    .ds-empty-icon {
        width:68px;
        height:68px;
        margin:0 auto 14px;
        display:grid;
        place-items:center;
        border-radius:50%;
        background:#eef4fc;
        color:#7890b6;
    }

    .ds-empty-icon svg {
        width:34px;
        height:34px;
    }

    .ds-empty strong {
        display:block;
        color:#14356c;
        font-size:15px;
        margin-bottom:5px;
    }

    .ds-table-foot {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        padding:13px 16px;
        color:#7183a2;
        font-size:9.5px;
    }

    .ds-pagination {
        display:flex;
        align-items:center;
        gap:6px;
    }

    .ds-page-link {
        min-width:34px;
        height:34px;
        display:grid;
        place-items:center;
        border:1px solid #d7e1ed;
        border-radius:6px;
        color:#29466f;
        text-decoration:none;
        font-size:10px;
        font-weight:800;
        background:#fff;
    }

    .ds-page-link.active {
        border-color:var(--ds-blue);
        color:var(--ds-blue);
    }

    .ds-side {
        display:grid;
        gap:14px;
    }

    .ds-side-card {
        padding:18px;
    }

    .ds-side-title {
        display:flex;
        align-items:center;
        gap:10px;
        margin-bottom:15px;
    }

    .ds-side-icon {
        width:34px;
        height:34px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:#2960ba;
        background:#edf4ff;
    }

    .ds-side-icon svg {
        width:18px;
        height:18px;
    }

    .ds-side-title h2 {
        margin:0;
        color:#17366d;
        font-size:13px;
        font-weight:900;
    }

    .ds-guide-copy {
        color:#486083;
        font-size:10px;
        line-height:1.55;
    }

    .ds-guide-list {
        display:grid;
        gap:12px;
        margin-top:14px;
    }

    .ds-guide-item {
        display:grid;
        grid-template-columns:18px 1fr;
        gap:8px;
        color:#27456f;
        font-size:9.5px;
        line-height:1.45;
    }

    .ds-guide-check {
        width:16px;
        height:16px;
        display:grid;
        place-items:center;
        border-radius:50%;
        border:1.5px solid #15a652;
        color:#15a652;
    }

    .ds-guide-check svg {
        width:10px;
        height:10px;
    }

    .ds-help {
        border-color:#ffb878;
        background:linear-gradient(180deg,#fffaf5,#fff);
    }

    .ds-help p {
        margin:0 0 16px;
        color:#41597f;
        font-size:10px;
        line-height:1.6;
    }

    .ds-help-btn {
        width:100%;
        min-height:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        border:0;
        border-radius:7px;
        background:linear-gradient(90deg,#ff690e,#ff4f00);
        color:#fff;
        font-size:10.5px;
        font-weight:900;
        text-decoration:none;
    }

    .ds-message {
        margin-bottom:14px;
        padding:12px 14px;
        border-radius:8px;
        font-size:11px;
        font-weight:700;
    }

    .ds-message.success {
        color:#126b35;
        background:#edf9f1;
        border:1px solid #bde8cc;
    }

    .ds-message.error {
        color:#9f1c1c;
        background:#fff1f2;
        border:1px solid #fecaca;
    }

    .ds-modal {
        position:fixed;
        inset:0;
        z-index:1000;
        display:none;
        align-items:center;
        justify-content:center;
        padding:20px;
        background:rgba(15,35,72,.56);
        backdrop-filter:blur(3px);
    }

    .ds-modal.is-open {
        display:flex;
    }

    .ds-modal-card {
        width:min(760px,100%);
        max-height:88vh;
        overflow:auto;
        border-radius:14px;
        background:#fff;
        box-shadow:0 25px 70px rgba(5,20,50,.28);
    }

    .ds-modal-head {
        display:flex;
        justify-content:space-between;
        gap:16px;
        padding:20px 22px 16px;
        border-bottom:1px solid var(--ds-line);
    }

    .ds-modal-head h2 {
        margin:0;
        color:var(--ds-navy);
        font-size:22px;
        font-weight:900;
    }

    .ds-modal-head p {
        margin:5px 0 0;
        color:#7182a0;
        font-size:11px;
    }

    .ds-modal-close {
        width:36px;
        height:36px;
        display:grid;
        place-items:center;
        border:0;
        border-radius:8px;
        background:#f3f6fa;
        color:#24456f;
        cursor:pointer;
    }

    .ds-modal-body {
        padding:20px 22px 22px;
    }

    .ds-detail-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
        margin-bottom:16px;
    }

    .ds-detail {
        padding:12px 13px;
        border:1px solid var(--ds-line);
        border-radius:8px;
        background:#fbfdff;
    }

    .ds-detail small {
        display:block;
        color:#7586a2;
        font-size:9px;
        font-weight:800;
        margin-bottom:4px;
    }

    .ds-detail strong {
        color:#16376d;
        font-size:11px;
    }

    .ds-reason,
    .ds-response {
        padding:14px;
        border-radius:9px;
        line-height:1.6;
        font-size:11px;
    }

    .ds-reason {
        background:#f8fafc;
        border:1px solid #e2e8f0;
        color:#274366;
    }

    .ds-response {
        margin-top:12px;
        background:#eff6ff;
        border:1px solid #bfdbfe;
        color:#1e4c8d;
    }

    .ds-form {
        margin-top:16px;
    }

    .ds-form textarea {
        width:100%;
        min-height:110px;
        padding:12px;
        border:1px solid #cfdbea;
        border-radius:8px;
        font:inherit;
        font-size:11px;
        resize:vertical;
        outline:none;
    }

    .ds-form textarea:focus {
        border-color:var(--ds-blue);
        box-shadow:0 0 0 3px rgba(19,103,238,.08);
    }

    .ds-modal-actions {
        display:flex;
        justify-content:flex-end;
        gap:9px;
        margin-top:12px;
    }

    .ds-action-dark,
    .ds-action-danger {
        min-height:40px;
        padding:0 15px;
        border:0;
        border-radius:7px;
        color:#fff;
        font-size:10.5px;
        font-weight:900;
        cursor:pointer;
    }

    .ds-action-dark { background:#0f2f68; }
    .ds-action-danger { background:#dc2626; }

    @media(max-width:1120px) {
        .ds-filters {
            grid-template-columns:1fr 1fr 1fr;
        }

        .ds-reset-cell {
            justify-content:flex-start;
        }

        .ds-content-grid {
            grid-template-columns:1fr;
        }

        .ds-side {
            grid-template-columns:1fr 1fr;
        }
    }

    @media(max-width:820px) {
        .ds-page {
            padding:18px 14px 30px;
        }

        .ds-head {
            flex-direction:column;
        }

        .ds-status-tabs {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .ds-status-tab {
            border-bottom:1px solid var(--ds-line);
        }

        .ds-filters {
            grid-template-columns:1fr;
        }

        .ds-side {
            grid-template-columns:1fr;
        }

        .ds-table th:nth-child(3),
        .ds-table td:nth-child(3),
        .ds-table th:nth-child(6),
        .ds-table td:nth-child(6),
        .ds-table th:nth-child(7),
        .ds-table td:nth-child(7) {
            display:none;
        }
    }

    @media(max-width:560px) {
        .ds-status-tabs {
            grid-template-columns:1fr;
        }

        .ds-table th:nth-child(2),
        .ds-table td:nth-child(2),
        .ds-table th:nth-child(4),
        .ds-table td:nth-child(4),
        .ds-table th:nth-child(6),
        .ds-table td:nth-child(6) {
            display:none;
        }

        .ds-detail-grid {
            grid-template-columns:1fr;
        }

        .ds-top-actions {
            width:100%;
        }
    }
</style>
@endsection

@section('content')
<!-- OVANIE-LITIGES-MAQUETTE-V3 -->
<div class="ds-page">
    <nav class="ds-breadcrumb">
        <a href="{{ route('vendor.disputes.index') }}">Litiges</a>
        <i data-lucide="chevron-right"></i>
        <span>Gestion des litiges</span>
    </nav>

    <div class="ds-head">
        <div>
            <h1>Gestion des litiges</h1>
            <p>Consultez et répondez aux litiges ouverts par les clients.</p>
        </div>

        <a
            class="ds-btn"
            href="{{ route('vendor.disputes.index', array_merge(request()->query(), ['export' => 1])) }}"
        >
            <i data-lucide="download"></i>
            Exporter CSV
        </a>
    </div>

    @if(session('success'))
        <div class="ds-message success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="ds-message error">{{ $errors->first() }}</div>
    @endif

    <section class="ds-card ds-status-tabs">
        @foreach($statusLabels as $key => $label)
            @php
                $dotClass = match($key) {
                    'new' => 'blue',
                    'in_progress' => 'orange',
                    'waiting_ovanie' => 'violet',
                    'resolved' => 'green',
                    'closed' => 'gray',
                    default => 'blue',
                };
            @endphp

            <a
                class="ds-status-tab {{ $selectedStatus === $key ? 'is-active' : '' }}"
                href="{{ route('vendor.disputes.index', array_filter([
                    'q' => $search,
                    'status' => $key === 'all' ? null : $key,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => $perPage,
                ])) }}"
            >
                <span class="ds-tab-label">
                    @if($key !== 'all')
                        <span class="ds-dot {{ $dotClass }}"></span>
                    @endif
                    {{ $label }}
                </span>

                <span class="ds-count">{{ $statusCounts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </section>

    <form class="ds-card ds-filters" method="GET" action="{{ route('vendor.disputes.index') }}" id="disputesFilterForm">
        <div class="ds-field">
            <label>Recherche</label>
            <div class="ds-input-wrap">
                <i data-lucide="search"></i>
                <input
                    class="ds-input"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Rechercher par n° de litige, commande, client..."
                >
            </div>
        </div>

        <div class="ds-field">
            <label>Statut</label>
            <div class="ds-select-wrap">
                <select class="ds-select js-auto-filter" name="status">
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}" @selected($selectedStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
        </div>

        <div class="ds-field">
            <label>Période</label>
            <input class="ds-input js-auto-filter" style="padding-left:12px" type="date" name="date_from" value="{{ $dateFrom }}">
        </div>

        <div class="ds-field">
            <label>&nbsp;</label>
            <input class="ds-input js-auto-filter" style="padding-left:12px" type="date" name="date_to" value="{{ $dateTo }}">
        </div>

        <div class="ds-field">
            <label>&nbsp;</label>
            <button class="ds-more-filters" type="button" id="toggleMoreFilters">
                Plus de filtres
                <i data-lucide="chevron-down"></i>
            </button>
        </div>

        <div class="ds-reset-cell">
            <a class="ds-reset" href="{{ route('vendor.disputes.index') }}">Réinitialiser</a>
        </div>

        <div class="ds-more-panel" id="moreFiltersPanel">
            <div class="ds-field">
                <label>Affichage</label>
                <div class="ds-select-wrap">
                    <select class="ds-select" name="per_page">
                        @foreach([5,10,20,50] as $value)
                            <option value="{{ $value }}" @selected((int)$perPage === $value)>{{ $value }} / page</option>
                        @endforeach
                    </select>
                    <i data-lucide="chevron-down"></i>
                </div>
            </div>

            <button class="ds-filter-btn" type="submit">Appliquer</button>
        </div>
    </form>

    <div class="ds-content-grid">
        <section class="ds-card ds-table-card">
            <table class="ds-table">
                <colgroup>
                    <col style="width:12.5%">
                    <col style="width:14%">
                    <col style="width:12%">
                    <col style="width:17%">
                    <col style="width:10.5%">
                    <col style="width:12.5%">
                    <col style="width:12.5%">
                    <col style="width:9%">
                </colgroup>

                <thead>
                    <tr>
                        <th>Litige</th>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Date d’ouverture</th>
                        <th>Dernière activité</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($disputes as $dispute)
                        @php
                            $displayStatus = $dispute->vendor_display_status ?: 'new';
                            $meta = $statusMeta[$displayStatus] ?? $statusMeta['new'];
                            $clientName = $dispute->client_name ?: $dispute->order?->client?->name ?: 'Client';
                            $clientPhone = $dispute->order?->client?->phone;
                            $orderNumber = $dispute->order_reference ?: $dispute->order?->order_number ?: ('Commande #' . $dispute->order_id);
                            $reference = 'LTG-' . now()->format('Y') . '-' . str_pad((string)$dispute->id, 5, '0', STR_PAD_LEFT);
                        @endphp

                        <tr>
                            <td>
                                <span class="ds-ref">{{ $reference }}</span>
                                <span class="ds-subtext">{{ $meta['label'] }}</span>
                            </td>

                            <td class="ds-order">
                                <strong>{{ $orderNumber }}</strong>
                                <span class="ds-subtext">{{ optional($dispute->created_at)->format('d/m/Y') }}</span>
                            </td>

                            <td class="ds-client">
                                <strong>{{ $clientName }}</strong>
                                <span class="ds-subtext">{{ $formatPhone($clientPhone) }}</span>
                            </td>

                            <td title="{{ $dispute->reason }}">
                                {{ \Illuminate\Support\Str::limit($dispute->reason, 76) }}
                            </td>

                            <td>
                                <span class="ds-status-pill {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                            </td>

                            <td>
                                {{ optional($dispute->created_at)->format('d/m/Y') }}
                                <span class="ds-subtext">{{ optional($dispute->created_at)->format('H:i') }}</span>
                            </td>

                            <td>
                                {{ optional($dispute->updated_at)->format('d/m/Y') }}
                                <span class="ds-subtext">{{ optional($dispute->updated_at)->format('H:i') }}</span>
                            </td>

                            <td>
                                <div class="ds-actions">
                                    <button
                                        type="button"
                                        class="ds-view js-open-dispute"
                                        data-id="{{ $dispute->id }}"
                                        data-reference="{{ $reference }}"
                                        data-order="{{ $orderNumber }}"
                                        data-client="{{ $clientName }}"
                                        data-phone="{{ $formatPhone($clientPhone) }}"
                                        data-reason="{{ e($dispute->reason) }}"
                                        data-response="{{ e($dispute->response) }}"
                                        data-responded-at="{{ optional($dispute->responded_at)->format('d/m/Y H:i') }}"
                                        data-escalated="{{ $dispute->escalated ? '1' : '0' }}"
                                        data-respond-url="{{ route('vendor.disputes.respond', $dispute->id) }}"
                                        data-escalate-url="{{ route('vendor.disputes.escalate', $dispute->id) }}"
                                    >
                                        Voir
                                    </button>

                                    <button
                                        type="button"
                                        class="ds-more js-open-dispute"
                                        data-id="{{ $dispute->id }}"
                                        data-reference="{{ $reference }}"
                                        data-order="{{ $orderNumber }}"
                                        data-client="{{ $clientName }}"
                                        data-phone="{{ $formatPhone($clientPhone) }}"
                                        data-reason="{{ e($dispute->reason) }}"
                                        data-response="{{ e($dispute->response) }}"
                                        data-responded-at="{{ optional($dispute->responded_at)->format('d/m/Y H:i') }}"
                                        data-escalated="{{ $dispute->escalated ? '1' : '0' }}"
                                        data-respond-url="{{ route('vendor.disputes.respond', $dispute->id) }}"
                                        data-escalate-url="{{ route('vendor.disputes.escalate', $dispute->id) }}"
                                    >
                                        <i data-lucide="ellipsis-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="ds-empty">
                                    <span class="ds-empty-icon"><i data-lucide="shield-check"></i></span>
                                    <strong>Aucun litige trouvé</strong>
                                    Aucun dossier ne correspond aux critères sélectionnés.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="ds-table-foot">
                <span>
                    Affichage de
                    {{ $disputes->total() ? $disputes->firstItem() : 0 }}
                    à
                    {{ $disputes->lastItem() ?? 0 }}
                    sur {{ $disputes->total() }} litige(s)
                </span>

                @if($disputes->hasPages())
                    <nav class="ds-pagination">
                        @if($disputes->onFirstPage())
                            <span class="ds-page-link">‹</span>
                        @else
                            <a class="ds-page-link" href="{{ $disputes->previousPageUrl() }}">‹</a>
                        @endif

                        @foreach(range(1, $disputes->lastPage()) as $page)
                            <a
                                class="ds-page-link {{ $page === $disputes->currentPage() ? 'active' : '' }}"
                                href="{{ $disputes->url($page) }}"
                            >
                                {{ $page }}
                            </a>
                        @endforeach

                        @if($disputes->hasMorePages())
                            <a class="ds-page-link" href="{{ $disputes->nextPageUrl() }}">›</a>
                        @else
                            <span class="ds-page-link">›</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        <aside class="ds-side">
            <section class="ds-card ds-side-card">
                <div class="ds-side-title">
                    <span class="ds-side-icon"><i data-lucide="shield-question"></i></span>
                    <h2>Guide des litiges</h2>
                </div>

                <div class="ds-guide-copy">Quelques bonnes pratiques pour bien gérer un litige :</div>

                <div class="ds-guide-list">
                    @foreach([
                        'Répondez rapidement, idéalement sous 24h.',
                        'Restez courtois et professionnel.',
                        'Apportez des preuves précises et vérifiables.',
                        'Proposez une solution équitable au client.',
                        'OVANIE intervient lorsque la médiation est nécessaire.',
                    ] as $tip)
                        <div class="ds-guide-item">
                            <span class="ds-guide-check"><i data-lucide="check"></i></span>
                            <span>{{ $tip }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ds-card ds-side-card ds-help">
                <div class="ds-side-title">
                    <span class="ds-side-icon" style="color:#ff650d;background:#fff0e5;">
                        <i data-lucide="shield-check"></i>
                    </span>
                    <h2>Besoin d’assistance ?</h2>
                </div>

                <p>
                    Pour un litige complexe, contactez le support OVANIE. L’équipe peut intervenir en médiation.
                </p>

                <a class="ds-help-btn" href="{{ route('contact.index') }}">Contacter le support</a>
            </section>
        </aside>
    </div>
</div>

<div class="ds-modal" id="disputeModal" aria-hidden="true">
    <div class="ds-modal-card">
        <div class="ds-modal-head">
            <div>
                <h2 id="modalReference">Détail du litige</h2>
                <p id="modalOrder">Commande</p>
            </div>

            <button class="ds-modal-close" type="button" id="closeDisputeModal">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="ds-modal-body">
            <div class="ds-detail-grid">
                <div class="ds-detail">
                    <small>Client</small>
                    <strong id="modalClient">—</strong>
                </div>

                <div class="ds-detail">
                    <small>Téléphone</small>
                    <strong id="modalPhone">—</strong>
                </div>
            </div>

            <div class="ds-reason">
                <strong>Motif du litige</strong><br>
                <span id="modalReason">—</span>
            </div>

            <div class="ds-response" id="modalResponseBox" hidden>
                <strong>Votre réponse enregistrée</strong><br>
                <span id="modalResponse">—</span>
                <div class="ds-subtext" id="modalRespondedAt"></div>
            </div>

            <form class="ds-form" id="respondForm" method="POST">
                @csrf
                <textarea
                    name="response"
                    minlength="10"
                    maxlength="5000"
                    required
                    placeholder="Décrivez précisément les faits et la solution proposée..."
                ></textarea>

                <div class="ds-modal-actions">
                    <button class="ds-action-dark" type="submit">Enregistrer ma réponse</button>
                </div>
            </form>

            <form
                class="ds-form"
                id="escalateForm"
                method="POST"
                onsubmit="return confirm('Transmettre ce litige à l’administration OVANIE ?')"
            >
                @csrf

                <div class="ds-modal-actions">
                    <button class="ds-action-danger" type="submit">Escalader à OVANIE</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('disputeModal');
    const closeButton = document.getElementById('closeDisputeModal');
    const respondForm = document.getElementById('respondForm');
    const escalateForm = document.getElementById('escalateForm');
    const responseBox = document.getElementById('modalResponseBox');
    const moreFiltersButton = document.getElementById('toggleMoreFilters');
    const moreFiltersPanel = document.getElementById('moreFiltersPanel');

    if (moreFiltersButton && moreFiltersPanel) {
        moreFiltersButton.addEventListener('click', () => {
            moreFiltersPanel.classList.toggle('is-open');
        });
    }

    const filterForm = document.getElementById('disputesFilterForm');

    document.querySelectorAll('.js-auto-filter').forEach((field) => {
        field.addEventListener('change', () => {
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.js-open-dispute').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('modalReference').textContent = button.dataset.reference || 'Détail du litige';
            document.getElementById('modalOrder').textContent = button.dataset.order || 'Commande';
            document.getElementById('modalClient').textContent = button.dataset.client || '—';
            document.getElementById('modalPhone').textContent = button.dataset.phone || '—';
            document.getElementById('modalReason').textContent = button.dataset.reason || '—';

            const response = button.dataset.response || '';
            const escalated = button.dataset.escalated === '1';

            respondForm.action = button.dataset.respondUrl;
            escalateForm.action = button.dataset.escalateUrl;

            if (response) {
                responseBox.hidden = false;
                document.getElementById('modalResponse').textContent = response;
                document.getElementById('modalRespondedAt').textContent =
                    button.dataset.respondedAt ? 'Répondu le ' + button.dataset.respondedAt : '';
                respondForm.hidden = true;
            } else {
                responseBox.hidden = true;
                respondForm.hidden = false;
                respondForm.querySelector('textarea').value = '';
            }

            escalateForm.hidden = escalated;

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    closeButton.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
</script>
@endsection
