<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\CartFulfillmentOptimization;
use App\Models\Shipment;
use App\Services\PayDunyaService;
use App\Services\SmsService;
use App\Services\CartFulfillmentOptimizer;
use App\Services\CartPriceSyncService;
use App\Services\CheckoutSummaryService;
use App\Services\CheckoutPaymentOptionsService;
use App\Services\CommissionService;
use App\Services\LoyaltyService;
use App\Services\CheckoutAddressResolver;
use App\Services\PaymentMethodResolver;
use App\Services\OvanieNotificationDispatcher;
use App\Services\OrderFinancialSummaryService;
use App\Services\OrderPaymentEligibilityService;
use App\Services\OrderSettlementService;
use App\Services\DeliveryPricingEngine;
use App\Services\VendorOrderReleaseService;
use App\Services\OrderWorkflowService;
use App\Services\OrderStockReservationService;
use App\Services\CheckoutCartFinalizerService;
use App\Services\GiftCardService;
use App\Services\GuestCartService;
use App\Services\Geo\GeocodingService;
use Barryvdh\DomPDF\Facade\Pdf;

class CheckoutController extends Controller
{
    private const PAYDUNYA_MAX_AMOUNT = 3000000;

    public function selection(Request $request, GuestCartService $guestCart): RedirectResponse
    {
        $validated = $request->validate([
            'cart_item_ids' => 'required|array|min:1',
            'cart_item_ids.*' => 'integer',
        ]);

        $selectedIds = collect($validated['cart_item_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (! $request->user()) {
            $snapshot = $guestCart->snapshot($request);
            $availableProductIds = $snapshot['cart']->items
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id);

            $ownedProductIds = $selectedIds
                ->intersect($availableProductIds)
                ->values();

            if ($ownedProductIds->isEmpty()) {
                return redirect()->route('cart.index')->with('error', 'Selectionnez au moins un produit pour passer commande.');
            }

            $request->session()->put(GuestCartService::CHECKOUT_PRODUCT_IDS_KEY, $ownedProductIds->all());
            $request->session()->put('url.intended', route('checkout.index'));

            return redirect()->route('login')->with(
                'info',
                'Connectez-vous ou creez un compte client pour passer votre commande. Un compte vendeur peut aussi acheter avec le meme identifiant.'
            );
        }

        $cart = Cart::with('items')
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Votre panier est vide.');
        }

        $ownedIds = $cart->items
            ->whereIn('id', $selectedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ownedIds->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Selectionnez au moins un produit pour passer commande.');
        }

        $request->session()->put('checkout_cart_item_ids', $ownedIds->all());

