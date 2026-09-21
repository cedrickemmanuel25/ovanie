@extends('layouts.logistics')
@section('title', 'Détail dossier Support Logistique | OVANIE')
@section('crumb', 'Support › Détail dossier')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-support.css') }}?v={{ @filemtime(public_path('css/logistics-support.css')) ?: '20260911' }}">
@endpush

@section('content')
@php
    $statusLabels = [
        'pending' => ['Nouveau', 'blue'],
        'assigned' => ['Affecté', 'sky'],
        'accepted' => ['Pris en charge', 'green'],
        'in_progress' => ['En traitement', 'orange'],
        'resolved' => ['Résolu', 'green'],
        'closed' => ['Clôturé', 'gray'],
    ];
    $priorityLabels = [
        'critical' => ['Critique', 'red'],
        'urgent' => ['Haute', 'red'],
        'high' => ['Haute', 'red'],
        'normal' => ['Moyenne', 'orange'],
        'low' => ['Basse', 'gray'],
    ];
    $status = $statusLabels[$handoff->status] ?? [ucfirst((string) $handoff->status), 'gray'];
    $priority = $priorityLabels[$handoff->severity] ?? ['Moyenne', 'orange'];
    $currentAgent = auth('admin')->user();
    $canClaim = in_array($handoff->status, ['pending','assigned'], true)
        && (!$handoff->assigned_to || (int) $handoff->assigned_to === (int) $currentAgent?->id);
@endphp

