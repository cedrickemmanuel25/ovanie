@extends('layouts.staff')
@section('title', 'Tableau de bord Commercial | OVANIE')
@section('content')
@php $money = fn($v) => number_format((float)$v,0,',',' ') . ' FCFA'; @endphp
<div class="page-header">
    <div><h1 class="page-title">Tableau de bord Commercial</h1><p class="page-subtitle">Pilotage des prospects, vendeurs, demandes B2B, devis, appels d’offres et opportunités de vente.</p></div>
    <div class="page-actions"><a class="btn btn-orange" href="{{ route('commercial.prospecting.areas') }}"><i data-lucide="map-pinned"></i>Ma mission de prospection</a>@if(auth()->user()->hasStaffPermission('leads.write'))<a class="btn btn-orange" href="{{ route('commercial.leads.create') }}"><i data-lucide="circle-plus"></i>Nouvelle opportunité</a>@endif<a class="btn" href="{{ route('commercial.leads.index') }}"><i data-lucide="chart-no-axes-combined"></i>Voir le pipeline</a></div>
</div>
<section class="grid kpi-grid">
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Mes clients créés</span><span class="kpi-icon"><i data-lucide="users"></i></span></div><div class="kpi-value">{{ $stats['clients_created'] }}</div><div class="kpi-foot"><a href="{{ route('commercial.clients.index') }}">Comptes créés depuis votre espace</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Mes vendeurs et boutiques</span><span class="kpi-icon"><i data-lucide="store"></i></span></div><div class="kpi-value">{{ $stats['vendors_created'] }} / {{ $stats['shops_created'] }}</div><div class="kpi-foot"><a href="{{ route('commercial.vendors.index') }}">Vendeurs / boutiques créés</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Opportunités actives</span><span class="kpi-icon"><i data-lucide="target"></i></span></div><div class="kpi-value">{{ $stats['active'] }}</div><div class="kpi-foot">{{ $stats['mine'] }} attribuée(s) à vous</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Valeur du pipeline</span><span class="kpi-icon"><i data-lucide="banknote"></i></span></div><div class="kpi-value" style="font-size:18px">{{ $money($stats['pipeline_value']) }}</div><div class="kpi-foot">Valeur pondérée : {{ $money($stats['weighted_value']) }}</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Relances à effectuer</span><span class="kpi-icon"><i data-lucide="calendar-clock"></i></span></div><div class="kpi-value">{{ $stats['followups_due'] }}</div><div class="kpi-foot">Actions arrivées à échéance</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Gagnées ce mois</span><span class="kpi-icon"><i data-lucide="trophy"></i></span></div><div class="kpi-value">{{ $stats['won_month'] }}</div><div class="kpi-foot">Opportunités converties</div></article>
</section>
<section class="grid kpi-grid">
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Demandes Business ouvertes</span><span class="kpi-icon"><i data-lucide="briefcase-business"></i></span></div><div class="kpi-value">{{ $stats['business_requests'] }}</div><div class="kpi-foot"><a href="{{ route('commercial.business.index') }}">Traiter les demandes</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Devis et appels d’offres</span><span class="kpi-icon"><i data-lucide="file-text"></i></span></div><div class="kpi-value">{{ $stats['quotes_total'] }}</div><div class="kpi-foot"><a href="{{ route('commercial.quotes.index') }}">Consulter les dossiers</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Nouvelles boutiques</span><span class="kpi-icon"><i data-lucide="store"></i></span></div><div class="kpi-value">{{ $stats['new_shops_month'] }}</div><div class="kpi-foot">Créées depuis le début du mois</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Ventes marketplace du mois</span><span class="kpi-icon"><i data-lucide="shopping-bag"></i></span></div><div class="kpi-value" style="font-size:18px">{{ $money($stats['sales_month']) }}</div><div class="kpi-foot">Commandes opérationnelles uniquement</div></article>
</section>
<div class="grid two-columns">
    <section class="card">
        <div class="card-head"><div><h2>Opportunités récentes</h2><p>Données commerciales reliées aux clients, boutiques et demandes.</p></div><a class="btn" href="{{ route('commercial.leads.index') }}">Tout afficher</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Référence</th><th>Contact</th><th>Étape</th><th>Valeur</th><th>Probabilité</th><th>Conseiller</th></tr></thead><tbody>
        @forelse($recentLeads as $lead)
            <tr><td><a class="record-title" href="{{ route('commercial.leads.show',$lead) }}">{{ $lead->reference }}</a><span class="record-sub">{{ \Illuminate\Support\Str::limit($lead->title,42) }}</span></td><td>{{ $lead->company_name ?: $lead->contact_name }}</td><td><span class="status {{ $lead->status }}">{{ str_replace('_',' ',$lead->status) }}</span></td><td>{{ $money($lead->estimated_value) }}</td><td>{{ $lead->probability }}%</td><td>{{ $lead->assignee?->name ?: 'Non assigné' }}</td></tr>
        @empty<tr><td colspan="6"><div class="empty"><i data-lucide="target"></i><div>Aucune opportunité créée.</div></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
    <aside class="card">
        <div class="card-head"><div><h2>Mes prochaines relances</h2><p>Actions classées par date prévue.</p></div></div>
        <div class="timeline">
        @forelse($myFollowUps as $lead)
            <a class="timeline-item" href="{{ route('commercial.leads.show',$lead) }}"><span class="timeline-dot">{{ $lead->probability }}%</span><span class="timeline-body {{ $lead->next_action_at?->isPast() ? 'internal' : '' }}"><span class="timeline-meta"><strong>{{ $lead->company_name ?: $lead->contact_name }}</strong><span>{{ $lead->next_action_at?->diffForHumans() }}</span></span><p>{{ \Illuminate\Support\Str::limit($lead->title,90) }}</p></span></a>
        @empty<div class="empty"><i data-lucide="calendar-check"></i><div>Aucune relance planifiée.</div></div>@endforelse
        </div>
    </aside>
</div>
@endsection
