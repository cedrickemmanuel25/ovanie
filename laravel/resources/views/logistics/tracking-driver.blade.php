@extends('layouts.logistics-operations')
@section('title','Suivi d’un livreur')
@section('page-class','ops-page-tracking ops-tracking-driver')
@php
    $trackingTitle = 'Suivi d’un livreur';
    $trackingSubtitle = 'Position GPS, disponibilité, véhicule, activité terrain et performance du livreur';
    $trackedMission = $group ? \App\ViewModels\LogisticsTrackingData::mission($group) : null;
    $trackedDriver = \App\ViewModels\LogisticsTrackingData::driver($driver);
    $trackingMapMode = 'driver';
    $trackingContext = 'driver';
    // Quand une mission est en cours, on réutilise exactement les mêmes
    // points (boutique, destination, livreur) que la page "Suivi d'une
    // mission" : sans la boutique et la destination, la carte n'avait rien
    // sur quoi se centrer et semblait vide/mal cadrée.
    $trackingPoints = $trackedMission['points'] ?? [];
    if (empty($trackingPoints) && $trackedDriver['lat'] !== null && $trackedDriver['lng'] !== null) {
        $trackingPoints[] = [
            'kind' => 'driver',
            'label' => $trackedDriver['name'],
            'detail' => $trackedDriver['fresh'] ? 'Position GPS récente' : 'Dernière position connue',
            'lat' => $trackedDriver['lat'],
            'lng' => $trackedDriver['lng'],
            'status' => $trackedMission ? 'mission' : ($trackedDriver['availabilityTone'] ?? 'available'),
            'driverId' => $trackedDriver['id'],
            'vehicle' => $trackedDriver['vehicle'],
            'vehicleCode' => $trackedDriver['vehicleCode'],
            'vehiclePlate' => $trackedDriver['vehiclePlate'] ?? null,
            'vehicleColor' => $trackedDriver['vehicleColor'] ?? null,
            'vehicleColorHex' => $trackedDriver['vehicleColorHex'] ?? null,
            'vehiclePhotoUrl' => $trackedDriver['vehiclePhotoUrl'] ?? null,
            'vehiclePhotoIsReal' => $trackedDriver['vehiclePhotoIsReal'] ?? false,
            'vehicleReferenceAssetUrl' => $trackedDriver['vehicleReferenceAssetUrl'] ?? null,
            'fleetVehicleCode' => $trackedDriver['fleetVehicleCode'] ?? null,
            'availability' => $trackedDriver['availability'],
            'recordedAt' => $trackedDriver['recordedAt'],
            'accuracy' => $trackedDriver['accuracy'],
            'speed' => $trackedDriver['speed'],
            'online' => $trackedDriver['online'],
            'url' => $trackedDriver['trackingUrl'],
            'heading' => $trackedDriver['heading'] ?? null,
        ];
    }
    // Le trajet de la mission en cours est affiché ici aussi : un responsable
    // qui suit un livreur doit voir où il va sans devoir rouvrir la page
    // Suivi d’une mission séparément. Mais tant que le livreur n'a pas
    // accepté la mission, il n'y a rien de réel à tracer.
    $trackingRoutes = ($trackedMission['assignmentAccepted'] ?? false)
        ? array_map(fn ($geometry) => ['geometry' => $geometry, 'late' => false, 'phase' => $trackedMission['status'] ?? null], $trackedMission['routeSegments'] ?? [])
        : [];
@endphp
@section('content')
@include('logistics.operations.tracking-header')

<div class="ops-tracking-detail-actions">
    <a class="ops-button" href="{{ route('logistics.tracking.drivers') }}"><x-operations.icon name="back"/>Retour au suivi des livreurs</a>
    <a class="ops-button" href="{{ route('logistics.tracking') }}"><x-operations.icon name="pin"/>Centre de suivi GPS</a>
</div>

@unless($driver)
    <x-operations.panel title="Livreur suivi">
        <p class="ops-empty">Aucun livreur actif à suivre.</p>
    </x-operations.panel>
