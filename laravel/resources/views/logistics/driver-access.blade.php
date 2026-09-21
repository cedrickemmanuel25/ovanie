@extends('layouts.logistics')

@section('title', 'Accès livreurs')
@section('crumb', 'Accès livreurs')

@php
    $totalDrivers = $drivers->count();
    $activeAccess = $drivers->filter(fn ($driver) => $driver->hasPortalAccess())->count();
    $pendingAccess = max(0, $totalDrivers - $activeAccess);
    $onlineDrivers = $drivers->where('is_online', true)->count();

    $vehicleLabels = [
        'moto' => 'Moto',
        'tricycle' => 'Tricycle',
        'pickup' => 'Pickup',
        'camion_3t' => 'Camion 3 tonnes',
        'camion_10t' => 'Camion 10 tonnes',
    ];

    $driverPayload = $drivers->map(function ($driver) use ($vehicleLabels) {
        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'initials' => $driver->initials,
            'phone' => $driver->phone,
            'email' => $driver->email ?: '',
            'vehicle_code' => $driver->vehicle ?: '',
            'vehicle' => $vehicleLabels[$driver->vehicle] ?? ($driver->vehicle ?: 'Non renseigné'),
            'zone' => $driver->zone ?: 'Zone non renseignée',
            'rating' => number_format((float) ($driver->rating ?? 0), 1, ',', ' '),
            'is_online' => (bool) $driver->is_online,
            'is_active' => (bool) $driver->is_active,
            'portal_active' => $driver->hasPortalAccess(),
            'active_assignments' => (int) ($driver->active_assignments_count ?? 0),
            'last_login' => $driver->last_login_at
                ? $driver->last_login_at->format('d/m/Y à H:i')
                : 'Jamais connecté',
            'search' => mb_strtolower(implode(' ', [
                $driver->name,
                $driver->phone,
                $driver->email,
                $driver->vehicle,
                $driver->zone,
            ])),
        ];
    })->values();
