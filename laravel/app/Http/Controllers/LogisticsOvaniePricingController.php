<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\DeliveryVehicleRateCard;
use App\Models\LogisticsPricingSimulation;
use App\Models\LogisticsPricingSupplement;
use App\Services\Geo\RoutingService;
use App\Services\LogisticsPricingWorkspaceService;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieDeliveryPriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $catalog = collect($this->vehicleResolver->catalog());
        $supplements = $this->workspace->supplements()->where('is_active', true)->values();

        return view('logistics.ovanie-pricing', [
            'rates' => $this->workspace->rateCards(),
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'supplements' => $supplements,
            'supplementCount' => $supplements->count(),
            'coveredCommuneCount' => $this->workspace->coveredCommunes()->count(),
            'activeVehicleCount' => $catalog->where('is_active', true)->count(),
            'pricedVehicleCount' => $this->pricedVehicleCodes()->count(),
        ]);
    }

    public function vehicles(): View
    {
        $catalog = collect($this->vehicleResolver->catalog());

        return view('logistics.pricing.vehicles', [
            'rates' => $this->workspace->rateCards(),
            'vehicles' => LogisticsPricingWorkspaceService::VEHICLES,
            'vehicleCatalog' => $catalog,
            'activeVehicleCount' => $catalog->where('is_active', true)->count(),
            'pricedVehicleCount' => $this->pricedVehicleCodes()->count(),
            'activeSupplementCount' => $this->workspace->supplements()->where('is_active', true)->count(),
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
            'pricedVehicleCount' => $this->pricedVehicleCodes()->count(),
            'simulationsToday' => $simulationsToday,
        ]);
    }

    /**
     * Enregistre les tarifs d'un véhicule utilisés par la formule :
     * base + distance × prix/km + poids × prix/kg + volume × prix/m³.
     */
    public function storeVehicleTariff(Request $request): RedirectResponse
    {
        $vehicles = LogisticsPricingWorkspaceService::VEHICLES;
        $data = $request->validate([
            'vehicle_code' => ['required', Rule::in(array_keys($vehicles))],
            'vehicle_label' => ['nullable', 'string', 'max:80'],
            'base_fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'price_per_km' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_per_kg' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_per_m3' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0.01'],
            'max_volume_m3' => ['nullable', 'numeric', 'min:0.0001'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ], [
            'base_fee.required' => 'Renseignez le tarif de base (0 si aucun).',
            'price_per_km.required' => 'Renseignez le prix par km (0 si aucun).',
            'price_per_kg.required' => 'Renseignez le prix par kg (0 si aucun).',
            'price_per_m3.required' => 'Renseignez le prix par m³ (0 si aucun).',
        ]);

        if ((bool) $data['is_active'] && (float) $data['base_fee'] + (float) $data['price_per_km']
            + (float) $data['price_per_kg'] + (float) $data['price_per_m3'] <= 0) {
            return back()->withErrors(['base_fee' => 'Un véhicule actif doit avoir au moins un tarif supérieur à 0.'])->withInput();
        }

        $vehicle = $vehicles[$data['vehicle_code']];

        DeliveryVehicleRateCard::query()->updateOrCreate(
            ['vehicle_code' => $data['vehicle_code']],
            [
                'vehicle_label' => ($data['vehicle_label'] ?? null) ?: $vehicle['label'],
                'min_weight_kg' => 0,
                'max_weight_kg' => $data['max_weight_kg'] ?? $vehicle['max_weight_kg'],
                'max_volume_m3' => $data['max_volume_m3'] ?? $vehicle['max_volume_m3'],
                'base_fee' => round((float) $data['base_fee'], 2),
                'price_per_km' => round((float) $data['price_per_km'], 2),
                'price_per_kg' => round((float) $data['price_per_kg'], 2),
                'price_per_m3' => round((float) $data['price_per_m3'], 2),
                // Formule stricte : ni minimum, ni plafond, ni marge.
                'handling_fee' => 0,
                'unloading_fee' => 0,
                'fragile_fee' => 0,
                'urgent_fee' => 0,
                'traffic_surcharge_fee' => 0,
                'intra_commune_min_fee' => 0,
                'min_fee' => null,
                'max_fee' => null,
                'margin_type' => 'none',
                'margin_value' => 0,
                'is_active' => (bool) $data['is_active'],
                'meta' => [
                    'description' => $data['description'] ?? null,
                    'source' => 'pricing_vehicle_page',
                    'pricing_mode' => 'distance_weight_volume_formula',
                ],
            ]
        );

        return back()->with('success', 'Tarifs du véhicule '.$vehicle['label'].' enregistrés.');
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
        if ($calculationType === 'percentage' && (float) $data['amount'] > 100) {
            return back()->withErrors(['amount' => 'Un supplément en pourcentage ne peut pas dépasser 100 %.'])->withInput();
        }
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
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
            'volume_m3' => ['required', 'numeric', 'min:0'],
            'fragile' => ['nullable', 'boolean'],
            'handling' => ['nullable', 'boolean'],
            'unloading' => ['nullable', 'boolean'],
            'urgency' => ['nullable', Rule::in(['standard', 'express', 'priority'])],
        ]);

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

        $route = $this->simulationRoute($origin, $destination, $data['distance_km'] ?? null);
        if (! $route) {
            return back()->withErrors(['simulation' => 'Distance impossible à déterminer : renseignez la distance en km.'])->withInput();
        }

        $options = [
            'fragile' => $request->boolean('fragile'),
            'handling' => $request->boolean('handling'),
            'unloading' => $request->boolean('unloading'),
            'urgency' => $data['urgency'] ?? 'standard',
        ];
        $pricingContext = [
            'fragile' => $options['fragile'],
            'handling' => $options['handling'],
            'unloading' => $options['unloading'],
            'urgent' => $options['urgency'] !== 'standard',
            'package_count' => 1,
        ];

        $pricing = $this->deliveryPriceCalculator->calculateMetrics($weight, $volume, $route, $pricingContext);

        if ((float) ($pricing['price'] ?? 0) <= 0) {
            return back()->withErrors(['simulation' => 'Le véhicule '.$vehicle['label'].' n’a pas encore de tarif (base, km, kg, m³). Renseignez-le dans Véhicules & tarifs.'])->withInput();
        }

        $durationMinutes = isset($pricing['duration_minutes']) ? (int) $pricing['duration_minutes'] : null;
        $routePoints = collect((array) data_get($route, 'route_geometry.coordinates', []))
            ->filter(fn ($point) => is_array($point) && count($point) >= 2)
            ->map(fn ($point) => [(float) $point[1], (float) $point[0]])
            ->values()->all();

        $priceLines = collect((array) ($pricing['components'] ?? []))
            ->filter(fn ($value) => abs((float) $value) > 0.0001)
            ->map(fn ($value, $key) => [
                'label' => $this->pricingComponentLabel((string) $key, $pricing),
                'value' => round((float) $value),
            ])
            ->values()
            ->all();

        $result = array_merge([
            'origin' => $origin->name,
            'destination' => $destination->name,
            'origin_commune_id' => $origin->id,
            'destination_commune_id' => $destination->id,
            'weight_kg' => $weight,
            'volume_m3' => $volume,
            'distance_input' => $data['distance_km'] ?? null,
            'vehicle_code' => $vehicleCode,
            'vehicle_label' => $vehicle['label'],
            'vehicle_image' => $vehicle['image'],
            'distance_km' => round((float) ($pricing['distance_km'] ?? 0), 1),
            'distance_source' => $route['source'] ?? null,
            'duration_minutes' => $durationMinutes,
            'duration' => $this->formatDuration($durationMinutes),
            'price_total' => (int) round((float) $pricing['price']),
            'pricing_source' => (string) ($pricing['rate_source'] ?? 'vehicle_rate_card'),
            'price_lines' => $priceLines,
            'route_points' => $routePoints,
            'alternatives' => $this->realAlternatives($vehicleCode, $weight, $volume, $route, $pricingContext),
        ], $options);

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
                'options' => $options,
            ]);
        }

        return redirect()->route('logistics.ovanie-pricing.simulator')->with('pricing_simulation', $result);
    }

    /**
     * Distance du simulateur : valeur saisie, sinon itinéraire routier entre
     * les centres des communes, sinon ligne droite (comme le checkout).
     */
    private function simulationRoute(AbidjanCommune $origin, AbidjanCommune $destination, mixed $manualKm): ?array
    {
        $empty = ['duration_minutes' => null, 'traffic_delay_minutes' => null, 'route_geometry' => null];

        if ($manualKm !== null && $manualKm !== '') {
            return $empty + ['success' => true, 'source' => 'manual', 'distance_km' => (float) $manualKm];
        }

        $hasCoordinates = $origin->latitude !== null && $origin->longitude !== null
            && $destination->latitude !== null && $destination->longitude !== null;
        if (! $hasCoordinates) {
            return null;
        }

        $route = $this->routing->route((float) $origin->latitude, (float) $origin->longitude, (float) $destination->latitude, (float) $destination->longitude);
        if ($route['success'] ?? false) {
            return $route + ['source' => $route['provider'] ?? 'routing'];
        }

        $lat1 = deg2rad((float) $origin->latitude);
        $lat2 = deg2rad((float) $destination->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $destination->longitude - (float) $origin->longitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $empty + ['success' => true, 'source' => 'haversine', 'distance_km' => round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2)];
    }

    public function export(Request $request)
    {
        $type = $request->string('type')->toString() ?: 'vehicles';
        if ($type === 'vehicles') {
            $rates = $this->workspace->rateCards()->keyBy('vehicle_code');
            $csv = "Véhicule;Tarif de base (FCFA);Prix/km;Prix/kg;Prix/m3;Charge max (kg);Volume max (m3);Statut\n";
            foreach ($this->vehicleResolver->catalog() as $row) {
                $rate = $rates->get($row['code']);
                $csv .= implode(';', [
                    $row['label'],
                    $rate?->base_fee ?? '',
                    $rate?->price_per_km ?? '',
                    $rate?->price_per_kg ?? '',
                    $rate?->price_per_m3 ?? '',
                    $row['max_weight_kg'] ?? '',
                    $row['max_volume_m3'] ?? '',
                    $row['is_active'] ? 'Actif' : 'Inactif',
                ])."\n";
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

    /**
     * Véhicules actifs disposant d'au moins un tarif de formule.
     */
    private function pricedVehicleCodes()
    {
        $rates = $this->workspace->rateCards()->keyBy('vehicle_code');

        return collect(array_keys(LogisticsPricingWorkspaceService::VEHICLES))
            ->filter(function (string $code) use ($rates) {
                $rate = $rates->get($code);

                return $rate && $rate->is_active && OvanieDeliveryPriceCalculator::hasFormulaRates($rate);
            })
            ->values();
    }

    private function realAlternatives(string $selected, float $weight, float $volume, array $route, array $pricingContext): array
    {
        $allowedCodes = collect($this->vehicleResolver->codesFrom($selected))
            ->reject(fn ($code) => $code === $selected);

        $items = [];
        foreach (LogisticsPricingWorkspaceService::VEHICLES as $code => $vehicle) {
            if (! $allowedCodes->contains($code)) {
                continue;
            }

            $pricing = $this->deliveryPriceCalculator->calculateMetrics($weight, $volume, $route, $pricingContext, null, null, $code);
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

    private function pricingComponentLabel(string $key, array $pricing = []): string
    {
        if (str_starts_with($key, 'supplement_')) {
            $code = substr($key, strlen('supplement_'));
            $supplement = $this->workspace->supplements()->firstWhere('code', $code);

            return 'Frais : '.($supplement?->label ?: str($code)->replace('_', ' ')->title());
        }

        $number = fn (float $value, int $decimals) => rtrim(rtrim(number_format($value, $decimals, ',', ' '), '0'), ',');

        return match ($key) {
            'base_fee' => 'Tarif de base',
            'distance_fee' => 'Distance ('.$number((float) ($pricing['distance_km'] ?? 0), 1).' km)',
            'weight_fee' => 'Poids ('.$number((float) ($pricing['weight_kg'] ?? 0), 2).' kg)',
            'volume_fee' => 'Volume ('.$number((float) ($pricing['volume_m3'] ?? 0), 3).' m³)',
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
