<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryGroupCollectionService
{
    public function __construct(
        private readonly ClientDeliveryGroupService $groups,
        private readonly OrderSettlementService $settlements
    ) {
    }

    /**
     * Enregistre l'encaissement d'une seule livraison d'une commande en paiement à la livraison.
     */
    public function collect(Order $order, string $groupKey, array $meta = []): array
    {
        return DB::transaction(function () use ($order, $groupKey, $meta) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder->load([
                'items.product',
                'items.shop',
                'items.shipment',
                'deliverySelections',
                'shipments',
                'payments',
            ]);

            if (PaymentService::normalizeMethod($lockedOrder->payment_method) !== PaymentService::METHOD_CASH_ON_DELIVERY) {
                throw ValidationException::withMessages([
                    'payment' => 'Cette commande n’est pas en paiement à la livraison.',
                ]);
            }

            $group = $this->groups->find($lockedOrder, $groupKey);

            if (! $group) {
                throw ValidationException::withMessages([
                    'payment' => 'La livraison concernée est introuvable.',
                ]);
            }

            if (! $group['is_delivered']) {
                throw ValidationException::withMessages([
                    'payment' => 'Le paiement ne peut être enregistré qu’après la livraison de tous les articles de ce groupe.',
                ]);
            }

            $reference = 'COD-GROUP-' . $lockedOrder->id . '-' . strtoupper(substr(sha1($groupKey), 0, 10));
            $existing = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existing?->isPaid()) {
                return [
                    'payment' => $existing,
                    'group' => $group,
                    'already_collected' => true,
                    'all_paid' => $lockedOrder->items->every(fn ($item) => (bool) $item->is_paid),
                ];
            }

            $outstanding = $this->settlements->outstandingAmount($lockedOrder);
            $amount = round(min((float) $group['total'], $outstanding), 2);

            if ($amount <= 0.01) {
                return [
                    'payment' => null,
                    'group' => $group,
                    'already_collected' => true,
                    'all_paid' => true,
                ];
            }

            $providerPayload = array_merge([
                'delivery_group_key' => $groupKey,
                'delivery_number' => $group['number'],
                'product_amount' => $group['products_payable'],
                'delivery_fee' => $group['delivery_fee'],
                'collection_status' => 'collected',
                'collected_at' => now()->toDateTimeString(),
            ], $meta);

            $payment = Payment::updateOrCreate(
                [
                    'order_id' => $lockedOrder->id,
                    'reference' => $reference,
                ],
                [
                    'method' => Payment::METHOD_CASH_ON_DELIVERY,
                    'amount' => $amount,
                    'status' => Payment::STATUS_PAID,
                    'user_id' => $lockedOrder->client_id,
                    'operator' => $meta['operator'] ?? 'ovanie_delivery_collection',
                    'mobile_number' => $lockedOrder->delivery_recipient_phone ?: $lockedOrder->phone,
                    'type' => 'cod_delivery_group',
                    'order_item_ids' => $group['item_ids']->all(),
                    'paid_at' => now(),
                    'provider_payload' => $providerPayload,
                ]
            );

            $lockedOrder->items()
                ->whereIn('id', $group['item_ids']->all())
                ->update(['is_paid' => true]);

            $lockedOrder->refresh()->load('items');
            $allPaid = $lockedOrder->items->isNotEmpty()
                && $lockedOrder->items->every(fn ($item) => (bool) $item->is_paid);

            $oldPaymentStatus = $lockedOrder->payment_status;
            $lockedOrder->forceFill([
                'payment_status' => $allPaid ? 'paid' : 'partial',
            ])->save();

            if ($allPaid) {
                Payment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->where('type', 'cod_collection')
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Payment $intent) {
                        $payload = $intent->provider_payload ?? [];
                        $payload['cancel_reason'] = 'replaced_by_delivery_group_collections';
                        $payload['cancelled_at'] = now()->toDateTimeString();

                        $intent->forceFill([
                            'status' => Payment::STATUS_CANCELLED,
                            'provider_payload' => $payload,
                        ])->save();
                    });
            }

            $workflow = app(OrderWorkflowService::class);
            $workflow->recordHistory($lockedOrder, null, 'payment', $oldPaymentStatus, $allPaid ? 'paid' : 'partial', [
                'actor_type' => $meta['actor_type'] ?? 'system',
                'user_id' => $meta['user_id'] ?? null,
                'label' => 'Paiement d’une livraison confirmé',
                'message' => 'Le montant correspondant aux articles remis et à leur livraison a été encaissé.',
                'metadata' => [
                    'delivery_group_key' => $groupKey,
                    'payment_id' => $payment->id,
                    'order_item_ids' => $group['item_ids']->all(),
                ],
            ]);

            $workflow->markPayoutsReadyWhenEligible($lockedOrder->fresh());

            if ($allPaid) {
                app(LoyaltyService::class)->awardForCompletedOrder($lockedOrder->fresh(['items']));
            }

            return [
                'payment' => $payment,
                'group' => $group,
                'already_collected' => false,
                'all_paid' => $allPaid,
            ];
        }, 3);
    }
}
