<?php

namespace App\Notifications\Delivery;

class DeliveryRescheduledNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData('Livraison reprogrammee', 'Votre livraison OVANIE a ete reprogrammee. Consultez votre commande pour le nouveau suivi.', route('client.orders.show', $this->item->order_id));
    }
}
