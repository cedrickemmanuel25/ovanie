@extends('layouts.logistics-operations')
@section('title', 'Missions')
@section('page-class', 'ops-page-missions')
@php
    $missions = collect(isset($shipments) ? $shipments->items() : [])
        ->map(fn($group) => \App\ViewModels\LogisticsOperationsData::mission($group))
        ->all();
    $numbers = array_merge([
        'all' => 0,
        'to_offer' => 0,
        'waiting_acceptance' => 0,
        'accepted_waiting_vendor' => 0,
        'ready_for_pickup' => 0,
        'collecting' => 0,
        'in_delivery' => 0,
        'delivered' => 0,
        'incident' => 0,
    ], $counts ?? []);
    $currentStatus = request('status', 'all');
    $tabs = [
        'all' => ['Toutes', $numbers['all']],
        'to_offer' => ['À proposer', $numbers['to_offer']],
        'waiting_acceptance' => ['En attente d’acceptation', $numbers['waiting_acceptance']],
        'accepted_waiting_vendor' => ['Acceptées · préparation vendeur', $numbers['accepted_waiting_vendor']],
        'ready_for_pickup' => ['Prêtes pour collecte', $numbers['ready_for_pickup']],
        'collecting' => ['Collecte en cours', $numbers['collecting']],
        'in_delivery' => ['En livraison', $numbers['in_delivery']],
        'delivered' => ['Livrées', $numbers['delivered']],
        'incident' => ['Incidents', $numbers['incident']],
    ];
@endphp
@section('content')
<x-operations.page-header
    title="Missions"
    subtitle="Supervision des missions automatiques OVANIE Logistics : proposition aux livreurs, préparation vendeur, collecte et livraison client."
    current="Missions"
>
    <a class="ops-button" href="{{ route('logistics.shipments.export', request()->query()) }}"><x-operations.icon name="download"/>Exporter</a>
    <a class="ops-button" href="{{ route('logistics.dashboard') }}"><x-operations.icon name="refresh"/>Actualiser</a>
</x-operations.page-header>

<div class="ops-alert" style="margin-bottom:16px">
    <x-operations.icon name="check-circle" class="text-green"/>
    <div>
        <strong>Affectation automatique active</strong>
        <p>La Logistique ne choisit plus manuellement un livreur. La mission est proposée aux livreurs partenaires éligibles et le premier qui accepte la réserve.</p>
    </div>
</div>

<div class="ops-kpis ops-kpis-missions">
    <x-operations.kpi icon="box" label="Toutes les missions" :value="$numbers['all']"/>
    <x-operations.kpi icon="clock" label="En attente d’acceptation" :value="$numbers['waiting_acceptance'] + $numbers['to_offer']" tone="orange"/>
    <x-operations.kpi icon="user" label="Réservées" :value="$numbers['accepted_waiting_vendor'] + $numbers['ready_for_pickup']" tone="green"/>
    <x-operations.kpi icon="truck" label="En opération" :value="$numbers['collecting'] + $numbers['in_delivery']"/>
    <x-operations.kpi icon="check-circle" label="Livrées" :value="$numbers['delivered']" tone="green"/>
    <x-operations.kpi icon="warning" label="Incidents" :value="$numbers['incident']" tone="red"/>
</div>

<div class="ops-filter-tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    @foreach($tabs as $value => [$label, $count])
        <a class="ops-button {{ $currentStatus === $value ? 'ops-button-primary' : '' }}"
           href="{{ request()->fullUrlWithQuery(['status' => $value === 'all' ? null : $value, 'page' => null]) }}">
            {{ $label }} <strong>{{ $count }}</strong>
        </a>
    @endforeach
</div>

<form class="ops-filters" method="get" action="{{ route('logistics.shipments') }}">
    @if($currentStatus !== 'all')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
    <label class="ops-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Mission, commande, client, boutique ou destination" aria-label="Rechercher une mission"></label>
    <select name="destination" aria-label="Destination" data-submit-filter><option value="">Toutes les destinations</option>@foreach($destinations ?? collect($missions)->pluck('destination')->unique() as $destination)<option value="{{ $destination }}" @selected(request('destination')===$destination)>{{ $destination }}</option>@endforeach</select>
    <select name="vehicle" aria-label="Véhicule requis" data-submit-filter><option value="">Tous les véhicules</option>@foreach($vehicles ?? [] as $vehicle)<option value="{{ $vehicle['code'] }}" @selected(request('vehicle')===$vehicle['code'])>{{ $vehicle['label'] }}</option>@endforeach</select>
    <a class="ops-reset" href="{{ route('logistics.shipments', $currentStatus !== 'all' ? ['status'=>$currentStatus] : []) }}"><x-operations.icon name="refresh"/>Réinitialiser</a>
</form>

