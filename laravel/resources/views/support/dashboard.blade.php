@extends('layouts.staff')
@section('title', 'Tableau de bord Support | OVANIE')
@section('content')
@php
    $conversationStatusLabels = ['active'=>'En cours','waiting_human'=>'À traiter','human'=>'Pris en charge','resolved'=>'Terminée','closed'=>'Archivée'];
    $ticketStatusLabels = ['open'=>'Nouveau','in_progress'=>'Pris en charge','waiting_customer'=>'Attente demandeur','waiting_internal'=>'Attente équipe OVANIE','resolved'=>'Terminé','closed'=>'Archivé','cancelled'=>'Annulé'];
@endphp
<section class="workspace-banner">
    <div>
        <span class="eyebrow"><i data-lucide="life-buoy"></i> Centre de support OVANIE</span>
        <h1>Bonjour {{ auth('admin')->user()?->first_name ?: auth('admin')->user()?->name }}</h1>
        <p>Le Support est l’assistance humaine derrière les assistants OVANIE : répondez aux préoccupations, suivez les demandes et transmettez aux équipes OVANIE uniquement lorsqu’une action métier est nécessaire.</p>
    </div>
    <div class="workspace-actions">
        <a class="btn btn-primary" href="{{ route('support.conversations.index') }}"><i data-lucide="messages-square"></i>Ouvrir la boîte</a>
        <a class="btn" href="{{ route('support.tickets.index', ['scope' => 'unassigned']) }}"><i data-lucide="inbox"></i>Dossiers à traiter</a>
    </div>
</section>

<form class="quick-search" method="GET" action="{{ route('support.search') }}">
    <i data-lucide="search"></i>
    <input name="q" placeholder="Rechercher un client, téléphone, commande, paiement, livraison, boutique, livreur ou mission…">
    <button class="btn btn-primary" type="submit">Rechercher</button>
</form>

<section class="grid kpi-grid">
    <a class="kpi-card" href="{{ route('support.conversations.index', ['scope' => 'waiting']) }}">
        <div class="kpi-head"><span class="kpi-label">Demandes à reprendre</span><span class="kpi-icon"><i data-lucide="messages-square"></i></span></div>
        <div class="kpi-value">{{ $stats['waiting_human_conversations'] }}</div>
        <div class="kpi-foot">Intervention humaine demandée</div>
    </a>
    <a class="kpi-card" href="{{ route('support.tickets.index', ['scope' => 'unassigned']) }}">
        <div class="kpi-head"><span class="kpi-label">Dossiers à prendre</span><span class="kpi-icon"><i data-lucide="inbox"></i></span></div>
        <div class="kpi-value">{{ $stats['unassigned'] }}</div>
        <div class="kpi-foot">Non attribués à un conseiller</div>
    </a>
    <a class="kpi-card" href="{{ route('support.tickets.index', ['scope' => 'mine']) }}">
        <div class="kpi-head"><span class="kpi-label">Mes dossiers actifs</span><span class="kpi-icon"><i data-lucide="user-check"></i></span></div>
        <div class="kpi-value">{{ $stats['mine'] }}</div>
        <div class="kpi-foot">Votre file personnelle</div>
    </a>
    <a class="kpi-card" href="{{ route('support.calls.index', ['tab' => 'callbacks']) }}">
        <div class="kpi-head"><span class="kpi-label">Rappels à effectuer</span><span class="kpi-icon"><i data-lucide="phone-call"></i></span></div>
        <div class="kpi-value">{{ $stats['pending_callbacks'] }}</div>
        <div class="kpi-foot">{{ $stats['missed_calls_today'] }} appel(s) manqué(s) aujourd’hui</div>
    </a>
</section>

@if($stats['urgent'] || $stats['overdue'])
<div class="notice warning"><i data-lucide="triangle-alert"></i><div><strong>Attention requise :</strong> {{ $stats['urgent'] }} dossier(s) urgent(s) et {{ $stats['overdue'] }} dossier(s) hors délai. <a class="record-title" href="{{ route('support.tickets.index', ['scope' => 'urgent']) }}">Voir les priorités</a>.</div></div>
@endif

