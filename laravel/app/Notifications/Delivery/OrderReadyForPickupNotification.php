<?php

namespace App\Notifications\Delivery;

class OrderReadyForPickupNotification extends BaseDeliveryNotification
{
    public function toArray(object $notifiable): array
    {
        $this->item->loadMissing('order', 'product');

        return $this->baseData(
            'Colis pret pour ramassage OVANIE',
            'La commande ' . ($this->item->order?->order_number ?? $this->item->order_id) . ' est prete pour assignation chauffeur.',
            route('logistics.shipments.details', $this->item)
        ) + [
            'weight_kg' => (float) ($this->item->product?->weight_kg ?? $this->item->product?->weight ?? 0) * (int) $this->item->quantity,
            'volume_m3' => (float) ($this->item->product?->volume_m3 ?? 0) * (int) $this->item->quantity,
            'action_label' => 'Assigner un chauffeur',
        ];
    }
}
