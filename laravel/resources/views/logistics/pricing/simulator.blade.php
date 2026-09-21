@extends('layouts.logistics')
@section('title','Simulateur logistique')
@section('crumb','Tarification > Simulateur logistique')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260911' }}">@endpush

@section('content')
<main class="pricing-page pricing-simulator-page">
    <div class="pricing-titlebar"><div><h1>Simulateur logistique</h1><p>Testez le tarif réel d’une livraison : le poids/volume choisit le véhicule, puis OVANIE lit le prix du trajet entre les communes et ajoute les suppléments applicables.</p></div></div>

    <section class="pricing-kpis pricing-simulator-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="list"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Simulations du jour</span><div class="pricing-kpi-value-row"><strong>{{ $simulationsToday }}</strong></div><p>calculs enregistrés</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="truck"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules actifs</span><div class="pricing-kpi-value-row"><strong>{{ $configuredRateCount }}</strong></div><p>capacités disponibles</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="map"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Tarifs de trajets actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeMatrixCount }}</strong></div><p>relations commune → commune</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="plus"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Suppléments automatiques</span><div class="pricing-kpi-value-row"><strong>{{ $activeSupplementCount }}</strong></div><p>règles conditionnelles actives</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    @if($errors->any())<div class="pricing-empty-state" style="margin-bottom:14px"><strong>Simulation impossible</strong><span>{{ $errors->first() }}</span></div>@endif

    <section class="pricing-simulator-layout">
        <article class="sim-card sim-parameters-card">
            <header class="sim-card-header"><div><h2><x-operations.icon name="truck"/> Paramètres de simulation</h2><p>Sélectionnez la commune de départ et la commune d’arrivée. Le même choix est autorisé pour une livraison intra-commune.</p></div></header>
            <form class="sim-form" method="POST" action="{{ route('logistics.ovanie-pricing.calculate') }}">@csrf
                <div class="sim-form-grid sim-form-grid-route">
                    <div class="sim-field"><label>Départ <sup>*</sup></label><div class="sim-input-wrap"><x-operations.icon name="pin"/><select name="origin_commune_id" required style="width:100%;border:0;background:transparent;outline:0"><option value="">Commune de départ</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((string)old('origin_commune_id',(string)data_get($result,'origin_commune_id'))===(string)$commune->id)>{{ $commune->name }}</option>@endforeach</select></div></div>
                    <div class="sim-swap"><x-operations.icon name="swap"/></div>
                    <div class="sim-field"><label>Destination <sup>*</sup></label><div class="sim-input-wrap"><x-operations.icon name="pin"/><select name="destination_commune_id" required style="width:100%;border:0;background:transparent;outline:0"><option value="">Commune de destination</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((string)old('destination_commune_id',(string)data_get($result,'destination_commune_id'))===(string)$commune->id)>{{ $commune->name }}</option>@endforeach</select></div></div>
                </div>
                <div class="sim-form-grid sim-form-grid-pair">
                    <div class="sim-field"><label>Poids (kg) <sup>*</sup></label><div class="sim-input-wrap"><x-operations.icon name="weight"/><input type="number" min="0" name="weight_kg" step="0.1" value="{{ old('weight_kg',data_get($result,'weight_kg')) }}" required><span class="unit">kg</span></div></div>
                    <div class="sim-field"><label>Volume (m³) <sup>*</sup></label><div class="sim-input-wrap"><x-operations.icon name="box"/><input type="number" min="0" name="volume_m3" step="0.01" value="{{ old('volume_m3',data_get($result,'volume_m3')) }}" required><span class="unit">m³</span></div></div>
                </div>
                <div class="sim-form-grid sim-form-grid-pair">
                    <div class="sim-field"><label>Fragile</label><div class="sim-toggle"><label><input type="radio" name="fragile" value="0" @checked(!old('fragile',data_get($result,'fragile',false)))>Non</label><label><input type="radio" name="fragile" value="1" @checked(old('fragile',data_get($result,'fragile',false)))>Oui</label></div></div>
                    <div class="sim-field"><label>Manutention</label><div class="sim-toggle"><label><input type="radio" name="handling" value="0" @checked(!old('handling',data_get($result,'handling',false)))>Non</label><label><input type="radio" name="handling" value="1" @checked(old('handling',data_get($result,'handling',false)))>Oui</label></div></div>
                </div>
                <div class="sim-form-grid sim-form-grid-pair sim-form-grid-last">
                    <div class="sim-field"><label>Déchargement requis</label><div class="sim-toggle"><label><input type="radio" name="unloading" value="0" @checked(!old('unloading',data_get($result,'unloading',false)))>Non</label><label><input type="radio" name="unloading" value="1" @checked(old('unloading',data_get($result,'unloading',false)))>Oui</label></div></div>
                    <div class="sim-field"><label>Urgence</label><div class="sim-toggle three">@foreach(['standard'=>'Standard','express'=>'Express','priority'=>'Prioritaire'] as $value=>$label)<label><input type="radio" name="urgency" value="{{ $value }}" @checked(old('urgency',data_get($result,'urgency','standard'))===$value)>{{ $label }}</label>@endforeach</div></div>
                </div>
                <button class="sim-calc-btn" type="submit" @disabled($communes->count()<1)><x-operations.icon name="list"/><span>Calculer</span><x-operations.icon name="next"/></button>
                @if($communes->count()<1)<p class="sim-disclaimer">Au moins une commune doit être reliée à une zone de livraison active.</p>@endif
            </form>
        </article>

        <article class="sim-card sim-result-card">
            <header class="sim-card-header sim-result-header"><div><h2><x-operations.icon name="check-circle"/> Résultat de la simulation</h2><p>Tarif fixe du trajet pour le véhicule recommandé, puis suppléments dont les conditions sont remplies.</p></div></header>
            <div class="sim-result-body">
            @if($result)
                <div class="sim-recommended"><img src="{{ asset($result['vehicle_image']) }}" alt="{{ $result['vehicle_label'] }}"><div class="sim-recommended-copy"><small>Véhicule recommandé</small><strong>{{ $result['vehicle_label'] }}</strong><span>{{ $result['origin'] }} → {{ $result['destination'] }}</span></div><span class="sim-reco-pill">Recommandé</span></div>
                <div class="sim-summary-kpis">
                    <div class="sim-summary-kpi"><x-operations.icon name="wallet"/><div><small>Prix calculé</small><strong>{{ number_format($result['price_total'],0,',',' ') }} FCFA</strong></div></div>
                    <div class="sim-summary-kpi"><x-operations.icon name="route"/><div><small>Distance routière</small><strong>{{ $result['distance_km']!==null ? $result['distance_km'].' km' : 'Non disponible' }}</strong></div></div>
                    <div class="sim-summary-kpi"><x-operations.icon name="clock"/><div><small>Temps estimé</small><strong>{{ $result['duration'] }}</strong></div></div>
                </div>
                <div class="sim-price-title">Détail du prix</div>@foreach($result['price_lines'] as $line)<div class="sim-price-line"><span>{{ $line['label'] }}</span><strong>{{ number_format($line['value'],0,',',' ') }} FCFA</strong></div>@endforeach
                <div class="sim-price-total"><span>Total estimé</span><strong>{{ number_format($result['price_total'],0,',',' ') }} FCFA</strong></div>
            @else
                <div class="pricing-empty-state"><strong>Aucune simulation lancée</strong><span>Renseignez les paramètres de l’expédition pour lancer le calcul.</span></div>
            @endif
            </div>
        </article>

        <div class="pricing-right-stack sim-right-column">
            <article class="sim-card sim-route-card"><header class="sim-card-header"><div><h2><x-operations.icon name="map"/> Itinéraire estimé</h2></div></header><div class="sim-map-wrap">
                @if($result && !empty($result['route_points']))<div class="sim-map" data-pricing-route-map data-points='@json($result["route_points"])'></div><div class="sim-map-route-info"><div class="sim-map-stops"><div class="sim-map-stop"><i></i>{{ $result['origin'] }}</div><div class="sim-map-stop red"><i></i>{{ $result['destination'] }}</div></div><div class="sim-map-stats"><span><x-operations.icon name="route"/> {{ $result['distance_km'] }} km</span><span><x-operations.icon name="clock"/> {{ $result['duration'] }}</span></div></div>@else<div class="pricing-empty-state"><strong>Itinéraire non affiché</strong><span>La carte apparaît uniquement lorsqu’un moteur routier retourne une géométrie fiable.</span></div>@endif
            </div></article>
            <article class="sim-card sim-alternatives-card"><header class="sim-card-header"><div><h2><x-operations.icon name="truck"/> Autres véhicules compatibles</h2><p>Comparez les autres véhicules compatibles avec l’expédition.</p></div></header><div class="sim-alternatives">@if($result && count($result['alternatives']))<table class="sim-alt-table"><thead><tr><th>Véhicule</th><th>Capacité</th><th>Prix estimé</th></tr></thead><tbody>@foreach($result['alternatives'] as $alt)<tr><td><img src="{{ asset($alt['image']) }}" alt=""><b>{{ $alt['label'] }}</b></td><td>{{ $alt['capacity'] }}</td><td>{{ number_format($alt['price'],0,',',' ') }} FCFA</td></tr>@endforeach</tbody></table>@else<div class="pricing-empty-state"><strong>Aucune alternative tarifée</strong></div>@endif</div></article>
        </div>
    </section>
</main>
@endsection
@push('scripts')<script defer src="{{ asset('js/logistics-pricing.js') }}?v={{ @filemtime(public_path('js/logistics-pricing.js')) ?: '20260911' }}"></script>@endpush