<div class="grid two-columns">
    <section class="card">
        <div class="card-head"><div><h2>À traiter maintenant</h2><p>Vos dossiers sont classés par priorité puis par échéance.</p></div><a class="btn" href="{{ route('support.tickets.index', ['scope' => 'mine']) }}">Voir ma file</a></div>
        <div class="timeline">
            @forelse($myQueue as $ticket)
                <a class="timeline-item" href="{{ route('support.tickets.show',$ticket) }}">
                    <span class="timeline-dot"><i data-lucide="{{ $ticket->priority === 'urgent' ? 'triangle-alert' : 'ticket' }}"></i></span>
                    <span class="timeline-body {{ $ticket->is_overdue ? 'internal' : '' }}">
                        <span class="timeline-meta"><strong>{{ $ticket->reference }}</strong><span>{{ $ticket->sla_due_at?->diffForHumans() }}</span></span>
                        <p>{{ \Illuminate\Support\Str::limit($ticket->subject,100) }}</p>
                        <span class="record-sub">{{ $ticket->requesterProfile?->name ?: $ticket->requester?->name ?: $ticket->requester_name ?: 'Demandeur non identifié' }}</span>
                    </span>
                </a>
            @empty
                <div class="empty"><i data-lucide="circle-check-big"></i><div>Votre file est à jour.</div><span class="record-sub">Aucune action personnelle en attente.</span></div>
            @endforelse
        </div>
    </section>

    <aside class="card">
        <div class="card-head"><div><h2>Suivi de l’équipe</h2><p>Les éléments qui attendent une réponse ou une action externe.</p></div></div>
        <div class="detail-list">
            <a class="detail-row" href="{{ route('support.handoffs.index') }}"><span>Transferts en attente</span><strong>{{ $stats['pending_handoffs'] }}</strong></a>
            <a class="detail-row" href="{{ route('support.tickets.index', ['scope' => 'urgent']) }}"><span>Urgences</span><strong>{{ $stats['urgent'] }}</strong></a>
            <a class="detail-row" href="{{ route('support.tickets.index', ['scope' => 'overdue']) }}"><span>Hors délai</span><strong>{{ $stats['overdue'] }}</strong></a>
            <div class="detail-row"><span>Suivis terminés aujourd’hui</span><strong>{{ $stats['resolved_today'] }}</strong></div>
        </div>
        <div class="support-section-title">Canaux</div>
        <div class="channel-summary">
            @foreach($services as $service)
                <span class="channel-pill {{ $service['operational'] ? 'ok' : 'off' }}"><span class="channel-dot"></span>{{ $service['label'] }}</span>
            @endforeach
        </div>
    </aside>
</div>

<div class="support-section-title">Dernières demandes</div>
<div class="grid two-columns">
    <section class="card">
        <div class="card-head"><div><h2>Boîte de réception</h2><p>Derniers échanges reçus depuis les différents canaux.</p></div><a class="btn" href="{{ route('support.conversations.index') }}">Tout afficher</a></div>
        <div class="timeline">
            @forelse($recentConversations as $conversation)
                <a class="timeline-item" href="{{ route('support.conversations.show',$conversation) }}">
                    <span class="timeline-dot"><i data-lucide="message-circle"></i></span>
                    <span class="timeline-body">
                        <span class="timeline-meta"><strong>{{ $conversation->requesterProfile?->name ?: $conversation->requester?->name ?: $conversation->requester_name ?: 'Demandeur non identifié' }}</strong><span>{{ $conversation->last_message_at?->diffForHumans() }}</span></span>
                        <p>{{ $conversation->subject ?: 'Assistance OVANIE' }}</p>
                        <span class="record-sub">{{ strtoupper($conversation->channel) }} · {{ $conversationStatusLabels[$conversation->status] ?? str_replace('_',' ',$conversation->status) }}</span>
                    </span>
                </a>
            @empty
                <div class="empty"><i data-lucide="messages-square"></i><div>Aucune conversation récente.</div></div>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Dossiers récents</h2><p>Demandes formalisées à suivre jusqu’au traitement et à l’information du demandeur.</p></div><a class="btn" href="{{ route('support.tickets.index') }}">Tout afficher</a></div>
        <div class="timeline">
            @forelse($recentTickets->take(8) as $ticket)
                <a class="timeline-item" href="{{ route('support.tickets.show',$ticket) }}">
                    <span class="timeline-dot"><i data-lucide="ticket"></i></span>
                    <span class="timeline-body">
                        <span class="timeline-meta"><strong>{{ $ticket->reference }}</strong><span>{{ $ticket->created_at?->diffForHumans() }}</span></span>
                        <p>{{ \Illuminate\Support\Str::limit($ticket->subject,100) }}</p>
                        <span class="record-sub">{{ $ticket->requesterProfile?->name ?: $ticket->requester?->name ?: $ticket->requester_name ?: 'Non identifié' }} · {{ $ticketStatusLabels[$ticket->status] ?? str_replace('_',' ',$ticket->status) }}</span>
                    </span>
                </a>
            @empty
                <div class="empty"><i data-lucide="inbox"></i><div>Aucun dossier récent.</div></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
