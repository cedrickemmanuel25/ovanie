<?php

namespace App\Http\Controllers;

use App\Services\PublicProductVisibilityService;

class DevisWebController extends Controller
{
    public function create(PublicProductVisibilityService $visibility)
    {
        $products = $visibility->query([], true)
            ->take(700)
            ->get();

        $ovanieBootstrapProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'promo_price' => $product->promo_price,
                'final_price' => $product->final_price,
                'stock' => $product->stock,
            ];
        })->values();

        return view('devis', compact('ovanieBootstrapProducts'));
    }
}
