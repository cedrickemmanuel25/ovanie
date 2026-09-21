<?php

namespace App\Notifications\Delivery;

class ShipmentDeliveredNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData('Colis livre', 'Votre livraison a ete indiquee comme livree. Confirmez la reception depuis votre espace client.', route('client.orders.show', $this->item->order_id));
    }
}
