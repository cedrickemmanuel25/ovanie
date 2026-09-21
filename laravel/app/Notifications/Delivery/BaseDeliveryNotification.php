<?php

namespace App\Notifications\Delivery;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseDeliveryNotification extends Notification
{
    use Queueable;

    public function __construct(protected OrderItem $item) {}

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
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['message'])
            ->action($data['action_label'] ?? 'Voir la commande', $data['url'] ?? url('/'));
    }

    protected function baseData(string $title, string $message, ?string $url = null): array
    {
        $this->item->loadMissing('order');

        return [
            'title' => $title,
            'message' => $message,
            'category' => 'deliveries',
            'order_id' => $this->item->order_id,
            'order_item_id' => $this->item->id,
            'order_number' => $this->item->order?->order_number,
            'url' => $url,
        ];
    }
}
