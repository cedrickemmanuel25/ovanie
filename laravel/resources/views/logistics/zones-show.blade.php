@extends('layouts.logistics')
@section('title','Détail zone | OVANIE Logistics')
@section('crumb','Territoire › Détail zone')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-territory.css') }}?v={{ @filemtime(public_path('css/logistics-territory.css')) ?: '20260911' }}">
@endpush

@section('content')
@php
    $activity7d=(int)($zoneMetrics['activity_7d'] ?? 0);
    $activityGrowth=(int)($zoneMetrics['activity_growth_percent'] ?? 0);
    $drivers=$zoneMetrics['drivers'] ?? [];
    $driversAvailable=(int)($zoneMetrics['drivers_available'] ?? 0);
    $driversTotal=(int)($zoneMetrics['drivers_total'] ?? 0);
    $currentLoad=(int)($zoneMetrics['current_load_percent'] ?? 0);
    $averageDistance=(float)($zoneMetrics['average_distance_km'] ?? 0);
    $sla=(int)($zoneMetrics['sla_percent'] ?? 0);
    $density=(string)($zoneMetrics['delivery_density'] ?? 'Faible');
    $selectedCommuneIds=collect(old('commune_ids', $zone->communes->pluck('id')->all()))->map(fn ($id)=>(int)$id)->filter()->unique()->values();
    $selectedCommunes=$communes->whereIn('id',$selectedCommuneIds);
    $communeBreakdownById=collect($communeBreakdown ?? [])->keyBy(fn ($row)=>$row['commune']->id);
