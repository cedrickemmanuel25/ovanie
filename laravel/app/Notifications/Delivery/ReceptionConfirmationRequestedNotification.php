<?php

namespace App\Notifications\Delivery;

class ReceptionConfirmationRequestedNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        return $this->baseData('Confirmation reception demandee', 'Confirmez la reception de votre commande depuis votre espace client.', route('client.orders.show', $this->item->order_id));
    }
}
