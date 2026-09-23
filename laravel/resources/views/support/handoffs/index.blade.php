@extends('layouts.staff')
@section('title', 'Transferts aux équipes OVANIE | Support')
@section('content')
@php
    $departmentLabels = ['logistique'=>'Logistique','commercial'=>'Commercial','administration'=>'Administration'];
    $statusLabels = ['pending'=>'En attente','assigned'=>'Affecté','accepted'=>'Pris en charge','in_progress'=>'En traitement','resolved'=>'Réponse reçue','closed'=>'Archivé','cancelled'=>'Annulé'];
    $priorityLabels = ['normal'=>'Normale','high'=>'Haute','urgent'=>'Urgente'];
@endphp
<div class="page-header">
    <div class="page-header-main"><span class="page-header-icon"><i data-lucide="forward"></i></span><div><h1 class="page-title">Transferts aux équipes OVANIE</h1><p class="page-subtitle">Suivez les actions demandées à la Logistique, au Commercial ou à l’Administration. Le Support reste l’interlocuteur du demandeur et attend le retour du service avant de l’informer.</p></div></div>
    <div class="page-actions"><a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="inbox"></i>Dossiers Support</a></div>
</div>

<div class="notice"><i data-lucide="database"></i><div><strong>Données opérationnelles uniquement.</strong> Les anciennes lignes du seeder de démonstration sont exclues. Chaque transfert affiché ici provient du workflow réel : un conseiller a demandé une action à la Logistique, au Commercial ou à l’Administration. Le Support reste l’interlocuteur du demandeur.</div></div>

<div class="grid kpi-grid">
    @foreach([
        ['label'=>'En attente du service','value'=>$queueStats['pending'],'icon'=>'hourglass'],
        ['label'=>'En traitement','value'=>$queueStats['in_progress'],'icon'=>'loader-circle'],
        ['label'=>'Hors délai','value'=>$queueStats['overdue'],'icon'=>'alarm-clock'],
        ['label'=>'Réponses reçues','value'=>$queueStats['resolved'],'icon'=>'message-square-check'],
    ] as $stat)
        <div class="kpi-card"><div class="kpi-head"><span class="kpi-label">{{ $stat['label'] }}</span><span class="kpi-icon"><i data-lucide="{{ $stat['icon'] }}"></i></span></div><div class="kpi-value">{{ $stat['value'] }}</div></div>
    @endforeach
</div>

