@extends('layouts.logistics')
@section('title','Tarifs configurés — OVANIE')
@section('crumb','Tarification > Tarifs configurés')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush

@section('content')
<main class="pricing-page pricing-communes-page pricing-configured-page">
    <div class="pricing-titlebar pricing-titlebar-separated">
        <div>
            <span class="pricing-eyebrow">Tarification communale</span>
            <h1>Tarifs configurés</h1>
            <p>Consultez et contrôlez l’ensemble des prix de livraison enregistrés entre les communes actives de Territoire.</p>
        </div>
        <div class="pricing-title-actions">
            <a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.export',['type'=>'communes']) }}"><x-operations.icon name="download"/> Exporter</a>
            <a class="pricing-btn pricing-btn-green" href="{{ route('logistics.ovanie-pricing.communes.configure') }}"><x-operations.icon name="plus"/> Configurer les tarifs</a>
        </div>
    </div>

    <section class="pricing-kpis pricing-kpis-relations pricing-kpis-dashboard" aria-label="État de la couverture tarifaire">
        <a class="pricing-kpi pricing-kpi-link" href="{{ route('logistics.ovanie-pricing.communes.configure') }}">
            <span class="pricing-kpi-icon blue"><x-operations.icon name="pin"/></span>
            <div class="pricing-kpi-body"><span class="pricing-kpi-label">Communes couvertes</span><div class="pricing-kpi-value-row"><strong>{{ $communes->count() }}</strong></div><p>{{ $zones->count() }} zone(s) active(s) synchronisée(s) depuis Territoire</p></div>
        </a>
        <a class="pricing-kpi pricing-kpi-link {{ $relationStatus === 'configured' ? 'is-selected' : '' }}" href="{{ route('logistics.ovanie-pricing.communes',['relation_status'=>'configured']) }}#tarifs-configures">
            <span class="pricing-kpi-icon blue"><x-operations.icon name="list"/></span>
            <div class="pricing-kpi-body"><span class="pricing-kpi-label">Tarifs configurés</span><div class="pricing-kpi-value-row"><strong>{{ $relationStats['configured'] }}/{{ $relationStats['required'] }}</strong></div><p>Au moins un prix véhicule enregistré</p></div>
        </a>
        <a class="pricing-kpi pricing-kpi-link {{ $relationStatus === 'covered' ? 'is-selected' : '' }}" href="{{ route('logistics.ovanie-pricing.communes',['relation_status'=>'covered']) }}#tarifs-configures">
            <span class="pricing-kpi-icon green"><x-operations.icon name="check-circle"/></span>
            <div class="pricing-kpi-body"><span class="pricing-kpi-label">Relations couvertes</span><div class="pricing-kpi-value-row"><strong>{{ $relationStats['covered'] }}/{{ $relationStats['required'] }}</strong></div><p>Tous les véhicules actifs possèdent un prix</p></div>
        </a>
        <a class="pricing-kpi pricing-kpi-link {{ $relationStatus === 'incomplete' ? 'is-selected' : '' }}" href="{{ route('logistics.ovanie-pricing.communes',['relation_status'=>'incomplete']) }}#tarifs-configures">
            <span class="pricing-kpi-icon orange"><x-operations.icon name="alert"/></span>
            <div class="pricing-kpi-body"><span class="pricing-kpi-label">Relations incomplètes</span><div class="pricing-kpi-value-row"><strong>{{ $relationStats['incomplete'] }}</strong></div><p>Un ou plusieurs véhicules actifs n’ont pas de prix</p></div>
        </a>
        <a class="pricing-kpi pricing-kpi-link {{ $relationStatus === 'unconfigured' ? 'is-selected' : '' }}" href="{{ route('logistics.ovanie-pricing.communes',['relation_status'=>'unconfigured']) }}#tarifs-configures">
            <span class="pricing-kpi-icon purple"><x-operations.icon name="route"/></span>
            <div class="pricing-kpi-body"><span class="pricing-kpi-label">Non configurées</span><div class="pricing-kpi-value-row"><strong>{{ $relationStats['unconfigured'] }}</strong></div><p>Couverture complète : {{ $relationStats['percent'] }}%</p></div>
        </a>
    </section>

    @include('logistics.pricing._module_navigation')

    @if(session('success'))
        <div class="pricing-feedback success" role="status"><x-operations.icon name="check-circle"/><div><strong>Enregistrement terminé</strong><span>{{ session('success') }}</span></div></div>
    @endif
    @if($errors->any())
        <div class="pricing-feedback error" role="alert"><x-operations.icon name="alert"/><div><strong>Action impossible</strong><span>{{ $errors->first() }}</span></div></div>
    @endif

    <section class="pricing-card configured-rates-card" id="tarifs-configures">
        <header class="pricing-card-header configured-rates-header">
            <div class="pricing-card-heading">
                <span class="pricing-section-icon"><x-operations.icon name="list"/></span>
                <div>
                    <h2>Liste globale des relations tarifaires</h2>
                    <p>Chaque sens est indépendant : Cocody → Abobo et Abobo → Cocody peuvent avoir des montants différents.</p>
                </div>
            </div>
            <div class="pricing-coverage-badge">
                <strong>{{ $relationStats['percent'] }}%</strong>
                <span>de couverture complète</span>
            </div>
        </header>

        <div class="pricing-card-body">
            <form method="GET" action="{{ route('logistics.ovanie-pricing.communes') }}" class="configured-rate-filters configured-rate-filters-pro" data-configured-rate-filters>
                <label class="pricing-search configured-search"><x-operations.icon name="search"/><input type="search" name="relation_search" value="{{ $relationSearch }}" placeholder="Rechercher une commune ou une zone..."></label>
                <select class="pricing-select" name="relation_status" aria-label="Statut">
                    <option value="configured" @selected($relationStatus === 'configured')>Tarifs configurés</option>
                    <option value="covered" @selected($relationStatus === 'covered')>Relations couvertes</option>
                    <option value="incomplete" @selected($relationStatus === 'incomplete')>Relations incomplètes</option>
                    <option value="inactive" @selected($relationStatus === 'inactive')>Relations inactives</option>
                    <option value="unconfigured" @selected($relationStatus === 'unconfigured')>Relations non configurées</option>
                    <option value="all" @selected($relationStatus === 'all')>Toutes les relations</option>
                </select>
                <select class="pricing-select" name="list_origin_zone" aria-label="Zone de départ">
                    <option value="">Toutes les zones de départ</option>
                    @foreach($zones as $zone)<option value="{{ $zone->code }}" @selected($listOriginZone === $zone->code)>{{ $zone->name }}</option>@endforeach
                </select>
                <select class="pricing-select" name="list_destination_zone" aria-label="Zone d’arrivée">
                    <option value="">Toutes les zones d’arrivée</option>
                    @foreach($zones as $zone)<option value="{{ $zone->code }}" @selected($listDestinationZone === $zone->code)>{{ $zone->name }}</option>@endforeach
                </select>
                <select class="pricing-select" name="list_origin" aria-label="Commune de départ">
                    <option value="0">Toutes les communes de départ</option>
                    @foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((int)$listOrigin === (int)$commune->id)>{{ $commune->name }}</option>@endforeach
                </select>
                <select class="pricing-select" name="list_destination" aria-label="Commune d’arrivée">
                    <option value="0">Toutes les communes d’arrivée</option>
                    @foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((int)$listDestination === (int)$commune->id)>{{ $commune->name }}</option>@endforeach
                </select>
                <button class="pricing-btn pricing-btn-green" type="submit"><x-operations.icon name="search"/> Filtrer</button>
                <a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.communes',['relation_status'=>'configured']) }}#tarifs-configures">Réinitialiser</a>
            </form>

            <div class="configured-list-summary configured-list-summary-pro">
                <div><strong>{{ $relationRows->count() }}</strong><span>relation(s) affichée(s)</span></div>
                <p>Sur {{ $relationStats['required'] }} relations possibles : {{ $relationStats['covered'] }} couverte(s), {{ $relationStats['incomplete'] }} incomplète(s), {{ $relationStats['inactive'] }} inactive(s), {{ $relationStats['unconfigured'] }} non configurée(s).</p>
            </div>

            <div class="pricing-table-wrap configured-rates-table-wrap">
                <table class="pricing-table configured-rates-table">
                    <thead>
                        <tr>
                            <th>Départ</th>
                            <th>Destination</th>
                            @foreach($vehicles as $code=>$vehicle)<th>{{ $vehicle['label'] }}</th>@endforeach
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($relationRows as $row)
                        @php
                            $statusLabel = match($row['status']) {
                                'covered' => 'Couverte',
                                'incomplete' => 'Incomplète',
                                'inactive' => 'Inactive',
                                default => 'Non configurée',
                            };
                            $originZoneCode = $row['origin_zone_codes'][0] ?? null;
                        @endphp
                        <tr class="relation-status-{{ $row['status'] }}">
                            <td><div class="relation-place"><strong>{{ $row['origin']->name }}</strong><small>{{ implode(', ', $row['origin_zone_names']) ?: 'Territoire' }}</small></div></td>
                            <td><div class="relation-place"><strong>{{ $row['destination']->name }}</strong><small>{{ implode(', ', $row['destination_zone_names']) ?: 'Territoire' }}</small></div></td>
                            @foreach($vehicles as $code=>$vehicle)
                                @php $value=(float)($row['prices'][$code] ?? 0); @endphp
                                <td class="relation-price-cell">@if($value>0)<strong>{{ number_format($value,0,',',' ') }}</strong><span>FCFA</span>@else<span class="pricing-empty-price">—</span>@endif</td>
                            @endforeach
                            <td><span class="relation-status-pill {{ $row['status'] }}">{{ $statusLabel }}</span></td>
                            <td>
                                <a class="pricing-btn pricing-btn-small {{ $row['status']==='unconfigured' ? 'pricing-btn-green' : '' }}" href="{{ route('logistics.ovanie-pricing.communes.configure',['origin_zone'=>$originZoneCode,'origin'=>$row['origin']->id,'destination'=>$row['destination']->id]) }}">
                                    {{ $row['status']==='unconfigured' ? 'Configurer' : 'Modifier' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($vehicles)+4 }}"><div class="pricing-empty-state"><strong>Aucune relation ne correspond aux filtres.</strong><span>Modifiez vos filtres ou ouvrez la page de configuration pour renseigner de nouveaux tarifs.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="pricing-info-strip">
        <div><span class="pricing-dot green"></span><strong>Couverte</strong><span>tous les véhicules actifs ont un prix.</span></div>
        <div><span class="pricing-dot orange"></span><strong>Incomplète</strong><span>au moins un véhicule actif n’a pas de prix.</span></div>
        <div><span class="pricing-dot gray"></span><strong>Non configurée</strong><span>aucun tarif n’est enregistré pour cette relation.</span></div>
    </section>
</main>
@endsection
