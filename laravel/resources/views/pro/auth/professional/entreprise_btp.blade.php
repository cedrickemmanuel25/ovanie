{{--
    Informations métier — Entreprise BTP (étape 3 sur 4).
    Six sections en onglets : Entreprise, Activité BTP, Achats, Organisation, Usages, Vérification.
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
        'company' => ['Entreprise', 'building', ['legal_name', 'trade_name', 'legal_form', 'founded_year', 'registration_number', 'tax_number', 'city', 'website', 'address', 'responsible_position', 'professional_email', 'professional_phone', 'professional_whatsapp']],
        'btp' => ['Activité BTP', 'hardhat', ['specialties', 'intervention_zones', 'work_types', 'project_categories']],
        'purchasing' => ['Achats', 'cart', ['purchase_categories', 'purchase_scope', 'purchase_volume']],
        'organisation' => ['Organisation', 'users', ['purchasing_manager', 'site_manager']],
        'functions' => ['Usages', 'grid', ['btp_functions']],
        'verification' => ['Vérification', 'shield', collect($documents)->keys()->map(fn ($k) => 'documents.' . $k)->all()],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Donnez de la visibilité à vos projets et trouvez des partenaires fiables.', 'Ces informations seront comparées à vos documents légaux à l’étape Vérification.'],
        'btp' => ['Trouvez des fournisseurs et des sous-traitants adaptés à vos chantiers.', 'Plus vos spécialités et zones sont précises, plus les propositions reçues seront pertinentes.'],
        'purchasing' => ['Accédez à des fournisseurs fiables pour vos projets de construction.', 'Ces informations servent à adapter vos demandes de devis et les fournisseurs recommandés.'],
        'organisation' => ['Construisez plus loin avec les bons partenaires.', 'Cette section est facultative. Aucun compte collaborateur n’est créé : vous pourrez inviter ces personnes depuis votre espace.'],
        'functions' => ['Des solutions B2B pour réaliser et piloter vos projets.', 'Les fonctionnalités réellement accessibles dépendront des rôles activés à l’étape suivante.'],
        'verification' => ['Des projets plus ambitieux grâce à un réseau de fournisseurs fiables.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $positions = ['Gérant / Fondateur', 'Direction générale', 'Direction technique', 'Responsable achats', 'Conducteur de travaux', 'Autre'];
    $specialties = ['Bâtiment résidentiel', 'Bâtiment commercial', 'Industriel', 'Routes', 'VRD (voirie et réseaux divers)', 'Génie civil', 'Électricité', 'Plomberie', 'Climatisation', 'Second œuvre', 'Rénovation'];
    $projectCategories = ['Logements individuels', 'Immeubles résidentiels', 'Bureaux', 'Centres commerciaux', 'Sites industriels', 'Infrastructures publiques', 'Écoles et hôpitaux', 'Routes et ponts'];
    $purchaseSuggestions = array_merge($opt['product_categories'], ['Sable', 'Gravier', 'Bois et dérivés', 'Engins de chantier', 'Matériel de levage', 'Équipements de sécurité (EPI)', 'Outillage', 'Matériel de coffrage', 'Groupes électrogènes']);
    $volumes = ['Moins de 10 millions FCFA par an', '10 à 50 millions FCFA par an', '50 à 200 millions FCFA par an', '200 millions à 1 milliard FCFA par an', 'Plus de 1 milliard FCFA par an'];
    $zoneSuggestions = array_merge($countries, (array) ($cities[$profile->activity_country] ?? []));
    $functionHelp = [
        'rfq' => 'Publier vos besoins et recevoir plusieurs devis de fournisseurs.',
        'tenders' => 'Lancer des appels d’offres et comparer les propositions.',
        'orders' => 'Acheter directement matériaux et équipements.',
        'services' => 'Accéder à des prestataires spécialisés (études, installation…).',
        'subcontracting' => 'Trouver des sous-traitants pour vos chantiers.',
    ];
    $workTypes = (array) $v('work_types', []);
    $scope = (array) $v('purchase_scope', []);
    $chosenFunctions = (array) $v('btp_functions', []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $docTitles = ['registry' => 'Registre de commerce (RCCM)', 'tax_certificate' => 'Attestation fiscale', 'representative_id' => 'Pièce d’identité du représentant', 'certifications' => 'Agréments et certifications'];
    $tabCount = count($tabs);
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('hardhat', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité d’{{ $profileMeta['label'] }}." data-verification="Ajoutez les documents nécessaires à la vérification de votre entreprise.">Décrivez votre activité d’{{ $profileMeta['label'] }}.</p>
            </div>
            <a class="fm-head__profile" href="{{ route('pro.register.qualification') }}">
                <img src="{{ asset('ovanie-pro/profiles/' . $profileKey . '-card.jpg') }}" alt="">
                <span><b>{{ $profileMeta['label'] }}</b><small>Changer de profil</small></span>
            </a>
        </header>

        <nav class="fm-tabs fm-tabs--6" role="tablist" aria-label="Sections du formulaire">
            @foreach($tabs as $tabKey => $tabDef)
                <button type="button" class="fm-tab" role="tab" data-pb-tab="{{ $tabKey }}" aria-controls="panel-{{ $tabKey }}">
                    @proicon($tabDef[1], 'oa-ico')<span>{{ $tabDef[0] }}</span><i class="pb-tab__ok" hidden>@proicon('check', 'oa-ico')</i>
                </button>
            @endforeach
            <div class="fm-tabs__meter" aria-hidden="true">
                <span><b data-pb-done>0</b> / {{ $tabCount }} sections</span>
                <div class="fm-segments" data-pb-segments>@for($i = 0; $i < $tabCount; $i++)<i></i>@endfor</div>
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
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. BTP Africa Construction SARL', 'class' => 'pb-span-2'])
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial', 'optional' => true, 'placeholder' => 'Ex. BTP Africa'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre de commerce (RCCM)', 'required' => true, 'placeholder' => 'Ex. CI-ABJ-2023-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro contribuable', 'optional' => true, 'placeholder' => 'Ex. 1234567 F'])
                    @include('pro.auth.access.field', ['name' => 'founded_year', 'label' => 'Année de création', 'type' => 'year', 'optional' => true, 'empty' => 'Sélectionnez'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Siège</h2><p>Adresse légale, en {{ $profile->registration_country ?: $profile->activity_country }}.</p></div></header>
                <input type="hidden" name="_registration_country" value="{{ $profile->registration_country ?: $profile->activity_country }}">
                <div class="pb-grid pb-grid--side">
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => '_registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse légale', 'required' => true, 'icon' => 'map-pin', 'placeholder' => 'Ex. Plateau, avenue Jean-Paul II, immeuble XYZ'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('user', 'oa-ico')</span><div><h2>Représentant et coordonnées</h2><p>{{ trim($user->first_name . ' ' . $user->last_name) }}, titulaire du compte, représente l’entreprise.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction dans l’entreprise', 'type' => 'select', 'options' => $positions, 'required' => true, 'icon' => 'user', 'empty' => 'Sélectionnez'])
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
                    @include('pro.auth.access.field', ['name' => 'website', 'label' => 'Site web', 'type' => 'url', 'optional' => true, 'icon' => 'globe', 'placeholder' => 'https://www.monentreprise.ci'])
                </div>
            </div>
        </section>

        {{-- ===================== 2. Activité BTP ===================== --}}
        <section class="fm-panel" id="panel-btp" data-pb-panel="btp" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('hardhat', 'oa-ico')</span><div><h2>Spécialités et travaux</h2><p>Vos domaines d’expertise et les travaux que vous réalisez.</p></div></header>
                <div class="pb-field @error('work_types') is-invalid @enderror">
                    <label>Types de travaux&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--3" data-required-group="work_types">
                        @foreach($options('work_types') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="work_types[]" value="{{ $k }}" @checked(in_array($k, $workTypes, true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                    @error('work_types')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('specialties') is-invalid @enderror">
                    <label>Spécialités de l’entreprise&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'specialties', 'value' => $values['specialties'] ?? '', 'placeholder' => 'Rechercher ou saisir une spécialité…', 'suggestions' => $specialties, 'required' => true])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Zones et projets</h2><p>Où vous intervenez et sur quels types de chantiers.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('intervention_zones') is-invalid @enderror">
                        <label>Zones d’intervention&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'intervention_zones', 'value' => $values['intervention_zones'] ?? '', 'placeholder' => 'Rechercher un pays ou une ville…', 'suggestions' => $zoneSuggestions, 'required' => true])
                    </div>
                    <div class="pb-field">
                        <label>Catégories de projets <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'project_categories', 'value' => $values['project_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $projectCategories])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 3. Achats ===================== --}}
        <section class="fm-panel" id="panel-purchasing" data-pb-panel="purchasing" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('cart', 'oa-ico')</span><div><h2>Achats professionnels</h2><p>Les matériaux et équipements que vous achetez habituellement.</p></div></header>
                <div class="pb-field @error('purchase_categories') is-invalid @enderror">
                    <label>Matériaux et équipements achetés&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'purchase_categories', 'value' => $values['purchase_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $purchaseSuggestions, 'required' => true])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field">
                        <label>Origine de vos achats <small>(facultatif)</small></label>
                        <div class="fm-choices fm-choices--2">
                            @foreach(['local' => ['Achats locaux', 'Dans votre pays ou la sous-région.'], 'international' => ['Achats internationaux', 'Importations hors de la sous-région.']] as $k => $scopeDef)
                                <label class="fm-choice fm-choice--check"><input type="checkbox" name="purchase_scope[]" value="{{ $k }}" @checked(in_array($k, $scope, true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $scopeDef[0] }}</b><small>{{ $scopeDef[1] }}</small></span></label>
                            @endforeach
                        </div>
                    </div>
                    @include('pro.auth.access.field', ['name' => 'purchase_volume', 'label' => 'Volume d’achat annuel', 'type' => 'select', 'options' => $volumes, 'optional' => true, 'empty' => 'Sélectionnez'])
                </div>
            </div>
        </section>

        {{-- ===================== 4. Organisation ===================== --}}
        <section class="fm-panel" id="panel-organisation" data-pb-panel="organisation" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('users', 'oa-ico')</span><div><h2>Contacts clés <small>(facultatif)</small></h2><p>Les personnes impliquées dans vos achats et vos chantiers.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'purchasing_manager', 'label' => 'Responsable achats', 'icon' => 'cart', 'placeholder' => 'Ex. Fatoumata Diarra'])
                    @include('pro.auth.access.field', ['name' => 'site_manager', 'label' => 'Responsable chantier', 'icon' => 'hardhat', 'placeholder' => 'Ex. Jean Marc Kouassi'])
                </div>
            </div>
        </section>

        {{-- ===================== 5. Usages ===================== --}}
        <section class="fm-panel" id="panel-functions" data-pb-panel="functions" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('grid', 'oa-ico')</span><div><h2>Usages souhaités <small>(facultatif)</small></h2><p>Ce que vous comptez faire sur OVANIE PRO. Plusieurs choix possibles.</p></div></header>
                <div class="pb-field">
                    <div class="fm-choices fm-choices--2">
                        @foreach($options('btp_functions') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="btp_functions[]" value="{{ $k }}" @checked(in_array($k, $chosenFunctions, true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b><small>{{ $functionHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 6. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="documents">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('shield', 'oa-ico')</span><div><h2>Documents de l’entreprise</h2><p>Les pièces demandées peuvent varier selon votre pays.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach($documents as $docType => $docDef)
                        @include('pro.auth.access.dropzone', ['type' => $docType, 'doc' => $docDef, 'existing' => $docs($docType), 'title' => $docTitles[$docType] ?? $docDef['label'], 'hint' => null])
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
            <div class="fm-box__head"><h2>Votre progression</h2><span><b data-pb-done>0</b> sur {{ $tabCount }}</span></div>
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
