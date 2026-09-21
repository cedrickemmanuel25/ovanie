@extends('layouts.vendor')

@section('title', 'Documents de la boutique | OVANIE')

@php
    $identityReady = (bool) (
        ($documents['identity_pdf']['uploaded'] ?? false)
        || (
            ($documents['identity_front']['uploaded'] ?? false)
            && ($documents['identity_back']['uploaded'] ?? false)
        )
    );

    $rccmReady = (bool) ($documents['rccm']['uploaded'] ?? false);
    $taxReady = (bool) ($documents['tax']['uploaded'] ?? false);
    $selfieReady = (bool) ($documents['selfie']['uploaded'] ?? false);

    $kycValue = strtolower((string) ($shop->kyc_status ?? ''));
    $kycApproved = in_array($kycValue, ['approved', 'verified', 'validated', 'validé', 'valide'], true);
    $kycPending = in_array($kycValue, ['pending', 'review', 'in_review', 'en_verification'], true);

    $statusFor = static function (bool $uploaded) use ($kycApproved, $kycPending): array {
        if (! $uploaded) {
            return [
                'label' => 'À compléter',
                'tone' => 'missing',
                'icon' => 'triangle-alert',
            ];
        }

        if ($kycApproved) {
            return [
                'label' => 'Validé',
                'tone' => 'valid',
                'icon' => 'circle-check',
            ];
        }

        return [
            'label' => $kycPending ? 'En vérification' : 'Soumis',
            'tone' => 'pending',
            'icon' => 'clock-3',
        ];
    };

    $documentRows = [
        [
            'type' => 'rccm',
            'label' => 'RCCM',
            'description' => 'Extrait du Registre du Commerce et du Crédit Mobilier.',
            'icon' => 'file-text',
            'ready' => $rccmReady,
            'accept' => '.pdf,.jpg,.jpeg,.png',
        ],
        [
            'type' => 'identity_pdf',
            'label' => 'Pièce d’identité du représentant',
            'description' => 'Carte nationale d’identité, passeport ou titre de séjour.',
            'icon' => 'badge-check',
            'ready' => $identityReady,
            'accept' => '.pdf',
        ],
        [
            'type' => 'tax',
            'label' => 'Attestation fiscale',
            'description' => 'NIF, attestation fiscale ou document fiscal valide.',
            'icon' => 'file-check-2',
            'ready' => $taxReady,
            'accept' => '.pdf,.jpg,.jpeg,.png',
        ],
        [
            'type' => 'selfie',
            'label' => 'Selfie vendeur',
            'description' => 'Photo claire du représentant principal de la boutique.',
            'icon' => 'user-round-check',
            'ready' => $selfieReady,
            'accept' => '.jpg,.jpeg,.png',
        ],
    ];

    $requiredIncomplete = collect($documentRows)->where('ready', false)->count();

    $historyRows = collect($documentRows)
        ->filter(fn ($row) => $row['ready'])
        ->map(function ($row) use ($shop, $statusFor) {
            return [
                'date' => $shop->updated_at,
                'label' => $row['label'],
                'action' => 'Soumis',
                'status' => $statusFor(true),
                'actor' => 'Vous',
            ];
        })
        ->values();

    $defaultUploadType = old('document_type', 'rccm');

    $supportUrl = \Illuminate\Support\Facades\Route::has('contact.index')
        ? route('contact.index')
        : url('/contact');
@endphp

