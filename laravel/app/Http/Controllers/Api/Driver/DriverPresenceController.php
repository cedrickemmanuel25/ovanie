<?php

namespace App\Http\Controllers\Api\Driver;

use App\Models\DeliveryDriver;
use App\Services\Geo\DriverTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverPresenceController
{
    /**
     * Heartbeat GPS de présence hors mission.
     *
     * L'application OVANIE Livreur appelle cet endpoint périodiquement lorsque
     * le livreur est connecté et que le GPS est autorisé. Une position reçue
     * rend le livreur "En ligne" ; l'absence de nouvelle position au-delà du
     * seuil configuré le fait automatiquement apparaître "Hors ligne" côté
     * Logistique, même si un ancien drapeau is_online=true reste en base.
     */
    public function heartbeat(Request $request, DriverTrackingService $tracking): JsonResponse
    {
        /** @var DeliveryDriver|null $driver */
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        if (! $driver->is_active || ! $driver->isOnboardingActive()) {
            return response()->json([
                'message' => 'Votre compte livreur n’est pas actif.',
                'online' => false,
            ], 403);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $location = $tracking->storeLocation($driver, [
            ...$data,
            // La présence doit refléter l'heure reçue par le serveur afin qu'un
            // horodatage erroné du téléphone ne rende pas le statut incohérent.
            'recorded_at' => now(),
        ]);

        $driver->load('currentLocation');

        return response()->json([
            'ok' => true,
            'online' => $driver->hasFreshGpsPresence(),
            'availability' => $driver->status,
            'last_seen_at' => $location->recorded_at?->toIso8601String(),
            'location' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => $location->accuracy !== null ? (float) $location->accuracy : null,
            ],
            'next_heartbeat_seconds' => (int) config('delivery.driver_presence_heartbeat_seconds', 45),
            'offline_after_seconds' => (int) config('delivery.driver_presence_online_seconds', 120),
        ]);
    }

    /**
     * Permet à l'application de signaler immédiatement la perte de présence
     * (déconnexion, GPS désactivé, passage volontaire hors ligne).
     */
    public function offline(Request $request): JsonResponse
    {
        /** @var DeliveryDriver|null $driver */
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        $driver->forceFill(['is_online' => false])->save();

        return response()->json([
            'ok' => true,
            'online' => false,
        ]);
    }

    /**
     * Disponibilité opérationnelle, distincte de la présence GPS.
     */
    public function availability(Request $request): JsonResponse
    {
        /** @var DeliveryDriver|null $driver */
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        if (! $driver->is_active || ! $driver->isOnboardingActive()) {
            return response()->json(['message' => 'Votre compte livreur n’est pas actif.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:Disponible,Indisponible'],
        ]);

        if ($driver->assignments()->whereIn('status', ['accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])->exists()) {
            return response()->json([
                'message' => 'Votre disponibilité ne peut pas être modifiée pendant une mission active.',
                'status' => 'En mission',
                'online' => $driver->hasFreshGpsPresence(),
            ], 409);
        }

        $driver->forceFill(['status' => $data['status']])->save();

        return response()->json([
            'ok' => true,
            'status' => $driver->status,
            'online' => $driver->hasFreshGpsPresence(),
        ]);
    }
}
