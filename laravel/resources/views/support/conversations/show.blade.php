@extends('layouts.staff')
@section('title', 'Conversation #'.$conversation->id.' | OVANIE')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Conversation #{{ $conversation->id }}</h1>
        <p class="page-subtitle">{{ $conversation->subject ?: 'Assistance OVANIE' }} · {{ ucfirst($conversation->channel) }} · Dernière activité {{ $conversation->last_message_at?->diffForHumans() }}</p>
    </div>
    <div class="page-actions">
        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover') && $conversation->assigned_to !== auth('admin')->id())
            <form method="POST" action="{{ route('support.conversations.takeover', $conversation) }}">@csrf<button class="btn btn-primary" type="submit"><i data-lucide="hand"></i>Prendre la conversation</button></form>
        @endif
        @if(!in_array($conversation->status, ['resolved','closed'], true))
            <form method="POST" action="{{ route('support.conversations.resolve', $conversation) }}" onsubmit="return confirm('Marquer cette conversation comme résolue ?')">@csrf<button class="btn" type="submit"><i data-lucide="check-circle"></i>Résoudre</button></form>
        @endif
    </div>
</div>

@if(!$conversation->requester_user_id)
    <div class="alert alert-error">Aucun compte réel n’a pu être identifié avec l’e-mail ou le téléphone fournis. Les informations de commande et de paiement ne sont donc pas devinées automatiquement.</div>
@endif

