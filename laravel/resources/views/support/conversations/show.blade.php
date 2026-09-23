@extends('layouts.staff')
@section('title', 'Conversation #'.$conversation->id.' | OVANIE Support')
@section('content')
@php
    $requesterName = $conversation->requesterProfile?->name ?: $conversation->requester?->name ?: $conversation->requester_name ?: 'Demandeur non identifié';
    $requesterType = $conversation->requesterProfile?->type_label ?: ($conversation->requester_user_id ? 'Compte OVANIE' : 'Visiteur');
    $statusLabels = ['active'=>'En cours','waiting_human'=>'À traiter','human'=>'Pris en charge','waiting_customer'=>'En attente du demandeur','waiting_internal'=>'En attente équipe OVANIE','resolved'=>'Terminée','closed'=>'Archivée'];
    $handoffStatusLabels = ['pending'=>'En attente','assigned'=>'Affecté','accepted'=>'Pris en charge','in_progress'=>'En traitement','resolved'=>'Réponse reçue','closed'=>'Archivé','cancelled'=>'Annulé'];
    $departmentLabels = ['logistique'=>'Logistique','commercial'=>'Commercial','administration'=>'Administration'];
    $activeHandoff = $conversation->handoffs->first(fn($handoff) => in_array($handoff->target_department, ['logistique','commercial','administration'], true) && in_array($handoff->status, ['pending','assigned','accepted','in_progress'], true));
    $contextTypeLabels = [
        'order'=>'Commande','payment'=>'Paiement','shipment'=>'Livraison','shop'=>'Boutique','return'=>'Retour',
        'dispute'=>'Litige','delivery_incident'=>'Incident logistique','vendor_payout'=>'Versement vendeur',
        'delivery_assignment'=>'Mission livreur','mission'=>'Mission livreur','driver'=>'Livreur partenaire',
        'product'=>'Produit','client'=>'Client','commercial_lead'=>'Suivi commercial'
    ];
@endphp
<div class="page-header">
    <div class="page-header-main"><span class="page-header-icon"><i data-lucide="messages-square"></i></span><div><h1 class="page-title">{{ $requesterName }}</h1><p class="page-subtitle">{{ $conversation->subject ?: 'Assistance OVANIE' }} · {{ ucfirst($conversation->channel) }} · dernière activité {{ $conversation->last_message_at?->diffForHumans() }}</p></div></div>
    <div class="page-actions">
        <a class="btn" href="{{ route('support.conversations.index') }}"><i data-lucide="arrow-left"></i>Boîte de réception</a>
        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover') && $conversation->assigned_to !== auth('admin')->id() && !in_array($conversation->status,['resolved','closed'],true))<form method="POST" action="{{ route('support.conversations.takeover',$conversation) }}">@csrf<button class="btn btn-primary" type="submit"><i data-lucide="hand"></i>Prendre en charge</button></form>@endif
        @if(!in_array($conversation->status,['resolved','closed'],true))<form method="POST" action="{{ route('support.conversations.resolve',$conversation) }}" onsubmit="return confirm('Terminer cette conversation ? Le suivi restera dans l’historique.')">@csrf<button class="btn" type="submit"><i data-lucide="check-circle"></i>Terminer</button></form>@endif
    </div>
</div>

<div class="notice"><i data-lucide="headphones"></i><div><strong>Assistance humaine OVANIE :</strong> répondez directement lorsque vous pouvez informer le demandeur. Si une action doit être réalisée par la Logistique, le Commercial ou l’Administration, transmettez-la à l’équipe concernée puis restez responsable de la communication avec le demandeur.</div></div>
@if($activeHandoff)<div class="notice warning"><i data-lucide="hourglass"></i><div><strong>En attente de {{ $departmentLabels[$activeHandoff->target_department] ?? ucfirst($activeHandoff->target_department) }}.</strong> Le Support continue de suivre cette conversation et informera le demandeur dès réception du retour.</div></div>@endif
@if(!$conversation->requester_user_id && !$conversation->requesterProfile)
<div class="notice warning"><i data-lucide="user-round-x"></i><div><strong>Demandeur non identifié.</strong> Aucun compte OVANIE n’a été retrouvé automatiquement. Vérifiez le téléphone ou l’e-mail avant de prendre une décision liée à une commande ou à un paiement.</div></div>
@endif

