<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ouvrir une boutique - OVANIE</title>
    <meta name="description" content="Créez votre boutique vendeur OVANIE et commencez à vendre vos produits de construction, finition, solaire et équipements BTP.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.css">
    <link rel="stylesheet" href="{{ asset('css/open-shop.css') }}?v={{ file_exists(public_path('css/open-shop.css')) ? filemtime(public_path('css/open-shop.css')) : time() }}">
</head>
@php
    $stepFields = [
        1 => ['sellerName', 'sellerEmail', 'sellerPhone', 'sellerType', 'password', 'companyName', 'legalForm', 'rccm', 'taxpayerNumber', 'rccmFile', 'taxFile'],
        2 => ['shopName', 'description', 'selfie', 'region', 'city', 'commune', 'commune_id', 'district', 'quarter_id', 'landmark', 'landmark_id', 'address', 'categories', 'categories.*', 'delivery_zone', 'logistics_type', 'latitude', 'longitude', 'whatsapp', 'business_email'],
        3 => ['identityCountry', 'identityType', 'identityNumber', 'identityUploadMode', 'identityFile', 'identityFileFront', 'identityFileBack'],
        4 => ['payment_mode', 'mmOperator', 'mmNumber', 'mmHolder'],
        5 => ['terms'],
    ];

    $initialStep = 1;
    foreach ($stepFields as $number => $fields) {
        if ($errors->hasAny($fields)) {
            $initialStep = $number;
            break;
        }
    }
    $formatCiPhone = function ($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (str_starts_with($digits, '225') && strlen($digits) >= 13) {
            $digits = substr($digits, -10);
        }
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return trim(chunk_split($digits, 2, ' '));
    };

    // Catalogue de secours intégré au formulaire. Si l'API des quartiers est
    // momentanément indisponible en production, le vendeur peut continuer sans
    // recharger la page ni perdre les informations déjà saisies.
    $localityRegistry = app(\App\Services\Geo\AbidjanLocalityRegistry::class);
    $locationFallbackCatalog = collect($communes)
        ->mapWithKeys(fn (array $communeOption) => [
            $communeOption['name'] => $localityRegistry->localitiesForCommune($communeOption['name'], null, 500),
        ])
        ->all();
@endphp
<body
    data-initial-step="{{ $initialStep }}"
    data-current-step="{{ $initialStep }}"
    data-has-server-errors="{{ $errors->any() ? '1' : '0' }}"
    data-geo-communes-url="{{ route('open-shop.geo.communes') }}"
    data-geo-quarters-url="{{ route('open-shop.geo.quarters') }}"
    data-geo-landmarks-url="{{ route('open-shop.geo.landmarks') }}"
    data-geo-reverse-url="{{ route('open-shop.geo.reverse') }}"
    data-geo-resolve-url="{{ route('open-shop.geo.resolve') }}"
