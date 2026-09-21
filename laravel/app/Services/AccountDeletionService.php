<?php

namespace App\Services;

use App\Models\AccountDeletionChallenge;
use App\Models\Payment;
use App\Models\ReturnModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(private readonly OrderSettlementService $settlements)
    {
    }

    public function assertCanSelfDelete(User $user): void
    {
        $hasActiveOrder = $user->orders()
            ->whereNotIn('status', ['completed', 'delivered', 'cancelled'])
            ->exists();

        $hasActiveReturn = ReturnModel::query()
            ->where('client_id', $user->id)
            ->whereNotIn('status', [
                ReturnModel::STATUS_REJECTED,
                ReturnModel::STATUS_CLOSED,
                ReturnModel::STATUS_REFUNDED,
                'cancelled',
                'resolved',
            ])
            ->exists();

        $hasPendingPayment = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', Payment::STATUS_PENDING)
            ->exists();

        $hasOutstandingTerminalOrder = $user->orders()
            ->whereIn('status', ['completed', 'delivered'])
            ->get()
            ->contains(fn ($order) => $this->settlements->outstandingAmount($order) > 0.01);

        if ($hasActiveOrder || $hasActiveReturn || $hasPendingPayment || $hasOutstandingTerminalOrder) {
            throw ValidationException::withMessages([
                'delete_confirmation' => 'Votre compte ne peut pas être supprimé tant qu’une commande, un paiement, une livraison, un solde restant ou un retour est encore en cours. Finalisez le dossier ou contactez le support OVANIE.',
            ]);
        }
    }

    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->deletion_in_progress) {
                throw ValidationException::withMessages([
                    'delete_confirmation' => 'Une suppression de compte est déjà en cours.',
                ]);
            }

            // Le même verrou utilisateur est pris par le checkout et la création
            // de demandes de retour. Dès ce point, aucune nouvelle opération client
            // sensible ne peut démarrer avant la fin de cette transaction.
            $lockedUser->forceFill(['deletion_in_progress' => true])->save();

            $this->assertCanSelfDelete($lockedUser);

            if ($lockedUser->avatar) {
                Storage::disk('public')->delete($lockedUser->avatar);
            }

            $lockedUser->favorites()->delete();
            $lockedUser->addresses()->delete();
            $lockedUser->paymentMethods()->delete();
            $lockedUser->notifications()->delete();
            AccountDeletionChallenge::query()->where('user_id', $lockedUser->id)->delete();
            if (Schema::hasTable('mobile_push_devices')) {
                DB::table('mobile_push_devices')->where('user_id', $lockedUser->id)->delete();
            }

            if ($cart = $lockedUser->cart) {
                $cart->items()->delete();
                $cart->delete();
            }

            $lockedUser->forceFill([
                'first_name' => null,
                'last_name' => null,
                'name' => 'Compte supprimé',
                'email' => 'deleted+' . $lockedUser->id . '+' . Str::lower(Str::random(16)) . '@deleted.ovanie.local',
                'email_verified_at' => null,
                'google_id' => null,
                'facebook_id' => null,
                'phone' => null,
                'secondary_phone' => null,
                'whatsapp_phone' => null,
                'whatsapp_verified_at' => null,
                'avatar' => null,
                'birth_date' => null,
                'gender' => null,
                'city' => null,
                'preferred_cities' => [],
                'favorite_categories' => [],
                'notification_preferences' => [],
                'loyalty_points' => 0,
                'loyalty_debt' => 0,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'status' => 'suspended',
                'deletion_in_progress' => false,
            ])->save();

            if (method_exists($lockedUser, 'tokens')) {
                $lockedUser->tokens()->delete();
            }
        }, 3);
    }
}
