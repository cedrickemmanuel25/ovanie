{{--
    Informations métier — Revendeur professionnel (étape 3 sur 4).
    Cinq sections en onglets : Entreprise, Approvisionnement, Activité commerciale, Besoins, Vérification.
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
        'company' => ['Entreprise', 'building', ['legal_name', 'registration_country', 'city', 'address', 'registration_number', 'tax_number', 'representative_name', 'responsible_position', 'professional_phone', 'professional_whatsapp', 'professional_email']],
        'sourcing' => ['Approvisionnement', 'truck', ['purchase_categories', 'resale_categories', 'sourcing_countries', 'usual_suppliers', 'usual_brands']],
        'commercial' => ['Activité', 'chart', ['sales_zones', 'purchase_volumes', 'purchase_volume_unit', 'purchase_volume_period', 'purchase_frequency', 'importer', 'commercial_supply_countries']],
        'needs' => ['Besoins', 'file-search', ['needs']],
        'verification' => ['Vérification', 'shield', collect($documents)->keys()->map(fn ($k) => 'documents.' . $k)->all()],
    ];
    $errorTab = null;
    foreach ($tabs as $tabKey => $tabDef) {
        foreach ($tabDef[2] as $fieldName) {
            if ($errors->has($fieldName) || $errors->has($fieldName . '.*')) { $errorTab = $tabKey; break 2; }
        }
    }

    $side = [
        'company' => ['Développez votre activité de revente auprès des professionnels du BTP.', 'Les informations demandées peuvent varier selon votre pays et le type de structure.'],
        'sourcing' => ['Accédez à de nouvelles sources d’approvisionnement.', 'Ces informations servent à vous recommander des fournisseurs et des fabricants adaptés.'],
        'commercial' => ['Trouvez des produits adaptés à vos clients professionnels.', 'Les volumes d’achat sont facultatifs et donnés à titre indicatif.'],
        'needs' => ['Choisissez les modes d’approvisionnement adaptés à votre activité.', 'Vos préférences personnalisent les fournisseurs suggérés et votre parcours d’achat.'],
        'verification' => ['Finalisez votre dossier pour accéder au réseau de fournisseurs.', 'L’envoi des documents ne vaut pas vérification : nos équipes les contrôlent et peuvent demander des compléments.'],
    ];

    $positions = ['Gérant / Fondateur', 'Direction générale', 'Direction commerciale', 'Responsable achats', 'Responsable de magasin', 'Autre'];
    $categorySuggestions = array_merge(['Quincaillerie', 'Électricité', 'Plomberie', 'Sanitaire', 'Outillage', 'Consommables BTP'], $opt['product_categories']);
    $supplierSuggestions = ['Würth', 'Rexel', 'Sonepar', 'CEDEO', 'Bernabé', 'CFAO'];
    $brandSuggestions = ['Legrand', 'Schneider Electric', 'Sika', 'Holcim', 'CIMAF', 'Dangote', 'Bosch', 'Makita'];
    $zoneSuggestions = array_merge(['National'], (array) ($cities[$profile->activity_country] ?? []));
    $needHelp = [
        'rfq' => 'Publier vos besoins et recevoir plusieurs devis.',
        'direct_orders' => 'Acheter rapidement des produits disponibles.',
        'find_manufacturers' => 'Identifier des fabricants capables de produire selon vos besoins.',
        'find_suppliers' => 'Découvrir des distributeurs pour vos achats réguliers.',
        'compare_offers' => 'Mettre plusieurs offres en concurrence.',
        'international_sourcing' => 'Rechercher des sources d’achat à l’international.',
    ];
    $chosenNeeds = (array) $v('needs', []);
    $whatsappSame = blank($v('professional_whatsapp')) || $v('professional_whatsapp') === $v('professional_phone');
    $country = $profile->registration_country ?: $profile->activity_country;
    $docGroups = [
        'legal' => ['Documents légaux', 'building', ['registry' => 'Registre professionnel (RCCM)', 'tax_certificate' => 'Identification fiscale']],
        'professional' => ['Documents professionnels', 'briefcase', ['professional_proof' => 'Justificatif d’activité professionnelle', 'commercial_proof' => 'Catalogue ou preuve d’activité de revente']],
        'permits' => ['Autorisations', 'award', ['other_authorizations' => 'Autorisation spécifique ou agrément']],
    ];
@endphp
<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data" class="fm" novalidate
      data-pb-form data-error-tab="{{ $errorTab }}" data-cities='@json($cities)'>
    @csrf

    <div class="fm-main">
        @include('pro.auth.access.stepper', ['current' => 3])

        <header class="fm-head">
            <span class="fm-head__icon">@proicon('store', 'oa-ico')</span>
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
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('building', 'oa-ico')</span><div><h2>Identité professionnelle</h2><p>Telle qu’elle figure sur vos documents professionnels.</p></div></header>
                <div class="pb-grid pb-grid--3">
                    @include('pro.auth.access.field', ['name' => 'legal_name', 'label' => 'Raison sociale ou nom commercial', 'required' => true, 'placeholder' => 'Ex. Quincaillerie Pro Services'])
                    @include('pro.auth.access.field', ['name' => 'registration_number', 'label' => 'Registre professionnel', 'optional' => true, 'placeholder' => 'Ex. CI-ABJ-2024-B-12345'])
                    @include('pro.auth.access.field', ['name' => 'tax_number', 'label' => 'Numéro contribuable', 'optional' => true, 'placeholder' => 'Ex. 1234567 F'])
                    @include('pro.auth.access.field', ['name' => 'registration_country', 'label' => 'Pays d’enregistrement', 'type' => 'country', 'options' => $countries, 'required' => true, 'default' => $country])
                    @include('pro.auth.access.city', ['name' => 'city', 'label' => 'Ville', 'countryField' => 'registration_country'])
                    @include('pro.auth.access.field', ['name' => 'address', 'label' => 'Adresse', 'required' => true, 'placeholder' => 'Ex. Zone 4, rue des Commerces, immeuble 12'])
                </div>
            </div>

            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon">@proicon('user', 'oa-ico')</span><div><h2>Représentant et coordonnées</h2><p>Utilisées pour vos échanges avec les fournisseurs et les acheteurs.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    @include('pro.auth.access.field', ['name' => 'representative_name', 'label' => 'Nom et prénoms', 'required' => true, 'icon' => 'user', 'placeholder' => 'Ex. Kouassi Jean Marc', 'default' => trim($user->first_name . ' ' . $user->last_name)])
                    @include('pro.auth.access.field', ['name' => 'responsible_position', 'label' => 'Fonction', 'type' => 'select', 'options' => $positions, 'required' => true, 'empty' => 'Sélectionnez'])
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
                </div>
            </div>
        </section>

        {{-- ===================== 2. Approvisionnement ===================== --}}
        <section class="fm-panel" id="panel-sourcing" data-pb-panel="sourcing" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('truck', 'oa-ico')</span><div><h2>Approvisionnement</h2><p>Ce que vous achetez et ce que vous revendez.</p></div></header>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field @error('purchase_categories') is-invalid @enderror">
                        <label>Catégories achetées&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'purchase_categories', 'value' => $values['purchase_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $categorySuggestions, 'required' => true])
                    </div>
                    <div class="pb-field @error('resale_categories') is-invalid @enderror">
                        <label>Catégories revendues&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'resale_categories', 'value' => $values['resale_categories'] ?? '', 'placeholder' => 'Rechercher une catégorie…', 'suggestions' => $categorySuggestions, 'required' => true])
                    </div>
                </div>
                <div class="pb-field @error('sourcing_countries') is-invalid @enderror">
                    <label>Pays d’approvisionnement recherchés&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'sourcing_countries', 'value' => $values['sourcing_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                </div>
                <div class="pb-grid pb-grid--2">
                    <div class="pb-field">
                        <label>Fournisseurs habituels <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'usual_suppliers', 'value' => $values['usual_suppliers'] ?? '', 'placeholder' => 'Rechercher ou saisir un fournisseur…', 'suggestions' => $supplierSuggestions])
                    </div>
                    <div class="pb-field">
                        <label>Marques habituelles <small>(facultatif)</small></label>
                        @include('pro.auth.access.tags', ['name' => 'usual_brands', 'value' => $values['usual_brands'] ?? '', 'placeholder' => 'Rechercher ou saisir une marque…', 'suggestions' => $brandSuggestions])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 3. Activité commerciale ===================== --}}
        <section class="fm-panel" id="panel-commercial" data-pb-panel="commercial" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('chart', 'oa-ico')</span><div><h2>Activité commerciale</h2><p>Où vous vendez et à quel rythme vous achetez.</p></div></header>
                <div class="pb-field @error('sales_zones') is-invalid @enderror">
                    <label>Zones de vente&nbsp;<span class="oa-req">*</span></label>
                    @include('pro.auth.access.tags', ['name' => 'sales_zones', 'value' => $values['sales_zones'] ?? '', 'placeholder' => 'Rechercher ou saisir une ville, une région…', 'suggestions' => $zoneSuggestions, 'required' => true])
                </div>
                <div class="pb-grid pb-grid--3">
                    <div class="pb-field @error('purchase_volumes') is-invalid @enderror">
                        <label for="f-purchase_volumes">Volume d’achat <small>(facultatif)</small></label>
                        <div class="pb-combo"><input class="pb-input" id="f-purchase_volumes" name="purchase_volumes" inputmode="decimal" value="{{ $v('purchase_volumes') }}" placeholder="Ex. 5000">
                            <div class="pb-select"><select name="purchase_volume_unit" aria-label="Unité">@foreach($options('purchase_volume_unit') as $k => $l)<option value="{{ $k }}" @selected($v('purchase_volume_unit', 'cartons') === $k)>{{ $l }}</option>@endforeach</select></div></div>
                    </div>
                    @include('pro.auth.access.field', ['name' => 'purchase_volume_period', 'label' => 'Période du volume', 'type' => 'select', 'options' => $options('purchase_volume_period'), 'default' => 'mensuelle', 'empty' => 'Sélectionnez'])
                    @include('pro.auth.access.field', ['name' => 'purchase_frequency', 'label' => 'Fréquence d’achat', 'type' => 'select', 'options' => $options('purchase_frequency'), 'required' => true, 'empty' => 'Sélectionnez'])
                </div>
                <div class="pb-grid pb-grid--side">
                    <div class="pb-field @error('importer') is-invalid @enderror">
                        <label>Êtes-vous importateur&nbsp;?&nbsp;<span class="oa-req">*</span></label>
                        <div class="pb-toggle pb-toggle--wide" data-required-group="importer">
                            @foreach(['yes' => 'Oui', 'no' => 'Non'] as $k => $l)<label><input type="radio" name="importer" value="{{ $k }}" @checked($v('importer') === $k)><span>{{ $l }}</span></label>@endforeach
                        </div>
                        @error('importer')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="pb-field @error('commercial_supply_countries') is-invalid @enderror">
                        <label>Pays d’approvisionnement actuels&nbsp;<span class="oa-req">*</span></label>
                        @include('pro.auth.access.tags', ['name' => 'commercial_supply_countries', 'value' => $values['commercial_supply_countries'] ?? '', 'placeholder' => 'Rechercher un pays…', 'suggestions' => $countries, 'required' => true])
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== 4. Besoins ===================== --}}
        <section class="fm-panel" id="panel-needs" data-pb-panel="needs" role="tabpanel" hidden>
            <div class="fm-card">
                <header class="fm-sec"><span class="fm-sec__icon fm-sec__icon--orange">@proicon('file-search', 'oa-ico')</span><div><h2>Besoins d’approvisionnement</h2><p>Comment souhaitez-vous vous approvisionner sur OVANIE PRO ? Plusieurs choix possibles.</p></div></header>
                <div class="pb-field @error('needs') is-invalid @enderror">
                    <div class="fm-choices fm-choices--2" data-required-group="needs">
                        @foreach($options('needs') as $k => $l)
                            <label class="fm-choice fm-choice--check"><input type="checkbox" name="needs[]" value="{{ $k }}" @checked(in_array($k, $chosenNeeds, true))><span class="fm-choice__dot">@proicon('check', 'oa-ico')</span><span><b>{{ $l }}</b><small>{{ $needHelp[$k] ?? '' }}</small></span></label>
                        @endforeach
                    </div>
                    @error('needs')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- ===================== 5. Vérification ===================== --}}
        <section class="fm-panel" id="panel-verification" data-pb-panel="verification" role="tabpanel" hidden>
            @foreach($docGroups as $groupKey => $groupDef)
                <div class="fm-card" data-pb-status="{{ $groupKey }}">
                    <header class="fm-sec"><span class="fm-sec__icon">@proicon($groupDef[1], 'oa-ico')</span><div><h2>{{ $groupDef[0] }}</h2>@if($loop->first)<p>Les pièces demandées varient selon votre pays. Aucune preuve d’usine n’est requise.</p>@endif</div></header>
                    <div class="pb-grid pb-grid--2">
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
