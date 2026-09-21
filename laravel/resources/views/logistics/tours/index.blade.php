@extends('layouts.logistics-operations')
@section('title', 'Tournées')
@section('page-class', 'ops-page-tours')
@php
    $tourRows = $operationsData['tours'] ?? $tours->map(fn ($tour) => \App\ViewModels\LogisticsOperationsData::tour($tour))->all();
    $selection = $operationsData['selected'] ?? collect($tourRows)->firstWhere('id', isset($selected) ? $selected->id : (int) request('tour')) ?? ($tourRows[0] ?? null);
    $numbers = $operationsData['stats'] ?? $stats;
    $tourCount = max(1, count($tourRows));
@endphp
@section('content')
<x-operations.page-header title="Tournées" subtitle="Regroupement de missions réelles, optimisation routière et suivi du livreur" :tour="true">
    <button class="ops-button" type="button" data-export-tours><x-operations.icon name="download"/>Exporter</button>
    @if($selection)<a class="ops-button" href="#selected-tour-map"><x-operations.icon name="map"/>Voir la carte</a>@endif
    <a class="ops-button ops-button-primary" href="{{ route('logistics.tours.create') }}"><x-operations.icon name="plus"/>Créer une tournée</a>
</x-operations.page-header>

@if($errors->any())<div class="ops-form-errors" role="alert"><strong>Action impossible.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="ops-kpis">
    <x-operations.kpi icon="tour" label="Tournées du jour" :value="$numbers['today']"/>
    <x-operations.kpi icon="check-circle" label="Tournées en cours" :value="$numbers['in_progress']" :detail="round($numbers['in_progress']/$tourCount*100).' %'" tone="green"/>
    <x-operations.kpi icon="clock" label="Tournées planifiées" :value="$numbers['planned']" :detail="round($numbers['planned']/$tourCount*100).' %'" tone="orange"/>
    <x-operations.kpi icon="warning" label="Tournées en retard" :value="$numbers['late']" :detail="round($numbers['late']/$tourCount*100).' %'" tone="red"/>
    <x-operations.kpi icon="flag" label="Livraisons totales" :value="$numbers['deliveries_total']" tone="purple"/>
</div>

