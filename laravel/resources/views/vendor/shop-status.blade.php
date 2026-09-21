@extends('layouts.vendor')

@section('title', 'Statut de la boutique | OVANIE')

@php
    $routeUrl = static function (string $name, array $params = [], string $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name)
            ? route($name, $params)
            : url($fallback);
    };

    $user = auth()->user();

    $identityReady = filled(data_get($shop, 'selfie'))
        && (
            filled(data_get($shop, 'identity_file'))
            || (
                filled(data_get($shop, 'identity_file_front'))
                && filled(data_get($shop, 'identity_file_back'))
            )
        );

    $rccmReady = filled(data_get($shop, 'rccm_file'));
    $taxReady = filled(data_get($shop, 'tax_file'));

    $addressReady = filled(data_get($shop, 'commune'))
        && filled(data_get($shop, 'district'))
        && filled(data_get($shop, 'landmark'))
        && filled(data_get($shop, 'latitude'))
        && filled(data_get($shop, 'longitude'));

    $paymentReady = filled(data_get($shop, 'mm_operator'))
        && filled(data_get($shop, 'mm_number'))
        && filled(data_get($shop, 'mm_holder'));

    $logisticsReady = (bool) data_get($logisticsValidation, 'complete', false);

    $hasProduct = false;
    try {
        if (method_exists($shop, 'products')) {
            $hasProduct = $shop->products()->exists();
        }
    } catch (\Throwable $e) {
        $hasProduct = (bool) $canPublish;
    }

    $termsAccepted = filled(data_get($shop, 'terms_accepted_at'))
        || filled(data_get($shop, 'conditions_accepted_at'))
        || filled(data_get($shop, 'accepted_terms_at'));

    $kycValue = strtolower((string) data_get($shop, 'kyc_status', ''));
    $kycVerified = in_array($kycValue, ['verified', 'approved', 'validated', 'valide', 'validé'], true);

    $scorePart = static function (array $checks): int {
        if ($checks === []) {
            return 0;
        }

        $done = collect($checks)->filter()->count();

        return (int) round(($done / count($checks)) * 100);
    };

    $informationScore = $scorePart([
        filled(data_get($shop, 'name')),
        filled(data_get($shop, 'description')),
        filled(data_get($shop, 'main_category')),
        filled(data_get($shop, 'business_email')) || filled(data_get($user, 'email')),
        filled(data_get($shop, 'whatsapp')) || filled(data_get($user, 'phone')),
        $addressReady,
    ]);

    $legalScore = $scorePart([
        $identityReady,
        $rccmReady,
        $taxReady,
    ]);

    $paymentScore = $paymentReady ? 100 : 0;
    $logisticsScore = $logisticsReady ? 100 : 0;

    $catalogScore = $scorePart([
        $hasProduct,
        (bool) $canPublish,
    ]);

    $overallScore = (int) round(
        (
            ($informationScore * 0.25)
            + ($legalScore * 0.20)
            + ($paymentScore * 0.15)
            + ($logisticsScore * 0.20)
            + ($catalogScore * 0.20)
        )
    );

    $validationRows = [
        [
            'label' => 'Identité du vendeur',
            'icon' => 'circle-check',
            'state' => $identityReady ? 'valid' : 'missing',
            'text' => $identityReady ? 'Validé' : 'À compléter',
        ],
        [
            'label' => 'RCCM',
            'icon' => 'file-text',
            'state' => $rccmReady ? 'valid' : 'missing',
            'text' => $rccmReady ? 'Validé' : 'À compléter',
        ],
        [
            'label' => 'Document fiscal',
            'icon' => 'clock-3',
            'state' => $taxReady ? ($kycVerified ? 'valid' : 'pending') : 'missing',
            'text' => $taxReady ? ($kycVerified ? 'Validé' : 'En cours') : 'À compléter',
        ],
        [
            'label' => 'Adresse de la boutique',
            'icon' => 'map-pin',
            'state' => $addressReady ? 'valid' : 'missing',
            'text' => $addressReady ? 'Validée' : 'À compléter',
        ],
        [
            'label' => 'Méthode de paiement',
            'icon' => 'wallet-cards',
            'state' => $paymentReady ? 'valid' : 'missing',
            'text' => $paymentReady ? 'Validé' : 'À compléter',
        ],
        [
            'label' => 'Paramètres logistiques',
            'icon' => 'truck',
            'state' => $logisticsReady ? 'valid' : 'missing',
            'text' => $logisticsReady ? 'Validés' : 'À compléter',
        ],
        [
            'label' => 'Première fiche produit',
            'icon' => 'package-check',
            'state' => $hasProduct ? 'valid' : 'missing',
            'text' => $hasProduct ? 'Validée' : 'À créer',
        ],
        [
            'label' => 'Conditions vendeur',
            'icon' => 'shield-check',
            'state' => $termsAccepted ? 'valid' : 'pending',
            'text' => $termsAccepted ? 'Acceptées' : 'À confirmer',
        ],
    ];

    $incompleteCount = collect($validationRows)
        ->whereIn('state', ['missing', 'pending'])
        ->count();

    $globalStatus = $commercialOpen ? 'Active' : 'Inactive';

    $visibilityText = $commercialOpen && $canPublish
        ? 'Visible sur OVANIE'
        : ($commercialOpen ? 'Visibilité limitée' : 'Non visible');

    $conformityText = $kycVerified
        ? 'Conforme'
        : (($identityReady || $rccmReady || $taxReady) ? 'En vérification' : 'À compléter');

    $updatedAt = data_get($shop, 'updated_at');
    $updatedDate = $updatedAt
        ? \Carbon\Carbon::parse($updatedAt)->translatedFormat('d F Y')
        : 'Non disponible';

    $updatedHuman = $updatedAt
        ? \Carbon\Carbon::parse($updatedAt)->diffForHumans()
        : 'Date inconnue';

    $updaterName = data_get($user, 'name') ?: 'Vous';

    $scoreRows = [
        ['label' => 'Informations boutique', 'icon' => 'info', 'score' => $informationScore],
        ['label' => 'Documents légaux', 'icon' => 'files', 'score' => $legalScore],
        ['label' => 'Paiement vendeur', 'icon' => 'wallet-cards', 'score' => $paymentScore],
        ['label' => 'Logistique', 'icon' => 'truck', 'score' => $logisticsScore],
        ['label' => 'Catalogue produits', 'icon' => 'package', 'score' => $catalogScore],
    ];

    $actions = [];

    if (! $addressReady) {
        $actions[] = [
            'title' => 'Compléter l’adresse',
            'description' => 'Ajoutez l’adresse, le quartier, le repère et la position GPS de la boutique.',
            'badge' => 'Urgent',
            'tone' => 'red',
            'icon' => 'map-pin',
            'button' => 'Compléter',
            'url' => $routeUrl('vendor.shop.edit', [], '/vendeur/shop-profile/edit'),
        ];
    }

    if (! $logisticsReady) {
        $actions[] = [
            'title' => 'Configurer la livraison',
            'description' => 'Finalisez les informations logistiques nécessaires à votre mode de livraison.',
            'badge' => 'À faire',
            'tone' => 'blue',
            'icon' => 'truck',
            'button' => 'Configurer',
            'url' => $routeUrl('vendor.delivery.index', [], '/vendeur/livraison'),
        ];
    }

    if ($taxReady && ! $kycVerified) {
        $actions[] = [
            'title' => 'Suivre le document fiscal',
            'description' => 'Votre document fiscal a été transmis et reste en cours de vérification.',
            'badge' => 'En cours',
            'tone' => 'orange',
            'icon' => 'file-clock',
            'button' => 'Voir le détail',
            'url' => $routeUrl('vendor.shop.documents', [], '/vendeur/shop-documents'),
        ];
    } elseif (! $taxReady) {
        $actions[] = [
            'title' => 'Ajouter le document fiscal',
            'description' => 'Téléversez votre NIF ou votre attestation fiscale.',
            'badge' => 'À compléter',
            'tone' => 'orange',
            'icon' => 'file-plus-2',
            'button' => 'Téléverser',
            'url' => $routeUrl('vendor.shop.documents', [], '/vendeur/shop-documents'),
        ];
    }

    if (! $paymentReady) {
        $actions[] = [
            'title' => 'Configurer le paiement',
            'description' => 'Ajoutez le compte Mobile Money destiné à recevoir vos reversements.',
            'badge' => 'À faire',
            'tone' => 'blue',
            'icon' => 'wallet-cards',
            'button' => 'Configurer',
            'url' => $routeUrl('vendor.payment-method', [], '/vendeur/payment-method'),
        ];
    }

    if (! $hasProduct) {
        $actions[] = [
            'title' => 'Ajouter votre premier produit',
            'description' => 'Créez une première fiche produit complète pour démarrer votre catalogue.',
            'badge' => 'Conseil',
            'tone' => 'green',
            'icon' => 'package-plus',
            'button' => 'Ajouter un produit',
            'url' => $routeUrl('vendor.add_product', [], '/vendeur/products/create'),
        ];
    }

    $actions[] = [
        'title' => 'Améliorer votre score boutique',
        'description' => 'Suivez les recommandations pour atteindre 100 % de complétude.',
        'badge' => 'Conseil',
        'tone' => 'green',
        'icon' => 'trending-up',
        'button' => 'Voir le profil',
        'url' => $routeUrl('vendor.shop.profile', [], '/vendeur/shop-profile'),
    ];

    $recommendedActions = collect($actions)->take(4)->values();

    $historyItems = collect();

    if (data_get($shop, 'created_at')) {
        $historyItems->push([
            'title' => 'Boutique créée',
            'description' => 'Votre boutique a été enregistrée sur OVANIE.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'created_at')),
            'tone' => 'blue',
            'icon' => 'store',
        ]);
    }

    if ($identityReady) {
        $historyItems->push([
            'title' => 'Pièce d’identité ajoutée',
            'description' => 'Le dossier d’identité du vendeur est disponible.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'updated_at') ?: now()),
            'tone' => 'green',
            'icon' => 'circle-check',
        ]);
    }

    if ($rccmReady) {
        $historyItems->push([
            'title' => 'RCCM ajouté',
            'description' => 'Le registre de commerce est présent dans le dossier de la boutique.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'updated_at') ?: now()),
            'tone' => 'green',
            'icon' => 'file-check-2',
        ]);
    }

    if ($taxReady) {
        $historyItems->push([
            'title' => 'Document fiscal soumis',
            'description' => $kycVerified
                ? 'Le document fiscal fait partie du dossier validé.'
                : 'Le document fiscal est disponible pour vérification.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'updated_at') ?: now()),
            'tone' => $kycVerified ? 'green' : 'orange',
            'icon' => 'clock-3',
        ]);
    }

    if ($logisticsReady) {
        $historyItems->push([
            'title' => 'Configuration logistique prête',
            'description' => 'Le mode logistique sélectionné est opérationnel.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'updated_at') ?: now()),
            'tone' => 'blue',
            'icon' => 'truck',
        ]);
    }

    if ($paymentReady) {
        $historyItems->push([
            'title' => 'Méthode de paiement configurée',
            'description' => 'Le compte vendeur est disponible pour les reversements.',
            'date' => \Carbon\Carbon::parse(data_get($shop, 'updated_at') ?: now()),
            'tone' => 'blue',
            'icon' => 'wallet-cards',
        ]);
    }

    $historyItems = $historyItems
        ->sortByDesc(fn ($item) => $item['date']->timestamp)
        ->take(6)
        ->values();

    $profileUrl = $routeUrl('vendor.shop.profile', [], '/vendeur/shop-profile');
    $editUrl = $routeUrl('vendor.shop.edit', [], '/vendeur/shop-profile/edit');
    $documentsUrl = $routeUrl('vendor.shop.documents', [], '/vendeur/shop-documents');
    $deliveryUrl = $routeUrl('vendor.delivery.index', [], '/vendeur/livraison');
