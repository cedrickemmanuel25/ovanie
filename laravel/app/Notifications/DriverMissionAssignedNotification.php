<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverMissionAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $missionNumber,
        private readonly string $destination,
        private readonly ?string $pickupAt,
        private readonly ?string $deliveryAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nouvelle mission de livraison',
            'message' => "La mission {$this->missionNumber} vous a été affectée.",
            'mission_number' => $this->missionNumber,
            'destination' => $this->destination,
            'pickup_at' => $this->pickupAt,
            'delivery_at' => $this->deliveryAt,
            'url' => route('driver.missions.show', $this->missionNumber),
        ];
    }
}
