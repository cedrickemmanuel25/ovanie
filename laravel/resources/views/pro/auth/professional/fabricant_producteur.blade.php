{{--
    Informations métier — Fabricant / Producteur (étape 3 sur 4).
    Cinq sections en onglets : Responsable, Entreprise, Usine, Commerce, Vérification.
    Mise en page : public/css/pro-fabricant-form.css (préfixe fm-) ; comportement : public/js/pro-business.js.
    La page n'est pas mise à l'échelle (classe « oa-natural ») : tailles réelles, largeur centrée, défilement normal.
--}}
@extends('pro.auth.access')
@section('title', 'Informations métier')
@section('html_class', 'oa-natural')
@include('pro.auth.access.business-setup')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pro-fabricant-form.css') }}?v={{ @filemtime(public_path('css/pro-fabricant-form.css')) ?: '1' }}">
@endpush
@section('content')
@php
    $opt = (array) config('ovanie_pro.business_options', []);
    $cities = (array) config('ovanie_pro_cities', []);
    $v = fn (string $field, $fallback = '') => old($field, $values[$field] ?? $fallback);
    $docs = fn (string $docType) => $providedDocuments->get($docType, collect());

    // Onglet => [libellé, pictogramme, champs concernés (pour rouvrir le bon onglet en cas d'erreur)].
    $tabs = [
        'responsible' => ['Responsable', 'user', ['responsible_position', 'professional_email', 'professional_phone', 'professional_whatsapp', 'identity_last_name', 'identity_first_name']],
        'company' => ['Entreprise', 'building', ['legal_name', 'trade_name', 'legal_form', 'founded_year', 'registration_number', 'tax_number', 'city', 'website', 'address', 'registration_country', 'sector', 'product_categories']],
        'factory' => ['Usine', 'factory', ['factory_name', 'factory_country', 'factory_city', 'factory_address', 'factory_position', 'factory_founded_year', 'factory_area', 'production_lines', 'capacity_monthly', 'capacity_annual', 'main_equipment', 'products_manufactured', 'markets_served']],
        'commerce' => ['Commerce', 'cart', ['moq', 'samples', 'production_lead_time', 'countries_served', 'export_enabled', 'export_countries', 'shipping_modes', 'incoterms']],
        'verification' => ['Vérification', 'shield', ['representative_id_type', 'representative_id_number', 'certification_type', 'certification_body', 'certification_number', 'certification_issue_date', 'certification_expiry_date', 'documents.registry', 'documents.tax_certificate', 'documents.representative_id', 'documents.other_legal_documents', 'documents.certifications', 'documents.factory_photos', 'documents.factory_videos', 'documents.technical_documents']],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    // Colonne de droite : accroche de la photo et conseil, par onglet.
    $side = [
        'responsible' => ['Transformez votre production en opportunités sur OVANIE PRO.', 'Plus vos informations sont complètes, plus votre profil sera visible auprès des acheteurs, des entreprises BTP et des partenaires.'],
        'company' => ['Transformez votre production en opportunités sur OVANIE PRO.', 'Saisissez les informations telles qu’elles figurent sur vos documents officiels : elles seront comparées aux justificatifs.'],
        'factory' => ['Valorisez votre outil de production et vos capacités industrielles.', 'Les capacités sont affichées comme « déclarées » jusqu’à la vérification de votre compte.'],
        'commerce' => ['Développez vos débouchés en Afrique de l’Ouest et à l’international.', 'Ces conditions sont indicatives : vous pourrez les ajuster par produit, volume et destination.'],
        'verification' => ['Des produits de qualité pour bâtir l’Afrique de demain.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $units = ['tonnes' => 'Tonnes', 'kg' => 'Kg', 'm3' => 'm³', 'm2' => 'm²', 'unites' => 'Unités', 'sacs' => 'Sacs', 'palettes' => 'Palettes', 'litres' => 'Litres'];
    $modes = ['fixed' => 'Valeur fixe', 'on_request' => 'Sur demande', 'variable' => 'Variable selon produit'];
    $legalForms = ['sarl' => 'SARL', 'sarlu' => 'SARLU', 'sa' => 'SA', 'sas' => 'SAS', 'sasu' => 'SASU', 'ei' => 'Entreprise individuelle', 'gie' => 'GIE', 'cooperative' => 'Coopérative', 'autre' => 'Autre'];
    $idTypes = ['cni' => 'Carte nationale d’identité', 'passport' => 'Passeport', 'driver_license' => 'Permis de conduire'];
    $markets = ['Marché local', 'Marché national', 'Marché sous-régional (Afrique de l’Ouest)', 'Export international'];
    $shippingModes = ['terrestre' => ['Terrestre', 'Par camion'], 'maritime' => ['Maritime', 'Par conteneur'], 'aerien' => ['Aérien', 'Par fret aérien']];
    $valueWithMode = [
        'moq' => ['Quantité minimale de commande (MOQ)', 'Ex. 25', $units, 'tonnes'],
        'production_lead_time' => ['Délai moyen de production', 'Ex. 10', ['jours' => 'Jours', 'semaines' => 'Semaines', 'mois' => 'Mois'], 'jours'],
    ];
    $capacities = ['capacity_monthly' => ['Capacité mensuelle', 'Ex. 1 000'], 'capacity_annual' => ['Capacité annuelle', 'Ex. 12 000']];
    $shipping = (array) $v('shipping_modes', []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $country = $profile->registration_country ?: $profile->activity_country;
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('briefcase', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité de {{ $profileMeta['label'] }}." data-verification="Ajoutez les documents nécessaires à la vérification de votre dossier.">Décrivez votre activité de {{ $profileMeta['label'] }}.</p>
            </div>
            <a class="fm-head__profile" href="{{ route('pro.register.qualification') }}">
                <img src="{{ asset('ovanie-pro/profiles/' . $profileKey . '-card.jpg') }}" alt="">
                <span><b>{{ $profileMeta['label'] }}</b><small>Changer de profil</small></span>
            </a>
        </header>

        <nav class="fm-tabs" role="tablist" aria-label="Sections du formulaire">
            @foreach($tabs as $tabKey => $tabDef)
                <button type="button" class="fm-tab" role="tab" data-pb-tab="{{ $tabKey }}" aria-controls="panel-{{ $tabKey }}">
                    @proicon($tabDef[1], 'oa-ico')<span>{{ $tabDef[0] }}</span><i class="pb-tab__ok" hidden>@proicon('check', 'oa-ico')</i>
                </button>
            @endforeach
            <div class="fm-tabs__meter" aria-hidden="true">
                <span><b data-pb-done>0</b> / 5 sections</span>
                <div class="fm-segments" data-pb-segments>@for($i = 0; $i < 5; $i++)<i></i>@endfor</div>
            </div>
        </nav>

        @include('pro.auth.access.alerts', ['except' => collect($tabs)->pluck(2)->flatten()->all()])
        @if($errorTab)
            <div class="oa-alert oa-alert--error" role="alert">@proicon('info', 'oa-ico')<span>Certaines informations sont manquantes ou incorrectes. Les champs concernés sont signalés en rouge.</span></div>
        @endif

        {{-- ===================== 1. Responsable ===================== --}}
        <section class="fm-panel" id="panel-responsible" data-pb-panel="responsible" role="tabpanel">
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('user', 'oa-ico')</span><div><h2>Responsable du profil</h2><p>La personne qui gère le compte professionnel de l’entreprise.</p></div></header>

                <div class="fm-identity" data-pb-identity>
                    <div class="pb-field"><label for="identity_last_name">Nom</label><input class="pb-input" id="identity_last_name" name="identity_last_name" value="{{ old('identity_last_name', $user->last_name) }}" maxlength="100" readonly></div>
                    <div class="pb-field"><label for="identity_first_name">Prénoms</label><input class="pb-input" id="identity_first_name" name="identity_first_name" value="{{ old('identity_first_name', $user->first_name) }}" maxlength="100" readonly></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-identity-edit>@proicon('wrench', 'oa-ico') Modifier</button>
                </div>

                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction dans l’entreprise', 'type' => 'select', 'options' => $opt['positions'], 'required' => true, 'icon' => 'user', 'empty' => 'Sélectionnez votre fonction'])
                    @include('pro.auth.access.field', ['name' => 'professional_email', 'label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'placeholder' => 'contact@monentreprise.ci'])
                    <div class="pb-field @error('professional_phone') is-invalid @enderror">
                        <label for="professional_phone">Téléphone professionnel&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_phone', 'value' => $values['professional_phone'] ?? '', 'id' => 'professional_phone', 'required' => true])
                        @error('professional_phone')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('professional_whatsapp') is-invalid @enderror" data-pb-whatsapp>
                        <label for="professional_whatsapp">WhatsApp professionnel</label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_whatsapp', 'value' => $values['professional_whatsapp'] ?? '', 'id' => 'professional_whatsapp', 'prefixIcon' => 'phone'])
                        @error('professional_whatsapp')<p class="pb-error">{{ $message }}</p>@enderror
                        <label class="pb-switch"><input type="checkbox" data-pb-whatsapp-same @checked($whatsappSame)><span class="pb-switch__track"></span><span><b>Identique au téléphone professionnel</b></span></label>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 2. Entreprise ===================== --}}
        <section class="fm-panel" id="panel-company" data-pb-panel="company" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('building', 'oa-ico')</span><div><h2>Identité légale</h2><p>Telle qu’elle figure sur vos documents officiels.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. Entreprise Industrielle du BTP SARL', 'class' => 'pb-span-2'])
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial', 'optional' => true, 'placeholder' => 'Ex. EIBTP'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $legalForms, 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre de commerce (RCCM)', 'required' => true, 'placeholder' => 'Ex. CI-ABJ-2023-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro contribuable', 'required' => true, 'placeholder' => 'Ex. 1234567 F'])
                    @include('pro.auth.access.field', ['name' => 'founded_year', 'label' => 'Année de création', 'type' => 'year', 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Siège</h2><p>Adresse d’enregistrement de l’entreprise.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays d’enregistrement', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Zone industrielle de Yopougon, rue des Usines'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('store', 'oa-ico')</span><div><h2>Activité</h2><p>Votre secteur et ce que vous fabriquez.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'sector', 'label' => 'Secteur d’activité principal', 'type' => 'select', 'options' => $opt['sectors'], 'required' => true, 'icon' => 'chart', 'empty' => 'Sélectionnez un secteur'])
                    @include('pro.auth.access.field', ['name' => 'website', 'label' => 'Site web', 'type' => 'url', 'optional' => true, 'icon' => 'globe', 'placeholder' => 'https://www.votre-entreprise.ci'])
                </div>
                <div class="pb-field">
                    <label>Catégories de produits fabriqués&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'product_categories', 'value' => $values['product_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $opt['product_categories'], 'required' => true])
                </div>
            </div>
        </section>

        {{-- ===================== 3. Usine ===================== --}}
        <section class="fm-panel" id="panel-factory" data-pb-panel="factory" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec">
                    <span class="fm-sec__icon">@proicon('factory', 'oa-ico')</span>
                    <div><h2>Site de production</h2><p>Votre usine principale. Vous pourrez en ajouter d’autres après la création du compte.</p></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-copy-company>@proicon('building', 'oa-ico') Même adresse que le siège</button>
                </header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'factory_name', 'label' => 'Nom de l’usine', 'required' => true, 'placeholder' => 'Ex. Usine de Yopougon'])
                    @include('pro.auth.access.field', ['name' => 'factory_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'factory_city', 'label' => 'Ville', 'countryField' => 'factory_country'])
                </div>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'factory_address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Zone industrielle, rue des Usines, lot n°12'])
                    <div class="pb-field @error('factory_position') is-invalid @enderror">
                        <label for="f-factory_position">Position GPS&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-inline">
                            <div class="pb-icon-input">@proicon('map-pin', 'oa-ico')<input class="pb-input" id="f-factory_position" name="factory_position" value="{{ $v('factory_position') }}" placeholder="5.3453, -4.0053" data-required="1" data-pb-position></div>
                            <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-locate>@proicon('map-pin', 'oa-ico') Ma position</button>
                        </div>
                        <p class="pb-help" data-pb-locate-msg>Latitude, longitude du site.</p>
                        @error('factory_position')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'factory_founded_year', 'label' => 'Année de mise en service', 'type' => 'year', 'required' => true, 'empty' => 'Sélectionnez'])
                    <div class="pb-field @error('factory_area') is-invalid @enderror">
                        <label for="f-factory_area">Superficie du site&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-combo"><input class="pb-input" id="f-factory_area" name="factory_area" inputmode="numeric" value="{{ $v('factory_area') }}" placeholder="Ex. 5000" data-required="1" data-pb-integer>
                            <div class="pb-select"><select name="factory_area_unit" aria-label="Unité de superficie">@foreach(['m2' => 'm²', 'ha' => 'ha'] as $k => $l)<option value="{{ $k }}" @selected($v('factory_area_unit', 'm2') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                        @error('factory_area')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('production_lines') is-invalid @enderror">
                        <label for="f-production_lines">Lignes de production&nbsp;<span class="oa-req">*</span></label>
                        <input class="pb-input" id="f-production_lines" name="production_lines" inputmode="numeric" value="{{ $v('production_lines') }}" placeholder="Ex. 3" data-required="1" data-pb-integer>
                        @error('production_lines')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('chart', 'oa-ico')</span><div><h2>Production</h2><p>Capacités réelles du site et produits fabriqués.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach($capacities as $capName => $capDef)
                        <div class="pb-field @error($capName) is-invalid @enderror">
                            <label for="f-{{ $capName }}">{{ $capDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                            <div class="pb-combo"><input class="pb-input" id="f-{{ $capName }}" name="{{ $capName }}" inputmode="decimal" value="{{ $v($capName) }}" placeholder="{{ $capDef[1] }}" data-required="1">
                                <div class="pb-select"><select name="{{ $capName }}_unit" aria-label="Unité">@foreach($units as $k => $l)<option value="{{ $k }}" @selected($v($capName . '_unit', 'tonnes') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                            @error($capName)<p class="pb-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="pb-field">
                        <label>Produits fabriqués sur ce site&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'products_manufactured', 'value' => $values['products_manufactured'] ?? '', 'placeholder' => 'Rechercher un produit…', 'suggestions' => $opt['product_categories'], 'required' => true])
                    </div>
                    @include('pro.auth.access.field', ['name' => 'markets_served', 'label' => 'Marché principal', 'type' => 'select', 'options' => $markets, 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('wrench', 'oa-ico')</span><div><h2>Équipements <small>(facultatif)</small></h2><p>Les principales machines de cette usine.</p></div></header>
                <div class="fm-equipment" data-pb-equipment>
                    <div class="pb-field"><label for="eq-name">Équipement</label><div class="pb-icon-input">@proicon('search', 'oa-ico')<input class="pb-input" id="eq-name" list="pb-equipment-list" placeholder="Rechercher…" data-eq="name"></div><datalist id="pb-equipment-list">@foreach($opt['equipment'] as $equipment)<option value="{{ $equipment }}">@endforeach</datalist></div>
                    <div class="pb-field"><label for="eq-brand">Marque</label><input class="pb-input" id="eq-brand" placeholder="Ex. Liebherr" data-eq="brand"></div>
                    <div class="pb-field"><label for="eq-qty">Quantité</label><input class="pb-input" id="eq-qty" inputmode="numeric" placeholder="Ex. 2" data-eq="qty"></div>
                    <div class="pb-field"><label for="eq-year">Année</label><div class="pb-select"><select id="eq-year" data-eq="year"><option value="">—</option>@for($y = (int) date('Y'); $y >= 1980; $y--)<option>{{ $y }}</option>@endfor</select></div></div>
                    <button type="button" class="oa-btn fm-btn-soft" data-eq-add>@proicon('plus', 'oa-ico') Ajouter</button>
                </div>
                @include('pro.auth.access.tags', ['name' => 'main_equipment', 'value' => $values['main_equipment'] ?? '', 'placeholder' => '', 'suggestions' => [], 'bare' => true])
            </div>
        </section>

        {{-- ===================== 4. Commerce ===================== --}}
        <section class="fm-panel" id="panel-commerce" data-pb-panel="commerce" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('cart', 'oa-ico')</span><div><h2>Conditions commerciales</h2><p>Ce que les acheteurs doivent savoir avant de vous contacter.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach($valueWithMode as $modeName => $modeDef)
                        @php
                            $modeValue = $v($modeName) === 'Sur demande' ? '' : $v($modeName);
                            $modeChoice = $v($modeName . '_mode', $v($modeName) === 'Sur demande' ? 'on_request' : 'fixed');
                        @endphp
                        <div class="pb-field @error($modeName) is-invalid @enderror" data-pb-mode-field>
                            <label for="f-{{ $modeName }}">{{ $modeDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                            <div class="pb-combo"><input class="pb-input" id="f-{{ $modeName }}" name="{{ $modeName }}" value="{{ $modeValue }}" placeholder="{{ $modeDef[1] }}" data-required="1" data-pb-mode-input>
                                <div class="pb-select"><select name="{{ $modeName }}_unit" aria-label="Unité">@foreach($modeDef[2] as $k => $l)<option value="{{ $k }}" @selected($v($modeName . '_unit', $modeDef[3]) === $k)>{{ $l }}</option>@endforeach</select></div></div>
                            <div class="pb-pills">@foreach($modes as $k => $l)<label><input type="radio" name="{{ $modeName }}_mode" value="{{ $k }}" @checked($modeChoice === $k)><span>{{ $l }}</span></label>@endforeach</div>
                            @error($modeName)<p class="pb-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="pb-grid pb-grid--side">
                    <div class="pb-field @error('samples') is-invalid @enderror">
                        <label>Échantillons disponibles&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-toggle pb-toggle--wide" data-required-group="samples">
                            @foreach(['yes' => 'Oui', 'no' => 'Non'] as $k => $l)<label><input type="radio" name="samples" value="{{ $k }}" @checked($v('samples') === $k)><span>{{ $l }}</span></label>@endforeach
                        </div>
                        @error('samples')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field">
                        <label>Pays desservis&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'countries_served', 'value' => $values['countries_served'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                </div>
            </div>

            <div class="fm-card" data-pb-export>
                <header class="fm-sec">
                    <span class="fm-sec__icon">@proicon('globe', 'oa-ico')</span>
                    <div><h2>Export</h2><p>Vos capacités à livrer hors de votre pays.</p></div>
                    <span class="pb-badge pb-badge--ok pb-badge--lg" data-pb-export-only>@proicon('check', 'oa-ico') Export activé</span>
                </header>
                <div class="pb-grid pb-grid--side">
                    <div class="pb-field @error('export_enabled') is-invalid @enderror">
                        <label>Exportez-vous vos produits&nbsp;?&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-toggle pb-toggle--wide" data-required-group="export_enabled">
                            @foreach(['yes' => 'Oui', 'no' => 'Non'] as $k => $l)<label><input type="radio" name="export_enabled" value="{{ $k }}" data-pb-export-radio @checked($v('export_enabled') === $k)><span>{{ $l }}</span></label>@endforeach
                        </div>
                        @error('export_enabled')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field" data-pb-export-only>
                        <label>Pays déjà exportés <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'export_countries', 'value' => $values['export_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries])
                    </div>
                </div>
                <div class="pb-grid pb-grid--2" data-pb-export-only>
                    <div class="pb-field @error('shipping_modes') is-invalid @enderror">
                        <label>Modes d’expédition&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-ship">
                            @foreach($shippingModes as $k => $shipDef)
                                <label><input type="checkbox" name="shipping_modes[]" value="{{ $k }}" @checked(in_array($k, $shipping, true))><span class="pb-ship__box">@proicon('check', 'oa-ico')</span><span><b>{{ $shipDef[0] }}</b><small>{{ $shipDef[1] }}</small></span></label>
                            @endforeach
                        </div>
                        @error('shipping_modes')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('incoterms') is-invalid @enderror">
                        <label>Incoterms utilisés&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'incoterms', 'value' => $values['incoterms'] ?? '', 'placeholder' => 'Rechercher un Incoterm…', 'suggestions' => $opt['incoterms']])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 5. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="company-docs">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('building', 'oa-ico')</span><div><h2>Documents de l’entreprise</h2><p>Les intitulés peuvent varier selon votre pays.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.dropzone', ['type' => 'registry', 'doc' => $documents['registry'], 'existing' => $docs('registry'), 'title' => 'Registre de commerce (RCCM)', 'hint' => null])
                    @include('pro.auth.access.dropzone', ['type' => 'tax_certificate', 'doc' => $documents['tax_certificate'], 'existing' => $docs('tax_certificate'), 'title' => 'Document fiscal', 'hint' => null])
                    @include('pro.auth.access.dropzone', ['type' => 'other_legal_documents', 'doc' => $documents['other_legal_documents'], 'existing' => $docs('other_legal_documents'), 'title' => 'Autres documents légaux', 'hint' => null])
                </div>
            </div>

            <div class="fm-card" data-pb-status="representative">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('id-card', 'oa-ico')</span><div><h2>Pièce d’identité du représentant</h2><p>Celle du responsable déclaré dans la première section.</p></div></header>
                <div class="pb-grid pb-grid--rep">
                    @include('pro.auth.access.field', ['name' => 'representative_id_type', 'label' => 'Type de pièce', 'type' => 'select', 'options' => $idTypes, 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'representative_id_number', 'label' => 'Numéro de la pièce', 'required' => true, 'placeholder' => 'Ex. CI0012345678'])
                    @include('pro.auth.access.dropzone', ['type' => 'representative_id', 'doc' => $documents['representative_id'], 'existing' => $docs('representative_id'), 'title' => 'Copie de la pièce', 'hint' => null])
                </div>
            </div>

            <div class="fm-card" data-pb-status="proofs">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('factory', 'oa-ico')</span><div><h2>Preuves industrielles</h2><p>Photos, vidéos et documents techniques de votre usine.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.dropzone', ['type' => 'factory_photos', 'doc' => $documents['factory_photos'], 'existing' => $docs('factory_photos'), 'title' => 'Photos de l’usine', 'hint' => null, 'help' => 'JPG, PNG. 10 Mo max, 6 fichiers au plus.'])
                    @include('pro.auth.access.dropzone', ['type' => 'factory_videos', 'doc' => $documents['factory_videos'], 'existing' => $docs('factory_videos'), 'title' => 'Vidéos de l’usine', 'hint' => null, 'help' => 'MP4, MOV. 20 Mo max par fichier.'])
                    @include('pro.auth.access.dropzone', ['type' => 'technical_documents', 'doc' => $documents['technical_documents'], 'existing' => $docs('technical_documents'), 'title' => 'Documents techniques', 'hint' => null])
                </div>
            </div>

            <div class="fm-card" data-pb-status="certifications">
                <header class="fm-sec">
                    <span class="fm-sec__icon">@proicon('award', 'oa-ico')</span>
                    <div><h2>Certifications <small>(facultatif)</small></h2><p>Qualité, normes ou autres certifications.</p></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-cert-add>@proicon('plus', 'oa-ico') Ajouter</button>
                </header>
                <div class="pb-grid pb-grid--cert">
                    @include('pro.auth.access.field', ['name' => 'certification_type', 'label' => 'Type', 'type' => 'select', 'options' => $opt['certification_types'], 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'certification_body', 'label' => 'Organisme', 'placeholder' => 'Ex. Bureau Veritas'])
                    @include('pro.auth.access.field', ['name' => 'certification_number', 'label' => 'Numéro', 'placeholder' => 'Ex. 12345'])
                    @include('pro.auth.access.field', ['name' => 'certification_issue_date', 'label' => 'Émise le', 'type' => 'date'])
                    @include('pro.auth.access.field', ['name' => 'certification_expiry_date', 'label' => 'Expire le', 'type' => 'date'])
                </div>
                <div class="pb-certs" data-pb-certs data-types='@json($opt['certification_types'])'>
                    <textarea name="certifications_json" hidden data-pb-certs-value>{{ $v('certifications_json') }}</textarea>
                    <div data-pb-certs-rows></div>
                </div>
                @include('pro.auth.access.dropzone', ['type' => 'certifications', 'doc' => $documents['certifications'], 'existing' => $docs('certifications'), 'title' => 'Justificatifs', 'hint' => 'Obligatoire si vous déclarez une certification.', 'badge' => false])
            </div>
        </section>

        <div class="fm-actions">
            <button type="button" class="oa-btn oa-btn--outline" data-pb-prev data-back="{{ route('pro.register.qualification') }}">@proicon('chevron-left', 'oa-ico') Retour</button>
            <div class="fm-actions__end">
                <button class="oa-btn oa-btn--outline" type="submit" name="action" value="draft">@proicon('file', 'oa-ico') Enregistrer le brouillon</button>
                <button class="oa-btn oa-btn--primary" type="button" data-pb-next>Continuer @proicon('chevron-right', 'oa-ico')</button>
                <button class="oa-btn oa-btn--primary" type="submit" name="action" value="submit" data-pb-submit hidden>Valider et passer aux fonctions @proicon('chevron-right', 'oa-ico')</button>
            </div>
        </div>
    </div>

    <aside class="fm-side">
        <div class="fm-hero" style="background-image:url('{{ asset('ovanie-pro/profiles/' . $profileKey . '-hero.jpg') }}')">
            <strong>{{ $profileMeta['label'] }}</strong>
            @foreach($side as $sideKey => $sideDef)<span data-pb-side="{{ $sideKey }}" hidden>{{ $sideDef[0] }}</span>@endforeach
        </div>

        <div class="fm-box" data-pb-side-not="verification">
            <div class="fm-box__head"><h2>Votre progression</h2><span><b data-pb-done>0</b> sur 5</span></div>
            <div class="fm-bar"><i data-pb-bar></i></div>
            <ul class="fm-steps">
                @foreach($tabs as $tabKey => $tabDef)
                    <li><button type="button" data-pb-goto="{{ $tabKey }}"><span class="fm-steps__num">{{ $loop->iteration }}</span><b>{{ $tabDef[0] }}</b><em data-pb-state="{{ $tabKey }}">À compléter</em></button></li>
                @endforeach
            </ul>
        </div>

        <div class="fm-box" data-pb-side="verification" hidden>
            <div class="fm-box__head"><h2>Votre dossier</h2></div>
            <ul class="fm-steps fm-steps--docs">
                @foreach(['company-docs' => 'Documents de l’entreprise', 'representative' => 'Pièce du représentant', 'proofs' => 'Preuves industrielles', 'certifications' => 'Certifications'] as $statusKey => $statusLabel)
                    <li><span class="fm-steps__row"><span class="fm-steps__num">@proicon('file', 'oa-ico')</span><b>{{ $statusLabel }}</b><em data-pb-doc-state="{{ $statusKey }}">À fournir</em></span></li>
                @endforeach
            </ul>
        </div>

        @foreach($side as $sideKey => $sideDef)
            <div class="fm-tip" data-pb-side="{{ $sideKey }}" hidden>@proicon('lightbulb', 'oa-ico')<p>{{ $sideDef[1] }}</p></div>
        @endforeach

        <div class="fm-secure" data-pb-side="verification" hidden>@proicon('shield', 'oa-ico')<p>Vos documents restent confidentiels et ne servent qu’à vérifier votre compte.</p></div>

        <a class="fm-help" href="{{ route('contact.index') }}" target="_blank" rel="noopener">@proicon('headset', 'oa-ico')<span><b>Besoin d’aide ?</b><small>Contactez notre équipe</small></span>@proicon('chevron-right', 'oa-ico')</a>
    </aside>
</form>
@endsection
