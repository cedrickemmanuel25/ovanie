@extends('layouts.logistics')
@section('title','Territoire | OVANIE Logistics')
@section('crumb','Territoire')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-territory.css') }}?v={{ @filemtime(public_path('css/logistics-territory.css')) ?: '20260911' }}">
@endpush

@section('content')
<main class="territory-screen territory-list-screen">
    <section class="territory-page-head">
        <div><h1>Zones de livraison</h1><p>Gestion des zones, des communes couvertes et des délais de livraison.</p></div>
        <div class="territory-head-actions"><a class="territory-outline-btn" href="{{ route('logistics.zones.export') }}"><x-operations.icon name="download"/> Exporter</a><button class="territory-primary-btn" type="button" data-open-zone-modal><x-operations.icon name="plus"/> Ajouter une zone</button></div>
    </section>

    @if($errors->any())<div class="territory-flash error"><strong>La zone n'a pas été enregistrée.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="territory-kpi-grid">
        <article><span class="territory-kpi-icon mint"><x-operations.icon name="pin"/></span><div><small>Zones actives</small><strong>{{ $stats['active'] }}</strong><em>{{ $stats['active_delta'] }}</em></div></article>
        <article><span class="territory-kpi-icon sky"><x-operations.icon name="pin"/></span><div><small>Communes couvertes</small><strong>{{ $stats['communes'] }}</strong><em>{{ $stats['communes_delta'] }}</em></div></article>
        <article><span class="territory-kpi-icon sky"><x-operations.icon name="clock"/></span><div><small>Délai moyen</small><strong>{{ $stats['avg_delay'] }}h</strong><em>{{ $stats['avg_delay_delta'] }}</em></div></article>
        <article><span class="territory-kpi-icon mint"><x-operations.icon name="chart"/></span><div><small>Livraisons (7 jours)</small><strong>{{ number_format($stats['activity'],0,',',' ') }}</strong><span>livraisons</span><em>{{ $stats['activity_delta'] }}</em></div></article>
    </section>

    <form class="territory-filter-row" method="GET">
        <label class="territory-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher une zone, une commune..."></label>
        <select name="status" onchange="this.form.submit()"><option value="">Tous les statuts</option><option value="active" @selected(request('status')==='active')>Actives</option><option value="inactive" @selected(request('status')==='inactive')>Inactives</option></select>
        <select name="delay" onchange="this.form.submit()"><option value="">Tous les délais</option>@foreach($delays as $delay)<option value="{{ $delay }}" @selected((string)request('delay')===(string)$delay)>{{ $delay }}h</option>@endforeach</select>
        <select name="region" onchange="this.form.submit()"><option value="">Toutes les régions</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(request('region')===$region)>{{ $region }}</option>@endforeach</select>
        <a href="{{ route('logistics.zones') }}"><x-operations.icon name="refresh"/> Réinitialiser les filtres</a>
    </form>

    <section class="territory-main-grid">
        <article class="territory-panel territory-zone-list">
            <h2>Liste des zones de livraison ({{ $zones->total() }})</h2>
            <div class="territory-table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nom de la zone</th><th>Région</th><th>Communes couvertes</th><th>Statut</th><th>Délai moyen</th><th>Activité (7j)</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($zones as $zone)
                    @php $metric = $metricsByZone[$zone->id] ?? []; $growth=(int)($metric['activity_growth_percent'] ?? 0); @endphp
                    <tr class="territory-clickable-row" data-zone-row-url="{{ route('logistics.zones.show',$zone) }}" tabindex="0">
                        <td><span class="territory-row-dot {{ $zone->is_active ? 'active' : '' }}"></span><a class="territory-code" href="{{ route('logistics.zones.show',$zone) }}">{{ $zone->code }}</a></td>
                        <td><a class="territory-zone-name-link" href="{{ route('logistics.zones.show',$zone) }}">{{ $zone->name }}</a></td><td>{{ $zone->region }}</td><td>{{ $zone->communes->count() }}</td>
                        <td><span class="territory-status {{ $zone->is_active?'active':'inactive' }}"><i></i>{{ $zone->is_active?'Active':'Inactive' }}</span></td>
                        <td>{{ $zone->average_delay_hours }}h</td>
                        <td><span class="territory-activity-count">{{ (int)($metric['activity_7d'] ?? 0) }}</span>@if(($metric['activity_7d'] ?? 0)>0)<span class="territory-growth"><x-operations.icon name="next"/> {{ $growth>0?'+':'' }}{{ $growth }} %</span>@else<span class="territory-zero">—</span>@endif</td>
                        <td><a class="territory-more" href="{{ route('logistics.zones.show',$zone) }}" aria-label="Voir {{ $zone->name }}"><x-operations.icon name="more"/></a></td>
                    </tr>
                    @empty<tr><td colspan="8" class="territory-empty">Aucune zone de livraison enregistrée.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if($zones->hasPages())<footer class="territory-table-footer"><span>Résultats {{ $zones->firstItem() }} à {{ $zones->lastItem() }} sur {{ $zones->total() }}</span>{{ $zones->links() }}</footer>@endif
        </article>

        <aside class="territory-right-column">
            <article class="territory-panel territory-map-card">
                <h3><span><span class="territory-round-icon"><x-operations.icon name="map"/></span> Carte des zones de livraison</span><small>Zones et communes enregistrées</small></h3>
                <div class="territory-live-map territory-index-map" data-territory-map="index" aria-label="Carte interactive des zones de livraison"></div>
            </article>

            <article class="territory-panel territory-selected-card">
                <h3><span><span class="territory-round-icon"><x-operations.icon name="list"/></span> Zone sélectionnée</span>@if($featured)<a href="{{ route('logistics.zones.show',$featured) }}"><x-operations.icon name="edit"/> Ouvrir</a>@endif</h3>
                @if($featured)
                <div class="selected-zone-title"><a href="{{ route('logistics.zones.show',$featured) }}">{{ $featured->code }}</a><strong>{{ $featured->name }}</strong><span class="territory-status {{ $featured->is_active?'active':'inactive' }}"><i></i>{{ $featured->is_active?'Active':'Inactive' }}</span></div>
                <div class="selected-zone-body">
                    <dl><dt>Région</dt><dd>{{ $featured->region }}</dd><dt>Communes couvertes</dt><dd>{{ $featured->communes->count() }} communes <a href="{{ route('logistics.zones.show',$featured) }}">Voir le détail</a></dd><dt>Délai moyen</dt><dd>{{ $featured->average_delay_hours }}h</dd><dt>Livraisons (7 jours)</dt><dd>{{ (int)($featuredMetrics['activity_7d'] ?? 0) }} livraison(s)</dd></dl>
                    <div class="principal-communes"><b>Communes</b>@forelse($featured->communes->take(5) as $commune)<span><x-operations.icon name="pin"/> {{ $commune->name }}</span>@empty<span>Aucune commune associée</span>@endforelse<a href="{{ route('logistics.zones.show',$featured) }}">Voir toutes les communes ({{ $featured->communes->count() }}) <x-operations.icon name="next"/></a></div>
                </div>
                @else
                <div class="territory-empty-card">Aucune zone de livraison n'est enregistrée.</div>
                @endif
            </article>
        </aside>
    </section>
