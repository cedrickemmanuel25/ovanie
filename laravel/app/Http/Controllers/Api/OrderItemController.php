<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Afficher les items d’une commande donnée
     */
    public function index($orderId)
    {
        $items = OrderItem::where('order_id', $orderId)->with('product')->get();

        return response()->json($items);
    }

    // Ajouter, modifier, supprimer un item selon besoin (optionnel)
}
