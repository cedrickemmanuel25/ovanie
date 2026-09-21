<?php

namespace App\Notifications\Delivery;

class DriverAssignedNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData(
            'Livreur OVANIE affecte',
            'Votre livraison OVANIE a ete prise en charge. Un livreur est affecte a votre commande et se prepare a partir.',
            route('client.orders.show', $this->item->order_id)
        );
    }
}
