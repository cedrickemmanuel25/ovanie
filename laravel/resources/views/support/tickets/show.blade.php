@extends('layouts.staff')
@section('title', $ticket->reference . ' | Support OVANIE')
@section('content')
@php
    $requesterName = $ticket->requesterProfile?->name ?: $ticket->requester?->name ?: $ticket->requester_name ?: 'Demandeur non identifié';
    $requesterEmail = $ticket->requesterProfile?->email ?: $ticket->requester?->email ?: $ticket->requester_email;
    $requesterPhone = $ticket->requesterProfile?->phone ?: $ticket->requester?->phone ?: $ticket->requester_phone;
    $requesterType = $ticket->requesterProfile?->type_label ?: 'Client / visiteur';
    $sourceLabels = ['client_mobile'=>'Application Client','vendor_mobile'=>'Application Vendeur','commercial_mobile'=>'Application Commercial','driver_mobile'=>'Application Livreur','website'=>'Site OVANIE','whatsapp'=>'WhatsApp','phone'=>'Téléphone','support_web'=>'Support interne','internal'=>'Interne'];
    $contextLabels = ['order'=>'Commande','shop'=>'Boutique','payment'=>'Paiement','shipment'=>'Livraison','return'=>'Retour','dispute'=>'Litige','delivery_incident'=>'Incident logistique','driver'=>'Livreur partenaire','delivery_assignment'=>'Mission de livraison','mission'=>'Mission de livraison','vendor_payout'=>'Versement vendeur','product'=>'Produit','commercial_lead'=>'Opportunité commerciale','client'=>'Client'];
    $statusLabels = ['open'=>'Nouveau','in_progress'=>'Pris en charge','waiting_customer'=>'En attente du demandeur','waiting_internal'=>'En attente équipe OVANIE','resolved'=>'Terminé','closed'=>'Archivé','cancelled'=>'Annulé'];
    $priorityLabels = ['low'=>'Faible','normal'=>'Normale','high'=>'Haute','urgent'=>'Urgente'];
    $handoffStatusLabels = ['pending'=>'En attente','assigned'=>'Affecté','accepted'=>'Pris en charge','in_progress'=>'En traitement','resolved'=>'Réponse reçue','closed'=>'Archivé','cancelled'=>'Annulé'];
    $departmentLabels = ['logistique'=>'Logistique','commercial'=>'Commercial','administration'=>'Administration'];
    $activeHandoff = $ticket->handoffs->first(fn($handoff) => in_array($handoff->status, ['pending','assigned','accepted','in_progress'], true));
    $linkedConversation = $ticket->conversations->first();
@endphp
<div class="page-header">
    <div class="page-header-main"><span class="page-header-icon"><i data-lucide="ticket"></i></span><div><h1 class="page-title">{{ $ticket->reference }}</h1><p class="page-subtitle">{{ $ticket->subject }} · créé le {{ $ticket->created_at?->format('d/m/Y à H:i') }}</p></div></div>
    <div class="page-actions"><a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="arrow-left"></i>Retour</a>@if($linkedConversation)<a class="btn" href="{{ route('support.conversations.show',$linkedConversation) }}"><i data-lucide="messages-square"></i>Conversation</a>@endif<span class="status {{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? ucfirst($ticket->priority) }}</span><span class="status {{ $ticket->status }}">{{ $statusLabels[$ticket->status] ?? str_replace('_',' ',$ticket->status) }}</span></div>
</div>

<div class="notice"><i data-lucide="life-buoy"></i><div><strong>Rôle du Support :</strong> répondre aux préoccupations, suivre le demandeur et réunir le bon contexte OVANIE. Lorsqu’une action métier est nécessaire, transmettez le dossier à l’équipe concernée. <strong>Le Support reste l’interlocuteur du demandeur jusqu’à la fin du suivi.</strong></div></div>
@if($activeHandoff)<div class="notice warning"><i data-lucide="hourglass"></i><div><strong>Action OVANIE en attente :</strong> ce dossier a été transmis à {{ $departmentLabels[$activeHandoff->target_department] ?? ucfirst($activeHandoff->target_department) }}. Le Support conserve le suivi et informera le demandeur lorsque la réponse du service sera reçue.</div></div>@endif
@if($ticket->is_overdue)<div class="notice warning"><i data-lucide="alarm-clock"></i><div><strong>Délai de suivi dépassé.</strong> Échéance prévue : {{ $ticket->sla_due_at?->format('d/m/Y à H:i') }}.</div></div>@endif

