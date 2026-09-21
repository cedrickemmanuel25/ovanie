<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReceptionForm;
use App\Models\Payment;
use App\Services\OrderPaymentEligibilityService;
use App\Services\OrderSettlementService;
use App\Services\OrderWorkflowService;
use App\Services\PayDunyaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderReceptionController extends Controller
{
    private const RECEPTION_PAYMENT_TYPES = [
        'order_line_payment',
        'order_lines_payment',
    ];

    public function form($orderId, OrderSettlementService $settlement)
    {
        $client = auth()->user();

        $order = Order::with(['items.product'])
            ->where('id', $orderId)
            ->where('client_id', $client->id)
            ->firstOrFail();

        $form = $this->firstOrCreateOwnedForm($order, $client->id, $client->name, $client->phone ?? '');

        if ($form->items()->count() === 0) {
            foreach ($order->items as $item) {
                $quantity = max(1, (int) ($item->quantity ?? 1));
                $unitPrice = (float) ($item->price ?? 0);
                $amount = (float) ($item->subtotal ?? ($quantity * $unitPrice));

                $form->items()->create([
                    'order_item_id' => $item->id,
                    'product_name' => $item->product?->name ?? 'Produit',
                    // La boutique et le vendeur restent strictement internes.
                    'shop_name' => null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'site_commission' => 0,
                    'vendor_gain' => 0,
                    'received' => $item->reception_status === 'confirmed',
                    'payment_status' => $item->is_paid ? 'paid' : 'pending',
                ]);
            }

            $form->load('items');
        } else {
            $this->synchronizeFormItems($order, $form);
        }

        $clientCode = $form->client_code;
        $outstandingAmount = $settlement->outstandingAmount($order);

        return view('client.orders.reception-form', compact(
            'client',
            'order',
            'form',
            'clientCode',
            'outstandingAmount'
        ));
    }

    public function save(Request $request, $orderId)
    {
        $client = auth()->user();

        $order = Order::with('items')
            ->where('id', $orderId)
            ->where('client_id', $client->id)
            ->firstOrFail();

        $form = OrderReceptionForm::with('items')
            ->where('order_id', $order->id)
            ->where('client_id', $client->id)
            ->firstOrFail();

        if ($form->status === 'validated') {
            return back()->with('error', 'Ce formulaire a déjà été validé et ne peut plus être modifié. Le paiement d’un solde restant demeure disponible séparément.');
        }

        $validated = $request->validate([
            'delivery_place' => ['nullable', 'string', 'max:255'],
            'reception_place' => ['nullable', 'string', 'max:255'],
            'validated_city' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.received' => ['nullable', 'boolean'],
            'items.*.received_date' => ['nullable', 'date'],
            'items.*.received_time' => ['nullable', 'date_format:H:i'],
            'action' => ['required', 'in:save,validate'],
        ]);

        DB::transaction(function () use ($request, $validated, $form, $order, $client) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load('items');

            $lockedForm = OrderReceptionForm::query()
                ->with('items')
                ->whereKey($form->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedForm->status === 'validated') {
                throw ValidationException::withMessages([
                    'items' => 'La réception a déjà été validée.',
                ]);
            }

            $lockedForm->update([
                'delivery_place' => $validated['delivery_place'] ?? null,
                'reception_place' => $validated['reception_place'] ?? null,
                'validated_city' => $validated['validated_city'] ?? null,
            ]);

            $itemsInput = $request->input('items', []);

            foreach ($lockedForm->items as $line) {
                $input = $itemsInput[$line->id] ?? [];
                $received = filter_var($input['received'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $line->update([
                    'received' => $received,
                    'received_date' => $received
                        ? (($input['received_date'] ?? null) ?: ($line->received_date?->format('Y-m-d') ?: now()->toDateString()))
                        : null,
                    'received_time' => $received
                        ? (($input['received_time'] ?? null) ?: ($line->received_time ?: now()->format('H:i')))
                        : null,
                ]);
            }

            if (($validated['action'] ?? null) !== 'validate') {
                return;
            }

            $allDelivered = $lockedOrder->items->every(
                fn ($orderItem) => $orderItem->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED
                    || $orderItem->isVendorDelivered()
            );

            if (! $allDelivered) {
                throw ValidationException::withMessages([
                    'delivery' => 'La réception ne peut être confirmée que lorsque tous les articles sont marqués comme livrés.',
                ]);
            }

            $lockedForm->refresh()->load('items');
            if (! $lockedForm->items->every(fn ($line) => (bool) $line->received)) {
                throw ValidationException::withMessages([
                    'items' => 'Confirmez la réception de chaque article avant de valider définitivement.',
                ]);
            }

            $lockedForm->update([
                'status' => 'validated',
                'validated_at' => now(),
                'validated_date' => now()->toDateString(),
            ]);

            foreach ($lockedForm->items as $line) {
                $orderItem = $lockedOrder->items->firstWhere('id', $line->order_item_id);

                if ($orderItem && $orderItem->reception_status !== 'confirmed') {
                    app(OrderWorkflowService::class)->confirmClientReception(
                        $orderItem,
                        $client,
                        'Réception confirmée par le client depuis son espace OVANIE.'
                    );
                }
            }
        }, 3);

        if (($validated['action'] ?? null) === 'validate') {
            return redirect()->route('client.orders.show', $order)
                ->with('success', 'La réception de votre commande a été confirmée. Un éventuel solde restant peut être réglé séparément depuis la fiche commande.');
        }

        return back()->with('success', 'Le formulaire de réception a été enregistré.');
    }

    public function payLine($orderId, $lineId, PayDunyaService $paydunya, OrderSettlementService $settlement, OrderPaymentEligibilityService $eligibility)
    {
        $client = auth()->user();
        $order = $this->ownedOrder($orderId, $client->id);
        $form = $this->firstOrCreateOwnedForm($order, $client->id, $client->name, $client->phone ?? '');
        $line = $form->items()->where('id', $lineId)->firstOrFail();
        $orderItem = $order->items()->whereKey($line->order_item_id)->firstOrFail();
        $eligibility->assertLinePayable($order, $orderItem);

        if (($line->payment_status ?? 'pending') === 'paid') {
            return back()->with('success', 'Ce produit est déjà payé.');
        }

        $intent = DB::transaction(function () use ($order, $line, $client, $settlement, $eligibility) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrderItem = $lockedOrder->items()->whereKey($line->order_item_id)->lockForUpdate()->firstOrFail();
            $eligibility->assertLinePayable($lockedOrder, $lockedOrderItem);

            if ($existing = $this->pendingReceptionPayment($lockedOrder->id)) {
                return ['payment' => $existing, 'created' => false];
            }

            $lockedLine = $line->newQuery()->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if (($lockedLine->payment_status ?? 'pending') === 'paid') {
                return ['already_paid' => true];
            }

            $payableAmount = $settlement->amountForLine($lockedOrder, (float) $lockedLine->amount);
            if ($payableAmount <= 0) {
                return ['already_paid' => true];
            }

            $payment = Payment::create([
                'order_id' => $lockedOrder->id,
                'method' => 'paydunya',
                'amount' => $payableAmount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $client->id,
                'operator' => 'paydunya',
                'mobile_number' => $client->phone ?? $lockedOrder->phone,
                'reference' => 'LINE-' . $lockedLine->id . '-' . strtoupper(Str::random(8)),
                'type' => 'order_line_payment',
                'order_item_ids' => $lockedLine->order_item_id ? [(int) $lockedLine->order_item_id] : [],
                'provider_payload' => [
                    'reception_form_id' => $lockedLine->order_reception_form_id,
                    'reception_line_ids' => [(int) $lockedLine->id],
                ],
            ]);

            return ['payment' => $payment, 'created' => true, 'line' => $lockedLine];
        }, 3);

        if (! empty($intent['already_paid'])) {
            return back()->with('success', 'Cette commande est déjà entièrement réglée.');
        }

        /** @var Payment $payment */
        $payment = $intent['payment'];

        if (! $intent['created']) {
            return $this->resumePendingPayment($payment);
        }

        $lockedLine = $intent['line'];

        return $this->initializePayDunyaPayment(
            $paydunya,
            $payment,
            [
                'item_name' => $lockedLine->product_name,
                'description' => 'Paiement du produit ' . $lockedLine->product_name . ' - commande ' . $order->order_number,
                'amount' => (float) $payment->amount,
                'return_url' => route('client.orders.reception.payLine.success', [$order->id, $lockedLine->id, $payment->id]),
                'cancel_url' => route('client.orders.reception.form', $order->id),
            ]
        );
    }

    public function payOrder($orderId, PayDunyaService $paydunya, OrderSettlementService $settlement, OrderPaymentEligibilityService $eligibility)
    {
        $client = auth()->user();
        $order = $this->ownedOrder($orderId, $client->id);
        $form = $this->firstOrCreateOwnedForm($order, $client->id, $client->name, $client->phone ?? '');
        $this->ensureFormItemsExist($order->loadMissing('items.product'), $form);
        $eligibility->assertOrderBalancePayable($order);

        $intent = DB::transaction(function () use ($order, $form, $client, $settlement, $eligibility) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load('items');
            $eligibility->assertOrderBalancePayable($lockedOrder);

            if ($existing = $this->pendingReceptionPayment($lockedOrder->id)) {
                return ['payment' => $existing, 'created' => false];
            }

            $unpaidLines = $form->items()->where('payment_status', '!=', 'paid')->lockForUpdate()->get();
            $amount = $settlement->outstandingAmount($lockedOrder);

            if ($amount <= 0 || $unpaidLines->isEmpty()) {
                return ['already_paid' => true];
            }

            $payment = Payment::create([
                'order_id' => $lockedOrder->id,
                'method' => 'paydunya',
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $client->id,
                'operator' => 'paydunya',
                'mobile_number' => $client->phone ?? $lockedOrder->phone,
                'reference' => 'ORDER-LINES-' . $lockedOrder->id . '-' . strtoupper(Str::random(8)),
                'type' => 'order_lines_payment',
                'order_item_ids' => $unpaidLines->pluck('order_item_id')->filter()->map(fn ($id) => (int) $id)->values()->all(),
                'provider_payload' => [
                    'reception_form_id' => $form->id,
                    'reception_line_ids' => $unpaidLines->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ],
            ]);

            return ['payment' => $payment, 'created' => true];
        }, 3);

        if (! empty($intent['already_paid'])) {
            return back()->with('success', 'Cette commande est déjà entièrement réglée.');
        }

        /** @var Payment $payment */
        $payment = $intent['payment'];

        if (! $intent['created']) {
            return $this->resumePendingPayment($payment);
        }

        return $this->initializePayDunyaPayment(
            $paydunya,
            $payment,
            [
                'item_name' => 'Paiement commande OVANIE',
                'description' => 'Paiement du solde de la commande ' . $order->order_number,
                'amount' => (float) $payment->amount,
                'return_url' => route('client.orders.reception.payOrder.success', [$order->id, $payment->id]),
                'cancel_url' => route('client.orders.reception.form', $order->id),
            ]
        );
    }

    public function payLineSuccess($orderId, $lineId, $paymentId)
    {
        $client = auth()->user();
        $order = $this->ownedOrder($orderId, $client->id);
        $form = $this->ownedForm($order->id, $client->id);
        $form->items()->where('id', $lineId)->firstOrFail();

        $payment = Payment::where('id', $paymentId)
            ->where('order_id', $order->id)
            ->where('user_id', $client->id)
            ->firstOrFail();

        return redirect()->route('client.orders.reception.form', $order->id)->with(
            'success',
            $payment->isPaid()
                ? 'Paiement confirmé.'
                : 'Votre paiement est en cours de vérification. Le statut sera mis à jour automatiquement après confirmation PayDunya.'
        );
    }

    public function payOrderSuccess($orderId, $paymentId)
    {
        $client = auth()->user();
        $order = $this->ownedOrder($orderId, $client->id);
        $payment = Payment::where('id', $paymentId)
            ->where('order_id', $order->id)
            ->where('user_id', $client->id)
            ->firstOrFail();

        return redirect()->route('client.orders.reception.form', $order->id)->with(
            'success',
            $payment->isPaid()
                ? 'Paiement de la commande confirmé.'
                : 'Votre paiement est en cours de vérification. Le statut sera mis à jour automatiquement après confirmation PayDunya.'
        );
    }

    private function initializePayDunyaPayment(PayDunyaService $paydunya, Payment $payment, array $invoiceData)
    {
        $invoice = $paydunya->createOrderInvoice($invoiceData);

        if ($invoice->create()) {
            $token = $this->extractPayDunyaToken($invoice, $payment->reference);
            $payload = $payment->provider_payload ?? [];
            $payload['checkout_url'] = $invoice->getInvoiceUrl();
            $payload['invoice_created_at'] = now()->toDateTimeString();

            $payment->update([
                'reference' => $token,
                'provider_payload' => $payload,
            ]);

            return redirect()->away($invoice->getInvoiceUrl());
        }

        $payment->markAsFailed($invoice->response_text ?? 'Initialisation PayDunya impossible');

        return back()->with('error', $invoice->response_text ?? 'Impossible d’initialiser le paiement PayDunya.');
    }

    private function resumePendingPayment(Payment $payment)
    {
        $checkoutUrl = data_get($payment->provider_payload, 'checkout_url');

        if (is_string($checkoutUrl) && filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($checkoutUrl);
        }

        return back()->with(
            'warning',
            'Une tentative de paiement est déjà en cours de création pour cette commande. Actualisez la page avant de relancer le paiement.'
        );
    }

    private function pendingReceptionPayment(int $orderId): ?Payment
    {
        return Payment::query()
            ->where('order_id', $orderId)
            ->where('status', Payment::STATUS_PENDING)
            ->whereIn('type', self::RECEPTION_PAYMENT_TYPES)
            ->latest('id')
            ->first();
    }

    private function synchronizeFormItems(Order $order, OrderReceptionForm $form): void
    {
        $itemsById = $order->items->keyBy('id');

        foreach ($form->items as $line) {
            $orderItem = $itemsById->get($line->order_item_id);
            if (! $orderItem) {
                continue;
            }

            $updates = [];

            if ($orderItem->is_paid && $line->payment_status !== 'paid') {
                $updates['payment_status'] = 'paid';
                $updates['paid_at'] = $line->paid_at ?: now();
            }

            if ($orderItem->reception_status === 'confirmed' && ! $line->received) {
                $updates['received'] = true;
                $updates['received_date'] = $orderItem->reception_confirmed_at?->toDateString() ?: now()->toDateString();
                $updates['received_time'] = $orderItem->reception_confirmed_at?->format('H:i') ?: now()->format('H:i');
            }

            if ($updates) {
                $line->update($updates);
            }
        }

        $form->load('items');
    }

    private function ensureFormItemsExist(Order $order, OrderReceptionForm $form): void
    {
        if ($form->items()->exists()) {
            $this->synchronizeFormItems($order, $form);
            return;
        }

        foreach ($order->items as $item) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $unitPrice = (float) ($item->price ?? 0);
            $amount = (float) ($item->subtotal ?? ($quantity * $unitPrice));

            $form->items()->create([
                'order_item_id' => $item->id,
                'product_name' => $item->product?->name ?? 'Produit',
                'shop_name' => null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'site_commission' => 0,
                'vendor_gain' => 0,
                'received' => $item->reception_status === 'confirmed',
                'payment_status' => $item->is_paid ? 'paid' : 'pending',
            ]);
        }

        $form->load('items');
    }

    private function firstOrCreateOwnedForm(Order $order, int $clientId, string $clientName, ?string $clientPhone): OrderReceptionForm
    {
        return OrderReceptionForm::with('items')->firstOrCreate(
            ['order_id' => $order->id, 'client_id' => $clientId],
            [
                'client_code' => $clientId . '-' . $order->order_number,
                'client_name' => $clientName,
                'client_phone' => $clientPhone ?? '',
                'status' => 'draft',
            ]
        );
    }

    private function ownedOrder(int|string $orderId, int $clientId): Order
    {
        return Order::where('id', $orderId)->where('client_id', $clientId)->firstOrFail();
    }

    private function ownedForm(int $orderId, int $clientId): OrderReceptionForm
    {
        return OrderReceptionForm::with('items')
            ->where('order_id', $orderId)
            ->where('client_id', $clientId)
            ->firstOrFail();
    }

    private function extractPayDunyaToken($invoice, string $fallback): string
    {
        return $invoice->token
            ?? $invoice->invoice_token
            ?? ($invoice->response_array['token'] ?? null)
            ?? ($invoice->response['token'] ?? null)
            ?? $fallback;
    }
}
