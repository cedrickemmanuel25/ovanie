<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCommissionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'status' => ['nullable', 'in:pending,paid,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:newest,oldest,amount_desc,amount_asc'],
        ]);

        $query = Commission::query()
            ->with([
                'shop:id,name,slug,user_id',
                'shop.user:id,name,first_name,last_name,email',
                'order:id,order_number,invoice_number,client_id,subtotal,total_amount,payment_method,payment_status,status,created_at',
                'order.client:id,name,first_name,last_name,email,phone',
            ]);

        $this->applyFilters($query, $filters);

        $summary = [
            'total_amount' => (float) (clone $query)->sum('amount'),
            'pending_amount' => (float) (clone $query)
                ->where('status', Commission::STATUS_PENDING)
                ->sum('amount'),
            'paid_amount' => (float) (clone $query)
                ->where('status', Commission::STATUS_PAID)
                ->sum('amount'),
            'shops_count' => (int) (clone $query)
                ->whereNotNull('shop_id')
                ->distinct('shop_id')
                ->count('shop_id'),
            'records_count' => (int) (clone $query)->count(),
        ];

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderByDesc('id'),
            default => $query->latest('created_at'),
        };

        $commissions = $query
            ->paginate(20)
            ->withQueryString();

        $shops = Shop::query()
            ->whereHas('commissions')
            ->orderBy('name')
            ->get(['id', 'name']);

        $commissionRate = (float) config('marketplace.default_commission_rate', 0.05);

        return view('admin.commissions.index', compact(
            'commissions',
            'shops',
            'summary',
            'commissionRate',
            'filters'
        ));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery
                    ->whereHas('order', function (Builder $orderQuery) use ($search) {
                        $orderQuery
                            ->where('order_number', 'like', "%{$search}%")
                            ->orWhere('invoice_number', 'like', "%{$search}%")
                            ->orWhereHas('client', function (Builder $clientQuery) use ($search) {
                                $clientQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('shop', function (Builder $shopQuery) use ($search) {
                        $shopQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
