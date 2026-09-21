<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * VendorOrderReleaseService
 *
 * Responsabilité unique : rendre les lignes de commande visibles aux vendeurs
 * UNIQUEMENT après confirmation réelle du paiement.
 *
 * Déclencheurs légitimes :
 *  - Webhook PayDunya confirmé pour le paiement en ligne
 *  - Commande confirmée en paiement à la livraison
 *  - Admin valide un virement bancaire
 *  - Admin valide une demande de vérification paiement vendeur
 *  - Admin force le statut paid/commission_paid sur la commande
 *
 * Ce service ne doit pas être appelé avant l'événement qui autorise réellement
 * la préparation vendeur : paiement confirmé pour le paiement en ligne, ou
 * confirmation de commande pour le paiement à la livraison.
 */
class VendorOrderReleaseService
{
    public function __construct(private readonly OrderWorkflowService $workflow) {}

    /**
     * Rend visibles les lignes de commande non encore libérées.
     *
     * - Idempotent : ne re-libère pas les lignes déjà visibles (vendor_visible_at IS NOT NULL).
     * - Multi-boutique : notifie chaque vendeur concerné séparément.
     * - Transactionnel : toutes les mises à jour sont atomiques.
     *
     * @return int Nombre de lignes libérées (0 si déjà toutes libérées ou conditions non remplies)
     */
    public function release(Order $order): int
    {
        if (! $this->canRelease($order)) {
            Log::info('VendorOrderReleaseService: release ignoré — conditions non remplies', [
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
            ]);

            return 0;
        }

        if (! Schema::hasColumn('order_items', 'vendor_visible_at')) {
            Log::warning('VendorOrderReleaseService: colonne vendor_visible_at absente de order_items — migration pending ?');

            return 0;
        }

        return DB::transaction(function () use ($order) {
            // Sélectionner uniquement les lignes pas encore libérées (idempotence)
            $items = OrderItem::query()
                ->with('product.shop.user')
                ->where('order_id', $order->id)
                ->whereNull('vendor_visible_at')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                Log::info('VendorOrderReleaseService: toutes les lignes sont déjà visibles', [
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                ]);

                return 0;
            }

            $releasedAt    = now();
            $releasedCount = 0;

            foreach ($items as $item) {
                $item->forceFill(['vendor_visible_at' => $releasedAt])->save();
                $releasedCount++;
            }

            Log::info('VendorOrderReleaseService: lignes libérées', [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'items_count'   => $releasedCount,
                'released_at'   => $releasedAt->toDateTimeString(),
            ]);

            // Notifier chaque boutique (vendeur) séparément — une notification par boutique
            $items->groupBy('shop_id')->each(function ($shopItems) use ($order) {
                $firstItem = $shopItems->first();
                $vendor    = $firstItem?->product?->shop?->user;

                if (! $vendor) {
                    Log::warning('VendorOrderReleaseService: vendeur introuvable pour shop_id', [
                        'shop_id'      => $firstItem?->shop_id,
                        'order_id'     => $order->id,
                        'order_number' => $order->order_number,
                    ]);

                    return;
                }

                $productCount = $shopItems->count();
                $shopName     = $firstItem?->product?->shop?->name ?? 'votre boutique';

                $this->workflow->notify(
                    $vendor,
                    'Nouvelle commande à préparer',
                    "La commande {$order->order_number} ({$productCount} produit(s)) est confirmée et attend votre préparation dans {$shopName}.",
                    [
                        'category'      => 'orders',
                        'order_id'      => $order->id,
                        'order_item_id' => $firstItem?->id,
                        'url'           => route('vendor.orders.show', $order),
                    ]
                );
            });

            // Historique global de l'action de libération
            $this->workflow->recordHistory($order, null, 'vendor_release', null, 'released', [
                'actor_type' => 'system',
                'label'      => 'Commande rendue visible aux vendeurs',
                'message'    => "Les {$releasedCount} ligne(s) vendeur ont été libérées après confirmation du paiement.",
                'metadata'   => [
                    'released_items_count' => $releasedCount,
                    'released_at'          => $releasedAt->toDateTimeString(),
                    'trigger'              => $order->payment_method === 'cash_on_delivery' ? 'cod_order_confirmed' : 'payment_confirmed',
                    'payment_method'       => $order->payment_method,
                    'payment_status'       => $order->payment_status,
                ],
            ]);

            return $releasedCount;
        });
    }

    /**
     * Libère uniquement les lignes d'une boutique spécifique.
     * Utile quand un virement admin concerne une seule boutique parmi une commande multi-boutiques.
     *
     * @param  Order  $order
     * @param  int    $shopId
     * @return int
     */
    public function releaseForShop(Order $order, int $shopId): int
    {
        if (! Schema::hasColumn('order_items', 'vendor_visible_at')) {
            return 0;
        }

        return DB::transaction(function () use ($order, $shopId) {
            $items = OrderItem::query()
                ->with('product.shop.user')
                ->where('order_id', $order->id)
                ->where('shop_id', $shopId)
                ->whereNull('vendor_visible_at')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return 0;
            }

            $releasedAt    = now();
            $releasedCount = 0;

            foreach ($items as $item) {
                $item->forceFill(['vendor_visible_at' => $releasedAt])->save();
                $releasedCount++;
            }

            $firstItem = $items->first();
            $vendor    = $firstItem?->product?->shop?->user;

            if ($vendor) {
                $this->workflow->notify(
                    $vendor,
                    'Nouvelle commande à préparer',
                    "La commande {$order->order_number} est confirmée et attend votre préparation.",
                    [
                        'category'      => 'orders',
                        'order_id'      => $order->id,
                        'order_item_id' => $firstItem?->id,
                        'url'           => route('vendor.orders.show', $order),
                    ]
                );
            }

            $this->workflow->recordHistory($order, null, 'vendor_release', null, 'released', [
                'actor_type' => 'system',
                'label'      => "Lignes boutique {$shopId} rendues visibles",
                'message'    => "{$releasedCount} ligne(s) de la boutique #{$shopId} libérées.",
                'metadata'   => [
                    'shop_id'              => $shopId,
                    'released_items_count' => $releasedCount,
                    'released_at'          => $releasedAt->toDateTimeString(),
                ],
            ]);

            return $releasedCount;
        });
    }

    /**
     * Vérifie si la commande est dans un état permettant la libération vendeur.
     *
     * Conditions :
     *  - Le statut commande doit être confirmé/payé/en traitement
     *  - Paiement en ligne : paiement réellement validé
     *  - Paiement à la livraison : commande confirmée, paiement encore en attente de collecte
     */
    private function canRelease(Order $order): bool
    {
        $order->refresh();

        $validStatuses = ['confirmed', 'paid', 'processing', 'completed'];
        if (! in_array($order->status, $validStatuses, true)) {
            return false;
        }

        if ($order->payment_method === 'cash_on_delivery'
            && $order->payment_status === 'pending'
            && in_array($order->status, ['confirmed', 'processing'], true)) {
            return true;
        }

        // Paiement en ligne complet ou autre paiement déjà validé.
        if (in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
            return true;
        }

        // Virement bancaire validé par admin : payment_status = 'paid' + payment_method = 'bank_transfer'
        // Ce cas est couvert par la condition ci-dessus (paid), on garde pour la lisibilité
        if ($order->payment_method === 'bank_transfer'
            && in_array($order->payment_status, ['paid', 'verified'], true)) {
            return true;
        }

        return false;
    }
}
