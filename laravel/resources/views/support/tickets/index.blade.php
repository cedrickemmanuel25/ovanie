@extends('layouts.staff')
@section('title', 'Dossiers Support | OVANIE')
@section('content')
<section class="workspace-banner">
    <div>
        <span class="eyebrow"><i data-lucide="inbox"></i> Suivi des demandes</span>
        <h1>Dossiers Support</h1>
        <p>Un dossier est créé lorsqu’une préoccupation nécessite un suivi. Le Support reste l’interlocuteur du demandeur, répond directement lorsqu’il le peut et transmet le dossier à l’équipe OVANIE concernée lorsqu’une action métier est nécessaire.</p>
    </div>
    @if(auth('admin')->user()?->hasStaffPermission('tickets.write'))
        <div class="workspace-actions"><a class="btn btn-primary" href="{{ route('support.tickets.create') }}"><i data-lucide="circle-plus"></i>Nouveau dossier</a></div>
    @endif
</section>

<nav class="tabs" aria-label="Filtres des dossiers">
    <a class="tab {{ $scope === 'open' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'open']) }}">Ouverts <span class="tab-count">{{ $ticketStats['open'] }}</span></a>
    <a class="tab {{ $scope === 'unassigned' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'unassigned']) }}">À prendre <span class="tab-count">{{ $ticketStats['unassigned'] }}</span></a>
    <a class="tab {{ $scope === 'mine' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'mine']) }}">Mes dossiers <span class="tab-count">{{ $ticketStats['mine'] }}</span></a>
    <a class="tab {{ $scope === 'urgent' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'urgent']) }}">Prioritaires <span class="tab-count">{{ $ticketStats['urgent'] }}</span></a>
    <a class="tab {{ $scope === 'waiting' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'waiting']) }}">En attente <span class="tab-count">{{ $ticketStats['waiting'] }}</span></a>
    <a class="tab {{ $scope === 'transferred' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'transferred']) }}">Transmis <span class="tab-count">{{ $ticketStats['transferred'] }}</span></a>
    <a class="tab {{ $scope === 'resolved' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'resolved']) }}">Terminés <span class="tab-count">{{ $ticketStats['resolved'] }}</span></a>
    <a class="tab {{ $scope === 'all' ? 'active' : '' }}" href="{{ route('support.tickets.index', ['scope'=>'all']) }}">Tous <span class="tab-count">{{ $ticketStats['all'] }}</span></a>
</nav>

<div class="toolbar">
    <form class="filters" method="GET">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Référence, objet, nom, e-mail…">
        <select name="priority"><option value="">Toutes les priorités</option>@foreach(['urgent'=>'Urgente','high'=>'Haute','normal'=>'Normale','low'=>'Faible'] as $value=>$label)<option value="{{ $value }}" @selected(request('priority')===$value)>{{ $label }}</option>@endforeach</select>
        <select name="assigned_to"><option value="">Tous les conseillers</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select>
        <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
        @if(request('q') || request('priority') || request('assigned_to'))<a class="btn" href="{{ route('support.tickets.index', ['scope'=>$scope]) }}">Effacer</a>@endif
    </form>
    @if(auth('admin')->user()->hasStaffPermission('support.ai.handoffs.read'))<a class="btn" href="{{ route('support.handoffs.index') }}"><i data-lucide="forward"></i>Transferts aux équipes OVANIE</a>@endif
</div>

<section class="card">
    <div class="card-head"><div><h2>Dossiers</h2><p>Ouvrez un dossier pour répondre au demandeur, ajouter une note, l’assigner ou transmettre la demande à l’équipe OVANIE qui doit intervenir.</p></div></div>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Dossier</th><th>Demandeur</th><th>Origine</th><th>Contexte OVANIE</th><th>Priorité</th><th>Statut</th><th>Responsable</th><th>Échéance</th></tr></thead>
        <tbody>
        @forelse($tickets as $ticket)
            @php
                $requesterType = $ticket->requesterProfile?->requester_type ?: data_get($ticket->metadata, 'requester_type', 'client');
                $source = $ticket->source_app ?: data_get($ticket->metadata, 'source_app', $ticket->channel);
                $context = $ticket->order?->order_number
                    ?: $ticket->shop?->name
                    ?: ($ticket->payment_id ? 'Paiement #'.$ticket->payment_id : null)
                    ?: ($ticket->shipment_id ? 'Livraison #'.$ticket->shipment_id : null)
                    ?: ($ticket->return_id ? 'Retour #'.$ticket->return_id : null)
                    ?: ($ticket->delivery_incident_id ? 'Incident #'.$ticket->delivery_incident_id : null);
                $activeHandoff = $ticket->handoffs->first(fn($handoff) => in_array($handoff->status, ['pending','assigned','accepted','in_progress'], true) && in_array($handoff->target_department, ['logistique','commercial','administration'], true));
                $statusLabel = match($ticket->status) {
                    'open' => 'Nouveau',
                    'in_progress' => 'Pris en charge',
                    'waiting_customer' => 'Attente demandeur',
                    'waiting_internal' => 'Attente équipe OVANIE',
                    'resolved' => 'Terminé',
                    'closed' => 'Archivé',
                    'cancelled' => 'Annulé',
                    default => str_replace('_',' ', $ticket->status),
                };
            @endphp
            <tr onclick="window.location='{{ route('support.tickets.show',$ticket) }}'" style="cursor:pointer">
                <td><span class="record-title">{{ $ticket->reference }}</span><span class="record-sub">{{ \Illuminate\Support\Str::limit($ticket->subject,52) }}</span></td>
                <td>{{ $ticket->requesterProfile?->name ?: $ticket->requester?->name ?: $ticket->requester_name ?: 'Non identifié' }}<span class="record-sub">{{ $ticket->requesterProfile?->type_label ?: ucfirst($requesterType) }}</span></td>
                <td><span class="support-source-badge {{ $requesterType }}">{{ str_replace('_',' ', $source ?: 'support') }}</span></td>
                <td>{{ $context ?: 'Général' }}<span class="record-sub">{{ $ticket->contextLinks->count() ? $ticket->contextLinks->count().' lien(s) métier' : 'Aucune donnée métier liée' }}</span></td>
                <td><span class="status {{ $ticket->priority }}">{{ $ticket->priority }}</span></td>
                <td><span class="status {{ $ticket->status }}">{{ $statusLabel }}</span>@if($activeHandoff)<span class="record-sub">Transmis à {{ ucfirst($activeHandoff->target_department) }}</span>@endif</td>
                <td>{{ $ticket->assignee?->name ?: 'À attribuer' }}</td>
                <td>{{ $ticket->sla_due_at?->format('d/m H:i') ?: '—' }}@if($ticket->is_overdue)<span class="record-sub" style="color:#b42318">Hors délai</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><i data-lucide="inbox"></i><div>Aucun dossier dans cette vue.</div><span class="record-sub">Les dossiers créés depuis les conversations ou les applications apparaîtront ici.</span></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination-row"><span>{{ $tickets->total() }} dossier(s)</span><div>{{ $tickets->links() }}</div></div>
</section>
@endsection