</main>

@php
    $oldCommuneIds = collect(old('commune_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
    $oldCommunes = $communes->whereIn('id', $oldCommuneIds);
@endphp
<dialog class="territory-zone-dialog" id="zone-create-dialog" data-open-on-error="{{ $errors->any() ? '1' : '0' }}">
    <form method="POST" action="{{ route('logistics.zones.store') }}" id="zone-create-form">@csrf
        <header class="zone-dialog-head"><span class="zone-dialog-pin"><x-operations.icon name="pin"/></span><div><h2>Ajouter une zone de livraison</h2></div><button type="button" data-close-zone-modal><x-operations.icon name="close"/></button></header>
        <div class="zone-dialog-grid">
            <div class="zone-dialog-left">
                <section class="zone-modal-section">
                    <h3><span class="territory-round-icon"><x-operations.icon name="list"/></span> Informations générales</h3>
                    <label>Nom de la zone <sup>*</sup><input name="name" value="{{ old('name') }}" placeholder="Ex. Cocody - Zone Est" required></label>
                    <label>Région / district <sup>*</sup><select name="region" required>@foreach($regions as $region)<option value="{{ $region }}" @selected(old('region')===$region)>{{ $region }}</option>@endforeach @if($regions->isEmpty())<option value="District autonome d’Abidjan">District autonome d’Abidjan</option>@endif</select></label>
                    <div class="zone-modal-two"><div><span class="field-title">Statut</span><div class="status-choice"><label><input type="radio" name="is_active" value="1" @checked(old('is_active','1')==='1')><span>● Active</span></label><label><input type="radio" name="is_active" value="0" @checked(old('is_active')==='0')><span>○ Inactive</span></label></div></div><label>Délai moyen<select name="average_delay_hours">@foreach([12,24,48,72] as $delay)<option value="{{ $delay }}" @selected((int)old('average_delay_hours',24)===$delay)>{{ $delay }}h</option>@endforeach</select></label></div>
                    <label>Responsable opérationnel<select name="responsible_name"><option value="">Non affecté</option>@foreach($responsibles as $responsible)<option value="{{ $responsible->name }}" @selected(old('responsible_name')===$responsible->name)>{{ $responsible->name }}</option>@endforeach</select></label>
                </section>
                <section class="zone-modal-section commune-section">
                    <h3><span class="territory-round-icon"><x-operations.icon name="pin"/></span> Communes couvertes</h3>
                    <label class="commune-selector"><x-operations.icon name="search"/><select data-commune-select><option value="">Choisir une commune...</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" data-name="{{ $commune->name }}" data-lat="{{ $commune->latitude }}" data-lng="{{ $commune->longitude }}" @disabled($oldCommuneIds->contains((int)$commune->id))>{{ $commune->name }}</option>@endforeach</select></label>
                    <div class="commune-chips" data-commune-chips>@foreach($oldCommunes as $commune)<span data-id="{{ $commune->id }}" data-name="{{ $commune->name }}" data-lat="{{ $commune->latitude }}" data-lng="{{ $commune->longitude }}">{{ $commune->name }} <button type="button" aria-label="Retirer {{ $commune->name }}">×</button><input type="hidden" name="commune_ids[]" value="{{ $commune->id }}"></span>@endforeach</div>
                    @if($communes->isEmpty())<p class="territory-warning">Le référentiel des communes est vide. Ajoutez d'abord les communes OVANIE.</p>@endif
                </section>
                <section class="zone-modal-section"><h3><x-operations.icon name="clock"/> Horaires et règles</h3><div class="zone-modal-two"><label>Début de couverture<input type="time" name="coverage_start_time" value="{{ old('coverage_start_time','06:00') }}"></label><label>Fin de couverture<input type="time" name="coverage_end_time" value="{{ old('coverage_end_time','22:00') }}"></label></div><label>Note opérationnelle<textarea name="operational_note" maxlength="500" placeholder="Information utile à l'équipe logistique...">{{ old('operational_note') }}</textarea><small><span data-note-count>{{ mb_strlen(old('operational_note','')) }}</span>/500</small></label></section>
            </div>
            <div class="zone-dialog-right">
                <section class="zone-modal-section coverage-section"><h3><span class="territory-round-icon"><x-operations.icon name="map"/></span> Zone de couverture</h3><div class="territory-map-editor"><div class="territory-live-map territory-create-map" data-territory-map="create" aria-label="Carte de la zone de livraison"></div></div><input type="hidden" name="coverage_geojson" data-coverage-geojson value=""></section>
                <section class="zone-modal-section zone-summary"><h3><x-operations.icon name="chart"/> Résumé</h3><div class="summary-grid summary-grid-compact"><article><span class="territory-kpi-icon sky"><x-operations.icon name="pin"/></span><small>Communes sélectionnées</small><strong data-commune-count>0</strong><em>commune(s)</em></article><article><span class="territory-kpi-icon sky"><x-operations.icon name="clock"/></span><small>Délai moyen</small><strong data-delay-summary>{{ old('average_delay_hours',24) }}h</strong></article></div></section>
            </div>
        </div>
        <footer class="zone-dialog-footer"><button class="territory-outline-btn" type="button" data-close-zone-modal>Annuler</button><button class="territory-primary-btn" type="submit"><x-operations.icon name="save"/> Enregistrer la zone</button></footer>
    </form>
</dialog>

<script type="application/json" id="territory-map-data">{!! json_encode(['zones'=>$mapZones,'selected'=>$featured?->code,'communes'=>$communes->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'lat'=>$c->latitude!==null?(float)$c->latitude:null,'lng'=>$c->longitude!==null?(float)$c->longitude:null])->values()], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
@push('scripts')
<script src="{{ asset('js/logistics-territory.js') }}?v={{ @filemtime(public_path('js/logistics-territory.js')) ?: '20260911' }}" defer></script>
@endpush
