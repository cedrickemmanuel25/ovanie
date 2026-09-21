@extends('layouts.staff')
@section('title', 'Conversations IA | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Conversations IA</h1><p class="page-subtitle">Chaque conversation est reliée automatiquement au compte, à la commande, au paiement et à la livraison lorsqu’une correspondance réelle est trouvée.</p></div>
</div>

@if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond'))
<section class="card" style="margin-bottom:14px">
    <div class="card-head"><div><h2>Démarrer une conversation réelle</h2><p>Les références servent uniquement à retrouver les enregistrements existants. Aucune copie de commande ou de paiement n’est créée.</p></div></div>
    <form method="POST" action="{{ route('support.conversations.store') }}">@csrf
        <div class="form-grid">
            <div class="form-group"><label>ID du compte (facultatif)</label><input type="number" min="1" name="requester_user_id" value="{{ old('requester_user_id') }}" placeholder="Recherche prioritaire par compte"></div>
            <div class="form-group"><label>Nom du demandeur</label><input name="requester_name" value="{{ old('requester_name') }}"></div>
            <div class="form-group"><label>Adresse e-mail</label><input type="email" name="requester_email" value="{{ old('requester_email') }}"></div>
            <div class="form-group"><label>Téléphone</label><input name="requester_phone" value="{{ old('requester_phone') }}"></div>
            <div class="form-group"><label>Canal</label>
                <select name="channel" required>
                    <option value="internal" @selected(old('channel')==='internal')>Saisie interne</option>
                    <option value="chat" @selected(old('channel','chat')==='chat')>Chat web</option>
                    @if($services['telephony']['operational'])<option value="phone" @selected(old('channel')==='phone')>Téléphone</option>@endif
                    @if($services['whatsapp']['operational'])<option value="whatsapp" @selected(old('channel')==='whatsapp')>WhatsApp</option>@endif
                    @if($services['email']['operational'])<option value="email" @selected(old('channel')==='email')>E-mail</option>@endif
                </select>
            </div>
            <div class="form-group"><label>Référence commande</label><input name="order_reference" value="{{ old('order_reference') }}" placeholder="N° commande ou facture"></div>
            <div class="form-group"><label>Référence paiement</label><input name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Référence transaction"></div>
            <div class="form-group"><label>Référence livraison</label><input name="tracking_reference" value="{{ old('tracking_reference') }}" placeholder="Numéro de suivi"></div>
            <div class="form-group full"><label>Objet</label><input name="subject" value="{{ old('subject') }}" required placeholder="Ex. Retard de livraison sur la commande"></div>
            <div class="form-group full"><label>Message initial</label><textarea name="message" required placeholder="Décrivez la demande telle qu’elle a été formulée.">{{ old('message') }}</textarea></div>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:12px"><i data-lucide="bot"></i>Créer, relier et faire répondre l’IA</button>
    </form>
</section>
@endif

<form class="filters" method="GET">
    <input name="q" value="{{ request('q') }}" placeholder="Compte, commande, livraison, ticket ou sujet">
    <select name="status"><option value="">Tous les statuts</option>@foreach(['active','waiting_human','human','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select>
    <select name="channel"><option value="">Tous les canaux</option>@foreach(['chat','phone','whatsapp','email','internal'] as $channel)<option value="{{ $channel }}" @selected(request('channel')===$channel)>{{ $channel }}</option>@endforeach</select>
    <select name="ai_agent_id"><option value="">Tous les agents IA</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('ai_agent_id')===(string)$agent->id)>{{ $agent->name }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="search"></i>Filtrer</button>
</form>

<section class="card">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Conversation</th><th>Compte réel</th><th>Dossiers reliés</th><th>Agent / canal</th><th>Statut</th><th>Messages</th><th>Dernière activité</th></tr></thead><tbody>
    @forelse($conversations as $conversation)
        <tr>
            <td><a class="record-title" href="{{ route('support.conversations.show', $conversation) }}">#{{ $conversation->id }} · {{ \Illuminate\Support\Str::limit($conversation->subject ?: 'Assistance OVANIE', 42) }}</a><span class="record-sub">Priorité {{ $conversation->priority }}</span></td>
            <td>{{ $conversation->requester?->name ?: $conversation->requester_name ?: 'Non identifié' }}<span class="record-sub">{{ $conversation->requester_user_id ? 'Compte #'.$conversation->requester_user_id.' · '.$conversation->requester_match_method : 'Aucun compte correspondant' }}</span></td>
            <td>
                @if($conversation->order)<strong>{{ $conversation->order->order_number }}</strong>@endif
                <span class="record-sub">
                    {{ $conversation->shipment?->tracking_number ? 'Livraison '.$conversation->shipment->tracking_number : '' }}
                    {{ $conversation->commercialLead ? ' · Opportunité '.$conversation->commercialLead->reference : '' }}
                    {{ $conversation->deliveryIncident ? ' · Incident #'.$conversation->deliveryIncident->id : '' }}
                    {{ !$conversation->order && !$conversation->shipment && !$conversation->commercialLead && !$conversation->deliveryIncident ? 'Aucun dossier relié' : '' }}
                </span>
            </td>
            <td>{{ $conversation->aiAgent?->name ?: 'Non attribué' }}<span class="record-sub">{{ $conversation->channel }}</span></td>
            <td><span class="status {{ $conversation->status }}">{{ str_replace('_',' ',$conversation->status) }}</span></td>
            <td>{{ $conversation->messages_count }}</td>
            <td>{{ $conversation->last_message_at?->diffForHumans() }}</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty"><i data-lucide="messages-square"></i><div>Aucune conversation réelle enregistrée.</div></div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination-row"><span>{{ $conversations->total() }} conversation(s)</span><div>{{ $conversations->links() }}</div></div>
</section>
@endsection
