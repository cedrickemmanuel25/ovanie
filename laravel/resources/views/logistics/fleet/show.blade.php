@extends('layouts.logistics')
@section('title','Fiche véhicule')
@section('crumb','Flotte › Fiche véhicule')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
$statusLabels = ['available'=>'Disponible','mission'=>'En mission','maintenance'=>'Maintenance','out_of_service'=>'Hors service'];
$statusLabel = $statusLabels[$effectiveStatus] ?? ucfirst((string)$effectiveStatus);
$driverName = $driver?->name ?: $vehicle->driver_name ?: 'Non affecté';
$driverPhone = $driver?->phone ?: $vehicle->driver_phone;
$driverEmail = $driver?->email ?: $vehicle->driver_email;
$documents = collect($vehicle->documents ?? [])->map(function($doc,$key){
    if(!is_array($doc)) return null;
    return [
        'name' => $doc['label'] ?? $doc['name'] ?? (is_string($key) ? ucfirst($key) : 'Document'),
        'status' => $doc['status'] ?? 'Enregistré',
        'expires' => $doc['expires'] ?? $doc['expiration'] ?? null,
        'url' => null,
    ];
})->filter();
if ($registeredDriver && mb_strtolower(trim((string) data_get($registeredDriver->profile, 'plate'))) === mb_strtolower(trim($vehicle->registration))
    && data_get($registeredDriver->profile, 'documents.Carte grise.path')) {
    $documents->push([
        'name' => 'Carte grise transmise par le livreur',
        'status' => data_get($registeredDriver->profile, 'documents.Carte grise.status', 'Transmise'),
        'expires' => null,
        'url' => route('logistics.drivers.document', [$registeredDriver, 'registration']),
    ]);
}
$missionStatusLabels = [
    'planned'=>'Planifiée','assigned'=>'Affectée','accepted'=>'Acceptée','collecting'=>'Collecte','picked_up'=>'Collectée',
    'in_transit'=>'En transit','arrived'=>'Arrivée','delivered'=>'Livrée','cancelled'=>'Annulée','rejected'=>'Refusée',
];
@endphp
<main class="mg-page fleet-v2-page fleet-profile-page">
    <header class="mg-titlebar fleet-v2-titlebar fleet-profile-titlebar">
        <div>
            <h1>Fiche véhicule</h1>
            <p>Identité du véhicule, chauffeur affecté, disponibilité et activité opérationnelle.</p>
        </div>
        <div class="mg-actions">
            <a class="mg-btn fleet-v2-btn" href="{{ route('logistics.fleet') }}"><x-logistics.fleet-svg name="arrow-left"/>Retour à la flotte</a>
        </div>
    </header>

    <section class="fleet-profile-hero">
        <article class="fleet-v2-card fleet-profile-identity">
            <div class="fleet-profile-main-photo">@if($vehicleImage)<img src="{{ $vehicleImage }}" alt="Photo du véhicule {{ $vehicle->registration }}">@else<span>Aucune photo du véhicule transmise.</span>@endif</div>
            <div class="fleet-profile-identity-copy">
                <span class="fleet-profile-eyebrow">{{ $vehicle->typeLabel() }}</span>
                <h2>{{ trim(($vehicle->brand ?: '').' '.($vehicle->model ?: '')) ?: $vehicle->typeLabel() }}</h2>
                <strong>{{ $vehicle->registration }}</strong>
                <span class="mg-status {{ $effectiveStatus }}"><i></i>{{ $statusLabel }}</span>
            </div>
        </article>
        <article class="fleet-v2-card fleet-profile-stat"><span class="fleet-profile-stat-icon"><x-logistics.fleet-svg name="package"/></span><div><small>Capacité maximale</small><strong>{{ $vehicle->capacityLabel() }}</strong><span>{{ $vehicle->volumeM3() ? number_format($vehicle->volumeM3(),2,',',' ').' m³ utiles' : 'Volume non renseigné' }}</span></div></article>
        <article class="fleet-v2-card fleet-profile-stat"><span class="fleet-profile-stat-icon"><x-logistics.fleet-svg name="map-pin"/></span><div><small>Zones de service</small><strong>{{ count($serviceZones) }} commune{{ count($serviceZones) > 1 ? 's' : '' }}</strong><span>{{ implode(', ', $serviceZones) ?: 'Non renseignées' }}</span></div></article>
        <article class="fleet-v2-card fleet-profile-stat"><span class="fleet-profile-stat-icon"><x-logistics.fleet-svg name="route"/></span><div><small>Missions enregistrées</small><strong>{{ $totalMissionCount }}</strong><span>{{ $activeAssignment ? '1 mission active' : 'Aucune mission active' }}</span></div></article>
    </section>

    <section class="fleet-profile-main-grid">
        <article class="fleet-v2-card fleet-profile-section fleet-profile-details">
            <header><h2><x-logistics.fleet-svg name="truck"/>Informations du véhicule</h2></header>
            <dl class="fleet-profile-dl">
                <div><dt>Immatriculation</dt><dd>{{ $vehicle->registration }}</dd></div>
                <div><dt>Type</dt><dd>{{ $vehicle->typeLabel() }}</dd></div>
                <div><dt>Capacité de charge</dt><dd>{{ $vehicle->capacityLabel() }}</dd></div>
                @if($vehicle->volumeM3())
                <div><dt>Volume utile</dt><dd>{{ number_format($vehicle->volumeM3(),2,',',' ') }} m³</dd></div>
                @endif
            </dl>
            @if($vehicle->observations)
            <div class="fleet-profile-note"><strong>Observations</strong><p>{{ $vehicle->observations }}</p></div>
            @endif
        </article>

        <article class="fleet-v2-card fleet-profile-section fleet-profile-driver-card">
            <header><h2><x-logistics.fleet-svg name="user"/>Chauffeur affecté</h2>@if($driver)<a href="{{ route('logistics.tracking.driver',['driver'=>$driver->id]) }}">Suivre le livreur →</a>@endif</header>
            @if($driver)
            <div class="fleet-profile-driver">
                <div class="fleet-profile-driver-avatar">{{ $driver?->initials ?: collect(preg_split('/\s+/', trim($driverName)))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('') }}</div>
                <div><h3>{{ $driverName }}</h3><span>{{ $driver?->is_online ? 'En ligne' : 'Compte livreur' }}</span></div>
            </div>
            <dl class="fleet-profile-dl compact">
                <div><dt>Téléphone</dt><dd>{{ $driverPhone ?: 'Non renseigné' }}</dd></div>
                <div><dt>E-mail</dt><dd>{{ $driverEmail ?: 'Non renseigné' }}</dd></div>
                <div><dt>Zones du livreur</dt><dd>{{ implode(', ', $driver?->interventionZones() ?? []) ?: 'Non renseignées' }}</dd></div>
                <div><dt>Statut compte</dt><dd>{{ $driver?->is_active ? 'Actif' : ($driver ? 'Inactif' : 'Non vérifié') }}</dd></div>
            </dl>
            @if($driverPhone)<a class="fleet-profile-contact" href="tel:{{ $driverPhone }}"><x-logistics.fleet-svg name="phone"/>Contacter le chauffeur</a>@endif
            @else
            <div class="fleet-profile-empty">Aucun chauffeur n’est actuellement affecté à ce véhicule.</div>
            @endif
        </article>

        <article class="fleet-v2-card fleet-profile-section fleet-profile-state-card">
            <header><h2><x-logistics.fleet-svg name="gauge"/>Disponibilité & suivi</h2></header>
            <div class="fleet-profile-state"><span class="mg-status {{ $effectiveStatus }}"><i></i>{{ $statusLabel }}</span><small>État calculé à partir des affectations réelles</small></div>
            <dl class="fleet-profile-dl compact">
                <div><dt>Dernière position GPS</dt><dd>@if($latestLocation){{ number_format((float)$latestLocation->latitude,5,',',' ') }}, {{ number_format((float)$latestLocation->longitude,5,',',' ') }}@else Non disponible @endif</dd></div>
                <div><dt>Dernière synchronisation</dt><dd>{{ $latestLocation?->recorded_at?->diffForHumans() ?: 'Aucune donnée GPS' }}</dd></div>
                <div><dt>Précision GPS</dt><dd>{{ $latestLocation?->accuracy !== null ? number_format((float)$latestLocation->accuracy,0,',',' ').' m' : 'Non disponible' }}</dd></div>
                <div><dt>Mission active</dt><dd>{{ $activeAssignment?->resolved_mission_number ?: 'Aucune' }}</dd></div>
            </dl>
            @if($activeAssignment)
                @if($activeAssignment->order_item_id)
                <a class="fleet-profile-mission" href="{{ route('logistics.tracking.mission',['item'=>$activeAssignment->order_item_id]) }}">
                    <span><strong>{{ $activeAssignment->resolved_mission_number }}</strong><small>{{ $missionStatusLabels[$activeAssignment->status] ?? ucfirst($activeAssignment->status) }}</small></span><span>Voir le suivi →</span>
                </a>
                @else
                <div class="fleet-profile-mission"><span><strong>{{ $activeAssignment->resolved_mission_number }}</strong><small>{{ $missionStatusLabels[$activeAssignment->status] ?? ucfirst($activeAssignment->status) }}</small></span></div>
                @endif
            @endif
        </article>
    </section>

    <section class="fleet-profile-bottom-grid">
        <article class="fleet-v2-card fleet-profile-section">
            <header><h2><x-logistics.fleet-svg name="document-check"/>Documents du véhicule</h2></header>
            <div class="fleet-profile-list">
                @forelse($documents as $document)
                <div class="fleet-profile-list-row"><span><strong>{{ $document['name'] }}</strong><small>{{ $document['expires'] ? 'Expiration : '.$document['expires'] : 'Document enregistré' }}</small></span><em>{{ $document['status'] }}</em>@if($document['url'])<a href="{{ $document['url'] }}" target="_blank" rel="noopener">Voir</a>@endif</div>
                @empty<div class="fleet-profile-empty">Aucun document enregistré.</div>@endforelse
            </div>
        </article>

        <article class="fleet-v2-card fleet-profile-section fleet-profile-missions">
            <header><h2><x-logistics.fleet-svg name="route"/>Missions récentes</h2></header>
            <div class="fleet-profile-table-wrap">
                <table class="fleet-profile-table"><thead><tr><th>Mission</th><th>Date</th><th>Chauffeur</th><th>Destination</th><th>Statut</th></tr></thead><tbody>
                @forelse($assignments->take(6) as $assignment)
                <tr>
                    <td><strong>{{ $assignment->resolved_mission_number }}</strong></td>
                    <td>{{ optional($assignment->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $assignment->driver?->name ?: 'Non affecté' }}</td>
                    <td>{{ $assignment->orderItem?->order?->delivery_commune ?: $assignment->delivery_address ?: 'Non renseignée' }}</td>
                    <td><span class="fleet-profile-status-text">{{ $missionStatusLabels[$assignment->status] ?? ucfirst((string)$assignment->status) }}</span></td>
                </tr>
                @empty<tr><td colspan="5" class="fleet-profile-empty">Aucune mission réelle enregistrée pour ce véhicule.</td></tr>@endforelse
                </tbody></table>
            </div>
        </article>


    </section>
</main>
@endsection
