@extends('layouts.logistics')
@section('title','Véhicules & tarifs')
@section('crumb','Tarification > Véhicules & tarifs')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush

@section('content')
<main class="pricing-page pricing-vehicles-page">
    <div class="pricing-titlebar">
        <div><h1>Véhicules & tarifs</h1><p>Pour chaque véhicule : tarif de base, prix par km, par kg et par m³. Livraison = base + distance × prix/km + poids × prix/kg + volume × prix/m³ + frais éventuels.</p></div>
        <div class="pricing-title-actions"><a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.export',['type'=>'vehicles']) }}"><x-operations.icon name="download"/> Exporter</a><button type="button" class="pricing-btn pricing-btn-green" data-pricing-open="vehicle-rate-modal"><x-operations.icon name="settings"/> Configurer un véhicule</button></div>
    </div>

    <section class="pricing-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="truck"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules tarifés</span><div class="pricing-kpi-value-row"><strong>{{ $pricedVehicleCount }}/{{ count($vehicles) }}</strong></div><p>Utilisables pour la livraison OVANIE</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="settings"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeVehicleCount }}/{{ count($vehicles) }}</strong></div><p>Disponibles pour le choix automatique</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="route"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Formule</span><div class="pricing-kpi-value-row"><strong class="pricing-kpi-text-value">Base + km + kg + m³</strong></div><p>Sans minimum ni plafond</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="plus"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Frais éventuels actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeSupplementCount }}</strong></div><p>Ajoutés selon leurs conditions</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    @if(session('success'))<div class="pricing-feedback success" role="status"><x-operations.icon name="check-circle"/><div><strong>Configuration enregistrée</strong><span>{{ session('success') }}</span></div></div>@endif
    @if($errors->any())<div class="pricing-feedback error" role="alert"><x-operations.icon name="alert"/><div><strong>Enregistrement impossible</strong><span>{{ $errors->first() }}</span></div></div>@endif

    <section class="pricing-grid-2">
        <article class="pricing-card">
            <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="truck"/></span><div><h2>Tarifs et capacités</h2><p>Le poids et le volume choisissent le véhicule ; ses tarifs calculent le prix.</p></div></div></header>
            <div class="pricing-card-body">
                <div class="pricing-filters vehicle-capacity-filters"><label class="pricing-search"><x-operations.icon name="search"/><input data-table-search="#vehicle-rates-table" placeholder="Rechercher un véhicule..."></label></div>
                <div class="pricing-table-wrap"><table class="pricing-table" id="vehicle-rates-table">
                    <thead><tr><th>Véhicule</th><th>Base</th><th>/ km</th><th>/ kg</th><th>/ m³</th><th>Capacité</th><th>Statut</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($vehicles as $code => $v)
                        @php
                            $rate = $rates->firstWhere('vehicle_code', $code);
                            $catalog = $vehicleCatalog->firstWhere('code', $code);
                            $maxWeight = $catalog['max_weight_kg'] ?? $v['max_weight_kg'];
                            $maxVolume = $catalog['max_volume_m3'] ?? $v['max_volume_m3'];
                            $isActive = (bool)($catalog['is_active'] ?? true);
                            $priced = \App\Services\OvanieDeliveryPriceCalculator::hasFormulaRates($rate);
                            $editorValues = [
                                'vehicle_code' => $code,
                                'vehicle_label' => $rate?->vehicle_label ?: $v['label'],
                                'base_fee' => $priced ? (float) $rate->base_fee : '',
                                'price_per_km' => $priced ? (float) $rate->price_per_km : '',
                                'price_per_kg' => $priced ? (float) $rate->price_per_kg : '',
                                'price_per_m3' => $priced ? (float) $rate->price_per_m3 : '',
                                'max_weight_kg' => $maxWeight,
                                'max_volume_m3' => $maxVolume,
                                'is_active' => $isActive ? 1 : 0,
                                'description' => data_get($rate?->meta, 'description'),
                            ];
                        @endphp
                        <tr class="compact-row">
                            <td><div class="pricing-vehicle"><img src="{{ asset($v['image']) }}" alt=""><div><strong>{{ $rate?->vehicle_label ?: $v['label'] }}</strong><small>{{ $v['subtitle'] }}</small></div></div></td>
                            @foreach(['base_fee','price_per_km','price_per_kg','price_per_m3'] as $field)
                                <td class="amount">{{ $priced ? number_format((float) $rate->{$field},0,',',' ').' F' : '—' }}</td>
                            @endforeach
                            <td class="amount">{{ $maxWeight !== null ? number_format((float)$maxWeight,0,',',' ').' kg' : 'Sans limite' }}<br><small>{{ $maxVolume !== null ? number_format((float)$maxVolume,2,',',' ').' m³' : '' }}</small></td>
                            <td><span class="pricing-status {{ !$priced || !$isActive ? 'inactive' : '' }}"><i></i>{{ !$priced ? 'Non tarifé' : ($isActive ? 'Actif' : 'Inactif') }}</span></td>
                            <td><button type="button" class="pricing-btn pricing-btn-small {{ $priced ? '' : 'pricing-btn-green' }}" data-pricing-open="vehicle-rate-modal" data-pricing-values="{{ json_encode($editorValues) }}">{{ $priced ? 'Modifier' : 'Tarifer' }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </article>

        <aside class="pricing-right-stack">
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="list"/></span><div><h2>Exemple de calcul</h2><p>Moto, 6 km, 12 kg, 0,05 m³.</p></div></div></header><div class="pricing-card-body">
                <div class="pricing-use-note"><x-operations.icon name="list"/><div><strong>Base + 6 × prix/km + 12 × prix/kg + 0,05 × prix/m³</strong><span>Puis les frais éventuels dont les conditions sont remplies (fragile, express…).</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="check-circle"/><div><strong>Tarif à 0</strong><span>Mettez 0 pour une composante que vous ne facturez pas (ex. prix/m³ pour la moto).</span></div></div>
            </div></article>
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon orange"><x-operations.icon name="chart"/></span><div><h2>Tester un prix</h2><p>Vérifiez un trajet avant de publier vos tarifs.</p></div></div><a href="{{ route('logistics.ovanie-pricing.simulator') }}" class="pricing-btn pricing-btn-soft pricing-btn-small">Simulateur</a></header></article>
        </aside>
    </section>
</main>

@include('logistics.pricing._rate_modal')
@endsection
@push('scripts')<script defer src="{{ asset('js/logistics-pricing.js') }}?v={{ @filemtime(public_path('js/logistics-pricing.js')) ?: '20260914' }}"></script>@endpush
