<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialActivity;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommercialMobileMenuController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $commercialId = (int) $user->id;

        return response()->json([
            'profile' => $this->profilePayload($user),
            'unread_notifications' => $this->unreadCount($user),
            'visited_this_month' => CommercialActivity::query()
                ->where('author_id', $commercialId)
                ->whereIn('type', ['meeting', 'visit'])
                ->where('happened_at', '>=', now()->startOfMonth())
                ->count(),
            'sessions_in_progress' => Schema::hasTable('commercial_capture_sessions')
                ? DB::table('commercial_capture_sessions')
                    ->where('commercial_id', $commercialId)
                    ->whereIn('status', ['draft', 'in_progress'])
                    ->count()
                : 0,
            'drafts_count' => $this->draftQuery($commercialId)->count(),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'profile' => $this->profilePayload($request->user()),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! Schema::hasTable('notifications')) {
            return response()->json(['items' => []]);
        }

        $items = $user->notifications()
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->notificationPayload($notification))
            ->values();

        return response()->json(['items' => $items]);
    }

    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Schema::hasTable('notifications'), 404);

        $item = $user->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (Schema::hasTable('notifications')) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->preferencesPayload($request->user()),
        ]);
    }

    public function savePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'new_clients' => ['required', 'boolean'],
            'follow_up_reminders' => ['required', 'boolean'],
            'shop_updates' => ['required', 'boolean'],
            'news' => ['required', 'boolean'],
            'language' => ['required', 'string', 'max:30'],
            'region' => ['required', 'string', 'max:80'],
            'theme' => ['required', 'in:light,dark,system'],
            'auto_sync' => ['required', 'boolean'],
            'wifi_only' => ['required', 'boolean'],
            'share_location' => ['required', 'boolean'],
            'sync_notifications' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        $stored['commercial_mobile'] = $data;
        $user->notification_preferences = $stored;
        $user->save();

        return response()->json(['preferences' => $this->preferencesPayload($user->fresh())]);
    }

    public function visits(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $commercialId = (int) $user->id;
        $now = now();
        $startToday = $now->copy()->startOfDay();
        $startWeek = $now->copy()->subDays(7)->startOfDay();

        $query = CommercialActivity::query()
            ->with(['lead.shop'])
            ->where('author_id', $commercialId)
            ->whereIn('type', ['meeting', 'visit']);

        $allThisMonth = (clone $query)->where('happened_at', '>=', $now->copy()->startOfMonth())->count();
        $today = (clone $query)->where('happened_at', '>=', $startToday)->count();
        $week = (clone $query)->where('happened_at', '>=', $startWeek)->count();
        $followUp = (clone $query)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '>=', $startToday)->count();
        $converted = (clone $query)->whereHas('lead', fn ($q) => $q->where('status', 'won'))->count();

        $activities = $query->latest('happened_at')->limit(50)->get();
        $shopIds = $activities->map(fn ($activity) => $activity->lead?->shop_id)->filter()->unique()->values();
        $productCounts = $shopIds->isEmpty()
            ? collect()
            : Product::query()
                ->whereIn('shop_id', $shopIds)
                ->where('created_by_commercial_id', $commercialId)
                ->selectRaw('shop_id, COUNT(*) as aggregate')
                ->groupBy('shop_id')
                ->pluck('aggregate', 'shop_id');

        $items = $activities->map(function (CommercialActivity $activity) use ($productCounts) {
            $lead = $activity->lead;
            $shop = $lead?->shop;
            $status = 'visited';
            $statusLabel = 'Visitée';

            if (($lead?->status ?? null) === 'won') {
                $status = 'converted';
                $statusLabel = 'Compte créé';
            } elseif ($activity->next_follow_up_at && $activity->next_follow_up_at->isFuture()) {
                $status = 'follow_up';
                $statusLabel = 'À relancer';
            } elseif (in_array(strtolower((string) $activity->outcome), ['no_answer', 'unavailable', 'sans_suite'], true)) {
                $status = 'no_follow_up';
                $statusLabel = 'Sans suite';
            }

            $name = trim((string) ($shop?->name ?: $lead?->company_name ?: $lead?->contact_name ?: $activity->subject));
            $location = trim(implode(', ', array_filter([
                $shop?->city ?: $lead?->city,
                $shop?->commune,
            ])));

            return [
                'activity_id' => $activity->id,
                'shop_id' => $shop?->id,
                'name' => $name !== '' ? $name : 'Boutique visitée',
                'category' => $shop?->main_category ?: $lead?->sector ?: 'Boutique OVANIE',
                'location' => $location !== '' ? $location : 'Localisation non renseignée',
                'contact' => $lead?->contact_name ?: '',
                'visited_at' => optional($activity->happened_at)->toIso8601String(),
                'next_action_at' => optional($activity->next_follow_up_at)->toIso8601String(),
                'status' => $status,
                'status_label' => $statusLabel,
                'products_captured' => $shop ? (int) ($productCounts[$shop->id] ?? 0) : 0,
                'image_url' => $shop ? $this->shopImageUrl($shop) : null,
            ];
        })->values();

        return response()->json([
            'summary' => [
                'all' => $allThisMonth,
                'today' => $today,
                'week' => $week,
                'follow_up' => $followUp,
                'converted' => $converted,
            ],
            'items' => $items,
        ]);
    }

    public function drafts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $commercialId = (int) $user->id;
        $products = $this->draftQuery($commercialId)
            ->with(['category.parent', 'images'])
            ->latest('updated_at')
            ->limit(60)
            ->get();

        $sessionMap = collect();
        if (Schema::hasTable('commercial_capture_session_products') && Schema::hasTable('commercial_capture_sessions') && $products->isNotEmpty()) {
            $sessionMap = DB::table('commercial_capture_session_products as csp')
                ->join('commercial_capture_sessions as cs', 'cs.id', '=', 'csp.session_id')
                ->whereIn('csp.product_id', $products->pluck('id'))
                ->where('cs.commercial_id', $commercialId)
                ->select(['csp.product_id', 'cs.name as session_name'])
                ->get()
                ->keyBy('product_id');
        }

        $items = $products->map(function (Product $product) use ($sessionMap) {
            $attributes = (array) ($product->product_attributes ?? []);
            $main = $product->category?->parent ?: $product->category;
            $sub = $product->category?->parent ? $product->category : null;
            $session = $sessionMap->get($product->id);

            return [
                'id' => $product->id,
                'name' => $product->name ?: 'Produit à compléter',
                'reference' => $product->sku ?: ('PRD-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT)),
                'category' => $main?->name ?: 'Produit OVANIE',
                'subcategory' => $sub?->name ?: '',
                'session_name' => $session?->session_name ?: 'Session produit',
                'updated_at' => optional($product->updated_at)->toIso8601String(),
                'completion_step' => max(0, min(5, (int) ($attributes['completion_step'] ?? 0))),
                'image_url' => $product->images->first()?->public_url ?: '',
                'synced' => true,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function syncStatus(Request $request): JsonResponse
    {
        return response()->json($this->syncPayload($request->user()));
    }

    public function synchronize(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->staffProfile) {
            $user->staffProfile->forceFill(['last_seen_at' => now()])->save();
        }

        return response()->json($this->syncPayload($user->fresh()));
    }


    private function shopImageUrl(Shop $shop): ?string
    {
        $path = trim((string) ($shop->logo ?? ''));
        if ($path === '') return null;
        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : url('/storage/'.ltrim($path, '/'));
    }

    private function profilePayload(User $user): array
    {
        $user->loadMissing(['staffProfile.manager']);
        $profile = $user->staffProfile;
        $commercialId = (int) $user->id;
        $name = trim((string) ($user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        $avatar = trim((string) ($user->avatar ?? ''));
        $avatarUrl = $avatar === '' ? null : (Str::startsWith($avatar, ['http://', 'https://']) ? $avatar : url('/storage/'.ltrim($avatar, '/')));

        $sessionsCount = Schema::hasTable('commercial_capture_sessions')
            ? DB::table('commercial_capture_sessions')->where('commercial_id', $commercialId)->count()
            : 0;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?: Str::before($name, ' '),
            'name' => $name !== '' ? $name : 'Commercial OVANIE',
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'whatsapp' => (string) ($user->whatsapp_phone ?: $user->phone ?: ''),
            'avatar_url' => $avatarUrl,
            'employee_code' => (string) ($profile?->employee_code ?: '—'),
            'job_title' => (string) ($profile?->job_title ?: 'Commercial terrain'),
            'city' => (string) ($user->city ?: 'Abidjan'),
            'zone' => (string) ($user->city ?: 'Abidjan'),
            'joined_at' => optional($profile?->created_at ?: $user->created_at)?->format('d M Y') ?: '—',
            'active' => $profile?->is_active ?? ($user->status !== 'inactive'),
            'shops_opened' => Shop::query()->where('created_by_commercial_id', $commercialId)->count(),
            'sessions_count' => $sessionsCount,
            'products_handled' => Product::query()->where('created_by_commercial_id', $commercialId)->count(),
        ];
    }

    private function unreadCount(User $user): int
    {
        return Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0;
    }

    private function preferencesPayload(User $user): array
    {
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        $mobile = is_array($stored['commercial_mobile'] ?? null) ? $stored['commercial_mobile'] : [];

        return array_merge([
            'new_clients' => true,
            'follow_up_reminders' => true,
            'shop_updates' => true,
            'news' => false,
            'language' => 'Français',
            'region' => $user->city ?: 'Abidjan',
            'theme' => 'light',
            'auto_sync' => true,
            'wifi_only' => false,
            'share_location' => true,
            'sync_notifications' => true,
        ], $mobile);
    }

    private function notificationPayload(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = trim((string) ($data['title'] ?? $data['subject'] ?? 'Notification OVANIE'));
        $message = trim((string) ($data['message'] ?? $data['body'] ?? $data['text'] ?? ''));
        $haystack = Str::lower($title.' '.$message.' '.($data['type'] ?? ''));
        $type = 'activity';

        if (Str::contains($haystack, ['session', 'capture'])) {
            $type = 'session';
        } elseif (Str::contains($haystack, ['brouillon', 'incomplet', 'produit'])) {
            $type = 'draft';
        } elseif (Str::contains($haystack, ['synchron', 'sync'])) {
            $type = 'sync';
        } elseif (Str::contains($haystack, ['version', 'système', 'system', 'mise à jour'])) {
            $type = 'system';
        } elseif (Str::contains($haystack, ['boutique', 'visite'])) {
            $type = 'shop';
        }

        return [
            'id' => (string) $notification->id,
            'title' => $title !== '' ? $title : 'Notification OVANIE',
            'message' => $message,
            'type' => $type,
            'read' => $notification->read_at !== null,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    private function draftQuery(int $commercialId)
    {
        return Product::query()
            ->where('created_by_commercial_id', $commercialId)
            ->where(function ($query) {
                $query->where('is_active', false);
                if (Schema::hasColumn('products', 'status')) {
                    $query->orWhereNull('status')->orWhere('status', 'draft');
                }
            });
    }

    private function syncPayload(User $user): array
    {
        $commercialId = (int) $user->id;
        $now = now();

        $latestClient = User::query()->where('created_by_commercial_id', $commercialId)->max('updated_at');
        $latestVisit = CommercialActivity::query()->where('author_id', $commercialId)->max('updated_at');
        $latestProduct = Product::query()->where('created_by_commercial_id', $commercialId)->max('updated_at');
        $latestShop = Shop::query()->where('created_by_commercial_id', $commercialId)->max('updated_at');

        $formatTime = function ($value) use ($now) {
            if (! $value) return $now->format('H:i');
            try { return Carbon::parse($value)->format('H:i'); } catch (\Throwable) { return $now->format('H:i'); }
        };

        return [
            'synced' => true,
            'last_sync' => $now->toIso8601String(),
            'elements' => [
                ['title' => 'Clients', 'subtitle' => 'Nouveaux clients et mises à jour', 'time' => $formatTime($latestClient)],
                ['title' => 'Boutiques visitées', 'subtitle' => 'Historique et notes de visite', 'time' => $formatTime($latestVisit ?: $latestShop)],
                ['title' => 'Produits', 'subtitle' => 'Produits capturés et fiches', 'time' => $formatTime($latestProduct)],
                ['title' => 'Activités terrain', 'subtitle' => 'Rapports, comptes rendus et tâches', 'time' => $formatTime($latestVisit)],
                ['title' => 'Paramètres', 'subtitle' => 'Préférences et configuration', 'time' => optional($user->updated_at)->format('H:i') ?: $now->format('H:i')],
            ],
        ];
    }
}
