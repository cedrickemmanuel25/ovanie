@extends('layouts.logistics')
@section('title','Flotte')
@section('crumb','Flotte')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
$statusLabels=['available'=>'Disponible','mission'=>'En mission','maintenance'=>'Maintenance','out_of_service'=>'Hors service'];
$vehicleImage=function($vehicle){
    $custom=$vehicle->resolved_photo_path ?: data_get($vehicle->meta,'photo_path');
    if($custom){ return asset('storage/'.$custom); }
    return asset(match($vehicle->vehicle_type){
        'Moto'=>'images/vendor/vehicles/moto.png',
        'Tricycle'=>'images/vendor/vehicles/tricycle.png',
        'Pickup'=>'images/vendor/vehicles/pickup.png',
        'Camion 10T'=>'images/vendor/vehicles/camion-10t.png',
        default=>'images/vendor/vehicles/camion-3t.png',
    });
};
$maxType=max(1,(int)collect($types)->max());
@endphp
<main class="mg-page fleet-v2-page">
    <header class="mg-titlebar fleet-v2-titlebar">
        <div>
            <h1>Flotte</h1>
            <p>Gestion opérationnelle des véhicules OVANIE Logistics : disponibilité, affectation, capacité et suivi.</p>
        </div>
        <div class="mg-actions">
            <a class="mg-btn fleet-v2-btn" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}"><x-logistics.fleet-svg name="download"/>Exporter</a>
            <a href="{{ route('logistics.fleet.create') }}" class="mg-btn mg-btn-primary fleet-v2-btn"><x-logistics.fleet-svg name="plus"/>Ajouter un véhicule</a>
        </div>
    </header>

    <section class="fleet-v2-kpis">
        @foreach([
            ['truck','Véhicules au total',$counts['total'],'green'],
            ['check','Disponibles',$counts['available'],'green'],
            ['route','En mission',$counts['mission'],'blue'],
            ['wrench','En maintenance',$counts['maintenance'],'orange'],
            ['warning','Hors service',$counts['out_of_service'],'red'],
        ] as [$icon,$label,$value,$tone])
        <article class="fleet-v2-kpi {{ $tone }}">
            <span class="fleet-v2-kpi-icon"><x-logistics.fleet-svg :name="$icon" :size="26"/></span>
            <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
        </article>
        @endforeach
    </section>

    <form class="fleet-v2-filters" method="get">
        <label class="fleet-v2-search"><x-logistics.fleet-svg name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher un véhicule, une immatriculation, un chauffeur ou une zone..."></label>
        <select name="type" onchange="this.form.submit()"><option value="all">Type — Tous</option>@foreach($types->keys() as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ $type }}</option>@endforeach</select>
        <select name="status" onchange="this.form.submit()"><option value="all">Disponibilité — Toutes</option>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
        <select name="zone" onchange="this.form.submit()"><option value="all">Zone — Toutes</option>@foreach($zones as $zone)<option value="{{ $zone }}" @selected(request('zone')===$zone)>{{ $zone }}</option>@endforeach</select>
        <a class="fleet-v2-reset" href="{{ route('logistics.fleet') }}"><x-logistics.fleet-svg name="refresh"/>Réinitialiser les filtres</a>
    </form>

    <section class="fleet-v2-layout">
        <article class="fleet-v2-card fleet-v2-table-card">
            <header class="fleet-v2-card-head"><div><h2>Liste des véhicules</h2><p>{{ $vehicles->total() }} véhicule{{ $vehicles->total()>1?'s':'' }} correspondant aux filtres</p></div></header>
            <div class="fleet-v2-table-wrap">
                <table class="fleet-v2-table">
                    <thead><tr><th>Véhicule</th><th>Type</th><th>Disponibilité</th><th>Chauffeur</th><th>Capacité</th><th>Zone</th><th>Missions</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($vehicles as $vehicle)
                    <tr>
                        <td>
                            <a class="fleet-v2-vehicle" href="{{ route('logistics.fleet.show',$vehicle) }}">
                                <span class="fleet-v2-photo"><img src="{{ $vehicleImage($vehicle) }}" alt="{{ $vehicle->typeLabel() }} {{ $vehicle->registration }}"></span>
                                <span><strong>{{ trim(($vehicle->brand ?: '').' '.($vehicle->model ?: '')) ?: $vehicle->typeLabel() }}</strong><small>{{ $vehicle->registration }}</small></span>
                            </a>
                        </td>
                        <td><strong class="fleet-v2-type">{{ $vehicle->typeLabel() }}</strong></td>
                        <td><span class="mg-status {{ $vehicle->effective_status }}"><i></i>{{ $statusLabels[$vehicle->effective_status] ?? $vehicle->effective_status }}</span></td>
                        <td><span class="fleet-v2-driver"><x-logistics.fleet-svg name="user" :size="17"/><span>{{ $vehicle->resolved_driver_name }}</span></span></td>
                        <td><strong>{{ $vehicle->capacityLabel() }}</strong>@if($vehicle->volume_m3)<small class="fleet-v2-sub">{{ number_format($vehicle->volume_m3,1,',',' ') }} m³</small>@endif</td>
                        <td><span class="fleet-v2-driver"><x-logistics.fleet-svg name="map-pin" :size="17"/><span>{{ implode(', ', $vehicle->resolved_zones) ?: 'Non renseignées' }}</span></span></td>
                        <td><strong>{{ $vehicle->real_mission_count }}</strong><small class="fleet-v2-sub">{{ $vehicle->active_mission_count }} active{{ $vehicle->active_mission_count>1?'s':'' }}</small></td>
                        <td><div class="fleet-v2-actions"><a href="{{ route('logistics.fleet.show',$vehicle) }}" title="Voir la fiche"><x-logistics.fleet-svg name="eye"/></a>@if($vehicle->resolved_driver_phone)<a href="tel:{{ $vehicle->resolved_driver_phone }}" title="Appeler le chauffeur"><x-logistics.fleet-svg name="phone"/></a>@endif<button type="button" title="Plus d'actions"><x-logistics.fleet-svg name="dots"/></button></div></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="fleet-v2-empty">Aucun véhicule ne correspond aux filtres.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="fleet-v2-footer">
                <span>Affichage de {{ $vehicles->firstItem() ?? 0 }} à {{ $vehicles->lastItem() ?? 0 }} sur {{ $vehicles->total() }} résultats</span>
                <div class="fleet-v2-pages">{{ $vehicles->onEachSide(1)->links() }}</div>
                <label>Éléments par page <select onchange="location.href='{{ request()->url() }}?'+new URLSearchParams({...Object.fromEntries(new URLSearchParams(location.search)),per_page:this.value,page:1})"><option value="10" @selected($vehicles->perPage()==10)>10</option><option value="20" @selected($vehicles->perPage()==20)>20</option><option value="50" @selected($vehicles->perPage()==50)>50</option></select></label>
            </footer>
        </article>

        <aside class="fleet-v2-side">
            <article class="fleet-v2-card fleet-v2-side-card">
                <header><h3><x-logistics.fleet-svg name="gauge"/>Répartition par type</h3></header>
                <div class="fleet-v2-bars">
                    @forelse($types as $type=>$count)
                    <div><span>{{ $type }}</span><i><b style="width:{{ round(($count/$maxType)*100) }}%"></b></i><strong>{{ $count }}</strong></div>
                    @empty<p>Aucun véhicule enregistré.</p>@endforelse
                </div>
            </article>

            <article class="fleet-v2-card fleet-v2-side-card">
                <header><h3><x-logistics.fleet-svg name="gauge"/>Disponibilité actuelle</h3></header>
                <ul class="fleet-v2-availability">
                    @foreach([['available','Disponible','green'],['mission','En mission','blue'],['maintenance','Maintenance','orange'],['out_of_service','Hors service','red']] as [$key,$label,$tone])
                    <li><span><i class="{{ $tone }}"></i>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></li>
                    @endforeach
                </ul>
            </article>

            <article class="fleet-v2-card fleet-v2-side-card">
                <header><h3><x-logistics.fleet-svg name="warning"/>Véhicules à suivre</h3></header>
                <div class="fleet-v2-watch-list">
                    @forelse($watchVehicles as $vehicle)
                    <a href="{{ route('logistics.fleet.show',$vehicle) }}" class="fleet-v2-watch">
                        <span class="fleet-v2-watch-photo"><img src="{{ $vehicleImage($vehicle) }}" alt=""></span>
                        <span><strong>{{ $vehicle->typeLabel() }} · {{ $vehicle->registration }}</strong><small>{{ $vehicle->resolved_driver_name }} · {{ implode(', ', $vehicle->resolved_zones) ?: 'Zones non renseignées' }}</small></span>
                        <em class="{{ $vehicle->effective_status }}">{{ $statusLabels[$vehicle->effective_status] ?? $vehicle->effective_status }}</em>
                    </a>
                    @empty<p class="fleet-v2-empty">Aucun véhicule à signaler.</p>@endforelse
                </div>
            </article>
        </aside>
    </section>
</main>
@endsection
