@extends('layouts.logistics-operations')
@section('title','Suivi d’une mission')
@section('page-class','ops-page-tracking ops-tracking-mission')
@php
    $trackingTitle='Suivi d’une mission';$trackingSubtitle='Position chauffeur, trajet, ETA, signal GPS, collecte et destination';
    $trackedMission=$group?\App\ViewModels\LogisticsTrackingData::mission($group):null;
    $trackedDriver=$trackedMission['driverData']??null;
    $trackingMapMode='mission';
    $trackingContext='mission';
    $trackingPoints=$trackedMission['points']??[];
    // Le tracé représente le trajet du livreur (boutique <-> domicile client) :
    // tant qu'il n'a pas accepté la mission, il n'y a rien de réel à tracer.
    $trackingRoutes=($trackedMission['assignmentAccepted']??false)?array_map(fn($geometry)=>['geometry'=>$geometry,'late'=>false,'phase'=>$trackedMission['status']??null],$trackedMission['routeSegments']??[]):[];
@endphp
@section('content')
@include('logistics.operations.tracking-header')
@unless($trackedMission)<x-operations.panel title="Mission suivie"><p class="ops-empty">Aucune mission à suivre. <a href="{{ route('logistics.shipments') }}">Voir les expéditions</a></p></x-operations.panel>
@else
<div class="ops-kpis ops-tracking-kpis"><x-operations.kpi icon="box" label="Mission suivie" :value="$trackedMission['reference']"/><x-operations.kpi icon="truck" label="Statut" value="" tone="green"><x-operations.badge :status="$trackedMission['status']"/></x-operations.kpi><x-operations.kpi icon="clock" label="ETA client" :value="$trackedMission['eta']" tone="orange"/><x-operations.kpi icon="chart" label="Signal GPS" :value="$trackedDriver['quality']" tone="green"/><x-operations.kpi icon="warning" label="Retard actuel" :value="$trackedMission['delayMinutes']?'+ '.$trackedMission['delayMinutes'].' min':'Aucun'" tone="red"/></div>
<div class="ops-tracking-detail-grid"><div class="ops-tracking-detail-map">
@include('logistics.operations.tracking-map')</div><aside class="ops-stack"><x-operations.panel title="Détail de la mission" icon="list"><x-slot:actions><x-operations.badge :status="$trackedMission['status']"/></x-slot:actions><div class="ops-tracking-facts-columns"><dl><dt>Mission</dt><dd>{{ $trackedMission['reference'] }}</dd><dt>Commande</dt><dd>{{ $trackedMission['order'] }}</dd><dt>Client</dt><dd>{{ $trackedMission['client'] }}</dd><dt>Véhicule</dt><dd>{{ $trackedMission['vehicle'] }}</dd><dt>Poids</dt><dd>{{ $trackedMission['weight'] }} kg</dd></dl><dl><dt>ETA client</dt><dd>{{ $trackedMission['eta'] }}</dd><dt>Retard estimé</dt><dd class="text-red">{{ $trackedMission['delayMinutes'] }} min</dd><dt>Dernière mise à jour</dt><dd>{{ $trackedDriver['at']??'—' }}</dd><dt>Signal GPS</dt><dd class="text-green">{{ $trackedDriver['quality'] }}</dd></dl></div></x-operations.panel>
<x-operations.panel title="Collecte & destination" icon="pin"><div class="ops-tracking-journey"><div><h3><x-operations.icon name="warehouse"/>Collecte (Boutique)</h3><strong>{{ $trackedMission['pickup'] }}</strong><p>{{ $trackedMission['pickupZone'] }}</p><span class="ops-badge is-green">{{ $assignment?->picked_up_at?'Collecte effectuée':'Collecte prévue' }}</span><small>{{ $assignment?->picked_up_at?->format('H:i') ?? $assignment?->pickup_scheduled_at?->format('H:i') }}</small></div><x-operations.icon name="next"/><div><h3><x-operations.icon name="pin"/>Destination (Client)</h3><strong>{{ $trackedMission['destination'] }}</strong><p>{{ $trackedMission['client'] }}</p><x-operations.badge :status="$trackedMission['status']"/><small>ETA {{ $trackedMission['eta'] }}</small></div></div></x-operations.panel>
<x-operations.panel title="Actions rapides" icon="bolt">
@include('logistics.operations.tracking-quick-actions')</x-operations.panel></aside></div>
<div class="ops-tracking-bottom ops-tracking-mission-bottom"><x-operations.panel title="Chronique GPS" icon="clock"><div class="ops-tracking-timeline">
@php
    // Étape "en cours" = première étape non terminée (et non la 3e étape
    // codée en dur, qui affichait "Position actuelle" même quand rien
    // n'avait encore commencé).
    $currentTimelineIndex = collect($trackedMission['timeline'])->search(fn ($step) => ! $step['done']);
@endphp
@foreach($trackedMission['timeline'] as $index=>$step)<div class="{{ $step['done']?'is-done':'' }} {{ $index===$currentTimelineIndex?'is-current':'' }}"><span><x-operations.icon :name="$step['done']?'check-circle':'clock'"/></span><strong>{{ $step['label'] }}</strong><b>{{ $step['at']??'—' }}</b><small>{{ $index===$currentTimelineIndex?'En cours':($step['done']?'Effectuée':'En attente') }}</small></div>
@endforeach
</div></x-operations.panel><x-operations.panel title="Activités / événements" icon="bell">
@include('logistics.operations.tracking-events',['trackingEvents'=>$events])</x-operations.panel><x-operations.panel title="Alertes" icon="warning">
@if($trackedMission['delayed'])<div class="ops-tracking-delay-alert"><strong><x-operations.icon name="warning"/>ETA dépassée de {{ $trackedMission['delayMinutes'] }} min</strong><p>Le délai de livraison client est dépassé.</p><span><x-operations.icon name="pin"/>{{ $trackedMission['destination'] }}</span></div>
@elseif(!$trackedDriver['fresh'])<p class="ops-footnote">Position GPS indisponible ou ancienne. Dernière réception : {{ $trackedDriver['at']??'non renseignée' }}.</p>
@else<p class="ops-footnote text-green">Aucune alerte en cours.</p>
@endif
</x-operations.panel></div>
@endunless
@endsection
