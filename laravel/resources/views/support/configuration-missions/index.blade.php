@extends('layouts.staff')
@section('title', 'Missions boutiques | Support OVANIE')

@section('content')
@php
    $statusLabels = [
        'pending' => 'À démarrer',
        'in_progress' => 'En cours',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];
@endphp

<div class="page-header">
    <div class="page-header-main">
        <span class="page-header-icon"><i data-lucide="clipboard-check"></i></span>
        <div>
            <h1 class="page-title">Missions de régularisation boutiques</h1>
            <p class="page-subtitle">Suivi des boutiques dont la logistique ou le reversement doit encore être finalisé par un commercial.</p>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('support.vendors.index') }}"><i data-lucide="store"></i>Vendeurs</a>
    </div>
</div>

<section class="grid kpi-grid">
    <a class="kpi-card" href="{{ route('support.configuration-missions.index') }}">
        <div class="kpi-head"><span class="kpi-label">À régulariser</span><span class="kpi-icon"><i data-lucide="clipboard-list"></i></span></div>
        <div class="kpi-value">{{ $stats['total'] }}</div>
        <div class="kpi-foot">Boutiques avec configuration incomplète</div>
    </a>
    <a class="kpi-card" href="{{ route('support.configuration-missions.index',['missing'=>'logistics']) }}">
        <div class="kpi-head"><span class="kpi-label">Logistique</span><span class="kpi-icon"><i data-lucide="truck"></i></span></div>
        <div class="kpi-value">{{ $stats['logistics'] }}</div>
        <div class="kpi-foot">À finaliser</div>
    </a>
    <a class="kpi-card" href="{{ route('support.configuration-missions.index',['missing'=>'payout']) }}">
        <div class="kpi-head"><span class="kpi-label">Reversement</span><span class="kpi-icon"><i data-lucide="wallet-cards"></i></span></div>
        <div class="kpi-value">{{ $stats['payout'] }}</div>
        <div class="kpi-foot">À finaliser</div>
    </a>
    <a class="kpi-card" href="{{ route('support.configuration-missions.index',['missing'=>'assigned']) }}">
        <div class="kpi-head"><span class="kpi-label">Missions actives</span><span class="kpi-icon"><i data-lucide="user-round-check"></i></span></div>
        <div class="kpi-value">{{ $stats['assigned'] }}</div>
        <div class="kpi-foot">Commercial affecté</div>
    </a>
</section>

<form class="filters" method="GET" style="margin-bottom:16px">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Boutique, vendeur, téléphone ou email…">
    <select name="missing">
        <option value="">Tous les besoins</option>
        <option value="both" @selected(request('missing')==='both')>Logistique + reversement</option>
        <option value="logistics" @selected(request('missing')==='logistics')>Logistique à configurer</option>
        <option value="payout" @selected(request('missing')==='payout')>Reversement à configurer</option>
        <option value="assigned" @selected(request('missing')==='assigned')>Missions actives</option>
    </select>
    <button class="btn btn-primary" type="submit"><i data-lucide="search"></i>Filtrer</button>
    <a class="btn" href="{{ route('support.configuration-missions.index') }}">Réinitialiser</a>
</form>

<div class="grid" style="gap:12px">
@forelse($shops as $shop)
    @php
        $mission = $shop->activeConfigurationAssignment;
        $needsLogistics = $shop->needsLogisticsConfiguration();
        $needsPayout = $shop->needsPayoutConfiguration();
    @endphp
    <article class="card" style="padding:16px 18px">
        <div style="display:flex;gap:14px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap">
            <div style="min-width:220px;flex:1">
                <div class="record-title" style="font-size:15px">{{ $shop->name }}</div>
                <div class="record-sub" style="margin-top:5px">{{ $shop->user?->name ?: 'Vendeur' }} · {{ $shop->user?->phone ?: $shop->user?->email ?: 'Contact non renseigné' }} · {{ $shop->products_count }} produit(s)</div>
            </div>
            <div style="display:flex;gap:7px;flex-wrap:wrap">
                <span class="status {{ $needsLogistics ? 'pending' : 'resolved' }}">Logistique : {{ $needsLogistics ? 'à configurer' : 'configurée' }}</span>
                <span class="status {{ $needsPayout ? 'pending' : 'resolved' }}">Reversement : {{ $needsPayout ? 'à configurer' : 'configuré' }}</span>
            </div>
        </div>
        <div class="detail-list" style="margin-top:12px">
            <div class="detail-row"><span>Localisation</span><strong>{{ $shop->configuration_location_verified ? $shop->configuration_location_label : 'À vérifier avec le vendeur' }}</strong></div>
            <div class="detail-row"><span>Commercial affecté</span><strong>{{ $mission?->commercial?->name ?: 'Non affecté' }}</strong></div>
            <div class="detail-row"><span>État de la mission</span><strong>{{ $mission ? ($statusLabels[$mission->status] ?? 'À démarrer') : 'Aucune mission active' }}</strong></div>
            @if($mission)
                <div class="detail-row"><span>Tâches</span><strong>
                    @if($mission->configure_logistics) Logistique {{ $mission->logistics_completed_at ? '✓' : '•' }} @endif
                    @if($mission->configure_payout) · Reversement {{ $mission->payout_completed_at ? '✓' : '•' }} @endif
                    @if($mission->manage_products) · Produits {{ $mission->products_completed_at ? '✓' : '•' }} @endif
                </strong></div>
            @endif
        </div>
    </article>
@empty
    <div class="card empty"><i data-lucide="circle-check-big"></i><div>Aucune boutique à régulariser.</div></div>
@endforelse
</div>

@if($shops->hasPages())
<div style="margin-top:16px">{{ $shops->links() }}</div>
@endif
@endsection