@section('styles')
<style>
    :root {
        --doc-navy:#0b2f6b;
        --doc-blue:#0f64ea;
        --doc-orange:#ff5a0a;
        --doc-green:#16a34a;
        --doc-red:#ef4444;
        --doc-text:#17366c;
        --doc-muted:#7183a1;
        --doc-line:#dfe7f1;
        --doc-soft:#f7faff;
    }

    .doc-page {
        max-width:1240px;
        margin:0 auto;
        padding:26px 28px 46px;
        color:var(--doc-text);
    }

    .doc-breadcrumb {
        display:flex;
        align-items:center;
        gap:9px;
        margin-bottom:14px;
        color:#8191aa;
        font-size:11px;
        font-weight:800;
        flex-wrap:wrap;
    }

    .doc-breadcrumb a {
        color:var(--doc-blue);
        text-decoration:none;
    }

    .doc-breadcrumb svg {
        width:13px;
        height:13px;
    }

    .doc-head {
        margin-bottom:18px;
    }

    .doc-head h1 {
        margin:0;
        color:var(--doc-navy);
        font-size:clamp(31px,3vw,44px);
        line-height:1.05;
        font-weight:900;
        letter-spacing:-.035em;
    }

    .doc-head p {
        margin:8px 0 0;
        color:#657999;
        font-size:13px;
        line-height:1.5;
    }

    .doc-card {
        border:1px solid var(--doc-line);
        border-radius:12px;
        background:#fff;
        box-shadow:0 10px 28px rgba(16,44,92,.035);
    }

    .doc-alert {
        min-height:72px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        margin-bottom:18px;
        padding:14px 18px;
        border:1px solid #ffb877;
        border-radius:10px;
        background:linear-gradient(90deg,#fff8f1,#fff);
    }

    .doc-alert-main {
        display:flex;
        align-items:center;
        gap:14px;
    }

    .doc-alert-icon {
        width:42px;
        height:42px;
        flex:0 0 42px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:var(--doc-orange);
        border:2px solid var(--doc-orange);
    }

    .doc-alert-icon svg {
        width:22px;
        height:22px;
    }

    .doc-alert strong {
        display:block;
        color:#17366c;
        font-size:11px;
        font-weight:900;
    }

    .doc-alert p {
        margin:4px 0 0;
        color:#6e809e;
        font-size:9px;
        line-height:1.5;
    }

    .doc-alert-count {
        min-height:30px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0 14px;
        border:1px solid #ffa866;
        border-radius:999px;
        color:#ee5d08;
        background:#fff;
        font-size:9px;
        font-weight:900;
        white-space:nowrap;
    }

    .doc-layout {
        display:grid;
        grid-template-columns:minmax(0,1fr) 310px;
        gap:18px;
        align-items:start;
    }

    .doc-main-stack,
    .doc-side-stack {
        display:grid;
        gap:18px;
    }

    .doc-panel {
        padding:18px 20px;
    }

    .doc-panel-title {
        margin:0 0 16px;
        color:var(--doc-navy);
        font-size:12px;
        font-weight:900;
    }

    .doc-table-head,
    .doc-row {
        display:grid;
        grid-template-columns:minmax(0,1.55fr) 112px 150px 198px 24px;
        gap:10px;
        align-items:center;
    }

    .doc-table-head {
        min-height:35px;
        padding:0 10px;
        color:#667b9b;
        font-size:8px;
        font-weight:900;
        text-transform:uppercase;
    }

    .doc-list {
        overflow:hidden;
        border:1px solid #e2eaf3;
        border-radius:9px;
    }

    .doc-row {
        min-height:74px;
        padding:9px 10px;
        border-bottom:1px solid #e8edf4;
    }

    .doc-row:last-child {
        border-bottom:0;
    }

    .doc-name {
        display:grid;
        grid-template-columns:44px minmax(0,1fr);
        gap:11px;
        align-items:center;
        min-width:0;
    }

    .doc-icon {
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border-radius:8px;
        color:#1d63e0;
        background:#edf4ff;
    }

    .doc-icon.orange {
        color:var(--doc-orange);
        background:#fff0e5;
    }

    .doc-icon.red {
        color:var(--doc-red);
        background:#fff0f0;
    }

    .doc-icon svg {
        width:20px;
        height:20px;
    }

    .doc-copy {
        min-width:0;
    }

    .doc-copy strong {
        display:block;
        margin-bottom:3px;
        color:#14346c;
        font-size:9.7px;
        font-weight:900;
    }

    .doc-copy span {
        display:block;
        color:#7586a1;
        font-size:8px;
        line-height:1.45;
    }

    .doc-status {
        display:inline-flex;
        align-items:center;
        gap:5px;
        width:max-content;
        min-height:25px;
        padding:0 8px;
        border-radius:5px;
        font-size:8px;
        font-weight:900;
        white-space:nowrap;
    }

    .doc-status svg {
        width:12px;
        height:12px;
    }

    .doc-status.valid {
        color:#168542;
        background:#e5f8eb;
    }

    .doc-status.pending {
        color:#1763df;
        background:#ebf2ff;
    }

    .doc-status.missing {
        color:#e45b0d;
        background:#fff0e5;
    }

    .doc-date {
        color:#3d587f;
        font-size:8.5px;
        line-height:1.45;
    }

    .doc-date span {
        display:block;
        color:#8190a7;
        font-size:7.8px;
    }

    .doc-actions {
        display:flex;
        align-items:center;
        gap:7px;
    }

    .doc-btn {
        min-height:34px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        padding:0 13px;
        border:1px solid #cad7e8;
        border-radius:6px;
        background:#fff;
        color:var(--doc-navy);
        font-size:8.5px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        white-space:nowrap;
    }

    .doc-btn:hover {
        border-color:var(--doc-blue);
        color:var(--doc-blue);
    }

    .doc-btn.orange {
        border-color:var(--doc-orange);
        background:linear-gradient(90deg,#ff650d,#ff4f00);
        color:#fff;
    }

    .doc-btn.ghost {
        color:#4f668c;
    }

    .doc-more {
        width:24px;
        height:24px;
        display:grid;
        place-items:center;
        border:0;
        background:transparent;
        color:#5d7194;
        cursor:pointer;
    }

    .doc-more svg {
        width:14px;
        height:14px;
    }

    .doc-upload-grid {
        display:grid;
        grid-template-columns:1fr 1.2fr;
        gap:18px;
        align-items:stretch;
    }

    .doc-dropzone {
        min-height:160px;
        position:relative;
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        padding:18px;
        border:1.5px dashed #88a6d4;
        border-radius:8px;
        background:#fbfdff;
        text-align:center;
        cursor:pointer;
        transition:.18s ease;
    }

    .doc-dropzone:hover,
    .doc-dropzone.is-dragover {
        border-color:var(--doc-blue);
        background:#f3f8ff;
    }

    .doc-dropzone input {
        position:absolute;
        inset:0;
        width:100%;
        height:100%;
        opacity:0;
        cursor:pointer;
    }

    .doc-dropzone svg {
        width:48px;
        height:48px;
        margin-bottom:10px;
        color:#143c84;
    }

    .doc-dropzone strong {
        color:#17366c;
        font-size:9.2px;
        font-weight:900;
    }

    .doc-dropzone strong span {
        color:var(--doc-blue);
    }

    .doc-dropzone small {
        display:block;
        margin-top:5px;
        color:#7587a3;
        font-size:8px;
    }

    .doc-file-selected {
        margin-top:7px;
        color:var(--doc-green);
        font-size:8.4px;
        font-weight:900;
        word-break:break-word;
    }

    .doc-form-fields {
        display:grid;
        gap:12px;
        align-content:start;
    }

    .doc-field label {
        display:block;
        margin-bottom:6px;
        color:#466087;
        font-size:8.5px;
        font-weight:900;
    }

    .doc-required {
        color:#e54b4b;
    }

    .doc-select,
    .doc-input {
        width:100%;
        min-height:42px;
        padding:0 12px;
        border:1px solid #cfdaea;
        border-radius:7px;
        background:#fff;
        color:#17366c;
        font:inherit;
        font-size:10px;
        outline:none;
    }

    .doc-select {
        appearance:none;
        padding-right:38px;
    }

    .doc-select-wrap {
        position:relative;
    }

    .doc-select-wrap svg {
        position:absolute;
        right:12px;
        top:50%;
        width:15px;
        height:15px;
        transform:translateY(-50%);
        color:#56709a;
        pointer-events:none;
    }

    .doc-select:focus,
    .doc-input:focus {
        border-color:var(--doc-blue);
        box-shadow:0 0 0 3px rgba(15,100,234,.08);
    }

    .doc-identity-scan {
        display:none;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .doc-identity-scan.is-visible {
        display:grid;
    }

    .doc-scan-box {
        position:relative;
        min-height:72px;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:10px;
        border:1px dashed #9eb4d4;
        border-radius:7px;
        background:#fbfdff;
        color:#516b91;
        font-size:8.2px;
        font-weight:900;
        text-align:center;
    }

    .doc-scan-box input {
        position:absolute;
        inset:0;
        width:100%;
        height:100%;
        opacity:0;
        cursor:pointer;
    }

    .doc-form-actions {
        display:flex;
        justify-content:flex-end;
        gap:9px;
        margin-top:2px;
    }

    .doc-side-card {
        padding:18px 20px;
    }

    .doc-side-title {
        display:flex;
        align-items:center;
        gap:9px;
        margin:0 0 17px;
        color:var(--doc-navy);
        font-size:12px;
        font-weight:900;
    }

    .doc-requirements {
        display:grid;
        gap:16px;
    }

    .doc-requirement {
        display:grid;
        grid-template-columns:40px minmax(0,1fr);
        gap:10px;
        align-items:start;
    }

    .doc-requirement-icon {
        width:38px;
        height:38px;
        display:grid;
        place-items:center;
        border-radius:50%;
        color:var(--doc-blue);
        background:#edf4ff;
    }

    .doc-requirement-icon svg {
        width:18px;
        height:18px;
    }

    .doc-requirement strong {
        display:block;
        margin-bottom:4px;
        color:#17366c;
        font-size:9px;
        font-weight:900;
    }

    .doc-requirement p {
        margin:0;
        color:#7183a1;
        font-size:8.2px;
        line-height:1.5;
    }

    .doc-support p {
        margin:0 0 14px;
        color:#7183a1;
        font-size:8.7px;
        line-height:1.55;
    }

    .doc-support .doc-btn {
        width:100%;
        min-height:38px;
    }

    .doc-history {
        margin-top:18px;
        padding:0;
        overflow:hidden;
    }

    .doc-history-head {
        min-height:54px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:0 18px;
        border-bottom:1px solid #e6edf5;
    }

    .doc-history-head h2 {
        margin:0;
        color:var(--doc-navy);
        font-size:12px;
        font-weight:900;
    }

    .doc-history-table {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
    }

    .doc-history-table th,
    .doc-history-table td {
        padding:12px 16px;
        border-bottom:1px solid #e9eef5;
        text-align:left;
    }

    .doc-history-table th {
        color:#617796;
        background:#fbfcfe;
        font-size:8px;
        font-weight:900;
        text-transform:uppercase;
    }

    .doc-history-table td {
        color:#294875;
        font-size:8.8px;
    }

    .doc-history-table tbody tr:last-child td {
        border-bottom:0;
    }

    .doc-empty {
        padding:28px 16px;
        color:#8191aa;
        font-size:9px;
        text-align:center;
    }

    .doc-modal {
        position:fixed;
        inset:0;
        z-index:1000;
        display:none;
        align-items:center;
        justify-content:center;
        padding:20px;
        background:rgba(17,43,82,.55);
        backdrop-filter:blur(3px);
    }

    .doc-modal.is-open {
        display:flex;
    }

    .doc-modal-card {
        width:min(480px,100%);
        padding:22px;
        border-radius:13px;
        background:#fff;
        box-shadow:0 24px 70px rgba(4,26,65,.25);
    }

    .doc-modal-head {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        margin-bottom:16px;
    }

    .doc-modal-head h3 {
        margin:0;
        color:var(--doc-navy);
        font-size:18px;
        font-weight:900;
    }

    .doc-modal-close {
        width:34px;
        height:34px;
        display:grid;
        place-items:center;
        border:0;
        border-radius:7px;
        background:#f3f6fa;
        color:#17366c;
        cursor:pointer;
    }

    .doc-modal-close svg {
        width:17px;
        height:17px;
    }

    .doc-modal-content {
        display:grid;
        gap:10px;
    }

    .doc-modal-row {
        display:grid;
        grid-template-columns:130px 1fr;
        gap:10px;
        padding:10px 0;
        border-bottom:1px solid #edf1f6;
    }

    .doc-modal-row:last-child {
        border-bottom:0;
    }

    .doc-modal-row span {
        color:#7788a2;
        font-size:9px;
    }

    .doc-modal-row strong {
        color:#17366c;
        font-size:9.5px;
        word-break:break-word;
    }

    .doc-message {
        margin-bottom:14px;
        padding:12px 14px;
        border-radius:8px;
        font-size:10.5px;
        font-weight:700;
    }

    .doc-message.success {
        border:1px solid #bde8cc;
        background:#edf9f1;
        color:#136b35;
    }

    .doc-message.error {
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b42318;
    }

    @media(max-width:1100px) {
        .doc-layout {
            grid-template-columns:1fr;
        }

        .doc-side-stack {
            grid-template-columns:1fr 1fr;
        }
    }

    @media(max-width:900px) {
        .doc-table-head {
            display:none;
        }

        .doc-row {
            grid-template-columns:minmax(0,1fr) auto;
            gap:10px 16px;
        }

        .doc-status,
        .doc-date,
        .doc-actions {
            grid-column:1 / -1;
        }

        .doc-actions {
            justify-content:flex-start;
        }

        .doc-more {
            position:absolute;
            right:12px;
        }

        .doc-row {
            position:relative;
        }

        .doc-upload-grid {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:700px) {
        .doc-page {
            padding:18px 14px 30px;
        }

        .doc-alert {
            align-items:flex-start;
            flex-direction:column;
        }

        .doc-side-stack {
            grid-template-columns:1fr;
        }

        .doc-identity-scan {
            grid-template-columns:1fr;
        }

        .doc-history-table th:nth-child(3),
        .doc-history-table td:nth-child(3),
        .doc-history-table th:nth-child(5),
        .doc-history-table td:nth-child(5) {
            display:none;
        }
    }
</style>
@endsection

@section('content')
<div class="doc-page">
    <nav class="doc-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.shop.profile') }}">Paramètres boutique</a>
        <i data-lucide="chevron-right"></i>
        <span>Documents de la boutique</span>
    </nav>

    <header class="doc-head">
        <h1>Documents de la boutique</h1>
        <p>Téléversez et suivez les documents nécessaires à la validation et au maintien de votre boutique.</p>
    </header>

    @if(session('success'))
        <div class="doc-message success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="doc-message error">
            <strong>Le document n’a pas été envoyé.</strong>
            <div style="margin-top:5px;">{{ $errors->first() }}</div>
        </div>
    @endif

    <section class="doc-alert">
        <div class="doc-alert-main">
            <span class="doc-alert-icon"><i data-lucide="info"></i></span>

            <div>
                <strong>Certains documents sont requis pour garder votre boutique conforme.</strong>
                <p>Assurez-vous que vos documents sont à jour et lisibles afin de faciliter leur vérification.</p>
            </div>
        </div>

        <span class="doc-alert-count">
            {{ $requiredIncomplete }} à compléter
        </span>
    </section>

    <div class="doc-layout">
        <main class="doc-main-stack">
            <section class="doc-card doc-panel">
                <h2 class="doc-panel-title">Documents obligatoires</h2>

                <div class="doc-table-head">
                    <span>Document</span>
                    <span>Statut</span>
                    <span>Mise à jour</span>
                    <span>Actions</span>
                    <span></span>
                </div>

                <div class="doc-list">
                    @foreach($documentRows as $row)
                        @php
                            $state = $statusFor($row['ready']);
                            $iconTone = $state['tone'] === 'missing' ? 'red' : ($state['tone'] === 'pending' ? 'orange' : '');
                        @endphp

                        <article
                            class="doc-row"
                            data-document-row
                            data-type="{{ $row['type'] }}"
                            data-label="{{ $row['label'] }}"
                            data-description="{{ $row['description'] }}"
                            data-status="{{ $state['label'] }}"
                            data-date="{{ $row['ready'] && $shop->updated_at ? $shop->updated_at->format('d/m/Y H:i') : 'Non disponible' }}"
                        >
                            <div class="doc-name">
                                <span class="doc-icon {{ $iconTone }}">
                                    <i data-lucide="{{ $row['icon'] }}"></i>
                                </span>

                                <div class="doc-copy">
                                    <strong>{{ $row['label'] }}</strong>
                                    <span>{{ $row['description'] }}</span>
                                </div>
                            </div>

                            <span class="doc-status {{ $state['tone'] }}">
                                <i data-lucide="{{ $state['icon'] }}"></i>
                                {{ $state['label'] }}
                            </span>

                            <div class="doc-date">
                                @if($row['ready'])
                                    Mis à jour le
                                    <span>{{ optional($shop->updated_at)->format('d/m/Y') }}</span>
                                @else
                                    —
                                @endif
                            </div>

                            <div class="doc-actions">
                                @if($row['ready'])
                                    <button class="doc-btn ghost" type="button" data-view-document>Voir</button>
                                @endif

                                <button
                                    class="doc-btn {{ $row['ready'] ? '' : 'orange' }}"
                                    type="button"
                                    data-select-document="{{ $row['type'] }}"
                                >
                                    {{ $row['ready'] ? 'Remplacer' : 'Téléverser' }}
                                </button>
                            </div>

                            <button class="doc-more" type="button" data-view-document aria-label="Voir les détails">
                                <i data-lucide="ellipsis-vertical"></i>
                            </button>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="doc-card doc-panel" id="uploadDocumentPanel">
                <h2 class="doc-panel-title">Ajouter un document</h2>

                <form
                    method="POST"
                    action="{{ route('vendor.shop.documents.update') }}"
                    enctype="multipart/form-data"
                    id="documentUploadForm"
                >
                    @csrf

                    <div class="doc-upload-grid">
                        <label class="doc-dropzone" id="documentDropzone">
                            <input
                                id="documentFile"
                                type="file"
                                name="document_file"
                                accept=".pdf,.jpg,.jpeg,.png"
                            >

                            <i data-lucide="cloud-upload"></i>

                            <strong>
                                Glissez-déposez votre fichier ici<br>
                                <span>ou cliquez pour parcourir</span>
                            </strong>

                            <small>Formats acceptés : PDF, JPG, PNG · 5 Mo maximum</small>

                            <span class="doc-file-selected" id="documentFileName"></span>
                        </label>

                        <div class="doc-form-fields">
                            <div class="doc-field">
                                <label for="documentType">Type de document <span class="doc-required">*</span></label>

                                <div class="doc-select-wrap">
                                    <select
                                        class="doc-select"
                                        id="documentType"
                                        name="document_type"
                                        required
                                    >
                                        <option value="rccm" @selected($defaultUploadType === 'rccm')>RCCM</option>
                                        <option value="identity_pdf" @selected($defaultUploadType === 'identity_pdf')>Pièce d’identité — PDF</option>
                                        <option value="identity_scan" @selected($defaultUploadType === 'identity_scan')>Pièce d’identité — recto / verso</option>
                                        <option value="tax" @selected($defaultUploadType === 'tax')>Attestation fiscale</option>
                                        <option value="selfie" @selected($defaultUploadType === 'selfie')>Selfie vendeur</option>
                                    </select>

                                    <i data-lucide="chevron-down"></i>
                                </div>
                            </div>

                            <div class="doc-field">
                                <label for="documentName">Nom du document</label>
                                <input
                                    class="doc-input"
                                    id="documentName"
                                    type="text"
                                    name="document_name"
                                    value="{{ old('document_name') }}"
                                    placeholder="Ex. Attestation fiscale - juillet 2026"
                                    maxlength="120"
                                >
                            </div>

                            <div class="doc-identity-scan" id="identityScanFields">
                                <label class="doc-scan-box">
                                    <input
                                        type="file"
                                        name="identity_file_front"
                                        accept=".jpg,.jpeg,.png"
                                    >
                                    Recto de la pièce
                                </label>

                                <label class="doc-scan-box">
                                    <input
                                        type="file"
                                        name="identity_file_back"
                                        accept=".jpg,.jpeg,.png"
                                    >
                                    Verso de la pièce
                                </label>
                            </div>

                            <div class="doc-form-actions">
                                <button class="doc-btn" type="reset" id="resetDocumentForm">Annuler</button>

                                <button class="doc-btn orange" type="submit">
                                    <i data-lucide="send"></i>
                                    Envoyer le document
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </main>

        <aside class="doc-side-stack">
            <section class="doc-card doc-side-card">
                <h2 class="doc-side-title">Exigences de validation</h2>

                <div class="doc-requirements">
                    <div class="doc-requirement">
                        <span class="doc-requirement-icon"><i data-lucide="eye"></i></span>
                        <div>
                            <strong>Fichiers lisibles</strong>
                            <p>Les documents doivent être nets, complets et non tronqués.</p>
                        </div>
                    </div>

                    <div class="doc-requirement">
                        <span class="doc-requirement-icon"><i data-lucide="file-text"></i></span>
                        <div>
                            <strong>Formats acceptés</strong>
                            <p>PDF, JPG et PNG selon le type de document.</p>
                        </div>
                    </div>

                    <div class="doc-requirement">
                        <span class="doc-requirement-icon"><i data-lucide="cloud-upload"></i></span>
                        <div>
                            <strong>Taille maximale</strong>
                            <p>5 Mo par fichier, sauf le PDF d’identité qui peut atteindre 10 Mo.</p>
                        </div>
                    </div>

                    <div class="doc-requirement">
                        <span class="doc-requirement-icon"><i data-lucide="user-round"></i></span>
                        <div>
                            <strong>Nom du titulaire</strong>
                            <p>Les documents doivent correspondre au représentant ou aux informations de la boutique.</p>
                        </div>
                    </div>

                    <div class="doc-requirement">
                        <span class="doc-requirement-icon"><i data-lucide="calendar-days"></i></span>
                        <div>
                            <strong>Documents à jour</strong>
                            <p>Les documents expirés ou incohérents peuvent retarder la vérification du dossier.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="doc-card doc-side-card doc-support">
                <h2 class="doc-side-title">
                    <i data-lucide="headphones"></i>
                    Besoin d’aide ?
                </h2>

                <p>En cas de problème avec vos documents ou pour une question sur la vérification, contactez le support OVANIE.</p>

                <a href="{{ $supportUrl }}" class="doc-btn orange">
                    Contacter le support
                </a>
            </section>
        </aside>
    </div>

    <section class="doc-card doc-history">
        <div class="doc-history-head">
            <h2>Historique des documents</h2>

            <button class="doc-btn ghost" type="button">
                Voir tout l’historique
            </button>
        </div>

        @if($historyRows->isNotEmpty())
            <table class="doc-history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Action</th>
                        <th>Statut</th>
                        <th>Modifié par</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($historyRows as $history)
                        <tr>
                            <td>
                                {{ $history['date'] ? $history['date']->format('d/m/Y - H:i') : '—' }}
                            </td>

                            <td><strong>{{ $history['label'] }}</strong></td>
                            <td>{{ $history['action'] }}</td>

                            <td>
                                <span class="doc-status {{ $history['status']['tone'] }}">
                                    <i data-lucide="{{ $history['status']['icon'] }}"></i>
                                    {{ $history['status']['label'] }}
                                </span>
                            </td>

                            <td>{{ $history['actor'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="doc-empty">Aucun document n’a encore été enregistré.</div>
        @endif
    </section>
</div>

<div class="doc-modal" id="documentDetailsModal" aria-hidden="true">
    <div class="doc-modal-card" role="dialog" aria-modal="true" aria-labelledby="documentModalTitle">
        <div class="doc-modal-head">
            <h3 id="documentModalTitle">Détail du document</h3>

            <button class="doc-modal-close" type="button" data-close-document-modal aria-label="Fermer">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="doc-modal-content">
            <div class="doc-modal-row">
                <span>Document</span>
                <strong id="modalDocumentLabel">—</strong>
            </div>

            <div class="doc-modal-row">
                <span>Description</span>
                <strong id="modalDocumentDescription">—</strong>
            </div>

            <div class="doc-modal-row">
                <span>Statut</span>
                <strong id="modalDocumentStatus">—</strong>
            </div>

            <div class="doc-modal-row">
                <span>Dernière mise à jour</span>
                <strong id="modalDocumentDate">—</strong>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('documentType');
    const documentFile = document.getElementById('documentFile');
    const fileName = document.getElementById('documentFileName');
    const scanFields = document.getElementById('identityScanFields');
    const dropzone = document.getElementById('documentDropzone');
    const uploadPanel = document.getElementById('uploadDocumentPanel');

    const modal = document.getElementById('documentDetailsModal');
    const modalLabel = document.getElementById('modalDocumentLabel');
    const modalDescription = document.getElementById('modalDocumentDescription');
    const modalStatus = document.getElementById('modalDocumentStatus');
    const modalDate = document.getElementById('modalDocumentDate');

    const acceptMap = {
        rccm: '.pdf,.jpg,.jpeg,.png',
        identity_pdf: '.pdf',
        identity_scan: '',
        tax: '.pdf,.jpg,.jpeg,.png',
        selfie: '.jpg,.jpeg,.png'
    };

    function updateUploadMode() {
        if (!typeSelect || !scanFields || !documentFile || !dropzone) {
            return;
        }

        const value = typeSelect.value;
        const scanMode = value === 'identity_scan';

        scanFields.classList.toggle('is-visible', scanMode);
        documentFile.disabled = scanMode;
        documentFile.required = !scanMode;
        dropzone.style.opacity = scanMode ? '.45' : '1';
        dropzone.style.pointerEvents = scanMode ? 'none' : '';
        documentFile.accept = acceptMap[value] || '.pdf,.jpg,.jpeg,.png';

        if (scanMode && fileName) {
            fileName.textContent = 'Utilisez les champs recto et verso.';
        } else if (fileName && !documentFile.files.length) {
            fileName.textContent = '';
        }
    }

    function selectDocumentType(type) {
        if (!typeSelect) {
            return;
        }

        typeSelect.value = type;
        updateUploadMode();

        uploadPanel?.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    document.querySelectorAll('[data-select-document]').forEach((button) => {
        button.addEventListener('click', () => {
            selectDocumentType(button.dataset.selectDocument);
        });
    });

    documentFile?.addEventListener('change', () => {
        if (!fileName) {
            return;
        }

        fileName.textContent = documentFile.files?.[0]?.name || '';
    });

    typeSelect?.addEventListener('change', updateUploadMode);

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, () => {
            dropzone.classList.remove('is-dragover');
        });
    });

    document.getElementById('resetDocumentForm')?.addEventListener('click', () => {
        setTimeout(() => {
            if (fileName) {
                fileName.textContent = '';
            }

            updateUploadMode();
        }, 0);
    });

    document.querySelectorAll('[data-view-document]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-document-row]');

            if (!row || !modal) {
                return;
            }

            modalLabel.textContent = row.dataset.label || '—';
            modalDescription.textContent = row.dataset.description || '—';
            modalStatus.textContent = row.dataset.status || '—';
            modalDate.textContent = row.dataset.date || '—';

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelector('[data-close-document-modal]')?.addEventListener('click', closeModal);

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
            closeModal();
        }
    });

    updateUploadMode();
});
</script>
@endsection
