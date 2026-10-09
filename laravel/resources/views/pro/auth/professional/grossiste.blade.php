{{--
    Informations métier — Grossiste (étape 3 sur 4).
    Cinq sections en onglets : Entreprise, Activité, Entrepôt & expédition, Commerce international, Vérification.
    Même mise en page que le Fabricant : public/css/pro-fabricant-form.css (préfixe fm-), public/js/pro-business.js.
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
    $options = fn (string $field) => (array) ($fields[$field]['options'] ?? []);

    // Onglet => [libellé, pictogramme, champs concernés (pour rouvrir le bon onglet en cas d'erreur)].
    $tabs = [
        'company' => ['Entreprise', 'building', ['legal_name', 'trade_name', 'registration_country', 'city', 'address', 'legal_form', 'registration_number', 'tax_number', 'representative_name', 'responsible_position', 'professional_phone', 'professional_whatsapp', 'professional_email', 'website']],
        'activity' => ['Activité', 'boxes', ['categories_sold', 'brands', 'goods_origin', 'distribution_status', 'stock_capacity', 'stock_unit', 'sale_units', 'moq', 'moq_unit', 'sales_volume', 'sales_volume_unit', 'volume_periodicity', 'zones_served', 'countries_served']],
        'warehouse' => ['Entrepôt & expédition', 'store', ['dispatch_country', 'dispatch_city', 'dispatch_address', 'dispatch_position', 'has_warehouses', 'warehouses', 'preparation_lead_time', 'preparation_lead_unit', 'preparation_workdays', 'preparation_conditions']],
        'international' => ['International', 'globe', ['trade_flows', 'supply_countries', 'destination_countries', 'transport_modes', 'international_notes']],
        'verification' => ['Vérification', 'shield', collect($documents)->keys()->map(fn ($k) => 'documents.' . $k)->all()],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Accédez à un large réseau d’acheteurs professionnels.', 'Saisissez les informations telles qu’elles figurent sur vos documents officiels : elles seront comparées aux justificatifs.'],
        'activity' => ['Un approvisionnement fiable pour les professionnels du BTP.', 'Les volumes et capacités sont affichés comme « déclarés » tant qu’ils ne sont pas vérifiés.'],
        'warehouse' => ['Des solutions logistiques pour approvisionner les chantiers.', 'Des délais de préparation précis aident les acheteurs à planifier leurs commandes.'],
        'international' => ['Développez vos échanges en Afrique de l’Ouest et à l’international.', 'Vous pourrez préciser les conditions d’expédition dans chacune de vos offres.'],
        'verification' => ['Complétez votre dossier pour renforcer votre crédibilité.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $units = $options('stock_unit');
    $statusHelp = [
        'importateur' => 'J’importe des produits depuis l’étranger.',
        'distributeur' => 'Je distribue des produits de marques ou de fabricants.',
        'importateur_distributeur' => 'J’importe et je distribue des produits.',
        'grossiste_independant' => 'J’achète et je revends en gros, en local.',
        'autre' => 'Autre modèle commercial.',
    ];
    $flowHelp = [
        'import' => 'J’importe des produits pour les distribuer en local.',
        'export' => 'J’exporte des produits vers d’autres pays.',
        'both' => 'J’importe et j’exporte des produits.',
        'none' => 'Pas encore d’activité internationale.',
    ];
    $transport = ['terrestre' => ['Terrestre', 'Par camion'], 'maritime' => ['Maritime', 'Par conteneur'], 'aerien' => ['Aérien', 'Par fret aérien']];
    $zones = ['National (local)', 'Afrique de l’Ouest', 'Afrique centrale', 'Afrique du Nord', 'Europe', 'Asie', 'International'];
    $brandSuggestions = ['Dangote', 'LafargeHolcim', 'CIMAF', 'SOCIM', 'Sika', 'TotalEnergies', 'Bosch', 'Makita', 'Schneider Electric', 'Legrand'];
    $warehouseTypes = ['principal' => 'Entrepôt principal', 'regional' => 'Entrepôt régional', 'depot' => 'Dépôt / point de retrait', 'transit' => 'Entrepôt de transit'];
    $warehouseUnits = ['m2' => 'm²', 'm3' => 'm³', 'tonnes' => 'Tonnes', 'palettes' => 'Palettes'];
    $countryList = array_values($countries);
    $workdays = (array) $v('preparation_workdays', ['lun', 'mar', 'mer', 'jeu', 'ven']);
    $modes = (array) $v('transport_modes', []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $country = $profile->registration_country ?: $profile->activity_country;

    // Justificatifs regroupés comme sur la maquette.
    $docGroups = [
        'legal' => ['Documents légaux', 'building', ['registry' => 'Registre de commerce (RCCM)', 'constitution_document' => 'Statuts ou acte de constitution', 'legal_existence' => 'Autre pièce d’existence légale']],
        'tax' => ['Documents fiscaux', 'file', ['tax_certificate' => 'Attestation fiscale / contribuable', 'tax_clearance' => 'Attestation de régularité fiscale', 'professional_license' => 'Licence professionnelle']],
        'activity' => ['Justificatifs d’activité', 'boxes', ['product_catalogue' => 'Catalogue ou liste de références', 'supply_proof' => 'Facture fournisseur ou bon de livraison', 'warehouse_photos' => 'Photos d’entrepôt ou de stock']],
        'permits' => ['Autorisations et certifications', 'award', ['sector_authorization' => 'Autorisation sectorielle', 'product_approval' => 'Agrément produit', 'certifications' => 'Certifications']],
    ];
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('cart', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité de {{ $profileMeta['label'] }}." data-verification="Ajoutez les justificatifs nécessaires à la vérification de votre dossier.">Décrivez votre activité de {{ $profileMeta['label'] }}.</p>
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

        {{-- ===================== 1. Entreprise ===================== --}}
        <section class="fm-panel" id="panel-company" data-pb-panel="company" role="tabpanel">
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('building', 'oa-ico')</span><div><h2>Identité légale</h2><p>Telle qu’elle figure sur vos documents officiels.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. Distribution BTP SARL', 'class' => 'pb-span-2'])
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial', 'optional' => true, 'placeholder' => 'Ex. BTP Distribution'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre de commerce (RCCM)', 'required' => true, 'placeholder' => 'Ex. CI-ABJ-2023-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro contribuable', 'required' => true, 'placeholder' => 'Ex. 1234567 F'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Siège</h2><p>Adresse d’enregistrement de l’entreprise.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays d’enregistrement', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Zone industrielle de Yopougon, lot 12'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('user', 'oa-ico')</span><div><h2>Représentant et coordonnées</h2><p>La personne qui gère le compte et les contacts utilisés avec les acheteurs.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'representative_name', 'label' => 'Nom et prénoms', 'required' => true, 'icon' => 'user', 'placeholder' => 'Ex. Kouassi Jean Marc', 'default' => trim($user->first_name . ' ' . $user->last_name)])
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction dans l’entreprise', 'type' => 'select', 'options' => $options('responsible_position'), 'required' => true, 'empty' => 'Sélectionnez'])
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
                    @include('pro.auth.access.field', ['name' => 'professional_email', 'label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'placeholder' => 'contact@monentreprise.ci'])
                    @include('pro.auth.access.field', ['name' => 'website', 'label' => 'Site web', 'type' => 'url', 'optional' => true, 'icon' => 'globe', 'placeholder' => 'https://www.monentreprise.ci'])
                </div>
            </div>
        </section>

        {{-- ===================== 2. Activité ===================== --}}
        <section class="fm-panel" id="panel-activity" data-pb-panel="activity" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('boxes', 'oa-ico')</span><div><h2>Catalogue et distribution</h2><p>Ce que vous vendez et d’où viennent vos marchandises.</p></div></header>
                <div class="pb-field @error('categories_sold') is-invalid @enderror">
                    <label>Catégories de produits vendues&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'categories_sold', 'value' => $values['categories_sold'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $opt['product_categories'], 'required' => true])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field">
                        <label>Marques distribuées <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'brands', 'value' => $values['brands'] ?? '', 'placeholder' => 'Rechercher ou saisir une marque…', 'suggestions' => $brandSuggestions])
                    </div>
                    <div class="pb-field @error('goods_origin') is-invalid @enderror">
                        <label>Origine des marchandises&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'goods_origin', 'value' => $values['goods_origin'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                </div>
                <div class="pb-field @error('distribution_status') is-invalid @enderror">
                    <label>Votre entreprise est&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices" data-required-group="distribution_status">
                        @foreach($options('distribution_status') as $k => $l)
                            <label class="fm-choice"><input type="radio" name="distribution_status" value="{{ $k }}" @checked($v('distribution_status') === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b><small>{{ $statusHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                    @error('distribution_status')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('chart', 'oa-ico')</span><div><h2>Stock et volumes</h2><p>Vos capacités de stockage et vos volumes de vente indicatifs.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach(['stock_capacity' => ['Capacité de stock', 'stock_unit', 'Ex. 5000'], 'sales_volume' => ['Volume de vente', 'sales_volume_unit', 'Ex. 500'], 'moq' => ['Quantité minimale de commande (MOQ)', 'moq_unit', 'Ex. 1']] as $qtyName => $qtyDef)
                        <div class="pb-field @error($qtyName) is-invalid @enderror">
                            <label for="f-{{ $qtyName }}">{{ $qtyDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                            <div class="pb-combo"><input class="pb-input" id="f-{{ $qtyName }}" name="{{ $qtyName }}" inputmode="decimal" value="{{ $v($qtyName) }}" placeholder="{{ $qtyDef[2] }}" data-required="1">
                                <div class="pb-select"><select name="{{ $qtyDef[1] }}" aria-label="Unité">@foreach($units as $k => $l)<option value="{{ $k }}" @selected($v($qtyDef[1], 'tonnes') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                            @error($qtyName)<p class="pb-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @include('pro.auth.access.field', ['name' => 'sale_units', 'label' => 'Unité de vente habituelle', 'type' => 'select', 'options' => $units, 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-field @error('volume_periodicity') is-invalid @enderror" style="max-width: 260px">
                    <label>Période du volume de vente&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-toggle pb-toggle--wide" data-required-group="volume_periodicity">
                        @foreach(['mensuel' => 'Par mois', 'annuel' => 'Par an'] as $k => $l)<label><input type="radio" name="volume_periodicity" value="{{ $k }}" @checked($v('volume_periodicity', 'mensuel') === $k)><span>{{ $l }}</span></label>@endforeach
                    </div>
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('globe', 'oa-ico')</span><div><h2>Marchés</h2><p>Les zones et pays que vous livrez.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('zones_served') is-invalid @enderror">
                        <label>Zones desservies&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'zones_served', 'value' => $values['zones_served'] ?? '', 'placeholder' => 'Sélectionner une zone…', 'suggestions' => $zones, 'required' => true])
                    </div>
                    <div class="pb-field @error('countries_served') is-invalid @enderror">
                        <label>Pays desservis&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'countries_served', 'value' => $values['countries_served'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 3. Entrepôt & expédition ===================== --}}
        <section class="fm-panel" id="panel-warehouse" data-pb-panel="warehouse" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec">
                    <span class="fm-sec__icon fm-sec__icon--orange">@proicon('map-pin', 'oa-ico')</span>
                    <div><h2>Lieu principal d’expédition</h2><p>Le site où vous préparez et expédiez les commandes.</p></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-copy-company data-prefix="dispatch">@proicon('building', 'oa-ico') Même adresse que le siège</button>
                </header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'dispatch_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'dispatch_city', 'label' => 'Ville', 'countryField' => 'dispatch_country'])
                    @include('pro.auth.access.field', ['name' => 'dispatch_address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Zone industrielle, rue des Entrepôts, lot 12'])
                </div>
                <div class="pb-field" style="max-width: 520px">
                    <label for="f-dispatch_position">Position GPS <small>(facultatif)</small></label>
                    <div class="pb-inline">
                        <div class="pb-icon-input">@proicon('map-pin', 'oa-ico')<input class="pb-input" id="f-dispatch_position" name="dispatch_position" value="{{ $v('dispatch_position') }}" placeholder="5.3453, -4.0053" data-pb-position></div>
                        <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-locate>@proicon('map-pin', 'oa-ico') Ma position</button>
                    </div>
                    <p class="pb-help" data-pb-locate-msg>Latitude, longitude du site.</p>
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec">
                    <span class="fm-sec__icon">@proicon('store', 'oa-ico')</span>
                    <div><h2>Entrepôts</h2><p>Vos lieux de stockage, si vous en avez.</p></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-warehouse-add data-pb-show-if="has_warehouses=yes">@proicon('plus', 'oa-ico') Ajouter un entrepôt</button>
                </header>
                <div class="pb-field @error('has_warehouses') is-invalid @enderror" style="max-width: 260px">
                    <label>Disposez-vous d’entrepôts&nbsp;?&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-toggle pb-toggle--wide" data-required-group="has_warehouses">
                        @foreach(['yes' => 'Oui', 'no' => 'Non'] as $k => $l)<label><input type="radio" name="has_warehouses" value="{{ $k }}" @checked($v('has_warehouses') === $k)><span>{{ $l }}</span></label>@endforeach
                    </div>
                    @error('has_warehouses')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div data-pb-show-if="has_warehouses=yes">
                    <div data-pb-warehouses data-countries='@json($countryList)' data-types='@json($warehouseTypes)' data-units='@json($warehouseUnits)' data-default-country="{{ $profile->activity_country }}">
                        <textarea name="warehouses" hidden data-pb-warehouses-value>{{ $v('warehouses') }}</textarea>
                        <div data-pb-warehouses-list></div>
                    </div>
                    @error('warehouses')<p class="pb-error" style="margin-bottom: 14px">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('clock', 'oa-ico')</span><div><h2>Préparation des commandes</h2><p>Vos délais avant expédition.</p></div></header>
                <div class="pb-grid pb-grid--lead">
                    <div class="pb-field @error('preparation_lead_time') is-invalid @enderror">
                        <label for="f-preparation_lead_time">Délai moyen de préparation&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-combo"><input class="pb-input" id="f-preparation_lead_time" name="preparation_lead_time" inputmode="numeric" value="{{ $v('preparation_lead_time') }}" placeholder="Ex. 24" data-required="1" data-pb-integer>
                            <div class="pb-select"><select name="preparation_lead_unit" aria-label="Unité">@foreach(['heures' => 'Heures', 'jours' => 'Jours'] as $k => $l)<option value="{{ $k }}" @selected($v('preparation_lead_unit', 'heures') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                        @error('preparation_lead_time')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('preparation_workdays') is-invalid @enderror">
                        <label>Jours ouvrés&nbsp;<span class="oa-req">*</span></label>
                        <div class="fm-days" data-required-group="preparation_workdays">
                            @foreach($options('preparation_workdays') as $k => $l)<label><input type="checkbox" name="preparation_workdays[]" value="{{ $k }}" @checked(in_array($k, $workdays, true))><span>{{ $l }}</span></label>@endforeach
                        </div>
                        @error('preparation_workdays')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                @include('pro.auth.access.field', ['name' => 'preparation_conditions', 'label' => 'Conditions particulières', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex. palettisation, retrait sur rendez-vous, horaires limites de préparation…'])
            </div>
        </section>

        {{-- ===================== 4. Commerce international ===================== --}}
        <section class="fm-panel" id="panel-international" data-pb-panel="international" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('globe', 'oa-ico')</span><div><h2>Commerce international</h2><p>Vos opérations d’importation et d’exportation.</p></div></header>
                <div class="pb-field @error('trade_flows') is-invalid @enderror">
                    <label>Réalisez-vous des opérations internationales&nbsp;?&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices" data-required-group="trade_flows">
                        @foreach($options('trade_flows') as $k => $l)
                            <label class="fm-choice"><input type="radio" name="trade_flows" value="{{ $k }}" @checked($v('trade_flows') === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b><small>{{ $flowHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                    @error('trade_flows')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('supply_countries') is-invalid @enderror" data-pb-show-if="trade_flows=import|both">
                    <label>Pays d’approvisionnement&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'supply_countries', 'value' => $values['supply_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    @error('supply_countries')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('destination_countries') is-invalid @enderror" data-pb-show-if="trade_flows=export|both">
                    <label>Pays de destination&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'destination_countries', 'value' => $values['destination_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    @error('destination_countries')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div data-pb-show-if="trade_flows=import|export|both">
                    <div class="pb-field @error('transport_modes') is-invalid @enderror">
                        <label>Modes de transport&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-ship" data-required-group="transport_modes">
                            @foreach($transport as $k => $modeDef)
                                <label><input type="checkbox" name="transport_modes[]" value="{{ $k }}" @checked(in_array($k, $modes, true))><span class="pb-ship__box">@proicon('check', 'oa-ico')</span><span><b>{{ $modeDef[0] }}</b><small>{{ $modeDef[1] }}</small></span></label>
                            @endforeach
                        </div>
                        @error('transport_modes')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    @include('pro.auth.access.field', ['name' => 'international_notes', 'label' => 'Informations complémentaires', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex. ports d’entrée, fréquence d’importation, partenaires logistiques, formalités douanières…'])
                </div>
            </div>
        </section>

        {{-- ===================== 5. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            @foreach($docGroups as $groupKey => $groupDef)
                <div class="fm-card" data-pb-status="{{ $groupKey }}">
                    <header class="fm-sec"><span class="fm-sec__icon">@proicon($groupDef[1], 'oa-ico')</span><div><h2>{{ $groupDef[0] }}</h2>@if($loop->first)<p>Les intitulés peuvent varier selon votre pays d’enregistrement.</p>@endif</div></header>
                    <div class="pb-grid pb-grid--3">
                        @foreach($groupDef[2] as $docType => $docTitle)
                            @include('pro.auth.access.dropzone', ['type' => $docType, 'doc' => $documents[$docType], 'existing' => $docs($docType), 'title' => $docTitle, 'hint' => null])
                        @endforeach
                    </div>
                </div>
            @endforeach
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
                @foreach($docGroups as $groupKey => $groupDef)
                    <li><span class="fm-steps__row"><span class="fm-steps__num">@proicon('file', 'oa-ico')</span><b>{{ $groupDef[0] }}</b><em data-pb-doc-state="{{ $groupKey }}">À fournir</em></span></li>
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
