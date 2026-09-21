<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Geo\DriverTrackingService;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request, DriverTrackingService $tracking)
    {
        $shipments = Shipment::query()
            ->with([
                'order.client',
                'orderItem.latestDeliveryAssignment.driver',
                'latestDriverLocation',
            ])
            ->whereHas('order', function ($query) use ($request) {
                $query->where('client_id', $request->user()->id);
            })
            ->latest()
            ->paginate(max(1, min(100, (int) $request->query('per_page', 20))));

        $shipments->getCollection()->transform(
            fn (Shipment $shipment) => $tracking->trackingPayload($shipment, false)
        );

        return response()->json($shipments);
    }

    public function show(Request $request, Shipment $shipment, DriverTrackingService $tracking)
    {
        $shipment->loadMissing([
            'order.client',
            'orderItem.latestDeliveryAssignment.driver',
            'latestDriverLocation',
        ]);

        abort_if(
            ! $shipment->order || (int) $shipment->order->client_id !== (int) $request->user()->id,
            403
        );

        return response()->json([
            'data' => $tracking->trackingPayload($shipment, false),
        ]);
    }
}
