@php
    $trustedShopGpsSources = ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'];
    $dashboardShops = collect($ovanieShops ?? [])->filter(function ($shop) use ($trustedShopGpsSources) {
        return in_array(strtolower((string) ($shop['geo_source'] ?? '')), $trustedShopGpsSources, true);
    })->map(fn ($shop) => [
        'id' => $shop['id'] ?? null,
        'name' => $shop['name'] ?? 'Boutique OVANIE',
        'address' => $shop['address'] ?? null,
        'commune' => $shop['commune'] ?? null,
        'lat' => $shop['lat'] ?? null,
        'lng' => $shop['lng'] ?? null,
    ])->filter(fn ($shop) => is_numeric($shop['lat']) && is_numeric($shop['lng']))->values();

    $dashboardDriversMap = collect($dashboardGpsDrivers ?? [])->values();

    // Comme dans l'app livreur, le point client n'apparaît qu'une fois la
    // collecte confirmée (le livreur n'a même pas encore le colis avant ça).
    $dashboardActiveMissions = collect($mapMissions ?? [])->filter(function ($row) {
        $status = (string) ($row['status'] ?? '');
        return (! empty($row['driverId']) || in_array($status, ['assigned', 'picked_up', 'in_transit'], true))
            && ($row['pickupDone'] ?? false);
    })->filter(fn ($row) => is_numeric($row['lat'] ?? null) && is_numeric($row['lng'] ?? null))->map(fn ($row) => [
        'id' => $row['id'] ?? null,
        'reference' => $row['reference'] ?? 'Mission',
        'destination' => $row['destination'] ?? 'Destination',
        'driver_id' => $row['driverId'] ?? null,
        'delayed' => (bool) ($row['delayed'] ?? false),
        'lat' => $row['lat'],
        'lng' => $row['lng'],
        'url' => $row['trackingUrl'] ?? null,
    ])->values();

    // Le tracé de chaque mission active doit être visible dès l'accueil, pas
    // seulement après avoir ouvert le Centre de suivi GPS. Mais tant que le
    // livreur n'a pas accepté la mission, il n'y a rien de réel à tracer.
    $dashboardMissionRoutes = collect($mapMissions ?? [])
        ->filter(fn ($row) => $row['assignmentAccepted'] ?? false)
        ->flatMap(fn ($row) => collect($row['routeSegments'] ?? [])->map(fn ($geometry) => [
            'missionId' => $row['id'] ?? null,
            'geometry' => $geometry,
            'phase' => $row['status'] ?? null,
        ]))
        ->values();
@endphp

<div class="ops-home-live-map"
     data-home-live-map
     data-shops='@json($dashboardShops)'
     data-drivers='@json($dashboardDriversMap)'
     data-missions='@json($dashboardActiveMissions)'
     data-routes='@json($dashboardMissionRoutes)'>
    <div class="ops-home-live-map-canvas" data-home-map-canvas aria-label="Carte opérationnelle OVANIE Logistics"></div>

    <div class="ops-home-map-toolbar">
        <div class="ops-home-map-state">
            <span class="ops-home-map-live-dot"></span>
            <strong>Vue opérationnelle</strong>
            <small><span data-home-live-online>{{ $dashboardDriversMap->count() }}</span> livreur(s) GPS en ligne</small>
        </div>
        <a href="{{ route('logistics.tracking') }}" class="ops-home-map-open-tracking">
            <x-operations.icon name="route"/> Ouvrir Tracking
        </a>
    </div>

    <div class="ops-home-map-controls" aria-label="Contrôles de la carte">
        <button type="button" data-home-map-zoom="1" aria-label="Zoom avant">+</button>
        <button type="button" data-home-map-zoom="-1" aria-label="Zoom arrière">−</button>
        <button type="button" data-home-map-center aria-label="Recentrer"><x-operations.icon name="target"/></button>
        <button type="button" data-home-map-fullscreen aria-label="Plein écran"><x-operations.icon name="expand"/></button>
    </div>

    <div class="ops-home-map-legend" data-home-live-legend aria-label="Légende de la carte">
        @if($dashboardDriversMap->where('status', 'available')->count())
            <span><i class="is-available"></i>{{ $dashboardDriversMap->where('status', 'available')->count() }} disponible(s)</span>
        @endif
        @if($dashboardDriversMap->where('status', 'mission')->count())
            <span><i class="is-mission"></i>{{ $dashboardDriversMap->where('status', 'mission')->count() }} en mission</span>
        @endif
        @if($dashboardDriversMap->where('status', 'unavailable')->count())
            <span><i class="is-unavailable"></i>{{ $dashboardDriversMap->where('status', 'unavailable')->count() }} indisponible(s)</span>
        @endif
        <span><i class="is-shop"></i>{{ $dashboardShops->count() }} boutique(s)</span>
        @if($dashboardActiveMissions->where('delayed', true)->count())
            <span><i class="is-late"></i>{{ $dashboardActiveMissions->where('delayed', true)->count() }} retard(s)</span>
        @endif
    </div>

    <span class="ops-home-map-sync" data-home-live-sync>Actualisé à {{ now()->format('H:i') }}</span>
</div>
