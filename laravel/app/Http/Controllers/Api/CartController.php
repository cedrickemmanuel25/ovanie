<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartPriceSyncService;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, CartPriceSyncService $priceSync)
    {
        $cart = $this->cartFor($request);
        $cart->load(['items.product']);
        $priceSync->sync($cart);
        $cart->load(['items.product']);

        $items = $cart->items->map(fn (CartItem $item) => $this->publicItem($item))->values();

        return response()->json([
            'cart' => [
                'id' => $cart->id,
                'items' => $items,
                'subtotal' => round((float) $items->sum('subtotal'), 2),
                'items_count' => (int) $items->sum('quantity'),
            ],
        ]);
    }

    public function store(Request $request, PublicProductVisibilityService $visibility)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = $visibility->query([], false)
            ->whereKey((int) $validated['product_id'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Ce produit n’est plus disponible.'], 422);
        }

        $cart = $this->cartFor($request);
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $quantity = (int) ($validated['quantity'] ?? $minimum);

        if ((int) $product->stock < $minimum) {
            return response()->json([
                'message' => "Stock insuffisant pour respecter le minimum de commande de {$minimum} unité(s).",
            ], 422);
        }

        if ($quantity < $minimum) {
            return response()->json([
                'message' => "La quantité minimale de commande est de {$minimum} unité(s).",
            ], 422);
        }
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        $targetQuantity = $quantity + (int) ($item?->quantity ?? 0);
        if ($targetQuantity > (int) $product->stock) {
            return response()->json(['message' => 'Stock insuffisant.'], 422);
        }

        $price = (float) ($product->final_price ?? $product->promo_price ?? $product->price);

        if ($item) {
            $item->update(['quantity' => $targetQuantity, 'price' => $price]);
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'price' => $price,
                'quantity' => $quantity,
            ]);
        }

        $request->user()->removeFavoriteProduct((int) $product->id);
        $item->setRelation('product', $product);

        return response()->json([
            'data' => $this->publicItem($item),
            'is_favorite' => false,
        ], 201);
    }

    public function update(Request $request, CartItem $cartItem, PublicProductVisibilityService $visibility)
    {
        $this->authorizeCartItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = $visibility->query([], false)->whereKey($cartItem->product_id)->first();
        if (! $product) {
            return response()->json(['message' => 'Ce produit n’est plus disponible.'], 422);
        }

        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $stock = max(0, (int) ($product->stock ?? 0));
        $quantity = (int) $validated['quantity'];

        if ($stock < $minimum) {
            return response()->json([
                'message' => "Stock insuffisant pour respecter le minimum de commande de {$minimum} unité(s).",
            ], 422);
        }

        if ($quantity < $minimum) {
            return response()->json([
                'message' => "La quantité minimale de commande est de {$minimum} unité(s).",
            ], 422);
        }

        if ($quantity > $stock) {
            return response()->json([
                'message' => "Stock insuffisant : seulement {$stock} unité(s) disponible(s).",
            ], 422);
        }

        $cartItem->update(['quantity' => $quantity]);
        $cartItem->setRelation('product', $product);

        return response()->json(['data' => $this->publicItem($cartItem)]);
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->authorizeCartItem($request, $cartItem);
        $cartItem->delete();

        return response()->json(['message' => 'Produit supprimé du panier.']);
    }

    public function clear(Request $request)
    {
        $cart = $this->cartFor($request);
        $cart->items()->delete();

        return response()->json(['message' => 'Panier vidé.']);
    }

    private function publicItem(CartItem $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'main_image_url' => $product->main_image_url,
            ] : null,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->price,
            'subtotal' => round((float) $item->price * (int) $item->quantity, 2),
        ];
    }

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        $cartItem->loadMissing('cart');

        abort_if(! $cartItem->cart || (int) $cartItem->cart->user_id !== (int) $request->user()->id, 403);
    }
}
