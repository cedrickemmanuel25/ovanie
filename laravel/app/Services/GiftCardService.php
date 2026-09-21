<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardRecharge;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardService
{
    public function normalizeCode(string $code): string
    {
        return Str::upper(trim(preg_replace('/\s+/', '', $code) ?: $code));
    }

    public function resolveForCheckout(User $user, string $code, string $pin, bool $lock = false): GiftCard
    {
        $query = GiftCard::query()->with('product')->where('code', $this->normalizeCode($code));

        if ($lock) {
            $query->lockForUpdate();
        }

        $card = $query->first();

        if (! $card || ! Hash::check(trim($pin), $card->pin_hash)) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'Code ou PIN de carte cadeau incorrect.',
            ]);
        }

        if ($card->isExpired()) {
            if ($card->status !== GiftCard::STATUS_EXPIRED) {
                $card->forceFill(['status' => GiftCard::STATUS_EXPIRED])->save();
            }

            throw ValidationException::withMessages([
                'gift_card_code' => 'Cette carte cadeau est expirée.',
            ]);
        }

        if ($card->status !== GiftCard::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'Cette carte cadeau n’est pas utilisable actuellement.',
            ]);
        }

        if ($card->product?->is_rechargeable && $card->owner_user_id && (int) $card->owner_user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'Cette carte rechargeable est personnelle et appartient à un autre compte.',
            ]);
        }

        if ($card->availableBalance() <= 0) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'Le solde disponible de cette carte est insuffisant.',
            ]);
        }

        return $card;
    }

    public function issueFromPaidPurchase(GiftCardPurchase $purchase): GiftCard
    {
        return DB::transaction(function () use ($purchase) {
            $lockedPurchase = GiftCardPurchase::query()
                ->with('product')
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->gift_card_id) {
                return GiftCard::query()->with('product')->findOrFail($lockedPurchase->gift_card_id);
            }

            $product = $lockedPurchase->product;
            $pin = (string) random_int(1000, 9999);
            $code = $this->generateUniqueCode($product->family === 'rechargeable' ? 'OV-ACCESS' : 'OV-GIFT');
            $activatedAt = now();
            $expiresAt = $product->validity_months
                ? $activatedAt->copy()->addMonths((int) $product->validity_months)
                : $activatedAt->copy()->addDays((int) ($product->validity_days ?: 90));

            $ownerId = null;
            if ($product->is_rechargeable) {
                // Les cartes rechargeables sont personnelles : elles appartiennent au compte acheteur.
                $ownerId = $lockedPurchase->buyer_user_id;
            }

            $card = GiftCard::create([
                'gift_card_product_id' => $product->id,
                'purchaser_user_id' => $lockedPurchase->buyer_user_id,
                'owner_user_id' => $ownerId,
                'beneficiary_name' => $lockedPurchase->recipient_name,
                'beneficiary_email' => $lockedPurchase->recipient_email,
                'beneficiary_phone' => $lockedPurchase->recipient_phone,
                'code' => $code,
                'pin_hash' => Hash::make($pin),
                'pin_encrypted' => Crypt::encryptString($pin),
                'initial_balance' => (float) $product->initial_balance,
                'current_balance' => (float) $product->initial_balance,
                'reserved_balance' => 0,
                'total_recharged' => 0,
                'currency' => 'XOF',
                'status' => GiftCard::STATUS_ACTIVE,
                'activated_at' => $activatedAt,
                'expires_at' => $expiresAt,
                'meta' => [
                    'purchase_id' => $lockedPurchase->id,
                    'personal_message' => $lockedPurchase->personal_message,
                ],
            ]);

            GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'user_id' => $lockedPurchase->buyer_user_id,
                'type' => 'activation_credit',
                'amount' => (float) $product->initial_balance,
                'balance_before' => 0,
                'balance_after' => (float) $product->initial_balance,
                'reserved_before' => 0,
                'reserved_after' => 0,
                'reference' => 'GCA-' . Str::upper(Str::random(18)),
                'description' => 'Activation de la carte cadeau OVANIE.',
                'meta' => ['purchase_id' => $lockedPurchase->id],
            ]);

            $lockedPurchase->forceFill([
                'gift_card_id' => $card->id,
                'status' => 'paid',
                'paid_at' => $lockedPurchase->paid_at ?: now(),
            ])->save();

            return $card->fresh('product');
        }, 3);
    }

    public function holdForOrder(GiftCard $card, Order $order, User $user, float $amount): GiftCardTransaction
    {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant à réserver doit être supérieur à zéro.');
        }

        $lockedCard = GiftCard::query()->with('product')->whereKey($card->id)->lockForUpdate()->firstOrFail();

        $this->assertCardUsableForUser($lockedCard, $user);

        if ($lockedCard->availableBalance() + 0.001 < $amount) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'Le solde de la carte a changé. Réessayez avec le nouveau solde disponible.',
            ]);
        }

        $existing = GiftCardTransaction::query()
            ->where('gift_card_id', $lockedCard->id)
            ->where('order_id', $order->id)
            ->where('type', 'order_hold')
            ->first();

        if ($existing) {
            return $existing;
        }

        $balanceBefore = (float) $lockedCard->current_balance;
        $reservedBefore = (float) $lockedCard->reserved_balance;
        $lockedCard->reserved_balance = round($reservedBefore + $amount, 2);

        if (! $lockedCard->owner_user_id && $lockedCard->product?->is_rechargeable) {
            $lockedCard->owner_user_id = $user->id;
        }

        $lockedCard->save();

        return GiftCardTransaction::create([
            'gift_card_id' => $lockedCard->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'order_hold',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore,
            'reserved_before' => $reservedBefore,
            'reserved_after' => (float) $lockedCard->reserved_balance,
            'reference' => 'GCH-' . Str::upper(Str::random(18)),
            'description' => 'Réservation du solde pour la commande ' . $order->order_number,
        ]);
    }

    public function captureForOrder(Order $order): ?GiftCardTransaction
    {
        if (! $order->gift_card_id || (float) $order->gift_card_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $lockedOrder->gift_card_amount, 2);

            $existing = GiftCardTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->where('gift_card_id', $lockedOrder->gift_card_id)
                ->where('type', 'order_debit')
                ->first();

            if ($existing) {
                return $existing;
            }

            $card = GiftCard::query()->whereKey($lockedOrder->gift_card_id)->lockForUpdate()->firstOrFail();

            if ((float) $card->reserved_balance + 0.001 < $amount) {
                throw new \RuntimeException('La réservation de la carte cadeau est insuffisante pour cette commande.');
            }

            if ((float) $card->current_balance + 0.001 < $amount) {
                throw new \RuntimeException('Le solde de la carte cadeau est insuffisant pour finaliser la commande.');
            }

            $balanceBefore = (float) $card->current_balance;
            $reservedBefore = (float) $card->reserved_balance;
            $card->current_balance = round($balanceBefore - $amount, 2);
            $card->reserved_balance = max(0, round($reservedBefore - $amount, 2));
            $card->last_used_at = now();

            if ((float) $card->current_balance <= 0.001) {
                $card->status = GiftCard::STATUS_EXHAUSTED;
            }

            $card->save();

            return GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'user_id' => $lockedOrder->client_id,
                'order_id' => $lockedOrder->id,
                'type' => 'order_debit',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $card->current_balance,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (float) $card->reserved_balance,
                'reference' => 'GCD-' . Str::upper(Str::random(18)),
                'description' => 'Paiement de la commande ' . $lockedOrder->order_number,
            ]);
        }, 3);
    }

    public function releaseForOrder(Order $order, string $reason = 'Paiement externe non confirmé'): ?GiftCardTransaction
    {
        if (! $order->gift_card_id || (float) $order->gift_card_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (GiftCardTransaction::query()->where('order_id', $lockedOrder->id)->where('type', 'order_debit')->exists()) {
                return null;
            }

            if (GiftCardTransaction::query()->where('order_id', $lockedOrder->id)->where('type', 'hold_release')->exists()) {
                return null;
            }

            $card = GiftCard::query()->whereKey($lockedOrder->gift_card_id)->lockForUpdate()->first();
            if (! $card) {
                return null;
            }

            $amount = min((float) $lockedOrder->gift_card_amount, (float) $card->reserved_balance);
            if ($amount <= 0) {
                return null;
            }

            $balanceBefore = (float) $card->current_balance;
            $reservedBefore = (float) $card->reserved_balance;
            $card->reserved_balance = max(0, round($reservedBefore - $amount, 2));

            if ($card->status === GiftCard::STATUS_EXHAUSTED && (float) $card->current_balance > 0) {
                $card->status = GiftCard::STATUS_ACTIVE;
            }

            $card->save();

            return GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'user_id' => $lockedOrder->client_id,
                'order_id' => $lockedOrder->id,
                'type' => 'hold_release',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (float) $card->reserved_balance,
                'reference' => 'GCR-' . Str::upper(Str::random(18)),
                'description' => $reason,
            ]);
        }, 3);
    }

    public function refundOrder(Order $order, string $reason = 'Remboursement de commande'): ?GiftCardTransaction
    {
        if (! $order->gift_card_id || (float) $order->gift_card_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $debit = GiftCardTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->where('type', 'order_debit')
                ->first();

            if (! $debit) {
                return null;
            }

            if (GiftCardTransaction::query()->where('order_id', $lockedOrder->id)->where('type', 'refund_credit')->exists()) {
                return null;
            }

            $card = GiftCard::query()->whereKey($lockedOrder->gift_card_id)->lockForUpdate()->firstOrFail();
            $amount = abs((float) $debit->amount);
            $balanceBefore = (float) $card->current_balance;
            $reservedBefore = (float) $card->reserved_balance;
            $card->current_balance = round($balanceBefore + $amount, 2);

            if (! $card->isExpired() && $card->status === GiftCard::STATUS_EXHAUSTED) {
                $card->status = GiftCard::STATUS_ACTIVE;
            }

            $card->save();

            return GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'user_id' => $lockedOrder->client_id,
                'order_id' => $lockedOrder->id,
                'type' => 'refund_credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $card->current_balance,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedBefore,
                'reference' => 'GCF-' . Str::upper(Str::random(18)),
                'description' => $reason,
            ]);
        }, 3);
    }

    public function confirmPayDunyaPayment(Payment $payment, array $payload = []): array
    {
        return DB::transaction(function () use ($payment, $payload) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->isPaid()) {
                return ['state' => 'already_processed'];
            }

            $reportedAmount = data_get($payload, 'data.invoice.total_amount');
            if ($reportedAmount !== null
                && abs(PaymentService::normalizeAmount($reportedAmount) - PaymentService::normalizeAmount($lockedPayment->amount)) > 0.01) {
                return ['state' => 'amount_mismatch'];
            }

            $lockedPayment->forceFill([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'transaction_id' => data_get($payload, 'data.transaction_id')
                    ?? data_get($payload, 'transaction_id')
                    ?? $lockedPayment->transaction_id,
                'provider_payload' => array_merge($lockedPayment->provider_payload ?? [], [
                    'confirmation_payload' => $payload,
                    'confirmed_at' => now()->toDateTimeString(),
                ]),
            ])->save();

            if ($lockedPayment->type === 'gift_card_purchase') {
                $purchaseId = (int) data_get($lockedPayment->provider_payload, 'gift_card_purchase_id', 0);
                $purchase = GiftCardPurchase::query()->whereKey($purchaseId)->lockForUpdate()->firstOrFail();
                $purchase->forceFill([
                    'payment_id' => $lockedPayment->id,
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();
                $card = $this->issueFromPaidPurchase($purchase);

                return ['state' => 'processed', 'kind' => 'purchase', 'gift_card_id' => $card->id];
            }

            if ($lockedPayment->type === 'gift_card_recharge') {
                $rechargeId = (int) data_get($lockedPayment->provider_payload, 'gift_card_recharge_id', 0);
                $recharge = GiftCardRecharge::query()->whereKey($rechargeId)->lockForUpdate()->firstOrFail();
                $this->applyRecharge($recharge, $lockedPayment);

                return ['state' => 'processed', 'kind' => 'recharge', 'gift_card_id' => $recharge->gift_card_id];
            }

            return ['state' => 'unsupported'];
        }, 3);
    }

    public function failPayDunyaPayment(Payment $payment, string $status = 'failed'): void
    {
        DB::transaction(function () use ($payment, $status) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->isPaid()) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => $status === 'cancelled' ? Payment::STATUS_CANCELLED : Payment::STATUS_FAILED,
                'failed_at' => now(),
            ])->save();

            if ($lockedPayment->type === 'gift_card_purchase') {
                GiftCardPurchase::query()
                    ->whereKey((int) data_get($lockedPayment->provider_payload, 'gift_card_purchase_id', 0))
                    ->where('status', 'pending')
                    ->update(['status' => $status === 'cancelled' ? 'cancelled' : 'failed']);
            }

            if ($lockedPayment->type === 'gift_card_recharge') {
                GiftCardRecharge::query()
                    ->whereKey((int) data_get($lockedPayment->provider_payload, 'gift_card_recharge_id', 0))
                    ->where('status', 'pending')
                    ->update(['status' => $status === 'cancelled' ? 'cancelled' : 'failed']);
            }
        }, 3);
    }

    public function applyRecharge(GiftCardRecharge $recharge, Payment $payment): GiftCardTransaction
    {
        $lockedRecharge = GiftCardRecharge::query()->whereKey($recharge->id)->lockForUpdate()->firstOrFail();

        if ($lockedRecharge->status === 'paid') {
            return GiftCardTransaction::query()
                ->where('gift_card_id', $lockedRecharge->gift_card_id)
                ->where('type', 'recharge_credit')
                ->where('meta->recharge_id', $lockedRecharge->id)
                ->firstOrFail();
        }

        $card = GiftCard::query()->with('product')->whereKey($lockedRecharge->gift_card_id)->lockForUpdate()->firstOrFail();
        $product = $card->product;

        if (! $product?->is_rechargeable) {
            throw new \RuntimeException('Cette carte ne peut pas être rechargée.');
        }

        if ($card->isExpired()) {
            throw new \RuntimeException('Cette carte est expirée.');
        }

        $amount = round((float) $lockedRecharge->amount, 2);
        $newRechargeTotal = round((float) $card->total_recharged + $amount, 2);

        if ($product->max_total_recharge !== null
            && $newRechargeTotal > (float) $product->max_total_recharge + 0.001) {
            throw new \RuntimeException('Le plafond cumulé de recharge de cette carte serait dépassé.');
        }

        $balanceBefore = (float) $card->current_balance;
        $reservedBefore = (float) $card->reserved_balance;
        $card->current_balance = round($balanceBefore + $amount, 2);
        $card->total_recharged = $newRechargeTotal;
        $card->status = GiftCard::STATUS_ACTIVE;
        $card->save();

        $lockedRecharge->forceFill([
            'payment_id' => $payment->id,
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        return GiftCardTransaction::create([
            'gift_card_id' => $card->id,
            'user_id' => $lockedRecharge->user_id,
            'type' => 'recharge_credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $card->current_balance,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedBefore,
            'reference' => 'GCT-' . Str::upper(Str::random(18)),
            'description' => 'Recharge de carte OVANIE.',
            'meta' => ['recharge_id' => $lockedRecharge->id, 'payment_id' => $payment->id],
        ]);
    }

    public function assertRechargeAllowed(GiftCard $card, User $user, float $amount): void
    {
        $card->loadMissing('product');

        if (! $card->product?->is_rechargeable) {
            throw ValidationException::withMessages(['amount' => 'Cette carte n’est pas rechargeable.']);
        }

        if ((int) $card->owner_user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['amount' => 'Vous ne pouvez pas recharger cette carte.']);
        }

        if ($card->isExpired() || $card->status === GiftCard::STATUS_BLOCKED) {
            throw ValidationException::withMessages(['amount' => 'Cette carte n’est pas rechargeable actuellement.']);
        }

        if ($amount < 5000) {
            throw ValidationException::withMessages(['amount' => 'Le montant minimum de recharge est de 5 000 FCFA.']);
        }

        $max = $card->product->max_total_recharge;
        if ($max !== null && ((float) $card->total_recharged + $amount) > (float) $max + 0.001) {
            $remaining = max(0, (float) $max - (float) $card->total_recharged);
            throw ValidationException::withMessages([
                'amount' => 'Le plafond de recharge serait dépassé. Recharge maximale restante : ' . number_format($remaining, 0, ',', ' ') . ' FCFA.',
            ]);
        }
    }

    private function assertCardUsableForUser(GiftCard $card, User $user): void
    {
        if ($card->isExpired()) {
            $card->status = GiftCard::STATUS_EXPIRED;
            $card->save();
            throw ValidationException::withMessages(['gift_card_code' => 'Cette carte cadeau est expirée.']);
        }

        if ($card->status !== GiftCard::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['gift_card_code' => 'Cette carte cadeau n’est pas active.']);
        }

        if ($card->product?->is_rechargeable && $card->owner_user_id && (int) $card->owner_user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['gift_card_code' => 'Cette carte rechargeable est personnelle.']);
        }
    }

    private function generateUniqueCode(string $prefix): string
    {
        do {
            $code = $prefix . '-' . Str::upper(Str::random(5)) . '-' . Str::upper(Str::random(5)) . '-' . Str::upper(Str::random(5));
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }
}
