<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PortalAuthenticationService;
use App\Services\Auth\PublicAccountService;
use App\Notifications\MobileResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly PortalAuthenticationService $portalAuth,
        private readonly PublicAccountService $accounts,
    ) {
    }

    /** Vérification simple que l'API mobile d'authentification est chargée. */
    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'OVANIE Mobile Auth',
            'version' => 'v75',
        ]);
    }

    /**
     * Connexion de l'application publique OVANIE.
     *
     * Le compte et le mot de passe sont ceux du site web : aucune deuxième
     * base de comptes n'est créée pour le mobile.
     */
    public function login(Request $request): JsonResponse
    {
        // Le Web historique appelle ce champ `email` tout en acceptant aussi
        // un numéro de téléphone. Les nouvelles apps envoient le même nom de
        // champ ; `identifier` reste accepté pour les anciens APK.
        $identifier = trim((string) $request->input('email', $request->input('identifier', '')));
        $request->merge(['identifier' => $identifier]);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
            'portal' => ['nullable', 'in:client,vendor'],
        ]);

        $identifier = trim((string) $data['identifier']);
        $password = (string) $data['password'];
        $throttleKey = $this->throttleKey($identifier, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans quelques instants.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $user = $this->portalAuth->findUserByIdentifier($identifier);

        if (! $user || ! $this->portalAuth->validateCredentials($user, $password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Identifiants incorrects. Vérifiez votre e-mail/téléphone et votre mot de passe.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($error = $this->portalAuth->accessError($user, $data['portal'] ?? null)) {
            return response()->json([
                'message' => $error,
            ], 403);
        }

        // V74 : ne plus révoquer les autres appareils à chaque connexion.
        // Un client peut être connecté simultanément sur son téléphone réel et
        // sur l'émulateur. Le token reste valide jusqu'à une déconnexion
        // explicite ou jusqu'au nettoyage de sécurité des anciens tokens.
        $token = $this->issuePersistentMobileToken($user, $data['device_name'] ?? null);

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->mobileUser($user),
        ]);
    }

    /**
     * Inscription client mobile.
     * Après création, la session est ouverte directement : pas de blocage
     * /verify-email, conformément au parcours OVANIE actuel.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $this->accounts->validateClientRegistration($request);
        $user = $this->accounts->createClient($data);

        $token = $this->issuePersistentMobileToken($user, $data['device_name'] ?? null);

        return response()->json([
            'message' => 'Inscription réussie.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->mobileUser($user),
        ], 201);
    }

    /**
     * Mot de passe oublié mobile.
     *
     * Le compte est recherché avec exactement les mêmes identifiants que la
     * connexion mobile (e-mail ou téléphone), puis le broker Laravel standard
     * envoie le même lien de réinitialisation que le Web. Aucune table ni
     * logique de mot de passe parallèle n'est créée pour l'application.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        // Le formulaire Web historique de récupération utilise le champ
        // `email`. Les applications mobiles utilisent désormais le même champ.
        // `identifier` reste accepté pour les anciens APK déjà installés.
        $identifier = trim((string) $request->input('email', $request->input('identifier', '')));
        $request->merge(['identifier' => $identifier]);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'portal' => ['nullable', 'in:client,vendor'],
        ]);

        $identifier = trim((string) $data['identifier']);
        $portal = $this->portalAuth->normalizePortal($data['portal'] ?? null);
        $user = $this->portalAuth->findUserByIdentifier($identifier);

        $genericMessage = 'Si un compte correspond à ces informations, un lien de réinitialisation a été envoyé à l’adresse e-mail associée.';

        if (! $user
            || $this->portalAuth->isInternalUser($user)
            || blank($user->email)
            || $this->portalAuth->accessError($user, $portal) !== null) {
            return response()->json(['message' => $genericMessage]);
        }

        try {
            $token = PasswordBroker::broker()->createToken($user);
            $user->notify(new MobileResetPasswordNotification(
                $token,
                $portal ?: (string) ($user->role ?: PortalAuthenticationService::PORTAL_CLIENT),
                true,
            ));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Le service de récupération OVANIE est temporairement indisponible. Réessayez dans quelques instants.',
            ], 503);
        }

        return response()->json(['message' => $genericMessage]);
    }

    /**
     * Termine la réinitialisation directement dans l'application.
     * Le token est validé par le Password Broker Laravel utilisé par le Web.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'portal' => ['nullable', 'in:client,vendor'],
        ]);

        $portal = $this->portalAuth->normalizePortal($data['portal'] ?? null);
        $account = $this->portalAuth->findUserByIdentifier((string) $data['email']);
        if (! $account || $this->portalAuth->accessError($account, $portal) !== null) {
            return response()->json([
                'message' => 'Ce lien de réinitialisation est invalide ou ne correspond pas à cette application.',
                'errors' => ['email' => ['Compte incompatible avec ce lien de récupération.']],
            ], 422);
        }

        $status = PasswordBroker::reset(
            [
                'email' => strtolower(trim((string) $data['email'])),
                'password' => (string) $data['password'],
                'password_confirmation' => (string) $data['password_confirmation'],
                'token' => (string) $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->where(function ($query) {
                    $query->where('name', 'like', 'ovanie-mobile%')
                        ->orWhere('name', 'like', 'OVANIE Vendeur%');
                })->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Ce lien de réinitialisation est invalide ou expiré. Demandez un nouveau lien.',
                'errors' => ['token' => ['Lien invalide ou expiré.']],
            ], 422);
        }

        return response()->json([
            'message' => 'Votre mot de passe OVANIE a été réinitialisé. Vous pouvez maintenant vous connecter.',
        ]);
    }

    /**
     * Profil de la session mobile courante.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Session OVANIE invalide ou expirée.',
            ], 401);
        }

        return response()->json([
            'user' => $this->mobileUser($user),
        ]);
    }

    /**
     * Déconnexion : seul le token actuellement utilisé est révoqué.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Émet un token mobile persistant sans déconnecter les autres appareils.
     *
     * Sanctum n'expire pas automatiquement ces tokens dans ce projet
     * (config sanctum.expiration = null). La déconnexion explicite révoque
     * uniquement le token courant. On garde au maximum 8 sessions mobiles
     * récentes et on supprime les très anciens tokens pour éviter une
     * accumulation illimitée.
     */
    private function issuePersistentMobileToken(User $user, ?string $deviceName = null): string
    {
        $mobileTokens = $user->tokens()
            ->where('name', 'like', 'ovanie-mobile%')
            ->orderByDesc('created_at')
            ->get(['id', 'created_at']);

        $staleIds = $mobileTokens
            ->filter(fn ($token) => $token->created_at && $token->created_at->lt(now()->subDays(180)))
            ->pluck('id')
            ->all();

        if ($staleIds !== []) {
            $user->tokens()->whereIn('id', $staleIds)->delete();
        }

        $recentIds = $user->tokens()
            ->where('name', 'like', 'ovanie-mobile%')
            ->orderByDesc('created_at')
            ->pluck('id')
            ->values();

        if ($recentIds->count() >= 8) {
            $idsToDelete = $recentIds->slice(7)->all();
            if ($idsToDelete !== []) {
                $user->tokens()->whereIn('id', $idsToDelete)->delete();
            }
        }

        $device = Str::of((string) ($deviceName ?: 'mobile'))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '')
            ->toString();

        return $user->createToken(
            'ovanie-mobile-' . ($device ?: 'mobile') . '-' . Str::lower(Str::random(8))
        )->plainTextToken;
    }

    private function mobileUser(User $user): array
    {
        $hasShop = false;
        if (method_exists($user, 'shop')) {
            try {
                $hasShop = $user->shop()->exists();
            } catch (\Throwable) {
                $hasShop = false;
            }
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
            'phone' => $user->phone,
            'whatsapp_phone' => $user->whatsapp_phone,
            'role' => $user->role ?: 'client',
            'status' => $user->status,
            'has_shop' => $hasShop,
        ];
    }

    private function throttleKey(string $identifier, ?string $ip): string
    {
        return Str::transliterate(
            'mobile-login|'.Str::lower($identifier).'|'.($ip ?: 'unknown')
        );
    }
}
