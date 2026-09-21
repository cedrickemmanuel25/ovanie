@extends('layouts.staff')
@section('title', $call->reference.' | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">{{ $call->reference }}</h1><p class="page-subtitle">{{ ucfirst($call->direction) }} · {{ $call->from_number }} → {{ $call->to_number }} · {{ $call->started_at?->format('d/m/Y H:i') }}</p></div>
    <div class="page-actions">@if($call->conversation)<a class="btn" href="{{ route('support.conversations.show',$call->conversation) }}"><i data-lucide="messages-square"></i>Voir la conversation</a>@endif</div>
</div>

@if(!$services['telephony']['operational'])
<div class="alert alert-error">{{ $services['telephony']['message'] }}</div>
@endif

<div class="grid two-columns">
<section class="card">
    <div class="card-head"><div><h2>Transcription et résumé</h2><p>Ces contenus proviennent uniquement des événements réels du fournisseur et des réponses réellement générées.</p></div><span class="status {{ $call->status }}">{{ str_replace('_',' ',$call->status) }}</span></div>
    <div class="form-group"><label>Résumé</label><div class="timeline-body"><p>{{ $call->summary ?: 'Aucun résumé réel disponible.' }}</p></div></div>
    <div class="form-group" style="margin-top:14px"><label>Transcription</label><div class="timeline-body"><p>{{ $call->transcript ?: 'Aucune transcription réelle disponible.' }}</p></div></div>
    @if($call->recording_url)<a class="btn" href="{{ $call->recording_url }}" target="_blank" rel="noopener" style="margin-top:14px"><i data-lucide="audio-lines"></i>Ouvrir l’enregistrement fournisseur</a>@endif
</section>

<aside class="grid" style="align-content:start">
    <section class="card">
        <div class="card-head"><div><h2>Détails et relations réelles</h2></div></div>
        <div class="detail-list">
            <div class="detail-row"><span>Fournisseur</span><strong>{{ $call->provider }}</strong></div>
            <div class="detail-row"><span>ID fournisseur</span><strong>{{ $call->provider_call_id ?: '—' }}</strong></div>
            <div class="detail-row"><span>Compte</span><strong>{{ $call->requester ? '#'.$call->requester->id.' · '.$call->requester->name : 'Non identifié' }}</strong></div>
            <div class="detail-row"><span>Agent IA</span><strong>{{ $call->aiAgent?->name ?: '—' }}</strong></div>
            <div class="detail-row"><span>Agent humain</span><strong>{{ $call->handler?->name ?: '—' }}</strong></div>
            <div class="detail-row"><span>Durée</span><strong>{{ gmdate('H:i:s',$call->duration_seconds) }}</strong></div>
            <div class="detail-row"><span>Commande</span><strong>{{ $call->conversation?->order?->order_number ?: '—' }}</strong></div>
            <div class="detail-row"><span>Paiement</span><strong>{{ $call->conversation?->payment ? '#'.$call->conversation->payment->id.' · '.$call->conversation->payment->status : '—' }}</strong></div>
            <div class="detail-row"><span>Livraison</span><strong>{{ $call->conversation?->shipment?->tracking_number ?: '—' }}</strong></div>
            <div class="detail-row"><span>Incident</span><strong>{{ $call->conversation?->deliveryIncident ? '#'.$call->conversation->deliveryIncident->id.' · '.$call->conversation->deliveryIncident->status : '—' }}</strong></div>
            <div class="detail-row"><span>Opportunité</span><strong>{{ $call->conversation?->commercialLead?->reference ?: '—' }}</strong></div>
            <div class="detail-row"><span>Ticket</span><strong>{{ $call->ticket?->reference ?: $call->conversation?->ticket?->reference ?: '—' }}</strong></div>
        </div>
    </section>

    @if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
    <section class="card"><div class="card-head"><div><h2>Demande de rappel</h2></div></div><form method="POST" action="{{ route('support.calls.callback',$call) }}">@csrf<div class="form-group"><label>Téléphone</label><input name="phone" required value="{{ $call->from_number ?: $call->to_number }}"></div><div class="form-group" style="margin-top:10px"><label>Motif</label><textarea name="reason">{{ $call->missed_reason ?: $call->summary }}</textarea></div><button class="btn" type="submit" style="margin-top:10px"><i data-lucide="phone-call"></i>Créer le rappel</button></form></section>
    @endif

    @if($telephonyConfigured && auth('admin')->user()->hasStaffPermission('support.ai.calls.transfer') && in_array($call->status,['waiting','ringing','in_progress','waiting_transfer'],true))
    <section class="card">
        <div class="card-head"><div><h2>Transférer l’appel réel</h2><p>Le fournisseur redirigera l’appel actif vers ce numéro.</p></div></div>
        <form method="POST" action="{{ route('support.calls.transfer',$call) }}">@csrf
            <div class="form-group"><label>Numéro humain</label><input name="destination" required value="{{ config('support_ai.telephony.human_transfer_number') }}" placeholder="Ex. +2250100000000"></div>
            <button class="btn btn-orange" type="submit" style="margin-top:10px"><i data-lucide="phone-forwarded"></i>Transférer</button>
        </form>
    </section>
    @endif
</aside>
</div>

<section class="card" style="margin-top:14px"><div class="card-head"><div><h2>Journal des événements fournisseur</h2><p>Chaque ligne correspond à un webhook réellement reçu.</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Événement</th><th>ID fournisseur</th><th>Date</th></tr></thead><tbody>@forelse($call->events as $event)<tr><td>{{ $event->event }}</td><td>{{ $event->provider_event_id ?: '—' }}</td><td>{{ $event->occurred_at?->format('d/m/Y H:i:s') }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Aucun événement réel enregistré.</div></td></tr>@endforelse</tbody></table></div></section>
@endsection
