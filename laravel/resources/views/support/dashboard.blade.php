@extends('layouts.staff')
@section('title', 'Tableau de bord Support | OVANIE')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Centre Support OVANIE</h1>
        <p class="page-subtitle">Supervision des agents IA, conversations, appels, rappels, transferts humains et tickets reliés aux données réelles de la plateforme.</p>
    </div>
    <div class="page-actions">
        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond'))
            <a class="btn btn-primary" href="{{ route('support.conversations.index') }}"><i data-lucide="messages-square"></i>Nouvelle conversation</a>
        @endif
        <a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="inbox"></i>File des tickets</a>
    </div>
</div>

<section class="card" style="margin-bottom:14px">
    <div class="card-head"><div><h2>Services réellement configurés</h2><p>Les fonctionnalités indisponibles sont clairement signalées et ne produisent aucune donnée simulée.</p></div></div>
    <div class="grid kpi-grid" style="margin-bottom:0">
        @foreach($services as $service)
            <article class="kpi-card" style="box-shadow:none">
                <div class="kpi-head"><span class="kpi-label">{{ $service['label'] }}</span><span class="status {{ $service['operational'] ? 'active' : 'unavailable' }}">{{ $service['operational'] ? 'Actif' : 'Indisponible' }}</span></div>
                <div class="kpi-foot" style="margin-top:10px">{{ $service['provider'] ?: 'Aucun fournisseur' }}</div>
                <span class="record-sub">{{ $service['message'] }}</span>
            </article>
        @endforeach
    </div>
</section>

<section class="grid kpi-grid">
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Conversations IA actives</span><span class="kpi-icon"><i data-lucide="bot"></i></span></div><div class="kpi-value">{{ $stats['active_ai_conversations'] }}</div><div class="kpi-foot">{{ $stats['active_ai_agents'] }} agent(s) IA actif(s)</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Appels en attente</span><span class="kpi-icon"><i data-lucide="headphones"></i></span></div><div class="kpi-value">{{ $stats['waiting_calls'] }}</div><div class="kpi-foot"><a href="{{ route('support.calls.index', ['status' => 'waiting']) }}">Voir le centre d’appels</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Appels manqués aujourd’hui</span><span class="kpi-icon"><i data-lucide="phone-missed"></i></span></div><div class="kpi-value">{{ $stats['missed_calls_today'] }}</div><div class="kpi-foot">Les appels manqués peuvent générer un rappel</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Demandes de rappel</span><span class="kpi-icon"><i data-lucide="phone-call"></i></span></div><div class="kpi-value">{{ $stats['pending_callbacks'] }}</div><div class="kpi-foot"><a href="{{ route('support.callbacks.index') }}">À planifier ou compléter</a></div></article>
</section>

<section class="grid kpi-grid">
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Transferts humains</span><span class="kpi-icon"><i data-lucide="user-round-check"></i></span></div><div class="kpi-value">{{ $stats['pending_handoffs'] }}</div><div class="kpi-foot"><a href="{{ route('support.handoffs.index', ['status' => 'pending']) }}">Cas sensibles en attente</a></div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Tickets créés par l’IA</span><span class="kpi-icon"><i data-lucide="ticket-check"></i></span></div><div class="kpi-value">{{ $stats['ai_created_tickets'] }}</div><div class="kpi-foot">Tickets issus des chats et appels IA</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Tickets ouverts</span><span class="kpi-icon"><i data-lucide="inbox"></i></span></div><div class="kpi-value">{{ $stats['open'] }}</div><div class="kpi-foot">{{ $stats['urgent'] }} urgent(s) · {{ $stats['overdue'] }} hors SLA</div></article>
    <article class="kpi-card"><div class="kpi-head"><span class="kpi-label">Ma file active</span><span class="kpi-icon"><i data-lucide="user-check"></i></span></div><div class="kpi-value">{{ $stats['mine'] }}</div><div class="kpi-foot">{{ $stats['unassigned'] }} ticket(s) non assigné(s)</div></article>
</section>