<div class="grid two-columns">
    <div class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Historique du dossier</h2><p>Réponses au demandeur, notes internes et retours des équipes OVANIE.</p></div></div>
            <div class="timeline">
                @forelse($ticket->messages as $message)
                    <article class="timeline-item"><span class="timeline-dot"><i data-lucide="{{ $message->is_internal_note ? 'notebook-tabs' : 'message-circle' }}"></i></span><div class="timeline-body {{ $message->is_internal_note ? 'internal' : '' }}"><div class="timeline-meta"><strong>{{ $message->author?->name ?: ucfirst($message->author_type) }}{{ $message->is_internal_note ? ' · Note interne' : '' }}</strong><span>{{ $message->created_at?->format('d/m/Y H:i') }}</span></div><p>{{ $message->body }}</p></div></article>
                @empty<div class="empty"><i data-lucide="messages-square"></i><div>Aucun message enregistré.</div></div>@endforelse
            </div>
        </section>

        @if(auth('admin')->user()?->hasStaffPermission('tickets.write') && !in_array($ticket->status,['resolved','closed','cancelled'],true))
        <section class="card">
            <div class="card-head"><div><h2>Répondre au demandeur</h2><p>Répondez directement lorsque le Support peut apporter l’information. Utilisez une note interne pour documenter une vérification sans notifier le demandeur.</p></div></div>
            <form method="POST" action="{{ route('support.tickets.reply',$ticket) }}">@csrf
                <div class="form-group"><label>Message *</label><textarea name="body" required placeholder="Écrivez la réponse ou la note de suivi…">{{ old('body') }}</textarea></div>
                <label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-size:10px;font-weight:750;color:#445b7a"><input type="checkbox" name="is_internal_note" value="1" @checked(old('is_internal_note'))> Note interne — ne pas notifier le demandeur</label>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-primary" type="submit"><i data-lucide="send"></i>Envoyer / enregistrer</button></div>
            </form>
        </section>
        @endif
    </div>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Demandeur</h2><p>Identité reçue depuis l’application ou le canal d’origine.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Type</span><strong>{{ $requesterType }}</strong></div>
                <div class="detail-row"><span>Nom</span><strong>{{ $requesterName }}</strong></div>
                <div class="detail-row"><span>E-mail</span><strong>{{ $requesterEmail ?: '—' }}</strong></div>
                <div class="detail-row"><span>Téléphone</span><strong>{{ $requesterPhone ?: '—' }}</strong></div>
                <div class="detail-row"><span>Origine</span><strong>{{ $sourceLabels[$ticket->source_app] ?? ucfirst(str_replace('_',' ',(string)$ticket->source_app)) }}</strong></div>
                <div class="detail-row"><span>Canal</span><strong>{{ ucfirst((string)$ticket->channel) }}</strong></div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Contexte OVANIE</h2><p>Informations métier liées au dossier sans duplication de données.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Commande</span><strong>{{ $ticket->order?->order_number ?: '—' }} @if($ticket->order)· {{ number_format((float)$ticket->order->total_amount,0,',',' ') }} FCFA @endif</strong></div>
                <div class="detail-row"><span>Boutique</span><strong>{{ $ticket->shop?->name ?: '—' }}</strong></div>
                <div class="detail-row"><span>Paiement</span><strong>{{ $ticket->payment?->reference ?: ($ticket->payment_id ? '#'.$ticket->payment_id : '—') }} @if($ticket->payment)· {{ $ticket->payment->status }}@endif</strong></div>
                <div class="detail-row"><span>Livraison</span><strong>{{ $ticket->shipment?->tracking_number ?: ($ticket->shipment_id ? '#'.$ticket->shipment_id : '—') }} @if($ticket->shipment)· {{ $ticket->shipment->status }}@endif</strong></div>
                <div class="detail-row"><span>Retour</span><strong>{{ $ticket->returnRequest?->product_name ?: ($ticket->return_id ? '#'.$ticket->return_id : '—') }}</strong></div>
                <div class="detail-row"><span>Litige</span><strong>{{ $ticket->dispute?->order_reference ?: ($ticket->dispute_id ? '#'.$ticket->dispute_id : '—') }}</strong></div>
                <div class="detail-row"><span>Incident</span><strong>{{ $ticket->deliveryIncident?->incident_type ?: ($ticket->delivery_incident_id ? '#'.$ticket->delivery_incident_id : '—') }}</strong></div>
                @foreach($ticket->contextLinks as $link)@if(!in_array($link->context_type,['order','shop','payment','shipment','return','dispute','delivery_incident'],true))<div class="detail-row"><span>{{ $contextLabels[$link->context_type] ?? ucfirst(str_replace('_',' ',$link->context_type)) }}</span><strong>#{{ $link->context_id }}{{ $link->is_primary ? ' · Principal' : '' }}</strong></div>@endif @endforeach
            </div>
        </section>

        @if(auth('admin')->user()?->hasStaffPermission('tickets.write') && !in_array($ticket->status,['resolved','closed','cancelled'],true))
        <section class="card">
            <div class="card-head"><div><h2>Action d’une équipe OVANIE</h2><p>À utiliser uniquement lorsqu’une action métier doit être réalisée hors du Support.</p></div></div>
            @if($activeHandoff)
                <div class="detail-list">
                    <div class="detail-row"><span>Service</span><strong>{{ $departmentLabels[$activeHandoff->target_department] ?? ucfirst($activeHandoff->target_department) }}</strong></div>
                    <div class="detail-row"><span>Statut</span><strong>{{ $handoffStatusLabels[$activeHandoff->status] ?? ucfirst(str_replace('_',' ',$activeHandoff->status)) }}</strong></div>
                    <div class="detail-row"><span>Action demandée</span><strong>{{ \Illuminate\Support\Str::limit($activeHandoff->reason,120) }}</strong></div>
                </div>
            @else
                <p class="record-sub" style="margin-bottom:14px">Le Support peut répondre directement. Si une intervention Logistique, Commerciale ou Administrative est nécessaire, transmettez le dossier avec une action attendue précise.</p>
                <button class="btn btn-primary" type="button" data-modal-open="#ticketHandoffModal"><i data-lucide="forward"></i>Transmettre à une équipe OVANIE</button>
            @endif
            @if($ticket->handoffs->isNotEmpty())
                <div class="support-section-title">Historique des transferts</div>
                <div class="detail-list">
                    @foreach($ticket->handoffs as $handoff)
                        <div class="detail-row"><span>{{ $departmentLabels[$handoff->target_department] ?? ucfirst($handoff->target_department) }}<span class="record-sub">{{ $handoff->reference }} · {{ $handoff->requested_at?->format('d/m/Y H:i') }}</span></span><strong>{{ $handoffStatusLabels[$handoff->status] ?? ucfirst(str_replace('_',' ',$handoff->status)) }}@if($handoff->notes)<span class="record-sub">{{ \Illuminate\Support\Str::limit($handoff->notes,100) }}</span>@endif</strong></div>
                    @endforeach
                </div>
            @endif
        </section>
        @endif

        @if(auth('admin')->user()?->hasStaffPermission('tickets.write'))
        <section class="card">
            <div class="card-head"><div><h2>Suivi Support</h2><p>Le dossier reste sous responsabilité Support. Modifiez uniquement son état de suivi, sa priorité et son conseiller.</p></div></div>
            <form method="POST" action="{{ route('support.tickets.update',$ticket) }}">@csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group"><label>État du suivi</label><select name="status">@foreach($statusLabels as $v=>$l)<option value="{{ $v }}" @selected(old('status',$ticket->status)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Priorité</label><select name="priority">@foreach($priorityLabels as $v=>$l)<option value="{{ $v }}" @selected(old('priority',$ticket->priority)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Catégorie</label><select name="category">@foreach(['general'=>'Général','account'=>'Compte','order'=>'Commande','payment'=>'Paiement','delivery'=>'Livraison','return'=>'Retour','dispute'=>'Litige','vendor'=>'Vendeur','product'=>'Produit','business'=>'Business'] as $v=>$l)<option value="{{ $v }}" @selected(old('category',$ticket->category)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Conseiller Support responsable</label><select name="assigned_to"><option value="">Non assigné</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)old('assigned_to',$ticket->assigned_to)===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Niveau d’escalade Support</label><select name="escalation_level">@for($i=0;$i<=5;$i++)<option value="{{ $i }}" @selected((int)old('escalation_level',$ticket->escalation_level)===$i)>Niveau {{ $i }}</option>@endfor</select></div>
                </div>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Mettre à jour le suivi</button></div>
            </form>
        </section>
        @endif
    </aside>
