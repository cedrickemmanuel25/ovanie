@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title', 'Détail de la mission')
@php
    $missionGroup=$shipmentGroup;
    $m=\App\ViewModels\LogisticsOperationsData::mission($missionGroup);
    $order=$missionGroup['order'];
    $driver=$plannedAssignment?->driver;
    $phase=$m['operationalStatus'];
    $phaseRank=[
        'to_offer'=>0,
        'waiting_acceptance'=>1,
        'accepted_waiting_vendor'=>2,
        'ready_for_pickup'=>3,
        'collecting'=>4,
        'in_delivery'=>5,
        'delivered'=>6,
        'incident'=>-1,
    ];
    $rank=$phaseRank[$phase] ?? 0;
@endphp
@section('content')
<x-operations.page-header title="Détail de la mission" subtitle="Supervision de la réservation livreur, de la préparation vendeur, de la collecte et de la livraison client." current="Détail mission">
    <a class="ops-button" href="{{ route('logistics.shipments') }}"><x-operations.icon name="back"/>Retour aux missions</a>
    <button class="ops-button" data-print><x-operations.icon name="print"/>Imprimer</button>
    @if($m['assignmentAccepted'])<a class="ops-button ops-button-primary" href="{{ $m['trackingUrl'] }}"><x-operations.icon name="pin"/>Suivre la mission</a>@endif
</x-operations.page-header>

<div class="ops-alert" style="margin-bottom:16px">
    <x-operations.icon name="check-circle" class="text-green"/>
    <div><strong>Mission gérée automatiquement</strong><p>OVANIE Logistics propose la mission aux livreurs partenaires compatibles. La Logistique supervise le traitement ; elle ne sélectionne pas manuellement le livreur.</p></div>
</div>

<div class="ops-kpis ops-detail-kpis">
    <x-operations.kpi icon="box" label="Mission" :value="$m['reference']"/>
    <x-operations.kpi icon="box" label="Commande" :value="$m['order']" tone="orange"/>
    <x-operations.kpi icon="warning" label="Phase" value="" tone="orange"><x-operations.badge :status="$m['operationalStatus']" :label="$m['operationalLabel']"/></x-operations.kpi>
    <x-operations.kpi icon="clock" label="Préparation vendeur" :value="$m['preparationPercent'].' %'" tone="orange"><small>{{ $m['readyStops'] }} / {{ $m['totalStops'] }} point(s) prêt(s)</small></x-operations.kpi>
    <x-operations.kpi icon="moto" label="Véhicule requis" :value="$m['vehicle']" tone="green"><small>{{ $m['weight'] }} kg · {{ $m['volume'] }} m³</small></x-operations.kpi>
</div>

<div class="ops-detail-layout"><div class="ops-stack">
    <x-operations.panel title="Informations commande" icon="list">
        <div class="ops-info-columns">
            <dl class="ops-facts">
                <dt>Commande</dt><dd>{{ $m['order'] }}</dd>
                <dt>Client</dt><dd>{{ $m['client'] }}</dd>
                <dt>Paiement</dt><dd><span class="ops-badge {{ $order?->payment_status==='paid' ? 'is-green' : 'is-orange' }}">{{ $order?->payment_method ?: 'Paiement' }} · {{ $order?->payment_status==='paid' ? 'Payé' : 'À la livraison / à confirmer' }}</span></dd>
                <dt>Destination</dt><dd>{{ $deliveryAddress ?: $m['destination'] }}</dd>
            </dl>
            <dl class="ops-facts">
                <dt>Mission</dt><dd>{{ $m['reference'] }}</dd>
                <dt>Phase actuelle</dt><dd><x-operations.badge :status="$m['operationalStatus']" :label="$m['operationalLabel']"/></dd>
                <dt>Offres actives</dt><dd>{{ $m['offerCount'] }} offre(s) · {{ $m['offeredDriverCount'] }} livreur(s)</dd>
                <dt>Préparation</dt><dd>{{ $m['preparationPercent'] }} %</dd>
            </dl>
        </div>
    </x-operations.panel>

    <x-operations.panel title="Points de collecte & préparation vendeurs" icon="pin">
        <div class="ops-table-scroll"><table class="ops-table"><thead><tr><th>Point</th><th>Boutique</th><th>Adresse</th><th>Charge</th><th>Préparation</th></tr></thead><tbody>
        @forelse($missionGroup['pickup_stops'] as $index=>$stop)
            <tr><td>{{ $index+1 }}</td><td><strong>{{ $stop['shop']?->name ?: 'Boutique' }}</strong></td><td>{{ $stop['address'] }}</td><td>{{ $stop['weight'] }} kg · {{ $stop['volume'] }} m³</td><td><x-operations.badge :status="$stop['ready'] ? 'ready_for_pickup' : 'accepted_waiting_vendor'" :label="$stop['ready'] ? 'Prête' : 'En préparation'"/></td></tr>
        @empty<tr><td colspan="5" class="ops-empty">Aucun point de collecte identifié.</td></tr>@endforelse
        </tbody></table></div>
    </x-operations.panel>

    <x-operations.panel title="Produits transportés" icon="box">
        @include('logistics.operations.products')
        @if(!$m['volume'])<p class="ops-footnote">Volume non renseigné dans la base de données.</p>@endif
    </x-operations.panel>

    <x-operations.panel title="Chronologie opérationnelle" icon="clock">
        <div class="ops-mission-timeline">
            @foreach([
                ['Mission créée et proposée', $rank >= 1 || $m['offerCount'] > 0 || $m['reserved'], $missionGroup['representative']->created_at],
                ['Livreur partenaire réservé', $m['reserved'], $plannedAssignment?->accepted_at],
                ['Préparation vendeur terminée', $m['preparationPercent'] >= 100, null],
                ['Collecte démarrée', $rank >= 4, $plannedAssignment?->started_at],
                ['Départ vers le client', $rank >= 5, $order?->in_transit_at],
                ['Livraison terminée', $rank >= 6, $plannedAssignment?->delivered_at],
            ] as [$label,$done,$date])
                <div class="{{ $done ? 'is-complete' : '' }}"><span><x-operations.icon :name="$done ? 'check-circle' : 'clock'"/></span><strong>{{ $label }}</strong><small>{{ $date?->format('d/m/Y H:i') ?? ($done ? 'Effectuée' : 'En attente') }}</small></div>
            @endforeach
        </div>
    </x-operations.panel>
