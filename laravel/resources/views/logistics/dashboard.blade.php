@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title','Accueil')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-dashboard-map.css') }}?v={{ @filemtime(public_path('css/logistics-dashboard-map.css')) ?: '20260914' }}">@endpush
@php
    $pendingRows=$missionsToAssign->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $routeRows=$missionsEnRoute->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $mapMissions=isset($mapMissionGroups)?$mapMissionGroups->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all():array_merge($pendingRows,$routeRows);
    // Même boîte de dialogue d'affectation que la page Expéditions (bouton
    // « Affecter » du flux des missions) : on reconstruit les mêmes structures
    // (missions/livreurs) avec le même transformateur pour rester cohérent.
    $driverRows=($activeDrivers ?? collect())->map(fn($d)=>\App\ViewModels\LogisticsOperationsData::driver($d))->all();
    $assignmentMissionRows=collect($pendingRows)->values();
@endphp
@section('content')
<x-operations.page-header title="Accueil" subtitle="Vue d’ensemble de l’activité logistique en temps réel"><span class="ops-button"><x-operations.icon name="calendar"/>{{ now()->locale('fr')->translatedFormat('D d M Y') }}</span><a class="ops-button" href="{{ route('logistics.dashboard') }}"><x-operations.icon name="refresh"/>Actualiser</a></x-operations.page-header>
<div class="ops-kpis ops-dashboard-kpis" data-live-kpis>
@foreach([['box','Missions à affecter',$readyToAssignCount,'orange','missions prioritaires'],['truck','En route',$enRouteMissionsCount??count($routeRows),'blue','En cours de livraison'],['warning','Retards',$stats['retards']??0,'red','SLA dépassé'],['bell','Incidents ouverts',$incidentsOpenCount,'red','Nécessite une action'],['users','Livreurs actifs',$driversOnlineCount,'green','Sur '.$driversTotalCount.' livreurs au total'],['check-circle','Livraisons clôturées',$deliveredTodayCount,'green','Aujourd’hui']] as [$icon,$label,$value,$tone,$caption])<x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$tone"><small>{{ $caption }}</small></x-operations.kpi>
@endforeach
</div>
<div class="ops-dashboard-layout"><div class="ops-stack"><x-operations.panel title="Carte temps réel" icon="box"><x-slot:actions><span class="ops-badge is-green"><span data-live-online>{{ $driversOnlineCount }}</span> livreurs en ligne · {{ count($ovanieShops) }} boutiques géolocalisées</span></x-slot:actions>
@include('logistics.operations.dashboard-live-map')</x-operations.panel>
<x-operations.panel title="Flux des missions" icon="box"><x-slot:actions><a href="{{ route('logistics.shipments') }}">Voir toutes les missions →</a></x-slot:actions><h3 class="ops-subheading"><span class="text-orange">●</span>Missions à affecter ({{ count($pendingRows) }})</h3>
@include('logistics.operations.mission-flow',['flowMissions'=>$pendingRows,'showAssign'=>true])<h3 class="ops-subheading"><span class="text-blue">●</span>En route ({{ count($routeRows) }})</h3>
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
@push('dialogs')@include('logistics.operations.assignment-dialog')@endpush
@push('scripts')<script defer src="{{ asset('js/logistics-dashboard-map.js') }}?v={{ @filemtime(public_path('js/logistics-dashboard-map.js')) ?: '20260915' }}"></script><script>window.OVANIE_DASHBOARD_LIVE_URL=@json(route('logistics.dashboard.live'));</script><script defer src="{{ asset('js/logistics-dashboard-live.js') }}?v={{ @filemtime(public_path('js/logistics-dashboard-live.js')) ?: '20260915' }}"></script><script type="application/json" id="ops-mission-data">{!! json_encode(['missions'=>$mapMissions,'assignmentMissions'=>$assignmentMissionRows->all(),'drivers'=>$driverRows], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endpush
@endsection
