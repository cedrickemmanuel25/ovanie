<?php

namespace App\Http\Controllers;

use App\Models\DeliveryDriver;
use App\Services\Geo\DriverTrackingService;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function store(Request $request, DriverTrackingService $tracking)
    {
        $data = $request->validate([
            'driver_id' => ['nullable', 'integer', 'exists:delivery_drivers,id'],
            'shipment_id' => ['nullable', 'integer', 'exists:shipments,id'],
            'delivery_assignment_id' => ['nullable', 'integer', 'exists:delivery_assignments,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'mission_number' => ['nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $driver = isset($data['driver_id'])
            ? DeliveryDriver::findOrFail($data['driver_id'])
            : $request->user();

        return response()->json([
            'success' => true,
            'location' => $tracking->storeLocation($driver, $data),
        ], 201);
    }
}
