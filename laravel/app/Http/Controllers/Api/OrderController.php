<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientOrderResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['items.product', 'payments'])
            ->where('client_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(max(1, min(100, (int) $request->query('per_page', 20))));

        return ClientOrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $order->load(['items.product', 'payments']);

        return new ClientOrderResource($order);
    }

    /**
     * L'ancien endpoint créait une commande partielle en contournant le checkout.
     * Il alimente désormais le panier canonique ; la commande est créée uniquement
     * par le workflow de checkout complet.
     */
    public function store(Request $request, PublicProductVisibilityService $visibility)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = $visibility->query([], false)
            ->whereKey((int) $data['product_id'])
            ->first();

        if (! $product) {
            return response()->json([
                'message' => 'Ce produit n’est plus disponible à la commande.',
            ], 422);
        }

        $quantity = (int) $data['quantity'];
        if ($quantity > (int) $product->stock) {
            return response()->json([
                'message' => 'Stock insuffisant pour la quantité demandée.',
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $newQuantity = (int) $item->quantity + $quantity;
            if ($newQuantity > (int) $product->stock) {
                return response()->json([
                    'message' => 'La quantité totale du panier dépasse le stock disponible.',
                ], 422);
            }

            $item->update([
                'quantity' => $newQuantity,
                'price' => $product->final_price ?? $product->promo_price ?? $product->price,
            ]);
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'price' => $product->final_price ?? $product->promo_price ?? $product->price,
                'quantity' => $quantity,
            ]);
        }

        return response()->json([
            'message' => 'Produit ajouté au panier. Finalisez ensuite le checkout pour créer la commande.',
            'cart_item' => [
                'id' => $item->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'main_image_url' => $product->main_image_url,
                ],
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'subtotal' => round((float) $item->price * (int) $item->quantity, 2),
            ],
            'next_step' => 'checkout',
        ], 202);
    }
}
