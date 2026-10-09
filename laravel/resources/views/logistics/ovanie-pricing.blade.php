@extends('layouts.logistics')
@section('title','Tarification')
@section('crumb','Tarification')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush
@section('content')
<main class="pricing-page">
    <div class="pricing-titlebar"><div><h1>Tarification</h1><p>Livraison = tarif de base + distance × prix/km + poids × prix/kg + volume × prix/m³ + frais éventuels. Les tarifs dépendent du véhicule choisi selon le poids et le volume.</p></div><div class="pricing-title-actions"><a class="pricing-btn pricing-btn-green" href="{{ route('logistics.ovanie-pricing.vehicles') }}"><x-operations.icon name="truck"/> Configurer les tarifs véhicules</a></div></div>

    <section class="pricing-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="truck"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules tarifés</span><div class="pricing-kpi-value-row"><strong>{{ $pricedVehicleCount }}/{{ count($vehicles) }}</strong></div><p>{{ $activeVehicleCount }} véhicule(s) actif(s)</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="route"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Distance</span><div class="pricing-kpi-value-row"><strong class="pricing-kpi-text-value">Itinéraire réel</strong></div><p>GPS boutique → client, sinon estimation</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="pin"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Communes couvertes</span><div class="pricing-kpi-value-row"><strong>{{ $coveredCommuneCount }}</strong></div><p>Issues de Territoire</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="plus"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Frais éventuels actifs</span><div class="pricing-kpi-value-row"><strong>{{ $supplementCount }}</strong></div><p>Ajoutés selon leurs conditions</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    @if($pricedVehicleCount < count($vehicles))
        <div class="pricing-feedback error" role="status"><x-operations.icon name="alert"/><div><strong>{{ count($vehicles) - $pricedVehicleCount }} véhicule(s) sans tarif</strong><span>Une commande qui nécessite un véhicule sans tarif ne peut pas être livrée par OVANIE : le client est invité à retirer l’article.</span></div></div>
    @endif

    <section class="pricing-grid-2">
        <article class="pricing-card">
            <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="truck"/></span><div><h2>Tarifs par véhicule</h2><p>Chaque véhicule a son tarif de base, son prix au km, au kg et au m³.</p></div></div><a class="pricing-btn pricing-btn-soft pricing-btn-small" href="{{ route('logistics.ovanie-pricing.vehicles') }}">Gérer</a></header>
            <div class="pricing-card-body"><div class="pricing-table-wrap"><table class="pricing-table"><thead><tr><th>Véhicule</th><th>Base</th><th>/ km</th><th>/ kg</th><th>/ m³</th><th>Statut</th></tr></thead><tbody>
                @foreach($vehicles as $code=>$vehicle)
                    @php($rate=$rates->firstWhere('vehicle_code',$code))
                    @php($priced = \App\Services\OvanieDeliveryPriceCalculator::hasFormulaRates($rate))
                    <tr>
                        <td><div class="pricing-vehicle"><img src="{{ asset($vehicle['image']) }}" alt=""><div><strong>{{ $rate?->vehicle_label ?: $vehicle['label'] }}</strong><small>{{ $vehicle['subtitle'] }}</small></div></div></td>
                        @foreach(['base_fee','price_per_km','price_per_kg','price_per_m3'] as $field)
                            <td class="amount">{{ $priced ? number_format((float) $rate->{$field},0,',',' ').' F' : '—' }}</td>
                        @endforeach
                        <td><span class="pricing-status {{ !$priced || !$rate->is_active ? 'inactive' : '' }}"><i></i>{{ !$priced ? 'Non tarifé' : ($rate->is_active ? 'Actif' : 'Inactif') }}</span></td>
                    </tr>
                @endforeach
            </tbody></table></div></div>
        </article>
        <aside class="pricing-right-stack">
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="list"/></span><div><h2>Règle de calcul</h2><p>Une seule formule pour le web, l’app client et l’app livreur.</p></div></div></header><div class="pricing-card-body">
                <div class="pricing-use-note"><x-operations.icon name="truck"/><div><strong>1. Choisir le véhicule</strong><span>Le plus petit véhicule actif capable de porter le poids et le volume du panier.</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="route"/><div><strong>2. Mesurer la distance</strong><span>Itinéraire entre la boutique et l’adresse du client (GPS), sinon distance entre communes, sinon ligne droite.</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="list"/><div><strong>3. Appliquer la formule</strong><span>Base + distance × prix/km + poids × prix/kg + volume × prix/m³. Aucun minimum ni plafond.</span></div></div>
                <div class="pricing-use-note"><x-operations.icon name="plus"/><div><strong>4. Ajouter les frais éventuels</strong><span>Fragile, manutention, déchargement, express… selon les règles actives.</span></div></div>
            </div></article>
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon orange"><x-operations.icon name="wallet"/></span><div><h2>Livreur</h2><p>Ce que reçoit le livreur.</p></div></div></header><div class="pricing-card-body"><div class="pricing-surcharge-row"><x-operations.icon name="check-circle"/><b>Montant livreur</b><span>Prix de livraison − commission OVANIE</span></div></div></article>
        </aside>
    </section>
</main>
@endsection
