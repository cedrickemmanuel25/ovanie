<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\ClientPaymentMethod;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Services\AccountDeletionChallengeService;
use App\Services\AccountDeletionService;
use App\Services\ClientOrderStatusService;
use App\Services\ClientOrderActionService;
use App\Services\ClientOrderTimelineService;
use App\Services\Geo\GeocodingService;
use App\Services\LoyaltyService;
use App\Services\OrderFinancialSummaryService;
use App\Services\OrderPaymentEligibilityService;
use App\Services\OrderWorkflowService;
use App\Services\PublicProductVisibilityService;
use App\Services\ReturnRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientAccountController extends Controller
{
    public function dashboard(
        Request $request,
        PublicProductVisibilityService $visibility,
        ClientOrderStatusService $statusService
    ) {
        $user = $request->user();
        $orders = $this->ordersQuery($user->id);
        $statusCounts = $statusService->countsForClient($user->id);

        $favoriteProducts = $user->favoriteProducts()->with('shop')->withCount('reviews');
        $visibility->apply($favoriteProducts->getQuery(), true);

        $favoriteCountQuery = $user->favoriteProducts();
        $visibility->apply($favoriteCountQuery->getQuery(), true);

        return view('client.dashboard', [
            'client' => $user,
            'activeOrdersCount' => $statusCounts['pending'],
            'deliveredOrdersCount' => $statusCounts['completed'],
            'ordersCount' => $statusCounts['all'],
            'favoritesCount' => $favoriteCountQuery->count(),
            'loyaltyPoints' => (int) ($user->loyalty_points ?? 0),
            'recentOrders' => (clone $orders)->latest()->take(5)->get(),
            'favoriteProducts' => $favoriteProducts->latest('favorites.created_at')->take(4)->get(),
            'defaultAddress' => $user->addresses()->where('is_default', true)->first()
                ?? $user->addresses()->latest()->first(),
            'clientOrderStatus' => $statusService,
        ]);
    }

    public function favorites(Request $request, PublicProductVisibilityService $visibility)
    {
        $makeVisibleQuery = function () use ($request, $visibility) {
            $query = $request->user()->favoriteProducts()->with('shop')->withCount('reviews');
            $visibility->apply($query->getQuery(), true);
            return $query;
        };

        $query = $makeVisibleQuery();

        if ($search = trim((string) $request->query('search'))) {
            $query->where('products.name', 'like', "%{$search}%");
        }

        if ($request->query('filter') === 'stock') {
            $query->where('stock', '>', 0);
        } elseif ($request->query('filter') === 'promo') {
            $query->whereNotNull('promo_price')->whereColumn('promo_price', '<', 'price');
        }

        $allQuery = $makeVisibleQuery();
        $stockQuery = $makeVisibleQuery();
        $promoQuery = $makeVisibleQuery();

        return view('client.favorites', [
            'products' => $query->paginate(12)->withQueryString(),
            'counts' => [
                'all' => $allQuery->count(),
                'stock' => $stockQuery->where('stock', '>', 0)->count(),
                'promo' => $promoQuery->whereNotNull('promo_price')->whereColumn('promo_price', '<', 'price')->count(),
                'out' => 0,
            ],
        ]);
    }

    public function toggleFavorite(
        Request $request,
        Product $product,
        PublicProductVisibilityService $visibility
    ) {
        $favorite = Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $favorite) {
            $publicQuery = Product::query()->whereKey($product->id);
            $visibility->apply($publicQuery, true);

            if (! $publicQuery->exists()) {
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => 'Ce produit n’est plus disponible.'], 422)
                    : back()->with('error', 'Ce produit n’est plus disponible et ne peut pas être ajouté aux favoris.');
            }
        }

        $added = ! $favorite;
        $favorite
            ? $favorite->delete()
            : Favorite::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        $visibleFavorites = $request->user()->favoriteProducts();
        $visibility->apply($visibleFavorites->getQuery(), true);

        return $request->expectsJson()
            ? response()->json([
                'success' => true,
                'added' => $added,
                'count' => $visibleFavorites->count(),
            ])
            : back()->with('success', $added ? 'Produit ajouté aux favoris.' : 'Produit retiré des favoris.');
    }

    public function orders(Request $request, ClientOrderStatusService $statusService)
    {
        $query = $this->ordersQuery($request->user()->id);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('items.product', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $statusService->applyFilter($query, (string) $request->query('status', ''));

        match ($request->query('period')) {
            'month' => $query->where('created_at', '>=', now()->startOfMonth()),
            '3months' => $query->where('created_at', '>=', now()->subMonths(3)),
            '6months' => $query->where('created_at', '>=', now()->subMonths(6)),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            default => null,
        };

        match ($request->query('sort')) {
            'oldest' => $query->oldest(),
            'amount' => $query->orderByDesc('total_amount'),
            default => $query->latest(),
        };

        return view('client.orders', [
            'orders' => $query->paginate(12)->withQueryString(),
            'counts' => $statusService->countsForClient($request->user()->id),
        ]);
    }

    public function orderShow(
        Request $request,
        Order $order,
        ClientOrderTimelineService $timelineService,
        OrderFinancialSummaryService $financialService,
        ClientOrderStatusService $statusService,
        OrderPaymentEligibilityService $paymentEligibility
    ) {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $order->load([
            'client',
            'items.product',
            'items.statusHistories',
            'statusHistories',
            'payments',
            'shipments.statusHistories',
            'returns.orderItem',
        ]);

        return view('client.order-show', [
            'order' => $order,
            'timelineEvents' => $timelineService->build($order),
            'financialSummary' => $financialService->summarize($order),
            'canCancel' => $statusService->canClientCancel($order),
            'canTrack' => $statusService->canTrack($order),
            'canReorder' => $statusService->canReorder($order),
            'canPayBalance' => $paymentEligibility->canPayOrderBalance($order),
        ]);
    }

    public function orderTracking(Request $request, Order $order, ClientOrderStatusService $statusService)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $order->loadMissing(['shipments.orderItem.product', 'items.product']);

        abort_unless($statusService->canTrack($order), 404);

        $deliveryOtpGroups = $order->items
            ->filter(fn ($item) => filled($item->delivery_otp_code) && $item->delivery_otp_verified_at === null)
            ->groupBy(fn ($item) => (string) $item->delivery_otp_code)
            ->map(fn ($items, $code) => [
                'code' => (string) $code,
                'products' => $items->pluck('product.name')->filter()->unique()->values()->all(),
            ])
            ->values();

        return view('client.orders.tracking', compact('order', 'deliveryOtpGroups'));
    }

    public function reorder(
        Request $request,
        Order $order,
        ClientOrderActionService $actions
    ) {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        try {
            $result = $actions->reorder($request->user(), $order);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first()
                ?: 'Cette commande ne peut pas encore être renouvelée.');
        }

        $addedCount = (int) ($result['added_lines'] ?? 0);

        return redirect()->route('cart.index')->with(
            $addedCount > 0 ? 'success' : 'error',
            $addedCount > 0
                ? 'Les articles disponibles ont été ajoutés au panier.'
                : 'Aucun article de cette commande n’est actuellement disponible.'
        );
    }

    public function returns(Request $request, ReturnRefundService $returnRefunds)
    {
        $orders = $this->ordersQuery($request->user()->id)
            ->with('items.product')
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->latest()
            ->get();

        $returnOptions = [];

        foreach ($orders as $order) {
            $eligibleItems = $order->items->filter(function ($item) use ($order, $returnRefunds, &$returnOptions) {
                $claimQuantity = $returnRefunds->availableQuantity($item, 'claim');
                $physicalQuantity = $returnRefunds->availableQuantity($item, 'return');
                $availableQuantity = max($claimQuantity, $physicalQuantity);
                $isDelivered = $item->isVendorDelivered();
                $withinWindow = $isDelivered && $returnRefunds->isWithinReturnWindow($item, $order);
                $deadline = $isDelivered ? $returnRefunds->returnDeadline($item, $order) : null;

                $returnOptions[$item->id] = [
                    'available_quantity' => $physicalQuantity,
                    'available_claim_quantity' => $claimQuantity,
                    'can_claim' => $claimQuantity > 0,
                    'can_return' => $physicalQuantity > 0 && $withinWindow,
                    'can_refund' => $physicalQuantity > 0 && $withinWindow,
                    'return_deadline' => $deadline?->format('d/m/Y'),
                ];

                return $physicalQuantity > 0 || $claimQuantity > 0;
            })->values();

            $order->setRelation('items', $eligibleItems);
        }

        $orders = $orders->filter(fn ($order) => $order->items->isNotEmpty())->values();

        $returns = ReturnModel::with(['order', 'orderItem.product'])
            ->where('client_id', $request->user()->id)
            ->latest()
            ->get();

        return view('client.returns', compact('orders', 'returns', 'returnOptions'));
    }

    public function storeReturn(Request $request, ReturnRefundService $returnRefunds)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'order_item_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
            'quantity' => ['required', 'integer', 'min:1'],
            'return_type' => ['required', Rule::in(['return', 'claim', 'refund'])],
            'photo_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $order = $this->ordersQuery($request->user()->id)
            ->with('items.product.shop.user')
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->findOrFail($data['order_id']);

        $item = $order->items->firstWhere('id', (int) $data['order_item_id']);
        if (! $item) {
            throw ValidationException::withMessages([
                'order_item_id' => 'Le produit sélectionné n’appartient pas à cette commande.',
            ]);
        }

        $isReturnOrRefund = in_array($data['return_type'], ['return', 'refund'], true);

        if ($isReturnOrRefund && ! $item->isVendorDelivered()) {
            throw ValidationException::withMessages([
                'order_item_id' => 'Un retour ou remboursement ne peut être demandé qu’après la livraison du produit.',
            ]);
        }

        if ($isReturnOrRefund && ! $returnRefunds->isWithinReturnWindow($item, $order)) {
            $deadline = $returnRefunds->returnDeadline($item, $order);
            $message = $deadline
                ? 'Le délai de retour de cet article a expiré le ' . $deadline->format('d/m/Y') . '. Vous pouvez ouvrir une réclamation auprès du support OVANIE.'
                : 'La date de livraison réelle de cet article n’est pas disponible. Ouvrez une réclamation afin qu’OVANIE vérifie le dossier.';

            throw ValidationException::withMessages([
                'order_item_id' => $message,
            ]);
        }

        $availableQuantity = $returnRefunds->availableQuantity($item, $data['return_type']);
        if ((int) $data['quantity'] > $availableQuantity) {
            throw ValidationException::withMessages([
                'quantity' => "La quantité maximale encore disponible pour une demande est {$availableQuantity}.",
            ]);
        }

        if ($availableQuantity <= 0) {
            throw ValidationException::withMessages([
                'order_item_id' => 'Toute la quantité disponible pour cet article est déjà concernée par une demande antérieure ou en cours.',
            ]);
        }

        $photoPath = $request->hasFile('photo_proof')
            ? $request->file('photo_proof')->store('private-documents/return-proof', 'local')
            : null;

        $return = DB::transaction(function () use ($request, $data, $order, $item, $photoPath, $returnRefunds) {
            $lockedUser = \App\Models\User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $lockedUser->deletion_in_progress || $lockedUser->status === 'suspended') {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Votre compte ne peut plus créer de nouvelle demande.',
                ]);
            }

            $lockedItem = $order->items()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $availableQuantity = $returnRefunds->availableQuantity($lockedItem, $data['return_type']);

            if ((int) $data['quantity'] > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "La quantité maximale encore disponible pour une demande est {$availableQuantity}.",
                ]);
            }

            $return = ReturnModel::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'client_id' => $request->user()->id,
                'vendor_id' => $item->product?->shop?->user_id,
                'shop_id' => $lockedItem->shop_id ?: $item->product?->shop_id,
                'product_id' => $lockedItem->product_id,
                'quantity' => $data['quantity'],
                'order_reference' => $order->order_number,
                'product_name' => $item->product?->name ?: 'Produit commande',
                'reason' => $data['reason'],
                'request_date' => now()->toDateString(),
                'status' => ReturnModel::STATUS_PENDING,
                'return_type' => $data['return_type'],
                'logistics_status' => $returnRefunds->initialLogisticsStatus(
                    $data['return_type'],
                    $lockedItem->delivery_provider
                ),
                'photo_proof' => $photoPath,
            ]);

            $lockedItem->forceFill([
                'return_status' => $data['return_type'] === 'claim' ? 'claim_pending' : ReturnModel::STATUS_PENDING,
                'payout_status' => 'blocked',
            ])->save();

            app(OrderWorkflowService::class)->recordHistory($order, $lockedItem, 'return', null, ReturnModel::STATUS_PENDING, [
                'actor_type' => 'client',
                'user_id' => $request->user()->id,
                'label' => match ($data['return_type']) {
                    'claim' => 'Réclamation enregistrée',
                    'refund' => 'Demande de remboursement enregistrée',
                    default => 'Demande de retour enregistrée',
                },
                'message' => $data['reason'],
                'metadata' => ['return_id' => $return->id, 'quantity' => $data['quantity']],
            ]);

            return $return;
        }, 3);

        app(OrderWorkflowService::class)->notify(
            $item->product?->shop?->user,
            'Retour / réclamation client',
            'Une demande concerne la commande ' . $order->order_number,
            [
                'category' => 'returns',
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'url' => route('vendor.returns.index'),
            ]
        );

        return redirect()->route('client.returns')->with('success', 'Votre demande a été envoyée avec succès.');
    }

    public function vouchers(Request $request, LoyaltyService $loyaltyService)
    {
        $user = $request->user();
        $points = (int) ($user->loyalty_points ?? 0);

        return view('client.vouchers', [
            'client' => $user,
            'points' => $points,
            'availableDiscount' => $loyaltyService->calculateDiscount($points),
            'recentOrders' => $this->ordersQuery($user->id)->latest()->take(4)->get(),
            'loyaltyTransactions' => $user->loyaltyTransactions()->latest()->take(20)->get(),
        ]);
    }

    public function addresses(Request $request)
    {
        return view('client.addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function storeAddress(Request $request, GeocodingService $geocoding)
    {
        $data = $this->validateAddress($request);
        $data = $this->resolveAddressCoordinates($data, $geocoding);
        $data['user_id'] = $request->user()->id;

        DB::transaction(function () use ($request, &$data) {
            if ($request->boolean('is_default') || ! $request->user()->addresses()->exists()) {
                $request->user()->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            Address::create($data);
        });

        return back()->with('success', 'Adresse ajoutée. La position logistique a été calculée en arrière-plan.');
    }

    public function updateAddress(Request $request, Address $address, GeocodingService $geocoding)
    {
        $this->authorizeAddress($request, $address);
        $data = $this->resolveAddressCoordinates($this->validateAddress($request), $geocoding);

        DB::transaction(function () use ($request, $address, &$data) {
            if ($request->boolean('is_default')) {
                $request->user()->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            $address->update($data);
        });

        return back()->with('success', 'Adresse modifiée.');
    }

    public function destroyAddress(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);
        $wasDefault = (bool) $address->is_default;

        DB::transaction(function () use ($request, $address, $wasDefault) {
            $address->delete();

            if ($wasDefault) {
                $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Adresse supprimée.');
    }

    public function defaultAddress(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Adresse principale mise à jour.');
    }

    public function payments(Request $request)
    {
        return view('client.payments', [
            'methods' => $request->user()->paymentMethods()->orderByDesc('is_default')->latest()->get(),
            'payments' => Payment::with('order')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->take(8)
                ->get(),
            'points' => (int) ($request->user()->loyalty_points ?? 0),
        ]);
    }

    public function storePaymentMethod(Request $request)
    {
        $data = $request->validate([
            'operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'account_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['type'] = 'mobile_money';

        DB::transaction(function () use ($request, $data) {
            if ($request->boolean('is_default') || ! $request->user()->paymentMethods()->exists()) {
                $request->user()->paymentMethods()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            ClientPaymentMethod::create($data);
        });

        return back()->with('success', 'Moyen de paiement ajouté.');
    }

    public function destroyPaymentMethod(Request $request, ClientPaymentMethod $method)
    {
        abort_unless((int) $method->user_id === (int) $request->user()->id, 403);
        $wasDefault = (bool) $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $request->user()->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Moyen de paiement supprimé.');
    }

    public function defaultPaymentMethod(Request $request, ClientPaymentMethod $method)
    {
        abort_unless((int) $method->user_id === (int) $request->user()->id, 403);

        DB::transaction(function () use ($request, $method) {
            $request->user()->paymentMethods()->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        return back()->with('success', 'Moyen de paiement principal mis à jour.');
    }

    public function notifications(Request $request)
    {
        $query = $request->user()->notifications();
        $type = (string) $request->query('type', '');

        $categoryMap = [
            'orders' => ['orders', 'order'],
            'payments' => ['payments', 'payment'],
            'delivery' => ['logistics', 'delivery', 'deliveries'],
            'returns' => ['returns', 'return', 'refund'],
            'promotions' => ['promo', 'promotion', 'promotions'],
            'system' => ['system', 'security', 'account', 'support'],
            'newsletter' => ['newsletter'],
            'cart' => ['cart'],
        ];

        if ($type !== '' && isset($categoryMap[$type])) {
            $query->whereIn('data->category', $categoryMap[$type]);
        }

        return view('client.notifications', [
            'notifications' => $query->latest()->paginate(20)->withQueryString(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readNotification(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $url = data_get($item->data, 'url');
        if (is_string($url) && $this->isSafeNotificationUrl($request, $url)) {
            return redirect()->to($url);
        }

        return back();
    }

    public function readAllNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }

    public function settings(Request $request)
    {
        return view('client.settings', ['client' => $request->user()]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['homme', 'femme', 'non_precise'])],
            'city' => ['nullable', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['particulier', 'professionnel', 'artisan'])],
            'locale' => ['nullable', Rule::in(['fr', 'en'])],
            'currency' => ['nullable', Rule::in(['XOF', 'EUR'])],
            'timezone' => ['nullable', 'timezone'],
            'date_format' => ['nullable', Rule::in(['d/m/Y', 'm/d/Y'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: $user->name;
        $user->update($data);

        return back()->with('success', 'Votre demande a été enregistrée et transmise pour traitement.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['nullable', 'boolean'],
        ]);

        $allowedTypes = [
            'orders',
            'payments',
            'deliveries',
            'returns',
            'promotions',
            'account',
            'support',
            'security',
            'newsletter',
            'cart',
        ];
        $allowedChannels = ['email', 'sms', 'in_app', 'push'];
        $preferences = [];
        $existingPreferences = $request->user()->notification_preferences ?? [];

        foreach ($allowedTypes as $type) {
            foreach ($allowedChannels as $channel) {
                // L'interface Web historique ne possède pas encore forcément
                // la case Push. Dans ce cas, conserver la préférence mobile au
                // lieu de la remettre silencieusement à false.
                $fallback = $channel === 'push'
                    ? (bool) data_get($existingPreferences, "{$type}.push", true)
                    : false;
                $preferences[$type][$channel] = (bool) data_get(
                    $validated,
                    "preferences.{$type}.{$channel}",
                    $fallback
                );
            }
        }

        // Les alertes de sécurité restent toujours actives : elles protègent le compte.
        $preferences['security'] = ['email' => true, 'sms' => true, 'in_app' => true, 'push' => true];

        $request->user()->update(['notification_preferences' => $preferences]);
        return back()->with('success', 'Préférences de notification enregistrées.');
    }

    public function requestAccountDeletionCode(
        Request $request,
        AccountDeletionService $accountDeletionService,
        AccountDeletionChallengeService $challengeService
    ) {
        $user = $request->user();

        if (! (filled($user->google_id) || filled($user->facebook_id))) {
            return back()->with('error', 'Le code de sécurité est réservé aux comptes connectés par Google ou Facebook.');
        }

        $accountDeletionService->assertCanSelfDelete($user);
        $challengeService->issue($user);

        return back()->with(
            'success',
            'Un code de confirmation à usage unique a été envoyé à l’adresse e-mail de votre compte.'
        );
    }

    public function deleteAccount(
        Request $request,
        AccountDeletionService $accountDeletionService,
        AccountDeletionChallengeService $challengeService
    ) {
        $user = $request->user();
        $isSocialAccount = filled($user->google_id) || filled($user->facebook_id);

        $rules = [
            'delete_confirmation' => ['required', Rule::in(['SUPPRIMER'])],
        ];

        if ($isSocialAccount) {
            $rules['deletion_code'] = ['required', 'digits:6'];
        } else {
            $rules['password'] = ['required', 'current_password'];
        }

        $request->validate($rules, [
            'delete_confirmation.in' => 'Saisissez exactement SUPPRIMER pour confirmer cette action irréversible.',
            'deletion_code.digits' => 'Le code de suppression doit contenir exactement 6 chiffres.',
        ]);

        $accountDeletionService->assertCanSelfDelete($user);

        if ($isSocialAccount) {
            $challengeService->consume($user, (string) $request->input('deletion_code'));
        }

        $accountDeletionService->anonymize($user);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with(
            'success',
            'Votre compte a été supprimé et vos données personnelles ont été anonymisées.'
        );
    }

    private function ordersQuery(int $userId)
    {
        return Order::query()->operational()->where('client_id', $userId)->with(['items.product', 'payments', 'shipments']);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['home', 'office', 'site', 'warehouse', 'other'])],
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'commune' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function resolveAddressCoordinates(array $data, GeocodingService $geocoding): array
    {
        try {
            if (isset($data['latitude'], $data['longitude'])) {
                $geo = $geocoding->reverse((float) $data['latitude'], (float) $data['longitude']);
            } else {
                $query = collect([
                    $data['address'] ?? null,
                    $data['quartier'] ?? null,
                    $data['commune'] ?? null,
                    $data['city'] ?? null,
                    $data['country'] ?? "Cote d'Ivoire",
                ])->filter()->implode(', ');

                $geo = $geocoding->search($query, config('geo.country_code', 'CI'))[0] ?? null;
            }

            if ($geo) {
                $data['latitude'] = $geo['latitude'] ?? $data['latitude'] ?? null;
                $data['longitude'] = $geo['longitude'] ?? $data['longitude'] ?? null;
                $resolved = (array) ($geo['resolved_location'] ?? []);

                if (($resolved['is_abidjan'] ?? false) === true) {
                    $data['city'] = 'Abidjan';
                    $data['commune'] = $resolved['commune']
                        ?: app(\App\Services\Geo\AbidjanLocalityRegistry::class)->canonicalCommune($data['commune'] ?? null)
                        ?: ($data['commune'] ?? 'Abidjan');
                    $data['quartier'] = $resolved['quartier'] ?? $data['quartier'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $data;
    }

    private function isSafeNotificationUrl(Request $request, string $url): bool
    {
        if (Str::startsWith($url, '/')) {
            return ! Str::startsWith($url, '//');
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            && $host !== ''
            && hash_equals(Str::lower($request->getHost()), Str::lower($host));
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_unless((int) $address->user_id === (int) $request->user()->id, 403);
    }
}
