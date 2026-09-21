<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminDashboardService
{
    public function dashboardPayload(): array
    {
        $overview = $this->overview();
        $securityAlerts = $this->securityAlerts();

        return [
            'overview' => $overview,
            'sales_trend' => $this->salesTrend(14),
            'actions_required' => $this->actionsRequired($overview),
            'recent_orders' => $this->recentOrders(6),
            'attention_shops' => $this->shopsNeedingAttention(6),
            'pending_products' => $this->pendingProducts(6),
            'pending_payments' => $this->pendingPayments(5),
            'commercial_activity' => $this->commercialActivity(8),
            'security_alerts' => $securityAlerts,
        ];
    }

    public function overview(): array
    {
        $paidOrders = $this->paidOrdersQuery();
        $salesColumn = $this->firstExistingColumn(Order::class, ['total_amount', 'grand_total', 'total', 'amount']);

        $currentMonthStart = now()->copy()->startOfMonth();
        $currentMonthEnd = now()->copy()->endOfMonth();
        $previousMonthStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = now()->copy()->subMonthNoOverflow()->endOfMonth();

        $salesTotal = $salesColumn ? (float) (clone $paidOrders)->sum($salesColumn) : 0.0;
        $salesMonth = $salesColumn
            ? (float) (clone $paidOrders)->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum($salesColumn)
            : 0.0;
        $salesPreviousMonth = $salesColumn
            ? (float) (clone $paidOrders)->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->sum($salesColumn)
            : 0.0;

        $deliveryColumn = $this->firstExistingColumn(Order::class, [
            'delivery_fee_total',
            'delivery_fee',
            'ovanie_delivery_fee',
        ]);

        $deliveryFeesCollected = $deliveryColumn
            ? (float) (clone $paidOrders)->sum($deliveryColumn)
            : 0.0;

        $operationalOrders = $this->operationalOrdersQuery();
        $ordersMonth = (clone $operationalOrders)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $ordersPreviousMonth = (clone $operationalOrders)
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $payoutFinancials = $this->payoutFinancials();

        return [
            'sales_total' => $salesTotal,
            'sales_month' => $salesMonth,
            'sales_previous_month' => $salesPreviousMonth,
            'sales_month_change' => $this->percentageChange($salesMonth, $salesPreviousMonth),
            'collected_total' => $salesTotal,
            'product_sales_total' => $payoutFinancials['product_sales'],
            'seller_delivery_payable' => $payoutFinancials['seller_delivery'],
            'ovanie_delivery_revenue' => $payoutFinancials['ovanie_delivery'],
            'delivery_fees_collected' => $deliveryFeesCollected,
            'commissions_revenue' => $payoutFinancials['commissions'],
            'commissions_month' => $payoutFinancials['commissions_month'],
            'vendor_funds_waiting_payment' => $payoutFinancials['waiting_payment'],
            'vendor_funds_pending' => $payoutFinancials['pending'],
            'vendor_funds_ready' => $payoutFinancials['ready'],
            'vendor_funds_processing' => $payoutFinancials['processing'],
            'vendor_funds_paid' => $payoutFinancials['paid'],

            'financial_mode' => strtolower((string) config('paydunya.mode', 'test')),
            'is_test_mode' => strtolower((string) config('paydunya.mode', 'test')) !== 'live',
            'payout_execution_mode' => (string) config('vendor_payouts.execution_mode', 'simulation'),

            'orders_total' => $this->countModel(Order::class),
            'orders_today' => $this->countWhereDate(Order::class, 'created_at', now()->toDateString()),
            'orders_month' => $ordersMonth,
            'orders_previous_month' => $ordersPreviousMonth,
            'orders_month_change' => $this->percentageChange($ordersMonth, $ordersPreviousMonth),
            'products_total' => $this->countModel(Product::class),
            'products_active' => $this->activeProductsCount(),
            'products_pending' => $this->pendingProductsCount(),
            'shops_total' => $this->countModel(Shop::class),
            'shops_pending' => $this->pendingShopsCount(),
            'shops_ready' => $this->readyShopsCount(),
            'shops_missing_gps' => $this->shopsMissingGpsCount(),
            'shops_logistics_incomplete' => $this->shopsLogisticsIncompleteCount(),
            'clients_total' => $this->usersByRoleCount('client'),
            'clients_month' => $this->usersByRoleCount('client', $currentMonthStart),
            'commercials_total' => $this->usersByRoleCount('commercial'),
            'payments_pending' => $this->paymentsPendingCount(),
            'disputes_open' => $this->disputesOpenCount(),
            'carriers_active' => $this->activeCarriersCount(),
            'payouts_pending' => $this->payoutsPendingCount(),
            'boosts_active' => $this->activeBoostsCount(),
            'online_visitors' => (int) Cache::get('online_visitors', 0),
            'visitors_today' => (int) Cache::get('visitors_today_' . now()->toDateString(), 0),
        ];
    }

    public function salesTrend(int $days = 14): array
    {
        $days = max(7, min($days, 31));
        $salesColumn = $this->firstExistingColumn(Order::class, ['total_amount', 'grand_total', 'total', 'amount']);
        $start = now()->copy()->subDays($days - 1)->startOfDay();
        $end = now()->copy()->endOfDay();

        $rows = collect();
        if ($salesColumn) {
            $rows = (clone $this->paidOrdersQuery())
                ->whereBetween('created_at', [$start, $end])
                ->get(['created_at', $salesColumn]);
        }

        $grouped = $rows->groupBy(fn ($order) => Carbon::parse($order->created_at)->format('Y-m-d'));
        $points = [];

        for ($index = 0; $index < $days; $index++) {
            $date = $start->copy()->addDays($index);
            $key = $date->format('Y-m-d');
            $dayRows = $grouped->get($key, collect());

            $points[] = [
                'date' => $key,
                'label' => $date->copy()->locale('fr')->translatedFormat('d M'),
                'amount' => $salesColumn ? (float) $dayRows->sum($salesColumn) : 0.0,
                'orders' => $dayRows->count(),
            ];
        }

        return [
            'days' => $days,
            'points' => $points,
            'total' => (float) collect($points)->sum('amount'),
            'orders' => (int) collect($points)->sum('orders'),
        ];
    }

    public function actionsRequired(array $overview): array
    {
        return array_values(array_filter([
            [
                'key' => 'payments',
                'label' => 'Paiements à contrôler',
                'description' => 'Transactions en attente de vérification ou de preuve.',
                'count' => (int) ($overview['payments_pending'] ?? 0),
                'route' => 'admin.payment-verifications.index',
                'tone' => 'orange',
            ],
            [
                'key' => 'shops-gps',
                'label' => 'Boutiques sans position fiable',
                'description' => 'OVANIE Logistics ne peut pas préparer la collecte sans coordonnées confirmées.',
                'count' => (int) ($overview['shops_missing_gps'] ?? 0),
                'route' => 'admin.shops.index',
                'tone' => 'red',
            ],
            [
                'key' => 'products',
                'label' => 'Produits à compléter',
                'description' => 'Fiches en brouillon, incomplètes ou non publiables.',
                'count' => (int) ($overview['products_pending'] ?? 0),
                'route' => 'admin.products.index',
                'tone' => 'amber',
            ],
            [
                'key' => 'payouts',
                'label' => 'Reversements prêts',
                'description' => 'Fonds vendeurs arrivés à échéance et disponibles pour traitement.',
                'count' => $this->readyPayoutsCount(),
                'route' => 'admin.payouts.index',
                'tone' => 'blue',
            ],
        ], fn (array $item) => $item['count'] > 0));
    }

    public function recentOrders(int $limit = 6): Collection
    {
        return $this->operationalOrdersQuery()
            ->with(['client:id,name,first_name,last_name,email,phone', 'shop:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function pendingProducts(int $limit = 6): Collection
    {
        $query = Product::query()->with(['shop:id,name', 'category:id,name'])->latest('created_at');

        if ($this->hasColumn(Product::class, 'status')) {
            $query->whereIn('status', [
                'pending',
                'draft',
                'inactive',
                'incomplete',
                'pending_logistics',
                'en_attente',
                'waiting',
            ]);
        } elseif ($this->hasColumn(Product::class, 'is_active')) {
            $query->where('is_active', false);
        }

        return $query->limit($limit)->get();
    }

    public function pendingPayments(int $limit = 5): Collection
    {
        if (! class_exists(Payment::class) || ! Schema::hasTable((new Payment())->getTable())) {
            return collect();
        }

        $query = Payment::query()->latest('created_at');

        if ($this->hasColumn(Payment::class, 'status')) {
            $query->whereIn('status', ['pending', 'escrow_held', 'manual_review', 'awaiting_proof']);
        }

        try {
            $query->with(['order:id,order_number,total_amount,status', 'user:id,name,first_name,last_name']);
        } catch (\Throwable $exception) {
            // Les relations peuvent varier selon l'historique du projet.
        }

        return $query->limit($limit)->get();
    }

    public function shopsNeedingAttention(int $limit = 6): Collection
    {
        if (! Schema::hasTable((new Shop())->getTable())) {
            return collect();
        }

        $query = Shop::query()
            ->with([
                'user:id,name,first_name,last_name,email,phone',
                'commercialCreator:id,name,first_name,last_name',
                'commercialManager:id,name,first_name,last_name',
            ])
            ->withCount('products')
            ->latest('updated_at');

        $query->where(function (Builder $attention) {
            $attention->whereRaw('1 = 0');

            if ($this->hasColumn(Shop::class, 'status')) {
                $attention->orWhereNotIn('status', ['approved', 'active']);
            }
            if ($this->hasColumn(Shop::class, 'is_active')) {
                $attention->orWhere('is_active', false);
            }
            if ($this->hasColumn(Shop::class, 'kyc_status')) {
                $attention->orWhereNotIn('kyc_status', ['verified', 'approved']);
            }
            if ($this->hasColumn(Shop::class, 'logistics_status')) {
                $attention->orWhere('logistics_status', '!=', Shop::LOGISTICS_READY);
            }
            if ($this->hasColumn(Shop::class, 'latitude') && $this->hasColumn(Shop::class, 'longitude')) {
                $attention->orWhere(function (Builder $gps) {
                    if ($this->hasColumn(Shop::class, 'logistics_type')) {
                        $gps->where('logistics_type', 'ovanie');
                    }
                    $gps->where(function (Builder $coordinates) {
                        $coordinates->whereNull('latitude')->orWhereNull('longitude');
                    });
                });
            }
            if ($this->hasColumn(Shop::class, 'geo_status')) {
                $attention->orWhereIn('geo_status', [
                    Shop::GEO_STATUS_VERIFICATION_REQUIRED,
                    Shop::GEO_STATUS_REVIEW_RECOMMENDED,
                ]);
            }
        });

        return $query->limit($limit)->get()->map(function (Shop $shop) {
            $shop->setAttribute('dashboard_issues', $this->shopAttentionIssues($shop));
            return $shop;
        });
    }

    public function commercialActivity(int $limit = 8): Collection
    {
        if (! Schema::hasTable((new User())->getTable()) || ! $this->hasColumn(User::class, 'role')) {
            return collect();
        }

        $commercials = User::query()
            ->where('role', 'commercial')
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'status', 'created_at']);

        return $commercials->map(function (User $commercial) {
            $commercialId = (int) $commercial->id;

            $clientsCreated = User::query()
                ->where('created_by_commercial_id', $commercialId)
                ->when($this->hasColumn(User::class, 'role'), fn (Builder $query) => $query->where('role', 'client'))
                ->count();

            $shopsCreated = Shop::query()->where('created_by_commercial_id', $commercialId)->count();
            $shopsManaged = Shop::query()
                ->where(function (Builder $query) use ($commercialId) {
                    $query->where('created_by_commercial_id', $commercialId)
                        ->orWhere('managed_by_commercial_id', $commercialId);
                })
                ->count();
            $productsCreated = Product::query()->where('created_by_commercial_id', $commercialId)->count();

            $ordersLinked = Order::query()
                ->where(function (Builder $query) use ($commercialId) {
                    $query->whereHas('client', fn (Builder $client) => $client->where('created_by_commercial_id', $commercialId))
                        ->orWhereHas('shop', function (Builder $shop) use ($commercialId) {
                            $shop->where('created_by_commercial_id', $commercialId)
                                ->orWhere('managed_by_commercial_id', $commercialId);
                        });
                })
                ->count();

            $lastActivity = collect([
                User::query()->where('created_by_commercial_id', $commercialId)->max('created_at'),
                Shop::query()->where('created_by_commercial_id', $commercialId)->max('created_at'),
                Product::query()->where('created_by_commercial_id', $commercialId)->max('created_at'),
            ])->filter()->sortDesc()->first();

            return [
                'id' => $commercialId,
                'name' => $this->userDisplayName($commercial),
                'email' => $commercial->email,
                'status' => $commercial->status,
                'clients_created' => $clientsCreated,
                'shops_created' => $shopsCreated,
                'shops_managed' => $shopsManaged,
                'products_created' => $productsCreated,
                'orders_linked' => $ordersLinked,
                'last_activity' => $lastActivity ? Carbon::parse($lastActivity) : null,
            ];
        });
    }

    public function securityAlerts(): array
    {
        $alerts = [];

        if (config('app.debug')) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Mode diagnostic actif',
                'message' => 'APP_DEBUG doit être désactivé avant la mise en production.',
            ];
        }

        if (! App::environment(['local', 'development']) && config('app.env') !== 'production') {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Environnement à vérifier',
                'message' => 'APP_ENV doit être défini sur production lors de la mise en ligne.',
            ];
        }

        foreach (['phpinfo.php', 'test.html', 'admi_ovanie.zip', 'Installer_Admin_Ovanie.exe'] as $file) {
            if (function_exists('public_path') && file_exists(public_path($file))) {
                $alerts[] = [
                    'level' => 'critical',
                    'title' => 'Fichier public sensible',
                    'message' => 'Supprimez public/' . $file . ' avant la mise en production.',
                ];
            }
        }

        return $alerts;
    }

    private function shopAttentionIssues(Shop $shop): array
    {
        $issues = [];

        if (($shop->status ?? null) !== Shop::STATUS_APPROVED || ! (bool) ($shop->is_active ?? false)) {
            $issues[] = 'Activation incomplète';
        }
        if (! in_array((string) ($shop->kyc_status ?? ''), [Shop::KYC_VERIFIED, 'approved'], true)) {
            $issues[] = 'KYC à contrôler';
        }
        if (($shop->logistics_status ?? null) !== Shop::LOGISTICS_READY) {
            $issues[] = 'Logistique incomplète';
        }
        if (($shop->logistics_type ?? 'ovanie') === 'ovanie') {
            if ($shop->latitude === null || $shop->longitude === null) {
                $issues[] = 'Position GPS manquante';
            } elseif (in_array((string) ($shop->geo_status ?? ''), [
                Shop::GEO_STATUS_VERIFICATION_REQUIRED,
                Shop::GEO_STATUS_REVIEW_RECOMMENDED,
            ], true)) {
                $issues[] = 'Position GPS à vérifier';
            }
        }

        return array_values(array_unique($issues));
    }

    private function pendingProductsCount(): int
    {
        $query = Product::query();

        if ($this->hasColumn(Product::class, 'status')) {
            $query->whereIn('status', [
                'pending',
                'draft',
                'inactive',
                'incomplete',
                'pending_logistics',
                'en_attente',
                'waiting',
            ]);
        } elseif ($this->hasColumn(Product::class, 'is_active')) {
            $query->where('is_active', false);
        }

        return $query->count();
    }

    private function activeProductsCount(): int
    {
        $query = Product::query();

        if ($this->hasColumn(Product::class, 'is_active')) {
            $query->where('is_active', true);
        }
        if ($this->hasColumn(Product::class, 'status')) {
            $query->whereIn('status', ['active', 'actif', 'published']);
        }

        return $query->count();
    }

    private function pendingShopsCount(): int
    {
        $query = Shop::query();

        if ($this->hasColumn(Shop::class, 'status')) {
            $query->whereNotIn('status', ['approved', 'active']);
        } elseif ($this->hasColumn(Shop::class, 'is_active')) {
            $query->where('is_active', false);
        }

        return $query->count();
    }

    private function readyShopsCount(): int
    {
        $query = Shop::query();

        if ($this->hasColumn(Shop::class, 'status')) {
            $query->where('status', Shop::STATUS_APPROVED);
        }
        if ($this->hasColumn(Shop::class, 'is_active')) {
            $query->where('is_active', true);
        }
        if ($this->hasColumn(Shop::class, 'logistics_status')) {
            $query->where('logistics_status', Shop::LOGISTICS_READY);
        }

        return $query->count();
    }

    private function shopsMissingGpsCount(): int
    {
        if (! $this->hasColumn(Shop::class, 'latitude') || ! $this->hasColumn(Shop::class, 'longitude')) {
            return 0;
        }

        return Shop::query()
            ->when($this->hasColumn(Shop::class, 'logistics_type'), fn (Builder $query) => $query->where('logistics_type', 'ovanie'))
            ->where(function (Builder $query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
                if ($this->hasColumn(Shop::class, 'geo_status')) {
                    $query->orWhereIn('geo_status', [
                        Shop::GEO_STATUS_VERIFICATION_REQUIRED,
                        Shop::GEO_STATUS_REVIEW_RECOMMENDED,
                    ]);
                }
            })
            ->count();
    }

    private function shopsLogisticsIncompleteCount(): int
    {
        if (! $this->hasColumn(Shop::class, 'logistics_status')) {
            return 0;
        }

        return Shop::query()->where('logistics_status', '!=', Shop::LOGISTICS_READY)->count();
    }

    private function paymentsPendingCount(): int
    {
        if (! class_exists(Payment::class) || ! Schema::hasTable((new Payment())->getTable())) {
            return 0;
        }

        $query = Payment::query();
        if ($this->hasColumn(Payment::class, 'status')) {
            $query->whereIn('status', ['pending', 'escrow_held', 'manual_review', 'awaiting_proof']);
        }

        return $query->count();
    }

    private function disputesOpenCount(): int
    {
        $class = 'App\\Models\\Dispute';
        if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
            return 0;
        }

        $query = $class::query();
        if ($this->hasColumn($class, 'status')) {
            $query->whereIn('status', ['open', 'pending', 'escalated', 'in_progress', 'ouvert']);
        }

        return $query->count();
    }

    private function activeCarriersCount(): int
    {
        $class = 'App\\Models\\Carrier';
        if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
            return 0;
        }

        $query = $class::query();
        if ($this->hasColumn($class, 'status')) {
            $query->whereIn('status', ['active', 'approved', 'enabled']);
        } elseif ($this->hasColumn($class, 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->count();
    }

    private function payoutsPendingCount(): int
    {
        if (! class_exists(VendorPayout::class) || ! Schema::hasTable((new VendorPayout())->getTable())) {
            return 0;
        }

        return VendorPayout::query()->whereIn('status', [
            VendorPayout::STATUS_WAITING_PAYMENT,
            VendorPayout::STATUS_WAITING_RECEPTION,
            VendorPayout::STATUS_BLOCKED,
            VendorPayout::STATUS_PENDING,
            VendorPayout::STATUS_APPROVED,
            VendorPayout::STATUS_PROCESSING,
        ])->count();
    }

    private function readyPayoutsCount(): int
    {
        if (! class_exists(VendorPayout::class) || ! Schema::hasTable((new VendorPayout())->getTable())) {
            return 0;
        }

        return VendorPayout::query()->where('status', VendorPayout::STATUS_APPROVED)->count();
    }

    private function activeBoostsCount(): int
    {
        $count = 0;

        if ($this->hasColumn(Product::class, 'is_boosted')) {
            $query = Product::query()->where('is_boosted', true);
            if ($this->hasColumn(Product::class, 'boost_end_at')) {
                $query->where(function (Builder $boost) {
                    $boost->whereNull('boost_end_at')->orWhere('boost_end_at', '>=', now());
                });
            }
            $count += $query->count();
        }

        foreach (['App\\Models\\HomeAd', 'App\\Models\\Banner'] as $class) {
            if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
                continue;
            }

            $query = $class::query();
            if ($this->hasColumn($class, 'status')) {
                $query->whereIn('status', ['active', 'published', 'enabled']);
            } elseif ($this->hasColumn($class, 'is_active')) {
                $query->where('is_active', true);
            }
            $count += $query->count();
        }

        return $count;
    }

    private function paidOrdersQuery(): Builder
    {
        $query = Order::query();

        if ($this->hasColumn(Order::class, 'payment_status')) {
            return $query->whereIn('payment_status', [
                'paid',
                'completed',
                'success',
                'escrow_held',
                'released_to_vendor',
                'commission_paid',
                'cod_completed',
                'verified',
            ]);
        }

        if (Schema::hasTable('payments')) {
            return $query->whereHas('payments', function (Builder $paymentQuery) {
                $paymentQuery->whereIn('status', [
                    'paid',
                    'completed',
                    'success',
                    'escrow_held',
                    'released_to_vendor',
                ]);
            });
        }

        if ($this->hasColumn(Order::class, 'status')) {
            return $query->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed', 'paid']);
        }

        return $query->whereRaw('1 = 0');
    }

    private function operationalOrdersQuery(): Builder
    {
        $query = Order::query();

        try {
            return $query->operational();
        } catch (\Throwable $exception) {
            return $query;
        }
    }

    private function payoutFinancials(): array
    {
        $empty = [
            'product_sales' => 0.0,
            'seller_delivery' => 0.0,
            'ovanie_delivery' => 0.0,
            'commissions' => 0.0,
            'commissions_month' => 0.0,
            'waiting_payment' => 0.0,
            'pending' => 0.0,
            'ready' => 0.0,
            'processing' => 0.0,
            'paid' => 0.0,
        ];

        if (! class_exists(VendorPayout::class) || ! Schema::hasTable((new VendorPayout())->getTable())) {
            if (class_exists(Commission::class) && Schema::hasTable((new Commission())->getTable())) {
                $empty['commissions'] = $this->sumFirstExistingColumn(Commission::class, ['amount', 'commission_amount', 'total_commission']);
                $empty['commissions_month'] = $this->sumFirstExistingColumn(
                    Commission::class,
                    ['amount', 'commission_amount', 'total_commission'],
                    fn (Builder $query) => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                );
            }
            return $empty;
        }

        $base = VendorPayout::query()->whereNotIn('status', [VendorPayout::STATUS_CANCELLED, VendorPayout::STATUS_FAILED]);
        $fundedBase = (clone $base)->where('status', '!=', VendorPayout::STATUS_WAITING_PAYMENT);
        $pendingStatuses = [VendorPayout::STATUS_WAITING_RECEPTION, VendorPayout::STATUS_BLOCKED, VendorPayout::STATUS_PENDING];

        return [
            'product_sales' => Schema::hasColumn('vendor_payouts', 'product_amount')
                ? (float) (clone $fundedBase)->sum('product_amount')
                : (float) (clone $fundedBase)->sum('total_amount'),
            'seller_delivery' => Schema::hasColumn('vendor_payouts', 'seller_delivery_amount')
                ? (float) (clone $fundedBase)->sum('seller_delivery_amount')
                : 0.0,
            'ovanie_delivery' => Schema::hasColumn('vendor_payouts', 'ovanie_delivery_amount')
                ? (float) (clone $fundedBase)->sum('ovanie_delivery_amount')
                : 0.0,
            'commissions' => (float) (clone $fundedBase)->sum('commission_amount'),
            'commissions_month' => (float) (clone $fundedBase)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('commission_amount'),
            'waiting_payment' => (float) (clone $base)->where('status', VendorPayout::STATUS_WAITING_PAYMENT)->sum('payout_amount'),
            'pending' => (float) (clone $base)->whereIn('status', $pendingStatuses)->sum('payout_amount'),
            'ready' => (float) (clone $base)->where('status', VendorPayout::STATUS_APPROVED)->sum('payout_amount'),
            'processing' => (float) (clone $base)->where('status', VendorPayout::STATUS_PROCESSING)->sum('payout_amount'),
            'paid' => (float) (clone $base)->where('status', VendorPayout::STATUS_PAID)->sum('payout_amount'),
        ];
    }

    private function usersByRoleCount(string $role, ?Carbon $since = null): int
    {
        if (! Schema::hasTable((new User())->getTable()) || ! $this->hasColumn(User::class, 'role')) {
            return 0;
        }

        return User::query()
            ->where('role', $role)
            ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->count();
    }

    private function percentageChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return round((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 1);
    }

    private function userDisplayName(User $user): string
    {
        $fullName = trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
        return $fullName !== '' ? $fullName : ((string) ($user->name ?? $user->email ?? 'Commercial'));
    }

    private function firstExistingColumn(string $class, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->hasColumn($class, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function countModel(string $class): int
    {
        if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
            return 0;
        }

        return $class::query()->count();
    }

    private function countWhereDate(string $class, string $column, string $date): int
    {
        if (! class_exists($class) || ! $this->hasColumn($class, $column)) {
            return 0;
        }

        return $class::query()->whereDate($column, $date)->count();
    }

    private function sumFirstExistingColumn(string $class, array $columns, ?callable $callback = null): float
    {
        if (! class_exists($class) || ! Schema::hasTable((new $class())->getTable())) {
            return 0.0;
        }

        foreach ($columns as $column) {
            if (! $this->hasColumn($class, $column)) {
                continue;
            }

            $query = $class::query();
            if ($callback) {
                $callback($query);
            }

            return (float) $query->sum($column);
        }

        return 0.0;
    }

    private function hasColumn(string $class, string $column): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $model = new $class();
        return Schema::hasTable($model->getTable()) && Schema::hasColumn($model->getTable(), $column);
    }
}
