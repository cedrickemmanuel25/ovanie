<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use App\Models\GiftCardRecharge;
use App\Models\Payment;
use App\Services\GiftCardService;
use App\Services\LoyaltyService;
use App\Services\PayDunyaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientGiftCardController extends Controller
{
    public function index(Request $request, LoyaltyService $loyaltyService): View
    {
        $user = $request->user();

        $cards = GiftCard::query()
            ->with('product')
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id)
                    ->orWhere('purchaser_user_id', $user->id)
                    ->when($user->email, fn ($q) => $q->orWhere('beneficiary_email', $user->email));
            })
            ->latest()
            ->get();

        $points = (int) ($user->loyalty_points ?? 0);

        return view('client.vouchers', [
            'client' => $user,
            'cards' => $cards,
            'points' => $points,
            'availableDiscount' => $loyaltyService->calculateDiscount($points),
            'loyaltyTransactions' => $user->loyaltyTransactions()->latest()->take(20)->get(),
            'recentOrders' => $user->orders()->latest()->take(4)->get(),
        ]);
    }

    public function show(Request $request, GiftCard $giftCard): View
    {
        $this->authorizeCard($request, $giftCard);

        $giftCard->load('product');
        $transactions = $giftCard->transactions()->latest()->paginate(30);

        return view('client.gift-card-show', compact('giftCard', 'transactions'));
    }

    public function rechargeForm(Request $request, GiftCard $giftCard): View
    {
        $this->authorizeOwner($request, $giftCard);
        $giftCard->load('product');

        abort_unless($giftCard->product?->is_rechargeable, 404);

        return view('client.gift-card-recharge', compact('giftCard'));
    }

    public function recharge(
        Request $request,
        GiftCard $giftCard,
        GiftCardService $giftCards,
        PayDunyaService $paydunya
    ): RedirectResponse {
        $this->authorizeOwner($request, $giftCard);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:5000', 'max:10000000'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $giftCards->assertRechargeAllowed($giftCard, $request->user(), $amount);

        [$recharge, $payment] = DB::transaction(function () use ($giftCard, $request, $amount) {
            $recharge = GiftCardRecharge::create([
                'gift_card_id' => $giftCard->id,
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $payment = Payment::create([
                'order_id' => null,
                'method' => 'paydunya',
                'type' => 'gift_card_recharge',
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $request->user()->id,
                'reference' => 'GIFTLOAD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8)),
                'provider_payload' => [
                    'gift_card_recharge_id' => $recharge->id,
                    'gift_card_id' => $giftCard->id,
                    'server_price' => $amount,
                ],
            ]);

            $recharge->update(['payment_id' => $payment->id]);

            return [$recharge, $payment];
        }, 3);

        try {
            $invoice = $paydunya->createOrderInvoice([
                'item_name' => 'Recharge carte OVANIE',
                'description' => 'Recharge de la carte ' . $giftCard->code,
                'amount' => $amount,
                'return_url' => route('gift-cards.payment.return'),
                'cancel_url' => route('gift-cards.payment.cancel'),
            ]);

            if (! $invoice->create()) {
                throw new \RuntimeException($invoice->response_text ?? 'Impossible d’initialiser la recharge.');
            }

            $token = $invoice->token
                ?? $invoice->invoice_token
                ?? ($invoice->response_array['token'] ?? null)
                ?? ($invoice->response['token'] ?? null)
                ?? $payment->reference;

            $payment->update(['reference' => $token]);
            $recharge->update(['provider_token' => $token]);

            return redirect()->away($invoice->getInvoiceUrl());
        } catch (\Throwable $e) {
            $payment->forceFill(['status' => Payment::STATUS_FAILED, 'failed_at' => now()])->save();
            $recharge->update(['status' => 'failed']);

            report($e);

            return back()->with('error', $e->getMessage() ?: 'La recharge n’a pas pu être lancée.');
        }
    }

    private function authorizeCard(Request $request, GiftCard $giftCard): void
    {
        $user = $request->user();
        $allowed = (int) $giftCard->owner_user_id === (int) $user->id
            || (int) $giftCard->purchaser_user_id === (int) $user->id
            || ($giftCard->beneficiary_email && strcasecmp((string) $giftCard->beneficiary_email, (string) $user->email) === 0);

        abort_unless($allowed, 403);
    }

    private function authorizeOwner(Request $request, GiftCard $giftCard): void
    {
        abort_unless((int) $giftCard->owner_user_id === (int) $request->user()->id, 403);
    }
}