>
    <template id="openShopLocalityFallback">@json($locationFallbackCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</template>
    <header class="topbar">
        <div class="topbar__inner">
            <a href="{{ route('home') }}" class="brand" aria-label="Retour à l'accueil OVANIE">
                <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
            </a>

            <div class="topbar__support">
                <div class="support-copy">
                    <span>Besoin d’aide ?</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92z"/></svg>
                    <a href="tel:{{ config('public_contact.phone_e164', '+2250161780000') }}" style="color:inherit;text-decoration:none"><strong>{{ config('public_contact.phone_display', '01 61 78 00 00') }}</strong></a>
                </div>
                <a href="{{ route('contact.index') }}" class="support-button">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    Centre d’assistance
                </a>
            </div>
        </div>
    </header>

    <section class="progress-wrap">
        <div class="progress-track" aria-label="Progression de l'ouverture de boutique">
            @foreach ([
                ['Informations personnelles', 'Vos informations principales'],
                ['Informations boutique', 'Détails de votre boutique'],
                ['Vérification identité', 'Pièce d’identité & documents'],
                ['Paiement vendeur', 'Conditions de paiement'],
                ['Conditions vendeur', 'Engagements & validation'],
            ] as $index => $step)
                <button type="button" class="progress-step" data-progress-step="{{ $index + 1 }}" aria-label="Étape {{ $index + 1 }} : {{ $step[0] }}">
                    <span class="progress-step__number">{{ $index + 1 }}</span>
                    <span class="progress-step__copy">
                        <strong>{{ $step[0] }}</strong>
                        <small>{{ $step[1] }}</small>
                    </span>
                </button>
            @endforeach
        </div>
    </section>

    <main class="page-shell">
        <div class="seller-space-heading">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 10h18M5 10v10h14V10M4 4h16l1 6H3l1-6zM8 14h3v6H8z"/></svg>
            <strong>ESPACE VENDEUR OVANIE</strong>
        </div>

        <section class="intro-card">
            <div class="intro-card__content">
                <span class="eyebrow">ESPACE VENDEUR OVANIE</span>
                <h1>Ouvrez votre boutique professionnelle</h1>
                <p>Rejoignez la marketplace des matériaux de construction en Côte d’Ivoire et développez votre activité en toute simplicité.</p>

                <div class="intro-stats" aria-label="Avantages vendeur">
                    <div>
                        <span class="intro-stat__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg></span>
                        <span class="intro-stat__copy"><strong>5 étapes simples</strong><small>Création rapide</small></span>
                    </div>
                    <div>
                        <span class="intro-stat__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                        <span class="intro-stat__copy"><strong>GPS adresse intelligente</strong><small>Position précise</small></span>
                    </div>
                    <div>
                        <span class="intro-stat__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></span>
                        <span class="intro-stat__copy"><strong>KYC vérification séparée</strong><small>Sécurisé & conforme</small></span>
                    </div>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <div class="alert-icon">!</div>
                <div>
                    <strong>Le formulaire contient des informations à corriger.</strong>
                    <p>Nous vous avons replacé automatiquement sur la première étape concernée.</p>
                </div>
            </div>
        @endif

        <form id="openShopForm" class="wizard-card" action="{{ route('shop.open.submit') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <input type="hidden" name="direct_payment" value="0">

            <div class="wizard-card__body">
                {{-- ÉTAPE 1 --}}
                <section class="wizard-step" data-step="1">
                    <div class="step-header">
                        <div>
                            <h2>Étape 1 sur 5 <span>— Informations personnelles</span></h2>
                            <p>Identifiez le responsable principal de la boutique.</p>
                        </div>
                        <span class="step-badge">Compte vendeur</span>
                    </div>

                    <div class="content-grid">
                        <div class="form-panel">
                            <div class="form-grid two-cols">
                                <div class="field span-2-mobile">
                                    <label for="sellerName">Nom complet <em>*</em></label>
                                    <input id="sellerName" name="sellerName" type="text" value="{{ old('sellerName', trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?? '')) }}" required autocomplete="name" placeholder="Ex. Kouamé Daniel">
                                    @error('sellerName')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="sellerEmail">Adresse email <em>*</em></label>
                                    <input id="sellerEmail" name="sellerEmail" type="email" value="{{ old('sellerEmail', $user->email ?? '') }}" required autocomplete="email" placeholder="vendeur@exemple.com" @if($user && $user->hasVerifiedEmail()) readonly @endif>
                                    @error('sellerEmail')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="sellerPhone">Téléphone <em>*</em></label>
                                    <div class="phone-field">
                                        <span class="phone-prefix">
                                            <span class="ci-flag" aria-hidden="true"><span></span><span></span><span></span></span>
                                            <span>+225</span>
                                        </span>
                                        <input id="sellerPhone" name="sellerPhone" type="tel" value="{{ old('sellerPhone', $formatCiPhone($user->phone ?? '')) }}" required autocomplete="tel" inputmode="numeric" maxlength="14" placeholder="01 02 03 04 05">
                                    </div>
                                    @error('sellerPhone')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="sellerType">Type de vendeur <em>*</em></label>
                                    <select id="sellerType" name="sellerType" required>
                                        <option value="">Sélectionner un profil</option>
                                        <option value="particulier" @selected(old('sellerType') === 'particulier')>Particulier — ventes personnelles</option>
                                        <option value="entreprise" @selected(old('sellerType') === 'entreprise')>Entreprise / société enregistrée</option>
                                        <option value="artisan" @selected(old('sellerType') === 'artisan')>Artisan / professionnel indépendant</option>
                                        <option value="grossiste" @selected(old('sellerType') === 'grossiste')>Grossiste / distributeur</option>
                                    </select>
                                    @error('sellerType')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                @guest
                                    <div class="field">
                                        <label for="password">Mot de passe <em>*</em></label>
                                        <div class="password-field">
                                            <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="8 caractères minimum">
                                            <button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Afficher le mot de passe">
                                                <span class="password-toggle__icon" aria-hidden="true">
                                                    <svg class="lucide lucide-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="password_confirmation">Confirmer le mot de passe <em>*</em></label>
                                        <div class="password-field">
                                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="8" placeholder="Répétez le mot de passe">
                                            <button type="button" class="password-toggle" data-password-toggle aria-controls="password_confirmation" aria-label="Afficher le mot de passe">
                                                <span class="password-toggle__icon" aria-hidden="true">
                                                    <svg class="lucide lucide-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endguest
                            </div>

                            <div id="companyFields" class="conditional-panel" hidden>
                                <div class="conditional-panel__head">
                                    <div>
                                        <span class="mini-badge">Entreprise</span>
                                        <h3>Informations légales de la société</h3>
                                    </div>
                                    <p>Ces données seront vérifiées séparément sans bloquer l’accès initial à l’espace vendeur.</p>
                                </div>

                                <div class="form-grid two-cols">
                                    <div class="field">
                                        <label for="companyName">Raison sociale <em>*</em></label>
                                        <input id="companyName" name="companyName" type="text" value="{{ old('companyName') }}" placeholder="Ex. Bâtir CI SARL">
                                        @error('companyName')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="legalForm">Forme juridique <em>*</em></label>
                                        <select id="legalForm" name="legalForm">
                                            <option value="">Sélectionner</option>
                                            @foreach(['sarl'=>'SARL','sarlu'=>'SARLU','sa'=>'SA','sas'=>'SAS','ei'=>'Entreprise individuelle','cooperative'=>'Coopérative','autre'=>'Autre'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('legalForm') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('legalForm')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="rccm">Numéro RCCM <em>*</em></label>
                                        <input id="rccm" name="rccm" type="text" value="{{ old('rccm') }}" placeholder="CI-ABJ-2026-B-00000">
                                        @error('rccm')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="taxpayerNumber">Numéro contribuable <em>*</em></label>
                                        <input id="taxpayerNumber" name="taxpayerNumber" type="text" value="{{ old('taxpayerNumber') }}" placeholder="Compte contribuable">
                                        @error('taxpayerNumber')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="rccmFile">Document RCCM <em>*</em></label>
                                        <input id="rccmFile" name="rccmFile" type="file" accept=".pdf,.jpg,.jpeg,.png">
                                        <small>PDF ou image, 10 Mo maximum.</small>
                                        @error('rccmFile')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="taxFile">Document fiscal <em>*</em></label>
                                        <input id="taxFile" name="taxFile" type="file" accept=".pdf,.jpg,.jpeg,.png">
                                        <small>PDF ou image, 10 Mo maximum.</small>
                                        @error('taxFile')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="info-panel info-panel-blue">
                            <div class="info-icon">01</div>
                            <h3>Un seul responsable principal</h3>
                            <p>Le compte créé devient propriétaire de la boutique et reçoit les notifications importantes liées aux commandes, paiements et contrôles.</p>
                            <ul class="check-list">
                                <li>Accès immédiat à l’espace vendeur</li>
                                <li>KYC suivi séparément</li>
                                <li>Données protégées et non affichées au client</li>
                            </ul>
                            <div class="store-line-illustration" aria-hidden="true">
                                <svg viewBox="0 0 360 150">
                                    <path d="M32 132h296"/>
                                    <path d="M94 62h118l10 28H84l10-28zM91 90v42h124V90M110 90v42M190 90v42"/>
                                    <path d="M126 108h42v24h-42zM244 102h54v30h-54z"/>
                                    <circle cx="286" cy="122" r="22"/>
                                    <circle cx="286" cy="115" r="7"/>
                                    <path d="M274 136c3-8 8-12 12-12s9 4 12 12"/>
                                    <path d="M46 132V91h28v41M54 91V70h12v21M226 132V82h18v50M234 82V58h8v24" opacity=".18"/>
                                </svg>
                            </div>
                        </aside>
                    </div>
                </section>

                {{-- ÉTAPE 2 --}}
                <section class="wizard-step" data-step="2" hidden>
                    <div class="step-header">
                        <div>
                            <h2>Étape 2 sur 5 <span>— Informations de la boutique</span></h2>
                            <p>Décrivez votre activité et enregistrez précisément votre point d’enlèvement.</p>
                        </div>
                        <span class="step-badge">Boutique & logistique</span>
                    </div>

                    <div class="content-grid content-grid-single">
                        <div class="form-panel">
                            <div class="form-grid two-cols shop-basics-grid">
                                <div class="field">
                                    <label for="shopName">Nom de la boutique <em>*</em></label>
                                    <input id="shopName" name="shopName" type="text" value="{{ old('shopName') }}" required placeholder="Ex. Matériaux Kouassi">
                                    @error('shopName')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="selfie">Photo du responsable <em>*</em></label>
                                    <input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png,image/webp" required>
                                    <small>Photo claire, visage visible, 5 Mo maximum.</small>
                                    @error('selfie')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field span-2">
                                    <div class="label-row">
                                        <label for="description">Description de l’activité <em>*</em></label>
                                        <span id="descriptionCounter">0/700</span>
                                    </div>
                                    <textarea id="description" name="description" rows="4" minlength="30" maxlength="700" required placeholder="Présentez vos produits, vos spécialités, votre expérience et les zones que vous servez...">{{ old('description') }}</textarea>
                                    @error('description')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <section class="shop-location-card" aria-labelledby="shopLocationTitle">
                            <div class="shop-location-card__header">
                                    <div class="shop-location-card__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M20 10c0 5.2-8 12-8 12S4 15.2 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.6"/></svg>
                                    </div>
                                    <div>
                                        <span class="shop-location-card__eyebrow">Adresse professionnelle</span>
                                        <h3 id="shopLocationTitle">Localisation de la boutique</h3>
                                        <p>Renseignez l’adresse exacte de votre boutique.</p>
                                    </div>
                                    <span class="shop-location-card__required"><em>*</em> Champs obligatoires</span>
                                </div>

                                <div class="location-form-grid">
                                    <div class="field location-field">
                                        <label for="region">Région</label>
                                        <div class="location-control location-control--locked location-control--plain">
                                            <input
                                                id="region"
                                                name="region"
                                                type="text"
                                                value="Abidjan"
                                                readonly
                                                aria-readonly="true"
                                            >
                                        </div>
                                        @error('region')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field location-field">
                                        <label for="city">Ville</label>
                                        <div class="location-control location-control--locked location-control--plain">
                                            <input id="city" name="city" type="text" value="Abidjan" readonly aria-readonly="true">
                                        </div>
                                        @error('city')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field location-field">
                                        <label for="communeSearch">Commune <em>*</em></label>
                                        <div class="geo-combobox" data-geo-combobox="commune">
                                            <div class="location-control location-control--select">
                                                <input
                                                    id="communeSearch"
                                                    data-validation-name="commune"
                                                    type="search"
                                                    value="{{ old('commune') }}"
                                                    required
                                                    autocomplete="off"
                                                    placeholder="Sélectionner une commune"
                                                    role="combobox"
                                                    aria-autocomplete="list"
                                                    aria-expanded="false"
                                                    aria-controls="communeOptions"
                                                >
                                                <button type="button" class="geo-combobox__toggle" aria-label="Afficher les communes" tabindex="-1">
                                                    <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                                                </button>
                                            </div>
                                            <div id="communeOptions" class="geo-combobox__menu" role="listbox" hidden>
                                                @foreach ($communes as $communeOption)
                                                    <button
                                                        type="button"
                                                        class="geo-combobox__option"
                                                        role="option"
                                                        data-value="{{ $communeOption['name'] }}"
                                                        data-id="{{ $communeOption['id'] ?? '' }}"
                                                        data-search="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($communeOption['name'].' '.implode(' ', $communeOption['aliases'] ?? []))) }}"
                                                        aria-selected="{{ old('commune') === $communeOption['name'] ? 'true' : 'false' }}"
                                                    >
                                                        <span class="geo-combobox__option-main">{{ $communeOption['name'] }}</span>
                                                    </button>
                                                @endforeach
                                                <div class="geo-combobox__empty" data-empty hidden>Aucune commune trouvée.</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="commune" name="commune" value="{{ old('commune') }}">
                                        <input type="hidden" id="commune_id" name="commune_id" value="{{ old('commune_id') }}">
                                        @error('commune')<p class="field-error">{{ $message }}</p>@enderror
                                        @error('commune_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field location-field">
                                        <label for="districtSearch">Quartier <em>*</em></label>
                                        <div class="geo-combobox" data-geo-combobox="locality">
                                            <div class="location-control location-control--select">
                                                <input
                                                    id="districtSearch"
                                                    data-validation-name="district"
                                                    type="search"
                                                    value="{{ old('district') }}"
                                                    required
                                                    autocomplete="off"
                                                    placeholder="Sélectionner un quartier"
                                                    role="combobox"
                                                    aria-autocomplete="list"
                                                    aria-expanded="false"
                                                    aria-controls="districtOptions"
                                                    @disabled(!old('commune'))
                                                >
                                                <button type="button" class="geo-combobox__toggle" aria-label="Afficher les localités" tabindex="-1" @disabled(!old('commune'))>
                                                    <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                                                </button>
                                            </div>
                                            <div id="districtOptions" class="geo-combobox__menu" role="listbox" hidden>
                                                @foreach ($initialQuarters as $quarterOption)
                                                    <button
                                                        type="button"
                                                        class="geo-combobox__option"
                                                        role="option"
                                                        data-value="{{ $quarterOption['name'] }}"
                                                        data-id="{{ $quarterOption['id'] ?? '' }}"
                                                        data-locality-id="{{ $quarterOption['locality_id'] ?? '' }}"
                                                        data-type="{{ $quarterOption['type'] ?? 'quartier' }}"
                                                        data-type-label="{{ $quarterOption['type_label'] ?? 'Quartier' }}"
                                                        data-latitude="{{ $quarterOption['latitude'] ?? '' }}"
                                                        data-longitude="{{ $quarterOption['longitude'] ?? '' }}"
                                                        data-search="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($quarterOption['name'].' '.($quarterOption['type_label'] ?? '').' '.implode(' ', $quarterOption['aliases'] ?? []))) }}"
                                                        aria-selected="{{ old('district') === $quarterOption['name'] ? 'true' : 'false' }}"
                                                    >
                                                        <span class="geo-combobox__option-main">{{ $quarterOption['name'] }}</span>
                                                    </button>
                                                @endforeach
                                                <div class="geo-combobox__empty" data-empty hidden>Aucune localité trouvée.</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="district" name="district" value="{{ old('district') }}">
                                        <input type="hidden" id="quarter_id" name="quarter_id" value="{{ old('quarter_id') }}">
                                        @error('district')<p class="field-error">{{ $message }}</p>@enderror
                                        @error('quarter_id')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field location-field location-field--wide">
                                        <label for="landmark">Point de repère <em>*</em></label>
                                        <div class="location-control">
                                            <span class="location-control__icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="M20 10c0 5.2-8 12-8 12S4 15.2 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.6"/></svg>
                                            </span>
                                            <input
                                                id="landmark"
                                                name="landmark"
                                                type="text"
                                                value="{{ old('landmark') }}"
                                                required
                                                maxlength="255"
                                                placeholder="Ex. En face de la pharmacie Sainte-Rita, près du marché"
                                                autocomplete="off"
                                            >
                                        </div>
                                        <small class="location-field__hint">Indiquez un lieu connu permettant d’identifier facilement l’entrée de la boutique.</small>
                                        <input type="hidden" id="landmark_id" name="landmark_id" value="{{ old('landmark_id') }}">
                                        <input type="hidden" id="landmark_source" name="landmark_source" value="{{ old('landmark_source', 'manual') }}">
                                        <input type="hidden" id="landmark_latitude" name="landmark_latitude" value="{{ old('landmark_latitude') }}">
                                        <input type="hidden" id="landmark_longitude" name="landmark_longitude" value="{{ old('landmark_longitude') }}">
                                        <input type="hidden" id="address" name="address" value="{{ old('address') }}">
                                        @error('landmark')<p class="field-error">{{ $message }}</p>@enderror
                                        @error('address')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                                <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                                <input type="hidden" id="geo_accuracy" name="geo_accuracy" value="{{ old('geo_accuracy') }}">
                                <input type="hidden" id="geo_source" name="geo_source" value="{{ old('geo_source') }}">
                                <input type="hidden" id="geo_precision" name="geo_precision" value="{{ old('geo_precision') }}">
                                <input type="hidden" id="geo_precision_score" name="geo_precision_score" value="{{ old('geo_precision_score') }}">

                            </section>

                            <div class="section-divider section-divider-spaced">
                                <div>
                                    <span class="section-number">B</span>
                                    <div>
                                        <h3>Activité commerciale</h3>
                                        <p>Ces informations structurent votre boutique et le traitement de vos commandes.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid two-cols">
                                <div class="field span-2">
                                    <label for="categories">Catégories de produits vendus <em>*</em></label>
                                    <small>Sélectionnez toutes les catégories dans lesquelles vous vendez des produits. La première catégorie cochée devient la catégorie principale de la boutique.</small>
                                    @php $selectedCategories = old('categories', []); @endphp
                                    <div class="categories-multiselect" id="categories">
                                        @foreach ($categories as $category)
                                            <label class="category-check">
                                                <input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $selectedCategories, true))>
                                                <span>{{ $category->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('categories')<p class="field-error">{{ $message }}</p>@enderror
                                    @error('categories.*')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="delivery_zone">Zone commerciale principale <em>*</em></label>
                                    <select id="delivery_zone" name="delivery_zone" required>
                                        <option value="">Sélectionner</option>
                                        <option value="abidjan" @selected(old('delivery_zone') === 'abidjan')>Abidjan</option>
                                        <option value="grand_abidjan" @selected(old('delivery_zone') === 'grand_abidjan')>Grand Abidjan</option>
                                        <option value="national" @selected(old('delivery_zone') === 'national')>Toute la Côte d’Ivoire</option>
                                        <option value="afrique_ouest" @selected(old('delivery_zone') === 'afrique_ouest')>Afrique de l’Ouest</option>
                                    </select>
                                    @error('delivery_zone')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="whatsapp">WhatsApp professionnel</label>
                                    <div class="phone-field">
                                        <span class="phone-prefix">
                                            <span class="ci-flag" aria-hidden="true"><span></span><span></span><span></span></span>
                                            <span>+225</span>
                                        </span>
                                        <input id="whatsapp" name="whatsapp" type="tel" value="{{ $formatCiPhone(old('whatsapp')) }}" inputmode="numeric" maxlength="14" placeholder="07 00 00 00 00">
                                    </div>
                                    @error('whatsapp')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="business_email">Email professionnel</label>
                                    <input id="business_email" name="business_email" type="email" value="{{ old('business_email') }}" placeholder="contact@votreboutique.ci">
                                    @error('business_email')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="section-divider section-divider-spaced">
                                <div>
                                    <span class="section-number">C</span>
                                    <div>
                                        <h3>Mode logistique de la boutique</h3>
                                        <p>Sélectionnez le mode de gestion de vos livraisons.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="choice-grid logistics-choice-grid">
                                <label class="choice-card choice-card-featured">
                                    <input type="radio" name="logistics_type" value="ovanie" @checked(old('logistics_type', 'ovanie') === 'ovanie') required>
                                    <span class="choice-check"></span>
                                    <div class="choice-icon">O</div>
                                    <h3>OVANIE Logistics</h3>
                                    <p>Confiez la prise en charge de vos livraisons au réseau logistique OVANIE.</p>
                                    <ul>
                                        <li>Transport organisé par OVANIE</li>
                                        <li>Tarification appliquée automatiquement</li>
                                        <li>Suivi centralisé des expéditions</li>
                                    </ul>
                                </label>

                                <label class="choice-card">
                                    <input type="radio" name="logistics_type" value="seller" @checked(old('logistics_type') === 'seller') required>
                                    <span class="choice-check"></span>
                                    <div class="choice-icon">V</div>
                                    <h3>Logistique vendeur</h3>
                                    <p>Organisez directement les livraisons de vos commandes avec vos propres moyens.</p>
                                    <ul>
                                        <li>Zones et tarifs définis par votre boutique</li>
                                        <li>Délais et capacités sous votre responsabilité</li>
                                        <li>Configuration requise avant la publication</li>
                                    </ul>
                                </label>
                            </div>
                            @error('logistics_type')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- ÉTAPE 3 --}}
                <section class="wizard-step" data-step="3" hidden>
                    <div class="step-header">
                        <div>
                            <h2>Étape 3 sur 5 <span>— Vérification d’identité</span></h2>
                            <p>Le contrôle KYC est séparé de l’activation immédiate de la boutique.</p>
                        </div>
                        <span class="step-badge">Sécurité vendeur</span>
                    </div>

                    <div class="content-grid content-grid-single">
                        <div class="form-panel">

                            <div class="form-grid identity-meta-grid">
                                <div class="field">
                                    <label for="identityCountry">Pays de délivrance <em>*</em></label>
                                    <select id="identityCountry" name="identityCountry" required>
                                        <option value="ci" selected>Côte d’Ivoire</option>
                                    </select>
                                    @error('identityCountry')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="identityType">Type de pièce <em>*</em></label>
                                    <select id="identityType" name="identityType" required>
                                        <option value="">Sélectionner</option>
                                        <option value="cni" @selected(old('identityType') === 'cni')>Carte nationale d’identité</option>
                                        <option value="passport" @selected(old('identityType') === 'passport')>Passeport</option>
                                        <option value="permis" @selected(old('identityType') === 'permis')>Permis de conduire</option>
                                        <option value="resident" @selected(old('identityType') === 'resident')>Carte de résident</option>
                                    </select>
                                    @error('identityType')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="identityNumber">Numéro de la pièce <em>*</em></label>
                                    <input id="identityNumber" name="identityNumber" type="text" value="{{ old('identityNumber') }}" required placeholder="Saisissez le numéro exactement comme sur la pièce">
                                    @error('identityNumber')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="section-divider section-divider-spaced">
                                <div>
                                    <span class="section-number">D</span>
                                    <div>
                                        <h3>Mode d’ajout du document</h3>
                                        <p>Importez un PDF complet ou les images recto et verso.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="choice-grid upload-choice-grid">
                                <label class="choice-card compact-choice">
                                    <input type="radio" name="identityUploadMode" value="pdf" @checked(old('identityUploadMode', 'pdf') === 'pdf') required>
                                    <span class="choice-check"></span>
                                    <div class="choice-icon">PDF</div>
                                    <h3>Fichier PDF</h3>
                                    <p>Un document unique contenant les faces nécessaires.</p>
                                </label>

                                <label class="choice-card compact-choice">
                                    <input type="radio" name="identityUploadMode" value="scan" @checked(old('identityUploadMode') === 'scan') required>
                                    <span class="choice-check"></span>
                                    <div class="choice-icon">IMG</div>
                                    <h3>Recto / verso</h3>
                                    <p>Deux images nettes prises avec un téléphone.</p>
                                </label>
                            </div>

                            <div id="identityPdfFields" class="upload-panel">
                                <div class="field">
                                    <label for="identityFile">Pièce d’identité en PDF <em>*</em></label>
                                    <input id="identityFile" name="identityFile" type="file" accept="application/pdf">
                                    <small>PDF uniquement, 10 Mo maximum.</small>
                                    @error('identityFile')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div id="identityScanFields" class="upload-panel" hidden>
                                <div class="form-grid two-cols">
                                    <div class="field">
                                        <label for="identityFileFront">Image recto <em>*</em></label>
                                        <input id="identityFileFront" name="identityFileFront" type="file" accept="image/jpeg,image/png,image/webp">
                                        @error('identityFileFront')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="field">
                                        <label for="identityFileBack">Image verso <em>*</em></label>
                                        <input id="identityFileBack" name="identityFileBack" type="file" accept="image/jpeg,image/png,image/webp">
                                        @error('identityFileBack')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ÉTAPE 4 --}}
                <section class="wizard-step" data-step="4" hidden>
                    <div class="step-header">
                        <div>
                            <h2>Étape 4 sur 5 <span>— Paiement vendeur</span></h2>
                            <p>Choisissez le calendrier de reversement de vos ventes éligibles.</p>
                        </div>
                        <span class="step-badge">Reversements</span>
                    </div>

                    <div class="content-grid content-grid-single">
                        <div class="form-panel">
                            <div class="payment-rules">
                                <div>
                                    <span>1</span>
                                    <div><strong>Commande payée</strong><small>Le paiement client est validé.</small></div>
                                </div>
                                <i></i>
                                <div>
                                    <span>2</span>
                                    <div><strong>Livraison terminée</strong><small>Le colis est marqué livré.</small></div>
                                </div>
                                <i></i>
                                <div>
                                    <span>3</span>
                                    <div><strong>Réception confirmée</strong><small>Le reversement devient éligible.</small></div>
                                </div>
                                <i></i>
                                <div>
                                    <span>4</span>
                                    <div><strong>Paiement programmé</strong><small>Selon votre calendrier choisi.</small></div>
                                </div>
                            </div>

                            <div class="choice-grid payment-choice-grid">
                                <label class="choice-card payment-card choice-card-featured">
                                    <input type="radio" name="payment_mode" value="post_delivery" @checked(old('payment_mode', 'post_delivery') === 'post_delivery') required>
                                    <span class="choice-check"></span>
                                    <span class="choice-tag">Paiement rapide</span>
                                    <div class="payment-card__top">
                                        <div class="choice-icon">72h</div>
                                        <div>
                                            <h3>Paiement après livraison</h3>
                                            <p>Reversement programmé 72 heures après l’éligibilité.</p>
                                        </div>
                                    </div>
                                    <ul>
                                        <li>90 premiers jours sans frais de traitement du reversement</li>
                                        <li>Ensuite 5 % de frais de traitement sur ce mode rapide</li>
                                        <li>La commission marketplace éventuelle reste distincte</li>
                                    </ul>
                                </label>

                                <label class="choice-card payment-card">
                                    <input type="radio" name="payment_mode" value="weekly" @checked(old('payment_mode') === 'weekly') required>
                                    <span class="choice-check"></span>
                                    <span class="choice-tag choice-tag-green">Sans frais de traitement</span>
                                    <div class="payment-card__top">
                                        <div class="choice-icon">7J</div>
                                        <div>
                                            <h3>Paiement hebdomadaire</h3>
                                            <p>Les ventes éligibles sont regroupées dans un batch hebdomadaire.</p>
                                        </div>
                                    </div>
                                    <ul>
                                        <li>Regroupement des reversements arrivés à échéance</li>
                                        <li>Aucun frais de traitement spécifique au calendrier</li>
                                        <li>Suivi du batch depuis l’espace vendeur</li>
                                    </ul>
                                </label>
                            </div>
                            @error('payment_mode')<p class="field-error">{{ $message }}</p>@enderror

                            <div class="payout-account-card">
                                <div class="payout-account-card__head">
                                    <div>
                                        <span class="mini-badge">Compte de réception</span>
                                        <h3>Coordonnées Mobile Money</h3>
                                    </div>
                                    <p>Ces informations sont obligatoires pour préparer les reversements.</p>
                                </div>

                                <div class="form-grid two-cols">
                                    <div class="field">
                                        <label for="mmOperator">Opérateur <em>*</em></label>
                                        <select id="mmOperator" name="mmOperator" required>
                                            <option value="">Sélectionner</option>
                                            @foreach(['orange'=>'Orange Money','mtn'=>'MTN Mobile Money','wave'=>'Wave','moov'=>'Moov Money'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('mmOperator') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('mmOperator')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field">
                                        <label for="mmNumber">Numéro de réception <em>*</em></label>
                                        <input id="mmNumber" name="mmNumber" type="tel" value="{{ $formatCiPhone(old('mmNumber')) }}" required inputmode="numeric" maxlength="14" placeholder="07 00 00 00 00">
                                        @error('mmNumber')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="field span-2">
                                        <label for="mmHolder">Nom du titulaire <em>*</em></label>
                                        <input id="mmHolder" name="mmHolder" type="text" value="{{ old('mmHolder') }}" required placeholder="Nom exactement enregistré chez l’opérateur">
                                        @error('mmHolder')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ÉTAPE 5 --}}
                <section class="wizard-step" data-step="5" hidden>
                    <div class="step-header">
                        <div>
                            <h2>Étape 5 sur 5 <span>— Conditions vendeur</span></h2>
                            <p>Relisez les engagements essentiels avant l’ouverture.</p>
                        </div>
                        <span class="step-badge">Validation finale</span>
                    </div>

                    <div class="content-grid final-grid">
                        <div class="form-panel">
                            <div class="summary-box">
                                <div class="summary-box__head">
                                    <span class="mini-badge">Résumé</span>
                                    <h3>Ce qui se passe après votre validation</h3>
                                </div>
                                <div class="summary-list">
                                    <div>
                                        <span>01</span>
                                        <div><strong>Boutique activée immédiatement</strong><p>Vous accédez directement au tableau de bord vendeur.</p></div>
                                    </div>
                                    <div>
                                        <span>02</span>
                                        <div><strong>KYC suivi séparément</strong><p>Vos documents restent en vérification sans bloquer automatiquement l’accès initial.</p></div>
                                    </div>
                                    <div>
                                        <span>03</span>
                                        <div><strong>Règles logistiques appliquées</strong><p>OVANIE Logistics publie avec une adresse GPS complète. La logistique vendeur exige sa configuration avant publication produit.</p></div>
                                    </div>
                                    <div>
                                        <span>04</span>
                                        <div><strong>Reversements programmés automatiquement</strong><p>Le calendrier choisi s’applique après livraison, réception et éligibilité du paiement.</p></div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="terms-button" data-open-terms>
                                <span>Lire les conditions vendeur OVANIE</span>
                                <span>↗</span>
                            </button>

                            <label class="terms-accept">
                                <input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms')) required>
                                <span class="terms-accept__box"></span>
                                <span>J’accepte les <button type="button" data-open-terms>conditions vendeur</button>, la politique de commission applicable aux ventes et les règles de reversement choisies. <em>*</em></span>
                            </label>
                            @error('terms')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <aside class="final-aside">
                            <div class="final-aside__illustration" aria-hidden="true">
                                <svg viewBox="0 0 240 190">
                                    <rect x="64" y="28" width="112" height="132" rx="10"/>
                                    <path d="M96 28v-8h48v8M85 64h70M85 86h54M85 108h62"/>
                                    <circle cx="170" cy="136" r="34" class="check-circle"/>
                                    <path d="m154 136 10 10 22-24" class="check-mark"/>
                                </svg>
                            </div>
                            <h3>Vous y êtes presque !</h3>
                            <p>Relisez et acceptez les conditions pour ouvrir votre boutique en toute confiance.</p>
                            <div class="final-aside__notice">
                                <span>i</span>
                                <p>En validant, vous confirmez avoir lu et accepté les conditions vendeur, la politique de commission et les règles de reversement choisies.</p>
                            </div>
                        </aside>
                    </div>
                </section>
            </div>

            <footer class="wizard-actions">
                <button type="button" id="previousStep" class="button button-secondary">
                    <span>←</span> Précédent
                </button>

                <div class="step-counter">
                    <span class="step-counter__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M9 5h6M9 3h6v4H9zM7 5H5v16h14V5h-2M8 11h8M8 15h8M8 19h5"/></svg>
                    </span>
                    <span class="step-counter__copy">
                        <strong>Étape <span id="currentStepNumber">1</span>/5</strong>
                        <small id="currentStepLabel">Informations personnelles</small>
                    </span>
                </div>

                <button type="button" id="nextStep" class="button button-primary">
                    Suivant <span>→</span>
                </button>

                <button type="submit" id="submitShop" class="button button-primary button-submit" hidden>
                    <span class="button-spinner" aria-hidden="true"></span>
                    <span>Ouvrir ma boutique</span>
                    <span>→</span>
                </button>
            </footer>
        </form>
    </main>


    <dialog id="locationMapModal" class="location-map-modal">
        <div class="location-map-modal__head">
            <div>
                <span class="mini-badge">Position exacte</span>
                <h2>Placez le marqueur sur l’entrée de la boutique</h2>
            </div>
            <button type="button" id="closeLocationMapButton" aria-label="Fermer">×</button>
        </div>
        <div class="location-map-modal__body">
            <div id="shopMap" class="shop-map" aria-label="Carte de positionnement de la boutique"></div>
            <div class="map-card__footer">
                <div>
                    <strong id="mapAddressLabel">Position du point d’enlèvement</strong>
                    <div class="coordinates"><span id="latitudeLabel">Lat. —</span><span id="longitudeLabel">Lng. —</span></div>
                </div>
                <small>Déplacez le marqueur exactement sur l’entrée utilisée par le livreur.</small>
            </div>
        </div>
        <div class="location-map-modal__foot">
            <button type="button" id="cancelLocationMapButton" class="button button-secondary">Annuler</button>
            <button type="button" id="confirmLocationButton" class="button button-primary">Confirmer cette position</button>
        </div>
    </dialog>

    <dialog id="termsModal" class="terms-modal">
        <div class="terms-modal__head">
            <div>
                <span class="mini-badge">Document vendeur</span>
                <h2>Conditions vendeur OVANIE</h2>
            </div>
            <button type="button" data-close-terms aria-label="Fermer">×</button>
        </div>
        <div class="terms-modal__body">
            <h3>1. Exactitude des informations</h3>
            <p>Le vendeur garantit l’exactitude des informations de son compte, de sa boutique, de ses documents et de ses produits. Toute information trompeuse peut entraîner une demande de correction ou une suspension.</p>

            <h3>2. Produits et disponibilité</h3>
            <p>Le vendeur maintient ses prix, stocks, unités de vente, dimensions, poids et caractéristiques techniques à jour. Les informations logistiques produit doivent être exactes pour permettre le calcul de livraison.</p>

            <h3>3. Traitement des commandes</h3>
            <p>Le vendeur traite les commandes dans le délai annoncé. Une commande n’est communiquée au vendeur qu’après la validation du paiement ou du mode de paiement prévu par le parcours OVANIE.</p>

            <h3>4. Logistique</h3>
            <p>Avec OVANIE Logistics, la plateforme organise la grille tarifaire et l’exécution logistique. Avec la logistique vendeur, le vendeur doit maintenir au moins une zone active, des tarifs, des délais et des capacités valides avant de pouvoir publier ses produits.</p>

            <h3>5. Reversements</h3>
            <p>Les reversements sont programmés lorsque la livraison est terminée, la réception client confirmée, le paiement éligible et qu’aucun retour actif ne bloque la transaction. Le calendrier choisi à l’ouverture de la boutique détermine la date de programmation.</p>

            <h3>6. Contrôle et sécurité</h3>
            <p>OVANIE peut vérifier les documents transmis, demander une mise à jour, suspendre une fonctionnalité ou bloquer un reversement en cas d’anomalie, litige, retour actif ou risque de fraude.</p>
        </div>
        <div class="terms-modal__foot">
            <button type="button" class="button button-primary" data-close-terms>J’ai compris</button>
        </div>
    </dialog>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.js" defer></script>
    <script src="{{ asset('js/open-shop.js') }}?v={{ file_exists(public_path('js/open-shop.js')) ? filemtime(public_path('js/open-shop.js')) : time() }}" defer></script>
</body>
</html>
