<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderSettlementService;
use App\Services\PayDunyaService;
use App\Services\PaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::query()
            ->with('order:id,order_number')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $payments->setCollection(
            $payments->getCollection()->map(fn (Payment $payment) => $this->clientPayload($payment))
        );

        return response()->json($payments);
    }

    public function store(
        Request $request,
        OrderSettlementService $settlements,
        PaymentMethodResolver $methods,
        PayDunyaService $paydunya
    ) {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'client_payment_method_id' => ['nullable', 'integer'],
        ]);

        $order = Order::query()->whereKey($validated['order_id'])->firstOrFail();
        abort_if((int) $order->client_id !== (int) $request->user()->id, 403);

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'order_id' => 'Une commande annulée ne peut pas recevoir de nouveau paiement.',
            ]);
        }

        $amount = $settlements->outstandingAmount($order);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'order_id' => 'Cette commande est déjà entièrement réglée.',
            ]);
        }

        if ($amount > 3_000_000) {
            throw ValidationException::withMessages([
                'order_id' => 'Le solde dépasse la limite du paiement en ligne. Contactez le support OVANIE pour un mode adapté.',
            ]);
        }

        $savedMethod = $methods->resolve(
            $request->user(),
            isset($validated['client_payment_method_id']) ? (int) $validated['client_payment_method_id'] : null,
            'paydunya'
        );
        $channel = $methods->paydunyaChannel($savedMethod);
        $phone = trim((string) ($savedMethod?->phone ?: $order->phone ?: $request->user()->phone));

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'client_payment_method_id' => $savedMethod?->id,
            'method' => 'paydunya',
            'type' => 'order_payment',
            'amount' => $amount,
            'operator' => $savedMethod?->operator ?: 'paydunya',
            'mobile_number' => $phone ?: null,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'API-PAY-' . $order->order_number . '-' . Str::upper(Str::random(6)),
        ]);

        try {
            $invoice = $paydunya->createOrderInvoice([
                'item_name' => 'Solde commande OVANIE',
                'description' => 'Paiement du solde de la commande ' . $order->order_number,
                'amount' => $amount,
                'return_url' => route('paydunya.mobile.return'),
                'cancel_url' => route('paydunya.mobile.cancel'),
                'channel' => $channel,
            ]);

            if (! $invoice->create()) {
                throw new \RuntimeException($invoice->response_text ?? 'Impossible d’initialiser le paiement.');
            }

            $token = $this->extractPayDunyaToken($invoice, (string) $payment->reference);
            $payment->forceFill(['reference' => $token])->save();

            return response()->json([
                'message' => 'Paiement sécurisé initialisé.',
                'payment' => $this->clientPayload($payment->fresh()),
                'payment_url' => $invoice->getInvoiceUrl(),
            ], 201);
        } catch (\Throwable $e) {
            $payment->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failed_at' => now(),
                'provider_payload' => ['initialization_error' => $e->getMessage()],
            ])->save();

            throw ValidationException::withMessages([
                'payment' => 'Le paiement n’a pas pu être initialisé. Aucun débit n’a été confirmé.',
            ]);
        }
    }

    public function show(Request $request, Payment $payment)
    {
        abort_if((int) $payment->user_id !== (int) $request->user()->id, 403);

        return response()->json(['data' => $this->clientPayload($payment)]);
    }


    private function extractPayDunyaToken(object $invoice, string $fallback): string
    {
        return (string) (
            $invoice->token
            ?? $invoice->invoice_token
            ?? ($invoice->response_array['token'] ?? null)
            ?? ($invoice->response['token'] ?? null)
            ?? $fallback
        );
    }


    private function clientPayload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_number' => (string) ($payment->order?->order_number ?? ''),
            'reference' => (string) ($payment->reference ?? ''),
            'method' => $payment->method,
            'operator' => $payment->operator,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'type' => $payment->type,
            'paid_at' => optional($payment->paid_at)->toIso8601String(),
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }
}
