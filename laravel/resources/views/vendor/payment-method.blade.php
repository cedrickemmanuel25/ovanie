@extends('layouts.vendor')

@section('title', 'Méthode de paiement vendeur | OVANIE')

@php
    $operator = old('mm_operator', $shop->mm_operator ?: 'orange');

    $operators = [
        'orange' => [
            'label' => 'Orange Money',
            'logo' => asset('storage/logo paiement/orange money.png'),
        ],
        'mtn' => [
            'label' => 'MTN Mobile Money',
            'logo' => asset('storage/logo paiement/mtn money.png'),
        ],
        'moov' => [
            'label' => 'Moov Money',
            'logo' => asset('storage/logo paiement/moov africa.png'),
        ],
        'wave' => [
            'label' => 'Wave',
            'logo' => asset('storage/logo paiement/wave.png'),
        ],
    ];

    $formatPhone = function ($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '225') && strlen($digits) >= 13) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return trim(chunk_split($digits, 2, ' '));
    };

    $identityTypeLabels = [
        'cni' => 'Carte Nationale d’Identité',
        'passport' => 'Passeport',
        'permis' => 'Permis de conduire',
        'residence' => 'Titre de séjour',
    ];

    $identityLabel = $identityTypeLabels[$shop->identity_type ?? ''] ?? 'Pièce d’identité enregistrée';
    $currentPhone = old('mm_number', $formatPhone($shop->mm_number));
    $currentHolder = old('mm_holder', $shop->mm_holder);
@endphp

