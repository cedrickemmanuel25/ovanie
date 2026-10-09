{{--
    Informations métier — Prestataire BTP (étape 3 sur 4).
    Huit sections en onglets : Structure, Services, Intervention, Moyens, Références, Qualifications, Tarifs, Vérification.
    Le statut (entreprise ou indépendant) se choisit ici : il adapte les champs et les documents demandés.
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
    $cities = (array) config('ovanie_pro_cities', []);
    $schema = app(\App\Support\ProForms\ProFormSchema::class);
    $v = fn (string $field, $fallback = '') => old($field, $values[$field] ?? $fallback);
    $docs = fn (string $docType) => $providedDocuments->get($docType, collect());
    $entity = old('profile_entity_type', $entityType ?: 'organization');
    // Les listes de choix viennent du schéma, quel que soit le statut affiché.
    $allFields = $schema->fields($profileKey, 'organization') + $schema->fields($profileKey, 'independent');
    $options = fn (string $field) => (array) ($allFields[$field]['options'] ?? []);

    // Onglet => [libellé, pictogramme, champs concernés (pour rouvrir le bon onglet en cas d'erreur)].
    $tabs = [
        'company' => ['Structure', 'building', ['profile_entity_type', 'legal_name', 'trade_name', 'independent_status', 'registration_country', 'legal_form', 'registration_number', 'tax_number', 'city', 'address', 'website', 'professional_email', 'professional_phone', 'professional_whatsapp']],
        'services' => ['Services', 'wrench', ['service_category', 'service_subcategory', 'service_title', 'services_description', 'skills', 'specialties', 'installation_types', 'intervention_levels', 'standards']],
        'intervention' => ['Intervention', 'map-pin', ['intervention_zones', 'availability', 'team_capacity', 'team_size', 'indicative_lead_time', 'client_types']],
        'means' => ['Moyens', 'truck', ['means_types', 'equipment_json']],
        'references' => ['Références', 'file', ['references_json', 'references_status', 'documents.work_media']],
        'qualifications' => ['Qualifications', 'award', ['qualifications_json', 'qualifications_status', 'documents.qualification_proofs']],
        'pricing' => ['Tarifs', 'tag', ['pricing_mode', 'indicative_rate', 'pricing_currency', 'pricing_unit', 'pricing_modes', 'pricing_notes']],
        'verification' => ['Vérification', 'shield', ['documents.professional_documents', 'documents.legal_documents', 'documents.insurance_documents', 'documents.accreditation_documents', 'documents.certification_documents', 'documents.authorization_documents']],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Valorisez votre expertise dans le secteur de la construction.', 'Les informations et documents demandés s’adaptent à votre statut.'],
        'services' => ['Mettez en avant vos services et votre savoir-faire.', 'Plus votre offre est claire et détaillée, plus vous serez contacté pour des projets pertinents.'],
        'intervention' => ['Mettez en avant votre couverture terrain et votre réactivité.', 'Des zones et une disponibilité claires augmentent vos chances d’être contacté.'],
        'means' => ['Des moyens adaptés pour des projets réussis.', 'Les moyens déclarés renforcent la confiance des clients et facilitent votre sélection.'],
        'references' => ['Mettez en avant vos réalisations.', 'Facultatif. Le nom d’un client n’est affiché publiquement que si vous l’autorisez.'],
        'qualifications' => ['Mettez en avant vos qualifications et certifications.', 'Facultatif. Des qualifications à jour augmentent votre visibilité.'],
        'pricing' => ['Clarifiez votre tarification pour rassurer vos clients.', 'Le tarif est indicatif : le montant final est précisé lors de la demande de devis.'],
        'verification' => ['Finalisez la vérification de votre profil.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $subcategories = ['Électricité', 'Plomberie', 'Climatisation', 'Gros œuvre', 'Second œuvre', 'Génie civil', 'Topographie', 'Études techniques', 'Engins de chantier', 'Échafaudages', 'Sécurité incendie', 'Contrôle technique'];
    $skillSuggestions = ['Installation', 'Câblage', 'Mise en service', 'Lecture de plans', 'Sécurité électrique', 'Maintenance préventive', 'Dépannage', 'Conduite d’engins', 'Soudure', 'Métré'];
    $specialtySuggestions = ['Bâtiments tertiaires', 'Sites industriels', 'Groupes électrogènes', 'Énergies renouvelables', 'Logements', 'Infrastructures routières'];
    $zoneSuggestions = array_merge((array) ($cities[$profile->activity_country] ?? []), $countries);
    $radioGroups = [
        'availability' => ['Disponibilité', ['Disponible immédiatement', 'Sous 24 à 48 h', 'Sous quelques jours', 'Sur planification'], 4],
        'team_capacity' => ['Capacité de l’équipe', ['Intervention légère', 'Équipe standard', 'Déploiement multi-sites', 'Forte capacité chantier'], 4],
        'team_size' => ['Nombre de personnes', ['1 personne', '2 à 5', '6 à 10', '11 à 20', 'Plus de 20'], 5],
        'indicative_lead_time' => ['Délai moyen d’intervention', ['Moins de 24 h', '24 à 48 h', '3 à 7 jours', 'Plus de 7 jours', 'Selon planning'], 5],
    ];
    $equipmentFields = [
        ['key' => 'name', 'label' => 'Nom de l’équipement', 'placeholder' => 'Ex. Groupe électrogène 50 kVA', 'required' => true],
        ['key' => 'type', 'label' => 'Type', 'placeholder' => 'Ex. Outillage électroportatif', 'required' => true],
        ['key' => 'ownership', 'label' => 'Propriété / location', 'type' => 'select', 'options' => ['Propriété', 'Location'], 'required' => true],
        ['key' => 'quantity', 'label' => 'Quantité', 'placeholder' => 'Ex. 2'],
        ['key' => 'description', 'label' => 'Description', 'placeholder' => 'Ex. utilisé pour l’alimentation électrique sur chantier.', 'wide' => true],
    ];
    $referenceFields = [
        ['key' => 'title', 'label' => 'Titre de la réalisation', 'placeholder' => 'Ex. Construction d’un immeuble R+4', 'required' => true],
        ['key' => 'type', 'label' => 'Type de prestation', 'type' => 'select', 'options' => array_values($options('service_category')), 'required' => true],
        ['key' => 'location', 'label' => 'Ville / pays', 'placeholder' => 'Ex. Abidjan, Côte d’Ivoire', 'required' => true],
        ['key' => 'year', 'label' => 'Année', 'type' => 'year', 'required' => true],
        ['key' => 'client', 'label' => 'Client de référence (si publiable)', 'placeholder' => 'Ex. SCI Les Jardins'],
        ['key' => 'client_public', 'label' => 'Afficher le nom du client sur mon profil', 'type' => 'select', 'options' => ['Non', 'Oui']],
        ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Travaux réalisés, surface, particularités…', 'wide' => true],
    ];
    $qualificationFields = [
        ['key' => 'category', 'label' => 'Catégorie', 'type' => 'select', 'options' => ['Certification', 'Assurance', 'Agrément', 'Autorisation', 'Qualification métier'], 'required' => true],
        ['key' => 'name', 'label' => 'Intitulé', 'placeholder' => 'Ex. ISO 9001, responsabilité civile…', 'required' => true],
        ['key' => 'body', 'label' => 'Organisme', 'placeholder' => 'Ex. AFNOR', 'required' => true],
        ['key' => 'number', 'label' => 'Numéro', 'placeholder' => 'Ex. FR-2024-00123'],
        ['key' => 'issued_on', 'label' => 'Émise le', 'type' => 'date'],
        ['key' => 'expires_on', 'label' => 'Expire le', 'type' => 'date'],
    ];
    $verificationDocs = ['professional_documents' => 'Documents professionnels', 'legal_documents' => 'Documents légaux', 'insurance_documents' => 'Assurances', 'accreditation_documents' => 'Agréments', 'certification_documents' => 'Certifications', 'authorization_documents' => 'Autorisations métier'];
    $verificationHints = ['professional_documents' => 'Attestation d’activité, portfolio, références.', 'legal_documents' => 'Obligatoire pour les entreprises.', 'insurance_documents' => 'Selon votre activité.', 'accreditation_documents' => 'Selon votre activité.', 'certification_documents' => 'Recommandé.', 'authorization_documents' => 'Selon votre activité.'];
    $tabCount = count($tabs);
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf
    {{-- Sections facultatives : marquées comme parcourues pour le serveur. --}}
    <input type="hidden" name="references_status" value="completed">
    <input type="hidden" name="qualifications_status" value="completed">

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('wrench', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité de {{ $profileMeta['label'] }}." data-verification="Ajoutez les documents nécessaires à la vérification de votre profil.">Décrivez votre activité de {{ $profileMeta['label'] }}.</p>
            </div>
            <a class="fm-head__profile" href="{{ route('pro.register.qualification') }}">
                <img src="{{ asset('ovanie-pro/profiles/' . $profileKey . '-card.jpg') }}" alt="">
                <span><b>{{ $profileMeta['label'] }}</b><small>Changer de profil</small></span>
            </a>
        </header>

        <nav class="fm-tabs fm-tabs--8" role="tablist" aria-label="Sections du formulaire">
            @foreach($tabs as $tabKey => $tabDef)
                <button type="button" class="fm-tab" role="tab" data-pb-tab="{{ $tabKey }}" aria-controls="panel-{{ $tabKey }}">
                    @proicon($tabDef[1], 'oa-ico')<span>{{ $tabDef[0] }}</span><i class="pb-tab__ok" hidden>@proicon('check', 'oa-ico')</i>
                </button>
            @endforeach
        </nav>

        @include('pro.auth.access.alerts', ['except' => collect($tabs)->pluck(2)->flatten()->all()])
        @if($errorTab)
            <div class="oa-alert oa-alert--error" role="alert">@proicon('info', 'oa-ico')<span>Certaines informations sont manquantes ou incorrectes. Les champs concernés sont signalés en rouge.</span></div>
        @endif

        {{-- ===================== 1. Structure ===================== --}}
        <section class="fm-panel" id="panel-company" data-pb-panel="company" role="tabpanel">
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('building', 'oa-ico')</span><div><h2>Entreprise ou professionnel</h2><p>Les champs s’adaptent à votre statut.</p></div></header>
                <div class="pb-field @error('profile_entity_type') is-invalid @enderror">
                    <label>Vous exercez comme&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--2" data-required-group="profile_entity_type">
                        <label class="fm-choice"><input type="radio" name="profile_entity_type" value="organization" @checked($entity === 'organization')><span class="fm-choice__dot"></span><span><b>Entreprise</b><small>Société, SARL, SA, etc.</small></span></label>
                        <label class="fm-choice"><input type="radio" name="profile_entity_type" value="independent" @checked($entity === 'independent')><span class="fm-choice__dot"></span><span><b>Professionnel indépendant</b><small>Artisan, consultant, freelance, etc.</small></span></label>
                    </div>
                    @error('profile_entity_type')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-grid pb-grid--2">
                    <div data-pb-show-if="profile_entity_type=organization">
                        @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. BTP Solutions SARL'])
                    </div>
                    <div data-pb-show-if="profile_entity_type=independent">
                        @include('pro.auth.access.field', ['name' => 'independent_status', 'label' => 'Statut professionnel', 'type' => 'select', 'options' => $options('independent_status'), 'required' => true, 'empty' => 'Sélectionnez'])
                    </div>
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial', 'required' => true, 'placeholder' => 'Ex. BTP Solutions'])
                </div>
                <div class="pb-grid pb-grid--3" data-pb-show-if="profile_entity_type=organization">
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro fiscal', 'required' => true, 'placeholder' => 'Ex. 1234567 F'])
                </div>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre (RCCM)', 'placeholder' => 'Ex. CI-ABJ-2024-B-12345', 'help' => 'Obligatoire pour les entreprises.'])
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->registration_country ?: $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                </div>
                @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse', 'placeholder' => 'Ex. Cocody, rue des Jardins, immeuble Horizon', 'help' => 'Obligatoire pour les entreprises.'])
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('phone', 'oa-ico')</span><div><h2>Coordonnées professionnelles</h2><p>Utilisées par vos clients pour vous joindre.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('professional_phone') is-invalid @enderror">
                        <label for="professional_phone">Téléphone professionnel&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_phone', 'value' => $values['professional_phone'] ?? '', 'id' => 'professional_phone', 'required' => true])
                        @error('professional_phone')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('professional_whatsapp') is-invalid @enderror">
                        <label for="professional_whatsapp">WhatsApp&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_whatsapp', 'value' => $values['professional_whatsapp'] ?? ($values['professional_phone'] ?? ''), 'id' => 'professional_whatsapp', 'prefixIcon' => 'phone', 'required' => true])
                        @error('professional_whatsapp')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    @include('pro.auth.access.field', ['name' => 'professional_email', 'label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'placeholder' => 'contact@monentreprise.ci'])
                    @include('pro.auth.access.field', ['name' => 'website', 'label' => 'Site web', 'type' => 'url', 'optional' => true, 'icon' => 'globe', 'placeholder' => 'https://www.monentreprise.ci'])
                </div>
            </div>
        </section>

        {{-- ===================== 2. Services ===================== --}}
        <section class="fm-panel" id="panel-services" data-pb-panel="services" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('wrench', 'oa-ico')</span><div><h2>Services</h2><p>La prestation principale que vous proposez.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'service_category', 'label' => 'Catégorie de service', 'type' => 'select', 'options' => $options('service_category'), 'required' => true, 'empty' => 'Sélectionnez'])
                    <div class="pb-field @error('service_subcategory') is-invalid @enderror">
                        <label for="f-service_subcategory">Sous-catégorie&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-icon-input">@proicon('search', 'oa-ico')<input class="pb-input" id="f-service_subcategory" name="service_subcategory" list="presta-subcategories" value="{{ $v('service_subcategory') }}" placeholder="Rechercher ou saisir…" maxlength="160" data-required="1"></div>
                        <datalist id="presta-subcategories">@foreach($subcategories as $sub)<option value="{{ $sub }}">@endforeach</datalist>
                        @error('service_subcategory')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                @include('pro.auth.access.field', ['name' => 'service_title', 'label' => 'Titre de la prestation', 'required' => true, 'placeholder' => 'Ex. Installation et mise en service de systèmes électriques'])
                @include('pro.auth.access.field', ['name' => 'services_description', 'label' => 'Description de la prestation', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Vos services, vos méthodes et votre valeur ajoutée.'])
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('award', 'oa-ico')</span><div><h2>Compétences et spécialités</h2><p>Ce que vous maîtrisez.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('skills') is-invalid @enderror">
                        <label>Compétences principales&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'skills', 'value' => $values['skills'] ?? '', 'placeholder' => 'Rechercher ou saisir une compétence…', 'suggestions' => $skillSuggestions, 'required' => true])
                    </div>
                    <div class="pb-field">
                        <label>Spécialités <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'specialties', 'value' => $values['specialties'] ?? '', 'placeholder' => 'Rechercher ou saisir une spécialité…', 'suggestions' => $specialtySuggestions])
                    </div>
                </div>
                <div class="pb-grid pb-grid--3">
                    @foreach(['installation_types' => ['Types d’interventions', ['Résidentiel', 'Tertiaire', 'Industriel', 'Infrastructures']], 'intervention_levels' => ['Niveau d’intervention', ['Basse tension (BT)', 'Moyenne tension (MT)', 'Petits ouvrages', 'Grands ouvrages']], 'standards' => ['Normes maîtrisées', ['NFC 15-100', 'IEC', 'ISO 9001', 'Eurocodes']]] as $tagName => $tagDef)
                        <div class="pb-field">
                            <label>{{ $tagDef[0] }} <small>(facultatif)</small></label>
                            @include('pro.auth.access.tags', ['name' => $tagName, 'value' => $values[$tagName] ?? '', 'placeholder' => 'Rechercher ou saisir…', 'suggestions' => $tagDef[1]])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ===================== 3. Intervention ===================== --}}
        <section class="fm-panel" id="panel-intervention" data-pb-panel="intervention" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('map-pin', 'oa-ico')</span><div><h2>Intervention</h2><p>Vos zones, votre disponibilité et votre capacité opérationnelle.</p></div></header>
                <div class="pb-field @error('intervention_zones') is-invalid @enderror">
                    <label>Zones d’intervention&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'intervention_zones', 'value' => $values['intervention_zones'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville, une région…', 'suggestions' => $zoneSuggestions, 'required' => true])
                </div>
                @foreach($radioGroups as $groupName => $groupDef)
                    <div class="pb-field @error($groupName) is-invalid @enderror">
                        <label>{{ $groupDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                        <div class="fm-choices fm-choices--{{ $groupDef[2] }}" data-required-group="{{ $groupName }}">
                            @foreach($groupDef[1] as $choice)
                                <label class="fm-choice"><input type="radio" name="{{ $groupName }}" value="{{ $choice }}" @checked($v($groupName) === $choice)><span class="fm-choice__dot"></span><span><b>{{ $choice }}</b></span></label>
                            @endforeach
                        </div>
                        @error($groupName)<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="pb-field">
                    <label>Types de clients <small>(facultatif)</small></label>
                    @include('pro.auth.access.tags', ['name' => 'client_types', 'value' => $values['client_types'] ?? '', 'placeholder' => 'Sélectionner…', 'suggestions' => ['Entreprises BTP', 'Promoteurs immobiliers', 'Maîtres d’ouvrage', 'Administrations publiques', 'Particuliers', 'ONG / Projets']])
                </div>
            </div>
        </section>

        {{-- ===================== 4. Moyens ===================== --}}
        <section class="fm-panel" id="panel-means" data-pb-panel="means" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('truck', 'oa-ico')</span><div><h2>Moyens</h2><p>Les équipements, machines et véhicules que vous utilisez.</p></div></header>
                <div class="pb-field @error('means_types') is-invalid @enderror">
                    <label>Types de moyens utilisés&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'means_types', 'value' => $values['means_types'] ?? '', 'placeholder' => 'Sélectionner…', 'suggestions' => ['Équipements', 'Machines', 'Véhicules', 'Équipements loués', 'Autres moyens'], 'required' => true])
                </div>
                <p class="fm-sublabel">Liste des équipements <span class="oa-req">*</span></p>
                <div data-pb-repeater data-noun="Équipement" data-min="1" data-fields='@json($equipmentFields)'>
                    <textarea name="equipment_json" hidden data-pb-repeater-value>{{ $v('equipment_json', '[]') }}</textarea>
                    <div data-pb-repeater-list></div>
                    <button type="button" class="fm-add-row" data-pb-repeater-add>@proicon('plus', 'oa-ico') Ajouter un équipement ou un moyen</button>
                </div>
                @error('equipment_json')<p class="pb-error" style="margin: -8px 0 14px">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- ===================== 5. Références ===================== --}}
        <section class="fm-panel" id="panel-references" data-pb-panel="references" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('file', 'oa-ico')</span><div><h2>Références <small>(facultatif)</small></h2><p>Vos réalisations. Ne renseignez pas les coordonnées privées de vos clients.</p></div></header>
                <div data-pb-repeater data-noun="Réalisation" data-fields='@json($referenceFields)'>
                    <textarea name="references_json" hidden data-pb-repeater-value>{{ $v('references_json', '[]') }}</textarea>
                    <div data-pb-repeater-list></div>
                    <button type="button" class="fm-add-row" data-pb-repeater-add>@proicon('plus', 'oa-ico') Ajouter une référence</button>
                </div>
                @include('pro.auth.access.dropzone', ['type' => 'work_media', 'doc' => $documents['work_media'], 'existing' => $docs('work_media'), 'title' => 'Photos et vidéos de réalisations', 'hint' => null, 'help' => 'JPG, PNG, MP4, MOV. 20 Mo max par fichier, 6 fichiers au plus.'])
            </div>
        </section>

        {{-- ===================== 6. Qualifications ===================== --}}
        <section class="fm-panel" id="panel-qualifications" data-pb-panel="qualifications" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('award', 'oa-ico')</span><div><h2>Qualifications <small>(facultatif)</small></h2><p>Certifications, assurances, agréments, autorisations et qualifications métier.</p></div></header>
                <div data-pb-repeater data-noun="Qualification" data-fields='@json($qualificationFields)'>
                    <textarea name="qualifications_json" hidden data-pb-repeater-value>{{ $v('qualifications_json', '[]') }}</textarea>
                    <div data-pb-repeater-list></div>
                    <button type="button" class="fm-add-row" data-pb-repeater-add>@proicon('plus', 'oa-ico') Ajouter une qualification</button>
                </div>
                @include('pro.auth.access.dropzone', ['type' => 'qualification_proofs', 'doc' => $documents['qualification_proofs'], 'existing' => $docs('qualification_proofs'), 'title' => 'Justificatifs', 'hint' => 'Un fichier par qualification déclarée.'])
            </div>
        </section>

        {{-- ===================== 7. Tarification ===================== --}}
        <section class="fm-panel" id="panel-pricing" data-pb-panel="pricing" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('tag', 'oa-ico')</span><div><h2>Tarification</h2><p>Comment vous facturez généralement vos prestations.</p></div></header>
                <div class="pb-field @error('pricing_mode') is-invalid @enderror">
                    <label>Mode de facturation principal&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--4" data-required-group="pricing_mode">
                        @foreach($options('pricing_mode') as $k => $l)
                            <label class="fm-choice"><input type="radio" name="pricing_mode" value="{{ $k }}" @checked($v('pricing_mode') === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                    @error('pricing_mode')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-grid pb-grid--3" data-pb-show-if="pricing_mode=indicative|hour|day|package|unit|other">
                    @include('pro.auth.access.field', ['name' => 'indicative_rate', 'label' => 'Montant indicatif (à partir de)', 'placeholder' => 'Ex. 25000'])
                    @include('pro.auth.access.field', ['name' => 'pricing_currency', 'label' => 'Devise', 'type' => 'select', 'options' => $options('pricing_currency'), 'default' => 'FCFA', 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'pricing_unit', 'label' => 'Unité de facturation', 'type' => 'select', 'options' => ['Heure', 'Journée', 'Forfait', 'Unité', 'm²', 'Mètre linéaire'], 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-field">
                    <label>Autres modes de facturation <small>(facultatif)</small></label>
                    @include('pro.auth.access.tags', ['name' => 'pricing_modes', 'value' => $values['pricing_modes'] ?? '', 'placeholder' => 'Sélectionner…', 'suggestions' => array_values($options('pricing_mode'))])
                </div>
                @include('pro.auth.access.field', ['name' => 'pricing_notes', 'label' => 'Précisions tarifaires', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex. les tarifs varient selon la complexité, la localisation du chantier, l’urgence et le volume.'])
            </div>
        </section>

        {{-- ===================== 8. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="documents">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('shield', 'oa-ico')</span><div><h2>Vérification</h2><p>Les documents demandés dépendent de votre pays, de votre statut et de votre service.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @foreach($verificationDocs as $docType => $docTitle)
                        @include('pro.auth.access.dropzone', ['type' => $docType, 'doc' => $documents[$docType], 'existing' => $docs($docType), 'title' => $docTitle, 'hint' => $verificationHints[$docType]])
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

        <a class="fm-help" href="{{ route('contact.index') }}" target="_blank" rel="noopener">@proicon('headset', 'oa-ico')<span><b>Besoin d’aide ?</b><small>Contactez notre équipe</small></span>@proicon('chevron-right', 'oa-ico')</a>
    </aside>
</form>
@endsection