@endphp

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    .driver-access-page {
        width: 100%;
        max-width: none;
        min-width: 0;
        margin: 0;
        padding: 30px;
        overflow-x: clip;
        box-sizing: border-box;
    }

    .driver-access-page,
    .driver-access-page * {
        box-sizing: border-box;
    }

    .driver-access-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 24px;
    }

    .driver-access-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        color: #15803d;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .driver-access-header h1 {
        margin: 0;
        color: #0f2740;
        font-size: clamp(28px, 3vw, 38px);
        font-weight: 800;
        letter-spacing: -.035em;
        line-height: 1.12;
    }

    .driver-access-header p {
        max-width: 760px;
        margin: 9px 0 0;
        color: #64748b;
        font-size: 15px;
        line-height: 1.65;
    }

    .driver-access-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .access-copy-link,
    .access-back-link {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 12px;
        padding: 0 15px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: .18s ease;
    }

    .access-copy-link {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
        cursor: pointer;
    }

    .access-copy-link:hover { background: #dcfce7; }

    .access-back-link {
        border: 1px solid #dfe7ef;
        background: #fff;
        color: #334155;
    }

    .access-back-link:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    .access-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }

    .access-stat {
        min-height: 118px;
        display: flex;
        align-items: center;
        gap: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(15, 39, 64, .045);
    }

    .access-stat-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: grid;
        place-items: center;
        border-radius: 15px;
    }

    .access-stat-icon svg { width: 23px; height: 23px; }
    .access-stat-icon.total { color: #1d4ed8; background: #eff6ff; }
    .access-stat-icon.active { color: #15803d; background: #ecfdf5; }
    .access-stat-icon.pending { color: #c2410c; background: #fff7ed; }
    .access-stat-icon.online { color: #7c3aed; background: #f5f3ff; }

    .access-stat-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .access-stat-value {
        margin-top: 5px;
        color: #0f2740;
        font-size: 30px;
        font-weight: 800;
        line-height: 1;
    }

    .access-panel {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 14px 38px rgba(15, 39, 64, .055);
    }

    .access-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 220px 220px auto;
        gap: 12px;
        align-items: end;
        padding: 18px;
        border-bottom: 1px solid #edf2f7;
        background: #fff;
    }

    .access-field label {
        display: block;
        margin-bottom: 7px;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .access-input-wrap { position: relative; }

    .access-input-wrap svg {
        position: absolute;
        top: 50%;
        left: 14px;
        width: 18px;
        height: 18px;
        color: #94a3b8;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .access-input,
    .access-select {
        width: 100%;
        height: 46px;
        border: 1px solid #dbe4ed;
        border-radius: 12px;
        background: #fff;
        color: #172033;
        font-size: 13px;
        outline: none;
        transition: .18s ease;
    }

    .access-input { padding: 0 14px 0 43px; }
    .access-select { padding: 0 38px 0 13px; }

    .access-input:focus,
    .access-select:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 4px rgba(22, 163, 74, .10);
    }

    .access-results {
        min-width: 120px;
        min-height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #f8fafc;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        padding: 0 14px;
    }

    .driver-list-head,
    .driver-list-row {
        width: 100%;
        min-width: 0;
        display: grid;
        grid-template-columns:
            minmax(220px, 1.25fr)
            minmax(155px, .90fr)
            minmax(165px, .90fr)
            minmax(145px, .75fr)
            minmax(250px, 1.20fr);
        align-items: center;
        column-gap: 14px;
    }

    .driver-list-head {
        min-height: 48px;
        padding: 0 20px;
        border-bottom: 1px solid #edf2f7;
        background: #f8fafc;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .driver-list-row {
        min-height: 98px;
        padding: 17px 20px;
        border-bottom: 1px solid #edf2f7;
        transition: background .15s ease;
    }

    .driver-list-row:last-child { border-bottom: 0; }
    .driver-list-row:hover { background: #fbfdff; }

    .driver-profile {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 13px;
    }

    .driver-avatar {
        position: relative;
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: linear-gradient(135deg, #e8f6f0, #d9efe7);
        color: #176950;
        font-size: 14px;
        font-weight: 900;
    }

    .driver-online-dot {
        position: absolute;
        right: -2px;
        bottom: -2px;
        width: 12px;
        height: 12px;
        border: 2px solid #fff;
        border-radius: 50%;
        background: #22c55e;
    }

    .driver-name {
        overflow: hidden;
        color: #10283e;
        font-size: 14px;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .driver-subline {
        overflow: hidden;
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .driver-meta-title {
        color: #172033;
        font-size: 13px;
        font-weight: 700;
    }

    .driver-meta-subtitle {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }

    .driver-status-line {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #172033;
        font-size: 13px;
        font-weight: 700;
    }

    .driver-status-dot {
        width: 8px;
        height: 8px;
        flex: 0 0 8px;
        border-radius: 50%;
        background: #cbd5e1;
    }

    .driver-status-dot.online { background: #22c55e; }

    .portal-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .portal-badge.active { background: #dcfce7; color: #166534; }
    .portal-badge.pending { background: #fff7ed; color: #c2410c; }

    .driver-access-control {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .driver-access-summary {
        min-width: 0;
    }

    .driver-configure {
        width: auto;
        min-width: 126px;
        min-height: 40px;
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 1px solid #d7e1ea;
        border-radius: 11px;
        background: #fff;
        color: #0f2740;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .driver-configure:hover {
        border-color: #16a34a;
        background: #f0fdf4;
        color: #166534;
    }

    .access-empty {
        padding: 68px 24px;
        text-align: center;
    }

    .access-empty-icon {
        width: 64px;
        height: 64px;
        display: grid;
        place-items: center;
        margin: 0 auto 14px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #64748b;
    }

    .access-empty h3 {
        margin: 0;
        color: #10283e;
        font-size: 18px;
        font-weight: 800;
    }

    .access-empty p {
        margin: 7px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .access-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(2, 18, 13, .58);
        padding: 20px;
        backdrop-filter: blur(4px);
    }

    .access-modal {
        width: min(620px, 100%);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, .65);
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 28px 80px rgba(2, 18, 13, .28);
    }

    .access-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px 18px;
        border-bottom: 1px solid #edf2f7;
    }

    .access-modal-profile {
        display: flex;
        align-items: center;
        gap: 13px;
    }

    .access-modal-profile h2 {
        margin: 0;
        color: #10283e;
        font-size: 20px;
        font-weight: 850;
    }

    .access-modal-profile p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 12px;
    }

    .access-modal-close {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 11px;
        background: #fff;
        color: #64748b;
        cursor: pointer;
    }

    .access-modal-body { padding: 22px 24px 24px; }

    .access-security-note {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-bottom: 20px;
        border: 1px solid #dbeafe;
        border-radius: 13px;
        background: #eff6ff;
        color: #1e40af;
        padding: 13px 14px;
        font-size: 12px;
        line-height: 1.55;
    }

    .access-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .access-form-grid .full { grid-column: 1 / -1; }

    .access-modal-label {
        display: block;
        margin-bottom: 7px;
        color: #475569;
        font-size: 11px;
        font-weight: 800;
    }

    .access-modal-input-wrap { position: relative; }

    .access-modal-input {
        width: 100%;
        height: 48px;
        border: 1px solid #dbe4ed;
        border-radius: 12px;
        background: #fff;
        color: #172033;
        font-size: 13px;
        padding: 0 44px 0 13px;
        outline: none;
        transition: .18s ease;
    }

    .access-modal-input:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 4px rgba(22, 163, 74, .10);
    }

    .access-password-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        display: grid;
        place-items: center;
        border: 0;
        background: transparent;
        color: #94a3b8;
        cursor: pointer;
        transform: translateY(-50%);
    }

    .access-modal-help {
        margin-top: 6px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.45;
    }

    .access-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid #edf2f7;
    }

    .access-cancel,
    .access-submit {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 12px;
        padding: 0 18px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .access-cancel {
        border: 1px solid #dbe4ed;
        background: #fff;
        color: #475569;
    }

    .access-submit {
        border: 0;
        background: #176950;
        color: #fff;
        box-shadow: 0 8px 22px rgba(23, 105, 80, .18);
    }

    .access-submit:hover { background: #125b44; }

    @media (min-width: 1241px) and (max-width: 1480px) {
        .driver-access-page { padding: 24px 20px; }
        .driver-list-head,
        .driver-list-row {
            grid-template-columns:
                minmax(205px, 1.20fr)
                minmax(145px, .85fr)
                minmax(150px, .85fr)
                minmax(135px, .72fr)
                minmax(230px, 1.15fr);
            column-gap: 11px;
        }
        .driver-list-head { padding: 0 16px; }
        .driver-list-row { padding: 16px; }
        .driver-configure { min-width: 112px; padding-left: 10px; padding-right: 10px; }
    }

    @media (max-width: 1240px) {
        .access-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .access-toolbar { grid-template-columns: minmax(240px, 1fr) 190px 190px; }
        .access-results { grid-column: 1 / -1; justify-content: flex-start; }
        .driver-list-head { display: none; }
        .driver-list-row {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            row-gap: 18px;
        }
        .driver-list-row > div::before {
            display: block;
            margin-bottom: 6px;
            color: #94a3b8;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .driver-list-row > div:nth-child(1)::before { content: 'Livreur'; }
        .driver-list-row > div:nth-child(2)::before { content: 'Contact'; }
        .driver-list-row > div:nth-child(3)::before { content: 'Véhicule et zone'; }
        .driver-list-row > div:nth-child(4)::before { content: 'Activité'; }
        .driver-list-row > div:nth-child(5)::before { content: 'Accès et action'; }
        .driver-access-control {
            align-items: flex-start;
            flex-direction: column;
        }
        .driver-configure {
            width: 100%;
        }
    }

    @media (max-width: 800px) {
        .driver-access-page { padding: 20px 16px; }
        .driver-access-header { flex-direction: column; }
        .driver-access-actions { width: 100%; }
        .access-copy-link, .access-back-link { flex: 1; }
        .access-stats { grid-template-columns: 1fr; }
        .access-toolbar { grid-template-columns: 1fr; }
        .access-results { grid-column: auto; }
        .driver-list-row { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 560px) {
        .driver-list-row { grid-template-columns: 1fr; }
        .access-form-grid { grid-template-columns: 1fr; }
        .access-form-grid .full { grid-column: auto; }
        .access-modal-footer { flex-direction: column-reverse; }
        .access-cancel, .access-submit { width: 100%; }
    }
</style>
@endpush

@section('content')
<div
    class="driver-access-page"
    x-data="driverAccessPage(@js($driverPayload), @js(url('/logistique/livreurs')))"
    @keydown.escape.window="closeModal()"
>
    <header class="driver-access-header">
        <div>
            <div class="driver-access-eyebrow">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                Sécurité des accès
            </div>
            <h1>Accès des livreurs</h1>
            <p>Activez et gérez les identifiants personnels utilisés par les livreurs pour consulter leurs missions, partager leur position et confirmer les livraisons.</p>
        </div>

        <div class="driver-access-actions">
            <button
                type="button"
                class="access-copy-link"
                @click="copyLoginUrl()"
            >
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                <span x-text="copied ? 'Lien copié' : 'Copier le lien de connexion'"></span>
            </button>
            <a href="{{ route('logistics.drivers') }}" class="access-back-link">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                Retour aux livreurs
            </a>
        </div>
    </header>

    @if($errors->any())
        <div class="flash-error" style="margin-bottom:18px;">
            <strong>La modification n’a pas été enregistrée.</strong>
            <div style="margin-top:5px;">{{ $errors->first() }}</div>
        </div>
    @endif

    <section class="access-stats" aria-label="Résumé des accès livreurs">
        <article class="access-stat">
            <div class="access-stat-icon total">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H2v-2a4 4 0 014-4h3m6 6H9v-2a4 4 0 018 0v2zM9 7a4 4 0 108 0 4 4 0 00-8 0zM2 7a3 3 0 106 0 3 3 0 00-6 0zm16 0a3 3 0 106 0 3 3 0 00-6 0z"/></svg>
            </div>
            <div>
                <div class="access-stat-label">Livreurs enregistrés</div>
                <div class="access-stat-value">{{ $totalDrivers }}</div>
            </div>
        </article>

        <article class="access-stat">
            <div class="access-stat-icon active">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
            </div>
            <div>
                <div class="access-stat-label">Accès actifs</div>
                <div class="access-stat-value">{{ $activeAccess }}</div>
            </div>
        </article>

        <article class="access-stat">
            <div class="access-stat-icon pending">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
            </div>
            <div>
                <div class="access-stat-label">À activer</div>
                <div class="access-stat-value">{{ $pendingAccess }}</div>
            </div>
        </article>

        <article class="access-stat">
            <div class="access-stat-icon online">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.55a11 11 0 0114.08 0M8.5 16a6 6 0 017 0M12 20h.01"/></svg>
            </div>
            <div>
                <div class="access-stat-label">En ligne maintenant</div>
                <div class="access-stat-value">{{ $onlineDrivers }}</div>
            </div>
        </article>
    </section>

    <section class="access-panel">
        <div class="access-toolbar">
            <div class="access-field">
                <label for="driver-search">Rechercher</label>
                <div class="access-input-wrap">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
                    <input id="driver-search" class="access-input" type="search" x-model.debounce.180ms="query" placeholder="Nom, téléphone, zone, véhicule…">
                </div>
            </div>

            <div class="access-field">
                <label for="access-filter">État de l’accès</label>
                <select id="access-filter" class="access-select" x-model="accessFilter">
                    <option value="all">Tous les accès</option>
                    <option value="active">Accès actifs</option>
                    <option value="pending">À activer</option>
                </select>
            </div>

            <div class="access-field">
                <label for="vehicle-filter">Type de véhicule</label>
                <select id="vehicle-filter" class="access-select" x-model="vehicleFilter">
                    <option value="all">Tous les véhicules</option>
                    <option value="moto">Moto</option>
                    <option value="tricycle">Tricycle</option>
                    <option value="pickup">Pickup</option>
                    <option value="camion_3t">Camion 3 tonnes</option>
                    <option value="camion_10t">Camion 10 tonnes</option>
                </select>
            </div>

            <div class="access-results">
                <span x-text="filteredDrivers.length"></span>&nbsp;livreur(s)
            </div>
        </div>

        <template x-if="filteredDrivers.length > 0">
            <div>
                <div class="driver-list-head" aria-hidden="true">
                    <div>Livreur</div>
                    <div>Contact</div>
                    <div>Véhicule et zone</div>
                    <div>Activité</div>
                    <div>Accès et action</div>
                </div>

                <template x-for="driver in filteredDrivers" :key="driver.id">
                    <article class="driver-list-row">
                        <div class="driver-profile">
                            <div class="driver-avatar">
                                <span x-text="driver.initials"></span>
                                <span class="driver-online-dot" x-show="driver.is_online"></span>
                            </div>
                            <div style="min-width:0;">
                                <div class="driver-name" x-text="driver.name"></div>
                                <div class="driver-subline">
                                    <span x-text="driver.active_assignments"></span> mission(s) active(s)
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="driver-meta-title" x-text="driver.phone || 'Non renseigné'"></div>
                            <div class="driver-meta-subtitle" x-text="driver.email || 'Aucun e-mail associé'"></div>
                        </div>

                        <div>
                            <div class="driver-meta-title" x-text="driver.vehicle"></div>
                            <div class="driver-meta-subtitle" x-text="driver.zone"></div>
                        </div>

                        <div>
                            <div class="driver-status-line">
                                <span class="driver-status-dot" :class="driver.is_online ? 'online' : ''"></span>
                                <span x-text="driver.is_online ? 'En ligne' : 'Hors ligne'"></span>
                            </div>
                            <div class="driver-meta-subtitle" x-text="driver.last_login"></div>
                        </div>

                        <div class="driver-access-control">
                            <div class="driver-access-summary">
                                <span class="portal-badge" :class="driver.portal_active ? 'active' : 'pending'">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <template x-if="driver.portal_active"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 21a9 9 0 100-18 9 9 0 000 18z"/></template>
                                        <template x-if="!driver.portal_active"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 3.9L2.6 17.2A2 2 0 004.3 20h15.4a2 2 0 001.7-2.8L13.7 3.9a2 2 0 00-3.4 0z"/></template>
                                    </svg>
                                    <span x-text="driver.portal_active ? 'Accès actif' : 'À activer'"></span>
                                </span>
                                <div class="driver-meta-subtitle" x-text="driver.portal_active ? 'Connexion par téléphone autorisée' : 'Aucun code personnel défini'"></div>
                            </div>

                            <button type="button" class="driver-configure" @click="openDriver(driver)">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 00-1.88-.34 1.7 1.7 0 00-1.04 1.55V20h-3v-.09a1.7 1.7 0 00-1.04-1.55 1.7 1.7 0 00-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 007 15a1.7 1.7 0 00-1.55-1.04H5v-3h.09A1.7 1.7 0 006.64 9.9a1.7 1.7 0 00-.34-1.88l-.06-.06 2.12-2.12.06.06A1.7 1.7 0 0010.3 6.24 1.7 1.7 0 0011.34 4.7V4h3v.09a1.7 1.7 0 001.04 1.55 1.7 1.7 0 001.88-.34l.06-.06 2.12 2.12-.06.06a1.7 1.7 0 00-.34 1.88 1.7 1.7 0 001.55 1.04H21v3h-.09A1.7 1.7 0 0019.4 15z"/></svg>
                                Configurer
                            </button>
                        </div>
                    </article>
                </template>
            </div>
        </template>

        <template x-if="filteredDrivers.length === 0">
            <div class="access-empty">
                <div class="access-empty-icon">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
                </div>
                <h3>Aucun livreur trouvé</h3>
                <p>Modifiez votre recherche ou les filtres sélectionnés.</p>
            </div>
        </template>
    </section>

    <div
        x-cloak
        x-show="modalOpen"
        x-transition.opacity
        class="access-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="access-modal-title"
        @click.self="closeModal()"
    >
        <section class="access-modal" x-show="modalOpen" x-transition.scale.origin.center>
            <header class="access-modal-header">
                <div class="access-modal-profile">
                    <div class="driver-avatar"><span x-text="selected?.initials"></span></div>
                    <div>
                        <h2 id="access-modal-title" x-text="selected?.portal_active ? 'Modifier l’accès livreur' : 'Activer l’accès livreur'"></h2>
                        <p><span x-text="selected?.name"></span> · <span x-text="selected?.phone"></span></p>
                    </div>
                </div>
                <button type="button" class="access-modal-close" @click="closeModal()" aria-label="Fermer">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </header>

            <form method="POST" :action="formAction" class="access-modal-body" @submit="submitting = true">
                @csrf
                @method('PUT')

                <div class="access-security-note">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex:0 0 19px;margin-top:1px;"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                    <div>Le livreur se connectera avec son numéro de téléphone et un code personnel à 6 chiffres. Ne partagez ce code qu’avec le livreur concerné.</div>
                </div>

                <div class="access-form-grid">
                    <div class="full">
                        <label class="access-modal-label" for="driver-access-email">E-mail professionnel <span style="font-weight:500;color:#94a3b8;">(facultatif)</span></label>
                        <input id="driver-access-email" class="access-modal-input" type="email" name="email" x-model="selected.email" placeholder="livreur@ovanie.com" autocomplete="email">
                        <div class="access-modal-help">Utilisé uniquement pour les communications opérationnelles et la récupération future du compte.</div>
                    </div>

                    <div>
                        <label class="access-modal-label" for="driver-access-pin">Code personnel à 6 chiffres</label>
                        <div class="access-modal-input-wrap">
                            <input
                                id="driver-access-pin"
                                class="access-modal-input"
                                :type="showPin ? 'text' : 'password'"
                                name="pin"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                minlength="6"
                                :required="!selected?.portal_active"
                                autocomplete="new-password"
                                placeholder="••••••"
                            >
                            <button type="button" class="access-password-toggle" @click="showPin = !showPin" aria-label="Afficher ou masquer le code">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </div>
                        <div class="access-modal-help" x-text="selected?.portal_active ? 'Laissez vide pour conserver le code actuel.' : 'Obligatoire pour activer l’accès.'"></div>
                    </div>

                    <div>
                        <label class="access-modal-label" for="driver-access-pin-confirmation">Confirmer le code</label>
                        <div class="access-modal-input-wrap">
                            <input
                                id="driver-access-pin-confirmation"
                                class="access-modal-input"
                                :type="showConfirmation ? 'text' : 'password'"
                                name="pin_confirmation"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                minlength="6"
                                :required="!selected?.portal_active"
                                autocomplete="new-password"
                                placeholder="••••••"
                            >
                            <button type="button" class="access-password-toggle" @click="showConfirmation = !showConfirmation" aria-label="Afficher ou masquer la confirmation">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <footer class="access-modal-footer">
                    <button type="button" class="access-cancel" @click="closeModal()">Annuler</button>
                    <button type="submit" class="access-submit" :disabled="submitting">
                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span x-text="submitting ? 'Enregistrement…' : (selected?.portal_active ? 'Enregistrer les modifications' : 'Activer l’accès')"></span>
                    </button>
                </footer>
            </form>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function driverAccessPage(drivers, baseUrl) {
    return {
        drivers,
        baseUrl,
        query: '',
        accessFilter: 'all',
        vehicleFilter: 'all',
        modalOpen: false,
        selected: null,
        showPin: false,
        showConfirmation: false,
        submitting: false,
        copied: false,

        get filteredDrivers() {
            const query = this.query.trim().toLowerCase();

            return this.drivers.filter((driver) => {
                const matchesSearch = !query || driver.search.includes(query);
                const matchesAccess = this.accessFilter === 'all'
                    || (this.accessFilter === 'active' && driver.portal_active)
                    || (this.accessFilter === 'pending' && !driver.portal_active);
                const matchesVehicle = this.vehicleFilter === 'all'
                    || driver.vehicle_code === this.vehicleFilter;

                return matchesSearch && matchesAccess && matchesVehicle;
            });
        },

        get formAction() {
            if (!this.selected) return '#';
            return `${this.baseUrl}/${this.selected.id}/acces`;
        },

        openDriver(driver) {
            this.selected = { ...driver };
            this.showPin = false;
            this.showConfirmation = false;
            this.submitting = false;
            this.modalOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.modalOpen = false;
            this.selected = null;
            this.submitting = false;
            document.body.style.overflow = '';
        },

        async copyLoginUrl() {
            const url = @js(url('/espace-livreur/connexion'));

            try {
                await navigator.clipboard.writeText(url);
                this.copied = true;
                window.setTimeout(() => this.copied = false, 1800);
            } catch (error) {
                window.prompt('Copiez le lien de connexion livreur :', url);
            }
        },
    };
}
</script>
@endpush
