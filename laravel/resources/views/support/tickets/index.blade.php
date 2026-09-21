@extends('layouts.staff')
@section('title', 'Tickets Support | OVANIE')
@section('content')
<div class="page-header">
    <div><h1 class="page-title">Tickets Support</h1><p class="page-subtitle">File unique pour les demandes clients, vendeurs et opérations marketplace.</p></div>
    <div class="page-actions">@if(auth()->user()->hasStaffPermission('tickets.write'))<a class="btn btn-primary" href="{{ route('support.tickets.create') }}"><i data-lucide="circle-plus"></i>Nouveau ticket</a>@endif</div>
</div>
<form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Référence, objet, client, e-mail…">
    <select name="status"><option value="">Tous les statuts</option>@foreach(['open'=>'Ouvert','in_progress'=>'En cours','waiting_customer'=>'Attente client','waiting_internal'=>'Attente interne','resolved'=>'Résolu','closed'=>'Fermé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="priority"><option value="">Toutes les priorités</option>@foreach(['urgent'=>'Urgente','high'=>'Haute','normal'=>'Normale','low'=>'Faible'] as $value=>$label)<option value="{{ $value }}" @selected(request('priority')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="assigned_to"><option value="">Tous les agents</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)request('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
    @if(request()->query())<a class="btn" href="{{ route('support.tickets.index') }}">Réinitialiser</a>@endif
</form>
<section class="card">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Ticket</th><th>Demandeur</th><th>Module lié</th><th>Priorité</th><th>Statut</th><th>Assigné à</th><th>SLA</th></tr></thead><tbody>
    @forelse($tickets as $ticket)
        @php
            $module = $ticket->order ? 'Commande '.$ticket->order->order_number : ($ticket->shop ? 'Boutique '.$ticket->shop->name : ($ticket->payment_id ? 'Paiement #'.$ticket->payment_id : ($ticket->shipment_id ? 'Livraison #'.$ticket->shipment_id : 'Général')));
        @endphp
        <tr>
            <td><a class="record-title" href="{{ route('support.tickets.show',$ticket) }}">{{ $ticket->reference }}</a><span class="record-sub">{{ \Illuminate\Support\Str::limit($ticket->subject,55) }}</span></td>
            <td>{{ $ticket->requester?->name ?: $ticket->requester_name ?: 'Non identifié' }}<span class="record-sub">{{ $ticket->requester?->email ?: $ticket->requester_email }}</span></td>
            <td>{{ $module }}</td>
            <td><span class="status {{ $ticket->priority }}">{{ $ticket->priority }}</span></td>
            <td><span class="status {{ $ticket->status }}">{{ str_replace('_',' ',$ticket->status) }}</span></td>
            <td>{{ $ticket->assignee?->name ?: 'Non assigné' }}</td>
            <td><span class="status {{ $ticket->is_overdue ? 'urgent' : '' }}">{{ $ticket->sla_due_at?->diffForHumans() ?: 'Non défini' }}</span></td>
        </tr>
    @empty<tr><td colspan="7"><div class="empty"><i data-lucide="inbox"></i><div>Aucun ticket ne correspond aux filtres.</div></div></td></tr>@endforelse
    </tbody></table></div>
    @if($tickets->hasPages())<div class="pagination-row"><span>{{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} sur {{ $tickets->total() }}</span><div class="pagination-actions">@if($tickets->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $tickets->previousPageUrl() }}">Précédent</a>@endif @if($tickets->hasMorePages())<a class="btn" href="{{ $tickets->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif</div></div>@endif
</section>
@endsection