<div class="grid two-columns">
    <div class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Conversation</h2><p>{{ $requesterType }} · {{ $conversation->messages->count() }} message(s)</p></div><span class="status {{ $conversation->status }}">{{ $statusLabels[$conversation->status] ?? str_replace('_',' ',$conversation->status) }}</span></div>
            <div class="timeline">
                @forelse($conversation->messages as $message)
                    @php
                        $label = match($message->sender_type) {
                            'ai' => 'Assistant automatique',
                            'human' => $message->sender?->name ?: 'Conseiller Support',
                            'customer' => $requesterName,
                            default => 'Système',
                        };
                    @endphp
                    <div class="timeline-item">
                        <span class="timeline-dot"><i data-lucide="{{ $message->sender_type === 'customer' ? 'user-round' : ($message->sender_type === 'ai' ? 'bot' : 'headphones') }}"></i></span>
                        <div class="timeline-body {{ $message->is_internal ? 'internal' : '' }}">
                            <span class="timeline-meta"><strong>{{ $label }}{{ $message->is_internal ? ' · Note interne' : '' }}</strong><span>{{ $message->created_at?->format('d/m/Y H:i') }}</span></span>
                            <p>{{ $message->body }}</p>
                        </div>
                    </div>
                @empty
                    <div class="empty"><i data-lucide="message-circle-off"></i><div>Aucun message enregistré.</div></div>
                @endforelse
            </div>
        </section>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond') && !in_array($conversation->status,['resolved','closed'],true))
        <section class="card">
            <div class="card-head"><div><h2>Répondre au demandeur</h2><p>Une réponse est envoyée au demandeur. Une note interne reste visible uniquement par l’équipe Support.</p></div></div>
            <form method="POST" action="{{ route('support.conversations.reply',$conversation) }}">@csrf
                <div class="form-group"><label>Message *</label><textarea name="body" required placeholder="Écrivez votre réponse…"></textarea></div>
                <label style="display:flex;align-items:center;gap:8px;font-size:10px;color:#536784;margin:10px 0"><input type="checkbox" name="is_internal" value="1"> Note interne — ne pas envoyer au demandeur</label>
                <div class="page-actions" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><i data-lucide="send"></i>Envoyer / enregistrer</button></div>
            </form>
        </section>
        @endif
    </div>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Demandeur</h2><p>Identité et coordonnées disponibles pour cette conversation.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Type</span><strong>{{ $requesterType }}</strong></div>
                <div class="detail-row"><span>Nom</span><strong>{{ $requesterName }}</strong></div>
                <div class="detail-row"><span>E-mail</span><strong>{{ $conversation->requester_email ?: $conversation->requester?->email ?: '—' }}</strong></div>
                <div class="detail-row"><span>Téléphone</span><strong>{{ $conversation->requester_phone ?: $conversation->requester?->phone ?: '—' }}</strong></div>
                <div class="detail-row"><span>Pris en charge par</span><strong>{{ $conversation->assignee?->name ?: ($conversation->requires_human ? 'À attribuer' : 'Assistant automatique') }}</strong></div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Contexte OVANIE</h2><p>Les données affichées restent dans leurs modules d’origine.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Commande</span><strong>{{ $conversation->order ? $conversation->order->order_number.' · '.$conversation->order->status : '—' }}</strong></div>
                <div class="detail-row"><span>Paiement</span><strong>{{ $conversation->payment ? '#'.$conversation->payment->id.' · '.$conversation->payment->status.' · '.number_format((float)$conversation->payment->amount,0,',',' ').' FCFA' : '—' }}</strong></div>
                <div class="detail-row"><span>Livraison</span><strong>{{ $conversation->shipment ? ($conversation->shipment->tracking_number ?: '#'.$conversation->shipment->id).' · '.$conversation->shipment->status : '—' }}</strong></div>
                <div class="detail-row"><span>Boutique</span><strong>{{ $conversation->shop?->name ?: '—' }}</strong></div>
                <div class="detail-row"><span>Retour</span><strong>{{ $conversation->returnRequest?->status ?: '—' }}</strong></div>
                <div class="detail-row"><span>Litige</span><strong>{{ $conversation->dispute ? ($conversation->dispute->escalated ? 'Escaladé' : 'Ouvert') : '—' }}</strong></div>
                <div class="detail-row"><span>Incident logistique</span><strong>{{ $conversation->deliveryIncident ? '#'.$conversation->deliveryIncident->id.' · '.$conversation->deliveryIncident->status : '—' }}</strong></div>
                <div class="detail-row"><span>Dossier Support</span><strong>@if($conversation->ticket)<a class="record-title" href="{{ route('support.tickets.show',$conversation->ticket) }}">{{ $conversation->ticket->reference }}</a><span class="record-sub">Lié automatiquement au suivi humain</span>@elseif($conversation->requires_human)<span>À créer lors de la prise en charge</span><span class="record-sub">Le bouton « Prendre en charge » crée et lie automatiquement le dossier.</span>@else<span>Pas encore nécessaire</span><span class="record-sub">Cette conversation reste un échange simple tant qu’aucun suivi humain n’est requis.</span>@endif</strong></div>
                @foreach($conversation->ticket?->contextLinks ?? [] as $contextLink)
                    @if(!in_array($contextLink->context_type,['order','payment','shipment','shop','return','dispute','delivery_incident'],true))
                        <div class="detail-row"><span>{{ $contextTypeLabels[$contextLink->context_type] ?? ucfirst(str_replace('_',' ',$contextLink->context_type)) }}</span><strong>#{{ $contextLink->context_id }}</strong></div>
                    @endif
                @endforeach
            </div>
        </section>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover'))
        <section class="card">
            <div class="card-head"><div><h2>Suivi de la demande</h2><p>Formalisez le suivi si nécessaire ou demandez une action à une équipe OVANIE.</p></div></div>
            <div class="page-actions">
                @if(!$conversation->ticket)<button class="btn btn-primary" type="button" data-modal-open="#createTicketModal"><i data-lucide="ticket-plus"></i>Créer un dossier de suivi</button>@endif
                @if(!$activeHandoff && !in_array($conversation->status,['resolved','closed'],true))<button class="btn" type="button" data-modal-open="#handoffModal"><i data-lucide="forward"></i>Transmettre à une équipe OVANIE</button>@endif
            </div>
            @if($conversation->handoffs->whereIn('target_department',['logistique','commercial','administration'])->isNotEmpty())
                <div class="support-section-title">Transferts aux équipes OVANIE</div>
                <div class="detail-list">@foreach($conversation->handoffs->whereIn('target_department',['logistique','commercial','administration']) as $handoff)<div class="detail-row"><span>{{ $departmentLabels[$handoff->target_department] ?? ucfirst($handoff->target_department) }}<span class="record-sub">{{ $handoff->reference }}</span></span><strong>{{ $handoffStatusLabels[$handoff->status] ?? str_replace('_',' ',$handoff->status) }}@if($handoff->notes)<span class="record-sub">{{ \Illuminate\Support\Str::limit($handoff->notes,90) }}</span>@endif</strong></div>@endforeach</div>
            @endif
        </section>
        @endif
    </aside>
