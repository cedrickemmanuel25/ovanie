@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title','Accueil')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-dashboard-map.css') }}?v={{ @filemtime(public_path('css/logistics-dashboard-map.css')) ?: '20260914' }}">@endpush
@php
    $waitingRows=($missionsWaitingAcceptance ?? collect())->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $acceptedRows=($missionsAcceptedWaitingVendor ?? collect())->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $readyRows=($missionsReadyForPickup ?? collect())->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $routeRows=($missionsEnRoute ?? collect())->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $mapMissions=isset($mapMissionGroups)?$mapMissionGroups->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all():array_merge($waitingRows,$acceptedRows,$readyRows,$routeRows);
    $missionCounts=array_merge([
        'all'=>0,'to_offer'=>0,'waiting_acceptance'=>0,'accepted_waiting_vendor'=>0,
        'ready_for_pickup'=>0,'collecting'=>0,'in_delivery'=>0,'delivered'=>0,'incident'=>0,
    ],$missionCounts??[]);
@endphp
@section('content')
<x-operations.page-header title="Accueil" subtitle="Vue d’ensemble de l’activité logistique en temps réel"><span class="ops-button"><x-operations.icon name="calendar"/>{{ now()->locale('fr')->translatedFormat('D d M Y') }}</span><a class="ops-button" href="{{ route('logistics.dashboard') }}"><x-operations.icon name="refresh"/>Actualiser</a></x-operations.page-header>
<div class="ops-kpis ops-dashboard-kpis" data-live-kpis>
@foreach([
['clock','Attente acceptation',($missionCounts['to_offer']+$missionCounts['waiting_acceptance']),'orange','Missions proposées automatiquement'],
['user','Réservées',($missionCounts['accepted_waiting_vendor']+$missionCounts['ready_for_pickup']),'green','Livreur partenaire déjà réservé'],
['truck','En opération',($missionCounts['collecting']+$missionCounts['in_delivery']),'blue','Collecte ou livraison en cours'],
['warning','Retards',$stats['retards']??0,'red','SLA dépassé'],
['bell','Incidents ouverts',$incidentsOpenCount,'red','Nécessite une action'],
['users','Livreurs actifs',$driversOnlineCount,'green','Sur '.$driversTotalCount.' livreurs au total']
] as [$icon,$label,$value,$tone,$caption])<x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$tone"><small>{{ $caption }}</small></x-operations.kpi>
@endforeach
</div>
<div class="ops-dashboard-layout"><div class="ops-stack"><x-operations.panel title="Carte temps réel" icon="box"><x-slot:actions><span class="ops-badge is-green"><span data-live-online>{{ $driversOnlineCount }}</span> livreurs en ligne · {{ count($ovanieShops) }} boutiques géolocalisées</span></x-slot:actions>
@include('logistics.operations.dashboard-live-map')</x-operations.panel>
<x-operations.panel title="Flux automatique des missions" icon="box"><x-slot:actions><a href="{{ route('logistics.shipments') }}">Voir toutes les missions →</a></x-slot:actions>
<div class="ops-alert" style="margin-bottom:12px"><x-operations.icon name="check-circle" class="text-green"/><div><strong>Affectation automatique active</strong><p>Les livreurs éligibles reçoivent l’offre automatiquement. La Logistique supervise le flux sans choisir manuellement le livreur.</p></div></div>
<h3 class="ops-subheading"><span class="text-orange">●</span>En attente d’acceptation ({{ count($waitingRows) }})</h3>
@include('logistics.operations.mission-flow',['flowMissions'=>$waitingRows])
<h3 class="ops-subheading"><span class="text-orange">●</span>Acceptées · préparation vendeur ({{ count($acceptedRows) }})</h3>
@include('logistics.operations.mission-flow',['flowMissions'=>$acceptedRows])
<h3 class="ops-subheading"><span class="text-green">●</span>Prêtes pour collecte ({{ count($readyRows) }})</h3>
@include('logistics.operations.mission-flow',['flowMissions'=>$readyRows])
<h3 class="ops-subheading"><span class="text-blue">●</span>Collecte / livraison en cours ({{ count($routeRows) }})</h3>
@include('logistics.operations.mission-flow',['flowMissions'=>$routeRows])</x-operations.panel></div>
<aside class="ops-stack"><x-operations.panel title="Livreurs actifs" icon="users"><x-slot:actions><a href="{{ route('logistics.drivers') }}">Voir tous</a></x-slot:actions><div class="ops-dashboard-drivers" data-live-driver-list>
@forelse($drivers->take(5) as $driver)
<div class="ops-dashboard-driver-row">
    @if($driver['vehicle_photo_url'] ?? null)
        <img class="ops-dashboard-driver-vehicle-photo" src="{{ $driver['vehicle_photo_url'] }}" alt="{{ $driver['vehicle'] }} de {{ $driver['name'] }}">
    @else
        <span class="ops-driver-initials">{{ collect(explode(' ',$driver['name']))->map(fn($part)=>mb_substr($part,0,1))->take(2)->implode('') }}</span>
    @endif
    <div class="ops-dashboard-driver-identity">
        <strong>{{ $driver['name'] }}</strong>
        <small><x-operations.icon name="pin"/>{{ $driver['zone'] }}</small>
    </div>
    <div class="ops-dashboard-driver-vehicle">
        <strong>{{ \Illuminate\Support\Str::headline(str_replace('_',' ',$driver['vehicle'])) }}</strong>
        <small>
            @if($driver['vehicle_color_hex'] ?? null)<i class="ops-dashboard-driver-color" style="--vehicle-color:{{ $driver['vehicle_color_hex'] }}"></i>@endif
            {{ ($driver['vehicle_color'] ?? null) ?: 'Couleur non détectée' }}
            @if($driver['vehicle_plate'] ?? null) · <b>{{ $driver['vehicle_plate'] }}</b>@endif
        </small>
    </div>
    <span class="ops-badge {{ $driver['status']==='En livraison' ? 'is-red' : 'is-green' }}">{{ $driver['status']==='En livraison' ? 'Occupé' : $driver['status'] }}</span>