<div class="ops-filters" data-tour-filters>
    <label class="ops-search"><x-operations.icon name="search"/><input placeholder="Rechercher une tournée, un livreur, une commune..." aria-label="Rechercher une tournée" data-filter="search"></label>
    <select data-filter="status" aria-label="Statut"><option value="">Tous les statuts</option>@foreach(['in_progress'=>'En cours','planned'=>'Planifiée','late'=>'En retard','done'=>'Terminée'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
    <label class="ops-date-filter"><x-operations.icon name="calendar"/><input type="text" placeholder="Date" aria-label="Date" data-filter="date" onfocus="this.type='date'" onblur="if(!this.value)this.type='text'"></label>
    @foreach(['driver'=>'Livreur','vehicle'=>'Véhicule','zone'=>'Zone'] as $key=>$label)
        <select data-filter="{{ $key }}" aria-label="{{ $label }}"><option value="">{{ $label }} : tous</option>@foreach(collect($tourRows)->map(fn ($row) => $key==='driver' ? ($row['driver']['name'] ?? 'Non affecté') : ($row[$key] ?? ''))->filter()->unique() as $value)<option>{{ $value }}</option>@endforeach</select>
    @endforeach
    <button class="ops-reset" type="button" data-reset-tours><x-operations.icon name="refresh"/>Réinitialiser les filtres</button>
</div>

<div class="ops-tours-layout">
    <div class="ops-stack">
        <x-operations.panel :title="'Liste des tournées ('.count($tourRows).')'" icon="tour">
            <p class="ops-panel-caption">Cliquez sur n’importe quelle ligne pour afficher cette tournée, sa carte, son chargement et son livreur.</p>
            <div class="ops-table-scroll"><table class="ops-table ops-tour-table"><thead><tr><th>#</th><th>Livreur</th><th>Véhicule</th><th>Livraisons</th><th>Commune(s)</th><th>Statut</th><th>Départ</th><th>Actions</th></tr></thead><tbody>
                @forelse($tourRows as $row)
                    <tr class="{{ $row['id']===($selection['id']??null) ? 'is-selected' : '' }}" data-tour-row="{{ $row['id'] }}" data-tour-select-url="{{ $row['selectUrl'] }}" tabindex="0" aria-label="Afficher la tournée {{ $row['reference'] }}">
                        <td><strong class="ops-reference">{{ $row['reference'] }}</strong></td>
                        <td><div class="ops-table-driver"><span class="ops-avatar"><x-operations.icon name="user"/></span><div>{{ $row['driver']['name'] ?? 'Non affecté' }}<span class="ops-rating"><x-operations.icon name="star"/>{{ $row['driver']['rating'] ?? '—' }}</span></div></div></td>
                        <td><span class="ops-vehicle-inline"><x-operations.icon :name="str_contains(mb_strtolower($row['vehicle']),'moto')?'moto':'truck'"/><span>{{ $row['vehicle'] }}<small>{{ $row['plate'] }}</small></span></span></td>
                        <td>{{ $row['total'] }}</td>
                        <td class="ops-communes">{{ $row['communes'] ?: 'À préciser' }}</td>
                        <td><x-operations.badge :status="$row['status']==='done'?'finished':$row['status']"/></td>
                        <td>{{ $row['departure'] }}</td>
                        <td><a class="ops-button ops-button-small" href="{{ $row['detailsUrl'] }}">Détails</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ops-empty">Aucune tournée réelle enregistrée. Les anciennes tournées de démonstration ne sont pas affichées.</td></tr>
                @endforelse
                <tr hidden data-no-tours><td colspan="8" class="ops-empty">Aucune tournée ne correspond aux filtres.</td></tr>
            </tbody></table></div>
        </x-operations.panel>

        @if($selection)
            <x-operations.panel title="Optimisation de parcours" icon="route" class="ops-route-optimization-card" id="optimization">
                <div class="ops-route-optimization-head">
                    <div class="ops-route-status-block">
                        <span class="ops-square-icon {{ $selection['routeComplete'] ? 'tone-green' : 'tone-orange' }}"><x-operations.icon :name="$selection['routeComplete']?'check-circle':'warning'"/></span>
                        <div><small>État du parcours</small><strong>{{ $selection['routeComplete'] ? ($selection['optimized'] ? 'Parcours optimisé' : 'Ordre manuel recalculé') : 'Parcours à recalculer' }}</strong><span>{{ $selection['optimizedAt'] ? 'Calculé le '.$selection['optimizedAt'] : 'Aucun calcul routier complet enregistré' }}{{ $selection['routingProvider'] ? ' · '.$selection['routingProvider'] : '' }}</span></div>
                    </div>
                    <div class="ops-route-actions">
                        @if($selection['status'] === 'planned')
                            <form method="post" action="{{ $selection['reoptimizeUrl'] }}">@csrf<button class="ops-button ops-button-primary ops-button-small" type="submit"><x-operations.icon name="refresh"/>Réoptimiser le parcours</button></form>
                            <button class="ops-button ops-button-small" type="button" data-toggle-route-order @disabled(!$selection['canEditOrder'])><x-operations.icon name="swap"/>Modifier l’ordre</button>
                        @else
                            <span class="ops-route-lock"><x-operations.icon name="help"/>Ordre verrouillé après démarrage</span>
                        @endif
                    </div>
                </div>
                <div class="ops-route-metrics">
                    <div><small>Livraisons</small><strong>{{ $selection['total'] }}</strong></div>
                    <div><small>Arrêts physiques</small><strong>{{ count($selection['stops']) }}</strong></div>
                    <div><small>Distance routière</small><strong>{{ $selection['distance'] !== null ? number_format($selection['distance'],1,',',' ').' km' : 'À confirmer' }}</strong></div>
                    <div><small>Durée routière</small><strong>{{ \App\ViewModels\LogisticsOperationsData::duration($selection['duration']) }}</strong></div>
                </div>
                <form method="post" action="{{ $selection['reorderUrl'] }}" class="ops-route-order-editor" hidden data-route-order-editor>
                    @csrf
                    <div class="ops-route-order-editor-head"><div><strong>Ordre des arrêts</strong><span>Déplacez les arrêts avec les boutons. Une livraison ne peut pas passer avant sa collecte.</span></div><button class="ops-button ops-button-small" type="button" data-cancel-route-order>Fermer</button></div>
                    <ol class="ops-route-order-list" data-route-order-list>
                        @foreach($selection['stops'] as $stop)
                            <li data-order-stop data-stop-key="{{ $stop['key'] }}"><input type="hidden" name="stop_order[]" value="{{ $stop['key'] }}"><span class="ops-step {{ $stop['kind']==='shop'?'is-green':'is-blue' }}" data-order-number>{{ $loop->iteration }}</span><div><strong>{{ $stop['label'] }}</strong><small>{{ $stop['type'] }} · ETA {{ $stop['time'] }}</small></div><div class="ops-order-buttons"><button type="button" data-order-up aria-label="Monter {{ $stop['label'] }}">↑</button><button type="button" data-order-down aria-label="Descendre {{ $stop['label'] }}">↓</button></div></li>
                        @endforeach
                    </ol>
                    <div class="ops-route-order-footer"><span>Les distances et ETA seront recalculés avec le moteur routier après l’enregistrement.</span><button type="submit" class="ops-button ops-button-primary"><x-operations.icon name="save"/>Enregistrer l’ordre et recalculer</button></div>
                </form>
            </x-operations.panel>

            <div class="ops-bottom-pair">
                <x-operations.panel title="Chargement de la tournée" icon="box"><x-operations.load :tour="$selection" :bars="true"/></x-operations.panel>
                <x-operations.panel title="Livreur & véhicule" icon="user"><x-operations.driver :driver="$selection['driver']" :vehicle="$selection['vehicle']" :plate="$selection['plate']"/></x-operations.panel>
            </div>
        @endif
    </div>

    <div class="ops-stack">
        @if($selection)
            <x-operations.panel id="selected-tour-map" class="ops-selected-tour">
                <div class="ops-selected-heading"><h2><x-operations.icon name="order"/>{{ $selection['reference'] }}</h2><x-operations.badge :status="$selection['status']"/>@if($selection['elapsed']!==null)<small>Démarrée il y a {{ \App\ViewModels\LogisticsOperationsData::duration($selection['elapsed']) }}</small>@endif<a class="ops-button ops-button-navy ops-button-small" href="{{ $selection['trackingUrl'] }}"><x-operations.icon name="pin"/>Suivre en temps réel</a></div>
                <div class="ops-selected-progress"><div class="ops-progress"><i style="width:{{ $selection['percent'] }}%"></i></div><div><span>{{ $selection['done'] }} / {{ $selection['total'] }} livraisons effectuées</span><strong>{{ $selection['percent'] }} %</strong></div></div>
                <x-operations.map :tour="$selection"/>
            </x-operations.panel>
            <x-operations.panel title="Détail de la tournée" icon="tour" class="ops-stops-panel">
                <x-slot:actions><a class="ops-button ops-button-primary ops-button-small" href="{{ $selection['detailsUrl'] }}">Ouvrir la fiche complète</a></x-slot:actions>
                <x-operations.stops :tour="$selection"/>
            </x-operations.panel>
        @else
            <x-operations.panel title="Détail de la tournée" icon="tour"><p class="ops-empty">Créez ou sélectionnez une tournée réelle pour afficher le suivi.</p></x-operations.panel>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script type="application/json" id="ops-tour-data">{!! json_encode($tourRows, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endpush
