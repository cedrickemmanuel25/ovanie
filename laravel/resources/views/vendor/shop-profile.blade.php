@extends('layouts.vendor')

@section('title', 'Profil de la boutique | OVANIE')

@php
    $user = auth()->user();

    $categoryLabels = [
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

    $deliveryZoneLabels = [
        'abidjan' => 'Abidjan',
        'grand_abidjan' => 'Grand Abidjan',
        'national' => 'Toute la Côte d’Ivoire',
        'afrique_ouest' => 'Afrique de l’Ouest',
    ];

    $processingLabels = [
        'lt24h' => 'Moins de 24 heures',
        '24_48h' => '24 à 48 heures ouvrées',
        '3_5j' => '3 à 5 jours ouvrés',
        '7j_plus' => '7 jours ou plus',
    ];

    $operatorLabels = [
        'orange' => 'Orange Money',
        'mtn' => 'MTN Mobile Money',
        'moov' => 'Moov Money',
        'wave' => 'Wave',
    ];

    $logisticsIsSeller = ($shop->logistics_type ?? 'ovanie') === 'seller';
    $logisticsLabel = $logisticsIsSeller ? 'Logistique vendeur' : 'OVANIE Logistics';

    $shopLatitude = is_numeric($shop->latitude ?? null)
        ? (float) $shop->latitude
        : null;

    $shopLongitude = is_numeric($shop->longitude ?? null)
        ? (float) $shop->longitude
        : null;

    $hasShopCoordinates = $shopLatitude !== null
        && $shopLongitude !== null
        && $shopLatitude >= -90
        && $shopLatitude <= 90
        && $shopLongitude >= -180
        && $shopLongitude <= 180
        && (abs($shopLatitude) > 0.000001 || abs($shopLongitude) > 0.000001);

    $shopMapAddress = collect([
        $shop->address,
        $shop->landmark,
        $shop->district,
        $shop->commune,
        $shop->city,
        $shop->region,
    ])
        ->filter(fn ($value) => filled($value))
        ->map(fn ($value) => trim((string) $value))
        ->unique(fn ($value) => mb_strtolower($value))
        ->implode(', ');

    $commercialActive = ($shop->status ?? null) === \App\Models\Shop::STATUS_APPROVED
        && (bool) ($shop->is_active ?? false);

    $paymentConfigured = filled($shop->mm_operator)
        && filled($shop->mm_number)
        && filled($shop->mm_holder);

    $logisticsConfigured = in_array(
        (string) ($shop->logistics_status ?? ''),
        ['complete', 'configured', 'active'],
        true
    );

    if (! $logisticsIsSeller) {
        $logisticsConfigured = filled($shop->latitude) && filled($shop->longitude);
    }

    $identityUploaded = (bool) (
        ($documents['identity_pdf']['uploaded'] ?? false)
        || (
            ($documents['identity_front']['uploaded'] ?? false)
            && ($documents['identity_back']['uploaded'] ?? false)
        )
    );

    $documentItems = [
        [
            'label' => 'Pièce d’identité',
            'detail' => match($shop->identity_type ?? null) {
                'cni' => 'Carte Nationale d’Identité',
                'passport' => 'Passeport',
                'permis' => 'Permis de conduire',
                'residence' => 'Titre de séjour',
                default => 'Document d’identité',
            },
            'ready' => $identityUploaded,
            'icon' => 'badge-check',
        ],
        [
            'label' => 'RCCM',
            'detail' => 'Registre du commerce et du crédit mobilier',
            'ready' => (bool) ($documents['rccm']['uploaded'] ?? false),
            'icon' => 'file-badge-2',
        ],
        [
            'label' => 'Document fiscal',
            'detail' => 'NIF ou attestation fiscale',
            'ready' => (bool) ($documents['tax']['uploaded'] ?? false),
            'icon' => 'file-text',
        ],
        [
            'label' => 'Selfie vendeur',
            'detail' => 'Photo de vérification du vendeur',
            'ready' => (bool) ($documents['selfie']['uploaded'] ?? false),
            'icon' => 'user-round-check',
        ],
    ];

    $completionChecks = [
        filled($shop->name),
        filled($shop->description),
        filled($shop->main_category),
        filled($shop->city),
        filled($shop->commune),
        filled($shop->district),
        filled($shop->landmark),
        filled($shop->whatsapp) || filled($user?->phone),
        filled($shop->business_email) || filled($user?->email),
        $identityUploaded,
        (bool) ($documents['rccm']['uploaded'] ?? false),
        $paymentConfigured,
        $logisticsConfigured,
    ];

    $completionScore = (int) round(
        collect($completionChecks)->filter()->count() / max(count($completionChecks), 1) * 100
    );

    $missingCount = collect($completionChecks)->filter(fn ($check) => ! $check)->count();

    $phone = $user?->phone ?: 'Non renseigné';
    $whatsapp = $shop->whatsapp ?: $phone;
    $businessEmail = $shop->business_email ?: $user?->email ?: 'Non renseigné';

    $formatPhone = static function ($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 10) {
            return trim(chunk_split($digits, 2, ' '));
        }

        return $value ?: 'Non renseigné';
    };

    $shopCreatedAt = $shop->created_at
        ? \Carbon\Carbon::parse($shop->created_at)->translatedFormat('d F Y')
        : 'Non disponible';

    $publicPreviewUrl = \Illuminate\Support\Facades\Route::has('catalog.index')
        ? route('catalog.index', ['shop' => $shop->slug])
        : url('/catalogue?shop=' . urlencode((string) $shop->slug));

    $deliverySettingsUrl = \Illuminate\Support\Facades\Route::has('vendor.delivery.edit')
        ? route('vendor.delivery.edit')
        : route('vendor.shop.edit');

    $supportUrl = \Illuminate\Support\Facades\Route::has('contact.index')
        ? route('contact.index')
        : url('/contact');