</div>

@if(auth('admin')->user()?->hasStaffPermission('tickets.write') && !in_array($ticket->status,['resolved','closed','cancelled'],true) && !$activeHandoff)
<div class="modal-backdrop" id="ticketHandoffModal"><div class="modal"><div class="modal-header"><div><h2>Transmettre à une équipe OVANIE</h2><p>Le Support garde la relation avec le demandeur. Le service choisi reçoit uniquement l’action métier à effectuer et le contexte lié au dossier.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div><form method="POST" action="{{ route('support.tickets.handoff',$ticket) }}">@csrf<div class="modal-body"><div class="form-grid"><div class="form-group"><label>Équipe OVANIE *</label><select name="target_department" required><option value="logistique">Logistique — livraison, mission, incident</option><option value="commercial">Commercial — OVANIE Business, prospect, opportunité</option><option value="administration">Administration — vérification ou décision interne</option></select></div><div class="form-group"><label>Priorité *</label><select name="severity" required><option value="normal">Normale</option><option value="high">Haute</option><option value="urgent">Urgente</option></select></div><div class="form-group full"><label>Action attendue du service *</label><textarea name="reason" required placeholder="Ex. Vérifier pourquoi la livraison n’a pas été reprogrammée et nous retourner le nouveau créneau afin que le Support informe le client."></textarea></div></div></div><div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit"><i data-lucide="forward"></i>Transmettre</button></div></form></div></div>
@endif
@endsection
