<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\FirebasePushService;
use App\Services\OvanieNotificationDispatcher;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Les notifications Laravel qui utilisent le canal database (notamment la
 * logistique) déclenchent aussi le push FCM lorsque la préférence client le
 * permet. Le contenu vient de la même Notification Laravel que le Web.
 */
class SendMobilePushForDatabaseNotification
{
    public function __construct(
        private readonly FirebasePushService $push,
        private readonly OvanieNotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $user = $event->notifiable;
        if (! method_exists($event->notification, 'toArray')) {
            return;
        }

        try {
            $data = (array) $event->notification->toArray($user);
        } catch (\Throwable) {
            return;
        }

        $title = trim((string) ($data['title'] ?? 'Notification OVANIE'));
        $message = trim((string) ($data['message'] ?? $data['body'] ?? ''));
        $category = $this->dispatcher->canonicalCategory((string) ($data['category'] ?? 'account'));

        if ($message === '' || ! $this->dispatcher->enabled($user, $category, 'push')) {
            return;
        }

        $this->push->sendToUser($user, $title, $message, [
            'category' => $category,
            'order_id' => $data['order_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'return_id' => $data['return_id'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
            'url' => $data['url'] ?? null,
        ]);
    }
}
