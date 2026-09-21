<?php

namespace App\Services;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\ReturnModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function pointValueXof(): int
    {
        return max(1, (int) config('loyalty.point_value_xof', 10));
    }

    public function calculateDiscount(int $points): float
    {
        return max(0, $points) * $this->pointValueXof();
    }

    public function maxRedeemablePoints(User $user, float $orderAmount): int
    {
        if ((int) ($user->loyalty_debt ?? 0) > 0) {
            return 0;
        }

        $percentage = min(100, max(0, (int) config('loyalty.max_redemption_percent', 20)));
        $maxDiscount = floor(max(0, $orderAmount) * ($percentage / 100));
        $maxByAmount = (int) floor($maxDiscount / $this->pointValueXof());

        return min((int) ($user->loyalty_points ?? 0), $maxByAmount);
    }

    public function reserveForOrder(User $user, Order $order, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $order, $points) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $reference = 'LOYALTY-REDEEM-' . $order->id;

            if (LoyaltyTransaction::where('reference', $reference)->exists()) {
                return;
            }

            if ((int) ($lockedUser->loyalty_debt ?? 0) > 0) {
                throw ValidationException::withMessages([
                    'loyalty_points' => 'Vos futurs gains fidélité doivent d’abord régulariser un ajustement lié à un remboursement.',
                ]);
            }

            if ((int) $lockedUser->loyalty_points < $points) {
                throw ValidationException::withMessages([
                    'loyalty_points' => 'Votre solde de points fidélité est insuffisant.',
                ]);
            }

            $balance = (int) $lockedUser->loyalty_points - $points;
            $lockedUser->forceFill(['loyalty_points' => $balance])->save();

            LoyaltyTransaction::create([
                'user_id' => $lockedUser->id,
                'order_id' => $order->id,
                'type' => 'redeem',
                'points' => -$points,
                'balance_after' => $balance,
                'reference' => $reference,
                'description' => 'Points utilisés pour la commande ' . $order->order_number,
            ]);
        }, 3);
    }

    public function restoreForOrder(Order $order, string $reason = 'Commande annulée'): void
    {
        $points = (int) ($order->loyalty_points_used ?? 0);
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points, $reason) {
            $reference = 'LOYALTY-RESTORE-' . $order->id;
            if (LoyaltyTransaction::where('reference', $reference)->exists()) {
                return;
            }

            $user = User::query()->whereKey($order->client_id)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $balance = (int) $user->loyalty_points + $points;
            $user->forceFill(['loyalty_points' => $balance])->save();

            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'restore',
                'points' => $points,
                'balance_after' => $balance,
                'reference' => $reference,
                'description' => $reason,
            ]);
        }, 3);
    }

    public function awardForCompletedOrder(Order $order): void
    {
        if (! $this->isEligibleForAward($order)) {
            return;
        }

        $base = max(0, (float) ($order->subtotal ?? 0)
            - (float) ($order->discount ?? 0)
            - (float) ($order->loyalty_discount ?? 0));
        $grossPoints = (int) floor($base / max(1, (int) config('loyalty.earn_every_xof', 1000)));

        if ($grossPoints <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $grossPoints) {
            $reference = 'LOYALTY-EARN-' . $order->id;
            if (LoyaltyTransaction::where('reference', $reference)->exists()) {
                return;
            }

            $user = User::query()->whereKey($order->client_id)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $currentDebt = max(0, (int) ($user->loyalty_debt ?? 0));
            $debtOffset = min($currentDebt, $grossPoints);
            $netPoints = $grossPoints - $debtOffset;
            $newDebt = $currentDebt - $debtOffset;
            $balance = (int) $user->loyalty_points + $netPoints;

            $user->forceFill([
                'loyalty_points' => $balance,
                'loyalty_debt' => $newDebt,
            ])->save();

            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'earn',
                'points' => $netPoints,
                'balance_after' => $balance,
                'reference' => $reference,
                'description' => 'Points gagnés pour la commande ' . $order->order_number,
                'meta' => [
                    'gross_points' => $grossPoints,
                    'debt_offset' => $debtOffset,
                    'remaining_debt' => $newDebt,
                ],
            ]);
        }, 3);
    }

    public function adjustForRefund(ReturnModel $return, float $refundAmount): void
    {
        $return->loadMissing(['order', 'orderItem']);
        $order = $return->order;
        $item = $return->orderItem;

        if (! $order || ! $item || $refundAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($return, $order, $refundAmount) {
            $user = User::query()->whereKey($order->client_id)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $eligibleMerchandiseBase = max(
                0.01,
                (float) ($order->subtotal ?? 0)
                    - (float) ($order->discount ?? 0)
                    - (float) ($order->loyalty_discount ?? 0)
            );
            $ratio = min(1, max(0, $refundAmount / $eligibleMerchandiseBase));

            $redeemedToRestore = (int) floor((int) ($order->loyalty_points_used ?? 0) * $ratio);
            if ($redeemedToRestore > 0) {
                $restoreReference = 'LOYALTY-RETURN-RESTORE-' . $return->id;
                if (! LoyaltyTransaction::where('reference', $restoreReference)->exists()) {
                    $balance = (int) $user->loyalty_points + $redeemedToRestore;
                    $user->forceFill(['loyalty_points' => $balance])->save();

                    LoyaltyTransaction::create([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'type' => 'return_restore',
                        'points' => $redeemedToRestore,
                        'balance_after' => $balance,
                        'reference' => $restoreReference,
                        'description' => 'Restitution de points liée au retour #' . $return->id,
                        'meta' => [
                            'return_id' => $return->id,
                            'refund_amount' => $refundAmount,
                            'ratio' => $ratio,
                        ],
                    ]);
                }
            }

            $earnTransaction = LoyaltyTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', 'earn')
                ->first();

            $earnedToReverse = $earnTransaction
                ? (int) floor(max(0, (int) data_get($earnTransaction->meta, 'gross_points', $earnTransaction->points)) * $ratio)
                : 0;

            if ($earnedToReverse <= 0) {
                return;
            }

            $reverseReference = 'LOYALTY-RETURN-REVERSE-' . $return->id;
            if (LoyaltyTransaction::where('reference', $reverseReference)->exists()) {
                return;
            }

            $deduction = min((int) $user->loyalty_points, $earnedToReverse);
            $debtCreated = max(0, $earnedToReverse - $deduction);
            $balance = max(0, (int) $user->loyalty_points - $deduction);
            $newDebt = max(0, (int) ($user->loyalty_debt ?? 0)) + $debtCreated;

            $user->forceFill([
                'loyalty_points' => $balance,
                'loyalty_debt' => $newDebt,
            ])->save();

            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'return_reversal',
                'points' => -$deduction,
                'balance_after' => $balance,
                'reference' => $reverseReference,
                'description' => 'Ajustement des points gagnés après le retour #' . $return->id,
                'meta' => [
                    'return_id' => $return->id,
                    'refund_amount' => $refundAmount,
                    'ratio' => $ratio,
                    'target_reversal_points' => $earnedToReverse,
                    'applied_reversal_points' => $deduction,
                    'debt_created' => $debtCreated,
                    'loyalty_debt_after' => $newDebt,
                ],
            ]);
        }, 3);
    }

    public function isEligibleForAward(Order $order): bool
    {
        $order->loadMissing('items');

        if (! in_array($order->payment_status, ['paid', 'escrow_held'], true)) {
            return false;
        }

        return $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED)
            && $order->items->every(fn ($item) => $item->reception_status === 'confirmed');
    }
}
