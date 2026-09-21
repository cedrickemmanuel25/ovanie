@extends('layouts.vendor')

@section('title', 'Modifier la boutique | OVANIE')

@php
    $user = auth()->user();

    $categories = [
        'ciment-beton' => 'Ciment & béton',
        'fer-metaux' => 'Fer & métaux',
        'carrelage' => 'Carrelage',
        'peinture' => 'Peinture',
        'isolation' => 'Isolation',
        'electricite' => 'Électricité',
        'plomberie' => 'Plomberie',
        'bois' => 'Bois',
        'outillage' => 'Outillage',
        'solaire' => 'Solaire',
        'quincaillerie' => 'Quincaillerie',
    ];

    $deliveryZones = [
        'abidjan' => 'Abidjan',
        'grand_abidjan' => 'Grand Abidjan',
        'national' => 'Toute la Côte d’Ivoire',
        'afrique_ouest' => 'Afrique de l’Ouest',
    ];

    $processingTimes = [
        'lt24h' => 'Moins de 24 heures',
        '24_48h' => '24 à 48 heures ouvrées',
        '3_5j' => '3 à 5 jours ouvrés',
        '7j_plus' => '7 jours ou plus',
    ];

    $operators = [
        'orange' => 'Orange Money',
        'mtn' => 'MTN Mobile Money',
        'moov' => 'Moov Money',
        'wave' => 'Wave',
    ];

    $regions = collect([
        $shop->region,
        'Abidjan',
        'Agnéby-Tiassa',
        'Bélier',
        'Cavally',
        'Gbêkê',
        'Grands-Ponts',
        'Haut-Sassandra',
        'Indénié-Djuablin',
        'La Mé',
        'Lôh-Djiboua',
        'Nawa',
        'Poro',
        'San-Pédro',
        'Sud-Comoé',
        'Tonkpi',
    ])->filter()->unique()->values();

    $communesAbidjan = collect([
        $shop->commune,
        'Abobo',
        'Adjamé',
        'Anyama',
        'Attécoubé',
        'Bingerville',
        'Cocody',
        'Koumassi',
        'Marcory',
        'Plateau',
        'Port-Bouët',
        'Songon',
        'Treichville',
        'Yopougon',
    ])->filter()->unique()->values();

    $cities = collect([
        $shop->city,
        'Abidjan',
        'Bouaké',
        'San-Pédro',
        'Yamoussoukro',
        'Korhogo',
        'Daloa',
        'Man',
        'Abengourou',
        'Grand-Bassam',
    ])->filter()->unique()->values();

    $logisticsIsSeller = ($shop->logistics_type ?? 'ovanie') === 'seller';
    $logisticsLabel = $logisticsIsSeller ? 'Logistique vendeur' : 'OVANIE Logistics';

    $shopActive = ($shop->status ?? null) === \App\Models\Shop::STATUS_APPROVED
        && (bool) ($shop->is_active ?? false);

    $paymentConfigured = filled($shop->mm_operator)
        && filled($shop->mm_number)
        && filled($shop->mm_holder);

    $identityReady = filled($shop->identity_file)
        || (filled($shop->identity_file_front) && filled($shop->identity_file_back));

    $docRows = [
        [
            'label' => 'Pièce d’identité',
            'detail' => match($shop->identity_type ?? null) {
                'cni' => 'Carte nationale d’identité',
                'passport' => 'Passeport',
                'permis' => 'Permis de conduire',
                'residence' => 'Titre de séjour',
                default => 'Document d’identité',
            },
            'ready' => $identityReady,
            'icon' => 'badge-check',
        ],
        [
            'label' => 'RCCM',
            'detail' => 'Registre de commerce et crédit mobilier',
            'ready' => filled($shop->rccm_file),
            'icon' => 'file-badge-2',
        ],
        [
            'label' => 'Document fiscal',
            'detail' => 'NIF ou attestation fiscale',
            'ready' => filled($shop->tax_file),
            'icon' => 'file-text',
        ],
        [
            'label' => 'Selfie vendeur',
            'detail' => 'Photo de vérification du vendeur',
            'ready' => filled($shop->selfie),
            'icon' => 'user-round-check',
        ],
    ];

    $completionChecks = [
        filled($shop->name),
        filled($shop->description),
        filled($shop->main_category),
        filled($shop->region),
        filled($shop->city),
        filled($shop->commune),
        filled($shop->district),
        filled($shop->address),
        filled($shop->business_email),
        filled($shop->whatsapp),
        $identityReady,
        filled($shop->rccm_file),
        $paymentConfigured,
    ];

    $completionScore = (int) round(
        collect($completionChecks)->filter()->count() / max(count($completionChecks), 1) * 100
    );

    $missingCount = collect($completionChecks)->filter(fn ($value) => ! $value)->count();

    $kycApproved = (string) ($shop->kyc_status ?? '') === (defined(\App\Models\Shop::class . '::KYC_APPROVED')
        ? \App\Models\Shop::KYC_APPROVED
        : 'approved');

    $publicPreviewUrl = \Illuminate\Support\Facades\Route::has('catalog.index')
        ? route('catalog.index', ['shop' => $shop->slug])
        : url('/catalogue?shop=' . urlencode((string) $shop->slug));


    $formatPhone = static function ($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 10) {
            return trim(chunk_split($digits, 2, ' '));
        }

        return $value ?: '';
    };
