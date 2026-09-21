@extends('layouts.logistics-operations')
@section('title', 'Créer une tournée')
@section('page-class', 'ops-page-create')
@php
    $missions = ($groups ?? collect())->map(fn ($group) => \App\ViewModels\LogisticsOperationsData::mission($group))->values()->all();
    $driverRows = ($activeDrivers ?? collect())->map(fn ($driver) => \App\ViewModels\LogisticsOperationsData::driver($driver))->values()->all();
    $vehicleRows = collect($fleetVehicles ?? [])->values()->all();
    $oldItemIds = collect(old('mission_items', (array) request('mission', [])))->map(fn ($id) => (int) $id)->filter()->unique();
    $selectedMissionIds = collect($missions)->filter(fn ($mission) => collect($mission['itemIds'])->map(fn ($id) => (int) $id)->intersect($oldItemIds)->isNotEmpty())->pluck('id')->map(fn ($id) => (int) $id)->all();
    $selectedMissions = collect($missions)->whereIn('id', $selectedMissionIds);
    $initialWeight = round((float) $selectedMissions->sum('weight'), 1);
    $initialVolume = round((float) $selectedMissions->sum('volume'), 2);
    $initialProducts = (int) $selectedMissions->sum('productsCount');
    $zones = collect($missions)->pluck('destination')->filter()->unique()->sort()->values();
    $vehicleRules = $vehicleRules ?? [];
    $defaultDeparture = now()->addMinutes(15)->format('H:i');
