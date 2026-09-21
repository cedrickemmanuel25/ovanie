@extends('layouts.logistics')
@section('title','Tarification')
@section('crumb','Tarification')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush
@section('content')
<main class="pricing-page">
    <div class="pricing-titlebar"><div><h1>Tarification</h1><p>Pilotez les tarifs de livraison OVANIE : véhicule compatible, prix fixe du trajet entre communes et suppléments éventuels.</p></div><div class="pricing-title-actions"><a class="pricing-btn pricing-btn-green" href="{{ route('logistics.ovanie-pricing.communes') }}"><x-operations.icon name="map"/> Configurer les tarifs par communes</a></div></div>

    <section class="pricing-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="truck"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeVehicleCount }}/{{ count($vehicles) }}</strong></div><p>{{ $configuredVehicleCount }} capacité(s) personnalisée(s)</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="route"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Tarifs de trajets</span><div class="pricing-kpi-value-row"><strong>{{ $matrixCount }}</strong><span class="pricing-chip">{{ $matrixCoveragePercent }}% couverts</span></div><p>{{ $matrixCoveredRouteCount }}/{{ $matrixRequiredRouteCount }} relations couvertes</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="pin"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Communes couvertes</span><div class="pricing-kpi-value-row"><strong>{{ $coveredCommuneCount }}</strong></div><p>Issues de Territoire</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="plus"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Suppléments actifs</span><div class="pricing-kpi-value-row"><strong>{{ $supplementCount }}</strong></div><p>Frais conditionnels</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    <section class="pricing-grid-2">
        <article class="pricing-card">
            <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="truck"/></span><div><h2>Véhicules & capacités</h2><p>Le poids et le volume déterminent le véhicule à utiliser. Aucun prix n’est configuré ici : le montant du trajet vient de Tarifs communes.</p></div></div><a class="pricing-btn pricing-btn-soft pricing-btn-small" href="{{ route('logistics.ovanie-pricing.vehicles') }}">Gérer</a></header>
            <div class="pricing-card-body"><div class="pricing-table-wrap"><table class="pricing-table"><thead><tr><th>Véhicule</th><th>Capacité max.</th><th>Statut</th></tr></thead><tbody>
                @foreach($vehicles as $code=>$vehicle) @php($rate=$rates->firstWhere('vehicle_code',$code))
                <tr><td><div class="pricing-vehicle"><img src="{{ asset($vehicle['image']) }}" alt=""><div><strong>{{ $vehicle['label'] }}</strong><small>{{ $vehicle['subtitle'] }}</small></div></div></td><td class="amount">{{ $rate?->max_weight_kg !== null ? number_format($rate->max_weight_kg,0,',',' ').' kg' : $vehicle['capacity'] }}</td><td><span class="pricing-status {{ $rate && !$rate->is_active?'inactive':'' }}"><i></i>{{ $rate && !$rate->is_active ? 'Inactif' : ($rate ? 'Actif' : 'Actif par défaut') }}</span></td></tr>
                @endforeach
            </tbody></table></div></div>
        </article>
        <aside class="pricing-right-stack">
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="list"/></span><div><h2>Règle de calcul</h2><p>Une seule logique pour éviter les montants incohérents.</p></div></div></header><div class="pricing-card-body">
                <div class="pricing-use-note"><x-operations.icon name="truck"/><div><strong>1. Choisir le véhicule</strong><span>OVANIE utilise le poids et le volume pour sélectionner Moto, Tricycle, Pickup, Camion 3T ou Camion 10T.</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="map"/><div><strong>2. Lire le tarif du trajet</strong><span>Exemple : Cocody → Cocody ou Cocody → Adjamé possède un prix propre pour chaque véhicule.</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="plus"/><div><strong>3. Ajouter les suppléments</strong><span>Fragile, manutention, déchargement, urgence et autres règles s’ajoutent ensuite si nécessaire.</span></div></div>
            </div></article>
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon orange"><x-operations.icon name="route"/></span><div><h2>Couverture tarifaire</h2><p>Chaque relation de communes doit avoir un tarif opérationnel.</p></div></div><a class="pricing-btn pricing-btn-soft pricing-btn-small" href="{{ route('logistics.ovanie-pricing.communes') }}">Configurer</a></header><div class="pricing-card-body"><div class="pricing-surcharge-row"><x-operations.icon name="check-circle"/><b>Relations couvertes</b><span>{{ $matrixCoveredRouteCount }}/{{ $matrixRequiredRouteCount }}</span></div></div></article>
        </aside>
    </section>
</main>
@endsection
