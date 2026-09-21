<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PayDunyaOrderReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Retour PayDunya PUBLIC.
 *
 * Le retour d'un prestataire de paiement ne doit jamais dépendre de la session
 * web OVANIE : l'application mobile n'en possède pas, et une session navigateur
 * peut être absente au retour d'un site tiers. Le token PayDunya sert à retrouver
 * le paiement puis son statut est vérifié directement chez PayDunya.
 */
class PayDunyaReturnController extends Controller
{
    public function success(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation
    ): View|RedirectResponse|Response {
        return $this->handle($request, $reconciliation, false, false);
    }

    public function cancel(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation
    ): View|RedirectResponse|Response {
        return $this->handle($request, $reconciliation, true, false);
    }

    public function successMobile(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation
    ): View|RedirectResponse|Response {
        return $this->handle($request, $reconciliation, false, true);
    }

    public function cancelMobile(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation
    ): View|RedirectResponse|Response {
        return $this->handle($request, $reconciliation, true, true);
    }

    private function handle(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation,
        bool $cancelRoute,
        bool $mobileReturn = false
    ): View|RedirectResponse|Response {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return response()->view('payment.paydunya-return', [
                'state' => 'invalid',
                'order' => null,
                'message' => 'Le retour de paiement ne contient pas de référence vérifiable.',
                'mobileReturn' => $mobileReturn,
                'appReturnUrl' => null,
            ], 422);
        }

        $payment = Payment::query()
            ->where('method', 'paydunya')
            ->where('reference', $token)
            ->latest('id')
            ->first();

        if (! $payment) {
            return response()->view('payment.paydunya-return', [
                'state' => 'invalid',
                'order' => null,
                'message' => 'Ce paiement OVANIE est introuvable ou n’est plus valide.',
                'mobileReturn' => $mobileReturn,
                'appReturnUrl' => null,
            ], 404);
        }

        $order = Order::query()->find($payment->order_id);
        if (! $order) {
            return response()->view('payment.paydunya-return', [
                'state' => 'invalid',
                'order' => null,
                'message' => 'La commande associée à ce paiement est introuvable.',
                'mobileReturn' => $mobileReturn,
                'appReturnUrl' => null,
            ], 404);
        }

        $state = $reconciliation->reconcile($order, $token);
        $order = $order->fresh();
        $confirmed = in_array((string) $order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)
            || $state === 'completed';

        if (! $mobileReturn
            && $confirmed
            && $request->user()
            && (int) $request->user()->id === (int) $order->client_id) {
            $request->session()->forget('checkout_pending_order_id');
            return redirect()->route('order.success', $order->id);
        }

        $viewState = $confirmed
            ? 'completed'
            : (in_array($state, ['cancelled', 'canceled', 'failed'], true) || $cancelRoute ? 'cancelled' : 'pending');

        $message = match ($viewState) {
            'completed' => 'Votre paiement a été confirmé. La commande est maintenant prise en charge par OVANIE.',
            'cancelled' => 'Le paiement n’a pas été confirmé. Aucun paiement validé n’est enregistré pour cette tentative.',
            default => 'Votre paiement est encore en cours de vérification. OVANIE mettra automatiquement la commande à jour dès confirmation.',
        };

        $appReturnUrl = null;
        if ($mobileReturn && $order) {
            $query = http_build_query([
                'order_id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'state' => $viewState,
            ]);
            $appReturnUrl = 'ovanie://payment/return?' . $query;
        }

        return view('payment.paydunya-return', [
            'state' => $viewState,
            'order' => $order,
            'message' => $message,
            'mobileReturn' => $mobileReturn,
            'appReturnUrl' => $appReturnUrl,
        ]);
    }
}
