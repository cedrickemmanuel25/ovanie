<?php

namespace App\Notifications\Delivery;

use App\Models\DeliveryIncident;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidentVendorNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DeliveryIncident $incident,
        private readonly OrderItem $item,
        private readonly string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        $preferences = $notifiable->notification_preferences ?? [];
        $channels = [];

        $inAppEnabled = data_get($preferences, 'deliveries.in_app');
        if ($inAppEnabled === null) {
            $inAppEnabled = data_get($preferences, 'deliveries.push', true);
        }

        if ((bool) $inAppEnabled) {
            $channels[] = 'database';
        }

        if ((bool) data_get($preferences, 'deliveries.email', true) && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->item->loadMissing('order');

        return (new MailMessage)
            ->subject('Incident client à vérifier')
            ->line($this->message)
            ->action('Voir la commande', route('vendor.orders.show', $this->item->order_id));
    }

    public function toArray(object $notifiable): array
    {
        $this->item->loadMissing('order');

        return [
            'title' => 'Incident client à vérifier',
            'message' => $this->message,
            'category' => 'delivery_incident',
            'incident_id' => $this->incident->id,
            'order_id' => $this->item->order_id,
            'order_item_id' => $this->item->id,
            'order_number' => $this->item->order?->order_number,
            'url' => route('vendor.orders.show', $this->item->order_id),
        ];
    }
}