@endphp
<main class="territory-screen territory-detail-screen">
    <section class="territory-page-head">
        <div><h1>Détail de la zone</h1><p>Couverture, activité, charge et disponibilité des livreurs.</p></div>
        <div class="territory-head-actions"><a class="territory-outline-btn" href="{{ route('logistics.zones') }}"><x-operations.icon name="back"/> Retour à la liste</a><button class="territory-outline-btn" type="button" data-edit-zone-coverage><x-operations.icon name="edit"/> Modifier la couverture</button><button class="territory-primary-btn" form="zone-detail-form" type="submit"><x-operations.icon name="save"/> Enregistrer les modifications</button></div>
    </section>

    @if($errors->any())<div class="territory-flash error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="territory-detail-kpis">
        <article class="zone-identity-kpi"><span class="territory-kpi-icon sky"><x-operations.icon name="pin"/></span><strong>{{ $zone->code }} — {{ $zone->name }}</strong><span class="territory-status {{ $zone->is_active?'active':'inactive' }}"><i></i>{{ $zone->is_active?'Active':'Inactive' }}</span></article>
        <article><span class="territory-kpi-icon sky"><x-operations.icon name="users"/></span><div><small>Communes couvertes</small><strong>{{ $zone->communes->count() }}</strong></div></article>
        <article><span class="territory-kpi-icon sky"><x-operations.icon name="clock"/></span><div><small>Délai moyen</small><strong>{{ $zone->average_delay_hours }}h</strong></div></article>
        <article><span class="territory-kpi-icon mint"><x-operations.icon name="chart"/></span><div><small>Livraisons (7 jours)</small><strong>{{ $activity7d }}</strong><span>livraison(s)</span><em>{{ $activityGrowth>0?'+':'' }}{{ $activityGrowth }} %</em></div></article>
        <article class="load-kpi"><span class="load-ring" style="--load:{{ $currentLoad }}"></span><div><small>Charge actuelle</small><strong>{{ $currentLoad }} %</strong></div></article>
        <article><span class="territory-kpi-icon sky"><x-operations.icon name="users"/></span><div><small>Livreurs disponibles</small><strong>{{ $driversAvailable }}</strong><span>sur {{ $driversTotal }}</span></div></article>
    </section>

    <form method="POST" action="{{ route('logistics.zones.update',$zone) }}" id="zone-detail-form">@csrf @method('PATCH')
        <input type="hidden" name="coverage_geojson" data-coverage-geojson value='@json($selectedMapZone["geometry"])'>
        <section class="territory-detail-grid">
            <div class="territory-detail-left">
                <article class="territory-panel zone-info-card zone-info-card-pro">
                    <h2><span class="territory-round-icon"><x-operations.icon name="list"/></span> Informations de la zone</h2>
                    <div class="zone-info-columns">
                        <div class="zone-info-column">
                            <label class="pro-field"><span>Nom de la zone <sup>*</sup></span><input name="name" value="{{ old('name',$zone->name) }}" required></label>
                            <label class="pro-field"><span>Région / district <sup>*</sup></span><select name="region">@foreach($regions ?? collect([$zone->region]) as $region)<option value="{{ $region }}" @selected(old('region',$zone->region)===$region)>{{ $region }}</option>@endforeach</select></label>
                            <div class="pro-field status-pro-field"><span>Statut</span><div class="status-pro-row"><label class="detail-switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$zone->is_active))><i></i><b>Active</b></label><span class="inactive-label">Inactive</span></div></div>
                        </div>
                        <div class="zone-info-column">
                            <label class="pro-field"><span>Délai moyen <sup>*</sup></span><select name="average_delay_hours">@foreach([12,24,48,72] as $delay)<option value="{{ $delay }}" @selected((int)old('average_delay_hours',$zone->average_delay_hours)===$delay)>{{ $delay }}h</option>@endforeach</select></label>
                            <label class="pro-field"><span>Responsable opérationnel</span><select name="responsible_name"><option value="">Non affecté</option>@foreach($responsibles as $responsible)<option value="{{ $responsible->name }}" @selected(old('responsible_name',$zone->responsible_name)===$responsible->name)>{{ $responsible->name }}</option>@endforeach</select></label>
                            <div class="pro-field"><span>Horaires de couverture</span><div class="schedule-field"><x-operations.icon name="clock"/><input type="time" name="coverage_start_time" value="{{ old('coverage_start_time',$zone->coverage_start_time ?: '06:00') }}"><span>–</span><input type="time" name="coverage_end_time" value="{{ old('coverage_end_time',$zone->coverage_end_time ?: '22:00') }}"><x-operations.icon name="clock"/></div></div>
                            <label class="pro-field note-pro-field"><span>Note opérationnelle</span><textarea name="operational_note" maxlength="500">{{ old('operational_note',$zone->operational_note) }}</textarea><small>{{ mb_strlen(old('operational_note',$zone->operational_note ?? '')) }}/500</small></label>
                        </div>
                    </div>
                </article>

                <article class="territory-panel covered-communes-card"><h2><x-operations.icon name="pin"/> Communes couvertes</h2><div class="covered-tools"><label><x-operations.icon name="search"/><select data-detail-commune-select><option value="">Ajouter une commune...</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" data-name="{{ $commune->name }}" data-lat="{{ $commune->latitude }}" data-lng="{{ $commune->longitude }}" @disabled($selectedCommuneIds->contains((int)$commune->id))>{{ $commune->name }}</option>@endforeach</select></label></div><div class="detail-commune-chips" data-detail-commune-chips>@foreach($selectedCommunes as $commune)@php $breakdown=$communeBreakdownById->get($commune->id); @endphp<span data-id="{{ $commune->id }}" data-name="{{ $commune->name }}" data-lat="{{ $commune->latitude }}" data-lng="{{ $commune->longitude }}">{{ $commune->name }} <button type="button" aria-label="Retirer {{ $commune->name }}">×</button><input type="hidden" name="commune_ids[]" value="{{ $commune->id }}">@if($breakdown)<em class="commune-driver-badge" title="{{ $breakdown['drivers_available'] }} disponible(s) · {{ $breakdown['drivers_in_mission'] }} en mission · {{ $breakdown['drivers_offline'] }} hors ligne">{{ $breakdown['drivers_total'] }} livreur(s) · {{ $breakdown['drivers_available'] }} disponible(s)</em>@endif</span>@endforeach</div></article>
            </div>

            <div class="territory-detail-right">
                <article class="territory-panel geographic-card"><h2><span class="territory-round-icon"><x-operations.icon name="map"/></span> Couverture géographique</h2><div class="geo-content"><div class="territory-live-map territory-detail-map" data-territory-map="detail" aria-label="Carte interactive de couverture de {{ $zone->name }}"></div><aside><h3>Aperçu de la zone</h3><dl><dt><x-operations.icon name="pin"/><span>Distance moyenne<strong>{{ number_format($averageDistance,1,',',' ') }} km</strong></span></dt><dt><x-operations.icon name="building"/><span>Communes<strong>{{ $zone->communes->count() }}</strong></span></dt><dt><x-operations.icon name="chart"/><span>Densité de livraison<strong>{{ $density }}</strong></span></dt><dt><x-operations.icon name="target"/><span>SLA respecté<strong class="green-text">{{ $sla }} %</strong></span></dt><dt><x-operations.icon name="users"/><span>Livreurs disponibles<strong>{{ $driversAvailable }} <small>sur {{ $driversTotal }}</small></strong><i class="preview-progress"><b style="width:{{ $driversTotal ? round($driversAvailable*100/$driversTotal):0 }}%"></b></i></span></dt></dl></aside></div></article>

                <article class="territory-panel zone-drivers-card"><h2><span><x-operations.icon name="users"/> Livreurs dans la zone</span><a href="{{ route('logistics.drivers') }}">Voir tous les livreurs <x-operations.icon name="next"/></a></h2><table><thead><tr><th>Livreur</th><th>Véhicule</th><th>Statut</th><th>Disponibilité</th><th>Commune</th></tr></thead><tbody>@forelse($drivers as $driver)<tr><td><span class="driver-avatar">{{ $driver['initials'] }}</span>{{ $driver['name'] }}</td><td>{{ $driver['vehicle'] }}</td><td><span class="driver-status {{ $driver['available']?'':'busy' }}"><i></i>{{ $driver['status'] }}</span></td><td><span class="availability-dot {{ $driver['available']?'':'blue' }}"></span>{{ $driver['availability'] }}</td><td>{{ $driver['commune'] }}</td></tr>@empty<tr><td colspan="5" class="territory-empty">Aucun livreur actif associé aux communes de cette zone.</td></tr>@endforelse</tbody></table></article>

                <article class="territory-panel coverage-rules-card"><h2><x-operations.icon name="settings"/> Règles de couverture</h2>@foreach([['allow_express','Livraison express autorisée','Autoriser les missions en express dans cette zone'],['prioritize_missions','Missions prioritaires',"Prioriser cette zone dans l'attribution des missions"],['auto_apply_new_missions','Application automatique','Appliquer ces règles aux nouvelles missions'],['show_in_filters','Afficher la zone dans les filtres','Rendre cette zone visible dans les filtres de recherche']] as [$name,$label,$description])<label><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name,$zone->{$name}))><span><b>{{ $label }}</b><small>{{ $description }}</small></span></label>@endforeach</article>
            </div>
        </section>
    </form>
</main>

<script type="application/json" id="territory-map-data">{!! json_encode(['zones'=>$mapZones,'selected'=>$zone->code,'communes'=>$communes->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'lat'=>$c->latitude!==null?(float)$c->latitude:null,'lng'=>$c->longitude!==null?(float)$c->longitude:null])->values()], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
@push('scripts')
<script src="{{ asset('js/logistics-territory.js') }}?v={{ @filemtime(public_path('js/logistics-territory.js')) ?: '20260911' }}" defer></script>
@endpush
