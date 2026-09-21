@extends('layouts.logistics')
@section('title','Véhicules & capacités')
@section('crumb','Tarification > Véhicules & capacités')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush

@section('content')
<main class="pricing-page pricing-vehicles-page">
    <div class="pricing-titlebar">
        <div><h1>Véhicules & capacités</h1><p>Définissez uniquement les capacités et l’activation des véhicules. Les prix de livraison se configurent dans Tarifs par communes.</p></div>
        <div class="pricing-title-actions"><a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.export',['type'=>'vehicles']) }}"><x-operations.icon name="download"/> Exporter</a><button type="button" class="pricing-btn pricing-btn-green" data-pricing-open="vehicle-rate-modal"><x-operations.icon name="settings"/> Configurer un véhicule</button></div>
    </div>

    <section class="pricing-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="truck"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Véhicules actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeVehicleCount }}/{{ count($vehicles) }}</strong></div><p>Disponibles pour le choix automatique</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="settings"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Capacités personnalisées</span><div class="pricing-kpi-value-row"><strong>{{ $configuredVehicleCount }}</strong></div><p>Sinon valeurs OVANIE par défaut</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="map"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Source du prix</span><div class="pricing-kpi-value-row"><strong class="pricing-kpi-text-value">Tarifs communes</strong></div><p>Aucun prix véhicule à saisir ici</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="plus"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Suppléments actifs</span><div class="pricing-kpi-value-row"><strong>{{ $activeSupplementCount }}</strong></div><p>Gérés séparément</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    @if(session('success'))<div class="pricing-feedback success" role="status"><x-operations.icon name="check-circle"/><div><strong>Configuration enregistrée</strong><span>{{ session('success') }}</span></div></div>@endif
    @if($errors->any())<div class="pricing-feedback error" role="alert"><x-operations.icon name="alert"/><div><strong>Enregistrement impossible</strong><span>{{ $errors->first() }}</span></div></div>@endif

    <section class="pricing-grid-2">
        <article class="pricing-card">
            <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="truck"/></span><div><h2>Capacités des véhicules</h2><p>Le poids et le volume servent uniquement à déterminer le plus petit véhicule actif capable de transporter la commande.</p></div></div></header>
            <div class="pricing-card-body">
                <div class="pricing-filters vehicle-capacity-filters"><label class="pricing-search"><x-operations.icon name="search"/><input data-table-search="#vehicle-rates-table" placeholder="Rechercher un véhicule..."></label></div>
                <div class="pricing-table-wrap"><table class="pricing-table" id="vehicle-rates-table">
                    <thead><tr><th>Véhicule</th><th>Charge maximale</th><th>Volume maximal</th><th>Configuration</th><th>Statut</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($vehicles as $code => $v)
                        @php
                            $rate = $rates->firstWhere('vehicle_code', $code);
                            $catalog = $vehicleCatalog->firstWhere('code', $code);
                            $maxWeight = $catalog['max_weight_kg'] ?? $v['max_weight_kg'];
                            $maxVolume = $catalog['max_volume_m3'] ?? $v['max_volume_m3'];
                            $isActive = (bool)($catalog['is_active'] ?? true);
                            $configured = (bool)($catalog['configured'] ?? false);
                            $editorValues = [
                                'vehicle_code' => $code,
                                'vehicle_label' => $rate?->vehicle_label ?: $v['label'],
                                'max_weight_kg' => $maxWeight,
                                'max_volume_m3' => $maxVolume,
                                'is_active' => $isActive ? 1 : 0,
                                'description' => data_get($rate?->meta, 'description'),
                            ];
                        @endphp
                        <tr class="compact-row">
                            <td><div class="pricing-vehicle"><img src="{{ asset($v['image']) }}" alt=""><div><strong>{{ $rate?->vehicle_label ?: $v['label'] }}</strong><small>{{ $v['subtitle'] }}</small></div></div></td>
                            <td class="amount">{{ $maxWeight !== null ? number_format((float)$maxWeight,0,',',' ').' kg' : 'Sans limite définie' }}</td>
                            <td class="amount">{{ $maxVolume !== null ? number_format((float)$maxVolume,2,',',' ').' m³' : '—' }}</td>
                            <td><span class="capacity-source {{ $configured ? 'custom' : '' }}">{{ $configured ? 'Personnalisée' : 'OVANIE par défaut' }}</span></td>
                            <td><span class="pricing-status {{ $isActive ? '' : 'inactive' }}"><i></i>{{ $isActive ? 'Actif' : 'Inactif' }}</span></td>
                            <td><button type="button" class="pricing-btn pricing-btn-small" data-pricing-open="vehicle-rate-modal" data-pricing-values="{{ json_encode($editorValues) }}">Modifier</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </article>

        <aside class="pricing-right-stack">
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="target"/></span><div><h2>Rôle de ce module</h2><p>Choisir le véhicule, pas calculer son prix.</p></div></div></header><div class="pricing-card-body"><div class="pricing-use-note"><x-operations.icon name="check-circle"/><div><strong>Capacité uniquement</strong><span>Moto, Tricycle, Pickup, Camion 3T et Camion 10T disposent déjà de capacités OVANIE par défaut. Modifiez-les uniquement si l’exploitation le demande.</span></div></div><div class="pricing-use-note"><x-operations.icon name="map"/><div><strong>Prix dans Tarifs communes</strong><span>Une fois le véhicule choisi, OVANIE lit le montant exact du trajet départ → destination dans la matrice communale.</span></div></div></div></article>
            <article class="pricing-card pricing-right-card"><header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon orange"><x-operations.icon name="map"/></span><div><h2>Configurer les prix</h2><p>La tarification réelle se fait commune par commune.</p></div></div><a href="{{ route('logistics.ovanie-pricing.communes') }}" class="pricing-btn pricing-btn-soft pricing-btn-small">Ouvrir la grille</a></header><div class="pricing-card-body"><div class="pricing-surcharge-row"><x-operations.icon name="route"/><b>Source principale</b><span>Commune → commune</span></div></div></article>
        </aside>
    </section>
</main>

@include('logistics.pricing._rate_modal')
@endsection
@push('scripts')<script defer src="{{ asset('js/logistics-pricing.js') }}?v={{ @filemtime(public_path('js/logistics-pricing.js')) ?: '20260914' }}"></script>@endpush
