@extends('layouts.staff')
@section('title', 'Historique des actions | OVANIE Support')
@section('content')
@php
    $actionLabels = [
        'support_request_received' => 'Nouvelle demande reçue',
        'support_customer_message' => 'Nouveau message du demandeur',
        'support_human_reply' => 'Réponse envoyée par un conseiller',
        'conversation_taken_over' => 'Conversation prise en charge par un conseiller',
        'conversation_resolved' => 'Suivi terminé',
        'handoff_enqueued' => 'Demande transmise à une équipe OVANIE',
        'handoff_claimed' => 'Demande prise en charge par l’équipe destinataire',
        'handoff_assigned' => 'Demande affectée à un collaborateur',
        'handoff_resolved' => 'Réponse reçue de l’équipe OVANIE',
        'ai_reply' => 'Réponse envoyée par l’assistant',
        'deterministic_welcome' => 'Message d’accueil automatique',
        'ai_agent_configuration_updated' => 'Configuration d’un assistant modifiée',
        'knowledge_article_created' => 'Procédure ajoutée',
        'knowledge_article_updated' => 'Procédure mise à jour',
        'knowledge_article_deleted' => 'Procédure archivée',
    ];
    $decisionLabels = [
        'clarify' => 'Informations complémentaires demandées',
        'laravel_welcome_without_anthropic' => 'Message d’accueil standard envoyé car le moteur IA externe n’était pas disponible',
        'human_control' => 'Prise en charge humaine confirmée',
        'resolved' => 'Suivi terminé',
        'human_support_queue' => 'Demande envoyée dans la file du Support humain',
        'message_recorded' => 'Message enregistré',
        'internal_note' => 'Note interne enregistrée',
        'support' => 'Transmis au Support',
        'logistique' => 'Transmis à la Logistique',
        'commercial' => 'Transmis au Commercial',
        'administration' => 'Transmis à l’Administration',
        'active' => 'Assistant activé',
        'paused' => 'Assistant mis en pause',
        'unavailable' => 'Assistant indisponible',
    ];
    $riskLabels = [
        'low' => 'Faible',
        'normal' => 'Normal',
        'medium' => 'Modéré',
        'high' => 'Élevé',
        'urgent' => 'Urgent',
        'critical' => 'Critique',
    ];
@endphp

<div class="page-header">
    <div class="page-header-main">
        <span class="page-header-icon"><i data-lucide="history"></i></span>
        <div>
            <h1 class="page-title">Historique des actions</h1>
            <p class="page-subtitle">Consultez les actions réellement enregistrées par le Support : réponses automatiques, reprises humaines et transferts aux équipes OVANIE. Les codes internes restent disponibles uniquement dans les détails techniques.</p>
        </div>
    </div>
</div>

<div class="notice"><i data-lucide="database"></i><div><strong>Aucune saisie manuelle.</strong> Cette page se remplit automatiquement lorsque le système reçoit une demande, qu’un assistant répond, qu’un conseiller prend la main ou qu’une équipe OVANIE reçoit ou retourne un transfert.</div></div>

<div class="grid kpi-grid" style="margin-bottom:16px">
    @foreach([
        ['label'=>'Actions aujourd’hui','value'=>$activityStats['today'],'icon'=>'calendar-check'],
        ['label'=>'Actions des assistants','value'=>$activityStats['assistant'],'icon'=>'bot'],
        ['label'=>'Actions humaines','value'=>$activityStats['human'],'icon'=>'user-check'],
        ['label'=>'Transferts enregistrés','value'=>$activityStats['transfers'],'icon'=>'forward'],
    ] as $stat)
        <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">{{ $stat['label'] }}</span><span class="kpi-icon"><i data-lucide="{{ $stat['icon'] }}"></i></span></div><div class="kpi-value">{{ $stat['value'] }}</div></div>
    @endforeach
</div>

