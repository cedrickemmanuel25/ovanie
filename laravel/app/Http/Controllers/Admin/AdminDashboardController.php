<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\MarketplacePerformanceService;
use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function index(AdminDashboardService $dashboardService)
    {
        $view = view()->exists('admin.dashboard.index')
            ? 'admin.dashboard.index'
            : 'admin.dashboard';

        return view($view, $dashboardService->dashboardPayload());
    }

    public function dashboardData(MarketplacePerformanceService $performance)
    {
        $kpis = $performance->adminDashboardData();

        $products = Product::query()
            ->with(['category:id,name', 'shop:id,name,city,commune'])
            ->latest('products.created_at')
            ->limit(20)
            ->get()
            ->map(function ($p) {
                $price = (float) ($p->price ?? 0);

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'categoryId' => $p->category_id,
                    'category' => $p->category?->name,
                    'shop' => $p->shop?->name,
                    'price' => round($price * 1.05, 2),
                    'stock' => $p->stock ?? 0,
                    'status' => $p->status,
                    'city' => $p->shop?->city ?? $p->city ?? null,
                    'createdAt' => $p->created_at,
                ];
            });

        $orders = $performance->recentOrdersForAdmin(20)->map(function ($order) {
            return [
                'id' => $order->id,
                'clientId' => $order->client?->id ?? $order->user?->id,
                'clientName' => $order->client?->name ?? $order->user?->name,
                'shopId' => $order->shop?->id,
                'shopName' => $order->shop?->name,
                'total' => $order->total_amount ?? $order->grand_total ?? $order->total ?? 0,
                'status' => $order->status,
                'createdAt' => $order->created_at,
                'commissionPaid' => $order->commission_paid ?? false,
                'commissionAmount' => $order->commission_amount ?? 0,
                'smsSent' => $order->sms_sent ?? false,
            ];
        });

        $users = User::query()
            ->select(array_values(array_filter(['id', 'name', 'email', 'last_login_at', 'created_at'], fn ($c) => Schema::hasColumn('users', $c))))
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'lastLogin' => $user->last_login_at ?? null,
            ]);

        return response()->json([
            'kpis' => $kpis,
            'products' => $products,
            'orders' => $orders,
            'shops' => Shop::query()->count(),
            'users' => $users,
            'paymentsPending' => class_exists(Payment::class) && Schema::hasColumn('payments', 'status')
                ? Payment::query()->whereIn('status', ['pending', 'en_attente'])->count()
                : 0,
            'visitors' => Cache::get('online_visitors', 0),
            'visitorsToday' => Cache::get('visitors_today_' . now()->toDateString(), 0),
        ]);
    }
}
