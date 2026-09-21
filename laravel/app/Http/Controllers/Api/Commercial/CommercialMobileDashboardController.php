<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialActivity;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommercialMobileDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();
        $commercialId = (int) $commercial->id;
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $clientsQuery = User::query()
            ->where('role', 'client')
            ->where('created_by_commercial_id', $commercialId);

        $shopsQuery = Shop::query()
            ->where('created_by_commercial_id', $commercialId);

        $productsQuery = Product::query()
            ->where('created_by_commercial_id', $commercialId);

        $clientsTotal = (clone $clientsQuery)->count();
        $clientsToday = (clone $clientsQuery)->where('created_at', '>=', $today)->count();
        $shopsTotal = (clone $shopsQuery)->count();
        $shopsToday = (clone $shopsQuery)->where('created_at', '>=', $today)->count();
        $productsTotal = (clone $productsQuery)->count();
        $productsToday = (clone $productsQuery)->where('created_at', '>=', $today)->count();
        $productsToComplete = (clone $productsQuery)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'draft');
            })
            ->count();

        $dailyVisitTarget = $this->positiveInt(config('commercial_mobile.daily_visit_target'));
        $monthlyVisitTarget = $this->positiveInt(config('commercial_mobile.monthly_visit_target'));

        $dailyVisits = CommercialActivity::query()
            ->where('author_id', $commercialId)
            ->where('type', 'meeting')
            ->where('happened_at', '>=', $today)
            ->count();

        $monthlyVisits = CommercialActivity::query()
            ->where('author_id', $commercialId)
            ->where('type', 'meeting')
            ->where('happened_at', '>=', $monthStart)
            ->count();

        $monthlyProgress = $monthlyVisitTarget
            ? min(1, $monthlyVisits / $monthlyVisitTarget)
            : null;

        return response()->json([
            'profile' => $this->profile($commercial),
            'unread_notifications' => $commercial->unreadNotifications()->count(),
            'stats' => [
                'clients_created' => [
                    'total' => $clientsTotal,
                    'today' => $clientsToday,
                ],
                'shops_opened' => [
                    'total' => $shopsTotal,
                    'today' => $shopsToday,
                ],
                'products_captured' => [
                    'total' => $productsTotal,
                    'today' => $productsToday,
                ],
                'products_to_complete' => $productsToComplete,
            ],
            'activities' => $this->todayActivities($commercialId, $today),
            'summary' => [
                'daily_objective_done' => $dailyVisitTarget ? $dailyVisits : null,
                'daily_objective_target' => $dailyVisitTarget,
                'shops_prospected_today' => $shopsToday,
                'monthly_progress' => $monthlyProgress,
                'monthly_visits' => $monthlyVisitTarget ? $monthlyVisits : null,
                'monthly_visit_target' => $monthlyVisitTarget,
            ],
        ]);
    }

    private function todayActivities(int $commercialId, Carbon $today): array
    {
        $items = collect();

        User::query()
            ->where('role', 'client')
            ->where('created_by_commercial_id', $commercialId)
            ->where('created_at', '>=', $today)
            ->latest()
            ->limit(3)
            ->get(['id', 'name', 'first_name', 'last_name', 'created_at'])
            ->each(function (User $client) use ($items) {
                $name = $client->name ?: trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
                $items->push([
                    'type' => 'client',
                    'subtitle' => 'Client ajouté',
                    'title' => $name !== '' ? $name : 'Nouveau client',
                    'status' => 'Terminé',
                    'time' => optional($client->created_at)->format('H:i') ?? '',
                    '_at' => $client->created_at,
                ]);
            });

        Shop::query()
            ->where('created_by_commercial_id', $commercialId)
            ->where('created_at', '>=', $today)
            ->latest()
            ->limit(3)
            ->get(['id', 'name', 'created_at'])
            ->each(function (Shop $shop) use ($items) {
                $items->push([
                    'type' => 'shop',
                    'subtitle' => 'Boutique ouverte',
                    'title' => $shop->name ?: 'Nouvelle boutique',
                    'status' => 'Terminé',
                    'time' => optional($shop->created_at)->format('H:i') ?? '',
                    '_at' => $shop->created_at,
                ]);
            });

        $latestProduct = Product::query()
            ->with('shop:id,name')
            ->where('created_by_commercial_id', $commercialId)
            ->where('created_at', '>=', $today)
            ->latest()
            ->first();

        if ($latestProduct) {
            $shopProducts = Product::query()
                ->where('created_by_commercial_id', $commercialId)
                ->where('shop_id', $latestProduct->shop_id)
                ->where('created_at', '>=', $today);

            $count = (clone $shopProducts)->count();
            $draftCount = (clone $shopProducts)
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', 'draft');
                })
                ->count();
            $completed = max(0, $count - $draftCount);
            $progress = $count > 0 ? $completed / $count : 0;

            $items->push([
                'type' => 'session',
                'subtitle' => 'Session produits',
                'title' => $latestProduct->shop?->name ?: 'Capture produits',
                'status' => $draftCount > 0 ? 'En cours' : 'Terminé',
                'time' => round($progress * 100).'%',
                'detail' => $completed.'/'.$count.' produits complétés',
                'progress' => round($progress, 4),
                '_at' => $latestProduct->created_at,
            ]);
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['_at']?->getTimestamp() ?? 0)
            ->take(3)
            ->map(function (array $item) {
                unset($item['_at']);
                return $item;
            })
            ->values()
            ->all();
    }

    private function profile(User $user): array
    {
        $avatar = trim((string) ($user->avatar ?? ''));
        $avatarUrl = null;

        if ($avatar !== '') {
            $avatarUrl = Str::startsWith($avatar, ['http://', 'https://'])
                ? $avatar
                : url('/storage/'.ltrim($avatar, '/'));
        }

        $name = $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?: Str::before($name, ' '),
            'name' => $name,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int) $value;
        return $number > 0 ? $number : null;
    }
}
