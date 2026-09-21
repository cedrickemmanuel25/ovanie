@extends('layouts.staff')
@section('title', 'Centre d’appels Support | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Centre d’appels Support</h1><p class="page-subtitle">Appels réels reçus du fournisseur, identification automatique des comptes, transcriptions, incidents, transferts et rappels.</p></div>
</div>

<section class="card" style="margin-bottom:14px">
    <div class="card-head"><div><h2>État de la téléphonie</h2><p>La plateforme ne crée aucun appel fictif.</p></div><span class="status {{ $services['telephony']['operational'] ? 'active' : 'unavailable' }}">{{ $services['telephony']['operational'] ? 'Opérationnelle' : 'Indisponible' }}</span></div>
    <div class="detail-list">
        <div class="detail-row"><span>Fournisseur</span><strong>{{ $services['telephony']['provider'] ?: 'Aucun' }}</strong></div>
        <div class="detail-row"><span>Configuration</span><strong>{{ $services['telephony']['message'] }}</strong></div>
        <div class="detail-row"><span>Numéro sortant</span><strong>{{ config('support_ai.telephony.from_number') ?: 'Non configuré' }}</strong></div>
        <div class="detail-row"><span>Transfert humain</span><strong>{{ config('support_ai.telephony.human_transfer_number') ?: 'Non configuré' }}</strong></div>
    </div>
</section>

@if(!$telephonyConfigured)
<div class="alert alert-error">{{ $services['telephony']['message'] }} Aucun appel réel ne peut être émis ou reçu correctement tant que le fournisseur et l’URL publique HTTPS ne sont pas opérationnels.</div>
@endif

@if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
<div class="grid two-columns" style="margin-bottom:14px">
    <section class="card">
        <div class="card-head"><div><h2>Lancer un appel sortant réel</h2><p>L’appel n’est enregistré en base qu’après acceptation par le fournisseur.</p></div></div>
        <form method="POST" action="{{ route('support.calls.outbound') }}">@csrf
            <div class="form-grid">
                <div class="form-group"><label>Numéro à appeler</label><input name="to_number" required value="{{ old('to_number') }}" placeholder="Ex. +2250700000000"></div>
                <div class="form-group"><label>ID du compte (facultatif)</label><input type="number" min="1" name="requester_user_id" value="{{ old('requester_user_id') }}"></div>
                <div class="form-group"><label>Nom du demandeur</label><input name="requester_name" value="{{ old('requester_name') }}"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="requester_email" value="{{ old('requester_email') }}"></div>
                <div class="form-group"><label>Référence commande</label><input name="order_reference" value="{{ old('order_reference') }}"></div>
                <div class="form-group"><label>Référence paiement</label><input name="payment_reference" value="{{ old('payment_reference') }}"></div>
                <div class="form-group"><label>Référence livraison</label><input name="tracking_reference" value="{{ old('tracking_reference') }}"></div>
                <div class="form-group"><label>Objet de l’appel</label><input name="subject" value="{{ old('subject') }}" placeholder="Ex. Rappel concernant une livraison"></div>
            </div>
            <button class="btn btn-primary" type="submit" style="margin-top:12px" @disabled(!$telephonyConfigured)><i data-lucide="phone-outgoing"></i>Lancer l’appel</button>
        </form>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Créer une demande de rappel</h2><p>Le rappel reste une tâche réelle même lorsque la téléphonie n’est pas encore connectée.</p></div></div>
        <form method="POST" action="{{ route('support.callbacks.store') }}">@csrf
            <div class="form-grid">
                <div class="form-group"><label>Téléphone</label><input name="phone" required></div>
                <div class="form-group"><label>ID du compte</label><input type="number" min="1" name="requester_user_id"></div>
                <div class="form-group"><label>Nom</label><input name="requester_name"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
                <div class="form-group full"><label>Date souhaitée</label><input type="datetime-local" name="preferred_at"></div>
                <div class="form-group full"><label>Motif</label><textarea name="reason"></textarea></div>
            </div>
            <button class="btn" type="submit" style="margin-top:12px"><i data-lucide="phone-call"></i>Enregistrer le rappel</button>
        </form>
    </section>
</div>
@endif

<form class="filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Référence, numéro, compte, commande ou livraison"><select name="status"><option value="">Tous les statuts</option>@foreach(['queued','waiting','ringing','in_progress','waiting_transfer','transferred','completed','missed','failed'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select><select name="direction"><option value="">Toutes les directions</option><option value="inbound" @selected(request('direction')==='inbound')>Entrant</option><option value="outbound" @selected(request('direction')==='outbound')>Sortant</option></select><button class="btn" type="submit"><i data-lucide="search"></i>Filtrer</button></form>

<section class="card">
<div class="table-wrap"><table class="data-table"><thead><tr><th>Appel</th><th>Numéro</th><th>Compte réel</th><th>Dossier lié</th><th>Agent</th><th>Statut</th><th>Durée</th><th>Début</th></tr></thead><tbody>
@forelse($calls as $call)
<tr>
    <td><a class="record-title" href="{{ route('support.calls.show',$call) }}">{{ $call->reference }}</a><span class="record-sub">{{ $call->provider_call_id ?: 'Référence fournisseur en attente' }} · {{ $call->direction }}</span></td>
    <td>{{ $call->direction==='inbound' ? $call->from_number : $call->to_number }}</td>
    <td>{{ $call->requester?->name ?: 'Non identifié' }}<span class="record-sub">{{ $call->requester_user_id ? 'Compte #'.$call->requester_user_id : 'Aucune correspondance' }}</span></td>
    <td>{{ $call->conversation?->order?->order_number ?: $call->conversation?->shipment?->tracking_number ?: $call->ticket?->reference ?: '—' }}</td>
    <td>{{ $call->handler?->name ?: $call->aiAgent?->name ?: 'Non attribué' }}</td>
    <td><span class="status {{ $call->status }}">{{ str_replace('_',' ',$call->status) }}</span></td>
    <td>{{ gmdate('H:i:s',$call->duration_seconds) }}</td>
    <td>{{ $call->started_at?->format('d/m/Y H:i') }}</td>
</tr>
@empty<tr><td colspan="8"><div class="empty"><i data-lucide="phone-off"></i><div>Aucun appel réel enregistré.</div></div></td></tr>@endforelse
</tbody></table></div>
<div class="pagination-row"><span>{{ $calls->total() }} appel(s)</span><div>{{ $calls->links() }}</div></div>
</section>
@endsection
