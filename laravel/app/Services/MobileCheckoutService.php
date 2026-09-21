<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartFulfillmentOptimization;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MobileCheckoutService
{
    public function __construct(
        private readonly CheckoutSummaryService $summaries,
        private readonly CartFulfillmentOptimizer $optimizer,
        private readonly MobileCartResolver $carts,
        private readonly CheckoutCartSelectionService $cartSelection,
        private readonly CheckoutCartFinalizerService $cartFinalizer,
        private readonly OrderWorkflowService $workflow,
        private readonly OrderStockReservationService $stockReservations,
        private readonly LoyaltyService $loyalty,
        private readonly CommissionService $commissions,
        private readonly PaymentMethodResolver $paymentMethods,
        private readonly CheckoutPaymentOptionsService $paymentOptions,
        private readonly PayDunyaService $paydunya,
        private readonly InvoiceNumberService $invoiceNumbers,
        private readonly PublicProductVisibilityService $visibility,
        private readonly OrderDeliveryPersistenceService $deliveryPersistence,
        private readonly OvanieNotificationDispatcher $notifications,
        private readonly VendorOrderReleaseService $vendorRelease
    ) {
    }

    public function place(Request $request, array $data): array
    {
        /** @var User $user */
        $user = $request->user();
        $savedMethod = $this->paymentMethods->resolve(
            $user,
            isset($data['client_payment_method_id']) ? (int) $data['client_payment_method_id'] : null,
            (string) $data['payment_method']
        );
        $onlineOperator = $data['payment_method'] === 'paydunya'
            ? strtolower(trim((string) ($data['online_operator'] ?? $savedMethod?->operator ?? '')))
            : '';
        $paymentChannel = $onlineOperator === 'card'
            ? null
            : $this->paymentMethods->paydunyaChannelForOperator($onlineOperator)
                ?? $this->paymentMethods->paydunyaChannel($savedMethod);
        $paymentPhone = trim((string) (
            ($data['payment_phone'] ?? '')
            ?: $savedMethod?->phone
            ?: ($data['phone'] ?? $user->phone ?? '')
        ));

        $result = DB::transaction(function () use ($request, $data, $user, $savedMethod, $paymentPhone, $onlineOperator) {
            $cart = $this->carts->forUser($user, lockForUpdate: true);

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Votre panier est vide.']);
            }

            $this->cartSelection->apply(
                $cart,
                (array) ($data['checkout_product_ids'] ?? []),
                (array) ($data['checkout_cart_item_ids'] ?? [])
            );
            $selectedCartItemIds = $cart->items
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $this->optimizer->optimizeForAddress($cart, $this->addressPayload($request));
            $cart->refresh()->load('items.product.shop');
            // refresh() recharge toutes les lignes du panier : réappliquer la
            // sélection par id de ligne garantit qu'aucun produit décoché ne
            // peut réapparaître dans la commande après l'optimisation.
            $this->cartSelection->apply($cart, [], $selectedCartItemIds, true);
            $this->validateOrderableCart($cart);

            $checkout = $this->summaries->build($cart, $request);
            if (! empty($checkout['delivery_quote_required'])) {
                throw ValidationException::withMessages([
                    'address' => DeliveryPricingEngine::UNKNOWN_MESSAGE,
                ]);
            }

            $unavailableGroup = collect($checkout['groups'] ?? [])->first(fn ($group) => empty($group['delivery_available']));
            if ($unavailableGroup) {
                throw ValidationException::withMessages([
                    'address' => 'Aucune option de livraison n’est disponible pour un ou plusieurs produits à cette adresse.',
                ]);
            }

            $subtotal = (float) $checkout['subtotal'];
            $deliveryFee = (float) $checkout['delivery_fee'];
            $requestedPoints = max(0, (int) ($data['loyalty_points'] ?? 0));
            $maxPoints = $this->loyalty->maxRedeemablePoints($user, $subtotal);
            if ($requestedPoints > $maxPoints) {
                throw ValidationException::withMessages([
                    'loyalty_points' => "Vous pouvez utiliser au maximum {$maxPoints} points sur cette commande.",
                ]);
            }

            $loyaltyDiscount = $this->loyalty->calculateDiscount($requestedPoints);
            $total = max(0, (float) $checkout['total'] - $loyaltyDiscount);
            $commissionAmount = (float) $checkout['commission'];

            // Même règle que le checkout Web : le backend décide si le mode
            // de paiement est autorisé pour CE panier et CE montant.
            $this->paymentOptions->assertAllowed($cart, (string) $data['payment_method'], $total);

            $orderPayload = [
                'order_number' => 'OVANIE-' . Str::upper(Str::random(8)),
                'invoice_number' => $this->invoiceNumbers->next(),
                'client_id' => $user->id,
                'customer_name' => trim((string) ($data['full_name'] ?? $user->name ?? 'Client OVANIE')),
                'phone' => $data['phone'] ?? $paymentPhone,
                'address' => $data['address'] ?? 'Retrait géré par OVANIE',
                'delivery_address' => $data['address'] ?? 'Retrait géré par OVANIE',
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'status' => $data['payment_method'] === 'cash_on_delivery' ? 'confirmed' : 'pending',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'delivery_fee_total' => $deliveryFee,
                'ovanie_delivery_fee' => collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_OVANIE)->sum('delivery_fee'),
                'seller_delivery_fee' => collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_SELLER)->sum('delivery_fee'),
                'partner_delivery_fee' => collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_PARTNER)->sum('delivery_fee'),
                'delivery_breakdown' => $checkout['delivery_breakdown'],
                'selected_carriers' => $checkout['selected_carriers'],
                'delivery_pricing_status' => 'calculated',
                'delivery_pricing_meta' => [
                    'calculated_at' => now()->toDateTimeString(),
                    'logistics_groups' => $checkout['logistics_groups'] ?? [],
                    // Même politique que le checkout web : pour tout paiement
                    // qui dépend d'un prestataire externe, le panier reste
                    // intact tant que le paiement n'est pas confirmé.
                    'checkout' => [
                        'state' => $data['payment_method'] === 'paydunya'
                            ? 'awaiting_payment'
                            : 'confirmed',
                        'source' => 'mobile_api',
                        'cart_id' => (int) $cart->id,
                        'cart_item_ids' => $cart->items->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        'cart_policy' => $data['payment_method'] === 'paydunya'
                            ? CheckoutCartFinalizerService::POLICY_PRESERVE_UNTIL_PAYMENT
                            : CheckoutCartFinalizerService::POLICY_CLEAR_IMMEDIATELY,
                        'cart_status' => $data['payment_method'] === 'paydunya'
                            ? 'preserved'
                            : 'pending_clear',
                        'created_at' => now()->toDateTimeString(),
                    ],
                ],
                'total_amount' => $total,
                'loyalty_points_used' => $requestedPoints,
                'loyalty_discount' => $loyaltyDiscount,
                'delivery_zone' => $data['delivery_zone'],
                'delivery_destination_type' => $data['delivery_destination_type'] ?? 'home',
                'delivery_recipient_name' => trim((string) ($data['full_name'] ?? $user->name ?? 'Client OVANIE')),
                'delivery_recipient_phone' => $data['phone'] ?? $paymentPhone,
                'delivery_commune' => $data['delivery_commune'] ?? null,
                'delivery_quartier' => $data['delivery_quartier'] ?? null,
                'delivery_city' => $data['delivery_city'] ?? null,
                'delivery_latitude' => $data['delivery_latitude'] ?? null,
                'delivery_longitude' => $data['delivery_longitude'] ?? null,
                'delivery_geo_accuracy' => $data['delivery_geo_accuracy'] ?? null,
                'delivery_geo_source' => $data['delivery_geo_source'] ?? 'mobile_api',
                'delivery_note' => $data['notes'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            // Compatibilité avec l'ancienne colonne orders.product_id encore
            // obligatoire dans certaines bases OVANIE. Le checkout web applique
            // déjà cette règle ; le checkout mobile doit faire exactement pareil.
            if (Schema::hasColumn('orders', 'product_id')) {
                $legacyProductId = $cart->items->first()?->product_id;

                if ($legacyProductId) {
                    $orderPayload['product_id'] = (int) $legacyProductId;
                }
            }

            $order = Order::create($orderPayload);

            if ($requestedPoints > 0) {
                $this->loyalty->reserveForOrder($user, $order, $requestedPoints);
            }

            $cartItemIds = $cart->items->pluck('id')->map(fn ($id) => (int) $id)->all();
            CartFulfillmentOptimization::where('cart_id', $cart->id)
                ->whereIn('cart_item_id', $cartItemIds)
                ->whereNull('order_id')
                ->update(['order_id' => $order->id, 'updated_at' => now()]);

            foreach ($cart->items as $cartItem) {
                $line = $this->deliveryLineForShop($checkout, (int) $cartItem->product->shop_id);
                $provider = $this->provider($line);
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'original_product_id' => $cartItem->original_product_id ?: $cartItem->product_id,
                    'fulfilled_product_id' => $cartItem->fulfillment_product_id ?: $cartItem->product_id,
                    'original_shop_id' => $cartItem->original_shop_id ?: $cartItem->product?->shop_id,
                    'fulfilled_shop_id' => $cartItem->fulfillment_shop_id ?: $cartItem->product?->shop_id,
                    'optimization_applied' => (bool) $cartItem->optimization_applied,
                    'optimization_savings' => (float) ($cartItem->optimization_savings ?? 0),
                    'optimization_meta' => $cartItem->optimization_meta,
                    'shop_id' => $cartItem->product->shop_id,
                    'quantity' => (int) $cartItem->quantity,
                    'price' => (float) $cartItem->price,
                    'subtotal' => (float) $cartItem->price * (int) $cartItem->quantity,
                    'logistics_vehicle_code' => $line['vehicle_code'] ?? null,
                    'logistics_vehicle_label' => $line['vehicle_label'] ?? null,
                    'logistics_weight_kg' => $this->weight($cartItem),
                    'logistics_volume_m3' => $this->volume($cartItem),
                ]);

                $this->workflow->initializeOrderItem($orderItem, $provider, [
                    'delivery_delay' => $line['estimated_delay'] ?? null,
                    'delivery_price' => $this->itemDeliveryPrice($cartItem, $line, $cart),
                    'delivery_service_id' => $line['delivery_service_id'] ?? null,
                    'delivery_zone_id' => $line['delivery_zone_id'] ?? null,
                    'checkout_provider_type' => $line['provider_type'] ?? null,
                ]);


            }

            $this->deliveryPersistence->persist($order->fresh(['items']), $checkout, $request);
            $this->commissions->createForOrderByShop($order->fresh(['items.product.shop']));

            // IMPORTANT : ne jamais vider le panier avant un appel réseau
            // PayDunya. Un timeout PHP est fatal et contourne le catch ; dans
            // l'ancienne version les lignes étaient donc perdues définitivement.
            // Pour un virement bancaire la commande est déjà enregistrée : on
            // peut réserver le stock et finaliser le panier immédiatement.
            if (in_array($data['payment_method'], ['bank_transfer', 'cash_on_delivery'], true)) {
                $this->stockReservations->reserve($order->fresh(['items']));
                $this->cartFinalizer->finalize($order->fresh());
            }

            $paymentAmount = $total;
            $paymentType = match ($data['payment_method']) {
                'cash_on_delivery' => 'cod_collection',
                'bank_transfer' => 'bank_transfer_payment',
                default => 'order_payment',
            };
            $paymentMethod = $data['payment_method'];

            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'amount' => $paymentAmount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $user->id,
                'client_payment_method_id' => $savedMethod?->id,
                'operator' => $data['payment_method'] === 'paydunya'
                    ? ($onlineOperator ?: 'paydunya')
                    : ($savedMethod?->operator ?: ($data['payment_method'] === 'bank_transfer' ? 'bank_transfer' : 'ovanie_cod')),
                'mobile_number' => $paymentPhone ?: null,
                'reference' => ($data['payment_method'] === 'cash_on_delivery' ? 'COD-' : 'PAY-')
                    . $order->order_number . '-' . Str::upper(Str::random(6)),
                'type' => $paymentType,
            ]);

            return compact('order', 'payment', 'paymentAmount');
        }, 3);

        /** @var Order $order */
        $order = $result['order'];
        /** @var Payment $payment */
        $payment = $result['payment'];

        // Le web affiche d'abord le formulaire Mobile Money APRÈS avoir créé
        // la commande. Le mobile suit désormais exactement ce parcours :
        // aucune facture PayDunya n'est créée ici. Le client choisit ensuite
        // l'opérateur et le numéro sur l'écran de paiement Flutter, puis
        // startOnlinePayment() initialise réellement PayDunya.
        if ($data['payment_method'] === 'paydunya') {
            return [
                'order' => $order->fresh(['items.product.images', 'payments']),
                'payment' => $payment->fresh(),
                'payment_url' => null,
                'payment_operator' => null,
            ];
        }

        if ($data['payment_method'] === 'bank_transfer') {
            $this->notifyOrderCreated($user, $order);

            return [
                'order' => $order->fresh(['items.product.images', 'payments']),
                'payment' => $payment,
                'payment_url' => null,
                'payment_operator' => $payment->operator,
            ];
        }

        // Paiement à la livraison : même parcours métier que le Web.
        // La commande est confirmée immédiatement, le stock est réservé,
        // le panier est vidé et les vendeurs peuvent commencer la préparation.
        // Aucun paiement PayDunya n'est demandé avant la livraison.
        if ($data['payment_method'] === 'cash_on_delivery') {
            $this->vendorRelease->release($order->fresh(['items']));
            $this->notifyOrderCreated($user, $order);

            return [
                'order' => $order->fresh(['items.product.images', 'payments']),
                'payment' => $payment->fresh(),
                'payment_url' => null,
                'payment_operator' => 'cash_on_delivery',
            ];
        }

        throw ValidationException::withMessages([
            'payment_method' => 'Ce mode de paiement n’est pas pris en charge.',
        ]);
    }

    /**
     * Démarre le paiement en ligne d'une commande mobile déjà créée.
     * Ce second temps correspond à checkout/payment/{order} sur le web.
     */
    public function startOnlinePayment(Request $request, Order $order, array $data): array
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $order->client_id !== (int) $user->id) {
            abort(403);
        }

        if ((string) $order->payment_method !== 'paydunya') {
            throw ValidationException::withMessages([
                'payment_method' => 'Cette commande n’utilise pas le paiement en ligne.',
            ]);
        }

        if (in_array((string) $order->payment_status, ['paid', 'commission_paid'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Le paiement de cette commande est déjà confirmé.',
            ]);
        }

        if (in_array((string) $order->status, ['cancelled', 'canceled'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Cette commande ne peut plus être payée.',
            ]);
        }

        $operator = strtolower(trim((string) ($data['online_operator'] ?? '')));
        $phone = trim((string) ($data['payment_phone'] ?? ''));
        $channel = $operator === 'card'
            ? null
            : $this->paymentMethods->paydunyaChannelForOperator($operator);

        if ($operator !== 'card' && preg_replace('/\D+/', '', $phone) === '') {
            throw ValidationException::withMessages([
                'payment_phone' => 'Renseignez le numéro Mobile Money à débiter.',
            ]);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('method', 'paydunya')
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => 'paydunya',
                'amount' => (float) $order->total_amount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $user->id,
                'operator' => $operator,
                'mobile_number' => $operator === 'card' ? null : $phone,
                'reference' => 'PAY-' . $order->order_number . '-' . Str::upper(Str::random(6)),
                'type' => 'order_payment',
            ]);
        } else {
            $payment->forceFill([
                'status' => Payment::STATUS_PENDING,
                'operator' => $operator,
                'mobile_number' => $operator === 'card' ? null : $phone,
                'failed_at' => null,
            ])->save();
        }

        try {
            // Même séquence que le web : le stock est réservé au moment où le
            // client valide réellement le formulaire de paiement.
            $this->stockReservations->reserve($order->refresh()->load('items'));

            $meta = is_array($order->delivery_pricing_meta) ? $order->delivery_pricing_meta : [];
            data_set($meta, 'checkout.state', 'payment_started');
            data_set($meta, 'checkout.payment_started_at', now()->toDateTimeString());
            data_set($meta, 'checkout.payment_operator', $operator);
            $order->forceFill(['delivery_pricing_meta' => $meta])->save();

            $invoice = $this->paydunya->createOrderInvoice([
                'item_name' => 'Commande OVANIE',
                'description' => 'Paiement de la commande ' . $order->order_number,
                'amount' => (float) $order->total_amount,
                'return_url' => route('paydunya.mobile.return'),
                'cancel_url' => route('paydunya.mobile.cancel'),
                'channel' => $channel,
            ]);

            if (! $invoice->create()) {
                throw new RuntimeException($invoice->response_text ?? 'Impossible d’initialiser le paiement.');
            }

            $token = $this->extractPayDunyaToken($invoice, (string) $payment->reference);
            $payment->forceFill([
                'reference' => $token,
                'operator' => $operator,
                'mobile_number' => $operator === 'card' ? null : $phone,
            ])->save();

            // En production, les paiements Mobile Money passent par SoftPay :
            // PayDunya reste côté serveur et aucune page Chrome n'est ouverte.
            // La carte bancaire conserve la page PCI PayDunya, affichée dans la
            // WebView sécurisée de l'application mobile.
            if ($operator !== 'card') {
                $softPay = $this->paydunya->startSoftPay([
                    'operator' => $operator,
                    'payment_token' => $token,
                    'full_name' => (string) ($user->name ?? $order->customer_name ?? ''),
                    'email' => (string) ($user->email ?? $order->customer_email ?? ''),
                    'phone' => $phone,
                    'orange_otp' => (string) ($data['orange_otp'] ?? ''),
                ]);

                if (empty($softPay['success'])) {
                    Log::warning('PayDunya SoftPay mobile indisponible.', [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                        'operator' => $operator,
                        'provider_message' => trim((string) ($softPay['message'] ?? '')),
                    ]);

                    throw new RuntimeException(
                        trim((string) ($softPay['message'] ?? ''))
                            ?: 'Le canal Mobile Money sélectionné est temporairement indisponible.'
                    );
                }

                $providerUrl = trim((string) ($softPay['url'] ?? ''));

                return [
                    'order' => $order->fresh(['items.product.images', 'payments']),
                    'payment' => $payment->fresh(),
                    'payment_url' => filter_var($providerUrl, FILTER_VALIDATE_URL) ? $providerUrl : null,
                    'payment_operator' => $operator,
                ];
            }

            return [
                'order' => $order->fresh(['items.product.images', 'payments']),
                'payment' => $payment->fresh(),
                'payment_url' => $invoice->getInvoiceUrl(),
                'payment_operator' => $operator,
            ];
        } catch (\Throwable $e) {
            Log::error('Initialisation du paiement mobile OVANIE impossible.', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'operator' => $operator,
                'error' => $e->getMessage(),
            ]);

            $payment->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failed_at' => now(),
            ])->save();

            // Ne pas supprimer la commande ni le panier : le client doit pouvoir
            // corriger son numéro/opérateur et relancer le paiement.
            try {
                $this->stockReservations->releaseAndRestoreCart($order, false);
            } catch (\Throwable $restoreError) {
                Log::warning('Restauration du panier après échec PayDunya incomplète.', [
                    'order_id' => $order->id,
                    'error' => $restoreError->getMessage(),
                ]);
            }

            $meta = is_array($order->delivery_pricing_meta) ? $order->delivery_pricing_meta : [];
            data_set($meta, 'checkout.state', 'awaiting_payment');
            data_set($meta, 'checkout.payment_error', 'initialization_failed');
            $order->forceFill([
                'delivery_pricing_meta' => $meta,
                'payment_status' => 'pending',
                'status' => 'pending',
            ])->save();

            throw ValidationException::withMessages([
                'payment_method' => trim($e->getMessage()) !== ''
                    ? $e->getMessage()
                    : 'Le paiement n’a pas pu être lancé. Votre commande et votre panier sont conservés ; vérifiez le moyen de paiement puis réessayez.',
            ]);
        }
    }


    private function notifyOrderCreated(User $user, Order $order): void
    {
        $this->notifications->send(
            $user,
            'orders',
            'Commande enregistrée',
            'Votre commande ' . $order->order_number . ' a bien été enregistrée. Vous pouvez suivre son évolution depuis votre espace client.',
            [
                'order_id' => $order->id,
                'url' => route('client.orders.show', $order->id),
                'phone' => $order->phone,
            ],
            ['push', 'email', 'sms']
        );
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


    private function validateOrderableCart(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $product = $this->visibility->query(['shop'], true)
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw ValidationException::withMessages(['cart' => 'Un produit du panier n’est plus disponible.']);
            }

            $product->setRelation('shop', \App\Models\Shop::query()
                ->whereKey($product->shop_id)->lockForUpdate()->first());
            if (! $product->shop?->canPublishProducts()) {
                throw ValidationException::withMessages(['cart' => 'La boutique ne peut plus accepter cette commande.']);
            }

            $minimum = max(1, (int) ($product->min_order_quantity ?: 1));
            if ((int) $item->quantity < $minimum) {
                throw ValidationException::withMessages([
                    'cart' => 'La quantité minimale pour ' . $product->name . ' est de ' . $minimum . '.',
                ]);
            }

            if ((int) $product->stock < (int) $item->quantity) {
                throw ValidationException::withMessages([
                    'cart' => 'Stock insuffisant pour le produit ' . $product->name . '.',
                ]);
            }

            $item->setRelation('product', $product);
        }
    }

    private function addressPayload(Request $request): array
    {
        return [
            'delivery_zone' => $request->input('delivery_zone'),
            'delivery_city' => $request->input('delivery_city'),
            'delivery_commune' => $request->input('delivery_commune'),
            'delivery_quartier' => $request->input('delivery_quartier'),
            'address' => $request->input('address'),
            'latitude' => $request->input('delivery_latitude'),
            'longitude' => $request->input('delivery_longitude'),
        ];
    }

    private function deliveryLineForShop(array $checkout, int $shopId): array
    {
        return collect($checkout['delivery_breakdown'] ?? [])
            ->first(fn ($line) => (int) ($line['shop_id'] ?? 0) === $shopId, []);
    }

    private function provider(array $line): string
    {
        return match ($line['provider_type'] ?? null) {
            OrderWorkflowService::PROVIDER_PICKUP => OrderWorkflowService::PROVIDER_PICKUP,
            OrderWorkflowService::PROVIDER_SELLER => OrderWorkflowService::PROVIDER_SELLER,
            OrderWorkflowService::PROVIDER_PARTNER => OrderWorkflowService::PROVIDER_PARTNER,
            default => OrderWorkflowService::PROVIDER_OVANIE,
        };
    }

    private function itemDeliveryPrice($cartItem, array $line, Cart $cart): float
    {
        $shopId = (int) $cartItem->product->shop_id;
        $shopItems = $cart->items->filter(fn ($item) => (int) ($item->product?->shop_id ?? 0) === $shopId);
        $shopSubtotal = max(1, (float) $shopItems->sum(fn ($item) => (float) $item->price * (int) $item->quantity));
        $itemSubtotal = (float) $cartItem->price * (int) $cartItem->quantity;

        return round((float) ($line['delivery_fee'] ?? 0) * ($itemSubtotal / $shopSubtotal), 2);
    }

    private function weight($cartItem): float
    {
        return round((float) ($cartItem->product?->weight_kg ?? $cartItem->product?->weight ?? 0)
            * (int) $cartItem->quantity, 3);
    }

    private function volume($cartItem): float
    {
        $product = $cartItem->product;
        $volume = (float) ($product?->volume_m3 ?? $product?->volume ?? 0);

        if ($volume <= 0 && $product?->length_cm && $product?->width_cm && $product?->height_cm) {
            $volume = ((float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm) / 1_000_000;
        }

        return round($volume * (int) $cartItem->quantity, 4);
    }
}
