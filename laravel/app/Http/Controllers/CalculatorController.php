<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Services\ProductCalculatorService;
use App\Services\GuestCartService;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function __construct(private ProductCalculatorService $calculator)
    {
    }

    public function index()
    {
        return view('calculator.index', [
            'categories' => $this->calculator->categories(),
            'estimate' => null,
            'input' => [],
        ]);
    }

    public function estimate(Request $request)
    {
        $validated = $this->validatedEstimate($request);
        $estimate = $this->calculator->estimate($validated);

        if ($request->expectsJson()) {
            return response()->json($estimate);
        }

        return view('calculator.index', [
            'categories' => $this->calculator->categories(),
            'estimate' => $estimate,
            'input' => $validated,
        ]);
    }

    public function addToCart(Request $request, GuestCartService $guestCart, PublicProductVisibilityService $visibility)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);
        $stock = max(0, (int) ($product->stock ?? 0));
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));

        $isPubliclyEligible = $visibility->query([], false)
            ->whereKey($product->getKey())
            ->exists();

        if (! $isPubliclyEligible || $stock < $minimum || $quantity < $minimum || $quantity > $stock) {
            $message = ! $isPubliclyEligible
                ? 'Ce produit est indisponible pour le moment.'
                : ($stock < $minimum
                    ? 'Stock insuffisant pour ce produit.'
                    : ($quantity < $minimum
                        ? "La quantité minimale de commande est de {$minimum} unité(s)."
                        : "Stock insuffisant : seulement {$stock} unité(s) disponible(s)."));

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : redirect()->route('calculator.index')->with('error', $message);
        }

        if (! $request->user()) {
            $guestCart->add($request, $product, $quantity);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produit ajouté au panier',
                    'cart_count' => $guestCart->count($request),
                ]);
            }

            return redirect()->route('cart.index')->with('success', 'Produit ajouté au panier');
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $item = $cart->items()->where('product_id', $product->id)->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->price = $product->final_price ?? $product->price;
            $item->save();
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'price' => $product->final_price ?? $product->price,
                'quantity' => $quantity,
            ]);
        }

        $request->user()->removeFavoriteProduct((int) $product->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Produit ajoute au panier',
                'cart_count' => $cart->items()->sum('quantity'),
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Produit ajoute au panier');
    }

    private function validatedEstimate(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'in:ciment,sable,gravier,carrelage,peinture'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'thickness' => ['nullable', 'numeric', 'min:0'],
            'surface' => ['nullable', 'numeric', 'min:0'],
            'layers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'waste_margin' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'unit_budget' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
