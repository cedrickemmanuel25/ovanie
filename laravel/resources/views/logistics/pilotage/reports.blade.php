@extends('layouts.logistics')
@section('title','Rapports & performances')
@section('crumb','Pilotage › Rapports & performances')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
    $maxDeliveries = max(1, (int) $deliveryEvolution->max('deliveries'));
    $maxIncidents = max(1, (int) $deliveryEvolution->max('incidents'));
    $displayDays = $deliveryEvolution->count();
    $axisStep = max(1, (int) ceil($displayDays / 6));

    $rates = $punctualityEvolution->pluck('rate')->filter(fn($v) => $v !== null)->values();
    $linePoints = [];
    $lineFillPoints = [];
    $count = max(1, $punctualityEvolution->count());
    foreach ($punctualityEvolution as $index => $point) {
        $x = $count <= 1 ? 20 : 20 + (($index / ($count - 1)) * 560);
        if ($point['rate'] === null) { continue; }
        $rate = (float) $point['rate'];
        $y = 155 - (($rate / 100) * 120);
        $linePoints[] = round($x, 1).','.round($y, 1);
    }
    if ($linePoints) {
        $lineFillPoints = array_merge([$linePoints[0]], $linePoints, ['580,165','20,165']);
    }

    $feeColors = ['#1979ef','#ff930f','#0ca463','#7347e6','#71819d','#0f766e'];
    $start = 0;
    $segments = [];
    foreach ($feeDistribution as $i => $row) {
        $end = min(100, $start + (float) $row['percent']);
        $segments[] = ($feeColors[$i % count($feeColors)]).' '.$start.'% '.$end.'%';
        $start = $end;
    }
    $donutStyle = $segments ? 'background:conic-gradient('.implode(',', $segments).')' : 'background:#e9eef5';
