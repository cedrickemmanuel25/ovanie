<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\VendorPayout;
use Illuminate\Database\Eloquent\Builder;

class VendorFinanceService
{
    public function __construct(
        private readonly VendorPayoutScheduleService $scheduleService,
    ) {
    }

    /**
     * Synchronise uniquement les commandes réellement visibles par la boutique.
     * Une commande ou une ligne qui n'a pas encore été libérée au vendeur ne doit
     * jamais créer de donnée financière dans son espace.
     */
    public function syncShop(Shop $shop): void
    {
        Order::query()
            ->whereHas('items', function (Builder $query) use ($shop) {
                $query->whereNotNull('vendor_visible_at')
                    ->where(function (Builder $shopQuery) use ($shop) {
                        $shopQuery->where('shop_id', $shop->id)
                            ->orWhere(function (Builder $legacyQuery) use ($shop) {
                                $legacyQuery->whereNull('shop_id')
                                    ->whereHas('product', fn (Builder $productQuery) => $productQuery->where('shop_id', $shop->id));
                            });
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $this->scheduleService->syncOrder($order);
                }
            });
    }

    public function summary(Shop $shop, int $vendorId): array
    {
        $query = $this->baseQuery($shop, $vendorId);
        $monthStart = now()->startOfMonth();
        $monthly = [];
        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $monthStart->copy()->subMonths($offset);
            $period = (clone $query)->whereBetween('created_at', [$start, $start->copy()->endOfMonth()]);
            $monthly[] = ['month' => $start->format('Y-m'), 'sales' => (float) (clone $period)->sum('payout_amount'),
                'commission' => (float) (clone $period)->sum('commission_amount')];
        }
        $monthQuery = (clone $query)->whereBetween('created_at', [$monthStart, now()->endOfMonth()]);
        // Le vendeur ne doit voir que ce qui lui revient réellement, jamais le
        // prix payé par le client (qui inclut la commission OVANIE).
        $monthNetSales = (float) (clone $monthQuery)->sum('payout_amount');
        $next = (clone $query)->whereIn('status', [VendorPayout::STATUS_PENDING, VendorPayout::STATUS_APPROVED, VendorPayout::STATUS_PROCESSING])
            ->whereNotNull('scheduled_for')->orderBy('scheduled_for')->first();

        return [
            'monthly_revenue' => $monthly,
            'month_sales' => $monthNetSales,
            'month_net_sales' => $monthNetSales,
            'month_commission' => (float) (clone $monthQuery)->sum('commission_amount'),
            'month_orders_count' => (clone $monthQuery)->whereNotIn('status', [VendorPayout::STATUS_WAITING_PAYMENT])->distinct()->count('order_id'),
            'month_paid_count' => (clone $query)->where('status', VendorPayout::STATUS_PAID)->whereBetween('paid_at', [$monthStart, now()->endOfMonth()])->count(),
            'next_payout_amount' => $next ? (float) $next->payout_amount : null,
            'next_payout_method' => $next?->payment_method_label,
            'next_payment_at' => $next?->scheduled_for?->toIso8601String(),
            // Les produits, la livraison vendeur et la livraison OVANIE restent séparés.
            'total_product_sales' => (float) (clone $query)->sum('product_amount'),
            'total_seller_delivery' => (float) (clone $query)->sum('seller_delivery_amount'),
            'total_ovanie_delivery' => (float) (clone $query)->sum('ovanie_delivery_amount'),
            'total_net_products' => (float) (clone $query)->sum('net_product_amount'),

            // Compatibilité historique : total_sales correspond à la base payable au vendeur
            // (produits + livraison vendeur), jamais à la livraison OVANIE.
            'total_sales' => (float) (clone $query)->sum('total_amount'),
            'total_commission' => (float) (clone $query)->sum('commission_amount'),
            'total_processing_fees' => (float) (clone $query)->sum('processing_fee_amount'),
            'total_net' => (float) (clone $query)->sum('payout_amount'),

            'waiting_payment' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_WAITING_PAYMENT)
                ->sum('payout_amount'),
            'waiting_reception' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_WAITING_RECEPTION)
                ->sum('payout_amount'),
            'blocked' => (float) (clone $query)
                ->whereIn('status', [VendorPayout::STATUS_BLOCKED, VendorPayout::STATUS_FAILED])
                ->sum('payout_amount'),
            'scheduled' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_PENDING)
                ->sum('payout_amount'),
            'ready' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_APPROVED)
                ->sum('payout_amount'),
            'processing' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_PROCESSING)
                ->sum('payout_amount'),
            'paid' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_PAID)
                ->sum('payout_amount'),

            'sales_count' => (clone $query)->count(),
            'scheduled_count' => (clone $query)->where('status', VendorPayout::STATUS_PENDING)->count(),
            'ready_count' => (clone $query)->where('status', VendorPayout::STATUS_APPROVED)->count(),
            'processing_count' => (clone $query)->where('status', VendorPayout::STATUS_PROCESSING)->count(),
            'paid_count' => (clone $query)->where('status', VendorPayout::STATUS_PAID)->count(),
            'next_payment_date' => $this->nextPaymentDate($shop, $vendorId),
        ];
    }

    public function baseQuery(Shop $shop, int $vendorId): Builder
    {
        return VendorPayout::query()
            ->where('shop_id', $shop->id)
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', VendorPayout::STATUS_CANCELLED);
    }

    private function nextPaymentDate(Shop $shop, int $vendorId): ?string
    {
        $date = $this->baseQuery($shop, $vendorId)
            ->whereIn('status', [VendorPayout::STATUS_PENDING, VendorPayout::STATUS_APPROVED, VendorPayout::STATUS_PROCESSING])
            ->whereNotNull('scheduled_for')
            ->orderBy('scheduled_for')
            ->value('scheduled_for');

        return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : null;
    }
}
