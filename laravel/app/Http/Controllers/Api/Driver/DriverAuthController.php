<?php

namespace App\Http\Controllers\Api\Driver;

/**
 * API MOBILE — Application Flutter "OVANIE Livreur" (contrat figé).
 *
 * OVANIE ne recrute pas ses propres livreurs : ce sont des livreurs partenaires
 * invités par la Logistique (nom, prénom, téléphone créés depuis le back-office
 * web — voir App\Http\Controllers\LogisticsDirectoryController::storeDriver).
 * Le livreur ouvre ensuite l'app mobile avec CE numéro pour vérifier son statut,
 * se connecter, puis compléter son dossier (voir DriverOnboardingController).
 *
 * Authentification : jetons Sanctum "personal access token", exactement comme
 * les autres apps mobiles OVANIE (Vendeur : VendorMobileController::openShop,
 * Commercial : CommercialMobileAuthController::login). Le jeton est renvoyé en
 * clair une seule fois à la vérification de l'OTP et doit être envoyé ensuite
 * dans l'entête `Authorization: Bearer <token>` de chaque appel protégé
 * (middleware `auth:sanctum`).
 *
 * Connexion par OTP SMS : le livreur ne mémorise plus de code personnel. Il
 * reçoit un code à 6 chiffres par SMS, valable 10 minutes, à usage unique.
 * L'envoi réutilise l'infrastructure SMS existante du projet
 * (App\Services\Sms\SmsManager, driver AllMySMS déjà configuré pour les OTP
 * de confirmation de livraison — voir LogisticsShipmentWorkflowService).
 * Si `SMS_ENABLED` vaut false (environnement local), SmsManager::send()
 * renvoie simplement false sans lever d'exception : dans ce cas le code est
 * uniquement journalisé (Log::info, canal par défaut) afin de rester
 * testable sans dépenser de crédits SMS. Voir `sendOtp()` ci-dessous.
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/auth/check-phone
 * Body   : {"phone": "+2250700000000"}
 * Réponses :
 *   - Numéro inconnu :
 *       {"registered": false}
 *   - Connu, dossier pas encore actif (invited | pending_review | rejected) :
 *       {
 *         "registered": true,
 *         "driver": {"id": 1, "first_name": "Adama", "last_name": "Koné", "phone": "+2250700000000"},
 *         "onboarding_status": "invited"
 *       }
 *   - Connu, actif ou suspendu (peut se connecter par OTP) :
 *       {"registered": true, "can_login": true, "onboarding_status": "active"}
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/auth/login
 * Body   : {"phone": "+2250700000000"}
 * Génère un code OTP à 6 chiffres et l'envoie par SMS au livreur. Fonctionne
 * pour tout statut sauf "suspended" (un livreur "invited"/"pending_review"/
 * "rejected" doit pouvoir se connecter pour compléter ou corriger son dossier).
 * 200 OK : {"otp_sent": true, "expires_in": 600}
 * 422 : {"message": "...", "errors": {"phone": ["Aucun livreur actif ne correspond à ce numéro."]}}
 * 403 : {"message": "Votre compte est suspendu...", "onboarding_status": "suspended"}
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/auth/login/verify
 * Body   : {"phone": "+2250700000000", "otp": "123456", "device_name": "Pixel 7"}
 * 200 OK :
 *   {
 *     "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
 *     "driver": {
 *       "id": 1, "first_name": "Adama", "last_name": "Koné", "name": "Adama Koné",
 *       "phone": "+2250700000000", "onboarding_status": "active", "is_active": true,
 *       "must_change_password": false
 *     }
 *   }
 * 422 : {"message": "...", "errors": {"otp": ["Code invalide ou expiré."]}}
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/auth/login/resend
 * Body   : {"phone": "+2250700000000"}
 * Renvoie un nouveau code OTP (throttlé par la route : 1 appel / 30s en plus
 * du throttle global de la route). Mêmes réponses que /login.
 * 429 : {"message": "Veuillez patienter avant de redemander un code."} (si trop tôt)
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/auth/logout   (auth:sanctum)
 * Révoque le jeton courant. 200 OK : {"ok": true}
 */
