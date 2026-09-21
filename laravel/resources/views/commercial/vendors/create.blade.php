@extends('layouts.staff')

@section('title', 'Créer un vendeur et sa boutique | Commercial OVANIE')

@section('inline_styles')
@include('commercial.accounts.partials.professional-form-styles')
@endsection

@php
    $stepFields = [
        1 => ['first_name', 'last_name', 'email', 'phone', 'password', 'selfie'],
        2 => ['sellerType', 'shopName', 'companyName', 'legalForm', 'rccm', 'taxpayerNumber', 'rccmFile', 'taxFile', 'main_category', 'description'],
        3 => ['region', 'city', 'commune', 'district', 'landmark', 'address', 'delivery_zone', 'whatsapp', 'business_email', 'logistics_type', 'location_mode', 'latitude', 'longitude', 'geo_accuracy', 'geo_source', 'location_confirmed'],
        4 => ['identityType', 'identityNumber', 'identityUploadMode', 'identityFile', 'identityFileFront', 'identityFileBack', 'payment_mode', 'mmOperator', 'mmNumber', 'mmHolder'],
    ];
    $initialStep = 1;
    foreach ($stepFields as $stepNumber => $fields) {
        foreach ($fields as $fieldName) {
            if ($errors->has($fieldName)) {
                $initialStep = $stepNumber;
                break 2;
            }
        }
    }
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Créer un vendeur et sa boutique</h1>
        <p class="page-subtitle">Suivez les quatre étapes. Une seule étape est affichée à la fois afin d’éviter les mélanges et les erreurs de saisie.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('commercial.vendors.index') }}"><i data-lucide="arrow-left"></i>Retour aux boutiques</a>
    </div>
</div>

