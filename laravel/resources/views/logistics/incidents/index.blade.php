@extends('layouts.logistics-operations')
@section('title','Incidents')
@section('page-class','ops-directory')
@include('logistics.directory.assets')

@section('content')
@php
use App\ViewModels\LogisticsDirectoryData as D;

$open = $allIncidents->where('status','open')->count();
$progress = $allIncidents->whereIn('status',['in_progress','rescheduled'])->count();
$resolved = $allIncidents->whereIn('status',['resolved','closed'])->count();
$critical = $allIncidents->whereIn('severity',['critical','high'])->whereNotIn('status',['resolved','closed'])->count();
$automatic = $allIncidents->filter(fn($incident) => data_get($incident->meta,'signal_source') === 'system_alert' || $incident->reported_by_type === 'system_alert')->whereNotIn('status',['resolved','closed'])->count();
$vendorReviewTypes = ['produit_endommage','produit_incomplet','quantite_incorrecte','probleme_chargement','litige_client'];
@endphp

<x-operations.directory-header
    title="Incidents"
    section="Incidents"
    subtitle="Réception et traitement des incidents remontés par les clients, vendeurs et livreurs"
/>

<div class="directory-kpis four">
@foreach([
    ['alert','Incidents ouverts',$open,'red'],
    ['alert','Priorité élevée',$critical,'red'],
    ['clock','Alertes automatiques',$automatic,'orange'],
    ['check','Incidents résolus',$resolved,'green']
] as [$icon,$label,$value,$tone])
    <x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$tone"/>
@endforeach
</div>

<div class="incident-inbox-note">
    <x-operations.icon name="bolt"/>
    <span><strong>Centre des incidents.</strong> Les dossiers ci-dessous proviennent des signalements réels des livreurs, des vendeurs/boutiques ou de l’assistance client. La Logistique qualifie, coordonne et communique ensuite avec la bonne partie.</span>
</div>

