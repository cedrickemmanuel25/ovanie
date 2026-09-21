@extends('layouts.staff')
@section('title', 'Demandes de rappel | OVANIE')
@section('content')
<div class="page-header"><div><h1 class="page-title">Demandes de rappel</h1><p class="page-subtitle">Rappels créés après un appel manqué, depuis une conversation IA ou par un agent humain.</p></div></div>
<form class="filters" method="GET"><select name="status"><option value="">Tous les statuts</option>@foreach(['pending','scheduled','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button></form>
<section class="card">
<div class="table-wrap"><table class="data-table"><thead><tr><th>Référence</th><th>Demandeur</th><th>Téléphone</th><th>Motif</th><th>Date souhaitée</th><th>Statut</th><th>Traitement</th></tr></thead><tbody>
@forelse($callbacks as $callback)
<tr>
<td><span class="record-title">{{ $callback->reference }}</span><span class="record-sub">{{ $callback->created_at?->format('d/m/Y H:i') }}</span></td>
<td>{{ $callback->requester?->name ?: $callback->requester_name ?: 'Non identifié' }}<span class="record-sub">{{ $callback->email }}</span></td>
<td>{{ $callback->phone }}</td><td>{{ \Illuminate\Support\Str::limit($callback->reason,70) }}</td><td>{{ $callback->preferred_at?->format('d/m/Y H:i') ?: 'Dès que possible' }}</td><td><span class="status {{ $callback->status }}">{{ $callback->status }}</span></td>
<td>
@if(auth('admin')->user()->hasStaffPermission('support.ai.callbacks.manage'))
<form method="POST" action="{{ route('support.callbacks.update',$callback) }}">@csrf @method('PUT')
<div style="display:grid;gap:7px;min-width:180px"><select class="field" name="status"><option value="pending" @selected($callback->status==='pending')>En attente</option><option value="scheduled" @selected($callback->status==='scheduled')>Planifié</option><option value="completed" @selected($callback->status==='completed')>Terminé</option><option value="cancelled" @selected($callback->status==='cancelled')>Annulé</option></select><select class="field" name="assigned_to"><option value="">Non assigné</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($callback->assigned_to===$agent->id)>{{ $agent->name }}</option>@endforeach</select><button class="btn" type="submit">Enregistrer</button></div>
</form>
@else {{ $callback->assignee?->name ?: 'Non assigné' }} @endif
</td></tr>
@empty<tr><td colspan="7"><div class="empty"><i data-lucide="phone-call"></i><div>Aucune demande de rappel enregistrée.</div></div></td></tr>@endforelse
</tbody></table></div>
<div class="pagination-row"><span>{{ $callbacks->total() }} rappel(s)</span><div>{{ $callbacks->links() }}</div></div>
</section>
@endsection
