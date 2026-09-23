<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerDeliveryTrackingSession;
use App\Models\VendorPaymentVerificationRequest;
use App\Services\CommissionService;
use App\Services\ClientDeliveryGroupService;
use App\Services\DeliveryNotificationService;
use App\Services\LogisticsShipmentWorkflowService;
use App\Services\OrderWorkflowService;
use App\Services\SellerDeliverySupervisionService;
use App\Services\VendorOrderTransitionService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class VendorOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $shop = Auth::user()?->shop;

        if (! $shop) {
            return redirect()
                ->route('open-shop')
                ->with('error', 'Vous devez créer une boutique.');
        }

        $allowedFilters = ['all', 'pending', 'preparing', 'ready', 'shipped', 'delivered', 'cancelled'];
        $currentFilter = in_array($request->string('status')->toString(), $allowedFilters, true)
            ? $request->string('status')->toString()
            : 'all';
        $search = trim($request->string('search')->toString());

        $baseQuery = Order::query()
            ->whereHas('items', function ($query) use ($shop) {
                $this->visibleVendorItemQuery($query, (int) $shop->id);
            });

        // Une seule catégorie opérationnelle par commande vendeur, calculée sur les lignes
        // réellement visibles de la boutique. Les compteurs et les filtres restent ainsi cohérents.
        $statusByOrder = DB::table('order_items')
            ->where('shop_id', $shop->id)
            ->whereNotNull('vendor_visible_at')
            ->get(['order_id', 'vendor_status', 'delivery_status'])
            ->groupBy('order_id')
            ->mapWithKeys(fn ($items, $orderId) => [
                (int) $orderId => $this->resolveVendorOrderStatus($items),
            ]);

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search, $shop) {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQuery) use ($search, $shop) {
                        $this->visibleVendorItemQuery($itemQuery, (int) $shop->id);
                        $itemQuery->whereHas('product', fn ($productQuery) =>
                            $productQuery->where('name', 'like', "%{$search}%")
                        );
                    });
            });
        }

        if ($currentFilter !== 'all') {
            $matchingOrderIds = $statusByOrder
                ->filter(fn ($status) => $status === $currentFilter)
                ->keys()
                ->all();

            $matchingOrderIds === []
                ? $baseQuery->whereRaw('1 = 0')
                : $baseQuery->whereIn('id', $matchingOrderIds);
        }

        $orders = $baseQuery
            ->with([
                'client',
                'items' => function ($query) use ($shop) {
                    $this->visibleVendorItemQuery($query, (int) $shop->id)
                        ->with(['product', 'statusHistories', 'sellerTrackingSession']);
                },
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $orders->getCollection()->each(function (Order $order) {
            $order->setAttribute('vendor_display_status', $this->resolveVendorOrderStatus($order->items));
        });

        $statusCounts = [
            'all' => $statusByOrder->count(),
            'pending' => $statusByOrder->filter(fn ($status) => $status === 'pending')->count(),
            'preparing' => $statusByOrder->filter(fn ($status) => $status === 'preparing')->count(),
            'ready' => $statusByOrder->filter(fn ($status) => $status === 'ready')->count(),
            'shipped' => $statusByOrder->filter(fn ($status) => $status === 'shipped')->count(),
            'delivered' => $statusByOrder->filter(fn ($status) => $status === 'delivered')->count(),
            'cancelled' => $statusByOrder->filter(fn ($status) => $status === 'cancelled')->count(),
        ];

        return $this->vendorView('orders', compact(
            'orders',
            'shop',
            'statusCounts',
            'currentFilter',
            'search'
        ));
    }

    public function show(
        Order $order,
        CommissionService $commissions,
        VendorOrderTransitionService $transitions
    ) {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $order->load([
            'client',
            'payments',
            'items' => function ($query) use ($shop) {
                $this->visibleVendorItemQuery($query, (int) $shop->id)
                    ->with([
                        'product',
                        'statusHistories',
                        'sellerTrackingSession',
                        'deliveryAssignments.driver',
                    ]);
            },
        ]);

        // Les prix stockés dans order_items sont les prix publics payés par le client.
        // L'espace vendeur doit toutefois afficher le prix réellement saisi par le vendeur,
        // sans la commission OVANIE ajoutée au prix public.
        $vendorLineBreakdowns = $order->items->mapWithKeys(function ($item) use ($commissions, $shop) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $publicSubtotal = (float) ($item->subtotal ?? 0);

            if ($publicSubtotal <= 0) {
                $publicSubtotal = (float) ($item->price ?? 0) * $quantity;
            }

            $breakdown = $commissions->breakdown($publicSubtotal, $shop);
            $sellerSubtotal = (float) $breakdown['vendor_amount'];
            $commissionAmount = (float) $breakdown['commission_amount'];

            return [
                $item->id => [
                    'quantity' => $quantity,
                    'public_unit_price' => round($publicSubtotal / $quantity, 2),
                    'public_subtotal' => round($publicSubtotal, 2),
                    'seller_unit_price' => round($sellerSubtotal / $quantity, 2),
                    'seller_subtotal' => round($sellerSubtotal, 2),
                    'commission_amount' => round($commissionAmount, 2),
                ],
            ];
        });

        $vendorPublicSubtotal = (float) $vendorLineBreakdowns->sum('public_subtotal');
        $vendorSellerSubtotal = (float) $vendorLineBreakdowns->sum('seller_subtotal');
        $vendorCommission = (float) $vendorLineBreakdowns->sum('commission_amount');

        // Compatibilité avec les autres traitements : vendorSubtotal reste le montant public.
        $vendorSubtotal = $vendorPublicSubtotal;
        $vendorNetProducts = $vendorSellerSubtotal;

        $sellerDeliveryFee = (float) $order->items
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
            ->sum(fn ($item) => (float) ($item->delivery_price ?? 0));
        $ovanieDeliveryFee = (float) $order->items
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE)
            ->sum(fn ($item) => (float) ($item->delivery_price ?? 0));

        if ($sellerDeliveryFee <= 0 && $order->items->every(fn ($item) => ! filled($item->delivery_provider) && ! filled($item->delivery_mode)) && $shop->usesSellerLogistics()) {
            $sellerDeliveryFee = (float) $order->items->sum(fn ($item) => (float) ($item->delivery_price ?? 0));
        }

        if ($ovanieDeliveryFee <= 0 && $order->items->every(fn ($item) => ! filled($item->delivery_provider) && ! filled($item->delivery_mode)) && $shop->usesOvanieLogistics()) {
            $ovanieDeliveryFee = (float) $order->items->sum(fn ($item) => (float) ($item->delivery_price ?? 0));
        }

        $vendorPayoutTotal = max(0, round($vendorNetProducts + $sellerDeliveryFee, 2));
        $vendorNet = $vendorNetProducts; // Compatibilité avec les anciennes vues.

        $nextPreparationActions = $order->items
            ->mapWithKeys(fn ($item) => [$item->id => $transitions->nextAction($item)])
            ->filter();

        $vendorDisplayStatus = $this->resolveVendorOrderStatus($order->items);

        $canConfirmCodPayment = $this->isCashOnDeliveryOrder($order)
            && ! in_array($order->payment_status, [Payment::STATUS_PAID, Payment::STATUS_ESCROW_HELD, 'paid', 'escrow_held'], true);

        $sellerTrackingSession = SellerDeliveryTrackingSession::query()
            ->where('order_id', $order->id)
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->first();
        $sellerMissionUrl = $sellerTrackingSession
            ? route('seller-driver.mission', [
                'publicId' => $sellerTrackingSession->public_id,
                'token' => $sellerTrackingSession->access_token,
            ])
            : null;

        return $this->vendorView('order_show', compact(
            'order',
            'shop',
            'vendorSubtotal',
            'vendorPublicSubtotal',
            'vendorSellerSubtotal',
            'vendorLineBreakdowns',
            'vendorCommission',
            'vendorNet',
            'vendorNetProducts',
            'sellerDeliveryFee',
            'ovanieDeliveryFee',
            'vendorPayoutTotal',
            'nextPreparationActions',
            'canConfirmCodPayment',
            'vendorDisplayStatus',
            'sellerTrackingSession',
            'sellerMissionUrl'
        ));
    }

    /**
     * Le vendeur ne peut gérer ici que la préparation de ses lignes.
     * Les statuts shipped/delivered passent exclusivement par le workflow livraison.
     */
    public function updateStatus(
        Order $order,
        Request $request,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow,
        LogisticsShipmentWorkflowService $logistics
    ) {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'vendor_status' => ['nullable', 'in:accepted,preparing,ready'],
            'status' => ['nullable', 'in:accepted,preparing,ready'],
            'vendor_status_note' => ['nullable', 'string', 'max:1000'],
            'vendor_cancel_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $validated['vendor_status'] ?? $validated['status'] ?? 'accepted';
        $note = $validated['vendor_status_note'] ?? null;

        DB::transaction(function () use ($order, $shop, $status, $note, $validated, $transitions, $workflow, $logistics) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = $this->vendorItems($lockedOrder, (int) $shop->id)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $oldStatus = $transitions->currentStatus($item);
                $transitions->assertPreparationTransition($item, $status);

                $item->vendor_status = $status;
                $item->vendor_status_note = $note;
                $item->vendor_status_updated_at = now();

                if ($status === 'accepted') {
                    $item->vendor_confirmed_at = $item->vendor_confirmed_at ?: now();
                }

                if ($status === 'preparing') {
                    $item->delivery_status = $item->delivery_provider === OrderWorkflowService::PROVIDER_SELLER
                        ? OrderWorkflowService::DELIVERY_PREPARING
                        : ($item->delivery_status ?: OrderWorkflowService::DELIVERY_PENDING);
                }

                if ($status === 'ready') {
                    $item->vendor_prepared_at = $item->vendor_prepared_at ?: now();
                }

                $item->save();

                $workflow->recordHistory($lockedOrder, $item, 'vendor_status', $oldStatus, $status, [
                    'actor_type' => 'vendor',
                    'user_id' => Auth::id(),
                    'label' => $this->vendorStatusLabel($status),
                    'message' => $note,
                ]);

                if ($status === 'ready' && $item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE) {
                    $logistics->markReadyAndBroadcast(
                        $item->refresh(),
                        Auth::user(),
                        'Le vendeur a terminé la préparation. Le colis est prêt pour enlèvement OVANIE.'
                    );
                }
            }

            $lockedOrder->refreshGlobalStatusFromItems();
        });

        $hasOvanieLogistics = $status === 'ready'
            && $this->vendorItems($order, (int) $shop->id)
                ->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE)
                ->exists();

        $message = $status !== 'ready'
            ? 'Statut de préparation mis à jour pour vos lignes de commande.'
            : ($hasOvanieLogistics
                ? 'Préparation terminée. OVANIE Logistics est informé et le livreur réservé reçoit automatiquement le signal de préparation. La collecte ne démarre que lorsque toute la mission est prête.'
                : 'Préparation terminée. Commande prête pour enlèvement ou livraison.');

        return back()->with('success', $message);
    }

    /**
     * Mise à jour du suivi d'une livraison vendeur.
     * Le statut "delivered" est volontairement interdit ici : seul verifyDeliveryOtp() peut le poser.
     */
    public function updateDeliveryStatus(
        Request $request,
        Order $order,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow,
        SellerDeliverySupervisionService $supervision
    ) {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'vendor_delivery_status' => ['required', 'in:in_delivery,delivery_failed'],
            'vendor_delivery_note' => ['nullable', 'string', 'max:1000'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_plate' => ['nullable', 'string', 'max:50'],
            'pickup_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'delivery_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $pickupPhoto = $request->hasFile('pickup_photo')
            ? $request->file('pickup_photo')->store('private-documents/delivery-evidence', 'local')
            : null;
        $deliveryPhoto = $request->hasFile('delivery_photo')
            ? $request->file('delivery_photo')->store('private-documents/delivery-evidence', 'local')
            : null;

        $session = SellerDeliveryTrackingSession::query()
            ->where('order_id', $order->id)
            ->where('shop_id', $shop->id)
            ->whereIn('status', [SellerDeliveryTrackingSession::STATUS_ACTIVE, SellerDeliveryTrackingSession::STATUS_INCIDENT])
            ->latest('id')
            ->first();

        if ($validated['vendor_delivery_status'] === 'in_delivery') {
            if (! $session) {
                throw ValidationException::withMessages([
                    'vendor_delivery_status' => 'Planifiez d’abord le chauffeur afin de générer sa mission mobile.',
                ]);
            }

            DB::transaction(function () use ($order, $shop, $validated, $pickupPhoto, $deliveryPhoto, $session) {
                $items = $this->vendorItems($order, (int) $shop->id)
                    ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    $item->forceFill(array_filter([
                        'driver_name' => $validated['driver_name'] ?? $session->driver_name,
                        'driver_phone' => $validated['driver_phone'] ?? $session->driver_phone,
                        'vehicle_plate' => $validated['vehicle_plate'] ?? $session->vehicle_plate,
                        'pickup_photo' => $pickupPhoto,
                        'delivery_photo' => $deliveryPhoto,
                        'vendor_delivery_note' => $validated['vendor_delivery_note'] ?? $item->vendor_delivery_note,
                        'vendor_delivery_updated_at' => now(),
                    ], fn ($value) => $value !== null))->save();
                }

                $session->forceFill(array_filter([
                    'driver_name' => $validated['driver_name'] ?? null,
                    'driver_phone' => $validated['driver_phone'] ?? null,
                    'vehicle_plate' => $validated['vehicle_plate'] ?? null,
                ], fn ($value) => filled($value)))->save();
            });

            return back()->with(
                'success',
                $session->mission_status === 'in_transit'
                    ? 'Informations de la mission mises à jour.'
                    : 'Chauffeur prêt. Le départ doit être confirmé depuis le lien de mission sur son téléphone.'
            );
        }

        DB::transaction(function () use ($order, $shop, $validated, $pickupPhoto, $deliveryPhoto, $transitions, $workflow) {
            $items = $this->vendorItems($order, (int) $shop->id)
                ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $transitions->assertCanUpdateSellerDelivery($item);
                $workflow->setDeliveryStatus(
                    $item,
                    OrderWorkflowService::DELIVERY_FAILED,
                    Auth::user(),
                    'vendor',
                    $validated['vendor_delivery_note'] ?? 'Incident signalé pendant la livraison vendeur.',
                    array_filter([
                        'driver_name' => $validated['driver_name'] ?? null,
                        'driver_phone' => $validated['driver_phone'] ?? null,
                        'vehicle_plate' => $validated['vehicle_plate'] ?? null,
                        'pickup_photo' => $pickupPhoto,
                        'delivery_photo' => $deliveryPhoto,
                        'suppress_notifications' => true,
                    ], fn ($value) => $value !== null)
                );
            }
        });

        if ($session) {
            $supervision->reportIncident(
                $session,
                'other',
                $validated['vendor_delivery_note'] ?? 'Incident signalé par le vendeur.'
            );
        }

        return back()->with('success', 'Incident transmis au centre de supervision OVANIE.');
    }

    public function verifyDeliveryOtp(
        Request $request,
        Order $order,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow,
        SellerDeliverySupervisionService $supervision,
        DeliveryNotificationService $deliveryNotifications
    ) {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'delivery_otp_code' => ['required', 'digits:6'],
        ]);

        DB::transaction(function () use ($order, $shop, $validated, $transitions, $workflow, $supervision, $deliveryNotifications) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = $this->vendorItems($lockedOrder, (int) $shop->id)
                ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'delivery_otp_code' => 'Aucune livraison vendeur disponible pour cette commande.',
                ]);
            }

            foreach ($items as $item) {
                $transitions->assertCanVerifyOtp($item);
            }

            $expectedCodes = $items->pluck('delivery_otp_code')->filter()->unique()->values();

            if ($expectedCodes->count() !== 1
                || ! hash_equals((string) $expectedCodes->first(), (string) $validated['delivery_otp_code'])) {
                throw ValidationException::withMessages([
                    'delivery_otp_code' => 'Code OTP invalide pour cette commande.',
                ]);
            }

            foreach ($items as $item) {
                $workflow->setDeliveryStatus(
                    $item,
                    OrderWorkflowService::DELIVERY_DELIVERED,
                    Auth::user(),
                    'vendor',
                    'Livraison confirmée par le code OTP remis par le client.',
                    [
                        'delivery_otp_verified_at' => now(),
                        'vendor_delivery_status' => 'delivered',
                        'suppress_notifications' => true,
                    ]
                );
            }

            $session = SellerDeliveryTrackingSession::query()
                ->where('order_id', $lockedOrder->id)
                ->where('shop_id', $shop->id)
                ->whereIn('status', [SellerDeliveryTrackingSession::STATUS_ACTIVE, SellerDeliveryTrackingSession::STATUS_INCIDENT])
                ->latest('id')
                ->first();

            if ($session) {
                $supervision->completeSession($session);
            }

            $deliveryNotifications->notifyGroup(
                $items->first()->refresh(),
                'delivered',
                'Livraison effectuée',
                'Cette livraison a été remise. Vérifiez les articles reçus puis confirmez la réception depuis votre espace client.'
            );

            $lockedOrder->refreshGlobalStatusFromItems();
        });

        return back()->with('success', 'OTP validé. La livraison de vos lignes est maintenant confirmée.');
    }

    /**
     * Le vendeur ne valide jamais directement le paiement global.
     * Pour le paiement à la livraison uniquement, il peut signaler un encaissement à vérifier.
     */
    public function confirmPayment(Request $request, Order $order)
    {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        if (! $this->isCashOnDeliveryOrder($order)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Seules les commandes en paiement à la livraison peuvent faire l’objet d’un signalement vendeur.',
            ]);
        }

        if (in_array($order->payment_status, ['paid', 'escrow_held'], true)) {
            return back()->with('info', 'Cette commande est déjà confirmée comme payée par le système OVANIE.');
        }

        $validated = $request->validate([
            'payment_method' => ['nullable', 'in:cash,mobile_money,bank_transfer,wave,cheque,other'],
            'amount_claimed' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->loadMissing([
            'items.product',
            'items.shop',
            'items.shipment',
            'deliverySelections',
            'shipments',
            'payments',
        ]);

        $deliveryGroup = app(ClientDeliveryGroupService::class)
            ->findForShop($order, (int) $shop->id);

        if (! $deliveryGroup) {
            throw ValidationException::withMessages([
                'payment_method' => 'Le groupe de livraison de cette boutique est introuvable.',
            ]);
        }

        if (! $deliveryGroup['is_delivered']) {
            throw ValidationException::withMessages([
                'payment_method' => 'Le paiement ne peut être déclaré qu’après la livraison des articles concernés.',
            ]);
        }

        $expectedAmount = (float) $deliveryGroup['total'];

        // Le montant est recalculé côté serveur. Une ancienne vue peut encore
        // envoyer uniquement le sous-total produits : cette valeur ne doit jamais
        // remplacer le montant contractuel du groupe (produits + livraison).
        $amountClaimed = $expectedAmount;

        $existingRequest = VendorPaymentVerificationRequest::query()
            ->where('order_id', $order->id)
            ->where('shop_id', $shop->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingRequest) {
            return back()->with('warning', 'Une demande de vérification paiement est déjà en attente pour cette commande.');
        }

        VendorPaymentVerificationRequest::create([
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'vendor_id' => $shop->user_id,
            'requested_by' => Auth::id(),
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'amount_claimed' => $expectedAmount,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'vendor_note' => $validated['payment_note'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Signalement envoyé. OVANIE vérifiera l’encaissement avant toute modification financière ou reversement.');
    }

    /**
     * Démarre la livraison vendeur ou confirme la mise à disposition OVANIE.
     */
    public function markShipped(
        Request $request,
        Order $order,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow,
        SellerDeliverySupervisionService $supervision,
        LogisticsShipmentWorkflowService $logistics
    ) {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'shipment_date' => ['required', 'date'],
            'delivery_provider' => ['nullable', 'in:ovanie,seller'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['required_if:delivery_provider,seller', 'nullable', 'string', 'max:150'],
            'driver_phone' => ['required_if:delivery_provider,seller', 'nullable', 'string', 'max:40'],
            'vehicle_plate' => ['required_if:delivery_provider,seller', 'nullable', 'string', 'max:80'],
        ]);

        $sellerTrackingSession = null;

        DB::transaction(function () use ($order, $shop, $validated, $transitions, $workflow, $supervision, $logistics, &$sellerTrackingSession) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $itemsQuery = $this->vendorItems($lockedOrder, (int) $shop->id);
            $requestedProvider = $validated['delivery_provider'] ?? null;

            if ($requestedProvider) {
                $itemsQuery->where('delivery_provider', $requestedProvider);
            }

            $items = $itemsQuery->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'delivery_provider' => 'Aucune ligne de commande ne correspond au groupe logistique demandé.',
                ]);
            }

            $providers = $items->pluck('delivery_provider')->filter()->unique()->values();

            if ($providers->count() !== 1) {
                throw ValidationException::withMessages([
                    'delivery_provider' => 'Sélectionnez le groupe OVANIE Logistics ou le groupe logistique vendeur à traiter.',
                ]);
            }

            $provider = $providers->first();

            foreach ($items as $item) {
                if ($provider === OrderWorkflowService::PROVIDER_OVANIE) {
                    if (in_array($item->delivery_status, [
                        OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                        OrderWorkflowService::DELIVERY_ASSIGNED,
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        OrderWorkflowService::DELIVERY_DELIVERED,
                    ], true)) {
                        continue;
                    }

                    $transitions->assertCanMarkReadyForOvanie($item);
                    $logistics->markReadyAndBroadcast(
                        $item,
                        Auth::user(),
                        'Le colis est prêt pour l’enlèvement OVANIE Logistics.'
                    );
                    continue;
                }

                $transitions->assertCanStartSellerDelivery($item);

                // Le vendeur planifie le chauffeur, mais ne démarre pas la livraison.
                // Le passage « en livraison » et l'envoi de l'OTP sont déclenchés
                // uniquement depuis le téléphone du chauffeur.
                $item->forceFill(array_filter([
                    'vendor_carrier' => $validated['carrier'] ?? null,
                    'vendor_tracking_number' => $validated['tracking_number'] ?? null,
                    'vendor_shipment_date' => $validated['shipment_date'],
                    'driver_name' => $validated['driver_name'] ?? null,
                    'driver_phone' => $validated['driver_phone'] ?? null,
                    'vehicle_plate' => $validated['vehicle_plate'] ?? null,
                    'vendor_delivery_note' => 'Chauffeur planifié. En attente du démarrage depuis la mission mobile.',
                    'vendor_delivery_updated_at' => now(),
                ], fn ($value) => $value !== null))->save();
            }

            if ($provider === OrderWorkflowService::PROVIDER_SELLER) {
                $sellerTrackingSession = $supervision->startSession(
                    $lockedOrder,
                    $shop,
                    $items,
                    [
                        'driver_name' => $validated['driver_name'],
                        'driver_phone' => $validated['driver_phone'],
                        'vehicle_plate' => $validated['vehicle_plate'] ?? null,
                    ]
                );
            }

            $lockedOrder->refreshGlobalStatusFromItems();
        });

        $whatsappSent = false;
        if ($sellerTrackingSession) {
            $missionUrl = $supervision->publicMissionUrl($sellerTrackingSession);
            try {
                $whatsappSent = WhatsAppService::send(
                    $sellerTrackingSession->driver_phone,
                    "OVANIE : mission de livraison {$order->order_number}. Ouvrez ce lien sur le téléphone du livreur et activez le GPS pendant toute la mission : {$missionUrl}"
                );
            } catch (\Throwable $e) {
                Log::warning('Envoi du lien de suivi vendeur impossible', [
                    'session_id' => $sellerTrackingSession->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return back()->with(
            $sellerTrackingSession && ! $whatsappSent ? 'warning' : 'success',
            match (true) {
                ! $sellerTrackingSession => 'Le colis est prêt et visible dans l’espace OVANIE Logistics pour enlèvement.',
                $whatsappSent => 'Chauffeur planifié. Le lien de mission a été transmis par WhatsApp. La livraison passera en cours uniquement lorsque le chauffeur la démarrera depuis son téléphone.',
                default => 'Chauffeur planifié, mais WhatsApp n’est pas encore configuré ou l’envoi a échoué. Copiez le lien de mission depuis le suivi vendeur et transmettez-le manuellement au chauffeur.',
            }
        );
    }

    public function ordersJson()
    {
        $shop = Auth::user()?->shop;

        if (! $shop) {
            return response()->json([]);
        }

        $orders = Order::query()
            ->whereHas('items', function ($query) use ($shop) {
                $this->visibleVendorItemQuery($query, (int) $shop->id);
            })
            ->with([
                'items' => function ($query) use ($shop) {
                    $this->visibleVendorItemQuery($query, (int) $shop->id)->with('product');
                },
            ])
            ->latest()
            ->take(10)
            ->get();

        return response()->json($orders);
    }

    public function updateDriverLocation(Request $request, Order $order, SellerDeliverySupervisionService $supervision)
    {
        $shop = Auth::user()?->shop;
        $this->authorizeOrder($order, $shop);

        $validated = $request->validate([
            'driver_latitude' => ['required', 'numeric', 'between:-90,90'],
            'driver_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $sellerItemsQuery = $this->vendorItems($order, (int) $shop->id)
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER);
        $items = (clone $sellerItemsQuery)->get();

        if ($items->isEmpty()
            || $items->contains(fn ($item) => $item->delivery_status !== OrderWorkflowService::DELIVERY_IN_TRANSIT)) {
            return response()->json([
                'success' => false,
                'message' => 'La position ne peut être publiée que pendant une livraison vendeur réellement en cours.',
            ], 422);
        }

        $sessionId = $items->pluck('seller_tracking_session_id')->filter()->unique()->first();
        $session = $sessionId ? SellerDeliveryTrackingSession::find($sessionId) : null;

        if ($session && $session->isTrackable()) {
            $supervision->recordLocation($session, [
                'latitude' => $validated['driver_latitude'],
                'longitude' => $validated['driver_longitude'],
            ]);
        } else {
            $sellerItemsQuery->update([
                'driver_latitude' => $validated['driver_latitude'],
                'driver_longitude' => $validated['driver_longitude'],
                'driver_location_updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Position GPS enregistrée dans le centre de supervision OVANIE.',
        ]);
    }

    protected function authorizeOrder(Order $order, $shop): void
    {
        abort_unless($shop, 403, 'Vous devez avoir une boutique.');

        $hasVendorItems = $order->items()
            ->whereNotNull('vendor_visible_at')
            ->where('shop_id', $shop->id)
            ->exists();

        abort_unless($hasVendorItems, 403, 'Vous n’êtes pas autorisé à gérer cette commande.');
    }

    protected function vendorItems(Order $order, int $shopId)
    {
        return $order->items()
            ->whereNotNull('vendor_visible_at')
            ->where('shop_id', $shopId);
    }

    protected function visibleVendorItemQuery($query, int $shopId)
    {
        return $query
            ->whereNotNull('vendor_visible_at')
            ->where('shop_id', $shopId);
    }

    /**
     * Délègue à OrderWorkflowService::resolveVendorDisplayStatus(), la même
     * logique désormais partagée avec l'app mobile vendeur (voir
     * VendorMobileController::vendorOrderStatus()) pour que web et mobile
     * affichent toujours le même statut pour une même commande.
     */
    protected function resolveVendorOrderStatus($items): string
    {
        return app(OrderWorkflowService::class)->resolveVendorDisplayStatus($items);
    }

    protected function vendorView(string $view, array $data = [])
    {
        if (View::exists('vendor.' . $view)) {
            return view('vendor.' . $view, $data);
        }

        return view('daniel.' . $view, $data);
    }

    protected function sendDeliveryOtp(Order $order, string $otp): void
    {
        $order->loadMissing('client');

        if ($order->client?->email) {
            Mail::raw(
                "OVANIE : votre code OTP de réception est {$otp}. Ne donnez ce code qu’après réception complète de votre commande {$order->order_number}.",
                function ($message) use ($order) {
                    $message->to($order->client->email)
                        ->subject('Code OTP de réception OVANIE');
                }
            );
        }

        $clientWhatsapp = $order->client?->whatsapp_phone
            ?? $order->client?->phone
            ?? $order->phone;

        if ($clientWhatsapp && class_exists(WhatsAppService::class)) {
            $message = "OVANIE : votre code OTP de réception est {$otp}. Ne communiquez ce code qu’après réception complète de votre commande {$order->order_number}.";

            try {
                WhatsAppService::send($clientWhatsapp, $message);
            } catch (\Throwable $e) {
                Log::warning('Erreur envoi WhatsApp OTP livraison vendeur', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function notifyVendorShipment(Order $order, $shop): void
    {
        Log::info('Expédition vendeur enregistrée', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'shop_id' => $shop->id,
        ]);
    }

    private function isCashOnDeliveryOrder(Order $order): bool
    {
        return in_array($order->payment_method, [
            'cash',
            'cod',
            'cash_on_delivery',
            'pay_on_delivery',
        ], true);
    }

    private function vendorStatusLabel(string $status): string
    {
        return match ($status) {
            'accepted' => 'Commande acceptée',
            'preparing' => 'Préparation démarrée',
            'ready' => 'Commande prête',
            'cancelled' => 'Commande annulée par le vendeur',
            default => 'Statut vendeur mis à jour',
        };
    }
}