@endphp

@section('styles')
<style>
    :root {
        --se-navy:#0b2f6b;
        --se-blue:#0f64ea;
        --se-orange:#ff5a0a;
        --se-green:#16a34a;
        --se-red:#ef4444;
        --se-text:#17366c;
        --se-muted:#7183a1;
        --se-line:#dfe7f1;
        --se-soft:#f8fbff;
    }

    .se-page {
        max-width:1240px;
        margin:0 auto;
        padding:26px 28px 46px;
        color:var(--se-text);
    }

    .se-breadcrumb {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:14px;
        color:#8191aa;
        font-size:11px;
        font-weight:800;
        flex-wrap:wrap;
    }

    .se-breadcrumb a {
        color:var(--se-blue);
        text-decoration:none;
    }

    .se-breadcrumb svg {
        width:13px;
        height:13px;
    }

    .se-head {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:18px;
        margin-bottom:22px;
    }

    .se-head h1 {
        margin:0;
        color:var(--se-navy);
        font-size:clamp(30px,3vw,44px);
        line-height:1.05;
        font-weight:900;
        letter-spacing:-.035em;
    }

    .se-head p {
        margin:8px 0 0;
        color:#657999;
        font-size:13px;
    }

    .se-head-actions {
        display:flex;
        gap:10px;
        flex:0 0 auto;
    }

    .se-btn {
        min-height:42px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:0 17px;
        border:1px solid #9eb3d2;
        border-radius:7px;
        background:#fff;
        color:var(--se-navy);
        font-size:11px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        transition:.18s ease;
    }

    .se-btn:hover {
        transform:translateY(-1px);
        border-color:var(--se-blue);
    }

    .se-btn svg {
        width:16px;
        height:16px;
    }

    .se-btn-orange {
        border-color:var(--se-orange);
        background:linear-gradient(90deg,#ff650d,#ff4f00);
        color:#fff;
        box-shadow:0 8px 18px rgba(255,90,10,.16);
    }

    .se-card {
        border:1px solid var(--se-line);
        border-radius:12px;
        background:#fff;
        box-shadow:0 10px 28px rgba(16,44,92,.035);
    }

    .se-stats {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
        margin-bottom:18px;
    }

    .se-stat {
        min-height:138px;
        padding:17px;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
    }

    .se-stat-main {
        display:grid;
        grid-template-columns:56px minmax(0,1fr);
        gap:12px;
        align-items:start;
    }

    .se-stat-icon,
    .se-ring {
        width:56px;
        height:56px;
        border-radius:50%;
        flex:0 0 56px;
    }

    .se-stat-icon {
        display:grid;
        place-items:center;
        color:var(--se-blue);
        background:#edf4ff;
    }

    .se-stat-icon.green {
        color:var(--se-green);
        background:#e8f8ed;
    }

    .se-stat-icon svg {
        width:25px;
        height:25px;
    }

    .se-ring {
        position:relative;
        display:grid;
        place-items:center;
        background:
            conic-gradient(
                var(--se-blue) calc(var(--score) * 1%),
                #e7eef7 0
            );
    }

    .se-ring::after {
        content:'';
        position:absolute;
        inset:7px;
        border-radius:50%;
        background:#fff;
    }

    .se-ring strong {
        position:relative;
        z-index:2;
        color:var(--se-blue);
        font-size:13px;
        font-weight:900;
    }

    .se-stat h3 {
        margin:0 0 6px;
        color:#244170;
        font-size:10px;
        font-weight:900;
    }

    .se-stat-value {
        margin-bottom:5px;
        color:var(--se-navy);
        font-size:12px;
        font-weight:900;
    }

    .se-stat-desc {
        margin:0;
        color:#71819f;
        font-size:9.4px;
        line-height:1.5;
    }

    .se-pill {
        display:inline-flex;
        align-items:center;
        width:max-content;
        min-height:25px;
        padding:0 9px;
        border-radius:999px;
        background:#e6f8eb;
        color:#188641;
        font-size:9px;
        font-weight:900;
    }

    .se-pill.blue {
        background:#ebf2ff;
        color:#1763df;
    }

    .se-pill.orange {
        background:#fff0e5;
        color:#e5650d;
    }

    .se-link {
        display:inline-flex;
        align-items:center;
        gap:6px;
        color:var(--se-blue);
        font-size:9.5px;
        font-weight:900;
        text-decoration:none;
    }

    .se-link svg {
        width:13px;
        height:13px;
    }

    .se-form {
        display:grid;
        gap:18px;
    }

    .se-grid-top,
    .se-grid-middle {
        display:grid;
        grid-template-columns:1fr 1.15fr;
        gap:18px;
    }

    .se-grid-middle {
        grid-template-columns:1fr 1fr;
    }

    .se-block {
        padding:18px 20px;
    }

    .se-block-title {
        display:flex;
        align-items:center;
        gap:8px;
        margin:0 0 18px;
        color:var(--se-navy);
        font-size:12px;
        font-weight:900;
    }

    .se-block-title svg {
        width:17px;
        height:17px;
        color:#2c5da7;
    }

    .se-block-title.orange svg {
        color:var(--se-orange);
    }

    .se-fields {
        display:grid;
        gap:13px;
    }

    .se-cols-2 {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px;
    }

    .se-field label {
        display:block;
        margin-bottom:6px;
        color:#466087;
        font-size:9px;
        font-weight:900;
    }

    .se-required {
        color:#e64949;
    }

    .se-input,
    .se-select,
    .se-textarea {
        width:100%;
        border:1px solid #cfdbea;
        border-radius:7px;
        background:#fff;
        color:#17366c;
        font:inherit;
        font-size:10.7px;
        outline:none;
        transition:.18s ease;
    }

    .se-input,
    .se-select {
        min-height:42px;
        padding:0 12px;
    }

    .se-select {
        appearance:none;
        padding-right:36px;
    }

    .se-textarea {
        min-height:82px;
        padding:11px 12px;
        line-height:1.55;
        resize:vertical;
    }

    .se-input:focus,
    .se-select:focus,
    .se-textarea:focus {
        border-color:var(--se-blue);
        box-shadow:0 0 0 3px rgba(15,100,234,.08);
    }

    .se-select-wrap {
        position:relative;
    }

    .se-select-wrap svg {
        position:absolute;
        right:12px;
        top:50%;
        width:15px;
        height:15px;
        color:#55709a;
        transform:translateY(-50%);
        pointer-events:none;
    }

    .se-help {
        margin:5px 0 0;
        color:#7788a4;
        font-size:8.5px;
        line-height:1.45;
    }

    .se-counter {
        margin-top:4px;
        color:#7b8ca7;
        font-size:8px;
        text-align:right;
    }

    .se-error {
        margin:5px 0 0;
        color:#dc2626;
        font-size:8.8px;
        font-weight:800;
    }

    .se-location-layout {
        display:grid;
        grid-template-columns:minmax(0,1fr) 230px;
        gap:16px;
        align-items:start;
    }

    .se-location-form {
        display:grid;
        gap:13px;
    }

    .se-map-panel {
        min-height:335px;
        position:relative;
        overflow:hidden;
        border:1px solid #dbe5f2;
        border-radius:10px;
        background:
            linear-gradient(35deg,transparent 42%,rgba(180,195,218,.28) 43% 45%,transparent 46%),
            linear-gradient(-35deg,transparent 46%,rgba(180,195,218,.22) 47% 49%,transparent 50%),
            linear-gradient(90deg,rgba(223,231,242,.55) 1px,transparent 1px),
            linear-gradient(rgba(223,231,242,.55) 1px,transparent 1px),
            #f6f9fd;
        background-size:70px 70px,82px 82px,34px 34px,34px 34px,auto;
    }

    .se-map-panel::after {
        content:'';
        position:absolute;
        inset:0;
        background:
            radial-gradient(circle at 30% 40%,rgba(255,255,255,.86),transparent 28%),
            radial-gradient(circle at 65% 62%,rgba(255,255,255,.68),transparent 34%);
    }

    .se-map-pin {
        position:absolute;
        z-index:2;
        left:50%;
        top:42%;
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border-radius:50% 50% 50% 8px;
        transform:translate(-50%,-50%) rotate(-45deg);
        background:#1b67ef;
        box-shadow:0 10px 24px rgba(27,103,239,.32);
    }

    .se-map-pin svg {
        width:20px;
        height:20px;
        color:#fff;
        transform:rotate(45deg);
    }

    .se-map-bottom {
        position:absolute;
        z-index:3;
        left:10px;
        right:10px;
        bottom:10px;
        display:grid;
        gap:7px;
    }

    .se-geo-btn {
        min-height:38px;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:7px;
        border:1px solid var(--se-blue);
        border-radius:7px;
        background:#fff;
        color:var(--se-blue);
        font-size:9.5px;
        font-weight:900;
        cursor:pointer;
    }

    .se-geo-btn svg {
        width:15px;
        height:15px;
    }

    .se-geo-status {
        margin:0;
        padding:7px 9px;
        border-radius:6px;
        background:rgba(255,255,255,.94);
        color:#5d7398;
        font-size:8px;
        line-height:1.4;
        text-align:center;
    }

    .se-logistics-switch {
        display:grid;
        grid-template-columns:1fr 1fr;
        overflow:hidden;
        border:1px solid #d8e2ee;
        border-radius:7px;
    }

    .se-logistics-choice {
        min-height:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:0 10px;
        background:#fff;
        color:#667995;
        font-size:9px;
        font-weight:900;
    }

    .se-logistics-choice.is-active {
        background:#eaf2ff;
        color:#155fd7;
    }

    .se-payment-state {
        min-height:62px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin-top:14px;
        padding:12px 14px;
        border:1px solid #ccebd6;
        border-radius:8px;
        background:linear-gradient(90deg,#f2fbf5,#fbfffc);
    }

    .se-payment-state-main {
        display:flex;
        align-items:center;
        gap:10px;
        color:#168742;
        font-size:9.5px;
        font-weight:900;
    }

    .se-payment-state-icon {
        width:28px;
        height:28px;
        display:grid;
        place-items:center;
        border-radius:50%;
        border:1.5px solid #16a34a;
        color:#16a34a;
    }

    .se-payment-state-icon svg {
        width:15px;
        height:15px;
    }

    .se-docs {
        padding:18px 20px;
    }

    .se-docs-subtitle {
        margin:-11px 0 14px 25px;
        color:#7789a5;
        font-size:8.5px;
    }

    .se-doc-list {
        overflow:hidden;
        border:1px solid #e2eaf3;
        border-radius:9px;
    }

    .se-doc-row {
        display:grid;
        grid-template-columns:44px minmax(0,1.4fr) 112px 96px 80px 24px;
        align-items:center;
        gap:10px;
        min-height:58px;
        padding:8px 10px;
        border-bottom:1px solid #e7edf5;
    }

    .se-doc-row:last-child {
        border-bottom:0;
    }

    .se-doc-icon {
        width:38px;
        height:38px;
        display:grid;
        place-items:center;
        border-radius:8px;
        background:#edf4ff;
        color:#1f64df;
    }

    .se-doc-icon.orange {
        background:#fff0e5;
        color:#ff650d;
    }

    .se-doc-icon.red {
        background:#fff0f0;
        color:#ef4444;
    }

    .se-doc-icon svg {
        width:19px;
        height:19px;
    }

    .se-doc-copy strong {
        display:block;
        color:#15366d;
        font-size:9.5px;
        font-weight:900;
    }

    .se-doc-copy span {
        display:block;
        margin-top:2px;
        color:#7889a5;
        font-size:8px;
    }

    .se-doc-status {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:max-content;
        min-height:24px;
        padding:0 8px;
        border-radius:5px;
        background:#e6f8eb;
        color:#178641;
        font-size:8px;
        font-weight:900;
    }

    .se-doc-status.pending {
        background:#fff0e5;
        color:#e6660c;
    }

    .se-doc-status.missing {
        background:#feeaea;
        color:#d83a3a;
    }

    .se-doc-date {
        color:#7183a1;
        font-size:8px;
        line-height:1.4;
    }

    .se-doc-date small {
        display:block;
        margin-bottom:2px;
        color:#9aa8bc;
        font-size:7px;
        text-transform:uppercase;
        font-weight:900;
    }

    .se-doc-action {
        color:var(--se-blue);
        font-size:8.5px;
        font-weight:900;
        text-decoration:none;
        white-space:nowrap;
    }

    .se-doc-more {
        width:24px;
        height:24px;
        display:grid;
        place-items:center;
        border:0;
        background:transparent;
        color:#61769a;
    }

    .se-doc-more svg {
        width:14px;
        height:14px;
    }

    .se-form-actions {
        display:flex;
        justify-content:flex-end;
        gap:10px;
        padding-top:2px;
    }

    .se-message {
        margin-bottom:14px;
        padding:12px 14px;
        border-radius:8px;
        font-size:10.5px;
        font-weight:700;
    }

    .se-message.error {
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b42318;
    }

    .se-message.success {
        border:1px solid #bde8cc;
        background:#edf9f1;
        color:#136b35;
    }

    @media(max-width:1120px) {
        .se-stats {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .se-grid-top,
        .se-grid-middle {
            grid-template-columns:1fr;
        }

        .se-doc-row {
            grid-template-columns:44px minmax(0,1fr) 105px 75px 24px;
        }

        .se-doc-date {
            display:none;
        }
    }

    @media(max-width:760px) {
        .se-page {
            padding:18px 14px 30px;
        }

        .se-head {
            flex-direction:column;
        }

        .se-head-actions {
            width:100%;
        }

        .se-head-actions .se-btn {
            flex:1;
        }

        .se-stats {
            grid-template-columns:1fr;
        }

        .se-location-layout,
        .se-cols-2 {
            grid-template-columns:1fr;
        }

        .se-map-panel {
            min-height:250px;
        }

        .se-doc-row {
            grid-template-columns:38px minmax(0,1fr) auto;
        }

        .se-doc-date,
        .se-doc-action,
        .se-doc-more {
            display:none;
        }

        .se-form-actions {
            flex-direction:column-reverse;
        }

        .se-form-actions .se-btn {
            width:100%;
        }
    }
</style>
@endsection

@section('content')
<div class="se-page">
    <nav class="se-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.shop.profile') }}">Boutique</a>
        <i data-lucide="chevron-right"></i>
        <span>Modifier la boutique</span>
    </nav>

    <div class="se-head">
        <div>
            <h1>Modifier la boutique</h1>
            <p>Mettez à jour les informations de votre boutique, sa localisation, sa logistique et son compte de versement.</p>
        </div>

        <div class="se-head-actions">
            <a href="{{ $publicPreviewUrl }}" class="se-btn" target="_blank">
                <i data-lucide="eye"></i>
                Aperçu public
            </a>

            <button class="se-btn se-btn-orange" type="submit" form="shopEditForm">
                <i data-lucide="save"></i>
                Enregistrer les modifications
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="se-message success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="se-message error">
            <strong>Le formulaire contient des erreurs.</strong>
            <div style="margin-top:5px;">{{ $errors->first() }}</div>
        </div>
    @endif

    <section class="se-stats">
        <article class="se-card se-stat">
            <div class="se-stat-main">
                <div class="se-ring" style="--score:{{ $completionScore }}">
                    <strong>{{ $completionScore }}%</strong>
                </div>

                <div>
                    <h3>Complétude du profil</h3>
                    <div class="se-stat-value">
                        {{ $completionScore >= 90 ? 'Excellent' : ($completionScore >= 75 ? 'Presque complet' : 'À compléter') }}
                    </div>
                    <p class="se-stat-desc">
                        {{ $missingCount === 0 ? 'Votre profil est complet.' : $missingCount . ' élément(s) restent à compléter.' }}
                    </p>
                </div>
            </div>

            <a class="se-link" href="{{ route('vendor.shop-status') }}">
                Voir les éléments manquants
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="se-card se-stat">
            <div class="se-stat-main">
                <span class="se-stat-icon"><i data-lucide="store"></i></span>

                <div>
                    <h3>Statut boutique</h3>
                    <span class="se-pill {{ $shopActive ? '' : 'orange' }}">
                        {{ $shopActive ? 'Active' : 'Inactive' }}
                    </span>
                    <p class="se-stat-desc" style="margin-top:8px;">
                        {{ $shopActive ? 'Votre boutique est visible et opérationnelle.' : 'La boutique est actuellement inactive.' }}
                    </p>
                </div>
            </div>

            <a class="se-link" href="{{ route('vendor.shop-status') }}">
                Voir le statut
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="se-card se-stat">
            <div class="se-stat-main">
                <span class="se-stat-icon"><i data-lucide="truck"></i></span>

                <div>
                    <h3>Mode logistique</h3>
                    <span class="se-pill blue">{{ $logisticsLabel }}</span>
                    <p class="se-stat-desc" style="margin-top:8px;">
                        {{ $logisticsIsSeller ? 'Vous gérez la livraison de vos commandes.' : 'OVANIE organise vos livraisons.' }}
                    </p>
                </div>
            </div>

            <a class="se-link" href="{{ route('vendor.delivery.index') }}">
                Voir la logistique
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="se-card se-stat">
            <div class="se-stat-main">
                <span class="se-stat-icon green"><i data-lucide="wallet-cards"></i></span>

                <div>
                    <h3>Paiement vendeur</h3>
                    <span class="se-pill {{ $paymentConfigured ? '' : 'orange' }}">
                        {{ $paymentConfigured ? 'Configuré' : 'À compléter' }}
                    </span>
                    <p class="se-stat-desc" style="margin-top:8px;">
                        {{ $paymentConfigured ? 'Votre compte de versement est actif.' : 'Ajoutez vos informations de paiement.' }}
                    </p>
                </div>
            </div>

            <a class="se-link" href="{{ route('vendor.payment-method') }}">
                Voir les détails
                <i data-lucide="arrow-right"></i>
            </a>
        </article>
    </section>

    <form
        id="shopEditForm"
        class="se-form"
        action="{{ route('vendor.shop.update') }}"
        method="POST"
        enctype="multipart/form-data"
    >
        @csrf
        @method('PUT')

        <input
            type="hidden"
            name="logistics_type"
            value="{{ old('logistics_type', $shop->logistics_type ?: 'ovanie') }}"
        >

        <input
            type="hidden"
            name="direct_payment"
            value="{{ old('direct_payment', $shop->direct_payment ? 1 : 0) }}"
        >

        <input
            id="shopLatitude"
            type="hidden"
            name="latitude"
            value="{{ old('latitude', $shop->latitude) }}"
        >

        <input
            id="shopLongitude"
            type="hidden"
            name="longitude"
            value="{{ old('longitude', $shop->longitude) }}"
        >

        <div class="se-grid-top">
            <section class="se-card se-block">
                <h2 class="se-block-title">
                    <i data-lucide="info"></i>
                    Informations générales
                </h2>

                <div class="se-fields">
                    <div class="se-field">
                        <label for="shopName">Nom de la boutique <span class="se-required">*</span></label>
                        <input
                            id="shopName"
                            class="se-input"
                            type="text"
                            name="name"
                            value="{{ old('name', $shop->name) }}"
                            maxlength="255"
                            required
                        >
                        @error('name')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label for="mainCategory">Catégorie principale <span class="se-required">*</span></label>
                        <div class="se-select-wrap">
                            <select id="mainCategory" class="se-select" name="main_category" required>
                                @foreach($categories as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('main_category', $shop->main_category) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        @error('main_category')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label>Type de vendeur</label>
                        <div class="se-select-wrap">
                            <select class="se-select" disabled>
                                <option>
                                    {{ ucfirst((string) ($shop->seller_type ?: 'professionnel')) }}
                                </option>
                            </select>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <p class="se-help">Le type de vendeur est défini lors de l’ouverture de la boutique.</p>
                    </div>

                    <div class="se-field">
                        <label for="shopDescription">Description de la boutique <span class="se-required">*</span></label>
                        <textarea
                            id="shopDescription"
                            class="se-textarea"
                            name="description"
                            maxlength="700"
                            required
                        >{{ old('description', $shop->description) }}</textarea>
                        <div class="se-counter"><span id="descriptionCount">0</span> / 700</div>
                        @error('description')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-cols-2">
                        <div class="se-field">
                            <label for="businessEmail">Email professionnel</label>
                            <input
                                id="businessEmail"
                                class="se-input"
                                type="email"
                                name="business_email"
                                value="{{ old('business_email', $shop->business_email) }}"
                            >
                            @error('business_email')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="shopWhatsapp">WhatsApp professionnel</label>
                            <input
                                id="shopWhatsapp"
                                class="se-input"
                                type="tel"
                                name="whatsapp"
                                value="{{ old('whatsapp', $formatPhone($shop->whatsapp ?: $user?->phone)) }}"
                            >
                            @error('whatsapp')<p class="se-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="se-card se-block">
                <h2 class="se-block-title">
                    <i data-lucide="map-pin"></i>
                    Coordonnées et localisation
                </h2>

                <div class="se-location-layout">
                    <div class="se-location-form">
                        <div class="se-field">
                            <label for="shopRegion">Région <span class="se-required">*</span></label>
                            <div class="se-select-wrap">
                                <select id="shopRegion" class="se-select" name="region" required>
                                    @foreach($regions as $region)
                                        <option
                                            value="{{ $region }}"
                                            @selected(old('region', $shop->region) === $region)
                                        >
                                            {{ $region }}
                                        </option>
                                    @endforeach
                                </select>
                                <i data-lucide="chevron-down"></i>
                            </div>
                            @error('region')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="city">Ville <span class="se-required">*</span></label>
                            <div class="se-select-wrap">
                                <select id="city" class="se-select" name="city" required>
                                    @foreach($cities as $city)
                                        <option
                                            value="{{ $city }}"
                                            @selected(old('city', $shop->city) === $city)
                                        >
                                            {{ $city }}
                                        </option>
                                    @endforeach
                                </select>
                                <i data-lucide="chevron-down"></i>
                            </div>
                            @error('city')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="commune">Commune <span class="se-required">*</span></label>
                            <div class="se-select-wrap">
                                <select id="commune" class="se-select" name="commune" required>
                                    @foreach($communesAbidjan as $commune)
                                        <option
                                            value="{{ $commune }}"
                                            @selected(old('commune', $shop->commune) === $commune)
                                        >
                                            {{ $commune }}
                                        </option>
                                    @endforeach
                                </select>
                                <i data-lucide="chevron-down"></i>
                            </div>
                            @error('commune')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="district">Quartier <span class="se-required">*</span></label>
                            <input
                                id="district"
                                class="se-input"
                                type="text"
                                name="district"
                                value="{{ old('district', $shop->district) }}"
                                required
                            >
                            @error('district')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="addressInput">Adresse du point d’enlèvement <span class="se-required">*</span></label>
                            <textarea
                                id="addressInput"
                                class="se-textarea"
                                name="address"
                                rows="3"
                                maxlength="255"
                                required
                            >{{ old('address', $shop->address) }}</textarea>
                            <p class="se-help">Indiquez l’adresse utile à l’équipe logistique. La position GPS reste la référence exacte sur la carte.</p>
                            @error('address')<p class="se-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="se-field">
                            <label for="landmarkInput">Point de repère <span style="font-weight:500;color:#7183a1;">(facultatif)</span></label>
                            <input
                                id="landmarkInput"
                                class="se-input"
                                type="text"
                                name="landmark"
                                value="{{ old('landmark', $shop->landmark) }}"
                                maxlength="255"
                                placeholder="Ex. : en face de la pharmacie, près du marché"
                            >
                            <p class="se-help">Laissez vide lorsqu’aucun repère fiable n’est disponible.</p>
                            @error('landmark')<p class="se-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="se-map-panel" data-ovanie-location-panel>
                        <span class="se-map-pin">
                            <i data-lucide="map-pin"></i>
                        </span>

                        <div class="se-map-bottom">
                            <button class="se-geo-btn" type="button" data-shop-use-location>
                                <i data-lucide="locate-fixed"></i>
                                Mettre à jour la position
                            </button>

                            <p class="se-geo-status" data-shop-geo-status>
                                {{ filled(old('latitude', $shop->latitude)) && filled(old('longitude', $shop->longitude))
                                    ? 'Position GPS enregistrée.'
                                    : 'Position GPS à enregistrer.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="se-grid-middle">
            <section class="se-card se-block">
                <h2 class="se-block-title">
                    <i data-lucide="truck"></i>
                    Livraison & logistique
                </h2>

                <div class="se-fields">
                    <div class="se-field">
                        <label>Mode logistique</label>

                        <div class="se-logistics-switch">
                            <span class="se-logistics-choice {{ $logisticsIsSeller ? 'is-active' : '' }}">
                                Ma propre logistique
                            </span>

                            <span class="se-logistics-choice {{ ! $logisticsIsSeller ? 'is-active' : '' }}">
                                OVANIE Logistics
                            </span>
                        </div>

                        <p class="se-help">
                            Tout changement s’applique uniquement aux nouvelles commandes.
                        </p>
                        <a class="se-link" href="{{ route('vendor.delivery.mode') }}">Changer de mode logistique</a>
                        <a class="se-link" href="{{ route($logisticsIsSeller ? 'vendor.delivery.edit' : 'vendor.delivery.location') }}">Modifier mes informations logistiques</a>
                    </div>

                    <div class="se-field">
                        <label for="deliveryZone">Zone de couverture <span class="se-required">*</span></label>
                        <div class="se-select-wrap">
                            <select id="deliveryZone" class="se-select" name="delivery_zone" required>
                                @foreach($deliveryZones as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('delivery_zone', $shop->delivery_zone) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        @error('delivery_zone')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label for="processingTime">Délai de traitement <span class="se-required">*</span></label>
                        <div class="se-select-wrap">
                            <select id="processingTime" class="se-select" name="processing_time" required>
                                @foreach($processingTimes as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('processing_time', $shop->processing_time) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        @error('processing_time')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label>Informations de livraison</label>
                        <textarea
                            class="se-textarea"
                            rows="4"
                            readonly
                        >{{ $logisticsIsSeller
                            ? 'Les zones, communes, tarifs et délais détaillés se configurent dans l’espace Livraison.'
                            : 'OVANIE organise la livraison à partir de la position enregistrée de votre boutique.' }}</textarea>
                    </div>

                    @if($logisticsIsSeller)
                        <a href="{{ route('vendor.delivery.index') }}" class="se-link">
                            Gérer mes paramètres de livraison
                            <i data-lucide="arrow-right"></i>
                        </a>
                    @endif
                </div>
            </section>

            <section class="se-card se-block">
                <h2 class="se-block-title orange">
                    <i data-lucide="wallet-cards"></i>
                    Paiement vendeur
                </h2>

                <div class="se-fields">
                    <div class="se-field">
                        <label for="mmOperator">Opérateur <span class="se-required">*</span></label>
                        <div class="se-select-wrap">
                            <select id="mmOperator" class="se-select" name="mm_operator" required>
                                @foreach($operators as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('mm_operator', $shop->mm_operator) === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        @error('mm_operator')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label for="mmNumber">Numéro de paiement <span class="se-required">*</span></label>
                        <input
                            id="mmNumber"
                            class="se-input"
                            type="tel"
                            name="mm_number"
                            value="{{ old('mm_number', $formatPhone($shop->mm_number)) }}"
                            required
                        >
                        <p class="se-help">Numéro enregistré auprès de l’opérateur.</p>
                        @error('mm_number')<p class="se-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="se-field">
                        <label for="mmHolder">Titulaire du compte <span class="se-required">*</span></label>
                        <input
                            id="mmHolder"
                            class="se-input"
                            type="text"
                            name="mm_holder"
                            value="{{ old('mm_holder', $shop->mm_holder) }}"
                            required
                        >
                        <p class="se-help">Le nom doit correspondre au titulaire enregistré chez l’opérateur.</p>
                        @error('mm_holder')<p class="se-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="se-payment-state">
                    <div class="se-payment-state-main">
                        <span class="se-payment-state-icon">
                            <i data-lucide="{{ $paymentConfigured ? 'check' : 'alert-circle' }}"></i>
                        </span>

                        <span>
                            {{ $paymentConfigured ? 'Compte actif et configuré' : 'Configuration du compte à compléter' }}
                        </span>
                    </div>

                    <span class="se-pill {{ $paymentConfigured ? '' : 'orange' }}">
                        {{ $paymentConfigured ? 'Actif' : 'À compléter' }}
                    </span>
                </div>
            </section>
        </div>

        <section class="se-card se-docs">
            <h2 class="se-block-title">
                <i data-lucide="files"></i>
                Documents de la boutique
            </h2>

            <p class="se-docs-subtitle">Les documents ci-dessous sont nécessaires pour la vérification de votre boutique.</p>

            <div class="se-doc-list">
                @foreach($docRows as $index => $document)
                    @php
                        $statusText = ! $document['ready']
                            ? 'À compléter'
                            : ($kycApproved ? 'Vérifié' : 'En vérification');

                        $statusClass = ! $document['ready']
                            ? 'missing'
                            : ($kycApproved ? '' : 'pending');

                        $iconClass = ! $document['ready']
                            ? 'red'
                            : ($kycApproved ? '' : 'orange');
                    @endphp

                    <div class="se-doc-row">
                        <span class="se-doc-icon {{ $iconClass }}">
                            <i data-lucide="{{ $document['icon'] }}"></i>
                        </span>

                        <div class="se-doc-copy">
                            <strong>{{ $document['label'] }}</strong>
                            <span>{{ $document['detail'] }}</span>
                        </div>

                        <span class="se-doc-status {{ $statusClass }}">{{ $statusText }}</span>

                        <div class="se-doc-date">
                            <small>{{ $document['ready'] ? 'Ajouté' : 'Document' }}</small>
                            {{ $document['ready'] ? optional($shop->updated_at)->format('d/m/Y') : '—' }}
                        </div>

                        <a href="{{ route('vendor.shop.documents') }}" class="se-doc-action">
                            {{ $document['ready'] ? 'Remplacer' : 'Téléverser' }}
                        </a>

                        <a href="{{ route('vendor.shop.documents') }}" class="se-doc-more" aria-label="Gérer le document">
                            <i data-lucide="ellipsis-vertical"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="se-form-actions">
            <a href="{{ route('vendor.shop.profile') }}" class="se-btn">Annuler</a>

            <button class="se-btn se-btn-orange" type="submit">
                <i data-lucide="save"></i>
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('shopEditForm');

    if (!form) {
        return;
    }

    const description = document.getElementById('shopDescription');
    const descriptionCount = document.getElementById('descriptionCount');

    const latitudeInput = document.getElementById('shopLatitude');
    const longitudeInput = document.getElementById('shopLongitude');

    const addressInput = document.getElementById('addressInput');

    const useLocationButton = form.querySelector('[data-shop-use-location]');
    const geoStatus = form.querySelector('[data-shop-geo-status]');

    const cityField = document.getElementById('city');
    const communeField = document.getElementById('commune');
    const districtField = document.getElementById('district');

    const whatsappInput = document.getElementById('shopWhatsapp');
    const mmNumberInput = document.getElementById('mmNumber');

    const updateDescriptionCount = () => {
        if (description && descriptionCount) {
            descriptionCount.textContent = description.value.length;
        }
    };

    const formatPhone = (value) => {
        let digits = String(value || '').replace(/\D+/g, '');

        if (digits.startsWith('225') && digits.length >= 13) {
            digits = digits.slice(-10);
        }

        digits = digits.slice(0, 10);

        return digits.match(/.{1,2}/g)?.join(' ') || '';
    };

    const setGeoStatus = (message, error = false) => {
        if (!geoStatus) {
            return;
        }

        geoStatus.textContent = message;
        geoStatus.style.color = error ? '#dc2626' : '#5d7398';
    };

    const ensureOption = (select, value) => {
        if (!select || !value) {
            return;
        }

        const exists = Array.from(select.options).some((option) => option.value === value);

        if (!exists) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        }

        select.value = value;
    };

    const reverseGeocode = async (lat, lng) => {
        const response = await fetch(
            `/geo/reverse?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
            {
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        if (!response.ok) {
            throw new Error('Adresse introuvable');
        }

        const payload = await response.json();

        return payload.result || {};
    };

    const fillAddress = (result) => {
        const address = result.address || result || {};

        const city = address.city
            || address.town
            || address.village
            || address.state
            || '';

        const commune = address.city_district
            || address.municipality
            || address.suburb
            || address.county
            || city;

        const district = address.neighbourhood
            || address.quarter
            || address.suburb
            || address.city_district
            || '';

        const readable = [
            address.road,
            address.house_number,
            address.neighbourhood || address.quarter,
            address.suburb,
        ].filter(Boolean).join(', ');

        ensureOption(cityField, city);
        ensureOption(communeField, commune);

        if (districtField && district) {
            districtField.value = district;
        }

        if (addressInput && readable) {
            addressInput.value = readable;
        }
    };

    const capturePosition = () => {
        if (!navigator.geolocation) {
            setGeoStatus('Votre navigateur ne supporte pas la géolocalisation.', true);
            return;
        }

        const originalHtml = useLocationButton?.innerHTML || 'Mettre à jour la position';

        if (useLocationButton) {
            useLocationButton.disabled = true;
            useLocationButton.textContent = 'Recherche GPS…';
        }

        setGeoStatus('Autorisez la localisation pour enregistrer la position exacte.');

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = Number(position.coords.latitude).toFixed(7);
                const lng = Number(position.coords.longitude).toFixed(7);

                if (latitudeInput) {
                    latitudeInput.value = lat;
                }

                if (longitudeInput) {
                    longitudeInput.value = lng;
                }

                try {
                    const data = await reverseGeocode(lat, lng);
                    fillAddress(data);
                    setGeoStatus('Position GPS enregistrée et adresse actualisée.');
                } catch (error) {
                    setGeoStatus('Position GPS enregistrée. Vérifiez l’adresse avant de sauvegarder.');
                } finally {
                    if (useLocationButton) {
                        useLocationButton.disabled = false;
                        useLocationButton.innerHTML = originalHtml;

                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    }
                }
            },
            (error) => {
                let message = 'Impossible de récupérer la position. Autorisez la localisation puis réessayez.';

                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Localisation refusée. Autorisez l’accès à la position puis réessayez.';
                }

                if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Position indisponible. Vérifiez votre GPS ou votre connexion.';
                }

                if (error.code === error.TIMEOUT) {
                    message = 'La recherche GPS a expiré. Réessayez.';
                }

                setGeoStatus(message, true);

                if (useLocationButton) {
                    useLocationButton.disabled = false;
                    useLocationButton.innerHTML = originalHtml;

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0,
            }
        );
    };

    description?.addEventListener('input', updateDescriptionCount);
    whatsappInput?.addEventListener('input', () => {
        whatsappInput.value = formatPhone(whatsappInput.value);
    });

    mmNumberInput?.addEventListener('input', () => {
        mmNumberInput.value = formatPhone(mmNumberInput.value);
    });

    useLocationButton?.addEventListener('click', capturePosition);

    updateDescriptionCount();
});
</script>
@endsection
