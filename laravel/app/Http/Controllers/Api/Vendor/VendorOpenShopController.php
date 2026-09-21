<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ShopController;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class VendorOpenShopController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $authenticatedUser = $this->resolveOptionalSanctumUser($request);

        if ($authenticatedUser) {
            if ($authenticatedUser->shop) {
                return response()->json([
                    'message' => 'Votre compte possède déjà une boutique OVANIE.',
                    'has_shop' => true,
                    'user' => $this->userPayload($authenticatedUser),
                    'shop' => $authenticatedUser->shop,
                ], 409);
            }

            $request->setUserResolver(static fn () => $authenticatedUser);
            Auth::setUser($authenticatedUser);
        }

        // Le même ShopController que le Web reste la source de vérité métier.
        // Accept JSON force les erreurs de validation Laravel en 422 JSON.
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('ovanie_mobile_api', true);
        $request->attributes->set('ovanie_mobile_skip_web_login', true);

        $response = app()->call([ShopController::class, 'store'], [
            'request' => $request,
        ]);

        if (! $response instanceof JsonResponse) {
            return response()->json([
                'message' => 'La création de la boutique n’a pas renvoyé une réponse API valide.',
            ], 500);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $payload = $response->getData(true);
        $shopId = (int) data_get($payload, 'shop.id', 0);
        $shop = $shopId > 0 ? Shop::query()->find($shopId) : null;

        if (! $shop && $authenticatedUser) {
            $shop = $authenticatedUser->fresh()?->shop;
        }

        if (! $shop) {
            $sellerEmail = Str::lower(trim((string) $request->input('sellerEmail')));
            if ($sellerEmail !== '') {
                $shop = Shop::query()
                    ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$sellerEmail]))
                    ->latest('id')
                    ->first();
            }
        }

        if (! $shop) {
            return response()->json([
                'message' => 'La boutique n’a pas pu être retrouvée après sa création.',
            ], 500);
        }

        $user = User::query()->find($shop->user_id);
        if (! $user) {
            return response()->json([
                'message' => 'Le compte vendeur associé à la boutique est introuvable.',
            ], 500);
        }

        $result = [
            'message' => (string) data_get($payload, 'message', 'Votre boutique OVANIE est ouverte.'),
            'has_shop' => true,
            'user' => $this->userPayload($user),
            'shop' => $shop->fresh(),
        ];

        // Pour un nouveau vendeur, la création de boutique ouvre immédiatement
        // la session mobile. Un compte déjà authentifié conserve son token.
        if (! $authenticatedUser) {
            $deviceName = trim((string) $request->input('device_name', 'ovanie-vendor-mobile'));
            if ($deviceName === '') {
                $deviceName = 'ovanie-vendor-mobile';
            }

            $result['token'] = $user->createToken($deviceName)->plainTextToken;
            $result['token_type'] = 'Bearer';
        }

        return response()->json($result, 201);
    }

    private function resolveOptionalSanctumUser(Request $request): ?User
    {
        $plainTextToken = trim((string) $request->bearerToken());
        if ($plainTextToken === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        if (! $accessToken || ! ($accessToken->tokenable instanceof User)) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $user = $accessToken->tokenable;
        $request->setUserResolver(static fn () => $user);
        Auth::setUser($user);

        return $user;
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'has_shop' => true,
        ];
    }
}
