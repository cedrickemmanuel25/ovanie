<?php
namespace App\ViewModels;

use Carbon\Carbon;
use App\Services\VehicleAppearanceService;

final class LogisticsTrackingData
{
    public static function driver($driver, $location = null): array
    {
        $location ??= $driver?->currentLocation;
        $at = $location?->recorded_at ?? $driver?->last_seen_at;
        $fresh = $at && $at->gte(now()->subSeconds((int) config('delivery.gps_weak_seconds',180)));
        $lat = $location?->latitude ?? $driver?->latitude;
        $lng = $location?->longitude ?? $driver?->longitude;
        $position = is_numeric($lat) && is_numeric($lng) && abs($lat)<=90 && abs($lng)<=180;
        $quality = !$position || !$at ? 'Indisponible' : (!$fresh ? 'Hors ligne' : (($location?->accuracy ?? 0)>30 ? 'Faible' : 'Bon'));
        $appearance = $driver
            ? app(VehicleAppearanceService::class)->forDriver($driver, false, false)
            : [];
        $vehicleRaw = $appearance['type_label'] ?? ($driver?->vehicle ?: 'Non renseigné');
        $vehicleCode = self::vehicleCode($appearance['type_code'] ?? $vehicleRaw);
        $vehicle = (string) ($appearance['type_label'] ?? $vehicleRaw);
        $plate = trim((string) ($appearance['plate'] ?? ''));
        $color = trim((string) ($appearance['color'] ?? ''));
        $activeMission = (int) ($driver?->active_assignments_count ?? 0) > 0
            || in_array((string) ($driver?->status ?? ''), ['En livraison', 'En mission'], true);
        $availability = $activeMission
            ? 'En mission'
            : (((string) ($driver?->status ?? '')) === 'Indisponible' ? 'Indisponible' : 'Disponible');

        return [
            'id' => $driver?->id,
            'name' => $driver?->name ?: 'Non affecté',
            'phone' => $driver?->phone,
            'vehicle' => $vehicle,
            'vehicleCode' => $vehicleCode,
            'vehiclePlate' => $plate !== '' ? $plate : null,
            'vehicleColor' => $color !== '' ? $color : null,
            'vehicleColorHex' => $appearance['color_hex'] ?? null,
            'vehiclePhotoUrl' => $appearance['photo_url'] ?? null,
            'vehiclePhotoIsReal' => (bool) ($appearance['photo_is_real'] ?? false),
            'vehicleReferenceAssetUrl' => $appearance['reference_photo_url'] ?? null,
            'fleetVehicleCode' => $appearance['fleet_code'] ?? null,
            'vehicleBrand' => $appearance['brand'] ?? null,
            'vehicleModel' => $appearance['model'] ?? null,
            'rating' => $driver?->rating ? number_format($driver->rating, 1, ',', '') : '—',
            'zone' => $driver?->interventionZonesLabel(2),
            'zones' => $driver?->interventionZones() ?? [],
            // La présence affichée doit être la même que dans l'espace Logistique :
            // une position GPS récente, pas l'ancien booléen is_online seul.
            'online' => $driver ? $driver->hasFreshGpsPresence() : false,
            'fresh' => (bool) $fresh,
            'quality' => $quality,
            'availability' => $availability,
            'availabilityTone' => $activeMission ? 'mission' : ($availability === 'Indisponible' ? 'unavailable' : 'available'),
            'at' => $at?->format('H:i'),
            'recordedAt' => $at?->toIso8601String(),
            'lat' => $position ? (float) $lat : null,
            'lng' => $position ? (float) $lng : null,
            'accuracy' => is_numeric($location?->accuracy) ? (float) $location->accuracy : null,
            'speed' => $location?->speed,
            'heading' => is_numeric($location?->heading) ? (float) $location->heading : null,
            'trackingUrl' => $driver?->id ? route('logistics.tracking.driver', ['driver' => $driver->id]) : null,
        ];
    }

