@extends('layouts.logistics')
@section('title','Ajouter un véhicule')
@section('crumb','Flotte › Ajouter un véhicule')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
$statusLabels=['available'=>'Disponible','maintenance'=>'Maintenance','out_of_service'=>'Hors service'];
@endphp
<main class="mg-page fleet-v2-page fleet-create-v2" data-fleet-create>
<form method="POST" action="{{ route('logistics.fleet.store') }}" enctype="multipart/form-data">
    @csrf
    <header class="mg-titlebar fleet-v2-titlebar">
        <div><h1>Ajouter un véhicule</h1><p>Enregistrez un véhicule réel de la flotte OVANIE, son chauffeur, sa capacité et ses documents de conformité.</p></div>
        <div class="mg-actions"><a class="mg-btn fleet-v2-btn" href="{{ route('logistics.fleet') }}">Annuler</a><button class="mg-btn mg-btn-primary fleet-v2-btn"><x-logistics.fleet-svg name="save"/>Enregistrer le véhicule</button></div>
    </header>

    @if($errors->any())
    <div class="fleet-v2-errors"><strong>Certains champs doivent être corrigés.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="fleet-create-v2-grid">
        <div class="fleet-create-v2-main">
            <article class="fleet-v2-card fleet-form-card">
                <header class="fleet-form-head"><span class="fleet-form-head-icon"><x-logistics.fleet-svg name="truck"/></span><div><h2>Informations du véhicule</h2><p>Identification, capacités et affectation opérationnelle.</p></div></header>
                <div class="fleet-form-body fleet-info-layout">
                    <div class="fleet-type-preview">
                        <div class="fleet-type-image"><img data-fleet-preview src="{{ asset($vehicleRules['Pickup']['image']) }}" alt="Aperçu du véhicule"></div>
                        <div><strong data-preview-title>Pickup</strong><span>Illustration de référence OVANIE</span></div>
                    </div>
                    <div class="fleet-form-grid">
                        <label>Type de véhicule <sup>*</sup>
                            <select name="vehicle_type" data-fleet-type required>
                                @foreach($vehicleRules as $type=>$rule)
                                <option value="{{ $type }}" data-capacity="{{ $rule['capacity'] }}" data-volume="{{ $rule['volume'] }}" data-image="{{ asset($rule['image']) }}" @selected(old('vehicle_type','Pickup')===$type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Immatriculation <sup>*</sup><input name="registration" value="{{ old('registration') }}" placeholder="Ex. PK-7789" required></label>
                        <label>Marque <sup>*</sup><input name="brand" value="{{ old('brand') }}" placeholder="Ex. Toyota" required></label>
                        <label>Modèle <sup>*</sup><input name="model" value="{{ old('model') }}" placeholder="Ex. Hilux" required></label>
                        <label>Capacité / charge maximale <sup>*</sup><span class="unit-input"><input name="capacity_kg" data-summary-capacity value="{{ old('capacity_kg',1000) }}" inputmode="numeric" required><b>kg</b></span></label>
                        <label>Volume utile <span class="unit-input"><input name="volume_m3" data-summary-volume value="{{ old('volume_m3',$vehicleRules['Pickup']['volume']) }}" inputmode="decimal" placeholder="Ex. 4,5"><b>m³</b></span></label>
                        <label>Année <sup>*</sup><input type="number" name="year" value="{{ old('year',now()->year) }}" min="1990" max="2100" required></label>
                        <label>Zone d’affectation
                            <select name="zone" data-summary-zone>
                                <option value="">Non affectée</option>
                                @foreach($zones as $zone)<option value="{{ $zone }}" @selected(old('zone')===$zone)>{{ $zone }}</option>@endforeach
                            </select>
                            @if($zones->isEmpty())<small class="fleet-field-help">Aucune zone de livraison active n’est encore configurée dans Territoire.</small>@endif
                        </label>
                        <label>Chauffeur assigné
                            <select name="driver_id" data-summary-driver><option value="">Aucun chauffeur</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected((string)old('driver_id')===(string)$driver->id)>{{ $driver->name }} — {{ $driver->zone ?: 'Zone non définie' }}</option>@endforeach</select>
                        </label>
                        <label>Statut initial <sup>*</sup>
                            <select name="status" data-summary-status required>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(old('status','available')===$value)>{{ $label }}</option>@endforeach</select>
                        </label>
                        <label>Kilométrage actuel <span class="unit-input"><input name="mileage_km" value="{{ old('mileage_km',0) }}" inputmode="numeric"><b>km</b></span></label>
                        <label class="fleet-field-wide">Observations<textarea name="observations" maxlength="500" placeholder="Informations complémentaires sur l’état ou l’usage du véhicule...">{{ old('observations') }}</textarea></label>
                    </div>
                </div>
            </article>

            <article class="fleet-v2-card fleet-form-card">
                <header class="fleet-form-head"><span class="fleet-form-head-icon"><x-logistics.fleet-svg name="document-check"/></span><div><h2>Documents & conformité</h2><p>Ajoutez les documents réels qui seront vérifiés par l’équipe logistique.</p></div></header>
                <div class="fleet-doc-grid">
                    @foreach([
                        ['registration_document','Carte grise','file',true,'PDF, JPG ou PNG'],
                        ['insurance_document','Assurance','shield',true,'PDF, JPG ou PNG'],
                        ['technical_inspection','Visite technique','inspection',true,'PDF, JPG ou PNG'],
                        ['vehicle_photo','Photo du véhicule','image',false,'JPG, PNG ou WEBP'],
                    ] as [$name,$label,$icon,$required,$hint])
                    <label class="fleet-upload-card">
                        <span class="fleet-upload-top"><x-logistics.fleet-svg :name="$icon" :size="24"/><strong>{{ $label }}@if($required)<sup>*</sup>@endif</strong></span>
                        <input type="file" name="{{ $name }}" data-fleet-file accept="{{ $name==='vehicle_photo'?'image/jpeg,image/png,image/webp':'.pdf,image/jpeg,image/png' }}" @required($required)>
                        <span class="fleet-upload-action"><x-logistics.fleet-svg name="upload" :size="18"/>Choisir un fichier</span>
                        <small data-file-name>{{ $hint }}</small>
                    </label>
                    @endforeach
                </div>
            </article>

            <article class="fleet-v2-card fleet-form-card">
                <header class="fleet-form-head"><span class="fleet-form-head-icon"><x-logistics.fleet-svg name="gauge"/></span><div><h2>Caractéristiques opérationnelles</h2><p>Paramètres métier utilisés pour les affectations et les tournées.</p></div></header>
                <div class="fleet-feature-grid">
                    @foreach([
                        ['refrigerated','snowflake','Véhicule réfrigéré','Transport sous température contrôlée'],
                        ['tail_lift','package','Hayon / manutention','Aide au chargement et déchargement'],
                        ['btp_heavy','truck','Transport BTP / charges lourdes','Adapté aux matériaux et charges lourdes'],
                        ['tour_ready','route','Disponible pour tournées','Peut être proposé lors de la création d’une tournée'],
                        ['returns_compatible','rotate','Compatible retours','Peut prendre en charge les missions de retour'],
                    ] as [$name,$icon,$label,$help])
                    <label class="fleet-feature"><span class="fleet-feature-icon"><x-logistics.fleet-svg :name="$icon"/></span><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, in_array($name,['btp_heavy','tour_ready','returns_compatible'],true)))></label>
                    @endforeach
                </div>
            </article>
        </div>

        <aside class="fleet-create-v2-side">
            <article class="fleet-v2-card fleet-summary-card">
                <header><div><h3>Résumé</h3><p>Aperçu des informations saisies.</p></div><span class="fleet-summary-state" data-summary-state>À compléter</span></header>
                <div class="fleet-summary-vehicle"><img data-summary-image src="{{ asset($vehicleRules['Pickup']['image']) }}" alt=""><div><strong data-summary-type>Pickup</strong><span data-summary-registration>Immatriculation non saisie</span></div></div>
                <dl>
                    <div><dt>Capacité</dt><dd data-summary-capacity-text>1 000 kg</dd></div>
                    <div><dt>Zone</dt><dd data-summary-zone-text>Non définie</dd></div>
                    <div><dt>Chauffeur</dt><dd data-summary-driver-text>Non affecté</dd></div>
                    <div><dt>Statut</dt><dd><span class="mg-status available" data-summary-status-text><i></i>Disponible</span></dd></div>
                    <div><dt>Documents obligatoires</dt><dd data-summary-documents>0/3</dd></div>
                </dl>
            </article>

            <article class="fleet-v2-card fleet-rules-card">
                <header><h3>Règles véhicules OVANIE</h3></header>
                <div class="fleet-rules-list">
                @foreach($vehicleRules as $type=>$rule)
                    <div><img src="{{ asset($rule['image']) }}" alt=""><span><strong>{{ $type }}</strong><small>≤ {{ number_format($rule['capacity'],0,',',' ') }} kg</small></span></div>
                @endforeach
                </div>
            </article>

            <article class="fleet-v2-card fleet-help-card">
                <header><h3>Aide de saisie</h3></header>
                <ol><li><span>1</span><div><strong>Vérifiez l’immatriculation</strong><small>Elle doit être valide et unique dans la flotte.</small></div></li><li><span>2</span><div><strong>Associez un chauffeur réel</strong><small>La liste vient des comptes livreurs actifs.</small></div></li><li><span>3</span><div><strong>Ajoutez les documents obligatoires</strong><small>Carte grise, assurance et visite technique.</small></div></li><li><span>4</span><div><strong>Définissez le bon statut</strong><small>Le statut conditionne les affectations possibles.</small></div></li></ol>
            </article>
        </aside>
    </section>
</form>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/logistics-management.js') }}?v={{ @filemtime(public_path('js/logistics-management.js')) ?: '1' }}"></script>
@endpush