</div>
@empty<p class="ops-empty">Aucun livreur actif.</p>
@endforelse
</div><div class="ops-network-counts">
@foreach([['occupés',$driversBusyCount,'red'],['disponibles',$driversAvailableCount,'green'],['hors ligne',$driversOfflineCount,'gray']] as [$label,$count,$tone])<div data-live-network="{{ $label }}"><x-operations.icon name="users" :class="'text-'.$tone"/><strong>{{ $count }}</strong><small>{{ $label }}</small></div>
@endforeach
</div></x-operations.panel>
<x-operations.panel title="Alertes opérationnelles" icon="bell"><x-slot:actions><a href="{{ route('logistics.incidents.index') }}">Voir toutes les alertes →</a></x-slot:actions>
@forelse($recentIncidents as $incident)<a class="ops-alert-row" href="{{ route('logistics.incidents.index') }}"><x-operations.icon name="warning" class="text-red"/><div><strong class="text-red">Incident ouvert</strong><p>{{ $incident->description }}</p><small>{{ $incident->created_at?->diffForHumans() }}</small></div></a>
@empty<p class="ops-footnote">Aucun incident ouvert.</p>
@endforelse @foreach($recentReturns as $return)<a class="ops-alert-row" href="{{ route('logistics.returns') }}"><x-operations.icon name="box" class="text-orange"/><div><strong>Retour en attente</strong><p>{{ $return->order?->order_number }}</p><small>{{ $return->created_at?->diffForHumans() }}</small></div></a>
@endforeach
<a class="ops-button ops-wide" href="{{ route('logistics.incidents.index') }}">Voir tous les incidents <x-operations.icon name="next"/></a></x-operations.panel></aside></div>
@push('scripts')<script defer src="{{ asset('js/logistics-dashboard-map.js') }}?v={{ @filemtime(public_path('js/logistics-dashboard-map.js')) ?: '20260915' }}"></script><script>window.OVANIE_DASHBOARD_LIVE_URL=@json(route('logistics.dashboard.live'));</script><script defer src="{{ asset('js/logistics-dashboard-live.js') }}?v={{ @filemtime(public_path('js/logistics-dashboard-live.js')) ?: '20260915' }}"></script>@endpush
@endsection
