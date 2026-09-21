@extends('driver.layouts.app')
@section('title', 'Tableau de bord livreur | OVANIE')
@section('page-title', 'Tableau de bord')

@php
    $priorityMission = $missions->first(fn ($mission) => in_array($mission['status'], ['assigned', 'planned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'], true))
        ?? $missions->first();
@endphp

@section('content')
<div class="driver-page-heading">
    <div>
        <div class="driver-breadcrumb"><span>Espace livreur</span><i data-lucide="chevron-right"></i><strong>Tableau de bord</strong></div>
        <h1>Bonjour, {{ $driver->name }}</h1>
        <p>Consultez vos missions, vos horaires et les actions à réaliser aujourd’hui.</p>
    </div>
    <div class="driver-page-actions">
        <a href="{{ route('driver.missions.index') }}" class="driver-btn driver-btn--primary">
            <i data-lucide="clipboard-list"></i>
            Voir mes missions
        </a>
    </div>
</div>

<section class="driver-kpi-grid">
    <article class="driver-kpi-card driver-kpi-card--blue">
        <span class="driver-kpi-icon"><i data-lucide="truck"></i></span>
        <div><span>Missions actives</span><strong>{{ $stats['active'] }}</strong><small>À accepter ou à exécuter</small></div>
    </article>
    <article class="driver-kpi-card driver-kpi-card--orange">
        <span class="driver-kpi-icon"><i data-lucide="calendar-clock"></i></span>
        <div><span>Prévues aujourd’hui</span><strong>{{ $stats['today'] }}</strong><small>Selon votre planning</small></div>
    </article>
    <article class="driver-kpi-card driver-kpi-card--green">
        <span class="driver-kpi-icon"><i data-lucide="badge-check"></i></span>
        <div><span>Livraisons terminées</span><strong>{{ $stats['delivered'] }}</strong><small>Missions confirmées</small></div>
    </article>
    <article class="driver-kpi-card driver-kpi-card--purple">
        <span class="driver-kpi-icon"><i data-lucide="bell"></i></span>
        <div><span>Notifications</span><strong>{{ $stats['notifications'] }}</strong><small>Informations à consulter</small></div>
    </article>
</section>

@if($priorityMission)
<section class="driver-priority-card">
    <div class="driver-priority-card__content">
        <div class="driver-priority-card__label"><i data-lucide="zap"></i> Mission prioritaire</div>
        <h2>{{ $priorityMission['mission_number'] }}</h2>
        <p>{{ $priorityMission['order_number'] }} · Livraison vers {{ $priorityMission['commune'] ?: 'destination à confirmer' }}</p>

        <div class="driver-priority-metrics">
            <div><span>Collectes</span><strong>{{ $priorityMission['pickup_count'] }} point(s)</strong></div>
            <div><span>Véhicule</span><strong>{{ $priorityMission['vehicle_label'] ?: 'À confirmer' }}</strong></div>
            <div><span>Livraison prévue</span><strong>{{ $priorityMission['estimated_delivery_at']?->format('d/m/Y H:i') ?: 'À confirmer' }}</strong></div>
            <div><span>Préparation</span><strong>{{ $priorityMission['preparation_percent'] }}%</strong></div>
        </div>
    </div>
    <div class="driver-priority-card__action">
        <span class="driver-status driver-status--{{ $priorityMission['status'] }}">{{ $priorityMission['status_label'] }}</span>
        <a class="driver-btn driver-btn--light" href="{{ route('driver.missions.show', $priorityMission['mission_number']) }}">
            Ouvrir la mission
            <i data-lucide="arrow-right"></i>
        </a>
    </div>
</section>
@endif

<section class="driver-panel">
    <div class="driver-panel__header">
        <div>
            <h2>Missions récentes</h2>
            <p>Vos dernières affectations et leur état actuel.</p>
        </div>
        <a href="{{ route('driver.missions.index') }}" class="driver-link">Tout afficher <i data-lucide="arrow-right"></i></a>
    </div>

    @if($missions->isNotEmpty())
        <div class="driver-table-wrap">
            <table class="driver-table">
                <thead>
                    <tr>
                        <th>Mission</th>
                        <th>Destination</th>
                        <th>Chargement</th>
                        <th>Véhicule</th>
                        <th>Livraison prévue</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($missions as $mission)
                        <tr>
                            <td data-label="Mission">
                                <strong class="driver-table-title">{{ $mission['mission_number'] }}</strong>
                                <small>{{ $mission['order_number'] }}</small>
                            </td>
                            <td data-label="Destination">{{ $mission['commune'] ?: 'À confirmer' }}</td>
                            <td data-label="Chargement">{{ $mission['pickup_count'] }} collecte(s) · {{ $mission['item_count'] }} article(s)</td>
                            <td data-label="Véhicule">{{ $mission['vehicle_label'] ?: 'À confirmer' }}</td>
                            <td data-label="Livraison prévue">{{ $mission['estimated_delivery_at']?->format('d/m/Y H:i') ?: 'À confirmer' }}</td>
                            <td data-label="Statut"><span class="driver-status driver-status--{{ $mission['status'] }}">{{ $mission['status_label'] }}</span></td>
                            <td data-label="Action"><a class="driver-table-action" href="{{ route('driver.missions.show', $mission['mission_number']) }}">Consulter</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="driver-empty">
            <span class="driver-empty__icon"><i data-lucide="package-open"></i></span>
            <h3>Aucune mission affectée</h3>
            <p>Vos nouvelles missions apparaîtront ici dès leur planification par le centre logistique.</p>
        </div>
    @endif
</section>
@endsection
