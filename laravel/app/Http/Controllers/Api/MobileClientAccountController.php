<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientPaymentMethod;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use App\Services\FirebasePushService;
use App\Services\AccountDeletionService;
use App\Services\AccountDeletionChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class MobileClientAccountController extends Controller
{
    private const NOTIFICATION_TYPES = [
        'orders',
        'payments',
        'deliveries',
        'returns',
        'promotions',
        'account',
        'support',
        'security',
        'newsletter',
        'cart',
    ];

    private const NOTIFICATION_CHANNELS = ['email', 'sms', 'in_app', 'push'];

    public function capabilities(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'payment_methods' => Schema::hasTable('client_payment_methods'),
                'loyalty' => Schema::hasTable('loyalty_transactions'),
                // Aucun modèle/table de carte cadeau n'existe actuellement dans OVANIE.
                // L'application doit donc masquer cette fonction au lieu d'inventer un solde.
                'gift_cards' => false,
                'recently_viewed_sync' => Schema::hasTable('recently_viewed_products'),
                'support_center' => Schema::hasTable('support_tickets') && Schema::hasTable('support_conversations'),
                // Le push n'est annoncé comme disponible que si la table des
                // appareils ET les identifiants FCM HTTP v1 sont réellement configurés.
                'push_notifications' => Schema::hasTable('mobile_push_devices')
                    && app(FirebasePushService::class)->configured(),
                'notification_channels' => [
                    ['key' => 'in_app', 'label' => "Dans l'application", 'available' => Schema::hasTable('notifications')],
                    ['key' => 'email', 'label' => 'E-mail', 'available' => true],
                    ['key' => 'sms', 'label' => 'SMS', 'available' => true],
                    ['key' => 'push', 'label' => 'Push mobile', 'available' => Schema::hasTable('mobile_push_devices') && app(FirebasePushService::class)->configured()],
                ],
                'supported_payment_operators' => [
                    ['key' => 'orange', 'label' => 'Orange Money'],
                    ['key' => 'mtn', 'label' => 'MTN MoMo'],
                    ['key' => 'wave', 'label' => 'Wave'],
                    ['key' => 'moov', 'label' => 'Moov Money'],
                ],
                'supported_saved_payment_types' => ['mobile_money', 'card'],
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profilePayload($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $data['first_name'] = trim((string) ($data['first_name'] ?? '')) ?: null;
        $data['last_name'] = trim((string) ($data['last_name'] ?? '')) ?: null;
        $data['email'] = strtolower(trim((string) $data['email']));
        $data['phone'] = trim((string) ($data['phone'] ?? '')) ?: null;
        $data['whatsapp_phone'] = trim((string) ($data['whatsapp_phone'] ?? '')) ?: null;
        $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $user->name;

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data' => $this->profilePayload($user->fresh()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->forceFill(['password' => Hash::make((string) $data['password'])])->save();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->user()?->currentAccessToken()?->id;
        $mobile = $request->user()->tokens()
            ->where('name', 'like', 'ovanie-mobile%')
            ->latest('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => 'token:'.(int) $token->id,
                'kind' => 'mobile',
                'name' => $this->deviceLabel((string) $token->name),
                'is_current' => (int) $token->id === (int) $currentId,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);

        // Si Laravel utilise les sessions en base, inclure aussi les vraies
        // sessions Web du même compte. On ne fabrique aucun appareil.
        $web = config('session.driver') === 'database' && Schema::hasTable('sessions')
            ? DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->limit(20)
                ->get()
                ->map(fn ($session) => [
                    'id' => 'web:'.(string) $session->id,
                    'kind' => 'web',
                    'name' => $this->webDeviceLabel((string) ($session->user_agent ?? '')),
                    'is_current' => false,
                    'ip_address' => (string) ($session->ip_address ?? ''),
                    'last_used_at' => ! empty($session->last_activity)
                        ? date(DATE_ATOM, (int) $session->last_activity)
                        : null,
                    'created_at' => null,
                ])
            : collect();

        return response()->json([
            'data' => $mobile->concat($web)->values(),
        ]);
    }

    public function destroySession(Request $request, string $session): JsonResponse
    {
        if (str_starts_with($session, 'web:')) {
            abort_unless(Schema::hasTable('sessions'), 404);
            $sessionId = substr($session, 4);
            $deleted = DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $request->user()->id)
                ->delete();
            abort_unless($deleted > 0, 404);

            return response()->json([
                'message' => 'Session Web déconnectée.',
                'current_session_revoked' => false,
            ]);
        }

        $tokenId = str_starts_with($session, 'token:') ? substr($session, 6) : $session;
        abort_unless(ctype_digit((string) $tokenId), 404);

        $accessToken = $request->user()->tokens()->whereKey((int) $tokenId)->firstOrFail();
        $isCurrent = (int) $request->user()->currentAccessToken()?->id === (int) $accessToken->id;
        $accessToken->delete();

        return response()->json([
            'message' => $isCurrent ? 'Session actuelle révoquée.' : 'Appareil déconnecté.',
            'current_session_revoked' => $isCurrent,
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $request->user()->paymentMethods()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'data' => $methods->map(fn (ClientPaymentMethod $method) => $this->paymentMethodPayload($method))->values(),
        ]);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', 'mobile_money')));
        $user = $request->user();

        if ($type === 'card') {
            $data = $request->validate([
                'type' => ['required', Rule::in(['card'])],
                'account_name' => ['required', 'string', 'max:100'],
                'card_brand' => ['required', Rule::in(['visa', 'mastercard'])],
                'card_last4' => ['required', 'digits:4'],
                'card_exp_month' => ['required', 'integer', 'between:1,12'],
                'card_exp_year' => ['required', 'integer', 'min:'.now()->year, 'max:'.(now()->year + 25)],
                'is_default' => ['nullable', 'boolean'],
            ]);

            $data['user_id'] = $user->id;
            $data['type'] = 'card';
            $data['operator'] = 'card';
            $data['account_name'] = trim((string) $data['account_name']);
            // Sécurité PCI : OVANIE ne reçoit ni le numéro complet ni le CVV.
            // La carte enregistrée est une préférence d'affichage/paiement ;
            // la saisie sensible reste hébergée par le prestataire au paiement.
            $data['phone'] = '';
        } else {
            $data = $request->validate([
                'operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
                'account_name' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'string', 'max:30'],
                'is_default' => ['nullable', 'boolean'],
            ]);

            $data['user_id'] = $user->id;
            $data['type'] = 'mobile_money';
            $data['account_name'] = trim((string) $data['account_name']);
            $data['phone'] = trim((string) $data['phone']);
        }

        if ($request->boolean('is_default') || ! $user->paymentMethods()->exists()) {
            $user->paymentMethods()->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $method = ClientPaymentMethod::create($data);

        return response()->json([
            'message' => 'Moyen de paiement ajouté.',
            'data' => $this->paymentMethodPayload($method),
        ], 201);
    }

    public function defaultPaymentMethod(Request $request, ClientPaymentMethod $method): JsonResponse
    {
        abort_unless((int) $method->user_id === (int) $request->user()->id, 403);
        $request->user()->paymentMethods()->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return response()->json([
            'message' => 'Moyen de paiement principal mis à jour.',
            'data' => $this->paymentMethodPayload($method->fresh()),
        ]);
    }

    public function destroyPaymentMethod(Request $request, ClientPaymentMethod $method): JsonResponse
    {
        abort_unless((int) $method->user_id === (int) $request->user()->id, 403);
        $wasDefault = (bool) $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $request->user()->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Moyen de paiement supprimé.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 50);
        $query = $request->user()->notifications();
        $category = trim((string) $request->query('category', ''));

        if ($category !== '') {
            $canonical = $this->canonicalNotificationCategory($category);
            $query->where('data->category', $canonical);
        }

        $page = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($item) => $this->notificationPayload($item))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function readNotification(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json(['data' => $this->notificationPayload($item->fresh())]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        $stored = $request->user()->notification_preferences ?? [];
        $preferences = [];

        foreach (self::NOTIFICATION_TYPES as $type) {
            foreach (self::NOTIFICATION_CHANNELS as $channel) {
                $value = data_get($stored, "{$type}.{$channel}");
                if ($value === null && $channel === 'in_app') {
                    $value = data_get($stored, "{$type}.push");
                }
                $preferences[$type][$channel] = $type === 'security' ? true : ($value === null ? true : (bool) $value);
            }
        }

        return response()->json([
            'data' => [
                'preferences' => $preferences,
                'types' => collect(self::NOTIFICATION_TYPES)->map(fn ($type) => [
                    'key' => $type,
                    'label' => $this->notificationTypeLabel($type),
                ])->values(),
                'channels' => [
                    ['key' => 'in_app', 'label' => "Dans l'application", 'available' => Schema::hasTable('notifications')],
                    ['key' => 'email', 'label' => 'E-mail', 'available' => true],
                    ['key' => 'sms', 'label' => 'SMS', 'available' => true],
                    ['key' => 'push', 'label' => 'Push mobile', 'available' => Schema::hasTable('mobile_push_devices') && app(FirebasePushService::class)->configured()],
                ],
            ],
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['nullable', 'boolean'],
        ]);

        $preferences = [];
        foreach (self::NOTIFICATION_TYPES as $type) {
            foreach (self::NOTIFICATION_CHANNELS as $channel) {
                $preferences[$type][$channel] = (bool) data_get(
                    $validated,
                    "preferences.{$type}.{$channel}",
                    false
                );
            }
        }

        $preferences['security'] = ['email' => true, 'sms' => true, 'in_app' => true, 'push' => true];
        $request->user()->update(['notification_preferences' => $preferences]);

        return $this->notificationPreferences($request);
    }

    public function accountDeletionStatus(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $user = $request->user();
        $social = filled($user->google_id) || filled($user->facebook_id);
        $canDelete = true;
        $reason = null;

        try {
            $deletion->assertCanSelfDelete($user);
        } catch (ValidationException $exception) {
            $canDelete = false;
            $reason = collect($exception->errors())->flatten()->first();
        }

        return response()->json([
            'data' => [
                'can_delete' => $canDelete,
                'reason' => $reason,
                'social_account' => $social,
                'confirmation_word' => 'SUPPRIMER',
                'code_required' => $social,
                'email' => (string) ($user->email ?? ''),
            ],
        ]);
    }

    public function requestAccountDeletionCode(
        Request $request,
        AccountDeletionService $deletion,
        AccountDeletionChallengeService $challenge
    ): JsonResponse {
        $user = $request->user();
        abort_unless(filled($user->google_id) || filled($user->facebook_id), 422, 'Ce compte utilise un mot de passe OVANIE et ne nécessite pas de code e-mail.');

        $deletion->assertCanSelfDelete($user);
        $challenge->issue($user);

        return response()->json([
            'message' => 'Un code de confirmation à usage unique a été envoyé à l’adresse e-mail de votre compte.',
        ]);
    }

    public function deleteAccount(
        Request $request,
        AccountDeletionService $deletion,
        AccountDeletionChallengeService $challenge
    ): JsonResponse {
        $user = $request->user();
        $social = filled($user->google_id) || filled($user->facebook_id);

        $data = $request->validate([
            'delete_confirmation' => ['required', Rule::in(['SUPPRIMER'])],
            'password' => [$social ? 'nullable' : 'required', 'nullable', 'string'],
            'deletion_code' => [$social ? 'required' : 'nullable', 'nullable', 'digits:6'],
        ], [
            'delete_confirmation.in' => 'Saisissez exactement SUPPRIMER pour confirmer la suppression du compte.',
        ]);

        $deletion->assertCanSelfDelete($user);

        if ($social) {
            $challenge->consume($user, (string) ($data['deletion_code'] ?? ''));
        } elseif (! Hash::check((string) ($data['password'] ?? ''), (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $deletion->anonymize($user);

        return response()->json([
            'message' => 'Votre compte a été supprimé. Vos données personnelles ont été anonymisées conformément au workflow OVANIE.',
        ]);
    }

    public function loyalty(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $user = $request->user();
        $transactions = Schema::hasTable('loyalty_transactions')
            ? LoyaltyTransaction::query()->where('user_id', $user->id)->latest()->limit(30)->get()
            : collect();

        return response()->json([
            'data' => [
                'points' => (int) ($user->loyalty_points ?? 0),
                'debt' => (int) ($user->loyalty_debt ?? 0),
                'point_value_xof' => $loyalty->pointValueXof(),
                'available_discount_xof' => $loyalty->calculateDiscount((int) ($user->loyalty_points ?? 0)),
                'transactions' => $transactions->map(fn ($tx) => [
                    'id' => (int) $tx->id,
                    'type' => (string) $tx->type,
                    'points' => (int) $tx->points,
                    'balance_after' => (int) $tx->balance_after,
                    'reference' => (string) $tx->reference,
                    'description' => (string) ($tx->description ?? ''),
                    'created_at' => $tx->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function legal(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'key' => 'terms',
                    'title' => "Conditions générales d'utilisation",
                    'available' => true,
                    'url' => route('cgu'),
                ],
                [
                    'key' => 'privacy',
                    'title' => 'Politique de confidentialité',
                    'available' => false,
                    'url' => null,
                    'message' => 'Aucun document de confidentialité distinct n’est publié dans le backend OVANIE actuel.',
                ],
                [
                    'key' => 'returns',
                    'title' => 'Politique de retour',
                    'available' => false,
                    'url' => null,
                    'message' => 'Les règles de retour sont appliquées par le workflow OVANIE, mais aucun document public distinct n’est publié actuellement.',
                ],
            ],
        ]);
    }

    private function profilePayload($user): array
    {
        return [
            'id' => (int) $user->id,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'name' => (string) ($user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'whatsapp_phone' => (string) ($user->whatsapp_phone ?? ''),
            'status' => (string) ($user->status ?? ''),
        ];
    }

    private function paymentMethodPayload(ClientPaymentMethod $method): array
    {
        return [
            'id' => (int) $method->id,
            'type' => (string) $method->type,
            'operator' => (string) $method->operator,
            'operator_label' => match ((string) $method->operator) {
                'orange' => 'Orange Money',
                'mtn' => 'MTN MoMo',
                'wave' => 'Wave',
                'moov' => 'Moov Money',
                'card' => strtoupper((string) ($method->card_brand ?: 'Carte bancaire')),
                default => strtoupper((string) $method->operator),
            },
            'account_name' => (string) ($method->account_name ?? ''),
            'phone' => (string) ($method->phone ?? ''),
            'is_default' => (bool) $method->is_default,
            'last_used_at' => $method->last_used_at?->toIso8601String(),
            'created_at' => $method->created_at?->toIso8601String(),
            'card_brand' => (string) ($method->card_brand ?? ''),
            'card_last4' => (string) ($method->card_last4 ?? ''),
            'card_exp_month' => $method->card_exp_month ? (int) $method->card_exp_month : null,
            'card_exp_year' => $method->card_exp_year ? (int) $method->card_exp_year : null,
        ];
    }

    private function notificationPayload($notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        return [
            'id' => (string) $notification->id,
            'title' => (string) ($data['title'] ?? 'Notification OVANIE'),
            'message' => (string) ($data['message'] ?? ''),
            'category' => $this->canonicalNotificationCategory((string) ($data['category'] ?? 'account')),
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'order_item_id' => isset($data['order_item_id']) ? (int) $data['order_item_id'] : null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function canonicalNotificationCategory(string $category): string
    {
        return match ($category) {
            'orders', 'order' => 'orders',
            'payments', 'payment', 'payouts' => 'payments',
            'logistics', 'delivery', 'deliveries' => 'deliveries',
            'returns', 'return', 'refund' => 'returns',
            'promo', 'promotion', 'promotions' => 'promotions',
            'security' => 'security',
            'support' => 'support',
            'system', 'account', 'admin' => 'account',
            'newsletter' => 'newsletter',
            'cart' => 'cart',
            default => 'account',
        };
    }

    private function notificationTypeLabel(string $type): string
    {
        return match ($type) {
            'orders' => 'Commandes',
            'payments' => 'Paiements',
            'deliveries' => 'Livraisons',
            'returns' => 'Retours & remboursements',
            'promotions' => 'Promotions',
            'support' => 'Support',
            'security' => 'Sécurité',
            'newsletter' => 'Actualités OVANIE',
            'cart' => 'Panier',
            default => 'Compte & support',
        };
    }

    private function webDeviceLabel(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        $device = str_contains($ua, 'iphone') || str_contains($ua, 'ipad')
            ? 'iPhone/iPad'
            : (str_contains($ua, 'android') ? 'Android' : (str_contains($ua, 'windows') ? 'Windows' : (str_contains($ua, 'macintosh') ? 'Mac' : 'Navigateur')));
        $browser = str_contains($ua, 'edg/')
            ? 'Edge'
            : (str_contains($ua, 'chrome/') ? 'Chrome' : (str_contains($ua, 'firefox/') ? 'Firefox' : (str_contains($ua, 'safari/') ? 'Safari' : 'Web')));

        return $device.' — '.$browser.' (Web OVANIE)';
    }

    private function deviceLabel(string $name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'android')) return 'Android — Application OVANIE';
        if (str_contains($name, 'ios')) return 'iPhone/iPad — Application OVANIE';
        if (str_contains($name, 'web')) return 'Navigateur — Application OVANIE';
        return 'Appareil mobile — Application OVANIE';
    }
}
