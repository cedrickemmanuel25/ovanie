<?php

namespace App\Observers;

use App\Models\DeliveryAssignment;
use App\Notifications\DriverMissionAssignedNotification;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeliveryAssignmentObserver
{
    public function created(DeliveryAssignment $assignment): void
    {
        $this->normalizeMissionNumber($assignment);

        // Une offre diffusée à plusieurs livreurs candidats (status "offered",
        // voir LogisticsShipmentWorkflowService::broadcastToEligibleDrivers)
        // n'est pas une affectation : le message "mission affectée" serait
        // trompeur tant qu'aucun livreur n'a accepté. La notification propre
        // à l'offre (avec le montant) part séparément via FirebasePushService.
        if ($assignment->status === 'offered') {
            return;
        }

        $this->notifyDriver($assignment->fresh(['driver', 'order']));
    }

    public function updated(DeliveryAssignment $assignment): void
    {
        $this->normalizeMissionNumber($assignment);

        if ($assignment->wasChanged('driver_id') && $assignment->driver_id) {
            $this->notifyDriver($assignment->fresh(['driver', 'order']));
            return;
        }

        // Un livreur qui remporte une offre diffusée (offered -> accepted)
        // reçoit ici la confirmation "mission affectée" classique.
        if ($assignment->wasChanged('status')
            && $assignment->status === 'accepted'
            && $assignment->getOriginal('status') === 'offered'
        ) {
            $this->notifyDriver($assignment->fresh(['driver', 'order']));
        }
    }

    private function normalizeMissionNumber(DeliveryAssignment $assignment): void
    {
        if ($assignment->mission_number) {
            return;
        }

        $missionNumber = data_get($assignment->meta, 'mission_number')
            ?: 'OVL-' . str_pad((string) $assignment->order_id, 5, '0', STR_PAD_LEFT) . '-01';

        $assignment->forceFill(['mission_number' => $missionNumber])->saveQuietly();
    }

    private function notifyDriver(DeliveryAssignment $assignment): void
    {
        $driver = $assignment->driver;

        if (! $driver || ! $assignment->driver_id) {
            return;
        }

        $mission = $assignment->resolved_mission_number;
        $cacheKey = "driver-mission-notified:{$driver->id}:{$mission}";

        if (! Cache::add($cacheKey, true, now()->addDay())) {
            return;
        }

        $driver->notify(new DriverMissionAssignedNotification(
            $mission,
            (string) ($assignment->delivery_address ?: 'Destination client'),
            $assignment->pickup_scheduled_at?->format('d/m/Y H:i'),
            $assignment->estimated_delivery_at?->format('d/m/Y H:i'),
        ));

        try {
            if (filled($driver->phone)) {
                WhatsAppService::send(
                    $driver->phone,
                    "OVANIE : nouvelle mission {$mission}. Ouvrez votre espace livreur : "
                    . route('driver.missions.show', $mission)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Notification WhatsApp livreur non envoyée.', [
                'driver_id' => $driver->id,
                'mission_number' => $mission,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
