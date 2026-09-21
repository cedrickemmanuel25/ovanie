<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Services\LogisticsVehicleResolver;
use App\Services\OvanieDeliveryPriceCalculator;
use App\Services\PublicProductVisibilityService;
use App\Services\CartPriceSyncService;
use App\Services\GuestCartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected function computeTotals(object $cart): array
    {
        if ($cart instanceof Cart) {
            $cart->loadMissing('items.product');
        }

        $subtotal = (int) $cart->items->sum(function ($item) {
            $price = $item->price ?: ($item->product->final_price ?? $item->product->price ?? 0);
            return (float) $price * (int) $item->quantity;
        });

        $deliveryFee = null;
        $deliveryPending = true;
        $grandTotal = $subtotal;

        return compact('subtotal', 'deliveryFee', 'deliveryPending', 'grandTotal');
    }

    protected function persistCartTotals(Cart $cart): void
    {
        $totals = $this->computeTotals($cart);
        $cart->delivery_fee = $totals['deliveryFee'] ?? 0;
        $cart->grand_total = $totals['grandTotal'];
        $cart->save();
    }

    protected function normalizeCartQuantities(Cart $cart, PublicProductVisibilityService $visibility): array
    {
        $cart->load('items.product');

        $messages = [];
        $eligibleProductIds = $visibility->query([], false)
            ->whereIn('products.id', $cart->items->pluck('product_id')->filter()->all())
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product || ! in_array((int) $product->id, $eligibleProductIds, true)) {
                $item->delete();
                $messages[] = 'Un produit devenu indisponible a été retiré du panier.';
                continue;
            }

            $stock = max((int) ($product->stock ?? 0), 0);
            $minimum = max(1, (int) ($product->min_order_quantity ?? 1));

            if ($stock < $minimum) {
                $item->delete();
                $messages[] = "Le produit {$product->name} ne dispose plus du stock minimum requis ({$minimum}) et a été retiré du panier.";
                continue;
            }

            if ((int) $item->quantity > $stock) {
                $item->quantity = $stock;
                $item->save();
                $messages[] = "La quantité de {$product->name} a été ajustée au stock disponible ({$stock}).";
            }

            if ((int) $item->quantity < $minimum) {
                $item->quantity = $minimum;
                $item->save();
                $messages[] = "La quantité de {$product->name} a été ajustée au minimum de commande ({$minimum}).";
            }

        }

        return $messages;
    }

    public function index(
        Request $request,
        OvanieDeliveryPriceCalculator $logisticsCalculator,
        LogisticsVehicleResolver $vehicleResolver,
        PublicProductVisibilityService $visibility,
        CartPriceSyncService $priceSync,
        GuestCartService $guestCart
    ) {

        $request->session()->forget('checkout_cart_item_ids');
        if (! $request->user()) {
            $request->session()->forget([
                GuestCartService::CHECKOUT_PRODUCT_IDS_KEY,
                GuestCartService::CHECKOUT_SELECTION_REQUIRED_KEY,
            ]);
        }

        if ($request->user()) {
            $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

            $messages = $this->normalizeCartQuantities($cart, $visibility);
            $priceSyncResult = $priceSync->sync($cart);
            $messages = array_merge($messages, $priceSyncResult['messages']);
            $this->persistCartTotals($cart);

            $cart->load([
                'items.product.category',
                'items.product.shop',
                'items.product.images',
            ]);
        } else {
            $snapshot = $guestCart->snapshot($request);
            $cart = $snapshot['cart'];
            $messages = $snapshot['messages'];
        }

        $totals = $this->computeTotals($cart);
        $subtotal = $totals['subtotal'];
        $deliveryFee = $totals['deliveryFee'];
        $deliveryPending = $totals['deliveryPending'];
        $grandTotal = $totals['grandTotal'];

        $logisticsItems = $cart->items->filter(fn ($item) => $item->product)->values();
        $totalWeightKg = round($logisticsCalculator->weightKg($logisticsItems), 3);
        $totalVolumeM3 = round($logisticsCalculator->volumeM3($logisticsItems), 4);
        $vehicle = $vehicleResolver->resolve($totalWeightKg, $totalVolumeM3);

        $missingWeightItems = $logisticsItems->filter(function ($item) {
            return (float) ($item->product?->weight_kg ?? $item->product?->weight ?? 0) <= 0;
        })->count();

        $missingVolumeItems = $logisticsItems->filter(function ($item) {
            $product = $item->product;
            $volume = (float) ($product?->volume_m3 ?? $product?->volume ?? 0);
            $hasDimensions = (float) ($product?->length_cm ?? 0) > 0
                && (float) ($product?->width_cm ?? 0) > 0
                && (float) ($product?->height_cm ?? 0) > 0;

            return $volume <= 0 && ! $hasDimensions;
        })->count();

        $cartLogisticsSummary = [
            'total_weight_kg' => $totalWeightKg,
            'total_volume_m3' => $totalVolumeM3,
            'recommended_vehicle_code' => $vehicle['code'],
            'recommended_vehicle_label' => $vehicle['label'],
            'metrics_complete' => $missingWeightItems === 0 && $missingVolumeItems === 0,
            'missing_weight_items' => $missingWeightItems,
            'missing_volume_items' => $missingVolumeItems,
        ];

        $vehicleCatalog = $vehicleResolver->catalog();

        $cartProductIds = $cart->items->pluck('product_id')->filter()->values();
        $cartCategoryIds = $cart->items
            ->map(fn ($item) => $item->product?->category_id)
            ->filter()
            ->unique()
            ->values();

        $similarProducts = $visibility->query(['category', 'shop', 'images'], true)
            ->withCount('reviews')
            ->whereNotIn('id', $cartProductIds)
            ->when($cartCategoryIds->count(), function ($query) use ($cartCategoryIds) {
                $query->whereIn('category_id', $cartCategoryIds);
            })
            ->latest()
            ->take(10)
            ->get();

        if ($similarProducts->count() < 5) {
            $fallback = $visibility->query(['category', 'shop', 'images'], true)
                ->withCount('reviews')
                ->whereNotIn('id', $cartProductIds->merge($similarProducts->pluck('id'))->unique()->values())
                ->latest()
                ->take(10 - $similarProducts->count())
                ->get();

            $similarProducts = $similarProducts->merge($fallback)->values();
        }

        return view('cart', compact(
            'cart',
            'deliveryFee',
            'deliveryPending',
            'grandTotal',
            'subtotal',
            'messages',
            'similarProducts',
            'cartLogisticsSummary',
            'vehicleCatalog'
        ));
    }

    public function add(Request $request, $productId, PublicProductVisibilityService $visibility)
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1|max:1000000',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($productId);
        $quantity = (int) $request->input('quantity', 1);
        $stock = max((int) ($product->stock ?? 0), 0);
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));

        $isPubliclyEligible = $visibility->query([], false)
            ->whereKey($product->getKey())
            ->exists();

        if (! $isPubliclyEligible) {
            return $this->stockErrorResponse($request, 'Ce produit est indisponible pour le moment.');
        }

        if ($stock < $minimum) {
            return $this->stockErrorResponse($request, "Stock insuffisant pour respecter le minimum de commande de {$minimum} unité(s).");
        }

        if ($quantity < $minimum) {
            return $this->stockErrorResponse($request, "La quantité minimale de commande est de {$minimum} unité(s).");
        }

        if ($quantity > $stock) {
            return $this->stockErrorResponse($request, "Stock insuffisant : seulement {$stock} unité(s) disponible(s).");
        }

        if (! $user) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);
            $currentQuantity = $guestCart->quantityFor($request, (int) $product->id);
            $newQuantity = $currentQuantity + $quantity;

            if ($newQuantity > $stock) {
                $guestCart->update($request, (int) $product->id, $stock);
                $snapshot = $guestCart->snapshot($request);

                return $this->stockErrorResponse(
                    $request,
                    "Stock limité : vous ne pouvez pas dépasser {$stock} unité(s) pour ce produit.",
                    $snapshot['cart']
                );
            }

            $guestCart->add($request, $product, $quantity);
            $snapshot = $guestCart->snapshot($request);
            $cart = $snapshot['cart'];

            if ($request->input('action_type') === 'buy_now') {
                $request->session()->put(GuestCartService::CHECKOUT_PRODUCT_IDS_KEY, [(int) $product->id]);
                $request->session()->put('url.intended', route('checkout.index'));

                if ($request->expectsJson()) {
                    return $this->cartJson($cart, true, 'Produit ajouté au panier', [
                        'redirect_url' => route('login'),
                        'authentication_required' => true,
                    ]);
                }

                return redirect()->route('login')
                    ->with('info', 'Connectez-vous ou créez un compte pour passer votre commande.');
            }

            if ($request->expectsJson()) {
                return $this->cartJson($cart, true, 'Produit ajouté au panier');
            }

            return redirect()->route('cart.index')->with('success', 'Produit ajouté au panier');
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->where('product_id', $product->id)->first();
        $unitPrice = $product->final_price ?? $product->price ?? 0;

        if ($item) {
            $newQuantity = (int) $item->quantity + $quantity;

            if ($newQuantity > $stock) {
                $item->quantity = $stock;
                $item->price = $unitPrice;
                $item->price_source = 'catalog';
                $item->negotiation_id = null;
                $item->save();

                $this->persistCartTotals($cart);

                return $this->stockErrorResponse(
                    $request,
                    "Stock limité : vous ne pouvez pas dépasser {$stock} unité(s) pour ce produit.",
                    $cart
                );
            }

            $item->quantity = $newQuantity;
            $item->price = $unitPrice;
            $item->price_source = 'catalog';
            $item->negotiation_id = null;
            $item->save();
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'original_product_id' => $product->id,
                'fulfillment_product_id' => $product->id,
                'original_shop_id' => $product->shop_id,
                'fulfillment_shop_id' => $product->shop_id,
                'optimization_applied' => false,
                'price' => $unitPrice,
                'price_source' => 'catalog',
                'negotiation_id' => null,
                'quantity' => $quantity,
            ]);
        }

        $this->persistCartTotals($cart);
        $user->removeFavoriteProduct((int) $product->id);

        if ($request->input('action_type') === 'buy_now') {
            $request->session()->put('checkout_cart_item_ids', [(int) $item->id]);
        } else {
            $request->session()->forget('checkout_cart_item_ids');
        }

        if ($request->expectsJson() && $request->input('action_type') === 'buy_now') {
            return $this->cartJson($cart, true, 'Produit ajoute au panier', [
                'redirect_url' => route('checkout.index'),
            ]);
        }

        if ($request->expectsJson()) {
            return $this->cartJson($cart, true, 'Produit ajouté au panier');
        }

        if ($request->input('action_type') === 'buy_now') {
            return redirect()->route('checkout.index');
        }

        return redirect()->route('cart.index')->with('success', 'Produit ajouté au panier');
    }

    public function updateQuantity(Request $request, $productId, PublicProductVisibilityService $visibility)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
        ]);

        $product = Product::findOrFail($productId);
        $user = $request->user();

        if (! $user) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);

            if ($guestCart->quantityFor($request, (int) $productId) <= 0) {
                abort(404);
            }

            $isPubliclyEligible = $visibility->query([], false)
                ->whereKey($product->getKey())
                ->exists();

            if (! $isPubliclyEligible) {
                $guestCart->remove($request, (int) $productId);
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, 'Ce produit n’est plus disponible et a été retiré du panier.', $snapshot['cart']);
            }

            $stock = max((int) ($product->stock ?? 0), 0);
            $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
            $quantity = (int) $request->quantity;

            if ($stock < $minimum) {
                $guestCart->remove($request, (int) $productId);
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, 'Ce produit est en rupture de stock et a été retiré du panier.', $snapshot['cart']);
            }

            if ($quantity < $minimum) {
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, "La quantité minimale de commande est de {$minimum} unité(s).", $snapshot['cart']);
            }

            $quantity = min($quantity, $stock);
            $guestCart->update($request, (int) $productId, $quantity);
            $snapshot = $guestCart->snapshot($request);
            $cart = $snapshot['cart'];

            if ($request->expectsJson()) {
                return $this->cartJson($cart, true, "Quantité mise à jour. Stock disponible : {$stock}.", [
                    'item_quantity' => $quantity,
                    'line_total' => (float) ($product->final_price ?? $product->price ?? 0) * $quantity,
                ]);
            }

            return redirect()->route('cart.index')->with('success', 'Quantité mise à jour.');
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()->where('product_id', $productId)->firstOrFail();

        $isPubliclyEligible = $visibility->query([], false)
            ->whereKey($product->getKey())
            ->exists();

        if (! $isPubliclyEligible) {
            $item->delete();
            $this->persistCartTotals($cart);

            return $this->stockErrorResponse($request, 'Ce produit n’est plus disponible et a été retiré du panier.', $cart);
        }

        $stock = max((int) ($product->stock ?? 0), 0);
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $quantity = (int) $request->quantity;

        if ($stock < $minimum) {
            $item->delete();
            $this->persistCartTotals($cart);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce produit est en rupture de stock et a été retiré du panier.',
                ], 422);
            }

            return redirect()->route('cart.index')
                ->with('error', 'Ce produit est en rupture de stock et a été retiré du panier.');
        }

        if ($quantity < $minimum) {
            return $this->stockErrorResponse(
                $request,
                "La quantité minimale de commande est de {$minimum} unité(s).",
                $cart
            );
        }

        if ($quantity > $stock) {
            $quantity = $stock;
        }

        $item->quantity = $quantity;
        if (($item->price_source ?? 'catalog') !== 'negotiated') {
            $item->price = $product->final_price ?? $product->price ?? 0;
            $item->price_source = 'catalog';
            $item->negotiation_id = null;
        }
        $item->save();

        $this->persistCartTotals($cart);

        if ($request->expectsJson()) {
            return $this->cartJson($cart, true, "Quantité mise à jour. Stock disponible : {$stock}.", [
                'item_quantity' => $item->quantity,
                'line_total' => (float) $item->price * (int) $item->quantity,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Quantité mise à jour.');
    }

    public function remove(Request $request, $productId)
    {
        if (! $request->user()) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);
            $guestCart->remove($request, (int) $productId);
            $snapshot = $guestCart->snapshot($request);

            if ($request->expectsJson()) {
                return $this->cartJson($snapshot['cart'], true, 'Produit supprimé');
            }

            return redirect()->route('cart.index')->with('success', 'Produit supprimé');
        }

        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->where('product_id', $productId)->delete();
            $this->persistCartTotals($cart);
        }

        if ($request->expectsJson()) {
            return $this->cartJson($cart, true, 'Produit supprimé');
        }

        return redirect()->route('cart.index')->with('success', 'Produit supprimé');
    }

    public function clear(Request $request)
    {
        if (! $request->user()) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);
            $guestCart->clear($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Panier vidé',
                    'cart_count' => 0,
                    'item_count' => 0,
                    'cart_total' => '0',
                    'subtotal' => 0,
                    'delivery_fee' => 0,
                    'delivery_fee_label' => 'Livraison calculée au checkout',
                    'delivery_pending' => true,
                    'grand_total' => 0,
                ]);
            }

            return redirect()->route('cart.index')->with('success', 'Panier vidé');
        }

        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delivery_fee = 0;
            $cart->grand_total = 0;
            $cart->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Panier vidé',
                'cart_count' => 0,
                'item_count' => 0,
                'cart_total' => '0',
                'subtotal' => 0,
                'delivery_fee' => 0,
                'delivery_fee_label' => 'Livraison calculée au checkout',
                'delivery_pending' => true,
                'grand_total' => 0,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Panier vidé');
    }

    public function apiCart(Request $request)
    {
        if (! $request->user()) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);
            $snapshot = $guestCart->snapshot($request);
            return $this->cartJson($snapshot['cart'], true, 'Panier chargé');
        }

        $cart = Cart::where('user_id', $request->user()->id)->first();
        return $this->cartJson($cart, true, 'Panier chargé');
    }

    protected function stockErrorResponse(Request $request, string $message, ?object $cart = null)
    {
        if ($request->expectsJson()) {
            return $this->cartJson($cart, false, $message, [], 422);
        }

        return redirect()->route('cart.index')->with('error', $message);
    }

    protected function cartJson(?object $cart, bool $success = true, string $message = '', array $extra = [], int $status = 200)
    {
        if (!$cart) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
                'cart_count' => 0,
                'item_count' => 0,
                'cart_total' => '0',
                'subtotal' => 0,
            'delivery_fee' => 0,
            'delivery_fee_label' => 'Livraison calculée au checkout',
            'delivery_pending' => true,
            'grand_total' => 0,
            ], $extra), $status);
        }

        if ($cart instanceof Cart) {
            $cart->load('items.product');
        }
        $totals = $this->computeTotals($cart);

        return response()->json(array_merge([
            'success' => $success,
            'message' => $message,
            'cart_count' => (int) $cart->items->sum('quantity'),
            'item_count' => (int) $cart->items->sum('quantity'),
            'cart_total' => (string) $totals['grandTotal'],
            'subtotal' => $totals['subtotal'],
            'delivery_fee' => $totals['deliveryFee'],
            'delivery_fee_label' => $totals['deliveryPending'] ? 'Livraison calculée au checkout' : number_format((float) $totals['deliveryFee'], 0, ',', ' ') . ' FCFA',
            'delivery_pending' => $totals['deliveryPending'],
            'grand_total' => $totals['grandTotal'],
        ], $extra), $status);
    }
}
