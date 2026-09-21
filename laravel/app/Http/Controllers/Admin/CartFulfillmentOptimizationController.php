<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartFulfillmentOptimization;

class CartFulfillmentOptimizationController extends Controller
{
    public function index()
    {
        $optimizations = CartFulfillmentOptimization::query()
            ->with(['order', 'cart', 'originalProduct', 'fulfillmentProduct', 'originalShop', 'fulfillmentShop'])
            ->latest()
            ->paginate(25);

        return view('admin.logistics.cart-optimizations.index', compact('optimizations'));
    }

    public function show(CartFulfillmentOptimization $optimization)
    {
        $optimization->load(['order', 'cart', 'originalProduct', 'fulfillmentProduct', 'originalShop', 'fulfillmentShop']);

        return view('admin.logistics.cart-optimizations.show', compact('optimization'));
    }
}
