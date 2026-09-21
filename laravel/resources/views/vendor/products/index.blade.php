@extends('layouts.vendor')

@section('title', 'Catalogue des produits | OVANIE Seller Central')

@section('styles')
<style>
    :root {
        --cp-navy: #0a2454;
        --cp-navy-deep: #08204d;
        --cp-blue: #0b5ce5;
        --cp-orange: #ff5a0a;
        --cp-orange-dark: #f04400;
        --cp-text: #10234a;
        --cp-muted: #6b7d9d;
        --cp-border: #dbe4ef;
        --cp-soft: #f7f9fc;
        --cp-disabled: #eef2f7;
        --cp-shadow: 0 5px 18px rgba(16, 35, 74, .07);
    }

    .cp-page,
    .cp-page * { box-sizing: border-box; }

    .cp-page {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 0 12px;
        color: var(--cp-text);
    }

    .cp-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 18px;
        color: var(--cp-blue);
        font-size: 12px;
        font-weight: 600;
    }

    .cp-breadcrumb a { color: var(--cp-blue); }
    .cp-breadcrumb svg { width: 14px; height: 14px; color: #8ea0bb; }
    .cp-breadcrumb .current { color: var(--cp-text); }

    .cp-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 24px;
    }

    .cp-title-wrap h1 {
        margin: 0 0 7px;
        color: var(--cp-navy);
        font-size: clamp(30px, 3vw, 38px);
        line-height: 1.05;
        letter-spacing: -.035em;
        font-weight: 900;
    }

    .cp-title-wrap p {
        margin: 0;
        color: #53698f;
        font-size: 13px;
        font-weight: 500;
    }

    .cp-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-top: 1px;
    }

    .cp-btn {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 0 22px;
        border: 1px solid #8ea4c7;
        border-radius: 7px;
        background: #fff;
        color: #163468;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .cp-btn svg { width: 20px; height: 20px; stroke-width: 1.9; }

    .cp-btn:hover {
        border-color: var(--cp-blue);
        box-shadow: 0 5px 16px rgba(11, 92, 229, .12);
    }

    .cp-btn.primary {
        min-width: 206px;
        border-color: transparent;
        background: linear-gradient(135deg, #ff6a0a 0%, #ff4d00 100%);
        color: #fff;
        box-shadow: 0 8px 20px rgba(255, 90, 10, .24);
    }

    .cp-btn.primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(255, 90, 10, .3);
    }

    .cp-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 16px;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
    }

    .cp-alert svg { width: 18px; height: 18px; }
    .cp-alert.success { background: #ecfdf5; color: #067647; border: 1px solid #a7f3d0; }
    .cp-alert.error { background: #fff1f2; color: #b42318; border: 1px solid #fecaca; }

    .cp-filter-card,
    .cp-search-card,
    .cp-catalog-card {
        background: #fff;
        border: 1px solid var(--cp-border);
        box-shadow: var(--cp-shadow);
    }

    .cp-filter-card {
        min-height: 72px;
        display: flex;
        align-items: stretch;
        gap: 0;
        margin-bottom: 18px;
        padding: 14px 18px;
        border-radius: 8px;
    }

    .cp-primary-tabs {
        display: flex;
        align-items: center;
        gap: 2px;
        flex: 0 0 auto;
        padding-right: 22px;
        border-right: 1px solid #e4eaf2;
    }

    .cp-primary-tab {
        min-width: 110px;
        height: 42px;
        padding: 0 18px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #153269;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: background .16s ease, color .16s ease;
    }

    .cp-primary-tab:hover { background: #f4f7fb; }

    .cp-primary-tab.is-active {
        background: linear-gradient(180deg, #173a73 0%, #0b2b60 100%);
        color: #fff;
        box-shadow: 0 5px 12px rgba(10, 36, 84, .18);
    }

    .cp-moderation {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding-left: 28px;
    }

    .cp-moderation-label {
        color: #173468;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .cp-select-wrap {
        position: relative;
        width: 190px;
        flex: 0 0 190px;
    }

    .cp-select-wrap select {
        width: 100%;
        height: 42px;
        appearance: none;
        padding: 0 38px 0 14px;
        border: 1px solid #cbd7e7;
        border-radius: 7px;
        outline: none;
        background: #fff;
        color: #53698f;
        font-size: 12px;
        cursor: pointer;
    }

    .cp-select-wrap svg {
        position: absolute;
        right: 12px;
        top: 50%;
        width: 16px;
        height: 16px;
        color: #264d85;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .cp-filter-info {
        width: 20px;
        height: 20px;
        flex: 0 0 20px;
        color: #536b95;
    }

    .cp-search-card {
        display: grid;
        grid-template-columns: minmax(0, 2.2fr) minmax(280px, 1fr);
        gap: 24px;
        margin-bottom: 18px;
        padding: 14px;
        border-radius: 8px;
    }

    .cp-search,
    .cp-category-select { position: relative; }

    .cp-search > svg,
    .cp-category-select > svg {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .cp-search > svg {
        left: 13px;
        width: 20px;
        height: 20px;
        color: #42638f;
    }

    .cp-search input,
    .cp-category-select select {
        width: 100%;
        height: 42px;
        border: 1px solid #cbd7e7;
        border-radius: 7px;
        outline: none;
        background: #fff;
        color: #173468;
        font-size: 12px;
        transition: border-color .16s ease, box-shadow .16s ease;
    }

    .cp-search input {
        padding: 0 14px 0 42px;
    }

    .cp-search input::placeholder { color: #8796af; }

    .cp-search input:focus,
    .cp-category-select select:focus,
    .cp-select-wrap select:focus {
        border-color: var(--cp-blue);
        box-shadow: 0 0 0 3px rgba(11, 92, 229, .08);
    }

    .cp-category-select select {
        appearance: none;
        padding: 0 38px 0 14px;
        font-weight: 600;
    }

    .cp-category-select > svg {
        right: 12px;
        width: 17px;
        height: 17px;
        color: #214579;
    }

    .cp-catalog-card {
        overflow: hidden;
        border-radius: 8px;
    }

    .cp-catalog-card,
    .cp-filter-card,
    .cp-search-card,
    .cp-toolbar,
    .cp-footer {
        width: 100%;
        max-width: 100%;
    }

    .cp-toolbar {
        min-height: 74px;
        display: grid;
        grid-template-columns: minmax(160px, 190px) minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 0 16px;
        border-bottom: 1px solid #e1e8f1;
    }

    .cp-count {
        color: #18366b;
        font-size: 11.5px;
        font-weight: 500;
        white-space: nowrap;
    }

    .cp-view-tabs {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0;
    }

    .cp-view-tab {
        position: relative;
        height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 20px;
        border: 1px solid #e1e8f1;
        background: #fff;
        color: #53698f;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .cp-view-tab:first-child { border-radius: 6px 0 0 6px; }
    .cp-view-tab:last-child { border-radius: 0 6px 6px 0; border-left: 0; }

    .cp-view-tab.is-active {
        color: #102d60;
        font-weight: 800;
    }

    .cp-view-tab.is-active::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: var(--cp-blue);
    }

    .cp-bulk-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
    }

    .cp-bulk-btn {
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 15px;
        border: 1px solid #d9e1ec;
        border-radius: 7px;
        background: #fff;
        color: #173468;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: background .16s ease, border-color .16s ease;
    }

    .cp-bulk-btn svg { width: 17px; height: 17px; }

    .cp-bulk-btn:disabled {
        border-color: #edf1f6;
        background: #f3f5f8;
        color: #b2bdcd;
        cursor: not-allowed;
    }

    .cp-table-wrap {
        width: 100%;
        overflow-x: visible;
        background: #fff;
    }

    .cp-table {
        width: 100%;
        min-width: 0;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .cp-table thead th {
        height: 54px;
        padding: 0 14px;
        border-bottom: 1px solid #dce4ef;
        color: #233f73;
        background: #fff;
        font-size: 10.5px;
        font-weight: 800;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cp-table thead th:last-child { text-align: right; }

    .cp-sort {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .cp-sort svg { width: 13px; height: 13px; color: #7d90ae; }

    .cp-check {
        width: 17px;
        height: 17px;
        accent-color: var(--cp-blue);
        cursor: pointer;
    }

    .cp-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        color: #173468;
        font-size: 12px;
        vertical-align: middle;
    }

    .cp-table tbody tr:last-child td { border-bottom: 0; }
    .cp-table tbody tr:hover { background: #fbfcfe; }

    .cp-product-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .cp-product-image,
    .cp-product-placeholder {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border: 1px solid #dce4ef;
        border-radius: 7px;
        background: #f8fafc;
    }

    .cp-product-image { object-fit: contain; padding: 3px; }

    .cp-product-placeholder {
        display: grid;
        place-items: center;
        color: #8aa0c1;
    }

    .cp-product-placeholder svg { width: 22px; height: 22px; }
    .cp-product-placeholder.is-hidden { display: none; }

    .cp-product-name {
        max-width: 100%;
        overflow: hidden;
        color: #102d60;
        font-size: 12px;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cp-product-meta {
        margin-top: 3px;
        color: #8493ab;
        font-size: 10px;
    }

    .cp-price { color: #102d60; font-weight: 800; }
    .cp-promo { color: #04914e; font-weight: 800; }
    .cp-muted-value { color: #9aa8bc; }

    .cp-stock {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 8px;
        border-radius: 5px;
        font-size: 10px;
        font-weight: 800;
    }

    .cp-stock svg { width: 13px; height: 13px; }
    .cp-stock.ok { background: #ecfdf5; color: #067647; }
    .cp-stock.low { background: #fff7e6; color: #b54708; }
    .cp-stock.empty { background: #fff1f2; color: #b42318; }

    .cp-toggle {
        position: relative;
        width: 38px;
        height: 22px;
        display: inline-block;
    }

    .cp-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .cp-toggle-track {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #cbd5e1;
        cursor: pointer;
        transition: background .2s ease;
    }

    .cp-toggle-track::before {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 2px 5px rgba(15, 23, 42, .16);
        transition: transform .2s ease;
    }

    .cp-toggle input:checked + .cp-toggle-track { background: #16b364; }
    .cp-toggle input:checked + .cp-toggle-track::before { transform: translateX(16px); }

    .cp-row-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        flex-wrap: nowrap;
    }

    .cp-row-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dbe3ee;
        border-radius: 6px;
        background: #fff;
        color: #566d92;
        cursor: pointer;
        text-decoration: none;
    }

    .cp-row-btn:hover { border-color: #9eb6d9; color: var(--cp-blue); background: #f7faff; }
    .cp-row-btn svg { width: 15px; height: 15px; }

    .cp-empty {
        min-height: 324px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 36px 20px;
        text-align: center;
    }

    .cp-empty-icon {
        width: 104px;
        height: 104px;
        display: grid;
        place-items: center;
        margin-bottom: 13px;
        border-radius: 50%;
        background: #eef4ff;
        color: #607aa3;
    }

    .cp-empty-icon svg { width: 54px; height: 54px; stroke-width: 1.45; }

    .cp-empty h3 {
        margin: 0 0 8px;
        color: #0b2b60;
        font-size: 21px;
        line-height: 1.2;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .cp-empty p {
        margin: 0 0 25px;
        color: #53698f;
        font-size: 13px;
        font-weight: 500;
    }

    .cp-empty .cp-btn.primary {
        min-width: 220px;
        height: 50px;
    }

    .cp-footer {
        min-height: 68px;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 18px;
        padding: 10px 14px;
        border-top: 1px solid #e1e8f1;
        background: #fff;
    }

    .cp-per-page {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #53698f;
        font-size: 11px;
        white-space: nowrap;
    }

    .cp-per-page select {
        width: 60px;
        height: 38px;
        padding: 0 9px;
        border: 1px solid #d3deec;
        border-radius: 6px;
        background: #fff;
        color: #173468;
        outline: none;
        font-size: 11px;
    }

    .cp-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .cp-page-link {
        min-width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dbe4ef;
        border-radius: 6px;
        background: #fff;
        color: #35517e;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
    }

    .cp-page-link svg { width: 16px; height: 16px; }
    .cp-page-link.is-current { border-color: var(--cp-blue); color: var(--cp-blue); }
    .cp-page-link.is-disabled { color: #b4becd; background: #fafbfd; pointer-events: none; }

    .cp-page-info {
        justify-self: start;
        color: #53698f;
        font-size: 11px;
        white-space: nowrap;
    }

    .cp-no-filter-result {
        display: none;
        min-height: 180px;
        align-items: center;
        justify-content: center;
        padding: 30px;
        color: #6b7d9d;
        font-size: 13px;
        font-weight: 600;
        text-align: center;
    }

    .cp-no-filter-result.is-visible { display: flex; }

    /* Boost modal conservée, mais harmonisée */
    .boost-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(7, 23, 57, .55);
        backdrop-filter: blur(4px);
    }

    .boost-overlay.open { display: flex; }

    .boost-modal {
        width: 100%;
        max-width: 480px;
        overflow: hidden;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 28px 70px rgba(7, 23, 57, .24);
    }

    .boost-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid #e1e8f1;
    }

    .boost-modal-head h3 {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0;
        color: var(--cp-navy);
        font-size: 17px;
        font-weight: 900;
    }

    .boost-modal-head h3 svg { width: 20px; height: 20px; color: var(--cp-blue); }

    .boost-close-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #dbe4ef;
        border-radius: 6px;
        background: #fff;
        color: #53698f;
        cursor: pointer;
        font-size: 20px;
    }

    .boost-modal-body { padding: 20px; }
    .boost-product-name { margin: 0 0 5px; color: var(--cp-navy); font-size: 15px; font-weight: 900; }
    .boost-amount-info { margin: 0 0 16px; color: #c2410c; font-size: 12px; font-weight: 800; }
    .boost-field { margin-bottom: 14px; }
    .boost-field label { display: block; margin-bottom: 6px; color: #314d79; font-size: 12px; font-weight: 800; }
    .boost-field select,
    .boost-field input {
        width: 100%;
        height: 44px;
        padding: 0 12px;
        border: 1px solid #ccd8e8;
        border-radius: 7px;
        outline: none;
        color: var(--cp-text);
        background: #fff;
        font-size: 13px;
    }

    .boost-pay-btn {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 7px;
        background: linear-gradient(135deg, #ff6a0a, #ff4d00);
        color: #fff;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
    }

    #boostPaymentMessage { margin: 10px 0 0; text-align: center; font-size: 12px; font-weight: 700; }

    @media (max-width: 1050px) {
        .cp-page { max-width: 100%; }
        .cp-toolbar { grid-template-columns: 1fr auto; }
        .cp-table-wrap { overflow-x: auto; }
        .cp-table { min-width: 980px; }
        .cp-view-tabs { order: 3; grid-column: 1 / -1; }
        .cp-filter-card { flex-direction: column; gap: 12px; }
        .cp-primary-tabs { padding-right: 0; padding-bottom: 12px; border-right: 0; border-bottom: 1px solid #e4eaf2; }
        .cp-moderation { padding-left: 0; }
    }

    @media (max-width: 760px) {
        .cp-page { padding-top: 10px; }
        .cp-page-header { flex-direction: column; align-items: stretch; }
        .cp-header-actions { width: 100%; }
        .cp-header-actions .cp-btn { flex: 1; min-width: 0; }
        .cp-search-card { grid-template-columns: 1fr; gap: 10px; }
        .cp-primary-tabs { display: grid; grid-template-columns: repeat(3, 1fr); width: 100%; }
        .cp-primary-tab { min-width: 0; padding: 0 8px; }
        .cp-moderation { flex-wrap: wrap; }
        .cp-select-wrap { width: 100%; flex: 1 1 220px; }
        .cp-toolbar { display: flex; flex-direction: column; align-items: stretch; padding: 12px; }
        .cp-view-tabs { order: initial; width: 100%; }
        .cp-view-tab { flex: 1; }
        .cp-bulk-actions { justify-content: stretch; }
        .cp-bulk-btn { flex: 1; }
        .cp-footer { grid-template-columns: 1fr; justify-items: center; }
        .cp-per-page, .cp-page-info { justify-self: center; }
    }
</style>
@endsection

@section('content')
@php
    $inactiveProducts = max(($totalProducts ?? 0) - ($activeProducts ?? 0), 0);
    $isArchivedView = ($statusFilter ?? request('status', 'catalogue')) === 'archived';
@endphp

<div class="cp-page">
    <nav class="cp-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.products') }}">Produits</a>
        <i data-lucide="chevron-right"></i>
        <span class="current">Catalogue des produits</span>
    </nav>

    <header class="cp-page-header">
        <div class="cp-title-wrap">
            <h1>Catalogue des produits</h1>
            <p>{{ $totalProducts ?? $products->total() }} produit(s) dans votre boutique</p>
        </div>

        <div class="cp-header-actions">
            <a href="{{ route('vendor.add_product', ['type' => 'single']) }}" class="cp-btn primary">
                <i data-lucide="circle-plus"></i>
                Ajouter un produit
            </a>
            <button class="cp-btn" id="cpImportButton" type="button">
                <i data-lucide="cloud-upload"></i>
                Importer
            </button>
        </div>
    </header>

    @if(session('success'))
        <div class="cp-alert success">
            <i data-lucide="circle-check"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="cp-alert error">
            <i data-lucide="circle-x"></i>
            {{ session('error') }}
        </div>
    @endif

    <section class="cp-filter-card" aria-label="Filtres de statut">
        <div class="cp-primary-tabs" role="tablist">
            <button type="button" class="cp-primary-tab is-active" data-status-filter="all">
                Tous ({{ $totalProducts ?? 0 }})
            </button>
            <button type="button" class="cp-primary-tab" data-status-filter="active">
                Actif ({{ $activeProducts ?? 0 }})
            </button>
            <button type="button" class="cp-primary-tab" data-status-filter="inactive">
                Inactif ({{ $inactiveProducts }})
            </button>
        </div>

        <div class="cp-moderation">
            <span class="cp-moderation-label">Statut de modération :</span>
            <div class="cp-select-wrap">
                <select id="cpModerationFilter" aria-label="Statut de modération">
                    <option value="all">Tous les statuts</option>
                    <option value="actif">Actif</option>
                    <option value="inactif">Inactif</option>
                    <option value="draft">Brouillon</option>
                    <option value="pending_logistics">Logistique à compléter</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
            <i class="cp-filter-info" data-lucide="info"></i>
        </div>
    </section>

    <section class="cp-search-card" aria-label="Recherche et catégorie">
        <div class="cp-search">
            <i data-lucide="search"></i>
            <input
                type="search"
                id="cpSearchInput"
                placeholder="Rechercher un produit par nom, marque, SKU..."
                autocomplete="off"
            >
        </div>

        <div class="cp-category-select">
            <select id="cpCategoryFilter" aria-label="Filtrer par catégorie">
                <option value="all">Toutes les catégories</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <i data-lucide="chevron-down"></i>
        </div>
    </section>

    <section class="cp-catalog-card">
        <div class="cp-toolbar">
            <div class="cp-count">
                <span id="cpVisibleCount">{{ $products->count() }}</span> produit(s) affiché(s) sur {{ $products->total() }}
            </div>

            <div class="cp-view-tabs">
                <a
                    href="{{ route('vendor.products') }}"
                    class="cp-view-tab {{ ! $isArchivedView ? 'is-active' : '' }}"
                >
                    Catalogue actif
                </a>
                <a
                    href="{{ route('vendor.products', ['status' => 'archived']) }}"
                    class="cp-view-tab {{ $isArchivedView ? 'is-active' : '' }}"
                >
                    Archives ({{ $archivedProducts ?? 0 }})
                </a>
            </div>

            <div class="cp-bulk-actions">
                <button type="button" class="cp-bulk-btn" id="cpBulkActivate" disabled>
                    <i data-lucide="square-check-big"></i>
                    Activer la sélection
                </button>
                <button type="button" class="cp-bulk-btn" id="cpBulkArchive" disabled>
                    <i data-lucide="archive"></i>
                    Archiver
                </button>
            </div>
        </div>

        <div class="cp-table-wrap" id="cpTableWrap">
            <table class="cp-table">
                <colgroup>
                    <col style="width: 4%">
                    <col style="width: 27%">
                    <col style="width: 12%">
                    <col style="width: 12%">
                    <col style="width: 11%">
                    <col style="width: 13%">
                    <col style="width: 8%">
                    <col style="width: 13%">
                </colgroup>
                <thead>
                    <tr>
                        <th><input type="checkbox" class="cp-check" id="cpSelectAll" aria-label="Tout sélectionner"></th>
                        <th><span class="cp-sort">Produit <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="cp-sort">Prix <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="cp-sort">Promo <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="cp-sort">Livraison <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="cp-sort">Stock <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="cp-sort">Actif <i data-lucide="chevrons-up-down"></i></span></th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="cpProductsBody">
                    @forelse($products as $product)
                        @php
                            $mainImageUrl = $product->thumb_image_url ?? $product->main_image_url;
                            $stock = (int) ($product->stock ?? 0);
                            $stockClass = $stock === 0 ? 'empty' : ($stock <= 5 ? 'low' : 'ok');
                            $stockLabel = $stock === 0 ? 'Rupture' : ($stock <= 5 ? 'Faible' : 'En stock');
                            $productStatus = (string) ($product->status ?? ($product->is_active ? 'actif' : 'inactif'));
                            $searchText = mb_strtolower(trim(implode(' ', [
                                $product->name,
                                $product->brand,
                                $product->sku,
                                '#'.$product->id,
                            ])));
                        @endphp
                        <tr
                            class="cp-product-row"
                            data-product-key="{{ $product->getRouteKey() }}"
                            data-product-id="{{ $product->id }}"
                            data-active="{{ $product->is_active ? '1' : '0' }}"
                            data-status="{{ $productStatus }}"
                            data-category="{{ $product->category_id }}"
                            data-search="{{ $searchText }}"
                        >
                            <td>
                                <input type="checkbox" class="cp-check cp-row-check" value="{{ $product->id }}" aria-label="Sélectionner {{ $product->name }}">
                            </td>

                            <td>
                                <div class="cp-product-cell">
                                    @if($mainImageUrl)
                                        <img
                                            src="{{ $mainImageUrl }}"
                                            alt="{{ $product->name }}"
                                            class="cp-product-image"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                        >
                                        <span class="cp-product-placeholder is-hidden"><i data-lucide="image"></i></span>
                                    @else
                                        <span class="cp-product-placeholder"><i data-lucide="image"></i></span>
                                    @endif

                                    <div style="min-width:0">
                                        <div class="cp-product-name" title="{{ $product->name }}">{{ $product->name }}</div>
                                        <div class="cp-product-meta">
                                            {{ $product->sku ?: '#'.$product->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="cp-price">{{ number_format($product->price, 0, ',', ' ') }} FCFA</span>
                            </td>

                            <td>
                                @if($product->promo_price)
                                    <span class="cp-promo">{{ number_format($product->promo_price, 0, ',', ' ') }} FCFA</span>
                                @else
                                    <span class="cp-muted-value">—</span>
                                @endif
                            </td>

                            <td>
                                @if($product->transport)
                                    <span>{{ number_format($product->transport, 0, ',', ' ') }} FCFA</span>
                                @else
                                    <span class="cp-muted-value">—</span>
                                @endif
                            </td>

                            <td>
                                <span class="cp-stock {{ $stockClass }}">
                                    <i data-lucide="{{ $stock === 0 ? 'x' : ($stock <= 5 ? 'triangle-alert' : 'check') }}"></i>
                                    {{ $stock }} · {{ $stockLabel }}
                                </span>
                            </td>

                            <td>
                                @if($product->is_archived)
                                    <span class="cp-muted-value">Archivé</span>
                                @else
                                    <label class="cp-toggle">
                                        <input
                                            type="checkbox"
                                            {{ $product->is_active ? 'checked' : '' }}
                                            onchange="cpToggleProductStatus('{{ $product->getRouteKey() }}', this)"
                                        >
                                        <span class="cp-toggle-track"></span>
                                    </label>
                                @endif
                            </td>

                            <td>
                                <div class="cp-row-actions">
                                    <a href="{{ route('vendor.products.edit', $product) }}" class="cp-row-btn" title="Modifier">
                                        <i data-lucide="pencil"></i>
                                    </a>

                                    @unless($product->is_archived)
                                        <button
                                            type="button"
                                            class="cp-row-btn open-boost-modal"
                                            data-product-id="{{ $product->getRouteKey() }}"
                                            data-product-name="{{ $product->name }}"
                                            title="Booster"
                                        >
                                            <i data-lucide="zap"></i>
                                        </button>
                                    @endunless

                                    <button type="button" class="cp-row-btn" onclick="cpCopyProductId('{{ $product->id }}')" title="Copier l’ID">
                                        <i data-lucide="copy"></i>
                                    </button>

                                    @if($product->is_archived)
                                        <form action="{{ route('vendor.products.restore', $product) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="cp-row-btn" title="Restaurer">
                                                <i data-lucide="rotate-ccw"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form
                                            action="{{ route('vendor.products.destroy', $product) }}"
                                            method="POST"
                                            onsubmit="return confirm('Archiver ce produit ? Il ne sera plus visible à la vente, mais l’historique des commandes sera conservé.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cp-row-btn" title="Archiver">
                                                <i data-lucide="archive"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="cp-empty">
                                    <div class="cp-empty-icon">
                                        <i data-lucide="package-open"></i>
                                    </div>
                                    <h3>Aucun produit dans votre catalogue</h3>
                                    <p>Commencez par ajouter votre premier produit.</p>
                                    <a href="{{ route('vendor.add_product', ['type' => 'single']) }}" class="cp-btn primary">
                                        <i data-lucide="circle-plus"></i>
                                        Ajouter un produit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($products->count() > 0)
                <div class="cp-no-filter-result" id="cpNoFilterResult">
                    Aucun produit ne correspond aux filtres sélectionnés.
                </div>
            @endif
        </div>

        <footer class="cp-footer">
            <div class="cp-per-page">
                <span>Afficher</span>
                <select aria-label="Produits par page" disabled title="Pagination gérée par le serveur">
                    <option selected>{{ $products->perPage() }}</option>
                </select>
                <span>par page</span>
            </div>

            <nav class="cp-pagination" aria-label="Pagination">
                <a
                    href="{{ $products->url(1) }}"
                    class="cp-page-link {{ $products->onFirstPage() ? 'is-disabled' : '' }}"
                    aria-label="Première page"
                ><i data-lucide="chevrons-left"></i></a>

                <a
                    href="{{ $products->previousPageUrl() ?: '#' }}"
                    class="cp-page-link {{ $products->onFirstPage() ? 'is-disabled' : '' }}"
                    aria-label="Page précédente"
                ><i data-lucide="chevron-left"></i></a>

                <span class="cp-page-link is-current">{{ $products->currentPage() }}</span>

                <a
                    href="{{ $products->nextPageUrl() ?: '#' }}"
                    class="cp-page-link {{ $products->hasMorePages() ? '' : 'is-disabled' }}"
                    aria-label="Page suivante"
                ><i data-lucide="chevron-right"></i></a>

                <a
                    href="{{ $products->url($products->lastPage()) }}"
                    class="cp-page-link {{ $products->currentPage() === $products->lastPage() ? 'is-disabled' : '' }}"
                    aria-label="Dernière page"
                ><i data-lucide="chevrons-right"></i></a>
            </nav>

            <div class="cp-page-info">
                Page {{ $products->currentPage() }} sur {{ $products->lastPage() }}
            </div>
        </footer>
    </section>
</div>

<div id="boostOverlay" class="boost-overlay" aria-hidden="true">
    <div class="boost-modal" role="dialog" aria-modal="true" aria-labelledby="boostModalTitle">
        <div class="boost-modal-head">
            <h3 id="boostModalTitle"><i data-lucide="zap"></i> Booster ce produit</h3>
            <button class="boost-close-btn" id="closeBoost" type="button" aria-label="Fermer">×</button>
        </div>

        <div class="boost-modal-body">
            <p class="boost-product-name" id="boostProductName"></p>
            <p class="boost-amount-info">Montant : <span id="boostAmount"></span> FCFA</p>

            <form id="boostForm">
                @csrf
                <input type="hidden" id="boostProductId">

                <div class="boost-field">
                    <label for="boostPackage">Pack de boost</label>
                    <select id="boostPackage" required>
                        @foreach($boostPackages ?? [] as $key => $pack)
                            <option value="{{ $key }}" data-price="{{ $pack['price'] }}">
                                {{ $pack['label'] }} — {{ number_format($pack['price'], 0, ',', ' ') }} FCFA
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="boost-field">
                    <label for="boostPhone">Numéro Mobile Money</label>
                    <input type="tel" id="boostPhone" placeholder="Ex : 07 00 00 00 00">
                </div>

                <button type="submit" class="boost-pay-btn">Payer maintenant</button>
                <p id="boostPaymentMessage"></p>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        window.lucide.createIcons();
    }

    const toggleUrlTemplate = @json(route('vendor.products.toggle', '__PRODUCT__'));
    const archiveUrlTemplate = @json(route('vendor.products.destroy', '__PRODUCT__'));
    const boostPayUrlTemplate = @json(route('vendor.products.boost.pay', '__PRODUCT__'));

    const rows = Array.from(document.querySelectorAll('.cp-product-row'));
    const statusTabs = Array.from(document.querySelectorAll('[data-status-filter]'));
    const searchInput = document.getElementById('cpSearchInput');
    const categoryFilter = document.getElementById('cpCategoryFilter');
    const moderationFilter = document.getElementById('cpModerationFilter');
    const visibleCount = document.getElementById('cpVisibleCount');
    const noResult = document.getElementById('cpNoFilterResult');
    const selectAll = document.getElementById('cpSelectAll');
    const bulkActivate = document.getElementById('cpBulkActivate');
    const bulkArchive = document.getElementById('cpBulkArchive');

    let mainStatusFilter = 'all';

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function applyFilters() {
        const query = normalize(searchInput?.value);
        const category = categoryFilter?.value || 'all';
        const moderation = moderationFilter?.value || 'all';
        let count = 0;

        rows.forEach((row) => {
            const rowSearch = normalize(row.dataset.search);
            const rowCategory = row.dataset.category || '';
            const rowStatus = row.dataset.status || '';
            const isActive = row.dataset.active === '1';

            const matchesSearch = !query || rowSearch.includes(query);
            const matchesCategory = category === 'all' || rowCategory === category;
            const matchesModeration = moderation === 'all' || rowStatus === moderation;
            const matchesMainStatus = mainStatusFilter === 'all'
                || (mainStatusFilter === 'active' && isActive)
                || (mainStatusFilter === 'inactive' && !isActive);

            const show = matchesSearch && matchesCategory && matchesModeration && matchesMainStatus;
            row.style.display = show ? '' : 'none';
            if (show) count += 1;
        });

        if (visibleCount) visibleCount.textContent = String(count);
        if (noResult) noResult.classList.toggle('is-visible', rows.length > 0 && count === 0);
        syncSelectionState();
    }

    statusTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            statusTabs.forEach((item) => item.classList.remove('is-active'));
            tab.classList.add('is-active');
            mainStatusFilter = tab.dataset.statusFilter || 'all';
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', applyFilters);
    categoryFilter?.addEventListener('change', applyFilters);
    moderationFilter?.addEventListener('change', applyFilters);

    const rowChecks = () => Array.from(document.querySelectorAll('.cp-row-check'));

    function selectedRows() {
        return rows.filter((row) => {
            const checkbox = row.querySelector('.cp-row-check');
            return checkbox?.checked;
        });
    }

    function syncSelectionState() {
        const selected = selectedRows();
        const visibleRows = rows.filter((row) => row.style.display !== 'none');
        const visibleChecked = visibleRows.filter((row) => row.querySelector('.cp-row-check')?.checked);

        if (selectAll) {
            selectAll.checked = visibleRows.length > 0 && visibleChecked.length === visibleRows.length;
            selectAll.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visibleRows.length;
        }

        if (bulkActivate) bulkActivate.disabled = selected.length === 0;
        if (bulkArchive) bulkArchive.disabled = selected.length === 0;
    }

    selectAll?.addEventListener('change', () => {
        rows.forEach((row) => {
            if (row.style.display === 'none') return;
            const checkbox = row.querySelector('.cp-row-check');
            if (checkbox) checkbox.checked = selectAll.checked;
        });
        syncSelectionState();
    });

    rowChecks().forEach((checkbox) => checkbox.addEventListener('change', syncSelectionState));

    document.getElementById('cpImportButton')?.addEventListener('click', () => {
        window.alert('Le module d’import catalogue sera activé lorsque le traitement Excel/CSV sera disponible.');
    });

    window.cpCopyProductId = async (id) => {
        try {
            await navigator.clipboard.writeText(String(id));
        } catch (error) {
            console.warn('Impossible de copier l’ID produit.', error);
        }
    };

    window.cpToggleProductStatus = async (productKey, input) => {
        const previousValue = !input.checked;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            const response = await fetch(toggleUrlTemplate.replace('__PRODUCT__', encodeURIComponent(productKey)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ is_active: input.checked })
            });

            if (!response.ok) throw new Error('toggle_failed');

            const row = input.closest('.cp-product-row');
            if (row) row.dataset.active = input.checked ? '1' : '0';
            applyFilters();
        } catch (error) {
            input.checked = previousValue;
            window.alert('Impossible de modifier le statut du produit. Veuillez réessayer.');
        }
    };

    bulkActivate?.addEventListener('click', async () => {
        const selected = selectedRows();
        if (selected.length === 0) return;

        bulkActivate.disabled = true;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            for (const row of selected) {
                const key = row.dataset.productKey;
                const response = await fetch(toggleUrlTemplate.replace('__PRODUCT__', encodeURIComponent(key)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ is_active: true })
                });

                if (!response.ok) throw new Error('bulk_activate_failed');
                row.dataset.active = '1';
                const toggle = row.querySelector('.cp-toggle input');
                if (toggle) toggle.checked = true;
            }

            applyFilters();
        } catch (error) {
            window.alert('Une erreur est survenue pendant l’activation de la sélection.');
        } finally {
            syncSelectionState();
        }
    });

    bulkArchive?.addEventListener('click', async () => {
        const selected = selectedRows();
        if (selected.length === 0) return;
        if (!window.confirm(`Archiver ${selected.length} produit(s) sélectionné(s) ?`)) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        bulkArchive.disabled = true;

        try {
            for (const row of selected) {
                const key = row.dataset.productKey;
                const response = await fetch(archiveUrlTemplate.replace('__PRODUCT__', encodeURIComponent(key)), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok && response.status !== 302) throw new Error('bulk_archive_failed');
                row.remove();
            }

            window.location.reload();
        } catch (error) {
            window.alert('Une erreur est survenue pendant l’archivage de la sélection.');
            syncSelectionState();
        }
    });

    /* Boost */
    const overlay = document.getElementById('boostOverlay');
    const closeBoost = document.getElementById('closeBoost');
    const productName = document.getElementById('boostProductName');
    const amount = document.getElementById('boostAmount');
    const productId = document.getElementById('boostProductId');
    const packageSelect = document.getElementById('boostPackage');
    const paymentMessage = document.getElementById('boostPaymentMessage');

    function refreshBoostAmount() {
        const option = packageSelect?.options[packageSelect.selectedIndex];
        if (option && amount) {
            amount.textContent = Number(option.dataset.price || 0).toLocaleString('fr-FR');
        }
    }

    document.querySelectorAll('.open-boost-modal').forEach((button) => {
        button.addEventListener('click', () => {
            if (productId) productId.value = button.dataset.productId || '';
            if (productName) productName.textContent = button.dataset.productName || '';
            if (paymentMessage) paymentMessage.textContent = '';
            refreshBoostAmount();
            overlay?.classList.add('open');
            overlay?.setAttribute('aria-hidden', 'false');
        });
    });

    packageSelect?.addEventListener('change', refreshBoostAmount);

    function closeBoostModal() {
        overlay?.classList.remove('open');
        overlay?.setAttribute('aria-hidden', 'true');
    }

    closeBoost?.addEventListener('click', closeBoostModal);
    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) closeBoostModal();
    });

    document.getElementById('boostForm')?.addEventListener('submit', async function (event) {
        event.preventDefault();
        const csrf = this.querySelector('input[name="_token"]')?.value;

        try {
            const response = await fetch(boostPayUrlTemplate.replace('__PRODUCT__', encodeURIComponent(productId.value)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    boost_package: packageSelect?.value,
                    phone: document.getElementById('boostPhone')?.value
                })
            });

            const data = await response.json();

            if (data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            if (paymentMessage) {
                paymentMessage.textContent = data.message || 'Impossible de lancer le paiement.';
                paymentMessage.style.color = '#b42318';
            }
        } catch (error) {
            if (paymentMessage) {
                paymentMessage.textContent = 'Erreur réseau. Veuillez réessayer.';
                paymentMessage.style.color = '#b42318';
            }
        }
    });

    applyFilters();
    syncSelectionState();
});
</script>
@endsection
