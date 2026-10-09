<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopConfigurationAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupportShopConfigurationMissionController extends Controller
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
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        match ($request->query('missing')) {
            'logistics' => $query->needsLogisticsConfiguration(),
            'payout' => $query->needsPayoutConfiguration(),
            'both' => $query->needsLogisticsConfiguration()->needsPayoutConfiguration(),
            'assigned' => $query->whereHas('activeConfigurationAssignment'),
            default => null,
        };

        $shops = $query->latest()->paginate(20)->withQueryString();

        $needsAny = fn (Builder $query) => $query->where(function (Builder $q) {
            $q->where(fn (Builder $needs) => $needs->needsLogisticsConfiguration())
                ->orWhere(fn (Builder $needs) => $needs->needsPayoutConfiguration())
                ->orWhereHas('activeConfigurationAssignment');
        });

        $stats = [
            'total' => $needsAny(Shop::query())->count(),
            'logistics' => Shop::query()->needsLogisticsConfiguration()->count(),
            'payout' => Shop::query()->needsPayoutConfiguration()->count(),
            'assigned' => ShopConfigurationAssignment::query()
                ->whereIn('status', [ShopConfigurationAssignment::STATUS_PENDING, ShopConfigurationAssignment::STATUS_IN_PROGRESS])
                ->count(),
        ];

        return view('support.configuration-missions.index', compact('shops', 'stats'));
    }
}
