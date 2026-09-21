@extends($workspace === 'logistics' ? 'layouts.logistics' : 'layouts.staff')
@section('title', $workspace === 'logistics' ? 'Dossiers Support Logistique | OVANIE' : 'Transferts Support | OVANIE')
@section('crumb', 'Support')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-support.css') }}?v={{ @filemtime(public_path('css/logistics-support.css')) ?: '20260911' }}">
@endpush

@section('content')
@php
    $logistics = $workspace === 'logistics';
    $statusLabels = [
        'pending' => ['Nouveau', 'blue'],
        'assigned' => ['Affecté', 'sky'],
        'accepted' => ['Pris en charge', 'green'],
        'in_progress' => ['En traitement', 'orange'],
        'resolved' => ['Résolu', 'green'],
        'closed' => ['Clôturé', 'gray'],
    ];
    $priorityLabels = [
        'critical' => ['Critique', 'red'],
        'urgent' => ['Haute', 'red'],
        'high' => ['Haute', 'red'],
        'normal' => ['Moyenne', 'orange'],
        'low' => ['Basse', 'gray'],
    ];
    $types = [
        'incident' => ['Incident', 'warning'],
        'livraison' => ['Livraison', 'truck'],
        'retour' => ['Retour', 'box'],
        'litige' => ['Litige', 'warning'],
        'paiement' => ['Paiement', 'wallet'],
        'commande' => ['Commande', 'list'],
    ];
    $sc = $stats['status_counts'];
    $donutTotal = max(1, $stats['total']);
    $donutParts = [
        ['#1675ec', $sc['pending']],
        ['#62aaf1', $sc['assigned']],
        ['#009c72', $sc['accepted']],
        ['#ff9c1b', $sc['in_progress']],
        ['#8995a7', $sc['resolved']],
        ['#56657a', $sc['closed']],
    ];
    $cursor = 0.0;
    $gradient = [];
    foreach ($donutParts as [$color, $count]) {
        $start = $cursor;
        $cursor += ($count * 100 / $donutTotal);
        $gradient[] = $color.' '.round($start, 2).'% '.round($cursor, 2).'%';
    }
    if ($stats['total'] === 0) {
        $gradient = ['#edf3f8 0% 100%'];
    }
@endphp

