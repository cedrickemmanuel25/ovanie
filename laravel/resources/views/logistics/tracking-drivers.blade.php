@extends('layouts.logistics-operations')
@section('title','Suivi des livreurs')
@section('page-class','ops-page-tracking ops-tracking-drivers-index')

@section('content')
<header class="ops-driver-list-header">
    <nav class="ops-breadcrumb">
        <a href="{{ route('logistics.dashboard') }}">Logistique</a>
        <x-operations.icon name="next"/>
        <a href="{{ route('logistics.tracking') }}">Tracking</a>
        <x-operations.icon name="next"/>
        <span>Suivi des livreurs</span>
    </nav>
    <div class="ops-driver-list-titlebar">
        <div>
            <h1>Suivi des livreurs</h1>
            <p>Choisissez un livreur pour ouvrir sa fiche GPS détaillée, sa disponibilité et sa dernière position connue.</p>
        </div>
        <a class="ops-button ops-button-primary" href="{{ route('logistics.tracking') }}"><x-operations.icon name="pin"/>Centre de suivi GPS</a>
    </div>
</header>

<section class="ops-driver-list-kpis" aria-label="Résumé des livreurs">
    <a href="{{ route('logistics.tracking.drivers') }}" class="ops-driver-list-kpi {{ ($filters['presence'] ?? 'all') === 'all' ? 'is-active' : '' }}">
        <span class="ops-driver-list-kpi-icon is-blue"><x-operations.icon name="user"/></span>
        <span><small>Livreurs suivis</small><strong>{{ $stats['total'] }}</strong></span>
    </a>
    <a href="{{ route('logistics.tracking.drivers',['presence'=>'online']) }}" class="ops-driver-list-kpi {{ ($filters['presence'] ?? '') === 'online' ? 'is-active' : '' }}">
        <span class="ops-driver-list-kpi-icon is-green"><x-operations.icon name="target"/></span>
        <span><small>En ligne GPS</small><strong>{{ $stats['online'] }}</strong></span>
    </a>
    <a href="{{ route('logistics.tracking.drivers',['presence'=>'available']) }}" class="ops-driver-list-kpi {{ ($filters['presence'] ?? '') === 'available' ? 'is-active' : '' }}">
        <span class="ops-driver-list-kpi-icon is-green"><x-operations.icon name="check-circle"/></span>
        <span><small>Disponibles</small><strong>{{ $stats['available'] }}</strong></span>
    </a>
    <a href="{{ route('logistics.tracking.drivers',['presence'=>'mission']) }}" class="ops-driver-list-kpi {{ ($filters['presence'] ?? '') === 'mission' ? 'is-active' : '' }}">
        <span class="ops-driver-list-kpi-icon is-orange"><x-operations.icon name="truck"/></span>
        <span><small>En mission</small><strong>{{ $stats['mission'] }}</strong></span>
    </a>
    <a href="{{ route('logistics.tracking.drivers',['presence'=>'offline']) }}" class="ops-driver-list-kpi {{ ($filters['presence'] ?? '') === 'offline' ? 'is-active' : '' }}">
        <span class="ops-driver-list-kpi-icon is-slate"><x-operations.icon name="clock"/></span>
        <span><small>Hors ligne</small><strong>{{ $stats['offline'] }}</strong></span>
    </a>
</section>