<form class="directory-filters">
    <div class="directory-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher un incident, une mission, une commande, un livreur…" aria-label="Rechercher un incident"></div>
    <label>Statut<select name="status" onchange="this.form.submit()"><option value="">Tous les statuts</option>
        @foreach(['open','in_progress','resolved','rescheduled'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ D::incidentStatus($s)[0] }}</option>@endforeach
    </select></label>
    <label>Priorité<select name="severity" onchange="this.form.submit()"><option value="">Toutes les priorités</option>
        @foreach(['critical','high','medium','low'] as $s)<option value="{{ $s }}" @selected(request('severity')===$s)>{{ D::severity($s)[0] }}</option>@endforeach
    </select></label>
    <label>Type<select name="type" onchange="this.form.submit()"><option value="">Tous les types</option>
        @foreach($types as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach
    </select></label>
    <label>Période<input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()"></label>
    <a href="{{ route('logistics.incidents.index') }}"><x-operations.icon name="refresh"/>Réinitialiser les filtres</a>
</form>

<div class="directory-grid">
    <x-operations.panel :title="'Liste des incidents ('.$incidents->total().')'" icon="alert" class="directory-table-panel">
        <div class="directory-table-wrap">
            <table class="directory-table incident-inbox-table">
                <thead><tr>
                    @foreach(['Incident','Reçu le','Signalé par','Problème','Mission / commande','Livreur / véhicule','Priorité','Statut','Traitement'] as $h)<th>{{ $h }}</th>@endforeach
                </tr></thead>
                <tbody>
                @forelse($incidents as $incident)
                    @php
                        [$severity,$color] = D::severity($incident->severity);
                        [$status,$tone] = D::incidentStatus($incident->status);
                        $sourceCode = data_get($incident->meta,'signal_source') ?: $incident->reported_by_type;
                        $isDriver = in_array($sourceCode,['ovanie_driver','ovanie_driver_app','driver','delivery_driver'],true);
                        $isSeller = in_array($sourceCode,['seller_driver','seller_driver_app','seller','vendor','seller_app','vendor_app'],true);
                        $isClient = in_array($sourceCode,['support_ai','support_transfer','client','customer','client_app'],true);

                        $actorRole = $isDriver ? 'Livreur' : ($isSeller ? 'Vendeur / boutique' : ($isClient ? 'Client' : 'Système'));
                        $actorName = data_get($incident->meta,'source_actor_name');
                        if (!$actorName && $isDriver) $actorName = $incident->orderItem?->latestDeliveryAssignment?->driver?->name;
                        if (!$actorName && $isClient) $actorName = $incident->order?->delivery_recipient_name ?: $incident->order?->customer_name ?: $incident->order?->client?->name;
                        if (!$actorName && $isSeller) $actorName = $incident->orderItem?->product?->shop?->display_name ?: $incident->orderItem?->product?->shop?->name;
                        if (!$actorName && $sourceCode === 'system_alert') $actorName = 'Supervision automatique OVANIE';
                        $actorName = $actorName ?: 'Signalant identifié par la source';

                        $typeLabel = $types[$incident->incident_type] ?? ucfirst(str_replace('_',' ',(string)$incident->incident_type));
                        $mission = data_get($incident->meta,'mission_number') ?: $incident->orderItem?->latestDeliveryAssignment?->mission_number ?: $incident->shipment?->tracking_number ?: 'Mission non renseignée';
                        $orderNumber = $incident->order?->order_number ?: data_get($incident->meta,'order_number') ?: 'Commande non renseignée';
                        $driverName = $incident->orderItem?->latestDeliveryAssignment?->driver?->name ?: data_get($incident->meta,'driver_name') ?: 'Non affecté';
                        $vehicle = $incident->orderItem?->latestDeliveryAssignment?->driver?->vehicle ?: data_get($incident->meta,'driver_vehicle') ?: 'Véhicule non renseigné';

                        $clientNotified = data_get($incident->meta,'customer_notification_sent_at') ?: data_get($incident->meta,'customer_notification_requested_at');
                        $vendorNotified = data_get($incident->meta,'vendor_notification_sent_at');
                        $canNotifyClient = ($isDriver || $isSeller) && !in_array($incident->status,['resolved','closed'],true);
                        $canForwardVendor = $isClient && in_array($incident->incident_type,$vendorReviewTypes,true) && !in_array($incident->status,['resolved','closed'],true);
                    @endphp
                    <tr>
                        <td><a class="directory-ref" href="{{ route('logistics.incidents.show',$incident) }}">{{ D::ref($incident,'INC') }}</a></td>
                        <td>{{ $incident->occurred_at?->format('d/m/Y') }}<small>{{ $incident->occurred_at?->format('H:i') }}</small></td>
                        <td><div class="incident-actor"><strong>{{ $actorRole }}</strong><span>{{ $actorName }}</span></div></td>
                        <td><a class="incident-problem-link" href="{{ route('logistics.incidents.show',$incident) }}"><strong>{{ $typeLabel }}</strong><span>{{ Str::limit($incident->description,62) }}</span></a></td>
                        <td><div class="incident-mission-ref"><strong>{{ $mission }}</strong><span>{{ $orderNumber }}</span></div></td>
                        <td><div class="incident-resource"><strong>{{ $driverName }}</strong><span>{{ $vehicle }}</span></div></td>
                        <td><x-operations.tag :tone="$color">{{ ucfirst((string)$severity) }}</x-operations.tag></td>
                        <td><x-operations.tag :tone="$tone">{{ ucfirst((string)$status) }}</x-operations.tag></td>
                        <td>
                            <div class="incident-treatment-actions">
                                @if($canNotifyClient)
                                    @if($clientNotified)
                                        <span class="incident-action-state is-done">Client informé</span>
                                    @else
                                        <form method="post" action="{{ route('logistics.incidents.update',$incident) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="incident_action" value="notify_client">
                                            <button class="incident-row-action is-client" type="submit">Informer le client</button>
                                        </form>
                                    @endif
                                @elseif($canForwardVendor)
                                    @if($vendorNotified)
                                        <span class="incident-action-state is-done">Vendeur informé</span>
                                    @else
                                        <form method="post" action="{{ route('logistics.incidents.update',$incident) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="incident_action" value="forward_vendor">
                                            <button class="incident-row-action is-vendor" type="submit">Transmettre au vendeur</button>
                                        </form>
                                    @endif
                                @else
                                    <a class="incident-row-action" href="{{ route('logistics.incidents.show',$incident) }}">Traiter le dossier</a>
                                @endif
                                <a class="incident-open-link" href="{{ route('logistics.incidents.show',$incident) }}" aria-label="Ouvrir le dossier"><x-operations.icon name="eye"/></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="directory-empty">Aucun incident réel reçu depuis les applications client, vendeur ou livreur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-operations.directory-pagination :paginator="$incidents"/>
    </x-operations.panel>

    <aside class="directory-stack">
        <x-operations.panel title="Répartition par statut" icon="chart">
            @include('logistics.directory.donut',['segments'=>[['Ouverts',$open,'red'],['En cours',$progress,'blue'],['Résolus',$resolved,'green']],'unit'=>'incidents'])
        </x-operations.panel>
        <x-operations.panel title="Répartition par priorité" icon="chart">
            @php($rows=collect(['critical','high','medium','low'])->map(fn($s)=>[D::severity($s)[0],$allIncidents->where('severity',$s)->count(),D::severity($s)[1],$allIncidents->count()])->all())
            <x-operations.directory-bars :rows="$rows"/>
        </x-operations.panel>
        <x-operations.panel title="Derniers incidents reçus" icon="clock">
            @foreach($allIncidents->take(5) as $i)
                <a class="directory-follow" href="{{ route('logistics.incidents.show',$i) }}"><span>{{ $i->occurred_at?->format('H:i') }}</span><span>{{ $types[$i->incident_type] ?? ucfirst(str_replace('_',' ',(string)$i->incident_type)) }}</span><x-operations.tag :tone="D::severity($i->severity)[1]">{{ ucfirst((string)D::severity($i->severity)[0]) }}</x-operations.tag></a>
            @endforeach
        </x-operations.panel>
    </aside>
</div>
@endsection
