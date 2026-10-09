{{--
    Informations métier — Promoteur / Immobilier (étape 3 sur 4).
    Cinq sections en onglets : Entreprise, Activité, Projets, Achats, Vérification.
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
    $picked = fn (string $field) => (array) $v($field, []);

    // Onglet => [libellé, pictogramme, champs concernés (pour rouvrir le bon onglet en cas d'erreur)].
    $tabs = [
        'company' => ['Entreprise', 'building', ['legal_name', 'trade_name', 'legal_form', 'registration_country', 'city', 'address', 'registration_number', 'tax_number', 'representative_name', 'responsible_position', 'professional_phone', 'professional_email', 'website']],
        'activity' => ['Activité', 'file', ['real_estate_activities', 'activity_zones', 'real_estate_segments']],
        'projects' => ['Projets', 'home', ['project_zones', 'program_types', 'current_projects']],
        'purchasing' => ['Achats', 'cart', ['purchase_categories', 'purchase_volume_band', 'purchase_local_countries', 'purchase_international_countries', 'rfq_usage', 'tender_usage', 'supplier_types']],
        'verification' => ['Vérification', 'shield', collect($documents)->keys()->map(fn ($k) => 'documents.' . $k)->all()],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Structurez vos projets et gagnez en crédibilité.', 'Ces informations serviront à vérifier votre structure.'],
        'activity' => ['Développez vos programmes avec les bons partenaires.', 'Une description claire de vos activités aide les fournisseurs à comprendre votre positionnement.'],
        'projects' => ['Présentez vos projets immobiliers.', 'Vos projets restent privés par défaut et ne sont publiés qu’avec votre accord.'],
        'purchasing' => ['Gérez vos achats et vos consultations.', 'Des informations précises sur vos achats permettent de vous recommander des fournisseurs pertinents.'],
        'verification' => ['Donnez vie à vos projets immobiliers en Afrique de l’Ouest.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $positions = ['Gérant / Fondateur', 'Direction générale', 'Direction des programmes', 'Direction technique', 'Responsable achats', 'Autre'];
    $activityHelp = ['promoteur' => 'Développement et commercialisation de projets.', 'amenageur' => 'Terrains, lotissements, zones d’activités.', 'constructeur' => 'Réalisation de bâtiments.', 'gestionnaire' => 'Gestion et exploitation de biens.', 'autre' => 'Autre activité liée à l’immobilier.'];
    $volumeHelp = ['low' => 'Moins de 50 millions FCFA par an', 'regular' => '50 à 500 millions FCFA par an', 'high' => 'Plus de 500 millions FCFA par an', 'project' => 'Variables selon les projets'];
    $zoneSuggestions = array_merge((array) ($cities[$profile->activity_country] ?? []), $countries);
    $purchaseSuggestions = array_merge($opt['product_categories'], ['Béton prêt à l’emploi', 'Carrelage', 'Menuiserie aluminium', 'Matériel électrique', 'Plomberie sanitaire', 'Climatisation', 'Ascenseurs', 'Sécurité incendie', 'Équipements de chantier']);
    $projectFields = [
        ['key' => 'name', 'label' => 'Nom du projet', 'placeholder' => 'Ex. Résidence Horizon Riviera', 'required' => true],
        ['key' => 'type', 'label' => 'Type de programme', 'type' => 'select', 'options' => array_values($options('program_types')), 'required' => true],
        ['key' => 'location', 'label' => 'Localisation', 'placeholder' => 'Ex. Cocody, Abidjan', 'required' => true],
        ['key' => 'status', 'label' => 'Avancement', 'type' => 'select', 'options' => ['Planification', 'Étude', 'En cours', 'Livré']],
        ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Ex. programme résidentiel moyen standing de plusieurs immeubles.', 'wide' => true],
    ];
    $docTitles = ['company_documents' => 'Documents de l’entreprise', 'registry' => 'Registre / immatriculation', 'tax_certificate' => 'Documents fiscaux', 'professional_proofs' => 'Justificatifs professionnels', 'approvals_certifications' => 'Agréments et certifications'];
    $docHints = ['company_documents' => 'Statuts, attestation d’existence ou équivalent.', 'registry' => 'Extrait du RCCM ou équivalent.', 'tax_certificate' => 'Attestation fiscale ou quitus.', 'professional_proofs' => 'Références de projets, contrats, attestations.', 'approvals_certifications' => 'Agrément promoteur, ISO…'];
    $radioGroups = ['rfq_usage' => 'Utilisation des demandes de devis (RFQ)', 'tender_usage' => 'Utilisation des appels d’offres'];
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('home', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité de {{ $profileMeta['label'] }}." data-verification="Ajoutez les documents nécessaires à la vérification de votre profil.">Décrivez votre activité de {{ $profileMeta['label'] }}.</p>
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
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. Société Ivoirienne de Promotion Immobilière', 'class' => 'pb-span-2'])
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial', 'optional' => true, 'placeholder' => 'Ex. SIPIM'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre (RCCM)', 'required' => true, 'placeholder' => 'Ex. CI-ABJ-2024-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro fiscal', 'required' => true, 'placeholder' => 'Ex. 1234567 F'])
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->registration_country ?: $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Cocody, rue des Jardins, immeuble Horizon'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('user', 'oa-ico')</span><div><h2>Représentant et coordonnées</h2><p>La personne qui représente la structure.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'representative_name', 'label' => 'Nom et prénoms', 'required' => true, 'icon' => 'user', 'placeholder' => 'Ex. Kouamé Jean Marc', 'default' => trim($user->first_name . ' ' . $user->last_name)])
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction', 'type' => 'select', 'options' => $positions, 'required' => true, 'empty' => 'Sélectionnez'])
                    <div class="pb-field @error('professional_phone') is-invalid @enderror">
                        <label for="professional_phone">Téléphone professionnel&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_phone', 'value' => $values['professional_phone'] ?? '', 'id' => 'professional_phone', 'required' => true])
                        @error('professional_phone')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    @include('pro.auth.access.field', ['name' => 'professional_email', 'label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'placeholder' => 'contact@monentreprise.ci'])
                    @include('pro.auth.access.field', ['name' => 'website', 'label' => 'Site web', 'type' => 'url', 'optional' => true, 'icon' => 'globe', 'placeholder' => 'https://www.monentreprise.ci'])
                </div>
            </div>
        </section>

        {{-- ===================== 2. Activité ===================== --}}
        <section class="fm-panel" id="panel-activity" data-pb-panel="activity" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('file', 'oa-ico')</span><div><h2>Activité</h2><p>Le positionnement et les domaines d’intervention de votre organisation.</p></div></header>
                <div class="pb-field @error('real_estate_activities') is-invalid @enderror">
                    <label>Activités de votre organisation&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--3" data-required-group="real_estate_activities">
                        @foreach($options('real_estate_activities') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="real_estate_activities[]" value="{{ $k }}" @checked(in_array($k, $picked('real_estate_activities'), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b><small>{{ $activityHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                    @error('real_estate_activities')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('activity_zones') is-invalid @enderror">
                    <label>Zones principales d’activité&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'activity_zones', 'value' => $values['activity_zones'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville, une région…', 'suggestions' => $zoneSuggestions, 'required' => true])
                </div>
                <div class="pb-field @error('real_estate_segments') is-invalid @enderror">
                    <label>Segments immobiliers&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--3" data-required-group="real_estate_segments">
                        @foreach($options('real_estate_segments') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="real_estate_segments[]" value="{{ $k }}" @checked(in_array($k, $picked('real_estate_segments'), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                    @error('real_estate_segments')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- ===================== 3. Projets ===================== --}}
        <section class="fm-panel" id="panel-projects" data-pb-panel="projects" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('home', 'oa-ico')</span><div><h2>Projets</h2><p>Où vous développez vos projets et quels programmes vous réalisez.</p></div></header>
                <div class="pb-field @error('project_zones') is-invalid @enderror">
                    <label>Zones géographiques des projets&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'project_zones', 'value' => $values['project_zones'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville, une région…', 'suggestions' => $zoneSuggestions, 'required' => true])
                </div>
                <div class="pb-field @error('program_types') is-invalid @enderror">
                    <label>Types de programmes&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--4" data-required-group="program_types">
                        @foreach($options('program_types') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="program_types[]" value="{{ $k }}" @checked(in_array($k, $picked('program_types'), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                    @error('program_types')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('lock', 'oa-ico')</span><div><h2>Projets en cours <small>(facultatif)</small></h2><p>Ces données sont privées et ne sont pas publiées automatiquement.</p></div></header>
                <div data-pb-repeater data-noun="Projet" data-fields='@json($projectFields)'>
                    <textarea name="current_projects" hidden data-pb-repeater-value>{{ $v('current_projects', '[]') }}</textarea>
                    <div data-pb-repeater-list></div>
                    <button type="button" class="fm-add-row" data-pb-repeater-add>@proicon('plus', 'oa-ico') Ajouter un projet</button>
                </div>
            </div>
        </section>

        {{-- ===================== 4. Achats ===================== --}}
        <section class="fm-panel" id="panel-purchasing" data-pb-panel="purchasing" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('cart', 'oa-ico')</span><div><h2>Achats</h2><p>Ce que vous achetez régulièrement et où.</p></div></header>
                <div class="pb-field @error('purchase_categories') is-invalid @enderror">
                    <label>Catégories régulièrement achetées&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'purchase_categories', 'value' => $values['purchase_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $purchaseSuggestions, 'required' => true])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('purchase_local_countries') is-invalid @enderror">
                        <label>Achats locaux&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'purchase_local_countries', 'value' => $values['purchase_local_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                    <div class="pb-field">
                        <label>Achats internationaux <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'purchase_international_countries', 'value' => $values['purchase_international_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries])
                    </div>
                </div>
                <div class="pb-field">
                    <label>Volume d’achat annuel <small>(facultatif)</small></label>
                    <div class="fm-choices fm-choices--4">
                        @foreach($options('purchase_volume_band') as $k => $l)
                            <label class="fm-choice"><input type="radio" name="purchase_volume_band" value="{{ $k }}" @checked($v('purchase_volume_band') === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b><small>{{ $volumeHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('file-search', 'oa-ico')</span><div><h2>Consultation des fournisseurs</h2><p>Votre usage des demandes de devis et des appels d’offres.</p></div></header>
                @foreach($radioGroups as $groupName => $groupLabel)
                    <div class="pb-field @error($groupName) is-invalid @enderror">
                        <label>{{ $groupLabel }}&nbsp;<span class="oa-req">*</span></label>
                        <div class="fm-choices fm-choices--4" data-required-group="{{ $groupName }}">
                            @foreach($options($groupName) as $k => $l)
                                <label class="fm-choice"><input type="radio" name="{{ $groupName }}" value="{{ $k }}" @checked($v($groupName) === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b></span></label>
                            @endforeach
                        </div>
                        @error($groupName)<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="pb-field">
                    <label>Types de fournisseurs recherchés <small>(facultatif)</small></label>
                    <div class="fm-choices fm-choices--3">
                        @foreach($options('supplier_types') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="supplier_types[]" value="{{ $k }}" @checked(in_array($k, $picked('supplier_types'), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 5. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="documents">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('shield', 'oa-ico')</span><div><h2>Vérification du profil</h2><p>Vos documents sont confidentiels ; vos projets ne sont pas rendus publics sans autorisation.</p></div></header>
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

        <a class="fm-help" href="{{ route('contact.index') }}" target="_blank" rel="noopener">@proicon('headset', 'oa-ico')<span><b>Besoin d’aide ?</b><small>Contactez notre équipe</small></span>@proicon('chevron-right', 'oa-ico')</a>
    </aside>
</form>
@endsection
