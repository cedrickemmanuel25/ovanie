<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingMatrix;
use App\Models\LogisticsPricingSimulation;
use App\Models\LogisticsPricingSupplement;
use App\Services\Geo\RoutingService;
use App\Services\LogisticsPricingWorkspaceService;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieDeliveryPriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LogisticsOvaniePricingController extends Controller
{
    public function __construct(
        private readonly LogisticsPricingWorkspaceService $workspace,
        private readonly LogisticsVehicleResolver $vehicleResolver,
        private readonly RoutingService $routing,
        private readonly OvanieDeliveryPriceCalculator $deliveryPriceCalculator,
    ) {}

    public function index(): View
    {
        $rates = $this->workspace->rateCards();
        $supplements = $this->workspace->supplements()->where('is_active', true)->values();
        $zones = $this->workspace->zones();
        $coveredCommunes = $this->workspace->coveredCommunes();
        $matrices = $this->workspace->matrices()->where('is_active', true)->values();
        $matrixCoverage = $this->matrixCoverage($coveredCommunes, $matrices);
        $vehicleCatalog = collect($this->vehicleResolver->catalog());

        return view('logistics.ovanie-pricing', [
            'rates' => $rates,
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'supplements' => $supplements,
            'zoneCount' => $zones->count(),
            'coveredCommuneCount' => $coveredCommunes->count(),
            'supplementCount' => $supplements->count(),
            'matrixCount' => $matrices->count(),
            'matrixCoveragePercent' => $matrixCoverage['percent'],
            'matrixCoveredRouteCount' => $matrixCoverage['covered'],
            'matrixRequiredRouteCount' => $matrixCoverage['required'],
            'activeVehicleCount' => $vehicleCatalog->where('is_active', true)->count(),
            'configuredVehicleCount' => $vehicleCatalog->where('configured', true)->count(),
        ]);
    }

    public function vehicles(): View
    {
        $rates = $this->workspace->rateCards();
        $catalog = collect($this->vehicleResolver->catalog());

        return view('logistics.pricing.vehicles', [
            'rates' => $rates,
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'vehicleCatalog' => $catalog,
            'activeVehicleCount' => $catalog->where('is_active', true)->count(),
            'configuredVehicleCount' => $catalog->where('configured', true)->count(),
            'activeSupplementCount' => $this->workspace->supplements()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Liste globale des tarifs commune -> commune.
     *
     * Cette page est volontairement séparée de la saisie afin que l'équipe
     * logistique puisse auditer ce qui est configuré sans afficher en même
     * temps les 13 x 5 champs de modification d'une commune de départ.
     */
    public function communes(Request $request): View
    {
        $zones = $this->workspace->zones();
        $communes = $this->workspace->coveredCommunes();
        $matrices = $this->workspace->matrices();
        $activeVehicleCodes = $this->activeVehicleCodes();
        $relationOverview = $this->relationOverview($communes, $matrices, $zones, $activeVehicleCodes);

        $relationStatus = trim((string) $request->query('relation_status', 'configured'));
        $allowedStatuses = ['configured', 'covered', 'incomplete', 'unconfigured', 'inactive', 'all'];
        if (! in_array($relationStatus, $allowedStatuses, true)) {
            $relationStatus = 'configured';
        }

        $relationSearch = trim((string) $request->query('relation_search', ''));
        $listOriginZone = trim((string) $request->query('list_origin_zone', ''));
        $listDestinationZone = trim((string) $request->query('list_destination_zone', ''));
        $listOrigin = (int) $request->query('list_origin', 0);
        $listDestination = (int) $request->query('list_destination', 0);

        $relationRows = $relationOverview['rows']->filter(function (array $row) use ($relationStatus, $relationSearch, $listOriginZone, $listDestinationZone, $listOrigin, $listDestination) {
            $statusMatches = match ($relationStatus) {
                'configured' => in_array($row['status'], ['covered', 'incomplete', 'inactive'], true),
                'all' => true,
                default => $row['status'] === $relationStatus,
            };

            if (! $statusMatches) return false;
            if ($listOrigin > 0 && (int) $row['origin']->id !== $listOrigin) return false;
            if ($listDestination > 0 && (int) $row['destination']->id !== $listDestination) return false;
            if ($listOriginZone !== '' && ! in_array($listOriginZone, $row['origin_zone_codes'], true)) return false;
            if ($listDestinationZone !== '' && ! in_array($listDestinationZone, $row['destination_zone_codes'], true)) return false;

            if ($relationSearch !== '') {
                $haystack = mb_strtolower(
                    $row['origin']->name.' '.$row['destination']->name.' '
                    .implode(' ', $row['origin_zone_names']).' '
                    .implode(' ', $row['destination_zone_names'])
                );
                if (! str_contains($haystack, mb_strtolower($relationSearch))) return false;
            }

            return true;
        })->values();

        return view('logistics.pricing.communes', [
            'zones' => $zones,
            'communes' => $communes,
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'activeVehicleCodes' => $activeVehicleCodes,
            'coverage' => $relationOverview['coverage'],
            'relationStats' => $relationOverview['stats'],
            'relationRows' => $relationRows,
            'relationStatus' => $relationStatus,
            'relationSearch' => $relationSearch,
            'listOriginZone' => $listOriginZone,
            'listDestinationZone' => $listDestinationZone,
            'listOrigin' => $listOrigin,
            'listDestination' => $listDestination,
        ]);
    }

    /**
     * Saisie en masse des tarifs d'une seule commune de départ.
     * Les zones et communes sont toujours lues depuis Territoire.
     */
    public function configureCommunes(Request $request): View
    {
        $zones = $this->workspace->zones();
        $communes = $this->workspace->coveredCommunes();
        $matrices = $this->workspace->matrices();
        $activeVehicleCodes = $this->activeVehicleCodes();
        $relationOverview = $this->relationOverview($communes, $matrices, $zones, $activeVehicleCodes);

        $requestedZone = trim((string) $request->query('origin_zone', ''));
        $originZone = $zones->firstWhere('code', $requestedZone) ?: $zones->first();
        $coveredIds = $communes->pluck('id')->map(fn ($id) => (int) $id);
        $originCommunes = $originZone
            ? $originZone->communes
                ->filter(fn (AbidjanCommune $commune) => $commune->is_active && $coveredIds->contains((int) $commune->id))
                ->values()
            : $communes;

        if ($originCommunes->isEmpty()) {
            $originCommunes = $communes;
        }

        $requestedOriginId = (int) $request->query('origin', 0);
        $selectedOrigin = $originCommunes->firstWhere('id', $requestedOriginId)
            ?: $communes->firstWhere('id', $requestedOriginId)
            ?: $originCommunes->first()
            ?: $communes->first();

        // Une commune transmise dans l'URL peut appartenir à une autre zone :
        // la zone visible est alors réalignée sur la source Territoire.
        if ($selectedOrigin && (! $originZone || ! $originZone->communes->contains('id', $selectedOrigin->id))) {
            $originZone = $zones->first(fn ($zone) => $zone->communes->contains('id', $selectedOrigin->id)) ?: $originZone;
            if ($originZone) {
                $originCommunes = $originZone->communes
                    ->filter(fn (AbidjanCommune $commune) => $commune->is_active && $coveredIds->contains((int) $commune->id))
                    ->values();
            }
        }

        $matrixByDestination = collect();
        if ($selectedOrigin) {
            $matrixByDestination = $matrices
                ->filter(fn (LogisticsPricingMatrix $row) => mb_strtolower(trim($row->origin_commune)) === mb_strtolower(trim($selectedOrigin->name)))
                ->keyBy(fn (LogisticsPricingMatrix $row) => mb_strtolower(trim($row->destination_commune)));
        }

        $zoneNamesByCommune = $relationOverview['zone_names_by_commune'];
        $zoneCodesByCommune = $relationOverview['zone_codes_by_commune'];

        $destinationRows = $communes->map(function (AbidjanCommune $commune) use ($matrixByDestination, $zoneNamesByCommune, $zoneCodesByCommune) {
            $matrix = $matrixByDestination->get(mb_strtolower(trim($commune->name)));

            return [
                'commune' => $commune,
                'matrix' => $matrix,
                'zone_names' => $zoneNamesByCommune[(int) $commune->id] ?? [],
                'zone_codes' => $zoneCodesByCommune[(int) $commune->id] ?? [],
            ];
        })->values();

        $selectedOriginRows = $selectedOrigin
            ? $relationOverview['rows']->filter(fn (array $row) => (int) $row['origin']->id === (int) $selectedOrigin->id)
            : collect();

        $originProgress = [
            'total' => $selectedOriginRows->count(),
            'configured' => $selectedOriginRows->filter(fn (array $row) => in_array($row['status'], ['covered', 'incomplete', 'inactive'], true))->count(),
            'covered' => $selectedOriginRows->where('status', 'covered')->count(),
            'incomplete' => $selectedOriginRows->where('status', 'incomplete')->count(),
            'inactive' => $selectedOriginRows->where('status', 'inactive')->count(),
            'unconfigured' => $selectedOriginRows->where('status', 'unconfigured')->count(),
        ];
        $originProgress['percent'] = $originProgress['total'] > 0
            ? (int) round($originProgress['covered'] / $originProgress['total'] * 100)
            : 0;

        return view('logistics.pricing.communes-configure', [
            'zones' => $zones,
            'communes' => $communes,
            'originZone' => $originZone,
            'originCommunes' => $originCommunes,
            'selectedOrigin' => $selectedOrigin,
            'destinationRows' => $destinationRows,
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'activeVehicleCodes' => $activeVehicleCodes,
            'originProgress' => $originProgress,
            'highlightDestination' => (int) $request->query('destination', 0),
        ]);
    }

    public function supplements(): View
    {
        $supplements = $this->workspace->supplements();

        return view('logistics.pricing.supplements', [
            'supplements' => $supplements,
            'activePercent' => $supplements->isNotEmpty()
                ? (int) round($supplements->where('is_active', true)->count() / $supplements->count() * 100)
                : 0,
            'averageSupplementPercent' => $this->workspace->averageSupplementPercent(),
            'categoryCount' => $supplements->pluck('category')->filter()->unique()->count(),
        ]);
    }

    public function simulator(): View
    {
        $simulationsToday = Schema::hasTable('logistics_pricing_simulations')
            ? LogisticsPricingSimulation::query()->whereDate('created_at', today())->count()
            : 0;

        return view('logistics.pricing.simulator', [
            'result' => session('pricing_simulation'),
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'communes' => $this->workspace->coveredCommunes(),
            'activeSupplementCount' => $this->workspace->supplements()->where('is_active', true)->where('automatic', true)->count(),
            'configuredRateCount' => $this->activeVehicleCodes()->count(),
            'activeMatrixCount' => $this->workspace->matrices()->where('is_active', true)->count(),
            'simulationsToday' => $simulationsToday,
            'coveredCommuneCount' => $this->workspace->coveredCommunes()->count(),
        ]);
    }

    public function storeVehicleTariff(Request $request): RedirectResponse
    {
        $vehicles = LogisticsPricingWorkspaceService::VEHICLES;
        $data = $request->validate([
            'vehicle_code' => ['required', Rule::in(array_keys($vehicles))],
            'vehicle_label' => ['nullable', 'string', 'max:80'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0.01'],
            'max_volume_m3' => ['nullable', 'numeric', 'min:0.0001'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $vehicle = $vehicles[$data['vehicle_code']];

        // Cette table reste utilisée comme configuration de capacité/statut afin
        // de préserver la compatibilité du projet. Les anciens champs monétaires
        // ne sont plus édités ici et ne participent pas au prix commune -> commune.
        DeliveryVehicleRateCard::query()->updateOrCreate(
            ['vehicle_code' => $data['vehicle_code']],
            [
                'vehicle_label' => ($data['vehicle_label'] ?? null) ?: $vehicle['label'],
                'min_weight_kg' => 0,
                'max_weight_kg' => $data['max_weight_kg'] ?? $vehicle['max_weight_kg'],
                'max_volume_m3' => $data['max_volume_m3'] ?? $vehicle['max_volume_m3'],
                'is_active' => (bool) $data['is_active'],
                'meta' => [
                    'description' => $data['description'] ?? null,
                    'source' => 'pricing_vehicle_capacity_page',
                    'pricing_mode' => 'commune_matrix',
                ],
            ]
        );

        return back()->with('success', 'Véhicule et capacités enregistrés.');
    }

    public function storeMatrix(Request $request): RedirectResponse
    {
        $vehicles = LogisticsPricingWorkspaceService::VEHICLES;
        $data = $request->validate([
            'origin_commune' => ['required', 'string', 'max:120'],
            'destination_commune' => ['required', 'string', 'max:120'],
            'vehicle_prices' => ['required', 'array'],
            'vehicle_prices.*' => ['nullable', 'integer', 'min:0'],
            'apply_reverse' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->assertCoveredCommune($data['origin_commune']);
        $this->assertCoveredCommune($data['destination_commune']);

        $prices = [];
        foreach (array_keys($vehicles) as $code) {
            $prices[$code] = max(0, (int) round((float) data_get($data, 'vehicle_prices.'.$code, 0)));
        }
        abort_unless(collect($prices)->contains(fn ($price) => $price > 0), 422, 'Renseignez au moins un prix véhicule pour ce trajet.');

        $payload = [
            'region' => 'Abidjan',
            'vehicle_prices' => $prices,
            'estimated_delays' => [],
            'rules' => ['pricing_mode' => 'fixed_commune_vehicle'],
            'description' => null,
            'is_active' => $request->boolean('is_active', true),
            'meta' => ['created_from' => 'pricing_communes_page'],
        ];

        LogisticsPricingMatrix::query()->updateOrCreate(
            ['region' => 'Abidjan', 'origin_commune' => $data['origin_commune'], 'destination_commune' => $data['destination_commune']],
            $payload
        );

        if ($data['origin_commune'] !== $data['destination_commune'] && $request->boolean('apply_reverse')) {
            LogisticsPricingMatrix::query()->updateOrCreate(
                ['region' => 'Abidjan', 'origin_commune' => $data['destination_commune'], 'destination_commune' => $data['origin_commune']],
                array_merge($payload, ['meta' => ['created_from' => 'pricing_communes_page', 'mirrored_from' => $data['origin_commune'].' -> '.$data['destination_commune']]])
            );
        }

        return back()->with('success', 'Tarif du trajet enregistré.');
    }

    public function storeMatrixBulk(Request $request): RedirectResponse
    {
        $vehicleCodes = array_keys(LogisticsPricingWorkspaceService::VEHICLES);
        $data = $request->validate([
            'origin_commune_id' => ['required', 'integer'],
            'origin_zone' => ['nullable', 'string', 'max:80'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'array'],
            'prices.*.*' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'array'],
            'active.*' => ['nullable', 'boolean'],
            'mirror' => ['nullable', 'array'],
            'mirror.*' => ['nullable', 'boolean'],
        ]);

        $covered = $this->workspace->coveredCommunes()->keyBy(fn (AbidjanCommune $commune) => (int) $commune->id);
        $origin = $covered->get((int) $data['origin_commune_id']);
        abort_unless($origin, 422, 'La commune de départ doit appartenir à une zone active de Territoire.');

        $pricesInput = (array) ($data['prices'] ?? []);
        $activeInput = (array) ($data['active'] ?? []);
        $mirrorInput = (array) ($data['mirror'] ?? []);
        $saved = 0;
        $removed = 0;
        $mirrored = 0;

        DB::transaction(function () use ($covered, $origin, $vehicleCodes, $pricesInput, $activeInput, $mirrorInput, &$saved, &$removed, &$mirrored) {
            foreach ($covered as $destinationId => $destination) {
                $submitted = (array) ($pricesInput[(string) $destinationId] ?? $pricesInput[$destinationId] ?? []);
                $prices = [];
                foreach ($vehicleCodes as $code) {
                    $prices[$code] = max(0, (int) round((float) ($submitted[$code] ?? 0)));
                }

                $hasPrice = collect($prices)->contains(fn ($price) => $price > 0);
                $query = LogisticsPricingMatrix::query()->where([
                    'region' => 'Abidjan',
                    'origin_commune' => $origin->name,
                    'destination_commune' => $destination->name,
                ]);

                if (! $hasPrice) {
                    $removed += $query->delete();
                    if ($origin->id !== $destination->id && (bool) ($mirrorInput[(string) $destinationId] ?? $mirrorInput[$destinationId] ?? false)) {
                        $removed += LogisticsPricingMatrix::query()->where([
                            'region' => 'Abidjan',
                            'origin_commune' => $destination->name,
                            'destination_commune' => $origin->name,
                        ])->delete();
                    }
                    continue;
                }

                $isActive = (bool) ($activeInput[(string) $destinationId] ?? $activeInput[$destinationId] ?? false);
                $payload = [
                    'vehicle_prices' => $prices,
                    'estimated_delays' => [],
                    'rules' => ['pricing_mode' => 'fixed_commune_vehicle'],
                    'description' => null,
                    'is_active' => $isActive,
                    'meta' => ['created_from' => 'pricing_communes_bulk_table'],
                ];

                LogisticsPricingMatrix::query()->updateOrCreate(
                    ['region' => 'Abidjan', 'origin_commune' => $origin->name, 'destination_commune' => $destination->name],
                    $payload
                );
                $saved++;

                if ($origin->id !== $destination->id && (bool) ($mirrorInput[(string) $destinationId] ?? $mirrorInput[$destinationId] ?? false)) {
                    LogisticsPricingMatrix::query()->updateOrCreate(
                        ['region' => 'Abidjan', 'origin_commune' => $destination->name, 'destination_commune' => $origin->name],
                        array_merge($payload, [
                            'meta' => [
                                'created_from' => 'pricing_communes_bulk_table',
                                'mirrored_from' => $origin->name.' -> '.$destination->name,
                            ],
                        ])
                    );
                    $mirrored++;
                }
            }
        });

        $activeVehicleCodes = $this->activeVehicleCodes();
        $originMatrices = LogisticsPricingMatrix::query()
            ->where('region', 'Abidjan')
            ->where('origin_commune', $origin->name)
            ->get();

        $complete = 0;
        $incomplete = 0;
        foreach ($originMatrices as $matrix) {
            $prices = (array) $matrix->vehicle_prices;
            $positive = $activeVehicleCodes->filter(fn ($code) => (float) ($prices[$code] ?? 0) > 0)->count();
            if ($positive === 0) continue;
            if ($matrix->is_active && $activeVehicleCodes->isNotEmpty() && $positive === $activeVehicleCodes->count()) {
                $complete++;
            } else {
                $incomplete++;
            }
        }

        if ($saved === 0 && $removed === 0) {
            $message = 'Aucun trajet tarifé : renseignez au moins un montant avant d’enregistrer.';
        } else {
            $message = $saved.' trajet(s) enregistré(s) pour '.$origin->name.' : '.$complete.' complet(s), '.$incomplete.' à compléter';
            if ($mirrored > 0) $message .= ', '.$mirrored.' trajet(s) inverse(s) copiés';
            if ($removed > 0) $message .= ', '.$removed.' ligne(s) vide(s) supprimée(s)';
            $message .= '.';
        }

        return redirect()->route('logistics.ovanie-pricing.communes.configure', [
            'origin_zone' => $data['origin_zone'] ?? null,
            'origin' => $origin->id,
        ])->with('success', $message);
    }

    public function storeSupplement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplement_id' => ['nullable', 'integer', 'exists:logistics_pricing_supplements,id'],
            'label' => ['required', 'string', 'max:140'],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'conditions' => ['required', 'array', 'min:1'],
            'compatible_with_others' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['draft', 'create'])],
        ]);

        $allowedConditions = [
            'express' => ['label' => 'Livraison urgente / express', 'category' => 'Urgence'],
            'fragile' => ['label' => 'Produit fragile', 'category' => 'Fragile'],
            'handling' => ['label' => 'Manutention spéciale', 'category' => 'Manutention'],
            'unloading' => ['label' => 'Déchargement requis', 'category' => 'Déchargement'],
            'bulky' => ['label' => 'Colis volumineux', 'category' => 'Volume'],
            'traffic' => ['label' => 'Trafic routier', 'category' => 'Trafic'],
        ];

        $conditions = collect(array_keys((array) ($data['conditions'] ?? [])))
            ->filter(fn ($key) => array_key_exists($key, $allowedConditions))
            ->values();

        if ($conditions->isEmpty()) {
            return back()->withErrors(['conditions' => 'Choisissez au moins une condition d’application.'])->withInput();
        }

        $supplement = isset($data['supplement_id'])
            ? LogisticsPricingSupplement::query()->findOrFail((int) $data['supplement_id'])
            : null;

        $code = $supplement?->code;
        if (! $code) {
            $code = str($data['label'])->ascii()->slug('_')->lower()->limit(52, '')->toString() ?: 'supplement';
            $baseCode = $code;
            $counter = 2;
            while (LogisticsPricingSupplement::query()->where('code', $code)->exists()) {
                $code = $baseCode.'_'.$counter++;
            }
        }

        $primaryCondition = $conditions->first();
        $category = $allowedConditions[$primaryCondition]['category'] ?? 'Livraison';
        $conditionLabel = $conditions
            ->map(fn ($key) => $allowedConditions[$key]['label'] ?? str($key)->replace('_', ' ')->title())
            ->implode(' / ');

        $calculationType = $data['calculation_type'];
        $amount = $calculationType === 'fixed'
            ? (int) round((float) $data['amount'])
            : round((float) $data['amount'], 2);

        $payload = [
            'code' => $code,
            'label' => $data['label'],
            'category' => $category,
            'calculation_type' => $calculationType,
            'amount' => $amount,
            'scope' => 'Par livraison',
            'condition_label' => $conditionLabel,
            'description' => null,
            'conditions' => $conditions->mapWithKeys(fn ($key) => [$key => true])->all(),
            'minimum_threshold' => 0,
            'compatible_with_others' => $request->boolean('compatible_with_others'),
            'automatic' => true,
            'is_active' => ($data['action'] ?? 'create') === 'draft' ? false : $request->boolean('is_active'),
            'meta' => ['created_from' => 'pricing_supplements_page'],
        ];

        if ($supplement) {
            $supplement->update($payload);
        } else {
            LogisticsPricingSupplement::query()->create($payload);
        }

        return back()->with('success', $supplement ? 'Supplément mis à jour.' : 'Nouveau supplément créé.');
    }
    public function calculate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'origin_commune_id' => ['required', 'integer'],
            'destination_commune_id' => ['required', 'integer'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
            'volume_m3' => ['required', 'numeric', 'min:0'],
            'fragile' => ['nullable', 'boolean'],
            'handling' => ['nullable', 'boolean'],
            'unloading' => ['nullable', 'boolean'],
            'urgency' => ['nullable', Rule::in(['standard', 'express', 'priority'])],
        ]);

        $coveredIds = $this->workspace->coveredCommunes()->pluck('id');
        abort_unless($coveredIds->contains((int) $data['origin_commune_id']) && $coveredIds->contains((int) $data['destination_commune_id']), 422, 'Le trajet doit utiliser deux communes couvertes par OVANIE Logistics.');

        $origin = AbidjanCommune::query()->findOrFail((int) $data['origin_commune_id']);
        $destination = AbidjanCommune::query()->findOrFail((int) $data['destination_commune_id']);
        $weight = (float) $data['weight_kg'];
        $volume = (float) $data['volume_m3'];
        $vehicleResolved = $this->vehicleResolver->resolve($weight, $volume);
        if (! ($vehicleResolved['is_active'] ?? true)) {
            return back()->withErrors(['simulation' => 'Aucun véhicule actif n’est compatible avec ce poids et ce volume.'])->withInput();
        }
        $vehicleCode = $vehicleResolved['code'];
        $vehicle = LogisticsPricingWorkspaceService::VEHICLES[$vehicleCode];

        $route = null;
        if ($origin->latitude !== null && $origin->longitude !== null && $destination->latitude !== null && $destination->longitude !== null) {
            $candidate = $this->routing->route((float) $origin->latitude, (float) $origin->longitude, (float) $destination->latitude, (float) $destination->longitude);
            if ($candidate['success'] ?? false) {
                $route = $candidate;
            }
        }

        $pricingContext = [
            'origin_commune' => $origin->name,
            'destination_commune' => $destination->name,
            'destination_city' => 'Abidjan',
            'delivery_zone' => $destination->name,
            'fragile' => $request->boolean('fragile'),
            'handling' => $request->boolean('handling'),
            'unloading' => $request->boolean('unloading'),
            'urgent' => ($data['urgency'] ?? 'standard') !== 'standard',
            'package_count' => 1,
        ];

        $fixedRelation = $this->deliveryPriceCalculator->fixedDestinationRelation($pricingContext, $vehicleCode);
        if (! $route && ! $fixedRelation) {
            return back()->withErrors(['simulation' => 'Aucun tarif de trajet n’est configuré pour cette relation de communes et ce véhicule.'])->withInput();
        }

        $pricing = $this->deliveryPriceCalculator->calculateMetrics(
            $weight,
            $volume,
            $route ?: [],
            $pricingContext,
        );

        if ((float) ($pricing['price'] ?? 0) <= 0) {
            return back()->withErrors(['simulation' => 'Aucun tarif opérationnel n’est disponible pour ce trajet et le véhicule recommandé.'])->withInput();
        }

        $durationMinutes = isset($pricing['duration_minutes']) && $pricing['duration_minutes'] !== null
            ? (int) $pricing['duration_minutes']
            : null;
        $routePoints = collect((array) data_get($route ?: [], 'route_geometry.coordinates', []))
            ->filter(fn ($point) => is_array($point) && count($point) >= 2)
            ->map(fn ($point) => [(float) $point[1], (float) $point[0]])
            ->values()->all();

        $priceLines = collect((array) ($pricing['components'] ?? []))
            ->filter(fn ($value) => abs((float) $value) > 0.0001)
            ->map(fn ($value, $key) => [
                'label' => $this->pricingComponentLabel((string) $key),
                'value' => round((float) $value),
            ])
            ->values()
            ->all();

        $result = [
            'origin' => $origin->name,
            'destination' => $destination->name,
            'origin_commune_id' => $origin->id,
            'destination_commune_id' => $destination->id,
            'weight_kg' => $weight,
            'volume_m3' => $volume,
            'fragile' => $request->boolean('fragile'),
            'handling' => $request->boolean('handling'),
            'unloading' => $request->boolean('unloading'),
            'urgency' => $data['urgency'] ?? 'standard',
            'vehicle_code' => $vehicleCode,
            'vehicle_label' => $vehicle['label'],
            'vehicle_image' => $vehicle['image'],
            'distance_km' => $route ? round((float) ($pricing['distance_km'] ?? $route['distance_km']), 1) : null,
            'duration_minutes' => $durationMinutes,
            'duration' => $this->formatDuration($durationMinutes),
            'price_total' => (int) round((float) $pricing['price']),
            'pricing_source' => (string) ($pricing['rate_source'] ?? 'vehicle_rate_card'),
            'price_lines' => $priceLines,
            'route_points' => $routePoints,
            'alternatives' => $this->realAlternatives($vehicleCode, $weight, $volume, $origin->name, $destination->name, $route, [
                'fragile' => $request->boolean('fragile'),
                'handling' => $request->boolean('handling'),
                'unloading' => $request->boolean('unloading'),
                'urgency' => $data['urgency'] ?? 'standard',
                'weight_kg' => $weight,
                'volume_m3' => $volume,
                'package_count' => 1,
            ]),
        ];

        if (Schema::hasTable('logistics_pricing_simulations')) {
            LogisticsPricingSimulation::query()->create([
                'user_id' => $request->user()?->id,
                'origin_commune_id' => $origin->id,
                'destination_commune_id' => $destination->id,
                'origin_label' => $origin->name,
                'destination_label' => $destination->name,
                'vehicle_code' => $vehicleCode,
                'weight_kg' => $weight,
                'volume_m3' => $volume,
                'distance_km' => $result['distance_km'],
                'duration_minutes' => $durationMinutes,
                'price_total' => $result['price_total'],
                'pricing_source' => $result['pricing_source'],
                'components' => $pricing['components'] ?? [],
                'options' => [
                    'fragile' => $request->boolean('fragile'),
                    'handling' => $request->boolean('handling'),
                    'unloading' => $request->boolean('unloading'),
                    'urgency' => $data['urgency'] ?? 'standard',
                ],
            ]);
        }

        return redirect()->route('logistics.ovanie-pricing.simulator')->with('pricing_simulation', $result);
    }

    public function export(Request $request)
    {
        $type = $request->string('type')->toString() ?: 'vehicles';
        if ($type === 'vehicles') {
            $rows = collect($this->vehicleResolver->catalog());
            $csv = "Véhicule;Charge maximale (kg);Volume maximal (m3);Statut;Configuration\n";
            foreach ($rows as $row) {
                $csv .= implode(';', [
                    $row['label'],
                    $row['max_weight_kg'] ?? '',
                    $row['max_volume_m3'] ?? '',
                    $row['is_active'] ? 'Actif' : 'Inactif',
                    $row['configured'] ? 'Personnalisée' : 'Valeurs OVANIE par défaut',
                ])."\n";
            }
        } elseif ($type === 'communes') {
            $rows = $this->workspace->matrices();
            $csv = "Départ;Destination;Moto;Tricycle;Pickup;Camion 3T;Camion 10T;Statut\n";
            foreach ($rows as $row) {
                $prices = (array) $row->vehicle_prices;
                $csv .= implode(';', [$row->origin_commune, $row->destination_commune, $prices['moto'] ?? 0, $prices['tricycle'] ?? 0, $prices['pickup'] ?? 0, $prices['camion_3t'] ?? 0, $prices['camion_10t'] ?? 0, $row->is_active ? 'Actif' : 'Inactif'])."\n";
            }
        } elseif ($type === 'supplements') {
            $rows = $this->workspace->supplements();
            $csv = "Libellé;Catégorie;Montant;Portée;Condition;Statut\n";
            foreach ($rows as $row) {
                $csv .= implode(';', [$row->label, $row->category, $row->amount, $row->scope, str_replace(';', ',', (string) $row->condition_label), $row->is_active ? 'Actif' : 'Inactif'])."\n";
            }
        } else {
            abort(404);
        }

        return Response::make("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tarification-ovanie-'.$type.'.csv"',
        ]);
    }

    private function assertCoveredCommune(string $name): void
    {
        abort_unless(
            $this->workspace->coveredCommunes()->contains(fn (AbidjanCommune $commune) => mb_strtolower($commune->name) === mb_strtolower($name)),
            422,
            'La commune sélectionnée n’est pas dans une zone de livraison OVANIE active.'
        );
    }

    private function relationOverview($communes, $matrices, $zones, $activeVehicleCodes): array
    {
        $zoneNamesByCommune = [];
        $zoneCodesByCommune = [];
        $coveredIds = $communes->pluck('id')->map(fn ($id) => (int) $id);

        foreach ($zones as $zone) {
            foreach ($zone->communes as $commune) {
                $communeId = (int) $commune->id;
                if (! $commune->is_active || ! $coveredIds->contains($communeId)) {
                    continue;
                }
                $zoneNamesByCommune[$communeId] ??= [];
                $zoneCodesByCommune[$communeId] ??= [];
                $zoneNamesByCommune[$communeId][] = $zone->name;
                $zoneCodesByCommune[$communeId][] = $zone->code;
                $zoneNamesByCommune[$communeId] = array_values(array_unique($zoneNamesByCommune[$communeId]));
                $zoneCodesByCommune[$communeId] = array_values(array_unique($zoneCodesByCommune[$communeId]));
            }
        }

        $matrixMap = $matrices->keyBy(function (LogisticsPricingMatrix $row) {
            return mb_strtolower(trim($row->origin_commune)).'|'.mb_strtolower(trim($row->destination_commune));
        });

        $rows = collect();
        $stats = [
            'required' => $communes->count() * $communes->count(),
            'configured' => 0,
            'covered' => 0,
            'incomplete' => 0,
            'inactive' => 0,
            'unconfigured' => 0,
        ];

        foreach ($communes as $origin) {
            foreach ($communes as $destination) {
                $key = mb_strtolower(trim($origin->name)).'|'.mb_strtolower(trim($destination->name));
                $matrix = $matrixMap->get($key);
                $prices = (array) ($matrix?->vehicle_prices ?? []);
                $positiveCount = $activeVehicleCodes->filter(fn ($code) => (float) ($prices[$code] ?? 0) > 0)->count();
                $hasAnyPrice = collect($prices)->contains(fn ($price) => (float) $price > 0);

                if (! $matrix || ! $hasAnyPrice) {
                    $status = 'unconfigured';
                } elseif (! (bool) $matrix->is_active) {
                    $status = 'inactive';
                } elseif ($activeVehicleCodes->isNotEmpty() && $positiveCount === $activeVehicleCodes->count()) {
                    $status = 'covered';
                } else {
                    $status = 'incomplete';
                }

                if ($hasAnyPrice) {
                    $stats['configured']++;
                }
                $stats[$status]++;

                $rows->push([
                    'origin' => $origin,
                    'destination' => $destination,
                    'matrix' => $matrix,
                    'prices' => $prices,
                    'status' => $status,
                    'positive_vehicle_count' => $positiveCount,
                    'required_vehicle_count' => $activeVehicleCodes->count(),
                    'origin_zone_names' => $zoneNamesByCommune[(int) $origin->id] ?? [],
                    'origin_zone_codes' => $zoneCodesByCommune[(int) $origin->id] ?? [],
                    'destination_zone_names' => $zoneNamesByCommune[(int) $destination->id] ?? [],
                    'destination_zone_codes' => $zoneCodesByCommune[(int) $destination->id] ?? [],
                ]);
            }
        }

        $stats['percent'] = $stats['required'] > 0
            ? (int) round($stats['covered'] / $stats['required'] * 100)
            : 0;

        return [
            'rows' => $rows,
            'stats' => $stats,
            'coverage' => [
                'required' => $stats['required'],
                'covered' => $stats['covered'],
                'percent' => $stats['percent'],
            ],
            'zone_names_by_commune' => $zoneNamesByCommune,
            'zone_codes_by_commune' => $zoneCodesByCommune,
        ];
    }

    private function matrixCoverage($communes, $matrices): array
    {
        $names = $communes->pluck('name')->values();
        $required = $names->count() * $names->count();
        if ($required === 0) {
            return ['required' => 0, 'covered' => 0, 'percent' => 0];
        }

        $activeVehicleCodes = $this->activeVehicleCodes();
        $covered = 0;
        foreach ($names as $origin) {
            foreach ($names as $destination) {
                $exists = $matrices->contains(function (LogisticsPricingMatrix $row) use ($origin, $destination, $activeVehicleCodes) {
                    $exact = mb_strtolower($row->origin_commune) === mb_strtolower($origin)
                        && mb_strtolower($row->destination_commune) === mb_strtolower($destination);
                    if (! $exact || $activeVehicleCodes->isEmpty()) return false;
                    $prices = (array) $row->vehicle_prices;
                    return $activeVehicleCodes->every(fn ($code) => (float) ($prices[$code] ?? 0) > 0);
                });
                if ($exists) $covered++;
            }
        }

        return ['required' => $required, 'covered' => $covered, 'percent' => (int) round($covered / $required * 100)];
    }

    private function activeVehicleCodes()
    {
        $rates = $this->workspace->rateCards()->keyBy('vehicle_code');

        return collect(array_keys(LogisticsPricingWorkspaceService::VEHICLES))
            ->filter(function (string $code) use ($rates) {
                $rate = $rates->get($code);
                return ! $rate || (bool) $rate->is_active;
            })
            ->values();
    }

    private function realAlternatives(
        string $selected,
        float $weight,
        float $volume,
        string $origin,
        string $destination,
        ?array $route,
        array $context = []
    ): array {
        $allowedCodes = collect($this->vehicleResolver->codesFrom($selected))
            ->reject(fn ($code) => $code === $selected);

        $pricingContext = [
            'origin_commune' => $origin,
            'destination_commune' => $destination,
            'destination_city' => 'Abidjan',
            'delivery_zone' => $destination,
            'fragile' => (bool) ($context['fragile'] ?? false),
            'handling' => (bool) ($context['handling'] ?? false),
            'unloading' => (bool) ($context['unloading'] ?? false),
            'urgent' => ($context['urgency'] ?? 'standard') !== 'standard',
            'package_count' => max(1, (int) ($context['package_count'] ?? 1)),
        ];

        $items = [];
        foreach (LogisticsPricingWorkspaceService::VEHICLES as $code => $vehicle) {
            if (! $allowedCodes->contains($code)) {
                continue;
            }

            $pricing = $this->deliveryPriceCalculator->calculateMetrics(
                $weight,
                $volume,
                $route ?: [],
                $pricingContext,
                null,
                null,
                $code,
            );

            $price = (float) ($pricing['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $items[] = [
                'code' => $code,
                'label' => (string) ($pricing['vehicle_label'] ?? $vehicle['label']),
                'capacity' => $vehicle['capacity'],
                'image' => $vehicle['image'],
                'price' => (int) round($price),
            ];
        }

        return collect($items)->sortBy('price')->values()->all();
    }

    private function pricingComponentLabel(string $key): string
    {
        if (str_starts_with($key, 'supplement_')) {
            $code = substr($key, strlen('supplement_'));
            $supplement = $this->workspace->supplements()->firstWhere('code', $code);
            return 'Supplément : '.($supplement?->label ?: str($code)->replace('_', ' ')->title());
        }

        return match ($key) {
            'commune_route_fee' => 'Tarif du trajet commune → commune',
            'base_fee' => 'Tarif de base',
            'distance_fee' => 'Distance',
            'weight_fee' => 'Poids',
            'volume_fee' => 'Volume',
            'ovanie_margin' => 'Marge logistique',
            default => str($key)->replace('_', ' ')->title()->toString(),
        };
    }

    private function formatDuration(?int $minutes): string
    {
        if (! $minutes) return 'Non disponible';
        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;
        if ($hours === 0) return $remain.' min';
        return $hours.'h'.($remain > 0 ? ' '.str_pad((string) $remain, 2, '0', STR_PAD_LEFT) : '');
    }
}
