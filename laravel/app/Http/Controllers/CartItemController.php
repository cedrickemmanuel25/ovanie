<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CartItem;

class CartItemController extends Controller
{
    // Liste tous les items du panier
    public function index()
    {
        $items = CartItem::all();
        return response()->json($items);
    }

    // Crée un nouvel item dans le panier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => 'required|integer|exists:carts,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = CartItem::create($validated);
        return response()->json($item, 201);
    }

    // Affiche un item spécifique
    public function show($id)
    {
        $item = CartItem::findOrFail($id);
        return response()->json($item);
    }

    // Met à jour un item
    public function update(Request $request, $id)
    {
        $item = CartItem::findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
            // pas besoin de changer cart_id ou product_id normalement
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    // Supprime un item du panier
    public function destroy($id)
    {
        $item = CartItem::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
