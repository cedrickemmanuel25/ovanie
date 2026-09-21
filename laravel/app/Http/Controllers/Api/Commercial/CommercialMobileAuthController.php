<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommercialMobileAuthController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'OVANIE Commercial Mobile Auth',
            'version' => 'v1',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $identifier = trim((string) $data['identifier']);
        $password = (string) $data['password'];
        $throttleKey = 'commercial-mobile-login:'.Str::lower($identifier).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans quelques instants.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $user = $this->findCommercial($identifier);

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Identifiants incorrects. Vérifiez votre e-mail/téléphone et votre mot de passe.',
            ], 401);
        }

        if ((string) $user->role !== 'commercial') {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Cet accès est réservé aux commerciaux OVANIE.',
            ], 403);
        }

        if ((string) $user->status !== 'active' || ($user->staffProfile && ! $user->staffProfile->is_active)) {
            return response()->json([
                'message' => 'Votre accès professionnel OVANIE est désactivé. Contactez un administrateur.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        if ($user->staffProfile) {
            $user->staffProfile->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $token = $this->issueToken($user, $data['device_name'] ?? null);

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || (string) $user->role !== 'commercial') {
            return response()->json(['message' => 'Session Commercial OVANIE invalide ou expirée.'], 401);
        }

        return response()->json(['user' => $this->userPayload($user->loadMissing('staffProfile'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function findCommercial(string $identifier): ?User
    {
        $query = User::query()->with('staffProfile');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query
                ->whereRaw('LOWER(email) = ?', [Str::lower($identifier)])
                ->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if ($digits === '') {
            return null;
        }

        $variants = collect([$digits])
            ->when(strlen($digits) === 10, fn ($items) => $items->push('225'.$digits))
            ->when(str_starts_with($digits, '225') && strlen($digits) > 10, fn ($items) => $items->push(substr($digits, 3)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $normalizedPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '')";
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $hasWhatsappPhone = Schema::hasColumn('users', 'whatsapp_phone');
        $normalizedWhatsappSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp_phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '')";

        return $query
            ->where(function ($phoneQuery) use (
                $identifier,
                $variants,
                $normalizedPhoneSql,
                $normalizedWhatsappSql,
                $placeholders,
                $hasWhatsappPhone
            ) {
                $phoneQuery->where('phone', $identifier)
                    ->orWhereRaw("{$normalizedPhoneSql} IN ({$placeholders})", $variants);

                if ($hasWhatsappPhone) {
                    $phoneQuery->orWhere('whatsapp_phone', $identifier)
                        ->orWhereRaw("{$normalizedWhatsappSql} IN ({$placeholders})", $variants);
                }
            })
            ->first();
    }

    private function issueToken(User $user, ?string $deviceName): string
    {
        $prefix = 'ovanie-commercial-mobile';

        $staleIds = $user->tokens()
            ->where('name', 'like', $prefix.'%')
            ->where('created_at', '<', now()->subDays(180))
            ->pluck('id')
            ->all();

        if ($staleIds !== []) {
            $user->tokens()->whereIn('id', $staleIds)->delete();
        }

        $recentIds = $user->tokens()
            ->where('name', 'like', $prefix.'%')
            ->latest('created_at')
            ->pluck('id')
            ->values();

        if ($recentIds->count() >= 8) {
            $idsToDelete = $recentIds->slice(7)->all();
            if ($idsToDelete !== []) {
                $user->tokens()->whereIn('id', $idsToDelete)->delete();
            }
        }

        $device = trim((string) $deviceName);
        $name = $prefix.($device !== '' ? ':'.Str::limit($device, 50, '') : '');

        return $user->createToken($name, ['commercial'])->plainTextToken;
    }

    private function userPayload(User $user): array
    {
        $avatar = trim((string) ($user->avatar ?? ''));
        $avatarUrl = null;

        if ($avatar !== '') {
            $avatarUrl = Str::startsWith($avatar, ['http://', 'https://'])
                ? $avatar
                : url('/storage/'.ltrim($avatar, '/'));
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'avatar_url' => $avatarUrl,
        ];
    }
}
