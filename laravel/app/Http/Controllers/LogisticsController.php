<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReturnModel;
use App\Models\AbidjanCommune;
use App\Models\DeliveryDriver;
use App\Models\DeliveryRouteCache;
use App\Models\Shipment;
use App\Models\Shop;
use App\Services\OrderWorkflowService;
use App\Services\OrderSettlementService;
use App\Services\LoyaltyService;
use App\Services\ClientDeliveryGroupService;
use App\Services\DeliveryGroupCollectionService;
use App\Services\OvanieShipmentConsolidationService;
use App\Services\DeliveryScheduleService;
use App\Services\LogisticsShipmentWorkflowService;
use App\Services\LogisticsMissionMonitoringService;
use App\Services\Geo\MissionRoutingService;
use App\Services\Geo\RoutingService;
use App\Services\ReturnRefundService;
use App\Services\VehicleAppearanceService;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LogisticsController extends Controller
{
    public function dashboard(Request $request, OvanieShipmentConsolidationService $consolidation, LogisticsMissionMonitoringService $monitor)
    {
        $monitor->run();
        $orders = $this->orderBase()
            ->whereHas('items', function ($itemQuery) {
                $this->onlyOvanieItems($itemQuery)
                    ->where(function ($statusQuery) {
                        $statusQuery->whereNull('delivery_status')
                            ->orWhereIn('delivery_status', [
                                OrderWorkflowService::DELIVERY_PENDING,
                                OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                            ]);
                    });
            })
            ->latest()
            ->take(5)
            ->get();
        $items = $this->deliveryItems()->take(40)->get();
        $drivers = $this->storedDrivers($items);
        $stats = $this->stats();
        $shipmentGroups = $consolidation->groups($this->deliveryItems()->get());
        $missionCounts = $consolidation->counts($shipmentGroups);

        // Correction logistique 02 : l'espace Logistique supervise le flux
        // automatique. Il ne présente plus des missions "à affecter" : une
        // mission est soit à proposer automatiquement, en attente
        // d'acceptation, réservée pendant la préparation vendeur, prête pour
        // collecte, en collecte ou en livraison.
        $missionsWaitingAcceptance = $shipmentGroups
            ->filter(fn (array $group) => in_array((string) ($group['operational_phase'] ?? ''), ['to_offer', 'waiting_acceptance'], true))
            ->take(8)
            ->values();
        $missionsAcceptedWaitingVendor = $shipmentGroups
            ->where('operational_phase', 'accepted_waiting_vendor')
            ->take(8)
            ->values();
        $missionsReadyForPickup = $shipmentGroups
            ->where('operational_phase', 'ready_for_pickup')
            ->take(8)
            ->values();
        $missionsEnRoute = $shipmentGroups
            ->filter(fn (array $group) => in_array((string) ($group['operational_phase'] ?? ''), ['collecting', 'in_delivery'], true))
            ->take(8)
            ->values();
        $enRouteMissionsCount = ($missionCounts['collecting'] ?? 0) + ($missionCounts['in_delivery'] ?? 0);
        $mapMissionGroups = $shipmentGroups
            ->reject(fn (array $group) => in_array((string) ($group['operational_phase'] ?? ''), ['delivered'], true))
            ->values();

        $driversTotalCount = $this->realDriversQuery()->where('is_active', true)->count();
        $driversOnlineCount = $this->realDriversQuery()->gpsOnline()->count();
        $driversBusyCount = $drivers->where('status', 'En livraison')->count();
        $driversAvailableCount = max(0, $driversOnlineCount - $driversBusyCount);
        $driversOfflineCount = max(0, $driversTotalCount - $driversOnlineCount);

        $incidentsOpenCount = $this->realIncidentsQuery()->whereNotIn('status', ['resolved', 'closed'])->count();
        $deliveredTodayCount = (clone $this->deliveryItems()->getQuery())
            ->where('delivery_status', OrderWorkflowService::DELIVERY_DELIVERED)
            ->whereDate('vendor_delivery_updated_at', today())
            ->count();

        $recentIncidents = $this->realIncidentsQuery()->with(['order'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest()
            ->take(3)
            ->get();
        $recentReturns = $this->realReturnsQuery()->whereIn('status', [
                \App\Models\ReturnModel::STATUS_PENDING,
            ])
            ->latest()
            ->take(3)
            ->get();

        $ovanieShops = $this->ovanieShopMarkers();

        // Présence GPS réellement fraîche destinée uniquement à la carte de l'accueil.
        // Une authentification seule ne suffit pas : gpsOnline() exige une position récente.
        $vehicleAppearanceService = app(VehicleAppearanceService::class);
        $dashboardGpsDrivers = $this->realDriversQuery()
            ->gpsOnline()
            ->with(['currentLocation', 'activeAssignments'])
            ->orderBy('name')
            ->get()
            ->map(function (DeliveryDriver $driver) use ($vehicleAppearanceService) {
                $location = $driver->currentLocation;
                $status = mb_strtolower(trim((string) $driver->status));
                $busy = $driver->activeAssignments->isNotEmpty()
                    || in_array($status, ['en mission', 'en livraison', 'occupé', 'occupe'], true);
                $available = ! $busy && in_array($status, ['disponible', 'available'], true);
                $appearance = $vehicleAppearanceService->forDriver($driver, false, false);
                $vehicleKey = match ($appearance['type_code']) {
                    'camion_10t' => 'truck10',
                    'camion_3t' => 'truck3',
                    default => $appearance['type_code'],
                };
                $vehicle = $appearance['type_label'];

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'vehicle' => $vehicle,
                    'vehicle_key' => $vehicleKey,
                    'vehicle_plate' => $appearance['plate'],
                    'vehicle_color' => $appearance['color'],
                    'vehicle_color_hex' => $appearance['color_hex'],
                    'vehicle_photo_url' => $appearance['photo_url'],
                    'vehicle_photo_is_real' => $appearance['photo_is_real'],
                    'vehicle_reference_asset_url' => $appearance['reference_photo_url'],
                    'fleet_vehicle_code' => $appearance['fleet_code'],
                    'zone' => $driver->interventionZonesLabel(2),
                    'status' => $busy ? 'mission' : ($available ? 'available' : 'unavailable'),
                    'status_label' => $busy ? 'En mission' : ($available ? 'Disponible' : 'Indisponible'),
                    'lat' => $location?->latitude,
                    'lng' => $location?->longitude,
                    'accuracy' => $location?->accuracy,
                    'speed' => $location?->speed,
                    'heading' => $location?->heading,
                    'recorded_at' => optional($location?->recorded_at)->toIso8601String(),
                    'tracking_url' => route('logistics.tracking.driver', ['driver' => $driver->id]),
                ];
            })
            ->filter(fn (array $driver) => is_numeric($driver['lat']) && is_numeric($driver['lng']))
            ->values();

        $sellerTrackingRows = app(\App\Services\SellerDeliverySupervisionService::class)->trackingRows();

        return view('logistics.dashboard', compact(
            'orders',
            'items',
            'drivers',
            'stats',
            'missionCounts',
            'missionsWaitingAcceptance',
            'missionsAcceptedWaitingVendor',
            'missionsReadyForPickup',
            'missionsEnRoute',
            'enRouteMissionsCount',
            'mapMissionGroups',
            'driversTotalCount',
            'driversOnlineCount',
            'driversBusyCount',
            'driversAvailableCount',
            'driversOfflineCount',
            'incidentsOpenCount',
            'deliveredTodayCount',
            'recentIncidents',
            'recentReturns',
            'ovanieShops',
            'dashboardGpsDrivers',
            'sellerTrackingRows'
        ));
    }

    public function dashboardLive(Request $request)
    {
        $activeDrivers = $this->realDriversQuery()
            ->where('is_active', true)
            ->with(['currentLocation', 'activeAssignments'])
            ->orderBy('name')
            ->get();

        $rows = $activeDrivers->map(fn (DeliveryDriver $driver) => \App\ViewModels\LogisticsTrackingData::driver($driver));
        $online = $rows->where('online', true)->values();
        $busy = $online->where('availability', 'En mission')->count();
        $available = $online->where('availability', 'Disponible')->count();
        $unavailable = $online->where('availability', 'Indisponible')->count();

        return response()->json([
            'counts' => [
                'online' => $online->count(),
                'total' => $rows->count(),
                'busy' => $busy,
                'available' => $available,
                'unavailable' => $unavailable,
                'offline' => max(0, $rows->count() - $online->count()),
            ],
            'drivers' => $rows->take(5)->values()->all(),
            'map_drivers' => $online->filter(fn (array $driver) => is_numeric($driver['lat']) && is_numeric($driver['lng']))->map(fn (array $driver) => [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'vehicle' => $driver['vehicle'],
                'vehicle_key' => match ($driver['vehicleCode'] ?? '') {
                    'truck_10t', 'camion_10t' => 'truck10',
                    'truck_3t', 'camion_3t' => 'truck3',
                    default => $driver['vehicleCode'] ?? 'vehicle',
                },
                'vehicle_plate' => $driver['vehiclePlate'],
                'vehicle_color' => $driver['vehicleColor'],
                'vehicle_color_hex' => $driver['vehicleColorHex'],
                'vehicle_photo_url' => $driver['vehiclePhotoUrl'],
                'vehicle_photo_is_real' => $driver['vehiclePhotoIsReal'],
                'vehicle_reference_asset_url' => $driver['vehicleReferenceAssetUrl'],
                'fleet_vehicle_code' => $driver['fleetVehicleCode'],
                'zone' => $driver['zone'],
                'status' => $driver['availabilityTone'] === 'mission' ? 'mission' : ($driver['availability'] === 'Disponible' ? 'available' : 'unavailable'),
                'status_label' => $driver['availability'],
                'lat' => $driver['lat'],
                'lng' => $driver['lng'],
                'accuracy' => $driver['accuracy'],
                'speed' => $driver['speed'],
                'heading' => $driver['heading'],
                'recorded_at' => $driver['recordedAt'],
                'tracking_url' => $driver['trackingUrl'],
            ])->values()->all(),
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function shipments(Request $request, OvanieShipmentConsolidationService $consolidation, LogisticsMissionMonitoringService $monitor)
    {
        $monitor->run();
        $allGroups = $consolidation->groups($this->deliveryItems()->get());
        $counts = $consolidation->counts($allGroups);
        $groups = $consolidation->filter(
            $allGroups,
            $request->query('q'),
            $request->query('destination'),
            $request->query('status', 'all'),
            $request->query('vehicle') ?: null,
            $request->query('driver_id') ? (int) $request->query('driver_id') : null
        );

        $perPage = max(5, min(100, (int) $request->query('per_page', 100)));
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $shipments = new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $destinations = $allGroups
            ->map(fn (array $g) => $g['order']?->delivery_commune ?: $g['order']?->delivery_zone)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $vehicles = collect(OvanieShipmentConsolidationService::vehicleOptions());

        $incidentsOpenCount = $this->realIncidentsQuery()->whereNotIn('status', ['resolved', 'closed'])->count();
        $latestOpenIncident = $this->realIncidentsQuery()->with(['order', 'orderItem.latestDeliveryAssignment'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest()
            ->first();

        $driversOnlineCount = $this->realDriversQuery()->gpsOnline()->count();
        $driversTotalCount = $this->realDriversQuery()->where('is_active', true)->count();
        $driversBusyCount = $this->realDriversQuery()->gpsOnline()->where('status', 'En livraison')->count();
        $driversAvailableCount = max(0, $driversOnlineCount - $driversBusyCount);
        $driversOfflineCount = max(0, $driversTotalCount - $driversOnlineCount);

        return view('logistics.shipments', [
            'shipments' => $shipments,
            'counts' => $counts,
            'destinations' => $destinations,
            'vehicles' => $vehicles,
            'incidentsOpenCount' => $incidentsOpenCount,
            'latestOpenIncident' => $latestOpenIncident,
            'driversOnlineCount' => $driversOnlineCount,
            'driversTotalCount' => $driversTotalCount,
            'driversBusyCount' => $driversBusyCount,
            'driversAvailableCount' => $driversAvailableCount,
            'driversOfflineCount' => $driversOfflineCount,
        ]);
    }

    public function assignments(Request $request, OvanieShipmentConsolidationService $consolidation)
    {
        return redirect()->route('logistics.shipments')
            ->with('info', 'L’affectation manuelle est désactivée : les missions sont proposées automatiquement aux livreurs partenaires éligibles.');
    }

    public function shipmentDetails(
        Request $request,
        OrderItem $item,
        OvanieShipmentConsolidationService $consolidation,
        MissionRoutingService $missionRouting,
        DeliveryScheduleService $schedule
    ) {
        $this->ensureOvanieShipment($item);
        $group = $consolidation->groupForItem($item);
        $item = $group['representative'];
        $item->loadMissing([
            'order.client',
            'order.items',
            'product.shop',
            'shipment',
            'latestDeliveryAssignment.driver',
            'latestDeliveryAssignment.latestLocation',
        ]);

        $order = $item->order;
        $deliveryGroup = app(ClientDeliveryGroupService::class)
            ->findForItem($order, (int) $item->id);

        $plannedAssignment = $group['items']
            ->flatMap(fn (OrderItem $groupItem) => $groupItem->relationLoaded('deliveryAssignments')
                ? $groupItem->deliveryAssignments
                : $groupItem->deliveryAssignments()->with('driver')->get())
            ->filter(fn ($assignment) => in_array((string) $assignment->status, [
                'accepted', 'assigned', 'collecting', 'picked_up', 'in_transit', 'arrived', 'delivered',
            ], true))
            ->sortByDesc(fn ($assignment) => optional($assignment->accepted_at ?: $assignment->updated_at)->timestamp ?? 0)
            ->first();

        $estimatedAt = $schedule->estimatedFor($group['items']);
        $minimumPickupAt = now()->addMinutes(5)->ceilMinute();
        $formPickupAt = $plannedAssignment?->pickup_scheduled_at
            && $plannedAssignment->pickup_scheduled_at->gte($minimumPickupAt)
                ? Carbon::parse($plannedAssignment->pickup_scheduled_at)
                : $minimumPickupAt->copy();
        $minimumDeliveryAt = $formPickupAt->copy()->addMinutes(30);
        $formDeliveryAt = $estimatedAt && Carbon::parse($estimatedAt)->gt($formPickupAt)
            ? Carbon::parse($estimatedAt)
            : $minimumDeliveryAt;

        $status = (string) ($group['status'] ?? OrderWorkflowService::DELIVERY_PENDING);
        $assignmentStatus = strtolower(trim((string) ($plannedAssignment?->status ?? '')));
        $pickupLegActive = in_array($assignmentStatus, ['accepted', 'collecting'], true);
        $deliveryLegActive = in_array($assignmentStatus, ['picked_up', 'in_transit', 'arrived', 'delivered'], true)
            || in_array($status, [
                OrderWorkflowService::DELIVERY_PICKED_UP,
                OrderWorkflowService::DELIVERY_IN_TRANSIT,
                OrderWorkflowService::DELIVERY_DELIVERED,
            ], true);
        $showMissionRoute = $pickupLegActive || $deliveryLegActive;

        $routePlan = $this->emptyMissionRoute(
            'Le tracé apparaîtra après acceptation de la mission et réception d’une position GPS réelle du livreur.'
        );

        if ($showMissionRoute) {
            [$destinationLat, $destinationLng] = $this->verifiedDestinationCoordinates($order);

            $pickupMarkers = $group['pickup_stops']->map(function (array $stop) {
                $shop = $stop['shop'] ?? null;
                [$latitude, $longitude] = $this->verifiedShopCoordinates($shop);

                return [
                    'id' => $stop['id'] ?? $shop?->id,
                    'name' => $shop?->name ?: 'Point de collecte',
                    'address' => $stop['address'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'weight' => $stop['weight'] ?? 0,
                    'volume' => $stop['volume'] ?? 0,
                ];
            })->values()->all();

            $driverPoint = [
                'name' => $group['driver_name'] ?: 'Livreur',
                'destination_address' => $this->orderDeliveryAddress($order),
            ];

            if ($deliveryLegActive) {
                $pickupMarkers = [];
            }

            $latestLocation = $plannedAssignment?->latestLocation;
            $lastSeenAt = $latestLocation?->recorded_at ?: $plannedAssignment?->gps_last_seen_at;
            $hasRecentPosition = $latestLocation
                && $lastSeenAt
                && $lastSeenAt->diffInSeconds(now()) <= (int) config('delivery.gps_weak_seconds', 180)
                && is_numeric($latestLocation->latitude)
                && is_numeric($latestLocation->longitude);

            if ($hasRecentPosition) {
                $driverPoint['latitude'] = (float) $latestLocation->latitude;
                $driverPoint['longitude'] = (float) $latestLocation->longitude;

                $routePlan = $missionRouting->build(
                    $pickupMarkers,
                    $destinationLat,
                    $destinationLng,
                    (string) ($group['vehicle_code'] ?? 'pickup'),
                    $driverPoint,
                    [
                        'include_destination' => $deliveryLegActive,
                        'require_driver' => true,
                        'phase' => $pickupLegActive ? 'pickup' : 'delivery',
                    ]
                );
            }
        }

        $drivers = $this->realDriversQuery()
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->orderBy('name')
            ->get();

        return view('logistics.shipments-details', [
            'item' => $item,
            'shipmentGroup' => $group,
            'deliveryAddress' => $this->orderDeliveryAddress($order),
            'routePlan' => $routePlan,
            'showMissionRoute' => $showMissionRoute,
            'routeTitle' => ! $showMissionRoute
                ? 'Suivi cartographique de la mission'
                : ($pickupLegActive ? 'Trajet vers les points de collecte' : 'Trajet vers le client'),
            'routeDescription' => ! $showMissionRoute
                ? 'Aucun tracé avant l’acceptation réelle de la mission par le livreur.'
                : ($pickupLegActive
                    ? 'La carte interne relie la position GPS réelle du livreur aux boutiques à collecter.'
                    : 'Après le chargement, la route relie uniquement le véhicule à la destination client.'),
            'drivers' => $drivers,
            'plannedAssignment' => $plannedAssignment,
            'estimatedDeliveryAt' => $estimatedAt,
            'minimumPickupAt' => $minimumPickupAt,
            'formPickupAt' => $formPickupAt,
            'formDeliveryAt' => $formDeliveryAt,
            'codCollectionRequired' => $this->codCollectionRequiredForItem($item),
            'codGroupAmount' => (float) ($deliveryGroup['total'] ?? 0),
            'codDeliveryNumber' => $deliveryGroup['number'] ?? null,
        ]);
    }

    public function assignPage(
        Request $request,
        OrderItem $item,
        OvanieShipmentConsolidationService $consolidation,
        DeliveryScheduleService $schedule,
        LogisticsShipmentWorkflowService $shipmentWorkflow,
        RoutingService $routing
    ) {
        return redirect()->route('logistics.shipments.details', $item)
            ->with('info', 'Planification manuelle désactivée : la mission est proposée automatiquement aux livreurs éligibles.');
    }

    public function assignmentData(
        Request $request,
        OrderItem $item,
        OvanieShipmentConsolidationService $consolidation,
        DeliveryScheduleService $schedule,
        LogisticsShipmentWorkflowService $shipmentWorkflow,
        RoutingService $routing
    ) {
        return response()->json([
            'message' => 'L’affectation manuelle est désactivée. La mission est proposée automatiquement aux livreurs partenaires éligibles.',
        ], 409);
    }

    public function tracking(Request $request, OvanieShipmentConsolidationService $consolidation)
    {
        $groups = $consolidation->groups($this->deliveryItems()->get());

        $activeGroups = $groups->whereIn('status', ['assigned', 'picked_up', 'in_transit'])->values();
        $lateGroups = $activeGroups->filter(function (array $g) {
            $updatedAt = $g['updated_at'] ?? null;

            return $updatedAt && \Carbon\Carbon::parse($updatedAt)->lt(now()->subDay());
        })->values();

        $driversTotal = $this->realDriversQuery()->where('is_active', true)->count();
        $driversOnline = $this->realDriversQuery()->gpsOnline()->count();
        $deliveredToday = $groups->where('status', 'delivered')->filter(function (array $g) {
            $updatedAt = $g['updated_at'] ?? null;

            return $updatedAt && \Carbon\Carbon::parse($updatedAt)->isToday();
        })->count();

        $shops = $this->ovanieShopMarkers();

        // La carte du centre de suivi n'affiche comme « en ligne » que les
        // livreurs ayant une présence GPS réellement fraîche. Ils sont affichés
        // même lorsqu'aucune mission ne leur est encore affectée afin que la
        // responsable puisse superviser les véhicules disponibles avant dispatch.
        $mapDrivers = $this->realDriversQuery()
            ->where('is_active', true)
            ->gpsOnline()
            ->with('currentLocation')
            ->withCount('activeAssignments')
            ->orderBy('name')
            ->get()
            ->map(fn (DeliveryDriver $driver) => \App\ViewModels\LogisticsTrackingData::driver($driver))
            ->filter(fn (array $driver) => $driver['lat'] !== null && $driver['lng'] !== null)
            ->values();

        $recentEvents = $this->recentAssignmentEvents(12);
        $incidentsOpenCount = $this->realIncidentsQuery()->whereNotIn('status', ['resolved', 'closed'])->count();
        $recentIncidents = $this->realIncidentsQuery()->with(['order', 'orderItem.latestDeliveryAssignment'])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest()->take(6)->get();

        return view('logistics.tracking', [
            'activeGroups' => $activeGroups,
            'lateGroups' => $lateGroups,
            'driversTotal' => $driversTotal,
            'driversOnline' => $driversOnline,
            'deliveredToday' => $deliveredToday,
            'shops' => $shops,
            'mapDrivers' => $mapDrivers,
            'recentEvents' => $recentEvents,
            'incidentsOpenCount' => $incidentsOpenCount,
            'recentIncidents' => $recentIncidents,
        ]);
    }

    public function trackingMission(Request $request, OvanieShipmentConsolidationService $consolidation, ?OrderItem $item = null)
    {
        if ($item && ! $this->deliveryItems()->whereKey($item->getKey())->exists()) {
            abort(404);
        }

        $groups = $consolidation->groups($this->deliveryItems()->get());
        $group = $item ? $consolidation->groupForItem($item) : $groups->whereIn('status', ['in_transit', 'picked_up', 'assigned'])->first();

        if (! $group) {
            $group = $groups->first();
        }

        $order = $group['order'] ?? null;
        $firstStop = $group ? $group['pickup_stops']->first() : null;
        [$shopLat, $shopLng] = $this->verifiedShopCoordinates($firstStop['shop'] ?? null);
        [$destLat, $destLng] = $this->verifiedDestinationCoordinates($order);

        $rep = $group ? $group['representative'] : null;
        $assignment = $rep?->latestDeliveryAssignment;
        $driverLat = null;
        $driverLng = null;
        if ($assignment?->driver && is_numeric($assignment->driver->latitude) && is_numeric($assignment->driver->longitude)) {
            $driverLat = (float) $assignment->driver->latitude;
            $driverLng = (float) $assignment->driver->longitude;
        }

        $isLate = $group && (($group['updated_at'] ?? null) && \Carbon\Carbon::parse($group['updated_at'])->lt(now()->subDay()));

        $events = $rep ? $this->assignmentTimeline($rep) : [];

        return view('logistics.tracking-mission', [
            'group' => $group,
            'order' => $order,
            'rep' => $rep,
            'assignment' => $assignment,
            'shopLat' => $shopLat, 'shopLng' => $shopLng,
            'destLat' => $destLat, 'destLng' => $destLng,
            'driverLat' => $driverLat, 'driverLng' => $driverLng,
            'isLate' => $isLate,
            'events' => $events,
            'missions' => $groups->whereIn('status', ['in_transit', 'picked_up', 'assigned'])->values(),
        ]);
    }

    public function trackingDrivers(Request $request)
    {
        $drivers = $this->realDriversQuery()
            ->where('is_active', true)
            ->where('onboarding_status', DeliveryDriver::ONBOARDING_ACTIVE)
            ->with('currentLocation')
            ->withCount('activeAssignments')
            ->orderBy('name')
            ->get();

        $rows = $drivers->map(function (DeliveryDriver $driver) {
            $tracking = \App\ViewModels\LogisticsTrackingData::driver($driver);

            return [
                'driver' => $driver,
                'tracking' => $tracking,
                'zones' => $driver->interventionZonesLabel(3),
                'missionCount' => (int) ($driver->active_assignments_count ?? 0),
            ];
        });

        $stats = [
            'total' => $rows->count(),
            'online' => $rows->where('tracking.online', true)->count(),
            'offline' => $rows->where('tracking.online', false)->count(),
            'available' => $rows->filter(fn ($row) => $row['tracking']['online'] && $row['tracking']['availability'] === 'Disponible')->count(),
            'mission' => $rows->filter(fn ($row) => $row['tracking']['availability'] === 'En mission')->count(),
            'unavailable' => $rows->filter(fn ($row) => $row['tracking']['online'] && $row['tracking']['availability'] === 'Indisponible')->count(),
        ];

        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $presence = (string) $request->query('presence', 'all');
        $vehicle = trim((string) $request->query('vehicle', ''));
        $zone = trim((string) $request->query('zone', ''));

        $filtered = $rows->filter(function ($row) use ($q, $presence, $vehicle, $zone) {
            $driver = $row['driver'];
            $tracking = $row['tracking'];

            if ($q !== '') {
                $haystack = mb_strtolower(collect([
                    $tracking['name'],
                    $tracking['phone'],
                    $tracking['vehicle'],
                    $row['zones'],
                ])->filter()->implode(' '));
                if (! str_contains($haystack, $q)) {
                    return false;
                }
            }

            if ($vehicle !== '' && $tracking['vehicle'] !== $vehicle) {
                return false;
            }

            if ($zone !== '' && ! str_contains(mb_strtolower((string) $row['zones']), mb_strtolower($zone))) {
                return false;
            }

            return match ($presence) {
                'online' => (bool) $tracking['online'],
                'offline' => ! $tracking['online'],
                'available' => $tracking['online'] && $tracking['availability'] === 'Disponible',
                'mission' => $tracking['availability'] === 'En mission',
                'unavailable' => $tracking['online'] && $tracking['availability'] === 'Indisponible',
                default => true,
            };
        })->values();

        return view('logistics.tracking-drivers', [
            'rows' => $filtered,
            'stats' => $stats,
            'vehicles' => $rows->pluck('tracking.vehicle')->filter()->unique()->sort()->values(),
            'zones' => $rows->pluck('zones')->filter()->unique()->sort()->values(),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'presence' => $presence,
                'vehicle' => $vehicle,
                'zone' => $zone,
            ],
        ]);
    }

    public function trackingDriver(Request $request, OvanieShipmentConsolidationService $consolidation, DeliveryDriver $driver)
    {
        if (! $this->realDriversQuery()->whereKey($driver->getKey())->exists()) {
            abort(404);
        }

        $driver->loadMissing('currentLocation');

        // Une « mission active » doit être une vraie affectation opérationnelle.
        // On exclut les brouillons/anciennes affectations terminées afin que le
        // suivi du livreur ne réutilise jamais une mission historique.
        $activeAssignment = $driver
            ? LogisticsOperationalDataScope::assignments(\App\Models\DeliveryAssignment::query())->with([
                    'orderItem.order.client',
                    'orderItem.product.shop',
                    'latestLocation',
                ])
                ->where('driver_id', $driver->id)
                ->whereIn('status', [
                    'planned', 'assigned', 'accepted', 'collecting',
                    'picked_up', 'in_transit', 'arrived',
                ])
                ->latest('updated_at')
                ->first()
            : null;

        $group = null;
        $shopLat = $shopLng = $destLat = $destLng = null;
        if ($activeAssignment?->order_item_id) {
            $activeItem = OrderItem::find($activeAssignment->order_item_id);
            if ($activeItem) {
                $group = $consolidation->groupForItem($activeItem);
                $firstStop = $group['pickup_stops']->first();
                [$shopLat, $shopLng] = $this->verifiedShopCoordinates($firstStop['shop'] ?? null);
                [$destLat, $destLng] = $this->verifiedDestinationCoordinates($group['order'] ?? null);
            }
        }

        $todayMissionsCount = $driver
            ? LogisticsOperationalDataScope::assignments(\App\Models\DeliveryAssignment::query())->where('driver_id', $driver->id)->whereDate('created_at', today())->count()
            : 0;

        $events = $driver ? $this->driverTimeline($driver, 10) : [];
        $driverPerformance = ['distance'=>null,'onlineMinutes'=>null,'onTimePercent'=>null];
        if ($driver) {
            $samples = $driver->locations()->whereDate('recorded_at', today())->orderBy('recorded_at')->get();
            if ($samples->count() > 1) {
                $distance = 0; $onlineSeconds = 0;
                foreach ($samples->values() as $index => $sample) {
                    if ($index === 0) continue;
                    $previous = $samples[$index - 1];
                    $seconds = abs($sample->recorded_at->diffInSeconds($previous->recorded_at));
                    if ($seconds > 600) continue;
                    $a = sin(deg2rad($sample->latitude - $previous->latitude) / 2) ** 2
                        + cos(deg2rad($previous->latitude)) * cos(deg2rad($sample->latitude))
                        * sin(deg2rad($sample->longitude - $previous->longitude) / 2) ** 2;
                    $distance += 6371 * 2 * asin(sqrt(min(1, $a)));
                    $onlineSeconds += $seconds;
                }
                $driverPerformance['distance'] = round($distance, 1);
                $driverPerformance['onlineMinutes'] = (int) floor($onlineSeconds / 60);
            }
            $finished = LogisticsOperationalDataScope::assignments(\App\Models\DeliveryAssignment::query())->where('driver_id', $driver->id)
                ->whereDate('delivered_at', today())->whereNotNull('estimated_delivery_at')->get();
            if ($finished->isNotEmpty()) $driverPerformance['onTimePercent'] = (int) round($finished->filter(fn($a) => $a->delivered_at->lte($a->estimated_delivery_at))->count() / $finished->count() * 100);
            $gpsEvents = $samples->sortByDesc('recorded_at')->take(4)->map(fn($sample) => ['at'=>$sample->recorded_at,'label'=>'Position GPS mise à jour'])->all();
            $events = collect(array_merge($events,$gpsEvents))->sortByDesc(fn($event)=>$event['at']->timestamp)->take(6)->values()->all();
        }

        return view('logistics.tracking-driver', [
            'driver' => $driver,
            'activeAssignment' => $activeAssignment,
            'group' => $group,
            'shopLat' => $shopLat, 'shopLng' => $shopLng,
            'destLat' => $destLat, 'destLng' => $destLng,
            'todayMissionsCount' => $todayMissionsCount,
            'driverPerformance' => $driverPerformance,
            'events' => $events,
            'allDrivers' => $this->realDriversQuery()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function recentAssignmentEvents(int $limit = 12): array
    {
        $events = [];
        $assignments = LogisticsOperationalDataScope::assignments(\App\Models\DeliveryAssignment::query())->with(['driver', 'orderItem.order'])
            ->latest('updated_at')
            ->take(30)
            ->get();

        foreach ($assignments as $a) {
            $driverName = $a->driver?->name ?: 'Livreur';
            $orderRef = $a->orderItem?->order?->order_number;
            $commune = $a->orderItem?->order?->delivery_commune;

            if ($a->delivered_at) {
                $events[] = ['at' => $a->delivered_at, 'label' => "Livraison effectuée" . ($orderRef ? " - {$orderRef}" : '') . ($commune ? " ({$commune})" : ''), 'type' => 'done'];
            } elseif ($a->picked_up_at) {
                $events[] = ['at' => $a->picked_up_at, 'label' => "{$driverName} a quitté la boutique" . ($commune ? " - {$commune}" : ''), 'type' => 'info'];
            } elseif ($a->accepted_at) {
                $events[] = ['at' => $a->accepted_at, 'label' => "{$driverName} a démarré la mission" . ($commune ? " - {$commune}" : ''), 'type' => 'info'];
            }
        }

        usort($events, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return array_slice($events, 0, $limit);
    }

    private function assignmentTimeline($rep): array
    {
        $assignment = $rep->latestDeliveryAssignment;
        if (! $assignment) {
            return [];
        }

        return array_filter([
            $assignment->accepted_at ? ['at' => $assignment->accepted_at, 'label' => 'Affectation confirmée'] : null,
            $assignment->picked_up_at ? ['at' => $assignment->picked_up_at, 'label' => 'Le chauffeur a quitté la boutique'] : null,
            $assignment->delivered_at ? ['at' => $assignment->delivered_at, 'label' => 'Mission livrée'] : null,
        ]);
    }

    private function driverTimeline(DeliveryDriver $driver, int $limit = 10): array
    {
        $events = [];
        $assignments = LogisticsOperationalDataScope::assignments(\App\Models\DeliveryAssignment::query())->where('driver_id', $driver->id)
            ->with('orderItem.order')
            ->latest('updated_at')
            ->take($limit)
            ->get();

        foreach ($assignments as $a) {
            $commune = $a->orderItem?->order?->delivery_commune;
            if ($a->delivered_at) {
                $events[] = ['at' => $a->delivered_at, 'label' => 'Mission livrée' . ($commune ? " ({$commune})" : '')];
            }
            if ($a->picked_up_at) {
                $events[] = ['at' => $a->picked_up_at, 'label' => 'A quitté la boutique' . ($commune ? " ({$commune})" : '')];
            }
            if ($a->accepted_at) {
                $events[] = ['at' => $a->accepted_at, 'label' => 'Affectation confirmée'];
            }
        }

        usort($events, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return array_slice($events, 0, $limit);
    }

    public function activeDeliveries(
        Request $request,
        OvanieShipmentConsolidationService $consolidation
    ) {
        $allGroups = $consolidation
            ->groups($this->deliveryItems()->get())
            ->map(function (array $group) {
                $displayStatus = (string) ($group['status'] ?? OrderWorkflowService::DELIVERY_PENDING);
                $hasDriver = filled($group['driver_name'] ?? null);
                $preparationPercent = (int) ($group['preparation_percent'] ?? 0);

                // Corrige les anciennes lignes marquées "assigned" sans livreur réel.
                if ($displayStatus === OrderWorkflowService::DELIVERY_ASSIGNED && ! $hasDriver) {
                    $displayStatus = $preparationPercent >= 100
                        ? OrderWorkflowService::DELIVERY_READY_FOR_PICKUP
                        : OrderWorkflowService::DELIVERY_PENDING;
                }

                if ($displayStatus === OrderWorkflowService::DELIVERY_PENDING && $preparationPercent >= 100) {
                    $displayStatus = OrderWorkflowService::DELIVERY_READY_FOR_PICKUP;
                }

                $etaAt = $group['items']
                    ->map(fn (OrderItem $item) => $item->shipment?->estimated_delivery_at)
                    ->filter()
                    ->sortBy(fn ($date) => $date->timestamp)
                    ->last();

                if (! $etaAt) {
                    $etaAt = $group['order']?->delivery_max_date
                        ?: $group['order']?->delivery_min_date;
                }

                if ($etaAt && ! $etaAt instanceof \Carbon\CarbonInterface) {
                    $etaAt = \Carbon\Carbon::parse($etaAt);
                }

                $updatedAt = $group['updated_at'] ?? null;
                if ($updatedAt && ! $updatedAt instanceof \Carbon\CarbonInterface) {
                    $updatedAt = \Carbon\Carbon::parse($updatedAt);
                }

                $isDelayed = in_array($displayStatus, [
                    OrderWorkflowService::DELIVERY_ASSIGNED,
                    OrderWorkflowService::DELIVERY_PICKED_UP,
                    OrderWorkflowService::DELIVERY_IN_TRANSIT,
                ], true) && (
                    ($etaAt && $etaAt->isPast())
                    || (! $etaAt && $updatedAt && $updatedAt->lt(now()->subDay()))
                );

                $fallbackDelay = $group['items']
                    ->pluck('delivery_delay')
                    ->filter()
                    ->first();

                $group['display_status'] = $displayStatus;
                $group['is_delayed'] = $isDelayed;
                $group['eta_at'] = $etaAt;
                $group['eta_label'] = $etaAt
                    ? $etaAt->format('d/m/Y à H:i')
                    : ($fallbackDelay ?: 'À confirmer');
                $group['status_label'] = $isDelayed
                    ? 'Retard à contrôler'
                    : match ($displayStatus) {
                        OrderWorkflowService::DELIVERY_READY_FOR_PICKUP => 'Prête à affecter',
                        OrderWorkflowService::DELIVERY_ASSIGNED => 'Livreur affecté',
                        OrderWorkflowService::DELIVERY_PICKED_UP => 'Collectes en cours',
                        OrderWorkflowService::DELIVERY_IN_TRANSIT => 'En route vers le client',
                        default => 'En attente de préparation',
                    };

                return $group;
            });

        $activeStatuses = [
            OrderWorkflowService::DELIVERY_PENDING,
            OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
            OrderWorkflowService::DELIVERY_ASSIGNED,
            OrderWorkflowService::DELIVERY_PICKED_UP,
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
        ];

        $activeGroups = $allGroups
            ->whereIn('display_status', $activeStatuses)
            ->values();

        $stats = [
            'active' => $activeGroups->count(),
            'ready' => $activeGroups
                ->where('display_status', OrderWorkflowService::DELIVERY_READY_FOR_PICKUP)
                ->count(),
            'route' => $activeGroups
                ->whereIn('display_status', [
                    OrderWorkflowService::DELIVERY_PICKED_UP,
                    OrderWorkflowService::DELIVERY_IN_TRANSIT,
                ])
                ->count(),
            'delivered_today' => $allGroups
                ->where('status', OrderWorkflowService::DELIVERY_DELIVERED)
                ->filter(function (array $group) {
                    $updatedAt = $group['updated_at'] ?? null;

                    return $updatedAt
                        && \Carbon\Carbon::parse($updatedAt)->isToday();
                })
                ->count(),
            'delayed' => $activeGroups->where('is_delayed', true)->count(),
        ];

        $filteredGroups = $activeGroups;

        if ($search = trim((string) $request->query('q'))) {
            $needle = mb_strtolower($search);

            $filteredGroups = $filteredGroups->filter(function (array $group) use ($needle) {
                $haystack = collect([
                    $group['mission_number'] ?? null,
                    $group['order']?->order_number,
                    $group['client_name'] ?? null,
                    $group['address'] ?? null,
                    $group['driver_name'] ?? null,
                    $group['vehicle_label'] ?? null,
                    $group['shops']->pluck('name')->implode(' '),
                    $group['items']->pluck('product.name')->implode(' '),
                ])->filter()->implode(' ');

                return str_contains(mb_strtolower($haystack), $needle);
            });
        }

        if ($zone = trim((string) $request->query('zone'))) {
            $zoneNeedle = mb_strtolower($zone);

            $filteredGroups = $filteredGroups->filter(function (array $group) use ($zoneNeedle) {
                $zoneText = collect([
                    $group['order']?->delivery_commune,
                    $group['order']?->delivery_zone,
                    $group['order']?->delivery_city,
                    $group['address'] ?? null,
                ])->filter()->implode(' ');

                return str_contains(mb_strtolower($zoneText), $zoneNeedle);
            });
        }

        if ($status = trim((string) $request->query('status'))) {
            $filteredGroups = $filteredGroups->filter(function (array $group) use ($status) {
                return match ($status) {
                    'waiting' => $group['display_status'] === OrderWorkflowService::DELIVERY_PENDING,
                    'ready' => $group['display_status'] === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                    'assigned' => $group['display_status'] === OrderWorkflowService::DELIVERY_ASSIGNED,
                    'route' => in_array($group['display_status'], [
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                    ], true),
                    'delayed' => (bool) ($group['is_delayed'] ?? false),
                    default => true,
                };
            });
        }

        if ($driverId = $request->query('driver_id')) {
            $filteredGroups = $filteredGroups->filter(fn (array $group) => (string) ($group['driver_id'] ?? '') === (string) $driverId);
        }
        if (in_array($request->query('delay'), ['late', 'on_time'], true)) {
            $wantDelayed = $request->query('delay') === 'late';
            $filteredGroups = $filteredGroups->filter(fn (array $group) => (bool) ($group['is_delayed'] ?? false) === $wantDelayed);
        }
        $filteredGroups = $filteredGroups->values();
        $perPage = $request->query('view') === 'map'
            ? max(1, $filteredGroups->count())
            : max(1, min(12, (int) $request->query('per_page', 2)));
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());

        $missions = new LengthAwarePaginator(
            $filteredGroups->forPage($page, $perPage)->values(),
            $filteredGroups->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $zones = $activeGroups
            ->flatMap(fn (array $group) => [
                $group['order']?->delivery_commune,
                $group['order']?->delivery_zone,
                $group['order']?->delivery_city,
            ])
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => mb_strtolower($value))
            ->sort()
            ->values();

        $stats['drivers_active'] = $this->realDriversQuery()->gpsOnline()->count();
        $ovanieShops = $this->ovanieShopMarkers();
        $activeDrivers = $this->realDriversQuery()->where('is_active', true)->orderBy('name')->get();

        return view('logistics.active-deliveries', [
            'missions' => $missions,
            'stats' => $stats,
            'zones' => $zones,
            'ovanieShops' => $ovanieShops,
            'activeDrivers' => $activeDrivers,
        ]);
    }

    public function driverTrackingPage(Request $request, Shipment $shipment)
    {
        abort_unless(
            LogisticsOperationalDataScope::shipments(Shipment::query())->whereKey($shipment->getKey())->exists(),
            404
        );

        $shipment->loadMissing('orderItem.latestDeliveryAssignment.driver', 'order');

        return view('logistics.driver-tracking', [
            'shipment' => $shipment,
            'assignment' => $shipment->orderItem?->latestDeliveryAssignment,
        ]);
    }

    public function assignShipment(
        Request $request,
        OrderItem $item,
        OvanieShipmentConsolidationService $consolidation,
        DeliveryScheduleService $schedule,
        LogisticsShipmentWorkflowService $shipmentWorkflow
    ) {
        return redirect()->route('logistics.shipments.details', $item)
            ->with('info', 'Affectation manuelle désactivée : le premier livreur éligible qui accepte l’offre réserve automatiquement la mission.');
    }

    public function updateShipmentStatus(
        Request $request,
        OrderItem $item,
        OvanieShipmentConsolidationService $consolidation
    ) {
        $data = $request->validate([
            'vendor_delivery_status' => ['required', 'in:pending,in_delivery,picked_up,assigned,delivery_failed'],
            'driver_id' => ['nullable', 'integer'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'driver_phone' => ['nullable', 'string', 'max:40'],
            'vehicle_plate' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->ensureOvanieShipment($item);
        $group = $consolidation->groupForItem($item);
        $centralStatus = $this->mapLegacyDeliveryFilter($data['vendor_delivery_status']);

        DB::transaction(function () use ($group, $data, $centralStatus, $request) {
            foreach ($group['items'] as $index => $groupItem) {
                $groupItem->refresh();

                $extra = array_filter([
                    'driver_name' => $data['driver_name'] ?? $groupItem->driver_name,
                    'driver_phone' => $data['driver_phone'] ?? $groupItem->driver_phone,
                    'vehicle_plate' => $data['vehicle_plate'] ?? $groupItem->vehicle_plate,
                    'suppress_notifications' => $index > 0,
                ], fn ($value) => $value !== null);

                if ($centralStatus === $groupItem->delivery_status) {
                    unset($extra['suppress_notifications']);
                    $groupItem->forceFill($extra)->save();
                    continue;
                }

                app(OrderWorkflowService::class)->setDeliveryStatus(
                    $groupItem,
                    $centralStatus,
                    $request->user(),
                    'logistics',
                    $data['note'] ?? null,
                    $extra
                );
            }
        }, 3);

        $message = match ($centralStatus) {
            OrderWorkflowService::DELIVERY_PICKED_UP => 'Tous les points de collecte ont été confirmés.',
            OrderWorkflowService::DELIVERY_IN_TRANSIT => 'La mission consolidée est maintenant en route.',
            default => 'La mission consolidée a été mise à jour.',
        };

        return back()->with('success', $message);
    }

    public function exportShipments(Request $request): StreamedResponse
    {
        $query = $this->deliveryItems();
        $this->applyShipmentFilters($query, $request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Expedition', 'Commande', 'Client', 'Produit', 'Zone', 'Statut', 'Livreur']);
            $query->chunk(100, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        'EXP-'.str_pad($item->id, 3, '0', STR_PAD_LEFT),
                        $item->order?->order_number,
                        $item->order?->client?->name,
                        $item->product?->name,
                        $item->order?->delivery_commune ?: $item->order?->delivery_zone,
                        $item->vendor_delivery_status ?: 'pending',
                        $item->driver_name,
                    ]);
                }
            });
            fclose($handle);
        }, 'expeditions-logistique.csv', ['Content-Type' => 'text/csv']);
    }

    public function returnDetails(ReturnModel $return, ReturnRefundService $refundService)
    {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Les dossiers de démonstration sont masqués. La page Retours affiche uniquement les demandes réelles.');
        }

        $return->load(['order.client', 'orderItem.product.shop', 'product.category']);
        $meta = is_array($return->meta) ? $return->meta : [];
        $decisionType = (string) data_get($meta, 'vendor_decision.type', '');
        if ($decisionType === '') {
            $decisionType = match (true) {
                $return->status === ReturnModel::STATUS_REJECTED => 'reject',
                $return->status === ReturnModel::STATUS_REFUNDED => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'refund' => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'return' => 'accept',
                default => '',
            };
        }

        $refundRelevant = $decisionType !== 'reject' && (
            $decisionType === 'refund'
            || $return->status === ReturnModel::STATUS_REFUNDED
            || in_array($return->logistics_status, [ReturnModel::LOGISTICS_REFUND_REVIEW, ReturnModel::LOGISTICS_REFUND_PENDING, ReturnModel::LOGISTICS_REFUNDED], true)
            || $return->refund_prepared_at
            || $return->refunded_at
        );

        $refundAmount = null;
        if ($refundRelevant) {
            $refundAmount = (float) ($return->refund_amount ?: $refundService->calculateRefundAmount($return));
        }

        $returnHistories = $return->order
            ? $return->order->statusHistories()
                ->where('order_item_id', $return->order_item_id)
                ->whereIn('status_type', ['return', 'return_logistics', 'return_refund', 'return_client_notification'])
                ->oldest('created_at')
                ->get()
            : collect();

        $returnDrivers = $this->realDriversQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $returnDriver = filled(data_get($meta, 'collection.driver_id'))
            ? $this->realDriversQuery()->whereKey((int) data_get($meta, 'collection.driver_id'))->first()
            : null;

        return view('logistics.returns-details', compact(
            'return',
            'refundAmount',
            'returnHistories',
            'returnDrivers',
            'returnDriver'
        ));
    }

    public function approveReturn(
        Request $request,
        ReturnModel $return,
        ReturnRefundService $refundService
    ) {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Action ignorée : ce dossier de démonstration n’est plus utilisé.');
        }
        $return->loadMissing(['order.client', 'orderItem']);

        if ($return->status === ReturnModel::STATUS_REFUNDED) {
            return back()->with('info', 'Cette demande est déjà remboursée.');
        }

        $workflow = app(OrderWorkflowService::class);
        $message = 'Demande mise à jour.';

        // Une réclamation n'est jamais transformée en collecte physique.
        if ($return->return_type === 'claim') {
            if ($return->status === ReturnModel::STATUS_PENDING) {
                $oldStatus = $return->status;
                $return->update([
                    'status' => ReturnModel::STATUS_ACCEPTED,
                    'logistics_status' => ReturnModel::LOGISTICS_CLAIM_REVIEW,
                    'accepted_at' => now(),
                ]);

                $workflow->recordHistory($return->order, $return->orderItem, 'return', $oldStatus, ReturnModel::STATUS_ACCEPTED, [
                    'actor_type' => 'logistics',
                    'user_id' => auth()->id(),
                    'label' => 'Réclamation prise en charge',
                    'message' => 'La réclamation est en cours d’analyse par OVANIE.',
                ]);

                $message = 'Réclamation prise en charge pour analyse.';
            } elseif ($return->logistics_status === ReturnModel::LOGISTICS_CLAIM_REVIEW) {
                $return->update([
                    'status' => ReturnModel::STATUS_CLOSED,
                    'logistics_status' => 'not_required',
                    'resolved_at' => now(),
                ]);

                $return->orderItem?->forceFill([
                    'return_status' => ReturnModel::STATUS_CLOSED,
                ])->save();

                $workflow->recordHistory($return->order, $return->orderItem, 'return', ReturnModel::STATUS_ACCEPTED, ReturnModel::STATUS_CLOSED, [
                    'actor_type' => 'logistics',
                    'user_id' => auth()->id(),
                    'label' => 'Réclamation résolue',
                    'message' => 'Le traitement de la réclamation est terminé.',
                ]);

                $workflow->markPayoutsReadyWhenEligible($return->order);
                $message = 'Réclamation clôturée sans collecte physique.';
            } else {
                return back()->with('info', 'Cette réclamation ne nécessite aucune étape de collecte.');
            }

            $this->notifyReturnClient($workflow, $return, $message);
            return back()->with('success', $message);
        }

        // Une demande de remboursement sans retour passe par une revue puis par
        // le rapprochement financier, sans déclencher de collecte automatique.
        if ($return->return_type === 'refund') {
            if ($return->status === ReturnModel::STATUS_PENDING) {
                $oldStatus = $return->status;
                $return->update([
                    'status' => ReturnModel::STATUS_ACCEPTED,
                    'logistics_status' => ReturnModel::LOGISTICS_REFUND_REVIEW,
                    'accepted_at' => now(),
                ]);

                $workflow->recordHistory($return->order, $return->orderItem, 'return_refund', $oldStatus, ReturnModel::LOGISTICS_REFUND_REVIEW, [
                    'actor_type' => 'logistics',
                    'user_id' => auth()->id(),
                    'label' => 'Demande de remboursement en analyse',
                    'message' => 'La demande est vérifiée avant préparation du remboursement.',
                ]);

                $message = 'Demande de remboursement acceptée pour analyse.';
            } elseif ($return->logistics_status === ReturnModel::LOGISTICS_REFUND_REVIEW) {
                $return = $refundService->prepareRefund($return, auth()->id());
                $workflow->recordHistory($return->order, $return->orderItem, 'return_refund', ReturnModel::LOGISTICS_REFUND_REVIEW, ReturnModel::LOGISTICS_REFUND_PENDING, [
                    'actor_type' => 'logistics',
                    'user_id' => auth()->id(),
                    'label' => 'Remboursement à exécuter',
                    'message' => 'Le montant du remboursement a été calculé et attend la référence de l’opération réellement exécutée.',
                    'metadata' => ['refund_amount' => (float) $return->refund_amount],
                ]);

                $message = 'Montant du remboursement préparé. Enregistrez la référence après exécution réelle.';
            } elseif ($return->logistics_status === ReturnModel::LOGISTICS_REFUND_PENDING) {
                $return = $this->confirmReturnRefundFromRequest($request, $return, $refundService, $workflow);
                $message = 'Remboursement confirmé et ajustement vendeur enregistré.';
            } else {
                return back()->with('info', 'Cette demande de remboursement ne nécessite aucune collecte physique.');
            }

            $this->notifyReturnClient($workflow, $return, $message);
            return back()->with('success', $message);
        }

        // Workflow de retour physique uniquement.
        if ($return->status === ReturnModel::STATUS_PENDING) {
            $oldStatus = $return->status;
            $nextLogisticsStatus = $return->logistics_status === ReturnModel::LOGISTICS_PENDING_PICKUP
                ? ReturnModel::LOGISTICS_PICKUP_PLANNED
                : 'not_required';

            $return->update([
                'status' => ReturnModel::STATUS_ACCEPTED,
                'logistics_status' => $nextLogisticsStatus,
                'accepted_at' => now(),
            ]);
            $return->orderItem?->forceFill([
                'return_status' => ReturnModel::STATUS_ACCEPTED,
                'payout_status' => 'blocked',
            ])->save();

            $workflow->recordHistory($return->order, $return->orderItem, 'return_logistics', $oldStatus, $nextLogisticsStatus, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => $nextLogisticsStatus === ReturnModel::LOGISTICS_PICKUP_PLANNED
                    ? 'Collecte du retour planifiée'
                    : 'Retour accepté',
                'message' => $nextLogisticsStatus === ReturnModel::LOGISTICS_PICKUP_PLANNED
                    ? 'OVANIE organise la collecte du colis retour.'
                    : 'Le retour est accepté et attend confirmation de réception au point de contrôle.',
            ]);
            $message = $nextLogisticsStatus === ReturnModel::LOGISTICS_PICKUP_PLANNED
                ? 'Retour approuvé et collecte planifiée.'
                : 'Retour approuvé sans collecte OVANIE.';
        } elseif ($return->logistics_status === ReturnModel::LOGISTICS_PENDING_PICKUP) {
            $return->update(['logistics_status' => ReturnModel::LOGISTICS_PICKUP_PLANNED]);
            $workflow->recordHistory($return->order, $return->orderItem, 'return_logistics', ReturnModel::LOGISTICS_PENDING_PICKUP, ReturnModel::LOGISTICS_PICKUP_PLANNED, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => 'Collecte du retour planifiée',
                'message' => 'La collecte du colis retour a été planifiée par OVANIE Logistics.',
            ]);
            $message = 'Collecte du retour planifiée.';
        } elseif ($return->logistics_status === ReturnModel::LOGISTICS_PICKUP_PLANNED) {
            $return->update(['logistics_status' => ReturnModel::LOGISTICS_IN_TRANSIT]);
            $workflow->recordHistory($return->order, $return->orderItem, 'return_logistics', ReturnModel::LOGISTICS_PICKUP_PLANNED, ReturnModel::LOGISTICS_IN_TRANSIT, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => 'Retour récupéré',
                'message' => 'Le colis retour est en route vers le point de contrôle OVANIE.',
            ]);
            $message = 'Retour marqué en transit.';
        } elseif (in_array($return->logistics_status, [ReturnModel::LOGISTICS_IN_TRANSIT, 'not_required'], true)) {
            $oldLogisticsStatus = $return->logistics_status;
            $return->update(['logistics_status' => ReturnModel::LOGISTICS_RECEIVED]);
            $workflow->recordHistory($return->order, $return->orderItem, 'return_logistics', $oldLogisticsStatus, ReturnModel::LOGISTICS_RECEIVED, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => 'Retour reçu et contrôlé',
                'message' => 'Le retour a été reçu au point de contrôle et peut être préparé pour remboursement.',
            ]);
            $message = 'Retour reçu. Il peut maintenant être préparé pour remboursement.';
        } elseif ($return->logistics_status === ReturnModel::LOGISTICS_RECEIVED) {
            $return = $refundService->prepareRefund($return, auth()->id());
            $workflow->recordHistory($return->order, $return->orderItem, 'return_refund', ReturnModel::LOGISTICS_RECEIVED, ReturnModel::LOGISTICS_REFUND_PENDING, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => 'Remboursement à exécuter',
                'message' => 'Le montant proportionnel au retour a été calculé et attend la référence du remboursement réel.',
                'metadata' => ['refund_amount' => (float) $return->refund_amount],
            ]);
            $message = 'Montant du remboursement calculé. Enregistrez la référence après exécution réelle du remboursement.';
        } elseif ($return->logistics_status === ReturnModel::LOGISTICS_REFUND_PENDING) {
            $return = $this->confirmReturnRefundFromRequest($request, $return, $refundService, $workflow);
            $message = 'Remboursement confirmé et ajustement vendeur enregistré.';
        }

        $this->notifyReturnClient($workflow, $return, $message);

        return back()->with('success', $message);
    }

    private function confirmReturnRefundFromRequest(
        Request $request,
        ReturnModel $return,
        ReturnRefundService $refundService,
        OrderWorkflowService $workflow
    ): ReturnModel {
        $data = $request->validate([
            'refund_reference' => ['required', 'string', 'max:190'],
            'refund_method' => ['required', 'in:mobile_money,bank_transfer,cash,paydunya_manual'],
        ]);

        $return = $refundService->confirmManualRefund(
            $return,
            $data['refund_reference'],
            $data['refund_method'],
            auth('admin')->id() ?? auth()->id()
        );

        $meta = is_array($return->meta) ? $return->meta : [];
        if (data_get($meta, 'vendor_decision.type') === 'refund') {
            data_set($meta, 'vendor_decision.refund_details', [
                'amount' => (float) $return->refund_amount,
                'method' => $data['refund_method'],
                'reference' => $data['refund_reference'],
                'refunded_at' => optional($return->refunded_at)->toIso8601String(),
            ]);
            if (data_get($meta, 'vendor_decision.published_at')) {
                data_set($meta, 'vendor_decision.client_label', 'Remboursé');
                data_set($meta, 'vendor_decision.client_message', 'Le remboursement de '.number_format((float) $return->refund_amount, 0, ',', ' ').' FCFA a été effectué. Référence : '.$data['refund_reference'].'.');
            }
            $return->forceFill(['meta' => $meta])->save();
            $return->refresh();
        }

        $workflow->recordHistory($return->order, $return->orderItem, 'return_refund', ReturnModel::LOGISTICS_REFUND_PENDING, ReturnModel::STATUS_REFUNDED, [
            'actor_type' => 'logistics',
            'user_id' => auth()->id(),
            'label' => 'Remboursement confirmé',
            'message' => 'Le remboursement exécuté a été enregistré et rapproché.',
            'metadata' => [
                'refund_amount' => (float) $return->refund_amount,
                'refund_reference' => $data['refund_reference'],
                'refund_method' => $data['refund_method'],
            ],
        ]);

        return $return;
    }

    private function notifyReturnClient(
        OrderWorkflowService $workflow,
        ReturnModel $return,
        string $message
    ): void {
        $return->loadMissing('client');
        $workflow->notify($return->client, 'Mise à jour de votre demande', $message, [
            'category' => 'returns',
            'order_id' => $return->order_id,
            'order_item_id' => $return->order_item_id,
            'url' => route('client.returns'),
        ]);
    }

    public function publishReturnDecision(
        Request $request,
        ReturnModel $return,
        ReturnRefundService $refundService
    ) {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Action ignorée : ce dossier de démonstration n’est plus utilisé.');
        }
        $return->loadMissing(['client', 'order.client', 'orderItem.product', 'vendor']);
        $meta = is_array($return->meta) ? $return->meta : [];
        $decision = (array) ($meta['vendor_decision'] ?? []);
        $decisionType = (string) ($decision['type'] ?? '');

        if ($decisionType === '') {
            // Compatibilité avec les dossiers réels déjà traités avant l'ajout du
            // champ vendor_decision : on déduit uniquement une décision certaine.
            $decisionType = match (true) {
                $return->status === ReturnModel::STATUS_REJECTED => 'reject',
                $return->status === ReturnModel::STATUS_REFUNDED => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'refund' => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'return' => 'accept',
                default => '',
            };
            $decision = [
                'type' => $decisionType,
                'response' => $return->vendor_response,
                'decided_at' => optional($return->accepted_at ?: $return->rejected_at ?: $return->updated_at)->toIso8601String(),
                'proofs' => [],
            ];
        }

        if (! in_array($decisionType, ['reject', 'accept', 'refund'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Aucune décision vendeur confirmable n’est disponible pour ce dossier.',
            ]);
        }

        if (! empty($decision['published_at'])) {
            return back()->with('info', 'Cette décision a déjà été transmise au client.');
        }

        $collection = (array) ($meta['collection'] ?? []);
        $driver = ! empty($collection['driver_id'])
            ? $this->realDriversQuery()->whereKey((int) $collection['driver_id'])->first()
            : null;

        if ($decisionType === 'accept') {
            $missingCollection = ! $driver
                || empty($collection['address'])
                || empty($collection['date'])
                || empty($collection['slot'])
                || empty($collection['destination']);

            if ($missingCollection) {
                throw ValidationException::withMessages([
                    'collection' => 'Planifiez d’abord la collecte avec un livreur OVANIE valide, la date, le créneau, l’adresse et la destination avant de transmettre le retour confirmé au client.',
                ]);
            }
        }
        $response = trim((string) ($decision['response'] ?? $return->vendor_response ?? ''));
        $productName = $return->orderItem?->product?->name ?: $return->product_name ?: 'Produit';
        $address = (string) ($collection['address'] ?? $return->order?->delivery_address ?? 'Adresse à confirmer');
        $slot = trim((string) (($collection['date'] ?? '').' '.($collection['slot'] ?? '')));
        $refundAmount = $decisionType === 'refund'
            ? (float) ($return->refund_amount ?: $refundService->calculateRefundAmount($return))
            : 0.0;
        $actorId = auth('admin')->id() ?? auth()->id();

        [$title, $message, $clientLabel] = match ($decisionType) {
            'reject' => [
                'Retour rejeté',
                'Votre demande de retour pour « '.$productName.' » a été rejetée. Motif : '.($response ?: 'Motif renseigné dans votre dossier.')
                    .(count((array) ($decision['proofs'] ?? [])) ? ' Les preuves du rejet sont maintenant disponibles dans votre espace client.' : ''),
                'Rejeté',
            ],
            'refund' => [
                $return->status === ReturnModel::STATUS_REFUNDED ? 'Remboursement effectué' : 'Remboursement confirmé',
                ($return->status === ReturnModel::STATUS_REFUNDED
                    ? 'Le remboursement de '.number_format($refundAmount, 0, ',', ' ').' FCFA pour « '.$productName.' » a été enregistré.'
                    : 'La boutique a décidé de rembourser « '.$productName.' » pour un montant calculé de '.number_format($refundAmount, 0, ',', ' ').' FCFA. OVANIE poursuit le traitement financier.')
                    .($response ? ' Détail : '.$response : ''),
                'Remboursé',
            ],
            default => [
                'Retour confirmé',
                'Votre retour pour « '.$productName.' » est confirmé. Collecte/livraison retour : '.$address
                    .($slot !== '' ? ', créneau '.$slot : ', créneau à planifier')
                    .($driver?->name ? ', livreur '.$driver->name : '')
                    .'.'.($response ? ' Détail vendeur : '.$response : ''),
                'Retour confirmé',
            ],
        };

        $decision['type'] = $decisionType;
        $decision['response'] = $response;
        $decision['published_at'] = now()->toIso8601String();
        $decision['published_by'] = $actorId;
        $decision['client_label'] = $clientLabel;
        $decision['client_message'] = $message;
        if ($decisionType === 'accept') {
            $decision['delivery_details'] = [
                'address' => $address,
                'date' => $collection['date'] ?? null,
                'slot' => $collection['slot'] ?? null,
                'driver_name' => $driver?->name,
                'driver_phone' => $driver?->phone,
                'vehicle' => $driver?->vehicle,
                'destination' => $collection['destination'] ?? null,
            ];
        } else {
            unset($decision['delivery_details']);
        }

        if ($decisionType === 'refund') {
            $decision['refund_details'] = [
                'amount' => $refundAmount,
                'method' => data_get($meta, 'refund_method'),
                'reference' => data_get($meta, 'refund_reference'),
                'refunded_at' => optional($return->refunded_at)->toIso8601String(),
            ];
        } else {
            unset($decision['refund_details']);
        }

        $meta['vendor_decision'] = $decision;
        $return->forceFill(['meta' => $meta])->save();

        $workflow = app(OrderWorkflowService::class);
        if ($return->orderItem) {
            $workflow->recordHistory(
                $return->order,
                $return->orderItem,
                'return_client_notification',
                $return->status,
                $return->status,
                [
                    'actor_type' => 'logistics',
                    'user_id' => $actorId,
                    'label' => $title.' transmis au client',
                    'message' => $message,
                    'metadata' => ['return_id' => $return->id, 'decision_type' => $decisionType],
                ]
            );
        }

        $workflow->notify($return->client ?: $return->order?->client, $title, $message, [
            'category' => 'returns',
            'order_id' => $return->order_id,
            'order_item_id' => $return->order_item_id,
            'url' => route('client.returns'),
        ]);

        return back()->with('success', $clientLabel.' : les informations ont été transmises au client.');
    }

    public function rejectReturn(ReturnModel $return)
    {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Action ignorée : ce dossier de démonstration n’est plus utilisé.');
        }

        if (in_array($return->logistics_status, [ReturnModel::LOGISTICS_REFUND_PENDING, ReturnModel::LOGISTICS_REFUNDED], true)) {
            return back()->with('error', 'Ce retour est déjà engagé dans le processus de remboursement et ne peut plus être rejeté.');
        }

        $return->loadMissing(['order.client', 'orderItem']);
        $oldStatus = $return->status;
        $return->update([
            'status' => ReturnModel::STATUS_REJECTED,
            'logistics_status' => 'not_required',
            'rejected_at' => now(),
            'resolved_at' => now(),
        ]);

        if ($return->orderItem) {
            $return->orderItem->forceFill(['return_status' => 'rejected'])->save();
            app(OrderWorkflowService::class)->markPayoutsReadyWhenEligible($return->order);
            $rejectionLabel = match ($return->return_type) {
                'claim' => 'Réclamation refusée',
                'refund' => 'Demande de remboursement refusée',
                default => 'Retour rejeté après contrôle',
            };

            app(OrderWorkflowService::class)->recordHistory($return->order, $return->orderItem, 'return_logistics', $oldStatus, ReturnModel::STATUS_REJECTED, [
                'actor_type' => 'logistics',
                'user_id' => auth()->id(),
                'label' => $rejectionLabel,
            ]);
        }

        $clientRejection = match ($return->return_type) {
            'claim' => ['Réclamation refusée', 'Votre réclamation a été refusée après analyse. Contactez le support OVANIE en cas de contestation.'],
            'refund' => ['Demande de remboursement refusée', 'Votre demande de remboursement a été refusée après analyse. Contactez le support OVANIE en cas de contestation.'],
            default => ['Retour rejeté', 'Votre demande de retour a été rejetée après contrôle. Contactez le support OVANIE en cas de contestation.'],
        };

        app(OrderWorkflowService::class)->notify(
            $return->order?->client,
            $clientRejection[0],
            $clientRejection[1],
            [
                'category' => 'returns',
                'order_id' => $return->order_id,
                'order_item_id' => $return->order_item_id,
                'url' => route('client.returns'),
            ]
        );

        return back()->with('success', 'Retour rejeté.');
    }

    private function orderBase()
    {
        return Order::with(['client', 'items.product.shop'])
            ->where(fn ($order) => $this->operationalOrderScope($order))
            ->whereHas('items', fn ($item) => $this->onlyOvanieItems($item));
    }

    private function deliveryItems()
    {
        return $this->onlyOvanieItems(OrderItem::with([
            'order.client',
            'product.shop',
            'shipment',
            'latestDeliveryAssignment.driver.currentLocation',
            'latestDeliveryAssignment.latestLocation',
            'deliveryAssignments.driver',
        ]))
            ->whereHas('order', fn ($order) => $this->operationalOrderScope($order))
            ->where(function ($statusQuery) {
                // Les lignes OVANIE sont visibles dès leur création dans Gestion des
                // expéditions. Tant que le vendeur ne les a pas marquées prêtes,
                // elles restent "En attente de préparation" et ne sont pas affectables.
                $statusQuery->whereNull('delivery_status')
                    ->orWhereIn('delivery_status', [
                        OrderWorkflowService::DELIVERY_PENDING,
                        OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                        OrderWorkflowService::DELIVERY_ASSIGNED,
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        OrderWorkflowService::DELIVERY_DELIVERED,
                        OrderWorkflowService::DELIVERY_FAILED,
                    ]);
            })
            ->where(function ($query) {
                $query->where('delivery_price', '>', 0)
                    ->orWhereHas('order', fn ($order) => $order->where('delivery_pricing_status', 'calculated'));
            })
            ->latest('updated_at');
    }

    private function operationalOrderScope($query)
    {
        LogisticsOperationalDataScope::orders($query);

        return $query->where(function ($order) {
            $order->whereIn('payment_status', ['paid', 'commission_paid'])
                ->orWhere(function ($cod) {
                    // Une commande contre-remboursement garde payment_status='pending'
                    // pendant TOUT son cycle actif (le client paie à la livraison), donc
                    // son statut global (orders.status, voir Order::refreshGlobalStatusFromItems())
                    // reste 'pending' dès la création jusqu'à l'expédition, avant de
                    // passer par 'shipped' puis 'completed'. N'autoriser qu'une liste
                    // figée de statuts tardifs ('confirmed','shipped',...) faisait
                    // disparaître la commande des écrans logistiques dès la préparation,
                    // avant même qu'un livreur ne puisse lui être affecté. On exclut donc
                    // seulement les statuts qui signifient réellement "plus actif".
                    $cod->where('payment_method', 'cash_on_delivery')
                        ->where('payment_status', 'pending')
                        ->whereNotIn('status', ['cancelled', 'failed']);
                });
        });
    }

    private function codCollectionRequiredForItem(OrderItem $item): bool
    {
        $item->loadMissing('order.items');
        $order = $item->order;

        if (! $order
            || $order->payment_method !== 'cash_on_delivery'
            || $order->payment_status === 'paid') {
            return false;
        }

        $group = app(ClientDeliveryGroupService::class)
            ->findForItem($order, (int) $item->id);

        if (! $group || $group['is_paid']) {
            return false;
        }

        // Le formulaire clôture la ligne courante. Toutes les autres lignes de
        // cette même livraison doivent donc déjà être livrées.
        return $group['items']
            ->where('id', '!=', $item->id)
            ->every(fn (OrderItem $otherItem) => $otherItem->isVendorDelivered());
    }

    private function onlyOvanieItems($query)
    {
        if ($this->schemaHasColumn('order_items', 'delivery_provider')) {
            return $query->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE);
        }

        // Compatibilité anciennes commandes non migrées : boutique OVANIE Logistics.
        return $query->whereHas('product.shop', fn ($shop) => $shop->where('logistics_type', 'ovanie'));
    }

    private function ensureOvanieShipment(OrderItem $item): void
    {
        if ($this->schemaHasColumn('order_items', 'delivery_provider')) {
            abort_unless(
                $item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE,
                403,
                'Cette expédition n’est pas prise en charge par OVANIE Logistics.'
            );

            return;
        }

        $item->loadMissing('product.shop');
        abort_unless(
            $item->product?->shop?->logistics_type === 'ovanie',
            403,
            'Cette expédition n’est pas prise en charge par OVANIE Logistics.'
        );
    }

    private function mapLegacyDeliveryFilter(string $status): string
    {
        return match ($status) {
            'assigned' => OrderWorkflowService::DELIVERY_ASSIGNED,
            'picked_up' => OrderWorkflowService::DELIVERY_PICKED_UP,
            'in_delivery' => OrderWorkflowService::DELIVERY_IN_TRANSIT,
            'delivery_failed' => OrderWorkflowService::DELIVERY_FAILED,
            default => OrderWorkflowService::DELIVERY_PENDING,
        };
    }

    private function applyShipmentFilters($query, Request $request): void
    {
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('driver_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_plate', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$search}%")
                        ->orWhere('delivery_commune', 'like', "%{$search}%")
                        ->orWhere('delivery_zone', 'like', "%{$search}%"))
                    ->orWhereHas('order.client', fn ($client) => $client->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"));
            });
        }

        if ($zone = $request->query('zone')) {
            $query->whereHas('order', fn ($order) => $order->where('delivery_commune', $zone)->orWhere('delivery_zone', $zone));
        }
    }


    /**
     * Construit la liste des livreurs à comparer à partir des données réelles.
     * Seuls les livreurs actifs possédant exactement le véhicule calculé pour
     * la mission sont proposés. Les ETA proviennent d'itinéraires routiers réels.
     */
    private function assignmentDriversForGroup(
        array $group,
        LogisticsShipmentWorkflowService $shipmentWorkflow,
        RoutingService $routing,
        ?float $shopLat,
        ?float $shopLng,
        array $clientLeg
    ): Collection {
        $requiredVehicleCode = (string) ($group['vehicle_code'] ?? '');
        $groupItemIds = $group['items']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $firstPickupStop = collect($group['pickup_stops'] ?? [])->first();
        $pickupShop = is_array($firstPickupStop) ? ($firstPickupStop['shop'] ?? null) : null;
        $pickupZone = trim((string) (
            $pickupShop?->commune
            ?: $pickupShop?->city
            ?: $pickupShop?->region
            ?: ''
        ));
        $normaliseZone = static fn (?string $value): string => mb_strtolower(
            trim((string) preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', (string) $value)))
        );
        $pickupZoneKey = $normaliseZone($pickupZone);

        // La Logistique peut fermer une commune (désactivation de la commune elle-même
        // ou de toutes les zones Territoire qui la couvrent). Dans ce cas, aucun livreur
        // ne doit être proposé pour une NOUVELLE mission dont la livraison cible cette
        // commune : c'est le comportement le plus sûr (pas de mission planifiée sur une
        // zone que la Logistique vient de fermer), plutôt qu'un simple avertissement
        // qu'un opérateur pressé pourrait ignorer.
        $deliveryCommuneName = trim((string) ($group['order']?->delivery_commune ?? ''));
        $deliveryCommuneOpen = $this->isDeliveryCommuneOpen($deliveryCommuneName);

        return $this->realDriversQuery()
            ->where('is_active', true)
            ->where('onboarding_status', DeliveryDriver::ONBOARDING_ACTIVE)
            ->with('currentLocation')
            ->withCount([
                'activeAssignments as conflicting_assignments_count' => fn ($query) => $query->whereNotIn('order_item_id', $groupItemIds),
            ])
            ->get()
            ->filter(fn (DeliveryDriver $driver) => $shipmentWorkflow->driverMatchesRequiredVehicle($driver, $requiredVehicleCode))
            ->values()
            ->map(function (DeliveryDriver $driver) use (
                $shipmentWorkflow,
                $routing,
                $shopLat,
                $shopLng,
                $clientLeg,
                $pickupZone,
                $pickupZoneKey,
                $normaliseZone,
                $deliveryCommuneOpen,
                $deliveryCommuneName
            ) {
                $busyOnAnotherMission = (int) $driver->conflicting_assignments_count > 0;
                $status = mb_strtolower(trim((string) $driver->status));
                $busyByStatus = in_array($status, ['en livraison', 'en mission'], true);
                $available = $shipmentWorkflow->isDriverAvailable($driver)
                    && ! $busyOnAnotherMission
                    && ! $busyByStatus
                    && $deliveryCommuneOpen;

                [$driverLat, $driverLng, $gpsRecordedAt] = $this->storedDriverCoordinates($driver);
                $pickupLeg = $this->assignmentRoadMetrics(
                    $routing,
                    $driverLat,
                    $driverLng,
                    $shopLat,
                    $shopLng
                );

                $driverZoneKeys = collect($driver->interventionZones())
                    ->map($normaliseZone)
                    ->filter()
                    ->values();

                $zoneMatch = $pickupZoneKey !== ''
                    && $driverZoneKeys->contains(function (string $driverZoneKey) use ($pickupZoneKey) {
                        return $driverZoneKey === $pickupZoneKey
                            || str_contains($driverZoneKey, $pickupZoneKey)
                            || str_contains($pickupZoneKey, $driverZoneKey);
                    });

                $pickupEta = $pickupLeg['duration_minutes'];
                $recommendation = ! $available
                    ? (! $deliveryCommuneOpen
                        ? 'Commune de livraison fermée par la Logistique ('.$deliveryCommuneName.')'
                        : ($busyOnAnotherMission || $busyByStatus ? 'Déjà engagé sur une autre mission' : 'Indisponible'))
                    : ($zoneMatch
                        ? 'Dans la zone de collecte' . ($pickupZone !== '' ? " ({$pickupZone})" : '')
                        : ($pickupEta !== null
                            ? "À {$pickupEta} min du point de collecte"
                            : ((bool) $driver->is_online
                                ? 'Disponible · position GPS à actualiser'
                                : 'Disponible · actuellement hors ligne')));

                $driver->setAttribute('busy', ! $available);
                $driver->setAttribute('compatible', true);
                $driver->setAttribute('eta_collecte_min', $pickupEta);
                // ETA client = boutique -> client uniquement. Elle ne contient
                // pas le temps livreur -> boutique.
                $driver->setAttribute('eta_client_min', $clientLeg['duration_minutes']);
                $driver->setAttribute('eta_collecte_distance_km', $pickupLeg['distance_km']);
                $driver->setAttribute('eta_client_distance_km', $clientLeg['distance_km']);
                $driver->setAttribute('eta_collecte_source', $pickupLeg['source']);
                $driver->setAttribute('eta_client_source', $clientLeg['source']);
                $driver->setAttribute('assignment_gps_recorded_at', $gpsRecordedAt?->toIso8601String());
                $driver->setAttribute('assignment_zone_match', $zoneMatch);
                $driver->setAttribute('assignment_pickup_zone', $pickupZone ?: null);
                $driver->setAttribute('assignment_recommendation', $recommendation);
                $driver->setAttribute('assignment_zone_rank', $zoneMatch ? 0 : 1);
                $driver->setAttribute('assignment_eta_rank', $pickupEta ?? 999999);
                $driver->setAttribute('assignment_online_rank', $driver->is_online ? 0 : 1);
                $driver->setAttribute(
                    'assignment_busy_reason',
                    ! $deliveryCommuneOpen
                        ? 'Commune de livraison fermée par la Logistique'
                        : ($busyOnAnotherMission || $busyByStatus
                            ? 'Déjà engagé sur une autre mission'
                            : (! $available ? 'Indisponible' : null))
                );

                return $driver;
            })
            ->sortBy([
                ['busy', 'asc'],
                ['assignment_zone_rank', 'asc'],
                ['assignment_eta_rank', 'asc'],
                ['assignment_online_rank', 'asc'],
                ['rating', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * Indique si une commune de livraison est encore ouverte, c'est-à-dire
     * qu'elle est active ET rattachée à au moins une zone Territoire active
     * (voir AbidjanCommune::scopeAvailableForOnboarding()). Une commune que
     * la Logistique ne gère pas du tout (absente de abidjan_communes, ex.
     * ville hors Abidjan) n'est volontairement PAS bloquée par cette règle :
     * elle est hors du périmètre du module Territoire.
     */
    private function isDeliveryCommuneOpen(string $communeName): bool
    {
        $communeName = trim($communeName);
        if ($communeName === '') {
            return true;
        }

        $commune = AbidjanCommune::query()->where('name', $communeName)->first(['id']);
        if (! $commune) {
            return true;
        }

        return AbidjanCommune::isAvailableForOnboarding((int) $commune->id);
    }

    /**
     * Dernière position réellement enregistrée pour le livreur. On privilégie
     * driver_locations, puis les coordonnées miroir de delivery_drivers.
     */
    private function storedDriverCoordinates(DeliveryDriver $driver): array
    {
        $location = $driver->currentLocation;
        if ($location
            && $location->recorded_at
            && $location->recorded_at->gte(now()->subMinutes(30))
            && is_numeric($location->latitude)
            && is_numeric($location->longitude)
            && ((float) $location->latitude !== 0.0 || (float) $location->longitude !== 0.0)) {
            return [
                (float) $location->latitude,
                (float) $location->longitude,
                $location->recorded_at,
            ];
        }

        if ($driver->last_seen_at
            && $driver->last_seen_at->gte(now()->subMinutes(30))
            && is_numeric($driver->latitude)
            && is_numeric($driver->longitude)
            && ((float) $driver->latitude !== 0.0 || (float) $driver->longitude !== 0.0)) {
            return [
                (float) $driver->latitude,
                (float) $driver->longitude,
                $driver->last_seen_at,
            ];
        }

        return [null, null, null];
    }

    /**
     * Retourne un trajet routier réel. Les calculs déjà effectués sont relus
     * depuis delivery_route_cache ; sinon Mapbox/OSRM/TomTom est interrogé via
     * RoutingService puis le résultat est persisté dans cette même table.
     */
    private function assignmentRoadMetrics(
        RoutingService $routing,
        ?float $fromLat,
        ?float $fromLng,
        ?float $toLat,
        ?float $toLng
    ): array {
        $empty = [
            'distance_km' => null,
            'duration_minutes' => null,
            'provider' => null,
            'source' => null,
        ];

        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return $empty;
        }

        $fromLat = round($fromLat, 7);
        $fromLng = round($fromLng, 7);
        $toLat = round($toLat, 7);
        $toLng = round($toLng, 7);

        if ($this->schemaHasTable('delivery_route_cache')) {
            $cached = DeliveryRouteCache::query()
                ->where('origin_lat', $fromLat)
                ->where('origin_lng', $fromLng)
                ->where('destination_lat', $toLat)
                ->where('destination_lng', $toLng)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            if ($cached && $cached->duration_minutes !== null) {
                return [
                    'distance_km' => $cached->distance_km,
                    'duration_minutes' => $cached->duration_minutes,
                    'provider' => $cached->provider,
                    'source' => 'database_route_cache',
                ];
            }
        }

        $route = $routing->route($fromLat, $fromLng, $toLat, $toLng);
        if (! ($route['success'] ?? false) || ! is_numeric($route['duration_minutes'] ?? null)) {
            return $empty;
        }

        $provider = trim((string) ($route['provider'] ?? 'routing')) ?: 'routing';

        if ($this->schemaHasTable('delivery_route_cache')) {
            DeliveryRouteCache::updateOrCreate([
                'provider' => $provider,
                'origin_lat' => $fromLat,
                'origin_lng' => $fromLng,
                'destination_lat' => $toLat,
                'destination_lng' => $toLng,
            ], [
                'distance_km' => is_numeric($route['distance_km'] ?? null) ? (float) $route['distance_km'] : null,
                'duration_minutes' => (int) $route['duration_minutes'],
                'traffic_delay_minutes' => is_numeric($route['traffic_delay_minutes'] ?? null)
                    ? (int) $route['traffic_delay_minutes']
                    : null,
                'no_traffic_duration_minutes' => is_numeric($route['no_traffic_duration_minutes'] ?? null)
                    ? (int) $route['no_traffic_duration_minutes']
                    : null,
                'route_geometry' => is_array($route['route_geometry'] ?? null)
                    ? $route['route_geometry']
                    : null,
                'raw_response' => is_array($route['raw'] ?? null) ? $route['raw'] : [],
                'expires_at' => now()->addHours(12),
            ]);
        }

        return [
            'distance_km' => is_numeric($route['distance_km'] ?? null) ? (float) $route['distance_km'] : null,
            'duration_minutes' => (int) $route['duration_minutes'],
            'provider' => $provider,
            'source' => 'routing_provider',
        ];
    }

    private function driverAssignmentVehicleReference(DeliveryDriver $driver): string
    {
        $profile = is_array($driver->profile) ? $driver->profile : [];

        foreach ([
            data_get($profile, 'vehicle_plate'),
            data_get($profile, 'registration'),
            data_get($profile, 'plate'),
            data_get($profile, 'vehicle.plate'),
            data_get($profile, 'vehicle.registration'),
        ] as $candidate) {
            if (filled($candidate)) {
                return trim((string) $candidate);
            }
        }

        // Le schéma historique DeliveryDriver ne possède pas de colonne dédiée
        // à l'immatriculation. Le champ vehicle est donc la seule référence
        // véhicule disponible lorsque le profil n'en fournit pas une séparément.
        return trim((string) $driver->vehicle);
    }

    private function orderDeliveryAddress(?Order $order): string
    {
        if (! $order) {
            return '—';
        }

        $parts = [
            $order->getAttribute('delivery_address'),
            $order->address,
            $order->delivery_quartier,
            $order->delivery_commune,
            $order->delivery_city,
        ];

        $clean = collect($parts)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();

        return $clean->isNotEmpty() ? $clean->implode(' - ') : '—';
    }

    private function shopPickupAddress(?Shop $shop): string
    {
        if (! $shop) {
            return '—';
        }

        $parts = [
            $shop->address,
            $shop->landmark,
            $shop->district,
            $shop->commune,
            $shop->city,
            $shop->region,
        ];

        $clean = collect($parts)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();

        return $clean->isNotEmpty() ? $clean->implode(' - ') : 'Adresse boutique non renseignée';
    }

    private function verifiedDestinationCoordinates(?Order $order): array
    {
        $coordinates = app(\App\Services\Geo\DeliveryCoordinateService::class)->forOrder($order);

        if (! ($coordinates['verified'] ?? false)) {
            return [null, null];
        }

        return [$coordinates['latitude'], $coordinates['longitude']];
    }

    private function verifiedShopCoordinates(?Shop $shop): array
    {
        if (! $shop) {
            return [null, null];
        }

        $source = strtolower(trim((string) $shop->geo_source));
        $verified = $shop->geo_status === Shop::GEO_STATUS_VERIFIED
            || in_array($source, ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'], true);

        return $verified ? $this->shopCoordinates($shop) : [null, null];
    }

    private function emptyMissionRoute(string $message): array
    {
        return [
            'success' => false,
            'provider' => null,
            'message' => $message,
            'geometry' => null,
            'distance_km' => null,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'arrival_at' => null,
            'calculated_at' => null,
            'points' => [],
            'pickup_stops' => [],
            'destination' => null,
            'invalid_stops' => [],
            'has_coordinate_warning' => false,
        ];
    }

    private function destinationCoordinates(?Order $order): array
    {
        $coordinates = app(\App\Services\Geo\DeliveryCoordinateService::class)->forOrder($order);

        return [$coordinates['latitude'], $coordinates['longitude']];
    }

    private function shopCoordinates(?Shop $shop): array
    {
        if ($shop && is_numeric($shop->latitude) && is_numeric($shop->longitude) && abs((float) $shop->latitude) > 0 && abs((float) $shop->longitude) > 0) {
            return [(float) $shop->latitude, (float) $shop->longitude];
        }

        return [null, null];
    }

    private function ovanieShopMarkers(): array
    {
        $query = LogisticsOperationalDataScope::shops(Shop::query())->where('logistics_type', 'ovanie');

        if ($this->schemaHasColumn('shops', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($this->schemaHasColumn('shops', 'status')) {
            $query->where(function ($q) {
                $q->whereIn('status', ['approved', 'active', 'actif'])
                    ->orWhereNull('status');
            });
        }

        if ($this->schemaHasColumn('shops', 'geo_source')) {
            $query->whereIn('geo_source', ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified']);
        }

        return $query->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('name')->get()->filter(function (Shop $shop) {
            return is_numeric($shop->latitude) && is_numeric($shop->longitude)
                && (abs((float) $shop->latitude) > 0 || abs((float) $shop->longitude) > 0);
        })->map(function (Shop $shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name,
                'address' => $this->shopPickupAddress($shop),
                'commune' => $shop->commune ?: $shop->city ?: $shop->region ?: 'Abidjan',
                'phone' => $shop->whatsapp ?: '',
                'lat' => (float) $shop->latitude,
                'lng' => (float) $shop->longitude,
                'has_real_position' => true,
                'geo_status' => $shop->geo_status ?: Shop::GEO_STATUS_VERIFICATION_REQUIRED,
                'geo_status_label' => $shop->geo_status_label,
                'geo_status_severity' => $shop->geo_status_severity,
                'geo_precision' => $shop->geo_precision,
                'geo_precision_score' => $shop->geo_precision_score,
                'geo_source' => $shop->geo_source,
                'geo_verified_at' => optional($shop->geo_verified_at)->toIso8601String(),
            ];
        })->values()->all();
    }

    private function stats(): array
    {
        $today = $this->orderBase()->whereDate('created_at', today())->count();
        $enRoute = (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_IN_TRANSIT)->count();
        $livrees = (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_DELIVERED)->count();
        $pending = (clone $this->deliveryItems()->getQuery())->where(fn ($q) => $q->whereNull('delivery_status')->orWhereIn('delivery_status', [OrderWorkflowService::DELIVERY_PENDING, OrderWorkflowService::DELIVERY_READY_FOR_PICKUP, OrderWorkflowService::DELIVERY_ASSIGNED]))->count();
        $retards = (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_IN_TRANSIT)
            ->where('vendor_delivery_updated_at', '<', now()->subDay())
            ->count();
        $retours = ReturnModel::query()->get()->reject(fn (ReturnModel $return) => $this->isDemonstrationReturn($return))->count();
        $total = max($enRoute + $livrees + $pending + $retards, 1);

        return compact('today', 'enRoute', 'livrees', 'pending', 'retards', 'retours', 'total');
    }

    private function shipmentCounts(): array
    {
        return [
            'prepare' => (clone $this->deliveryItems()->getQuery())
                ->where(function ($query) {
                    $query->whereNull('delivery_status')
                        ->orWhereIn('delivery_status', [
                            OrderWorkflowService::DELIVERY_PENDING,
                            OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                        ]);
                })
                ->count(),
            'ship' => (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_ASSIGNED)->count(),
            'route' => (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_IN_TRANSIT)->count(),
            'done' => (clone $this->deliveryItems()->getQuery())->where('delivery_status', OrderWorkflowService::DELIVERY_DELIVERED)->count(),
        ];
    }

    private function storedDrivers(Collection $items): Collection
    {
        $assigned = $items->filter(fn ($item) => $item->driver_name)->groupBy('driver_name');
        $vehicleAppearanceService = app(VehicleAppearanceService::class);

        $drivers = $this->realDriversQuery()->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (DeliveryDriver $driver) use ($assigned, $vehicleAppearanceService) {
                $rows = $assigned->get($driver->name, collect());
                $appearance = $vehicleAppearanceService->forDriver($driver, false, false);

                return [
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'zone' => $driver->interventionZonesLabel(2),
                    'vehicle' => $appearance['type_label'],
                    'vehicle_plate' => $appearance['plate'],
                    'vehicle_color' => $appearance['color'],
                    'vehicle_color_hex' => $appearance['color_hex'],
                    'vehicle_photo_url' => $appearance['photo_url'],
                    'vehicle_photo_is_real' => $appearance['photo_is_real'],
                    'vehicle_reference_asset_url' => $appearance['reference_photo_url'],
                    'fleet_vehicle_code' => $appearance['fleet_code'],
                    'rating' => $driver->rating,
                    'deliveries' => $rows->count(),
                    'status' => $rows->where('vendor_delivery_status', 'in_delivery')->count()
                        ? 'En livraison'
                        : ($driver->status ?: 'Disponible'),
                    'performance' => $this->driverSuccessRate($rows),
                    'updated' => optional($rows->first())->driver_location_updated_at ?: optional($rows->first())->vendor_delivery_updated_at,
                    'success_rate' => $this->driverSuccessRate($rows),
                    'retards' => $rows->where('vendor_delivery_status', 'in_delivery')->where('vendor_delivery_updated_at', '<', now()->subDay())->count(),
                    'items' => $this->driverItemData($rows),
                ];
            });

        $knownNames = $drivers->pluck('name')->all();
        $legacyDrivers = $this->collectDrivers($items)
            ->reject(fn ($driver) => in_array($driver['name'], $knownNames, true));

        return $drivers->concat($legacyDrivers)->values();
    }

private function driverItemData(Collection $rows): array
    {
        return $rows->map(function ($item) {
            return [
                'id' => $item->id,
                'order_number' => $item->order?->order_number ?: '-',
                'client_name' => $item->order?->client?->name ?? '-',
                'address' => $item->order?->delivery_commune ? ($item->order->delivery_commune . ', ' . ($item->order->address ?? '')) : ($item->order?->address ?? '-'),
                'status' => $item->vendor_delivery_status ?: 'pending',
                'time' => $item->updated_at ? $item->updated_at->format('H:i') : '-',
                'note' => $item->vendor_delivery_note,
                'date' => $item->updated_at ? $item->updated_at->format('d M Y') : '-',
            ];
        })->values()->toArray();
    }

    private function collectDrivers(Collection $items): Collection
    {
        return $items->filter(fn ($item) => $item->driver_name)
            ->groupBy('driver_name')
            ->map(function ($rows, $name) {
                $first = $rows->first();

                return [
                    'name' => $name,
                    'phone' => $first->driver_phone ?: '-',
                    'zone' => $first->order?->delivery_commune ?: 'Abidjan',
                    'vehicle' => $first->vehicle_plate ?: 'Véhicule',
                    'rating' => null,
                    'deliveries' => $rows->count(),
                    'status' => $rows->where('vendor_delivery_status', 'in_delivery')->count() ? 'En livraison' : 'Disponible',
                    'performance' => $this->driverSuccessRate($rows),
                    'updated' => $first->driver_location_updated_at ?: $first->vendor_delivery_updated_at,
                    'success_rate' => $this->driverSuccessRate($rows),
                    'retards' => $rows->where('vendor_delivery_status', 'in_delivery')->where('vendor_delivery_updated_at', '<', now()->subDay())->count(),
                    'items' => $rows->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'order_number' => $item->order?->order_number ?: '—',
                            'client_name' => $item->order?->client?->name ?? '—',
                            'address' => $item->order?->delivery_commune ? ($item->order->delivery_commune . ', ' . ($item->order->address ?? '')) : ($item->order?->address ?? '—'),
                            'status' => $item->vendor_delivery_status ?: 'pending',
                            'time' => $item->updated_at ? $item->updated_at->format('H:i') : '—',
                            'note' => $item->vendor_delivery_note,
                            'date' => $item->updated_at ? $item->updated_at->format('d M Y') : '—',
                        ];
                    })->values()->toArray(),
                ];
            })->values();
    }

    private function realIncidentsQuery()
    {
        $query = \App\Models\DeliveryIncident::query();

        return $query->whereHas('order', function ($orderQuery) {
            LogisticsOperationalDataScope::orders($orderQuery);
        });
    }

    private function realReturnsQuery()
    {
        return ReturnModel::query()->where(function ($query) {
            $query->whereNull('order_reference')
                ->orWhere('order_reference', 'not like', 'CMD-DEMO-RETURN-%');
        });
    }

    private function driverSuccessRate(Collection $rows): ?int
    {
        $delivered = $rows->filter(fn ($item) => in_array((string) ($item->delivery_status ?? $item->vendor_delivery_status), [
            OrderWorkflowService::DELIVERY_DELIVERED,
            'delivered',
        ], true))->count();
        $failed = $rows->filter(fn ($item) => in_array((string) ($item->delivery_status ?? $item->vendor_delivery_status), [
            OrderWorkflowService::DELIVERY_FAILED,
            'failed',
            'cancelled',
        ], true))->count();
        $terminal = $delivered + $failed;

        return $terminal > 0 ? (int) round(($delivered / $terminal) * 100) : null;
    }

    private function realDriversQuery()
    {
        return LogisticsOperationalDataScope::drivers(DeliveryDriver::query());
    }

    private function schemaHasTable(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasTable($table);
    }

    private function schemaHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table.'.'.$column;

        return $cache[$key] ??= Schema::hasColumn($table, $column);
    }

    private function isDemonstrationReturn(ReturnModel $return): bool
    {
        return data_get($return->meta, 'demo_seeder') === 'logistics-returns'
            || str_starts_with((string) $return->order_reference, 'CMD-DEMO-RETURN-');
    }

}
