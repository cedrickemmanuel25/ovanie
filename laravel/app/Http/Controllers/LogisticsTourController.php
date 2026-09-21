<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryTour;
use App\Models\DeliveryTourStop;
use App\Models\LogisticsFleetVehicle;
use App\Models\OrderItem;
use App\Services\DeliveryScheduleService;
use App\Services\LogisticsTourPlanningService;
use App\Services\LogisticsPilotageDataService;
use App\Services\LogisticsVehicleResolver;
use App\Services\OrderWorkflowService;
use App\Services\OvanieShipmentConsolidationService;
use App\Support\LogisticsOperationalDataScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogisticsTourController extends Controller
{
    public function index(Request $request): View
    {
        $tours = $this->realToursQuery()->get();

        $stats = [
            'today' => $tours->filter(fn ($tour) => optional($tour->tour_date)->isToday())->count(),
            'in_progress' => $tours->where('status', 'in_progress')->count(),
            'planned' => $tours->where('status', 'planned')->count(),
            'late' => $tours->where('status', 'late')->count(),
            'deliveries_total' => $tours->sum(fn ($tour) => $this->deliveryStopCount($tour)),
        ];

        $requestedTourId = (int) $request->query('tour');
        $selected = $requestedTourId > 0
            ? $tours->firstWhere('id', $requestedTourId)
            : $tours->first();

        return view('logistics.tours.index', compact('tours', 'stats', 'selected'));
    }

    public function create(
        Request $request,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
        LogisticsVehicleResolver $vehicleResolver,
        LogisticsPilotageDataService $pilotage,
    ): View
    {
        $groups = $this->availableGroups($consolidation);

        // Une tournée OVANIE ne doit jamais être créée avec une moto. Le véhicule
        // affiché après choix du livreur est donc son véhicule de flotte réellement
        // attribué, disponible et au minimum de type Tricycle.
        $fleetVehicles = $this->fleetVehicles('available')
            ->map(fn ($row) => $planning->vehicleSnapshot($row))
            ->reject(fn (array $vehicle) => $this->normalizeVehicleCode($vehicle['type'] ?? '') === 'moto')
            ->values();

        $activeDrivers = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->where('onboarding_status', DeliveryDriver::ONBOARDING_ACTIVE)
            ->whereDoesntHave('activeAssignments', fn ($query) => LogisticsOperationalDataScope::assignments($query))
            ->with('currentLocation')
            ->withCount(['assignments' => fn ($query) => LogisticsOperationalDataScope::assignments($query)])
            ->orderByDesc('is_online')
            ->orderBy('name')
            ->get()
            ->map(function (DeliveryDriver $driver) use ($fleetVehicles) {
                $vehicle = $this->assignedTourVehicleForDriver($driver, $fleetVehicles);
                if (! $vehicle) {
                    return null;
                }

                $driver->setAttribute('tour_fleet_vehicle_id', $vehicle['id']);
                $driver->setAttribute('tour_vehicle_code', $vehicle['type_code']);
                $driver->setAttribute('tour_vehicle_label', $this->vehicleDisplayName($vehicle));
                $driver->setAttribute('tour_vehicle_registration', $vehicle['registration']);

                return $driver;
            })
            ->filter()
            ->values();

        // Le catalogue commence volontairement au Tricycle : une tournée peut
        // utiliser un véhicule plus grand si c'est celui du livreur sélectionné.
        $vehicleRules = collect($vehicleResolver->catalog())
            ->reject(fn (array $rule) => ($rule['code'] ?? null) === 'moto')
            ->values()
            ->all();

        $optimizationEnabled = $pilotage->settingEnabled('tour_optimization', true);

        return view('logistics.tours.create', compact('groups', 'activeDrivers', 'fleetVehicles', 'vehicleRules', 'optimizationEnabled'));
    }

    public function preview(
        Request $request,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
    ): JsonResponse {
        $data = $this->validatedPlanningPayload($request);
        [$driver, $vehicle, $groups, $departureAt] = $this->planningContext($data, $consolidation, $planning, true);

        $plan = $planning->plan(
            $groups,
            $driver,
            $vehicle,
            $departureAt,
            (bool) ($data['optimization_enabled'] ?? true),
            $data['stop_order'] ?? [],
        );

        return response()->json([
            'success' => true,
            'plan' => $this->previewPayload($plan),
            'vehicle' => $vehicle,
        ]);
    }

    public function store(
        Request $request,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
        DeliveryScheduleService $schedule,
        OrderWorkflowService $workflow,
    ): RedirectResponse {
        $data = $this->validatedPlanningPayload($request);
        [$driver, $vehicle, $groups, $departureAt] = $this->planningContext($data, $consolidation, $planning, true);

        $plan = $planning->plan(
            $groups,
            $driver,
            $vehicle,
            $departureAt,
            (bool) ($data['optimization_enabled'] ?? true),
            $data['stop_order'] ?? [],
        );

        if (! $plan['complete']) {
            throw ValidationException::withMessages([
                'mission_items' => 'La tournée ne peut pas être validée : au moins une boutique, une destination client ou un tronçon routier ne possède pas de données GPS exploitables. Corrigez les adresses puis relancez l’optimisation.',
            ]);
        }

        $tour = DB::transaction(function () use (
            $data,
            $driver,
            $vehicle,
            $groups,
            $departureAt,
            $plan,
            $planning,
            $schedule,
            $workflow,
        ) {
            $this->reserveFleetVehicle($vehicle, $driver);
            $this->synchronizeDriverVehicle($driver, $vehicle);

            $reference = $this->nextReference();
            $tour = DeliveryTour::create([
                'reference' => $reference,
                'driver_id' => $driver->id,
                'fleet_vehicle_id' => $vehicle['id'],
                'vehicle_code' => $this->normalizeVehicleCode($vehicle['type']),
                'vehicle_label' => $this->vehicleDisplayName($vehicle),
                'vehicle_plate' => $vehicle['registration'],
                'vehicle_capacity_kg' => $vehicle['capacity_kg'],
                'vehicle_volume_m3' => $vehicle['volume_m3'],
                'zone_label' => $data['zone_label'] ?? null,
                'status' => 'planned',
                'tour_date' => $departureAt->toDateString(),
                'departure_time' => $departureAt->format('H:i:s'),
                'optimization_enabled' => (bool) ($data['optimization_enabled'] ?? true),
            ]);

            $planning->persist($tour, $plan);
            $this->synchronizeMissionAssignments($groups, $driver, $vehicle, $plan, $schedule, $workflow, $reference);

            return $tour->fresh();
        }, 3);

        return redirect()
            ->route('logistics.tours.show', $tour)
            ->with('success', 'Tournée créée à partir des missions, du livreur, du véhicule et des itinéraires réels.');
    }

    public function show(DeliveryTour $tour): View
    {
        abort_if(str_starts_with((string) $tour->reference, 'TRN-DEMO-'), 404);
        $tour->load($this->tourRelations());

        return view('logistics.tours.show', compact('tour'));
    }

    public function start(DeliveryTour $tour): RedirectResponse
    {
        abort_if(str_starts_with((string) $tour->reference, 'TRN-DEMO-'), 404);
        $tour->load('driver');

        DB::transaction(function () use ($tour) {
            $tour->update(['status' => 'in_progress', 'started_at' => now()]);
            if ($tour->driver) {
                $tour->driver->forceFill(['status' => 'En mission'])->save();
            }
            if ($tour->fleet_vehicle_id && Schema::hasTable('logistics_fleet_vehicles')) {
                LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
                    ->whereKey($tour->fleet_vehicle_id)
                    ->update(['status' => 'mission']);
            }
        });

        return back()->with('success', 'Tournée démarrée avec le livreur et le véhicule affectés.');
    }

    public function reoptimize(
        DeliveryTour $tour,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
    ): RedirectResponse {
        abort_if(str_starts_with((string) $tour->reference, 'TRN-DEMO-'), 404);
        if ($tour->status !== 'planned') {
            throw ValidationException::withMessages([
                'route' => 'Une tournée déjà démarrée ne peut pas être réordonnée depuis cet écran. Modifiez uniquement les tournées planifiées.',
            ]);
        }
        $tour->load($this->tourRelations());
        [$groups, $driver, $vehicle, $departureAt] = $this->existingTourContext($tour, $consolidation, $planning);

        $plan = $planning->plan($groups, $driver, $vehicle, $departureAt, true);
        if (! $plan['complete']) {
            throw ValidationException::withMessages([
                'route' => 'Impossible de réoptimiser cette tournée : certaines coordonnées ou routes réelles sont indisponibles.',
            ]);
        }

        DB::transaction(function () use ($tour, $plan, $planning) {
            $tour->forceFill(['optimization_enabled' => true])->save();
            $planning->persist($tour, $plan);
        }, 3);

        return redirect()->route('logistics.tours.index', ['tour' => $tour->id])
            ->with('success', 'Parcours réoptimisé avec les données routières actuelles.');
    }

    public function reorder(
        Request $request,
        DeliveryTour $tour,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
    ): RedirectResponse {
        abort_if(str_starts_with((string) $tour->reference, 'TRN-DEMO-'), 404);
        if ($tour->status !== 'planned') {
            throw ValidationException::withMessages([
                'stop_order' => 'L’ordre des arrêts peut être modifié uniquement avant le démarrage de la tournée.',
            ]);
        }
        $data = $request->validate([
            'stop_order' => ['required', 'array', 'min:1', 'max:60'],
            'stop_order.*' => ['required', 'string', 'max:180', 'distinct'],
        ]);
        $tour->load($this->tourRelations());
        [$groups, $driver, $vehicle, $departureAt] = $this->existingTourContext($tour, $consolidation, $planning);

        $plan = $planning->plan($groups, $driver, $vehicle, $departureAt, false, $data['stop_order']);
        if (! $plan['complete']) {
            throw ValidationException::withMessages([
                'stop_order' => 'L’ordre a été reconnu, mais l’itinéraire réel ne peut pas être recalculé pour tous les arrêts.',
            ]);
        }

        DB::transaction(function () use ($tour, $plan, $planning) {
            $tour->forceFill(['optimization_enabled' => false])->save();
            $planning->persist($tour, $plan);
        }, 3);

        return redirect()->route('logistics.tours.index', ['tour' => $tour->id])
            ->with('success', 'Ordre des arrêts enregistré et distances/ETA recalculés.');
    }

    private function validatedPlanningPayload(Request $request): array
    {
        return $request->validate([
            'driver_id' => ['required', 'integer', 'exists:delivery_drivers,id'],
            'fleet_vehicle_id' => ['required', 'integer'],
            'tour_date' => ['required', 'date'],
            'departure_time' => ['required', 'date_format:H:i'],
            'zone_label' => ['nullable', 'string', 'max:120'],
            'optimization_enabled' => ['nullable', 'boolean'],
            'mission_items' => ['required', 'array', 'min:1', 'max:40'],
            'mission_items.*' => ['integer', 'distinct', 'exists:order_items,id'],
            'stop_order' => ['nullable', 'array', 'max:80'],
            'stop_order.*' => ['string', 'max:180', 'distinct'],
        ]);
    }

    /**
     * @return array{0:DeliveryDriver,1:array<string,mixed>,2:Collection,3:Carbon}
     */
    private function planningContext(
        array $data,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
        bool $requireAvailableVehicle,
    ): array {
        $driver = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->with('currentLocation')->findOrFail((int) $data['driver_id']);
        if (! $driver->is_active) {
            throw ValidationException::withMessages([
                'driver_id' => 'Ce livreur n’est plus actif.',
            ]);
        }

        $vehicle = $this->fleetVehicle((int) $data['fleet_vehicle_id'], $planning);
        if ($requireAvailableVehicle && $vehicle['status'] !== 'available') {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Ce véhicule n’est plus disponible. Actualisez la page et choisissez un autre véhicule.',
            ]);
        }
        $this->assertVehicleCanBeUsedByDriver($vehicle, $driver);

        $groups = $this->groupsForItems(collect($data['mission_items']), $consolidation);
        if ($driver->activeAssignments()->exists()) {
            throw ValidationException::withMessages([
                'driver_id' => 'Ce livreur possède déjà une mission active. Choisissez un livreur réellement disponible.',
            ]);
        }
        if ($groups->isEmpty()) {
            throw ValidationException::withMessages(['mission_items' => 'Aucune mission réelle et disponible n’a été trouvée.']);
        }
        $planning->assertVehicleCapacity($vehicle, $groups);
        $this->assertMandatoryVehicleType($vehicle, $groups);
        $this->assertAssignmentsCompatible($groups, $driver);

        $departureAt = Carbon::parse($data['tour_date'] . ' ' . $data['departure_time'])->startOfMinute();
        if ($departureAt->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages([
                'departure_time' => 'L’heure de départ doit être actuelle ou future.',
            ]);
        }

        return [$driver, $vehicle, $groups, $departureAt];
    }

    /** @return array{0:Collection,1:DeliveryDriver,2:array<string,mixed>,3:Carbon} */
    private function existingTourContext(
        DeliveryTour $tour,
        OvanieShipmentConsolidationService $consolidation,
        LogisticsTourPlanningService $planning,
    ): array {
        $driver = $tour->driver;
        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'Aucun livreur réel n’est lié à cette tournée.']);
        }
        $itemIds = $tour->stops->pluck('order_item_id')->filter()->unique()->values();
        $groups = $consolidation->groups(
            OrderItem::with(['order.client', 'product.shop', 'shipment', 'latestDeliveryAssignment.driver.currentLocation'])
                ->whereIn('id', $itemIds)
                ->whereHas('order', fn ($order) => LogisticsOperationalDataScope::orders($order))
                ->when(
                    Schema::hasColumn('order_items', 'delivery_provider'),
                    fn ($query) => $query->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE),
                    fn ($query) => $query->whereHas('product.shop', fn ($shop) => $shop->where('logistics_type', 'ovanie')),
                )
                ->get()
        );
        if ($groups->isEmpty()) {
            throw ValidationException::withMessages(['mission_items' => 'Les missions de cette tournée ne sont plus disponibles.']);
        }

        $vehicleId = (int) ($tour->fleet_vehicle_id ?: 0);
        if ($vehicleId <= 0 && Schema::hasTable('logistics_fleet_vehicles') && filled($tour->vehicle_plate)) {
            $vehicleId = (int) LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
                ->where('registration', $tour->vehicle_plate)
                ->value('id');
        }
        if ($vehicleId <= 0) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Aucun véhicule de flotte réel n’est lié à cette tournée. Sélectionnez le véhicule depuis Créer une tournée.',
            ]);
        }
        $vehicle = $this->fleetVehicle($vehicleId, $planning);
        $departureAt = Carbon::parse(($tour->tour_date?->toDateString() ?: now()->toDateString()) . ' ' . substr((string) $tour->departure_time, 0, 5));

        return [$groups, $driver, $vehicle, $departureAt];
    }

    private function availableGroups(OvanieShipmentConsolidationService $consolidation): Collection
    {
        $usedItemIds = DeliveryTourStop::query()
            ->whereHas('tour', fn ($query) => $query
                ->whereNotIn('status', ['done', 'cancelled'])
                ->where('reference', 'not like', 'TRN-DEMO-%'))
            ->pluck('order_item_id')
            ->filter()
            ->unique();

        $query = $this->deliveryItems();
        if ($usedItemIds->isNotEmpty()) {
            $query->whereNotIn('id', $usedItemIds);
        }

        return $consolidation->groups($query->get())
            ->where('status', OrderWorkflowService::DELIVERY_READY_FOR_PICKUP)
            ->values();
    }

    private function groupsForItems(Collection $ids, OvanieShipmentConsolidationService $consolidation): Collection
    {
        $ids = $ids->map(fn ($id) => (int) $id)->unique()->values();
        $available = $this->deliveryItems()->whereIn('id', $ids)->get();
        if ($available->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'mission_items' => 'Une ou plusieurs missions sélectionnées ne sont plus éligibles aux opérations logistiques.',
            ]);
        }

        $conflictingTourItems = DeliveryTourStop::query()
            ->whereIn('order_item_id', $ids)
            ->whereHas('tour', fn ($query) => $query
                ->whereNotIn('status', ['done', 'cancelled'])
                ->where('reference', 'not like', 'TRN-DEMO-%'))
            ->exists();
        if ($conflictingTourItems) {
            throw ValidationException::withMessages([
                'mission_items' => 'Une mission sélectionnée appartient déjà à une tournée active.',
            ]);
        }

        $groups = $consolidation->groups($available)
            ->where('status', OrderWorkflowService::DELIVERY_READY_FOR_PICKUP)
            ->values();
        $groupItemIds = $groups->flatMap(fn ($group) => $group['items']->pluck('id'))->map(fn ($id) => (int) $id)->unique()->sort()->values();
        if ($groupItemIds->all() !== $ids->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'mission_items' => 'La sélection contient une mission non prête ou déjà finalisée.',
            ]);
        }

        return $groups;
    }

    private function deliveryItems()
    {
        $query = OrderItem::with([
            'order.client',
            'product.shop',
            'shipment',
            'latestDeliveryAssignment.driver.currentLocation',
            'latestDeliveryAssignment.latestLocation',
        ]);

        if (Schema::hasColumn('order_items', 'delivery_provider')) {
            $query->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE);
        } else {
            $query->whereHas('product.shop', fn ($shop) => $shop->where('logistics_type', 'ovanie'));
        }

        return $query
            ->whereHas('order', function ($order) {
                LogisticsOperationalDataScope::orders($order);

                $order->where(function ($query) {
                    $query->whereIn('payment_status', ['paid', 'commission_paid'])
                        ->orWhere(function ($cod) {
                            $cod->where('payment_method', 'cash_on_delivery')
                                ->where('payment_status', 'pending')
                                ->whereIn('status', ['confirmed', 'processing', 'completed']);
                        });
                });
            })
            ->where('delivery_status', OrderWorkflowService::DELIVERY_READY_FOR_PICKUP)
            ->where(function ($query) {
                $query->where('delivery_price', '>', 0)
                    ->orWhereHas('order', fn ($order) => $order->where('delivery_pricing_status', 'calculated'));
            })
            ->whereDoesntHave('latestDeliveryAssignment', fn ($assignment) => $assignment->where('mission_number', 'like', 'SHP-DEMO-%'))
            ->latest('updated_at');
    }

    private function realToursQuery()
    {
        return DeliveryTour::query()
            ->where('reference', 'not like', 'TRN-DEMO-%')
            ->with($this->tourRelations())
            ->orderByDesc('tour_date')
            ->orderByDesc('departure_time')
            ->orderByDesc('id');
    }

    private function tourRelations(): array
    {
        return [
            'driver.currentLocation',
            'driver.assignments',
            'stops.orderItem.order.client',
            'stops.orderItem.product.shop',
            'stops.orderItem.shipment',
            'stops.orderItem.latestDeliveryAssignment.driver',
        ];
    }

    private function fleetVehicles(?string $status = null): Collection
    {
        if (! Schema::hasTable('logistics_fleet_vehicles')) {
            return collect();
        }

        $vehicles = LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
            ->whereNotIn('status', ['maintenance', 'out_of_service'])
            ->orderBy('vehicle_type')
            ->orderBy('registration')
            ->get();

        $activeVehicleIds = $this->activeFleetVehicleIds();
        foreach ($vehicles as $vehicle) {
            $vehicle->status = $activeVehicleIds->contains((int) $vehicle->id) ? 'mission' : 'available';
        }

        if ($status !== null) {
            $vehicles = $vehicles->where('status', $status)->values();
        }

        return $vehicles;
    }

    private function fleetVehicle(int $id, LogisticsTourPlanningService $planning): array
    {
        if (! Schema::hasTable('logistics_fleet_vehicles')) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'La flotte OVANIE n’est pas encore configurée dans la base de données.',
            ]);
        }
        $row = LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
            ->whereKey($id)
            ->first();
        if (! $row) {
            throw ValidationException::withMessages(['fleet_vehicle_id' => 'Le véhicule sélectionné n’existe plus.']);
        }

        $snapshot = $planning->vehicleSnapshot($row);
        $snapshot['status'] = $this->activeFleetVehicleIds()->contains((int) $row->id) ? 'mission' : $row->status;
        if (! in_array($snapshot['status'], ['maintenance', 'out_of_service', 'mission'], true)) {
            $snapshot['status'] = 'available';
        }

        return $snapshot;
    }

    private function assertVehicleCanBeUsedByDriver(array $vehicle, DeliveryDriver $driver): void
    {
        if ($this->normalizeVehicleCode($vehicle['type'] ?? '') === 'moto') {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Une moto ne peut pas être utilisée pour une tournée. Sélectionnez un livreur disposant au minimum d’un Tricycle.',
            ]);
        }

        $fleet = collect([$vehicle]);
        $assigned = $this->assignedTourVehicleForDriver($driver, $fleet);
        if (! $assigned || (int) $assigned['id'] !== (int) $vehicle['id']) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Le véhicule sélectionné n’est pas le véhicule de flotte réellement attribué à ce livreur.',
            ]);
        }
    }

    private function assertMandatoryVehicleType(array $vehicle, Collection $groups): void
    {
        $resolved = app(LogisticsVehicleResolver::class)->resolve((float) $groups->sum('weight'), (float) $groups->sum('volume'));
        $requiredCode = $resolved['code'] === 'moto' ? 'tricycle' : $resolved['code'];
        $requiredLabel = $resolved['code'] === 'moto' ? 'Tricycle' : $resolved['label'];
        $selectedCode = $this->normalizeVehicleCode($vehicle['type'] ?? '');

        $rank = [
            'moto' => 1,
            'tricycle' => 2,
            'pickup' => 3,
            'camion_3t' => 4,
            'camion_10t' => 5,
        ];

        if (($rank[$selectedCode] ?? 0) < ($rank[$requiredCode] ?? PHP_INT_MAX)) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Pour une tournée avec cette charge, le véhicule minimum est ' . $requiredLabel . '. Le véhicule du livreur sélectionné est insuffisant.',
            ]);
        }
    }

    /**
     * Retrouve le véhicule de flotte réellement lié au livreur. On privilégie
     * l'affectation explicite driver_name, puis l'immatriculation enregistrée
     * dans le profil/vehicle du livreur.
     *
     * @param Collection<int,array<string,mixed>> $vehicles
     * @return array<string,mixed>|null
     */
    private function assignedTourVehicleForDriver(DeliveryDriver $driver, Collection $vehicles): ?array
    {
        $driverName = mb_strtolower(trim((string) $driver->name));
        $explicit = $vehicles->first(function (array $vehicle) use ($driverName) {
            $assigned = mb_strtolower(trim((string) ($vehicle['driver_name'] ?? '')));

            return $assigned !== '' && $assigned === $driverName;
        });

        if ($explicit) {
            return $explicit;
        }

        $profile = is_array($driver->profile ?? null) ? $driver->profile : [];
        $references = collect([
            data_get($profile, 'vehicle_plate'),
            data_get($profile, 'registration'),
            data_get($profile, 'plate'),
            data_get($profile, 'vehicle.plate'),
            data_get($profile, 'vehicle.registration'),
            $driver->vehicle,
        ])->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)));

        return $vehicles->first(function (array $vehicle) use ($references) {
            $registration = mb_strtolower(trim((string) ($vehicle['registration'] ?? '')));
            if ($registration === '') {
                return false;
            }

            return $references->contains(fn (string $reference) => str_contains($reference, $registration));
        });
    }

    private function assertAssignmentsCompatible(Collection $groups, DeliveryDriver $driver): void
    {
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $assignment = $item->latestDeliveryAssignment;
                if ($item->delivery_status === OrderWorkflowService::DELIVERY_ASSIGNED
                    && $assignment?->driver_id
                    && (int) $assignment->driver_id !== (int) $driver->id) {
                    throw ValidationException::withMessages([
                        'driver_id' => 'La mission ' . ($group['mission_number'] ?? '#'.$item->id) . ' est déjà affectée à un autre livreur.',
                    ]);
                }
            }
        }
    }

    private function reserveFleetVehicle(array $vehicle, DeliveryDriver $driver): void
    {
        if ($this->activeFleetVehicleIds()->contains((int) $vehicle['id'])) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Le véhicule vient d’être réservé par une autre opération. Choisissez-en un autre.',
            ]);
        }

        $updated = LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
            ->whereKey($vehicle['id'])
            ->whereNotIn('status', ['maintenance', 'out_of_service'])
            ->update([
                'status' => 'mission',
                'driver_name' => $driver->name,
                'driver_phone' => $driver->phone,
            ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'fleet_vehicle_id' => 'Le véhicule n’est plus disponible. Actualisez la page et choisissez-en un autre.',
            ]);
        }
    }

    private function activeFleetVehicleIds(): Collection
    {
        if (! Schema::hasTable('delivery_assignments') || ! Schema::hasColumn('delivery_assignments', 'vehicle_id')) {
            return collect();
        }

        return LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
            ->whereNotNull('vehicle_id')
            ->whereIn('status', ['planned', 'assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])
            ->pluck('vehicle_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function synchronizeDriverVehicle(DeliveryDriver $driver, array $vehicle): void
    {
        $driver->forceFill([
            'vehicle' => $this->vehicleDisplayName($vehicle) . ' (' . $vehicle['registration'] . ')',
        ])->save();
    }

    private function synchronizeMissionAssignments(
        Collection $groups,
        DeliveryDriver $driver,
        array $vehicle,
        array $plan,
        DeliveryScheduleService $schedule,
        OrderWorkflowService $workflow,
        string $tourReference,
    ): void {
        foreach ($groups as $group) {
            $missionKey = 'mission:' . $group['representative']->id;
            $missionTimes = $plan['missionSchedule'][$missionKey] ?? null;
            if (! $missionTimes || ! $missionTimes['pickup_at'] || ! $missionTimes['delivery_at']) {
                throw ValidationException::withMessages([
                    'mission_items' => 'Les ETA réels n’ont pas pu être calculés pour la mission ' . $group['mission_number'] . '.',
                ]);
            }
            $pickupAt = Carbon::parse($missionTimes['pickup_at']);
            $deliveryAt = Carbon::parse($missionTimes['delivery_at']);

            $schedule->synchronizeMission(
                $group['items'],
                $driver->id,
                $driver->name,
                $driver->phone,
                $vehicle['registration'],
                $pickupAt,
                $deliveryAt,
                [
                    'mission_number' => $group['mission_number'],
                    'tour_reference' => $tourReference,
                    'tour_planning' => true,
                    'vehicle_code' => $this->normalizeVehicleCode($vehicle['type']),
                    'vehicle_label' => $this->vehicleDisplayName($vehicle),
                ],
            );

            foreach ($group['items'] as $index => $item) {
                $item->refresh();
                if ($item->delivery_status === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP) {
                    $assignment = $workflow->assignDriver($item, [
                        'driver_id' => $driver->id,
                        'vehicle_id' => $vehicle['id'],
                        'pickup_scheduled_at' => $pickupAt,
                        'meta' => [
                            'mission_number' => $group['mission_number'],
                            'tour_planning' => true,
                            'tour_reference' => $tourReference,
                            'suppress_notifications' => $index > 0,
                        ],
                        'suppress_notifications' => $index > 0,
                    ], auth()->user());
                    $assignment->forceFill([
                        'estimated_delivery_at' => $deliveryAt,
                        'vehicle_id' => $vehicle['id'],
                    ])->save();
                } elseif ($item->delivery_status === OrderWorkflowService::DELIVERY_ASSIGNED) {
                    $assignment = $item->latestDeliveryAssignment;
                    if ($assignment) {
                        $assignment->forceFill([
                            'driver_id' => $driver->id,
                            'vehicle_id' => $vehicle['id'],
                            'pickup_scheduled_at' => $pickupAt,
                            'estimated_delivery_at' => $deliveryAt,
                        ])->save();
                    }
                }
            }
        }
    }

    private function nextReference(): string
    {
        $next = ((int) DB::table('delivery_tours')->lockForUpdate()->max('id')) + 1;

        return 'TRN-' . now()->format('Y') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function deliveryStopCount(DeliveryTour $tour): int
    {
        return $tour->stops
            ->groupBy('sequence')
            ->filter(fn ($rows) => ($rows->first()->stop_type ?: 'delivery') === 'delivery')
            ->count();
    }

    private function vehicleDisplayName(array $vehicle): string
    {
        return collect([$vehicle['type'] ?? null, $vehicle['brand'] ?? null, $vehicle['model'] ?? null])
            ->filter()
            ->unique()
            ->implode(' ');
    }

    private function normalizeVehicleCode(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['-', ' ', 'é', 'è'], ['_', '_', 'e', 'e'], $value);

        return match (true) {
            str_contains($value, '10t') => 'camion_10t',
            str_contains($value, '3t') => 'camion_3t',
            str_contains($value, 'pickup'), str_contains($value, 'pick_up') => 'pickup',
            str_contains($value, 'tricycle') => 'tricycle',
            str_contains($value, 'moto') => 'moto',
            default => $value,
        };
    }

    private function previewPayload(array $plan): array
    {
        return [
            'start' => array_merge($plan['start'], ['kind' => 'driver', 'status' => 'start']),
            'stops' => collect($plan['stops'])->map(fn ($stop) => [
                'key' => $stop['key'],
                'type' => $stop['type'],
                'kind' => $stop['type'] === 'pickup' ? 'shop' : 'delivery',
                'status' => 'pending',
                'label' => $stop['label'],
                'address' => $stop['address'],
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
                'time' => $stop['estimatedArrivalLabel'],
                'distanceFromPrevious' => $stop['distanceFromPrevious'],
                'durationFromPrevious' => $stop['durationFromPrevious'],
            ])->values()->all(),
            'stopOrder' => $plan['stopOrder'],
            'routeSegments' => $plan['routeSegments'],
            'distance' => $plan['distance'],
            'duration' => $plan['duration'],
            'routingProvider' => $plan['routingProvider'],
            'complete' => $plan['complete'],
            'optimized' => $plan['optimized'],
            'weight' => $plan['weight'],
            'volume' => $plan['volume'],
            'products' => $plan['products'],
            'fillPercent' => $plan['fillPercent'],
        ];
    }
}
