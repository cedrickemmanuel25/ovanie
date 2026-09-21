@extends('layouts.vendor')

@section('title', 'Paramètres de livraison | OVANIE')

@php
    $profile = $shop->sellerDeliveryProfile;
    $selectedVehicles = old('vehicle_types', $profile?->vehicle_types ?? []);
    $activeZonesCount = $zones->where('is_active', true)->pluck('commune')->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->unique()->count();

    $profileReady = $profile
        && filled($profile->default_delay)
        && filled($profile->max_weight_kg)
        && filled($profile->max_volume_m3)
        && filled($profile->conditions);

    $configurationReady = $profileReady && $activeZonesCount > 0;

    $vehicles = [
        'moto' => [
            'label' => 'Moto',
            'image' => asset('images/vendor/vehicles/moto.png'),
            'hint' => 'Petites commandes',
        ],
        'tricycle' => [
            'label' => 'Tricycle',
            'image' => asset('images/vendor/vehicles/tricycle.png'),
            'hint' => 'Charges légères',
        ],
        'pickup' => [
            'label' => 'Pickup',
            'image' => asset('images/vendor/vehicles/pickup.png'),
            'hint' => 'Charges moyennes',
        ],
        'camion_3t' => [
            'label' => 'Camion 3T',
            'image' => asset('images/vendor/vehicles/camion-3t.png'),
            'hint' => 'Charges lourdes',
        ],
        'camion_10t' => [
            'label' => 'Camion 10T',
            'image' => asset('images/vendor/vehicles/camion-10t.png'),
            'hint' => 'Très gros volumes',
        ],
    ];
@endphp

