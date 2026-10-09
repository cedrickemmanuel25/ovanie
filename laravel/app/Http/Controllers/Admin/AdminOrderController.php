<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReceptionForm;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\Shipment;
use App\Services\OrderStockReservationService;
use App\Services\CheckoutCartFinalizerService;
use App\Services\OrderWorkflowService;
use App\Services\VendorOrderReleaseService;
use App\Services\VendorPayoutScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminOrderController extends Controller
{
    public function paymentProof(Order $order): BinaryFileResponse
    {
        abort_unless($order->payment_proof && Storage::disk('local')->exists($order->payment_proof), 404);

        return response()->download(
            Storage::disk('local')->path($order->payment_proof),
            'preuve-paiement-commande-'.$order->id.'.'.pathinfo($order->payment_proof, PATHINFO_EXTENSION),
            [
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Liste générale des commandes.
     *
     * Une commande peut contenir plusieurs boutiques. La liaison fiable est :
     * orders.id -> order_items.order_id -> order_items.shop_id -> shops.id.
     */
    public function index(Request $request)
    {
        $shops = Shop::query()
            ->with('user')
            ->orderBy('name')
            ->get();

        $query = Order::query()
            ->operational()
            ->with([
                'client',
                'shop.user',
                'items.shop.user',
                'items.product.shop.user',
            ])
            ->withCount('items');

        if ($request->filled('q')) {
            $search = trim((string) $request->q);

            $query->where(function ($orderQuery) use ($search) {
                $orderQuery
                    ->where('id', $search)
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.shop', function ($shopQuery) use ($search) {
                        $shopQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('shop_id')) {
            $shopId = (int) $request->shop_id;

            $query->where(function ($orderQuery) use ($shopId) {
                $orderQuery
                    ->where('shop_id', $shopId)
                    ->orWhereHas('items', function ($itemQuery) use ($shopId) {
                        $itemQuery->where('shop_id', $shopId)
                            ->orWhereHas('product', function ($productQuery) use ($shopId) {
                                $productQuery->where('shop_id', $shopId);
                            });
                    });
            });
        }

        $sort = $request->get('sort') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sort);

        $orders = $query
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Order::query()->operational()->count(),
            'today' => Order::query()->operational()->whereDate('created_at', now()->toDateString())->count(),
            'pending' => Order::query()->operational()->where('status', 'pending')->count(),
            'paid' => Order::query()->operational()->where('payment_status', 'paid')->count(),
        ];

        return view('admin.orders.index', compact('orders', 'shops', 'stats'));
    }

    /**
     * Détail d'une commande avec regroupement des lignes par boutique.
     */
    public function show(Order $order)
    {
        $order->load([
            'client',
            'shop.user',
            'items.shop.user',
            'items.product.shop.user',
        ]);

        $shopGroups = $order->items
            ->groupBy(function ($item) {
                $shop = $item->shop ?: $item->product?->shop;

                return $shop?->id ? 'shop-' . $shop->id : 'shop-unknown';
            })
            ->map(function ($items) {
                $firstItem = $items->first();
                $shop = $firstItem?->shop ?: $firstItem?->product?->shop;

                return [
                    'shop' => $shop,
                    'seller' => $shop?->user,
                    'items' => $items,
                    'subtotal' => $items->sum(function ($item) {
                        return (float) $item->price * (int) $item->quantity;
                    }),
                    'delivery_fee' => $items->sum(function ($item) {
                        return (float) ($item->delivery_price ?? 0);
                    }),
                    'weight_kg' => $items->sum(function ($item) {
                        return (float) ($item->logistics_weight_kg ?? 0);
                    }),
                    'volume_m3' => $items->sum(function ($item) {
                        return (float) ($item->logistics_volume_m3 ?? 0);
                    }),
                    'visible_lines' => $items->whereNotNull('vendor_visible_at')->count(),
                ];
            })
            ->values();

        // Compatibilité avec les anciennes commandes possédant seulement orders.shop_id.
        if ($shopGroups->isEmpty() && $order->shop) {
            $shopGroups = collect([[
                'shop' => $order->shop,
                'seller' => $order->shop->user,
                'items' => collect(),
                'subtotal' => (float) ($order->subtotal ?? 0),
                'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                'weight_kg' => 0,
                'volume_m3' => 0,
                'visible_lines' => 0,
            ]]);
        }

        $hasReceptionForm = OrderReceptionForm::query()
            ->where('order_id', $order->id)
            ->exists();

        return view('admin.orders.show', compact('order', 'shopGroups', 'hasReceptionForm'));
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => ['nullable', \Illuminate\Validation\Rule::in(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed', 'paid'])],
            'payment_status' => ['required', \Illuminate\Validation\Rule::in(['pending', 'paid', 'failed', 'commission_paid', 'cod_completed', 'verified'])],
        ]);

        $previousStatus = (string) $order->status;
        $previousPaymentStatus = (string) $order->payment_status;
        $paymentChanged = $request->payment_status !== $previousPaymentStatus;
        $statusChosen = $request->filled('status') && $request->status !== $previousStatus;
        $alreadyHandedOver = in_array($previousStatus, ['shipped', 'delivered', 'completed'], true);

        // Une commande déjà livrée ou finalisée ne peut pas voir son paiement repasser « en attente » ou « échoué » :
        // cela annulerait la commande et remettrait le stock en vente alors que le client a reçu ses articles.
        if ($paymentChanged && in_array($previousStatus, ['delivered', 'completed'], true)
            && in_array($request->payment_status, ['pending', 'failed'], true)) {
            return back()->with('error', 'Cette commande est déjà livrée : son paiement ne peut plus être remis en attente ni marqué comme échoué.');
        }

        if ($statusChosen) {
            $order->status = $request->status;
        }

        $order->payment_status = $request->payment_status === 'verified' ? 'paid' : $request->payment_status;

        if ($request->payment_status === 'paid' && $request->hasFile('payment_proof')) {
            $order->payment_proof = $request->file('payment_proof')->store('payment_proofs', 'local');
        }

        // Le statut de la commande suit le paiement uniquement quand le paiement vient de changer et que
        // l'administrateur n'a pas lui-même choisi un statut. Une commande déjà expédiée ne revient pas en arrière.
        if ($paymentChanged && ! $statusChosen) {
            $derivedStatus = match ($request->payment_status) {
                'paid' => $order->payment_method === 'cash_on_delivery' ? 'processing' : 'confirmed',
                'commission_paid', 'verified' => 'confirmed',
                'failed' => 'cancelled',
                'pending' => 'pending',
                default => null,
            };

            if ($derivedStatus !== null && ! ($alreadyHandedOver && $derivedStatus !== 'cancelled')) {
                $order->status = $derivedStatus;
            }
        }

        $order->save();

        $stockReservations = app(OrderStockReservationService::class);

        if (in_array($order->payment_status, ['paid', 'commission_paid'], true)) {
            $stockReservations->commit($order->refresh());

            if ($order->payment_method === 'bank_transfer') {
                Payment::where('order_id', $order->id)
                    ->where('type', 'bank_transfer_payment')
                    ->where('status', Payment::STATUS_PENDING)
                    ->update([
                        'status' => Payment::STATUS_PAID,
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        } elseif ($order->payment_status === 'failed') {
            $cartFinalizer = app(CheckoutCartFinalizerService::class);
            $stockReservations->releaseAndRestoreCart(
                $order->refresh(),
                ! $cartFinalizer->cartWasPreserved($order)
            );
            $cartFinalizer->markAbandoned($order->refresh());

            Payment::where('order_id', $order->id)
                ->where('status', Payment::STATUS_PENDING)
                ->update([
                    'status' => Payment::STATUS_FAILED,
                    'failed_at' => now(),
                    'updated_at' => now(),
                ]);

            Shipment::where('order_id', $order->id)
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
        }

        $releaseStatuses = ['paid', 'commission_paid', 'verified'];
        $releaseOrderStatuses = ['confirmed', 'paid', 'processing', 'completed'];

        if (
            in_array($order->payment_status, $releaseStatuses, true)
            && in_array($order->status, $releaseOrderStatuses, true)
        ) {
            app(VendorOrderReleaseService::class)->release($order->refresh());
        }

        return redirect()->back()->with('success', 'Les statuts de la commande ont été mis à jour.');
    }

    public function confirmDelivery(
        Order $order,
        OrderWorkflowService $workflow,
        VendorPayoutScheduleService $payoutSchedule
    ) {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return back()->with('error', 'Cette commande ne contient aucune ligne à livrer.');
        }

        if ($order->items->every(fn ($item) => $item->isVendorDelivered())) {
            return back()->with('error', 'Cette commande est déjà marquée comme livrée.');
        }

        foreach ($order->items as $item) {
            if ($item->isVendorDelivered()) {
                continue;
            }

            $workflow->setDeliveryStatus(
                item: $item,
                status: OrderWorkflowService::DELIVERY_DELIVERED,
                actor: auth()->user(),
                actorType: 'admin',
                note: 'Livraison confirmée par l’administration OVANIE.'
            );
        }

        $order->refresh();
        $payoutSchedule->syncOrder($order);

        return back()->with(
            'success',
            'Livraison confirmée. Le reversement vendeur reste soumis aux règles de paiement et de réception.'
        );
    }
}