<div class="toolbar">
    <form class="filters" method="GET">
        <select name="action"><option value="">Toutes les actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action')===$action)>{{ $actionLabels[$action] ?? 'Autre action enregistrée' }}</option>@endforeach</select>
        <select name="risk_level"><option value="">Tous les niveaux</option>@foreach(['low','normal','medium','high','urgent','critical'] as $risk)<option value="{{ $risk }}" @selected(request('risk_level')===$risk)>{{ $riskLabels[$risk] }}</option>@endforeach</select>
        <select name="ai_agent_id"><option value="">Tous les assistants</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('ai_agent_id')===(string)$agent->id)>{{ $agent->name }}</option>@endforeach</select>
        <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
    </form>
</div>

<section class="card">
    <div class="card-head"><div><h2>Activité enregistrée</h2><p>Lecture métier en français. Ouvrez « Détails techniques » uniquement pour un diagnostic.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Action</th><th>Effectuée par</th><th>Dossier / conversation</th><th>Résultat</th><th>Niveau</th><th>Contrôle</th></tr></thead><tbody>
    @forelse($logs as $log)
        @php
            $resultLabel = $decisionLabels[$log->decision] ?? ($log->decision ? 'Action enregistrée' : '—');
            $actorLabel = $log->actor?->name ?: (($log->aiAgent?->code === 'AI-LOGISTICS-SUPPORT') ? 'Ancien assistant Support (historique)' : ($log->aiAgent?->name ?: 'Système OVANIE'));
            $actorType = $log->actor ? 'Conseiller / collaborateur OVANIE' : ($log->aiAgent ? 'Assistant automatisé' : 'Traitement automatique');
        @endphp
        <tr>
            <td>{{ $log->occurred_at?->format('d/m/Y H:i') ?: $log->created_at?->format('d/m/Y H:i') }}</td>
            <td><span class="record-title">{{ $actionLabels[$log->action] ?? 'Action système enregistrée' }}</span>
                <details style="margin-top:5px"><summary class="record-sub" style="cursor:pointer">Détails techniques</summary><span class="record-sub">Code : {{ $log->action }}<br>Identifiant : {{ $log->event_uuid }}</span></details>
            </td>
            <td><span class="record-title">{{ $actorLabel }}</span><span class="record-sub">{{ $actorType }}</span></td>
            <td>
                @if($log->conversation)
                    @if($log->conversation->ticket)<a class="record-title" href="{{ route('support.tickets.show',$log->conversation->ticket) }}">{{ $log->conversation->ticket->reference }}</a><span class="record-sub">Conversation #{{ $log->conversation->id }}</span>
                    @else<a class="record-title" href="{{ route('support.conversations.show',$log->conversation) }}">Conversation #{{ $log->conversation->id }}</a><span class="record-sub">Aucun dossier nécessaire à ce stade</span>@endif
                @elseif($log->call)
                    <a class="record-title" href="{{ route('support.calls.show',$log->call) }}">{{ $log->call->reference }}</a>
                @else<span class="record-sub">Action générale du système</span>@endif
            </td>
            <td><span class="record-title">{{ $resultLabel }}</span>@if($log->decision && !isset($decisionLabels[$log->decision]))<details><summary class="record-sub" style="cursor:pointer">Voir le code interne</summary><span class="record-sub">{{ $log->decision }}</span></details>@endif</td>
            <td><span class="status {{ $log->risk_level }}">{{ $riskLabels[$log->risk_level] ?? 'Normal' }}</span></td>
            <td><span class="status {{ $log->integrity_valid ? 'active' : 'unavailable' }}">{{ $log->integrity_valid ? 'Vérifié' : 'À contrôler' }}</span></td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty"><i data-lucide="history"></i><div>Aucune action réelle enregistrée.</div><span class="record-sub">La liste se remplira automatiquement lorsque le Support sera utilisé.</span></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination-row"><span>{{ $logs->total() }} action(s)</span><div>{{ $logs->links() }}</div></div>
</section>
@endsection
