<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\VendorPayout;
use App\Models\VendorPayoutAdjustment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorPayoutService
{
    public function __construct(
        protected CommissionService $commissionService
    ) {
    }

    /**
     * Crée un reversement par boutique sans réinitialiser un reversement déjà payé,
     * approuvé ou en cours de traitement.
     */
    public function generateForOrder(Order $order, string $paymentMethod = 'vendor_payout'): Collection
    {
        $order->loadMissing('items.product.shop.user', 'items.shop');

        return DB::transaction(function () use ($order, $paymentMethod) {
            // Les lignes non encore visibles, annulées ou masquées au vendeur ne doivent
            // jamais alimenter un reversement vendeur.
            $visibleItems = $order->items
                ->filter(fn ($item) => $item->vendor_visible_at !== null)
                ->reject(fn ($item) => $item->vendor_status === 'cancelled')
                ->values();

            $activeShopIds = $visibleItems
                ->map(fn ($item) => $item->shop_id ?: $item->product?->shop_id)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            VendorPayout::query()
                ->where('order_id', $order->id)
                ->when($activeShopIds->isNotEmpty(), fn ($query) => $query->whereNotIn('shop_id', $activeShopIds->all()))
                ->when($activeShopIds->isEmpty(), fn ($query) => $query)
                ->whereIn('status', [
                    VendorPayout::STATUS_BLOCKED,
                    VendorPayout::STATUS_WAITING_PAYMENT,
                    VendorPayout::STATUS_WAITING_RECEPTION,
                    VendorPayout::STATUS_PENDING,
                ])
                ->update([
                    'status' => VendorPayout::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            return $visibleItems
                ->groupBy(fn ($item) => $item->shop_id ?: $item->product?->shop_id)
                ->filter(fn ($items, $shopId) => ! empty($shopId))
                ->map(function ($items, $shopId) use ($order, $paymentMethod) {
                    $shop = $items->first()?->shop
                        ?: $items->first()?->product?->shop
                        ?: Shop::find($shopId);

                    if (! $shop) {
                        return null;
                    }

                    // La commission porte uniquement sur les produits.
                    // La livraison vendeur est reversée intégralement au vendeur.
                    // La livraison OVANIE reste un revenu logistique OVANIE et n'entre jamais
                    // dans le montant du reversement vendeur.
                    $baseProductAmount = $this->commissionService->itemsSubtotal($items);
                    $baseSellerDeliveryAmount = round((float) $items
                        ->filter(fn ($item) => $item->delivery_provider === OrderWorkflowService::PROVIDER_SELLER)
                        ->sum(fn ($item) => (float) ($item->delivery_price ?? 0)), 2);
                    $baseOvanieDeliveryAmount = round((float) $items
                        ->filter(fn ($item) => $item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE)
                        ->sum(fn ($item) => (float) ($item->delivery_price ?? 0)), 2);

                    // Compatibilité avec les anciennes lignes dont delivery_provider était vide.
                    if ($baseSellerDeliveryAmount <= 0
                        && $baseOvanieDeliveryAmount <= 0
                        && $shop->usesSellerLogistics()) {
                        $baseSellerDeliveryAmount = round((float) $items
                            ->sum(fn ($item) => (float) ($item->delivery_price ?? 0)), 2);
                    }

                    if ($baseOvanieDeliveryAmount <= 0
                        && $baseSellerDeliveryAmount <= 0
                        && $shop->usesOvanieLogistics()) {
                        $baseOvanieDeliveryAmount = round((float) $items
                            ->sum(fn ($item) => (float) ($item->delivery_price ?? 0)), 2);
                    }

                    $baseCommissionAmount = (float) $this->commissionService
                        ->commissionFromPublicAmount($baseProductAmount, $shop);

                    $refundAdjustments = VendorPayoutAdjustment::query()
                        ->where('order_id', $order->id)
                        ->where('shop_id', $shop->id)
                        ->whereIn('status', ['pending_application', 'applied'])
                        ->get();

                    // Les remboursements diminuent d'abord les produits. Les frais de livraison
                    // vendeur restent séparés et ne sont modifiés que par une correction explicite.
                    $refundGrossReduction = min(
                        max(0, $baseProductAmount),
                        max(0, (float) $refundAdjustments->sum('amount'))
                    );
                    $commissionRatio = $baseProductAmount > 0
                        ? max(0, $baseCommissionAmount) / $baseProductAmount
                        : 0;
                    $commissionReduction = round($refundGrossReduction * $commissionRatio, 2);

                    $productAmount = max(0, round($baseProductAmount - $refundGrossReduction, 2));
                    $commissionAmount = max(0, round($baseCommissionAmount - $commissionReduction, 2));
                    $netProductAmount = max(0, round($productAmount - $commissionAmount, 2));
                    $sellerDeliveryAmount = max(0, $baseSellerDeliveryAmount);
                    $ovanieDeliveryAmount = max(0, $baseOvanieDeliveryAmount);

                    // total_amount représente désormais la base payable au vendeur :
                    // produits + livraison vendeur. La livraison OVANIE est exclue.
                    $totalAmount = round($productAmount + $sellerDeliveryAmount, 2);

                    $existing = VendorPayout::query()
                        ->where('order_id', $order->id)
                        ->where('shop_id', $shop->id)
                        ->first();

                    if ($existing && in_array($existing->status, [
                        VendorPayout::STATUS_APPROVED,
                        VendorPayout::STATUS_PROCESSING,
                        VendorPayout::STATUS_PAID,
                    ], true)) {
                        return $existing;
                    }

                    $payout = $existing ?: new VendorPayout([
                        'order_id' => $order->id,
                        'shop_id' => $shop->id,
                    ]);

                    $processingFee = (float) ($payout->processing_fee_amount ?? 0);
                    $payoutAmount = max(0, round(
                        $netProductAmount + $sellerDeliveryAmount - $processingFee,
                        2
                    ));

                    $meta = $payout->meta ?? [];
                    $meta = array_merge($meta, [
                        'order_number' => $order->order_number ?? null,
                        'shop_name' => $shop->name,
                        'items_count' => $items->count(),
                        'generated_at' => $meta['generated_at'] ?? now()->toDateTimeString(),
                        'blocked_reason' => 'Le reversement attend la livraison, la réception client et la validation du paiement.',
                        'refund_adjustment_total' => $refundGrossReduction,
                        'financial_breakdown' => [
                            'product_amount' => $productAmount,
                            'commission_amount' => $commissionAmount,
                            'net_product_amount' => $netProductAmount,
                            'seller_delivery_amount' => $sellerDeliveryAmount,
                            'ovanie_delivery_amount' => $ovanieDeliveryAmount,
                            'processing_fee_amount' => $processingFee,
                            'payout_amount' => $payoutAmount,
                        ],
                        'delivery_owner' => $sellerDeliveryAmount > 0 ? 'seller' : 'ovanie',
                    ]);

                    $payout->fill([
                        'vendor_id' => $shop->user_id,
                        'product_amount' => $productAmount,
                        'seller_delivery_amount' => $sellerDeliveryAmount,
                        'ovanie_delivery_amount' => $ovanieDeliveryAmount,
                        'net_product_amount' => $netProductAmount,
                        'total_amount' => $totalAmount,
                        'commission_amount' => $commissionAmount,
                        'processing_fee_amount' => $processingFee,
                        'payout_amount' => $payoutAmount,
                        'phone' => $shop->mm_number ?: $shop->whatsapp,
                        'payment_method' => $paymentMethod,
                        'payment_channel' => $shop->mm_operator,
                        'payment_mode_snapshot' => $shop->payment_mode ?: Shop::PAYMENT_WEEKLY,
                        'payout_reference' => $payout->payout_reference
                            ?: 'PAYOUT-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                        'status' => $payout->status ?: VendorPayout::STATUS_BLOCKED,
                        'meta' => $meta,
                    ]);

                    if (! $existing) {
                        $payout->status = VendorPayout::STATUS_BLOCKED;
                    }

                    $payout->save();

                    VendorPayoutAdjustment::query()
                        ->where('order_id', $order->id)
                        ->where('shop_id', $shop->id)
                        ->where('status', 'pending_application')
                        ->update([
                            'vendor_payout_id' => $payout->id,
                            'status' => 'applied',
                            'updated_at' => now(),
                        ]);

                    return $payout;
                })
                ->filter()
                ->values();
        });
    }

    public function approve(VendorPayout $payout): VendorPayout
    {
        $payout->update([
            'status' => VendorPayout::STATUS_APPROVED,
            'approved_at' => $payout->approved_at ?? now(),
        ]);

        return $this->notifyStatus(
            $payout->refresh(),
            'Reversement prêt',
            'Votre reversement est arrivé à échéance et peut maintenant être traité.'
        );
    }

    public function markProcessing(VendorPayout $payout, ?string $batchReference = null): VendorPayout
    {
        $payout->update([
            'status' => VendorPayout::STATUS_PROCESSING,
            'batch_reference' => $batchReference ?? $payout->batch_reference,
            'processing_at' => $payout->processing_at ?? now(),
        ]);

        return $this->notifyStatus(
            $payout->refresh(),
            'Reversement en traitement',
            'Votre reversement est en cours de traitement.'
        );
    }

    public function markPaid(VendorPayout $payout, ?string $reference = null): VendorPayout
    {
        $meta = $payout->meta ?? [];
        $meta['paid_at'] = now()->toDateTimeString();

        $payout->update([
            'status' => VendorPayout::STATUS_PAID,
            'payout_reference' => $reference ?? $payout->payout_reference,
            'paid_at' => $payout->paid_at ?? now(),
            'meta' => $meta,
        ]);

        return $this->notifyStatus(
            $payout->refresh(),
            'Reversement payé',
            'Votre reversement vendeur a été marqué comme payé.'
        );
    }

    public function fail(VendorPayout $payout, ?string $reason = null): VendorPayout
    {
        $meta = $payout->meta ?? [];

        if ($reason) {
            $meta['failure_reason'] = $reason;
        }

        $payout->update([
            'status' => VendorPayout::STATUS_FAILED,
            'failed_at' => $payout->failed_at ?? now(),
            'meta' => $meta,
        ]);

        return $this->notifyStatus(
            $payout->refresh(),
            'Échec de reversement',
            'Un reversement vendeur nécessite une vérification.'
        );
    }

    private function notifyStatus(VendorPayout $payout, string $title, string $message): VendorPayout
    {
        $payout->loadMissing(['vendor', 'order']);

        app(OrderWorkflowService::class)->notify($payout->vendor, $title, $message, [
            'category' => 'payouts',
            'order_id' => $payout->order_id,
            'url' => route('vendor.payouts.show', $payout),
        ]);

        return $payout;
    }
}
