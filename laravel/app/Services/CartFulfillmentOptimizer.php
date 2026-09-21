<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartFulfillmentOptimization;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CartFulfillmentOptimizer
{
    public function __construct(
        private readonly DeliveryPricingEngine $pricingEngine,
        private readonly PublicProductVisibilityService $visibility
    ) {}

    /**
     * Applique réellement le meilleur scénario au panier.
     * Cette méthode est réservée à la validation finale du checkout.
     */
    public function optimizeForAddress(Cart $cart, array $deliveryAddress): array
    {
        $this->markOriginals($cart);
        $cart->loadMissing('items.product.shop');

        $plan = $this->buildPlan($cart, $deliveryAddress);

        if (! $plan['optimized']) {
            $this->restoreOriginalProducts($cart);
            return $this->result(
                false,
                $plan['original_delivery_fee'],
                $plan['optimized_delivery_fee'],
                [],
                $plan['reason']
            );
        }

        return $this->applyOptimization(
            $cart,
            $plan['items'],
            $plan['scenario'],
            $plan['original_delivery_fee']
        );
    }

    /**
     * Simule l'optimisation sans aucun UPDATE/INSERT.
     * Utilisé par /checkout/delivery-fee-preview.
     */
    public function previewForAddress(Cart $cart, array $deliveryAddress): array
    {
        $plan = $this->buildPlan($cart, $deliveryAddress);
        $previewCart = clone $cart;
        $previewItems = $plan['items']->map(function (CartItem $item) use ($plan) {
            $clone = clone $item;
            $product = $plan['scenario']['items']->get($item->id, $this->baseProductForItem($item));
            $clone->product_id = $product->id;
            $clone->fulfillment_product_id = $product->id;
            $clone->fulfillment_shop_id = $product->shop_id;
            $clone->setRelation('product', $product);
            return $clone;
        })->values();

        $previewCart->setRelation('items', $previewItems);

        return [
            'cart' => $previewCart,
            'result' => $this->result(
                $plan['optimized'],
                $plan['original_delivery_fee'],
                $plan['optimized_delivery_fee'],
                $plan['changed'],
                $plan['reason']
            ),
        ];
    }

    public function findEquivalentProducts(CartItem $item, array $deliveryAddress): Collection
    {
        return $this->findEquivalentProductsFor(
            $this->baseProductForItem($item),
            (int) $item->quantity,
            $deliveryAddress
        );
    }

    public function isStrictlyEquivalent(Product $original, Product $candidate): bool
    {
        if (! $original->master_product_id || $original->master_product_id !== $candidate->master_product_id) {
            return false;
        }

        if ((float) $original->final_price !== (float) $candidate->final_price) {
            return false;
        }

        foreach (['brand', 'category_id', 'unit', 'packaging', 'weight_kg', 'volume_m3', 'length_cm', 'width_cm', 'height_cm', 'color', 'material_grade', 'standard', 'warranty', 'return_policy'] as $field) {
            if ((string) ($original->{$field} ?? '') !== (string) ($candidate->{$field} ?? '')) {
                return false;
            }
        }

        return true;
    }

    protected function simulateScenario(Collection $items, Request $request): array
    {
        return $this->simulateScenarioProducts(
            $items,
            $items->mapWithKeys(fn (CartItem $item) => [$item->id => $this->baseProductForItem($item)]),
            $request
        );
    }

    protected function simulateScenarioProducts(Collection $items, Collection|array $productsByItem, Request $request): array
    {
        $products = $productsByItem instanceof Collection ? $productsByItem : collect($productsByItem);

        $lines = $items->map(function (CartItem $item) use ($products) {
            $product = $products->get($item->id, $this->baseProductForItem($item));
            $currentCartPrice = (float) ($item->price ?? 0);
            $priceToUse = $currentCartPrice > 0 ? $currentCartPrice : (float) ($product->final_price ?? 0);

            return (object) [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price' => $priceToUse,
                'product' => $product,
            ];
        });

        $quotedGroups = collect();

        foreach ($lines->groupBy(fn ($line) => (int) $line->product->shop_id) as $shopId => $group) {
            $quote = $this->pricingEngine->quote($group, $request);
            if (empty($quote['available']) || ! empty($quote['quote_required'])) {
                return ['delivery_fee' => INF];
            }

            $shop = $group->first()?->product?->shop;
            $quotedGroups->push([
                'shop_id' => (int) $shopId,
                'shop' => $shop,
                'shop_name' => $shop?->name ?? 'Point de collecte',
                'items' => $group->values(),
                'subtotal' => (float) $group->sum(fn ($line) => (float) $line->price * (int) $line->quantity),
                'selected_carrier' => $quote,
            ]);
        }

        $ovanieGroups = $quotedGroups
            ->filter(fn (array $group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE)
            ->values();

        $nonOvanieFee = (float) $quotedGroups
            ->reject(fn (array $group) => ($group['selected_carrier']['provider_type'] ?? null) === OrderWorkflowService::PROVIDER_OVANIE)
            ->sum(fn (array $group) => (float) ($group['selected_carrier']['price'] ?? 0));

        if ($ovanieGroups->count() >= 2) {
            $consolidated = $this->pricingEngine->quoteConsolidated($ovanieGroups, $request);
            if ($consolidated && ! empty($consolidated['available']) && empty($consolidated['quote_required'])) {
                return ['delivery_fee' => round($nonOvanieFee + (float) $consolidated['price'], 0)];
            }
        }

        $ovanieFee = (float) $ovanieGroups->sum(fn (array $group) => (float) ($group['selected_carrier']['price'] ?? 0));

        return ['delivery_fee' => round($nonOvanieFee + $ovanieFee, 0)];
    }

    protected function applyOptimization(Cart $cart, Collection $items, array $bestScenario, float $originalFee): array
    {
        $changed = [];
        $optimizedFee = (float) $bestScenario['delivery_fee'];
        $totalSavings = max(0, $originalFee - $optimizedFee);
        $scenarioProducts = $bestScenario['items'] instanceof Collection
            ? $bestScenario['items']
            : collect($bestScenario['items']);

        $changedCount = $items->filter(function (CartItem $item) use ($scenarioProducts) {
            $original = $this->baseProductForItem($item);
            $candidate = $scenarioProducts->get($item->id, $original);
            return (int) $candidate->id !== (int) $original->id;
        })->count();

        $lineSavings = $changedCount > 0 ? round($totalSavings / $changedCount, 2) : 0.0;

        foreach ($items as $item) {
            $original = $this->baseProductForItem($item);
            $fulfillment = $scenarioProducts->get($item->id, $original);
            $changedLine = (int) $fulfillment->id !== (int) $original->id;
            $currentCartPrice = (float) ($item->price ?? 0);
            $priceToUse = $currentCartPrice > 0 ? $currentCartPrice : (float) ($fulfillment->final_price ?? 0);

            $item->forceFill([
                'original_product_id' => $original->id,
                'fulfillment_product_id' => $fulfillment->id,
                'original_shop_id' => $original->shop_id,
                'fulfillment_shop_id' => $fulfillment->shop_id,
                'product_id' => $fulfillment->id,
                'price' => $priceToUse,
                'optimization_applied' => $changedLine,
                'optimization_savings' => $changedLine ? $lineSavings : 0,
                'optimization_meta' => [
                    'scenario' => $bestScenario['name'],
                    'reason' => $changedLine ? 'Point de vente plus proche du lieu de livraison' : 'Produit original conservé',
                    'original_delivery_fee' => $originalFee,
                    'optimized_delivery_fee' => $optimizedFee,
                ],
            ])->save();

            $item->setRelation('product', $fulfillment);

            if (! $changedLine) {
                continue;
            }

            $changed[] = [
                'cart_item_id' => $item->id,
                'original_product_id' => $original->id,
                'fulfillment_product_id' => $fulfillment->id,
                'original_shop_id' => $original->shop_id,
                'fulfillment_shop_id' => $fulfillment->shop_id,
                'reason' => 'Point de vente plus proche du lieu de livraison',
            ];

            CartFulfillmentOptimization::create([
                'cart_id' => $cart->id,
                'cart_item_id' => $item->id,
                'original_product_id' => $original->id,
                'fulfillment_product_id' => $fulfillment->id,
                'original_shop_id' => $original->shop_id,
                'fulfillment_shop_id' => $fulfillment->shop_id,
                'original_delivery_fee' => $originalFee,
                'optimized_delivery_fee' => $optimizedFee,
                'savings' => $lineSavings,
                'reason' => 'Point de vente plus proche du lieu de livraison',
                'meta' => ['scenario' => $bestScenario['name']],
            ]);
        }

        return $this->result(! empty($changed), $originalFee, $optimizedFee, $changed, $bestScenario['name']);
    }

    private function buildPlan(Cart $cart, array $deliveryAddress): array
    {
        $cart->loadMissing('items.product.shop');
        $items = $cart->items->filter(fn ($item) => $item->product)->values();

        if ($items->isEmpty()) {
            return [
                'optimized' => false,
                'items' => $items,
                'scenario' => ['name' => 'empty', 'items' => collect(), 'delivery_fee' => 0.0],
                'original_delivery_fee' => 0.0,
                'optimized_delivery_fee' => 0.0,
                'changed' => [],
                'reason' => 'Panier vide.',
            ];
        }

        $request = Request::create('/checkout/optimization', 'POST', $deliveryAddress);
        $originalScenario = $items->mapWithKeys(fn (CartItem $item) => [
            $item->id => $this->baseProductForItem($item),
        ]);

        $originalPricing = $this->simulateScenarioProducts($items, $originalScenario, $request);
        $originalFee = (float) ($originalPricing['delivery_fee'] ?? INF);
        $currentScenario = collect($originalScenario->all());
        $currentFee = $originalFee;
        $improved = true;
        $pass = 0;
        $maxPasses = max(1, min(5, $items->count()));

        while ($improved && $pass < $maxPasses) {
            $improved = false;
            $pass++;

            foreach ($items as $item) {
                $baseProduct = $this->baseProductForItem($item);
                $currentProduct = $currentScenario->get($item->id, $baseProduct);
                $candidatePool = collect([$currentProduct])
                    ->merge($this->findEquivalentProductsFor($baseProduct, (int) $item->quantity, $deliveryAddress))
                    ->unique(fn (Product $product) => (int) $product->id)
                    ->values();

                $bestProduct = $currentProduct;
                $bestFee = $currentFee;

                foreach ($candidatePool as $candidate) {
                    if ((int) $candidate->id === (int) $currentProduct->id) {
                        continue;
                    }

                    $trialScenario = collect($currentScenario->all())->put($item->id, $candidate);
                    $pricing = $this->simulateScenarioProducts($items, $trialScenario, $request);
                    $trialFee = (float) ($pricing['delivery_fee'] ?? INF);

                    if (is_finite($trialFee) && (! is_finite($bestFee) || $trialFee < $bestFee)) {
                        $bestFee = $trialFee;
                        $bestProduct = $candidate;
                    }
                }

                if ((int) $bestProduct->id !== (int) $currentProduct->id) {
                    $currentScenario = $currentScenario->put($item->id, $bestProduct);
                    $currentFee = $bestFee;
                    $improved = true;
                }
            }
        }

        $hasFiniteImprovement = is_finite($currentFee)
            && (! is_finite($originalFee) || $currentFee < $originalFee);

        $auditOriginalFee = is_finite($originalFee) ? $originalFee : (is_finite($currentFee) ? $currentFee : 0.0);
        $auditCurrentFee = is_finite($currentFee) ? $currentFee : $auditOriginalFee;
        $scenario = [
            'name' => $hasFiniteImprovement ? 'automatic_strict_fulfillment' : 'original_fulfillment',
            'items' => $hasFiniteImprovement ? $currentScenario : $originalScenario,
            'delivery_fee' => $hasFiniteImprovement ? $auditCurrentFee : $auditOriginalFee,
        ];

        $changed = $items->map(function (CartItem $item) use ($scenario) {
            $original = $this->baseProductForItem($item);
            $candidate = $scenario['items']->get($item->id, $original);

            if ((int) $candidate->id === (int) $original->id) {
                return null;
            }

            return [
                'cart_item_id' => $item->id,
                'original_product_id' => $original->id,
                'fulfillment_product_id' => $candidate->id,
                'original_shop_id' => $original->shop_id,
                'fulfillment_shop_id' => $candidate->shop_id,
            ];
        })->filter()->values()->all();

        return [
            'optimized' => $hasFiniteImprovement,
            'items' => $items,
            'scenario' => $scenario,
            'original_delivery_fee' => $auditOriginalFee,
            'optimized_delivery_fee' => $scenario['delivery_fee'],
            'changed' => $changed,
            'reason' => $hasFiniteImprovement
                ? 'automatic_strict_fulfillment'
                : 'Aucune optimisation stricte ne réduit le coût de livraison.',
        ];
    }

    private function findEquivalentProductsFor(Product $original, int $quantity, array $deliveryAddress): Collection
    {
        if (! $original->master_product_id) {
            Log::info('Fulfillment optimization skipped: no master product', ['product_id' => $original->id]);
            return collect();
        }

        return $this->visibility
            ->query(['shop.sellerDeliveryProfile', 'shop.sellerDeliveryZones'])
            ->where('master_product_id', $original->master_product_id)
            ->where('id', '!=', $original->id)
            ->where('is_fulfillment_enabled', true)
            ->where('stock', '>=', $quantity)
            ->get()
            ->filter(fn (Product $candidate) => $this->isStrictlyEquivalent($original, $candidate))
            ->filter(fn (Product $candidate) => $this->deliveryAvailable($candidate, $quantity, $deliveryAddress))
            ->values();
    }

    private function deliveryAvailable(Product $product, int $quantity, array $deliveryAddress): bool
    {
        $quote = $this->pricingEngine->quote(collect([(object) [
            'quantity' => $quantity,
            'price' => $product->final_price,
            'product' => $product,
        ]]), Request::create('/checkout/optimization', 'POST', $deliveryAddress));

        return ! empty($quote['available']) && empty($quote['quote_required']);
    }

    private function baseProductForItem(CartItem $item): Product
    {
        if ($item->original_product_id) {
            $original = Product::with('shop.sellerDeliveryProfile', 'shop.sellerDeliveryZones')->find($item->original_product_id);
            if ($original) {
                return $original;
            }
        }

        return $item->product;
    }

    private function markOriginals(Cart $cart): void
    {
        $cart->loadMissing('items.product');

        foreach ($cart->items as $item) {
            if ($item->original_product_id) {
                continue;
            }

            $item->forceFill([
                'original_product_id' => $item->product_id,
                'fulfillment_product_id' => $item->product_id,
                'original_shop_id' => $item->product?->shop_id,
                'fulfillment_shop_id' => $item->product?->shop_id,
                'optimization_applied' => false,
                'optimization_savings' => 0,
            ])->save();
        }
    }

    private function restoreOriginalProducts(Cart $cart): void
    {
        $cart->loadMissing('items');

        foreach ($cart->items as $item) {
            if (! $item->original_product_id) {
                continue;
            }

            $original = Product::find($item->original_product_id);
            if (! $original) {
                continue;
            }

            $item->forceFill([
                'product_id' => $original->id,
                'fulfillment_product_id' => $original->id,
                'fulfillment_shop_id' => $original->shop_id,
                'optimization_applied' => false,
                'optimization_savings' => 0,
                'optimization_meta' => null,
            ])->save();
            $item->setRelation('product', $original);
        }
    }

    private function result(bool $optimized, float $originalFee, float $optimizedFee, array $changed, string $reason): array
    {
        return [
            'optimized' => $optimized,
            'original_delivery_fee' => $originalFee,
            'optimized_delivery_fee' => $optimizedFee,
            'savings' => max(0, $originalFee - $optimizedFee),
            'items_changed' => $changed,
            'reason' => $reason,
        ];
    }
}