@endphp
@section('content')
<form method="post" action="{{ route('logistics.tours.store') }}" id="ops-create-form">
    @csrf
    <x-operations.page-header title="Créer une tournée" subtitle="Regroupez des missions prêtes, choisissez un livreur : son véhicule de flotte réel est attribué automatiquement, puis calculez le parcours routier." :tour="true" current="Créer une tournée">
        <a class="ops-button" href="{{ route('logistics.tours.index') }}"><x-operations.icon name="close"/>Annuler</a>
        <button type="button" class="ops-button" data-save-tour><x-operations.icon name="save"/>Enregistrer brouillon</button>
        <button class="ops-button ops-button-primary" type="submit"><x-operations.icon name="check-circle"/>Créer la tournée</button>
    </x-operations.page-header>

    @if($errors->any())
        <div class="ops-form-errors" role="alert"><strong>La tournée n’a pas pu être créée.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="ops-kpis">
        <x-operations.kpi icon="tour" label="Missions sélectionnées" :value="count($selectedMissionIds)" data-kpi="count"/>
        <x-operations.kpi icon="weight" label="Poids total" :value="$initialWeight.' kg'" data-kpi="weight"/>
        <x-operations.kpi icon="box" label="Volume total" :value="str_replace('.', ',', (string) $initialVolume).' m³'" tone="green" data-kpi="volume"/>
        <x-operations.kpi icon="truck" label="Véhicule requis" value="À déterminer" data-kpi="vehicle"/>
        <x-operations.kpi icon="clock" label="Durée routière" value="À calculer" tone="orange" data-kpi="duration"/>
    </div>

    <div class="ops-create-layout">
        <div class="ops-stack">
            <x-operations.panel title="Paramètres de la tournée" icon="settings" class="ops-tour-parameters">
                <div class="ops-form-grid">
                    <label>Livreur <em>*</em>
                        <span class="ops-field-icon"><x-operations.icon name="user"/>
                            <select required name="driver_id" data-plan-driver-select>
                                <option value="">Choisir un livreur disponible</option>
                                @foreach($driverRows as $driver)
                                    <option value="{{ $driver['id'] }}" @selected((string) old('driver_id') === (string) $driver['id'])>{{ $driver['name'] }} — {{ $driver['zone'] }} — ★ {{ $driver['rating'] }}</option>
                                @endforeach
                            </select>
                        </span>
                    </label>
                    <label>Véhicule attribué <em>*</em>
                        <span class="ops-field-icon"><x-operations.icon name="truck"/>
                            <select required name="fleet_vehicle_id" data-plan-fleet-vehicle>
                                <option value="">Choisir d’abord un livreur</option>
                            </select>
                        </span>
                        <small class="ops-field-help" data-vehicle-help>Le véhicule réel attribué au livreur s’affichera automatiquement. Les motos ne peuvent pas être utilisées pour une tournée.</small>
                    </label>
                    <label>Date <em>*</em>
                        <span class="ops-field-icon ops-date-styled"><x-operations.icon name="calendar"/>
                            <input name="tour_date" type="date" required min="{{ now()->format('Y-m-d') }}" value="{{ old('tour_date', now()->format('Y-m-d')) }}"><span data-date-display></span>
                        </span>
                    </label>
                    <label>Heure de départ <em>*</em>
                        <span class="ops-field-icon"><x-operations.icon name="clock"/><input name="departure_time" type="time" required value="{{ old('departure_time', $defaultDeparture) }}"></span>
                    </label>
                    <label>Zone principale
                        <span class="ops-field-icon"><x-operations.icon name="pin"/>
                            <select name="zone_label"><option value="">Toutes les destinations sélectionnées</option>@foreach($zones as $zone)<option value="{{ $zone }}" @selected(old('zone_label') === $zone)>{{ $zone }}</option>@endforeach</select>
                        </span>
                    </label>
                    <div class="ops-optimization-setting">
                        <label class="ops-switch-label"><input type="hidden" name="optimization_enabled" value="0"><input class="ops-switch" type="checkbox" name="optimization_enabled" value="1" @checked(old('optimization_enabled', ($optimizationEnabled ?? true) ? '1' : '0') !== '0')>Optimisation automatique</label>
                        <div class="ops-availability"><span class="ops-badge" data-driver-available>Choisir un livreur</span><span class="ops-badge" data-vehicle-compatible>Véhicule du livreur</span></div>
                    </div>
                </div>
            </x-operations.panel>

            <x-operations.panel title="Missions disponibles" icon="list" class="ops-available-missions">
                <p class="ops-panel-caption">Uniquement les commandes OVANIE Logistics réellement payées/confirmées et prêtes à être collectées.</p>
                <div class="ops-filters ops-filters-compact">
                    <label class="ops-search"><x-operations.icon name="search"/><input placeholder="Rechercher une mission, destination, client..." aria-label="Rechercher une mission" data-mission-filter="search"></label>
                    <select data-mission-filter="vehicle" aria-label="Véhicule"><option value="">Tous les véhicules requis</option>@foreach(collect($missions)->pluck('vehicle')->filter()->unique() as $value)<option>{{ $value }}</option>@endforeach</select>
                    <select data-mission-filter="destination" aria-label="Destination"><option value="">Toutes les destinations</option>@foreach($zones as $value)<option>{{ $value }}</option>@endforeach</select>
                    <button type="button" class="ops-reset" data-reset-missions><x-operations.icon name="refresh"/>Réinitialiser</button>
                </div>
                <div class="ops-table-scroll"><table class="ops-table ops-available-table"><thead><tr><th><input type="checkbox" data-select-all aria-label="Sélectionner toutes les missions affichées"></th><th>Mission</th><th>Commande / client</th><th>Destination</th><th>Poids / volume</th><th>Véhicule requis</th><th>Actions</th></tr></thead><tbody>
                    @forelse($missions as $mission)
                        <tr data-mission-row="{{ $mission['id'] }}">
                            <td><input type="checkbox" data-select-mission="{{ $mission['id'] }}" @checked(in_array((int) $mission['id'], $selectedMissionIds, true)) aria-label="Sélectionner {{ $mission['reference'] }}"></td>
                            <td><strong class="ops-reference">{{ $mission['reference'] }}</strong></td>
                            <td><strong>{{ $mission['order'] }}</strong><small class="ops-table-subline">{{ $mission['client'] }}</small></td>
                            <td>{{ $mission['destination'] }}</td>
                            <td>{{ number_format($mission['weight'], 1, ',', ' ') }} kg<small class="ops-table-subline">{{ number_format($mission['volume'], 2, ',', ' ') }} m³</small></td>
                            <td><span class="ops-vehicle-inline"><x-operations.icon :name="$mission['vehicleCode']==='moto'?'moto':'truck'"/>{{ $mission['vehicle'] }}</span></td>
                            <td><a class="ops-more" href="{{ $mission['detailsUrl'] }}" aria-label="Ouvrir {{ $mission['reference'] }}"><x-operations.icon name="more"/></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ops-empty">Aucune commande réelle n’est actuellement prête pour une nouvelle tournée.</td></tr>
                    @endforelse
                    <tr hidden data-no-missions><td colspan="7" class="ops-empty">Aucune mission ne correspond aux filtres.</td></tr>
                </tbody></table></div>
                <div data-mission-inputs>@foreach($selectedMissions as $mission)@foreach($mission['itemIds'] as $id)<input type="hidden" name="mission_items[]" value="{{ $id }}">@endforeach @endforeach</div>
            </x-operations.panel>

            <x-operations.panel title="Ordre des collectes & livraisons" icon="order" id="stop-order" class="ops-route-editor-panel">
                <x-slot:actions>
                    <span class="ops-route-state" data-route-state>Parcours non calculé</span>
                    <button type="button" class="ops-button ops-button-primary ops-button-small" data-optimize><x-operations.icon name="route"/>Calculer & optimiser</button>
                    <button type="button" class="ops-button ops-button-small" data-edit-stops disabled><x-operations.icon name="swap"/>Modifier l’ordre</button>
                </x-slot:actions>
                <p class="ops-panel-caption">Le calcul utilise les coordonnées réelles du livreur, des boutiques et des clients. Les collectes restent toujours avant leurs livraisons associées.</p>
                <div class="ops-route-editor-toolbar" hidden data-manual-route-toolbar>
                    <span>Ordre manuel modifié : recalculez les distances et les ETA avant validation.</span>
                    <button type="button" class="ops-button ops-button-small" data-recalculate-order><x-operations.icon name="refresh"/>Recalculer les ETA</button>
                </div>
                <ol class="ops-stop-cards ops-stop-cards-editable" data-plan-stops><li class="ops-empty-route"><span>Aucun parcours calculé.</span><small>Sélectionnez les missions, le livreur et le véhicule, puis cliquez sur « Calculer & optimiser ».</small></li></ol>
                <div data-stop-order-inputs></div>
            </x-operations.panel>
        </div>

        <div class="ops-stack">
            <x-operations.panel title="Carte de la tournée" icon="map"><x-operations.map :tour="['stops'=>[], 'routeSegments'=>[], 'start'=>[]]" mode="create" :legend="true"/></x-operations.panel>

            <x-operations.panel title="Résumé du parcours" icon="chart" id="optimization" class="ops-real-route-summary">
                <x-slot:actions><span class="ops-badge" data-optimization-status>À calculer</span></x-slot:actions>
                <div class="ops-summary-grid ops-summary-grid-real">
                    @foreach([
                        ['tour',count($selectedMissionIds),'missions','blue','count'],
                        ['box',$initialProducts,'produits','blue','products'],
                        ['route','À calculer','distance routière','green','distance'],
                        ['clock','À calculer','durée routière','blue','duration'],
                        ['pie','—','remplissage véhicule','green','fill'],
                        ['target','—','moteur routier','blue','provider'],
                    ] as [$icon,$value,$label,$tone,$key])
                        <div><span class="ops-square-icon tone-{{ $tone }}"><x-operations.icon :name="$icon"/></span><p><strong data-summary="{{ $key }}">{{ $value }}</strong><small>{{ $label }}</small></p></div>
                    @endforeach
                </div>
            </x-operations.panel>

            <x-operations.panel title="Livreur, véhicule & chargement" icon="user" class="ops-plan-driver">
                <div class="ops-plan-driver-grid ops-real-plan-grid">
                    <div class="ops-real-driver-card"><span class="ops-avatar ops-avatar-large"><x-operations.icon name="user"/></span><div><strong data-plan-driver-name>Non affecté</strong><span class="ops-rating"><x-operations.icon name="star"/><span data-plan-driver-rating>—</span></span><small data-plan-driver-zone>Zone à confirmer</small><span class="ops-badge" data-plan-driver-status>Choisir un livreur</span></div><a class="ops-button ops-button-small" data-plan-driver-call href="#" hidden><x-operations.icon name="phone"/>Appeler</a></div>
                    <div class="ops-real-vehicle-card"><span class="ops-square-icon tone-blue"><x-operations.icon name="truck"/></span><div><small>Véhicule de flotte sélectionné</small><strong data-plan-vehicle-text>Non attribué</strong><span class="ops-badge" data-plan-vehicle-state>À sélectionner</span></div></div>
                    <div class="ops-plan-load"><strong>Chargement réel sélectionné</strong>
                        <div><x-operations.icon name="weight"/><p><span>Poids</span><b data-plan-load="weight">{{ number_format($initialWeight,1,',',' ') }} kg</b></p><div class="ops-progress"><i data-plan-load-bar="weight" style="width:0%"></i></div></div>
                        <div><x-operations.icon name="box"/><p><span>Volume</span><b data-plan-load="volume">{{ number_format($initialVolume,2,',',' ') }} m³</b></p><div class="ops-progress"><i data-plan-load-bar="volume" style="width:0%"></i></div></div>
                    </div>
                </div>
            </x-operations.panel>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script type="application/json" id="ops-plan-data">{!! json_encode([
    'missions' => $missions,
    'drivers' => $driverRows,
    'vehicles' => $vehicleRows,
    'vehicleRules' => $vehicleRules,
    'previewUrl' => route('logistics.tours.preview'),
], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endpush
