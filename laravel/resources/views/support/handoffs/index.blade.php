@extends('layouts.staff')
@section('title', 'Transferts humains | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Files d’assignation humaine</h1><p class="page-subtitle">Chaque transfert est un dossier réel, assignable, verrouillé lors de la prise en charge et relié à sa conversation, son ticket, son incident ou son opportunité.</p></div>
</div>

<div class="grid kpi-grid">
    @foreach([
        ['label'=>'Support non assigné','value'=>$queueStats['pending'],'icon'=>'inbox'],
        ['label'=>'Mes dossiers ouverts','value'=>$queueStats['mine'],'icon'=>'user-check'],
        ['label'=>'Échéances dépassées','value'=>$queueStats['overdue'],'icon'=>'alarm-clock'],
        ['label'=>'Toutes les files ouvertes','value'=>$queueStats['all_open'],'icon'=>'workflow'],
    ] as $stat)
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">{{ $stat['label'] }}</span><span class="kpi-icon"><i data-lucide="{{ $stat['icon'] }}"></i></span></div><div class="kpi-value">{{ $stat['value'] }}</div></div>
    @endforeach
</div>

<form class="filters" method="GET">
    <input name="q" value="{{ request('q') }}" placeholder="Référence, demandeur, ticket ou motif">
    <select name="target_department"><option value="">Tous les services</option>@foreach(['support'=>'Support','logistique'=>'Logistique','commercial'=>'Commercial','administration'=>'Administration'] as $value=>$label)<option value="{{ $value }}" @selected(request('target_department')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="status"><option value="">Tous les statuts</option>@foreach(['pending','assigned','accepted','in_progress','resolved','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select>
    <select name="severity"><option value="">Toutes les priorités</option>@foreach(['normal','high','urgent','critical'] as $severity)<option value="{{ $severity }}" @selected(request('severity')===$severity)>{{ $severity }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
</form>

<section class="card">
<div class="table-wrap"><table class="data-table"><thead><tr><th>Référence</th><th>Service / file</th><th>Dossier réel</th><th>Motif</th><th>Priorité / SLA</th><th>Assignation</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
@forelse($handoffs as $handoff)
<tr>
    <td><span class="record-title">{{ $handoff->reference }}</span><span class="record-sub">{{ $handoff->requested_at?->format('d/m/Y H:i') }}</span></td>
    <td>{{ ucfirst($handoff->target_department) }}<span class="record-sub">{{ $handoff->queue_key }}</span></td>
    <td>
        @if($handoff->conversation)<a class="record-title" href="{{ route('support.conversations.show',$handoff->conversation) }}">Conversation #{{ $handoff->conversation->id }}</a>@elseif($handoff->call)<a class="record-title" href="{{ route('support.calls.show',$handoff->call) }}">{{ $handoff->call->reference }}</a>@else<span class="record-title">Dossier sans conversation</span>@endif
        <span class="record-sub">
            {{ $handoff->conversation?->requester?->name ?: $handoff->conversation?->requester_name ?: 'Non identifié' }}
            {{ $handoff->ticket ? ' · Ticket '.$handoff->ticket->reference : '' }}
            {{ $handoff->deliveryIncident ? ' · Incident #'.$handoff->deliveryIncident->id : '' }}
            {{ $handoff->commercialLead ? ' · '.$handoff->commercialLead->reference : '' }}
        </span>
    </td>
    <td>{{ \Illuminate\Support\Str::limit($handoff->reason,110) }}</td>
    <td><span class="status {{ $handoff->severity }}">{{ $handoff->severity }}</span><span class="record-sub" style="color:{{ $handoff->is_overdue ? 'var(--red)' : 'inherit' }}">{{ $handoff->due_at ? 'Échéance '.$handoff->due_at->format('d/m H:i') : 'Sans échéance' }}</span></td>
    <td>{{ $handoff->assignee?->name ?: 'Non assigné' }}</td>
    <td><span class="status {{ $handoff->status }}">{{ str_replace('_',' ',$handoff->status) }}</span></td>
    <td>
        @if($handoff->target_department === 'support' && auth('admin')->user()->hasStaffPermission('support.ai.handoffs.manage'))
        <div class="page-actions">
            @if(in_array($handoff->status,['pending','assigned'],true) && (!$handoff->assigned_to || (int)$handoff->assigned_to === (int)auth('admin')->id()))
                <form method="POST" action="{{ route('support.handoffs.accept',$handoff) }}">@csrf<button class="btn btn-primary" type="submit">Prendre</button></form>
            @endif
            @if(in_array($handoff->status,['pending','assigned'],true))
                <form method="POST" action="{{ route('support.handoffs.assign',$handoff) }}" style="display:flex;gap:5px">@csrf<select name="assigned_to" class="field" required><option value="">Assigner</option>@foreach($supportAgents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach</select><button class="btn" type="submit">OK</button></form>
            @endif
            @if(in_array($handoff->status,['pending','assigned','accepted','in_progress'],true) && (!$handoff->assigned_to || (int)$handoff->assigned_to === (int)auth('admin')->id()))
                <form method="POST" action="{{ route('support.handoffs.resolve',$handoff) }}" onsubmit="return confirm('Clôturer ce transfert ?')">@csrf<input type="hidden" name="resolution_code" value="answered"><button class="btn" type="submit">Clôturer</button></form>
            @endif
        </div>
        @else
            <span class="record-sub">Traitement dans l’espace {{ ucfirst($handoff->target_department) }}</span>
        @endif
    </td>
</tr>
@empty<tr><td colspan="8"><div class="empty"><i data-lucide="user-round-check"></i><div>Aucun transfert réel enregistré.</div></div></td></tr>@endforelse
</tbody></table></div>
<div class="pagination-row"><span>{{ $handoffs->total() }} transfert(s)</span><div>{{ $handoffs->links() }}</div></div>
</section>
@endsection