<div class="ocf-page">
    <form method="POST" action="{{ route('commercial.vendors.store') }}" enctype="multipart/form-data" class="ocf-card ocf-wizard ocf-form" id="vendorCreateForm" data-initial-step="{{ $initialStep }}" novalidate>
        @csrf
        @if(!empty($prospect))<input type="hidden" name="prospect_id" value="{{ $prospect->id }}">@endif

        <div class="ocf-card__head">
            <div class="ocf-card__head-main">
                <span class="ocf-card__icon"><i data-lucide="store"></i></span>
                <div>
                    <h2>Ouverture d’une boutique</h2>
                    <p>Le compte vendeur et la boutique seront créés ensemble. Les informations restent séparées par étape : responsable, boutique, logistique, puis identité et reversement.</p>
                </div>
            </div>
            <span class="ocf-badge">Saisie terrain</span>
        </div>

        <nav class="ocf-steps" aria-label="Étapes de création">
            <button class="ocf-step" type="button" data-step-button="1">
                <span class="ocf-step__dot">1</span>
                <span><strong>Responsable</strong><small>Compte et contact</small></span>
            </button>
            <button class="ocf-step" type="button" data-step-button="2">
                <span class="ocf-step__dot">2</span>
                <span><strong>Boutique</strong><small>Activité et catégorie</small></span>
            </button>
            <button class="ocf-step" type="button" data-step-button="3">
                <span class="ocf-step__dot">3</span>
                <span><strong>Localisation</strong><small>Adresse et logistique</small></span>
            </button>
            <button class="ocf-step" type="button" data-step-button="4">
                <span class="ocf-step__dot">4</span>
                <span><strong>Vérification</strong><small>Identité et reversement</small></span>
            </button>
        </nav>

        @if($errors->any())
            <div class="ocf-errors" role="alert" style="margin:20px 26px 0">
                <strong>La boutique n’a pas été créée. Corrigez les champs signalés.</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="ocf-panel" data-step-panel="1" aria-labelledby="vendorStep1Title">
            <div class="ocf-panel-intro">
                <div>
                    <h3 id="vendorStep1Title">Responsable de la boutique</h3>
                    <p>Créez les accès du vendeur et ajoutez sa photo. Aucune information de boutique n’est demandée dans cette étape.</p>
                </div>
                <span class="ocf-panel-tag">Étape 1 sur 4</span>
            </div>

            <div class="ocf-grid">
                <div class="ocf-field">
                    <label for="first_name">Prénom <span class="ocf-required">*</span></label>
                    <input id="first_name" name="first_name" value="{{ old('first_name', !empty($prospect) ? collect(preg_split('/\s+/', trim((string)$prospect->contact_name)))->first() : '') }}" autocomplete="given-name" placeholder="Ex. Marcel" required>
                    @error('first_name')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="last_name">Nom <span class="ocf-required">*</span></label>
                    <input id="last_name" name="last_name" value="{{ old('last_name', !empty($prospect) ? collect(preg_split('/\s+/', trim((string)$prospect->contact_name)))->skip(1)->implode(' ') : '') }}" autocomplete="family-name" placeholder="Ex. Mobio" required>
                    @error('last_name')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="email">Adresse e-mail <span class="ocf-required">*</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="vendeur@exemple.com" required>
                    @error('email')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="phone">Téléphone <span class="ocf-required">*</span></label>
                    <div class="ocf-phone">
                        <select name="phone_country" aria-label="Indicatif téléphonique" required>
                            <option value="+225" selected>CI +225</option>
                        </select>
                        <input id="phone" name="phone" inputmode="numeric" autocomplete="tel" value="{{ old('phone', $prospectPhone ?? '') }}" placeholder="07 00 00 00 00" required>
                    </div>
                    @error('phone')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="password">Mot de passe temporaire <span class="ocf-required">*</span></label>
                    <div class="ocf-password">
                        <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="8 caractères minimum" required>
                        <button type="button" data-toggle-password="password" aria-label="Afficher le mot de passe"><i data-lucide="eye"></i></button>
                    </div>
                    <span class="ocf-help">Le vendeur pourra l’utiliser pour sa première connexion.</span>
                    @error('password')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="password_confirmation">Confirmation <span class="ocf-required">*</span></label>
                    <div class="ocf-password">
                        <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Répétez le mot de passe" required>
                        <button type="button" data-toggle-password="password_confirmation" aria-label="Afficher la confirmation"><i data-lucide="eye"></i></button>
                    </div>
                    <span class="ocf-help" id="passwordMatch">Les deux mots de passe doivent être identiques.</span>
                </div>

                <div class="ocf-field ocf-span-2">
                    <label for="selfie">Photo du responsable <span class="ocf-required">*</span></label>
                    <div class="ocf-file">
                        <input id="selfie" type="file" name="selfie" accept="image/jpeg,image/png,image/webp" capture="user" required>
                    </div>
                    <span class="ocf-help">JPG, PNG ou WEBP, 5 Mo maximum. Sur téléphone, la caméra avant peut s’ouvrir directement.</span>
                    @error('selfie')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="ocf-panel" data-step-panel="2" aria-labelledby="vendorStep2Title">
            <div class="ocf-panel-intro">
                <div>
                    <h3 id="vendorStep2Title">Boutique et activité</h3>
                    <p>Décrivez l’activité commerciale de la boutique. Les informations d’entreprise apparaissent uniquement lorsque le type « Entreprise / société » est sélectionné.</p>
                </div>
                <span class="ocf-panel-tag">Étape 2 sur 4</span>
            </div>

            <div class="ocf-grid">
                <div class="ocf-field">
                    <label for="sellerType">Type de vendeur <span class="ocf-required">*</span></label>
                    <select id="sellerType" name="sellerType" required>
                        <option value="particulier" @selected(old('sellerType', 'particulier') === 'particulier')>Particulier</option>
                        <option value="artisan" @selected(old('sellerType') === 'artisan')>Artisan / indépendant</option>
                        <option value="grossiste" @selected(old('sellerType') === 'grossiste')>Grossiste / distributeur</option>
                        <option value="entreprise" @selected(old('sellerType') === 'entreprise')>Entreprise / société</option>
                    </select>
                    @error('sellerType')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="shopName">Nom de la boutique <span class="ocf-required">*</span></label>
                    <input id="shopName" name="shopName" value="{{ old('shopName', $prospect->business_name ?? '') }}" placeholder="Ex. Quincaillerie Marcel" required>
                    @error('shopName')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label>Catégorie principale <span class="ocf-required">*</span></label>
                    <div class="ocf-category" id="categoryPicker">
                        <input type="hidden" name="main_category" id="mainCategory" value="{{ old('main_category') }}">
                        <button type="button" class="ocf-select-button" id="categoryButton" aria-expanded="false">
                            {{ optional($categories->firstWhere('slug', old('main_category')))->name ?? 'Sélectionner une catégorie' }}
                        </button>
                        <div class="ocf-category-menu ocf-hidden" id="categoryMenu">
                            <div class="ocf-field">
                                <input type="search" id="categorySearch" placeholder="Rechercher une catégorie…" autocomplete="off">
                            </div>
                            <div class="ocf-category-options">
                                @forelse($categories as $category)
                                    <button type="button" class="ocf-category-option" data-value="{{ $category->slug }}">{{ $category->name }}</button>
                                @empty
                                    <button type="button" class="ocf-category-option" data-value="materiaux-gros-oeuvre">Matériaux gros œuvre</button>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <span class="ocf-error ocf-hidden" id="categoryError">Sélectionnez une catégorie principale.</span>
                    @error('main_category')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="description">Description de l’activité <span class="ocf-optional">facultatif</span></label>
                    <textarea id="description" name="description" maxlength="700" placeholder="Ex. Vente de ciment, fer à béton, outillage et équipements de chantier.">{{ old('description') }}</textarea>
                    @error('description')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div id="companyFields" class="ocf-subpanel {{ old('sellerType') === 'entreprise' ? '' : 'ocf-hidden' }}">
                    <div class="ocf-subpanel__head">
                        <h4>Informations de l’entreprise</h4>
                        <span>Demandées uniquement pour une société</span>
                    </div>
                    <div class="ocf-grid">
                        <div class="ocf-field">
                            <label for="companyName">Raison sociale <span class="ocf-required">*</span></label>
                            <input id="companyName" name="companyName" value="{{ old('companyName') }}" data-company-required>
                            @error('companyName')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="legalForm">Forme juridique <span class="ocf-required">*</span></label>
                            <select id="legalForm" name="legalForm" data-company-required>
                                <option value="">Sélectionner</option>
                                @foreach(['sarl'=>'SARL','sarlu'=>'SARLU','sa'=>'SA','sas'=>'SAS','ei'=>'Entreprise individuelle','cooperative'=>'Coopérative','autre'=>'Autre'] as $value=>$label)
                                    <option value="{{ $value }}" @selected(old('legalForm') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('legalForm')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="rccm">Numéro RCCM <span class="ocf-required">*</span></label>
                            <input id="rccm" name="rccm" value="{{ old('rccm') }}" data-company-required>
                            @error('rccm')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="taxpayerNumber">Numéro contribuable <span class="ocf-required">*</span></label>
                            <input id="taxpayerNumber" name="taxpayerNumber" value="{{ old('taxpayerNumber') }}" data-company-required>
                            @error('taxpayerNumber')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="rccmFile">Document RCCM <span class="ocf-required">*</span></label>
                            <div class="ocf-file"><input id="rccmFile" type="file" name="rccmFile" accept=".pdf,.jpg,.jpeg,.png" data-company-required></div>
                            @error('rccmFile')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="taxFile">Document fiscal <span class="ocf-required">*</span></label>
                            <div class="ocf-file"><input id="taxFile" type="file" name="taxFile" accept=".pdf,.jpg,.jpeg,.png" data-company-required></div>
                            @error('taxFile')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ocf-panel" data-step-panel="3" aria-labelledby="vendorStep3Title">
            <div class="ocf-panel-intro">
                <div>
                    <h3 id="vendorStep3Title">Localisation et logistique</h3>
                    <p>La commune et le quartier décrivent la zone. Pour OVANIE Logistics, la latitude et la longitude indiquent le point réel d’enlèvement.</p>
                </div>
                <span class="ocf-panel-tag">Étape 3 sur 4</span>
            </div>

            <div class="ocf-grid">
                <div class="ocf-field">
                    <label for="region">Région <span class="ocf-required">*</span></label>
                    <input id="region" name="region" value="{{ old('region', 'Abidjan') }}" required>
                    @error('region')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="city">Ville <span class="ocf-required">*</span></label>
                    <input id="city" name="city" value="{{ old('city', 'Abidjan') }}" required>
                    @error('city')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="commune">Commune <span class="ocf-required">*</span></label>
                    <input id="commune" name="commune" value="{{ old('commune') }}" placeholder="Ex. Yopougon" required>
                    @error('commune')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="district">Quartier <span class="ocf-required">*</span></label>
                    <input id="district" name="district" value="{{ old('district') }}" placeholder="Ex. Niangon" required>
                    @error('district')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="landmark">Point de repère <span class="ocf-optional">facultatif</span></label>
                    <input id="landmark" name="landmark" value="{{ old('landmark', $prospect->landmark ?? '') }}" placeholder="Ex. en face du marché">
                    @error('landmark')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="address">Adresse complète <span class="ocf-optional">facultatif</span></label>
                    <input id="address" name="address" value="{{ old('address', $prospect->address ?? '') }}" placeholder="Rue, lot, îlot ou précision utile">
                    @error('address')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="delivery_zone">Zone commerciale <span class="ocf-required">*</span></label>
                    <select id="delivery_zone" name="delivery_zone" required>
                        @foreach(['abidjan'=>'Abidjan','grand_abidjan'=>'Grand Abidjan','national'=>'Toute la Côte d’Ivoire','afrique_ouest'=>'Afrique de l’Ouest'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('delivery_zone', 'abidjan') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('delivery_zone')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field">
                    <label for="whatsapp">WhatsApp professionnel <span class="ocf-optional">facultatif</span></label>
                    <input id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $prospectWhatsapp ?? '') }}" inputmode="tel" placeholder="07 00 00 00 00">
                    @error('whatsapp')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
                <div class="ocf-field ocf-span-2">
                    <label for="business_email">E-mail professionnel <span class="ocf-optional">facultatif</span></label>
                    <input id="business_email" type="email" name="business_email" value="{{ old('business_email') }}" placeholder="boutique@exemple.com">
                    @error('business_email')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <fieldset class="ocf-fieldset ocf-span-2" style="border:0;padding:0;margin:0">
                    <legend>Mode logistique <span class="ocf-required">*</span></legend>
                    <div class="ocf-choice-grid">
                        <label class="ocf-choice">
                            <input type="radio" name="logistics_type" value="ovanie" @checked(old('logistics_type', 'ovanie') === 'ovanie') required>
                            <span><strong>OVANIE Logistics</strong><small>OVANIE organise la collecte et la livraison. La position GPS doit être confirmée avant publication.</small></span>
                        </label>
                        <label class="ocf-choice">
                            <input type="radio" name="logistics_type" value="seller" @checked(old('logistics_type') === 'seller') required>
                            <span><strong>Logistique vendeur</strong><small>Le vendeur configurera ensuite ses zones, tarifs, délais et capacités de livraison.</small></span>
                        </label>
                    </div>
                    @error('logistics_type')<span class="ocf-error">{{ $message }}</span>@enderror
                </fieldset>

                @include('commercial.vendors.partials.location-picker', ['shop' => null, 'showMode' => true])
            </div>
        </section>

        <section class="ocf-panel" data-step-panel="4" aria-labelledby="vendorStep4Title">
            <div class="ocf-panel-intro">
                <div>
                    <h3 id="vendorStep4Title">Identité et reversement</h3>
                    <p>Ajoutez les justificatifs du vendeur et les coordonnées Mobile Money destinées aux reversements.</p>
                </div>
                <span class="ocf-panel-tag">Étape 4 sur 4</span>
            </div>

            <div class="ocf-grid">
                <input type="hidden" name="identityCountry" value="ci">

                <div class="ocf-field">
                    <label for="identityType">Type de pièce <span class="ocf-required">*</span></label>
                    <select id="identityType" name="identityType" required>
                        @foreach(['cni'=>'Carte nationale d’identité','passport'=>'Passeport','permis'=>'Permis de conduire','resident'=>'Carte de résident'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('identityType', 'cni') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('identityType')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="identityNumber">Numéro de la pièce <span class="ocf-required">*</span></label>
                    <input id="identityNumber" name="identityNumber" value="{{ old('identityNumber') }}" autocomplete="off" placeholder="Numéro indiqué sur la pièce" required>
                    @error('identityNumber')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <fieldset class="ocf-fieldset ocf-span-2" style="border:0;padding:0;margin:0">
                    <legend>Format du document <span class="ocf-required">*</span></legend>
                    <div class="ocf-choice-grid">
                        <label class="ocf-choice">
                            <input type="radio" name="identityUploadMode" value="pdf" required @checked(old('identityUploadMode', 'pdf') === 'pdf')>
                            <span><strong>Document PDF</strong><small>Importer un document PDF contenant la pièce.</small></span>
                        </label>
                        <label class="ocf-choice">
                            <input type="radio" name="identityUploadMode" value="scan" required @checked(old('identityUploadMode') === 'scan')>
                            <span><strong>Photos recto et verso</strong><small>Photographier directement la pièce avec le téléphone.</small></span>
                        </label>
                    </div>
                </fieldset>

                <div id="identityPdf" class="ocf-field ocf-span-2">
                    <label for="identityFile">Pièce d’identité PDF <span class="ocf-required">*</span></label>
                    <div class="ocf-file"><input id="identityFile" type="file" name="identityFile" accept="application/pdf"></div>
                    @error('identityFile')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div id="identityScan" class="ocf-subpanel ocf-hidden">
                    <div class="ocf-subpanel__head"><h4>Photographies de la pièce</h4><span>JPG, PNG ou WEBP</span></div>
                    <div class="ocf-grid">
                        <div class="ocf-field">
                            <label for="identityFileFront">Recto <span class="ocf-required">*</span></label>
                            <div class="ocf-file"><input id="identityFileFront" type="file" name="identityFileFront" accept="image/jpeg,image/png,image/webp" capture="environment"></div>
                            @error('identityFileFront')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="ocf-field">
                            <label for="identityFileBack">Verso <span class="ocf-required">*</span></label>
                            <div class="ocf-file"><input id="identityFileBack" type="file" name="identityFileBack" accept="image/jpeg,image/png,image/webp" capture="environment"></div>
                            @error('identityFileBack')<span class="ocf-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="ocf-field">
                    <label for="payment_mode">Calendrier de reversement <span class="ocf-required">*</span></label>
                    <select id="payment_mode" name="payment_mode" required>
                        <option value="post_delivery" @selected(old('payment_mode', 'post_delivery') === 'post_delivery')>Après livraison</option>
                        <option value="weekly" @selected(old('payment_mode') === 'weekly')>Hebdomadaire</option>
                    </select>
                    @error('payment_mode')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="mmOperator">Opérateur Mobile Money <span class="ocf-required">*</span></label>
                    <select id="mmOperator" name="mmOperator" required>
                        @foreach(['orange'=>'Orange Money','mtn'=>'MTN MoMo','wave'=>'Wave','moov'=>'Moov Money'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('mmOperator', 'orange') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('mmOperator')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="mmNumber">Numéro de réception <span class="ocf-required">*</span></label>
                    <input id="mmNumber" name="mmNumber" value="{{ old('mmNumber') }}" inputmode="tel" placeholder="07 00 00 00 00" required>
                    @error('mmNumber')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>

                <div class="ocf-field">
                    <label for="mmHolder">Nom du titulaire <span class="ocf-required">*</span></label>
                    <input id="mmHolder" name="mmHolder" value="{{ old('mmHolder') }}" placeholder="Nom figurant sur le compte Mobile Money" required>
                    @error('mmHolder')<span class="ocf-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <footer class="ocf-wizard-footer">
            <span class="ocf-wizard-footer__status" id="wizardStatus">Étape 1 sur 4</span>
            <div class="ocf-wizard-footer__buttons">
                <button class="btn" type="button" id="previousStep"><i data-lucide="arrow-left"></i>Précédent</button>
                <button class="btn btn-orange" type="button" id="nextStep">Continuer<i data-lucide="arrow-right"></i></button>
                <button class="btn btn-orange ocf-hidden" type="submit" id="submitVendor"><i data-lucide="store"></i>Créer le vendeur et la boutique</button>
            </div>
        </footer>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js"></script>
<script src="{{ asset('js/commercial-shop-location.js') }}"></script>
<script>
(() => {
    const form = document.getElementById('vendorCreateForm');
    if (!form) return;

    const panels = [...form.querySelectorAll('[data-step-panel]')];
    const stepButtons = [...form.querySelectorAll('[data-step-button]')];
    const previousButton = document.getElementById('previousStep');
    const nextButton = document.getElementById('nextStep');
    const submitButton = document.getElementById('submitVendor');
    const status = document.getElementById('wizardStatus');
    const totalSteps = panels.length;
    let currentStep = Math.min(totalSteps, Math.max(1, Number(form.dataset.initialStep || 1)));

    function fieldsInStep(step) {
        return [...form.querySelectorAll(`[data-step-panel="${step}"] input, [data-step-panel="${step}"] select, [data-step-panel="${step}"] textarea`)]
            .filter(field => !field.disabled && field.type !== 'hidden');
    }

    function validateCategory() {
        const category = document.getElementById('mainCategory');
        const error = document.getElementById('categoryError');
        const valid = Boolean(category?.value);
        error?.classList.toggle('ocf-hidden', valid);
        return valid;
    }

    function validateStep(step) {
        let valid = true;
        fieldsInStep(step).forEach(field => {
            if (!field.checkValidity()) {
                valid = false;
                field.reportValidity();
            }
        });
        if (step === 2 && !validateCategory()) {
            valid = false;
            document.getElementById('categoryButton')?.focus();
        }
        if (step === 3) {
            const logistics = form.querySelector('input[name="logistics_type"]:checked')?.value;
            const locationMode = form.querySelector('input[name="location_mode"]:checked')?.value;
            const latitude = form.querySelector('[data-location-latitude]')?.value;
            const longitude = form.querySelector('[data-location-longitude]')?.value;
            const confirmed = form.querySelector('[data-location-confirmed]')?.value;
            if (logistics === 'ovanie' && locationMode === 'gps_now' && (!latitude || !longitude || confirmed !== '1')) {
                valid = false;
                const locationStatus = form.querySelector('[data-location-status]');
                if (locationStatus) {
                    locationStatus.textContent = 'Capturez la position exacte ou choisissez de la confirmer plus tard.';
                    locationStatus.dataset.tone = 'danger';
                }
                form.querySelector('[data-shop-location-picker]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        return valid;
    }

    function showStep(step, scroll = true) {
        currentStep = Math.min(totalSteps, Math.max(1, step));
        panels.forEach(panel => panel.classList.toggle('is-active', Number(panel.dataset.stepPanel) === currentStep));
        stepButtons.forEach(button => {
            const number = Number(button.dataset.stepButton);
            button.classList.toggle('is-active', number === currentStep);
            button.classList.toggle('is-complete', number < currentStep);
            button.setAttribute('aria-current', number === currentStep ? 'step' : 'false');
        });
        previousButton.classList.toggle('ocf-hidden', currentStep === 1);
        nextButton.classList.toggle('ocf-hidden', currentStep === totalSteps);
        submitButton.classList.toggle('ocf-hidden', currentStep !== totalSteps);
        status.textContent = `Étape ${currentStep} sur ${totalSteps}`;
        if (scroll) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.lucide?.createIcons();
    }

    nextButton.addEventListener('click', () => {
        if (validateStep(currentStep)) showStep(currentStep + 1);
    });
    previousButton.addEventListener('click', () => showStep(currentStep - 1));
    stepButtons.forEach(button => button.addEventListener('click', () => {
        const target = Number(button.dataset.stepButton);
        if (target <= currentStep || validateStep(currentStep)) showStep(target);
    }));

    const phone = document.getElementById('phone');
    phone?.addEventListener('input', () => {
        const digits = phone.value.replace(/\D/g, '').slice(0, 10);
        phone.value = digits.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
    });
    const mmNumber = document.getElementById('mmNumber');
    mmNumber?.addEventListener('input', () => {
        const prefix = mmNumber.value.trim().startsWith('+') ? '+' : '';
        const digits = mmNumber.value.replace(/\D/g, '').slice(0, 15);
        mmNumber.value = prefix + digits;
    });

    const password = document.getElementById('password');
    const confirmation = document.getElementById('password_confirmation');
    const match = document.getElementById('passwordMatch');
    function checkPassword() {
        const different = Boolean(confirmation?.value) && password?.value !== confirmation.value;
        confirmation?.setCustomValidity(different ? 'Les mots de passe ne correspondent pas.' : '');
        if (match) {
            match.textContent = different ? 'Les mots de passe ne correspondent pas.' : 'Les deux mots de passe doivent être identiques.';
            match.style.color = different ? '#be123c' : '';
        }
    }
    password?.addEventListener('input', checkPassword);
    confirmation?.addEventListener('input', checkPassword);

    form.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.togglePassword);
        if (!field) return;
        field.type = field.type === 'password' ? 'text' : 'password';
        button.innerHTML = field.type === 'password' ? '<i data-lucide="eye"></i>' : '<i data-lucide="eye-off"></i>';
        window.lucide?.createIcons();
    }));

    const sellerType = document.getElementById('sellerType');
    const companyFields = document.getElementById('companyFields');
    function syncCompany() {
        const active = sellerType?.value === 'entreprise';
        companyFields?.classList.toggle('ocf-hidden', !active);
        companyFields?.querySelectorAll('[data-company-required]').forEach(field => {
            field.disabled = !active;
            field.required = active;
        });
    }
    sellerType?.addEventListener('change', syncCompany);
    syncCompany();

    const identityPdf = document.getElementById('identityPdf');
    const identityScan = document.getElementById('identityScan');
    function syncIdentity() {
        const mode = form.querySelector('input[name="identityUploadMode"]:checked')?.value || 'pdf';
        identityPdf?.classList.toggle('ocf-hidden', mode !== 'pdf');
        identityScan?.classList.toggle('ocf-hidden', mode !== 'scan');
        identityPdf?.querySelectorAll('input').forEach(field => {
            field.disabled = mode !== 'pdf';
            field.required = mode === 'pdf';
        });
        identityScan?.querySelectorAll('input').forEach(field => {
            field.disabled = mode !== 'scan';
            field.required = mode === 'scan';
        });
    }
    form.querySelectorAll('input[name="identityUploadMode"]').forEach(input => input.addEventListener('change', syncIdentity));
    syncIdentity();

    const categoryPicker = document.getElementById('categoryPicker');
    const categoryButton = document.getElementById('categoryButton');
    const categoryMenu = document.getElementById('categoryMenu');
    const categorySearch = document.getElementById('categorySearch');
    const categoryInput = document.getElementById('mainCategory');
    const categoryOptions = [...document.querySelectorAll('.ocf-category-option')];
    function closeCategories() {
        categoryMenu?.classList.add('ocf-hidden');
        categoryButton?.setAttribute('aria-expanded', 'false');
    }
    categoryButton?.addEventListener('click', () => {
        const opening = categoryMenu?.classList.contains('ocf-hidden');
        categoryMenu?.classList.toggle('ocf-hidden');
        categoryButton.setAttribute('aria-expanded', String(opening));
        if (opening) {
            categorySearch.value = '';
            categoryOptions.forEach(option => option.hidden = false);
            categorySearch.focus();
        }
    });
    categorySearch?.addEventListener('input', () => {
        const query = categorySearch.value.trim().toLocaleLowerCase('fr');
        categoryOptions.forEach(option => option.hidden = !option.textContent.toLocaleLowerCase('fr').includes(query));
    });
    categoryOptions.forEach(option => option.addEventListener('click', () => {
        categoryInput.value = option.dataset.value;
        categoryButton.textContent = option.textContent.trim();
        document.getElementById('categoryError')?.classList.add('ocf-hidden');
        closeCategories();
    }));
    document.addEventListener('click', event => {
        if (categoryPicker && !categoryPicker.contains(event.target)) closeCategories();
    });

    form.addEventListener('submit', event => {
        checkPassword();
        for (let step = 1; step <= totalSteps; step++) {
            if (!validateStep(step)) {
                event.preventDefault();
                showStep(step);
                return;
            }
        }
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="location-spinner" aria-hidden="true"></span> Création en cours…';
    });

    showStep(currentStep, false);
})();
</script>
@endpush
