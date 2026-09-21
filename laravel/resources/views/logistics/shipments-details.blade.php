@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title', 'Détail de la mission')
@php
    $missionGroup=$shipmentGroup;
    $m=\App\ViewModels\LogisticsOperationsData::mission($missionGroup);
    $order=$missionGroup['order'];
    $driver=$plannedAssignment?->driver;
@endphp
@section('content')
<x-operations.page-header title="Détail de la mission" subtitle="Vue complète de l’expédition et des opérations associées" current="Détail mission">
<a class="ops-button" href="{{ route('logistics.shipments') }}"><x-operations.icon name="back"/>Retour</a><button class="ops-button" data-print><x-operations.icon name="print"/>Imprimer la fiche</button><a class="ops-button ops-button-primary" href="{{ $m['assignmentUrl'] }}"><x-operations.icon name="settings"/>Planification avancée</a>
</x-operations.page-header>
<div class="ops-kpis ops-detail-kpis">
<x-operations.kpi icon="box" label="Mission" :value="$m['reference']"/><x-operations.kpi icon="box" label="Commande" :value="$m['order']" tone="orange"/><x-operations.kpi icon="warning" label="Statut" value="" tone="orange"><x-operations.badge :status="$m['status']"/></x-operations.kpi><x-operations.kpi icon="weight" label="Poids / Volume" :value="$m['weight'].' kg'" tone="purple"><small>{{ $m['volume'] ? $m['volume'].' m³' : 'Volume non renseigné' }}</small></x-operations.kpi><x-operations.kpi icon="moto" label="Véhicule requis" :value="$m['vehicle']" tone="green"/>
</div>
<div class="ops-detail-layout"><div class="ops-stack">
<x-operations.panel title="Informations commande" icon="list"><div class="ops-info-columns"><dl class="ops-facts"><dt>Commande</dt><dd>{{ $m['order'] }}</dd><dt>Client</dt><dd>{{ $m['client'] }}</dd><dt>Paiement</dt><dd><span class="ops-badge {{ $order?->payment_status==='paid' ? 'is-green' : 'is-orange' }}">{{ $order?->payment_method ?: 'Paiement' }} · {{ $order?->payment_status==='paid' ? 'Payé' : 'À confirmer' }}</span></dd></dl><dl class="ops-facts"><dt>Mission</dt><dd>{{ $m['reference'] }}</dd><dt>Statut</dt><dd><x-operations.badge :status="$m['status']"/></dd><dt>Destination</dt><dd>{{ $m['destination'] }}</dd></dl></div></x-operations.panel>
<x-operations.panel title="Produits transportés" icon="box">
@include('logistics.operations.products')@if(!$m['volume'])<p class="ops-footnote">Volume non renseigné dans la base de données.</p>
@endif
</x-operations.panel>
<x-operations.panel title="Points de collecte & destination" icon="pin">
@include('logistics.operations.mission-summary')</x-operations.panel>
<x-operations.panel title="Chronologie de la mission" icon="clock"><div class="ops-mission-timeline">
@foreach([['Commande payée',$order?->payment_status==='paid',$order?->created_at],['Mission créée',true,$missionGroup['representative']->created_at],['Affectation',$driver!==null,$plannedAssignment?->created_at],['Collecte en boutique',in_array($m['status'],['picked_up','in_transit','delivered']),null],['Livraison au client',$m['status']==='delivered',null]] as [$label,$done,$date])<div class="{{ $done ? 'is-complete' : '' }}"><span><x-operations.icon :name="$done ? 'check-circle' : 'clock'"/></span><strong>{{ $label }}</strong><small>{{ $date?->format('d/m/Y H:i') ?? ($done ? 'Effectuée' : 'En attente') }}</small></div>
@endforeach
</div></x-operations.panel>
</div><aside class="ops-stack">
<x-operations.panel title="Chauffeur" icon="user"><div class="ops-chauffeur"><span class="ops-avatar-large"><x-operations.icon name="user"/></span><div><h3>{{ $driver?->name ?: 'Non affecté' }}</h3><p>{{ $driver?->vehicle ?: 'Aucun livreur assigné à cette mission' }}</p><a class="ops-button" href="{{ $m['assignmentUrl'] }}"><x-operations.icon name="settings"/>Planifier l’affectation</a></div></div></x-operations.panel>
<x-operations.panel title="Véhicule & charge" icon="truck"><dl class="ops-facts ops-facts-rows"><dt>Véhicule requis</dt><dd>{{ $m['vehicle'] }}</dd><dt>Poids total</dt><dd>{{ $m['weight'] }} kg</dd><dt>Volume</dt><dd>{{ $m['volume'] ? $m['volume'].' m³' : 'Volume non renseigné' }}</dd><dt>Compatibilité</dt><dd>{{ $driver ? 'À vérifier' : 'En attente du livreur' }}</dd></dl></x-operations.panel>
<x-operations.panel title="Adresse de destination" icon="pin"><div class="ops-location-card"><h3>{{ $m['destination'] }}</h3><p>{{ $deliveryAddress ?: 'Adresse détaillée non renseignée' }}</p></div></x-operations.panel>
<x-operations.panel title="Documents / actions rapides" icon="list"><div class="ops-quick-actions"><button class="ops-button" onclick="window.print()"><x-operations.icon name="print"/>Imprimer la mission</button>
@if($missionGroup['shops']->first()?->whatsapp)<a class="ops-button" href="tel:{{ $missionGroup['shops']->first()->whatsapp }}"><x-operations.icon name="phone"/>Contacter la boutique</a>
@endif
<a class="ops-button" href="{{ $m['assignmentUrl'] }}"><x-operations.icon name="settings"/>Planification avancée</a></div></x-operations.panel>
</aside></div>
@endsection
