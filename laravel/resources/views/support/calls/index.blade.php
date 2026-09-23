@extends('layouts.staff')
@section('title', 'Appels | OVANIE Support')
@section('content')
<section class="workspace-banner">
    <div>
        <span class="eyebrow"><i data-lucide="phone"></i> Téléphonie</span>
        <h1>Appels</h1>
        <p>Retrouvez les appels entrants et sortants, les appels manqués et les rappels à effectuer. Les rappels font partie du même flux de travail et ne sont plus séparés dans un autre module.</p>
    </div>
    @if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
        <div class="workspace-actions">
            <button class="btn" type="button" data-modal-open="#callbackModal"><i data-lucide="calendar-phone"></i>Planifier un rappel</button>
            <button class="btn btn-primary" type="button" data-modal-open="#outboundCallModal" @disabled(!$telephonyConfigured)><i data-lucide="phone-outgoing"></i>Nouvel appel</button>
        </div>
    @endif
</section>

@if(!$telephonyConfigured)
<div class="notice warning"><i data-lucide="circle-alert"></i><div><strong>Téléphonie indisponible.</strong> Les appels réels ne peuvent pas encore être émis ou reçus. Vous pouvez cependant enregistrer et suivre des rappels depuis cet écran.</div></div>
@endif

<nav class="tabs" aria-label="Vues téléphonie">
    <a class="tab {{ $tab === 'calls' ? 'active' : '' }}" href="{{ route('support.calls.index', ['tab'=>'calls']) }}">Appels <span class="tab-count">{{ $callStats['active'] }}</span></a>
    <a class="tab {{ $tab === 'missed' ? 'active' : '' }}" href="{{ route('support.calls.index', ['tab'=>'missed']) }}">Appels manqués <span class="tab-count">{{ $callStats['missed'] }}</span></a>
    <a class="tab {{ $tab === 'callbacks' ? 'active' : '' }}" href="{{ route('support.calls.index', ['tab'=>'callbacks']) }}">À rappeler <span class="tab-count">{{ $callStats['callbacks'] }}</span></a>
    <a class="tab {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('support.calls.index', ['tab'=>'history']) }}">Historique <span class="tab-count">{{ $callStats['history'] }}</span></a>
</nav>

