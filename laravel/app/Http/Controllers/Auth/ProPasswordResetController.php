<?php

namespace App\Http\Controllers\Auth;

use App\Models\ProPasswordResetCode;
use App\Models\ProUser;
use App\Notifications\ProPasswordResetCodeNotification;
use App\Services\Sms\SmsManager;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** ACC-16 / ACC-17 : récupération par e-mail ou téléphone avec code à 6 chiffres. */
class ProPasswordResetController extends ProAccessController
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function requestForm(): View
    {
        return view('pro.auth.forgot-password', [
            'challenge' => session('reset_challenge'),
        ]);
    }

    /**
     * Le nom historique sendResetLink est conservé pour ne pas casser les routes,
     * mais le parcours utilise maintenant un code à 6 chiffres, comme la maquette.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        // Compatibilité avec l'ancien formulaire qui envoyait « email ».
        if (! $request->filled('identifier') && $request->filled('email')) {
            $request->merge(['identifier' => $request->input('email')]);
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [], ['identifier' => 'e-mail ou téléphone']);

        $identifier = trim((string) $data['identifier']);
        $user = $this->findProUserByIdentifier($identifier);
        if ($user && $user->status !== 'active') {
            $user = null;
        }
        $channel = str_contains($identifier, '@') ? 'mail' : 'sms';

        [$challenge, $code] = $this->createChallenge($user, $channel);
        $this->deliverCode($user, $channel, $code, $challenge);

        // Toujours la même réponse : aucun indice sur l'existence du compte.
        return redirect()->route('pro.password.request')->with([
            'status' => 'Si ces informations correspondent à un compte OVANIE PRO, les instructions de réinitialisation ont été envoyées.',
            'reset_challenge' => $challenge,
        ]);
    }

    public function resetForm(Request $request, ?string $challenge = null): View
    {
        $success = (bool) session('password_reset_success', false);
        $record = $challenge ? $this->findChallenge($challenge) : null;

        $state = 'ready';
        if ($success) {
            $state = 'success';
        } elseif (! $record) {
            $state = 'invalid';
        } elseif ($record->isExpired()) {
            $state = 'expired';
        }

        return view('pro.auth.reset-password', [
            'challenge' => $challenge,
            'state' => $state,
            'expiresAt' => $record?->expires_at,
            // Compatibilité avec d'éventuels appels de vue existants.
            'token' => $challenge,
            'email' => '',
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'min:20', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ], [
            'code.digits' => 'Saisissez le code de vérification à 6 chiffres.',
        ], [
            'code' => 'code de vérification',
            'password' => 'nouveau mot de passe',
        ]);

        $challengeHash = hash('sha256', (string) $data['challenge']);
        $result = DB::transaction(function () use ($challengeHash, $data) {
            /** @var ProPasswordResetCode|null $record */
            $record = ProPasswordResetCode::query()
                ->where('challenge_hash', $challengeHash)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->isExpired()) {
                return 'expired';
            }

            if (! $record->pro_user_id || ! Hash::check((string) $data['code'], (string) $record->code_hash)) {
                $record->attempts = min(self::MAX_ATTEMPTS, ((int) $record->attempts) + 1);
                if ($record->attempts >= self::MAX_ATTEMPTS) {
                    $record->used_at = now();
                }
                $record->save();

                return $record->attempts >= self::MAX_ATTEMPTS ? 'expired' : 'invalid_code';
            }

            $user = ProUser::query()->lockForUpdate()->find($record->pro_user_id);
            if (! $user || $user->status !== 'active') {
                $record->forceFill(['used_at' => now()])->save();

                return 'expired';
            }

            $user->forceFill([
                'password' => (string) $data['password'],
                'remember_token' => Str::random(60),
            ])->save();

            $record->forceFill(['used_at' => now()])->save();
            ProPasswordResetCode::query()
                ->where('pro_user_id', $user->id)
                ->whereNull('used_at')
                ->whereKeyNot($record->id)
                ->update(['used_at' => now()]);

            event(new PasswordReset($user));

            return 'success';
        });

        if ($result === 'success') {
            return redirect()->route('pro.password.reset')
                ->with('password_reset_success', true);
        }

        if ($result === 'expired') {
            return redirect()->route('pro.password.reset', ['challenge' => $data['challenge']])
                ->withErrors(['code' => 'Ce code est expiré ou n’est plus utilisable. Demandez un nouveau code.']);
        }

        return back()
            ->withInput($request->only('challenge'))
            ->withErrors(['code' => 'Code incorrect. Vérifiez les 6 chiffres reçus.']);
    }

    /**
     * Vérifie le code dès sa saisie (indicateur « Code valide » de l'écran) sans le consommer.
     * Un code faux compte comme un essai, exactement comme lors de la réinitialisation.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'min:20', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $status = DB::transaction(function () use ($data) {
            /** @var ProPasswordResetCode|null $record */
            $record = ProPasswordResetCode::query()
                ->where('challenge_hash', hash('sha256', (string) $data['challenge']))
                ->lockForUpdate()
                ->first();

            if (! $record || $record->isExpired()) {
                return 'expired';
            }
            if ($record->pro_user_id && Hash::check((string) $data['code'], (string) $record->code_hash)) {
                return 'valid';
            }

            $record->attempts = min(self::MAX_ATTEMPTS, ((int) $record->attempts) + 1);
            if ($record->attempts >= self::MAX_ATTEMPTS) {
                $record->used_at = now();
            }
            $record->save();

            return $record->attempts >= self::MAX_ATTEMPTS ? 'expired' : 'invalid';
        });

        return response()->json(['status' => $status]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'min:20', 'max:255'],
        ]);

        $record = $this->findChallenge((string) $data['challenge']);
        $user = $record?->user;
        $channel = $record?->channel ?: 'mail';

        if ($record && ! $record->used_at) {
            $record->forceFill(['used_at' => now()])->save();
        }

        [$challenge, $code] = $this->createChallenge($user, $channel);
        $this->deliverCode($user, $channel, $code, $challenge);

        return redirect()->route('pro.password.reset', ['challenge' => $challenge])
            ->with('status', 'Un nouveau code a été généré. Si le compte existe, vous le recevrez dans quelques instants.');
    }

    /** @return array{0:string,1:string} */
    private function createChallenge(?ProUser $user, string $channel): array
    {
        $challenge = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($user) {
            ProPasswordResetCode::query()
                ->where('pro_user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
        }

        ProPasswordResetCode::query()->create([
            'pro_user_id' => $user?->id,
            'challenge_hash' => hash('sha256', $challenge),
            'code_hash' => Hash::make($code),
            'channel' => in_array($channel, ['mail', 'sms'], true) ? $channel : 'mail',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        return [$challenge, $code];
    }

    private function findChallenge(string $challenge): ?ProPasswordResetCode
    {
        if (strlen($challenge) < 20 || strlen($challenge) > 255) {
            return null;
        }

        return ProPasswordResetCode::query()
            ->with('user')
            ->where('challenge_hash', hash('sha256', $challenge))
            ->first();
    }

    private function deliverCode(?ProUser $user, string $channel, string $code, string $challenge): void
    {
        if (! $user) {
            return;
        }

        try {
            if ($channel === 'sms' && $user->phone) {
                $sent = SmsManager::send(
                    $user->phone,
                    "OVANIE PRO : votre code de réinitialisation est {$code}. Il expire dans " . self::CODE_TTL_MINUTES . ' minutes.'
                );

                // Si le SMS est désactivé/indisponible et qu'un e-mail existe, basculer
                // silencieusement sur l'e-mail afin de ne pas bloquer le compte.
                if ($sent) {
                    return;
                }
            }

            if ($user->email) {
                $user->notify(new ProPasswordResetCodeNotification($code, $challenge));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Aide au développement local uniquement ; jamais en production.
        if (app()->environment(['local', 'testing'])) {
            Log::info('OVANIE PRO password reset code (local/testing only)', [
                'pro_user_id' => $user->id,
                'code' => $code,
            ]);
        }
    }
}