    public static function mission(array $group): array
    {
        $m = LogisticsOperationsData::mission($group);
        $representative = $group['representative'];
        $representative->loadMissing([
            'latestDeliveryAssignment.driver.currentLocation',
            'latestDeliveryAssignment.latestLocation',
            'order.client',
        ]);

        $assignment = $representative->latestDeliveryAssignment;
        $driver = self::driver($assignment?->driver, $assignment?->latestLocation);
        if ($assignment?->driver) {
            $driver['availability'] = 'En mission';
            $driver['availabilityTone'] = 'mission';
        }
        $eta = $assignment?->manual_eta_at
            ?? $assignment?->estimated_delivery_at
            ?? $representative->shipment?->estimated_delivery_at;
        $eta = $eta ? Carbon::parse($eta) : null;
        $late = $eta && $eta->isPast() && ! in_array($m['status'], ['delivered', 'cancelled'], true);

        // Les informations affichées dans le centre GPS proviennent uniquement
        // des relations métier réelles (commande, affectation, livreur, boutiques).
        $actualVehicle = $assignment?->driver?->vehicle
            ?: $representative->vehicle_plate
            ?: $m['vehicle'];

        $shops = $group['shops']->map(fn ($shop) => [
            'kind' => 'shop',
            'label' => $shop->name,
            'detail' => collect([$shop->commune, $shop->district])->filter()->implode(' · '),
            'lat' => $shop->latitude,
            'lng' => $shop->longitude,
            'status' => $assignment?->picked_up_at ? 'done' : 'pending',
        ])->all();

        $points = $shops;
        // Comme dans l'app livreur, le point client n'apparaît qu'une fois la
        // collecte confirmée : avant ça, le livreur n'a même pas encore le
        // colis, il n'y a rien à livrer et rien à situer chez le client.
        if (($m['pickupDone'] ?? false) && is_numeric($m['lat']) && is_numeric($m['lng'])) {
            $points[] = [
                'kind' => 'destination',
                'label' => $m['destination'],
                'detail' => $m['client'],
                'lat' => (float) $m['lat'],
                'lng' => (float) $m['lng'],
                'status' => $m['status'] === 'delivered' ? 'done' : ($late ? 'late' : 'pending'),
            ];
        }

        if ($driver['lat'] !== null) {
            $points[] = [
                'kind' => 'driver',
                'label' => $driver['name'],
                'detail' => $driver['fresh'] ? 'Position GPS récente' : 'Dernière position connue',
                'lat' => $driver['lat'],
                'lng' => $driver['lng'],
                'status' => $late ? 'late' : ($driver['fresh'] ? 'mission' : 'pending'),
                'driverId' => $driver['id'],
                'vehicle' => $driver['vehicle'],
                'vehicleCode' => $driver['vehicleCode'],
                'vehiclePlate' => $driver['vehiclePlate'],
                'vehicleColor' => $driver['vehicleColor'],
                'vehicleColorHex' => $driver['vehicleColorHex'] ?? null,
                'vehiclePhotoUrl' => $driver['vehiclePhotoUrl'] ?? null,
                'vehiclePhotoIsReal' => $driver['vehiclePhotoIsReal'] ?? false,
                'vehicleReferenceAssetUrl' => $driver['vehicleReferenceAssetUrl'] ?? null,
                'fleetVehicleCode' => $driver['fleetVehicleCode'] ?? null,
                'availability' => $driver['availability'],
                'recordedAt' => $driver['recordedAt'],
                'accuracy' => $driver['accuracy'],
                'speed' => $driver['speed'],
                'heading' => $driver['heading'],
                'online' => $driver['online'],
                'url' => $driver['trackingUrl'],
            ];
        }

        return array_replace($m, [
            'driverData' => $driver,
            'vehicle' => $actualVehicle,
            'eta' => $eta?->format('H:i') ?? 'À confirmer',
            'delayed' => (bool) $late,
            'delayMinutes' => $late ? (int) $eta->diffInMinutes(now()) : 0,
            'points' => $points,
            'timeline' => [
                ['label' => 'Collecte boutique', 'at' => $assignment?->picked_up_at?->format('H:i'), 'done' => (bool) $assignment?->picked_up_at],
                ['label' => 'Départ livraison', 'at' => $assignment?->started_at?->format('H:i'), 'done' => (bool) $assignment?->started_at],
                ['label' => 'En transit', 'at' => $driver['at'], 'done' => $m['status'] === 'in_transit'],
                ['label' => 'Livraison client', 'at' => $assignment?->delivered_at?->format('H:i') ?? $eta?->format('H:i'), 'done' => (bool) $assignment?->delivered_at],
            ],
        ]);
    }

    private static function vehicleCode(?string $vehicle): string
    {
        $value = mb_strtolower((string) $vehicle);
        $value = str_replace(['-', '_'], ' ', $value);

        return match (true) {
            str_contains($value, 'tricycle') => 'tricycle',
            str_contains($value, 'moto') || str_contains($value, 'scooter') => 'moto',
            str_contains($value, 'pickup') || str_contains($value, 'pick up') => 'pickup',
            str_contains($value, '10t') || str_contains($value, '10 t') => 'truck_10t',
            str_contains($value, '3t') || str_contains($value, '3 t') => 'truck_3t',
            str_contains($value, 'camion') || str_contains($value, 'truck') => 'truck',
            default => 'vehicle',
        };
    }
}
