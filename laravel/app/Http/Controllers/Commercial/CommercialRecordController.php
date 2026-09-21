<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\AppelOffre;
use App\Models\BusinessRequest;
use App\Models\Devis;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CommercialShopLocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CommercialRecordController extends Controller
{
    public function clients(Request $request)
    {
        return $this->users($request);
    }

    public function vendors(Request $request, CommercialShopLocationService $locations)
    {
        return $this->shops($request, $locations);
    }

    public function products(Request $request)
    {
        return $this->productsList($request);
    }

    public function orders(Request $request)
    {
        return $this->ordersList($request);
    }

    public function businessRequests(Request $request)
    {
        return $this->businessList($request);
    }

    public function quotes(Request $request)
    {
        return $this->quotesList($request);
    }

    private function users(Request $request)
    {
        $commercialId = (int) $request->user()->id;

        $base = User::query()
            ->where('role', 'client')
            ->where('created_by_commercial_id', $commercialId);

        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'with_orders' => (clone $base)->whereHas('orders')->count(),
            'new_this_month' => (clone $base)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        $query = (clone $base)
            ->withCount('orders')
            ->latest();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function (Builder $search) use ($q) {
                $search->where('name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $clients = $query->paginate(18)->withQueryString();

        return view('commercial.clients.index', compact('clients', 'stats'));
    }

    private function shops(Request $request, CommercialShopLocationService $locations)
    {
        $commercialId = (int) $request->user()->id;
        $base = $this->managedShopsQuery($commercialId);

        $stats = [
            'total' => (clone $base)->count(),
            'logistics_ready' => (clone $base)->where('logistics_status', Shop::LOGISTICS_READY)->count(),
            'gps_missing' => (clone $base)
                ->where(function (Builder $mode) {
                    $mode->whereNull('logistics_type')->orWhere('logistics_type', '!=', 'seller');
                })
                ->where(function (Builder $gps) {
                    $gps->whereNull('latitude')
                        ->orWhereNull('longitude')
                        ->orWhereNull('geo_status')
                        ->orWhereNotIn('geo_status', [Shop::GEO_STATUS_RELIABLE, Shop::GEO_STATUS_VERIFIED]);
                })
                ->count(),
            'with_products' => (clone $base)->whereHas('products')->count(),
        ];

        $query = $base
            ->with([
                'user:id,name,email,phone',
                'sellerDeliveryProfile',
                'sellerDeliveryZones',
            ])
            ->withCount(['products', 'orderItems'])
            ->latest();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function (Builder $search) use ($q) {
                $search->where('name', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('commune', 'like', "%{$q}%")
                    ->orWhere('district', 'like', "%{$q}%")
                    ->orWhereHas('user', function (Builder $user) use ($q) {
                        $user->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('readiness')) {
            match ($request->query('readiness')) {
                'ready' => $query->where('logistics_status', Shop::LOGISTICS_READY),
                'gps_missing' => $query
                    ->where(function (Builder $mode) {
                        $mode->whereNull('logistics_type')->orWhere('logistics_type', '!=', 'seller');
                    })
                    ->where(function (Builder $gps) {
                        $gps->whereNull('latitude')
                            ->orWhereNull('longitude')
                            ->orWhereNull('geo_status')
                            ->orWhereNotIn('geo_status', [Shop::GEO_STATUS_RELIABLE, Shop::GEO_STATUS_VERIFIED]);
                    }),
                'kyc_pending' => $query->where('kyc_status', '!=', Shop::KYC_VERIFIED),
                'no_products' => $query->doesntHave('products'),
                default => null,
            };
        }

        $shops = $query->paginate(20)->withQueryString();
        $shops->getCollection()->each(function (Shop $shop) use ($locations) {
            $shop->setAttribute('commercial_readiness', $locations->readiness($shop));
        });

        return view('commercial.vendors.index', compact('shops', 'stats'));
    }

    private function productsList(Request $request)
    {
        $shops = $this->managedShopsQuery((int) $request->user()->id)
            ->with('user:id,name,email')
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $selectedShop = $shops->firstWhere('id', (int) $request->query('shop_id')) ?: $shops->first();
        $query = Product::query()
            ->with('images')
            ->when(
                $selectedShop,
                fn (Builder $products) => $products->where('shop_id', $selectedShop->id),
                fn (Builder $products) => $products->whereRaw('1 = 0'),
            )
            ->latest();

        $this->search($query, $request, ['name', 'sku', 'brand', 'type', 'status']);
        $products = $query->paginate(20)->withQueryString();

        return view('commercial.products.index', compact('shops', 'selectedShop', 'products'));
    }

    private function ordersList(Request $request)
    {
        $commercialId = (int) $request->user()->id;
        $base = $this->scopedOrdersQuery($commercialId);

        $stats = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $query = $base->with([
            'client:id,name,email,phone,created_by_commercial_id',
            'shop:id,name,created_by_commercial_id,managed_by_commercial_id',
            'items:id,order_id,product_id,shop_id,quantity,subtotal,vendor_status,delivery_status',
            'items.product:id,name',
            'items.shop:id,name,created_by_commercial_id,managed_by_commercial_id',
        ])->latest();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function (Builder $search) use ($q) {
                $search->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhereHas('client', function (Builder $client) use ($q) {
                        $client->where('email', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    })
                    ->orWhereHas('items.product', fn (Builder $product) => $product->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $orders = $query->paginate(20)->withQueryString();
        $orders->getCollection()->transform(function (Order $order) use ($commercialId) {
            $clientTracked = (int) $order->client?->created_by_commercial_id === $commercialId;
            $managedItems = $order->items->filter(fn ($item) => $this->shopBelongsToCommercial($item->shop, $commercialId));
            $visibleItems = $clientTracked ? $order->items : $managedItems;
            $amount = $clientTracked
                ? (float) $order->total_amount
                : (float) $managedItems->sum(fn ($item) => (float) $item->subtotal);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'client_name' => $order->client?->name ?: $order->customer_name ?: 'Client',
                'client_email' => $order->client?->email,
                'client_phone' => $order->client?->phone ?: $order->phone,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'amount' => $amount,
                'products' => $visibleItems->pluck('product.name')->filter()->unique()->take(3)->values(),
                'products_count' => $visibleItems->count(),
                'scope_label' => $clientTracked && $managedItems->isNotEmpty()
                    ? 'Client et boutique suivis'
                    : ($clientTracked ? 'Client suivi' : 'Boutique suivie'),
                'created_at' => $order->created_at,
                'can_follow_up' => ! in_array($order->status, ['completed', 'cancelled'], true),
                'lead_query' => [
                    'user_id' => $order->client_id,
                    'contact_name' => $order->client?->name ?: $order->customer_name ?: 'Client',
                    'email' => $order->client?->email,
                    'phone' => $order->client?->phone ?: $order->phone,
                    'estimated_value' => $amount,
                    'title' => 'Relance commerciale commande '.$order->order_number,
                    'need_summary' => 'Suivi de la commande '.$order->order_number.' — statut actuel : '.($order->status ?: 'non renseigné').'.',
                    'lead_type' => 'buyer',
                    'source' => 'client',
                    'status' => 'new',
                ],
            ];
        });

        return view('commercial.orders.index', compact('orders', 'stats'));
    }

    private function businessList(Request $request)
    {
        $query = BusinessRequest::with('user')->latest();
        $this->search($query, $request, ['title', 'description', 'sector', 'status']);
        $records = $query->paginate(25)->withQueryString();
        $records->getCollection()->transform(fn ($requestItem) => [
            'primary' => $requestItem->title,
            'secondary' => $requestItem->user?->email,
            'status' => $requestItem->status,
            'detail' => trim(($requestItem->sector ?: 'Secteur non renseigné').' · '.number_format((float) $requestItem->budget, 0, ',', ' ').' FCFA'),
            'created_at' => $requestItem->created_at,
            'lead_query' => [
                'business_request_id' => $requestItem->id,
                'user_id' => $requestItem->user_id,
                'contact_name' => $requestItem->user?->name ?: 'Client Business',
                'email' => $requestItem->user?->email,
                'phone' => $requestItem->user?->phone,
                'sector' => $requestItem->sector,
                'estimated_value' => $requestItem->budget,
                'title' => $requestItem->title,
                'need_summary' => $requestItem->description,
                'lead_type' => 'business',
                'source' => 'business_request',
                'status' => 'new',
            ],
        ]);

        return view('commercial.records.index', [
            'records' => $records,
            'title' => 'Demandes OVANIE Pro',
            'type' => 'business',
        ]);
    }

    private function quotesList(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $devis = Devis::query()
            ->when($q, fn (Builder $query) => $query->where('email', 'like', "%{$q}%")->orWhere('projet', 'like', "%{$q}%"))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($quote) => [
                'primary' => $quote->projet ?: 'Demande de devis',
                'secondary' => $quote->email,
                'status' => 'devis',
                'detail' => number_format((float) $quote->budget, 0, ',', ' ').' FCFA · '.$quote->ville,
                'created_at' => $quote->created_at,
                'lead_query' => [
                    'devis_id' => $quote->id,
                    'contact_name' => trim($quote->prenom.' '.$quote->nom),
                    'email' => $quote->email,
                    'phone' => $quote->telephone,
                    'city' => $quote->ville,
                    'sector' => $quote->secteur,
                    'estimated_value' => $quote->budget,
                    'title' => $quote->projet ?: 'Demande de devis',
                    'need_summary' => $quote->message,
                    'lead_type' => 'business',
                    'source' => 'devis',
                    'status' => 'new',
                ],
            ]);

        $calls = AppelOffre::query()
            ->when($q, fn (Builder $query) => $query->where('email', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($call) => [
                'primary' => 'Appel d’offre #'.$call->id,
                'secondary' => $call->email,
                'status' => 'appel_offre',
                'detail' => number_format((float) $call->budget, 0, ',', ' ').' FCFA · '.$call->ville,
                'created_at' => $call->created_at,
                'lead_query' => [
                    'appel_offre_id' => $call->id,
                    'contact_name' => trim($call->prenom.' '.$call->nom),
                    'email' => $call->email,
                    'phone' => $call->telephone,
                    'city' => $call->ville,
                    'sector' => $call->secteur,
                    'estimated_value' => $call->budget,
                    'title' => 'Appel d’offre #'.$call->id,
                    'need_summary' => $call->description,
                    'lead_type' => 'business',
                    'source' => 'appel_offre',
                    'status' => 'new',
                ],
            ]);

        $items = $devis->concat($calls)->sortByDesc('created_at')->values();
        $page = (int) $request->query('page', 1);
        $records = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage($page, 25)->values(),
            $items->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('commercial.records.index', [
            'records' => $records,
            'title' => 'Devis et appels d’offres',
            'type' => 'quote',
        ]);
    }

    private function scopedOrdersQuery(int $commercialId): Builder
    {
        return Order::query()
            ->operational()
            ->where(function (Builder $scope) use ($commercialId) {
                $scope
                    ->whereHas('client', fn (Builder $client) => $client->where('created_by_commercial_id', $commercialId))
                    ->orWhereHas('shop', function (Builder $shop) use ($commercialId) {
                        $shop->where('created_by_commercial_id', $commercialId)
                            ->orWhere('managed_by_commercial_id', $commercialId);
                    })
                    ->orWhereHas('items.shop', function (Builder $shop) use ($commercialId) {
                        $shop->where('created_by_commercial_id', $commercialId)
                            ->orWhere('managed_by_commercial_id', $commercialId);
                    });
            });
    }

    private function managedShopsQuery(int $commercialId): Builder
    {
        return Shop::query()->where(function (Builder $owner) use ($commercialId) {
            $owner->where('created_by_commercial_id', $commercialId)
                ->orWhere('managed_by_commercial_id', $commercialId);
        });
    }

    private function shopBelongsToCommercial(?Shop $shop, int $commercialId): bool
    {
        return $shop !== null
            && (
                (int) $shop->created_by_commercial_id === $commercialId
                || (int) $shop->managed_by_commercial_id === $commercialId
            );
    }

    private function search(Builder $query, Request $request, array $columns): void
    {
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function (Builder $search) use ($q, $columns) {
                foreach ($columns as $column) {
                    $search->orWhere($column, 'like', "%{$q}%");
                }
            });
        }
    }
}
