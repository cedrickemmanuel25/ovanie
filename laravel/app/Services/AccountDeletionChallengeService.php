<?php

namespace App\Services;

use App\Models\AccountDeletionChallenge;
use App\Models\User;
use App\Notifications\AccountDeletionCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountDeletionChallengeService
{
    private const VALIDITY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function issue(User $user): void
    {
        if (! filled($user->email)) {
            throw ValidationException::withMessages([
                'deletion_code' => 'Aucune adresse e-mail valide n’est disponible pour confirmer la suppression du compte.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $code) {
            AccountDeletionChallenge::query()
                ->where('user_id', $user->id)
                ->delete();

            AccountDeletionChallenge::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'requested_at' => now(),
                'expires_at' => now()->addMinutes(self::VALIDITY_MINUTES),
            ]);
        }, 3);

        $user->notify(new AccountDeletionCodeNotification($code, self::VALIDITY_MINUTES));
    }

    public function consume(User $user, string $code): void
    {
        $result = DB::transaction(function () use ($user, $code) {
            $challenge = AccountDeletionChallenge::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $challenge || ! $challenge->expires_at || now()->greaterThan($challenge->expires_at)) {
                $challenge?->delete();

                return ['error' => 'Le code de suppression est absent ou expiré. Demandez un nouveau code.'];
            }

            if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                $challenge->delete();

                return ['error' => 'Trop de tentatives incorrectes. Demandez un nouveau code.'];
            }

            if (! Hash::check(trim($code), $challenge->code_hash)) {
                $challenge->forceFill([
                    'attempts' => $challenge->attempts + 1,
                ])->save();

                return ['error' => 'Le code de confirmation est incorrect.'];
            }

            // Suppression immédiate après validation : le code est à usage unique.
            $challenge->delete();

            return ['success' => true];
        }, 3);

        if (! empty($result['error'])) {
            throw ValidationException::withMessages([
                'deletion_code' => $result['error'],
            ]);
        }
    }
}
