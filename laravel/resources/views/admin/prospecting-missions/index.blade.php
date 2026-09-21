@extends('admin.layouts.app')
@section('title','Prospection vendeurs | Admin OVANIE')
@section('page-title','Prospection vendeurs')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_prospecting.css') }}">@endpush

@section('content')
<div class="prospecting-admin">
    <section class="prospecting-hero">
        <div>
            <h2>Planification des missions terrain</h2>
            <p>OVANIE affecte une commune à une équipe commerciale pour une période déterminée. Les commerciaux ne choisissent pas leur zone : ils travaillent uniquement sur la mission reçue et suivent l’avancement quartier par quartier.</p>
        </div>
        <div class="prospecting-actions"><a class="prospecting-btn prospecting-btn-primary" href="{{ route('admin.prospecting-missions.create') }}">+ Nouvelle mission</a></div>
    </section>

    <section class="prospecting-stats">
        <article class="prospecting-stat"><span>Missions en cours</span><strong>{{ $stats['active'] }}</strong><small>Communes actuellement confiées aux équipes.</small></article>
        <article class="prospecting-stat"><span>Missions planifiées</span><strong>{{ $stats['scheduled'] }}</strong><small>Départs prévus pour les prochains jours.</small></article>
        <article class="prospecting-stat"><span>Commerciaux déployés</span><strong>{{ $stats['commercials_deployed'] }}</strong><small>Commerciaux affectés aujourd’hui à une mission.</small></article>
        <article class="prospecting-stat"><span>Boutiques obtenues</span><strong>{{ $stats['shops_opened'] }}</strong><small>Conversions issues des missions de prospection.</small></article>
    </section>

    <section class="prospecting-card">
        <div class="prospecting-card-head"><div><h3>Missions de prospection</h3><p>Une seule mission par commune et par période. Plusieurs commerciaux peuvent travailler ensemble dans la même commune.</p></div></div>
        <form class="prospecting-filter" method="GET">
            <select name="commune_id"><option value="">Toutes les communes</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((int)request('commune_id')===$commune->id)>{{ $commune->name }}</option>@endforeach</select>
            <select name="status"><option value="">Tous les statuts</option><option value="active" @selected(request('status')==='active')>En cours</option><option value="scheduled" @selected(request('status')==='scheduled')>Planifiées</option><option value="overdue" @selected(request('status')==='overdue')>Échéance dépassée</option><option value="completed" @selected(request('status')==='completed')>Terminées</option></select>
            <button class="prospecting-btn" type="submit">Filtrer</button>
            @if(request()->hasAny(['commune_id','status']))<a class="prospecting-btn" href="{{ route('admin.prospecting-missions.index') }}">Réinitialiser</a>@endif
        </form>
        <div class="prospecting-table-wrap"><table class="prospecting-table"><thead><tr><th>Commune / période</th><th>Équipe</th><th>Quartiers</th><th>Vendeurs trouvés</th><th>Boutiques</th><th>Statut</th><th></th></tr></thead><tbody>
        @forelse($missions as $mission)
            @php
                $effective=$mission->effectiveStatus();
                $pct=$mission->mission_quarters_count ? round(($mission->completed_quarters_count/$mission->mission_quarters_count)*100) : 0;
                $label=['in_progress'=>'En cours','scheduled'=>'Planifiée','overdue'=>'Échéance dépassée','completed'=>'Terminée','cancelled'=>'Annulée'][$effective] ?? $effective;
            @endphp
            <tr>
                <td><span class="prospecting-title">{{ $mission->commune?->name }}</span><span class="prospecting-sub">{{ $mission->starts_on?->format('d/m/Y') }} → {{ $mission->ends_on?->format('d/m/Y') }} · {{ $mission->starts_on?->diffInDays($mission->ends_on)+1 }} jour(s)</span></td>
                <td><div class="team-stack">@foreach($mission->members->take(4) as $member)<span class="team-avatar" title="{{ $member->name }}">{{ mb_strtoupper(mb_substr($member->name ?: 'C',0,1)) }}</span>@endforeach @if($mission->members_count>4)<span class="team-more">+{{ $mission->members_count-4 }}</span>@endif</div><span class="prospecting-sub">{{ $mission->members_count }} commercial(aux)</span></td>
                <td><div class="progress-row"><div class="progress-track"><i style="width:{{ $pct }}%"></i></div><strong>{{ $mission->completed_quarters_count }}/{{ $mission->mission_quarters_count }}</strong></div><span class="prospecting-sub">{{ $pct }}% couverts</span></td>
                <td><span class="prospecting-title">{{ $mission->prospects_count }}</span><span class="prospecting-sub">{{ $mission->visits_count }} visite(s)</span></td>
                <td><span class="prospecting-title">{{ $mission->shops_opened_count }}</span></td>
                <td><span class="mission-status {{ $effective }}">{{ $label }}</span></td>
                <td><a class="prospecting-btn" href="{{ route('admin.prospecting-missions.show',$mission) }}">Piloter</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="prospecting-empty"><strong>Aucune mission planifiée</strong>Créez une mission, choisissez la commune, la période et l’équipe commerciale.</div></td></tr>
        @endforelse
        </tbody></table></div>
        @if($missions->hasPages())<div class="pagination-box">{{ $missions->links() }}</div>@endif
    </section>
</div>
@endsection
