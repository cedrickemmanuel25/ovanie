<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\Negotiation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Models\Review;
use App\Models\Shop;
use App\Models\VendorPayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorDashboardService
{
    private array $paidOrderPaymentStatuses = [
        'paid',
        'escrow_held',
        'released_to_vendor',
    ];

    private array $paidPaymentStatuses = [
        'paid',
        'success',
        'completed',
        'escrow_held',
        'released_to_vendor',
    ];

    public function __construct(
        private readonly CommissionService $commissionService,
        private readonly VendorFinanceService $financeService,
    ) {
    }

    /**
     * Construit toutes les données du dashboard vendeur.
     */
    public function build(Shop $shop): array
    {
        $this->financeService->syncShop($shop);

        $productQuery = Product::query()->where('shop_id', $shop->id);
        $orderItemQuery = $this->vendorOrderItemsQuery($shop);
        $ordersBase = $this->vendorOrdersQuery($shop);

        $salesToday = $this->salesForPeriod($shop, now()->startOfDay(), now()->endOfDay());
        $sales7Days = $this->salesForPeriod($shop, now()->subDays(6)->startOfDay(), now()->endOfDay());
        $sales30Days = $this->salesForPeriod($shop, now()->subDays(29)->startOfDay(), now()->endOfDay());
        $grossSales = $this->grossSales($shop);

        // Les frais de livraison vendeur sont un revenu vendeur distinct.
        // Les frais OVANIE Logistics ne sont jamais ajoutés au net vendeur.
        $sellerDeliveryToday = $this->sellerDeliveryForPeriod($shop, now()->startOfDay(), now()->endOfDay());
        $sellerDelivery7Days = $this->sellerDeliveryForPeriod($shop, now()->subDays(6)->startOfDay(), now()->endOfDay());
        $sellerDelivery30Days = $this->sellerDeliveryForPeriod($shop, now()->subDays(29)->startOfDay(), now()->endOfDay());
        $sellerDeliveryTotal = $this->sellerDeliveryTotal($shop);

        $financeSummary = $shop->user_id
            ? $this->financeService->summary($shop, (int) $shop->user_id)
            : [
                'total_product_sales' => 0,
                'total_seller_delivery' => 0,
                'total_ovanie_delivery' => 0,
                'total_net_products' => 0,
                'total_sales' => 0,
                'total_commission' => 0,
                'total_processing_fees' => 0,
                'total_net' => 0,
                'waiting_payment' => 0,
                'waiting_reception' => 0,
                'blocked' => 0,
                'scheduled' => 0,
                'ready' => 0,
                'processing' => 0,
                'paid' => 0,
            ];

        $commissionRate = $this->commissionService->rateForShop($shop);
        $commissionToday = $this->commissionService->commissionFromPublicAmount($salesToday, $shop, $commissionRate);
        $commission7Days = $this->commissionService->commissionFromPublicAmount($sales7Days, $shop, $commissionRate);
        $commission30Days = $this->commissionService->commissionFromPublicAmount($sales30Days, $shop, $commissionRate);
        $totalCommission = (float) ($financeSummary['total_commission'] ?? 0);
        $totalProductSales = (float) ($financeSummary['total_product_sales'] ?? $grossSales);
        $totalSellerDelivery = (float) ($financeSummary['total_seller_delivery'] ?? $sellerDeliveryTotal);
        $totalOvanieDelivery = (float) ($financeSummary['total_ovanie_delivery'] ?? 0);
        $totalNetProducts = (float) ($financeSummary['total_net_products'] ?? 0);
        $totalNet = (float) ($financeSummary['total_net'] ?? 0);

        if ($totalCommission <= 0 && $grossSales > 0) {
            $totalCommission = $this->commissionService->commissionFromPublicAmount($grossSales, $shop, $commissionRate);
        }

        if ($totalNetProducts <= 0 && $grossSales > 0) {
            $totalNetProducts = max(0, round($grossSales - $totalCommission, 2));
        }

        if ($totalNet <= 0 && ($grossSales > 0 || $sellerDeliveryTotal > 0)) {
            $totalNet = max(0, round($totalNetProducts + $sellerDeliveryTotal, 2));
        }

        $reviewStats = $this->reviewStats($shop);
        $returnStats = $this->returnStats($shop);
        $disputeStats = $this->disputeStats($shop);
        $negotiationStats = $this->negotiationStats($shop);

        $stats = [
            'products' => (clone $productQuery)->count(),
            'active_products' => $this->countActiveProducts(clone $productQuery),
            'pending_products' => $this->countProductStatuses(clone $productQuery, ['pending', 'draft', 'inactive', 'review']),
            'archived_products' => $this->countProductStatuses(clone $productQuery, ['archived']),
            'boosted_products' => $this->countBoostedProducts($shop),
            'low_stock_products' => $this->countLowStockProducts($shop),
            'out_of_stock_products' => $this->countOutOfStockProducts($shop),

            'orders' => (clone $ordersBase)->count(),
            'orders_today' => (clone $ordersBase)->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'pending_orders' => $this->distinctOrdersByVendorItemStatus($shop, ['pending', null]),
            'orders_to_prepare' => $this->distinctOrdersByVendorItemStatus($shop, ['pending', 'accepted', 'preparing', null]),
            'shipped_orders' => $this->distinctOrdersByDeliveryStatus($shop, ['assigned', 'picked_up', 'in_transit']),
            'delivered_orders' => $this->distinctOrdersByDeliveryStatus($shop, ['delivered']),
            'cancelled_vendor_items' => (clone $orderItemQuery)->where('vendor_status', 'cancelled')->count(),

            // Ventes produits payées. Le prix public des produits inclut la commission OVANIE.
            'sales_today' => $salesToday,
            'sales_7_days' => $sales7Days,
            'sales_30_days' => $sales30Days,
            'gross_sales' => $grossSales,
            'product_sales_total' => $totalProductSales,

            // Frais de livraison : seul le montant de la logistique vendeur est reversable.
            'seller_delivery_today' => $sellerDeliveryToday,
            'seller_delivery_7_days' => $sellerDelivery7Days,
            'seller_delivery_30_days' => $sellerDelivery30Days,
            'seller_delivery_total' => $totalSellerDelivery,
            'ovanie_delivery_total' => $totalOvanieDelivery,
            'uses_seller_logistics' => $shop->usesSellerLogistics(),
            'uses_ovanie_logistics' => $shop->usesOvanieLogistics(),

            // La commission porte uniquement sur les produits.
            'commission_rate' => $commissionRate,
            'commission_percent' => round($commissionRate * 100, 2),
            'commission_today' => $commissionToday,
            'commission_7_days' => $commission7Days,
            'commission_30_days' => $commission30Days,
            'net_products_today' => max(0, round($salesToday - $commissionToday, 2)),
            'net_products_7_days' => max(0, round($sales7Days - $commission7Days, 2)),
            'net_products_30_days' => max(0, round($sales30Days - $commission30Days, 2)),
            'net_today' => max(0, round($salesToday - $commissionToday + $sellerDeliveryToday, 2)),
            'net_7_days' => max(0, round($sales7Days - $commission7Days + $sellerDelivery7Days, 2)),
            'net_30_days' => max(0, round($sales30Days - $commission30Days + $sellerDelivery30Days, 2)),

            // Cumuls issus des dossiers de reversement par boutique.
            'commission' => $totalCommission,
            'net_products_revenue' => $totalNetProducts,
            'net_revenue' => $totalNet,
            'payout_waiting_payment' => (float) ($financeSummary['waiting_payment'] ?? 0),
            'payout_waiting_reception' => (float) ($financeSummary['waiting_reception'] ?? 0),
            'payout_blocked' => (float) ($financeSummary['blocked'] ?? 0),
            'payout_scheduled' => (float) ($financeSummary['scheduled'] ?? 0),
            'payout_available' => (float) ($financeSummary['ready'] ?? 0),
            'payout_processing' => (float) ($financeSummary['processing'] ?? 0),
            'payout_paid' => (float) ($financeSummary['paid'] ?? 0),

            // Clés historiques conservées pour les autres vues du projet.
            'pending_payouts' => (float) (($financeSummary['scheduled'] ?? 0) + ($financeSummary['ready'] ?? 0)),
            'processing_payouts' => (float) ($financeSummary['processing'] ?? 0),
            'paid_payouts' => (float) ($financeSummary['paid'] ?? 0),
            'blocked_payouts' => (float) (($financeSummary['blocked'] ?? 0) + ($financeSummary['waiting_payment'] ?? 0) + ($financeSummary['waiting_reception'] ?? 0)),

            // En local/test, les montants sont simulés. En live, ils proviennent des paiements confirmés.
            'financial_mode' => strtolower((string) config('paydunya.mode', 'test')),
            'is_test_mode' => strtolower((string) config('paydunya.mode', 'test')) !== 'live',

            'payments_pending' => $this->countPayments($shop, ['pending']),
            'payments_paid' => $this->countPayments($shop, $this->paidPaymentStatuses),

            'returns_pending' => $returnStats['pending'],
            'returns_total' => $returnStats['total'],
            'disputes_open' => $disputeStats['open'],
            'disputes_escalated' => $disputeStats['escalated'],
            'negotiations_open' => $negotiationStats['open'],
            'negotiations_total' => $negotiationStats['total'],

            'reviews' => $reviewStats['count'],
            'average_rating' => $reviewStats['average'],
            'shop_score' => $this->shopScore($shop),
        ];

        return [
            'shop' => $shop,
            'stats' => $stats,
            'orders' => $this->recentOrders($shop),
            'recentProducts' => $this->recentProducts($shop),
            'lowStockProducts' => $this->lowStockProducts($shop),
            'topProducts' => $this->topProducts($shop),
            'recentPayouts' => $this->recentPayouts($shop),
            'recentNegotiations' => $this->recentNegotiations($shop),
            'recentReturns' => $this->recentReturns($shop),
            'recentDisputes' => $this->recentDisputes($shop),
            'recentReviews' => $this->recentReviews($shop),
            'salesSeries' => $this->salesSeries($shop, 7),
            'alerts' => $this->alerts($shop, $stats),
        ];
    }

    /**
     * Lignes de commande appartenant à la boutique du vendeur.
     */
    private function vendorOrderItemsQuery(Shop $shop): Builder
    {
        return OrderItem::query()
            ->when(Schema::hasColumn('order_items', 'vendor_visible_at'), fn ($query) => $query->whereNotNull('vendor_visible_at'))
            ->when(Schema::hasColumn('order_items', 'vendor_status'), function ($query) {
                $query->where(function ($status) {
                    $status->whereNull('vendor_status')->orWhere('vendor_status', '!=', 'cancelled');
                });
            })
            ->where(function ($query) use ($shop) {
                if (Schema::hasColumn('order_items', 'shop_id')) {
                    $query->where('shop_id', $shop->id)
                        ->orWhere(function ($legacy) use ($shop) {
                            $legacy->whereNull('shop_id')
                                ->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
                        });
                    return;
                }

                $query->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
            });
    }

    private function vendorOrdersQuery(Shop $shop): Builder
    {
        return Order::query()->whereHas('items', function ($query) use ($shop) {
            if (Schema::hasColumn('order_items', 'vendor_visible_at')) {
                $query->whereNotNull('vendor_visible_at');
            }

            $query->where(function ($shopQuery) use ($shop) {
                if (Schema::hasColumn('order_items', 'shop_id')) {
                    $shopQuery->where('shop_id', $shop->id)
                        ->orWhere(function ($legacy) use ($shop) {
                            $legacy->whereNull('shop_id')
                                ->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
                        });
                    return;
                }

                $shopQuery->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
            });
        });
    }

    private function grossSales(Shop $shop): float
    {
        return $this->sumVendorItems(
            $this->vendorOrderItemsQuery($shop)->whereHas('order', function ($query) {
                $query->whereIn('payment_status', $this->paidOrderPaymentStatuses)
                    ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->whereIn('status', $this->paidPaymentStatuses));
            })
        );
    }

    private function salesForPeriod(Shop $shop, $from, $to): float
    {
        return $this->sumVendorItems(
            $this->vendorOrderItemsQuery($shop)->whereHas('order', function ($query) use ($from, $to) {
                $query->whereBetween('created_at', [$from, $to])
                    ->where(function ($paidQuery) {
                        $paidQuery->whereIn('payment_status', $this->paidOrderPaymentStatuses)
                            ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->whereIn('status', $this->paidPaymentStatuses));
                    });
            })
        );
    }

    private function sellerDeliveryForPeriod(Shop $shop, $from, $to): float
    {
        if (! Schema::hasColumn('order_items', 'delivery_price')) {
            return 0.0;
        }

        $query = $this->vendorOrderItemsQuery($shop)
            ->whereHas('order', function ($orderQuery) use ($from, $to) {
                $orderQuery->whereBetween('created_at', [$from, $to])
                    ->where(function ($paidQuery) {
                        $paidQuery->whereIn('payment_status', $this->paidOrderPaymentStatuses)
                            ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->whereIn('status', $this->paidPaymentStatuses));
                    });
            });

        return $this->applySellerDeliveryScope($query, $shop)->sum('delivery_price');
    }

    private function sellerDeliveryTotal(Shop $shop): float
    {
        if (! Schema::hasColumn('order_items', 'delivery_price')) {
            return 0.0;
        }

        $query = $this->vendorOrderItemsQuery($shop)
            ->whereHas('order', function ($orderQuery) {
                $orderQuery->whereIn('payment_status', $this->paidOrderPaymentStatuses)
                    ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->whereIn('status', $this->paidPaymentStatuses));
            });

        return (float) $this->applySellerDeliveryScope($query, $shop)->sum('delivery_price');
    }

    private function applySellerDeliveryScope(Builder $query, Shop $shop): Builder
    {
        if (! Schema::hasColumn('order_items', 'delivery_provider')) {
            return $shop->usesSellerLogistics() ? $query : $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $deliveryQuery) use ($shop) {
            $deliveryQuery->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER);

            if ($shop->usesSellerLogistics()) {
                $deliveryQuery->orWhereNull('delivery_provider');
            }
        });
    }

    private function sumVendorItems(Builder $query): float
    {
        $items = $query->get(['price', 'quantity', 'subtotal']);

        return (float) $items->sum(function ($item) {
            if (! is_null($item->subtotal)) {
                return (float) $item->subtotal;
            }

            return (float) $item->price * (float) $item->quantity;
        });
    }

    private function countActiveProducts(Builder $query): int
    {
        if (Schema::hasColumn('products', 'is_active')) {
            return (clone $query)->where('is_active', true)->count();
        }

        return $this->countProductStatuses($query, ['active', 'published', 'approved']);
    }

    private function countProductStatuses(Builder $query, array $statuses): int
    {
        if (! Schema::hasColumn('products', 'status')) {
            return 0;
        }

        return (clone $query)->whereIn('status', $statuses)->count();
    }

    private function countBoostedProducts(Shop $shop): int
    {
        if (! Schema::hasColumn('products', 'is_boosted')) {
            return 0;
        }

        return Product::where('shop_id', $shop->id)
            ->where('is_boosted', true)
            ->when(Schema::hasColumn('products', 'boost_end_at'), function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('boost_end_at')->orWhere('boost_end_at', '>=', now());
                });
            })
            ->count();
    }

    private function countLowStockProducts(Shop $shop): int
    {
        if (! Schema::hasColumn('products', 'stock')) {
            return 0;
        }

        return Product::where('shop_id', $shop->id)
            ->where('stock', '>', 0)
            ->where('stock', '<=', 5)
            ->count();
    }

    private function countOutOfStockProducts(Shop $shop): int
    {
        if (! Schema::hasColumn('products', 'stock')) {
            return 0;
        }

        return Product::where('shop_id', $shop->id)->where('stock', '<=', 0)->count();
    }

    private function distinctOrdersByVendorItemStatus(Shop $shop, array $statuses): int
    {
        if (! Schema::hasColumn('order_items', 'vendor_status')) {
            return 0;
        }

        $query = $this->vendorOrderItemsQuery($shop);

        $hasNull = in_array(null, $statuses, true);
        $cleanStatuses = array_values(array_filter($statuses, fn ($status) => ! is_null($status)));

        $query->where(function ($statusQuery) use ($cleanStatuses, $hasNull) {
            if ($cleanStatuses) {
                $statusQuery->whereIn('vendor_status', $cleanStatuses);
            }

            if ($hasNull) {
                $statusQuery->orWhereNull('vendor_status');
            }
        });

        return (int) $query->distinct('order_id')->count('order_id');
    }

    private function distinctOrdersByDeliveryStatus(Shop $shop, array $statuses): int
    {
        if (! Schema::hasColumn('order_items', 'delivery_status')) {
            return 0;
        }

        return (int) $this->vendorOrderItemsQuery($shop)
            ->whereIn('delivery_status', $statuses)
            ->distinct('order_id')
            ->count('order_id');
    }

    private function payoutStats(Shop $shop): array
    {
        if (! Schema::hasTable('vendor_payouts')) {
            return [
                'pending' => 0,
                'processing' => 0,
                'paid' => 0,
                'blocked' => 0,
                'commission' => 0,
                'net' => 0,
            ];
        }

        $base = VendorPayout::query()->where('shop_id', $shop->id);
        $amountColumn = Schema::hasColumn('vendor_payouts', 'payout_amount') ? 'payout_amount' : 'total_amount';

        return [
            'pending' => (float) (clone $base)->whereIn('status', [VendorPayout::STATUS_PENDING, VendorPayout::STATUS_APPROVED])->sum($amountColumn),
            'processing' => (float) (clone $base)->where('status', VendorPayout::STATUS_PROCESSING)->sum($amountColumn),
            'paid' => (float) (clone $base)->where('status', VendorPayout::STATUS_PAID)->sum($amountColumn),
            'blocked' => (float) (clone $base)->whereIn('status', [VendorPayout::STATUS_BLOCKED, VendorPayout::STATUS_FAILED])->sum($amountColumn),
            'commission' => Schema::hasColumn('vendor_payouts', 'commission_amount') ? (float) (clone $base)->sum('commission_amount') : 0,
            'net' => Schema::hasColumn('vendor_payouts', $amountColumn) ? (float) (clone $base)->sum($amountColumn) : 0,
        ];
    }

    private function countPayments(Shop $shop, array $statuses): int
    {
        if (! Schema::hasTable('payments')) {
            return 0;
        }

        return Payment::query()
            ->whereIn('status', $statuses)
            ->whereHas('order.items', function ($query) use ($shop) {
                if (Schema::hasColumn('order_items', 'vendor_visible_at')) {
                    $query->whereNotNull('vendor_visible_at');
                }

                $query->where(function ($shopQuery) use ($shop) {
                    if (Schema::hasColumn('order_items', 'shop_id')) {
                        $shopQuery->where('shop_id', $shop->id)
                            ->orWhere(function ($legacyQuery) use ($shop) {
                                $legacyQuery->whereNull('shop_id')
                                    ->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
                            });
                        return;
                    }

                    $shopQuery->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
                });
            })
            ->count();
    }

    private function vendorReturnsQuery(Shop $shop): Builder
    {
        return ReturnModel::query()
            ->where(function (Builder $query) use ($shop) {
                if (Schema::hasColumn('returns', 'shop_id')) {
                    $query->where('shop_id', $shop->id);
                }

                $query->orWhereHas('orderItem', function (Builder $itemQuery) use ($shop) {
                    $itemQuery->where('shop_id', $shop->id)
                        ->when(Schema::hasColumn('order_items', 'vendor_visible_at'), fn ($visible) => $visible->whereNotNull('vendor_visible_at'));
                });
            });
    }

    private function returnStats(Shop $shop): array
    {
        if (! class_exists(ReturnModel::class) || ! Schema::hasTable('returns')) {
            return ['pending' => 0, 'total' => 0];
        }

        $query = $this->vendorReturnsQuery($shop);

        return [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'total' => (clone $query)->count(),
        ];
    }

    private function disputeStats(Shop $shop): array
    {
        if (! class_exists(Dispute::class) || ! Schema::hasTable('disputes')) {
            return ['open' => 0, 'escalated' => 0];
        }

        $query = Dispute::query();

        if (Schema::hasColumn('disputes', 'vendor_id') && $shop->user_id) {
            $query->where('vendor_id', $shop->user_id);
        } else {
            $query->whereHas('order.items', function ($itemQuery) use ($shop) {
                if (Schema::hasColumn('order_items', 'shop_id')) {
                    $itemQuery->where('shop_id', $shop->id);
                }

                $itemQuery->orWhereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
            });
        }

        return [
            'open' => (clone $query)->whereNull('response')->count(),
            'escalated' => Schema::hasColumn('disputes', 'escalated') ? (clone $query)->where('escalated', true)->count() : 0,
        ];
    }

    private function negotiationStats(Shop $shop): array
    {
        if (! class_exists(Negotiation::class) || ! Schema::hasTable('negotiations')) {
            return ['open' => 0, 'total' => 0];
        }

        $query = Negotiation::query()->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));

        return [
            'open' => (clone $query)->whereIn('status', ['pending', 'open', 'new'])->count(),
            'total' => (clone $query)->count(),
        ];
    }

    private function reviewStats(Shop $shop): array
    {
        if (! class_exists(Review::class) || ! Schema::hasTable('reviews')) {
            return ['count' => 0, 'average' => 0];
        }

        $query = Review::query()->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));

        return [
            'count' => (clone $query)->count(),
            'average' => round((float) (clone $query)->avg('rating'), 1),
        ];
    }

    private function recentOrders(Shop $shop): Collection
    {
        return $this->vendorOrdersQuery($shop)
            ->with([
                'client',
                'items' => function ($query) use ($shop) {
                    if (Schema::hasColumn('order_items', 'vendor_visible_at')) {
                        $query->whereNotNull('vendor_visible_at');
                    }

                    $query->where(function ($shopQuery) use ($shop) {
                        if (Schema::hasColumn('order_items', 'shop_id')) {
                            $shopQuery->where('shop_id', $shop->id);
                        }

                        $shopQuery->orWhereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
                    })->with('product');
                },
            ])
            ->latest()
            ->take(8)
            ->get();
    }

    private function recentProducts(Shop $shop): Collection
    {
        return Product::query()
            ->where('shop_id', $shop->id)
            ->latest()
            ->take(6)
            ->get();
    }

    private function lowStockProducts(Shop $shop): Collection
    {
        if (! Schema::hasColumn('products', 'stock')) {
            return collect();
        }

        return Product::query()
            ->where('shop_id', $shop->id)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->take(6)
            ->get();
    }

    private function topProducts(Shop $shop): Collection
    {
        if (! Schema::hasTable('order_items')) {
            return collect();
        }

        $items = $this->vendorOrderItemsQuery($shop)
            ->whereHas('order', function ($orderQuery) {
                $orderQuery->whereIn('payment_status', $this->paidOrderPaymentStatuses)
                    ->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->whereIn('status', $this->paidPaymentStatuses));
            })
            ->select([
                'product_id',
                DB::raw('SUM(quantity) as sold_quantity'),
                DB::raw('SUM(COALESCE(subtotal, price * quantity)) as sold_amount'),
            ])
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('sold_quantity')
            ->take(5)
            ->get();

        $productIds = $items->pluck('product_id')->filter()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $items->map(function ($item) use ($products) {
            $item->product = $products->get($item->product_id);
            return $item;
        })->filter(fn ($item) => $item->product !== null)->values();
    }

    private function recentPayouts(Shop $shop): Collection
    {
        if (! Schema::hasTable('vendor_payouts')) {
            return collect();
        }

        return VendorPayout::query()
            ->where('shop_id', $shop->id)
            ->with('order')
            ->latest()
            ->take(5)
            ->get();
    }

    private function recentNegotiations(Shop $shop): Collection
    {
        if (! class_exists(Negotiation::class) || ! Schema::hasTable('negotiations')) {
            return collect();
        }

        return Negotiation::query()
            ->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id))
            ->with(['product', 'buyer'])
            ->latest()
            ->take(5)
            ->get();
    }

    private function recentReturns(Shop $shop): Collection
    {
        if (! class_exists(ReturnModel::class) || ! Schema::hasTable('returns')) {
            return collect();
        }

        return $this->vendorReturnsQuery($shop)
            ->latest()
            ->take(5)
            ->get();
    }

    private function recentDisputes(Shop $shop): Collection
    {
        if (! class_exists(Dispute::class) || ! Schema::hasTable('disputes')) {
            return collect();
        }

        $query = Dispute::query();

        if (Schema::hasColumn('disputes', 'vendor_id') && $shop->user_id) {
            $query->where('vendor_id', $shop->user_id);
        } else {
            $query->whereHas('order.items', function ($itemQuery) use ($shop) {
                if (Schema::hasColumn('order_items', 'shop_id')) {
                    $itemQuery->where('shop_id', $shop->id);
                }

                $itemQuery->orWhereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id));
            });
        }

        return $query->latest()->take(5)->get();
    }

    private function recentReviews(Shop $shop): Collection
    {
        if (! class_exists(Review::class) || ! Schema::hasTable('reviews')) {
            return collect();
        }

        return Review::query()
            ->whereHas('product', fn ($productQuery) => $productQuery->where('shop_id', $shop->id))
            ->with(['product', 'user'])
            ->latest()
            ->take(5)
            ->get();
    }

    private function salesSeries(Shop $shop, int $days = 7): array
    {
        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $amount = $this->salesForPeriod($shop, $date->copy()->startOfDay(), $date->copy()->endOfDay());

            $series[] = [
                'label' => $date->format('d/m'),
                'amount' => $amount,
            ];
        }

        return $series;
    }

    private function shopScore(Shop $shop): int
    {
        $score = 0;
        $score += $shop->name ? 10 : 0;
        $score += $shop->description ? 15 : 0;
        $score += $shop->logo ? 10 : 0;
        $score += $shop->city ? 10 : 0;
        $score += $shop->commune ? 10 : 0;
        $score += $shop->whatsapp || $shop->business_email ? 10 : 0;
        $score += $shop->main_category ? 10 : 0;
        $score += $shop->delivery_zone ? 10 : 0;
        $score += $shop->mm_number ? 10 : 0;
        $score += $shop->status === 'approved' && (bool) $shop->is_active ? 15 : 0;

        return min(100, $score);
    }

    private function alerts(Shop $shop, array $stats): array
    {
        $alerts = [];

        if (! $shop->canPublishProducts()) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Publication à vérifier',
                'message' => 'La boutique ou sa configuration logistique ne permet pas actuellement de publier de nouveaux produits actifs.',
                'route' => 'vendor.shop-status',
                'label' => 'Voir le statut',
            ];
        }

        if (($stats['orders_to_prepare'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'urgent',
                'title' => 'Commandes à préparer',
                'message' => 'Vous avez des commandes qui attendent votre préparation ou validation.',
                'route' => 'vendor.orders',
                'label' => 'Traiter',
            ];
        }

        if (($stats['low_stock_products'] ?? 0) > 0 || ($stats['out_of_stock_products'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Stock à surveiller',
                'message' => 'Certains produits sont en rupture ou presque épuisés.',
                'route' => 'vendor.products',
                'label' => 'Voir les produits',
            ];
        }

        if (($stats['pending_payouts'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Reversement en attente',
                'message' => 'Des reversements sont programmés ou prêts à être traités par OVANIE.',
                'route' => 'vendor.payouts.index',
                'label' => 'Voir les reversements',
            ];
        }

        if (($stats['disputes_open'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'urgent',
                'title' => 'Litige ouvert',
                'message' => 'Un ou plusieurs litiges nécessitent votre réponse.',
                'route' => 'vendor.disputes.index',
                'label' => 'Répondre',
            ];
        }

        if (($stats['products'] ?? 0) === 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Catalogue vide',
                'message' => 'Ajoutez vos premiers produits pour commencer à vendre.',
                'route' => 'vendor.add_product',
                'label' => 'Ajouter',
            ];
        }

        return $alerts;
    }
}
