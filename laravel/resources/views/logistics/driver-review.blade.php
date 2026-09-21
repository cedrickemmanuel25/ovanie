@extends('layouts.logistics-operations')
@section('title', 'Dossier livreur à vérifier')
@section('page-class', 'ops-directory')
@include('logistics.directory.assets')
@section('content')
@php
    use App\ViewModels\LogisticsDirectoryData as D;

    $profile = $driver->profile ?? [];
    [$onboardingLabel, $onboardingTone] = D::driverOnboarding($driver);

    $vehicleLabels = ['moto' => 'Moto', 'tricycle' => 'Tricycle', 'pickup' => 'Pickup', 'camion_3t' => 'Camion 3T', 'camion_10t' => 'Camion 10T'];
    $vehicleLabel = $vehicleLabels[$driver->vehicle] ?? ($driver->vehicle ? ucfirst(str_replace('_', ' ', (string) $driver->vehicle)) : 'Non renseigné');

    $dayLabels = ['lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche'];
    $availabilityDays = collect(data_get($profile, 'availability_days', []))
        ->map(fn ($day) => $dayLabels[$day] ?? ucfirst((string) $day))
        ->values();

    $zones = $driver->interventionZones();

    $documents = collect(data_get($profile, 'documents', []));
    $supportingDocuments = collect(data_get($profile, 'supporting_documents', []));
@endphp

<x-operations.directory-header
    title="Dossier livreur"
    section="Livreurs"
    :url="route('logistics.drivers')"
    subtitle="Vérifiez les informations soumises par le livreur depuis l’application mobile OVANIE Livreur avant de valider son accès aux missions."
>
    <x-operations.tag :tone="$onboardingTone">{{ $onboardingLabel }}</x-operations.tag>
</x-operations.directory-header>

