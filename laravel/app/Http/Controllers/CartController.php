<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
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
            $cart->loadMissing(['items.product', 'items.variant']);
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
        $cart->load(['items.product.variants', 'items.variant']);

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

            $activeVariants = $product->variants->where('is_active', true);
            if ($activeVariants->isNotEmpty() && ! $item->variant) {
                $item->delete();
                $messages[] = "{$product->name} possède plusieurs modèles/couleurs. L'ancienne ligne a été retirée : choisissez le modèle exact sur la fiche produit.";
                continue;
            }

            if ($item->variant) {
                $variantImage = $item->variant->card_url ?: $item->variant->image_url;
                $dirtySnapshot = $item->variant_label_snapshot !== $item->variant->label
                    || (string) $item->variant_image_snapshot !== (string) $variantImage;

                if ($dirtySnapshot) {
                    $item->forceFill([
                        'variant_label_snapshot' => $item->variant->label,
                        'variant_image_snapshot' => $variantImage ?: null,
                    ])->save();
                }
            }

            $stock = max((int) ($item->variant?->stock ?? $product->stock ?? 0), 0);
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
                'items.variant',
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
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($productId);
        $quantity = (int) $request->input('quantity', 1);
        $variant = null;
        $variantId = $request->integer('product_variant_id') ?: null;
        if ($product->variants()->where('is_active', true)->exists()) {
            if (! $variantId) {
                return $this->stockErrorResponse($request, 'Choisissez un modèle ou une couleur avant d’ajouter ce produit au panier.');
            }
            $variant = $product->variants()->whereKey($variantId)->where('is_active', true)->first();
            if (! $variant) {
                return $this->stockErrorResponse($request, 'Le modèle sélectionné n’est plus disponible.');
            }
        }
        $stock = max((int) ($variant?->stock ?? $product->stock ?? 0), 0);
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
            $currentQuantity = $guestCart->quantityFor($request, (int) $product->id, $variantId);
            $newQuantity = $currentQuantity + $quantity;

            if ($newQuantity > $stock) {
                $guestCart->update($request, (int) $product->id, $stock, $variantId);
                $snapshot = $guestCart->snapshot($request);

                return $this->stockErrorResponse(
                    $request,
                    "Stock limité : vous ne pouvez pas dépasser {$stock} unité(s) pour ce produit.",
                    $snapshot['cart']
                );
            }

            $guestCart->add($request, $product, $quantity, $variant);
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
        $item = $cart->items()->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->first();
        $unitPrice = $variant?->price_override ?? $product->final_price ?? $product->price ?? 0;

        if ($item) {
            $isNegotiated = $item->price_source === 'negotiated';
            $newQuantity = (int) $item->quantity + $quantity;

            if ($newQuantity > $stock) {
                $item->quantity = $stock;
                if ($variant) {
                    $item->product_variant_id = $variant->id;
                    $item->variant_label_snapshot = $variant->label;
                    $item->variant_image_snapshot = $variant->card_url ?: $variant->image_url;
                }
                if (! $isNegotiated) {
                    $item->price = $unitPrice;
                    $item->price_source = 'catalog';
                    $item->negotiation_id = null;
                }
                $item->save();

                $this->persistCartTotals($cart);

                return $this->stockErrorResponse(
                    $request,
                    "Stock limité : vous ne pouvez pas dépasser {$stock} unité(s) pour ce produit.",
                    $cart
                );
            }

            $item->quantity = $newQuantity;
            if ($variant) {
                $item->product_variant_id = $variant->id;
                $item->variant_label_snapshot = $variant->label;
                $item->variant_image_snapshot = $variant->card_url ?: $variant->image_url;
            }
            if (! $isNegotiated) {
                $item->price = $unitPrice;
                $item->price_source = 'catalog';
                $item->negotiation_id = null;
            }
            $item->save();
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'variant_label_snapshot' => $variant?->label,
                'variant_image_snapshot' => $variant ? ($variant->card_url ?: $variant->image_url) : null,
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
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $product = Product::findOrFail($productId);
        $variantId = $request->integer('product_variant_id') ?: null;
        $variant = $variantId
            ? $product->variants()->whereKey($variantId)->where('is_active', true)->first()
            : null;
        if ($product->variants()->where('is_active', true)->exists() && ! $variant) {
            return $this->stockErrorResponse($request, 'Le modèle sélectionné n’est plus disponible.');
        }
        $user = $request->user();

        if (! $user) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);

            if ($guestCart->quantityFor($request, (int) $productId, $variantId) <= 0) {
                abort(404);
            }

            $isPubliclyEligible = $visibility->query([], false)
                ->whereKey($product->getKey())
                ->exists();

            if (! $isPubliclyEligible) {
                $guestCart->remove($request, (int) $productId, $variantId);
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, 'Ce produit n’est plus disponible et a été retiré du panier.', $snapshot['cart']);
            }

            $stock = max((int) ($variant?->stock ?? $product->stock ?? 0), 0);
            $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
            $quantity = (int) $request->quantity;

            if ($stock < $minimum) {
                $guestCart->remove($request, (int) $productId, $variantId);
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, 'Ce modèle est en rupture de stock et a été retiré du panier.', $snapshot['cart']);
            }

            if ($quantity < $minimum) {
                $snapshot = $guestCart->snapshot($request);
                return $this->stockErrorResponse($request, "La quantité minimale de commande est de {$minimum} unité(s).", $snapshot['cart']);
            }

            $quantity = min($quantity, $stock);
            $guestCart->update($request, (int) $productId, $quantity, $variantId);
            $snapshot = $guestCart->snapshot($request);
            $cart = $snapshot['cart'];
            $unitPrice = (float) ($variant?->price_override ?? $product->final_price ?? $product->price ?? 0);

            if ($request->expectsJson()) {
                return $this->cartJson($cart, true, "Quantité mise à jour. Stock disponible : {$stock}.", [
                    'item_quantity' => $quantity,
                    'line_total' => $unitPrice * $quantity,
                ]);
            }

            return redirect()->route('cart.index')->with('success', 'Quantité mise à jour.');
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $item = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->firstOrFail();
        $item->loadMissing('variant');

        $isPubliclyEligible = $visibility->query([], false)
            ->whereKey($product->getKey())
            ->exists();

        if (! $isPubliclyEligible) {
            $item->delete();
            $this->persistCartTotals($cart);
            return $this->stockErrorResponse($request, 'Ce produit n’est plus disponible et a été retiré du panier.', $cart);
        }

        $stock = max((int) ($item->variant?->stock ?? $product->stock ?? 0), 0);
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $quantity = (int) $request->quantity;

        if ($stock < $minimum) {
            $item->delete();
            $this->persistCartTotals($cart);
            return $this->stockErrorResponse($request, 'Ce modèle est en rupture de stock et a été retiré du panier.', $cart);
        }

        if ($quantity < $minimum) {
            return $this->stockErrorResponse($request, "La quantité minimale de commande est de {$minimum} unité(s).", $cart);
        }

        $quantity = min($quantity, $stock);
        $item->quantity = $quantity;
        if (($item->price_source ?? 'catalog') !== 'negotiated') {
            $item->price = $item->variant?->price_override ?? $product->final_price ?? $product->price ?? 0;
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
        // Pas de contrôle « le modèle existe » ici : un article dont le modèle a été retiré
        // du catalogue doit pouvoir être supprimé du panier comme les autres.
        $request->validate([
            'product_variant_id' => 'nullable|integer',
        ]);
        $variantId = $request->integer('product_variant_id') ?: null;

        if (! $request->user()) {
            /** @var GuestCartService $guestCart */
            $guestCart = app(GuestCartService::class);
            $guestCart->remove($request, (int) $productId, $variantId);
            $snapshot = $guestCart->snapshot($request);

            if ($request->expectsJson()) {
                return $this->cartJson($snapshot['cart'], true, 'Produit supprimé');
            }

            return redirect()->route('cart.index')->with('success', 'Produit supprimé');
        }

        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $removed = $cart->items()
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->delete();
            // Ligne enregistrée avec un autre modèle que celui envoyé par la page : on retire le produit quand même.
            if ($removed === 0) {
                $cart->items()->where('product_id', $productId)->delete();
            }
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
            \App\Support\LiveSync::touchUser((int) $cart->user_id, 'cart');
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
            $cart->load(['items.product', 'items.variant']);
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