        return redirect()->route('checkout.index');
    }

    public function index(
        Request $request,
        CommissionService $commissions,
        CartPriceSyncService $priceSync,
        CheckoutAddressResolver $addressResolver
    ): View|RedirectResponse
    {
        $cart = Cart::with('items.product.shop')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Votre panier est vide.');
        }

        $selectedIds = $this->selectedCartItemIds($request);
        $selectionRequired = (bool) $request->session()->pull(GuestCartService::CHECKOUT_SELECTION_REQUIRED_KEY, false);

        if ($selectionRequired && empty($selectedIds)) {
            $request->session()->forget('checkout_cart_item_ids');
            return redirect()->route('cart.index')->with('error', 'La sélection choisie n’est plus disponible. Vérifiez votre panier avant de continuer.');
        }

        if ($selectedIds) {
            $this->applyCheckoutSelection($cart, $selectedIds);

            if ($cart->items->isEmpty()) {
                $request->session()->forget('checkout_cart_item_ids');
                return redirect()->route('cart.index')->with('error', 'Votre selection de panier n est plus disponible.');
            }
        }

        $priceSyncResult = $priceSync->sync($cart);
        if ($priceSyncResult['changed']) {
            $request->session()->flash('error', implode(' ', $priceSyncResult['messages']) . ' Vérifiez les montants avant de poursuivre.');
        }

        // L'ouverture du checkout ne lance aucun calcul logistique : le tarif de
        // livraison est calculé uniquement après une adresse complète ou un retrait.
        $subtotal = (float) $cart->items->sum(function ($item) {
            $price = (float) ($item->price ?: ($item->product?->final_price ?? $item->product?->price ?? 0));
            return $price * (int) $item->quantity;
        });
        $commission = $commissions->commissionFromPublicItems($cart->items);

        $settings = [
            'bankName' => Setting::getValue('bankName', 'NSIA Banque'),
            'bankAccountName' => Setting::getValue('bankAccountName', 'OVANIE SARL'),
            'bankAccountNumber' => Setting::getValue('bankAccountNumber', '1234567890'),
        ];

        // Source de vérité commune Web / API mobile pour les modes de paiement.
        // Le total final sera de nouveau contrôlé dans store() après calcul de la livraison.
        $paymentOptions = app(CheckoutPaymentOptionsService::class)->forCart($cart, 0);

        $savedAddresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();
        $defaultSavedAddress = $savedAddresses->firstWhere('is_default', true) ?? $savedAddresses->first();
        $savedAddressSnapshots = $savedAddresses
            ->map(fn ($address) => $addressResolver->snapshot($address))
            ->values();
        $defaultSavedAddressSnapshot = $defaultSavedAddress
            ? $addressResolver->snapshot($defaultSavedAddress)
            : null;
        $savedPaymentMethods = $request->user()
            ->paymentMethods()
            ->defaultFirst()
            ->get();
        $loyaltyService = app(LoyaltyService::class);
        $loyaltyPoints = (int) ($request->user()->loyalty_points ?? 0);
        $loyaltyPointValue = $loyaltyService->pointValueXof();
        $maxLoyaltyPoints = $loyaltyService->maxRedeemablePoints($request->user(), $subtotal);

        return view('checkout', compact(
            'cart',
            'subtotal',
            'commission',
            'settings',
            'paymentOptions',
            'savedAddresses',
            'defaultSavedAddress',
            'savedAddressSnapshots',
            'defaultSavedAddressSnapshot',
            'savedPaymentMethods',
            'loyaltyPoints',
            'loyaltyPointValue',
            'maxLoyaltyPoints'
        ));
    }

    public function store(
        Request $request,
        CheckoutSummaryService $checkoutSummary,
        CartFulfillmentOptimizer $optimizer,
        OrderStockReservationService $stockReservations,
        CommissionService $commissions,
        CartPriceSyncService $priceSync,
        LoyaltyService $loyaltyService,
        CheckoutCartFinalizerService $cartFinalizer,
        GiftCardService $giftCards
    ): RedirectResponse
    {
        app(CheckoutAddressResolver::class)->applyToRequest($request);
        $this->resolveCheckoutGeoIfPossible($request);

        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'delivery_recipient_name' => ['nullable', 'string', 'max:255'],
            'delivery_recipient_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp_phone' => ['required', 'string', 'max:30'],
            'delivery_destination_type' => ['required', Rule::in(['home', 'pickup'])],
            'address' => [
                Rule::requiredIf(fn () => $request->input('delivery_destination_type') !== 'pickup'),
                'nullable',
                'string',
                'max:500',
            ],
            'delivery_zone' => ['required', Rule::in(['abidjan', 'interieur'])],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            'delivery_commune' => [
                Rule::requiredIf(fn () => $request->input('delivery_destination_type') !== 'pickup'
                    && $request->input('delivery_zone') === 'abidjan'),
                'nullable',
                'string',
                'max:100',
            ],
            'delivery_quartier' => ['nullable', 'string', 'max:150'],
            'delivery_city' => [
                Rule::requiredIf(fn () => $request->input('delivery_destination_type') !== 'pickup'
                    && $request->input('delivery_zone') === 'interieur'),
                'nullable',
                'string',
                'max:150',
            ],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'delivery_geo_source' => ['nullable', 'string', 'max:50'],
            'saved_address_id' => ['nullable', 'integer'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'gift_card_code' => ['nullable', 'string', 'max:64'],
            'gift_card_pin' => ['nullable', 'string', 'min:4', 'max:12'],
            'payment_method' => ['required', Rule::in(['paydunya', 'bank_transfer', 'cash_on_delivery'])],
            'bank_reference' => ['required_if:payment_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_receipt' => ['required_if:payment_method,bank_transfer', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        app(\App\Services\Geo\DeliveryPointGuard::class)->validateOperationalPoint($request);

        if ($request->payment_method === 'paydunya') {
            $this->cancelPreviousPendingOnlineDraft(
                $request,
                $stockReservations,
                $loyaltyService,
                $cartFinalizer
            );
        }

        // Les coordonnées du destinataire, du compte client et du moyen de
        // paiement sont trois contextes distincts. Le contact de livraison saisi
        // dans la modale ne doit jamais écraser le profil WhatsApp du client.
        $deliveryRecipientName = trim((string) (
            $request->input('delivery_recipient_name')
            ?: $request->input('full_name')
        ));
        $deliveryRecipientPhone = trim((string) (
            $request->input('delivery_recipient_phone')
            ?: $request->input('whatsapp_phone')
            ?: $request->input('phone')
        ));
        $customerName = trim((string) ($request->user()->name ?: $request->input('full_name')));
        $contactPhone = trim((string) (
            $request->user()->whatsapp_phone
            ?: $request->user()->phone
            ?: $deliveryRecipientPhone
        ));

        $requestedLoyaltyPoints = (int) $request->input('loyalty_points', 0);

        $cart = Cart::with('items.product.shop')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Votre panier est vide.');
        }

        $selectedIds = $this->selectedCartItemIds($request);
        if ($selectedIds) {
            $this->applyCheckoutSelection($cart, $selectedIds);

            if ($cart->items->isEmpty()) {
                $request->session()->forget('checkout_cart_item_ids');
                return redirect()->route('cart.index')->with('error', 'Votre selection de panier n est plus disponible.');
            }
        }

        $priceSyncResult = $priceSync->sync($cart);
        if ($priceSyncResult['changed']) {
            return redirect()->route('checkout.index')
                ->with('error', implode(' ', $priceSyncResult['messages']) . ' Vérifiez les nouveaux montants avant de valider la commande.')
                ->withInput();
        }

        $preview = $optimizer->previewForAddress($cart, $this->optimizationAddressPayload($request));
        $checkout = $checkoutSummary->build($preview['cart'], $request);

        if (! empty($checkout['delivery_quote_required'])) {
            return redirect()->route('checkout.index')
                ->with('error', DeliveryPricingEngine::UNKNOWN_MESSAGE)
                ->withInput();
        }

        $unavailableGroup = collect($checkout['groups'])->first(fn ($group) => empty($group['delivery_available']));
        if ($unavailableGroup) {
            return redirect()->route('checkout.index')
                ->with('error', 'Aucune option de livraison n’est disponible pour un ou plusieurs produits de votre panier à cette adresse.')
                ->withInput();
        }

        $subtotal = $checkout['subtotal'];
        $deliveryFee = $checkout['delivery_fee'];
        $totalBeforeLoyalty = $checkout['total'];
        $maxRedeemablePoints = $loyaltyService->maxRedeemablePoints($request->user(), $subtotal);

        if ($requestedLoyaltyPoints > $maxRedeemablePoints) {
            return redirect()->route('checkout.index')
                ->with('error', "Vous pouvez utiliser au maximum {$maxRedeemablePoints} points sur cette commande.")
                ->withInput();
        }

        $loyaltyDiscount = $loyaltyService->calculateDiscount($requestedLoyaltyPoints);
        $total = max(0, $totalBeforeLoyalty - $loyaltyDiscount);
        $commission = $checkout['commission'];

        $giftCard = null;
        $giftCardAmount = 0.0;
        $amountDue = (float) $total;

        if ($request->filled('gift_card_code')) {
            if (! $request->filled('gift_card_pin')) {
                throw ValidationException::withMessages([
                    'gift_card_pin' => 'Le PIN de la carte cadeau est obligatoire.',
                ]);
            }

            $giftCard = $giftCards->resolveForCheckout(
                $request->user(),
                (string) $request->input('gift_card_code'),
                (string) $request->input('gift_card_pin')
            );

            $giftCardAmount = min($giftCard->availableBalance(), (float) $total);
            $amountDue = max(0, round((float) $total - $giftCardAmount, 2));

            // Pour éviter une carte bloquée pendant des jours, le paiement mixte
            // est limité au paiement en ligne immédiat. Une carte qui couvre 100 %
            // de la commande n'a besoin d'aucun paiement externe.
            if ($amountDue > 0 && $request->payment_method !== 'paydunya') {
                throw ValidationException::withMessages([
                    'payment_method' => 'Avec une carte cadeau et un reste à payer, choisissez le paiement en ligne.',
                ]);
            }
        }

        if ($amountDue > 0 && $request->payment_method === 'paydunya' && ! config('paydunya.enabled')) {
            throw ValidationException::withMessages([
                'payment_method' => 'Le paiement en ligne est temporairement indisponible pour le reste à payer.',
            ]);
        }

        if ($amountDue > 0) {
            // Règle partagée Web/API : Laravel décide des moyens de paiement
            // disponibles pour ce panier. Le mobile ne possède pas une logique parallèle.
            app(CheckoutPaymentOptionsService::class)->assertAllowed(
                $preview['cart'],
                (string) $request->payment_method,
                (float) $amountDue
            );
        }

        $receiptPath = null;

        if ($request->payment_method === 'bank_transfer' && $request->hasFile('bank_receipt')) {
            $receiptPath = $request->file('bank_receipt')->store('private-documents/order-evidence', 'local');
        }

        $orderPayload = [
            'order_number' => 'OVANIE-' . strtoupper(Str::random(8)),
            'invoice_number' => $this->generateInvoiceNumber(),
            'client_id' => $request->user()->id,
            'customer_name' => $customerName,
            'phone' => $contactPhone,
            'address' => $request->address ?: 'Retrait géré par OVANIE',
            'delivery_address' => $request->address ?: 'Retrait géré par OVANIE',
            'payment_method' => $amountDue <= 0 && $giftCardAmount > 0 ? 'gift_card' : $request->payment_method,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'delivery_breakdown' => $checkout['delivery_breakdown'],
            'selected_carriers' => $checkout['selected_carriers'],
            'total_amount' => $total,
            'loyalty_points_used' => $requestedLoyaltyPoints,
            'loyalty_discount' => $loyaltyDiscount,
            'gift_card_id' => $giftCard?->id,
            'gift_card_amount' => $giftCardAmount,
            'status' => $amountDue <= 0 && $giftCardAmount > 0
                ? 'confirmed'
                : ($request->payment_method === 'cash_on_delivery' ? 'confirmed' : 'pending'),
            'bank_reference' => $request->bank_reference ?? null,
            'receipt_path' => $receiptPath,
            'payment_status' => $amountDue <= 0 && $giftCardAmount > 0 ? 'paid' : 'pending',
            'delivery_zone' => $request->delivery_zone,
            'delivery_destination_type' => $request->delivery_destination_type,
            'delivery_site_name' => null,
            'delivery_recipient_name' => $deliveryRecipientName,
            'delivery_recipient_phone' => $deliveryRecipientPhone,
            'delivery_commune' => $request->delivery_commune,
            'delivery_quartier' => $request->delivery_quartier,
            'delivery_city' => $request->delivery_city,
            'delivery_started_at' => $this->getProcessingDate(),
            'delivery_min_date' => $this->getDeliveryMinDate($request->delivery_zone),
            'delivery_max_date' => $this->getDeliveryMaxDate($request->delivery_zone),
            'delivery_note' => $this->buildDeliveryNote($request),
            'delivery_latitude' => $this->safeRequestCoordinate($request, 'delivery_latitude'),
            'delivery_longitude' => $this->safeRequestCoordinate($request, 'delivery_longitude'),
            'delivery_geo_accuracy' => $request->filled('delivery_geo_accuracy') ? round((float) $request->delivery_geo_accuracy, 2) : null,
            'delivery_geo_source' => $request->input('delivery_geo_source'),
        ];

        if (Schema::hasColumn('orders', 'delivery_lat')) {
            $orderPayload['delivery_lat'] = $this->safeDeliveryCoordinate($request, 'delivery_latitude', 'delivery_lat');
        }

        if (Schema::hasColumn('orders', 'delivery_lng')) {
            $orderPayload['delivery_lng'] = $this->safeDeliveryCoordinate($request, 'delivery_longitude', 'delivery_lng');
        }

        try {
            DB::beginTransaction();

            $checkoutUser = \App\Models\User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $checkoutUser->deletion_in_progress || $checkoutUser->status === 'suspended') {
                throw new \RuntimeException('Ce compte ne peut plus créer de commande.');
            }

            $cart = Cart::with('items.product.shop')
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new \RuntimeException('Votre panier est vide.');
            }

            if ($selectedIds) {
                $this->applyCheckoutSelection($cart, $selectedIds);

                if ($cart->items->isEmpty()) {
                    throw new \RuntimeException('Votre selection de panier n est plus disponible.');
                }
            }

            $optimizer->optimizeForAddress($cart, $this->optimizationAddressPayload($request));
            $cart->loadMissing('items.product.shop');

            foreach ($cart->items as $cartItem) {
                $lockedProduct = \App\Models\Product::query()
                    ->whereKey($cartItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedProduct
                    || $lockedProduct->is_archived
                    || ! $lockedProduct->is_active
                    || ! in_array($lockedProduct->status, ['actif', 'active', 'approved'], true)) {
                    throw new \RuntimeException('Un produit du panier est indisponible.');
                }

                // Serialize checkout with a shop logistics change: the quote and
                // the persisted order snapshot must use the same current mode.
                $lockedProduct->setRelation('shop', \App\Models\Shop::query()
                    ->whereKey($lockedProduct->shop_id)->lockForUpdate()->first());

                if (! $lockedProduct->shop?->canPublishProducts()) {
                    throw new \RuntimeException('Le produit ' . $lockedProduct->name . ' n’est plus disponible à la commande.');
                }

                $minimumQuantity = max(1, (int) ($lockedProduct->min_order_quantity ?: 1));
                if ((int) $cartItem->quantity < $minimumQuantity) {
                    throw new \RuntimeException('La quantité minimale pour ' . $lockedProduct->name . ' est de ' . $minimumQuantity . '.');
                }

                if ((int) $lockedProduct->stock < (int) $cartItem->quantity) {
                    throw new \RuntimeException('Stock insuffisant pour le produit ' . $lockedProduct->name . '.');
                }

                $cartItem->setRelation('product', $lockedProduct->loadMissing('shop'));

                $priceError = $priceSync->validationError($cartItem, $lockedProduct, (int) $request->user()->id);
                if ($priceError) {
                    throw new \RuntimeException($priceError);
                }
            }

            $checkout = $checkoutSummary->build($cart, $request);
            if (! empty($checkout['delivery_quote_required'])) {
                throw new \RuntimeException(DeliveryPricingEngine::UNKNOWN_MESSAGE);
            }

            $subtotal = $checkout['subtotal'];
            $deliveryFee = $checkout['delivery_fee'];
            $totalBeforeLoyalty = $checkout['total'];
            $maxRedeemablePoints = $loyaltyService->maxRedeemablePoints($request->user(), $subtotal);

            if ($requestedLoyaltyPoints > $maxRedeemablePoints) {
                throw new \RuntimeException("Vous pouvez utiliser au maximum {$maxRedeemablePoints} points sur cette commande.");
            }

            $loyaltyDiscount = $loyaltyService->calculateDiscount($requestedLoyaltyPoints);
            $total = max(0, $totalBeforeLoyalty - $loyaltyDiscount);
            $commission = $checkout['commission'];

            // Revalidation sous verrou : le solde de la carte peut avoir changé
            // entre l'affichage du checkout et le clic final.
            $giftCard = null;
            $giftCardAmount = 0.0;
            $amountDue = (float) $total;

            if ($request->filled('gift_card_code')) {
                $giftCard = $giftCards->resolveForCheckout(
                    $checkoutUser,
                    (string) $request->input('gift_card_code'),
                    (string) $request->input('gift_card_pin'),
                    true
                );
                $giftCardAmount = min($giftCard->availableBalance(), (float) $total);
                $amountDue = max(0, round((float) $total - $giftCardAmount, 2));

                if ($amountDue > 0 && $request->payment_method !== 'paydunya') {
                    throw new \RuntimeException('Avec une carte cadeau et un reste à payer, le paiement en ligne est obligatoire.');
                }
            }

            if ($amountDue > 0) {
                app(CheckoutPaymentOptionsService::class)->assertAllowed(
                    $cart,
                    (string) $request->payment_method,
                    (float) $amountDue
                );
            }

            $orderPayload['subtotal'] = $subtotal;
            $orderPayload['delivery_fee'] = $deliveryFee;
            $orderPayload['delivery_fee_total'] = $deliveryFee;
            $orderPayload['ovanie_delivery_fee'] = collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_OVANIE)->sum('delivery_fee');
            $orderPayload['seller_delivery_fee'] = collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_SELLER)->sum('delivery_fee');
            $orderPayload['partner_delivery_fee'] = collect($checkout['delivery_breakdown'])->where('provider_type', OrderWorkflowService::PROVIDER_PARTNER)->sum('delivery_fee');
            $orderPayload['delivery_pricing_status'] = 'calculated';
            $orderPayload['delivery_pricing_meta'] = [
                'calculated_at' => now()->toDateTimeString(),
                'logistics_groups' => $checkout['logistics_groups'] ?? [],
            ];
            $orderPayload['delivery_breakdown'] = $checkout['delivery_breakdown'];
            $orderPayload['selected_carriers'] = $checkout['selected_carriers'];
            $orderPayload['total_amount'] = $total;
            $orderPayload['loyalty_points_used'] = $requestedLoyaltyPoints;
            $orderPayload['loyalty_discount'] = $loyaltyDiscount;
            $orderPayload['gift_card_id'] = $giftCard?->id;
            $orderPayload['gift_card_amount'] = $giftCardAmount;
            $orderPayload['payment_method'] = $amountDue <= 0 && $giftCardAmount > 0 ? 'gift_card' : $request->payment_method;
            $orderPayload['payment_status'] = $amountDue <= 0 && $giftCardAmount > 0 ? 'paid' : 'pending';
            $orderPayload['status'] = $amountDue <= 0 && $giftCardAmount > 0
                ? 'confirmed'
                : ($request->payment_method === 'cash_on_delivery' ? 'confirmed' : 'pending');

            $orderedCartItemIds = $cart->items->pluck('id')->map(fn ($id) => (int) $id)->all();

            $checkoutMeta = is_array($orderPayload['delivery_pricing_meta'] ?? null)
                ? $orderPayload['delivery_pricing_meta']
                : [];

            data_set($checkoutMeta, 'checkout', [
                'state' => $amountDue > 0 && $request->payment_method === 'paydunya' ? 'awaiting_payment' : 'confirmed',
                'cart_id' => (int) $cart->id,
                'cart_item_ids' => $orderedCartItemIds,
                'cart_policy' => $amountDue > 0 && $request->payment_method === 'paydunya'
                    ? CheckoutCartFinalizerService::POLICY_PRESERVE_UNTIL_PAYMENT
                    : CheckoutCartFinalizerService::POLICY_CLEAR_IMMEDIATELY,
                'cart_status' => $amountDue > 0 && $request->payment_method === 'paydunya' ? 'preserved' : 'pending_clear',
                'created_at' => now()->toDateTimeString(),
            ]);

            $orderPayload['delivery_pricing_meta'] = $checkoutMeta;

            // Compatibilité avec l'ancienne colonne orders.product_id encore obligatoire
            // dans certaines bases. Les lignes réelles de la commande restent stockées
            // dans order_items ; cette valeur conserve uniquement la compatibilité du schéma.
            if (Schema::hasColumn('orders', 'product_id')) {
                $legacyProductId = $cart->items->first()?->product_id;

                if ($legacyProductId) {
                    $orderPayload['product_id'] = (int) $legacyProductId;
                }
            }

            $order = Order::create($orderPayload);

            if ($giftCard && $giftCardAmount > 0) {
                $giftCards->holdForOrder($giftCard, $order, $checkoutUser, $giftCardAmount);
            }

            if ($requestedLoyaltyPoints > 0) {
                $loyaltyService->reserveForOrder($checkoutUser, $order, $requestedLoyaltyPoints);
            }

            if ($request->payment_method === 'cash_on_delivery' && $amountDue > 0) {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => Payment::METHOD_CASH_ON_DELIVERY,
                    'amount' => $amountDue,
                    'status' => Payment::STATUS_PENDING,
                    'user_id' => $request->user()->id,
                    'operator' => 'ovanie_cod',
                    'mobile_number' => $deliveryRecipientPhone ?: $contactPhone,
                    'reference' => 'COD-' . $order->order_number,
                    'type' => 'cod_collection',
                    'provider_payload' => [
                        'collection_status' => 'awaiting_delivery',
                        'created_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            CartFulfillmentOptimization::where('cart_id', $cart->id)
                ->whereIn('cart_item_id', $orderedCartItemIds)
                ->whereNull('order_id')
                ->update(['order_id' => $order->id, 'updated_at' => now()]);

            $workflow = app(OrderWorkflowService::class);

            foreach ($cart->items as $item) {
                $deliveryLine = $this->deliveryLineForShop($checkout, (int) $item->product->shop_id);
                $provider = $this->deliveryProviderForCartItem($item, $deliveryLine);
                $deliveryPrice = $this->deliveryPriceForCartItem($item, $deliveryLine, $cart);
                $delay = $deliveryLine['estimated_delay'] ?? null;

                $orderItemPayload = [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'original_product_id' => $item->original_product_id ?: $item->product_id,
                    'fulfilled_product_id' => $item->fulfillment_product_id ?: $item->product_id,
                    'original_shop_id' => $item->original_shop_id ?: $item->product?->shop_id,
                    'fulfilled_shop_id' => $item->fulfillment_shop_id ?: $item->product?->shop_id,
                    'optimization_applied' => (bool) $item->optimization_applied,
                    'optimization_savings' => (float) ($item->optimization_savings ?? 0),
                    'optimization_meta' => $item->optimization_meta,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->price * $item->quantity,
                    'shop_id' => $item->product->shop_id,
                ];

                $orderItemOptionalColumns = [
                    'vendor_visible_at' => null,
                    'logistics_vehicle_code' => $deliveryLine['vehicle_code'] ?? null,
                    'logistics_vehicle_label' => $deliveryLine['vehicle_label'] ?? $deliveryLine['recommended_vehicle'] ?? null,
                    'logistics_weight_kg' => $this->cartItemWeightKg($item),
                    'logistics_volume_m3' => $this->cartItemVolumeM3($item),
                ];

                foreach ($orderItemOptionalColumns as $column => $value) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $orderItemPayload[$column] = $value;
                    }
                }

                $orderItem = OrderItem::create($orderItemPayload);

                $workflow->initializeOrderItem($orderItem, $provider, [
                    'delivery_delay' => $delay,
                    'delivery_price' => $deliveryPrice,
                    'delivery_service_id' => $deliveryLine['delivery_service_id'] ?? null,
                    'delivery_zone_id' => $deliveryLine['delivery_zone_id'] ?? null,
                    'checkout_provider_type' => $deliveryLine['provider_type'] ?? null,
                ]);
            }

            $this->persistDeliverySelectionsAndShipments($order, $checkout, $request);

            $this->generateCommissions($order, $commissions);

            // Si la carte cadeau couvre 100 % de la commande, le paiement est
            // confirmé immédiatement côté OVANIE et aucun prestataire externe n'est appelé.
            if ($giftCardAmount > 0 && $amountDue <= 0) {
                $stockReservations->reserve($order);
                $giftCards->captureForOrder($order);

                Payment::create([
                    'order_id' => $order->id,
                    'method' => 'gift_card',
                    'type' => 'order_payment',
                    'amount' => $giftCardAmount,
                    'status' => Payment::STATUS_ESCROW_HELD,
                    'user_id' => $checkoutUser->id,
                    'reference' => 'GIFT-' . $order->order_number . '-' . strtoupper(Str::random(6)),
                    'paid_at' => now(),
                    'provider_payload' => [
                        'gift_card_id' => $giftCard->id,
                        'gift_card_amount' => $giftCardAmount,
                    ],
                ]);

                $order->items()->update(['is_paid' => true]);
                Commission::where('order_id', $order->id)->update(['status' => 'payé']);
                $stockReservations->commit($order->refresh());
                $cartFinalizer->finalize($order->refresh());
                $order->forceFill(['payment_status' => 'paid', 'status' => 'confirmed'])->save();
            } elseif ($request->payment_method !== 'paydunya') {
                // Les autres modes non PayDunya créent immédiatement une commande opérationnelle.
                $stockReservations->reserve($order);
                $cartFinalizer->finalize($order);
            }

            DB::commit();
            $request->session()->forget('checkout_cart_item_ids');

            if ($request->payment_method === 'paydunya' && $amountDue > 0) {
                $request->session()->put('checkout_pending_order_id', $order->id);
            } else {
                $request->session()->forget('checkout_pending_order_id');
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($receiptPath) {
                Storage::disk('public')->delete($receiptPath);
            }

            \Log::warning('Checkout OVANIE failed', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            $customerMessage = $e instanceof \Illuminate\Database\QueryException
                ? 'Impossible de finaliser la commande pour le moment. Votre panier a été conservé. Réessayez dans quelques instants.'
                : ($e->getMessage() ?: 'Impossible de finaliser la commande. Votre panier a été conservé.');

            return redirect()->route('checkout.index')
                ->with('error', $customerMessage)
                ->withInput();
        }

        if ($giftCardAmount > 0 && $amountDue <= 0) {
            app(VendorOrderReleaseService::class)->release($order->fresh());
            app(\App\Services\VendorPayoutService::class)->generateForOrder($order->fresh(), 'gift_card');
            $this->sendOrderCreatedSms($order, $contactPhone);

            return redirect()->route('order.success', $order->id)
                ->with('success', 'Commande confirmée et payée intégralement avec votre carte cadeau OVANIE.');
        }

        if ($request->payment_method === 'paydunya') {
            return redirect()->route('checkout.payment.show', $order);
        }

        if ($request->payment_method === 'cash_on_delivery') {
            app(VendorOrderReleaseService::class)->release($order->fresh());
            $this->sendOrderCreatedSms($order, $contactPhone);

            return redirect()->route('order.success', $order->id)
                ->with('success', 'Commande confirmée. Le montant sera à régler au moment de la livraison.');
        }

        if ($request->payment_method === 'bank_transfer') {
            Payment::create([
                'order_id' => $order->id,
                'method' => 'bank_transfer',
                'type' => 'bank_transfer_payment',
                'amount' => $amountDue,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $request->user()->id,
                'reference' => $request->bank_reference,
            ]);

            $this->sendOrderCreatedSms($order, $contactPhone);

            return redirect()->route('order.success', $order->id)
                ->with('success', 'Commande enregistrée. Paiement en attente de validation.');
        }

        $this->sendOrderCreatedSms($order, $contactPhone);

        return redirect()->route('order.success', $order->id);
    }

    public function showOnlinePayment(Request $request, Order $order): View|RedirectResponse
    {
        if ((int) $order->client_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($order->payment_method !== 'paydunya') {
            return redirect()->route('order.success', $order->id);
        }

        if (in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
            return redirect()->route('order.success', $order->id)
                ->with('success', 'Le paiement de cette commande est déjà confirmé.');
        }

        if (in_array($order->status, ['cancelled', 'canceled'], true)
            || in_array($order->payment_status, ['failed', 'cancelled', 'canceled'], true)) {
            return redirect()->route('orders.index')
                ->with('error', 'Cette commande ne peut plus être payée.');
        }

        $amountDue = max(0, round((float) $order->total_amount - (float) ($order->gift_card_amount ?? 0), 2));
        if ($amountDue <= 0) {
            return redirect()->route('order.success', $order->id);
        }

        $savedPaymentMethods = $request->user()
            ->paymentMethods()
            ->defaultFirst()
            ->get();
        $defaultSavedPaymentMethod = $savedPaymentMethods->firstWhere('is_default', true)
            ?? $savedPaymentMethods->first();
        $onlineOperators = app(\App\Services\OvanieReferenceDataService::class)->checkoutOperators();
        $allowedOperatorCodes = collect($onlineOperators)->pluck('code')->map(fn ($code) => (string) $code)->all();
        $defaultOperator = old('online_operator', $defaultSavedPaymentMethod?->operator ?? '');
        if ($defaultOperator !== '' && ! in_array($defaultOperator, $allowedOperatorCodes, true)) {
            $defaultOperator = '';
        }
        $defaultPhone = old(
            'payment_phone',
            $defaultSavedPaymentMethod?->phone
                ?? $request->user()->whatsapp_phone
                ?? $request->user()->phone
                ?? $order->phone
                ?? ''
        );

        return view('checkout-payment', compact(
            'order',
            'savedPaymentMethods',
            'defaultSavedPaymentMethod',
            'defaultOperator',
            'defaultPhone',
            'amountDue',
            'onlineOperators'
        ));
    }

    public function startOnlinePayment(
        Request $request,
        Order $order,
        PayDunyaService $paydunya
    ): RedirectResponse {
        if ((int) $order->client_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($order->payment_method !== 'paydunya') {
            return redirect()->route('order.success', $order->id);
        }

        if (in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
            return redirect()->route('order.success', $order->id)
                ->with('success', 'Le paiement de cette commande est déjà confirmé.');
        }

        if (in_array($order->status, ['cancelled', 'canceled'], true)
            || in_array($order->payment_status, ['failed', 'cancelled', 'canceled'], true)) {
            return redirect()->route('orders.index')
                ->with('error', 'Cette commande ne peut plus être payée.');
        }

        $allowedOperators = collect(app(\App\Services\OvanieReferenceDataService::class)->checkoutOperators())
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $validated = $request->validate([
            'client_payment_method_id' => ['nullable', 'integer'],
            'online_operator' => ['required', Rule::in($allowedOperators)],
            'payment_phone' => [
                Rule::requiredIf(fn () => $request->input('online_operator') !== 'card'),
                'nullable',
                'string',
                'max:30',
            ],
            'orange_otp' => [
                Rule::requiredIf(fn () => strtolower((string) config('paydunya.mode', 'test')) !== 'test'
                    && $request->input('online_operator') === 'orange'),
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $amountDue = max(0, round((float) $order->total_amount - (float) ($order->gift_card_amount ?? 0), 2));
        if ($amountDue <= 0) {
            return redirect()->route('order.success', $order->id);
        }

        if ($amountDue > self::PAYDUNYA_MAX_AMOUNT) {
            return redirect()->route('checkout.payment.show', $order)
                ->with('error', 'Le paiement en ligne est limité à 3 000 000 FCFA.');
        }

        $paymentMethodResolver = app(PaymentMethodResolver::class);
        $savedPaymentMethod = $paymentMethodResolver->resolve(
            $request->user(),
            $request->filled('client_payment_method_id')
                ? (int) $request->input('client_payment_method_id')
                : null,
            'paydunya'
        );

        $operator = trim((string) $validated['online_operator']);
        $phone = trim((string) (($validated['payment_phone'] ?? '') ?: $savedPaymentMethod?->phone));
        $orangeOtp = trim((string) ($validated['orange_otp'] ?? ''));

        try {
            app(OrderStockReservationService::class)->reserve($order->refresh());

            $meta = is_array($order->delivery_pricing_meta) ? $order->delivery_pricing_meta : [];
            data_set($meta, 'checkout.state', 'payment_started');
            data_set($meta, 'checkout.payment_started_at', now()->toDateTimeString());
            $order->forceFill(['delivery_pricing_meta' => $meta])->save();
        } catch (\Throwable $e) {
            return redirect()->route('checkout.payment.show', $order)
                ->with('error', $e->getMessage() ?: 'Le stock de cette commande n’est plus disponible.');
        }

        if ($operator === 'card') {
            return $this->startPayDunyaPayment(
                paydunya: $paydunya,
                order: $order,
                userId: (int) $request->user()->id,
                phone: '',
                amount: $amountDue,
                method: 'paydunya',
                referencePrefix: 'PAY-',
                itemName: 'Commande OVANIE',
                description: 'Paiement complet de la commande ' . $order->order_number,
                paymentType: 'order_payment',
                clientPaymentMethodId: $savedPaymentMethod?->id,
                paymentOperator: 'card',
                paydunyaChannel: null
            );
        }

        return $this->startCheckoutSoftPayPayment(
            paydunya: $paydunya,
            order: $order,
            userId: $request->user()->id,
            phone: $phone,
            amount: $amountDue,
            method: 'paydunya',
            referencePrefix: 'PAY-',
            itemName: 'Commande OVANIE',
            description: 'Paiement complet de la commande ' . $order->order_number,
            paymentType: 'order_payment',
            clientPaymentMethodId: $savedPaymentMethod?->id,
            paymentOperator: $operator,
            customerName: (string) ($order->customer_name ?: $request->user()->name),
            customerEmail: (string) $request->user()->email,
            orangeOtp: $orangeOtp
        );
    }

    public function abandonOnlinePayment(
        Request $request,
        Order $order,
        OrderStockReservationService $stockReservations,
        LoyaltyService $loyaltyService,
        CheckoutCartFinalizerService $cartFinalizer
    ): RedirectResponse {
        if ((int) $order->client_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($order->payment_method !== 'paydunya') {
            return redirect()->route('cart.index');
        }

        if (in_array($order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            return redirect()->route('order.success', $order)
                ->with('success', 'Le paiement de cette commande est déjà confirmé.');
        }

        $this->cancelOnlineDraft($order, $stockReservations, $loyaltyService, $cartFinalizer);
        $request->session()->forget('checkout_pending_order_id');

        return redirect()->route('cart.index')
            ->with('success', 'Paiement interrompu. Votre panier a été conservé.');
    }

    private function cancelPreviousPendingOnlineDraft(
        Request $request,
        OrderStockReservationService $stockReservations,
        LoyaltyService $loyaltyService,
        CheckoutCartFinalizerService $cartFinalizer
    ): void {
        $pendingOrderId = (int) $request->session()->get('checkout_pending_order_id', 0);

        if ($pendingOrderId <= 0) {
            return;
        }

        $pendingOrder = Order::query()
            ->whereKey($pendingOrderId)
            ->where('client_id', $request->user()->id)
            ->where('payment_method', 'paydunya')
            ->where('payment_status', 'pending')
            ->where('status', 'pending')
            ->first();

        if ($pendingOrder) {
            $this->cancelOnlineDraft($pendingOrder, $stockReservations, $loyaltyService, $cartFinalizer);
        }

        $request->session()->forget('checkout_pending_order_id');
    }

    private function cancelOnlineDraft(
        Order $order,
        OrderStockReservationService $stockReservations,
        LoyaltyService $loyaltyService,
        CheckoutCartFinalizerService $cartFinalizer
    ): void {
        DB::transaction(function () use ($order, $stockReservations, $loyaltyService, $cartFinalizer) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOrder->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
                return;
            }

            $loyaltyService->restoreForOrder($lockedOrder, 'Abandon du paiement en ligne');
            app(GiftCardService::class)->releaseForOrder($lockedOrder, 'Abandon du paiement en ligne');

            // Le nouveau workflow conserve déjà les lignes dans le panier.
            // On libère uniquement la réservation de stock, sans recréer les lignes.
            $stockReservations->releaseAndRestoreCart(
                $lockedOrder,
                ! $cartFinalizer->cartWasPreserved($lockedOrder)
            );

            Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', Payment::STATUS_PENDING)
                ->update([
                    'status' => Payment::STATUS_CANCELLED,
                    'failed_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->cancelOpenShipments($lockedOrder->id);

            $cartFinalizer->markAbandoned($lockedOrder);
            $lockedOrder->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ])->save();
        }, 3);
    }

    private function selectedCartItemIds(Request $request): array
    {
        $ids = $request->input('checkout_cart_item_ids', $request->session()->get('checkout_cart_item_ids', []));

        return collect(is_array($ids) ? $ids : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function applyCheckoutSelection(Cart $cart, array $selectedIds): void
    {
        if (empty($selectedIds)) {
            return;
        }

        $cart->setRelation('items', $cart->items->whereIn('id', $selectedIds)->values());
    }

    private function deliveryLineForShop(array $checkout, int $shopId): array
    {
        foreach (($checkout['delivery_breakdown'] ?? []) as $line) {
            if ((int) ($line['shop_id'] ?? 0) === $shopId) {
                return $line;
            }
        }

        return [];
    }

    private function deliveryProviderForCartItem($cartItem, array $deliveryLine = []): string
    {
        if (($deliveryLine['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_PICKUP) {
            return OrderWorkflowService::PROVIDER_PICKUP;
        }

        if (($deliveryLine['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_SELLER) {
            return OrderWorkflowService::PROVIDER_SELLER;
        }

        if (($deliveryLine['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_PARTNER) {
            return OrderWorkflowService::PROVIDER_PARTNER;
        }

        return OrderWorkflowService::PROVIDER_OVANIE;
    }

    private function deliveryPriceForCartItem($cartItem, array $deliveryLine, Cart $cart): float
    {
        $shopId = (int) $cartItem->product->shop_id;
        $shopItems = $cart->items->filter(fn ($line) => (int) ($line->product?->shop_id ?? 0) === $shopId);
        $shopSubtotal = max(1, (float) $shopItems->sum(fn ($line) => (float) $line->price * (int) $line->quantity));
        $itemSubtotal = (float) $cartItem->price * (int) $cartItem->quantity;
        $shopDeliveryFee = (float) ($deliveryLine['delivery_fee'] ?? 0);

        return round($shopDeliveryFee * ($itemSubtotal / $shopSubtotal), 2);
    }

    private function cartItemWeightKg($cartItem): float
    {
        $weight = (float) ($cartItem->product?->weight_kg ?? $cartItem->product?->weight ?? 0);

        return round($weight * (int) $cartItem->quantity, 3);
    }

    private function cartItemVolumeM3($cartItem): float
    {
        $product = $cartItem->product;
        $volume = (float) ($product?->volume_m3 ?? $product?->volume ?? 0);

        if ($volume <= 0 && $product?->length_cm && $product?->width_cm && $product?->height_cm) {
            $volume = ((float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm) / 1000000;
        }

        return round($volume * (int) $cartItem->quantity, 4);
    }

    private function notifyWorkflowActors(Order $order): void
    {
        $workflow = app(OrderWorkflowService::class);
        $order->loadMissing('items.product.shop.user', 'client');

        foreach ($order->items as $item) {
            $vendor = $item->product?->shop?->user;
            $workflow->notify($vendor, 'Nouvelle commande OVANIE', 'Une nouvelle commande doit être préparée : ' . $order->order_number, [
                'category' => 'orders',
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'url' => route('vendor.orders.show', $order),
            ]);

            if ($item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE) {
                $logisticsUsers = \App\Models\User::where('role', 'logistique')->get();
                foreach ($logisticsUsers as $logisticsUser) {
                    $workflow->notify($logisticsUser, 'Nouvelle livraison OVANIE', 'Un colis sera à prendre en charge après préparation vendeur.', [
                        'category' => 'deliveries',
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'url' => route('logistics.shipments.details', $item),
                    ]);
                }
            }
        }
    }

    private function persistDeliverySelectionsAndShipments(Order $order, array $checkout, Request $request): void
    {
        app(\App\Services\OrderDeliveryPersistenceService::class)->persist($order, $checkout, $request);
    }

    private function calculateProductDeliveryFee(Cart $cart, Request $request): float
    {
        return app(CheckoutSummaryService::class)->calculateDeliveryFee($cart, $request);
    }

    private function sendOrderCreatedSms(Order $order, string $phone): void
    {
        $order->loadMissing('client');
        $message = $this->buildOrderNotificationMessage($order);

        app(OvanieNotificationDispatcher::class)->send(
            $order->client,
            'orders',
            'Commande enregistrée',
            $message,
            [
                'order_id' => $order->id,
                'url' => route('client.orders.show', $order),
                'phone' => $phone,
            ],
            ['push', 'email', 'sms']
        );
    }

    private function notifyVendorsNewOrder(Order $order): void
    {
        $order->loadMissing('items.product.shop.user');

        foreach ($order->items as $item) {
            $shop = $item->product?->shop;
            $vendorPhone = $shop?->mm_number ?? $shop?->user?->phone ?? null;

            if (!$vendorPhone) {
                continue;
            }

            $message = "OVANIE : nouvelle commande {$order->order_number}. "
                . "Produit : {$item->product->name}. Qté : {$item->quantity}. "
                . "Merci de préparer la livraison.";

            $sent = SmsService::send($vendorPhone, $message);

            $this->logSms($vendorPhone, $message, $sent);
        }
    }

    public function deliveryFeePreview(
        Request $request,
        CheckoutSummaryService $checkoutSummary,
        CartFulfillmentOptimizer $optimizer,
        CommissionService $commissions,
        CheckoutPaymentOptionsService $paymentOptions
    )
    {
        app(CheckoutAddressResolver::class)->applyToRequest($request);
        $this->resolveCheckoutGeoIfPossible($request);

        $request->validate([
            'delivery_zone' => 'required|in:abidjan,interieur',
            'delivery_destination_type' => 'nullable|in:home,pickup',
            'delivery_instructions' => 'nullable|string|max:500',
            'address' => 'nullable|string|max:500',
            'delivery_commune' => 'nullable|string|max:100',
            'delivery_quartier' => 'nullable|string|max:150',
            'delivery_city' => 'nullable|string|max:150',
            'delivery_latitude' => 'nullable|numeric|between:-90,90',
            'delivery_longitude' => 'nullable|numeric|between:-180,180',
            'delivery_geo_accuracy' => 'nullable|numeric|min:0',
            'delivery_geo_source' => 'nullable|string|max:50',
            'carrier' => 'nullable|array',
            'saved_address_id' => 'nullable|integer',
        ]);

        app(\App\Services\Geo\DeliveryPointGuard::class)->validateOperationalPoint($request);
        $territoryZone = app(\App\Services\LogisticsTerritoryCoverageService::class)->summaryForRequest($request);

        $cart = Cart::with('items.product.shop')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'subtotal' => 0,
                'delivery_fee' => 0,
                'total' => 0,
                'commission' => 0,
                'delivery_breakdown' => [],
                'total_weight_kg' => 0,
                'total_volume_m3' => 0,
                'recommended_vehicle' => null,
                'recommended_vehicle_code' => null,
                'delivery_quote_required' => false,
                'delivery_available' => false,
                'delivery_message' => null,
                'territory_zone' => $territoryZone,
                'payment_options' => $paymentOptions->forCart($cart, 0),
            ]);
        }

        $selectedIds = $this->selectedCartItemIds($request);
        if ($selectedIds) {
            $this->applyCheckoutSelection($cart, $selectedIds);
        }

        if (! $this->hasSufficientDeliveryAddress($request)) {
            $subtotal = (float) $cart->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity);

            return response()->json([
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'total' => $subtotal,
                'commission' => $commissions->commissionFromPublicItems($cart->items),
                'delivery_breakdown' => [],
                'total_weight_kg' => 0,
                'total_volume_m3' => 0,
                'recommended_vehicle' => null,
                'recommended_vehicle_code' => null,
                'delivery_quote_required' => false,
                'delivery_calculated' => false,
                'delivery_available' => false,
                'delivery_message' => null,
                'territory_zone' => $territoryZone,
                'payment_options' => $paymentOptions->forCart($cart, $subtotal),
            ]);
        }

        $preview = $optimizer->previewForAddress($cart, $this->optimizationAddressPayload($request));
        $checkout = $checkoutSummary->build($preview['cart'], $request);
        return response()->json([
            'subtotal'                => $checkout['subtotal'],
            'delivery_fee'            => $checkout['delivery_fee'],
            'total'                   => $checkout['total'],
            'commission'              => $checkout['commission'],
            'delivery_quote_required' => $checkout['delivery_quote_required'] ?? false,
            'delivery_available'      => $checkout['delivery_available'] ?? false,
            'delivery_message'        => $checkout['client_delivery_message'] ?? null,
            'delivery_debug'          => $checkout['delivery_issues'] ?? [],
            'delivery_calculated'     => true,
            'total_weight_kg'         => $checkout['total_weight_kg']         ?? 0,
            'total_volume_m3'         => $checkout['total_volume_m3']         ?? 0,
            'recommended_vehicle'     => $checkout['recommended_vehicle']     ?? null,
            'recommended_vehicle_code'=> $checkout['recommended_vehicle_code']?? null,
            'shop_deliveries'         => $checkout['shop_deliveries']         ?? [],
            'territory_zone'         => $territoryZone,
            'payment_options'         => $paymentOptions->forCart($preview['cart'], (float) $checkout['total']),
        ]);
    }

    private function safeDeliveryCoordinate(Request $request, string $inputName, string $columnName): ?float
    {
        if (! Schema::hasColumn('orders', $columnName)) {
            return null;
        }

        $value = $request->input($inputName);

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 7);
    }

    private function safeRequestCoordinate(Request $request, string $inputName): ?float
    {
        $value = $request->input($inputName);

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 7);
    }

    private function shouldResolveCheckoutGeo(Request $request): bool
    {
        return $request->filled('delivery_latitude')
            || $request->filled('delivery_longitude')
            || $request->filled('address')
            || $request->filled('delivery_commune')
            || $request->filled('delivery_city')
            || $request->filled('delivery_quartier');
    }


    private function resolveCheckoutGeoIfPossible(Request $request): void
    {
        if ($request->input('delivery_destination_type') === 'pickup' || ! $this->shouldResolveCheckoutGeo($request)) {
            return;
        }

        $geocoding = app(GeocodingService::class);

        if ($request->filled('delivery_latitude') && $request->filled('delivery_longitude')) {
            $geo = $geocoding->reverse(
                (float) $request->input('delivery_latitude'),
                (float) $request->input('delivery_longitude')
            );

            $request->merge([
                'delivery_geo_source' => $request->input('delivery_geo_source') ?: 'browser',
            ]);

            if ($geo) {
                $this->mergeResolvedCheckoutLocation($request, $geo);
            }

            return;
        }

        $geo = $geocoding->searchBestMatch(
            $request->input('address'),
            $request->input('delivery_commune') ?: $request->input('delivery_city'),
            $request->input('delivery_quartier'),
            "Cote d'Ivoire"
        );

        if ($geo) {
            $request->merge([
                'delivery_latitude' => $geo['latitude'],
                'delivery_longitude' => $geo['longitude'],
                'delivery_geo_source' => 'geocoding',
            ]);
            $this->mergeResolvedCheckoutLocation($request, $geo);
        }
    }

    private function mergeResolvedCheckoutLocation(Request $request, array $geo): void
    {
        $resolved = (array) ($geo['resolved_location'] ?? []);
        if ($resolved === []) {
            return;
        }

        $zone = $resolved['zone'] ?? $request->input('delivery_zone');
        $commune = $resolved['commune'] ?? null;
        $quartier = $resolved['quartier'] ?? null;
        $city = $resolved['city'] ?? null;
        $locations = app(\App\Services\Geo\AbidjanLocationResolver::class);
        $existingCommune = $locations->detectCommune([$request->input('delivery_commune')]);

        $request->merge([
            'delivery_zone' => $zone,
            'delivery_commune' => $zone === 'abidjan'
                ? ($commune ?: $existingCommune ?: 'Abidjan')
                : ($request->input('delivery_commune') ?: $commune),
            'delivery_quartier' => $quartier ?: $request->input('delivery_quartier'),
            'delivery_city' => $zone === 'abidjan'
                ? null
                : ($city ?: $request->input('delivery_city')),
            'address' => $request->input('address') ?: ($geo['display_name'] ?? null),
        ]);
    }

    private function hasSufficientDeliveryAddress(Request $request): bool
    {
        if ($request->input('delivery_destination_type') === 'pickup') {
            return true;
        }

        if (! $request->filled('address') || ! $request->filled('delivery_zone')) {
            return false;
        }

        if ($request->input('delivery_zone') === 'abidjan') {
            return $request->filled('delivery_commune')
                || ($request->filled('delivery_latitude') && $request->filled('delivery_longitude'));
        }

        return $request->input('delivery_zone') === 'interieur'
            && $request->filled('delivery_city');
    }

    private function optimizationAddressPayload(Request $request): array
    {
        return [
            'address' => $request->input('address'),
            'delivery_zone' => $request->input('delivery_zone'),
            'delivery_commune' => $request->input('delivery_commune'),
            'delivery_quartier' => $request->input('delivery_quartier'),
            'delivery_city' => $request->input('delivery_city'),
            'delivery_latitude' => $request->input('delivery_latitude'),
            'delivery_longitude' => $request->input('delivery_longitude'),
            'delivery_geo_source' => $request->input('delivery_geo_source'),
        ];
    }

    private function logSms(string $phone, string $message, mixed $sent): void
    {
        if (! Schema::hasTable('sms_logs')) {
            return;
        }

        DB::table('sms_logs')->insert([
            'phone' => $phone,
            'message' => $message,
            'status' => $sent ? 'sent' : 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getProcessingDate(): string
    {
        $date = now();

        if ($date->isSaturday()) {
            return $date->addDays(2)->toDateString();
        }

        if ($date->isSunday()) {
            return $date->addDay()->toDateString();
        }

        return $date->toDateString();
    }

    private function addBusinessDays($date, int $days): string
    {
        $result = \Carbon\Carbon::parse($date);

        while ($days > 0) {
            $result->addDay();

            if (!$result->isWeekend()) {
                $days--;
            }
        }

        return $result->toDateString();
    }

    private function getDeliveryMinDate(string $zone): string
    {
        $start = $this->getProcessingDate();

        return $zone === 'abidjan'
            ? $this->addBusinessDays($start, 1)
            : $this->addBusinessDays($start, 2);
    }

    private function getDeliveryMaxDate(string $zone): string
    {
        // Promesse client à Abidjan : livraison sous 48h pile après la
        // commande (peu importe jour ouvré/weekend), pas 2 "jours ouvrés"
        // qui peut glisser plus loin selon le jour de commande.
        if ($zone === 'abidjan') {
            return now()->addHours(48)->toDateString();
        }

        $start = $this->getProcessingDate();

        return $this->addBusinessDays($start, 5);
    }

    private function buildDeliveryNote(Request $request): string
    {
        if ($request->delivery_destination_type === 'pickup') {
            return 'Retrait géré par OVANIE.';
        }

        $location = $request->delivery_zone === 'abidjan'
            ? trim(
                'Abidjan - Commune : ' . $request->delivery_commune .
                ' - Quartier : ' . ($request->delivery_quartier ?: 'Non précisé')
            )
            : trim(
                'Hors Abidjan - Ville : ' . $request->delivery_city .
                ' - Quartier : ' . ($request->delivery_quartier ?: 'Non précisé')
            );

        $instructions = trim((string) $request->input('delivery_instructions'));
        if ($instructions === '') {
            return $location;
        }

        $instructions = preg_replace('/\s+/', ' ', $instructions) ?: $instructions;

        return $location . ' - Instructions : ' . $instructions;
    }

    private function startCheckoutSoftPayPayment(
        PayDunyaService $paydunya,
        Order $order,
        int $userId,
        string $phone,
        float|int $amount,
        string $method,
        string $referencePrefix,
        string $itemName,
        string $description,
        string $paymentType,
        ?int $clientPaymentMethodId,
        string $paymentOperator,
        string $customerName,
        string $customerEmail,
        string $orangeOtp = ''
    ): RedirectResponse {
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'user_id' => $userId,
            'operator' => $paymentOperator,
            'mobile_number' => $phone,
            'reference' => $referencePrefix . $order->order_number . '-' . strtoupper(Str::random(6)),
            'type' => $paymentType,
            'client_payment_method_id' => $clientPaymentMethodId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | TEST LOCAL
        |--------------------------------------------------------------------------
        |
        | Le paiement Mobile Money direct (SoftPay) n'a pas d'équivalent de test
        | chez PayDunya (voir PayDunyaService::startSoftPay). En local, on
        | confirme donc directement le paiement pour pouvoir tester le reste du
        | parcours (commande, notifications, livraison) sans argent réel.
        |
        | En production, ce bloc n'est jamais exécuté.
        */
        if (app()->environment('local')
            && strtolower((string) config('paydunya.mode', 'test')) === 'test') {
            $token = 'LOCAL-TEST-' . strtoupper(Str::random(12));
            $payment->update(['reference' => $token]);

            app(\App\Http\Controllers\PaymentController::class)->confirmPaydunyaPaymentFromReturn($payment, [
                'data' => [
                    'status' => 'completed',
                    'transaction_id' => $token,
                    'invoice' => [
                        'total_amount' => $amount,
                        'currency' => 'XOF',
                        'token' => $token,
                    ],
                ],
                'local_simulation' => true,
            ]);

            return redirect()->route('order.success', $order->id)
                ->with('success', 'Paiement local simulé avec succès (mode test).');
        }

        try {
            $invoice = $paydunya->createOrderInvoice([
                'item_name' => $itemName,
                'description' => $description,
                'amount' => $amount,
                'return_url' => route('paydunya.return'),
                'cancel_url' => route('paydunya.cancel'),
            ]);

            if (! $invoice->create()) {
                $failureMessage = $invoice->response_text ?? 'Impossible d’initialiser le paiement en ligne.';
            } else {
                $token = $this->extractPayDunyaToken($invoice, $payment->reference);
                $payment->update(['reference' => $token]);

                $softPayResponse = $paydunya->startSoftPay([
                    'operator' => $paymentOperator,
                    'payment_token' => $token,
                    'full_name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $phone,
                    'orange_otp' => $orangeOtp,
                ]);

                if (! empty($softPayResponse['success'])) {
                    $providerUrl = trim((string) ($softPayResponse['url'] ?? ''));
                    if ($providerUrl !== '' && filter_var($providerUrl, FILTER_VALIDATE_URL)) {
                        return redirect()->away($providerUrl);
                    }

                    $message = trim((string) ($softPayResponse['message'] ?? ''));

                    return redirect()->route('order.success', $order->id)
                        ->with('success', $message !== ''
                            ? $message
                            : 'Paiement lancé. Validez la demande sur votre téléphone pour finaliser la transaction.');
                }

                \Log::warning('PayDunya SoftPay web indisponible.', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'operator' => $paymentOperator,
                    'provider_message' => trim((string) ($softPayResponse['message'] ?? '')),
                ]);

                $failureMessage = trim((string) ($softPayResponse['message'] ?? ''))
                    ?: 'Le paiement n’a pas pu être lancé. Vérifiez les informations saisies et réessayez.';
            }
        } catch (\Throwable $e) {
            \Log::error('Initialisation du paiement en ligne impossible', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'operator' => $paymentOperator,
                'message' => $e->getMessage(),
            ]);
            $failureMessage = 'Impossible d’initialiser le paiement en ligne.';
        }

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
        ])->save();

        if (in_array($paymentType, ['order_payment', 'commission_payment'], true)) {
            app(LoyaltyService::class)->restoreForOrder($order, 'Échec d’initialisation du paiement');
            app(GiftCardService::class)->releaseForOrder($order, 'Échec d’initialisation du paiement');
            $cartFinalizer = app(CheckoutCartFinalizerService::class);
            app(OrderStockReservationService::class)->releaseAndRestoreCart(
                $order,
                ! $cartFinalizer->cartWasPreserved($order)
            );
            $cartFinalizer->markAbandoned($order);
            $order->forceFill([
                'payment_status' => 'failed',
                'status' => 'cancelled',
            ])->save();

            $this->cancelOpenShipments($order->id);
        }

        return redirect()->route('checkout.index')
            ->with('error', $failureMessage)
            ->withInput(request()->except('orange_otp'));
    }

    private function startPayDunyaPayment(
        PayDunyaService $paydunya,
        Order $order,
        int $userId,
        string $phone,
        float|int $amount,
        string $method,
        string $referencePrefix,
        string $itemName,
        string $description,
        ?array $orderItemIds = null,
        string $paymentType = 'order_payment',
        ?int $clientPaymentMethodId = null,
        ?string $paymentOperator = null,
        ?string $paydunyaChannel = null
    ): RedirectResponse {
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'user_id' => $userId,
            'operator' => $paymentOperator ?: 'paydunya',
            'mobile_number' => $phone,
            'reference' => $referencePrefix . $order->order_number . '-' . strtoupper(Str::random(6)),
            'type' => $paymentType,
            'order_item_ids' => $orderItemIds,
            'client_payment_method_id' => $clientPaymentMethodId,
        ]);

        try {
            $invoice = $paydunya->createOrderInvoice([
                'item_name' => $itemName,
                'description' => $description,
                'amount' => $amount,
                'return_url' => route('paydunya.return'),
                'cancel_url' => route('paydunya.cancel'),
                'channel' => $paydunyaChannel,
            ]);

            if ($invoice->create()) {
                $token = $this->extractPayDunyaToken($invoice, $payment->reference);
                $payment->update(['reference' => $token]);

                return redirect()->away($invoice->getInvoiceUrl());
            }

            $failureMessage = $invoice->response_text ?? 'Impossible d’initialiser le paiement en ligne.';
        } catch (\Throwable $e) {
            \Log::error('Initialisation PayDunya impossible', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);
            $failureMessage = 'Impossible d’initialiser le paiement en ligne.';
        }

        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
        ])->save();

        if (in_array($paymentType, ['order_payment', 'commission_payment'], true)) {
            app(LoyaltyService::class)->restoreForOrder($order, 'Échec d’initialisation du paiement');
            $cartFinalizer = app(CheckoutCartFinalizerService::class);
            app(OrderStockReservationService::class)->releaseAndRestoreCart(
                $order,
                ! $cartFinalizer->cartWasPreserved($order)
            );
            $cartFinalizer->markAbandoned($order);
            $order->forceFill([
                'payment_status' => 'failed',
                'status' => 'cancelled',
            ])->save();

            $this->cancelOpenShipments($order->id);
        }

        return redirect()->route('checkout.index')->with('error', $failureMessage);
    }

    private function extractPayDunyaToken($invoice, string $fallback): string
    {
        return $invoice->token
            ?? $invoice->invoice_token
            ?? ($invoice->response_array['token'] ?? null)
            ?? ($invoice->response['token'] ?? null)
            ?? $fallback;
    }

    /**
     * Annule les expéditions ouvertes sans laisser une ancienne référence
     * order_item_id orpheline faire échouer tout le traitement PayDunya.
     */
    private function cancelOpenShipments(int $orderId): void
    {
        DB::table('shipments')
            ->where('shipments.order_id', $orderId)
            ->whereNotNull('shipments.order_item_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_items')
                    ->whereColumn('order_items.id', 'shipments.order_item_id')
                    ->whereColumn('order_items.order_id', 'shipments.order_id');
            })
            ->update([
                'order_item_id' => null,
                'updated_at' => now(),
            ]);

        Shipment::query()
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }

    public function paydunyaSuccess(
        Request $request,
        Order $order,
        PayDunyaService $paydunya
    ): RedirectResponse {
        if ((int) $order->client_id !== (int) $request->user()->id) {
            abort(403);
        }

        $this->reconcilePaydunyaOrder(
            $order,
            $paydunya,
            trim((string) $request->query('token'))
        );

        $freshOrder = $order->fresh();

        if (in_array($freshOrder->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            $request->session()->forget('checkout_pending_order_id');
        }

        return redirect()->route('order.success', $freshOrder->id);
    }

    public function paydunyaCancel(
        Order $order,
        OrderStockReservationService $stockReservations,
        LoyaltyService $loyaltyService,
        CheckoutCartFinalizerService $cartFinalizer
    ): RedirectResponse {
        if ((int) $order->client_id !== (int) auth()->id()) {
            abort(403);
        }

        $payment = Payment::where('order_id', $order->id)
            ->whereIn('method', ['paydunya', 'cash_on_delivery_commission'])
            ->latest()
            ->first();

        if ($payment?->isPaid() || in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
            session()->forget('checkout_pending_order_id');

            return redirect()->route('order.success', $order->id)
                ->with('success', 'Le paiement a déjà été confirmé.');
        }

        if (! ($payment?->isCancelled()
            || $payment?->isFailed()
            || in_array($order->payment_status, ['cancelled', 'failed'], true))) {
            $this->cancelOnlineDraft($order, $stockReservations, $loyaltyService, $cartFinalizer);
        }

        session()->forget('checkout_pending_order_id');

        return redirect()->route('cart.index')
            ->with('success', 'Paiement annulé. Votre panier a été conservé.');
    }

    public function success(
        Request $request,
        $id,
        PayDunyaService $paydunya
    ) {
        $order = Order::with('items.product.shop')
            ->where('id', $id)
            ->where('client_id', $request->user()->id)
            ->firstOrFail();

        // Répare aussi une commande de test déjà revenue sur cette page avant
        // l'installation du correctif : un simple rechargement relance la
        // vérification PayDunya et finalise le panier si le paiement est validé.
        if ($order->payment_method === 'paydunya'
            && ! in_array($order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            $this->reconcilePaydunyaOrder($order, $paydunya);
            $order = $order->fresh(['items.product.shop']);
        }

        return view('success', compact('order'));
    }

    /**
     * Vérifie le statut PayDunya puis exécute exactement le même traitement que
     * le webhook : paiement, stock, panier, visibilité vendeur et reversements.
     */
    private function reconcilePaydunyaOrder(
        Order $order,
        PayDunyaService $paydunya,
        string $returnToken = ''
    ): string {
        // V75 : une seule logique de réconciliation pour Web, retour public et APK.
        return app(\App\Services\PayDunyaOrderReconciliationService::class)
            ->reconcile($order, $returnToken);
    }

    public function downloadReceipt($id, OrderFinancialSummaryService $financialService)
    {
        $order = Order::with(['items.product', 'items.receptionItem', 'payments'])
            ->where('id', $id)
            ->where('client_id', auth()->id())
            ->firstOrFail();

        $financialSummary = $financialService->summarize($order);
        $pdf = Pdf::loadView('pdf.receipt', compact('order', 'financialSummary'));

        return $pdf->download('facture-' . $order->invoice_number . '.pdf');
    }

    protected function generateInvoiceNumber(): string
    {
        return app(\App\Services\InvoiceNumberService::class)->next();
    }

    private function generateCommissions(Order $order, CommissionService $commissions): void
    {
        $order->loadMissing('items.product.shop');

        $grouped = $order->items->groupBy('shop_id');

        foreach ($grouped as $shopId => $items) {
            $commission = $commissions->commissionFromPublicItems($items);

            Commission::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'shop_id' => $shopId,
                ],
                [
                    'amount' => $commission,
                    'status' => 'non payé',
                ]
            );
        }
    }

    private function buildOrderNotificationMessage(Order $order): string
    {
        $start = \Carbon\Carbon::parse($order->delivery_started_at)->format('d/m/Y');
        $min = \Carbon\Carbon::parse($order->delivery_min_date)->format('d/m/Y');
        $max = \Carbon\Carbon::parse($order->delivery_max_date)->format('d/m/Y');

        if ($order->payment_method === 'paydunya') {
            return "OVANIE : commande {$order->order_number} enregistrée. "
                . "Paiement en ligne en attente de confirmation. "
                . "Traitement prévu le {$start}. Livraison entre {$min} et {$max}.";
        }

        if ($order->payment_method === 'cash_on_delivery') {
            return "OVANIE : commande {$order->order_number} confirmée. "
                . "Le montant de " . number_format((float) $order->total_amount, 0, ',', ' ') . " FCFA sera à régler au moment de la livraison.";
        }

        if ($order->payment_method === 'bank_transfer') {
            return "OVANIE : commande {$order->order_number} enregistrée. "
                . "Paiement par virement en attente de validation.";
        }

        return "OVANIE : commande {$order->order_number} enregistrée.";
    }

    public function showReceipt(
        $id,
        OrderFinancialSummaryService $financialService,
        OrderPaymentEligibilityService $eligibility
    ) {
        $order = Order::with(['items.product', 'payments'])
            ->where('id', $id)
            ->where('client_id', auth()->id())
            ->firstOrFail();

        $financialSummary = $financialService->summarize($order);
        $canPayBalance = $eligibility->canPayOrderBalance($order);
        $payableItemIds = $order->items
            ->filter(fn (OrderItem $item) => $eligibility->canPayLine($order, $item))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('receipt', compact(
            'order',
            'financialSummary',
            'canPayBalance',
            'payableItemIds'
        ));
    }

    // -------------------------------------------------------------------------
    // Partie ajouter : Paiement par produit ou total depuis la page facture.
    // Redirige directement vers PayDunya pour le montant sélectionné.
    // -------------------------------------------------------------------------
    public function payItem(Request $request, Order $order, PayDunyaService $paydunya, OrderSettlementService $settlement, OrderPaymentEligibilityService $eligibility): RedirectResponse
    {
        if ((int) $order->client_id !== (int) auth()->id()) {
            abort(403);
        }

        $outstandingAmount = $settlement->outstandingAmount($order);
        if ($outstandingAmount <= 0) {
            return back()->with('success', 'Cette commande est déjà entièrement réglée.');
        }


        $request->validate([
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $selectedIds = collect($request->input('item_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $eligibility->assertOrderBalancePayable($order->loadMissing('items'));
        }

        $query = $order->items()->where('is_paid', false);

        if ($selectedIds->isNotEmpty()) {
            $query->whereIn('id', $selectedIds->all());
        }

        $orderItems = $query->get();

        if ($orderItems->isEmpty()) {
            return back()->with('error', 'Aucun produit impayé valide n’a été sélectionné.');
        }

        if ($selectedIds->isNotEmpty() && $orderItems->count() !== $selectedIds->count()) {
            return back()->with('error', 'Un ou plusieurs produits sélectionnés ont déjà été payés ou sont invalides.');
        }

        foreach ($orderItems as $orderItem) {
            $eligibility->assertLinePayable($order, $orderItem);
        }

        $orderItemIds = $orderItems->pluck('id')->map(fn ($id) => (int) $id)->all();
        $pendingPaymentItemIds = Payment::query()
            ->where('order_id', $order->id)
            ->where('type', 'item_payment')
            ->where('status', Payment::STATUS_PENDING)
            ->get(['order_item_ids'])
            ->flatMap(fn (Payment $payment) => collect($payment->order_item_ids ?? []))
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($pendingPaymentItemIds->intersect($orderItemIds)->isNotEmpty()) {
            return back()->with('error', 'Un paiement en ligne est déjà en attente pour un ou plusieurs produits sélectionnés.');
        }

        $productsAmount = (float) $orderItems->sum('subtotal');
        $deliveryAmount = (float) $orderItems->sum(fn ($item) => (float) ($item->delivery_price ?? 0));
        $amount = round(min($productsAmount + $deliveryAmount, $outstandingAmount), 0);

        if ($amount <= 0) {
            return back()->with('error', 'Le montant à payer est invalide.');
        }

        if ($amount > self::PAYDUNYA_MAX_AMOUNT) {
            return back()->with('error', 'Le paiement en ligne est limité à 3 000 000 FCFA par transaction.');
        }

        $itemName = $selectedIds->isNotEmpty()
            ? count($orderItemIds) . ' produit(s) sélectionné(s)'
            : 'Reste de la commande';

        return $this->startPayDunyaPayment(
            paydunya: $paydunya,
            order: $order,
            userId: auth()->id(),
            phone: $order->phone,
            amount: $amount,
            method: 'paydunya',
            referencePrefix: 'ITEM-',
            itemName: $itemName,
            description: 'Paiement - ' . $itemName . ' - Commande ' . $order->order_number,
            orderItemIds: $orderItemIds,
            paymentType: 'item_payment'
        );
    }
    // -------------------------------------------------------------------------
}