@section('styles')
<style>
    :root {
        --dl-navy: #0a2a62;
        --dl-blue: #0d5ee8;
        --dl-orange: #ff5a0a;
        --dl-green: #12b76a;
        --dl-text: #102a5a;
        --dl-muted: #63769a;
        --dl-border: #dbe4f0;
        --dl-soft: #f7f9fc;
        --dl-card: #ffffff;
        --dl-shadow: 0 10px 30px rgba(16, 42, 90, .07);
    }

    .dl-page,
    .dl-page * { box-sizing: border-box; }

    .dl-page {
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 18px 12px 34px;
        color: var(--dl-text);
    }

    .dl-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 17px;
        color: var(--dl-blue);
        font-size: 12px;
        font-weight: 700;
    }

    .dl-breadcrumb a {
        color: var(--dl-blue);
        text-decoration: none;
    }

    .dl-breadcrumb .current { color: #52678d; }
    .dl-breadcrumb svg { width: 14px; height: 14px; color: #9cadc6; }

    .dl-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
    }

    .dl-kicker {
        margin-bottom: 6px;
        color: var(--dl-orange);
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .dl-title {
        margin: 0;
        color: var(--dl-navy);
        font-size: clamp(30px, 3vw, 40px);
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: -.035em;
    }

    .dl-subtitle {
        max-width: 820px;
        margin: 7px 0 0;
        color: #5d7298;
        font-size: 13px;
        line-height: 1.55;
    }

    .dl-back {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 0 18px;
        border: 1px solid #aebdd3;
        border-radius: 7px;
        background: #fff;
        color: var(--dl-navy);
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .dl-back svg { width: 17px; height: 17px; }

    .dl-flash {
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 12px;
        line-height: 1.45;
        font-weight: 700;
    }

    .dl-flash.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #166534; }
    .dl-flash.error { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }

    .dl-status {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
        padding: 17px 18px;
        border: 1px solid #ffc7a6;
        border-radius: 8px;
        background: #fffaf6;
    }

    .dl-status.ready {
        border-color: #b7eacb;
        background: #f5fff9;
    }

    .dl-status-main {
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 0;
    }

    .dl-status-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid;
        place-items: center;
        border: 2px solid var(--dl-orange);
        border-radius: 50%;
        color: var(--dl-orange);
    }

    .dl-status.ready .dl-status-icon {
        border-color: var(--dl-green);
        color: var(--dl-green);
    }

    .dl-status-icon svg { width: 23px; height: 23px; }

    .dl-status strong {
        display: block;
        margin-bottom: 4px;
        color: var(--dl-orange);
        font-size: 15px;
        font-weight: 900;
    }

    .dl-status.ready strong { color: #0b8a49; }

    .dl-status p {
        margin: 0;
        color: #53698f;
        font-size: 12px;
        line-height: 1.45;
    }

    .dl-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 13px;
        border: 1px solid #ffb37f;
        border-radius: 5px;
        background: #fff7f0;
        color: var(--dl-orange);
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .dl-status.ready .dl-status-badge {
        border-color: #a7e2c0;
        background: #effcf5;
        color: #0d934d;
    }

    .dl-panel {
        margin-bottom: 20px;
        overflow: hidden;
        border: 1px solid var(--dl-border);
        border-radius: 8px;
        background: var(--dl-card);
        box-shadow: var(--dl-shadow);
    }

    .dl-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 24px 17px;
        border-bottom: 1px solid #e8eef6;
    }

    .dl-panel-head h2 {
        margin: 0;
        color: var(--dl-navy);
        font-size: 18px;
        font-weight: 900;
    }

    .dl-panel-head p {
        margin: 6px 0 0;
        color: #6a7ea0;
        font-size: 11.5px;
        line-height: 1.5;
    }

    .dl-panel-state {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 12px;
        border: 1px solid #ffb37f;
        border-radius: 5px;
        background: #fff7f0;
        color: var(--dl-orange);
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .dl-panel-state.complete {
        border-color: #b8e5c9;
        background: #f2fcf6;
        color: #0d934d;
    }

    .dl-form-body { padding: 22px 24px 20px; }

    .dl-section + .dl-section {
        margin-top: 24px;
        padding-top: 22px;
        border-top: 1px solid #edf1f6;
    }

    .dl-section-title {
        margin: 0 0 16px;
        color: var(--dl-navy);
        font-size: 14px;
        font-weight: 900;
    }

    .dl-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 28px;
    }

    .dl-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 26px;
    }

    .dl-field { min-width: 0; }

    .dl-field label {
        display: block;
        margin-bottom: 7px;
        color: #18315f;
        font-size: 11.5px;
        font-weight: 800;
    }

    .dl-input-wrap { position: relative; }

    .dl-input,
    .dl-textarea {
        width: 100%;
        border: 1px solid #cbd7e6;
        border-radius: 6px;
        background: #fff;
        color: #172f5f;
        font: inherit;
        font-size: 12px;
        outline: none;
        transition: .18s ease;
    }

    .dl-input {
        height: 44px;
        padding: 0 13px;
    }

    .dl-input.with-unit { padding-right: 55px; }

    .dl-textarea {
        min-height: 92px;
        padding: 13px;
        resize: vertical;
        line-height: 1.5;
    }

    .dl-input:focus,
    .dl-textarea:focus {
        border-color: var(--dl-blue);
        box-shadow: 0 0 0 3px rgba(13, 94, 232, .09);
    }


    .dl-select-wrap {
        position: relative;
    }

    .dl-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 42px;
        cursor: pointer;
        background-color: #fff;
    }

    .dl-select-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        width: 16px;
        height: 16px;
        color: #5f7293;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .dl-unit {
        position: absolute;
        top: 0;
        right: 0;
        height: 44px;
        min-width: 50px;
        display: grid;
        place-items: center;
        border-left: 1px solid #d4deea;
        color: #5f7293;
        font-size: 11px;
        font-weight: 700;
    }

    .dl-field-help {
        margin: 7px 0 0;
        color: #7182a0;
        font-size: 10.5px;
        line-height: 1.45;
    }

    .dl-vehicles {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 16px;
    }

    .dl-vehicle { position: relative; min-width: 0; }
    .dl-vehicle input { position: absolute; opacity: 0; pointer-events: none; }

    .dl-vehicle-card {
        position: relative;
        min-height: 152px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px 10px 12px;
        border: 1px solid #c8d4e4;
        border-radius: 7px;
        background: #fff;
        cursor: pointer;
        transition: .18s ease;
    }

    .dl-vehicle-card:hover {
        border-color: #87a9e4;
        transform: translateY(-1px);
    }

    .dl-vehicle-visual {
        width: 132px;
        height: 88px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 10px;
        border-radius: 14px;
        background: linear-gradient(180deg, #f7faff 0%, #eef4ff 100%);
        border: 1px solid #e1eaf6;
        overflow: hidden;
    }

    .dl-vehicle-visual img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
        display: block;
        filter: drop-shadow(0 6px 10px rgba(18, 40, 84, 0.10));
    }

    .dl-vehicle-card strong {
        color: var(--dl-navy);
        font-size: 12px;
        font-weight: 900;
        text-align: center;
    }

    .dl-vehicle-check {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 20px;
        height: 20px;
        display: grid;
        place-items: center;
        border: 1.5px solid #9fb0c8;
        border-radius: 50%;
        background: #fff;
        color: transparent;
    }

    .dl-vehicle-check svg { width: 13px; height: 13px; stroke-width: 3; }

    .dl-vehicle input:checked + .dl-vehicle-card {
        border-color: var(--dl-blue);
        background: #fbfdff;
        box-shadow: inset 0 0 0 1px rgba(13, 94, 232, .15);
    }

    .dl-vehicle input:checked + .dl-vehicle-card .dl-vehicle-check {
        border-color: var(--dl-blue);
        background: var(--dl-blue);
        color: #fff;
    }

    .dl-save-row {
        display: flex;
        justify-content: center;
        padding-top: 20px;
    }

    .dl-btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 0;
        border-radius: 6px;
        padding: 0 20px;
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        text-decoration: none;
    }

    .dl-btn.orange {
        min-width: 360px;
        background: linear-gradient(90deg, #ff5a0a, #ff6c13);
        color: #fff;
        box-shadow: 0 8px 18px rgba(255, 90, 10, .18);
    }

    .dl-btn.outline-orange {
        min-height: 38px;
        border: 1px solid var(--dl-orange);
        background: #fff;
        color: var(--dl-orange);
    }

    .dl-btn.light {
        border: 1px solid #cbd7e6;
        background: #fff;
        color: var(--dl-navy);
    }

    .dl-btn.danger {
        border: 1px solid #fecaca;
        background: #fff;
        color: #b91c1c;
    }

    .dl-tariff-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 24px 14px;
    }

    .dl-tariff-head h2 {
        margin: 0;
        color: var(--dl-navy);
        font-size: 18px;
        font-weight: 900;
    }

    .dl-tariff-head p {
        margin: 6px 0 0;
        color: #6a7ea0;
        font-size: 11.5px;
    }

    .dl-table-wrap {
        margin: 0 24px 22px;
        overflow: hidden;
        border: 1px solid #dce5f0;
        border-radius: 7px;
        background: #fff;
    }

    .dl-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .dl-table th {
        padding: 12px 16px;
        border-bottom: 1px solid #e5ebf3;
        background: #fbfcfe;
        color: #24406f;
        font-size: 10.5px;
        font-weight: 900;
        text-align: left;
        text-transform: uppercase;
    }

    .dl-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #edf2f7;
        color: #425a80;
        font-size: 11.5px;
        vertical-align: middle;
    }

    .dl-table tbody tr:last-child td { border-bottom: 0; }
    .dl-table strong { color: var(--dl-navy); }

    .dl-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 0 9px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 900;
    }

    .dl-badge.on { background: #eafaf1; color: #0b9550; }
    .dl-badge.off { background: #f1f5f9; color: #64748b; }

    .dl-row-actions {
        display: flex;
        justify-content: flex-end;
    }

    .dl-edit-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #ced9e7;
        border-radius: 5px;
        background: #fff;
        color: var(--dl-navy);
        padding: 7px 9px;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
    }

    .dl-empty {
        min-height: 190px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 30px 20px;
        text-align: center;
    }

    .dl-empty-icon {
        width: 76px;
        height: 76px;
        display: grid;
        place-items: center;
        margin-bottom: 12px;
        border-radius: 50%;
        background: #eef4ff;
        color: #345d9e;
    }

    .dl-empty-icon svg { width: 42px; height: 42px; stroke-width: 1.5; }
    .dl-empty strong { color: var(--dl-navy); font-size: 15px; font-weight: 900; }
    .dl-empty p { margin: 6px 0 0; color: #6b7e9e; font-size: 11px; }

    .dl-modal {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .dl-modal.is-open { display: flex; }
    .dl-modal-backdrop { position: absolute; inset: 0; background: rgba(9, 27, 58, .58); }

    .dl-modal-card {
        position: relative;
        width: min(720px, 100%);
        max-height: 90vh;
        overflow: auto;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 30px 80px rgba(9, 27, 58, .28);
    }

    .dl-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 20px 22px;
        border-bottom: 1px solid #e8eef6;
    }

    .dl-modal-head h3 { margin: 0; color: var(--dl-navy); font-size: 19px; }
    .dl-modal-head p { margin: 5px 0 0; color: #687b9c; font-size: 12px; }

    .dl-modal-close {
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 8px;
        background: #f2f5f9;
        color: #445a7c;
        font-size: 21px;
        cursor: pointer;
    }

    .dl-modal-body { display: grid; gap: 18px; padding: 21px 22px; }
    .dl-modal-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 22px;
        border-top: 1px solid #e8eef6;
        background: #fbfcfe;
    }

    .dl-modal-actions { display: flex; gap: 10px; margin-left: auto; }
    .dl-switch { display: inline-flex; align-items: center; gap: 8px; color: #2f4772; font-size: 11px; font-weight: 800; }
    .dl-switch input { width: 18px; height: 18px; }

    .dl-advanced {
        padding: 12px 14px;
        border: 1px dashed #c9d6e7;
        border-radius: 7px;
        background: #fbfcff;
    }

    .dl-advanced summary { color: #405a82; font-size: 11.5px; font-weight: 800; cursor: pointer; }

    @media (max-width: 1040px) {
        .dl-grid-3 { grid-template-columns: 1fr 1fr; }
        .dl-vehicles { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
        .dl-page { padding-inline: 6px; }
        .dl-head { flex-direction: column; }
        .dl-back { width: 100%; }
        .dl-status { align-items: flex-start; flex-direction: column; }
        .dl-grid-3, .dl-grid-2 { grid-template-columns: 1fr; gap: 16px; }
        .dl-vehicles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dl-form-body, .dl-panel-head, .dl-tariff-head { padding-left: 16px; padding-right: 16px; }
        .dl-table-wrap { margin-left: 16px; margin-right: 16px; overflow-x: auto; }
        .dl-table { min-width: 760px; }
        .dl-btn.orange { width: 100%; min-width: 0; }
        .dl-modal-foot { align-items: stretch; flex-direction: column; }
        .dl-modal-actions { width: 100%; }
        .dl-modal-actions .dl-btn { flex: 1; }
    }
</style>
@endsection

@section('content')
<div class="dl-page">
    <div class="dl-breadcrumb">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.delivery.index') }}">Livraison</a>
        <i data-lucide="chevron-right"></i>
        <span class="current">Configuration de ma livraison</span>
    </div>

    <header class="dl-head">
        <div>
            <div class="dl-kicker">Logistique vendeur</div>
            <h1 class="dl-title">Paramètres de livraison</h1>
            <p class="dl-subtitle">Configurez vos capacités de transport et votre grille tarifaire par commune pour gérer vos propres livraisons.</p>
        </div>

        <a class="dl-back" href="{{ route('vendor.delivery.index') }}">
            <i data-lucide="arrow-left"></i>
            Retour à la livraison
        </a>
    </header>

    @if($shop->usesOvanieLogistics())
        <section class="dl-panel" style="padding:20px;margin-bottom:20px">
            <h2>Préparer ma propre logistique</h2>
            <p>OVANIE Logistics reste actif pendant la configuration. Enregistrez vos capacités, puis au moins une zone avec son tarif et son délai, avant de confirmer le changement.</p>
            <form method="POST" action="{{ route('vendor.delivery.mode.update') }}" onsubmit="return window.confirm('Activer ma propre logistique uniquement pour les nouvelles commandes ? Les commandes existantes conservent leur mode logistique.');">
                @csrf
                <input type="hidden" name="logistics_type" value="seller">
                <input type="hidden" name="expected_logistics_type" value="ovanie">
                <input type="hidden" name="confirmed" value="1">
                <button type="submit" class="dl-back" @disabled(!$configurationReady)>Activer ma propre logistique</button>
                <a class="dl-back" href="{{ route('vendor.delivery.index') }}">Annuler le changement</a>
            </form>
        </section>
    @endif

    @if(session('success'))
        <div class="dl-flash success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="dl-flash error">
            <strong>Des informations doivent être corrigées :</strong>
            <ul style="margin:7px 0 0;padding-left:18px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="dl-status {{ $configurationReady ? 'ready' : '' }}">
        <div class="dl-status-main">
            <span class="dl-status-icon">
                <i data-lucide="{{ $configurationReady ? 'circle-check-big' : 'circle-alert' }}"></i>
            </span>
            <div>
                <strong>{{ $configurationReady ? 'Configuration opérationnelle' : 'Configuration à compléter' }}</strong>
                <p>
                    @if($configurationReady)
                        Vos paramètres de transport et votre grille tarifaire sont configurés pour {{ $activeZonesCount }} commune{{ $activeZonesCount > 1 ? 's' : '' }} active{{ $activeZonesCount > 1 ? 's' : '' }}.
                    @else
                        Finalisez vos paramètres de transport et votre grille tarifaire pour activer votre logistique vendeur.
                    @endif
                </p>
            </div>
        </div>
        <span class="dl-status-badge">{{ $configurationReady ? 'Opérationnelle' : 'À compléter' }}</span>
    </section>

    <section class="dl-panel">
        <div class="dl-panel-head">
            <div>
                <h2>1. Paramètres de transport</h2>
                <p>Définissez vos capacités de transport, véhicules disponibles et conditions de livraison.</p>
            </div>
            <span class="dl-panel-state {{ $profileReady ? 'complete' : '' }}">{{ $profileReady ? 'Complet' : 'À compléter' }}</span>
        </div>

        <form method="POST" action="{{ route('vendor.delivery.update') }}" class="dl-form-body">
            @csrf

            <div class="dl-section">
                <h3 class="dl-section-title">Limites de transport</h3>
                <div class="dl-grid-3">
                    <div class="dl-field">
                        <label for="default_delay">Délai de préparation avant départ</label>
                        <div class="dl-input-wrap">
                            <input
                                id="default_delay"
                                class="dl-input"
                                name="default_delay"
                                value="{{ old('default_delay', $profile?->default_delay) }}"
                                placeholder="Ex. 24h"
                                required
                            >
                        </div>
                        <p class="dl-field-help">Temps moyen nécessaire avant le départ de la livraison.</p>
                    </div>

                    <div class="dl-field">
                        <label for="max_weight_kg">Poids maximal par livraison</label>
                        <div class="dl-input-wrap">
                            <input
                                id="max_weight_kg"
                                class="dl-input with-unit"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="max_weight_kg"
                                value="{{ old('max_weight_kg', $profile?->max_weight_kg) }}"
                                placeholder="Ex. 250"
                                required
                            >
                            <span class="dl-unit">kg</span>
                        </div>
                        <p class="dl-field-help">Au-delà de cette limite, la commande devra être traitée autrement.</p>
                    </div>

                    <div class="dl-field">
                        <label for="max_volume_m3">Volume maximal par livraison</label>
                        <div class="dl-input-wrap">
                            <input
                                id="max_volume_m3"
                                class="dl-input with-unit"
                                type="number"
                                step="0.001"
                                min="0.001"
                                name="max_volume_m3"
                                value="{{ old('max_volume_m3', $profile?->max_volume_m3) }}"
                                placeholder="Ex. 3,5"
                                required
                            >
                            <span class="dl-unit">m³</span>
                        </div>
                        <p class="dl-field-help">Volume total maximal pris en charge par livraison.</p>
                    </div>
                </div>
            </div>

            <div class="dl-section">
                <h3 class="dl-section-title">Véhicules disponibles</h3>
                <div class="dl-vehicles">
                    @foreach($vehicles as $value => $vehicle)
                        <label class="dl-vehicle">
                            <input
                                type="checkbox"
                                name="vehicle_types[]"
                                value="{{ $value }}"
                                @checked(in_array($value, $selectedVehicles, true))
                            >
                            <span class="dl-vehicle-card">
                                <span class="dl-vehicle-check"><i data-lucide="check"></i></span>
                                <span class="dl-vehicle-visual">
                                    <img src="{{ $vehicle['image'] }}" alt="{{ $vehicle['label'] }}">
                                </span>
                                <strong>{{ $vehicle['label'] }}</strong>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="dl-section">
                <h3 class="dl-section-title">Services et conditions</h3>
                <div class="dl-grid-2">
                    <div class="dl-field">
                        <label for="capacity_description">Services disponibles <span style="font-weight:500;color:#8291aa">(optionnel)</span></label>
                        <textarea
                            id="capacity_description"
                            class="dl-textarea"
                            name="capacity_description"
                            placeholder="Ex. Livraison express, manutention légère, suivi WhatsApp"
                        >{{ old('capacity_description', $profile?->capacity_description) }}</textarea>
                        <p class="dl-field-help">Décrivez les services additionnels que vous proposez.</p>
                    </div>

                    <div class="dl-field">
                        <label for="conditions">Conditions générales de livraison</label>
                        <textarea
                            id="conditions"
                            class="dl-textarea"
                            name="conditions"
                            placeholder="Ex. Livraison du lundi au samedi. Le client doit confirmer l'adresse et rester joignable."
                            required
                        >{{ old('conditions', $profile?->conditions) }}</textarea>
                        <p class="dl-field-help">Indiquez vos conditions de livraison et responsabilités.</p>
                    </div>
                </div>
            </div>

            <div class="dl-save-row">
                <button class="dl-btn orange" type="submit">
                    <i data-lucide="save" style="width:17px;height:17px"></i>
                    Enregistrer les paramètres de transport
                </button>
            </div>
        </form>
    </section>

    <section class="dl-panel">
        <div class="dl-tariff-head">
            <div>
                <h2>2. Grille tarifaire par commune</h2>
                <p>Définissez vos tarifs et délais de livraison selon la ville et la commune desservies.</p>
            </div>
            <button type="button" class="dl-btn outline-orange" data-open-rate-modal="create">
                <i data-lucide="circle-plus" style="width:16px;height:16px"></i>
                Ajouter un tarif
            </button>
        </div>

        <div class="dl-table-wrap">
            @if($zones->isNotEmpty())
                <table class="dl-table">
                    <thead>
                        <tr>
                            <th>Ville</th>
                            <th>Commune</th>
                            <th>Véhicule</th>
                            <th>Tarif</th>
                            <th>Délai</th>
                            <th>Limite poids</th>
                            <th>Statut</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($zones as $zone)
                            <tr>
                                <td>{{ $zone->city }}</td>
                                <td><strong>{{ $zone->commune }}</strong></td>
                                <td>{{ $vehicles[$zone->vehicle_code]['label'] ?? 'Tarif général' }}</td>
                                <td><strong>{{ number_format((float) $zone->delivery_price, 0, ',', ' ') }} FCFA</strong></td>
                                <td>{{ $zone->estimated_delay }}</td>
                                <td>{{ filled($zone->max_weight_kg) ? number_format((float) $zone->max_weight_kg, 0, ',', ' ').' kg' : 'Global' }}</td>
                                <td>
                                    <span class="dl-badge {{ $zone->is_active ? 'on' : 'off' }}">
                                        {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dl-row-actions">
                                        <button
                                            type="button"
                                            class="dl-edit-btn"
                                            data-open-rate-modal="edit"
                                            data-zone-id="{{ $zone->id }}"
                                            data-zone-city="{{ $zone->city }}"
                                            data-zone-commune="{{ $zone->commune }}"
                                            data-zone-commune-id="{{ $zone->commune_id }}"
                                            data-zone-vehicle="{{ $zone->vehicle_code }}"
                                            data-zone-price="{{ (float) $zone->delivery_price }}"
                                            data-zone-delay="{{ $zone->estimated_delay }}"
                                            data-zone-weight="{{ $zone->max_weight_kg }}"
                                            data-zone-volume="{{ $zone->max_volume_m3 }}"
                                            data-zone-active="{{ $zone->is_active ? '1' : '0' }}"
                                            data-update-url="{{ route('vendor.delivery.zones.update', $zone) }}"
                                        >
                                            <i data-lucide="pencil" style="width:14px;height:14px"></i>
                                            Modifier
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="dl-empty">
                    <span class="dl-empty-icon"><i data-lucide="map-pinned"></i></span>
                    <strong>Aucun tarif enregistré</strong>
                    <p>Ajoutez votre première ligne tarifaire pour commencer à facturer la livraison selon les communes.</p>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="dl-modal" id="rateModal" aria-hidden="true">
    <div class="dl-modal-backdrop" data-close-rate-modal></div>

    <div class="dl-modal-card" role="dialog" aria-modal="true" aria-labelledby="rateModalTitle">
        <div class="dl-modal-head">
            <div>
                <h3 id="rateModalTitle">Ajouter un tarif de livraison</h3>
                <p id="rateModalSubtitle">Une commune correspond à un tarif et un délai de livraison.</p>
            </div>
            <button type="button" class="dl-modal-close" data-close-rate-modal aria-label="Fermer">×</button>
        </div>

        <form id="rateForm" method="POST" action="{{ route('vendor.delivery.zones.store') }}">
            @csrf
            <input type="hidden" name="_method" id="rateMethod" value="POST">

            <div class="dl-modal-body">
                <div class="dl-grid-2">
                    <div class="dl-field">
                        <label for="rateCity">Ville</label>
                        <input id="rateCity" class="dl-input" name="city" value="{{ old('city', 'Abidjan') }}" placeholder="Ex. Abidjan" required>
                    </div>

                    <div class="dl-field">
                        <label for="rateCommune">Commune <span style="color:#ef4444">*</span></label>
                        <div class="dl-select-wrap">
                            <select id="rateCommune" class="dl-input dl-select" name="commune_id" required>
                                <option value="">Sélectionner une commune</option>
                                @foreach($communes as $commune)
                                    <option value="{{ $commune->id }}" @selected(old('commune_id') == $commune->id)>{{ $commune->name }}</option>
                                @endforeach
                            </select>
                            <i class="dl-select-icon" data-lucide="chevron-down"></i>
                        </div>
                    </div>

                    <div class="dl-field">
                        <label for="rateVehicle">Véhicule <span style="color:#ef4444">*</span></label>
                        <div class="dl-select-wrap">
                            <select id="rateVehicle" class="dl-input dl-select" name="vehicle_code" required>
                                <option value="">Sélectionner un véhicule</option>
                                @foreach($vehicles as $vehicleCode => $vehicle)
                                    <option value="{{ $vehicleCode }}" @selected(old('vehicle_code') === $vehicleCode)>{{ $vehicle['label'] }}</option>
                                @endforeach
                            </select>
                            <i class="dl-select-icon" data-lucide="chevron-down"></i>
                        </div>
                    </div>

                    <div class="dl-field">
                        <label for="ratePrice">Prix de livraison</label>
                        <div class="dl-input-wrap">
                            <input id="ratePrice" class="dl-input with-unit" type="number" min="0" step="1" name="delivery_price" value="{{ old('delivery_price') }}" placeholder="Ex. 3500" required>
                            <span class="dl-unit">FCFA</span>
                        </div>
                    </div>

                    <div class="dl-field">
                        <label for="rateDelay">Délai estimé</label>
                        <input id="rateDelay" class="dl-input" name="estimated_delay" value="{{ old('estimated_delay') }}" placeholder="Ex. 4 h, 24 h, 1-2 jours" required>
                    </div>
                </div>

                <details class="dl-advanced">
                    <summary>Limites particulières pour cette commune <span style="font-weight:500;color:#8191aa">(optionnel)</span></summary>
                    <div class="dl-grid-2" style="margin-top:14px">
                        <div class="dl-field">
                            <label for="rateWeight">Poids maximal spécifique</label>
                            <div class="dl-input-wrap">
                                <input id="rateWeight" class="dl-input with-unit" type="number" min="0.01" step="0.01" name="zone_max_weight_kg" value="{{ old('zone_max_weight_kg') }}" placeholder="Laisser vide = limite globale">
                                <span class="dl-unit">kg</span>
                            </div>
                        </div>

                        <div class="dl-field">
                            <label for="rateVolume">Volume maximal spécifique</label>
                            <div class="dl-input-wrap">
                                <input id="rateVolume" class="dl-input with-unit" type="number" min="0.001" step="0.001" name="zone_max_volume_m3" value="{{ old('zone_max_volume_m3') }}" placeholder="Laisser vide = limite globale">
                                <span class="dl-unit">m³</span>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="dl-modal-foot">
                <label class="dl-switch" id="activeField" hidden>
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="rateActive" name="is_active" value="1" checked>
                    Tarif actif au checkout
                </label>

                <div class="dl-modal-actions">
                    <button type="button" class="dl-btn light" data-close-rate-modal>Annuler</button>
                    <button type="submit" class="dl-btn orange" style="min-width:180px" id="rateSubmitLabel">Ajouter le tarif</button>
                </div>
            </div>
        </form>

        <div id="deleteZoneArea" style="display:none;padding:0 22px 20px">
            <form id="deleteZoneForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <button type="submit" class="dl-btn danger" onclick="return confirm('Retirer cette commune de votre grille tarifaire ?')">
                    <i data-lucide="trash-2" style="width:15px;height:15px"></i>
                    Supprimer ce tarif
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('rateModal');
    const form = document.getElementById('rateForm');
    const methodInput = document.getElementById('rateMethod');
    const title = document.getElementById('rateModalTitle');
    const subtitle = document.getElementById('rateModalSubtitle');
    const submitLabel = document.getElementById('rateSubmitLabel');
    const activeField = document.getElementById('activeField');
    const activeInput = document.getElementById('rateActive');
    const deleteArea = document.getElementById('deleteZoneArea');
    const deleteForm = document.getElementById('deleteZoneForm');

    const storeUrl = @json(route('vendor.delivery.zones.store'));
    const destroyTemplate = @json(route('vendor.delivery.zones.destroy', ['zone' => '__ZONE__']));

    const inputs = {
        city: document.getElementById('rateCity'),
        commune: document.getElementById('rateCommune'),
        vehicle: document.getElementById('rateVehicle'),
        price: document.getElementById('ratePrice'),
        delay: document.getElementById('rateDelay'),
        weight: document.getElementById('rateWeight'),
        volume: document.getElementById('rateVolume'),
    };


    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function createMode() {
        form.action = storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Ajouter un tarif de livraison';
        subtitle.textContent = 'Renseignez la commune, le véhicule, le prix facturé et le délai estimé.';
        submitLabel.textContent = 'Ajouter le tarif';
        inputs.city.value = 'Abidjan';
        inputs.commune.value = '';
        inputs.commune.classList.remove('is-invalid');
        inputs.vehicle.value = '';
        inputs.vehicle.classList.remove('is-invalid');
        inputs.price.value = '';
        inputs.delay.value = '';
        inputs.weight.value = '';
        inputs.volume.value = '';
        activeField.hidden = true;
        activeInput.checked = true;
        deleteArea.style.display = 'none';
        deleteForm.action = '';
        openModal();
    }

    function editMode(button) {
        form.action = button.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Modifier le tarif de livraison';
        subtitle.textContent = 'Modifiez les informations qui doivent être mises à jour.';
        submitLabel.textContent = 'Enregistrer les modifications';
        inputs.city.value = button.dataset.zoneCity || '';
        inputs.commune.value = button.dataset.zoneCommuneId || '';
        inputs.commune.classList.remove('is-invalid');
        inputs.vehicle.value = button.dataset.zoneVehicle || '';
        inputs.vehicle.classList.remove('is-invalid');
        inputs.price.value = button.dataset.zonePrice || '';
        inputs.delay.value = button.dataset.zoneDelay || '';
        inputs.weight.value = button.dataset.zoneWeight || '';
        inputs.volume.value = button.dataset.zoneVolume || '';
        activeField.hidden = false;
        activeInput.checked = button.dataset.zoneActive === '1';
        deleteArea.style.display = 'block';
        deleteForm.action = destroyTemplate.replace('__ZONE__', button.dataset.zoneId);
        openModal();
    }

    form.addEventListener('submit', (event) => {
        if (!inputs.commune.value) {
            event.preventDefault();
            inputs.commune.classList.add('is-invalid');
            inputs.commune.focus();
            return;
        }

        if (!inputs.vehicle.value) {
            event.preventDefault();
            inputs.vehicle.classList.add('is-invalid');
            inputs.vehicle.focus();
        }
    });

    inputs.commune.addEventListener('change', () => inputs.commune.classList.remove('is-invalid'));
    inputs.vehicle.addEventListener('change', () => inputs.vehicle.classList.remove('is-invalid'));

    document.querySelectorAll('[data-open-rate-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            button.dataset.openRateModal === 'edit' ? editMode(button) : createMode();
        });
    });

    document.querySelectorAll('[data-close-rate-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
});
</script>
@endsection
