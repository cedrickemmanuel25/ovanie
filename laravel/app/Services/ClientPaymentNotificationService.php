<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garantit qu'une confirmation de paiement client est publiée au plus une fois
 * dans la boîte OVANIE et envoyée vers les canaux configurés (dont FCM).
 *
 * Cette couche est appelée par le webhook, la réconciliation de retour PayDunya
 * et la simulation locale afin que le canal de confirmation ne dépende pas du
 * chemin technique qui a constaté le paiement.
 */
class ClientPaymentNotificationService
{
    public function __construct(
        private readonly OvanieNotificationDispatcher $notifications,
    ) {
    }

    public function paymentConfirmed(Order $order): void
    {
        $order->loadMissing('client');
        $client = $order->client;

        if (! $client instanceof User) {
            return;
        }

        // Déduplication sans nouvelle table : OvanieNotificationDispatcher crée
        // toujours la notification in-app lorsqu'un push est demandé et autorisé.
        if ($this->alreadyPublished($client, $order)) {
            return;
        }

        $this->notifications->send(
            $client,
            'payments',
            'Paiement confirmé',
            "Votre paiement pour la commande {$order->order_number} a été confirmé. Votre commande est maintenant prise en charge par OVANIE.",
            [
                'order_id' => $order->id,
                'url' => route('client.orders.show', $order),
                'phone' => $order->phone ?: $client->phone,
            ],
            ['push', 'email', 'sms']
        );
    }

    private function alreadyPublished(User $client, Order $order): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $client->id)
            ->where('type', 'ovanie.client.notification')
            ->where('data', 'like', '%"category":"payments"%')
            ->where('data', 'like', '%"order_id":' . (int) $order->id . '%')
            ->where('data', 'like', '%"title":"Paiement confirmé"%')
            ->exists();
    }
}
