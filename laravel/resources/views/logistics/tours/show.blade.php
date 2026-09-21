@extends('layouts.logistics-operations')
@section('title', 'Détail de la tournée')
@section('page-class', 'ops-page-detail')
@php $detail = $operationsData['selected'] ?? \App\ViewModels\LogisticsOperationsData::tour($tour); @endphp
@section('content')
<x-operations.page-header title="Détail de la tournée" subtitle="Données réelles de la tournée : parcours, missions, livreur, véhicule, chargement et ETA" :tour="true" current="Détail de la tournée">
    <a class="ops-button" href="{{ route('logistics.tours.index', ['tour' => $detail['id']]) }}"><x-operations.icon name="back"/>Retour aux tournées</a>
    <button class="ops-button" type="button" data-print><x-operations.icon name="print"/>Imprimer</button>
    <a class="ops-button ops-button-primary" href="{{ $detail['trackingUrl'] }}"><x-operations.icon name="play"/>Suivre en temps réel</a>
</x-operations.page-header>


<div class="ops-kpis ops-detail-kpis">
    <x-operations.kpi icon="tour" label="Tournée" :value="$detail['reference']"/>
    <x-operations.kpi icon="check-circle" label="Statut" value="" tone="green"><x-operations.badge :status="$detail['status']"/></x-operations.kpi>
    <x-operations.kpi icon="user" label="Livreur" :value="$detail['driver']['name'] ?? 'Non affecté'"/>
    <x-operations.kpi icon="truck" label="Véhicule" :value="$detail['vehicle'].' · '.$detail['plate']"/>
    <x-operations.kpi icon="chart" label="Progression" :value="$detail['done'].' / '.$detail['total'].' livraisons'" tone="purple"><div class="ops-kpi-progress"><div class="ops-progress"><i style="width:{{ $detail['percent'] }}%"></i></div><b>{{ $detail['percent'] }} %</b></div></x-operations.kpi>
</div>

<div class="ops-detail-layout"><div class="ops-stack">
    <x-operations.panel title="Carte de la tournée" icon="map" class="ops-detail-map-panel">
        <x-operations.map :tour="$detail" mode="detail" :summary="false" :legend="true"/>
    </x-operations.panel>

    <x-operations.panel title="Ordre réel des arrêts" icon="route">
        <x-slot:actions><a class="ops-button ops-button-small" href="{{ route('logistics.tours.index', ['tour' => $detail['id']]) }}#optimization"><x-operations.icon name="swap"/>Optimisation & ordre</a></x-slot:actions>
        <div class="ops-table-scroll"><table class="ops-table ops-stops-table"><thead><tr><th>#</th><th>Lieu</th><th>Type</th><th>Missions</th><th>Trajet précédent</th><th>ETA</th><th>Statut</th></tr></thead><tbody>
            <tr><td><span class="ops-step is-green"><x-operations.icon name="play"/></span></td><td><strong>{{ $detail['start']['label'] }}</strong></td><td><span class="ops-type is-green"><x-operations.icon name="truck"/></span>Départ livreur</td><td>—</td><td>—</td><td>{{ $detail['departure'] }}</td><td><x-operations.badge status="done" label="Départ planifié"/></td></tr>
            @foreach($detail['stops'] as $stop)
                <tr><td><span class="ops-step {{ $stop['status']==='done'?'is-green':($stop['status']==='in_progress'?'is-blue':'is-gray') }}">{{ $loop->iteration }}</span></td><td><strong>{{ $stop['label'] }}</strong>@if($stop['address'])<small class="ops-table-subline">{{ $stop['address'] }}</small>@endif</td><td><span class="ops-type {{ $stop['kind']==='shop'?'is-green':'is-blue' }}"><x-operations.icon :name="$stop['kind']==='shop'?'warehouse':'pin'"/></span>{{ $stop['type'] }}</td><td>{{ $stop['missions'] }}</td><td>{{ $stop['distanceFromPrevious'] !== null ? number_format($stop['distanceFromPrevious'],1,',',' ').' km' : 'À confirmer' }}<small class="ops-table-subline">{{ \App\ViewModels\LogisticsOperationsData::duration($stop['durationFromPrevious']) }}</small></td><td>{{ $stop['time'] }}</td><td><x-operations.badge :status="$stop['status']==='in_progress'?'current':$stop['status']" :label="$stop['status']==='done' ? ($stop['kind']==='shop'?'Collecté':'Livré').(!empty($stop['completedTime'])?' à '.$stop['completedTime']:'') : null"/></td></tr>
            @endforeach
        </tbody></table></div>
    </x-operations.panel>

    <x-operations.panel title="Missions regroupées" icon="box">
        <div class="ops-table-scroll"><table class="ops-table ops-grouped-table"><thead><tr><th>Mission</th><th>Destination</th><th>Poids</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
            @forelse($detail['missions'] as $mission)
                <tr><td><strong>{{ $mission['reference'] }}</strong></td><td>{{ $mission['destination'] }}</td><td>{{ number_format($mission['weight'],1,',',' ') }} kg</td><td><x-operations.badge :status="$mission['status']==='current'?'current':$mission['status']"/></td><td><a class="ops-more" href="{{ $mission['detailsUrl'] }}" aria-label="Détail de {{ $mission['reference'] }}"><x-operations.icon name="more"/></a></td></tr>
            @empty<tr><td colspan="5" class="ops-empty">Aucune mission réelle liée à cette tournée.</td></tr>@endforelse
        </tbody></table></div>
    </x-operations.panel>