</div>

@if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.takeover'))
@if(!$activeHandoff && !in_array($conversation->status,['resolved','closed'],true))
<div class="modal-backdrop" id="handoffModal"><div class="modal"><div class="modal-header"><div><h2>Transmettre à une équipe OVANIE</h2><p>Le service destinataire effectue l’action métier. Le Support reste responsable du suivi avec le demandeur.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div><form method="POST" action="{{ route('support.conversations.handoff',$conversation) }}">@csrf<div class="modal-body"><div class="form-grid"><div class="form-group"><label>Équipe OVANIE *</label><select name="target_department" required><option value="logistique" @selected($conversation->deliveryIncident)>Logistique — livraison, mission, incident</option><option value="commercial" @selected($conversation->commercialLead)>Commercial — OVANIE Business, opportunité</option><option value="administration">Administration — vérification ou décision interne</option></select></div><div class="form-group"><label>Priorité *</label><select name="severity" required><option value="normal">Normale</option><option value="high">Haute</option><option value="urgent">Urgente</option></select></div><div class="form-group full"><label>Action attendue *</label><textarea name="reason" required placeholder="Expliquez précisément ce que l’équipe doit vérifier ou effectuer, et quelle information doit revenir au Support."></textarea></div></div></div><div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit"><i data-lucide="forward"></i>Transmettre</button></div></form></div></div>
@endif

@if(!$conversation->ticket)
<div class="modal-backdrop" id="createTicketModal"><div class="modal"><div class="modal-header"><div><h2>Créer un dossier de suivi</h2><p>À utiliser si cette préoccupation nécessite plusieurs actions, un délai de suivi ou l’intervention d’une autre équipe OVANIE.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div><form method="POST" action="{{ route('support.conversations.create-ticket',$conversation) }}">@csrf<div class="modal-body"><div class="form-grid"><div class="form-group"><label>Priorité *</label><select name="priority" required><option value="normal">Normale</option><option value="high">Haute</option><option value="urgent">Urgente</option><option value="low">Faible</option></select></div><div class="form-group full"><label>Préoccupation à suivre *</label><textarea name="reason" required placeholder="Résumez le problème à suivre…">{{ $conversation->summary }}</textarea></div></div></div><div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit"><i data-lucide="ticket-plus"></i>Créer le dossier</button></div></form></div></div>
@endif
@endif
@endsection