<main class="ls-support support-list-screen">
    <section class="ls-page-head">
        <div>
            <h1>{{ $logistics ? 'Dossiers Support Logistique' : 'Transferts Support' }}</h1>
            <p>{{ $logistics ? 'Demandes réellement transférées par le Support OVANIE nécessitant une intervention de l’équipe Logistique' : 'Dossiers transférés par le Support vers le service concerné' }}</p>
        </div>
        @if($logistics)<span class="support-live-data"><i></i>Données opérationnelles</span>@endif
    </section>

    <section class="ls-kpi-row support-kpi-row">
        <article class="ls-kpi-card">
            <span class="ls-kpi-icon mint"><span class="support-inbox-icon"></span></span>
            <div><small>Nouveaux dossiers</small><strong>{{ $stats['new'] }}</strong><em>{{ $stats['new_today'] }} aujourd’hui</em></div>
        </article>
        <article class="ls-kpi-card">
            <span class="ls-kpi-icon sky"><x-operations.icon name="user"/></span>
            <div><small>Affectés</small><strong>{{ $stats['assigned'] }}</strong><em class="muted">En attente de prise en charge</em></div>
        </article>
        <article class="ls-kpi-card">
            <span class="ls-kpi-icon violet"><x-operations.icon name="clock"/></span>
            <div><small>En traitement</small><strong>{{ $stats['in_treatment'] }}</strong><em>{{ $stats['taken_this_week'] }} pris en charge cette semaine</em></div>
        </article>
        <article class="ls-kpi-card">
            <span class="ls-kpi-icon rose"><x-operations.icon name="flag"/></span>
            <div><small>Dossiers prioritaires</small><strong>{{ $stats['priority'] }}</strong><em class="muted">Ouverts, priorité haute ou critique</em></div>
        </article>
        <article class="ls-kpi-card">
            <span class="ls-kpi-icon mint"><x-operations.icon name="check"/></span>
            <div><small>Résolus / clôturés</small><strong>{{ $stats['resolved_total'] }}</strong></div>
        </article>
    </section>

    <form class="ls-filter-row support-filter-row support-filter-row-real" method="GET" action="{{ $logistics ? route('logistics.handoffs.index') : route('commercial.handoffs.index') }}">
        <label class="ls-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="N° dossier, client, commande, sujet..."></label>
        <select name="status">
            <option value="">Tous les statuts</option>
            @foreach($statusLabels as $key => [$label])<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach
        </select>
        <select name="severity">
            <option value="">Toutes les priorités</option>
            <option value="critical" @selected(request('severity')==='critical')>Critique</option>
            <option value="high" @selected(request('severity')==='high')>Haute</option>
            <option value="normal" @selected(request('severity')==='normal')>Moyenne</option>
            <option value="low" @selected(request('severity')==='low')>Basse</option>
        </select>
        <select name="type">
            <option value="">Tous les types</option>
            @foreach($types as $key => [$label])<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach
        </select>
        <label class="support-date-filter"><span>Du</span><input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label class="support-date-filter"><span>Au</span><input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button class="support-filter-submit" type="submit">Filtrer</button>
        <a class="ls-reset" href="{{ $logistics ? route('logistics.handoffs.index') : route('commercial.handoffs.index') }}"><x-operations.icon name="refresh"/> Réinitialiser</a>
    </form>

    <section class="support-main-grid">
        <div class="ls-panel support-table-panel">
            <h2>Liste des dossiers logistiques ({{ $handoffs->total() }})</h2>
            <div class="support-table-wrap">
                <table class="support-table">
                    <thead><tr><th>#</th><th>Date</th><th>Source</th><th>Client</th><th>Sujet / Description</th><th>Type</th><th>Priorité</th><th>SLA</th><th>Statut</th><th>Assigné à</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($handoffs as $handoff)
                        @php
                            $conversation = $handoff->conversation;
                            $ticket = $handoff->ticket ?: $conversation?->ticket;
                            $status = $statusLabels[$handoff->status] ?? [ucfirst((string) $handoff->status), 'gray'];
                            $priority = $priorityLabels[$handoff->severity] ?? ['Moyenne', 'orange'];
                            $client = $conversation?->requester?->name
                                ?: $conversation?->requester_name
                                ?: $ticket?->requester?->name
                                ?: $ticket?->requester_name
                                ?: $conversation?->order?->client?->name
                                ?: 'Non identifié';
                            $source = $handoff->requested_by_type === 'human'
                                ? ($handoff->requester?->name ? 'Support — '.$handoff->requester->name : 'Support humain')
                                : ($handoff->aiAgent?->name ?: 'Support IA');
                            $typeKey = 'commande';
                            if ($conversation?->return_id || $ticket?->return_id) $typeKey = 'retour';
                            elseif ($conversation?->dispute_id || $ticket?->dispute_id) $typeKey = 'litige';
                            elseif ($conversation?->payment_id || $ticket?->payment_id) $typeKey = 'paiement';
                            elseif ($handoff->delivery_incident_id || $conversation?->delivery_incident_id || $ticket?->delivery_incident_id) $typeKey = 'incident';
                            elseif ($conversation?->shipment_id || $ticket?->shipment_id) $typeKey = 'livraison';
                            $type = $types[$typeKey] ?? ['Assistance', 'help'];
                            $detail = $conversation?->summary ?: $ticket?->description ?: $handoff->reason;
                            $assigned = $handoff->assignee?->name ?: 'Non assigné';
                            $sla = '—';
                            if ($handoff->requested_at && $handoff->due_at) {
                                $minutes = max(0, $handoff->requested_at->diffInMinutes($handoff->due_at, false));
                                $sla = $minutes < 60 ? $minutes.' min' : intdiv($minutes,60).' h'.($minutes % 60 ? ' '.($minutes%60).' min' : '');
                            }
                        @endphp
                        <tr>
                            <td><a class="support-ref" href="{{ $logistics ? route('logistics.handoffs.show', $handoff) : '#' }}">{{ $handoff->reference }}</a></td>
                            <td class="nowrap">{{ $handoff->requested_at?->format('d/m/Y H:i') ?: '—' }}</td>
                            <td><span class="support-source"><x-operations.icon name="{{ $handoff->requested_by_type === 'human' ? 'help' : 'order' }}"/>{{ $source }}</span></td>
                            <td>{{ $client }}</td>
                            <td class="support-subject"><b>{{ $conversation?->subject ?: $ticket?->subject ?: $handoff->reason }}</b><small title="{{ $detail }}">{{ \Illuminate\Support\Str::limit($detail, 58) }}</small></td>
                            <td><span class="support-type"><x-operations.icon name="{{ $type[1] }}"/>{{ $type[0] }}</span></td>
                            <td><span class="ls-badge {{ $priority[1] }}"><i></i>{{ $priority[0] }}</span></td>
                            <td>{{ $sla }}</td>
                            <td><span class="ls-badge {{ $status[1] }}"><i></i>{{ $status[0] }}</span></td>
                            <td>{{ $assigned }}</td>
                            <td><div class="support-actions"><a href="{{ $logistics ? route('logistics.handoffs.show', $handoff) : '#' }}" aria-label="Voir le dossier" title="Voir le dossier"><x-operations.icon name="eye"/></a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="support-empty"><strong>Aucun dossier Support Logistique réel.</strong><br>Les nouveaux transferts apparaîtront ici dès que le Support enverra un dossier au service Logistique.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="ls-table-footer">
                <span>Affichage de {{ $handoffs->firstItem() ?? 0 }} à {{ $handoffs->lastItem() ?? 0 }} sur {{ $handoffs->total() }} résultats</span>
                <nav class="ls-pager" aria-label="Pagination">
                    @php $current=$handoffs->currentPage(); $last=$handoffs->lastPage(); @endphp
                    <a class="pager-arrow {{ $current<=1?'disabled':'' }}" href="{{ $current>1?$handoffs->url($current-1):'#' }}"><x-operations.icon name="back"/></a>
                    @foreach(range(1, max(1,min(5,$last))) as $p)<a class="{{ $p===$current?'active':'' }}" href="{{ $handoffs->url($p) }}">{{ $p }}</a>@endforeach
                    @if($last>6)<span>...</span><a href="{{ $handoffs->url($last) }}">{{ $last }}</a>@endif
                    <a class="pager-arrow {{ $current>=$last?'disabled':'' }}" href="{{ $current<$last?$handoffs->url($current+1):'#' }}"><x-operations.icon name="next"/></a>
                </nav>
                <span class="support-data-note">Source : base OVANIE</span>
            </footer>
        </div>

        <aside class="support-side-column">
            <section class="ls-panel side-card status-card">
                <h3><span class="side-title-icon navy">+</span> Répartition par statut</h3>
                <div class="status-distribution">
                    <div class="support-donut" style="background:conic-gradient({{ implode(',', $gradient) }})"><span><strong>{{ $stats['total'] }}</strong><small>dossiers</small></span></div>
                    <ul>
                        @foreach([
                            ['Nouveaux',$sc['pending'],'blue'],['Affectés',$sc['assigned'],'sky'],['Pris en charge',$sc['accepted'],'green'],['En traitement',$sc['in_progress'],'orange'],['Résolus',$sc['resolved'],'gray'],['Clôturés',$sc['closed'],'gray']
                        ] as [$label,$count,$tone])
                        <li><span><i class="legend-dot {{ $tone }}"></i>{{ $label }}</span><b>{{ $count }} ({{ $stats['total'] ? round($count*100/$stats['total']) : 0 }} %)</b></li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="ls-panel side-card sla-card sla-card-pro">
                <h3><span class="side-title-icon green">Q</span> <span>Respect des SLA</span><small>Dossiers réels</small></h3>
                <div class="sla-pro-layout">
                    <div class="sla-pro-score">
                        <div class="sla-ring" style="--value:{{ $stats['sla_percent'] }}"><strong>{{ $stats['sla_percent'] }}%</strong></div>
                        <div><b>Objectif de service</b><span class="sla-good">SLA respectés</span><small>Calculé avec les échéances réelles</small></div>
                    </div>
                    <div class="sla-pro-metrics">
                        <div><span>Dossiers dans les délais</span><strong>{{ $stats['sla_in_time'] }}</strong></div>
                        <div><span>Dossiers en retard</span><strong class="danger-text">{{ $stats['sla_late'] }}</strong></div>
                        <div><span>Prise en charge moyenne</span><strong>{{ $stats['avg_pickup'] }}</strong></div>
                        <div><span>Résolution moyenne</span><strong>{{ $stats['avg_resolution'] }}</strong></div>
                    </div>
                </div>
            </section>

            <section class="ls-panel side-card priority-card">
                <h3><x-operations.icon name="flag"/> Dossiers par priorité</h3>
                @php $pc=$stats['priority_counts']; $maxPriority=max(1,max($pc)); @endphp
                @foreach([['Critique',$pc['critical'],'red'],['Haute',$pc['high'],'orange'],['Moyenne',$pc['normal'],'yellow'],['Basse',$pc['low'],'gray']] as [$label,$count,$tone])
                <div class="priority-row"><span><i class="legend-dot {{ $tone }}"></i>{{ $label }}</span><div class="priority-track"><i class="{{ $tone }}" style="width:{{ round($count*100/$maxPriority) }}%"></i></div><b>{{ $count }}</b></div>
                @endforeach
            </section>

            <section class="ls-panel side-card latest-card latest-card-pro">
                <h3><span class="latest-title"><x-operations.icon name="truck"/> Derniers dossiers transférés</span><a href="{{ $logistics ? route('logistics.handoffs.index') : route('commercial.handoffs.index') }}">Voir tout <x-operations.icon name="next"/></a></h3>
                <div class="latest-table-head"><span>Heure</span><span>Dossier</span><span>Objet</span><span>Priorité</span></div>
                <div class="latest-list">
                    @forelse($latest as $item)
                        @php
                            $p=$priorityLabels[$item->severity]??['Moyenne','orange'];
                            $latestClient=$item->conversation?->requester?->name ?: $item->conversation?->requester_name ?: $item->ticket?->requester_name ?: 'Client';
                        @endphp
                        <a class="latest-transfer-row" href="{{ $logistics ? route('logistics.handoffs.show', $item) : '#' }}">
                            <time><i class="latest-dot {{ $p[1] }}"></i>{{ $item->requested_at?->format('H:i') ?: '—' }}</time>
                            <b>{{ $item->reference }}</b>
                            <span><strong>{{ $item->conversation?->subject ?: $item->ticket?->subject ?: $item->reason }}</strong><small>{{ $latestClient }}</small></span>
                            <em class="{{ $p[1] }}">{{ $p[0] }}</em>
                        </a>
                    @empty
                        <div class="latest-empty">Aucun transfert réel pour le moment.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </section>
</main>
@endsection