@else
    <div class="ops-kpis ops-tracking-kpis">
        <x-operations.kpi icon="user" label="Livreur suivi" :value="$trackedDriver['name']"/>
        <x-operations.kpi icon="box" label="Mission active" :value="$trackedMission['reference'] ?? 'Aucune'"/>
        <x-operations.kpi icon="truck" label="Disponibilité" value="" tone="green">
            @if($trackedMission)
                <span class="ops-badge is-blue">En mission</span>
            @elseif($driver->is_online)
                <span class="ops-badge {{ $trackedDriver['availability']==='Indisponible' ? '' : 'is-green' }}">{{ $trackedDriver['availability'] }}</span>
            @else
                <span class="ops-badge">Hors ligne</span>
            @endif
        </x-operations.kpi>
        <x-operations.kpi icon="chart" label="Qualité GPS" :value="$trackedDriver['quality']" tone="green"/>
        <x-operations.kpi icon="clock" label="Dernière activité" :value="$trackedDriver['at'] ?? '—'" tone="orange"/>
    </div>

    <div class="ops-tracking-detail-grid">
        <div class="ops-tracking-detail-map">
            @include('logistics.operations.tracking-map')
        </div>

        <aside class="ops-stack">
            <x-operations.panel title="Profil du livreur" icon="user">
                <x-slot:actions>
                    <span class="ops-badge {{ $activeAssignment ? 'is-blue' : ($driver->is_online && $trackedDriver['availability']!=='Indisponible' ? 'is-green' : '') }}">
                        {{ $activeAssignment ? '● En mission' : ($driver->is_online ? '● '.$trackedDriver['availability'] : '● Hors ligne') }}
                    </span>
                </x-slot:actions>
                <div class="ops-tracking-driver-profile">
                    <span class="ops-tracking-avatar"><x-operations.icon name="user"/></span>
                    <div>
                        <h3>{{ $trackedDriver['name'] }}</h3>
                        <p><span class="ops-rating">★ {{ $trackedDriver['rating'] }}</span></p>
                    </div>
                    <div class="ops-tracking-signal">
                        <strong>● Signal {{ mb_strtolower($trackedDriver['quality']) }}</strong>
                        <small>{{ $trackedDriver['fresh'] ? 'Position récente' : 'Dernière position connue' }}</small>
                    </div>
                </div>
                <div class="ops-tracking-vehicle-card ops-tracking-vehicle-card-real">
                    <div class="ops-tracking-vehicle-photo-wrap">
                        @if($trackedDriver['vehiclePhotoUrl'])
                            <img src="{{ $trackedDriver['vehiclePhotoUrl'] }}" alt="{{ $trackedDriver['vehicle'] }} de {{ $trackedDriver['name'] }}" class="ops-tracking-vehicle-photo">
                        @elseif($trackedDriver['vehicleReferenceAssetUrl'])
                            <img src="{{ $trackedDriver['vehicleReferenceAssetUrl'] }}" alt="Asset OVANIE {{ $trackedDriver['vehicle'] }}" class="ops-tracking-vehicle-photo ops-tracking-vehicle-photo-reference">
                            <span class="ops-vehicle-photo-reference">Asset OVANIE</span>
                        @endif
                    </div>
                    <div class="ops-tracking-vehicle-card-main">
                        <small>{{ $trackedDriver['vehiclePhotoIsReal'] ? 'Véhicule enregistré dans Flotte' : 'Véhicule Flotte' }}</small>
                        <strong>{{ $trackedDriver['vehicle'] }}</strong>
                        <span class="ops-vehicle-color-line">
                            @if($trackedDriver['vehicleColorHex'])<i style="--vehicle-color:{{ $trackedDriver['vehicleColorHex'] }}"></i>@endif
                            {{ $trackedDriver['vehicleColor'] ?: 'Couleur non détectée' }}
                        </span>
                        @if($trackedDriver['fleetVehicleCode'])<small>{{ $trackedDriver['fleetVehicleCode'] }}</small>@endif
                    </div>
                    <div class="ops-tracking-vehicle-plate">{{ $trackedDriver['vehiclePlate'] ?: 'Immatriculation non renseignée' }}</div>
                </div>
                <div class="ops-tracking-driver-facts">
                    <span><x-operations.icon name="pin"/><b>Zone</b>{{ $trackedDriver['zone'] ?: 'Non renseignée' }}</span>
                    @if($trackedDriver['phone'])
                        <span><x-operations.icon name="phone"/><b>Téléphone</b>{{ $trackedDriver['phone'] }}</span>
                    @endif
                </div>
            </x-operations.panel>

            <x-operations.panel title="Mission active" icon="box" class="ops-driver-active-mission">
                @if($trackedMission)
                    <x-slot:actions><a href="{{ $trackedMission['trackingUrl'] }}">Ouvrir le suivi →</a></x-slot:actions>
                    <a class="ops-driver-mission-card" href="{{ $trackedMission['trackingUrl'] }}" aria-label="Ouvrir le suivi de la mission {{ $trackedMission['reference'] }}">
                        <div>
                            <small>Mission</small>
                            <strong>{{ $trackedMission['reference'] }}</strong>
                            <span>{{ $trackedMission['order'] }}</span>
                        </div>
                        <div>
                            <small>Destination</small>
                            <strong>{{ $trackedMission['destination'] }}</strong>
                            <span>{{ $trackedMission['client'] }}</span>
                        </div>
                        <div>
                            <small>ETA client</small>
                            <strong>{{ $trackedMission['eta'] }}</strong>
                            <span>{{ $trackedMission['weight'] }} kg · {{ $trackedMission['vehicle'] }}</span>
                        </div>
                        <x-operations.icon name="next"/>
                    </a>
                @else
                    <div class="ops-driver-no-mission">
                        <x-operations.icon name="check-circle"/>
                        <div><strong>Aucune mission active</strong><p>Le livreur est suivi indépendamment des livraisons.</p></div>
                    </div>
                @endif
            </x-operations.panel>

            <x-operations.panel title="Signal GPS" icon="chart">
                <div class="ops-tracking-gps">
                    <div><x-operations.icon name="chart"/><span>Qualité du signal<strong>{{ $trackedDriver['quality'] }}</strong></span></div>
                    <div><x-operations.icon name="clock"/><span>Dernière synchronisation<strong>{{ $trackedDriver['at'] ?? '—' }}</strong></span></div>
                    <div><x-operations.icon name="target"/><span>Précision<strong>{{ $trackedDriver['accuracy'] !== null ? $trackedDriver['accuracy'].' m' : 'Non renseignée' }}</strong></span></div>
                    <div><x-operations.icon name="route"/><span>Vitesse GPS<strong>{{ $trackedDriver['speed'] !== null ? round($trackedDriver['speed']).' km/h' : 'Non renseignée' }}</strong></span></div>
                </div>
            </x-operations.panel>
        </aside>
    </div>

    <div class="ops-tracking-bottom">
        <x-operations.panel title="Chronique du livreur" icon="clock">
            @include('logistics.operations.tracking-events',['trackingEvents'=>$events])
        </x-operations.panel>
        <x-operations.panel title="Performance du jour" icon="chart">
            <div class="ops-tracking-performance">
                <div><x-operations.icon name="box"/><span>Missions du jour</span><strong>{{ $todayMissionsCount }}</strong></div>
                <div><x-operations.icon name="route"/><span>Kilomètres parcourus</span><strong>{{ isset($driverPerformance['distance']) ? $driverPerformance['distance'].' km' : '—' }}</strong></div>
                <div><x-operations.icon name="clock"/><span>Temps en ligne</span><strong>{{ isset($driverPerformance['onlineMinutes']) ? \App\ViewModels\LogisticsOperationsData::duration($driverPerformance['onlineMinutes']) : '—' }}</strong></div>
                <div><x-operations.icon name="check-circle"/><span>Ponctualité</span><strong>{{ isset($driverPerformance['onTimePercent']) ? $driverPerformance['onTimePercent'].' %' : '—' }}</strong></div>
            </div>
        </x-operations.panel>
        <x-operations.panel title="Accès rapide" icon="bolt">
            @include('logistics.operations.tracking-quick-actions')
        </x-operations.panel>
    </div>
@endunless
@endsection
