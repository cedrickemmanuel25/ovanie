@extends('layouts.logistics-operations')
@section('title','Centre de suivi GPS')
@section('page-class','ops-page-tracking ops-tracking-center ops-tracking-live-page ops-dispatch-page')

@php
    $trackingTitle = 'Centre de suivi GPS';
    $trackingSubtitle = 'Supervision en temps réel des livreurs, véhicules et missions';
    $trackingRows = $activeGroups->map(fn($g) => \App\ViewModels\LogisticsTrackingData::mission($g))->all();
    $liveDrivers = collect($mapDrivers ?? []);

    $missionPoints = collect($trackingRows)->flatMap(function ($mission) {
        return array_map(function ($point) use ($mission) {
            return $point + [
                'missionId' => $mission['id'],
                'missionStatus' => $mission['status'],
                'driverId' => $point['driverId'] ?? $mission['driverId'],
                'zone' => $mission['destination'],
                'delayed' => $mission['delayed'],
            ];
        }, $mission['points']);
    });

    $liveDriverPoints = $liveDrivers->map(function (array $driver) {
        return [
            'kind' => 'driver',
            'label' => $driver['name'],
            'detail' => trim(($driver['availability'] ?? 'Disponible').' · GPS '.($driver['at'] ?? 'récent')),
            'lat' => $driver['lat'],
            'lng' => $driver['lng'],
            'status' => $driver['availabilityTone'] ?? 'available',
            'driverId' => $driver['id'],
            'vehicle' => $driver['vehicle'] ?? 'Véhicule',
            'vehicleCode' => $driver['vehicleCode'] ?? 'vehicle',
            'vehiclePlate' => $driver['vehiclePlate'] ?? null,
            'vehicleColor' => $driver['vehicleColor'] ?? null,
            'vehicleColorHex' => $driver['vehicleColorHex'] ?? null,
            'vehiclePhotoUrl' => $driver['vehiclePhotoUrl'] ?? null,
            'vehiclePhotoIsReal' => $driver['vehiclePhotoIsReal'] ?? false,
            'fleetVehicleCode' => $driver['fleetVehicleCode'] ?? null,
            'availability' => $driver['availability'] ?? 'Disponible',
            'recordedAt' => $driver['recordedAt'] ?? null,
            'accuracy' => $driver['accuracy'] ?? null,
            'speed' => $driver['speed'] ?? null,
            'heading' => $driver['heading'] ?? null,
            'zone' => $driver['zone'] ?? null,
            'online' => true,
            'standalone' => true,
            'url' => $driver['trackingUrl'] ?? null,
        ];
    });

    $trackingPoints = $missionPoints->concat($liveDriverPoints)->values()->all();
    // Le tracé représente le trajet du livreur (boutique <-> domicile client) :
    // tant qu'il n'a pas accepté la mission, il n'y a rien de réel à tracer.
    $trackingRoutes = collect($trackingRows)
        ->filter(fn($mission) => $mission['assignmentAccepted'] ?? false)
        ->flatMap(fn($mission) => array_map(fn($geometry) => [
            'geometry' => $geometry,
            'missionId' => $mission['id'],
            'late' => $mission['delayed'],
            'phase' => $mission['status'] ?? null,
        ], $mission['routeSegments']))->all();

    $filterDrivers = collect($trackingRows)
        ->filter(fn($mission) => filled($mission['driverId'] ?? null))
        ->map(fn($mission) => ['id' => $mission['driverId'], 'name' => $mission['driver']])
        ->concat($liveDrivers->map(fn($driver) => ['id' => $driver['id'], 'name' => $driver['name']]))
        ->filter(fn($driver) => filled($driver['id']))
        ->unique('id')
        ->sortBy('name')
        ->values();

    $filterZones = collect($trackingRows)->pluck('destination')
        ->concat($liveDrivers->pluck('zone'))
        ->filter()->unique()->sort()->values();

    $inTransitCount = collect($trackingRows)->whereIn('status', ['in_transit', 'picked_up'])->count();
    $lateCount = collect($trackingRows)->where('delayed', true)->count();
@endphp

@section('content')
@include('logistics.operations.tracking-header')

