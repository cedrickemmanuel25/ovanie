@extends('layouts.logistics-operations')

@section('title', 'Livreurs')
@section('page-class', 'ops-directory')

@include('logistics.directory.assets')

@section('content')
@php
    $directoryData = \App\ViewModels\LogisticsDirectoryData::class;

    $total = $allDrivers->count();
    $onlineDrivers = $allDrivers
        ->filter(fn ($driver) => $directoryData::driverConnectionStatus($driver)[0] === 'En ligne')
        ->values();
    $online = $onlineDrivers->count();
    $offline = max(0, $total - $online);
    $available = $onlineDrivers
        ->filter(fn ($driver) => $directoryData::driverAvailabilityStatus($driver)[0] === 'Disponible')
        ->count();
    $busy = $onlineDrivers
        ->filter(fn ($driver) => $directoryData::driverAvailabilityStatus($driver)[0] === 'En mission')
        ->count();
    $unavailable = $onlineDrivers
        ->filter(fn ($driver) => $directoryData::driverAvailabilityStatus($driver)[0] === 'Indisponible')
        ->count();
    $localizedOnlineDrivers = $onlineDrivers
        ->sortByDesc(fn ($driver) => $driver->lastGpsSeenAt()?->getTimestamp() ?? 0)
        ->take(5)
        ->values();

    $tones = ['green', 'blue', 'orange', 'purple', 'slate'];
    $fleetRows = collect($fleetInUse ?? [])
        ->map(function ($count, $label) use ($tones, $fleetVehiclesTotal) {
            static $index = 0;
            $tone = $tones[$index % count($tones)];
            $index++;

            return [
                $label,
                (int) $count,
                $tone,
                max(1, (int) $fleetVehiclesTotal),
            ];
        })
        ->values()
        ->all();
@endphp

<x-operations.directory-header
    title="Livreurs"
    section="Livreurs"
    subtitle="Liste complète des livreurs, disponibilité, véhicule, zone, notation et missions"
>
    <a class="ops-button" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">
        <x-operations.icon name="download" />
        Exporter
    </a>
    <button class="ops-button ops-button-primary" type="button" data-directory-open="driver-create">
        <x-operations.icon name="plus" />
        Ajouter un livreur
    </button>
</x-operations.directory-header>

<div class="directory-kpis directory-kpis-clickable">
    <a href="{{ route('logistics.drivers') }}"><x-operations.kpi icon="user" label="Livreurs au total" :value="$total" tone="green" /></a>
    <a href="{{ request()->fullUrlWithQuery(['status'=>'Disponible','connection'=>'online','page'=>null]) }}"><x-operations.kpi icon="check" label="Disponibles" :value="$available" tone="green" /></a>
    <a href="{{ request()->fullUrlWithQuery(['status'=>'En mission','connection'=>'online','page'=>null]) }}"><x-operations.kpi icon="truck" label="En mission" :value="$busy" tone="blue" /></a>
    <a href="{{ request()->fullUrlWithQuery(['connection'=>'offline','status'=>null,'page'=>null]) }}"><x-operations.kpi icon="clock" label="Hors ligne" :value="$offline" tone="slate" /></a>
    <a href="{{ request()->fullUrlWithQuery(['connection'=>'online','status'=>null,'page'=>null]) }}"><x-operations.kpi icon="chart" label="En ligne" :value="$online" tone="green" /></a>
</div>

@php
    $onboardingTabLabels = [
        '' => 'Tous',
        'invited' => "En attente d'inscription",
        'pending_review' => 'Dossiers à vérifier',
        'active' => 'Actifs',
        'suspended' => 'Suspendus',
        'rejected' => 'Refusés',
    ];
@endphp
<div class="directory-status-tabs" role="tablist" aria-label="Filtrer les livreurs par statut d’inscription">
    @foreach ($onboardingTabLabels as $onboardingTabValue => $onboardingTabLabel)
        @php
            $onboardingTabCount = $onboardingTabValue === '' ? $total : (int) ($onboardingCounts[$onboardingTabValue] ?? 0);
            $onboardingTabActive = (string) request('onboarding', '') === (string) $onboardingTabValue;
        @endphp
        <a
            class="directory-status-tab {{ $onboardingTabActive ? 'is-active' : '' }}"
            href="{{ request()->fullUrlWithQuery(['onboarding' => $onboardingTabValue ?: null, 'page' => null]) }}"
            role="tab"
            aria-selected="{{ $onboardingTabActive ? 'true' : 'false' }}"
        >
            {{ $onboardingTabLabel }}
            <span class="directory-status-tab-count">{{ $onboardingTabCount }}</span>
        </a>
    @endforeach
