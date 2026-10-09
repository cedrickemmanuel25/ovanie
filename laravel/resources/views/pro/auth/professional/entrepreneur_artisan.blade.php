{{--
    Informations métier — Entrepreneur / Artisan professionnel (étape 3 sur 4).
    Six sections en onglets : Identité, Métier, Intervention, Réalisations, Qualifications, Vérification.
    Le statut (indépendant ou structure) se choisit ici : il adapte les champs demandés.
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
    $v = fn (string $field, $fallback = '') => old($field, $values[$field] ?? $fallback);
    $docs = fn (string $docType) => $providedDocuments->get($docType, collect());
    $options = fn (string $field) => (array) ($fields[$field]['options'] ?? []);
    $entity = old('profile_entity_type', $entityType ?: 'independent');

    // Onglet => [libellé, pictogramme, champs concernés (pour rouvrir le bon onglet en cas d'erreur)].
    $tabs = [
        'identity' => ['Identité', 'user', ['profile_entity_type', 'trade_name', 'independent_status', 'legal_name', 'legal_form', 'registration_country', 'city', 'address', 'registration_number', 'tax_number', 'professional_phone', 'professional_whatsapp', 'professional_email']],
        'trade' => ['Métier', 'briefcase', ['main_trade', 'specialties', 'experience_level', 'services']],
        'intervention' => ['Intervention', 'map-pin', ['intervention_zones', 'availability', 'team_size', 'intervention_capacity', 'client_types']],
        'references' => ['Réalisations', 'file', ['references_json', 'references_completed', 'documents.work_photos']],
        'qualifications' => ['Qualifications', 'award', ['qualifications_note', 'qualifications_completed', 'documents.certificates', 'documents.authorizations', 'documents.insurance', 'documents.professional_card', 'documents.approvals']],
        'verification' => ['Vérification', 'shield', ['documents.representative_id', 'documents.professional_document', 'documents.activity_proof', 'documents.authorization_approval', 'documents.liability_insurance', 'documents.other_documents']],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'identity' => ['Développez votre activité et trouvez de nouvelles opportunités BTP.', 'Les informations et documents demandés s’adaptent à votre statut et à votre pays.'],
        'trade' => ['Recevez des demandes adaptées à votre métier.', 'Choisissez plusieurs spécialités et services pour mieux décrire vos compétences.'],
        'intervention' => ['Trouvez des chantiers près de chez vous.', 'Indiquez toutes les zones où vous intervenez pour recevoir des projets pertinents.'],
        'references' => ['Vos réalisations rassurent vos futurs clients.', 'Cette section est facultative, mais quelques réalisations renforcent nettement votre crédibilité.'],
        'qualifications' => ['Valorisez vos compétences et vos qualifications.', 'Ces documents sont facultatifs. Plus vous en ajoutez, plus vous rassurez vos clients.'],
        'verification' => ['Des professionnels qualifiés pour bâtir l’Afrique de l’Ouest.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $capacityHelp = ['petits_travaux' => 'Moins de 10 millions FCFA', 'projets_moyens' => '10 à 50 millions FCFA', 'grands_chantiers' => 'Plus de 50 millions FCFA', 'tous' => ''];
    $availabilityHelp = ['maintenant' => 'Je peux démarrer rapidement.', 'quelques_jours' => 'Disponible dans moins d’un mois.', 'planification' => 'Selon les projets et mon planning.'];
    $zoneSuggestions = array_merge((array) ($cities[$profile->activity_country] ?? []), $countries);
    $referenceFields = [
        ['key' => 'title', 'label' => 'Titre du projet', 'placeholder' => 'Ex. Installation électrique villa', 'required' => true],
        ['key' => 'type', 'label' => 'Type de réalisation', 'type' => 'select', 'options' => array_values($options('services')), 'required' => true],
        ['key' => 'location', 'label' => 'Ville / pays', 'placeholder' => 'Ex. Abidjan, Côte d’Ivoire', 'required' => true],
        ['key' => 'year', 'label' => 'Année', 'type' => 'year', 'required' => true],
        ['key' => 'description', 'label' => 'Description courte', 'type' => 'textarea', 'placeholder' => 'Travaux réalisés, particularités, résultat obtenu…', 'wide' => true],
        ['key' => 'client', 'label' => 'Client / référence (si publiable)', 'placeholder' => 'Ex. Client privé, Société ABC…', 'wide' => true],
    ];
    $qualificationDocs = ['certificates' => 'Certificats professionnels', 'authorizations' => 'Autorisations', 'insurance' => 'Assurances', 'professional_card' => 'Carte professionnelle', 'approvals' => 'Agréments'];
    $qualificationHints = ['certificates' => 'Diplômes, attestations de formation.', 'authorizations' => 'Autorisations liées à votre activité.', 'insurance' => 'Responsabilité civile, décennale…', 'professional_card' => 'Carte d’artisan ou équivalent.', 'approvals' => 'Agréments spécifiques à votre métier.'];
    $verificationDocs = ['representative_id' => 'Pièce d’identité', 'professional_document' => 'Document professionnel', 'activity_proof' => 'Justificatif d’activité', 'authorization_approval' => 'Autorisation ou agrément', 'liability_insurance' => 'Assurance responsabilité civile', 'other_documents' => 'Autres documents'];
    $verificationHints = ['representative_id' => 'CNI, passeport ou titre de séjour valide.', 'professional_document' => 'Carte d’artisan ou attestation d’enregistrement.', 'activity_proof' => 'Facture, contrat ou devis de moins de 12 mois.', 'authorization_approval' => 'Pour certaines activités (électricité, gaz…).', 'liability_insurance' => 'Recommandée.', 'other_documents' => 'Tout document utile.'];
    $picked = fn (string $field) => (array) $v($field, []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $tabCount = count($tabs);
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf
    {{-- Sections facultatives : marquées comme parcourues pour le serveur. --}}
    <input type="hidden" name="references_completed" value="1">
    <input type="hidden" name="qualifications_completed" value="1">

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('hardhat', 'oa-ico')</span>
            <div class="fm-head__text">
                <h1>Informations métier</h1>
                <p data-pb-lead data-default="Décrivez votre activité d’{{ $profileMeta['label'] }}." data-verification="Ajoutez les documents nécessaires à la vérification de votre profil.">Décrivez votre activité d’{{ $profileMeta['label'] }}.</p>
            </div>
            <a class="fm-head__profile" href="{{ route('pro.register.qualification') }}">
                <img src="{{ asset('ovanie-pro/profiles/' . $profileKey . '-card.jpg') }}" alt="">
                <span><b>Entrepreneur / Artisan</b><small>Changer de profil</small></span>
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

        {{-- ===================== 1. Identité professionnelle ===================== --}}
        <section class="fm-panel" id="panel-identity" data-pb-panel="identity" role="tabpanel">
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('user', 'oa-ico')</span><div><h2>Identité professionnelle</h2><p>Les champs s’adaptent à votre statut.</p></div></header>
                <div class="pb-field @error('profile_entity_type') is-invalid @enderror">
                    <label>Vous exercez comme&nbsp;<span class="oa-req">*</span></label>
                    <div class="fm-choices fm-choices--2" data-required-group="profile_entity_type">
                        <label class="fm-choice"><input type="radio" name="profile_entity_type" value="independent" @checked($entity === 'independent')><span class="fm-choice__dot"></span><span><b>Professionnel indépendant</b><small>Vous exercez un métier ou proposez directement des prestations.</small></span></label>
                        <label class="fm-choice"><input type="radio" name="profile_entity_type" value="organization" @checked($entity === 'organization')><span class="fm-choice__dot"></span><span><b>Entreprise / structure</b><small>Vous avez une entreprise, une société ou une structure formelle.</small></span></label>
                    </div>
                    @error('profile_entity_type')<p class="pb-error">{{ $message }}</p>@enderror
                </div>

                <div class="pb-grid pb-grid--2" data-pb-show-if="profile_entity_type=independent">
                    @include('pro.auth.access.field', ['name' => 'independent_status', 'label' => 'Statut professionnel', 'type' => 'select', 'options' => $options('independent_status'), 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-grid pb-grid--2" data-pb-show-if="profile_entity_type=organization">
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale', 'required' => true, 'placeholder' => 'Ex. Diarra Services SARL'])
                    @include('pro.auth.access.field', ['name' => 'legal_form', 'label' => 'Forme juridique', 'type' => 'select', 'options' => $options('legal_form'), 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'trade_name', 'label' => 'Nom commercial / enseigne', 'optional' => true, 'placeholder' => 'Ex. SOS Bâtiment'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre (RCCM ou équivalent)', 'optional' => true, 'placeholder' => 'Ex. CI-ABJ-2023-A-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro fiscal', 'optional' => true, 'placeholder' => 'Ex. 1234567 F'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('map-pin', 'oa-ico')</span><div><h2>Localisation et coordonnées</h2><p>Où vous êtes basé et comment vos clients vous joignent.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $profile->registration_country ?: $profile->activity_country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse professionnelle', 'optional' => true, 'placeholder' => 'Ex. Cocody, Riviera 3'])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('professional_phone') is-invalid @enderror">
                        <label for="professional_phone">Téléphone professionnel&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_phone', 'value' => $values['professional_phone'] ?? '', 'id' => 'professional_phone', 'required' => true])
                        @error('professional_phone')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('professional_whatsapp') is-invalid @enderror" data-pb-whatsapp>
                        <label for="professional_whatsapp">WhatsApp</label>
                        @include('pro.auth.access.phone-split', ['name' => 'professional_whatsapp', 'value' => $values['professional_whatsapp'] ?? '', 'id' => 'professional_whatsapp', 'prefixIcon' => 'phone'])
                        @error('professional_whatsapp')<p class="pb-error">{{ $message }}</p>@enderror
                        <label class="pb-switch"><input type="checkbox" data-pb-whatsapp-same @checked($whatsappSame)><span class="pb-switch__track"></span><span><b>Identique au téléphone professionnel</b></span></label>
                    </div>
                    @include('pro.auth.access.field', ['name' => 'professional_email', 'label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'icon' => 'mail', 'placeholder' => 'votre.email@exemple.com'])
                </div>
            </div>
        </section>

        {{-- ===================== 2. Métier ===================== --}}
        <section class="fm-panel" id="panel-trade" data-pb-panel="trade" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('briefcase', 'oa-ico')</span><div><h2>Métier</h2><p>Votre métier, vos spécialités et vos services.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'main_trade', 'label' => 'Métier principal', 'type' => 'select', 'options' => $options('main_trade'), 'required' => true, 'icon' => 'wrench', 'empty' => 'Sélectionnez votre métier'])
                </div>
                @foreach(['specialties' => ['Spécialités', 'checkbox', 4], 'experience_level' => ['Années d’expérience', 'radio', 4], 'services' => ['Services proposés', 'checkbox', 5]] as $groupName => $groupDef)
                    <div class="pb-field @error($groupName) is-invalid @enderror">
                        <label>{{ $groupDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                        <div class="fm-choices fm-choices--{{ $groupDef[2] }}" data-required-group="{{ $groupName }}">
                            @foreach($options($groupName) as $k => $l)
                                @if($groupDef[1] === 'radio')
                                    <label class="fm-choice"><input type="radio" name="{{ $groupName }}" value="{{ $k }}" @checked($v($groupName) === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b></span></label>
                                @else
                                    <label class="fm-choice fm-choice--check"><input type="checkbox" name="{{ $groupName }}[]" value="{{ $k }}" @checked(in_array($k, $picked($groupName), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                                @endif
                            @endforeach
                        </div>
                        @error($groupName)<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ===================== 3. Intervention ===================== --}}
        <section class="fm-panel" id="panel-intervention" data-pb-panel="intervention" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('map-pin', 'oa-ico')</span><div><h2>Intervention</h2><p>Où vous intervenez, quand, et pour quels projets.</p></div></header>
                <div class="pb-field @error('intervention_zones') is-invalid @enderror">
                    <label>Zones d’intervention&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'intervention_zones', 'value' => $values['intervention_zones'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville, une commune, une région…', 'suggestions' => $zoneSuggestions, 'required' => true])
                </div>
                @foreach(['availability' => ['Disponibilité', 3, $availabilityHelp], 'team_size' => ['Taille d’équipe', 4, []], 'intervention_capacity' => ['Capacité d’intervention', 4, $capacityHelp]] as $groupName => $groupDef)
                    <div class="pb-field @error($groupName) is-invalid @enderror">
                        <label>{{ $groupDef[0] }}&nbsp;<span class="oa-req">*</span></label>
                        <div class="fm-choices fm-choices--{{ $groupDef[1] }}" data-required-group="{{ $groupName }}">
                            @foreach($options($groupName) as $k => $l)
                                <label class="fm-choice"><input type="radio" name="{{ $groupName }}" value="{{ $k }}" @checked($v($groupName) === $k)><span class="fm-choice__dot"></span><span><b>{{ $l }}</b>@if(!empty($groupDef[2][$k]))<small>{{ $groupDef[2][$k] }}</small>@endif</span></label>
                            @endforeach
                        </div>
                        @error($groupName)<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="pb-field">
                    <label>Types de clients <small>(facultatif)</small></label>
                    <div class="fm-choices fm-choices--3">
                        @foreach($options('client_types') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="client_types[]" value="{{ $k }}" @checked(in_array($k, $picked('client_types'), true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b></span></label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 4. Réalisations ===================== --}}
        <section class="fm-panel" id="panel-references" data-pb-panel="references" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('file', 'oa-ico')</span><div><h2>Réalisations <small>(facultatif)</small></h2><p>Quelques travaux réalisés pour mettre en valeur votre savoir-faire.</p></div></header>
                <div data-pb-repeater data-noun="Réalisation" data-fields='@json($referenceFields)'>
                    <textarea name="references_json" hidden data-pb-repeater-value>{{ $v('references_json', '[]') }}</textarea>
                    <div data-pb-repeater-list></div>
                    <button type="button" class="fm-add-row" data-pb-repeater-add>@proicon('plus', 'oa-ico') Ajouter une réalisation</button>
                </div>
                @include('pro.auth.access.dropzone', ['type' => 'work_photos', 'doc' => $documents['work_photos'], 'existing' => $docs('work_photos'), 'title' => 'Photos de réalisations', 'hint' => null, 'help' => 'JPG, PNG. 10 Mo max, 6 fichiers au plus.'])
            </div>
        </section>

        {{-- ===================== 5. Qualifications ===================== --}}
        <section class="fm-panel" id="panel-qualifications" data-pb-panel="qualifications" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('award', 'oa-ico')</span><div><h2>Qualifications <small>(facultatif)</small></h2><p>Certificats, autorisations, assurances et autres documents qui renforcent votre crédibilité.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @foreach($qualificationDocs as $docType => $docTitle)
                        @include('pro.auth.access.dropzone', ['type' => $docType, 'doc' => $documents[$docType], 'existing' => $docs($docType), 'title' => $docTitle, 'hint' => $qualificationHints[$docType]])
                    @endforeach
                </div>
                @include('pro.auth.access.field', ['name' => 'qualifications_note', 'label' => 'Précisions', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex. organisme émetteur, numéro, date d’expiration de vos documents…'])
            </div>
        </section>

        {{-- ===================== 6. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            <div class="fm-card" data-pb-status="documents">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('shield', 'oa-ico')</span><div><h2>Vérification du profil</h2><p>Les trois premiers documents sont requis ; les autres dépendent de votre métier.</p></div></header>
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
            <strong>Entrepreneur / Artisan</strong>
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