<main class="ls-support support-detail-screen support-detail-real">
    <section class="ls-page-head support-detail-head">
        <div>
            <h1>Détail dossier Support Logistique <span class="support-id-pill">{{ $handoff->reference }}</span> <span class="support-priority-pill {{ $priority[1] }}"><x-operations.icon name="flag"/> Priorité : {{ $priority[0] }}</span></h1>
            <p>Données liées au véritable transfert Support, à la commande et à l’opération logistique correspondante</p>
        </div>
        <div class="detail-head-actions">
            <a class="ls-outline-btn" href="{{ route('logistics.handoffs.index') }}"><x-operations.icon name="back"/> Retour à la liste</a>
            @if($canClaim)
                <form method="POST" action="{{ route('logistics.handoffs.claim', $handoff) }}">@csrf
                    <button class="ls-main-btn" type="submit"><x-operations.icon name="check"/> Prendre en charge</button>
                </form>
            @elseif($handoff->status === 'accepted')
                <form method="POST" action="{{ route('logistics.handoffs.status', $handoff) }}">@csrf @method('PATCH')
                    <input type="hidden" name="status" value="in_progress">
                    <button class="support-progress-btn" type="submit"><x-operations.icon name="clock"/> Démarrer le traitement</button>
                </form>
            @elseif($handoff->status === 'resolved')
                <form method="POST" action="{{ route('logistics.handoffs.status', $handoff) }}">@csrf @method('PATCH')
                    <input type="hidden" name="status" value="closed">
                    <button class="support-close-btn" type="submit"><x-operations.icon name="check"/> Clôturer le dossier</button>
                </form>
            @endif
        </div>
    </section>

    @if(session('success'))<div class="support-flash success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="support-flash error"><strong>Correction requise :</strong> {{ $errors->first() }}</div>
    @endif

    <section class="ls-kpi-row detail-kpi-row">
        <article class="ls-kpi-card"><span class="ls-kpi-icon sky"><x-operations.icon name="help"/></span><div><small>Statut</small><strong class="detail-status {{ $status[1] }}"><i></i>{{ $status[0] }}</strong><em class="muted">Depuis le {{ $handoff->requested_at?->format('d/m/Y H:i') ?: '—' }}</em></div></article>
        <article class="ls-kpi-card"><span class="ls-kpi-icon rose"><x-operations.icon name="flag"/></span><div><small>Priorité</small><strong class="priority-red">{{ $priority[0] }}</strong></div></article>
        <article class="ls-kpi-card"><span class="ls-kpi-icon violet"><x-operations.icon name="clock"/></span><div><small>SLA</small><strong>{{ $case['sla_label'] }}</strong>@if($case['sla_remaining'])<em class="{{ str_contains($case['sla_remaining'],'Dépassé') ? 'danger' : '' }}">{{ $case['sla_remaining'] }}</em>@endif</div></article>
        <article class="ls-kpi-card"><span class="ls-kpi-icon steel"><x-operations.icon name="truck"/></span><div><small>Type de dossier</small><strong class="detail-type-value">{{ $case['case_type'] }}</strong></div></article>
        <article class="ls-kpi-card assignment-card"><span class="ls-kpi-icon sky"><x-operations.icon name="user"/></span><div><small>Assigné à</small><strong class="detail-assignee-value">{{ $case['assigned_label'] }}</strong></div></article>
    </section>

    <section class="support-detail-grid">
        <article class="ls-panel detail-card info-card real-info-card">
            <h2><span class="card-title-icon navy"><x-operations.icon name="list"/></span> Informations générales</h2>
            <dl class="detail-dl">
                <dt>N° dossier</dt><dd><b>{{ $handoff->reference }}</b></dd>
                @if($handoff->ticket)<dt>Ticket Support</dt><dd><b>{{ $handoff->ticket->reference }}</b></dd>@endif
                <dt>Source</dt><dd><b>{{ $case['source'] }}</b></dd>
                <dt>Date de transfert</dt><dd><b>{{ $handoff->requested_at?->format('d/m/Y H:i') ?: '—' }}</b></dd>
                <dt>Client</dt><dd><b>{{ $case['client_name'] }}</b><br>{{ $case['client_phone'] }}@if($case['client_email'] !== '—')<br>{{ $case['client_email'] }}@endif</dd>
                <dt>Canal</dt><dd><b>{{ $case['channel'] }}</b></dd>
                <dt>Sujet</dt><dd><b>{{ $case['subject'] }}</b></dd>
                <dt>Description initiale</dt><dd>{{ $case['initial_description'] }}</dd>
                <dt class="attachments-label">Pièces jointes</dt>
                <dd class="attachments-cell real-attachments">
                    @forelse($case['attachments'] as $attachment)
                        <a class="real-attachment-link" href="{{ $attachment['download_url'] }}">
                            <span class="attachment-file-icon">{{ str_contains(strtolower($attachment['mime'] ?? ''),'pdf') ? 'PDF' : 'DOC' }}</span>
                            <span><b>{{ $attachment['name'] }}</b>@if($attachment['size'])<small>{{ number_format($attachment['size']/1024, 0, ',', ' ') }} Ko</small>@endif</span>
                        </a>
                    @empty
                        <span class="empty-real-data">Aucune pièce jointe enregistrée sur le ticket.</span>
                    @endforelse
                </dd>
            </dl>
        </article>

        <article class="ls-panel detail-card order-card real-order-card">
            <h2><span class="card-title-icon navy"><x-operations.icon name="truck"/></span> Commande / Livraison liée</h2>
            @if($case['order'] || $case['shipment_item_id'] || $case['tracking_number'] !== '—')
                <dl class="detail-dl">
                    <dt>N° commande</dt><dd><b>{{ $case['order_number'] }}</b>@if($case['shipment_item_id'])<a class="mini-link-btn" href="{{ route('logistics.shipments.details', $case['shipment_item_id']) }}">Voir l’expédition</a>@endif</dd>
                    <dt>Date commande</dt><dd><b>{{ $case['order_date'] }}</b></dd>
                    <dt>Produits</dt><dd class="real-product-cell">
                        @if($case['products_count'] > 0)
                            <b>{{ $case['product_name'] }}</b>@if($case['product_quantity']) <span>× {{ $case['product_quantity'] }}</span>@endif
                            @if($case['products_count'] > 1)<small>{{ $case['products_count'] }} lignes produit dans la commande</small>@endif
                        @else — @endif
                    </dd>
                    <dt>Mode de livraison</dt><dd><b>{{ $case['delivery_mode'] }}</b></dd>
                    <dt>N° de suivi</dt><dd><b>{{ $case['tracking_number'] }}</b>@if($case['shipment_item_id'])<a class="mini-link-btn" href="{{ route('logistics.tracking.mission', $case['shipment_item_id']) }}">Suivre</a>@endif</dd>
                    <dt>Statut livraison</dt><dd><span class="delivery-status real-delivery-status"><i></i>{{ $case['delivery_status'] }}</span></dd>
                    <dt>Date prévue</dt><dd><b>{{ $case['expected_date'] }}</b></dd>
                    <dt>Adresse de livraison</dt><dd class="address-cell"><x-operations.icon name="pin"/><span>{{ $case['delivery_address'] }}</span></dd>
                </dl>
            @else
                <div class="real-empty-panel"><x-operations.icon name="truck"/><strong>Aucune commande ou livraison liée</strong><span>Le Support n’a pas associé ce dossier à une opération logistique précise.</span></div>
            @endif
        </article>

        <article class="ls-panel detail-card problem-card real-problem-card">
            <h2><span class="card-title-icon red"><x-operations.icon name="alert"/></span> Problème signalé</h2>
            <dl class="detail-dl problem-dl">
                <dt>Catégorie</dt><dd><b>{{ $case['problem_category'] }}</b></dd>
                <dt>Gravité</dt><dd><span class="ls-badge {{ $priority[1] }}"><i></i>{{ $case['problem_severity'] }}</span></dd>
                <dt>Détails</dt><dd>{{ $case['problem_details'] }}</dd>
                @if($case['incident'])
                    <dt>Incident lié</dt><dd><a class="support-inline-link" href="{{ route('logistics.incidents.show', $case['incident']) }}">Ouvrir l’incident logistique</a></dd>
                @endif
            </dl>
        </article>

        <article class="ls-panel conversation-card real-conversation-card">
            <h2><span class="card-title-icon navy"><x-operations.icon name="users"/></span> Conversation liée au dossier</h2>
            <div class="conversation-list">
                @forelse($handoff->conversation?->messages ?? [] as $message)
                    @php
                        $tone = $message->sender_type === 'ai' ? 'ai' : ($message->sender_type === 'human' ? 'team' : 'client');
                        $label = match($message->sender_type) {
                            'ai' => $message->aiAgent?->name ?: 'Support IA',
                            'human' => $message->sender?->name ?: data_get($message->metadata, 'sender_label', 'Équipe Logistique'),
                            default => $case['client_name'],
                        };
                        $initials = collect(preg_split('/\s+/u', trim($label)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part,0,1)))->implode('');
                    @endphp
                    <div class="conversation-row {{ $tone }}">
                        <span class="message-avatar">{{ $initials ?: 'OV' }}</span>
                        <div class="message-main"><b>{{ $label }}</b><div class="message-bubble">{!! nl2br(e($message->body)) !!}</div></div>
                        <time>{{ $message->created_at?->format('d/m/Y H:i') ?: '—' }}</time>
                    </div>
                @empty
                    <div class="conversation-empty">Aucun message n’est enregistré dans la conversation liée à ce dossier.</div>
                @endforelse
            </div>
            @if($handoff->support_conversation_id && $handoff->status !== 'closed')
                <form class="message-compose real-message-compose" method="POST" action="{{ route('logistics.handoffs.messages', $handoff) }}">@csrf
                    <label><input name="message" placeholder="Écrire une réponse au client / Support..." required maxlength="2500"></label>
                    <button class="ls-main-btn" type="submit">Envoyer</button>
                </form>
            @endif
        </article>

        <div class="detail-right-stack">
            <article class="ls-panel detail-card resolution-card real-resolution-card">
                <h2><span class="card-title-icon green"><x-operations.icon name="check"/></span> Résolution</h2>
                @if(in_array($handoff->status, ['resolved','closed'], true))
                    <dl class="detail-dl">
                        <dt>Solution apportée</dt><dd><b>{{ $case['resolution_label'] }}</b></dd>
                        <dt>Détail</dt><dd>{{ $case['resolution_detail'] ?: '—' }}</dd>
                        <dt>Date de résolution</dt><dd>{{ $handoff->resolved_at?->format('d/m/Y H:i') ?: '—' }}</dd>
                        <dt>Résolu par</dt><dd>{{ $handoff->completedBy?->name ?: '—' }}</dd>
                    </dl>
                @elseif(in_array($handoff->status, ['accepted','in_progress'], true))
                    <form class="resolution-form" method="POST" action="{{ route('logistics.handoffs.resolve', $handoff) }}">@csrf
                        <label>Solution apportée
                            <select name="resolution_code" required>
                                <option value="">Sélectionner...</option>
                                <option value="incident_resolved" @selected(old('resolution_code')==='incident_resolved')>Incident résolu</option>
                                <option value="answered" @selected(old('resolution_code')==='answered')>Réponse apportée au client</option>
                                <option value="redirected" @selected(old('resolution_code')==='redirected')>Redirigé vers le service compétent</option>
                                <option value="commercial_follow_up" @selected(old('resolution_code')==='commercial_follow_up')>Suivi commercial nécessaire</option>
                                <option value="duplicate" @selected(old('resolution_code')==='duplicate')>Dossier en doublon</option>
                                <option value="other" @selected(old('resolution_code')==='other')>Autre</option>
                            </select>
                        </label>
                        <label>Détail de la résolution
                            <textarea name="notes" required maxlength="3000" placeholder="Décrire l’action réellement effectuée...">{{ old('notes') }}</textarea>
                        </label>
                        <button class="ls-main-btn" type="submit">Enregistrer la résolution</button>
                    </form>
                @else
                    <div class="resolution-waiting"><strong>Résolution en attente</strong><span>Le dossier doit être pris en charge avant d’enregistrer la solution.</span></div>
                @endif
            </article>

            <article class="ls-panel history-card real-history-card">
                <h2><span class="card-title-icon violet"><x-operations.icon name="clock"/></span> Historique du dossier</h2>
                <div class="history-list">
                    @forelse($case['history'] as $event)
                        <div class="history-event {{ $event['tone'] ?? 'blue' }}"><i></i><time>{{ $event['time'] }}</time><div><b>{{ $event['title'] }}</b><span>{{ $event['text'] }}</span></div></div>
                    @empty
                        <div class="history-empty">Aucun événement enregistré.</div>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
</main>
@endsection
