<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryService;
use App\Models\DeliveryServiceRate;
use App\Models\DeliveryDistanceMatrix;
use App\Models\LogisticsPricingMatrix;
use App\Models\MasterProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerDeliveryProfile;
use App\Models\SellerDeliveryZone;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutSummaryService;
use App\Services\CartFulfillmentOptimizer;
use App\Services\DeliveryPricingEngine;
use App\Services\OrderWorkflowService;
use App\Services\SellerLogisticsValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeliveryMarketplaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_logistics_without_zone_keeps_shop_inactive(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Seller Logistics',
            'slug' => 'seller-logistics',
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'district' => 'Angre',
            'address' => 'Interne OVANIE',
            'identity_type' => 'cni',
            'identity_number' => 'CI-SELLER-001',
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'seller',
        ]);

        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);

        app(SellerLogisticsValidator::class)->synchronizeShopStatus($shop);

        $this->assertSame(Shop::LOGISTICS_INCOMPLETE, $shop->refresh()->logistics_status);
        $this->assertTrue((bool) $shop->is_active);
    }

    public function test_seller_zone_price_is_calculated_for_checkout(): void
    {
        [$client, $shop, $product] = $this->sellerProduct();

        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);

        SellerDeliveryZone::create([
            'shop_id' => $shop->id,
            'commune' => 'Cocody',
            'delivery_price' => 2500,
            'estimated_delay' => '24h',
            'is_active' => true,
        ]);

        $cart = $this->cartFor($client, $product);
        $request = Request::create('/checkout', 'POST', ['delivery_zone' => 'abidjan', 'delivery_commune' => 'Cocody']);
        $summary = app(CheckoutSummaryService::class)->build($cart, $request);

        $this->assertFalse($summary['delivery_quote_required']);
        $this->assertSame(2500.0, (float) $summary['delivery_fee']);
    }

    public function test_checkout_blocks_unknown_delivery_price(): void
    {
        [$client, $shop, $product] = $this->sellerProduct();

        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);

        $cart = $this->cartFor($client, $product);
        $request = Request::create('/checkout', 'POST', ['delivery_zone' => 'abidjan', 'delivery_commune' => 'Yopougon']);
        $summary = app(CheckoutSummaryService::class)->build($cart, $request);

        $this->assertTrue($summary['delivery_quote_required']);
        $this->assertSame(0.0, (float) $summary['delivery_fee']);
    }

    public function test_ovanie_checkout_uses_commune_vehicle_matrix_and_is_not_quote_required(): void
    {
        [$client, $shop, $product] = $this->sellerProduct('ovanie');

        // 50 kg => Tricycle. Le checkout doit lire directement le prix
        // Cocody -> Cocody / Tricycle dans la matrice Tarification.
        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Cocody',
            'vehicle_prices' => ['tricycle' => 3200],
            'is_active' => true,
        ]);

        $cart = $this->cartFor($client, $product);
        $request = Request::create('/checkout', 'POST', [
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Cocody',
        ]);
        $summary = app(CheckoutSummaryService::class)->build($cart, $request);

        $this->assertFalse($summary['delivery_quote_required']);
        $this->assertSame(3200.0, (float) $summary['delivery_fee']);
        $this->assertSame(
            'logistics_pricing_matrix',
            data_get($summary, 'groups.0.selected_carrier.meta.calculation.rate_source')
        );
    }

    public function test_ovanie_checkout_blocks_when_commune_vehicle_tariff_is_missing(): void
    {
        [$client, $shop, $product] = $this->sellerProduct('ovanie');
        $cart = $this->cartFor($client, $product);
        $request = Request::create('/checkout', 'POST', [
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Abobo',
        ]);

        $summary = app(CheckoutSummaryService::class)->build($cart, $request);

        $this->assertTrue($summary['delivery_quote_required']);
        $this->assertSame(0.0, (float) $summary['delivery_fee']);
        $this->assertSame('commune_tariff_missing', data_get($summary, 'delivery_issues.0.issue_code'));
    }

    public function test_optimizer_replaces_same_master_product_with_cheaper_fulfillment(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $master = MasterProduct::create(['name' => 'Ciment CPJ', 'brand' => 'OV', 'sku' => 'CPJ-50', 'unit' => 'sac', 'is_active' => true]);
        $original = $this->fulfillmentProduct($master, 'opt-original', 'Yopougon', 5000, 10);
        $candidate = $this->fulfillmentProduct($master, 'opt-candidate', 'Cocody', 2000, 10);
        $cart = $this->cartFor($client, $original);

        $result = app(CartFulfillmentOptimizer::class)->optimizeForAddress($cart, [
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Cocody',
        ]);

        $this->assertTrue($result['optimized']);
        $this->assertSame($candidate->id, $cart->items()->first()->refresh()->product_id);
        $this->assertGreaterThan(0, $result['savings']);
    }

    public function test_optimizer_does_not_replace_without_same_master_product(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $masterA = MasterProduct::create(['name' => 'A', 'brand' => 'OV', 'sku' => 'A', 'unit' => 'sac', 'is_active' => true]);
        $masterB = MasterProduct::create(['name' => 'B', 'brand' => 'OV', 'sku' => 'A', 'unit' => 'sac', 'is_active' => true]);
        $original = $this->fulfillmentProduct($masterA, 'no-master-a', 'Yopougon', 5000, 10);
        $this->fulfillmentProduct($masterB, 'no-master-b', 'Cocody', 1000, 10);
        $cart = $this->cartFor($client, $original);

        $result = app(CartFulfillmentOptimizer::class)->optimizeForAddress($cart, ['delivery_zone' => 'abidjan', 'delivery_commune' => 'Cocody']);

        $this->assertFalse($result['optimized']);
        $this->assertSame($original->id, $cart->items()->first()->refresh()->product_id);
    }

    public function test_optimizer_does_not_replace_when_price_or_stock_differs(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $master = MasterProduct::create(['name' => 'Ciment', 'brand' => 'OV', 'sku' => 'STRICT', 'unit' => 'sac', 'is_active' => true]);
        $original = $this->fulfillmentProduct($master, 'strict-original', 'Yopougon', 5000, 10);
        $this->fulfillmentProduct($master, 'strict-price', 'Cocody', 1000, 10, 12000);
        $this->fulfillmentProduct($master, 'strict-stock', 'Plateau', 1000, 0);
        $cart = $this->cartFor($client, $original);

        $result = app(CartFulfillmentOptimizer::class)->optimizeForAddress($cart, ['delivery_zone' => 'abidjan', 'delivery_commune' => 'Cocody']);

        $this->assertFalse($result['optimized']);
        $this->assertSame($original->id, $cart->items()->first()->refresh()->product_id);
    }

    public function test_ovanie_distance_without_gps_or_matrix_blocks_quote(): void
    {
        [$client, $shop, $product] = $this->sellerProduct('ovanie');
        $shop->update(['commune' => 'Ville inconnue', 'latitude' => null, 'longitude' => null]);

        $service = DeliveryService::create([
            'provider_type' => 'ovanie',
            'code' => 'ovanie-distance-test',
            'name' => 'OVANIE Distance',
            'estimated_hours' => 24,
            'is_active' => true,
            'meta' => ['quote_required' => false],
        ]);

        DeliveryServiceRate::create(['delivery_service_id' => $service->id, 'base_fee' => 3000, 'price_per_km' => 100, 'is_active' => true]);

        $quote = app(DeliveryPricingEngine::class)->quote(collect([(object) ['product' => $product->refresh(), 'quantity' => 1]]), Request::create('/checkout', 'POST', [
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Autre inconnue',
        ]));

        $this->assertFalse($quote['available']);
    }

    public function test_ovanie_distance_uses_database_matrix_when_gps_is_missing(): void
    {
        [$client, $shop, $product] = $this->sellerProduct('ovanie');
        $shop->update(['commune' => 'Cocody', 'latitude' => null, 'longitude' => null]);

        DeliveryDistanceMatrix::create([
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Yopougon',
            'distance_km' => 23,
            'estimated_duration_minutes' => 55,
            'is_active' => true,
        ]);

        LogisticsPricingMatrix::query()->create([
            'region' => 'Abidjan',
            'origin_commune' => 'Cocody',
            'destination_commune' => 'Yopougon',
            'vehicle_prices' => ['tricycle' => 4500],
            'is_active' => true,
        ]);

        $service = DeliveryService::create([
            'provider_type' => 'ovanie',
            'code' => 'ovanie-db-distance-test',
            'name' => 'OVANIE Distance DB',
            'estimated_hours' => 24,
            'is_active' => true,
            'meta' => ['quote_required' => false],
        ]);

        DeliveryServiceRate::create(['delivery_service_id' => $service->id, 'base_fee' => 3000, 'price_per_km' => 100, 'is_active' => true]);

        $quote = app(DeliveryPricingEngine::class)->quote(collect([(object) ['product' => $product->refresh(), 'quantity' => 1]]), Request::create('/checkout', 'POST', [
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Yopougon',
        ]));

        $this->assertTrue($quote['available']);
        $this->assertSame(23.0, (float) $quote['meta']['distance_km']);
    }

    public function test_seller_zone_city_fallback_requires_explicit_coverage_type(): void
    {
        [$client, $shop, $product] = $this->sellerProduct();

        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);

        SellerDeliveryZone::create([
            'shop_id' => $shop->id,
            'city' => 'Abidjan',
            'commune' => 'Abidjan',
            'coverage_type' => 'commune',
            'delivery_price' => 5000,
            'estimated_delay' => '24h',
            'is_active' => true,
        ]);

        $request = Request::create('/checkout', 'POST', [
            'delivery_city' => 'Abidjan',
            'delivery_commune' => 'Yopougon',
        ]);
        $blocked = app(DeliveryPricingEngine::class)->quote(collect([(object) ['product' => $product, 'quantity' => 1]]), $request);

        $this->assertFalse($blocked['available']);

        SellerDeliveryZone::create([
            'shop_id' => $shop->id,
            'city' => 'Abidjan',
            'commune' => 'all',
            'coverage_type' => 'city',
            'delivery_price' => 7000,
            'estimated_delay' => '48h',
            'is_active' => true,
        ]);

        $allowed = app(DeliveryPricingEngine::class)->quote(collect([(object) ['product' => $product, 'quantity' => 1]]), $request);

        $this->assertTrue($allowed['available']);
        $this->assertSame(7000.0, (float) $allowed['price']);
    }

    public function test_mobile_order_ignores_client_delivery_fee_and_blocks_unknown_delivery(): void
    {
        [$client, $shop, $product] = $this->sellerProduct();
        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);
        $this->cartFor($client, $product);
        $this->actingAs($client);

        $response = $this->postJson('/api/mobile/orders', [
            'address' => 'Adresse client',
            'delivery_zone' => 'abidjan',
            'delivery_commune' => 'Yopougon',
            'delivery_fee' => 0,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_cannot_be_marked_delivered_without_strong_proof(): void
    {
        [$client, $shop, $product] = $this->sellerProduct('ovanie');
        $order = Order::create([
            'order_number' => 'OV-PROOF-001',
            'client_id' => $client->id,
            'vendor_id' => $shop->user_id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => 'paid',
            'payment_status' => 'paid',
            'subtotal' => 10000,
            'delivery_fee' => 3000,
            'total_amount' => 13000,
            'address' => 'Cocody',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
            'price' => 10000,
            'subtotal' => 10000,
            'delivery_provider' => OrderWorkflowService::PROVIDER_OVANIE,
            'delivery_status' => OrderWorkflowService::DELIVERY_IN_TRANSIT,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Veuillez ajouter une preuve de livraison valide');

        app(OrderWorkflowService::class)->createDeliveryProof($item, [
            'receiver_name' => 'Client',
            'receiver_phone' => '0101010101',
        ]);
    }

    private function sellerProduct(string $logisticsType = 'seller'): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Shop Test',
            'slug' => 'shop-test-' . $logisticsType,
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'district' => 'Angre',
            'address' => 'Interne OVANIE',
            'latitude' => 5.37,
            'longitude' => -3.98,
            'identity_type' => 'cni',
            'identity_number' => 'CI-' . strtoupper($logisticsType) . '-001',
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => $logisticsType,
        ]);

        $category = Category::create(['name' => 'Materiaux', 'slug' => 'materiaux-' . $logisticsType, 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Ciment',
            'slug' => 'ciment-' . $logisticsType,
            'price' => 10000,
            'weight_kg' => 50,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);

        return [$client, $shop, $product];
    }

    private function cartFor(User $client, Product $product): Cart
    {
        $cart = Cart::create(['user_id' => $client->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'original_product_id' => $product->id,
            'fulfillment_product_id' => $product->id,
            'original_shop_id' => $product->shop_id,
            'fulfillment_shop_id' => $product->shop_id,
            'quantity' => 1,
            'price' => $product->final_price,
        ]);

        return $cart->load('items.product.shop');
    }

    private function fulfillmentProduct(MasterProduct $master, string $slug, string $commune, float $deliveryPrice, int $stock, ?float $price = null): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Shop ' . $slug,
            'slug' => 'shop-' . $slug,
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => $commune,
            'district' => 'Centre',
            'address' => 'Interne OVANIE',
            'identity_type' => 'cni',
            'identity_number' => 'CI-' . strtoupper($slug),
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'seller',
        ]);

        SellerDeliveryProfile::create([
            'shop_id' => $shop->id,
            'is_enabled' => true,
            'default_delay' => '24h',
            'max_weight_kg' => 1000,
            'max_volume_m3' => 4,
            'vehicle_types' => ['pickup'],
            'capacity_description' => 'Pickup chantier',
            'conditions' => 'Livraison sur Abidjan',
            'status' => 'pending_review',
        ]);

        SellerDeliveryZone::create([
            'shop_id' => $shop->id,
            'commune' => 'Cocody',
            'delivery_price' => $deliveryPrice,
            'estimated_delay' => '24h',
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(['slug' => 'fulfillment'], ['name' => 'Fulfillment', 'status' => 'actif']);

        return Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'master_product_id' => $master->id,
            'name' => 'Ciment strict',
            'slug' => $slug,
            'sku' => 'CPJ-50',
            'brand' => 'OV',
            'unit' => 'sac',
            'packaging' => 'sac 50kg',
            'price' => $price ?? 10000,
            'weight_kg' => 50,
            'stock' => $stock,
            'status' => 'approved',
            'is_active' => true,
            'is_fulfillment_enabled' => true,
        ]);
    }
}