@endphp

@section('styles')
<style>
    :root {
        --st-navy:#0b2f6b;
        --st-blue:#0f64ea;
        --st-orange:#ff5a0a;
        --st-green:#16a34a;
        --st-red:#ef4444;
        --st-purple:#6d4ce8;
        --st-text:#17366c;
        --st-muted:#7183a1;
        --st-line:#dfe7f1;
        --st-soft:#f7faff;
    }

    .st-page {
        max-width:1240px;
        margin:0 auto;
        padding:26px 28px 46px;
        color:var(--st-text);
    }

    .st-breadcrumb {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:14px;
        color:#8191aa;
        font-size:11px;
        font-weight:800;
        flex-wrap:wrap;
    }

    .st-breadcrumb a {
        color:var(--st-blue);
        text-decoration:none;
    }

    .st-breadcrumb svg {
        width:13px;
        height:13px;
    }

    .st-head {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:18px;
        margin-bottom:22px;
    }

    .st-head h1 {
        margin:0;
        color:var(--st-navy);
        font-size:clamp(31px,3vw,44px);
        line-height:1.05;
        font-weight:900;
        letter-spacing:-.035em;
    }

    .st-head p {
        margin:8px 0 0;
        color:#657999;
        font-size:13px;
        max-width:780px;
        line-height:1.55;
    }

    .st-head-actions {
        display:flex;
        gap:10px;
        flex:0 0 auto;
    }

    .st-btn {
        min-height:42px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:0 17px;
        border:1px solid #9eb3d2;
        border-radius:7px;
        background:#fff;
        color:var(--st-navy);
        font-size:11px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        transition:.18s ease;
    }

    .st-btn:hover {
        transform:translateY(-1px);
        border-color:var(--st-blue);
    }

    .st-btn svg {
        width:16px;
        height:16px;
    }

    .st-btn-orange {
        border-color:var(--st-orange);
        background:linear-gradient(90deg,#ff650d,#ff4f00);
        color:#fff;
        box-shadow:0 8px 18px rgba(255,90,10,.16);
    }

    .st-card {
        border:1px solid var(--st-line);
        border-radius:12px;
        background:#fff;
        box-shadow:0 10px 28px rgba(16,44,92,.035);
    }

    .st-overview-grid {
        display:grid;
        grid-template-columns:1.08fr 1fr 1fr 1fr;
        gap:14px;
        margin-bottom:18px;
    }

    .st-overview-card {
        min-height:154px;
        padding:17px;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
    }

    .st-overview-main {
        display:flex;
        align-items:flex-start;
        gap:12px;
    }

    .st-icon-circle {
        width:46px;
        height:46px;
        flex:0 0 46px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:var(--st-blue);
        background:#edf4ff;
    }

    .st-icon-circle.green {
        color:var(--st-green);
        background:#e8f8ed;
    }

    .st-icon-circle.orange {
        color:var(--st-orange);
        background:#fff0e5;
    }

    .st-icon-circle svg {
        width:22px;
        height:22px;
    }

    .st-overview-copy h3 {
        margin:0 0 7px;
        color:#244170;
        font-size:10px;
        font-weight:900;
    }

    .st-overview-value {
        color:var(--st-navy);
        font-size:11px;
        font-weight:900;
    }

    .st-overview-desc {
        margin:8px 0 0;
        color:#71819f;
        font-size:9px;
        line-height:1.45;
    }

    .st-mini-pill {
        display:inline-flex;
        align-items:center;
        width:max-content;
        min-height:25px;
        padding:0 9px;
        border-radius:999px;
        color:#14823d;
        background:#e6f8eb;
        font-size:8.7px;
        font-weight:900;
    }

    .st-mini-pill.orange {
        color:#e5650d;
        background:#fff0e5;
    }

    .st-mini-pill.blue {
        color:#1763df;
        background:#ebf2ff;
    }

    .st-mini-pill.red {
        color:#d93636;
        background:#feeaea;
    }

    .st-card-link {
        display:inline-flex;
        align-items:center;
        gap:6px;
        color:var(--st-blue);
        font-size:9.4px;
        font-weight:900;
        text-decoration:none;
    }

    .st-card-link svg {
        width:13px;
        height:13px;
    }

    .st-global-layout {
        display:grid;
        grid-template-columns:96px minmax(0,1fr);
        gap:12px;
        align-items:center;
    }

    .st-ring {
        --score:0;
        width:88px;
        height:88px;
        position:relative;
        display:grid;
        place-items:center;
        border-radius:50%;
        background:
            conic-gradient(
                var(--st-green) calc(var(--score) * 1%),
                #e7eef7 0
            );
    }

    .st-ring::after {
        content:'';
        position:absolute;
        inset:8px;
        border-radius:50%;
        background:#fff;
    }

    .st-ring strong {
        position:relative;
        z-index:2;
        color:var(--st-green);
        font-size:18px;
        font-weight:900;
    }

    .st-alert {
        min-height:62px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        margin-bottom:18px;
        padding:12px 16px;
        border:1px solid #ffb36f;
        border-radius:10px;
        background:linear-gradient(90deg,#fff7f0,#fff);
    }

    .st-alert-main {
        display:flex;
        align-items:center;
        gap:12px;
    }

    .st-alert-icon {
        width:36px;
        height:36px;
        flex:0 0 36px;
        display:grid;
        place-items:center;
        color:var(--st-orange);
    }

    .st-alert-icon svg {
        width:27px;
        height:27px;
    }

    .st-alert strong {
        display:block;
        color:#8f3f0d;
        font-size:11px;
        font-weight:900;
    }

    .st-alert p {
        margin:3px 0 0;
        color:#6f7590;
        font-size:9px;
    }

    .st-main-grid {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:18px;
        margin-bottom:18px;
    }

    .st-block {
        padding:18px 20px;
    }

    .st-section-title {
        display:flex;
        align-items:center;
        gap:8px;
        margin:0 0 16px;
        color:var(--st-navy);
        font-size:12px;
        font-weight:900;
    }

    .st-score-top {
        display:grid;
        grid-template-columns:132px minmax(0,1fr);
        gap:22px;
        align-items:center;
        margin-bottom:18px;
    }

    .st-large-ring {
        --score:0;
        width:128px;
        height:128px;
        position:relative;
        display:grid;
        place-items:center;
        border-radius:50%;
        background:
            conic-gradient(
                var(--st-blue) calc(var(--score) * 1%),
                #e8eef7 0
            );
    }

    .st-large-ring::after {
        content:'';
        position:absolute;
        inset:10px;
        border-radius:50%;
        background:#fff;
    }

    .st-large-ring strong {
        position:relative;
        z-index:2;
        color:var(--st-blue);
        font-size:26px;
        font-weight:900;
    }

    .st-score-copy strong {
        display:block;
        color:var(--st-green);
        font-size:13px;
        font-weight:900;
        margin-bottom:7px;
    }

    .st-score-copy p {
        margin:0 0 12px;
        color:#7183a1;
        font-size:9.4px;
        line-height:1.5;
    }

    .st-score-rows {
        display:grid;
        gap:11px;
    }

    .st-score-row {
        display:grid;
        grid-template-columns:150px minmax(0,1fr) 42px;
        gap:10px;
        align-items:center;
    }

    .st-score-label {
        display:flex;
        align-items:center;
        gap:7px;
        color:#3d587f;
        font-size:9.3px;
        font-weight:800;
    }

    .st-score-label svg {
        width:14px;
        height:14px;
        color:#3565ad;
    }

    .st-score-track {
        height:6px;
        overflow:hidden;
        border-radius:999px;
        background:#e8eef7;
    }

    .st-score-fill {
        height:100%;
        border-radius:inherit;
        background:var(--st-blue);
    }

    .st-score-number {
        text-align:right;
        color:#506a91;
        font-size:9px;
        font-weight:900;
    }

    .st-score-note {
        display:flex;
        align-items:center;
        gap:10px;
        margin-top:16px;
        padding:12px;
        border-radius:8px;
        background:#f4f8fe;
        color:#506989;
        font-size:9px;
        line-height:1.5;
    }

    .st-score-note svg {
        width:20px;
        height:20px;
        color:var(--st-blue);
        flex:0 0 20px;
    }

    .st-validation-list {
        display:grid;
    }

    .st-validation-row {
        min-height:43px;
        display:grid;
        grid-template-columns:26px minmax(0,1fr) auto;
        gap:8px;
        align-items:center;
        border-bottom:1px solid #e8edf4;
    }

    .st-validation-row:last-child {
        border-bottom:0;
    }

    .st-validation-icon {
        width:22px;
        height:22px;
        display:grid;
        place-items:center;
        border-radius:50%;
    }

    .st-validation-icon.valid {
        color:#12a04a;
        background:#e9f9ee;
    }

    .st-validation-icon.pending {
        color:#f07a19;
        background:#fff2e7;
    }

    .st-validation-icon.missing {
        color:#ef4444;
        background:#fff0f0;
    }

    .st-validation-icon svg {
        width:14px;
        height:14px;
    }

    .st-validation-label {
        color:#405b83;
        font-size:9.5px;
        font-weight:800;
    }

    .st-validation-state {
        min-height:24px;
        display:inline-flex;
        align-items:center;
        padding:0 8px;
        border-radius:5px;
        font-size:8.2px;
        font-weight:900;
        white-space:nowrap;
    }

    .st-validation-state.valid {
        color:#14823d;
        background:#e4f7ea;
    }

    .st-validation-state.pending {
        color:#e76a11;
        background:#fff0e5;
    }

    .st-validation-state.missing {
        color:#dc3434;
        background:#feeaea;
    }

    .st-actions-section {
        margin-bottom:18px;
    }

    .st-actions-title {
        margin:0 0 12px;
        color:var(--st-navy);
        font-size:12px;
        font-weight:900;
    }

    .st-actions-grid {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
    }

    .st-action-card {
        min-height:158px;
        padding:15px;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
    }

    .st-action-top {
        display:grid;
        grid-template-columns:40px minmax(0,1fr);
        gap:10px;
        align-items:start;
    }

    .st-action-icon {
        width:40px;
        height:40px;
        display:grid;
        place-items:center;
        border-radius:50%;
    }

    .st-action-icon.red {
        color:var(--st-red);
        background:#fff0f0;
    }

    .st-action-icon.blue {
        color:var(--st-blue);
        background:#edf4ff;
    }

    .st-action-icon.orange {
        color:var(--st-orange);
        background:#fff0e5;
    }

    .st-action-icon.green {
        color:var(--st-green);
        background:#e8f8ed;
    }

    .st-action-icon svg {
        width:20px;
        height:20px;
    }

    .st-action-card h3 {
        margin:0 0 5px;
        color:#17366d;
        font-size:9.8px;
        font-weight:900;
        line-height:1.35;
    }

    .st-action-card p {
        margin:9px 0 12px;
        color:#7183a1;
        font-size:8.5px;
        line-height:1.5;
    }

    .st-action-badge {
        display:inline-flex;
        align-items:center;
        width:max-content;
        min-height:22px;
        padding:0 7px;
        border-radius:5px;
        font-size:7.8px;
        font-weight:900;
    }

    .st-action-badge.red {
        color:#d93838;
        background:#feeaea;
    }

    .st-action-badge.blue {
        color:#1763df;
        background:#ebf2ff;
    }

    .st-action-badge.orange {
        color:#e5650d;
        background:#fff0e5;
    }

    .st-action-badge.green {
        color:#168444;
        background:#e5f8eb;
    }

    .st-action-button {
        min-height:34px;
        display:flex;
        align-items:center;
        justify-content:center;
        border:1px solid currentColor;
        border-radius:6px;
        background:#fff;
        color:var(--st-blue);
        font-size:8.7px;
        font-weight:900;
        text-decoration:none;
    }

    .st-action-button.red { color:var(--st-red); }
    .st-action-button.orange { color:var(--st-orange); }
    .st-action-button.green { color:var(--st-green); }

    .st-bottom-grid {
        display:grid;
        grid-template-columns:1fr 1.15fr;
        gap:18px;
        margin-bottom:18px;
    }

    .st-history-list {
        position:relative;
        display:grid;
        gap:0;
    }

    .st-history-item {
        display:grid;
        grid-template-columns:28px 1fr auto;
        gap:10px;
        align-items:start;
        min-height:60px;
        position:relative;
    }

    .st-history-item:not(:last-child)::before {
        content:'';
        position:absolute;
        left:13px;
        top:25px;
        bottom:-4px;
        width:1px;
        border-left:1px dashed #aebed4;
    }

    .st-history-dot {
        width:27px;
        height:27px;
        z-index:1;
        display:grid;
        place-items:center;
        border-radius:50%;
        background:#fff;
        border:1.5px solid var(--st-blue);
        color:var(--st-blue);
    }

    .st-history-dot.green {
        border-color:var(--st-green);
        color:var(--st-green);
    }

    .st-history-dot.orange {
        border-color:var(--st-orange);
        color:var(--st-orange);
    }

    .st-history-dot svg {
        width:14px;
        height:14px;
    }

    .st-history-copy strong {
        display:block;
        margin-bottom:3px;
        color:#17366d;
        font-size:9.3px;
        font-weight:900;
    }

    .st-history-copy p {
        margin:0;
        color:#7586a1;
        font-size:8.2px;
        line-height:1.45;
    }

    .st-history-date {
        color:#657c9f;
        font-size:8px;
        line-height:1.45;
        text-align:right;
        white-space:nowrap;
    }

    .st-benefits-grid {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .st-benefit {
        min-height:86px;
        display:grid;
        grid-template-columns:42px 1fr;
        gap:10px;
        align-items:center;
        padding:12px;
        border:1px solid #e3eaf3;
        border-radius:8px;
    }

    .st-benefit-icon {
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:var(--st-blue);
        background:#edf4ff;
    }

    .st-benefit-icon.orange {
        color:var(--st-orange);
        background:#fff0e5;
    }

    .st-benefit-icon.green {
        color:var(--st-green);
        background:#e8f8ed;
    }

    .st-benefit-icon.red {
        color:var(--st-red);
        background:#fff0f0;
    }

    .st-benefit-icon svg {
        width:20px;
        height:20px;
    }

    .st-benefit strong {
        display:block;
        margin-bottom:4px;
        color:#17366d;
        font-size:9.2px;
        font-weight:900;
    }

    .st-benefit p {
        margin:0;
        color:#7183a1;
        font-size:8px;
        line-height:1.45;
    }

    .st-benefit-note {
        display:flex;
        align-items:center;
        gap:10px;
        margin-top:10px;
        padding:11px 13px;
        border:1px solid #e2e9f2;
        border-radius:8px;
        color:#5c7294;
        font-size:8.5px;
        line-height:1.45;
    }

    .st-benefit-note svg {
        width:19px;
        height:19px;
        color:#f2a414;
        flex:0 0 19px;
    }

    .st-footer-actions {
        display:flex;
        justify-content:flex-end;
        gap:10px;
        padding-top:2px;
    }

    @media(max-width:1120px) {
        .st-overview-grid {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .st-actions-grid {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }
    }

    @media(max-width:820px) {
        .st-page {
            padding:18px 14px 30px;
        }

        .st-head {
            flex-direction:column;
        }

        .st-head-actions {
            width:100%;
        }

        .st-head-actions .st-btn {
            flex:1;
        }

        .st-main-grid,
        .st-bottom-grid {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:620px) {
        .st-overview-grid,
        .st-actions-grid,
        .st-benefits-grid {
            grid-template-columns:1fr;
        }

        .st-score-top {
            grid-template-columns:1fr;
            justify-items:center;
            text-align:center;
        }

        .st-score-row {
            grid-template-columns:1fr 50px;
        }

        .st-score-label {
            grid-column:1 / -1;
        }

        .st-alert {
            align-items:flex-start;
            flex-direction:column;
        }

        .st-footer-actions {
            flex-direction:column-reverse;
        }

        .st-footer-actions .st-btn {
            width:100%;
        }

        .st-history-item {
            grid-template-columns:28px 1fr;
        }

        .st-history-date {
            grid-column:2;
            text-align:left;
            margin-top:-8px;
            padding-bottom:12px;
        }
    }
</style>
@endsection

@section('content')
<div class="st-page">
    <nav class="st-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ $routeUrl('vendor.dashboard', [], '/vendeur/dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ $profileUrl }}">Boutique</a>
        <i data-lucide="chevron-right"></i>
        <span>Statut de la boutique</span>
    </nav>

    <div class="st-head">
        <div>
            <h1>Statut de la boutique</h1>
            <p>Suivez l’état de votre boutique, les éléments validés et les actions à compléter pour rester opérationnel sur OVANIE.</p>
        </div>

        <div class="st-head-actions">
            <a href="{{ $profileUrl }}" class="st-btn">
                <i data-lucide="eye"></i>
                Voir le profil
            </a>

            <a href="{{ $editUrl }}" class="st-btn st-btn-orange">
                <i data-lucide="pencil"></i>
                Compléter ma boutique
            </a>
        </div>
    </div>

    <section class="st-overview-grid">
        <article class="st-card st-overview-card">
            <div class="st-global-layout">
                <div class="st-ring" style="--score:{{ $overallScore }}">
                    <strong>{{ $overallScore }}%</strong>
                </div>

                <div class="st-overview-copy">
                    <h3>Statut global</h3>
                    <span class="st-mini-pill {{ $commercialOpen ? '' : 'red' }}">{{ $globalStatus }}</span>
                    <div class="st-overview-value" style="margin-top:8px;">
                        {{ $overallScore >= 90 ? 'Boutique prête' : ($overallScore >= 70 ? 'Presque prête' : 'À compléter') }}
                    </div>
                    <p class="st-overview-desc">
                        {{ $statusDescription ?? 'Suivez les éléments nécessaires au bon fonctionnement de votre boutique.' }}
                    </p>
                </div>
            </div>

            <a class="st-card-link" href="#score">
                Voir les détails
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="st-card st-overview-card">
            <div class="st-overview-main">
                <span class="st-icon-circle"><i data-lucide="eye"></i></span>

                <div class="st-overview-copy">
                    <h3>Visibilité</h3>
                    <span class="st-mini-pill {{ $commercialOpen && $canPublish ? '' : 'orange' }}">
                        {{ $visibilityText }}
                    </span>
                    <p class="st-overview-desc">
                        {{ $commercialOpen && $canPublish
                            ? 'Vos produits peuvent être visibles par les acheteurs.'
                            : 'Certains éléments limitent encore la visibilité complète de votre boutique.' }}
                    </p>
                </div>
            </div>

            <a class="st-card-link" href="{{ $profileUrl }}">
                Voir la visibilité
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="st-card st-overview-card">
            <div class="st-overview-main">
                <span class="st-icon-circle orange"><i data-lucide="shield-check"></i></span>

                <div class="st-overview-copy">
                    <h3>Conformité</h3>
                    <span class="st-mini-pill {{ $kycVerified ? '' : 'orange' }}">{{ $conformityText }}</span>
                    <p class="st-overview-desc">
                        {{ $kycVerified
                            ? 'Les éléments de conformité disponibles sont validés.'
                            : 'Les documents sont suivis séparément du statut commercial de la boutique.' }}
                    </p>
                </div>
            </div>

            <a class="st-card-link" href="{{ $documentsUrl }}">
                Voir le détail
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="st-card st-overview-card">
            <div class="st-overview-main">
                <span class="st-icon-circle"><i data-lucide="calendar-days"></i></span>

                <div class="st-overview-copy">
                    <h3>Dernière mise à jour</h3>
                    <div class="st-overview-value">{{ $updatedDate }}</div>
                    <p class="st-overview-desc">
                        Mise à jour par<br>
                        <strong style="color:#17366d;">{{ $updaterName }}</strong><br>
                        {{ $updatedHuman }}
                    </p>
                </div>
            </div>

            <a class="st-card-link" href="#history">
                Voir l’historique
                <i data-lucide="arrow-right"></i>
            </a>
        </article>
    </section>

    @if($incompleteCount > 0)
        <section class="st-alert">
            <div class="st-alert-main">
                <span class="st-alert-icon"><i data-lucide="triangle-alert"></i></span>

                <div>
                    <strong>Éléments à compléter</strong>
                    <p>
                        {{ $incompleteCount }} élément(s) restent à traiter pour optimiser et sécuriser votre boutique.
                    </p>
                </div>
            </div>

            <a href="{{ $editUrl }}" class="st-btn" style="border-color:#ff9b52;color:#e85c08;">
                Compléter maintenant
                <i data-lucide="arrow-right"></i>
            </a>
        </section>
    @endif

    <section class="st-main-grid" id="score">
        <article class="st-card st-block">
            <h2 class="st-section-title">Score de complétude</h2>

            <div class="st-score-top">
                <div class="st-large-ring" style="--score:{{ $overallScore }}">
                    <strong>{{ $overallScore }}%</strong>
                </div>

                <div class="st-score-copy">
                    <strong>
                        {{ $overallScore >= 90 ? 'Excellent !' : ($overallScore >= 70 ? 'Très bien !' : 'Continuez vos efforts') }}
                    </strong>

                    <p>
                        {{ $overallScore >= 100
                            ? 'Votre boutique est complètement renseignée.'
                            : 'Votre boutique progresse vers un profil complet et rassurant pour les acheteurs.' }}
                    </p>

                    <a href="{{ $editUrl }}" class="st-card-link">
                        Comment améliorer mon score ?
                        <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="st-score-rows">
                @foreach($scoreRows as $scoreRow)
                    <div class="st-score-row">
                        <span class="st-score-label">
                            <i data-lucide="{{ $scoreRow['icon'] }}"></i>
                            {{ $scoreRow['label'] }}
                        </span>

                        <span class="st-score-track">
                            <span class="st-score-fill" style="width:{{ $scoreRow['score'] }}%;"></span>
                        </span>

                        <span class="st-score-number">{{ $scoreRow['score'] }}%</span>
                    </div>
                @endforeach
            </div>

            <div class="st-score-note">
                <i data-lucide="info"></i>
                <span>Plus votre boutique est complète, plus elle inspire confiance et réduit les blocages opérationnels.</span>
            </div>
        </article>

        <article class="st-card st-block">
            <h2 class="st-section-title">Résumé des validations</h2>

            <div class="st-validation-list">
                @foreach($validationRows as $validation)
                    <div class="st-validation-row">
                        <span class="st-validation-icon {{ $validation['state'] }}">
                            <i data-lucide="{{ $validation['icon'] }}"></i>
                        </span>

                        <span class="st-validation-label">{{ $validation['label'] }}</span>

                        <span class="st-validation-state {{ $validation['state'] }}">
                            {{ $validation['text'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <a href="{{ $editUrl }}" class="st-card-link" style="margin-top:16px;">
                Voir tous les détails
                <i data-lucide="arrow-right"></i>
            </a>
        </article>
    </section>

    <section class="st-actions-section">
        <h2 class="st-actions-title">Actions recommandées</h2>

        <div class="st-actions-grid">
            @foreach($recommendedActions as $action)
                <article class="st-card st-action-card">
                    <div>
                        <div class="st-action-top">
                            <span class="st-action-icon {{ $action['tone'] }}">
                                <i data-lucide="{{ $action['icon'] }}"></i>
                            </span>

                            <div>
                                <h3>{{ $action['title'] }}</h3>
                                <span class="st-action-badge {{ $action['tone'] }}">{{ $action['badge'] }}</span>
                            </div>
                        </div>

                        <p>{{ $action['description'] }}</p>
                    </div>

                    <a href="{{ $action['url'] }}" class="st-action-button {{ $action['tone'] }}">
                        {{ $action['button'] }}
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="st-bottom-grid">
        <article class="st-card st-block" id="history">
            <h2 class="st-section-title">Historique des statuts</h2>

            <div class="st-history-list">
                @forelse($historyItems as $history)
                    <div class="st-history-item">
                        <span class="st-history-dot {{ $history['tone'] }}">
                            <i data-lucide="{{ $history['icon'] }}"></i>
                        </span>

                        <div class="st-history-copy">
                            <strong>{{ $history['title'] }}</strong>
                            <p>{{ $history['description'] }}</p>
                        </div>

                        <span class="st-history-date">
                            {{ $history['date']->format('d/m/Y') }}<br>
                            {{ $history['date']->format('H:i') }}
                        </span>
                    </div>
                @empty
                    <p style="color:#7183a1;font-size:9px;">Aucun événement disponible pour le moment.</p>
                @endforelse
            </div>

            <a href="{{ $profileUrl }}" class="st-card-link" style="margin-top:10px;">
                Voir le profil complet
                <i data-lucide="arrow-right"></i>
            </a>
        </article>

        <article class="st-card st-block">
            <h2 class="st-section-title">Avantages d’une boutique complète</h2>

            <div class="st-benefits-grid">
                <div class="st-benefit">
                    <span class="st-benefit-icon orange"><i data-lucide="shield-check"></i></span>
                    <div>
                        <strong>Plus de confiance</strong>
                        <p>Les acheteurs font davantage confiance aux boutiques bien renseignées et conformes.</p>
                    </div>
                </div>

                <div class="st-benefit">
                    <span class="st-benefit-icon"><i data-lucide="eye"></i></span>
                    <div>
                        <strong>Meilleure visibilité</strong>
                        <p>Une boutique opérationnelle facilite la présence de ses produits dans les parcours d’achat.</p>
                    </div>
                </div>

                <div class="st-benefit">
                    <span class="st-benefit-icon red"><i data-lucide="lock-keyhole"></i></span>
                    <div>
                        <strong>Moins de blocages</strong>
                        <p>Une configuration complète limite les restrictions liées aux documents et à la logistique.</p>
                    </div>
                </div>

                <div class="st-benefit">
                    <span class="st-benefit-icon green"><i data-lucide="wallet-cards"></i></span>
                    <div>
                        <strong>Reversements facilités</strong>
                        <p>Un compte vendeur bien configuré réduit les retards de traitement des reversements.</p>
                    </div>
                </div>
            </div>

            <div class="st-benefit-note">
                <i data-lucide="star"></i>
                <span>Une boutique complète, conforme et logistiquement prête renforce sa crédibilité sur OVANIE.</span>
            </div>

            <a href="{{ $profileUrl }}" class="st-card-link" style="margin-top:14px;">
                Voir le profil de la boutique
                <i data-lucide="arrow-right"></i>
            </a>
        </article>
    </section>

    <div class="st-footer-actions">
        <a href="{{ $profileUrl }}" class="st-btn">Retour au profil</a>

        <a href="{{ $editUrl }}" class="st-btn st-btn-orange">
            <i data-lucide="pencil"></i>
            Compléter ma boutique
        </a>
    </div>
</div>
@endsection