<div class="toolbar"><form class="filters" method="GET">
    <input name="q" value="{{ request('q') }}" placeholder="Référence, demandeur, dossier ou action attendue">
    <select name="target_department"><option value="">Toutes les équipes OVANIE</option>@foreach($departmentLabels as $value=>$label)<option value="{{ $value }}" @selected(request('target_department')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="status"><option value="">Tous les statuts</option>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
    <button class="btn" type="submit"><i data-lucide="filter"></i>Filtrer</button>
</form></div>

<section class="card">
    <div class="card-head"><div><h2>Actions demandées aux équipes OVANIE</h2><p>Le Support consulte l’avancement, récupère la réponse puis informe le client, vendeur, livreur partenaire ou commercial concerné.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Transfert</th><th>Demandeur</th><th>Équipe OVANIE</th><th>Dossier / conversation</th><th>Action attendue</th><th>Priorité</th><th>Statut</th><th>Suivi</th></tr></thead><tbody>
    @forelse($handoffs as $handoff)
        @php
            $requesterName = $handoff->ticket?->requesterProfile?->name
                ?: $handoff->ticket?->requester_name
                ?: $handoff->conversation?->requesterProfile?->name
                ?: $handoff->conversation?->requester?->name
                ?: $handoff->conversation?->requester_name
                ?: 'Demandeur non identifié';
            $requesterType = $handoff->ticket?->requesterProfile?->type_label
                ?: $handoff->conversation?->requesterProfile?->type_label
                ?: 'Utilisateur OVANIE';
            $order = $handoff->ticket?->order ?: $handoff->conversation?->order;
            $payment = $handoff->ticket?->payment ?: $handoff->conversation?->payment;
            $shipment = $handoff->ticket?->shipment ?: $handoff->conversation?->shipment;
            $shop = $handoff->ticket?->shop ?: $handoff->conversation?->shop;
            $contextLines = collect();
            if ($order?->order_number) $contextLines->push('Commande '.$order->order_number);
            if ($payment?->reference) $contextLines->push('Paiement '.$payment->reference);
            if ($shipment?->tracking_number) $contextLines->push('Livraison '.$shipment->tracking_number);
            if ($shop) $contextLines->push('Boutique '.($shop->name ?: '#'.$shop->id));
            if ($handoff->deliveryIncident) $contextLines->push('Incident #'.$handoff->deliveryIncident->id);
            if ($handoff->commercialLead?->reference) $contextLines->push('Suivi commercial '.$handoff->commercialLead->reference);
        @endphp
        <tr>
            <td><span class="record-title">{{ $handoff->reference }}</span><span class="record-sub">{{ $handoff->requested_at?->format('d/m/Y H:i') }}</span></td>
            <td><strong>{{ $requesterName }}</strong><span class="record-sub">{{ $requesterType }}</span></td>
            <td><strong>{{ $departmentLabels[$handoff->target_department] ?? ucfirst($handoff->target_department) }}</strong>@if($handoff->assignee)<span class="record-sub">Pris par {{ $handoff->assignee->name }}</span>@endif</td>
            <td>
                @if($handoff->ticket)<a class="record-title" href="{{ route('support.tickets.show',$handoff->ticket) }}">{{ $handoff->ticket->reference }}</a>@endif
                @if($handoff->conversation)<a class="record-sub" href="{{ route('support.conversations.show',$handoff->conversation) }}">Conversation #{{ $handoff->conversation->id }}</a>@endif
                @foreach($contextLines->take(3) as $line)<span class="record-sub">{{ $line }}</span>@endforeach
                @if(!$handoff->ticket && !$handoff->conversation && $contextLines->isEmpty())<span class="record-sub">Contexte interne OVANIE</span>@endif
            </td>
            <td>{{ \Illuminate\Support\Str::limit($handoff->reason,110) }}@if($handoff->notes)<span class="record-sub"><strong>Retour :</strong> {{ \Illuminate\Support\Str::limit($handoff->notes,100) }}</span>@endif</td>
            <td><span class="status {{ $handoff->severity }}">{{ $priorityLabels[$handoff->severity] ?? ucfirst($handoff->severity) }}</span>@if($handoff->is_overdue)<span class="record-sub" style="color:#b42318">Échéance dépassée</span>@elseif($handoff->due_at)<span class="record-sub">Avant {{ $handoff->due_at->format('d/m H:i') }}</span>@endif</td>
            <td><span class="status {{ $handoff->status }}">{{ $statusLabels[$handoff->status] ?? str_replace('_',' ',$handoff->status) }}</span></td>
            <td>
                @if($handoff->ticket)<a class="btn" href="{{ route('support.tickets.show',$handoff->ticket) }}"><i data-lucide="eye"></i>Suivre</a>
                @elseif($handoff->conversation)<a class="btn" href="{{ route('support.conversations.show',$handoff->conversation) }}"><i data-lucide="eye"></i>Suivre</a>
                @else<span class="record-sub">Suivi uniquement</span>@endif
            </td>
        </tr>
    @empty<tr><td colspan="8"><div class="empty"><i data-lucide="forward"></i><div>Aucun transfert vers une équipe OVANIE.</div><span class="record-sub">Les transferts apparaissent ici dès qu’un conseiller demande une action à la Logistique, au Commercial ou à l’Administration.</span></div></td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination-row"><span>{{ $handoffs->total() }} transfert(s)</span><div>{{ $handoffs->links() }}</div></div>
</section>
@endsection
