<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\VendorPayout;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorPayoutScheduleService
{
    public function __construct(
        private readonly VendorPayoutService $payoutService,
        private readonly OrderWorkflowService $workflowService,
    ) {
    }

    /**
     * Synchronise tous les reversements d'une commande avec les règles réelles :
     * - livraison terminée ;
     * - réception client confirmée ;
     * - paiement client éligible ;
     * - aucun retour actif ;
     * - calendrier 72 h ou hebdomadaire.
     */
    public function syncOrder(Order $order): Collection
    {
        $order->loadMissing('items.product.shop', 'items.shop');

        $payouts = $this->payoutService->generateForOrder($order);

        return $payouts->map(function (VendorPayout $payout) use ($order) {
            if (in_array($payout->status, VendorPayout::FINAL_STATUSES, true)
                || in_array($payout->status, [VendorPayout::STATUS_PROCESSING, VendorPayout::STATUS_PAID], true)) {
                return $payout;
            }

            return $this->syncPayout($payout, $order);
        });
    }

    public function syncPayout(VendorPayout $payout, ?Order $order = null): VendorPayout
    {
        $order ??= $payout->order;

        if (! $order) {
            return $payout;
        }

        $order->loadMissing('items.product.shop', 'items.shop');
        $payout->loadMissing('shop');

        $shop = $payout->shop;
        if (! $shop) {
            return $payout;
        }

        $items = $this->itemsForShop($order, (int) $shop->id);
        if ($items->isEmpty()) {
            return $payout;
        }

        $eligible = $items->every(
            fn (OrderItem $item) => $this->workflowService->isItemPayoutEligible($item)
        );

        if (! $eligible) {
            $status = $this->waitingStatus($order, $items);
            $meta = $payout->meta ?? [];
            $meta['blocked_reason'] = $this->waitingReason($order, $items, $status);

            $payout->forceFill([
                'status' => $status,
                'eligible_at' => null,
                'scheduled_for' => null,
                'expected_payment_date' => null,
                'meta' => $meta,
            ])->save();

            return $payout->refresh();
        }

        $eligibleAt = $this->eligibleAt($items);
        $mode = in_array($shop->payment_mode, [Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY], true)
            ? $shop->payment_mode
            : Shop::PAYMENT_WEEKLY;

        $scheduledFor = $this->scheduledFor($shop, $eligibleAt);
        $processingFee = $this->processingFee($shop, (float) $payout->total_amount, $eligibleAt);
        $net = max(
            0,
            round((float) $payout->total_amount - (float) $payout->commission_amount - $processingFee, 2)
        );

        $meta = $payout->meta ?? [];
        $meta['schedule'] = [
            'payment_mode' => $mode,
            'eligible_at' => $eligibleAt->toDateTimeString(),
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'processing_fee_amount' => $processingFee,
        ];

        $payout->forceFill([
            'status' => VendorPayout::STATUS_PENDING,
            'payment_mode_snapshot' => $mode,
            'processing_fee_amount' => $processingFee,
            'payout_amount' => $net,
            'phone' => $shop->mm_number ?: $payout->phone,
            'payment_channel' => $shop->mm_operator ?: $payout->payment_channel,
            'eligible_at' => $eligibleAt,
            'scheduled_for' => $scheduledFor,
            'expected_payment_date' => $scheduledFor->toDateString(),
            'payout_period_key' => $this->periodKey($shop, $scheduledFor),
            'meta' => $meta,
        ])->save();

        return $payout->refresh();
    }

    /**
     * Recalcule les dossiers ouverts puis bascule ceux arrivés à échéance vers "approved".
     * Le transfert financier final reste ensuite exécuté par le canal de paiement configuré
     * ou validé depuis l'administration, sans jamais marquer payé avant confirmation réelle.
     */
    public function synchronizeAndReleaseDue(): array
    {
        $orderIds = VendorPayout::query()
            ->whereNotIn('status', VendorPayout::FINAL_STATUSES)
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id');

        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $this->syncOrder($order);
            }
        }

        $released = $this->releaseDuePayouts();

        return [
            'orders_synced' => $orderIds->count(),
            'payouts_released' => $released->count(),
        ];
    }

    public function releaseDuePayouts(): Collection
    {
        $due = VendorPayout::query()
            ->with(['vendor', 'shop'])
            ->where('status', VendorPayout::STATUS_PENDING)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('shop_id')
            ->orderBy('scheduled_for')
            ->get();

        if ($due->isEmpty()) {
            return collect();
        }

        $released = collect();

        DB::transaction(function () use ($due, $released) {
            $due->groupBy(function (VendorPayout $payout) {
                if ($payout->payment_mode_snapshot === Shop::PAYMENT_WEEKLY) {
                    return 'weekly:' . $payout->shop_id . ':' . ($payout->payout_period_key ?: $payout->scheduled_for?->format('o-W'));
                }

                return 'post_delivery:' . $payout->id;
            })->each(function (Collection $group) use ($released) {
                $batchReference = 'BATCH-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));

                foreach ($group as $payout) {
                    $payout->forceFill([
                        'status' => VendorPayout::STATUS_APPROVED,
                        'approved_at' => $payout->approved_at ?: now(),
                        'batch_reference' => $batchReference,
                    ])->save();

                    $this->workflowService->notify(
                        $payout->vendor,
                        'Reversement prêt',
                        'Votre reversement est arrivé à échéance et a été préparé pour paiement.',
                        [
                            'category' => 'payouts',
                            'order_id' => $payout->order_id,
                            'url' => route('vendor.payouts.index'),
                        ]
                    );

                    $released->push($payout->refresh());
                }
            });
        });

        return $released;
    }

    public function scheduledFor(Shop $shop, Carbon $eligibleAt): Carbon
    {
        if ($shop->payment_mode === Shop::PAYMENT_POST_DELIVERY) {
            return $eligibleAt->copy()->addHours(
                (int) config('vendor_payouts.post_delivery_delay_hours', 72)
            );
        }

        $isoDay = max(1, min(7, (int) config('vendor_payouts.weekly_iso_day', 5)));
        $hour = max(0, min(23, (int) config('vendor_payouts.weekly_hour', 17)));
        $minute = max(0, min(59, (int) config('vendor_payouts.weekly_minute', 0)));

        $slot = $eligibleAt->copy()->startOfDay()->setTime($hour, $minute);
        $daysToAdd = ($isoDay - $eligibleAt->isoWeekday() + 7) % 7;
        $slot->addDays($daysToAdd);

        if ($slot->lte($eligibleAt)) {
            $slot->addWeek();
        }

        return $slot;
    }

    public function processingFee(Shop $shop, float $grossAmount, Carbon $eligibleAt): float
    {
        if ($shop->payment_mode !== Shop::PAYMENT_POST_DELIVERY) {
            return 0.0;
        }

        $startedAt = $shop->payout_onboarding_started_at ?: $shop->created_at ?: now();
        $freeUntil = Carbon::parse($startedAt)->addDays(
            (int) config('vendor_payouts.post_delivery_free_days', 90)
        );

        if ($eligibleAt->lt($freeUntil)) {
            return 0.0;
        }

        $rate = (float) config('vendor_payouts.post_delivery_processing_fee_rate', 0.05);

        return round(max(0, $grossAmount) * max(0, $rate), 2);
    }

    private function itemsForShop(Order $order, int $shopId): Collection
    {
        return $order->items
            ->filter(fn (OrderItem $item) => (int) ($item->shop_id ?: $item->product?->shop_id) === $shopId)
            ->values();
    }

    private function waitingStatus(Order $order, Collection $items): string
    {
        $allDelivered = $items->every(fn (OrderItem $item) => $item->isVendorDelivered());
        if (! $allDelivered) {
            return VendorPayout::STATUS_BLOCKED;
        }

        $allReceived = $items->every(fn (OrderItem $item) => $item->reception_status === 'confirmed');
        if (! $allReceived) {
            return VendorPayout::STATUS_WAITING_RECEPTION;
        }

        $paymentEligible = in_array($order->payment_status, ['paid', 'escrow_held'], true);

        return $paymentEligible
            ? VendorPayout::STATUS_BLOCKED
            : VendorPayout::STATUS_WAITING_PAYMENT;
    }


    private function waitingReason(Order $order, Collection $items, string $status): string
    {
        if ($status === VendorPayout::STATUS_WAITING_PAYMENT) {
            return 'Le règlement client doit encore être confirmé avant de programmer ce reversement.';
        }

        if ($status === VendorPayout::STATUS_WAITING_RECEPTION) {
            return 'La livraison est terminée mais la réception client doit encore être confirmée.';
        }

        $allDelivered = $items->every(fn (OrderItem $item) => $item->isVendorDelivered());
        if (! $allDelivered) {
            return 'Le reversement reste bloqué jusqu’à la livraison effective de toutes les lignes de cette boutique.';
        }

        return 'Un retour, une réclamation ou un contrôle métier bloque temporairement ce reversement.';
    }

    private function eligibleAt(Collection $items): Carbon
    {
        $timestamps = $items
            ->map(function (OrderItem $item) {
                return $item->reception_confirmed_at
                    ?: $item->payout_ready_at
                    ?: $item->delivery_completed_at
                    ?: now();
            })
            ->map(fn ($date) => Carbon::parse($date));

        return $timestamps->sortByDesc(fn (Carbon $date) => $date->timestamp)->first() ?: now();
    }

    private function periodKey(Shop $shop, Carbon $scheduledFor): string
    {
        if ($shop->payment_mode === Shop::PAYMENT_WEEKLY) {
            return $scheduledFor->format('o-\WW');
        }

        return 'PD-' . $scheduledFor->format('Ymd-His');
    }
}