use App\Models\DeliveryDriver;
use App\Services\Sms\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverAuthController
{
    /** Durée de validité d'un code OTP, en secondes. */
    private const OTP_TTL_SECONDS = 600;

    /** Délai minimum entre deux envois d'OTP pour un même livreur, en secondes. */
    private const OTP_RESEND_COOLDOWN_SECONDS = 30;

    public function checkPhone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $driver = $this->findByPhone($data['phone']);

        if (! $driver) {
            return response()->json(['registered' => false]);
        }

        if (in_array($driver->onboarding_status, [
            DeliveryDriver::ONBOARDING_INVITED,
            DeliveryDriver::ONBOARDING_PENDING_REVIEW,
            DeliveryDriver::ONBOARDING_REJECTED,
        ], true)) {
            return response()->json([
                'registered' => true,
                'driver' => [
                    'id' => $driver->id,
                    'first_name' => $driver->first_name,
                    'last_name' => $driver->last_name,
                    'phone' => $driver->phone,
                ],
                'onboarding_status' => $driver->onboarding_status,
            ]);
        }

        // active ou suspended : le livreur peut demander un code OTP.
        return response()->json([
            'registered' => true,
            'can_login' => true,
            'onboarding_status' => $driver->onboarding_status,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        return $this->sendOtp($request);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->sendOtp($request, isResend: true);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'phone.required' => 'Renseignez votre numéro de téléphone.',
            'otp.required' => 'Renseignez le code reçu par SMS.',
            'otp.digits' => 'Le code doit contenir exactement 6 chiffres.',
        ]);

        $driver = $this->findByPhone($data['phone']);

        $invalid = ! $driver
            || ! filled($driver->otp_code)
            || filled($driver->otp_used_at)
            || ! $driver->otp_expires_at
            || $driver->otp_expires_at->isPast()
            || ! hash_equals((string) $driver->otp_code, (string) $data['otp']);

        if ($invalid) {
            throw ValidationException::withMessages([
                'otp' => 'Code invalide ou expiré.',
            ]);
        }

        // Un livreur "invited"/"pending_review"/"rejected" doit pouvoir obtenir
        // un jeton pour compléter/corriger son dossier d'inscription : seul un
        // dossier "suspended" (jamais "actif" mais bloqué volontairement) est
        // refusé ici. "rejected" reste autorisé pour permettre une nouvelle
        // soumission après correction.
        if ($driver->onboarding_status === DeliveryDriver::ONBOARDING_SUSPENDED) {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez OVANIE Logistics.',
                'onboarding_status' => $driver->onboarding_status,
            ], 403);
        }

        // Usage unique : le code ne peut plus être rejoué après vérification.
        $driver->forceFill(['otp_used_at' => now()])->save();

        $device = Str::of((string) ($data['device_name'] ?? 'mobile'))
            ->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(24, '')->toString();

        $token = $driver->createToken('ovanie-livreur-'.($device ?: 'mobile').'-'.Str::lower(Str::random(8)))->plainTextToken;

        // Une authentification réussie ne suffit plus à déclarer le livreur
        // « En ligne ». Le statut en ligne est activé uniquement après réception
        // d'un heartbeat GPS via /api/driver/presence.
        $driver->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
            'is_online' => false,
        ])->save();

        return response()->json([
            'token' => $token,
            'driver' => $this->driverPayload($driver),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $driver = $request->user();
        $driver?->currentAccessToken()?->delete();
        $driver?->forceFill(['is_online' => false])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Génère, enregistre et envoie un code OTP au livreur identifié par son
     * numéro de téléphone. Utilisé par /login et /login/resend.
     */
    private function sendOtp(Request $request, bool $isResend = false): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
        ], [
            'phone.required' => 'Renseignez votre numéro de téléphone.',
        ]);

        $driver = $this->findByPhone($data['phone']);

        if (! $driver) {
            throw ValidationException::withMessages([
                'phone' => 'Aucun livreur actif ne correspond à ce numéro.',
            ]);
        }

        if ($driver->onboarding_status === DeliveryDriver::ONBOARDING_SUSPENDED) {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez OVANIE Logistics.',
                'onboarding_status' => $driver->onboarding_status,
            ], 403);
        }

        if ($isResend && $driver->otp_last_sent_at && $driver->otp_last_sent_at->diffInSeconds(now()) < self::OTP_RESEND_COOLDOWN_SECONDS) {
            return response()->json([
                'message' => 'Veuillez patienter avant de redemander un code.',
            ], 429);
        }

        $otp = (string) random_int(100000, 999999);

        $driver->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addSeconds(self::OTP_TTL_SECONDS),
            'otp_used_at' => null,
            'otp_last_sent_at' => now(),
        ])->save();

        $message = "Votre code OVANIE Livreur de connexion est {$otp}. Il expire dans 10 minutes. Ne le communiquez à personne.";

        $sent = false;
        try {
            $sent = SmsManager::send($driver->phone, $message);
        } catch (\Throwable $e) {
            Log::error('Erreur envoi OTP livreur', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $sent) {
            // SMS_ENABLED=false en local (ou échec silencieux du driver SMS) :
            // on journalise le code pour permettre les tests sans crédit SMS.
            Log::info('OTP livreur (SMS désactivé ou échoué, code journalisé pour test local)', [
                'driver_id' => $driver->id,
                'phone' => $driver->phone,
                'otp' => $otp,
            ]);
        }

        return response()->json([
            'otp_sent' => true,
            'expires_in' => self::OTP_TTL_SECONDS,
        ]);
    }

    private function findByPhone(string $phone): ?DeliveryDriver
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $localDigits = str_starts_with($digits, '225') ? substr($digits, 3) : $digits;

        return DeliveryDriver::query()->get()->first(function (DeliveryDriver $candidate) use ($digits, $localDigits): bool {
            $stored = preg_replace('/\D+/', '', (string) $candidate->phone) ?? '';
            $storedLocal = str_starts_with($stored, '225') ? substr($stored, 3) : $stored;

            return $stored !== '' && ($stored === $digits || $storedLocal === $localDigits);
        });
    }

    public function driverPayload(DeliveryDriver $driver): array
    {
        return [
            'id' => $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'email' => $driver->email,
            'onboarding_status' => $driver->onboarding_status,
            'is_active' => (bool) $driver->is_active,
            'must_change_password' => (bool) $driver->must_change_password,
            'is_online' => $driver->is_online,
            'presence_heartbeat_seconds' => (int) config('delivery.driver_presence_heartbeat_seconds', 45),
            'presence_offline_after_seconds' => (int) config('delivery.driver_presence_online_seconds', 120),
        ];
    }
}