<div class="grid two-columns">
    <section class="card">
        <div class="card-head"><div><h2>Conversations récentes</h2><p>Chats, appels et demandes traités par les agents IA ou repris par un humain.</p></div><a class="btn" href="{{ route('support.conversations.index') }}">Tout afficher</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Conversation</th><th>Demandeur</th><th>Agent</th><th>Canal</th><th>Statut</th><th>Dernier message</th></tr></thead><tbody>
        @forelse($recentConversations as $conversation)
            <tr>
                <td><a class="record-title" href="{{ route('support.conversations.show', $conversation) }}">#{{ $conversation->id }} · {{ \Illuminate\Support\Str::limit($conversation->subject ?: 'Assistance OVANIE', 38) }}</a><span class="record-sub">{{ $conversation->ticket?->reference ?: 'Sans ticket' }}</span></td>
                <td>{{ $conversation->requester?->name ?: $conversation->requester_name ?: 'Non identifié' }}</td>
                <td>{{ $conversation->aiAgent?->name ?: 'Non attribué' }}</td>
                <td><span class="status">{{ $conversation->channel }}</span></td>
                <td><span class="status {{ $conversation->status }}">{{ str_replace('_', ' ', $conversation->status) }}</span></td>
                <td>{{ $conversation->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><i data-lucide="messages-square"></i><div>Aucune conversation IA enregistrée.</div></div></td></tr>
        @endforelse
        </tbody></table></div>
    </section>

    <aside class="card">
        <div class="card-head"><div><h2>Transferts prioritaires</h2><p>Cas sensibles que l’IA ne doit pas traiter seule.</p></div><a class="btn" href="{{ route('support.handoffs.index') }}">Voir tout</a></div>
        <div class="timeline">
            @forelse($waitingHandoffs as $handoff)
                <a class="timeline-item" href="{{ $handoff->conversation ? route('support.conversations.show', $handoff->conversation) : route('support.handoffs.index') }}">
                    <span class="timeline-dot">{{ mb_strtoupper(mb_substr($handoff->severity, 0, 1)) }}</span>
                    <span class="timeline-body internal">
                        <span class="timeline-meta"><strong>{{ $handoff->aiAgent?->name ?: 'IA' }}</strong><span>{{ $handoff->requested_at?->diffForHumans() }}</span></span>
                        <p>{{ \Illuminate\Support\Str::limit($handoff->reason, 120) }}</p>
                    </span>
                </a>
            @empty
                <div class="empty"><i data-lucide="shield-check"></i><div>Aucun transfert humain en attente.</div></div>
            @endforelse
        </div>
    </aside>
</div>

<div class="grid two-columns" style="margin-top:14px">
    <section class="card">
        <div class="card-head"><div><h2>Appels récents</h2><p>Aucun appel fictif n’est généré : cette liste provient uniquement des événements téléphoniques enregistrés.</p></div><a class="btn" href="{{ route('support.calls.index') }}">Centre d’appels</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Référence</th><th>Numéro</th><th>Direction</th><th>Agent</th><th>Statut</th><th>Début</th></tr></thead><tbody>
        @forelse($recentCalls as $call)
            <tr><td><a class="record-title" href="{{ route('support.calls.show', $call) }}">{{ $call->reference }}</a></td><td>{{ $call->direction === 'inbound' ? $call->from_number : $call->to_number }}</td><td>{{ $call->direction }}</td><td>{{ $call->handler?->name ?: $call->aiAgent?->name ?: 'Non attribué' }}</td><td><span class="status {{ $call->status }}">{{ str_replace('_', ' ', $call->status) }}</span></td><td>{{ $call->started_at?->format('d/m/Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="6"><div class="empty"><i data-lucide="phone-off"></i><div>Aucun appel réel enregistré.</div></div></td></tr>
        @endforelse
        </tbody></table></div>
    </section>

    <aside class="card">
        <div class="card-head"><div><h2>Ma file de tickets</h2><p>Classée par priorité et échéance SLA.</p></div></div>
        <div class="timeline">
        @forelse($myQueue as $ticket)
            <a class="timeline-item" href="{{ route('support.tickets.show',$ticket) }}"><span class="timeline-dot">{{ mb_substr($ticket->priority,0,1) }}</span><span class="timeline-body {{ $ticket->is_overdue ? 'internal' : '' }}"><span class="timeline-meta"><strong>{{ $ticket->reference }}</strong><span>{{ $ticket->sla_due_at?->diffForHumans() }}</span></span><p>{{ \Illuminate\Support\Str::limit($ticket->subject,90) }}</p></span></a>
        @empty<div class="empty"><i data-lucide="check-circle"></i><div>Aucun ticket actif dans votre file.</div></div>@endforelse
        </div>
    </aside>
</div>
@endsection