</div>

<form class="directory-filters" method="get" action="{{ route('logistics.drivers') }}">
    <div class="directory-search">
        <x-operations.icon name="search" />
        <input
            name="q"
            value="{{ request('q') }}"
            placeholder="Rechercher un livreur, une zone, un véhicule…"
            aria-label="Rechercher un livreur"
        >
    </div>

    <label>
        Disponibilité
        <select name="status" onchange="this.form.submit()">
            <option value="">Toutes</option>
            @foreach (['Disponible', 'Indisponible', 'En mission'] as $statusOption)
                <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>
                    {{ $statusOption }}
                </option>
            @endforeach
        </select>
    </label>

    <label>
        Connexion GPS
        <select name="connection" onchange="this.form.submit()">
            <option value="">Toutes</option>
            <option value="online" @selected(request('connection') === 'online')>En ligne</option>
            <option value="offline" @selected(request('connection') === 'offline')>Hors ligne</option>
        </select>
    </label>

    <label>
        Zone
        <select name="zone" onchange="this.form.submit()">
            <option value="">Toutes</option>
            @foreach ($zones as $zoneOption)
                <option value="{{ $zoneOption->name }}" @selected(request('zone') === $zoneOption->name)>
                    {{ $zoneOption->name }}
                </option>
            @endforeach
        </select>
    </label>

    <label>
        Véhicule
        <select name="vehicle" onchange="this.form.submit()">
            <option value="">Tous</option>
            @foreach (['Moto', 'Tricycle', 'Fourgonnette', 'Pickup', 'Camion'] as $vehicleOption)
                <option value="{{ $vehicleOption }}" @selected(request('vehicle') === $vehicleOption)>
                    {{ $vehicleOption }}
                </option>
            @endforeach
        </select>
    </label>

    {{-- Le filtre "Inscription" est désormais géré par les compteurs cliquables
         ci-dessus (mêmes paramètre/valeurs `onboarding=...`), afin de rester
         visible et lisible sans dupliquer le système de filtrage. --}}
    <input type="hidden" name="onboarding" value="{{ request('onboarding') }}">

    <a href="{{ route('logistics.drivers') }}">
        <x-operations.icon name="refresh" />
        Réinitialiser les filtres
    </a>
</form>

