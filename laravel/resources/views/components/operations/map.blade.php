@props(['tour' => null, 'mode' => 'list', 'summary' => true, 'legend' => false])
@php
    $mapStops = collect();
    if (!empty($tour['start']['lat']) && !empty($tour['start']['lng'])) {
        $mapStops->push(array_merge($tour['start'], ['label' => $tour['start']['label'] ?? 'Départ', 'kind' => 'driver', 'status' => 'start']));
    }
    $mapStops = $mapStops->concat(collect($tour['stops'] ?? []))->values();
@endphp
<div {{ $attributes->class(['ops-map', 'ops-map-'.$mode]) }} data-map data-map-points="{{ json_encode($mapStops) }}" data-map-geometry="{{ json_encode($tour['routeSegments'] ?? []) }}">
    <div class="ops-map-canvas" aria-label="Carte des arrêts de la tournée"></div>
    @if($summary)
        <div class="ops-map-summary">
            @foreach([
                ['route','Distance totale',isset($tour['distance']) && $tour['distance'] !== null ? $tour['distance'].' km':'À confirmer'],
                ['clock','Temps estimé',\App\ViewModels\LogisticsOperationsData::duration($tour['duration']??null)],
                ['clock','Temps restant',\App\ViewModels\LogisticsOperationsData::duration($tour['remaining']??null)],
                ['target','Moteur routier',$tour['routingProvider'] ?? 'À confirmer'],
            ] as [$icon,$label,$value])
                <div><x-operations.icon :name="$icon"/><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
            @endforeach
        </div>
    @endif
    @if(in_array($mode,['detail','supervision']))
        <button type="button" class="ops-map-fullscreen" data-map-fullscreen aria-label="Afficher la carte en plein écran"><x-operations.icon name="expand"/>Plein écran</button>
    @endif
    <div class="ops-map-controls"><div><button type="button" data-map-zoom="1" aria-label="Zoom avant">+</button><button type="button" data-map-zoom="-1" aria-label="Zoom arrière">−</button></div><button type="button" data-map-center aria-label="Recentrer la carte"><x-operations.icon name="target"/></button></div>
    @if($legend)
        <div class="ops-map-legend">
            <span><x-operations.icon name="truck"/>Départ livreur</span>
            <span><x-operations.icon name="warehouse"/>Collecte boutique</span>
            <span class="text-blue"><x-operations.icon name="pin"/>Livraison client</span>
            <span><i></i>Itinéraire routier</span>
        </div>
    @endif
</div>
