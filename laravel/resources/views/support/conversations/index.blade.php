@extends('layouts.staff')
@section('title', 'Boîte de réception | OVANIE Support')
@section('content')
<section class="workspace-banner">
    <div>
        <span class="eyebrow"><i data-lucide="messages-square"></i> Service client</span>
        <h1>Boîte de réception</h1>
        <p>Les demandes envoyées depuis les applications Client, Vendeur, Commercial et Livreur, ainsi que le Chat, WhatsApp, l’e-mail et les appels, sont centralisées ici. Les assistants automatiques répondent en premier lorsqu’ils le peuvent ; les demandes qui nécessitent un humain apparaissent dans « À traiter ».</p>
    </div>
    @if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond'))
        <div class="workspace-actions"><button class="btn" type="button" data-modal-open="#newConversationModal"><i data-lucide="message-circle-plus"></i>Démarrer un suivi</button></div>
    @endif
</section>

<nav class="tabs" aria-label="Filtres de la boîte de réception">
    <a class="tab {{ $scope === 'waiting' ? 'active' : '' }}" href="{{ route('support.conversations.index', ['scope'=>'waiting']) }}">À traiter <span class="tab-count">{{ $conversationStats['waiting'] }}</span></a>
    <a class="tab {{ $scope === 'mine' ? 'active' : '' }}" href="{{ route('support.conversations.index', ['scope'=>'mine']) }}">Mes conversations <span class="tab-count">{{ $conversationStats['mine'] }}</span></a>
    <a class="tab {{ $scope === 'open' ? 'active' : '' }}" href="{{ route('support.conversations.index', ['scope'=>'open']) }}">En cours <span class="tab-count">{{ $conversationStats['open'] }}</span></a>
    <a class="tab {{ $scope === 'closed' ? 'active' : '' }}" href="{{ route('support.conversations.index', ['scope'=>'closed']) }}">Terminées <span class="tab-count">{{ $conversationStats['closed'] }}</span></a>
    <a class="tab {{ $scope === 'all' ? 'active' : '' }}" href="{{ route('support.conversations.index', ['scope'=>'all']) }}">Toutes <span class="tab-count">{{ $conversationStats['all'] }}</span></a>
</nav>

<div class="channel-summary" style="margin-bottom:16px">
    <span class="channel-pill ok"><span class="channel-dot"></span>Applications OVANIE</span>
    <span class="channel-pill"><span class="channel-dot"></span>Chat web</span>
    <span class="channel-pill"><span class="channel-dot"></span>WhatsApp</span>
    <span class="channel-pill"><span class="channel-dot"></span>E-mail</span>
    <span class="channel-pill"><span class="channel-dot"></span>Téléphone</span>
</div>

