@extends('driver.layouts.app')
@section('title', 'Mes missions | OVANIE')
@section('page-title', 'Mes missions')

@section('content')
<div class="driver-page-heading">
    <div>
        <div class="driver-breadcrumb"><span>Espace livreur</span><i data-lucide="chevron-right"></i><strong>Mes missions</strong></div>
        <h1>Mes missions</h1>
        <p>Consultez vos affectations, leurs horaires et leur niveau de préparation.</p>
    </div>
</div>

<section class="driver-filter-panel">
    <div class="driver-status-tabs">
        @foreach([
            '' => ['Toutes', 'list-filter'],
            'planned' => ['Planifiées', 'calendar-clock'],
            'assigned' => ['À accepter', 'inbox'],
            'accepted' => ['Acceptées', 'circle-check'],
            'collecting' => ['Collectes', 'package-search'],
            'in_transit' => ['En route', 'truck'],
            'delivered' => ['Livrées', 'badge-check'],
        ] as $key => [$label, $icon])
            <a
                href="{{ route('driver.missions.index', array_filter(['status' => $key])) }}"
                class="{{ $status === $key ? 'active' : '' }}"
            >
                <i data-lucide="{{ $icon }}"></i>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>

    <div class="driver-search-control">
        <i data-lucide="search"></i>
        <input id="mission-search" type="search" placeholder="Rechercher une mission, une commande ou une destination…">
    </div>
</section>

<section class="driver-panel">
    <div class="driver-panel__header">
        <div>
            <h2>Liste des missions</h2>
            <p>{{ $missions->count() }} mission(s) trouvée(s)</p>
        </div>
    </div>

    @if($missions->isNotEmpty())
        <div class="driver-table-wrap">
            <table class="driver-table driver-missions-table" id="missions-table">
                <thead>
                    <tr>
                        <th>Mission</th>
                        <th>Destination</th>
                        <th>Collectes</th>
                        <th>Charge</th>
                        <th>Véhicule</th>
                        <th>Livraison prévue</th>
                        <th>Préparation</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($missions as $mission)
                        <tr class="mission-search-row" data-search="{{ mb_strtolower($mission['mission_number'].' '.$mission['order_number'].' '.$mission['client_name'].' '.$mission['commune'].' '.$mission['destination']) }}">
                            <td data-label="Mission">
                                <strong class="driver-table-title">{{ $mission['mission_number'] }}</strong>
                                <small>{{ $mission['order_number'] }}</small>
                            </td>
                            <td data-label="Destination">
                                <strong>{{ $mission['commune'] ?: 'À confirmer' }}</strong>
                                <small>{{ \Illuminate\Support\Str::limit($mission['destination'], 45) }}</small>
                            </td>
                            <td data-label="Collectes">{{ $mission['pickup_count'] }} point(s) · {{ $mission['item_count'] }} article(s)</td>
                            <td data-label="Charge">{{ number_format($mission['total_weight_kg'], 2, ',', ' ') }} kg<br><small>{{ number_format($mission['total_volume_m3'], 2, ',', ' ') }} m³</small></td>
                            <td data-label="Véhicule">{{ $mission['vehicle_label'] ?: 'À confirmer' }}</td>
                            <td data-label="Livraison prévue">{{ $mission['estimated_delivery_at']?->format('d/m/Y H:i') ?: 'À confirmer' }}</td>
                            <td data-label="Préparation">
                                <div class="driver-inline-progress">
                                    <div><span style="width: {{ $mission['preparation_percent'] }}%"></span></div>
                                    <strong>{{ $mission['preparation_percent'] }}%</strong>
                                </div>
                            </td>
                            <td data-label="Statut"><span class="driver-status driver-status--{{ $mission['status'] }}">{{ $mission['status_label'] }}</span></td>
                            <td data-label="Action">
                                <a class="driver-table-action" href="{{ route('driver.missions.show', $mission['mission_number']) }}">Ouvrir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="driver-empty driver-empty--search" id="mission-search-empty" hidden>
            <span class="driver-empty__icon"><i data-lucide="search-x"></i></span>
            <h3>Aucun résultat</h3>
            <p>Aucune mission ne correspond à votre recherche.</p>
        </div>
    @else
        <div class="driver-empty">
            <span class="driver-empty__icon"><i data-lucide="package-open"></i></span>
            <h3>Aucune mission trouvée</h3>
            <p>Aucune mission ne correspond au filtre sélectionné.</p>
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('mission-search');
    const rows = [...document.querySelectorAll('.mission-search-row')];
    const empty = document.getElementById('mission-search-empty');

    input?.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase('fr');
        let visible = 0;
        rows.forEach(row => {
            const matches = !query || row.dataset.search.includes(query);
            row.hidden = !matches;
            if (matches) visible++;
        });
        if (empty) empty.hidden = visible !== 0;
    });
});
</script>
@endpush
