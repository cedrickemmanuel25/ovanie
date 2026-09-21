@php($mapMode = $trackingMapMode ?? 'mission')
<div class="ops-tracking-map ops-dispatch-map" data-tracking-map data-map-mode="{{ $mapMode }}" data-map-points='@json($trackingPoints)' data-map-routes='@json($trackingRoutes)'>
    <div class="ops-tracking-map-canvas" aria-label="Carte du suivi GPS"></div>
    @isset($mapFilters){{ $mapFilters }}@endisset

    <div class="ops-tracking-map-tools ops-dispatch-map-tools">
        <button type="button" data-tracking-zoom="1" aria-label="Zoom avant">+</button>
        <button type="button" data-tracking-zoom="-1" aria-label="Zoom arrière">−</button>
        <button type="button" data-tracking-center aria-label="Centrer la carte"><x-operations.icon name="target"/></button>
        @if($mapMode !== 'driver')
            <button type="button" data-tracking-layers aria-label="Afficher ou masquer le trajet" aria-pressed="true"><x-operations.icon name="map"/></button>
        @endif
    </div>

    @if($mapMode === 'driver')
        <div class="ops-tracking-legend ops-tracking-legend-driver">
            <span class="text-blue"><x-operations.icon name="user"/>Position du livreur</span>
            <span><x-operations.icon name="clock"/>Dernière position GPS enregistrée</span>
        </div>
    @else
        <div class="ops-dispatch-route-legend" data-route-legend hidden>
            <span class="is-shop"><i></i>Collecte</span>
            <span class="is-driver"><i></i>Livreur</span>
            <span class="is-client"><i></i>Client</span>
        </div>
    @endif
</div>