@if($tab === 'callbacks')
    <div class="toolbar">
        <form class="filters" method="GET">
            <input type="hidden" name="tab" value="callbacks">
            <select name="callback_status"><option value="">Tous les statuts</option>@foreach(['pending'=>'En attente','scheduled'=>'Planifié','completed'=>'Terminé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(request('callback_status')===$value)>{{ $label }}</option>@endforeach</select>
            <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
        </form>
    </div>

    <section class="card">
        <div class="card-head"><div><h2>Rappels à effectuer</h2><p>Les rappels peuvent être créés depuis un appel manqué, une conversation ou manuellement par un conseiller.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Référence</th><th>Demandeur</th><th>Téléphone</th><th>Motif</th><th>Date souhaitée</th><th>Statut</th><th>Responsable</th><th>Action</th></tr></thead><tbody>
        @forelse($callbacks as $callback)
            <tr>
                <td><span class="record-title">{{ $callback->reference }}</span><span class="record-sub">{{ $callback->created_at?->format('d/m/Y H:i') }}</span></td>
                <td>{{ $callback->requester?->name ?: $callback->requester_name ?: 'Non identifié' }}<span class="record-sub">{{ $callback->email }}</span></td>
                <td>{{ $callback->phone }}</td>
                <td>{{ \Illuminate\Support\Str::limit($callback->reason,70) ?: 'Rappel demandé' }}</td>
                <td>{{ $callback->preferred_at?->format('d/m/Y H:i') ?: 'Dès que possible' }}</td>
                <td><span class="status {{ $callback->status }}">{{ str_replace('_',' ',$callback->status) }}</span></td>
                <td>{{ $callback->assignee?->name ?: 'Non assigné' }}</td>
                <td>
                    @if(auth('admin')->user()->hasStaffPermission('support.ai.callbacks.manage'))
                        <form method="POST" action="{{ route('support.callbacks.update',$callback) }}" style="display:flex;gap:6px;align-items:center">@csrf @method('PUT')
                            <input type="hidden" name="assigned_to" value="{{ $callback->assigned_to }}"><input type="hidden" name="preferred_at" value="{{ $callback->preferred_at?->format('Y-m-d\TH:i') }}"><input type="hidden" name="notes" value="{{ $callback->notes }}">
                            <select class="field" name="status" style="min-width:120px"><option value="pending" @selected($callback->status==='pending')>En attente</option><option value="scheduled" @selected($callback->status==='scheduled')>Planifié</option><option value="completed" @selected($callback->status==='completed')>Terminé</option><option value="cancelled" @selected($callback->status==='cancelled')>Annulé</option></select>
                            <button class="btn" type="submit">Mettre à jour</button>
                        </form>
                    @else — @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><i data-lucide="phone-call"></i><div>Aucun rappel dans cette vue.</div></div></td></tr>
        @endforelse
        </tbody></table></div>
        <div class="pagination-row"><span>{{ $callbacks->total() }} rappel(s)</span><div>{{ $callbacks->links() }}</div></div>
    </section>
@else
    <div class="toolbar">
        <form class="filters" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="q" value="{{ request('q') }}" placeholder="Référence, numéro, client, commande ou livraison">
            <select name="direction"><option value="">Toutes les directions</option><option value="inbound" @selected(request('direction')==='inbound')>Entrant</option><option value="outbound" @selected(request('direction')==='outbound')>Sortant</option></select>
            <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
        </form>
    </div>

    <section class="card">
        <div class="card-head"><div><h2>{{ $tab === 'missed' ? 'Appels manqués' : ($tab === 'history' ? 'Historique des appels' : 'Appels') }}</h2><p>Chaque appel peut être relié à une conversation, un dossier, une commande ou une livraison.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Appel</th><th>Numéro</th><th>Demandeur</th><th>Dossier / contexte</th><th>Conseiller</th><th>Statut</th><th>Durée</th><th>Début</th></tr></thead><tbody>
        @forelse($calls as $call)
        <tr onclick="window.location='{{ route('support.calls.show',$call) }}'" style="cursor:pointer">
            <td><span class="record-title">{{ $call->reference }}</span><span class="record-sub">{{ $call->direction === 'inbound' ? 'Entrant' : 'Sortant' }}</span></td>
            <td>{{ $call->direction==='inbound' ? $call->from_number : $call->to_number }}</td>
            <td>{{ $call->requester?->name ?: 'Non identifié' }}<span class="record-sub">{{ $call->requester_user_id ? 'Compte #'.$call->requester_user_id : 'Aucune correspondance' }}</span></td>
            <td>{{ $call->ticket?->reference ?: $call->conversation?->order?->order_number ?: $call->conversation?->shipment?->tracking_number ?: '—' }}</td>
            <td>{{ $call->handler?->name ?: 'Non attribué' }}</td>
            <td><span class="status {{ $call->status }}">{{ str_replace('_',' ',$call->status) }}</span></td>
            <td>{{ gmdate('H:i:s',$call->duration_seconds) }}</td>
            <td>{{ $call->started_at?->format('d/m/Y H:i') ?: '—' }}</td>
        </tr>
        @empty<tr><td colspan="8"><div class="empty"><i data-lucide="phone-off"></i><div>Aucun appel dans cette vue.</div></div></td></tr>@endforelse
        </tbody></table></div>
        <div class="pagination-row"><span>{{ $calls->total() }} appel(s)</span><div>{{ $calls->links() }}</div></div>
    </section>
@endif

@if(auth('admin')->user()->hasStaffPermission('support.ai.calls.initiate'))
<div class="modal-backdrop" id="callbackModal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header"><div><h2>Planifier un rappel</h2><p>Créez une tâche de rappel pour qu’un conseiller recontacte la personne au bon moment.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div>
        <form method="POST" action="{{ route('support.callbacks.store') }}">@csrf
            <div class="modal-body"><div class="form-grid">
                <div class="form-group"><label>Téléphone *</label><input name="phone" required placeholder="+225..."></div>
                <div class="form-group"><label>ID du compte</label><input type="number" min="1" name="requester_user_id" placeholder="Facultatif"></div>
                <div class="form-group"><label>Nom</label><input name="requester_name"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
                <div class="form-group full"><label>Date souhaitée</label><input type="datetime-local" name="preferred_at"></div>
                <div class="form-group full"><label>Motif</label><textarea name="reason" placeholder="Pourquoi faut-il rappeler cette personne ?"></textarea></div>
            </div></div>
            <div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit"><i data-lucide="calendar-check"></i>Planifier le rappel</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="outboundCallModal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header"><div><h2>Nouvel appel sortant</h2><p>Le système tentera d’identifier automatiquement le compte et le contexte correspondant.</p></div><button class="modal-close" type="button" data-modal-close><i data-lucide="x"></i></button></div>
        <form method="POST" action="{{ route('support.calls.outbound') }}">@csrf
            <div class="modal-body"><div class="form-grid">
                <div class="form-group"><label>Numéro à appeler *</label><input name="to_number" required placeholder="+225..."></div>
                <div class="form-group"><label>ID du compte</label><input type="number" min="1" name="requester_user_id" placeholder="Facultatif"></div>
                <div class="form-group"><label>Nom du demandeur</label><input name="requester_name"></div>
                <div class="form-group"><label>E-mail</label><input type="email" name="requester_email"></div>
                <div class="form-group full"><label>Objet de l’appel</label><input name="subject" placeholder="Ex. Suivi d’une livraison"></div>
                <div class="form-group"><label>Commande</label><input name="order_reference" placeholder="N° de commande"></div>
                <div class="form-group"><label>Livraison</label><input name="tracking_reference" placeholder="N° de suivi"></div>
                <div class="form-group full"><label>Paiement</label><input name="payment_reference" placeholder="Référence de paiement"></div>
            </div></div>
            <div class="modal-footer"><button class="btn" type="button" data-modal-close>Annuler</button><button class="btn btn-primary" type="submit" @disabled(!$telephonyConfigured)><i data-lucide="phone-outgoing"></i>Lancer l’appel</button></div>
        </form>
    </div>
</div>
@endif
@endsection
