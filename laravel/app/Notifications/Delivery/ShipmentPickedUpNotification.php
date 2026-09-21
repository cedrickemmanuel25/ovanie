<?php

namespace App\Notifications\Delivery;

class ShipmentPickedUpNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData('Colis recupere', 'OVANIE a recupere votre colis et poursuit la livraison.', route('client.orders.show', $this->item->order_id));
    }
}
