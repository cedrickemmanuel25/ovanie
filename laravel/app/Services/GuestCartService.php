<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Panier visiteur stocké en session.
 *
 * Un visiteur peut consulter le catalogue et préparer son panier sans compte.
 * Le panier n'est transformé en panier base de données qu'après connexion ou
 * inscription, juste avant le checkout.
 */
class GuestCartService
{
    public const SESSION_KEY = 'ovanie_guest_cart';
    public const CHECKOUT_PRODUCT_IDS_KEY = 'checkout_guest_product_ids';
    public const CHECKOUT_SELECTION_REQUIRED_KEY = 'checkout_guest_selection_required';

    public function __construct(private PublicProductVisibilityService $visibility)
    {
    }

    public function raw(Request $request): array
    {
        $raw = $request->session()->get(self::SESSION_KEY, []);

        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $productId => $entry) {
            $id = (int) $productId;
            if ($id <= 0) {
                continue;
            }

            $quantity = is_array($entry)
                ? (int) ($entry['quantity'] ?? 0)
                : (int) $entry;

            if ($quantity > 0) {
                $normalized[$id] = ['quantity' => $quantity];
            }
        }

        return $normalized;
    }

    public function quantityFor(Request $request, int $productId): int
    {
        return (int) ($this->raw($request)[$productId]['quantity'] ?? 0);
    }

    public function add(Request $request, Product $product, int $quantity): int
    {
        $raw = $this->raw($request);
        $productId = (int) $product->getKey();
        $stock = max(0, (int) ($product->stock ?? 0));
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $next = max($minimum, (int) ($raw[$productId]['quantity'] ?? 0) + max(1, $quantity));

        if ($stock > 0) {
            $next = min($next, $stock);
        }

        $raw[$productId] = ['quantity' => $next];
        $request->session()->put(self::SESSION_KEY, $raw);

        return $next;
    }

    public function update(Request $request, int $productId, int $quantity): void
    {
        $raw = $this->raw($request);

        if (! isset($raw[$productId])) {
            return;
        }

        $raw[$productId] = ['quantity' => max(1, $quantity)];
        $request->session()->put(self::SESSION_KEY, $raw);
    }

    public function remove(Request $request, int $productId): void
    {
        $raw = $this->raw($request);
        unset($raw[$productId]);
        $request->session()->put(self::SESSION_KEY, $raw);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_KEY,
            self::CHECKOUT_PRODUCT_IDS_KEY,
            self::CHECKOUT_SELECTION_REQUIRED_KEY,
        ]);
    }

    public function count(Request $request): int
    {
        return (int) collect($this->raw($request))->sum(
            fn (array $entry) => (int) ($entry['quantity'] ?? 0)
        );
    }

    /**
     * Construit un panier compatible avec la vue panier sans créer de ligne DB.
     * Les produits indisponibles sont retirés et les quantités sont normalisées.
     *
     * @return array{cart: object, messages: array<int,string>}
     */
    public function snapshot(Request $request): array
    {
        $raw = $this->raw($request);
        $messages = [];

        if ($raw === []) {
            return ['cart' => $this->virtualCart(collect()), 'messages' => []];
        }

        $ids = array_keys($raw);
        $eligibleIds = $this->visibility->query([], false)
            ->whereIn('products.id', $ids)
            ->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $products = Product::query()
            ->with(['category', 'shop', 'images'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Product $product) => (int) $product->id);

        $normalized = [];
        $items = collect();

        foreach ($raw as $productId => $entry) {
            $productId = (int) $productId;
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (! $product || ! in_array($productId, $eligibleIds, true)) {
                $messages[] = 'Un produit devenu indisponible a été retiré du panier.';
                continue;
            }

            $stock = max(0, (int) ($product->stock ?? 0));
            $minimum = max(1, (int) ($product->min_order_quantity ?? 1));

            if ($stock < $minimum) {
                $messages[] = "Le produit {$product->name} ne dispose plus du stock minimum requis ({$minimum}) et a été retiré du panier.";
                continue;
            }

            $quantity = max($minimum, (int) ($entry['quantity'] ?? 1));
            if ($quantity > $stock) {
                $quantity = $stock;
                $messages[] = "La quantité de {$product->name} a été ajustée au stock disponible ({$stock}).";
            }

            $normalized[$productId] = ['quantity' => $quantity];

            $item = new stdClass();
            // Pour un panier visiteur, l'identifiant temporaire est le product_id.
            // CheckoutController sait le convertir en vrai cart_item_id après login.
            $item->id = $productId;
            $item->product_id = $productId;
            $item->product = $product;
            $item->quantity = $quantity;
            $item->price = (float) ($product->final_price ?? $product->price ?? 0);
            $item->price_source = 'catalog';
            $item->negotiation_id = null;
            $items->push($item);
        }

        $request->session()->put(self::SESSION_KEY, $normalized);

        return [
            'cart' => $this->virtualCart($items),
            'messages' => $messages,
        ];
    }

    /**
     * Fusionne le panier visiteur avec le panier du compte public connecté.
     * Les comptes vendeurs utilisent le même panier d'achat que les clients,
     * sans changer leur rôle vendeur.
     */
    public function mergeIntoUserCart(Request $request, User $user): Cart
    {
        $raw = $this->raw($request);
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        if ($raw !== []) {
            $ids = array_keys($raw);
            $eligibleIds = $this->visibility->query([], false)
                ->whereIn('products.id', $ids)
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $products = Product::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy(fn (Product $product) => (int) $product->id);

            foreach ($raw as $productId => $entry) {
                $productId = (int) $productId;
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || ! in_array($productId, $eligibleIds, true)) {
                    continue;
                }

                $stock = max(0, (int) ($product->stock ?? 0));
                $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
                if ($stock < $minimum) {
                    continue;
                }

                $guestQuantity = max($minimum, (int) ($entry['quantity'] ?? 1));
                $guestQuantity = min($guestQuantity, $stock);
                $item = $cart->items()->where('product_id', $productId)->first();

                // Ne jamais écraser un prix négocié existant par un panier visiteur.
                if ($item && ($item->price_source ?? 'catalog') === 'negotiated') {
                    continue;
                }

                $quantity = min($stock, (int) ($item?->quantity ?? 0) + $guestQuantity);
                $unitPrice = (float) ($product->final_price ?? $product->price ?? 0);

                if ($item) {
                    $item->forceFill([
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'price_source' => 'catalog',
                        'negotiation_id' => null,
                    ])->save();
                } else {
                    $cart->items()->create([
                        'product_id' => $productId,
                        'original_product_id' => $productId,
                        'fulfillment_product_id' => $productId,
                        'original_shop_id' => $product->shop_id,
                        'fulfillment_shop_id' => $product->shop_id,
                        'optimization_applied' => false,
                        'price' => $unitPrice,
                        'price_source' => 'catalog',
                        'negotiation_id' => null,
                        'quantity' => $quantity,
                    ]);
                }
            }
        }

        $selectedProductIds = collect($request->session()->pull(self::CHECKOUT_PRODUCT_IDS_KEY, []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedProductIds->isNotEmpty()) {
            $selectedCartItemIds = $cart->items()
                ->whereIn('product_id', $selectedProductIds->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $request->session()->put('checkout_cart_item_ids', $selectedCartItemIds);
            $request->session()->put(self::CHECKOUT_SELECTION_REQUIRED_KEY, true);
        }

        $request->session()->forget(self::SESSION_KEY);

        return $cart->fresh('items.product');
    }

    private function virtualCart(Collection $items): object
    {
        $cart = new stdClass();
        $cart->items = $items;
        $cart->delivery_fee = 0;
        $cart->grand_total = 0;

        return $cart;
    }
}