@section('styles')
<style>
    :root {
        --pm-navy: #0b2a63;
        --pm-blue: #0f61e8;
        --pm-orange: #ff5a0a;
        --pm-green: #16a34a;
        --pm-text: #10295c;
        --pm-muted: #6d7f9f;
        --pm-line: #dfe7f1;
        --pm-bg: #f8fbff;
        --pm-card: #ffffff;
    }

    .pm-page {
        max-width: 1240px;
        margin: 0 auto;
        padding: 26px 28px 42px;
        color: var(--pm-text);
    }

    .pm-breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        font-size: 12px;
        font-weight: 700;
        color: #7690b7;
    }

    .pm-breadcrumb a {
        color: var(--pm-blue);
        text-decoration: none;
    }

    .pm-header {
        margin-bottom: 20px;
    }

    .pm-header h1 {
        margin: 0;
        color: var(--pm-navy);
        font-size: clamp(30px, 3vw, 44px);
        line-height: 1.08;
        letter-spacing: -0.035em;
        font-weight: 900;
    }

    .pm-header p {
        margin: 8px 0 0;
        color: #617597;
        font-size: 14px;
    }

    .pm-alert,
    .pm-card {
        border: 1px solid var(--pm-line);
        border-radius: 14px;
        background: var(--pm-card);
        box-shadow: 0 12px 30px rgba(18, 45, 92, .035);
    }

    .pm-alert {
        min-height: 98px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 18px 22px;
        margin-bottom: 18px;
        border-color: #ffcfac;
        background: linear-gradient(90deg, #fff9f4 0%, #fffdfa 100%);
    }

    .pm-alert-main {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }

    .pm-alert-icon {
        width: 56px;
        height: 56px;
        flex: 0 0 56px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff2e7;
        color: var(--pm-orange);
    }

    .pm-alert-icon svg {
        width: 30px;
        height: 30px;
    }

    .pm-alert h2 {
        margin: 0 0 5px;
        color: #15336d;
        font-size: 17px;
        font-weight: 900;
    }

    .pm-alert p {
        margin: 0;
        color: #526b96;
        font-size: 12.5px;
        line-height: 1.55;
    }

    .pm-alert-link {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--pm-orange);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .pm-alert-link svg {
        width: 16px;
        height: 16px;
    }

    .pm-layout {
        display: grid;
        grid-template-columns: minmax(0, 2.15fr) minmax(280px, .95fr);
        gap: 18px;
        align-items: stretch;
    }

    .pm-card {
        overflow: hidden;
    }

    .pm-card-head {
        padding: 18px 20px 0;
    }

    .pm-card-head h2 {
        margin: 0;
        color: var(--pm-navy);
        font-size: 17px;
        font-weight: 900;
    }

    .pm-form {
        padding: 18px 20px 20px;
    }

    .pm-field {
        margin-bottom: 14px;
    }

    .pm-label {
        display: block;
        margin-bottom: 7px;
        color: #203a70;
        font-size: 11.5px;
        font-weight: 800;
    }

    .pm-required {
        color: #ef4444;
    }

    .pm-operators {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .pm-operator {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .pm-operator input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .pm-operator-card {
        min-height: 64px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 12px;
        border: 1px solid #cdd9e9;
        border-radius: 8px;
        background: #fff;
        color: #1d376e;
        transition: .18s ease;
    }

    .pm-operator-card:hover {
        border-color: #8eb2ef;
        transform: translateY(-1px);
    }

    .pm-operator input:checked + .pm-operator-card {
        border: 1.5px solid var(--pm-orange);
        background: linear-gradient(180deg, #fffdfa, #fff8f2);
        box-shadow: 0 0 0 3px rgba(255, 90, 10, .07);
    }

    .pm-operator-logo {
        width: 38px;
        height: 30px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 6px;
        background: #f7f9fc;
        overflow: hidden;
    }

    .pm-operator-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
    }

    .pm-operator-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 10.5px;
        font-weight: 800;
    }

    .pm-input-wrap {
        position: relative;
    }

    .pm-input-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        width: 17px;
        height: 17px;
        color: #2b5db6;
        pointer-events: none;
    }

    .pm-input,
    .pm-select,
    .pm-file-control {
        width: 100%;
        min-height: 44px;
        border: 1px solid #cfdbea;
        border-radius: 7px;
        background: #fff;
        color: #173264;
        font: inherit;
        font-size: 12px;
        outline: none;
        transition: .18s ease;
    }

    .pm-input {
        padding: 0 13px;
    }

    .pm-input.has-icon {
        padding-left: 42px;
    }

    .pm-input:focus,
    .pm-select:focus {
        border-color: var(--pm-blue);
        box-shadow: 0 0 0 3px rgba(15, 97, 232, .08);
    }

    .pm-help {
        margin: 6px 0 0;
        color: #7484a0;
        font-size: 10.5px;
        line-height: 1.45;
    }

    .pm-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .pm-select-wrap {
        position: relative;
    }

    .pm-select {
        appearance: none;
        padding: 0 42px 0 42px;
        cursor: not-allowed;
        color: #536684;
        background: #f9fbfd;
    }

    .pm-select-left {
        position: absolute;
        left: 13px;
        top: 50%;
        width: 16px;
        height: 16px;
        color: #496b9d;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .pm-select-right {
        position: absolute;
        right: 13px;
        top: 50%;
        width: 16px;
        height: 16px;
        color: #496b9d;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .pm-file-control {
        display: flex;
        align-items: center;
        gap: 0;
        overflow: hidden;
        background: #fff;
    }

    .pm-file-control a {
        height: 42px;
        display: inline-flex;
        align-items: center;
        padding: 0 16px;
        border-right: 1px solid var(--pm-line);
        color: var(--pm-navy);
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        background: #fbfcfe;
    }

    .pm-file-control span {
        padding: 0 14px;
        color: #7c8da8;
        font-size: 11px;
    }

    .pm-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 4px;
    }

    .pm-btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 19px;
        border-radius: 7px;
        font-size: 11.5px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
    }

    .pm-btn-secondary {
        border: 1px solid #cbd6e5;
        background: #fff;
        color: var(--pm-navy);
    }

    .pm-btn-primary {
        border: 1px solid var(--pm-orange);
        background: linear-gradient(90deg, #ff650d, #ff4b00);
        color: #fff;
        box-shadow: 0 8px 18px rgba(255, 90, 10, .16);
    }

    .pm-btn:hover {
        transform: translateY(-1px);
    }

    .pm-info-card {
        padding-bottom: 12px;
    }

    .pm-info-list {
        padding: 14px 18px 18px;
    }

    .pm-info-item {
        display: grid;
        grid-template-columns: 42px 1fr;
        gap: 13px;
        padding: 13px 0;
    }

    .pm-info-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 50%;
    }

    .pm-info-icon svg {
        width: 20px;
        height: 20px;
    }

    .pm-info-icon.green { background: #e9f9ef; color: #16a34a; }
    .pm-info-icon.blue { background: #edf4ff; color: #1764e8; }
    .pm-info-icon.violet { background: #f2edff; color: #6d49e5; }
    .pm-info-icon.orange { background: #fff1e7; color: #ff660d; }

    .pm-info-item h3 {
        margin: 1px 0 5px;
        color: #19366c;
        font-size: 12px;
        font-weight: 900;
    }

    .pm-info-item p {
        margin: 0;
        color: #667a9c;
        font-size: 11px;
        line-height: 1.6;
    }

    .pm-bottom {
        display: grid;
        grid-template-columns: minmax(0, 2.5fr) minmax(260px, .8fr);
        gap: 18px;
        margin-top: 18px;
    }

    .pm-history-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 16px 18px 12px;
    }

    .pm-history-head h2 {
        margin: 0;
        color: var(--pm-navy);
        font-size: 15px;
        font-weight: 900;
    }

    .pm-history-link {
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        padding: 0 12px;
        border: 1px solid #d6e0ee;
        border-radius: 6px;
        color: #17366c;
        font-size: 10px;
        font-weight: 800;
        text-decoration: none;
    }

    .pm-table-wrap {
        width: 100%;
        overflow: hidden;
    }

    .pm-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .pm-table th,
    .pm-table td {
        padding: 12px 14px;
        border-top: 1px solid #e6ecf4;
        text-align: left;
        font-size: 10.5px;
        color: #17366c;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pm-table th {
        color: #6e7f9b;
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        background: #fbfcfe;
    }

    .pm-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
    }

    .pm-status.current {
        color: #13833c;
        background: #e8f8ee;
    }

    .pm-status.previous {
        color: #62708c;
        background: #eef2f7;
    }

    .pm-support {
        padding: 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 100%;
    }

    .pm-support-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .pm-support-title svg {
        width: 22px;
        height: 22px;
        color: #214b91;
    }

    .pm-support-title h2 {
        margin: 0;
        color: var(--pm-navy);
        font-size: 15px;
        font-weight: 900;
    }

    .pm-support p {
        margin: 0 0 18px;
        color: #667a9c;
        font-size: 11px;
        line-height: 1.65;
    }

    .pm-support .pm-btn-primary {
        width: 100%;
    }

    .pm-message {
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .pm-message.success {
        color: #126b35;
        border: 1px solid #bfe9cf;
        background: #edf9f1;
    }

    .pm-message.error {
        color: #b42318;
        border: 1px solid #fecaca;
        background: #fff1f2;
    }

    .pm-error {
        margin: 6px 0 0;
        color: #dc2626;
        font-size: 10.5px;
        font-weight: 700;
    }

    @media (max-width: 1100px) {
        .pm-layout,
        .pm-bottom {
            grid-template-columns: 1fr;
        }

        .pm-operators {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .pm-page {
            padding: 18px 14px 30px;
        }

        .pm-alert {
            align-items: flex-start;
            flex-direction: column;
        }

        .pm-grid-2,
        .pm-operators {
            grid-template-columns: 1fr;
        }

        .pm-actions {
            flex-direction: column-reverse;
        }

        .pm-btn {
            width: 100%;
        }

        .pm-table th:nth-child(3),
        .pm-table td:nth-child(3),
        .pm-table th:nth-child(4),
        .pm-table td:nth-child(4),
        .pm-table th:nth-child(6),
        .pm-table td:nth-child(6) {
            display: none;
        }
    }
</style>
@endsection

@section('content')
<div class="pm-page">
    <nav class="pm-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.shop.profile') }}">Paramètres boutique</a>
        <i data-lucide="chevron-right" style="width:14px;height:14px"></i>
        <span>Méthode de paiement</span>
    </nav>

    <header class="pm-header">
        <h1>Méthode de paiement vendeur</h1>
        <p>Définissez le compte sur lequel vous souhaitez recevoir vos reversements.</p>
    </header>

    @if(session('success'))
        <div class="pm-message success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="pm-message error">Veuillez corriger les champs signalés avant d’enregistrer.</div>
    @endif

    <section class="pm-alert">
        <div class="pm-alert-main">
            <span class="pm-alert-icon">
                <i data-lucide="shield-check"></i>
            </span>
            <div>
                <h2>Versements sécurisés</h2>
                <p>
                    Vos reversements sont effectués sur le compte enregistré ci-dessous selon votre calendrier de paiement.
                    <br>Assurez-vous que les informations sont exactes et actives.
                </p>
            </div>
        </div>

        <a href="{{ route('vendor.payouts.index') }}" class="pm-alert-link">
            <i data-lucide="info"></i>
            En savoir plus
        </a>
    </section>

    <div class="pm-layout">
        <section class="pm-card">
            <div class="pm-card-head">
                <h2>Informations du compte de versement</h2>
            </div>

            <form
                class="pm-form"
                method="POST"
                action="{{ route('vendor.payment-method.update') }}"
            >
                @csrf
                @method('PUT')

                <input type="hidden" name="direct_payment" value="{{ old('direct_payment', $shop->direct_payment ? 1 : 0) }}">
                <input type="hidden" name="whatsapp" value="{{ old('whatsapp', $shop->whatsapp) }}">
                <input type="hidden" name="business_email" value="{{ old('business_email', $shop->business_email) }}">

                <div class="pm-field">
                    <span class="pm-label">Opérateur de mobile money <span class="pm-required">*</span></span>

                    <div class="pm-operators">
                        @foreach($operators as $value => $item)
                            <label class="pm-operator">
                                <input
                                    type="radio"
                                    name="mm_operator"
                                    value="{{ $value }}"
                                    @checked($operator === $value)
                                    required
                                >
                                <span class="pm-operator-card">
                                    <span class="pm-operator-logo">
                                        <img src="{{ $item['logo'] }}" alt="{{ $item['label'] }}">
                                    </span>
                                    <span class="pm-operator-name">{{ $item['label'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('mm_operator')
                        <p class="pm-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="mmNumber">Numéro de téléphone <span class="pm-required">*</span></label>

                    <div class="pm-input-wrap">
                        <i class="pm-input-icon" data-lucide="phone"></i>
                        <input
                            class="pm-input has-icon"
                            id="mmNumber"
                            name="mm_number"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel"
                            maxlength="20"
                            value="{{ $currentPhone }}"
                            placeholder="Ex. 07 00 00 00 45"
                            required
                        >
                    </div>

                    <p class="pm-help">Saisissez le numéro associé à votre compte Mobile Money.</p>

                    @error('mm_number')
                        <p class="pm-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="mmHolder">Titulaire du compte <span class="pm-required">*</span></label>

                    <div class="pm-input-wrap">
                        <i class="pm-input-icon" data-lucide="user-round"></i>
                        <input
                            class="pm-input has-icon"
                            id="mmHolder"
                            name="mm_holder"
                            type="text"
                            value="{{ $currentHolder }}"
                            placeholder="Nom exactement enregistré chez l’opérateur"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <p class="pm-help">Le nom doit correspondre à celui enregistré chez l’opérateur.</p>

                    @error('mm_holder')
                        <p class="pm-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pm-grid-2">
                    <div class="pm-field">
                        <label class="pm-label">Pièce d’identité (pour vérification)</label>

                        <div class="pm-select-wrap">
                            <i class="pm-select-left" data-lucide="file-badge"></i>
                            <select class="pm-select" disabled aria-label="Pièce d’identité enregistrée">
                                <option>{{ $identityLabel }}</option>
                            </select>
                            <i class="pm-select-right" data-lucide="chevron-down"></i>
                        </div>
                    </div>

                    <div class="pm-field">
                        <label class="pm-label">N° de la pièce</label>
                        <input
                            class="pm-input"
                            type="text"
                            value="{{ $shop->identity_number ?: 'Non renseigné' }}"
                            readonly
                        >
                    </div>
                </div>

                <div class="pm-field">
                    <span class="pm-label">Photo de la pièce d’identité</span>

                    <div class="pm-file-control">
                        <a href="{{ route('vendor.shop.documents') }}">Gérer mes documents</a>
                        <span>
                            {{ filled($shop->identity_file) || filled($shop->identity_file_front) ? 'Document déjà enregistré' : 'Aucun document disponible' }}
                        </span>
                    </div>

                    <p class="pm-help">Les pièces d’identité sont gérées dans la section Documents de la boutique.</p>
                </div>

                <div class="pm-actions">
                    <a href="{{ route('vendor.payouts.index') }}" class="pm-btn pm-btn-secondary">Annuler</a>
                    <button class="pm-btn pm-btn-primary" type="submit">Enregistrer les modifications</button>
                </div>
            </form>
        </section>

        <aside class="pm-card pm-info-card">
            <div class="pm-card-head">
                <h2>Informations importantes</h2>
            </div>

            <div class="pm-info-list">
                <div class="pm-info-item">
                    <span class="pm-info-icon green"><i data-lucide="circle-check-big"></i></span>
                    <div>
                        <h3>Calendrier de reversement</h3>
                        <p><strong>{{ $shop->payment_mode_label }}</strong></p>
                        <p>
                            Les reversements sont programmés selon le mode de paiement choisi lors de l’ouverture de votre boutique.
                        </p>
                    </div>
                </div>

                <div class="pm-info-item">
                    <span class="pm-info-icon blue"><i data-lucide="lock-keyhole"></i></span>
                    <div>
                        <h3>Sécurité garantie</h3>
                        <p>
                            OVANIE ne demande jamais vos codes ou mots de passe. Vos informations de paiement restent protégées.
                        </p>
                    </div>
                </div>

                <div class="pm-info-item">
                    <span class="pm-info-icon violet"><i data-lucide="user-round-check"></i></span>
                    <div>
                        <h3>Titulaire du compte</h3>
                        <p>
                            Le compte doit être au même nom que votre pièce d’identité afin d’éviter tout rejet de reversement.
                        </p>
                    </div>
                </div>

                <div class="pm-info-item">
                    <span class="pm-info-icon orange"><i data-lucide="clock-3"></i></span>
                    <div>
                        <h3>Modification du compte</h3>
                        <p>
                            Toute modification enregistrée sera utilisée pour les prochains reversements qui ne sont pas encore exécutés.
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="pm-bottom">
        <section class="pm-card">
            <div class="pm-history-head">
                <h2>Historique des modifications</h2>
                <a href="{{ route('vendor.payouts.index') }}" class="pm-history-link">Voir les reversements</a>
            </div>

            <div class="pm-table-wrap">
                <table class="pm-table">
                    <colgroup>
                        <col style="width:18%">
                        <col style="width:18%">
                        <col style="width:18%">
                        <col style="width:18%">
                        <col style="width:12%">
                        <col style="width:16%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Opérateur</th>
                            <th>Numéro</th>
                            <th>Titulaire</th>
                            <th>Statut</th>
                            <th>Modifié par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ optional($shop->updated_at)->format('d/m/Y - H:i') ?: '—' }}</td>
                            <td>{{ $operators[$shop->mm_operator]['label'] ?? ucfirst((string) ($shop->mm_operator ?: 'Non défini')) }}</td>
                            <td>{{ $formatPhone($shop->mm_number) ?: '—' }}</td>
                            <td>{{ $shop->mm_holder ?: '—' }}</td>
                            <td><span class="pm-status current">Actuel</span></td>
                            <td>Vous</td>
                        </tr>

                        @if($shop->created_at && $shop->updated_at && ! $shop->created_at->equalTo($shop->updated_at))
                            <tr>
                                <td>{{ $shop->created_at->format('d/m/Y - H:i') }}</td>
                                <td>{{ $operators[$shop->mm_operator]['label'] ?? ucfirst((string) ($shop->mm_operator ?: 'Non défini')) }}</td>
                                <td>{{ $formatPhone($shop->mm_number) ?: '—' }}</td>
                                <td>{{ $shop->mm_holder ?: '—' }}</td>
                                <td><span class="pm-status previous">Création</span></td>
                                <td>Vous</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="pm-card pm-support">
            <div>
                <div class="pm-support-title">
                    <i data-lucide="headphones"></i>
                    <h2>Besoin d’aide ?</h2>
                </div>

                <p>
                    En cas de problème avec vos reversements ou pour toute question, contactez le support OVANIE.
                </p>
            </div>

            <a href="{{ route('contact.index') }}" class="pm-btn pm-btn-primary">Contacter le support</a>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('mmNumber');

    if (input) {
        const format = (value) => {
            let digits = String(value || '').replace(/\D+/g, '');

            if (digits.startsWith('225') && digits.length >= 13) {
                digits = digits.slice(-10);
            }

            digits = digits.slice(0, 10);

            return digits.match(/.{1,2}/g)?.join(' ') || '';
        };

        input.addEventListener('input', () => {
            input.value = format(input.value);
        });
    }
});
</script>
@endsection
