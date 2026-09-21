@extends('layouts.staff')
@section('title', 'Supervision IA | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Supervision et audit IA</h1><p class="page-subtitle">Journal append-only protégé par chaîne de hachage et par blocage SQL des modifications et suppressions.</p></div>
</div>

<div class="grid kpi-grid">
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">Événements enregistrés</span><span class="kpi-icon"><i data-lucide="scroll-text"></i></span></div><div class="kpi-value">{{ $integrity['total'] }}</div></div>
    <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">Événements hachés</span><span class="kpi-icon"><i data-lucide="shield-check"></i></span></div><div class="kpi-value">{{ $integrity['hashed'] }}</div></div>
    <div class="kpi-card" style="grid-column:span 2"><div class="kpi-head"><span class="kpi-label">Dernière empreinte de la chaîne</span><span class="kpi-icon"><i data-lucide="fingerprint"></i></span></div><div class="record-title" style="margin-top:14px;word-break:break-all">{{ $integrity['last_hash'] ?: 'Aucun événement' }}</div></div>
</div>

<form class="filters" method="GET">
    <select name="action"><option value="">Toutes les actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action')===$action)>{{ $action }}</option>@endforeach</select>
    <select name="risk_level"><option value="">Tous les risques</option>@foreach(['low','normal','medium','high','urgent','critical'] as $risk)<option value="{{ $risk }}" @selected(request('risk_level')===$risk)>{{ $risk }}</option>@endforeach</select>
    <select name="ai_agent_id"><option value="">Tous les agents IA</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('ai_agent_id')===(string)$agent->id)>{{ $agent->name }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
</form>

<section class="card">
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Action</th><th>Agent IA / acteur</th><th>Conversation / appel</th><th>Décision</th><th>Risque</th><th>Confiance</th><th>Intégrité</th></tr></thead><tbody>
@forelse($logs as $log)
<tr>
<td>{{ $log->occurred_at?->format('d/m/Y H:i:s') ?: $log->created_at?->format('d/m/Y H:i:s') }}</td>
<td><span class="record-title">{{ $log->action }}</span><span class="record-sub">{{ $log->event_uuid }}</span></td>
<td>{{ $log->aiAgent?->name ?: 'Système' }}<span class="record-sub">{{ $log->actor?->name ?: 'Processus automatique' }}</span></td>
<td>@if($log->conversation)<a class="record-title" href="{{ route('support.conversations.show',$log->conversation) }}">Conversation #{{ $log->conversation->id }}</a>@elseif($log->call)<a class="record-title" href="{{ route('support.calls.show',$log->call) }}">{{ $log->call->reference }}</a>@else — @endif</td>
<td>{{ $log->decision ?: '—' }}</td>
<td><span class="status {{ $log->risk_level }}">{{ $log->risk_level }}</span></td>
<td>{{ $log->confidence !== null ? number_format($log->confidence,0).' %' : '—' }}</td>
<td><span class="status {{ $log->integrity_valid ? 'active' : 'unavailable' }}">{{ $log->integrity_valid ? 'Valide' : 'À vérifier' }}</span><span class="record-sub">{{ \Illuminate\Support\Str::limit($log->record_hash,20) }}</span></td>
</tr>
@empty<tr><td colspan="8"><div class="empty"><i data-lucide="scroll-text"></i><div>Aucun événement IA réel enregistré.</div></div></td></tr>@endforelse
</tbody></table></div>
<div class="pagination-row"><span>{{ $logs->total() }} événement(s)</span><div>{{ $logs->links() }}</div></div>
</section>
@endsection