<div class="directory-grid">
    <div class="directory-stack">
        <x-operations.panel title="Identité" icon="user">
            <div class="directory-person" style="margin-bottom:14px">
                @if ($driver->avatar)
                    <img class="directory-avatar large" src="{{ Storage::disk('public')->url($driver->avatar) }}" alt="Photo de {{ $driver->name }}">
                @else
                    <span class="directory-avatar large">{{ $driver->initials }}</span>
                @endif
                <span>
                    <strong>{{ $driver->name }}</strong>
                    <br>
                    <small>{{ $driver->phone }}</small>
                </span>
            </div>
            <dl class="directory-facts">
                <dt>Nom</dt><dd>{{ $driver->last_name ?: '—' }}</dd>
                <dt>Prénom</dt><dd>{{ $driver->first_name ?: '—' }}</dd>
                <dt>Téléphone</dt><dd>{{ $driver->phone }}</dd>
                <dt>Soumis le</dt><dd>{{ $driver->submitted_at?->format('d/m/Y à H:i') ?? 'Non soumis' }}</dd>
            </dl>
        </x-operations.panel>

        <x-operations.panel title="Véhicule" icon="truck">
            <dl class="directory-facts">
                <dt>Type</dt><dd>{{ $vehicleLabel }}</dd>
                <dt>Immatriculation</dt><dd>{{ data_get($profile, 'plate') ?: 'Non renseignée' }}</dd>
            </dl>
            @include('logistics.directory.driver-photos')
        </x-operations.panel>

        <x-operations.panel title="Zones d’intervention" icon="pin">
            @if (count($zones))
                <div class="directory-zone-summary">
                    @foreach ($zones as $zone)
                        <span class="directory-zone-pill">{{ $zone }}</span>
                    @endforeach
                </div>
            @else
                <p class="directory-empty">Aucune zone renseignée.</p>
            @endif
        </x-operations.panel>

        <x-operations.panel title="Disponibilité" icon="clock">
            @if ($availabilityDays->isNotEmpty())
                <div class="directory-zone-summary">
                    @foreach ($availabilityDays as $day)
                        <span class="directory-zone-pill">{{ $day }}</span>
                    @endforeach
                </div>
            @else
                <p class="directory-empty">Aucune disponibilité renseignée.</p>
            @endif

        </x-operations.panel>

        <x-operations.panel title="Justificatifs" icon="list">
            @if ($documents->isNotEmpty() || $supportingDocuments->isNotEmpty())
                <div class="directory-files">
                    @foreach ($documents as $label => $document)
                        <div>
                            <x-operations.icon name="list" />
                            <span>{{ $label }} <x-operations.tag tone="slate">{{ $document['status'] ?? 'À vérifier' }}</x-operations.tag></span>
                            @if (! empty($document['path']))
                                <a href="{{ route('logistics.drivers.document', [$driver, array_search($label, ['identity' => 'Pièce d’identité', 'registration' => 'Carte grise', 'license' => 'Permis de conduire', 'insurance' => 'Assurance']) ?: 'identity']) }}" target="_blank" rel="noopener">Voir</a>
                            @endif
                        </div>
                    @endforeach
                    @foreach ($supportingDocuments as $index => $path)
                        <div><span>Justificatif supplémentaire {{ $index + 1 }}</span><a href="{{ route('logistics.drivers.document', [$driver, 'supporting-'.$index]) }}" target="_blank" rel="noopener">Voir</a></div>
                    @endforeach
                </div>
            @else
                <p class="directory-empty">Aucun justificatif transmis.</p>
            @endif
        </x-operations.panel>
    </div>

    <aside class="directory-stack">
        <x-operations.panel title="Décision" icon="check">
            @if ($driver->onboarding_status === 'rejected' && $driver->rejection_reason)
                <div class="ops-feedback is-red" style="margin-bottom:12px">Précédent motif de refus : {{ $driver->rejection_reason }}</div>
            @elseif ($driver->onboarding_status === 'invited' && $driver->rejection_reason)
                <div class="ops-feedback is-orange" style="margin-bottom:12px">Correction demandée précédemment : {{ $driver->rejection_reason }}</div>
            @endif

            <form method="post" action="{{ route('logistics.drivers.review.approve', $driver) }}" style="margin-bottom:14px">
                @csrf
                <button class="ops-button ops-button-primary" type="submit" style="width:100%" @if ($driver->onboarding_status !== 'pending_review' && $driver->onboarding_status !== 'rejected') onclick="return confirm('Valider ce dossier même s’il n’a pas encore été soumis ?')" @endif>
                    <x-operations.icon name="check" />
                    Valider le dossier
                </button>
            </form>

            <form class="directory-form" method="post" action="{{ route('logistics.drivers.review.request-correction', $driver) }}" style="margin-bottom:14px">
                @csrf
                <label>
                    Motif de la correction demandée
                    <textarea name="rejection_reason" required maxlength="1000" placeholder="Ex. Photo du véhicule illisible, immatriculation à corriger…"></textarea>
                </label>
                <button class="ops-button" type="submit" style="width:100%;margin-top:10px">
                    <x-operations.icon name="alert" />
                    Demander une correction
                </button>
            </form>

            <form class="directory-form" method="post" action="{{ route('logistics.drivers.review.reject', $driver) }}">
                @csrf
                <label>
                    Motif du refus
                    <textarea name="rejection_reason" required maxlength="1000" placeholder="Ex. Photo du véhicule illisible, pièce d’identité expirée…"></textarea>
                </label>
                <button class="ops-button" type="submit" style="width:100%;margin-top:10px">Refuser le dossier</button>
            </form>
        </x-operations.panel>

        <x-operations.panel title="Informations" icon="user">
            <dl class="directory-facts">
                <dt>Inscription</dt><dd><x-operations.tag :tone="$onboardingTone">{{ $onboardingLabel }}</x-operations.tag></dd>
                <dt>Invité le</dt><dd>{{ $driver->created_at?->format('d/m/Y') }}</dd>
                <dt>Revu le</dt><dd>{{ $driver->reviewed_at?->format('d/m/Y à H:i') ?? '—' }}</dd>
                <dt>Revu par</dt><dd>{{ $driver->reviewer?->name ?? '—' }}</dd>
            </dl>
        </x-operations.panel>
    </aside>
</div>
@endsection
