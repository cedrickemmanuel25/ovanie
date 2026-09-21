<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Shipment;
use App\Services\Geo\DriverTrackingService;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request, DriverTrackingService $tracking)
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:delivery_drivers,id'],
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $shipment = Shipment::with('orderItem')->findOrFail($data['shipment_id']);
        $driver = DeliveryDriver::findOrFail($data['driver_id']);
        $user = $request->user();
        $isLogistics = $user && (
            (bool) $user->is_admin
            || in_array($user->role, ['logistique', 'logistics', 'admin'], true)
        );
        $isAssignedDriverUser = $user
            && in_array($user->role, ['driver', 'livreur', 'chauffeur'], true)
            && $this->samePhone((string) $user->phone, (string) $driver->phone);

        /*
         * L'affectation doit correspondre à cette livraison précise. Le repli
         * sur la commande n'est permis que pour les anciennes affectations sans
         * order_item_id afin d'éviter de mélanger deux livreurs d'une même commande.
         */
        $assignment = DeliveryAssignment::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'accepted', 'collecting', 'picked_up', 'in_transit'])
            ->where(function ($query) use ($shipment) {
                $query->where('order_item_id', $shipment->order_item_id)
                    ->orWhere(function ($legacy) use ($shipment) {
                        $legacy->whereNull('order_item_id')
                            ->where('order_id', $shipment->order_id);
                    });
            })
            ->latest('id')
            ->first();

        abort_unless($assignment, 403, 'Ce livreur n est pas assigne a cette livraison active.');
        abort_unless($isLogistics || $isAssignedDriverUser, 403, 'Utilisateur non autorise pour ce chauffeur.');

        $location = $tracking->storeLocation($driver, [
            ...$data,
            'shipment_id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'delivery_assignment_id' => $assignment->id,
            'mission_number' => $assignment->resolved_mission_number,
        ]);

        $shipment->orderItem?->forceFill([
            'driver_latitude' => $location->latitude,
            'driver_longitude' => $location->longitude,
            'driver_location_updated_at' => $location->recorded_at,
        ])->save();

        $assignment->forceFill([
            'gps_status' => 'active',
            'gps_last_seen_at' => $location->recorded_at ?: now(),
        ])->save();

        $shipment->loadMissing(['order.client', 'orderItem.latestDeliveryAssignment.driver', 'latestDriverLocation']);

        return response()->json([
            'ok' => true,
            'location' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => optional($location->recorded_at)->toIso8601String(),
            ],
            'tracking' => $tracking->trackingPayload($shipment, $isLogistics),
        ]);
    }

    private function samePhone(string $left, string $right): bool
    {
        $normalize = static fn (string $phone): string => preg_replace('/\D+/', '', $phone) ?: '';
        $left = $normalize($left);
        $right = $normalize($right);

        return $left !== '' && $right !== '' && substr($left, -8) === substr($right, -8);
    }
}
