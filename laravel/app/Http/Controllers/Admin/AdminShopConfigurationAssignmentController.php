<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopConfigurationAssignment;
use App\Models\User;
use App\Services\Commercial\ShopConfigurationAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminShopConfigurationAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Shop::query()
            ->with([
                'user:id,name,email,phone',
                'sellerDeliveryProfile',
                'sellerDeliveryZones',
                'activeConfigurationAssignment.commercial:id,name,email',
            ])
            ->withCount('products')
            ->where(function (Builder $q) {
                $q->where(fn (Builder $needs) => $needs->needsLogisticsConfiguration())
                    ->orWhere(fn (Builder $needs) => $needs->needsPayoutConfiguration())
                    ->orWhereHas('activeConfigurationAssignment');
            });

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        match ($request->query('missing')) {
            'logistics' => $query->needsLogisticsConfiguration(),
            'payout' => $query->needsPayoutConfiguration(),
            'both' => $query->needsLogisticsConfiguration()->needsPayoutConfiguration(),
            default => null,
        };

        $shops = $query->latest()->paginate(20)->withQueryString();
        $commercials = User::query()
            ->where('role', 'commercial')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $stats = [
            'total' => Shop::query()
                ->where(function (Builder $q) {
                    $q->where(fn (Builder $needs) => $needs->needsLogisticsConfiguration())
                        ->orWhere(fn (Builder $needs) => $needs->needsPayoutConfiguration())
                        ->orWhereHas('activeConfigurationAssignment');
                })
                ->count(),
            'logistics' => Shop::query()->needsLogisticsConfiguration()->count(),
            'payout' => Shop::query()->needsPayoutConfiguration()->count(),
            'assigned' => ShopConfigurationAssignment::query()
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
        ];

        return view('admin.shops.configuration-missions', compact('shops', 'commercials', 'stats'));
    }

    public function store(Request $request, Shop $shop, ShopConfigurationAssignmentService $service)
    {
        $data = $request->validate([
            'commercial_id' => ['required', 'integer', 'exists:users,id'],
            'configure_logistics' => ['nullable', 'boolean'],
            'configure_payout' => ['nullable', 'boolean'],
            'manage_products' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $commercial = User::findOrFail((int) $data['commercial_id']);
        $admin = Auth::guard('admin')->user();

        $service->assign($shop, $commercial, $admin, [
            'configure_logistics' => $request->boolean('configure_logistics'),
            'configure_payout' => $request->boolean('configure_payout'),
            'manage_products' => $request->boolean('manage_products'),
        ], $data['notes'] ?? null);

        return back()->with('success', 'Mission boutique affectée à ' . $commercial->name . '.');
    }

    public function cancel(ShopConfigurationAssignment $assignment)
    {
        if ($assignment->isActive()) {
            $assignment->forceFill([
                'status' => ShopConfigurationAssignment::STATUS_CANCELLED,
                'completed_at' => now(),
            ])->save();
        }

        return back()->with('success', 'Mission annulée.');
    }
}