<div class="ops-missions-layout">
    <x-operations.panel title="Suivi des missions" icon="box" class="ops-mission-list">
        <x-slot:actions>
            <form method="get">
                @foreach(request()->except('page','per_page') as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <select class="ops-page-size" name="per_page" aria-label="Missions par page" data-submit-filter>@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',100)===$size)>{{ $size }} par page</option>@endforeach</select>
            </form>
        </x-slot:actions>
        <p class="ops-panel-caption">Les statuts ci-dessous proviennent des vraies offres livreur, de l’acceptation, de la préparation vendeur et de l’avancement de la livraison.</p>
        <div class="ops-table-scroll">
            <table class="ops-table ops-mission-table">
                <thead><tr><th>Mission</th><th>Commande / client</th><th>Phase actuelle</th><th>Préparation vendeur</th><th>Collectes</th><th>Véhicule</th><th>Livreur partenaire</th><th>Montants</th></tr></thead>
                <tbody>
                @forelse($missions as $mission)
                    <tr>
                        <td><a class="ops-reference" href="{{ $mission['detailsUrl'] }}">{{ $mission['reference'] }}</a><small style="display:block">{{ $mission['destination'] }}</small></td>
                        <td><strong>{{ $mission['order'] }}</strong><small style="display:block">{{ $mission['client'] }}</small></td>
                        <td><x-operations.badge :status="$mission['operationalStatus']" :label="$mission['operationalLabel']"/>
                            @if($mission['operationalStatus']==='waiting_acceptance')<small style="display:block">{{ $mission['offeredDriverCount'] }} livreur(s) invité(s)</small>@endif
                            @if($mission['hasOpenIncident'])<small style="display:block;margin-top:5px"><a href="{{ $mission['incidentUrl'] }}" class="text-red">{{ $mission['automaticDelay'] ? 'Alerte retard automatique' : 'Incident à traiter' }} ↗</a></small>@endif
                        </td>
                        <td style="min-width:150px"><strong>{{ $mission['preparationPercent'] }} %</strong><div class="ops-progress"><i class="tone-{{ $mission['preparationPercent'] >= 100 ? 'green' : 'orange' }}" style="width:{{ $mission['preparationPercent'] }}%"></i></div></td>
                        <td>{{ $mission['readyStops'] }} / {{ $mission['totalStops'] }} prêt(s)</td>
                        <td><strong>{{ $mission['vehicle'] }}</strong><small style="display:block">{{ $mission['weight'] }} kg · {{ $mission['volume'] }} m³</small></td>
                        <td>
                            <strong>{{ $mission['driver'] }}</strong>
                            @if($mission['reserved'])<small style="display:block">Mission réservée{{ $mission['acceptedAt'] ? ' · '.$mission['acceptedAt'] : '' }}</small>
                            @elseif($mission['operationalStatus']==='waiting_acceptance')<small style="display:block">Premier acceptant = mission réservée</small>
                            @else<small style="display:block">Aucun livreur réservé</small>@endif
                        </td>
                        <td>
                            @if($mission['deliveryPriceAmount'] !== null)<strong>{{ number_format((float)$mission['deliveryPriceAmount'],0,',',' ') }} FCFA</strong><small style="display:block">Livraison client</small>@else—@endif
                            @if($mission['driverNetAmount'] !== null)<small style="display:block">Gain livreur : {{ number_format((float)$mission['driverNetAmount'],0,',',' ') }} FCFA</small>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ops-empty">Aucune mission ne correspond aux filtres.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <footer class="ops-table-footer"><span>{{ count($missions) ? (isset($shipments) ? $shipments->firstItem() : 1).' – '.(isset($shipments) ? $shipments->lastItem() : count($missions)) : '0' }} sur {{ isset($shipments) ? $shipments->total() : $numbers['all'] }} missions</span>@if(isset($shipments)){{ $shipments->links() }}@endif</footer>
    </x-operations.panel>

    <aside class="ops-stack">
        <x-operations.panel title="Lecture du flux" icon="chart">
            <div class="ops-distribution">
                @foreach([
                    ['À proposer','to_offer','gray','box'],
                    ['Attente acceptation','waiting_acceptance','orange','clock'],
                    ['Préparation vendeur','accepted_waiting_vendor','orange','user'],
                    ['Prêtes collecte','ready_for_pickup','green','check-circle'],
                    ['Collecte','collecting','blue','truck'],
                    ['En livraison','in_delivery','blue','pin'],
                    ['Livrées','delivered','green','check-circle'],
                ] as [$label,$key,$tone,$icon])
                    <div><span class="ops-distribution-count"><span class="ops-square-icon tone-{{ $tone }}"><x-operations.icon :name="$icon"/></span><span><b>{{ $numbers[$key] }}</b><small>{{ $label }}</small></span></span></div>
                @endforeach
            </div>
        </x-operations.panel>
        <x-operations.panel title="Réseau livreurs" icon="users">
            <x-slot:actions><a href="{{ route('logistics.drivers') }}">Voir tous</a></x-slot:actions>
            <div class="ops-driver-counts">
                @foreach([['En ligne',$driversOnlineCount??0,'blue'],['au total',$driversTotalCount??0,'gray'],['occupés',$driversBusyCount??0,'orange'],['disponibles',$driversAvailableCount??0,'green']] as [$label,$value,$tone])<div><x-operations.icon name="users" class="text-{{ $tone }}"/><p><strong>{{ $value }}</strong><span>{{ $label }}</span></p></div>@endforeach
            </div>
            <p class="ops-offline">{{ $driversOfflineCount ?? 0 }} hors ligne</p>
        </x-operations.panel>
        <x-operations.panel title="Incident à suivre" icon="warning">
            <x-slot:actions><a href="{{ route('logistics.incidents.index') }}">Voir tous les incidents</a></x-slot:actions>
            <div class="ops-incident-body">@if($latestOpenIncident ?? null)<strong>{{ $latestOpenIncident->orderItem?->latestDeliveryAssignment?->mission_number ?? 'Incident #'.$latestOpenIncident->id }}</strong><x-operations.badge status="incident"/><p>{{ $latestOpenIncident->description ?? 'Consultez le détail de cet incident.' }}</p>@else<div class="ops-alert"><x-operations.icon name="check-circle"/><div><strong>Aucun incident ouvert</strong><p>Les missions se déroulent normalement.</p></div></div>@endif</div>
        </x-operations.panel>
    </aside>
</div>
@endsection