</div><aside class="ops-stack">
    <x-operations.panel title="Synthèse du parcours" icon="map">
        <div class="ops-trip-summary">
            @foreach([
                ['route','Distance routière',$detail['distance'] !== null ? number_format($detail['distance'],1,',',' ').' km' : 'À confirmer'],
                ['clock','Durée routière',\App\ViewModels\LogisticsOperationsData::duration($detail['duration'])],
                ['clock','Temps écoulé',\App\ViewModels\LogisticsOperationsData::duration($detail['elapsed'])],
                ['hourglass','Temps restant',\App\ViewModels\LogisticsOperationsData::duration($detail['remaining'])],
                ['check-circle','Livraisons effectuées',$detail['done'].' / '.$detail['total']],
                ['target','Moteur routier',$detail['routingProvider'] ?: 'À confirmer'],
            ] as [$icon,$label,$value])
                <div><span class="ops-square-icon tone-blue"><x-operations.icon :name="$icon"/></span><p><small>{{ $label }}</small><strong>{{ $value }}</strong></p></div>
            @endforeach
        </div>
        @if($detail['optimizedAt'])<p class="ops-panel-caption">Dernier calcul du parcours : {{ $detail['optimizedAt'] }}.</p>@endif
    </x-operations.panel>

    <x-operations.panel title="Progression de la tournée" icon="tour">
        <div class="ops-tour-progress"><div class="ops-progress"><i style="width:{{ $detail['percent'] }}%"></i></div><strong>{{ $detail['percent'] }} %</strong></div>
        <ol class="ops-progress-steps"><li class="is-complete"><span class="ops-step is-green"><x-operations.icon name="check-circle"/></span><strong>{{ $detail['start']['label'] }}</strong><small>{{ $detail['departure'] }}</small></li>@foreach($detail['stops'] as $stop)<li class="{{ $stop['status']==='done'?'is-complete':'' }}"><span class="ops-step {{ $stop['status']==='done'?'is-green':($stop['status']==='in_progress'?'is-blue':'is-gray') }}">@if($stop['status']==='done')<x-operations.icon name="check-circle"/>@else{{ $loop->iteration }}@endif</span><strong>{{ $stop['label'] }}</strong><small class="{{ $stop['status']==='in_progress'?'text-blue':'' }}">{{ $stop['time'] }} · {{ $stop['type'] }}</small></li>@endforeach</ol>
    </x-operations.panel>

    <x-operations.panel title="Livreur & véhicule" icon="user"><x-operations.driver :driver="$detail['driver']" :vehicle="$detail['vehicle']" :plate="$detail['plate']"/></x-operations.panel>
    <x-operations.panel title="Chargement de la tournée" icon="box"><x-operations.load :tour="$detail" :bars="true"/></x-operations.panel>
    <x-operations.panel title="Alertes / incidents" icon="warning"><a class="ops-alert" href="{{ route('logistics.incidents.index') }}"><x-operations.icon name="warning"/><div><strong>Incidents liés aux opérations</strong><p>Consulter les incidents enregistrés dans la base.</p></div><x-operations.icon name="next"/></a></x-operations.panel>
</aside></div>
@endsection
