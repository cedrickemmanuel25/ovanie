<?php

namespace App\Notifications\Delivery;

class DriverOnTheWayNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData(
            'Livreur en route',
            'Votre livreur est en route. Vous pouvez suivre l avancement de votre livraison depuis votre espace client.',
            route('client.orders.show', $this->item->order_id)
        );
    }
}