<section class="ops-dispatch-shell" data-tracking-shell>
    <div class="ops-dispatch-map-frame">
        <div class="ops-dispatch-topbar">
            <button type="button" class="ops-dispatch-action ops-dispatch-missions-button" data-tracking-panel-toggle aria-expanded="false">
                <x-operations.icon name="list"/>
                <span>Missions</span>
                <b data-mission-count>{{ count($trackingRows) }}</b>
            </button>

            <label class="ops-dispatch-search">
                <x-operations.icon name="search"/>
                <input data-tracking-map-search placeholder="Rechercher un livreur, une mission ou une zone…" aria-label="Rechercher sur la carte">
            </label>

            <div class="ops-dispatch-topbar-spacer"></div>

            <button type="button" class="ops-dispatch-action" data-tracking-filter-toggle aria-expanded="false">
                <x-operations.icon name="settings"/>
                <span>Filtres</span>
            </button>
        </div>

        <div class="ops-dispatch-filter-popover" data-tracking-filter-panel hidden>
            <div>
                <label>Statut</label>
                <select data-tracking-status aria-label="Statut mission">
                    <option value="">Tous</option>
                    <option value="in_transit">En transit</option>
                    <option value="assigned">En attente</option>
                    <option value="late">En retard</option>
                </select>
            </div>
            <div>
                <label>Livreur</label>
                <select data-tracking-driver aria-label="Livreur">
                    <option value="">Tous</option>
                    @foreach($filterDrivers as $driver)
                        <option value="{{ $driver['id'] }}">{{ $driver['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Zone</label>
                <select data-tracking-zone aria-label="Zone">
                    <option value="">Toutes</option>
                    @foreach($filterZones as $zone)
                        <option value="{{ $zone }}">{{ $zone }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="ops-dispatch-stat-dock" data-tracking-live-stats>
            <div class="ops-dispatch-stat is-online">
                <span><x-operations.icon name="users"/></span>
                <div><small>En ligne</small><strong>{{ $driversOnline }}</strong></div>
            </div>
            <div class="ops-dispatch-stat is-mission">
                <span><x-operations.icon name="truck"/></span>
                <div><small>En mission</small><strong>{{ $inTransitCount }}</strong></div>
            </div>
            <div class="ops-dispatch-stat is-late">
                <span><x-operations.icon name="warning"/></span>
                <div><small>En retard</small><strong>{{ $lateCount }}</strong></div>
            </div>
        </div>

        <div class="ops-dispatch-selected-mission" data-selected-mission-chip hidden>
            <span class="ops-dispatch-selected-dot"></span>
            <div>
                <small>Mission suivie</small>
                <strong data-selected-mission-label>—</strong>
            </div>
            <button type="button" data-tracking-clear-focus aria-label="Fermer le suivi de la mission"><x-operations.icon name="close"/></button>
        </div>

        @if($driversOnline === 0)
            <div class="ops-dispatch-no-driver" data-no-live-drivers>
                <span><x-operations.icon name="pin"/></span>
                <div><strong>Aucun livreur GPS en ligne</strong><small>Les véhicules apparaîtront ici dès qu’une position GPS récente sera reçue.</small></div>
            </div>
        @endif

        <aside class="ops-tracking-mission-drawer ops-dispatch-drawer" data-tracking-missions-panel aria-hidden="true">
            <header>
                <div>
                    <strong>Missions en cours</strong>
                    <small data-mission-panel-count>{{ count($trackingRows) }} mission(s) active(s)</small>
                </div>
                <button type="button" data-tracking-panel-close aria-label="Fermer le panneau"><x-operations.icon name="close"/></button>
            </header>

            <label class="ops-search ops-drawer-search"><x-operations.icon name="search"/><input data-tracking-search placeholder="Mission, livreur ou destination…" aria-label="Rechercher une mission"></label>

            <div class="ops-tracking-tabs">
                @foreach(['all'=>'Toutes','in_transit'=>'En transit','assigned'=>'En attente','late'=>'En retard'] as $key=>$label)
                    <button data-tracking-tab="{{ $key }}" class="{{ $key==='all'?'active':'' }}">
                        {{ $label }}
                        <span>{{ $key==='all' ? count($trackingRows) : ($key==='late' ? $lateCount : collect($trackingRows)->where('status',$key)->count()) }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ops-tracking-drawer-list">
                @forelse($trackingRows as $row)
                    <article class="ops-tracking-mission-row" data-tracking-row="{{ $row['id'] }}" data-tracking-open="{{ $row['trackingUrl'] }}" data-search="{{ mb_strtolower($row['reference'].' '.$row['order'].' '.$row['client'].' '.$row['driver'].' '.$row['destination']) }}" data-reference="{{ $row['reference'] }}" data-status="{{ $row['status'] }}" data-delayed="{{ $row['delayed']?'1':'0' }}" data-driver="{{ $row['driverId'] }}" data-zone="{{ $row['destination'] }}" role="button" tabindex="0" aria-label="Afficher la mission {{ $row['reference'] }} sur la carte">
                        <header>
                            <div><strong>{{ $row['reference'] }}</strong><small>{{ $row['order'] }} · {{ $row['client'] }}</small></div>
                            <x-operations.badge :status="$row['delayed']?'late':$row['status']"/>
                        </header>
                        <div>
                            <span><x-operations.icon name="pin" class="text-red"/>{{ $row['destination'] }}</span>
                            <span><x-operations.icon name="user"/>{{ $row['driver'] }}</span>
                            <span><x-operations.icon :name="$row['vehicleCode']==='moto'?'moto':'truck'"/>{{ $row['vehicle'] }}</span>
                            <span class="{{ $row['delayed']?'text-red':'' }}">{{ $row['delayed']?'+ '.$row['delayMinutes'].' min':'ETA '.$row['eta'] }}</span>
                            <a href="{{ $row['trackingUrl'] }}" aria-label="Ouvrir le détail de {{ $row['reference'] }}">Détail</a>
                        </div>
                    </article>
                @empty
                    <p class="ops-empty">Aucune mission en cours. Les livreurs GPS en ligne restent visibles sur la carte.</p>
                @endforelse
                <p class="ops-empty" data-tracking-empty hidden>Aucune mission ne correspond aux filtres.</p>
            </div>

            <nav class="ops-list-pagination">
                <span data-tracking-page-label></span>
                <div><button class="ops-button ops-button-small" data-tracking-page="-1">Précédent</button><button class="ops-button ops-button-small" data-tracking-page="1">Suivant</button></div>
            </nav>
        </aside>

        @include('logistics.operations.tracking-map')
    </div>
</section>
@endsection