<section class="ops-driver-list-panel">
    <div class="ops-driver-list-panel-head">
        <div>
            <h2>Liste des livreurs</h2>
            <p>{{ $rows->count() }} livreur(s) correspondant aux filtres actuels.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('logistics.tracking.drivers') }}" class="ops-driver-list-filters">
        <label class="ops-driver-list-search">
            <x-operations.icon name="search"/>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un livreur, téléphone, véhicule ou zone...">
        </label>
        <select name="presence" aria-label="Filtrer par état">
            <option value="all" @selected(($filters['presence'] ?? 'all')==='all')>Tous les états</option>
            <option value="online" @selected(($filters['presence'] ?? '')==='online')>En ligne GPS</option>
            <option value="available" @selected(($filters['presence'] ?? '')==='available')>Disponibles</option>
            <option value="mission" @selected(($filters['presence'] ?? '')==='mission')>En mission</option>
            <option value="unavailable" @selected(($filters['presence'] ?? '')==='unavailable')>Indisponibles</option>
            <option value="offline" @selected(($filters['presence'] ?? '')==='offline')>Hors ligne</option>
        </select>
        <select name="vehicle" aria-label="Filtrer par véhicule">
            <option value="">Tous les véhicules</option>
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle }}" @selected(($filters['vehicle'] ?? '')===$vehicle)>{{ $vehicle }}</option>
            @endforeach
        </select>
        <select name="zone" aria-label="Filtrer par zone">
            <option value="">Toutes les zones</option>
            @foreach($zones as $zone)
                <option value="{{ $zone }}" @selected(($filters['zone'] ?? '')===$zone)>{{ $zone }}</option>
            @endforeach
        </select>
        <button class="ops-button ops-button-primary" type="submit"><x-operations.icon name="search"/>Filtrer</button>
        <a class="ops-button" href="{{ route('logistics.tracking.drivers') }}">Réinitialiser</a>
    </form>

    <div class="ops-driver-list-table-wrap">
        <table class="ops-driver-list-table">
            <thead>
                <tr>
                    <th>Livreur</th>
                    <th>Connexion GPS</th>
                    <th>Disponibilité</th>
                    <th>Véhicule</th>
                    <th>Zone</th>
                    <th>Dernière position</th>
                    <th>Mission</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $driver = $row['driver'];
                    $tracking = $row['tracking'];
                    $online = (bool) $tracking['online'];
                    $lastPosition = $driver->currentLocation?->recorded_at;
                    $availability = $tracking['availability'];
                @endphp
                <tr>
                    <td>
                        <a class="ops-driver-list-person" href="{{ route('logistics.tracking.driver',$driver) }}">
                            <span class="ops-driver-list-avatar"><x-operations.icon name="user"/></span>
                            <span>
                                <strong>{{ $tracking['name'] }}</strong>
                                <small>{{ $tracking['phone'] ?: 'Téléphone non renseigné' }}</small>
                            </span>
                        </a>
                    </td>
                    <td>
                        <span class="ops-driver-presence {{ $online ? 'is-online' : 'is-offline' }}"><i></i>{{ $online ? 'En ligne' : 'Hors ligne' }}</span>
                        <small class="ops-driver-list-sub">{{ $online ? 'GPS récent' : 'Aucun signal récent' }}</small>
                    </td>
                    <td>
                        @if($availability === 'En mission')
                            <span class="ops-badge is-blue">En mission</span>
                        @elseif($online && $availability === 'Disponible')
                            <span class="ops-badge is-green">Disponible</span>
                        @elseif($online)
                            <span class="ops-badge">Indisponible</span>
                        @else
                            <span class="ops-driver-list-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="ops-driver-list-vehicle-real">
                            @if($tracking['vehiclePhotoUrl'])
                                <img src="{{ $tracking['vehiclePhotoUrl'] }}" alt="{{ $tracking['vehicle'] }}" class="ops-driver-list-vehicle-photo">
                            @endif
                            <span>
                                <strong class="ops-driver-list-vehicle">{{ $tracking['vehicle'] }}</strong>
                                <small class="ops-driver-list-sub">
                                    {{ $tracking['vehicleColor'] ?: 'Couleur non détectée' }}
                                    @if($tracking['vehiclePlate']) · <b>{{ $tracking['vehiclePlate'] }}</b>@endif
                                </small>
                            </span>
                        </div>
                    </td>
                    <td>{{ $row['zones'] ?: ($tracking['zone'] ?: 'Non renseignée') }}</td>
                    <td>
                        @if($lastPosition)
                            <strong>{{ $lastPosition->format('H:i') }}</strong>
                            <small class="ops-driver-list-sub">{{ $lastPosition->locale('fr')->diffForHumans() }}</small>
                        @else
                            <span class="ops-driver-list-muted">Aucune position</span>
                        @endif
                    </td>
                    <td>
                        @if($row['missionCount'] > 0)
                            <span class="ops-badge is-blue">{{ $row['missionCount'] }} active</span>
                        @else
                            <span class="ops-driver-list-muted">Aucune</span>
                        @endif
                    </td>
                    <td>
                        <a class="ops-button ops-button-primary ops-driver-list-follow" href="{{ route('logistics.tracking.driver',$driver) }}"><x-operations.icon name="target"/>Suivre</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ops-driver-list-empty"><x-operations.icon name="user"/><strong>Aucun livreur trouvé</strong><span>Modifiez les filtres ou vérifiez les livreurs actifs.</span></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
