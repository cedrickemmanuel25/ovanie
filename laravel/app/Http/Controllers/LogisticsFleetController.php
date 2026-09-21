<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\LogisticsFleetVehicle;
use App\Models\LogisticsTerritoryZone;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogisticsFleetController extends Controller
{
    private const ACTIVE_ASSIGNMENT_STATUSES = [
        'planned', 'assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived',
    ];

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $allVehicles = Schema::hasTable('logistics_fleet_vehicles')
            ? LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
                ->orderBy('id')
                ->get()
            : collect();

        [$missionCounts, $activeMissionCounts, $activeAssignments] = $this->assignmentMetrics();
        $drivers = $this->realDrivers()->get();

        $this->decorateVehicles(
            $allVehicles,
            $missionCounts,
            $activeMissionCounts,
            $activeAssignments,
            $drivers->keyBy('id'),
            $drivers->keyBy(fn (DeliveryDriver $driver) => mb_strtolower(trim((string) $driver->name)))
        );

        $filtered = $allVehicles->filter(function (LogisticsFleetVehicle $vehicle) use ($request) {
            $search = mb_strtolower(trim((string) $request->query('q', '')));
            if ($search !== '' && ! str_contains(mb_strtolower(implode(' ', [
                $vehicle->registration,
                $vehicle->vehicle_type,
                $vehicle->brand,
                $vehicle->model,
                implode(' ', $vehicle->resolved_zones),
                $vehicle->resolved_driver_name,
            ])), $search)) {
                return false;
            }

            if ($request->filled('type') && $request->query('type') !== 'all' && $vehicle->vehicle_type !== $request->query('type')) {
                return false;
            }

            if ($request->filled('status') && $request->query('status') !== 'all' && $vehicle->effective_status !== $request->query('status')) {
                return false;
            }

            if ($request->filled('zone') && $request->query('zone') !== 'all' && ! in_array($request->query('zone'), $vehicle->resolved_zones, true)) {
                return false;
            }

            return true;
        })->values();

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($filtered) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Immatriculation', 'Type', 'Marque', 'Modèle', 'Statut', 'Chauffeur', 'Capacité kg', 'Zone', 'Missions']);
                foreach ($filtered as $vehicle) {
                    fputcsv($out, [
                        $vehicle->registration,
                        $vehicle->vehicle_type,
                        $vehicle->brand,
                        $vehicle->model,
                        $vehicle->effective_status,
                        $vehicle->resolved_driver_name,
                        $vehicle->capacityKg() ?? '',
                        implode(', ', $vehicle->resolved_zones),
                        $vehicle->real_mission_count,
                    ]);
                }
                fclose($out);
            }, 'flotte-ovanie.csv');
        }

        $perPage = max(5, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));
        $vehicles = new \Illuminate\Pagination\LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $counts = [
            'total' => $allVehicles->count(),
            'available' => $allVehicles->where('effective_status', 'available')->count(),
            'mission' => $allVehicles->where('effective_status', 'mission')->count(),
            'maintenance' => $allVehicles->where('effective_status', 'maintenance')->count(),
            'out_of_service' => $allVehicles->where('effective_status', 'out_of_service')->count(),
        ];
        $types = $allVehicles->groupBy('vehicle_type')->map->count()->sortDesc();
        $zones = $allVehicles->flatMap(fn ($vehicle) => $vehicle->resolved_zones)->filter()->unique()->sort()->values();
        $watchVehicles = $allVehicles
            ->whereIn('effective_status', ['mission', 'maintenance', 'out_of_service'])
            ->sortByDesc(fn ($vehicle) => $vehicle->active_mission_count)
            ->take(4)
            ->values();

        return view('logistics.fleet.index', compact(
            'vehicles', 'allVehicles', 'counts', 'types', 'zones', 'watchVehicles'
        ));
    }

    public function create(): View
    {
        $drivers = $this->realDrivers()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $zones = $this->operationalZoneNames();
        $vehicleRules = $this->vehicleRules();

        return view('logistics.fleet.create', compact('drivers', 'zones', 'vehicleRules'));
    }

    public function show(LogisticsFleetVehicle $vehicle): View
    {
        abort_unless($this->isOperationalVehicle($vehicle), 404);

        $assignmentQuery = null;
        $assignments = collect();
        $totalMissionCount = 0;
        $activeAssignment = null;

        if (Schema::hasTable('delivery_assignments') && Schema::hasColumn('delivery_assignments', 'vehicle_id')) {
            $assignmentQuery = LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
                ->where('vehicle_id', $vehicle->id);

            $totalMissionCount = (clone $assignmentQuery)->count();
            $activeAssignment = (clone $assignmentQuery)
                ->whereIn('status', self::ACTIVE_ASSIGNMENT_STATUSES)
                ->with(['driver.currentLocation', 'orderItem.order', 'orderItem.product'])
                ->latest('created_at')
                ->first();

            $assignments = (clone $assignmentQuery)
                ->with(['driver', 'orderItem.order', 'orderItem.product'])
                ->latest('created_at')
                ->limit(12)
                ->get();
        }

        $driver = $activeAssignment?->driver;
        $driverId = (int) data_get($vehicle->meta, 'driver_id', 0);
        if (! $driver && $driverId > 0) {
            $driver = $this->realDrivers()->with('currentLocation')->find($driverId);
        }
        if (! $driver && filled($vehicle->driver_name)) {
            $driver = $this->realDrivers()->with('currentLocation')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $vehicle->driver_name))])
                ->first();
        }

        $effectiveStatus = in_array($vehicle->status, ['maintenance', 'out_of_service'], true)
            ? $vehicle->status
            : ($activeAssignment ? 'mission' : 'available');

        $latestLocation = $driver?->currentLocation;
        $photoPath = $vehicle->photoPathForDriver($driver);
        $vehicleImage = $photoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($photoPath) : null;
        $registeredDriver = $driverId > 0 ? $this->realDrivers()->find($driverId) : null;
        $serviceZones = $vehicle->serviceZones($registeredDriver);

        return view('logistics.fleet.show', compact(
            'vehicle', 'driver', 'assignments', 'totalMissionCount', 'activeAssignment', 'effectiveStatus', 'latestLocation', 'vehicleImage', 'serviceZones', 'registeredDriver'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_type' => ['required', Rule::in(array_keys($this->vehicleRules()))],
            'registration' => 'required|string|max:60|unique:logistics_fleet_vehicles,registration',
            'brand' => 'required|string|max:80',
            'model' => 'required|string|max:80',
            'capacity_kg' => 'required|integer|min:1',
            'volume_m3' => 'nullable|numeric|min:0',
            'year' => 'required|integer|min:1990|max:2100',
            'zone' => 'nullable|string|max:100',
            'driver_id' => 'nullable|integer|exists:delivery_drivers,id',
            'status' => 'required|in:available,maintenance,out_of_service',
            'mileage_km' => 'nullable|integer|min:0',
            'observations' => 'nullable|string|max:500',
            'registration_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'insurance_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'technical_inspection' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'vehicle_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $rule = $this->vehicleRules()[$data['vehicle_type']];
        if ((int) $data['capacity_kg'] > (int) $rule['capacity']) {
            throw ValidationException::withMessages([
                'capacity_kg' => 'La capacité dépasse la limite OVANIE pour le type '.$data['vehicle_type'].' ('.number_format($rule['capacity'], 0, ',', ' ').' kg).',
            ]);
        }

        if (filled($data['zone']) && ! $this->operationalZoneNames()->contains($data['zone'])) {
            throw ValidationException::withMessages([
                'zone' => 'La zone sélectionnée n’est pas une zone de livraison OVANIE active.',
            ]);
        }

        $driver = null;
        if (filled($data['driver_id'])) {
            $driver = $this->realDrivers()->where('is_active', true)->find($data['driver_id']);
            if (! $driver) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Le livreur sélectionné n’est pas un livreur OVANIE actif.',
                ]);
            }

            $alreadyAssigned = LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
                ->get()
                ->first(function (LogisticsFleetVehicle $vehicle) use ($driver) {
                    return (int) data_get($vehicle->meta, 'driver_id', 0) === (int) $driver->id
                        || mb_strtolower(trim((string) $vehicle->driver_name)) === mb_strtolower(trim((string) $driver->name));
                });

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Ce livreur est déjà rattaché au véhicule '.$alreadyAssigned->registration.'.',
                ]);
            }
        }
        unset($data['driver_id']);

        $documents = [
            'registration' => [
                'label' => 'Carte grise',
                'path' => $request->file('registration_document')->store('private-documents/fleet', 'local'),
                'status' => 'À vérifier',
            ],
            'insurance' => [
                'label' => 'Assurance',
                'path' => $request->file('insurance_document')->store('private-documents/fleet', 'local'),
                'status' => 'À vérifier',
            ],
            'inspection' => [
                'label' => 'Visite technique',
                'path' => $request->file('technical_inspection')->store('private-documents/fleet', 'local'),
                'status' => 'À vérifier',
            ],
        ];

        $photoPath = $request->hasFile('vehicle_photo')
            ? $request->file('vehicle_photo')->store('fleet/vehicles', 'public')
            : null;

        DB::transaction(function () use ($data, $driver, $documents, $photoPath, $request) {
            $vehicle = LogisticsFleetVehicle::create([
                ...$data,
                'code' => $this->nextFleetCode(),
                'driver_name' => $driver?->name,
                'driver_phone' => $driver?->phone,
                'driver_email' => $driver?->email,
                'mission_count' => 0,
                'fuel_percent' => 100,
                'documents' => $documents,
                'mission_history' => [],
                'maintenance_history' => [],
                'features' => [
                    'refrigerated' => $request->boolean('refrigerated'),
                    'tail_lift' => $request->boolean('tail_lift'),
                    'btp_heavy' => $request->boolean('btp_heavy'),
                    'tour_ready' => $request->boolean('tour_ready'),
                    'returns_compatible' => $request->boolean('returns_compatible'),
                ],
                'maintenance_state' => $data['status'] === 'maintenance' ? 'Maintenance' : 'Bon état',
                'meta' => [
                    'driver_id' => $driver?->id,
                    'photo_path' => $photoPath,
                    'created_from' => 'logistics_fleet_form',
                    'data_source' => 'operationnelle',
                    'mileage_recorded' => isset($data['mileage_km']),
                ],
            ]);

            if ($driver) {
                $profile = is_array($driver->profile) ? $driver->profile : [];
                $profile['plate'] = $vehicle->registration;
                $profile['fleet_vehicle_id'] = $vehicle->id;
                $driver->forceFill([
                    'profile' => $profile,
                    'vehicle' => trim($vehicle->vehicle_type.' '.$vehicle->brand.' '.$vehicle->model).' ('.$vehicle->registration.')',
                ])->save();
            }
        });

        return redirect()->route('logistics.fleet')->with('success', 'Véhicule enregistré avec succès.');
    }

    private function assignmentMetrics(): array
    {
        if (! Schema::hasTable('delivery_assignments') || ! Schema::hasColumn('delivery_assignments', 'vehicle_id')) {
            return [collect(), collect(), collect()];
        }

        $base = LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
            ->whereNotNull('vehicle_id');

        $missionCounts = (clone $base)
            ->selectRaw('vehicle_id, COUNT(*) AS aggregate')
            ->groupBy('vehicle_id')
            ->pluck('aggregate', 'vehicle_id');

        $activeRows = (clone $base)
            ->whereIn('status', self::ACTIVE_ASSIGNMENT_STATUSES)
            ->with('driver')
            ->latest('created_at')
            ->get();

        $activeMissionCounts = $activeRows->groupBy('vehicle_id')->map->count();
        $activeAssignments = $activeRows->groupBy('vehicle_id')->map->first();

        return [$missionCounts, $activeMissionCounts, $activeAssignments];
    }

    private function decorateVehicles(
        Collection $vehicles,
        Collection $missionCounts,
        Collection $activeMissionCounts,
        Collection $activeAssignments,
        Collection $driversById,
        Collection $driversByName
    ): void {
        foreach ($vehicles as $vehicle) {
            $activeMissionCount = (int) ($activeMissionCounts[$vehicle->id] ?? 0);
            $realMissionCount = (int) ($missionCounts[$vehicle->id] ?? 0);
            $effectiveStatus = in_array($vehicle->status, ['maintenance', 'out_of_service'], true)
                ? $vehicle->status
                : ($activeMissionCount > 0 ? 'mission' : 'available');

            $activeAssignment = $activeAssignments->get($vehicle->id);
            $driver = $activeAssignment?->driver;
            $driverId = (int) data_get($vehicle->meta, 'driver_id', 0);

            if (! $driver && $driverId > 0) {
                $driver = $driversById->get($driverId);
            }
            if (! $driver && filled($vehicle->driver_name)) {
                $driver = $driversByName->get(mb_strtolower(trim((string) $vehicle->driver_name)));
            }

            $vehicle->setAttribute('effective_status', $effectiveStatus);
            $vehicle->setAttribute('resolved_zones', $vehicle->serviceZones($driversById->get($driverId)));
            $vehicle->setAttribute('real_mission_count', $realMissionCount);
            $vehicle->setAttribute('active_mission_count', $activeMissionCount);
            $vehicle->setAttribute('resolved_driver_name', $driver?->name ?: 'Non affecté');
            $vehicle->setAttribute('resolved_photo_path', $vehicle->photoPathForDriver($driver));
            $vehicle->setAttribute('resolved_driver_phone', $driver?->phone);
        }
    }

    private function realDrivers()
    {
        return LogisticsOperationalDataScope::drivers(DeliveryDriver::query());
    }

    private function operationalZoneNames(): Collection
    {
        if (! Schema::hasTable('logistics_territory_zones')) {
            return collect();
        }

        $query = LogisticsTerritoryZone::query()->where('is_active', true);
        if (Schema::hasColumn('logistics_territory_zones', 'source')) {
            $query->where(function ($zone) {
                $zone->whereNull('source')->orWhere('source', '!=', 'demo');
            });
        }

        return $query->orderBy('name')->pluck('name')->filter()->values();
    }

    private function isOperationalVehicle(LogisticsFleetVehicle $vehicle): bool
    {
        return LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
            ->whereKey($vehicle->getKey())
            ->exists();
    }

    private function nextFleetCode(): string
    {
        $next = LogisticsFleetVehicle::query()
            ->pluck('code')
            ->map(function ($code) {
                return preg_match('/^FLT-(\d+)$/', (string) $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max() + 1;

        do {
            $code = 'FLT-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (LogisticsFleetVehicle::query()->where('code', $code)->exists());

        return $code;
    }

    private function vehicleRules(): array
    {
        return [
            'Moto' => ['capacity' => 20, 'volume' => 0.18, 'image' => 'images/vendor/vehicles/moto.png'],
            'Tricycle' => ['capacity' => 250, 'volume' => 1.5, 'image' => 'images/vendor/vehicles/tricycle.png'],
            'Pickup' => ['capacity' => 1000, 'volume' => 7, 'image' => 'images/vendor/vehicles/pickup.png'],
            'Camion 3T' => ['capacity' => 3000, 'volume' => 20, 'image' => 'images/vendor/vehicles/camion-3t.png'],
            'Camion 10T' => ['capacity' => 10000, 'volume' => 60, 'image' => 'images/vendor/vehicles/camion-10t.png'],
        ];
    }
}
