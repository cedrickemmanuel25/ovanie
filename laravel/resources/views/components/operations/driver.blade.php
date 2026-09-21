@props(['driver' => null, 'vehicle' => null, 'plate' => null])
<div {{ $attributes->class('ops-driver-vehicle') }}>
    <div class="ops-driver-card">
        <span class="ops-avatar"><x-operations.icon name="user"/></span>
        <div>
            <strong>{{ $driver['name'] ?? 'Non affecté' }}</strong>
            <span class="ops-rating"><x-operations.icon name="star"/>{{ $driver['rating'] ?? '—' }}</span>
            <small>{{ $driver['zone'] ?? 'Zone non renseignée' }} · {{ ($driver['online'] ?? false) ? 'En ligne' : 'Hors ligne' }}</small>
            @if(isset($driver['reviews']))<small>{{ $driver['reviews'] }} mission(s) enregistrée(s)</small>@endif
        </div>
        @if(!empty($driver['phone']))<a class="ops-button ops-button-small" href="tel:{{ $driver['phone'] }}"><x-operations.icon name="phone"/>Appeler</a>@endif
    </div>
    <div class="ops-vehicle-card">
        <span class="ops-square-icon tone-blue"><x-operations.icon name="truck"/></span>
        <div>
            <strong>{{ $vehicle ?? 'Véhicule' }} <span>{{ $plate ?? '—' }}</span></strong>
            <small>Véhicule enregistré sur la tournée</small>
        </div>
    </div>
</div>
