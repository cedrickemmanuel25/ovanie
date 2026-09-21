<?php

namespace App\Http\Controllers;

use App\Models\BusinessOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function catalog(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'lead_time' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'string', 'max:120'],
            'offer_type' => ['nullable', 'in:catalogue,sur_demande'],
        ]);

        $businessOffers = BusinessOffer::query()
            ->published()
            ->with(['product.category', 'product.shop', 'product.images'])
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%"));
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $category) => $query
                ->whereHas('product', fn ($product) => $product->where('category_id', $category)))
            ->when($filters['minimum_quantity'] ?? null, fn ($query, $quantity) => $query
                ->where('minimum_quantity', '<=', $quantity))
            ->when($filters['lead_time'] ?? null, fn ($query, $leadTime) => $query
                ->where('lead_time', $leadTime))
            ->when($filters['zone'] ?? null, fn ($query, $zone) => $query
                ->whereHas('product.shop', fn ($shop) => $shop
                    ->where('city', $zone)->orWhere('commune', $zone)))
            ->when(($filters['offer_type'] ?? null) === 'catalogue', fn ($query) => $query->whereNotNull('product_id'))
            ->when(($filters['offer_type'] ?? null) === 'sur_demande', fn ($query) => $query->whereNull('product_id'))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $categories = DB::table('business_offers')
            ->join('products', 'products.id', '=', 'business_offers.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('business_offers.status', 'published')
            ->select('categories.id', 'categories.name')
            ->distinct()->orderBy('categories.name')->get();

        $leadTimes = BusinessOffer::query()->published()
            ->whereNotNull('lead_time')->where('lead_time', '!=', '')
            ->distinct()->orderBy('lead_time')->pluck('lead_time');

        $zones = DB::table('business_offers')
            ->join('products', 'products.id', '=', 'business_offers.product_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('business_offers.status', 'published')
            ->select('shops.city', 'shops.commune')->get()
            ->flatMap(fn ($shop) => [$shop->commune, $shop->city])
            ->filter()->unique()->sort()->values();

        return view('catalog.business', compact('businessOffers', 'categories', 'leadTimes', 'zones', 'filters'));
    }
}
