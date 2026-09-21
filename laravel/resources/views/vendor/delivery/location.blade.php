@extends('layouts.vendor')
@section('title', 'Informations logistiques | OVANIE')
@section('content')
@include('vendor.delivery.settings-style')
<div class="ls-page">
    <a href="{{ route('vendor.delivery.index') }}">Livraison & logistique</a>
    <h1>{{ $changing ? 'Passer à OVANIE Logistics' : 'Modifier mes informations logistiques' }}</h1>
    <p class="ls-muted">Vérifiez le point d’enlèvement de {{ $shop->name }} avant d’enregistrer.</p>
    @if($errors->any())<div class="ls-notice ls-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('vendor.delivery.mode.update') }}" class="ls-card" id="logistics-location-form"
        data-location-ready="{{ !$changing && \App\Services\ShopLogisticsSettingsService::hasReliableStoredLocation($shop) && !$errors->any() ? '1' : '0' }}"
        data-resolve-url="{{ route('vendor.delivery.location.resolve') }}"
        data-changing="{{ $changing ? '1' : '0' }}">
        @csrf
        <input type="hidden" name="logistics_type" value="ovanie">
        <input type="hidden" name="expected_logistics_type" value="{{ $shop->usesSellerLogistics() ? 'seller' : 'ovanie' }}">
        <p id="location-address-status" class="ls-notice">Adresse actuellement enregistrée. Ces informations ne constituent pas une nouvelle localisation. Lancez la capture depuis la boutique pour vérifier le point d’enlèvement.</p>
        <div class="ls-grid">
            @foreach(['address' => 'Adresse de la boutique', 'commune' => 'Commune', 'district' => 'Quartier', 'landmark' => 'Point de repère'] as $field => $label)
                <div class="ls-field"><label for="{{ $field }}">{{ $label }} *</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $shop->$field) }}" maxlength="{{ in_array($field, ['commune', 'district']) ? 100 : 255 }}" required></div>
            @endforeach
            @foreach(['latitude', 'longitude'] as $field)
                <input type="hidden" id="{{ $field }}" name="{{ $field }}" value="{{ $shop->$field }}">
            @endforeach
            <input type="hidden" id="geo_accuracy" name="geo_accuracy" value="">
            <input type="hidden" id="geo_source" name="geo_source" value="">
            <input type="hidden" id="geo_captured_at" name="geo_captured_at" value="">
            <input type="hidden" id="location_token" name="location_token" value="">
        </div>
        <div class="ls-actions"><button class="ls-button secondary" type="button" id="capture-shop-gps">Utiliser ma position GPS</button>
        </div>
        <p id="gps-feedback" role="status" class="ls-muted">Placez-vous au point d’enlèvement de la boutique, de préférence avec votre téléphone, puis lancez la localisation.</p>
        <section id="detected-location" class="ls-notice" hidden>
            <strong>Adresse détectée pour la position actuelle</strong>
            <p id="detected-location-address"></p>
            <a id="detected-location-map" target="_blank" rel="noopener noreferrer">Vérifier le point d’enlèvement sur la carte</a>
            <p>Si ce point n’est pas celui de votre boutique, ne le confirmez pas. Relancez la capture depuis la boutique.</p>
        </section>
        <label class="ls-check"><input type="checkbox" name="location_confirmed" value="1" required> Je confirme que l’adresse détectée et le point indiqué sur la carte correspondent à l’entrée de ma boutique.</label>
        @if($changing)
            <p class="ls-notice">Vos anciennes zones, tarifs et délais seront conservés mais désactivés. Les commandes existantes gardent leur mode logistique.</p>
            <input type="hidden" name="confirmed" value="1">
        @endif
        <div class="ls-actions"><button class="ls-button" type="submit">{{ $changing ? 'Activer OVANIE Logistics' : 'Enregistrer mes informations' }}</button><a class="ls-button secondary" href="{{ route('vendor.delivery.index') }}">Annuler</a></div>
    </form>
</div>
<script src="{{ asset('js/shop-logistics-location.js') }}?v={{ filemtime(public_path('js/shop-logistics-location.js')) }}" defer></script>
@endsection