</div><aside class="ops-stack">
    <x-operations.panel title="Livreur partenaire" icon="user">
        <div class="ops-chauffeur"><span class="ops-avatar-large"><x-operations.icon name="user"/></span><div>
            <h3>{{ $driver?->name ?: ($m['operationalStatus']==='waiting_acceptance' ? 'En attente d’acceptation' : 'Aucun livreur réservé') }}</h3>
            <p>{{ $driver?->vehicle ?: $m['vehicle'].' requis' }}</p>
            @if($m['reserved'])<x-operations.badge status="ready_for_pickup" label="Mission réservée"/>@elseif($m['operationalStatus']==='waiting_acceptance')<x-operations.badge status="waiting_acceptance" :label="$m['offeredDriverCount'].' livreur(s) invité(s)'"/>@endif
        </div></div>
        @if($m['acceptedAt'])<p class="ops-footnote">Acceptée le {{ $m['acceptedAt'] }}.</p>@endif
    </x-operations.panel>

    <x-operations.panel title="Montants de la mission" icon="chart">
        <dl class="ops-facts ops-facts-rows">
            <dt>Livraison client</dt><dd>{{ $m['deliveryPriceAmount'] !== null ? number_format((float)$m['deliveryPriceAmount'],0,',',' ').' FCFA' : 'À confirmer' }}</dd>
            <dt>Gain livreur</dt><dd>{{ $m['driverNetAmount'] !== null ? number_format((float)$m['driverNetAmount'],0,',',' ').' FCFA' : 'À confirmer' }}</dd>
        </dl>
    </x-operations.panel>

    <x-operations.panel title="Véhicule & charge" icon="truck">
        <dl class="ops-facts ops-facts-rows"><dt>Véhicule requis</dt><dd>{{ $m['vehicle'] }}</dd><dt>Poids total</dt><dd>{{ $m['weight'] }} kg</dd><dt>Volume</dt><dd>{{ $m['volume'] ? $m['volume'].' m³' : 'Volume non renseigné' }}</dd><dt>Collectes</dt><dd>{{ $m['totalStops'] }} point(s)</dd></dl>
    </x-operations.panel>

    <x-operations.panel title="Actions de supervision" icon="list">
        <div class="ops-quick-actions">
            <button class="ops-button" onclick="window.print()"><x-operations.icon name="print"/>Imprimer la mission</button>
            @if($missionGroup['shops']->first()?->whatsapp)<a class="ops-button" href="tel:{{ $missionGroup['shops']->first()->whatsapp }}"><x-operations.icon name="phone"/>Contacter la boutique</a>@endif
            @if($m['assignmentAccepted'])<a class="ops-button" href="{{ $m['trackingUrl'] }}"><x-operations.icon name="pin"/>Ouvrir le suivi GPS</a>@endif
            @if($phase==='incident')<a class="ops-button ops-button-primary" href="{{ route('logistics.incidents.index') }}"><x-operations.icon name="warning"/>Traiter l’incident</a>@endif
        </div>
    </x-operations.panel>
</aside></div>
@endsection
