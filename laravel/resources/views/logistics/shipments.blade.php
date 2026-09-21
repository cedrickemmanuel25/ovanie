@extends('layouts.logistics-operations')
@section('title', 'Expéditions')
@section('page-class', 'ops-page-missions')
@php
    $missions = $operationsData['missions'] ?? collect(isset($shipments) ? $shipments->items() : [])->map(fn($g) => \App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $driverRows = $operationsData['drivers'] ?? ($activeDrivers ?? collect())->map(fn($d) => \App\ViewModels\LogisticsOperationsData::driver($d))->all();
    $numbers = $operationsData['counts'] ?? ['all'=>$counts['all'] ?? 0, 'to_assign'=>$counts['to_assign'] ?? 0, 'route'=>$counts['route'] ?? 0, 'done'=>$counts['done'] ?? 0];
    $total = max(1, $numbers['all']);
    $assignableStatuses = ['pending', 'ready_for_pickup', 'delivery_failed'];
    $assignmentMissionRows = collect($assignmentMissions ?? $missions)
        ->filter(fn($mission) => in_array($mission['status'] ?? null, $assignableStatuses, true))
        ->values();
    $assignableMissions = $assignmentMissionRows;
@endphp
@section('content')
<x-operations.page-header title="Expéditions" subtitle="Gestion des missions OVANIE Logistics, affectation des livreurs et suivi des livraisons" current="Expéditions">
    <a class="ops-button" href="{{ route('logistics.shipments.export', request()->query()) }}"><x-operations.icon name="download"/>Exporter</a>
    <button class="ops-button ops-button-primary" type="button" data-open-assignment="" @disabled($assignableMissions->isEmpty())><x-operations.icon name="plus"/>Affecter une mission</button>
</x-operations.page-header>
<form class="ops-filters" method="get" action="{{ route('logistics.shipments') }}">
    <label class="ops-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher une expédition, une commande, un client ou une destination" aria-label="Rechercher une mission"></label>
    <select name="status" aria-label="Statut" data-submit-filter><option value="all">Statut</option>@foreach(['to_assign'=>'À affecter','in_delivery'=>'En transit','delivered'=>'Livrée'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="destination" aria-label="Destination" data-submit-filter><option value="">Destination</option>@foreach($destinations ?? collect($missions)->pluck('destination')->unique() as $destination)<option @selected(request('destination')===$destination)>{{ $destination }}</option>@endforeach</select>
    <select name="vehicle" aria-label="Véhicule requis" data-submit-filter><option value="">Véhicule requis</option>@foreach($vehicles ?? [] as $vehicle)<option value="{{ $vehicle['code'] }}" @selected(request('vehicle')===$vehicle['code'])>{{ $vehicle['label'] }}</option>@endforeach</select>
    <a class="ops-reset" href="{{ route('logistics.shipments') }}"><x-operations.icon name="refresh"/>Réinitialiser les filtres</a>
</form>
<div class="ops-kpis ops-kpis-missions">
    <x-operations.kpi icon="box" label="Toutes les missions" :value="$numbers['all']"/>
    <x-operations.kpi icon="clock" label="À affecter" :value="$numbers['to_assign']" tone="orange"/>
    <x-operations.kpi icon="truck" label="En transit" :value="$numbers['route']"/>
    <x-operations.kpi icon="check-circle" label="Livrées" :value="$numbers['done']" tone="green"/>
    <x-operations.kpi icon="warning" label="Incidents ouverts" :value="$incidentsOpenCount ?? 0" tone="red"/>
</div>
<div class="ops-missions-layout">
    <x-operations.panel title="Toutes les missions" icon="box" class="ops-mission-list">
        <x-slot:actions><form method="get">@foreach(request()->except('page','per_page') as $key=>$value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<select class="ops-page-size" name="per_page" aria-label="Missions par page" data-submit-filter>@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',100)===$size)>{{ $size }} par page</option>@endforeach</select></form></x-slot:actions>
        <p class="ops-panel-caption">{{ $numbers['all'] }} missions au total</p>
        <div class="ops-table-scroll"><table class="ops-table ops-mission-table"><thead><tr><th>Expédition</th><th>Commande</th><th>Statut</th><th>Destination</th><th>Produits</th><th>Poids</th><th>Véhicule requis</th><th>Livreur</th></tr></thead><tbody>
        @forelse($missions as $mission)
        <tr><td><a class="ops-reference" href="{{ $mission['detailsUrl'] }}">{{ $mission['reference'] }}</a></td><td>{{ $mission['order'] }}</td><td><x-operations.badge :status="$mission['status']"/></td><td>{{ $mission['destination'] }}</td><td class="ops-products-cell">@foreach($mission['products'] as $product)<div>{{ $product }}</div>@endforeach</td><td>{{ $mission['weight'] }} kg</td><td>{{ $mission['vehicle'] }}</td><td>@if(in_array($mission['status'] ?? null, $assignableStatuses, true))<button type="button" class="ops-button ops-button-small" data-open-assignment="{{ $mission['id'] }}">Affecter</button>@else{{ $mission['driver'] }}@endif</td></tr>
        @empty<tr><td colspan="8" class="ops-empty">Aucune mission ne correspond aux filtres.</td></tr>@endforelse
        </tbody></table></div>
        <footer class="ops-table-footer"><span>{{ count($missions) ? (isset($shipments) ? $shipments->firstItem() : 1).' – '.(isset($shipments) ? $shipments->lastItem() : count($missions)) : '0' }} sur {{ isset($shipments) ? $shipments->total() : $numbers['all'] }} missions</span>@if(isset($shipments)){{ $shipments->links() }}@endif</footer>
    </x-operations.panel>
    <aside class="ops-stack">
        <x-operations.panel title="Répartition des missions" icon="chart"><div class="ops-distribution">@foreach([['À affecter','to_assign','orange','box'],['En transit','route','blue','truck'],['Livrées','done','green','check-circle']] as [$label,$key,$tone,$icon])<div><span class="ops-distribution-count"><span class="ops-square-icon tone-{{ $tone }}"><x-operations.icon :name="$icon"/></span><span><b>{{ $numbers[$key] }}</b><small>{{ $label }}</small></span></span><div><strong>{{ round($numbers[$key]/$total*100) }} %</strong><div class="ops-progress"><i class="tone-{{ $tone }}" style="width:{{ round($numbers[$key]/$total*100) }}%"></i></div></div></div>@endforeach</div></x-operations.panel>
        <x-operations.panel title="Livreurs actifs" icon="users"><x-slot:actions><a href="{{ route('logistics.drivers') }}">Voir tous</a></x-slot:actions><div class="ops-driver-counts">@foreach([['En ligne',$driversOnlineCount??0,'blue'],['au total',$driversTotalCount??0,'gray'],['occupés',$driversBusyCount??0,'orange'],['disponibles',$driversAvailableCount??0,'green']] as [$label,$value,$tone])<div><x-operations.icon name="users" class="text-{{ $tone }}"/><p><strong>{{ $value }}</strong><span>{{ $label }}</span></p></div>@endforeach</div><p class="ops-offline">{{ $driversOfflineCount ?? 0 }} hors ligne</p></x-operations.panel>
        <x-operations.panel title="Incident à suivre" icon="warning"><x-slot:actions><a href="{{ route('logistics.incidents.index') }}">Voir tous les incidents</a></x-slot:actions><div class="ops-incident-body">@if($latestOpenIncident ?? null)<strong>{{ $latestOpenIncident->orderItem?->latestDeliveryAssignment?->mission_number ?? 'Incident #'.$latestOpenIncident->id }}</strong><x-operations.badge status="incident"/><p>{{ $latestOpenIncident->description ?? 'Consultez le détail de cet incident.' }}</p>@else<div class="ops-alert"><x-operations.icon name="check-circle"/><div><strong>Aucun incident ouvert</strong><p>Les missions se déroulent normalement.</p></div></div>@endif</div></x-operations.panel>
    </aside>
</div>
@endsection
@push('dialogs')@include('logistics.operations.assignment-dialog')@endpush
@push('scripts')<script type="application/json" id="ops-mission-data">{!! json_encode(['missions'=>$missions,'assignmentMissions'=>$assignmentMissionRows->all(),'drivers'=>$driverRows], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endpush
