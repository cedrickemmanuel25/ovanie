@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title',request('view')==='map'?'Vue carte des livraisons':'Livraisons en cours')
@php
    $isMap=request('view')==='map';
    $rows=collect($missions->items())->map(fn($g)=>\App\ViewModels\LogisticsOperationsData::mission($g))->all();
    $mapMissions=$rows;
    $lateRows=collect($rows)->where('delayed',true);
@endphp
@section('content')
<x-operations.page-header :title="$isMap?'Vue carte des livraisons':'Livraisons en cours'" :subtitle="$isMap?'Supervision temps réel des missions actives, positions et retards':'Missions actuellement actives avec progression et retards'" current="Livraisons en cours">
@if($isMap)<a class="ops-button" href="{{ route('logistics.active-deliveries') }}"><x-operations.icon name="back"/>Retour à la liste</a>
@endif
<a class="ops-button" href="{{ request()->fullUrl() }}"><x-operations.icon name="refresh"/>Actualiser</a>
@unless($isMap)<a class="ops-button ops-button-primary" href="{{ route('logistics.active-deliveries',array_merge(request()->query(),['view'=>'map'])) }}"><x-operations.icon name="map"/>Vue carte</a>
@endunless</x-operations.page-header>
<div class="ops-kpis ops-live-kpis">
@foreach([['box','Missions actives','active','blue'],['truck','En transit','route','blue'],['warning','En retard','delayed','red'],['users','Livreurs actifs','drivers_active','green']] as [$icon,$label,$key,$tone])<x-operations.kpi :icon="$icon" :label="$label" :value="$stats[$key]" :tone="$tone"/>
@endforeach
</div>
@unless($isMap)
@include('logistics.operations.delivery-filters')
@endunless

<div class="ops-live-layout {{ $isMap?'ops-live-map-layout':'' }}"><div class="ops-stack">
@if($isMap)<div class="ops-full-map">
@include('logistics.operations.delivery-filters')

@include('logistics.operations.supervision-map')</div>
@else<x-operations.panel title="Missions actives" icon="box"><p class="ops-panel-caption">{{ $missions->total() }} missions actuellement en circulation</p>
@forelse($rows as $row)<article class="ops-live-card"><header><h3>{{ $row['reference'] }}</h3><x-operations.badge :status="$row['status']"/>
@if($row['delayed'])<span class="ops-badge is-red">ETA dépassée</span>
@endif
<a class="ops-button" href="{{ $row['trackingUrl'] }}">Voir le suivi</a></header><div class="ops-live-facts">
@foreach(['order'=>'Commande','client'=>'Client','destination'=>'Destination','products'=>'Produits','weight'=>'Poids','vehicle'=>'Véhicule requis','driver'=>'Chauffeur / affectation'] as $key=>$label)<div><small>{{ $label }}</small><p>{{ is_array($row[$key])?implode(' + ',$row[$key]):$row[$key] }}{{ $key==='weight'?' kg':'' }}</p></div>
@endforeach @if($row['delayed'])<div class="ops-delay"><small>Retard estimé :</small><strong>{{ $row['delayMinutes']!==null?$row['delayMinutes'].' min':'À contrôler' }}</strong></div>
@endif
</div><div class="ops-mission-timeline">
@foreach($row['timeline'] ?? [] as $step)<div class="{{ ($step['state'] ?? 'pending')==='complete'?'is-complete':((($step['state'] ?? 'pending')==='active')?'is-active':'') }}"><span><x-operations.icon name="check-circle"/></span><strong>{{ $step['label'] }}</strong><small>{{ $step['detail'] }}</small></div>
@endforeach
</div><div class="ops-live-progress"><div class="ops-progress"><i style="width:{{ $row['progress'] }}%"></i></div><span>{{ $row['progress'] }} %</span></div></article>
@empty<p class="ops-empty">Aucune mission ne correspond aux filtres.</p>
@endforelse<x-operations.pagination :paginator="$missions"/></x-operations.panel>
@endif
</div>
<aside class="ops-stack">
@if($isMap)<x-operations.panel title="Missions sur la carte" icon="pin"><div class="ops-map-tabs"><a class="ops-button {{ !request('delay')?'ops-button-primary':'' }}" href="{{ request()->fullUrlWithQuery(['delay'=>null]) }}">Toutes</a><a class="ops-button" href="{{ request()->fullUrlWithQuery(['delay'=>'late']) }}">En retard</a></div>
@foreach($rows as $row)<article class="ops-map-mission"><strong>{{ $row['reference'] }}</strong><x-operations.badge :status="$row['status']"/><p>{{ $row['order'] }}</p><p>{{ $row['client'] }} · {{ $row['destination'] }}</p><footer><span>{{ $row['weight'] }} kg · {{ $row['vehicle'] }}</span><button class="ops-button" data-center-mission="{{ $row['id'] }}">Centrer sur la carte</button></footer></article>
@endforeach
</x-operations.panel>
@endif
<x-operations.panel :title="$isMap?'État réseau / supervision':'Synthèse temps réel'" icon="chart"><div class="ops-summary-grid">
@foreach([['box',$stats['active'],'missions actives'],['truck',$stats['route'],'en transit'],['users',$stats['drivers_active'],'livreurs actifs'],['warehouse',count($ovanieShops),'boutiques OVANIE']] as [$icon,$value,$label])<div><x-operations.icon :name="$icon"/><strong>{{ $value }}</strong><small>{{ $label }}</small></div>
@endforeach
</div><p class="ops-sync"><x-operations.icon name="clock"/>Dernière actualisation : {{ now()->format('H:i') }}</p></x-operations.panel>
<x-operations.panel title="Retards à traiter" icon="warning">
@forelse($lateRows as $row)<a class="ops-delay-card" href="{{ $row['trackingUrl'] }}"><strong>{{ $row['reference'] }}</strong><span class="ops-badge is-red">{{ $row['delayMinutes']!==null?'+ '.$row['delayMinutes'].' min':'ETA dépassée' }}</span><p><x-operations.icon name="pin"/>{{ $row['destination'] }}</p></a>
@empty<p class="ops-empty">Aucun retard à traiter.</p>
@endforelse
<a class="ops-button ops-wide" href="{{ route('logistics.active-deliveries',['status'=>'delayed']) }}">Voir tous les retards <x-operations.icon name="next"/></a></x-operations.panel></aside></div>
@endsection
