@extends('layouts.staff')
@section('title', $ticket->reference . ' | Support OVANIE')
@section('content')
@php
    $requesterName = $ticket->requester?->name ?: $ticket->requester_name ?: 'Demandeur non identifié';
    $requesterEmail = $ticket->requester?->email ?: $ticket->requester_email;
    $requesterPhone = $ticket->requester?->phone ?: $ticket->requester_phone;
@endphp
<div class="page-header">
    <div><h1 class="page-title">{{ $ticket->reference }} — {{ $ticket->subject }}</h1><p class="page-subtitle">Créé le {{ $ticket->created_at?->format('d/m/Y à H:i') }} par {{ $ticket->creator?->name ?: 'le système' }}.</p></div>
    <div class="page-actions"><a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="arrow-left"></i>Retour</a><span class="status {{ $ticket->priority }}">{{ $ticket->priority }}</span><span class="status {{ $ticket->status }}">{{ str_replace('_',' ',$ticket->status) }}</span></div>
</div>
<div class="grid two-columns">
    <div class="grid">
        <section class="card">
            <div class="card-head"><div><h2>Conversation et historique</h2><p>Les notes internes restent réservées à l’équipe OVANIE.</p></div></div>
            <div class="timeline">
                @forelse($ticket->messages as $message)
                    <article class="timeline-item">
                        <span class="timeline-dot">{{ mb_strtoupper(mb_substr($message->author?->name ?: $message->author_type,0,2)) }}</span>
                        <div class="timeline-body {{ $message->is_internal_note ? 'internal' : '' }}">
                            <div class="timeline-meta"><strong>{{ $message->author?->name ?: ucfirst($message->author_type) }} {{ $message->is_internal_note ? '· Note interne' : '' }}</strong><span>{{ $message->created_at?->format('d/m/Y H:i') }}</span></div>
                            <p>{{ $message->body }}</p>
                        </div>
                    </article>
                @empty
                    <div class="empty"><i data-lucide="messages-square"></i><div>Aucun message enregistré.</div></div>
                @endforelse
            </div>
        </section>
        @if(auth()->user()->hasStaffPermission('tickets.write'))
        <section class="card">
            <div class="card-head"><div><h2>Ajouter une réponse</h2><p>Utilisez une note interne pour les informations qui ne doivent pas être communiquées au demandeur.</p></div></div>
            <form method="POST" action="{{ route('support.tickets.reply',$ticket) }}">@csrf
                <div class="form-group"><label>Message *</label><textarea name="body" required placeholder="Écrivez la réponse ou la note de suivi…">{{ old('body') }}</textarea></div>
                <label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-size:10px;font-weight:750;color:#445b7a"><input type="checkbox" name="is_internal_note" value="1" @checked(old('is_internal_note'))> Enregistrer comme note interne</label>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-primary" type="submit"><i data-lucide="send"></i>Enregistrer</button></div>
            </form>
        </section>
        @endif
    </div>
    <aside class="grid">
        @if(auth()->user()->hasStaffPermission('tickets.write'))
        <section class="card">
            <div class="card-head"><div><h2>Gestion du ticket</h2><p>Assignation, priorité, statut et escalade.</p></div></div>
            <form method="POST" action="{{ route('support.tickets.update',$ticket) }}">@csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group"><label>Statut</label><select name="status">@foreach(['open'=>'Ouvert','in_progress'=>'En cours','waiting_customer'=>'Attente client','waiting_internal'=>'Attente interne','resolved'=>'Résolu','closed'=>'Fermé','cancelled'=>'Annulé'] as $v=>$l)<option value="{{ $v }}" @selected(old('status',$ticket->status)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Priorité</label><select name="priority">@foreach(['low'=>'Faible','normal'=>'Normale','high'=>'Haute','urgent'=>'Urgente'] as $v=>$l)<option value="{{ $v }}" @selected(old('priority',$ticket->priority)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Catégorie</label><select name="category">@foreach(['general'=>'Général','account'=>'Compte','order'=>'Commande','payment'=>'Paiement','delivery'=>'Livraison','return'=>'Retour','dispute'=>'Litige','vendor'=>'Vendeur','product'=>'Produit','business'=>'Business'] as $v=>$l)<option value="{{ $v }}" @selected(old('category',$ticket->category)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Équipe</label><select name="team">@foreach(['support'=>'Support général','payments'=>'Paiements','logistics'=>'Logistique','returns'=>'Retours','disputes'=>'Litiges','vendors'=>'Vendeurs','business'=>'OVANIE Pro'] as $v=>$l)<option value="{{ $v }}" @selected(old('team',$ticket->team)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Agent assigné</label><select name="assigned_to"><option value="">Non assigné</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)old('assigned_to',$ticket->assigned_to)===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Niveau d’escalade</label><select name="escalation_level">@for($i=0;$i<=5;$i++)<option value="{{ $i }}" @selected((int)old('escalation_level',$ticket->escalation_level)===$i)>Niveau {{ $i }}</option>@endfor</select></div>
                </div>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Mettre à jour</button></div>
            </form>
        </section>
        @endif
        <section class="card">
            <div class="card-head"><div><h2>Demandeur</h2><p>Coordonnées issues du compte ou saisies au ticket.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Nom</span><strong>{{ $requesterName }}</strong></div>
                <div class="detail-row"><span>E-mail</span><strong>{{ $requesterEmail ?: 'Non renseigné' }}</strong></div>
                <div class="detail-row"><span>Téléphone</span><strong>{{ $requesterPhone ?: 'Non renseigné' }}</strong></div>
                <div class="detail-row"><span>Canal</span><strong>{{ ucfirst($ticket->channel) }}</strong></div>
                <div class="detail-row"><span>SLA</span><strong class="{{ $ticket->is_overdue ? 'status urgent' : '' }}">{{ $ticket->sla_due_at?->format('d/m/Y H:i') ?: 'Non défini' }}</strong></div>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><div><h2>Données liées</h2><p>Relations directes vers les modules de la plateforme.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Commande</span><strong>{{ $ticket->order?->order_number ?: '—' }} @if($ticket->order)· {{ number_format((float)$ticket->order->total_amount,0,',',' ') }} FCFA @endif</strong></div>
                <div class="detail-row"><span>Boutique</span><strong>{{ $ticket->shop?->name ?: '—' }}</strong></div>
                <div class="detail-row"><span>Paiement</span><strong>{{ $ticket->payment?->reference ?: ($ticket->payment_id ? '#'.$ticket->payment_id : '—') }} @if($ticket->payment)· {{ $ticket->payment->status }}@endif</strong></div>
                <div class="detail-row"><span>Livraison</span><strong>{{ $ticket->shipment?->tracking_number ?: ($ticket->shipment_id ? '#'.$ticket->shipment_id : '—') }} @if($ticket->shipment)· {{ $ticket->shipment->status }}@endif</strong></div>
                <div class="detail-row"><span>Retour</span><strong>{{ $ticket->returnRequest?->product_name ?: ($ticket->return_id ? '#'.$ticket->return_id : '—') }}</strong></div>
                <div class="detail-row"><span>Litige</span><strong>{{ $ticket->dispute?->order_reference ?: ($ticket->dispute_id ? '#'.$ticket->dispute_id : '—') }}</strong></div>
                <div class="detail-row"><span>Incident</span><strong>{{ $ticket->deliveryIncident?->incident_type ?: ($ticket->delivery_incident_id ? '#'.$ticket->delivery_incident_id : '—') }}</strong></div>
                <div class="detail-row"><span>Message entrant</span><strong>{{ $ticket->submission?->email ?: ($ticket->submission_id ? '#'.$ticket->submission_id : '—') }}</strong></div>
            </div>
        </section>
    </aside>
</div>
@endsection
