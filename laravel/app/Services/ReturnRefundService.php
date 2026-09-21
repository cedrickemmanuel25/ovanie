<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReturnModel;
use App\Models\VendorPayout;
use App\Models\VendorPayoutAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRefundService
{

    public function initialLogisticsStatus(string $returnType, ?string $deliveryProvider): string
    {
        return match ($returnType) {
            'claim' => ReturnModel::LOGISTICS_CLAIM_REVIEW,
            'refund' => ReturnModel::LOGISTICS_REFUND_REVIEW,
            default => in_array($deliveryProvider, [
                OrderWorkflowService::PROVIDER_OVANIE,
                OrderWorkflowService::PROVIDER_PARTNER,
            ], true)
                ? ReturnModel::LOGISTICS_PENDING_PICKUP
                : 'not_required',
        };
    }

    public function acceptedLogisticsStatus(ReturnModel $return): string
    {
        return match ($return->return_type) {
            'claim' => ReturnModel::LOGISTICS_CLAIM_REVIEW,
            'refund' => ReturnModel::LOGISTICS_REFUND_REVIEW,
            default => $this->initialLogisticsStatus(
                'return',
                $return->orderItem?->delivery_provider
            ),
        };
    }

    public function returnWindowDays(): int
    {
        return max(1, (int) config('client_space.return_window_days', 7));
    }

    public function availableQuantity(OrderItem $item, string $requestType = 'return'): int
    {
        $types = $requestType === 'claim'
            ? ['claim']
            : ['return', 'refund'];

        // Une réclamation n'immobilise pas la quantité physique disponible pour
        // un retour ou un remboursement. Chaque famille de demande possède son
        // propre compteur afin d'éviter les doubles demandes du même type.
        $alreadyRequested = (int) ReturnModel::query()
            ->where('order_item_id', $item->id)
            ->whereIn('return_type', $types)
            ->whereNotIn('status', [
                ReturnModel::STATUS_REJECTED,
                ReturnModel::STATUS_CLOSED,
                'cancelled',
                'resolved',
            ])
            ->sum('quantity');

        return max(0, (int) $item->quantity - $alreadyRequested);
    }

    public function availableClaimQuantity(OrderItem $item): int
    {
        return $this->availableQuantity($item, 'claim');
    }

    public function deliveredAt(OrderItem $item, ?Order $order = null): ?\Illuminate\Support\Carbon
    {
        $order ??= $item->relationLoaded('order') ? $item->order : $item->order()->first();

        $candidates = [
            $item->delivery_completed_at,
            $item->vendor_delivered_at,
            $item->delivery_otp_verified_at,
            $order?->delivered_at,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \Illuminate\Support\Carbon) {
                return $candidate->copy();
            }

            if ($candidate) {
                return \Illuminate\Support\Carbon::parse($candidate);
            }
        }

        return null;
    }

    public function returnDeadline(OrderItem $item, ?Order $order = null): ?\Illuminate\Support\Carbon
    {
        $deliveredAt = $this->deliveredAt($item, $order);

        return $deliveredAt?->copy()->addDays($this->returnWindowDays())->endOfDay();
    }

    public function isWithinReturnWindow(OrderItem $item, ?Order $order = null): bool
    {
        $deadline = $this->returnDeadline($item, $order);

        return $deadline !== null && now()->lte($deadline);
    }

    public function calculateRefundAmount(ReturnModel $return): float
    {
        $return->loadMissing('orderItem');
        $item = $return->orderItem;

        if (! $item) {
            return 0.0;
        }

        $orderedQuantity = max(1, (int) $item->quantity);
        $returnedQuantity = min($orderedQuantity, max(1, (int) $return->quantity));
        $itemSubtotal = (float) $item->subtotal > 0
            ? (float) $item->subtotal
            : max(0, (float) $item->price) * $orderedQuantity;
        $unitNetPrice = $itemSubtotal / $orderedQuantity;
        $returnedGross = min($itemSubtotal, $unitNetPrice * $returnedQuantity);

        $order = $return->order ?: $item->order;
        $order?->loadMissing('items');
        $orderSubtotal = (float) ($order?->subtotal ?? 0);

        if ($orderSubtotal <= 0 && $order) {
            $orderSubtotal = (float) $order->items->sum(fn ($orderItem) => (float) $orderItem->subtotal > 0
                ? (float) $orderItem->subtotal
                : ((float) $orderItem->price * max(1, (int) $orderItem->quantity)));
        }

        $orderSubtotal = max(0.01, $orderSubtotal > 0 ? $orderSubtotal : $itemSubtotal);
        $orderDiscounts = max(0, (float) ($order?->discount ?? 0))
            + max(0, (float) ($order?->loyalty_discount ?? 0));
        $discountShare = min($returnedGross, $orderDiscounts * ($returnedGross / $orderSubtotal));

        return round(max(0, $returnedGross - $discountShare), 2);
    }

    public function prepareRefund(ReturnModel $return, ?int $actorId = null): ReturnModel
    {
        return DB::transaction(function () use ($return, $actorId) {
            $locked = ReturnModel::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->return_type === 'claim') {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Une réclamation doit être convertie en retour ou en remboursement avant toute préparation financière.',
                ]);
            }

            $amount = $this->calculateRefundAmount($locked);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Le montant remboursable de ce retour est invalide.',
                ]);
            }

            $meta = $locked->meta ?? [];
            $meta['refund_prepared_by'] = $actorId;
            $meta['refund_prepared_at'] = now()->toDateTimeString();

            $locked->forceFill([
                'logistics_status' => ReturnModel::LOGISTICS_REFUND_PENDING,
                'refund_amount' => $amount,
                'refund_prepared_at' => now(),
                'meta' => $meta,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function confirmManualRefund(
        ReturnModel $return,
        string $reference,
        string $method,
        ?int $actorId = null
    ): ReturnModel {
        $reference = trim($reference);
        $method = trim($method);

        if ($reference === '') {
            throw ValidationException::withMessages([
                'refund_reference' => 'La référence du remboursement réellement exécuté est obligatoire.',
            ]);
        }

        if (! in_array($method, ['mobile_money', 'bank_transfer', 'cash', 'paydunya_manual'], true)) {
            throw ValidationException::withMessages([
                'refund_method' => 'La méthode de remboursement est invalide.',
            ]);
        }

        return DB::transaction(function () use ($return, $reference, $method, $actorId) {
            $locked = ReturnModel::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->return_type === 'claim') {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Une réclamation ne peut pas être remboursée directement sans décision de conversion en retour ou remboursement.',
                ]);
            }

            if ($locked->status === ReturnModel::STATUS_REFUNDED) {
                return $locked;
            }

            if ($locked->logistics_status !== ReturnModel::LOGISTICS_REFUND_PENDING) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Le retour doit d’abord être reçu et validé avant le remboursement.',
                ]);
            }

            $amount = (float) ($locked->refund_amount ?: $this->calculateRefundAmount($locked));
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Le montant remboursable est invalide.',
                ]);
            }

            $duplicateReference = Payment::query()
                ->where('reference', $reference)
                ->exists();

            if ($duplicateReference) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Cette référence de remboursement est déjà utilisée.',
                ]);
            }

            $meta = $locked->meta ?? [];
            $meta['refund_reference'] = $reference;
            $meta['refund_method'] = $method;
            $meta['refunded_by'] = $actorId;
            $meta['refunded_at'] = now()->toDateTimeString();

            $locked->forceFill([
                'status' => ReturnModel::STATUS_REFUNDED,
                'logistics_status' => ReturnModel::LOGISTICS_REFUNDED,
                'refund_amount' => $amount,
                'resolved_at' => now(),
                'refunded_at' => now(),
                'meta' => $meta,
            ])->save();

            if ($locked->orderItem) {
                $isFullLineReturn = (int) $locked->quantity >= (int) $locked->orderItem->quantity;
                $locked->orderItem->forceFill([
                    'return_status' => ReturnModel::STATUS_REFUNDED,
                    'payout_status' => $isFullLineReturn ? 'cancelled' : 'adjusted',
                ])->save();
            }

            Payment::firstOrCreate(
                [
                    'order_id' => $locked->order_id,
                    'reference' => $reference,
                ],
                [
                    'method' => PaymentService::METHOD_MANUAL_PROOF,
                    'type' => 'return_refund',
                    'amount' => $amount,
                    'status' => Payment::STATUS_REFUNDED,
                    'user_id' => $locked->client_id,
                    'provider_payload' => [
                        'return_id' => $locked->id,
                        'order_item_id' => $locked->order_item_id,
                        'refund_method' => $method,
                        'confirmed_by' => $actorId,
                    ],
                ]
            );

            $this->applyPayoutAdjustment($locked, $amount);
            app(LoyaltyService::class)->adjustForRefund($locked, $amount);

            return $locked->refresh();
        }, 3);
    }

    private function applyPayoutAdjustment(ReturnModel $return, float $refundAmount): void
    {
        $payout = VendorPayout::query()
            ->where('order_id', $return->order_id)
            ->where('shop_id', $return->shop_id)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (! $payout) {
            VendorPayoutAdjustment::firstOrCreate(
                ['return_id' => $return->id],
                [
                    'vendor_payout_id' => null,
                    'order_id' => $return->order_id,
                    'order_item_id' => $return->order_item_id,
                    'shop_id' => $return->shop_id,
                    'amount' => $refundAmount,
                    'type' => 'refund_deduction',
                    'status' => 'pending_application',
                    'reference' => 'RET-ADJ-' . $return->id,
                    'note' => 'Ajustement à appliquer au prochain reversement pour le retour #' . $return->id,
                ]
            );

            return;
        }

        $requiresRecovery = in_array($payout->status, [
            VendorPayout::STATUS_PROCESSING,
            VendorPayout::STATUS_PAID,
        ], true);

        $adjustment = VendorPayoutAdjustment::firstOrCreate(
            ['return_id' => $return->id],
            [
                'vendor_payout_id' => $payout->id,
                'order_id' => $return->order_id,
                'order_item_id' => $return->order_item_id,
                'shop_id' => $return->shop_id,
                'amount' => $refundAmount,
                'type' => 'refund_deduction',
                'status' => $requiresRecovery ? 'recovery_pending' : 'applied',
                'reference' => 'RET-ADJ-' . $return->id,
                'note' => 'Ajustement lié au remboursement du retour #' . $return->id,
            ]
        );

        if (! $adjustment->wasRecentlyCreated || $requiresRecovery) {
            return;
        }

        // Un remboursement produit ne doit pas diminuer automatiquement les frais
        // de livraison vendeur. La commission est recalculée uniquement sur les produits.
        $oldProductAmount = max(
            0.01,
            (float) ($payout->product_amount
                ?? data_get($payout->meta, 'financial_breakdown.product_amount')
                ?? ((float) $payout->total_amount - (float) ($payout->seller_delivery_amount ?? 0)))
        );
        $sellerDeliveryAmount = max(0, (float) ($payout->seller_delivery_amount ?? 0));
        $ovanieDeliveryAmount = max(0, (float) ($payout->ovanie_delivery_amount ?? 0));
        $processingFee = max(0, (float) ($payout->processing_fee_amount ?? 0));

        $grossReduction = min($refundAmount, $oldProductAmount);
        $commissionRatio = max(0, (float) $payout->commission_amount) / $oldProductAmount;
        $commissionReduction = round($grossReduction * $commissionRatio, 2);

        $newProductAmount = max(0, round($oldProductAmount - $grossReduction, 2));
        $newCommissionAmount = max(0, round((float) $payout->commission_amount - $commissionReduction, 2));
        $newNetProductAmount = max(0, round($newProductAmount - $newCommissionAmount, 2));
        $newTotalAmount = round($newProductAmount + $sellerDeliveryAmount, 2);
        $newPayoutAmount = max(0, round(
            $newNetProductAmount + $sellerDeliveryAmount - $processingFee,
            2
        ));
        $netReduction = max(0, round((float) $payout->payout_amount - $newPayoutAmount, 2));

        $meta = $payout->meta ?? [];
        $meta['refund_adjustments'][] = [
            'return_id' => $return->id,
            'product_reduction' => $grossReduction,
            'commission_reduction' => $commissionReduction,
            'net_reduction' => $netReduction,
            'seller_delivery_preserved' => $sellerDeliveryAmount,
            'applied_at' => now()->toDateTimeString(),
        ];
        $meta['financial_breakdown'] = [
            'product_amount' => $newProductAmount,
            'commission_amount' => $newCommissionAmount,
            'net_product_amount' => $newNetProductAmount,
            'seller_delivery_amount' => $sellerDeliveryAmount,
            'ovanie_delivery_amount' => $ovanieDeliveryAmount,
            'processing_fee_amount' => $processingFee,
            'payout_amount' => $newPayoutAmount,
        ];

        $payout->forceFill([
            'product_amount' => $newProductAmount,
            'net_product_amount' => $newNetProductAmount,
            'total_amount' => $newTotalAmount,
            'commission_amount' => $newCommissionAmount,
            'payout_amount' => $newPayoutAmount,
            'meta' => $meta,
            'admin_note' => trim(($payout->admin_note ? $payout->admin_note . "\n" : '')
                . 'Ajustement du retour #' . $return->id . ' : -' . number_format($netReduction, 0, ',', ' ') . ' FCFA.'),
        ])->save();
    }
}
