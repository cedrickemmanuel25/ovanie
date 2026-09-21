@extends('layouts.vendor')

@section('title', 'Livraison & logistique | OVANIE')

@section('content')
<style>
    :root {
        --dl-navy: #0b2b61;
        --dl-blue: #0861ed;
        --dl-blue-dark: #064fc6;
        --dl-orange: #ff5a0a;
        --dl-orange-soft: #fff7f1;
        --dl-green: #11b763;
        --dl-text: #102f67;
        --dl-muted: #60749a;
        --dl-border: #dce5f1;
        --dl-soft: #f7f9fc;
        --dl-shadow: 0 7px 22px rgba(18, 50, 103, .06);
    }

    .dl-page,
    .dl-page * {
        box-sizing: border-box;
    }

    .dl-page {
        width: 100%;
        max-width: 1260px;
        margin: 0 auto;
        padding: 8px 6px 28px;
        color: var(--dl-text);
    }

    .dl-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .dl-breadcrumb a {
        color: var(--dl-blue);
        text-decoration: none;
    }

    .dl-breadcrumb span {
        color: var(--dl-text);
    }

    .dl-breadcrumb svg {
        width: 14px;
        height: 14px;
        color: #94a3bb;
    }

    .dl-header {
        margin-bottom: 20px;
    }

    .dl-header h1 {
        margin: 0;
        color: var(--dl-navy);
        font-size: clamp(30px, 3vw, 38px);
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: -.035em;
    }

    .dl-header p {
        margin: 8px 0 0;
        color: #4d6792;
        font-size: 13px;
        font-weight: 500;
    }

    .dl-alert {
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 700;
    }

    .dl-alert.success {
        border: 1px solid #a7efc5;
        background: #effcf4;
        color: #137442;
    }

    .dl-alert.info {
        border: 1px solid #bdd7ff;
        background: #eff6ff;
        color: #1e4b8f;
    }

    .dl-config-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        min-height: 80px;
        margin-bottom: 14px;
        padding: 15px 18px;
        border: 1px solid #ffc99f;
        border-radius: 7px;
        background: linear-gradient(90deg, #fff8f2 0%, #fffdfb 100%);
    }

    .dl-config-banner.is-ready {
        border-color: #b8ebcb;
        background: linear-gradient(90deg, #f1fcf5 0%, #fbfffc 100%);
    }

    .dl-config-main {
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 0;
    }

    .dl-config-icon {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border: 2px solid var(--dl-orange);
        border-radius: 50%;
        color: var(--dl-orange);
    }

    .dl-config-banner.is-ready .dl-config-icon {
        border-color: var(--dl-green);
        color: var(--dl-green);
    }

    .dl-config-icon svg {
        width: 22px;
        height: 22px;
    }

    .dl-config-copy strong {
        display: block;
        margin-bottom: 4px;
        color: var(--dl-orange);
        font-size: 15px;
        font-weight: 800;
    }

    .dl-config-banner.is-ready .dl-config-copy strong {
        color: #0a9c4a;
    }

    .dl-config-copy p {
        margin: 0;
        color: #506a95;
        font-size: 12px;
        line-height: 1.45;
        font-weight: 500;
    }

    .dl-config-link {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        flex: 0 0 auto;
        color: var(--dl-navy);
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .dl-config-link svg {
        width: 18px;
        height: 18px;
        color: var(--dl-orange);
    }

    .dl-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 14px;
    }

    .dl-overview-card {
        min-height: 142px;
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 18px;
        border: 1px solid var(--dl-border);
        border-radius: 7px;
        background: #fff;
        box-shadow: var(--dl-shadow);
    }

    .dl-overview-icon,
    .dl-capacity-icon {
        width: 60px;
        height: 60px;
        flex: 0 0 60px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #eef4ff;
        color: #0759df;
    }

    .dl-overview-icon svg,
    .dl-capacity-icon svg {
        width: 32px;
        height: 32px;
        stroke-width: 1.7;
    }

    .dl-overview-copy {
        min-width: 0;
    }

    .dl-overview-copy span {
        display: block;
        color: var(--dl-text);
        font-size: 12px;
        line-height: 1.35;
        font-weight: 800;
    }

    .dl-overview-copy strong {
        display: block;
        margin: 7px 0 9px;
        color: #7b8dae;
        font-size: 20px;
        line-height: 1;
        font-weight: 800;
    }

    .dl-overview-copy a {
        color: var(--dl-blue);
        text-decoration: none;
        font-size: 11px;
        font-weight: 700;
    }

    .dl-mode-badge {
        display: inline-flex !important;
        align-items: center;
        width: fit-content;
        margin-top: 8px;
        padding: 5px 10px;
        border-radius: 999px;
        background: #edf3ff;
        color: #0b4fc9 !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        white-space: nowrap;
    }

    .dl-section {
        margin-bottom: 10px;
        padding: 16px 12px 12px;
        border: 1px solid var(--dl-border);
        border-radius: 7px;
        background: #fff;
        box-shadow: var(--dl-shadow);
    }

    .dl-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin: 0 6px 14px;
    }

    .dl-section-title h2 {
        margin: 0;
        color: var(--dl-navy);
        font-size: 16px;
        line-height: 1.2;
        font-weight: 900;
    }

    .dl-section-title p {
        margin: 6px 0 0;
        color: #5e7398;
        font-size: 11px;
        line-height: 1.45;
    }

    .dl-edit-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--dl-blue);
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .dl-edit-link svg {
        width: 18px;
        height: 18px;
    }

    .dl-capacity-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .dl-capacity-card {
        min-height: 124px;
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 16px;
        border: 1px solid var(--dl-border);
        border-radius: 7px;
        background: #fff;
    }

    .dl-capacity-icon {
        width: 58px;
        height: 58px;
        flex-basis: 58px;
    }

    .dl-capacity-copy span {
        display: block;
        color: var(--dl-text);
        font-size: 11px;
        line-height: 1.35;
        font-weight: 800;
    }

    .dl-capacity-copy strong {
        display: block;
        margin: 8px 0 9px;
        color: #7c8eae;
        font-size: 20px;
        line-height: 1;
        font-weight: 800;
    }

    .dl-capacity-copy a {
        color: var(--dl-blue);
        text-decoration: none;
        font-size: 11px;
        font-weight: 700;
    }

    .dl-table-wrap {
        overflow: hidden;
        border: 1px solid var(--dl-border);
        border-radius: 6px;
    }

    .dl-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .dl-table th,
    .dl-table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid #e7edf5;
    }

    .dl-table th {
        background: #fbfcfe;
        color: var(--dl-navy);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .dl-table th .dl-sort {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .dl-table th svg {
        width: 12px;
        height: 12px;
        color: #6881a8;
    }

    .dl-table td {
        color: #344d78;
        font-size: 11px;
        font-weight: 500;
    }

    .dl-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .dl-table td strong {
        color: var(--dl-navy);
        font-weight: 800;
    }

    .dl-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
    }

    .dl-status.on {
        background: #e9fbf1;
        color: #0d9a4e;
    }

    .dl-status.off {
        background: #f0f3f8;
        color: #72819b;
    }

    .dl-empty {
        min-height: 220px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 24px 18px;
        text-align: center;
    }

    .dl-empty-icon {
        width: 74px;
        height: 74px;
        display: grid;
        place-items: center;
        margin-bottom: 12px;
        border-radius: 50%;
        background: #eef4ff;
        color: #60789f;
    }

    .dl-empty-icon svg {
        width: 42px;
        height: 42px;
        stroke-width: 1.6;
    }

    .dl-empty h3 {
        margin: 0;
        color: var(--dl-navy);
        font-size: 16px;
        font-weight: 900;
    }

    .dl-empty p {
        margin: 7px 0 16px;
        color: #60749a;
        font-size: 11px;
        line-height: 1.4;
    }

    .dl-orange-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 22px;
        border: 0;
        border-radius: 6px;
        background: linear-gradient(180deg, #ff6b0a 0%, #ff5200 100%);
        color: #fff;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
        box-shadow: 0 7px 16px rgba(255, 90, 10, .18);
    }

    .dl-orange-btn svg {
        width: 16px;
        height: 16px;
    }

    @media (max-width: 1100px) {
        .dl-overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 820px) {
        .dl-page {
            padding: 10px 0 24px;
        }

        .dl-config-banner {
            align-items: flex-start;
            flex-direction: column;
        }

        .dl-capacity-grid {
            grid-template-columns: 1fr;
        }

        .dl-table-wrap {
            overflow-x: auto;
            scrollbar-width: none;
        }

        .dl-table-wrap::-webkit-scrollbar {
            display: none;
        }

        .dl-table {
            min-width: 760px;
        }
    }

    @media (max-width: 580px) {
        .dl-overview-grid {
            grid-template-columns: 1fr;
        }

        .dl-section-head {
            flex-direction: column;
        }
    }
</style>

<div class="dl-page">
    @include('vendor.delivery.settings-style')
    <nav class="dl-breadcrumb" aria-label="Fil d'Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <span>Livraison</span>
    </nav>

    <header class="dl-header">
        <h1>Livraison & logistique</h1>
        <p>Gérez les informations logistiques de votre boutique {{ $shop->name }}.</p>
    </header>

    <section class="ls-card">
        <h2>Mode actuel</h2>
        <strong class="ls-mode">{{ $shop->usesSellerLogistics() ? 'Ma propre logistique' : 'OVANIE Logistics' }}</strong>
        <p>{{ $shop->usesSellerLogistics() ? 'Vous gérez vos zones, tarifs et délais de livraison.' : 'OVANIE organise la collecte et la livraison de vos nouvelles commandes.' }}</p>
        <div class="ls-actions">
            <a class="ls-button" href="{{ route('vendor.delivery.mode') }}">Changer de mode logistique</a>
            <a class="ls-button secondary" href="{{ route($shop->usesSellerLogistics() ? 'vendor.delivery.edit' : 'vendor.delivery.location') }}">Modifier mes informations logistiques</a>
        </div>
        <p class="ls-notice">Tout changement s’applique uniquement aux nouvelles commandes. Les commandes déjà créées ou en cours conservent leur mode logistique.</p>
    </section>

    @if(session('success'))
        <div class="dl-alert success">{{ session('success') }}</div>
    @endif

    @if(session('info'))
        <div class="dl-alert info">{{ session('info') }}</div>
    @endif

    @if($shop->usesSellerLogistics())
    @php
        $missingCount = count($validation['missing'] ?? []);
        $profile = $shop->sellerDeliveryProfile;
    @endphp

    <section class="dl-config-banner {{ $validation['complete'] ? 'is-ready' : '' }}">
        <div class="dl-config-main">
            <span class="dl-config-icon">
                <i data-lucide="{{ $validation['complete'] ? 'circle-check' : 'alert-circle' }}"></i>
            </span>

            <div class="dl-config-copy">
                <strong>{{ $validation['complete'] ? 'Configuration opérationnelle' : 'Configuration à compléter' }}</strong>
                <p>
                    @if($validation['complete'])
                        Votre logistique vendeur est prête à traiter les livraisons.
                    @else
                        {{ $missingCount }} élément{{ $missingCount > 1 ? 's' : '' }} à configurer pour activer votre logistique vendeur.
                    @endif
                </p>
            </div>
        </div>

        <a class="dl-config-link" href="{{ route('vendor.delivery.edit') }}">
            Accéder à la configuration
            <i data-lucide="chevron-right"></i>
        </a>
    </section>

    <section class="dl-overview-grid">
        <article class="dl-overview-card">
            <span class="dl-overview-icon"><i data-lucide="map-pin"></i></span>
            <div class="dl-overview-copy">
                <span>Nombre de communes<br>configurées</span>
                <strong>{{ $stats['active_communes'] > 0 ? $stats['active_communes'] : '—' }}</strong>
                <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
            </div>
        </article>

        <article class="dl-overview-card">
            <span class="dl-overview-icon"><i data-lucide="clock-3"></i></span>
            <div class="dl-overview-copy">
                <span>Délai habituel</span>
                <strong>{{ $profile?->default_delay ?: '—' }}</strong>
                <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
            </div>
        </article>

        <article class="dl-overview-card">
            <span class="dl-overview-icon"><i data-lucide="weight"></i></span>
            <div class="dl-overview-copy">
                <span>Poids maximum</span>
                <strong>{{ $profile?->max_weight_kg ? number_format((float) $profile->max_weight_kg, 0, ',', ' ').' kg' : '—' }}</strong>
                <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
            </div>
        </article>

        <article class="dl-overview-card">
            <span class="dl-overview-icon"><i data-lucide="truck"></i></span>
            <div class="dl-overview-copy">
                <span>Mode logistique</span>
                <span class="dl-mode-badge">Logistique vendeur</span>
            </div>
        </article>
    </section>

    <section class="dl-section">
        <div class="dl-section-head">
            <div class="dl-section-title">
                <h2>Capacité opérationnelle</h2>
                <p>Définissez vos capacités de traitement des commandes et vos limites d'expédition.</p>
            </div>
        </div>

        <div class="dl-capacity-grid">
            <article class="dl-capacity-card">
                <span class="dl-capacity-icon"><i data-lucide="clock-3"></i></span>
                <div class="dl-capacity-copy">
                    <span>Délai de préparation avant départ</span>
                    <strong>{{ $profile?->default_delay ?: '—' }}</strong>
                    <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
                </div>
            </article>

            <article class="dl-capacity-card">
                <span class="dl-capacity-icon"><i data-lucide="weight"></i></span>
                <div class="dl-capacity-copy">
                    <span>Poids maximum</span>
                    <strong>{{ $profile?->max_weight_kg ? number_format((float) $profile->max_weight_kg, 0, ',', ' ').' kg' : '—' }}</strong>
                    <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
                </div>
            </article>

            <article class="dl-capacity-card">
                <span class="dl-capacity-icon"><i data-lucide="box"></i></span>
                <div class="dl-capacity-copy">
                    <span>Volume maximum</span>
                    <strong>{{ $profile?->max_volume_m3 ? rtrim(rtrim(number_format((float) $profile->max_volume_m3, 4, '.', ''), '0'), '.').' m³' : '—' }}</strong>
                    <a href="{{ route('vendor.delivery.edit') }}">Configurer</a>
                </div>
            </article>
        </div>
    </section>

    <section class="dl-section">
        <div class="dl-section-head">
            <div class="dl-section-title">
                <h2>Grille tarifaire par commune</h2>
                <p>Définissez vos tarifs et délais de livraison par commune.</p>
            </div>

            <a class="dl-edit-link" href="{{ route('vendor.delivery.edit') }}">
                <i data-lucide="pen-line"></i>
                Modifier la grille
            </a>
        </div>

        <div class="dl-table-wrap">
            <table class="dl-table">
                <thead>
                    <tr>
                        <th><span class="dl-sort">Ville <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="dl-sort">Commune <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="dl-sort">Tarif <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="dl-sort">Délai <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="dl-sort">Limite poids <i data-lucide="chevrons-up-down"></i></span></th>
                        <th><span class="dl-sort">Statut <i data-lucide="chevrons-up-down"></i></span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                        <tr>
                            <td>{{ $zone->city ?: '—' }}</td>
                            <td><strong>{{ $zone->commune }}</strong></td>
                            <td><strong>{{ number_format((float) $zone->delivery_price, 0, ',', ' ') }} FCFA</strong></td>
                            <td>{{ $zone->estimated_delay ?: '—' }}</td>
                            <td>{{ $zone->max_weight_kg ? number_format((float) $zone->max_weight_kg, 0, ',', ' ').' kg' : 'Limite globale' }}</td>
                            <td>
                                <span class="dl-status {{ $zone->is_active ? 'on' : 'off' }}">
                                    {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:0;border-bottom:0;">
                                <div class="dl-empty">
                                    <span class="dl-empty-icon"><i data-lucide="archive"></i></span>
                                    <h3>Aucune grille tarifaire configurée</h3>
                                    <p>Vous n'avez pas encore défini de tarifs de livraison par commune.</p>
                                    <a class="dl-orange-btn" href="{{ route('vendor.delivery.edit') }}">
                                        <i data-lucide="settings"></i>
                                        Configurer ma grille tarifaire
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
@endsection
