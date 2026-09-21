<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actions client partagées par le Web et l'application mobile.
 *
 * Une action métier ne doit jamais être réimplémentée séparément dans Flutter.
 * Les deux interfaces appellent Laravel et Laravel exécute exactement le même
 * workflow sur les mêmes commandes, paiements, stocks, paniers et livraisons.
 */
class ClientOrderActionService
{
    public function __construct(
        private readonly OrderStockReservationService $stockReservations,
        private readonly OrderWorkflowService $workflow,
        private readonly ClientOrderStatusService $statusService,
        private readonly LoyaltyService $loyaltyService,
        private readonly CheckoutCartFinalizerService $cartFinalizer,
        private readonly PublicProductVisibilityService $visibility,
    ) {
    }

    public function cancel(User $user, Order $order): Order
    {
        if ((int) $order->client_id !== (int) $user->id) {
            abort(403);
        }

        if (! $this->statusService->canClientCancel($order)) {
            throw ValidationException::withMessages([
                'order' => 'Cette commande ne peut plus être annulée depuis votre espace client. Contactez le support OVANIE si vous avez besoin d’aide.',
            ]);
        }

        return DB::transaction(function () use ($user, $order) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->where('client_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->statusService->canClientCancel($lockedOrder)) {
                throw ValidationException::withMessages([
                    'order' => 'Le statut de la commande a changé. Elle ne peut plus être annulée depuis votre espace client.',
                ]);
            }

            $oldStatus = (string) $lockedOrder->status;

            $this->stockReservations->releaseAndRestoreCart(
                $lockedOrder,
                ! $this->cartFinalizer->cartWasPreserved($lockedOrder)
            );
            $this->cartFinalizer->markAbandoned($lockedOrder);

            $lockedOrder->items()->update([
                'vendor_status' => 'cancelled',
                'vendor_cancelled_at' => now(),
                'vendor_cancel_reason' => 'Annulation demandée par le client avant traitement.',
                'delivery_status' => OrderWorkflowService::DELIVERY_CANCELLED,
                'payout_status' => 'not_ready',
            ]);

            Shipment::query()
                ->where('order_id', $lockedOrder->id)
                ->whereNotIn('status', ['delivered', 'completed'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', Payment::STATUS_PENDING)
                ->lockForUpdate()
                ->get()
                ->each(function (Payment $payment) use ($user) {
                    $payload = $payment->provider_payload ?? [];
                    $payload['cancel_reason'] = 'client_order_cancelled';
                    $payload['cancelled_at'] = now()->toDateTimeString();
                    $payload['cancelled_by_user_id'] = $user->id;

                    $payment->forceFill([
                        'status' => Payment::STATUS_CANCELLED,
                        'provider_payload' => $payload,
                    ])->save();
                });

            $lockedOrder->forceFill([
                'status' => 'cancelled',
                'delivery_status' => OrderWorkflowService::DELIVERY_CANCELLED,
            ])->save();

            $this->loyaltyService->restoreForOrder($lockedOrder, 'Annulation de la commande');

            $this->workflow->recordHistory($lockedOrder, null, 'order', $oldStatus, 'cancelled', [
                'actor_type' => 'client',
                'user_id' => $user->id,
                'label' => 'Commande annulée',
                'message' => 'La commande a été annulée par le client avant le début du traitement logistique.',
            ]);

            return $lockedOrder->fresh(['items.product.images', 'payments', 'returns', 'shipments']);
        }, 3);
    }

    /**
     * Ajoute au panier canonique Laravel les produits encore réellement
     * disponibles. Le panier Web et le panier mobile sont donc le même panier.
     *
     * @return array{added_lines:int, skipped_lines:int}
     */
    public function reorder(User $user, Order $order): array
    {
        if ((int) $order->client_id !== (int) $user->id) {
            abort(403);
        }

        if (! $this->statusService->canReorder($order)) {
            throw ValidationException::withMessages([
                'order' => 'Cette commande ne peut pas encore être renouvelée.',
            ]);
        }

        $order->loadMissing('items.product');
        $productIds = $order->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all();

        $visibleProductIds = $this->visibility->query([], true)
            ->whereIn('products.id', $productIds)
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $cart = $user->cart()->firstOrCreate();
        $addedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($order, $visibleProductIds, $cart, &$addedCount, &$skippedCount) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product
                    || ! in_array((int) $item->product_id, $visibleProductIds, true)
                    || (int) $product->stock < 1) {
                    $skippedCount++;
                    continue;
                }

                $quantity = min(max(1, (int) $item->quantity), (int) $product->stock);
                $cartItem = $cart->items()->firstOrNew(['product_id' => $item->product_id]);
                $cartItem->price = $product->final_price;
                $cartItem->quantity = min(((int) $cartItem->quantity) + $quantity, (int) $product->stock);
                $cartItem->save();
                $addedCount++;
            }
        }, 3);

        return [
            'added_lines' => $addedCount,
            'skipped_lines' => $skippedCount,
        ];
    }
}
