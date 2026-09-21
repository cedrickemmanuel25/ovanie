@extends('layouts.logistics')
@section('title','Configurer les tarifs par commune — OVANIE')
@section('crumb','Tarification > Tarifs communes > Configurer')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush

@section('content')
<main class="pricing-page pricing-communes-page pricing-configure-page">
    <div class="pricing-titlebar pricing-titlebar-separated">
        <div>
            <span class="pricing-eyebrow">Saisie des prix de livraison</span>
            <h1>Configurer les tarifs d’une commune</h1>
            <p>Choisissez une commune de départ puis renseignez, en une seule grille, les tarifs par véhicule vers toutes les destinations actives de Territoire.</p>
        </div>
        <div class="pricing-title-actions">
            <a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.export',['type'=>'communes']) }}"><x-operations.icon name="download"/> Exporter</a>
            <a class="pricing-btn pricing-btn-green" href="{{ route('logistics.ovanie-pricing.communes') }}"><x-operations.icon name="list"/> Tarifs configurés</a>
        </div>
    </div>

    @include('logistics.pricing._module_navigation')

    @if(session('success'))
        <div class="pricing-feedback success" role="status"><x-operations.icon name="check-circle"/><div><strong>Enregistrement terminé</strong><span>{{ session('success') }}</span></div></div>
    @endif
    @if($errors->any())
        <div class="pricing-feedback error" role="alert"><x-operations.icon name="alert"/><div><strong>Enregistrement impossible</strong><span>{{ $errors->first() }}</span></div></div>
    @endif

    <section class="pricing-config-workflow" aria-label="Étapes de configuration">
        <div class="pricing-workflow-step is-active"><span>1</span><div><strong>Choisir le départ</strong><small>Zone et commune venant de Territoire</small></div></div>
        <i></i>
        <div class="pricing-workflow-step"><span>2</span><div><strong>Renseigner les prix</strong><small>Un montant par véhicule et destination</small></div></div>
        <i></i>
        <div class="pricing-workflow-step"><span>3</span><div><strong>Enregistrer la grille</strong><small>Les relations sont immédiatement disponibles au checkout</small></div></div>
    </section>

    <section class="pricing-card origin-selector-card professional-origin-card" id="grille-configuration">
        <header class="pricing-card-header">
            <div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="route"/></span><div><h2>Commune de départ</h2><p>Les listes sont synchronisées avec les zones et communes actives de Territoire. Le changement de commune recharge automatiquement la bonne grille.</p></div></div>
            <span class="territory-sync-badge"><span></span> Synchronisé avec Territoire</span>
        </header>
        <div class="pricing-card-body">
            @if($zones->isEmpty() || $communes->isEmpty())
                <div class="pricing-dependency-panel"><x-operations.icon name="map"/><strong>Aucune commune couverte disponible</strong><p>Ajoutez d’abord des communes à une zone active dans Territoire avant de configurer les prix.</p><a class="pricing-btn pricing-btn-small" href="{{ route('logistics.zones') }}">Ouvrir Territoire</a></div>
            @else
                <form method="GET" action="{{ route('logistics.ovanie-pricing.communes.configure') }}" class="origin-selector-form origin-selector-form-pro" data-origin-selector-form>
                    <label><span>Zone de départ</span><select name="origin_zone" class="pricing-select" data-origin-zone-select>@foreach($zones as $zone)<option value="{{ $zone->code }}" @selected($originZone?->code === $zone->code)>{{ $zone->name }} — {{ $zone->communes->where('is_active',true)->count() }} commune(s)</option>@endforeach</select></label>
                    <label><span>Commune de départ</span><select name="origin" class="pricing-select" data-origin-commune-select data-server-selected="{{ $selectedOrigin?->id }}">@foreach($originCommunes as $commune)<option value="{{ $commune->id }}" @selected((int)($selectedOrigin?->id ?? 0) === (int)$commune->id)>{{ $commune->name }}</option>@endforeach</select></label>
                    <button type="submit" class="pricing-btn pricing-btn-green"><x-operations.icon name="search"/> Afficher la grille</button>
                </form>

                @if($selectedOrigin)
                    <div class="origin-dashboard">
                        <div class="origin-current-summary"><x-operations.icon name="pin"/><span>Départ sélectionné</span><strong>{{ $selectedOrigin->name }}</strong>@if($originZone)<em>{{ $originZone->name }}</em>@endif</div>
                        <div class="origin-progress-summary">
                            <div><span>Relations complètes</span><strong>{{ $originProgress['covered'] }}/{{ $originProgress['total'] }}</strong></div>
                            <div><span>À compléter</span><strong>{{ $originProgress['incomplete'] + $originProgress['unconfigured'] }}</strong></div>
                            <div><span>Couverture</span><strong>{{ $originProgress['percent'] }}%</strong></div>
                            <div class="origin-progress-bar"><i style="width:{{ $originProgress['percent'] }}%"></i></div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </section>

    @if($selectedOrigin)
    <form method="POST" action="{{ route('logistics.ovanie-pricing.matrices.bulk') }}" class="pricing-card commune-bulk-card professional-bulk-card" data-commune-bulk-form>
        @csrf
        <input type="hidden" name="origin_commune_id" value="{{ $selectedOrigin->id }}">
        <input type="hidden" name="origin_zone" value="{{ $originZone?->code }}">

        <header class="pricing-card-header bulk-card-header">
            <div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="list"/></span><div><h2>Grille au départ de {{ $selectedOrigin->name }}</h2><p>Renseignez les montants en FCFA. Une case vide ou à 0 signifie que ce véhicule n’est pas encore tarifé pour la relation.</p></div></div>
            <button type="submit" class="pricing-btn pricing-btn-green"><x-operations.icon name="check-circle"/> Enregistrer les tarifs</button>
        </header>

        <div class="pricing-card-body">
            <div class="bulk-tools bulk-tools-pro">
                <label class="pricing-search"><x-operations.icon name="search"/><input type="search" placeholder="Rechercher une destination..." data-bulk-search></label>
                <select class="pricing-select" data-bulk-zone-filter><option value="">Toutes les zones de destination</option>@foreach($zones as $zone)<option value="{{ $zone->code }}">{{ $zone->name }}</option>@endforeach</select>
                <label class="bulk-check-filter"><input type="checkbox" data-bulk-missing-only> <span>Afficher seulement les tarifs incomplets</span></label>
            </div>

            <div class="bulk-apply-bar bulk-apply-bar-pro">
                <div class="bulk-apply-copy"><strong>Saisie rapide</strong><span>Appliquer le même montant à un véhicule sur toutes les lignes actuellement visibles.</span></div>
                <select class="pricing-select" data-bulk-fill-vehicle>@foreach($vehicles as $code=>$vehicle)<option value="{{ $code }}">{{ $vehicle['label'] }}</option>@endforeach</select>
                <div class="pricing-money-input bulk-apply-amount"><input type="number" min="0" step="1" placeholder="Montant" data-bulk-fill-value><span>FCFA</span></div>
                <button type="button" class="pricing-btn" data-bulk-fill-apply>Appliquer aux lignes visibles</button>
                <label class="bulk-mirror-all"><input type="checkbox" data-bulk-mirror-all> Copier aussi vers les trajets inverses</label>
            </div>

            <div class="bulk-help-row">
                <span><i class="bulk-dot complete"></i> Complet = tous les véhicules actifs ont un prix.</span>
                <span><i class="bulk-dot missing"></i> Incomplet = au moins un prix véhicule actif manque.</span>
                <span>{{ $selectedOrigin->name }} → destination et destination → {{ $selectedOrigin->name }} sont deux tarifs indépendants.</span>
            </div>

            <div class="pricing-table-wrap commune-bulk-table-wrap professional-grid-wrap">
                <table class="pricing-table commune-bulk-table" id="commune-bulk-table">
                    <thead>
                        <tr>
                            <th class="zone-col">Zone destination</th>
                            <th class="destination-col">Destination</th>
                            @foreach($vehicles as $code=>$vehicle)
                                <th class="vehicle-price-col {{ $activeVehicleCodes->contains($code) ? '' : 'vehicle-inactive' }}">{{ $vehicle['label'] }}<small>{{ $vehicle['capacity'] }}</small></th>
                            @endforeach
                            <th class="status-col">Actif</th>
                            <th class="mirror-col">Copier inverse</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($destinationRows as $row)
                        @php
                            $commune = $row['commune'];
                            $matrix = $row['matrix'];
                            $prices = (array) ($matrix?->vehicle_prices ?? []);
                            $missing = $activeVehicleCodes->contains(fn($code) => (float)($prices[$code] ?? 0) <= 0);
                            $zoneCodes = implode('|', $row['zone_codes']);
                            $zoneNames = implode(' · ', $row['zone_names']);
                            $same = (int)$commune->id === (int)$selectedOrigin->id;
                            $highlighted = (int)$highlightDestination === (int)$commune->id;
                        @endphp
                        <tr data-bulk-row data-zone-codes="{{ $zoneCodes }}" data-missing="{{ $missing ? '1' : '0' }}" data-destination="{{ mb_strtolower($commune->name) }}" class="{{ $missing ? 'is-missing' : 'is-complete' }} {{ $highlighted ? 'is-highlighted' : '' }}">
                            <td><span class="destination-zone-name">{{ $zoneNames ?: 'Territoire actif' }}</span></td>
                            <td><div class="destination-name"><strong>{{ $commune->name }}</strong>@if($same)<span class="same-commune-badge">Intra-commune</span>@endif</div></td>
                            @foreach($vehicles as $code=>$vehicle)
                                <td class="vehicle-price-cell {{ $activeVehicleCodes->contains($code) ? '' : 'vehicle-inactive' }}">
                                    <div class="bulk-price-input"><input type="number" name="prices[{{ $commune->id }}][{{ $code }}]" min="0" step="1" value="{{ (float)($prices[$code] ?? 0) > 0 ? (int) round((float) $prices[$code]) : '' }}" placeholder="0" data-bulk-price="{{ $code }}" @readonly(!$activeVehicleCodes->contains($code))><span>FCFA</span></div>
                                </td>
                            @endforeach
                            <td class="bulk-check-cell"><label title="Activer ou désactiver ce trajet"><input type="checkbox" name="active[{{ $commune->id }}]" value="1" @checked($matrix ? $matrix->is_active : true) data-route-active><span></span></label></td>
                            <td class="bulk-check-cell"><label title="Copier les mêmes prix vers {{ $commune->name }} → {{ $selectedOrigin->name }}"><input type="checkbox" name="mirror[{{ $commune->id }}]" value="1" data-mirror-row @disabled($same)><span></span></label></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bulk-save-footer bulk-save-footer-pro">
                <div><strong>{{ $destinationRows->count() }} destination(s)</strong><span>Les lignes entièrement vides ne créent pas de tarif. Effacer tous les montants d’une ligne supprime cette relation lors de l’enregistrement.</span></div>
                <div class="bulk-footer-actions"><a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.communes') }}">Annuler</a><button type="submit" class="pricing-btn pricing-btn-green"><x-operations.icon name="check-circle"/> Enregistrer toute la grille</button></div>
            </div>
        </div>
    </form>
    @endif
</main>
@endsection

@push('scripts')<script defer src="{{ asset('js/logistics-pricing.js') }}?v={{ @filemtime(public_path('js/logistics-pricing.js')) ?: '20260914' }}"></script>@endpush