<div class="grid two-columns">
    <section class="card">
        <div class="card-head">
            <div><h2>Fil de conversation</h2><p>{{ $conversation->aiAgent?->name ?: 'Agent IA non attribué' }} · Confiance {{ $conversation->ai_confidence !== null ? number_format($conversation->ai_confidence, 0).' %' : '—' }}</p></div>
            <span class="status {{ $conversation->status }}">{{ str_replace('_',' ',$conversation->status) }}</span>
        </div>
        <div class="timeline">
            @forelse($conversation->messages as $message)
                @php
                    $label = match($message->sender_type) {
                        'ai' => $message->aiAgent?->name ?: 'Agent IA',
                        'human' => $message->sender?->name ?: 'Agent humain',
                        'customer' => $conversation->requester?->name ?: $conversation->requester_name ?: 'Demandeur',
                        default => 'Système',
                    };
                @endphp
                <div class="timeline-item">
                    <span class="timeline-dot">{{ mb_strtoupper(mb_substr($label,0,1)) }}</span>
                    <div class="timeline-body {{ $message->is_internal ? 'internal' : '' }}">
                        <span class="timeline-meta"><strong>{{ $label }} · {{ ucfirst($message->sender_type) }}{{ $message->is_internal ? ' · Note interne' : '' }}</strong><span>{{ $message->created_at?->format('d/m/Y H:i') }}</span></span>
                        <p>{{ $message->body }}</p>
                        @if($message->confidence !== null)<span class="record-sub">Confiance IA : {{ number_format($message->confidence,0) }} % · Fournisseur : {{ $message->provider ?: '—' }}</span>@endif
                    </div>
                </div>
            @empty
                <div class="empty"><i data-lucide="message-circle-off"></i><div>Aucun message réel enregistré.</div></div>
            @endforelse
        </div>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond') && !in_array($conversation->status, ['resolved','closed'], true))
        <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--line)">
            <form method="POST" action="{{ route('support.conversations.reply', $conversation) }}">@csrf
                <div class="form-group"><label>Réponse humaine ou note interne</label><textarea name="body" required placeholder="Écrivez votre réponse au demandeur ou une note pour l’équipe."></textarea></div>
                <label style="display:flex;align-items:center;gap:8px;font-size:10px;color:var(--muted);margin:10px 0"><input type="checkbox" name="is_internal" value="1"> Enregistrer comme note interne</label>
                <button class="btn btn-primary" type="submit"><i data-lucide="send"></i>Enregistrer la réponse</button>
            </form>
            <form method="POST" action="{{ route('support.conversations.ai-reply', $conversation) }}" style="margin-top:14px">@csrf
                <div class="form-group"><label>Nouveau message du demandeur à analyser</label><textarea name="body" required placeholder="L’IA relira les relations réelles et uniquement les connaissances publiées."></textarea></div>
                <button class="btn" type="submit" style="margin-top:10px"><i data-lucide="bot"></i>Faire répondre l’agent IA</button>
            </form>
        </div>
        @endif
    </section>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Compte et données reliés</h2><p>Les données restent dans leurs tables d’origine ; seuls les identifiants sont conservés dans la conversation.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Compte</span><strong>{{ $conversation->requester ? '#'.$conversation->requester->id.' · '.$conversation->requester->name : 'Non identifié' }}</strong></div>
                <div class="detail-row"><span>Correspondance</span><strong>{{ $conversation->requester_match_method ?: 'Aucune' }}{{ $conversation->requester_matched_at ? ' · '.$conversation->requester_matched_at->format('d/m/Y H:i') : '' }}</strong></div>
                <div class="detail-row"><span>E-mail</span><strong>{{ $conversation->requester_email ?: $conversation->requester?->email ?: '—' }}</strong></div>
                <div class="detail-row"><span>Téléphone</span><strong>{{ $conversation->requester_phone ?: $conversation->requester?->phone ?: '—' }}</strong></div>
                <div class="detail-row"><span>Commande</span><strong>{{ $conversation->order ? $conversation->order->order_number.' · '.$conversation->order->status : '—' }}</strong></div>
                <div class="detail-row"><span>Paiement</span><strong>{{ $conversation->payment ? '#'.$conversation->payment->id.' · '.$conversation->payment->status.' · '.number_format((float)$conversation->payment->amount,0,',',' ').' FCFA' : '—' }}</strong></div>
                <div class="detail-row"><span>Livraison</span><strong>{{ $conversation->shipment ? ($conversation->shipment->tracking_number ?: '#'.$conversation->shipment->id).' · '.$conversation->shipment->status : '—' }}</strong></div>
                <div class="detail-row"><span>Boutique</span><strong>{{ $conversation->shop?->name ?: '—' }}</strong></div>
                <div class="detail-row"><span>Incident logistique</span><strong>{{ $conversation->deliveryIncident ? '#'.$conversation->deliveryIncident->id.' · '.$conversation->deliveryIncident->incident_type.' · '.$conversation->deliveryIncident->status : '—' }}</strong></div>
                <div class="detail-row"><span>Opportunité Miss Rita</span><strong>{{ $conversation->commercialLead ? $conversation->commercialLead->reference.' · '.$conversation->commercialLead->status : '—' }}</strong></div>
                <div class="detail-row"><span>Retour</span><strong>{{ $conversation->returnRequest?->status ?: '—' }}</strong></div>
                <div class="detail-row"><span>Litige</span><strong>{{ $conversation->dispute ? ($conversation->dispute->escalated ? 'Escaladé' : 'Ouvert') : '—' }}</strong></div>
                <div class="detail-row"><span>Ticket</span><strong>@if($conversation->ticket)<a href="{{ route('support.tickets.show',$conversation->ticket) }}">{{ $conversation->ticket->reference }}</a>@else Aucun @endif</strong></div>
            </div>
        </section>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover'))
        <section class="card">
            <div class="card-head"><div><h2>Envoyer dans une file humaine</h2><p>Le dossier est assigné au service cible sans dupliquer les données métier.</p></div></div>
            <form method="POST" action="{{ route('support.conversations.handoff',$conversation) }}">@csrf
                <div class="form-group"><label>Service destinataire</label><select name="target_department" required><option value="support">Support</option><option value="logistique" @selected($conversation->deliveryIncident)>Logistique</option><option value="commercial" @selected($conversation->commercialLead)>Commercial</option><option value="administration">Administration</option></select></div>
                <div class="form-group" style="margin-top:10px"><label>Niveau</label><select name="severity" required><option value="normal">Normal</option><option value="high">Élevé</option><option value="urgent">Urgent</option></select></div>
                <div class="form-group" style="margin-top:10px"><label>Motif</label><textarea name="reason" required placeholder="Expliquez l’action attendue du service destinataire."></textarea></div>
                <button class="btn btn-orange" type="submit" style="margin-top:10px"><i data-lucide="user-round-check"></i>Ajouter à la file</button>
            </form>
        </section>
        @endif

        <section class="card">
            <div class="card-head"><div><h2>Files et suivis</h2><p>État des transferts réels issus de cette conversation.</p></div></div>
            <div class="detail-list">
                @forelse($conversation->handoffs as $handoff)
                    <div class="detail-row"><span>{{ $handoff->reference }}</span><strong>{{ ucfirst($handoff->target_department) }} · {{ str_replace('_',' ',$handoff->status) }} · {{ $handoff->assignee?->name ?: 'Non assigné' }}</strong></div>
                @empty
                    <div class="detail-row"><span>Transferts</span><strong>Aucun</strong></div>
                @endforelse
                <div class="detail-row"><span>Appels</span><strong>{{ $conversation->calls->count() }}</strong></div>
                <div class="detail-row"><span>Rappels</span><strong>{{ $conversation->callbackRequests->count() }}</strong></div>
            </div>
        </section>

        @if(!$conversation->ticket && auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover'))
        <section class="card">
            <div class="card-head"><div><h2>Créer un ticket</h2><p>Le ticket reprend les mêmes relations commande, paiement, livraison et incident.</p></div></div>
            <form method="POST" action="{{ route('support.conversations.create-ticket',$conversation) }}">@csrf
                <div class="form-group"><label>Priorité</label><select name="priority" required><option value="normal">Normale</option><option value="high">Élevée</option><option value="urgent">Urgente</option><option value="low">Faible</option></select></div>
                <div class="form-group" style="margin-top:10px"><label>Résumé du ticket</label><textarea name="reason" required>{{ $conversation->summary }}</textarea></div>
                <button class="btn btn-primary" type="submit" style="margin-top:10px"><i data-lucide="ticket-plus"></i>Créer le ticket</button>
            </form>
        </section>
        @endif
    </aside>
</div>
@endsection