<div class="toolbar">
    <form class="filters" method="GET">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, téléphone, commande, livraison, ticket ou sujet">
        <select name="channel">
            <option value="">Tous les canaux</option>
            @foreach(['chat'=>'Chat','phone'=>'Téléphone','whatsapp'=>'WhatsApp','email'=>'E-mail','internal'=>'Interne'] as $channel=>$label)
                <option value="{{ $channel }}" @selected(request('channel')===$channel)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="assigned_to">
            <option value="">Tous les conseillers</option>
            @foreach($humanAgents as $agent)
                <option value="{{ $agent->id }}" @selected((string)request('assigned_to')===(string)$agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
        @if(request('q') || request('channel') || request('assigned_to'))<a class="btn" href="{{ route('support.conversations.index', ['scope'=>$scope]) }}">Effacer</a>@endif
    </form>
</div>

<div class="notice" style="margin-bottom:16px"><i data-lucide="link-2"></i><div><strong>Comment le dossier est lié :</strong> une conversation reste un simple échange tant que l’assistant peut répondre seul. Dès qu’un conseiller prend la conversation en charge ou qu’un transfert vers une équipe OVANIE est nécessaire, un <strong>Dossier Support SUP-…</strong> est créé et lié automatiquement. Les références commande, paiement, livraison, boutique ou incident sont également affichées lorsqu’elles ont été reconnues.</div></div>

<section class="card">
    <div class="card-head">
        <div><h2>{{ $scope === 'waiting' ? 'Demandes nécessitant une intervention humaine' : ($scope === 'closed' ? 'Conversations terminées' : 'Conversations') }}</h2><p>Cliquez sur une ligne pour consulter l’historique complet et le contexte OVANIE associé.</p></div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Demandeur</th><th>Demande</th><th>Contexte OVANIE</th><th>Canal</th><th>Prise en charge</th><th>Statut</th><th>Dernière activité</th></tr></thead>
            <tbody>
            @forelse($conversations as $conversation)
                @php
                    $requesterName = $conversation->requesterProfile?->name ?: $conversation->requester?->name ?: $conversation->requester_name ?: 'Non identifié';
                    $requesterType = $conversation->requesterProfile?->type_label ?: ($conversation->requester_user_id ? 'Compte OVANIE' : 'Visiteur');
                    $contextItems = collect();
                    if ($conversation->ticket) $contextItems->push(['label'=>'Dossier', 'value'=>$conversation->ticket->reference, 'url'=>route('support.tickets.show',$conversation->ticket)]);
                    if ($conversation->order?->order_number) $contextItems->push(['label'=>'Commande', 'value'=>$conversation->order->order_number, 'url'=>null]);
                    if ($conversation->payment?->reference) $contextItems->push(['label'=>'Paiement', 'value'=>$conversation->payment->reference, 'url'=>null]);
                    if ($conversation->shipment?->tracking_number) $contextItems->push(['label'=>'Livraison', 'value'=>$conversation->shipment->tracking_number, 'url'=>null]);
                    if ($conversation->shop) $contextItems->push(['label'=>'Boutique', 'value'=>$conversation->shop->name ?: '#'.$conversation->shop->id, 'url'=>null]);
                    if ($conversation->returnRequest) $contextItems->push(['label'=>'Retour', 'value'=>'#'.$conversation->returnRequest->id, 'url'=>null]);
                    if ($conversation->dispute) $contextItems->push(['label'=>'Litige', 'value'=>'#'.$conversation->dispute->id, 'url'=>null]);
                    if ($conversation->commercialLead?->reference) $contextItems->push(['label'=>'Commercial', 'value'=>$conversation->commercialLead->reference, 'url'=>null]);
                    if ($conversation->deliveryIncident) $contextItems->push(['label'=>'Incident', 'value'=>'#'.$conversation->deliveryIncident->id, 'url'=>null]);
                    $genericContextLabels = [
                        'order'=>'Commande','payment'=>'Paiement','shipment'=>'Livraison','shop'=>'Boutique','return'=>'Retour',
                        'dispute'=>'Litige','delivery_incident'=>'Incident','vendor_payout'=>'Versement vendeur',
                        'delivery_assignment'=>'Mission livreur','mission'=>'Mission livreur','driver'=>'Livreur partenaire',
                        'product'=>'Produit','client'=>'Client','commercial_lead'=>'Suivi commercial'
                    ];
                    foreach($conversation->ticket?->contextLinks ?? [] as $link) {
                        $label = $genericContextLabels[$link->context_type] ?? ucfirst(str_replace('_',' ',$link->context_type));
                        if (!$contextItems->contains(fn($item) => $item['label'] === $label)) {
                            $contextItems->push(['label'=>$label, 'value'=>'#'.$link->context_id, 'url'=>null]);
                        }
                    }
                    $contextHelp = $conversation->ticket
                        ? 'Dossier Support lié automatiquement'
                        : ($contextItems->isNotEmpty()
                            ? 'Données OVANIE reconnues · dossier créé lors de la prise en charge humaine'
                            : ($conversation->requires_human
                                ? 'Intervention humaine demandée · dossier créé à la prise en charge'
                                : 'Conversation simple · aucun dossier nécessaire pour le moment'));
                    $statusLabels = [
                        'active' => 'En cours',
                        'waiting_human' => 'À traiter',
                        'human' => 'Pris en charge',
                        'resolved' => 'Terminée',
                        'closed' => 'Archivée',
                    ];
                    $sourceLabels = [
                        'client_mobile' => 'Application Client',
                        'vendor_mobile' => 'Application Vendeur',
                        'commercial_mobile' => 'Application Commercial',
                        'driver_mobile' => 'Application Livreur',
                        'website' => 'Site OVANIE',
                        'web' => 'Site OVANIE',
                        'whatsapp' => 'WhatsApp',
                        'phone' => 'Téléphone',
                        'support_web' => 'Support OVANIE',
                        'internal' => 'Interne',
                    ];
                    $channelLabels = [
                        'chat' => 'Chat',
                        'phone' => 'Téléphone',
                        'whatsapp' => 'WhatsApp',
                        'email' => 'E-mail',
                        'internal' => 'Interne',
                    ];
                @endphp
                <tr onclick="window.location='{{ route('support.conversations.show',$conversation) }}'" style="cursor:pointer">
                    <td><span class="record-title">{{ $requesterName }}</span><span class="record-sub">{{ $requesterType }}{{ $conversation->requester_phone ? ' · '.$conversation->requester_phone : '' }}</span></td>
                    <td><span class="record-title">{{ \Illuminate\Support\Str::limit($conversation->subject ?: 'Assistance OVANIE', 54) }}</span><span class="record-sub">{{ $conversation->messages_count }} message(s) · priorité {{ $conversation->priority }}</span></td>
                    <td>
                        @if($contextItems->isNotEmpty())
                            @foreach($contextItems->take(3) as $item)
                                <div>@if($item['url'])<a class="record-title" href="{{ $item['url'] }}" onclick="event.stopPropagation()">{{ $item['label'] }} : {{ $item['value'] }}</a>@else<span class="record-title">{{ $item['label'] }} : {{ $item['value'] }}</span>@endif</div>
                            @endforeach
                            @if($contextItems->count() > 3)<span class="record-sub">+ {{ $contextItems->count()-3 }} autre(s) donnée(s) liée(s)</span>@endif
                        @else
                            <span class="record-title">Aucune donnée métier liée</span>
                        @endif
                        <span class="record-sub">{{ $contextHelp }}</span>
                    </td>
                    <td><span class="support-source-badge">{{ $channelLabels[$conversation->channel] ?? ucfirst($conversation->channel) }}</span></td>
                    <td>{{ $conversation->assignee?->name ?: ($conversation->requires_human ? 'À attribuer' : 'Assistance automatique') }}<span class="record-sub">{{ $conversation->source_app ? ($sourceLabels[$conversation->source_app] ?? ucfirst(str_replace('_',' ',$conversation->source_app))) : '' }}</span></td>
                    <td><span class="status {{ $conversation->status }}">{{ $statusLabels[$conversation->status] ?? ucfirst(str_replace('_',' ',$conversation->status)) }}</span></td>
                    <td>{{ $conversation->last_message_at?->diffForHumans() ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i data-lucide="messages-square"></i><div>Aucune conversation dans cette vue.</div><span class="record-sub">Les nouveaux messages apparaîtront ici automatiquement.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-row"><span>{{ $conversations->total() }} conversation(s)</span><div>{{ $conversations->links() }}</div></div>
</section>

@if(auth('admin')->user()->hasStaffPermission('support.ai.conversations.respond'))
<div class="modal-backdrop {{ $errors->any() ? 'open' : '' }}" id="newConversationModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="newConversationTitle">
        <div class="modal-header">
            <div><h2 id="newConversationTitle">Démarrer un suivi sortant</h2><p>Utilisez cette action lorsqu’un conseiller doit reprendre contact après un appel, demander une information complémentaire ou enregistrer un suivi initié par OVANIE. Les demandes entrantes arrivent automatiquement ici.</p></div>
            <button class="modal-close" type="button" data-modal-close aria-label="Fermer"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('support.conversations.store') }}">@csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>ID du compte OVANIE</label><input type="number" min="1" name="requester_user_id" value="{{ old('requester_user_id') }}" placeholder="Facultatif"></div>
                    <div class="form-group"><label>Canal</label><select name="channel" required><option value="internal" @selected(old('channel','internal')==='internal')>Suivi interne</option><option value="chat" @selected(old('channel')==='chat')>Chat web</option>@if($services['telephony']['operational'])<option value="phone" @selected(old('channel')==='phone')>Téléphone</option>@endif @if($services['whatsapp']['operational'])<option value="whatsapp" @selected(old('channel')==='whatsapp')>WhatsApp</option>@endif @if($services['email']['operational'])<option value="email" @selected(old('channel')==='email')>E-mail</option>@endif</select></div>
                    <div class="form-group"><label>Nom</label><input name="requester_name" value="{{ old('requester_name') }}" placeholder="Nom du demandeur"></div>
                    <div class="form-group"><label>Téléphone</label><input name="requester_phone" value="{{ old('requester_phone') }}"></div>
                    <div class="form-group full"><label>E-mail</label><input type="email" name="requester_email" value="{{ old('requester_email') }}"></div>
                    <div class="form-group full"><label>Objet *</label><input name="subject" value="{{ old('subject') }}" required placeholder="Ex. Suivi d’une livraison"></div>
                    <div class="form-group full"><label>Message *</label><textarea name="message" required placeholder="Saisissez le message initial…">{{ old('message') }}</textarea></div>
                </div>
                <details style="margin-top:14px">
                    <summary style="cursor:pointer;color:#476487;font-size:10px;font-weight:800">Lier une référence OVANIE (facultatif)</summary>
                    <div class="form-grid" style="margin-top:12px">
                        <div class="form-group"><label>Commande</label><input name="order_reference" value="{{ old('order_reference') }}" placeholder="N° de commande"></div>
                        <div class="form-group"><label>Paiement</label><input name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Référence transaction"></div>
                        <div class="form-group full"><label>Livraison</label><input name="tracking_reference" value="{{ old('tracking_reference') }}" placeholder="Numéro de suivi"></div>
                    </div>
                </details>
            </div>
            <div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit"><i data-lucide="send"></i>Créer le suivi</button></div>
        </form>
    </div>
</div>
@endif
@endsection
