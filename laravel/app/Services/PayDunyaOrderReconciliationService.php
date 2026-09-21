<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Réconciliation PayDunya indépendante de la session navigateur.
 *
 * Source de vérité : le token PayDunya enregistré dans payments.reference,
 * puis la vérification serveur-à-serveur CheckoutInvoice::confirm(token).
 * Ce service est utilisé par :
 * - le retour public du navigateur ;
 * - le checkout web historique ;
 * - le polling authentifié de l'application mobile.
 */
class PayDunyaOrderReconciliationService
{
    public function __construct(
        private readonly PayDunyaService $paydunya,
        private readonly CheckoutCartFinalizerService $cartFinalizer,
        private readonly VendorOrderReleaseService $vendorRelease
    ) {
    }

    public function reconcile(Order $order, string $returnToken = ''): string
    {
        $order = $order->fresh();

        if (! $order || (string) $order->payment_method !== 'paydunya') {
            return 'not_applicable';
        }

        if (in_array((string) $order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            $this->repairAfterConfirmedPayment($order);

            // Le paiement peut avoir été confirmé par un webhook ou une requête
            // concurrente avant le retour navigateur. Dans ce cas, garantir que
            // la notification client n'a pas été perdue.
            if ((string) $order->payment_method === 'paydunya') {
                app(\App\Services\ClientPaymentNotificationService::class)
                    ->paymentConfirmed($order->refresh());
            }

            return 'completed';
        }

        $paymentQuery = Payment::query()
            ->where('order_id', $order->id)
            ->where('method', 'paydunya');

        $returnToken = trim($returnToken);

        // IMPORTANT : lorsqu'un token est fourni par le navigateur, il doit
        // appartenir exactement à cette commande. Ne jamais retomber sur le
        // dernier paiement d'une autre tentative si le token ne correspond pas.
        if ($returnToken !== '') {
            $payment = (clone $paymentQuery)
                ->where('reference', $returnToken)
                ->latest('id')
                ->first();

            if (! $payment) {
                Log::warning('Retour PayDunya : token non associé à la commande.', [
                    'order_id' => $order->id,
                    'token_prefix' => substr($returnToken, 0, 12),
                ]);
                return 'token_mismatch';
            }
        } else {
            $payment = $paymentQuery->latest('id')->first();
        }

        if (! $payment) {
            return 'payment_missing';
        }

        $token = $returnToken !== ''
            ? $returnToken
            : trim((string) $payment->reference);

        if ($token === '') {
            return 'token_missing';
        }

        $confirmation = $this->paydunya->confirmInvoice($token);
        $providerStatus = strtolower(trim((string) ($confirmation['status'] ?? 'unknown')));

        if (($confirmation['verified'] ?? false) && $providerStatus === 'completed') {
            $invoice = ['token' => $token];
            $confirmedAmount = $confirmation['amount'] ?? null;

            if (is_numeric($confirmedAmount) && (float) $confirmedAmount > 0) {
                $invoice['total_amount'] = (float) $confirmedAmount;
            }

            $payload = [
                'data' => [
                    'status' => 'completed',
                    'transaction_id' => $token,
                    'invoice' => $invoice,
                ],
                'source' => 'paydunya_status_reconciliation',
            ];

            $processed = app(PaymentController::class)
                ->confirmPaydunyaPaymentFromReturn($payment, $payload);

            $freshOrder = $order->fresh(['items']);
            if ($processed || in_array((string) $freshOrder->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
                $this->repairAfterConfirmedPayment($freshOrder);

                // Idempotent : si confirmOrderPayment() a déjà publié la
                // notification, le service la détecte et n'en crée pas une seconde.
                app(\App\Services\ClientPaymentNotificationService::class)
                    ->paymentConfirmed($freshOrder->refresh());

                return 'completed';
            }

            return 'processing';
        }

        if (in_array($providerStatus, ['cancelled', 'canceled', 'failed'], true)) {
            app(PaymentController::class)->failPaydunyaPaymentFromReturn(
                $payment,
                $providerStatus,
                [
                    'data' => [
                        'status' => $providerStatus,
                        'transaction_id' => $token,
                        'invoice' => ['token' => $token],
                    ],
                    'source' => 'paydunya_status_reconciliation',
                ]
            );

            return $providerStatus === 'canceled' ? 'cancelled' : $providerStatus;
        }

        return $providerStatus !== '' ? $providerStatus : 'unknown';
    }

    private function repairAfterConfirmedPayment(Order $order): void
    {
        // Idempotent : répare également les paiements confirmés avant que le
        // panier / visibilité vendeur ait pu être finalisé.
        $this->cartFinalizer->finalize($order->refresh());
        $this->vendorRelease->release($order->refresh());
    }
}
