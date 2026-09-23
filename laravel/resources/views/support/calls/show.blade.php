@extends('layouts.staff')
@section('title', $call->reference.' | OVANIE Support')
@section('content')
<div class="page-header">
    <div class="page-header-main"><span class="page-header-icon"><i data-lucide="phone"></i></span><div><h1 class="page-title">{{ $call->reference }}</h1><p class="page-subtitle">{{ $call->direction === 'inbound' ? 'Appel entrant' : 'Appel sortant' }} · {{ $call->from_number }} → {{ $call->to_number }} · {{ $call->started_at?->format('d/m/Y H:i') }}</p></div></div>
    <div class="page-actions"><a class="btn" href="{{ route('support.calls.index') }}"><i data-lucide="arrow-left"></i>Retour aux appels</a>@if($call->conversation)<a class="btn btn-primary" href="{{ route('support.conversations.show',$call->conversation) }}"><i data-lucide="messages-square"></i>Conversation liée</a>@endif</div>
</div>

@if(!$services['telephony']['operational'])<div class="notice warning"><i data-lucide="circle-alert"></i><div>Le canal téléphonique est actuellement indisponible. Les informations déjà enregistrées restent consultables.</div></div>@endif

<div class="grid two-columns">
    <div class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Compte rendu de l’appel</h2><p>Résumé et transcription disponibles pour le conseiller.</p></div><span class="status {{ $call->status }}">{{ str_replace('_',' ',$call->status) }}</span></div>
            <div class="form-section"><div class="form-section-title"><span><i data-lucide="file-text"></i></span><div><h3>Résumé</h3><p>Points principaux de l’échange.</p></div></div><div class="timeline-body"><p>{{ $call->summary ?: 'Aucun résumé disponible.' }}</p></div></div>
            <div class="form-section"><div class="form-section-title"><span><i data-lucide="audio-lines"></i></span><div><h3>Transcription</h3><p>Contenu textuel de l’appel lorsqu’il est disponible.</p></div></div><div class="timeline-body"><p>{{ $call->transcript ?: 'Aucune transcription disponible.' }}</p></div>@if($call->recording_url)<a class="btn" href="{{ $call->recording_url }}" target="_blank" rel="noopener" style="margin-top:12px"><i data-lucide="play"></i>Écouter l’enregistrement</a>@endif</div>
        </section>
    </div>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card-head"><div><h2>Demandeur et contexte</h2><p>Données OVANIE reliées à cet appel.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Demandeur</span><strong>{{ $call->requester?->name ?: 'Non identifié' }}</strong></div>
                <div class="detail-row"><span>Numéro</span><strong>{{ $call->direction==='inbound' ? $call->from_number : $call->to_number }}</strong></div>
                <div class="detail-row"><span>Conseiller</span><strong>{{ $call->handler?->name ?: 'Non attribué' }}</strong></div>
                <div class="detail-row"><span>Durée</span><strong>{{ gmdate('H:i:s',$call->duration_seconds) }}</strong></div>
                <div class="detail-row"><span>Commande</span><strong>{{ $call->conversation?->order?->order_number ?: '—' }}</strong></div>
                <div class="detail-row"><span>Paiement</span><strong>{{ $call->conversation?->payment ? '#'.$call->conversation->payment->id.' · '.$call->conversation->payment->status : '—' }}</strong></div>
                <div class="detail-row"><span>Livraison</span><strong>{{ $call->conversation?->shipment?->tracking_number ?: '—' }}</strong></div>
                <div class="detail-row"><span>Dossier Support</span><strong>{{ $call->ticket?->reference ?: $call->conversation?->ticket?->reference ?: '—' }}</strong></div>
            </div>
        </section>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
        <section class="card"><div class="card-head"><div><h2>Actions</h2><p>Planifiez un rappel si la personne doit être recontactée.</p></div></div><button class="btn btn-primary" type="button" data-modal-open="#callCallbackModal"><i data-lucide="calendar-phone"></i>Planifier un rappel</button>@if($telephonyConfigured && auth('admin')->user()->hasStaffPermission('support.ai.calls.transfer') && in_array($call->status,['waiting','ringing','in_progress','waiting_transfer'],true))<button class="btn" type="button" data-modal-open="#callTransferModal" style="margin-left:6px"><i data-lucide="phone-forwarded"></i>Transférer l’appel</button>@endif</section>
        @endif

        @if(auth('admin')->user()->hasStaffPermission('support.ai.supervision'))
        <details class="card"><summary style="cursor:pointer;font-size:11px;font-weight:800;color:#516783">Détails techniques de l’appel</summary><div class="detail-list" style="margin-top:12px"><div class="detail-row"><span>Fournisseur</span><strong>{{ $call->provider ?: '—' }}</strong></div><div class="detail-row"><span>ID fournisseur</span><strong>{{ $call->provider_call_id ?: '—' }}</strong></div><div class="detail-row"><span>Événements reçus</span><strong>{{ $call->events->count() }}</strong></div></div></details>
        @endif
    </aside>
</div>

@if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
<div class="modal-backdrop" id="callCallbackModal"><div class="modal"><div class="modal-header"><div><h2>Planifier un rappel</h2><p>Crée une tâche de suivi dans l’onglet « À rappeler ».</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div><form method="POST" action="{{ route('support.calls.callback',$call) }}">@csrf<div class="modal-body"><div class="form-grid"><div class="form-group"><label>Téléphone *</label><input name="phone" required value="{{ $call->from_number ?: $call->to_number }}"></div><div class="form-group"><label>Date souhaitée</label><input type="datetime-local" name="preferred_at"></div><div class="form-group full"><label>Motif</label><textarea name="reason">{{ $call->missed_reason ?: $call->summary }}</textarea></div></div></div><div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit">Planifier</button></div></form></div></div>
@endif

@if($telephonyConfigured && auth('admin')->user()->hasStaffPermission('support.ai.calls.transfer') && in_array($call->status,['waiting','ringing','in_progress','waiting_transfer'],true))
<div class="modal-backdrop" id="callTransferModal"><div class="modal"><div class="modal-header"><div><h2>Transférer l’appel</h2><p>Redirige l’appel actif vers le numéro indiqué.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div><form method="POST" action="{{ route('support.calls.transfer',$call) }}">@csrf<div class="modal-body"><div class="form-group"><label>Numéro de destination *</label><input name="destination" required value="{{ config('support_ai.telephony.human_transfer_number') }}" placeholder="+225..."></div></div><div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit">Transférer</button></div></form></div></div>
@endif
@endsection
