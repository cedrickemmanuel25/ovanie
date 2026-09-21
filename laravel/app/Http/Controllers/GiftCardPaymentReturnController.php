<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use App\Models\Payment;
use App\Services\GiftCardService;
use App\Services\PayDunyaService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GiftCardPaymentReturnController extends Controller
{
    public function success(Request $request, PayDunyaService $paydunya, GiftCardService $giftCards): View
    {
        return $this->handle($request, $paydunya, $giftCards, false);
    }

    public function cancel(Request $request, PayDunyaService $paydunya, GiftCardService $giftCards): View
    {
        return $this->handle($request, $paydunya, $giftCards, true);
    }

    private function handle(
        Request $request,
        PayDunyaService $paydunya,
        GiftCardService $giftCards,
        bool $cancelRoute
    ): View {
        $token = trim((string) $request->query('token', ''));
        $payment = $token !== ''
            ? Payment::query()
                ->where('reference', $token)
                ->whereIn('type', ['gift_card_purchase', 'gift_card_recharge'])
                ->latest('id')
                ->first()
            : null;

        if (! $payment) {
            return view('gift-cards.payment-return', [
                'state' => 'invalid',
                'message' => 'La référence du paiement est introuvable.',
                'giftCard' => null,
                'canReveal' => false,
            ]);
        }

        if (! $payment->isPaid()) {
            $confirmation = $paydunya->confirmInvoice($token);
            $status = PaymentService::normalizeStatus($confirmation['status'] ?? 'pending');

            if (! empty($confirmation['verified'])) {
                $payload = [
                    'data' => [
                        'status' => 'completed',
                        'invoice' => [
                            'total_amount' => $confirmation['amount'] ?? $payment->amount,
                            'currency' => 'XOF',
                            'token' => $token,
                        ],
                    ],
                ];
                $giftCards->confirmPayDunyaPayment($payment, $payload);
            } elseif (PaymentService::isFailedStatus($status) || $cancelRoute) {
                $giftCards->failPayDunyaPayment($payment, $cancelRoute ? 'cancelled' : $status);
            }
        }

        $payment->refresh();
        $giftCardId = (int) data_get($payment->provider_payload, 'gift_card_id', 0);

        if ($payment->type === 'gift_card_purchase') {
            $purchaseId = (int) data_get($payment->provider_payload, 'gift_card_purchase_id', 0);
            $giftCardId = (int) \App\Models\GiftCardPurchase::query()->whereKey($purchaseId)->value('gift_card_id');
        } elseif ($payment->type === 'gift_card_recharge') {
            $rechargeId = (int) data_get($payment->provider_payload, 'gift_card_recharge_id', 0);
            $giftCardId = (int) \App\Models\GiftCardRecharge::query()->whereKey($rechargeId)->value('gift_card_id');
        }

        $giftCard = $giftCardId > 0 ? GiftCard::with('product')->find($giftCardId) : null;
        $currentUserId = (int) ($request->user()?->id ?? 0);
        $canReveal = $giftCard && $currentUserId > 0 && (
            (int) $giftCard->purchaser_user_id === $currentUserId
            || (int) $giftCard->owner_user_id === $currentUserId
        );
        $state = $payment->isPaid() ? 'completed' : ($payment->isFailed() || $payment->isCancelled() ? 'cancelled' : 'pending');
        $message = match ($state) {
            'completed' => $payment->type === 'gift_card_recharge'
                ? 'Recharge confirmée. Le nouveau solde de votre carte est disponible.'
                : 'Paiement confirmé. Votre carte cadeau OVANIE est active.',
            'cancelled' => 'Le paiement n’a pas été confirmé.',
            default => 'Le paiement est encore en cours de vérification.',
        };

        return view('gift-cards.payment-return', compact('state', 'message', 'giftCard', 'canReveal'));
    }
}
