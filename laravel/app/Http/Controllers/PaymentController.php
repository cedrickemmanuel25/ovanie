<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Commission;
use App\Models\AppelOffre;
use App\Models\BusinessContactAccess;
use App\Models\Devis;
use App\Models\Payment;
use App\Models\OrderReceptionFormItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Services\PaymentService;
use App\Services\OvanieNotificationDispatcher;
use App\Services\VendorOrderReleaseService;
use App\Services\OrderStockReservationService;
use App\Services\CheckoutCartFinalizerService;
use App\Services\LoyaltyService;
use App\Services\GiftCardService;
use App\Services\VendorPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected VendorPayoutService $vendorPayoutService,
        protected VendorOrderReleaseService $vendorOrderReleaseService,
        protected OrderStockReservationService $stockReservations,
        protected CheckoutCartFinalizerService $cartFinalizer
    ) {
    }

    public function paydunyaWebhook(Request $request)
    {
        if (! $this->hasValidPaydunyaSignature($request)) {
            Log::warning('PayDunya webhook signature invalide.', [
                'ip' => $request->ip(),
                'reference' => $request->input('data.invoice.token') ?? $request->input('data.token') ?? $request->input('token') ?? $request->input('reference'),
            ]);

            return response()->json(['error' => 'Signature invalide'], 401);
        }

        $token = $request->input('data.invoice.token') ?? $request->input('data.token') ?? $request->input('token') ?? $request->input('reference');

        if (! $token) {
            return response()->json(['error' => 'Token manquant'], 422);
        }

        $status = PaymentService::normalizeStatus(
            $request->input('data.status')
            ?? $request->input('status')
            ?? $request->input('data.transaction_status')
            ?? PaymentService::STATUS_PENDING
        );

        $payment = Payment::where('reference', $token)
            ->orWhere('transaction_id', $token)
            ->first();

        if ($payment) {
            if ($payment->type === 'business_contact') {
                if (PaymentService::isPaidStatus($status)) {
                    return $this->confirmBusinessContactPayment($payment, $request->all());
                }

                return response()->json(['success' => true, 'message' => 'Statut Business non confirmant']);
            }

            if (in_array($payment->type, ['gift_card_purchase', 'gift_card_recharge'], true)) {
                /** @var GiftCardService $giftCards */
                $giftCards = app(GiftCardService::class);

                if (PaymentService::isPaidStatus($status)) {
                    $result = $giftCards->confirmPayDunyaPayment($payment, $request->all());

                    if (($result['state'] ?? null) === 'amount_mismatch') {
                        Log::critical('Montant IPN PayDunya incohérent pour une carte cadeau.', [
                            'payment_id' => $payment->id,
                            'expected_amount' => $payment->amount,
                            'reported_amount' => data_get($request->all(), 'data.invoice.total_amount'),
                        ]);

                        return response()->json(['error' => 'Montant de paiement incohérent'], 422);
                    }

                    if (($result['state'] ?? null) === 'unsupported') {
                        return response()->json(['error' => 'Type de paiement carte cadeau non pris en charge'], 422);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => ($result['state'] ?? null) === 'already_processed'
                            ? 'Paiement de carte cadeau déjà traité.'
                            : 'Paiement de carte cadeau confirmé.',
                    ]);
                }

                if (PaymentService::isFailedStatus($status)) {
                    $giftCards->failPayDunyaPayment($payment, $status);

                    return response()->json([
                        'success' => true,
                        'message' => 'Échec du paiement de carte cadeau enregistré.',
                    ]);
                }

                return response()->json(['success' => true, 'message' => 'Paiement de carte cadeau en attente']);
            }

            if (! $this->isOrderPaymentType($payment->type)) {
                return response()->json(['error' => 'Type de paiement non pris en charge'], 422);
            }

            if (PaymentService::isPaidStatus($status)) {
                return $this->confirmOrderPayment($payment, $request->all());
            }

            if (PaymentService::isFailedStatus($status)) {
                return $this->failOrderPayment($payment, $status, $request->all());
            }

            return response()->json(['success' => true, 'message' => 'Statut de paiement en attente']);
        }

        $product = Product::where('boost_payment_reference', $token)->first();

        if ($product && PaymentService::isPaidStatus($status)) {
            return $this->confirmBoostPayment($product);
        }

        return response()->json(['error' => 'Transaction introuvable'], 404);
    }

    private function confirmBusinessContactPayment(Payment $payment, array $payload)
    {
        $reportedAmount = data_get($payload, 'data.invoice.total_amount');
        $currency = strtoupper((string) (
            data_get($payload, 'data.invoice.currency')
            ?? data_get($payload, 'data.currency')
            ?? ''
        ));

        if ($reportedAmount === null || ! in_array($currency, ['XOF', 'FCFA'], true)) {
            return response()->json(['error' => 'Données financières invalides'], 422);
        }

        $processed = DB::transaction(function () use ($payment, $payload, $reportedAmount, $currency): string {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->where('type', 'business_contact')
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return 'invalid';
            }

            $context = $lockedPayment->provider_payload ?? [];
            $expectedAmount = PaymentService::normalizeAmount($context['server_price'] ?? $lockedPayment->amount);
            $requestId = filter_var($context['business_request_id'] ?? null, FILTER_VALIDATE_INT);
            $requestType = $context['business_request_type'] ?? null;

            if (! $requestId
                || abs(PaymentService::normalizeAmount($reportedAmount) - $expectedAmount) > 0.01
                || abs(PaymentService::normalizeAmount($lockedPayment->amount) - $expectedAmount) > 0.01
                || ! $this->businessRequestExists((string) $requestType, (int) $requestId)) {
                return 'invalid';
            }

            if ($lockedPayment->isPaid()) {
                $this->grantBusinessContactAccess($lockedPayment, $context);

                return 'already_processed';
            }

            $lockedPayment->forceFill([
                'status' => Payment::STATUS_PAID,
                'transaction_id' => data_get($payload, 'data.transaction_id')
                    ?? data_get($payload, 'transaction_id'),
                'paid_at' => now(),
                'provider_payload' => array_merge($context, [
                    'confirmation' => [
                        'status' => data_get($payload, 'data.status') ?? data_get($payload, 'status'),
                        'transaction_id' => data_get($payload, 'data.transaction_id')
                            ?? data_get($payload, 'transaction_id'),
                        'reported_amount' => PaymentService::normalizeAmount($reportedAmount),
                        'currency' => $currency,
                        'confirmed_at' => now()->toDateTimeString(),
                    ],
                ]),
            ])->save();

            $this->grantBusinessContactAccess($lockedPayment, $context);

            return 'processed';
        }, 3);

        return match ($processed) {
            'processed' => response()->json(['success' => true]),
            'already_processed' => response()->json(['success' => true, 'message' => 'Déjà traité']),
            default => response()->json(['error' => 'Paiement Business incohérent'], 422),
        };
    }

    private function grantBusinessContactAccess(Payment $payment, array $context): void
    {
        BusinessContactAccess::query()->firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'user_id' => $payment->user_id,
                'company_id' => $context['company_id'] ?? null,
                'business_request_type' => $context['business_request_type'],
                'business_request_id' => $context['business_request_id'],
                'granted_at' => now(),
            ]
        );
    }

    private function businessRequestExists(string $type, int $id): bool
    {
        return match ($type) {
            'devis' => Devis::query()->whereKey($id)->exists(),
            'appel_offre' => AppelOffre::query()->whereKey($id)->exists(),
            default => false,
        };
    }

    private function isOrderPaymentType(?string $type): bool
    {
        return $type === null || in_array($type, [
            'order_payment',
            'order_line_payment',
            'order_lines_payment',
        ], true);
    }

    /**
     * Confirmation locale réservée au développement.
     *
     * PayDunya ne peut pas appeler une URL 127.0.0.1 depuis Internet. Ce point
     * d'entrée permet donc de tester le parcours complet (paiement, panier,
     * visibilité vendeur et logistique) sans être exposé en production.
     */
    public function simulatePaydunyaSuccess(Request $request, Order $order)
    {
        abort_unless(
            app()->environment('local')
                && strtolower((string) config('paydunya.mode', 'test')) === 'test',
            404
        );

        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'online_operator' => ['required', 'in:wave,orange,mtn,moov'],
            'payment_phone' => ['required', 'string', 'max:30'],
        ]);

        if (in_array($order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            return redirect()->route('order.success', $order)
                ->with('success', 'Le paiement est déjà confirmé.');
        }

        try {
            $payment = DB::transaction(function () use ($order, $request, $validated) {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->payment_method !== 'paydunya') {
                    throw new \RuntimeException('Cette commande n’utilise pas le paiement en ligne.');
                }

                if (in_array($lockedOrder->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
                    return Payment::query()->where('order_id', $lockedOrder->id)->latest('id')->first();
                }

                $this->stockReservations->reserve($lockedOrder);

                $payment = Payment::create([
                    'order_id' => $lockedOrder->id,
                    'method' => 'paydunya',
                    'type' => 'order_payment',
                    'amount' => (float) $lockedOrder->total_amount,
                    'status' => Payment::STATUS_PENDING,
                    'user_id' => $request->user()->id,
                    'operator' => $validated['online_operator'],
                    'mobile_number' => trim((string) $validated['payment_phone']),
                    'reference' => 'LOCAL-' . $lockedOrder->order_number . '-' . strtoupper(Str::random(6)),
                    'provider_payload' => [
                        'local_simulation' => true,
                        'simulated_at' => now()->toDateTimeString(),
                    ],
                ]);

                $payment->setRelation('order', $lockedOrder);
                $this->paymentService->markAsPaid(
                    $payment,
                    'LOCAL-' . strtoupper(Str::random(12)),
                    ['local_simulation' => true]
                );

                $lockedOrder->items()->update(['is_paid' => true]);
                Commission::where('order_id', $lockedOrder->id)->update(['status' => 'payé']);
                $this->stockReservations->commit($lockedOrder->refresh());

                app(\App\Services\OrderWorkflowService::class)->recordHistory(
                    $lockedOrder,
                    null,
                    'payment',
                    'pending',
                    'paid',
                    [
                        'actor_type' => 'system',
                        'label' => 'Paiement de test confirmé',
                        'message' => 'Paiement confirmé localement pour tester le parcours vendeur et logistique.',
                    ]
                );

                return $payment->refresh();
            }, 3);
        } catch (\Throwable $e) {
            return redirect()->route('checkout.payment.show', $order)
                ->with('error', $e->getMessage() ?: 'La simulation du paiement a échoué.');
        }

        $freshOrder = $order->fresh(['items']);
        $this->cartFinalizer->finalize($freshOrder);
        $this->vendorOrderReleaseService->release($freshOrder->refresh());

        if ($this->paymentService->shouldCreateVendorPayout($payment)) {
            $this->vendorPayoutService->generateForOrder($freshOrder->refresh(), 'local_test_payout');
        }

        // La simulation locale doit déclencher exactement la même confirmation
        // client que le webhook / retour PayDunya réel.
        app(\App\Services\ClientPaymentNotificationService::class)
            ->paymentConfirmed($freshOrder->refresh());

        $request->session()->forget('checkout_pending_order_id');

        return redirect()->route('order.success', $freshOrder)
            ->with('success', 'Paiement de test confirmé. La commande est maintenant visible par les vendeurs concernés.');
    }

    /**
     * Réutilise le même workflow sécurisé que le webhook lorsque le client
     * revient de PayDunya. Le retour navigateur est particulièrement utile en
     * local, où le webhook ne peut pas atteindre 127.0.0.1.
     */
    public function confirmPaydunyaPaymentFromReturn(Payment $payment, array $payload = []): bool
    {
        $response = $this->confirmOrderPayment($payment, $payload);

        if (! method_exists($response, 'getStatusCode')
            || ! method_exists($response, 'getData')) {
            return false;
        }

        $data = $response->getData(true);

        return $response->getStatusCode() < 400
            && (bool) data_get($data, 'success', false);
    }

    /**
     * Même principe que confirmPaydunyaPaymentFromReturn(), mais pour un statut
     * final annulé/échoué vérifié directement auprès de PayDunya.
     */
    public function failPaydunyaPaymentFromReturn(
        Payment $payment,
        string $status,
        array $payload = []
    ): bool {
        $normalized = PaymentService::normalizeStatus($status);
        if (! PaymentService::isFailedStatus($normalized)) {
            return false;
        }

        $response = $this->failOrderPayment($payment, $normalized, $payload);
        if (! method_exists($response, 'getStatusCode') || ! method_exists($response, 'getData')) {
            return false;
        }

        $data = $response->getData(true);
        return $response->getStatusCode() < 400
            && (bool) data_get($data, 'success', false);
    }

    private function confirmOrderPayment(Payment $payment, array $payload = [])
    {
        $result = DB::transaction(function () use ($payment, $payload) {
            // Ordre de verrouillage identique au workflow d'annulation client :
            // commande d'abord, paiements ensuite. Cela évite un deadlock entre
            // un webhook PayDunya et une annulation exécutés au même instant.
            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return ['state' => 'order_missing'];
            }

            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return ['state' => 'payment_missing'];
            }

            if ($lockedPayment->isPaid()) {
                return ['state' => 'already_processed'];
            }

            $order->load('items.product.shop');
            $lockedPayment->setRelation('order', $order);

            $reportedAmount = data_get($payload, 'data.invoice.total_amount');
            if ($reportedAmount !== null
                && abs(PaymentService::normalizeAmount($reportedAmount) - PaymentService::normalizeAmount($lockedPayment->amount)) > 0.01) {
                Log::critical('Montant IPN PayDunya incohérent.', [
                    'payment_id' => $lockedPayment->id,
                    'expected_amount' => $lockedPayment->amount,
                    'reported_amount' => $reportedAmount,
                ]);

                return ['state' => 'amount_mismatch'];
            }

            if ($order->status === 'cancelled') {
                $providerPayload = array_merge($lockedPayment->provider_payload ?? [], [
                    'confirmation_payload' => $payload,
                    'confirmed_at' => now()->toDateTimeString(),
                    'received_after_order_cancellation' => true,
                    'refund_review_required' => true,
                ]);

                // Le prestataire confirme un encaissement réel, mais l'annulation
                // commerciale reste définitive. Le paiement est conservé en escrow
                // pour rapprochement/remboursement sans aucun effet sur le stock,
                // la livraison, la visibilité vendeur ou les reversements.
                $lockedPayment->forceFill([
                    'status' => Payment::STATUS_ESCROW_HELD,
                    'transaction_id' => data_get($payload, 'data.transaction_id')
                        ?? data_get($payload, 'transaction_id')
                        ?? $lockedPayment->transaction_id,
                    'provider_payload' => $providerPayload,
                    'paid_at' => $lockedPayment->paid_at ?: now(),
                ])->save();

                return [
                    'state' => 'confirmed',
                    'kind' => 'cancelled_order_payment_review',
                    'order_id' => $order->id,
                    'payment_id' => $lockedPayment->id,
                    'can_release' => false,
                ];
            }

            if ($lockedPayment->isCancelled()
                && data_get($lockedPayment->provider_payload, 'cancel_reason') === 'superseded_by_confirmed_reception_payment') {
                $providerPayload = array_merge($lockedPayment->provider_payload ?? [], [
                    'confirmation_payload' => $payload,
                    'confirmed_at' => now()->toDateTimeString(),
                    'overpayment_review_required' => true,
                ]);

                // L'argent a réellement été reçu par le prestataire même si cette
                // facture locale avait été supplantée. On l'enregistre dans le
                // ledger pour ne jamais masquer un trop-perçu et on demande une
                // régularisation, sans repayer les lignes de commande.
                $lockedPayment->forceFill([
                    'status' => Payment::STATUS_ESCROW_HELD,
                    'transaction_id' => data_get($payload, 'data.transaction_id')
                        ?? data_get($payload, 'transaction_id')
                        ?? $lockedPayment->transaction_id,
                    'provider_payload' => $providerPayload,
                    'paid_at' => $lockedPayment->paid_at ?: now(),
                ])->save();

                return [
                    'state' => 'confirmed',
                    'kind' => 'overpayment_review',
                    'order_id' => $order->id,
                    'payment_id' => $lockedPayment->id,
                    'can_release' => false,
                ];
            }

            if (in_array($lockedPayment->type, ['order_line_payment', 'order_lines_payment'], true)) {
                return $this->confirmReceptionPayment($lockedPayment, $order, $payload);
            }

            if ($lockedPayment->method === PaymentService::METHOD_CASH_ON_DELIVERY
                || $lockedPayment->method === 'cash_on_delivery_commission') {
                $this->paymentService->markAsPaid(
                    $lockedPayment,
                    data_get($payload, 'data.transaction_id') ?? data_get($payload, 'transaction_id'),
                    $payload
                );

                $order->update([
                    'payment_status' => 'commission_paid',
                    'status' => 'confirmed',
                ]);

                Commission::where('order_id', $order->id)->update(['status' => 'payé']);
                $this->stockReservations->commit($order->refresh());
                $this->cartFinalizer->finalize($order->refresh());
                $this->vendorOrderReleaseService->release($order->refresh());

                app(\App\Services\OrderWorkflowService::class)->recordHistory($order, null, 'payment', null, 'commission_paid', [
                    'actor_type' => 'system',
                    'label' => 'Commission OVANIE payée',
                    'message' => 'Commission confirmée par le webhook PayDunya.',
                ]);

                return [
                    'state' => 'confirmed',
                    'kind' => 'commission',
                    'order_id' => $order->id,
                    'payment_id' => $lockedPayment->id,
                    'can_release' => true,
                ];
            }

            // Le solde de la carte cadeau a été réservé au checkout. Il n'est
            // définitivement débité qu'après confirmation du complément PayDunya.
            if ((float) ($order->gift_card_amount ?? 0) > 0) {
                app(GiftCardService::class)->captureForOrder($order);
            }

            $lockedPayment = $this->paymentService->holdEscrow(
                $lockedPayment,
                data_get($payload, 'data.transaction_id') ?? data_get($payload, 'transaction_id'),
                $payload
            );

            $paidItemIds = $this->markPaidOrderItems($lockedPayment, $order);
            $canReleaseOrder = empty($paidItemIds) || $this->areAllOrderItemsPaid($order->refresh());

            if ($canReleaseOrder) {
                Commission::where('order_id', $order->id)->update(['status' => 'payé']);
                $this->stockReservations->commit($order->refresh());
                $this->cartFinalizer->finalize($order->refresh());

                app(\App\Services\OrderWorkflowService::class)->recordHistory($order, null, 'payment', null, 'paid', [
                    'actor_type' => 'system',
                    'label' => 'Paiement validé',
                    'message' => 'Paiement confirmé par le webhook PayDunya.',
                ]);
            }

            if ($canReleaseOrder) {
                $this->vendorOrderReleaseService->release($order->refresh());
            }

            // La génération filtre volontairement les lignes non encore visibles
            // par le vendeur. La libération doit donc précéder le reversement.
            if ($canReleaseOrder && $this->paymentService->shouldCreateVendorPayout($lockedPayment)) {
                $this->vendorPayoutService->generateForOrder($order->refresh(), 'paydunya_payout');
            }

            return [
                'state' => 'confirmed',
                'kind' => 'online',
                'order_id' => $order->id,
                'payment_id' => $lockedPayment->id,
                'can_release' => $canReleaseOrder,
            ];
        }, 3);

        return match ($result['state'] ?? null) {
            'payment_missing' => response()->json(['error' => 'Paiement introuvable'], 404),
            'order_missing' => response()->json(['error' => 'Commande introuvable'], 404),
            'amount_mismatch' => response()->json(['error' => 'Montant de paiement incohérent'], 422),
            'payment_context_missing' => response()->json(['error' => 'Contexte de paiement incomplet'], 422),
            'already_processed' => response()->json(['success' => true, 'message' => 'Déjà traité']),
            default => $this->finishConfirmedPaymentResponse($result),
        };
    }

    private function finishConfirmedPaymentResponse(array $result)
    {
        $order = Order::find($result['order_id'] ?? null);

        if (! $order) {
            return response()->json(['error' => 'Commande introuvable'], 404);
        }

        if ($paymentId = ($result['payment_id'] ?? null)) {
            $confirmedPayment = Payment::with('clientPaymentMethod')->find($paymentId);
            $confirmedPayment?->clientPaymentMethod?->markAsUsed();
        }

        if (($result['kind'] ?? null) === 'cancelled_order_payment_review') {
            app(\App\Services\OrderWorkflowService::class)->notifyAdmins(
                'Paiement reçu après annulation',
                'Un paiement PayDunya a été reçu après l’annulation de la commande ' . $order->order_number . '. Rapprochement et remboursement requis.',
                [
                    'category' => 'payments',
                    'order_id' => $order->id,
                    'url' => route('admin.orders.show', $order),
                ]
            );

            $this->sendPaymentSms(
                $order,
                "OVANIE : un paiement a été reçu après l’annulation de la commande {$order->order_number}. Il est bloqué pour vérification et remboursement ; la commande reste annulée."
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement reçu après annulation et placé en rapprochement',
            ]);
        }

        if (($result['kind'] ?? null) === 'overpayment_review') {
            app(\App\Services\OrderWorkflowService::class)->notifyAdmins(
                'Paiement supplémentaire à régulariser',
                'Un paiement PayDunya supplémentaire a été reçu pour la commande ' . $order->order_number . '. Vérification et régularisation requises.',
                [
                    'category' => 'payments',
                    'order_id' => $order->id,
                    'url' => route('admin.orders.show', $order),
                ]
            );

            return response()->json(['success' => true, 'message' => 'Paiement reçu et signalé pour régularisation']);
        }

        app(LoyaltyService::class)->awardForCompletedOrder($order->fresh(['items']));

        if (($result['kind'] ?? null) === 'commission') {
            $this->sendPaymentSms(
                $order,
                "OVANIE : frais confirmés pour la commande {$order->order_number}. Le reste sera payé à la livraison."
            );

            return response()->json(['success' => true]);
        }

        if (($result['kind'] ?? null) === 'reception') {
            $message = ! empty($result['all_paid'])
                ? "OVANIE : paiement confirmé pour tous les articles de la commande {$order->order_number}."
                : "OVANIE : paiement d’article confirmé pour la commande {$order->order_number}.";

            $this->sendPaymentSms($order, $message);

            return response()->json(['success' => true]);
        }

        if (! empty($result['can_release'])) {
            $this->sendPaymentSms(
                $order,
                "OVANIE : paiement confirmé pour la commande {$order->order_number}. Votre commande est en cours de traitement."
            );

            $this->notifyPaymentConfirmed($order);
        } else {
            $this->sendPaymentSms(
                $order,
                "OVANIE : paiement partiel enregistré pour la commande {$order->order_number}. Les produits restants doivent encore être réglés."
            );
        }

        return response()->json(['success' => true]);
    }

    private function failOrderPayment(Payment $payment, string $status, array $payload = [])
    {
        $result = DB::transaction(function () use ($payment, $status, $payload) {
            // Même ordre de verrouillage que l'annulation et la confirmation :
            // commande puis paiement.
            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return ['state' => 'order_missing'];
            }

            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return ['state' => 'payment_missing'];
            }

            if ($lockedPayment->isPaid()) {
                return ['state' => 'paid'];
            }

            $orderWasAlreadyCancelled = $order->status === 'cancelled';

            $lockedPayment->forceFill([
                'status' => $status === PaymentService::STATUS_CANCELLED
                    ? Payment::STATUS_CANCELLED
                    : Payment::STATUS_FAILED,
                'failed_at' => now(),
                'provider_payload' => array_merge($lockedPayment->provider_payload ?? [], [
                    'failure_payload' => $payload,
                    'failed_status' => $status,
                    'failed_at' => now()->toDateTimeString(),
                ]),
            ])->save();

            $paymentType = $lockedPayment->type ?: 'order_payment';

            if (! $orderWasAlreadyCancelled
                && in_array($paymentType, ['order_payment', 'commission_payment'], true)
                && ! in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
                $order->load('items');
                app(LoyaltyService::class)->restoreForOrder($order, 'Échec ou annulation du paiement');
                app(GiftCardService::class)->releaseForOrder($order, 'Échec ou annulation du paiement PayDunya');
                $this->stockReservations->releaseAndRestoreCart(
                    $order,
                    ! $this->cartFinalizer->cartWasPreserved($order)
                );
                $this->cartFinalizer->markAbandoned($order);
                $order->forceFill([
                    'payment_status' => $status,
                    'status' => 'cancelled',
                ])->save();

                Shipment::where('order_id', $order->id)
                    ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            }

            return ['state' => 'failed'];
        }, 3);

        return match ($result['state'] ?? null) {
            'payment_missing' => response()->json(['error' => 'Paiement introuvable'], 404),
            'order_missing' => response()->json(['error' => 'Commande introuvable'], 404),
            'paid' => response()->json(['success' => true, 'message' => 'Paiement déjà confirmé, échec ignoré']),
            default => response()->json(['success' => true, 'message' => 'Échec de paiement enregistré']),
        };
    }

    private function confirmReceptionPayment(Payment $payment, Order $order, array $payload): array
    {
        $itemIds = $this->paymentOrderItemIds($payment);

        if (empty($itemIds)) {
            Log::critical('Paiement de réception sans lignes de commande associées.', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'type' => $payment->type,
            ]);

            return ['state' => 'payment_context_missing'];
        }

        $providerPayload = array_merge($payment->provider_payload ?? [], [
            'confirmation_payload' => $payload,
            'confirmed_at' => now()->toDateTimeString(),
        ]);

        $payment->forceFill([
            'status' => Payment::STATUS_ESCROW_HELD,
            'transaction_id' => data_get($payload, 'data.transaction_id')
                ?? data_get($payload, 'transaction_id')
                ?? $payment->transaction_id,
            'provider_payload' => $providerPayload,
            'paid_at' => $payment->paid_at ?: now(),
        ])->save();

        // Une confirmation rend les autres factures de réception locales obsolètes.
        // Elles sont annulées dans le ledger ; si un prestataire confirme malgré
        // tout l'une d'elles plus tard, le webhook la comptabilise et la signale
        // comme trop-perçu à régulariser.
        Payment::query()
            ->where('order_id', $order->id)
            ->where('id', '!=', $payment->id)
            ->where('status', Payment::STATUS_PENDING)
            ->whereIn('type', ['order_line_payment', 'order_lines_payment'])
            ->lockForUpdate()
            ->get()
            ->each(function (Payment $competingPayment) use ($payment) {
                $competingPayload = $competingPayment->provider_payload ?? [];
                $competingPayload['cancel_reason'] = 'superseded_by_confirmed_reception_payment';
                $competingPayload['superseded_by_payment_id'] = $payment->id;
                $competingPayload['cancelled_at'] = now()->toDateTimeString();

                $competingPayment->forceFill([
                    'status' => Payment::STATUS_CANCELLED,
                    'provider_payload' => $competingPayload,
                ])->save();
            });

        $order->items()->whereIn('id', $itemIds)->update(['is_paid' => true]);

        $receptionLineIds = data_get($providerPayload, 'reception_line_ids', []);
        if (is_string($receptionLineIds)) {
            $receptionLineIds = json_decode($receptionLineIds, true) ?: [];
        }

        $lineQuery = OrderReceptionFormItem::query()
            ->whereHas('form', fn ($query) => $query->where('order_id', $order->id));

        if (is_array($receptionLineIds) && ! empty($receptionLineIds)) {
            $lineQuery->whereIn('id', array_map('intval', $receptionLineIds));
        } else {
            $lineQuery->whereIn('order_item_id', $itemIds);
        }

        $lineQuery->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $payment->reference,
            'updated_at' => now(),
        ]);

        $allPaid = $this->areAllOrderItemsPaid($order->refresh());
        $order->forceFill([
            'payment_status' => $allPaid ? 'paid' : 'partial',
            'status' => $allPaid && $order->status === 'pending' ? 'confirmed' : $order->status,
        ])->save();

        if ($allPaid) {
            $this->stockReservations->commit($order->refresh());
        }

        $workflow = app(\App\Services\OrderWorkflowService::class);
        $workflow->markPayoutsReadyWhenEligible($order->refresh());
        $workflow->recordHistory($order, null, 'payment', null, $allPaid ? 'paid' : 'partial', [
            'actor_type' => 'system',
            'label' => $allPaid ? 'Paiement complet confirmé' : 'Paiement partiel confirmé',
            'message' => 'Paiement PayDunya confirmé par webhook pour les articles concernés.',
            'metadata' => [
                'payment_id' => $payment->id,
                'order_item_ids' => $itemIds,
            ],
        ]);

        return [
            'state' => 'confirmed',
            'kind' => 'reception',
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'all_paid' => $allPaid,
            'can_release' => false,
        ];
    }

    private function markPaidOrderItems(Payment $payment, Order $order): array
    {
        $itemIds = $this->paymentOrderItemIds($payment);

        if (! $itemIds) {
            if (($payment->type ?? 'order_payment') === 'order_payment') {
                $order->items()->update(['is_paid' => true]);
            }

            return [];
        }

        $order->items()
            ->whereIn('id', $itemIds)
            ->update(['is_paid' => true]);

        if (! $this->areAllOrderItemsPaid($order->refresh())) {
            $order->forceFill([
                'payment_status' => 'partial',
                'status' => 'pending',
            ])->save();
        }

        return $itemIds;
    }

    private function areAllOrderItemsPaid(Order $order): bool
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return false;
        }

        return $order->items->every(fn ($item) => (bool) $item->is_paid);
    }

    private function paymentOrderItemIds(Payment $payment): array
    {
        $itemIds = $payment->order_item_ids ?? null;

        if (is_string($itemIds)) {
            $itemIds = json_decode($itemIds, true);
        }

        return is_array($itemIds)
            ? array_values(array_filter(array_map('intval', $itemIds)))
            : [];
    }

    private function hasValidPaydunyaSignature(Request $request): bool
    {
        // Format IPN officiel PayDunya : data.hash = SHA-512(MasterKey).
        $masterKey = (string) config('paydunya.master_key', '');
        $paydunyaHash = (string) $request->input('data.hash', '');

        if ($masterKey !== '' && $paydunyaHash !== '') {
            return hash_equals(hash('sha512', $masterKey), $paydunyaHash);
        }

        // Compatibilité optionnelle avec une ancienne intégration interne HMAC.
        $secret = (string) config('paydunya.webhook_secret', '');
        $legacySignature = $request->header('X-Paydunya-Signature')
            ?? $request->header('Paydunya-Signature')
            ?? $request->input('signature');

        if ($secret !== '' && $legacySignature) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            return hash_equals($expected, Str::after((string) $legacySignature, 'sha256='));
        }

        if (app()->environment('testing')
            && (bool) config('paydunya.allow_unsigned_webhooks_in_testing', true)) {
            return true;
        }

        Log::critical('Webhook PayDunya refusé : hash IPN ou configuration de vérification absente.');

        return false;
    }

    private function isPaydunyaPaidPayload(Request $request): bool
    {
        $status = Str::lower((string) (
            $request->input('data.status')
            ?? $request->input('status')
            ?? $request->input('data.transaction_status')
            ?? 'paid'
        ));

        return in_array($status, array_map('strtolower', config('paydunya.paid_statuses', [])), true);
    }

    private function notifyPaymentConfirmed(Order $order): void
    {
        $workflow = app(\App\Services\OrderWorkflowService::class);
        $order->loadMissing(['client', 'items.product.shop.user']);

        $workflow->notifyAdmins('Paiement PayDunya confirme', 'Commande ' . $order->order_number . ' confirmee par webhook.', [
            'category' => 'payments',
            'order_id' => $order->id,
            'url' => route('admin.orders.show', $order),
        ]);
    }

    private function sendPaymentSms(Order $order, string $message): void
    {
        $order->loadMissing('client');

        app(OvanieNotificationDispatcher::class)->send(
            $order->client,
            'payments',
            'Mise à jour du paiement',
            $message,
            [
                'order_id' => $order->id,
                'url' => route('client.orders.show', $order),
                'phone' => $order->phone,
            ],
            ['push', 'email', 'sms']
        );
    }

    private function confirmBoostPayment(Product $product)
    {
        if ($product->boost_payment_status === 'paid') {
            return response()->json(['success' => true, 'message' => 'Déjà traité']);
        }

        $days = $product->boost_duration_days ?: 7;

        $product->update([
            'is_boosted' => true,
            'boost_payment_status' => 'paid',
            'boost_paid_at' => now(),
            'boost_start_at' => now(),
            'boost_end_at' => now()->addDays($days),
        ]);

        return response()->json(['success' => true]);
    }

    public function myPayments()
    {
        $payments = Payment::with('order')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json($payments);
    }
}
