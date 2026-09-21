<?php

namespace App\Services\Geo;

use App\Models\DeliveryDriver;
use App\Models\DeliveryRoute;
use App\Models\Shipment;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RouteOptimizationService
{
    public function optimizeForDriver(int $driverId, array $shipmentIds): DeliveryRoute
    {
        $driver = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->findOrFail($driverId);
        $shipments = LogisticsOperationalDataScope::shipments(Shipment::query())
            ->whereIn('id', $shipmentIds)
            ->where('provider_type', 'ovanie')
            ->whereIn('status', ['ready_for_pickup', 'assigned', 'picked_up'])
            ->get()
            ->filter(fn (Shipment $shipment) => $this->validPoint($shipment->pickup_latitude, $shipment->pickup_longitude)
                && $this->validPoint($shipment->delivery_latitude, $shipment->delivery_longitude))
            ->values();

        if ($shipments->isEmpty()) {
            throw ValidationException::withMessages([
                'shipment_ids' => 'Aucune expédition ne possède les coordonnées nécessaires à l’optimisation.',
            ]);
        }

        $startLng = $this->validPoint($driver->latitude, $driver->longitude)
            ? (float) $driver->longitude
            : (float) config('geo.default_lng');
        $startLat = $this->validPoint($driver->latitude, $driver->longitude)
            ? (float) $driver->latitude
            : (float) config('geo.default_lat');

        $payload = [
            'vehicles' => [[
                'id' => $driver->id,
                'start' => [$startLng, $startLat],
                'capacity' => [$this->vehicleCapacityKg($driver->vehicle)],
            ]],
            'shipments' => $shipments->map(fn (Shipment $shipment) => [
                'pickup' => [
                    'id' => $shipment->id * 10 + 1,
                    'location' => [(float) $shipment->pickup_longitude, (float) $shipment->pickup_latitude],
                ],
                'delivery' => [
                    'id' => $shipment->id * 10 + 2,
                    'location' => [(float) $shipment->delivery_longitude, (float) $shipment->delivery_latitude],
                ],
                'amount' => [max(1, (int) round((float) $shipment->total_weight_kg))],
                'metadata' => ['shipment_id' => $shipment->id],
            ])->all(),
        ];

        $responseData = $this->callOptimizer($payload);

        return DB::transaction(function () use ($driver, $shipments, $payload, $responseData) {
            $route = DeliveryRoute::create([
                'driver_id' => $driver->id,
                'status' => $responseData ? 'optimized' : 'draft',
                'total_distance_km' => isset($responseData['summary']['distance'])
                    ? round($responseData['summary']['distance'] / 1000, 2)
                    : null,
                'total_duration_minutes' => isset($responseData['summary']['duration'])
                    ? (int) round($responseData['summary']['duration'] / 60)
                    : null,
                'optimized_payload' => $payload,
                'optimized_response' => $responseData,
                'created_by' => auth()->id(),
            ]);

            $stops = $this->orderedStops($shipments, $responseData);
            foreach ($stops as $index => $stop) {
                $route->stops()->create([
                    'shipment_id' => $stop['shipment']->id,
                    'type' => $stop['type'],
                    'stop_order' => $index + 1,
                    'address' => $stop['type'] === 'pickup'
                        ? $stop['shipment']->pickup_address
                        : $stop['shipment']->delivery_address,
                    'latitude' => $stop['type'] === 'pickup'
                        ? $stop['shipment']->pickup_latitude
                        : $stop['shipment']->delivery_latitude,
                    'longitude' => $stop['type'] === 'pickup'
                        ? $stop['shipment']->pickup_longitude
                        : $stop['shipment']->delivery_longitude,
                ]);
            }

            foreach ($shipments as $shipment) {
                $shipment->forceFill(['optimized_route_id' => $route->id])->save();
            }

            return $route->load(['driver', 'stops.shipment.order']);
        });
    }

    public function optimizeForZone(string $zone): array
    {
        return ['success' => false, 'message' => 'Sélectionnez un livreur et des expéditions pour optimiser une tournée.'];
    }

    private function callOptimizer(array $payload): ?array
    {
        $baseUrl = trim((string) config('geo.vroom_base_url'));
        if ($baseUrl === '') {
            return null;
        }

        try {
            $response = Http::timeout(20)->post(rtrim($baseUrl, '/') . '/', $payload);
            return $response->ok() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function orderedStops($shipments, ?array $responseData): array
    {
        $byPickupId = $shipments->keyBy(fn (Shipment $shipment) => $shipment->id * 10 + 1);
        $byDeliveryId = $shipments->keyBy(fn (Shipment $shipment) => $shipment->id * 10 + 2);
        $steps = collect($responseData['routes'][0]['steps'] ?? []);
        $result = [];

        foreach ($steps as $step) {
            $id = (int) ($step['id'] ?? 0);
            if ($byPickupId->has($id)) {
                $result[] = ['shipment' => $byPickupId->get($id), 'type' => 'pickup'];
            } elseif ($byDeliveryId->has($id)) {
                $result[] = ['shipment' => $byDeliveryId->get($id), 'type' => 'delivery'];
            }
        }

        if ($result !== []) {
            return $result;
        }

        foreach ($shipments as $shipment) {
            $result[] = ['shipment' => $shipment, 'type' => 'pickup'];
            $result[] = ['shipment' => $shipment, 'type' => 'delivery'];
        }

        return $result;
    }


    private function vehicleCapacityKg(?string $vehicle): int
    {
        return match (mb_strtolower(trim((string) $vehicle))) {
            'moto' => 20,
            'tricycle' => 250,
            'pickup' => 1000,
            'camion_3t', 'camion 3t' => 3000,
            'camion_10t', 'camion 10t' => 10000,
            default => 20,
        };
    }

    private function validPoint($lat, $lng): bool
    {
        return is_numeric($lat) && is_numeric($lng)
            && (float) $lat >= -90 && (float) $lat <= 90
            && (float) $lng >= -180 && (float) $lng <= 180
            && (abs((float) $lat) > 0 || abs((float) $lng) > 0);
    }
}
