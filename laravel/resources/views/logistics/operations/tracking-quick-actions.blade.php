<div class="ops-tracking-quick">
@if($trackedDriver['phone'] ?? null)
    <a class="ops-button ops-button-primary" href="tel:{{ $trackedDriver['phone'] }}"><x-operations.icon name="phone"/>Contacter le livreur</a>
@endif

@if(($trackingContext ?? 'mission') === 'driver')
    @if($trackedMission)
        <a class="ops-button" href="{{ $trackedMission['trackingUrl'] }}"><x-operations.icon name="box"/>Voir le suivi de la mission</a>
    @endif
@else
    @if($trackedMission)
        <a class="ops-button" href="{{ $trackedMission['detailsUrl'] }}"><x-operations.icon name="list"/>Voir la fiche mission</a>
    @endif
    @if($trackedDriver['trackingUrl'] ?? null)
        <a class="ops-button" href="{{ $trackedDriver['trackingUrl'] }}"><x-operations.icon name="user"/>Suivre le livreur</a>
    @endif
@endif

<button class="ops-button" data-tracking-fullscreen><x-operations.icon name="expand"/>Ouvrir en plein écran</button>
</div>
