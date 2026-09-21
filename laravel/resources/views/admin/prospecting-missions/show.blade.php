@extends('admin.layouts.app')
@section('title',($mission->commune?->name ?? 'Mission').' | Prospection OVANIE')
@section('page-title','Mission de prospection')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_prospecting.css') }}">@endpush
@section('content')
@php
    $effective=$mission->effectiveStatus();
    $statusLabel=['in_progress'=>'En cours','scheduled'=>'Planifiée','overdue'=>'Échéance dépassée','completed'=>'Terminée','cancelled'=>'Annulée'][$effective] ?? $effective;
    $pct=$mission->mission_quarters_count ? round(($mission->completed_quarters_count/$mission->mission_quarters_count)*100) : 0;
@endphp
<div class="prospecting-admin">
    <section class="prospecting-hero"><div><h2>{{ $mission->commune?->name }} · {{ $mission->starts_on?->format('d/m') }} au {{ $mission->ends_on?->format('d/m/Y') }}</h2><p>{{ $mission->members->count() }} commercial(aux) affecté(s) · {{ $mission->mission_quarters_count }} quartiers à couvrir. Cette page sert à piloter l’affectation ; il n’existe pas de rôle « responsable commercial ».</p></div><div class="prospecting-actions"><span class="mission-status {{ $effective }}">{{ $statusLabel }}</span><a class="prospecting-btn" href="{{ route('admin.prospecting-missions.index') }}">Toutes les missions</a></div></section>

    <section class="mission-summary prospecting-card"><div class="mission-summary-grid">
        <article class="mini-kpi"><span>Quartiers terminés</span><strong>{{ $mission->completed_quarters_count }}/{{ $mission->mission_quarters_count }}</strong></article>
        <article class="mini-kpi"><span>Vendeurs identifiés</span><strong>{{ $mission->prospects_count }}</strong></article>
        <article class="mini-kpi"><span>Vendeurs intéressés</span><strong>{{ $mission->interested_count }}</strong></article>
        <article class="mini-kpi"><span>Boutiques ouvertes</span><strong>{{ $mission->shops_opened_count }}{{ $mission->shop_target ? '/'.$mission->shop_target : '' }}</strong></article>
    </div><div style="margin-top:14px" class="progress-row"><div class="progress-track" style="flex:1;width:auto;height:10px"><i style="width:{{ $pct }}%"></i></div><strong>{{ $pct }}% de la commune couverte</strong></div></section>

    <div class="mission-layout">
        <section class="prospecting-card"><div class="prospecting-card-head"><div><h3>Avancement par quartier</h3><p>Le statut est partagé par toute l’équipe affectée à {{ $mission->commune?->name }}.</p></div></div><div class="quarter-grid">
            @foreach($mission->missionQuarters->sortBy(fn($mq)=>$mq->quarter?->name) as $mq)
                @php $ql=['pending'=>'À faire','in_progress'=>'En cours','completed'=>'Terminé'][$mq->status] ?? $mq->status; @endphp
                <article class="quarter-card"><div class="quarter-card-top"><strong>{{ $mq->quarter?->name }}</strong><span class="quarter-state {{ $mq->status }}">{{ $ql }}</span></div><p>@if($mq->completed_at)Terminé le {{ $mq->completed_at->format('d/m/Y H:i') }}@elseif($mq->started_at)Commencé le {{ $mq->started_at->format('d/m/Y H:i') }}@elsePas encore commencé@endif @if($mq->updatedBy)· {{ $mq->updatedBy->name }}@endif</p></article>
            @endforeach
        </div></section>

        <aside class="prospecting-card"><div class="prospecting-card-head"><div><h3>Affectation</h3><p>Modifier les dates, l’équipe ou les consignes.</p></div></div><form class="prospecting-form" method="POST" action="{{ route('admin.prospecting-missions.update',$mission) }}">@csrf @method('PUT')
            <div class="prospecting-grid" style="grid-template-columns:1fr">
                <div class="prospecting-field"><label>Commune</label><input value="{{ $mission->commune?->name }}" readonly></div>
                <div class="prospecting-field"><label>Début</label><input type="date" name="starts_on" value="{{ old('starts_on',$mission->starts_on?->toDateString()) }}" required></div>
                <div class="prospecting-field"><label>Fin</label><input type="date" name="ends_on" value="{{ old('ends_on',$mission->ends_on?->toDateString()) }}" required></div>
                <div class="prospecting-field"><label>Objectif boutiques</label><input type="number" min="1" name="shop_target" value="{{ old('shop_target',$mission->shop_target) }}"></div>
                <div class="prospecting-field"><label>Équipe</label><div class="commercial-checks" style="grid-template-columns:1fr">@foreach($commercials as $commercial)<label class="commercial-check"><input type="checkbox" name="commercial_ids[]" value="{{ $commercial->id }}" @checked(in_array($commercial->id,(array)old('commercial_ids',$mission->members->pluck('id')->all())))><span><strong>{{ $commercial->name }}</strong><span>{{ $commercial->email }}</span></span></label>@endforeach</div></div>
                <div class="prospecting-field"><label>Consignes</label><textarea name="instructions">{{ old('instructions',$mission->instructions) }}</textarea></div>
            </div>
            @if($mission->status!=='completed')<div class="form-footer"><button class="prospecting-btn prospecting-btn-primary" type="submit">Enregistrer l’affectation</button></div>@endif
        </form>
        @if($mission->status!=='completed')<form style="padding:0 22px 22px" method="POST" action="{{ route('admin.prospecting-missions.complete',$mission) }}">@csrf<button class="prospecting-btn prospecting-btn-danger" style="width:100%" type="submit" onclick="return confirm('Clôturer cette mission ?')">Clôturer manuellement la mission</button></form>@endif
        </aside>
    </div>

    <section class="prospecting-card"><div class="prospecting-card-head"><div><h3>Derniers vendeurs identifiés</h3><p>Les informations sont partagées entre tous les commerciaux de la mission.</p></div></div><div class="prospecting-table-wrap"><table class="prospecting-table"><thead><tr><th>Vendeur</th><th>Quartier</th><th>Commercial</th><th>Statut</th><th>Boutique</th></tr></thead><tbody>@forelse($mission->prospects as $prospect)<tr><td><span class="prospecting-title">{{ $prospect->business_name }}</span><span class="prospecting-sub">{{ $prospect->category ?: 'Vendeur BTP' }}</span></td><td>{{ $prospect->quarter?->name ?: '—' }}</td><td>{{ $prospect->commercial?->name ?: '—' }}</td><td>{{ str_replace('_',' ',$prospect->status) }}</td><td>{{ $prospect->shop?->name ?: '—' }}</td></tr>@empty<tr><td colspan="5"><div class="prospecting-empty">Aucun vendeur n’a encore été enregistré pour cette mission.</div></td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
