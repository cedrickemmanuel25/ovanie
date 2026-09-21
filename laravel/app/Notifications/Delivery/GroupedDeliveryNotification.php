<?php

namespace App\Notifications\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupedDeliveryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly int $orderId,
        private readonly ?string $orderNumber = null,
        private readonly ?string $groupKey = null,
        private readonly ?string $url = null,
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
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Voir ma commande', $this->url ?: route('client.orders.show', $this->orderId));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'category' => 'deliveries',
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'delivery_group_key' => $this->groupKey,
            'url' => $this->url ?: route('client.orders.show', $this->orderId),
        ];
    }
}