@endphp
<main class="mg-page pilotage-page reports-page pilotage-v2">
    <header class="mg-titlebar pilotage-titlebar">
        <div>
            <h1>Rapports &amp; performances</h1>
            <p>Suivez les performances réelles des opérations OVANIE Logistics.</p>
        </div>
        <form method="GET" class="pilotage-report-filters">
            <label class="pilotage-range"><x-operations.icon name="calendar"/><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"><span>—</span><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></label>
            <label class="pilotage-select-icon"><x-operations.icon name="pin"/><select name="territory">
                <option value="">Tous les territoires</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->code }}" @selected(request('territory') === $zone->code)>{{ $zone->name }}</option>
                @endforeach
            </select></label>
            <button class="mg-btn" type="submit"><x-operations.icon name="refresh"/>Actualiser</button>
            <button class="mg-btn mg-btn-primary" type="submit" name="export" value="1"><x-operations.icon name="download"/>Exporter le rapport</button>
        </form>
    </header>

    <section class="mg-kpis mg-kpis-6 report-kpis pilotage-report-kpis">
        @foreach($kpis as $kpi)
            <article class="mg-kpi">
                <span class="mg-kpi-icon {{ $kpi['tone'] }}"><x-operations.icon :name="$kpi['icon']"/></span>
                <div>
                    <strong>{{ $kpi['value'] }}</strong>
                    <span>{{ $kpi['label'] }}</span>
                    <b class="trend {{ $kpi['positive'] ? 'up' : 'negative' }}">{{ $kpi['change_label'] }}</b>
                    <small>vs période précédente</small>
                </div>
            </article>
        @endforeach
    </section>

    <section class="report-chart-grid pilotage-chart-grid">
        <article class="mg-card chart-card pilotage-chart-card">
            <header><h3><x-operations.icon name="chart"/> Évolution des livraisons</h3><span><i class="legend green"></i>Livraisons <i class="legend red"></i>Incidents</span></header>
            @if(($deliveryEvolution->sum('deliveries') + $deliveryEvolution->sum('incidents')) === 0)
                <div class="pilotage-empty">Aucune livraison enregistrée sur cette période.</div>
            @else
                <div class="bar-chart pilotage-bar-chart">
                    @foreach($deliveryEvolution as $point)
                        <i title="{{ $point['date']->format('d/m/Y') }} : {{ $point['deliveries'] }} livraison(s)" style="height:{{ $point['deliveries'] ? max(3, ($point['deliveries'] / $maxDeliveries) * 100) : 0 }}%"><b title="{{ $point['incidents'] }} incident(s)" style="height:{{ $point['incidents'] ? max(4, ($point['incidents'] / $maxIncidents) * 35) : 2 }}%"></b></i>
                    @endforeach
                </div>
                <div class="chart-axis">
                    @foreach($deliveryEvolution as $i => $point)
                        @if($i === 0 || $i === $deliveryEvolution->count()-1 || $i % $axisStep === 0)
                            <span>{{ $point['date']->format('d M') }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </article>

        <article class="mg-card chart-card cost-card pilotage-chart-card">
            <header><h3><x-operations.icon name="wallet"/> Répartition des frais de livraison facturés</h3></header>
            <div class="cost-content">
                <div class="donut pilotage-donut" style="{{ $donutStyle }}"><span>{{ number_format($deliveryFeesTotal,0,',',' ') }}<br><small>FCFA</small></span></div>
                <ul>
                    @forelse($feeDistribution as $i => $row)
                        <li><i style="background:{{ $feeColors[$i % count($feeColors)] }}"></i><span>{{ $row['label'] }}</span><b>{{ number_format($row['percent'],1,',',' ') }}%</b></li>
                    @empty
                        <li class="pilotage-no-cost">Aucun frais de livraison facturé sur cette période.</li>
                    @endforelse
                </ul>
            </div>
        </article>

        <article class="mg-card chart-card line-card pilotage-chart-card">
            <header><h3><x-operations.icon name="clock"/> Taux de ponctualité</h3><b>Moyenne : {{ $rates->isNotEmpty() ? number_format((float)$rates->avg(),1,',',' ').'%' : '—' }}</b></header>
            <div class="line-chart pilotage-line-chart">
                @if($linePoints)
                    <svg viewBox="0 0 600 180" preserveAspectRatio="none" aria-label="Évolution de la ponctualité">
                        <defs><linearGradient id="pilotageFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#10b981" stop-opacity=".25"/><stop offset="1" stop-color="#10b981" stop-opacity="0"/></linearGradient></defs>
                        <polygon points="{{ implode(' ', $lineFillPoints) }}" fill="url(#pilotageFill)"/>
                        <polyline points="{{ implode(' ', $linePoints) }}" fill="none" stroke="#10a365" stroke-width="3"/>
                        @foreach($linePoints as $point)
                            @php $coords = explode(',', $point); $cx = $coords[0]; $cy = $coords[1]; @endphp
                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="4" fill="#fff" stroke="#10a365" stroke-width="2"/>
                        @endforeach
                    </svg>
                @else
                    <div class="pilotage-empty">Aucune donnée de ponctualité disponible.</div>
                @endif
                <div class="chart-axis">
                    @foreach($punctualityEvolution as $i => $point)
                        @if($i === 0 || $i === $punctualityEvolution->count()-1 || $i % $axisStep === 0)
                            <span>{{ $point['date']->format('d M') }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        </article>
    </section>

    <section class="report-table-grid">
        <article class="mg-card mg-table-card">
            <header class="mg-card-head"><h2>Performance des livreurs (Top 5)</h2><a href="{{ route('logistics.drivers') }}">Voir tous les livreurs</a></header>
            <div class="mg-table-wrap"><table class="mg-table report-table"><thead><tr><th>#</th><th>Livreur</th><th>Livraisons</th><th>Ponctualité</th><th>Incidents</th><th>Note</th></tr></thead><tbody>
            @forelse($driverPerformance as $i => $row)
                <tr><td>{{ $i+1 }}</td><td><span class="pilotage-driver"><span>{{ collect(preg_split('/\s+/', $row['name']))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('') }}</span><b>{{ $row['name'] }}</b></span></td><td>{{ $row['deliveries'] }}</td><td class="green-text">{{ $row['punctuality'] === null ? '—' : number_format($row['punctuality'],1,',',' ').'%' }}</td><td class="red-text">{{ $row['incidents'] }}</td><td>{{ $row['rating'] ? number_format($row['rating'],1,',',' ').'/5' : '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="pilotage-table-empty">Aucune livraison avec livreur sur la période.</td></tr>
            @endforelse
            </tbody></table></div>
        </article>

        <article class="mg-card mg-table-card">
            <header class="mg-card-head"><h2>Zones avec le plus de livraisons</h2><a href="{{ route('logistics.zones') }}">Voir toutes les zones</a></header>
            <div class="mg-table-wrap"><table class="mg-table report-table"><thead><tr><th>#</th><th>Zone / commune</th><th>Livraisons</th><th>Ponctualité</th><th>Incidents</th></tr></thead><tbody>
            @forelse($topZones as $i => $row)
                <tr><td>{{ $i+1 }}</td><td>{{ $row['zone'] }}</td><td>{{ $row['deliveries'] }}</td><td class="green-text">{{ $row['punctuality'] === null ? '—' : number_format($row['punctuality'],1,',',' ').'%' }}</td><td class="red-text">{{ $row['incidents'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="pilotage-table-empty">Aucune zone avec livraison sur la période.</td></tr>
            @endforelse
            </tbody></table></div>
        </article>
    </section>

    <section class="report-bottom-grid">
        <article class="mg-card mg-table-card">
            <header class="mg-card-head"><h2>Derniers incidents</h2><a href="{{ route('logistics.incidents.index') }}">Voir tous</a></header>
            <table class="mg-table tiny"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Zone</th><th>Statut</th></tr></thead><tbody>
            @forelse($latestIncidents as $row)
                <tr><td>{{ optional($row['date'])->format('d/m/Y H:i') }}</td><td>{{ $row['type'] }}</td><td>{{ \Illuminate\Support\Str::limit($row['description'], 58) }}</td><td>{{ $row['zone'] }}</td><td><span class="mg-chip {{ in_array($row['status'],['Résolu','Clôturé'])?'green':'orange' }}">{{ $row['status'] }}</span></td></tr>
            @empty
                <tr><td colspan="5" class="pilotage-table-empty">Aucun incident opérationnel.</td></tr>
            @endforelse
            </tbody></table>
        </article>
        <article class="mg-card mg-table-card">
            <header class="mg-card-head"><h2>Derniers retours</h2><a href="{{ route('logistics.returns') }}">Voir tous</a></header>
            <table class="mg-table tiny"><thead><tr><th>Date</th><th>Motif</th><th>Zone</th><th>Statut</th></tr></thead><tbody>
            @forelse($latestReturns as $row)
                <tr><td>{{ optional($row['date'])->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Str::limit($row['reason'], 45) }}</td><td>{{ $row['zone'] }}</td><td><span class="mg-chip {{ in_array($row['status'],['Traité','Remboursé'])?'green':($row['status']==='Rejeté'?'red':'orange') }}">{{ $row['status'] }}</span></td></tr>
            @empty
                <tr><td colspan="4" class="pilotage-table-empty">Aucun retour enregistré.</td></tr>
            @endforelse
            </tbody></table>
        </article>
        <article class="mg-card mg-side-card indicators-card">
            <header><h3>Indicateurs clés</h3><small>{{ $dataSourceLabel }}</small></header>
            @foreach($keyIndicators as $row)
                <div><span>{{ $row['label'] }}</span><b>{{ $row['value'] }}</b></div>
            @endforeach
        </article>
    </section>
</main>
@endsection
