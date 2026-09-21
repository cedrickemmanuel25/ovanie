<?php

namespace App\Notifications\Delivery;

class DeliveryIncidentNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData(
            'Incident de livraison',
            'Un incident opérationnel a été enregistré sur cette livraison. OVANIE Logistics suit la situation et organise la suite.',
            route('client.orders.show', $this->item->order_id)
        );
    }
}
