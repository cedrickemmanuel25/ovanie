@extends('layouts.staff')
@section('title', 'Opportunités commerciales | OVANIE')
@section('content')
@php $money = fn($v) => number_format((float)$v,0,',',' ') . ' FCFA'; @endphp
<div class="page-header">
    <div><h1 class="page-title">Pipeline commercial</h1><p class="page-subtitle">Suivi centralisé des opportunités clients, vendeurs, partenaires et demandes OVANIE Pro.</p></div>
    <div class="page-actions">@if(auth()->user()->hasStaffPermission('leads.write'))<a class="btn btn-orange" href="{{ route('commercial.leads.create') }}"><i data-lucide="circle-plus"></i>Nouvelle opportunité</a>@endif</div>
</div>
<form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Référence, société, contact, objet…">
    <select name="status"><option value="">Toutes les étapes</option>@foreach(['new'=>'Nouveau','qualified'=>'Qualifié','proposal'=>'Proposition','negotiation'=>'Négociation','won'=>'Gagné','lost'=>'Perdu','cancelled'=>'Annulé'] as $v=>$l)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $l }}</option>@endforeach</select>
    <select name="lead_type"><option value="">Tous les profils</option>@foreach(['buyer'=>'Acheteur','vendor'=>'Vendeur','business'=>'Business','partner'=>'Partenaire'] as $v=>$l)<option value="{{ $v }}" @selected(request('lead_type')===$v)>{{ $l }}</option>@endforeach</select>
    <select name="assigned_to"><option value="">Tous les conseillers</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
    @if(request()->query())<a class="btn" href="{{ route('commercial.leads.index') }}">Réinitialiser</a>@endif
</form>
<section class="card">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Opportunité</th><th>Contact</th><th>Profil</th><th>Étape</th><th>Valeur</th><th>Probabilité</th><th>Prochaine action</th><th>Conseiller</th></tr></thead><tbody>
    @forelse($leads as $lead)
        <tr>
            <td><a class="record-title" href="{{ route('commercial.leads.show',$lead) }}">{{ $lead->reference }}</a><span class="record-sub">{{ \Illuminate\Support\Str::limit($lead->title,52) }}</span></td>
            <td>{{ $lead->company_name ?: $lead->contact_name }}<span class="record-sub">{{ $lead->email ?: $lead->phone }}</span></td>
            <td>{{ ucfirst($lead->lead_type) }}</td>
            <td><span class="status {{ $lead->status }}">{{ str_replace('_',' ',$lead->status) }}</span></td>
            <td>{{ $money($lead->estimated_value) }}</td>
            <td>{{ $lead->probability }}%</td>
            <td><span class="status {{ $lead->next_action_at?->isPast() && !in_array($lead->status,['won','lost','cancelled']) ? 'urgent' : '' }}">{{ $lead->next_action_at?->format('d/m/Y H:i') ?: 'Non planifiée' }}</span></td>
            <td>{{ $lead->assignee?->name ?: 'Non assigné' }}</td>
        </tr>
    @empty<tr><td colspan="8"><div class="empty"><i data-lucide="target"></i><div>Aucune opportunité ne correspond aux filtres.</div></div></td></tr>@endforelse
    </tbody></table></div>
    @if($leads->hasPages())<div class="pagination-row"><span>{{ $leads->firstItem() }}–{{ $leads->lastItem() }} sur {{ $leads->total() }}</span><div class="pagination-actions">@if($leads->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $leads->previousPageUrl() }}">Précédent</a>@endif @if($leads->hasMorePages())<a class="btn" href="{{ $leads->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif</div></div>@endif
</section>
@endsection
