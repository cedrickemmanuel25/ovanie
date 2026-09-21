<?php

namespace App\Http\Controllers;

use App\Models\DeliveryDriver;
use App\Models\DeliveryRoute;
use App\Models\Shipment;
use App\Services\Geo\RouteOptimizationService;
use App\Services\Geo\RoutingService;
use App\Services\LogisticsShipmentWorkflowService;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogisticsRouteController extends Controller
{
    public function index(): View
    {
        return view('logistics.routes', [
            'drivers' => LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
                ->where('is_active', true)
                ->orderByDesc('is_online')
                ->orderBy('name')
                ->get()
                ->filter(fn (DeliveryDriver $driver) => app(LogisticsShipmentWorkflowService::class)->isDriverAvailable($driver))
                ->values(),
            'shipments' => LogisticsOperationalDataScope::shipments(Shipment::query())
                ->with(['order.client', 'shop', 'orderItem.latestDeliveryAssignment.driver'])
                ->where('provider_type', 'ovanie')
                ->whereIn('status', ['ready_for_pickup', 'assigned', 'picked_up'])
                ->whereNotNull('pickup_latitude')
                ->whereNotNull('pickup_longitude')
                ->whereNotNull('delivery_latitude')
                ->whereNotNull('delivery_longitude')
                ->latest()
                ->take(100)
                ->get(),
            'routes' => DeliveryRoute::query()
                ->with(['driver', 'stops.shipment.order'])
                ->latest()
                ->take(20)
                ->get(),
        ]);
    }

    public function optimize(
        Request $request,
        RouteOptimizationService $optimization,
        LogisticsShipmentWorkflowService $shipmentWorkflow
    ) {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:delivery_drivers,id'],
            'shipment_ids' => ['required', 'array', 'min:1', 'max:30'],
            'shipment_ids.*' => ['integer', 'distinct', 'exists:shipments,id'],
        ]);

        $driver = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->findOrFail((int) $data['driver_id']);
        if (! $shipmentWorkflow->isDriverAvailable($driver)) {
            throw ValidationException::withMessages([
                'driver_id' => 'Ce livreur n’est pas disponible pour une nouvelle tournée.',
            ]);
        }

        $shipments = LogisticsOperationalDataScope::shipments(Shipment::query())
            ->with(['orderItem.shipment', 'orderItem.latestDeliveryAssignment'])
            ->whereIn('id', $data['shipment_ids'])
            ->where('provider_type', 'ovanie')
            ->whereIn('status', ['ready_for_pickup', 'assigned', 'picked_up'])
            ->get();

        if ($shipments->count() !== count($data['shipment_ids'])) {
            throw ValidationException::withMessages([
                'shipment_ids' => 'Une ou plusieurs expéditions sélectionnées ne sont plus disponibles pour une tournée.',
            ]);
        }

        foreach ($shipments as $shipment) {
            $item = $shipment->orderItem;
            if (! $item || ! $shipmentWorkflow->driverCanHandle($driver, $shipmentWorkflow->requiredVehicleCode($item))) {
                throw ValidationException::withMessages([
                    'driver_id' => "Le véhicule de {$driver->name} n’est pas compatible avec toutes les expéditions sélectionnées.",
                ]);
            }

            $assignment = $item->latestDeliveryAssignment;
            if ($assignment
                && in_array($assignment->status, ['assigned', 'picked_up', 'in_transit'], true)
                && (int) $assignment->driver_id !== (int) $driver->id) {
                throw ValidationException::withMessages([
                    'shipment_ids' => "L’expédition #{$shipment->id} est déjà affectée à un autre livreur. Réaffectez-la avant de l’ajouter à cette tournée.",
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'route' => $optimization->optimizeForDriver($driver->id, $shipments->pluck('id')->all()),
        ]);
    }

    public function calculateShipment(Shipment $shipment, RoutingService $routing)
    {
        abort_unless(
            $shipment->provider_type === 'ovanie'
            && LogisticsOperationalDataScope::shipments(Shipment::query())->whereKey($shipment->getKey())->exists(),
            404
        );
        $routing->estimateShipment($shipment);

        return back()->with('success', 'Itinéraire recalculé à partir des coordonnées disponibles.');
    }
}
