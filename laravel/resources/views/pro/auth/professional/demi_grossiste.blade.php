{{--
    Informations métier — Demi-grossiste (étape 3 sur 4).
    Cinq sections en onglets : Entreprise, Activité, Zones, Conditions commerciales, Vérification.
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
        'company' => ['Entreprise', 'building', ['legal_name', 'legal_form', 'registration_country', 'registration_number', 'tax_number', 'city', 'address', 'representative_name', 'responsible_position', 'professional_phone', 'professional_whatsapp', 'professional_email', 'website']],
        'activity' => ['Activité', 'boxes', ['categories', 'brands_origins', 'origin_country', 'sale_units', 'minimum_quantity', 'minimum_quantity_unit', 'stock_capacity', 'stock_unit', 'supply_frequency', 'quantity_tiers']],
        'zones' => ['Zones', 'map-pin', ['dispatch_country', 'dispatch_city', 'dispatch_address', 'dispatch_position', 'cities_served', 'regions_served', 'countries_served']],
        'conditions' => ['Conditions', 'handshake', ['availability', 'preparation_time', 'preparation_unit', 'restock_frequency', 'volume_negotiation', 'commercial_comment']],
        'verification' => ['Vérification', 'shield', collect($documents)->keys()->map(fn ($k) => 'documents.' . $k)->all()],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Des quantités adaptées aux besoins des professionnels du BTP.', 'Saisissez les informations telles qu’elles figurent sur vos documents officiels : elles pourront être vérifiées.'],
        'activity' => ['Des produits en semi-gros, avec plusieurs paliers de quantité.', 'Des paliers clairs aident les acheteurs à savoir à partir de quelle quantité vous contacter.'],
        'zones' => ['Une distribution en semi-gros au plus près des chantiers.', 'Des zones précises permettent aux acheteurs proches de vous trouver plus facilement.'],
        'conditions' => ['Des conditions commerciales claires pour une collaboration efficace.', 'Ces conditions sont indicatives : elles peuvent varier selon les produits et les quantités.'],
        'verification' => ['Complétez votre dossier pour renforcer votre crédibilité.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $units = ['Unités', 'Sacs', 'Pièces', 'Cartons', 'Packs', 'Palettes', 'Tonnes', 'Kg', 'm²', 'm³', 'Litres', 'Lots'];
    $positions = ['Gérant / Fondateur', 'Direction générale', 'Direction commerciale', 'Responsable achats', 'Responsable logistique', 'Autre'];
    $brandSuggestions = ['Holcim', 'CIMAF', 'Dangote', 'Sika', 'Legrand', 'Schneider Electric', 'Bosch', 'Makita'];
    $regionSuggestions = ['District d’Abidjan', 'District de Yamoussoukro', 'Vallée du Bandama', 'Bas-Sassandra', 'Sassandra-Marahoué', 'Savanes', 'Montagnes', 'Comoé', 'Lagunes', 'Lacs', 'Zanzan', 'Denguélé', 'Gôh-Djiboua', 'Woroba'];
    $citySuggestions = (array) ($cities[$v('dispatch_country', $profile->activity_country)] ?? []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $country = $profile->registration_country ?: $profile->activity_country;
    $docTitles = ['registry' => 'Registre de commerce ou équivalent', 'tax_certificate' => 'Identification fiscale', 'professional_proof' => 'Justificatif professionnel', 'other_authorizations' => 'Autres autorisations'];
    $docHints = ['registry' => 'RCCM ou extrait de registre.', 'tax_certificate' => 'Selon votre pays.', 'professional_proof' => 'Preuve d’adresse commerciale, bail ou attestation de siège.', 'other_authorizations' => 'Licence ou autorisation spécifique.'];
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
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('building', 'oa-ico')</span><div><h2>Identité professionnelle</h2><p>Telle qu’elle figure sur vos documents officiels.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale ou nom commercial', 'required' => true, 'placeholder' => 'Ex. Distributions Express', 'class' => 'pb-span-2'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre de commerce (RCCM)', 'optional' => true, 'placeholder' => 'Ex. CI-ABJ-2023-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro fiscal', 'optional' => true, 'placeholder' => 'Ex. 1234567 F'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Siège</h2><p>Adresse de votre entreprise.</p></div></header>
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
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction dans l’entreprise', 'type' => 'select', 'options' => $positions, 'required' => true, 'empty' => 'Sélectionnez'])
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
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('boxes', 'oa-ico')</span><div><h2>Activité en semi-gros</h2><p>Les produits que vous vendez et vos volumes habituels.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('categories') is-invalid @enderror">
                        <label>Catégories de produits&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'categories', 'value' => $values['categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $opt['product_categories'], 'required' => true])
                    </div>
                    <div class="pb-field">
                        <label>Principales marques <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'brands_origins', 'value' => $values['brands_origins'] ?? '', 'placeholder' => 'Rechercher ou saisir une marque…', 'suggestions' => $brandSuggestions])
                    </div>
                    @include('pro.auth.access.field', ['name' => 'origin_country', 'label' => 'Origine des produits', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->activity_country])
                    <div class="pb-field @error('sale_units') is-invalid @enderror">
                        <label>Unités de vente&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'sale_units', 'value' => $values['sale_units'] ?? '', 'placeholder' => 'Sélectionner une unité…', 'suggestions' => $units, 'required' => true])
                    </div>
                    <div class="pb-field @error('minimum_quantity') is-invalid @enderror">
                        <label for="f-minimum_quantity">Quantité minimale habituelle&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-combo"><input class="pb-input" id="f-minimum_quantity" name="minimum_quantity" inputmode="decimal" value="{{ $v('minimum_quantity') }}" placeholder="Ex. 10" data-required="1">
                            <div class="pb-select"><select name="minimum_quantity_unit" aria-label="Unité">@foreach($units as $u)<option @selected($v('minimum_quantity_unit', 'Unités') === $u)>{{ $u }}</option>@endforeach</select></div></div>
                        @error('minimum_quantity')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field">
                        <label for="f-stock_capacity">Capacité de stock <small>(facultatif)</small></label>
                        <div class="pb-combo"><input class="pb-input" id="f-stock_capacity" name="stock_capacity" inputmode="decimal" value="{{ $v('stock_capacity') }}" placeholder="Ex. 5000">
                            <div class="pb-select"><select name="stock_unit" aria-label="Unité">@foreach($units as $u)<option @selected($v('stock_unit', 'Unités') === $u)>{{ $u }}</option>@endforeach</select></div></div>
                    </div>
                    @include('pro.auth.access.field', ['name' => 'supply_frequency', 'label' => 'Fréquence d’approvisionnement', 'type' => 'select', 'options' => $options('supply_frequency'), 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('chart', 'oa-ico')</span><div><h2>Paliers de quantité</h2><p>Vos tranches de quantité habituelles. Laissez « jusqu’à » vide pour le dernier palier.</p></div></header>
                <div data-pb-tiers data-units='@json($units)'>
                    <textarea name="quantity_tiers" hidden data-pb-tiers-value>{{ $v('quantity_tiers') }}</textarea>
                    <div data-pb-tiers-list></div>
                </div>
                <button type="button" class="fm-add-row" data-pb-tier-add>@proicon('plus', 'oa-ico') Ajouter un palier</button>
                @error('quantity_tiers')<p class="pb-error" style="margin: -8px 0 14px">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- ===================== 3. Zones ===================== --}}
        <section class="fm-panel" id="panel-zones" data-pb-panel="zones" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec">
                    <span class="fm-sec__icon fm-sec__icon--orange">@proicon('map-pin', 'oa-ico')</span>
                    <div><h2>Origine d’expédition</h2><p>Votre principal point de départ des marchandises.</p></div>
                    <button type="button" class="oa-btn oa-btn--outline fm-btn-ghost" data-pb-copy-company data-prefix="dispatch">@proicon('building', 'oa-ico') Même adresse que le siège</button>
                </header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'dispatch_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'dispatch_city', 'label' => 'Ville', 'countryField' => 'dispatch_country'])
                    @include('pro.auth.access.field', ['name' => 'dispatch_address', 'label' => 'Adresse', 'optional' => true, 'placeholder' => 'Ex. Zone industrielle, rue des Entrepôts'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('globe', 'oa-ico')</span><div><h2>Zones desservies</h2><p>Les villes, régions et pays où vous livrez.</p></div></header>
                <div class="pb-field @error('cities_served') is-invalid @enderror">
                    <label>Villes desservies&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'cities_served', 'value' => $values['cities_served'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville…', 'suggestions' => $citySuggestions, 'required' => true])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('regions_served') is-invalid @enderror">
                        <label>Régions desservies&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'regions_served', 'value' => $values['regions_served'] ?? '', 'placeholder' => 'Rechercher ou saisir une région…', 'suggestions' => $regionSuggestions, 'required' => true])
                    </div>
                    <div class="pb-field @error('countries_served') is-invalid @enderror">
                        <label>Pays desservis&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'countries_served', 'value' => $values['countries_served'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 4. Conditions commerciales ===================== --}}
        <section class="fm-panel" id="panel-conditions" data-pb-panel="conditions" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('handshake', 'oa-ico')</span><div><h2>Conditions commerciales</h2><p>Disponibilité, délais et négociation.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'availability', 'label' => 'Disponibilité habituelle des produits', 'type' => 'select', 'options' => $options('availability'), 'required' => true, 'empty' => 'Sélectionnez'])
                    <div class="pb-field @error('preparation_time') is-invalid @enderror">
                        <label for="f-preparation_time">Délai moyen de préparation&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-combo"><input class="pb-input" id="f-preparation_time" name="preparation_time" inputmode="numeric" value="{{ $v('preparation_time') }}" placeholder="Ex. 24" data-required="1" data-pb-integer>
                            <div class="pb-select"><select name="preparation_unit" aria-label="Unité">@foreach(['heures' => 'Heures', 'jours' => 'Jours'] as $k => $l)<option value="{{ $k }}" @selected($v('preparation_unit', 'heures') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                        @error('preparation_time')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    @include('pro.auth.access.field', ['name' => 'restock_frequency', 'label' => 'Fréquence de réapprovisionnement', 'type' => 'select', 'options' => $options('restock_frequency'), 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-field @error('volume_negotiation') is-invalid @enderror">
                    <label>Négociation possible selon le volume&nbsp;?&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices" data-required-group="volume_negotiation" style="max-width: 560px">
                        <label class="fm-choice"><input type="radio" name="volume_negotiation" value="yes" @checked($v('volume_negotiation') === 'yes')><span class="fm-choice__dot"></span><span><b>Oui</b><small>Des remises peuvent être accordées selon les quantités.</small></span></label>
                        <label class="fm-choice"><input type="radio" name="volume_negotiation" value="no" @checked($v('volume_negotiation') === 'no')><span class="fm-choice__dot"></span><span><b>Non</b><small>Nos prix sont fixes.</small></span></label>
                    </div>
                    @error('volume_negotiation')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                @include('pro.auth.access.field', ['name' => 'commercial_comment', 'label' => 'Commentaire', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex. remises sur certaines gammes, conditions de paiement, conditions pour les clients réguliers…'])
            </div>
        </section>

        {{-- ===================== 5. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="documents">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('file', 'oa-ico')</span><div><h2>Justificatifs</h2><p>Les pièces demandées peuvent varier selon votre pays. Aucune preuve industrielle n’est requise.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach($documents as $docType => $docDef)
                        @include('pro.auth.access.dropzone', ['type' => $docType, 'doc' => $docDef, 'existing' => $docs($docType), 'title' => $docTitles[$docType] ?? $docDef['label'], 'hint' => $docHints[$docType] ?? null])
                    @endforeach
                </div>
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

        <div class="fm-box">
            <div class="fm-box__head"><h2>Votre progression</h2><span><b data-pb-done>0</b> sur 5</span></div>
            <div class="fm-bar"><i data-pb-bar></i></div>
            <ul class="fm-steps">
                @foreach($tabs as $tabKey => $tabDef)
                    <li><button type="button" data-pb-goto="{{ $tabKey }}"><span class="fm-steps__num">{{ $loop->iteration }}</span><b>{{ $tabDef[0] }}</b><em data-pb-state="{{ $tabKey }}">À compléter</em></button></li>
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