<div class="directory-grid">
    <x-operations.panel title="Liste des livreurs" icon="list" class="directory-table-panel">
        <div class="directory-table-wrap">
            <table class="directory-table">
                <thead>
                    <tr>
                        @foreach (['Livreur', 'Inscription', 'Connexion GPS', 'Disponibilité', 'Véhicule', 'Zones', 'Notation', 'Missions', 'Actions'] as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($drivers->count() > 0)
                        @foreach ($drivers as $driver)
                            @php
                                [$connectionStatus, $connectionTone] = $directoryData::driverConnectionStatus($driver);
                                [$availabilityStatus, $availabilityTone] = $directoryData::driverAvailabilityStatus($driver);
                                [$onboardingLabel, $onboardingTone] = $directoryData::driverOnboarding($driver);
                                $lastGpsAt = $driver->lastGpsSeenAt();
                            @endphp
                            <tr>
                                <td>
                                    <a class="directory-person" href="{{ route('logistics.drivers.show', $driver) }}">
                                        @if ($driver->avatar)
                                            <img class="directory-avatar" src="{{ Storage::disk('public')->url($driver->avatar) }}" alt="Photo de {{ $driver->name }}">
                                        @else
                                            <span class="directory-avatar">{{ $driver->initials }}</span>
                                        @endif
                                        <span>
                                            <strong>{{ $driver->name }}</strong>
                                            @if (!empty($driver->phone))
                                                <small>{{ $driver->phone }}</small>
                                            @endif
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    @if ($driver->onboarding_status === 'pending_review')
                                        <a href="{{ route('logistics.drivers.review', $driver) }}">
                                            <x-operations.tag :tone="$onboardingTone">{{ $onboardingLabel }}</x-operations.tag>
                                        </a>
                                        @if ($driver->submitted_at)
                                            <small>Envoyé le {{ $driver->submitted_at->format('d/m/Y à H:i') }}</small>
                                        @endif
                                    @else
                                        <x-operations.tag :tone="$onboardingTone">{{ $onboardingLabel }}</x-operations.tag>
                                    @endif
                                </td>
                                <td>
                                    <x-operations.tag :tone="$connectionTone">{{ $connectionStatus }}</x-operations.tag>
                                    <small class="driver-presence-meta">
                                        @if($lastGpsAt)
                                            GPS {{ $lastGpsAt->locale('fr')->diffForHumans() }}
                                        @else
                                            Aucun GPS reçu
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <x-operations.tag :tone="$availabilityTone">{{ $availabilityStatus }}</x-operations.tag>
                                </td>
                                <td>
                                    @php
                                        $vehicleLabelsMap = ['moto' => 'Moto', 'tricycle' => 'Tricycle', 'pickup' => 'Pickup', 'camion_3t' => 'Camion 3T', 'camion_10t' => 'Camion 10T'];
                                        $vehicleRegistration = $driver->fleet_vehicle_registration ?: data_get($driver->profile, 'plate');
                                    @endphp
                                    {{ $driver->fleet_vehicle_label ?: ($vehicleLabelsMap[$driver->vehicle] ?? $driver->vehicle) ?: 'Non renseigné' }}
                                    @if (!empty($vehicleRegistration))
                                        <small>{{ $vehicleRegistration }}</small>
                                    @endif
                                </td>
                                <td class="directory-zone-cell">
                                    @php($driverZones = $driver->interventionZones())
                                    <span class="directory-inline" title="{{ implode(', ', $driverZones) }}">
                                        <x-operations.icon name="pin" />
                                        {{ $driverZones[0] ?? 'Non renseignée' }}
                                    </span>
                                    @if (count($driverZones) > 1)
                                        <small>+{{ count($driverZones) - 1 }} autre{{ count($driverZones) > 2 ? 's' : '' }} zone{{ count($driverZones) > 2 ? 's' : '' }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($driver->rating !== null)
                                        <span class="directory-rating">★ <span>{{ number_format($driver->rating, 1, ',', ' ') }}</span></span>
                                    @else
                                        <span>Pas encore noté</span>
                                    @endif
                                </td>
                                <td>{{ (int) $driver->assignments_count }} missions</td>
                                <td>
                                    <div class="actions">
                                        @if ($driver->onboarding_status === 'pending_review')
                                            <a href="{{ route('logistics.drivers.review', $driver) }}" title="Examiner et valider le dossier">Examiner le dossier</a>
                                        @endif
                                        <a href="{{ route('logistics.drivers.show', $driver) }}" aria-label="Fiche de {{ $driver->name }}">
                                            <x-operations.icon name="eye" />
                                        </a>
                                        @if (!empty($driver->phone))
                                            <a href="tel:{{ $driver->phone }}" aria-label="Appeler {{ $driver->name }}">
                                                <x-operations.icon name="phone" />
                                            </a>
                                        @endif
                                        <a href="{{ route('logistics.drivers.show', $driver) }}#documents" aria-label="Documents du livreur">
                                            ⋮
                                        </a>
                                        <form method="post" action="{{ route('logistics.drivers.destroy', $driver) }}" onsubmit="return confirm('Supprimer définitivement le compte de {{ $driver->name }} ? Cette action est irréversible.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Supprimer {{ $driver->name }}">
                                                <x-operations.icon name="trash" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="9" class="directory-empty">Aucun livreur ne correspond aux filtres.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <x-operations.directory-pagination :paginator="$drivers" />
    </x-operations.panel>

    <aside class="directory-stack">
        <x-operations.panel title="En ligne et localisés" icon="pin">
            <x-slot:actions>
                <a href="{{ request()->fullUrlWithQuery(['connection'=>'online','status'=>null,'page'=>null]) }}">Voir les {{ $online }} →</a>
            </x-slot:actions>
            @forelse($localizedOnlineDrivers as $onlineDriver)
                <a class="directory-follow driver-presence-row" href="{{ route('logistics.tracking.driver', $onlineDriver) }}">
                    <span class="directory-person">
                        @if ($onlineDriver->avatar)
                            <img class="directory-avatar" src="{{ Storage::disk('public')->url($onlineDriver->avatar) }}" alt="Photo de {{ $onlineDriver->name }}">
                        @else
                            <span class="directory-avatar">{{ $onlineDriver->initials }}</span>
                        @endif
                        <span>
                            <strong>{{ $onlineDriver->name }}</strong>
                            <small>{{ $onlineDriver->interventionZonesLabel(1) }}</small>
                        </span>
                    </span>
                    <span class="directory-link driver-presence-live">
                        ● GPS localisé
                        <small>{{ $onlineDriver->lastGpsSeenAt()?->locale('fr')->diffForHumans() ?: 'à l’instant' }}</small>
                    </span>
                </a>
            @empty
                <p class="directory-empty">Aucun livreur avec une position GPS récente.</p>
            @endforelse
        </x-operations.panel>

        <x-operations.panel title="Répartition" icon="chart">
            <x-operations.directory-bars
                :rows="[
                    ['Disponible', $available, 'green', max(1, $total)],
                    ['Indisponible', $unavailable, 'orange', max(1, $total)],
                    ['En mission', $busy, 'blue', max(1, $total)],
                    ['Hors ligne', $offline, 'slate', max(1, $total)],
                ]"
            />
        </x-operations.panel>

        <x-operations.panel title="Flotte utilisée" icon="truck">
            @if (count($fleetRows) > 0)
                <x-operations.directory-bars :rows="$fleetRows" />
            @else
                <p class="directory-empty">Aucun véhicule de flotte enregistré.</p>
            @endif
        </x-operations.panel>

        <x-operations.panel title="Livreurs à suivre" icon="target">
            <x-slot:actions>
                <a href="{{ route('logistics.tracking') }}">Voir tout →</a>
            </x-slot:actions>

            @if ($driversToWatch->count() > 0)
                @foreach ($driversToWatch as $driverToWatch)
                    @continue(! $driverToWatch)
                    <a class="directory-follow" href="{{ route('logistics.tracking.driver', $driverToWatch) }}">
                    @php($lastTrackingAt = $driverToWatch->currentLocation?->recorded_at ?? $driverToWatch->last_seen_at)
                        <span class="directory-person">
                            @if ($driverToWatch->avatar)
                                <img class="directory-avatar" src="{{ Storage::disk('public')->url($driverToWatch->avatar) }}" alt="Photo de {{ $driverToWatch->name }}">
                            @else
                                <span class="directory-avatar">{{ $driverToWatch->initials }}</span>
                            @endif
                            <span>
                                <strong>{{ $driverToWatch->name }}</strong>
                                <small>
                                    {{ $driverToWatch->fleet_vehicle_type ?: $driverToWatch->vehicle ?: 'Véhicule non renseigné' }}
                                    · {{ $driverToWatch->interventionZonesLabel(1) }}
                                </small>
                            </span>
                        </span>
                        <span class="directory-link">
                            ● {{ (int) $driverToWatch->active_assignments_count }} mission{{ (int) $driverToWatch->active_assignments_count > 1 ? 's' : '' }} active{{ (int) $driverToWatch->active_assignments_count > 1 ? 's' : '' }}
                            <small>{{ $lastTrackingAt ? $lastTrackingAt->locale('fr')->diffForHumans() : 'GPS indisponible' }}</small>
                        </span>
                    </a>
                @endforeach
            @else
                <p class="directory-empty">Aucune mission active à suivre.</p>
            @endif
        </x-operations.panel>
    </aside>
</div>

@include('logistics.directory.driver-form', ['editing' => false, 'driver' => null])

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const refreshMs = 20000;
    window.setInterval(function () {
        if (document.visibilityState !== 'visible') return;
        if (document.querySelector('dialog.directory-modal[open]')) return;
        const active = document.activeElement;
        if (active && ['INPUT','SELECT','TEXTAREA'].includes(active.tagName)) return;
        window.location.reload();
    }, refreshMs);
});
</script>
@endpush
@endsection
