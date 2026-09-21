<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\Delivery\GroupedDeliveryNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DeliveryNotificationService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly ClientDeliveryGroupService $groups,
    ) {
    }

    public function notifyUser(?User $user, object $notification, ?string $smsMessage = null): void
    {
        if (! $user) {
            return;
        }

        try {
            Notification::send($user, $notification);
        } catch (\Throwable $e) {
            Log::warning('Delivery notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $phone = $user->phone ?? $user->whatsapp_phone ?? null;
        $smsEnabled = (bool) data_get($user->notification_preferences ?? [], 'deliveries.sms', true);
        if ($phone && $smsMessage && $smsEnabled) {
            try {
                $this->sms->send($phone, $smsMessage);
            } catch (\Throwable $e) {
                Log::warning('Delivery SMS notification failed', ['user_id' => $user->id, 'phone' => $phone, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Envoie une seule notification pour toute une livraison, même si elle contient
     * plusieurs articles. Le verrou évite les doublons lors des doubles clics ou
     * des appels concurrents.
     */
    public function notifyGroup(OrderItem $item, string $event, string $title, string $message, ?string $smsMessage = null): void
    {
        $item->loadMissing('order.client');
        $order = $item->order;
        $client = $order?->client;

        if (! $order || ! $client) {
            return;
        }

        $group = $this->groups->findForItem($order, (int) $item->id);
        $groupKey = (string) ($group['key'] ?? ('item-' . $item->id));
        $groupLabel = (string) ($group['label'] ?? 'Votre livraison');
        $cacheKey = sprintf('ovanie:delivery-notification:%d:%s:%s', $order->id, sha1($groupKey), $event);

        if (! Cache::add($cacheKey, true, now()->addDays(7))) {
            return;
        }

        $finalTitle = str_contains(mb_strtolower($title), 'livraison')
            ? $title
            : $groupLabel . ' · ' . $title;

        $notification = new GroupedDeliveryNotification(
            $finalTitle,
            $message,
            (int) $order->id,
            $order->order_number,
            $groupKey,
            route('client.orders.show', $order->id),
        );

        $this->notifyUser($client, $notification, $smsMessage);
    }

    public function logisticsUsers()
    {
        return User::query()
            ->whereIn('role', ['logistique', 'logistics', 'admin'])
            ->orWhere('is_admin', true)
            ->get();
    }

    public function notifyLogistics(object $notification): void
    {
        $this->logisticsUsers()->each(fn (User $user) => $this->notifyUser($user, $notification));
    }

    public function notifyDriverAssigned(OrderItem $item, object $notification): void
    {
        $item->loadMissing('order.client', 'product.shop.user');

        $this->notifyGroup(
            $item,
            'driver_assigned',
            'Livreur affecté',
            'Votre livraison a été prise en charge. Le livreur prépare son départ.',
            'Votre livraison OVANIE a été prise en charge. Le livreur prépare son départ.'
        );

        $this->notifyUser($item->product?->shop?->user, $notification);
    }
}