@endphp

@section('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    crossorigin=""
>
<style>
    :root {
        --sp-navy: #0b2f6b;
        --sp-blue: #0f64ea;
        --sp-orange: #ff5a0a;
        --sp-green: #16a34a;
        --sp-red: #ef4444;
        --sp-text: #17366c;
        --sp-muted: #7183a1;
        --sp-line: #dfe7f1;
        --sp-card: #fff;
    }

    .sp-page {
        max-width: 1240px;
        margin: 0 auto;
        padding: 26px 28px 44px;
        color: var(--sp-text);
    }

    .sp-breadcrumb {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:14px;
        color:#8291aa;
        font-size:11px;
        font-weight:800;
        flex-wrap:wrap;
    }

    .sp-breadcrumb a {
        color:var(--sp-blue);
        text-decoration:none;
    }

    .sp-breadcrumb svg {
        width:13px;
        height:13px;
    }

    .sp-head {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:18px;
        margin-bottom:22px;
    }

    .sp-head h1 {
        margin:0;
        color:var(--sp-navy);
        font-size:clamp(31px,3vw,44px);
        line-height:1.05;
        font-weight:900;
        letter-spacing:-.035em;
    }

    .sp-head p {
        margin:8px 0 0;
        color:#657999;
        font-size:13px;
    }

    .sp-head-actions {
        display:flex;
        gap:10px;
        flex:0 0 auto;
    }

    .sp-btn {
        min-height:42px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:0 17px;
        border:1px solid #9eb3d2;
        border-radius:7px;
        background:#fff;
        color:var(--sp-navy);
        font-size:11px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        transition:.18s ease;
    }

    .sp-btn:hover {
        transform:translateY(-1px);
        border-color:var(--sp-blue);
    }

    .sp-btn svg {
        width:16px;
        height:16px;
    }

    .sp-btn-orange {
        border-color:var(--sp-orange);
        background:linear-gradient(90deg,#ff650d,#ff4f00);
        color:#fff;
        box-shadow:0 8px 18px rgba(255,90,10,.16);
    }

    .sp-card {
        border:1px solid var(--sp-line);
        border-radius:12px;
        background:var(--sp-card);
        box-shadow:0 10px 28px rgba(16,44,92,.035);
    }

    .sp-stats {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
        margin-bottom:18px;
    }

    .sp-stat {
        min-height:138px;
        padding:17px;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
    }

    .sp-stat-main {
        display:grid;
        grid-template-columns:56px minmax(0,1fr);
        gap:12px;
        align-items:start;
    }

    .sp-stat-icon,
    .sp-ring {
        width:56px;
        height:56px;
        flex:0 0 56px;
        border-radius:50%;
    }

    .sp-stat-icon {
        display:grid;
        place-items:center;
        background:#edf4ff;
        color:var(--sp-blue);
    }

    .sp-stat-icon.green {
        background:#e8f8ed;
        color:var(--sp-green);
    }

    .sp-stat-icon svg {
        width:25px;
        height:25px;
    }

    .sp-ring {
        position:relative;
        display:grid;
        place-items:center;
        background:
            conic-gradient(
                var(--sp-blue) calc(var(--score) * 1%),
                #e8eef7 0
            );
    }

    .sp-ring::after {
        content:'';
        position:absolute;
        inset:7px;
        border-radius:50%;
        background:#fff;
    }

    .sp-ring strong {
        position:relative;
        z-index:2;
        color:var(--sp-blue);
        font-size:13px;
        font-weight:900;
    }

    .sp-stat h3 {
        margin:0 0 6px;
        color:#244170;
        font-size:10px;
        font-weight:900;
    }

    .sp-stat-value {
        margin-bottom:5px;
        color:var(--sp-navy);
        font-size:12px;
        font-weight:900;
    }

    .sp-stat-desc {
        margin:0;
        color:#71819f;
        font-size:9.4px;
        line-height:1.5;
    }

    .sp-status-pill {
        display:inline-flex;
        align-items:center;
        min-height:25px;
        width:max-content;
        padding:0 9px;
        border-radius:999px;
        color:#188641;
        background:#e6f8eb;
        font-size:9px;
        font-weight:900;
    }

    .sp-status-pill.blue {
        color:#1763df;
        background:#ebf2ff;
    }

    .sp-status-pill.orange {
        color:#e5650d;
        background:#fff0e5;
    }

    .sp-stat-link,
    .sp-inline-link {
        display:inline-flex;
        align-items:center;
        gap:6px;
        color:var(--sp-blue);
        font-size:9.5px;
        font-weight:900;
        text-decoration:none;
    }

    .sp-stat-note {
        display:inline-flex;
        align-items:center;
        gap:6px;
        color:#5f7394;
        font-size:9px;
        line-height:1.35;
        font-weight:800;
    }

    .sp-stat-note svg {
        width:14px;
        height:14px;
        color:var(--sp-blue);
        flex:0 0 14px;
    }

    .sp-stat-link svg,
    .sp-inline-link svg {
        width:13px;
        height:13px;
    }

    .sp-overview {
        display:grid;
        grid-template-columns:1.12fr .9fr 1.35fr;
        margin-bottom:18px;
        overflow:hidden;
    }

    .sp-overview-col {
        min-width:0;
        padding:18px 20px;
    }

    .sp-overview-col + .sp-overview-col {
        border-left:1px solid var(--sp-line);
    }

    .sp-section-title {
        display:flex;
        align-items:center;
        gap:8px;
        margin:0 0 16px;
        color:var(--sp-navy);
        font-size:12px;
        font-weight:900;
    }

    .sp-section-title svg {
        width:17px;
        height:17px;
        color:#2e5ca4;
    }

    .sp-data-list {
        display:grid;
        gap:13px;
    }

    .sp-data-row small {
        display:block;
        margin-bottom:4px;
        color:#7485a2;
        font-size:8.8px;
        font-weight:800;
    }

    .sp-data-row strong,
    .sp-data-row p {
        color:#17366d;
        font-size:10.7px;
    }

    .sp-data-row strong {
        font-weight:900;
    }

    .sp-data-row p {
        margin:0;
        line-height:1.55;
    }

    .sp-value-icon {
        display:flex;
        align-items:center;
        gap:7px;
    }

    .sp-value-icon svg {
        width:15px;
        height:15px;
        color:#3466b4;
    }

    .sp-location-grid {
        display:grid;
        grid-template-columns:minmax(0,1fr) 170px;
        gap:18px;
        align-items:start;
    }

    .sp-map {
        min-height:224px;
        position:relative;
        overflow:hidden;
        border:1px solid #dbe5f2;
        border-radius:10px;
        background:#eef4fb;
    }

    .sp-real-map {
        position:absolute;
        inset:0 0 42px;
        z-index:1;
        min-height:182px;
        background:#eef4fb;
    }

    .sp-map .leaflet-container {
        width:100%;
        height:100%;
        font-family:inherit;
        background:#eef4fb;
    }

    .sp-map .leaflet-control-zoom {
        margin-top:10px;
        margin-left:10px;
        border:0;
        box-shadow:0 8px 20px rgba(17,47,94,.15);
    }

    .sp-map .leaflet-control-zoom a {
        width:28px;
        height:28px;
        line-height:28px;
        border:0;
        color:#12366d;
        font-weight:900;
    }

    .sp-map .leaflet-control-attribution {
        padding:2px 5px;
        background:rgba(255,255,255,.88);
        color:#6e7f9a;
        font-size:7px;
    }

    .sp-map .leaflet-control-attribution a {
        color:#245fae;
    }

    .sp-map-footer {
        position:absolute;
        z-index:500;
        left:0;
        right:0;
        bottom:0;
        min-height:42px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        padding:0 10px;
        border-top:1px solid #dfe7f1;
        background:rgba(255,255,255,.98);
    }

    .sp-map-state {
        min-width:0;
        display:flex;
        align-items:center;
        gap:6px;
        color:#244877;
        font-size:8.2px;
        font-weight:900;
    }

    .sp-map-state svg {
        width:14px;
        height:14px;
        flex:0 0 14px;
        color:#17a34a;
    }

    .sp-map-action {
        flex:0 0 auto;
        white-space:nowrap;
    }

    .sp-map-empty {
        position:absolute;
        inset:0 0 42px;
        display:grid;
        place-items:center;
        padding:20px;
        text-align:center;
        background:
            radial-gradient(circle at 50% 38%,rgba(15,100,234,.11),transparent 34%),
            linear-gradient(135deg,#f7faff,#edf3fb);
    }

    .sp-map-empty-inner {
        display:grid;
        justify-items:center;
        gap:7px;
        color:#6d7f9d;
        font-size:8.8px;
        line-height:1.45;
    }

    .sp-map-empty-icon {
        width:44px;
        height:44px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:#0f64ea;
        background:#fff;
        box-shadow:0 10px 24px rgba(15,100,234,.13);
    }

    .sp-map-empty-icon svg {
        width:21px;
        height:21px;
    }

    .sp-map-marker-wrap {
        border:0;
        background:transparent;
    }

    .sp-map-marker {
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border:3px solid #fff;
        border-radius:50% 50% 50% 12px;
        color:#fff;
        background:linear-gradient(135deg,#0f64ea,#0747b8);
        box-shadow:0 8px 18px rgba(15,100,234,.28);
        transform:rotate(-45deg);
    }

    .sp-map-marker svg {
        width:20px;
        height:20px;
        transform:rotate(45deg);
    }

    .sp-map-popup strong {
        display:block;
        margin-bottom:3px;
        color:#0b2f6b;
        font-size:11px;
    }

    .sp-map-popup span {
        color:#667b9b;
        font-size:9px;
        line-height:1.4;
    }

    .sp-logistics-info {
        padding:11px 12px;
        border:1px solid #dbe8fb;
        border-radius:8px;
        background:#f2f7ff;
        color:#31547f;
        font-size:9px;
        line-height:1.55;
    }

    .sp-logistics-info strong {
        color:#0f58ca;
    }

    .sp-stat-note {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:22px;
        height:22px;
        border-radius:999px;
        background:#eef5ff;
        color:#0f64ea;
        border:1px solid #d9e7ff;
    }

    .sp-stat-note svg {
        width:13px;
        height:13px;
    }

    .sp-grid-2 {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:18px;
        margin-bottom:18px;
    }

    .sp-block {
        padding:18px 20px;
    }

    .sp-rows {
        display:grid;
        gap:14px;
    }

    .sp-detail-row {
        display:grid;
        grid-template-columns:138px minmax(0,1fr);
        gap:12px;
        align-items:start;
    }

    .sp-detail-row span {
        color:#7485a2;
        font-size:9px;
        font-weight:800;
    }

    .sp-detail-row strong {
        color:#17366d;
        font-size:10.5px;
        font-weight:900;
    }

    .sp-operator {
        display:inline-flex;
        align-items:center;
        gap:8px;
    }

    .sp-operator-mark {
        width:28px;
        height:22px;
        display:grid;
        place-items:center;
        border-radius:5px;
        background:#fff1df;
        color:#ef6800;
        font-size:8px;
        font-weight:900;
    }

    .sp-bottom-grid {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:18px;
        align-items:start;
    }

    .sp-doc-list {
        display:grid;
        gap:9px;
        margin-top:4px;
    }

    .sp-doc-row {
        display:grid;
        grid-template-columns:42px minmax(0,1fr) auto 26px;
        align-items:center;
        gap:10px;
        padding:10px;
        border:1px solid #e3eaf3;
        border-radius:8px;
        background:#fff;
    }

    .sp-doc-icon {
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border-radius:8px;
        color:#1e63df;
        background:#edf4ff;
    }

    .sp-doc-icon svg {
        width:20px;
        height:20px;
    }

    .sp-doc-copy strong {
        display:block;
        color:#15346c;
        font-size:10px;
        font-weight:900;
    }

    .sp-doc-copy span {
        display:block;
        margin-top:2px;
        color:#7586a1;
        font-size:8.5px;
    }

    .sp-doc-state {
        min-height:24px;
        display:inline-flex;
        align-items:center;
        padding:0 8px;
        border-radius:5px;
        background:#e5f8ea;
        color:#178641;
        font-size:8.5px;
        font-weight:900;
        white-space:nowrap;
    }

    .sp-doc-state.missing {
        background:#feeaea;
        color:#d63838;
    }

    .sp-doc-check {
        width:24px;
        height:24px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:#14a44d;
        background:#edf9f1;
    }

    .sp-doc-check.missing {
        color:#e64545;
        background:#fff0f0;
    }

    .sp-doc-check svg {
        width:14px;
        height:14px;
    }

    .sp-right-stack {
        display:grid;
        gap:18px;
    }

    .sp-advice-list {
        display:grid;
        gap:12px;
    }

    .sp-advice-item {
        display:grid;
        grid-template-columns:18px 1fr;
        gap:8px;
        color:#36527e;
        font-size:9.5px;
        line-height:1.5;
    }

    .sp-advice-check {
        width:17px;
        height:17px;
        display:grid;
        place-items:center;
        border-radius:50%;
        border:1.5px solid #16a34a;
        color:#16a34a;
    }

    .sp-advice-check svg {
        width:10px;
        height:10px;
    }

    .sp-help {
        padding:16px 18px;
    }

    .sp-help-title {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:10px;
    }

    .sp-help-title svg {
        width:20px;
        height:20px;
        color:#1e4b92;
    }

    .sp-help-title h3 {
        margin:0;
        color:#15366d;
        font-size:12px;
        font-weight:900;
    }

    .sp-help p {
        margin:0 0 12px;
        color:#7183a1;
        font-size:9px;
        line-height:1.55;
    }

    .sp-help-actions {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:8px;
    }

    .sp-help-actions .sp-btn {
        min-height:36px;
        padding:0 10px;
        font-size:9px;
    }

    .sp-message {
        margin-bottom:14px;
        padding:12px 14px;
        border-radius:8px;
        border:1px solid #bde8cc;
        background:#edf9f1;
        color:#136b35;
        font-size:11px;
        font-weight:700;
    }

    @media(max-width:1100px) {
        .sp-stats {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .sp-overview {
            grid-template-columns:1fr;
        }

        .sp-overview-col + .sp-overview-col {
            border-left:0;
            border-top:1px solid var(--sp-line);
        }

        .sp-grid-2,
        .sp-bottom-grid {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:700px) {
        .sp-page {
            padding:18px 14px 30px;
        }

        .sp-head {
            flex-direction:column;
        }

        .sp-head-actions {
            width:100%;
        }

        .sp-head-actions .sp-btn {
            flex:1;
        }

        .sp-stats {
            grid-template-columns:1fr;
        }

        .sp-location-grid {
            grid-template-columns:1fr;
        }

        .sp-map {
            min-height:180px;
        }

        .sp-detail-row {
            grid-template-columns:1fr;
            gap:4px;
        }

        .sp-doc-row {
            grid-template-columns:38px minmax(0,1fr) auto;
        }

        .sp-doc-check {
            display:none;
        }

        .sp-help-actions {
            grid-template-columns:1fr;
        }
    }
    .sp-password-card{margin-bottom:18px;padding:20px}.sp-password-card h2{margin:0 0 6px;font-size:18px}.sp-password-card p{margin:0 0 16px;color:#6f7f96}.sp-password-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sp-password-grid label{font-size:12px;font-weight:700;color:#294364}.sp-password-grid input{display:block;width:100%;height:44px;margin-top:6px;padding:0 12px;border:1px solid #dbe3ed;border-radius:8px}.sp-password-card .sp-btn{margin-top:14px;border:0}@media(max-width:760px){.sp-password-grid{grid-template-columns:1fr}}
</style>
@endsection

@section('content')
<div class="sp-page">
    <nav class="sp-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route('vendor.shop.profile') }}">Boutique</a>
        <i data-lucide="chevron-right"></i>
        <span>Profil de la boutique</span>
    </nav>

    <div class="sp-head">
        <div>
            <h1>Profil de la boutique</h1>
            <p>Gérez les informations de votre boutique, vos coordonnées, votre logistique et vos documents.</p>
        </div>

        <div class="sp-head-actions">
            <a href="{{ $publicPreviewUrl }}" class="sp-btn" target="_blank">
                <i data-lucide="eye"></i>
                Aperçu public
            </a>

            <a href="{{ route('vendor.shop.edit') }}" class="sp-btn sp-btn-orange">
                <i data-lucide="pencil"></i>
                Modifier la boutique
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="sp-message">{{ session('success') }}</div>
    @endif

    <section class="sp-card sp-password-card" id="vendorPassword">
        <h2>Sécurité du compte</h2>
        <p>Modifiez le mot de passe temporaire communiqué lors de la création de votre compte.</p>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')
            <div class="sp-password-grid">
                <label>Mot de passe actuel<input type="password" name="current_password" autocomplete="current-password" required></label>
                <label>Nouveau mot de passe<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                <label>Confirmer le mot de passe<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
            </div>
            @error('current_password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
            @error('password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
            <button class="sp-btn sp-btn-orange" type="submit">Modifier mon mot de passe</button>
        </form>
    </section>

    <section class="sp-stats">
        <article class="sp-card sp-stat">
            <div class="sp-stat-main">
                <div class="sp-ring" style="--score:{{ $completionScore }}">
                    <strong>{{ $completionScore }}%</strong>
                </div>

                <div>
                    <h3>Score de complétude</h3>
                    <div class="sp-stat-value">
                        {{ $completionScore >= 90 ? 'Excellent !' : ($completionScore >= 75 ? 'Très bien !' : 'À compléter') }}
                    </div>
                    <p class="sp-stat-desc">
                        {{ $missingCount === 0
                            ? 'Votre profil est complet.'
                            : $missingCount . ' élément(s) restent à compléter.' }}
                    </p>
                </div>
            </div>

            <a class="sp-stat-link" href="{{ route('vendor.shop-status') }}">
                Voir les éléments manquants
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="sp-card sp-stat">
            <div class="sp-stat-main">
                <span class="sp-stat-icon"><i data-lucide="store"></i></span>

                <div>
                    <h3>Statut boutique</h3>
                    <span class="sp-status-pill {{ $commercialActive ? '' : 'orange' }}">
                        {{ $commercialActive ? 'Active' : 'Inactive' }}
                    </span>
                    <p class="sp-stat-desc" style="margin-top:8px;">
                        {{ $commercialActive
                            ? 'Votre boutique est visible et opérationnelle.'
                            : 'La boutique est actuellement inactive.' }}
                    </p>
                </div>
            </div>

            <a class="sp-stat-link" href="{{ route('vendor.shop-status') }}">
                Voir le statut
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="sp-card sp-stat">
            <div class="sp-stat-main">
                <span class="sp-stat-icon"><i data-lucide="truck"></i></span>

                <div>
                    <h3>Mode logistique</h3>
                    <span class="sp-status-pill blue">{{ $logisticsLabel }}</span>
                    <p class="sp-stat-desc" style="margin-top:8px;">
                        {{ $logisticsIsSeller
                            ? 'Vous gérez la livraison de vos commandes.'
                            : 'OVANIE organise la livraison de vos commandes.' }}
                    </p>
                </div>
            </div>

            @if($logisticsIsSeller)
                <a class="sp-stat-link" href="{{ $deliverySettingsUrl }}">
                    Voir les paramètres
                    <i data-lucide="arrow-right"></i>
                </a>
            @else
                <span class="sp-stat-note" aria-hidden="true">
                    <i data-lucide="map-pin-check"></i>
                </span>
            @endif
        </article>

        <article class="sp-card sp-stat">
            <div class="sp-stat-main">
                <span class="sp-stat-icon green"><i data-lucide="wallet-cards"></i></span>

                <div>
                    <h3>Paiement vendeur</h3>
                    <span class="sp-status-pill {{ $paymentConfigured ? '' : 'orange' }}">
                        {{ $paymentConfigured ? 'Configuré' : 'À compléter' }}
                    </span>
                    <p class="sp-stat-desc" style="margin-top:8px;">
                        {{ $paymentConfigured
                            ? 'Votre méthode de paiement est active.'
                            : 'Ajoutez un compte de versement.' }}
                    </p>
                </div>
            </div>

            <a class="sp-stat-link" href="{{ route('vendor.payment-method') }}">
                Voir les détails
                <i data-lucide="arrow-right"></i>
            </a>
        </article>
    </section>

    <section class="sp-card sp-overview">
        <div class="sp-overview-col">
            <h2 class="sp-section-title">
                <i data-lucide="info"></i>
                Informations générales
            </h2>

            <div class="sp-data-list">
                <div class="sp-data-row">
                    <small>Nom de la boutique</small>
                    <strong>{{ $shop->name ?: 'Non renseigné' }}</strong>
                </div>

                <div class="sp-data-row">
                    <small>Catégorie principale</small>
                    <div class="sp-value-icon">
                        <i data-lucide="package"></i>
                        <strong>{{ $categoryLabels[$shop->main_category] ?? ucfirst(str_replace('-', ' ', (string) $shop->main_category)) ?: 'Non renseignée' }}</strong>
                    </div>
                </div>

                <div class="sp-data-row">
                    <small>Description</small>
                    <p>{{ $shop->description ?: 'Aucune description renseignée.' }}</p>
                </div>

                <div class="sp-data-row">
                    <small>Date d’ouverture</small>
                    <div class="sp-value-icon">
                        <i data-lucide="calendar-days"></i>
                        <strong>{{ $shopCreatedAt }}</strong>
                    </div>
                </div>

                <div class="sp-data-row">
                    <small>Type de vendeur</small>
                    <div class="sp-value-icon">
                        <i data-lucide="user-round"></i>
                        <strong>{{ ucfirst((string) ($shop->seller_type ?: 'professionnel')) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-overview-col">
            <h2 class="sp-section-title">
                <i data-lucide="user-round"></i>
                Coordonnées professionnelles
            </h2>

            <div class="sp-data-list">
                <div class="sp-data-row">
                    <small>Téléphone</small>
                    <div class="sp-value-icon">
                        <i data-lucide="phone"></i>
                        <strong>{{ $formatPhone($phone) }}</strong>
                    </div>
                </div>

                <div class="sp-data-row">
                    <small>WhatsApp pro</small>
                    <div class="sp-value-icon">
                        <i data-lucide="message-circle"></i>
                        <strong>{{ $formatPhone($whatsapp) }}</strong>
                    </div>
                </div>

                <div class="sp-data-row">
                    <small>E-mail pro</small>
                    <div class="sp-value-icon">
                        <i data-lucide="mail"></i>
                        <strong>{{ $businessEmail }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-overview-col">
            <h2 class="sp-section-title">
                <i data-lucide="map-pin"></i>
                Localisation
            </h2>

            <div class="sp-location-grid">
                <div class="sp-data-list">
                    <div class="sp-data-row">
                        <small>Région</small>
                        <strong>{{ $shop->region ?: 'Non renseignée' }}</strong>
                    </div>

                    <div class="sp-data-row">
                        <small>Ville</small>
                        <strong>{{ $shop->city ?: 'Non renseignée' }}</strong>
                    </div>

                    <div class="sp-data-row">
                        <small>Commune</small>
                        <strong>{{ $shop->commune ?: 'Non renseignée' }}</strong>
                    </div>

                    <div class="sp-data-row">
                        <small>Quartier</small>
                        <strong>{{ $shop->district ?: 'Non renseigné' }}</strong>
                    </div>

                    <div class="sp-data-row">
                        <small>Adresse / point de repère</small>
                        <p>
                            {{ collect([$shop->address, $shop->landmark])->filter()->implode(' — ') ?: 'Non renseigné' }}
                        </p>
                    </div>
                </div>

                <div
                    class="sp-map"
                    @if($hasShopCoordinates)
                        data-shop-map
                        data-latitude="{{ $shopLatitude }}"
                        data-longitude="{{ $shopLongitude }}"
                    @endif
                >
                    @if($hasShopCoordinates)
                        <div
                            id="shopProfileMap"
                            class="sp-real-map"
                            aria-label="Carte de la position réelle de la boutique"
                        ></div>
                    @else
                        <div class="sp-map-empty">
                            <div class="sp-map-empty-inner">
                                <span class="sp-map-empty-icon">
                                    <i data-lucide="map-pin-off"></i>
                                </span>
                                <strong>Position GPS non enregistrée</strong>
                                <span>Ajoutez la position exacte de votre boutique pour l’afficher sur la carte.</span>
                            </div>
                        </div>
                    @endif

                    <div class="sp-map-footer">
                        <span class="sp-map-state">
                            <i data-lucide="{{ $hasShopCoordinates ? 'badge-check' : 'circle-alert' }}"></i>
                            {{ $hasShopCoordinates ? 'Position réelle enregistrée' : 'Position à compléter' }}
                        </span>

                        <a href="{{ route('vendor.shop.edit') }}" class="sp-inline-link sp-map-action">
                            <i data-lucide="pencil"></i>
                            Modifier
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="sp-grid-2">
        <section class="sp-card sp-block">
            <h2 class="sp-section-title">
                <i data-lucide="truck"></i>
                Logistique & livraison
            </h2>

            <div class="sp-rows">
                <div class="sp-detail-row">
                    <span>Mode logistique</span>
                    <strong>{{ $logisticsLabel }}</strong>
                </div>

                @if($logisticsIsSeller)
                    <div class="sp-detail-row">
                        <span>Zone de couverture</span>
                        <strong>{{ $deliveryZoneLabels[$shop->delivery_zone] ?? 'Non renseignée' }}</strong>
                    </div>

                    <div class="sp-detail-row">
                        <span>Délai de traitement</span>
                        <strong>{{ $processingLabels[$shop->processing_time] ?? 'Non renseigné' }}</strong>
                    </div>

                    <div class="sp-detail-row">
                        <span>État de la configuration</span>
                        <strong>
                            <span class="sp-status-pill {{ $logisticsConfigured ? '' : 'orange' }}">
                                {{ $logisticsConfigured ? 'Configuré' : 'À compléter' }}
                            </span>
                        </strong>
                    </div>

                    <a href="{{ $deliverySettingsUrl }}" class="sp-inline-link">
                        Voir les paramètres de livraison
                        <i data-lucide="arrow-right"></i>
                    </a>
                @else
                    <div class="sp-detail-row">
                        <span>Point d’enlèvement</span>
                        <strong>{{ $shopMapAddress ?: 'Adresse à compléter' }}</strong>
                    </div>

                    <div class="sp-detail-row">
                        <span>Position GPS</span>
                        <strong>
                            <span class="sp-status-pill {{ $hasShopCoordinates ? '' : 'orange' }}">
                                {{ $hasShopCoordinates ? 'Enregistrée' : 'À compléter' }}
                            </span>
                        </strong>
                    </div>

                    <div class="sp-detail-row">
                        <span>Prise en charge</span>
                        <strong>Transport organisé par OVANIE</strong>
                    </div>


                    <a href="{{ route('vendor.shop.edit') }}" class="sp-inline-link">
                        Mettre à jour la position de la boutique
                        <i data-lucide="map-pin"></i>
                    </a>
                @endif
            </div>
        </section>

        <section class="sp-card sp-block">
            <h2 class="sp-section-title">
                <i data-lucide="wallet-cards" style="color:#ff650d;"></i>
                Méthode de paiement vendeur
            </h2>

            <div class="sp-rows">
                <div class="sp-detail-row">
                    <span>Opérateur</span>
                    <strong class="sp-operator">
                        <span class="sp-operator-mark">{{ strtoupper(substr((string) ($shop->mm_operator ?: 'MM'), 0, 2)) }}</span>
                        {{ $operatorLabels[$shop->mm_operator] ?? 'Non configuré' }}
                    </strong>
                </div>

                <div class="sp-detail-row">
                    <span>Numéro</span>
                    <strong>{{ $formatPhone($shop->mm_number) }}</strong>
                </div>

                <div class="sp-detail-row">
                    <span>Titulaire du compte</span>
                    <strong>{{ $shop->mm_holder ?: 'Non renseigné' }}</strong>
                </div>

                <div class="sp-detail-row">
                    <span>Statut</span>
                    <strong>
                        <span class="sp-status-pill {{ $paymentConfigured ? '' : 'orange' }}">
                            {{ $paymentConfigured ? 'Active' : 'À compléter' }}
                        </span>
                    </strong>
                </div>

                <a href="{{ route('vendor.payment-method') }}" class="sp-inline-link">
                    Gérer la méthode de paiement
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </section>
    </div>

    <div class="sp-bottom-grid">
        <section class="sp-card sp-block">
            <h2 class="sp-section-title">
                <i data-lucide="files"></i>
                Documents de la boutique
            </h2>

            <p style="margin:-8px 0 15px;color:#7485a2;font-size:9px;">
                Les documents ci-dessous sont utilisés pour la vérification et le suivi de votre boutique.
            </p>

            <div class="sp-doc-list">
                @foreach($documentItems as $document)
                    <div class="sp-doc-row">
                        <span class="sp-doc-icon">
                            <i data-lucide="{{ $document['icon'] }}"></i>
                        </span>

                        <div class="sp-doc-copy">
                            <strong>{{ $document['label'] }}</strong>
                            <span>{{ $document['detail'] }}</span>
                        </div>

                        <span class="sp-doc-state {{ $document['ready'] ? '' : 'missing' }}">
                            {{ $document['ready'] ? 'Ajouté' : 'À compléter' }}
                        </span>

                        <span class="sp-doc-check {{ $document['ready'] ? '' : 'missing' }}">
                            <i data-lucide="{{ $document['ready'] ? 'check' : 'alert-circle' }}"></i>
                        </span>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('vendor.shop.documents') }}" class="sp-inline-link" style="margin-top:14px;">
                Gérer les documents
                <i data-lucide="arrow-right"></i>
            </a>
        </section>

        <div class="sp-right-stack">
            <section class="sp-card sp-block">
                <h2 class="sp-section-title">
                    <i data-lucide="lightbulb"></i>
                    Conseils OVANIE
                </h2>

                <div class="sp-advice-list">
                    @foreach([
                        'Complétez 100% de votre profil pour renforcer la confiance des clients.',
                        'Ajoutez une description claire et maintenez vos informations à jour.',
                        'Gardez votre méthode de paiement active pour éviter les retards de reversement.',
                        $logisticsIsSeller
                            ? 'Vérifiez régulièrement vos paramètres logistiques et vos délais de traitement.'
                            : 'Maintenez l’adresse et la position GPS de votre boutique à jour pour faciliter les enlèvements OVANIE.',
                    ] as $advice)
                        <div class="sp-advice-item">
                            <span class="sp-advice-check"><i data-lucide="check"></i></span>
                            <span>{{ $advice }}</span>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('vendor.shop-status') }}" class="sp-inline-link" style="margin-top:14px;">
                    Voir tous les conseils
                    <i data-lucide="arrow-right"></i>
                </a>
            </section>

            <section class="sp-card sp-help">
                <div class="sp-help-title">
                    <i data-lucide="headphones"></i>
                    <h3>Besoin d’assistance ?</h3>
                </div>

                <p>Notre équipe est disponible pour vous accompagner sur la gestion de votre boutique.</p>

                <div class="sp-help-actions">
                    <a href="tel:0161780000" class="sp-btn">
                        <i data-lucide="phone"></i>
                        01 61 78 00 00
                    </a>

                    @if(filled($whatsapp) && $whatsapp !== 'Non renseigné')
                        <a
                            href="https://wa.me/{{ preg_replace('/\D+/', '', (string) $whatsapp) }}"
                            class="sp-btn"
                            target="_blank"
                            rel="noopener"
                        >
                            <i data-lucide="message-circle"></i>
                            WhatsApp
                        </a>
                    @else
                        <a href="{{ $supportUrl }}" class="sp-btn">
                            <i data-lucide="message-circle"></i>
                            Support
                        </a>
                    @endif
                </div>

                <a href="{{ $supportUrl }}" class="sp-btn" style="width:100%;margin-top:8px;">
                    Contacter le support
                    <i data-lucide="arrow-right"></i>
                </a>
            </section>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    crossorigin=""
></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.querySelector('[data-shop-map]');
    const mapElement = document.getElementById('shopProfileMap');

    if (!mapContainer || !mapElement || typeof window.L === 'undefined') {
        return;
    }

    const latitude = Number(mapContainer.dataset.latitude);
    const longitude = Number(mapContainer.dataset.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const shopName = @json($shop->name ?: 'Votre boutique');
    const shopAddress = @json($shopMapAddress ?: 'Position GPS enregistrée');

    const map = L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
        dragging: true,
        doubleClickZoom: true,
        touchZoom: true,
    }).setView([latitude, longitude], 16);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }
    ).addTo(map);

    const markerIcon = L.divIcon({
        className: 'sp-map-marker-wrap',
        html: `
            <span class="sp-map-marker" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 10h16M6 10V7l2-3h8l2 3v3M6 10v9h12v-9M9 19v-5h6v5"></path>
                </svg>
             </span>
        `,
        iconSize: [42, 42],
        iconAnchor: [21, 40],
    });

    L.marker([latitude, longitude], {
        icon: markerIcon,
        title: shopName,
        keyboard: true,
    }).addTo(map).bindPopup(`
        <div class="sp-map-popup">
            <strong>${String(shopName).replace(/[&<>"']/g, '')}</strong>
            <span>${String(shopAddress).replace(/[&<>"']/g, '')}</span>
        </div>
    `);

    setTimeout(function () {
        map.invalidateSize();
    }, 150);
});
</script>
@endsection
