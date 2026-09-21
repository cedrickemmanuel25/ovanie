<?php

namespace Tests\Feature;

use App\Models\{AbidjanCommune, Category, Order, OrderItem, Product, Shop, User};
use App\Services\{DeliveryPricingEngine, OrderWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ShopLogisticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/mobile/v1/vendor/shop/logistics-settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(\App\Services\Geo\GeocodingService::class)->shouldReceive('reverse')->byDefault()
            ->andReturnUsing(fn ($lat, $lng, $useCache) => [
                'latitude' => $lat, 'longitude' => $lng, 'source' => 'test', 'address_quality' => 'detailed',
                'display_name' => 'Rue des artisans, Angré, Cocody, Abidjan',
                'address' => ['road' => 'Rue des artisans', 'city' => 'Abidjan'],
                'resolved_location' => ['commune' => 'Cocody', 'quartier' => 'Angré'],
            ]);
    }

    private function shop(string $mode = 'ovanie'): Shop
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $user->id, 'name' => 'Boutique '.uniqid(), 'slug' => uniqid('shop-'),
            'seller_type' => 'particulier', 'city' => 'Abidjan', 'commune' => 'Cocody',
            'district' => 'Angré', 'landmark' => 'Face à la pharmacie', 'address' => 'Rue des artisans',
            'latitude' => 5.37, 'longitude' => -3.98, 'geo_status' => 'reliable',
            'identity_type' => 'cni', 'identity_number' => uniqid(),
            'status' => 'approved', 'kyc_status' => 'verified', 'is_active' => true,
            'logistics_type' => $mode, 'logistics_status' => 'ready',
        ]);
        $this->actingAs($user, 'sanctum');
        return $shop;
    }

    private function profileData(): array
    {
        return ['default_delay' => '24h', 'max_weight_kg' => 1000,
            'max_volume_m3' => 4, 'conditions' => 'Livraison au portail', 'vehicle_types' => ['pickup']];
    }

    private function configure(Shop $shop): void
    {
        $profile = $shop->sellerDeliveryProfile()->create($this->profileData() + ['is_enabled' => $shop->usesSellerLogistics()]);
        $shop->sellerDeliveryZones()->create([
            'seller_delivery_profile_id' => $profile->id, 'commune' => 'Cocody', 'city' => 'Abidjan',
            'delivery_price' => 2500, 'estimated_delay' => '24h', 'is_active' => true,
        ]);
    }

    private function switchData(string $target): array
    {
        $data = ['logistics_type' => $target, 'expected_logistics_type' => $target === 'seller' ? 'ovanie' : 'seller',
            'confirmed' => true, 'address' => 'Rue des artisans', 'commune' => 'Cocody', 'district' => 'Angré',
            'landmark' => 'Face à la pharmacie', 'latitude' => 5.37, 'longitude' => -3.98, 'location_confirmed' => true,
            'geo_source' => 'browser_gps', 'geo_accuracy' => 12, 'geo_captured_at' => now()->toIso8601String()];
        if ($target === 'ovanie') {
            $data['location_token'] = app(\App\Services\ShopPickupLocationService::class)->resolve(
                auth()->user()->shop()->firstOrFail(), $data)['location_token'];
        }
        return $data;
    }

    private function item(Shop $shop, string $mode, string $status = 'in_transit'): OrderItem
    {
        $category = Category::create(['name' => 'Matériaux', 'slug' => uniqid('cat-'), 'status' => 'actif']);
        $product = Product::create(['shop_id' => $shop->id, 'vendor_id' => $shop->user_id, 'category_id' => $category->id,
            'name' => 'Ciment', 'slug' => uniqid('product-'), 'price' => 10000, 'stock' => 10,
            'weight_kg' => 50, 'status' => 'approved', 'is_active' => true]);
        $order = Order::create(['order_number' => uniqid('CMD-'), 'client_id' => $shop->user_id,
            'vendor_id' => $shop->user_id, 'shop_id' => $shop->id, 'product_id' => $product->id,
            'status' => 'paid', 'payment_status' => 'paid', 'subtotal' => 10000,
            'delivery_fee' => 2500, 'total_amount' => 12500]);
        return OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 10000, 'subtotal' => 10000, 'delivery_price' => 2500,
            'delivery_provider' => $mode, 'delivery_mode' => $mode, 'delivery_status' => $status])->fresh();
    }

    public function test_switch_to_ovanie_retains_configuration_and_every_existing_order_snapshot(): void
    {
        $shop = $this->shop('seller');
        $this->configure($shop);
        $items = collect(['pending', 'in_transit', 'delivered'])->map(fn ($status) => $this->item($shop, 'seller', $status));
        $snapshots = $items->map(fn ($item) => $item->getRawOriginal());
        $zone = $shop->sellerDeliveryZones()->first();
        $before = $zone->getRawOriginal();
        $this->postJson(self::ENDPOINT, $this->switchData('ovanie'))->assertOk()->assertJsonPath('shop.logistics_type', 'ovanie');
        $this->assertFalse($shop->sellerDeliveryProfile()->first()->is_enabled);
        $this->assertSame($before, $zone->fresh()->getRawOriginal());
        foreach ($items as $index => $item) $this->assertSame($snapshots[$index], $item->fresh()->getRawOriginal());
        $this->assertDatabaseCount('shops', 1);
        $this->getJson('/api/mobile/v1/vendor/shop/delivery')->assertOk()->assertJsonCount(0, 'zones')->assertJsonPath('profile', null);
        $this->getJson('/api/mobile/v1/vendor/shop/delivery?configure=1')->assertOk()->assertJsonCount(1, 'zones');
        // The next checkout chooses OVANIE even for a product sold before the switch.
        $product = $items->first()->product->fresh('shop');
        $quote = app(DeliveryPricingEngine::class)->quote(collect([(object) ['product' => $product, 'quantity' => 1]]),
            Request::create('/checkout', 'POST', ['delivery_commune' => 'Cocody', 'delivery_zone' => 'abidjan']));
        $this->assertSame(OrderWorkflowService::PROVIDER_OVANIE, $quote['provider_type']);
    }

    public function test_each_location_field_and_explicit_confirmation_are_required(): void
    {
        $shop = $this->shop('seller');
        $this->configure($shop);
        foreach (['address', 'commune', 'district', 'landmark', 'latitude', 'longitude', 'location_confirmed', 'confirmed'] as $field) {
            $data = $this->switchData('ovanie'); unset($data[$field]);
            $this->postJson(self::ENDPOINT, $data)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertSame('seller', $shop->fresh()->logistics_type);
            $this->assertTrue($shop->sellerDeliveryProfile()->first()->is_enabled);
        }
        $this->postJson(self::ENDPOINT, array_replace($this->switchData('ovanie'), ['latitude' => 91]))->assertUnprocessable();
    }

    public function test_seller_draft_can_be_saved_without_activation_but_requires_complete_zones(): void
    {
        $shop = $this->shop();
        $this->postJson(self::ENDPOINT, $this->switchData('seller'))->assertUnprocessable();
        $this->postJson('/api/mobile/v1/vendor/shop/delivery', $this->profileData())->assertOk();
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
        $this->assertFalse($shop->sellerDeliveryProfile()->first()->is_enabled);
        $this->postJson(self::ENDPOINT, $this->switchData('seller'))->assertUnprocessable();
        $this->assertFalse($shop->sellerDeliveryProfile()->first()->is_enabled);
        $commune = AbidjanCommune::query()->firstOrCreate(['name' => 'Cocody'], ['slug' => 'cocody', 'is_active' => true]);
        $zone = ['commune_id' => $commune->id, 'vehicle_code' => 'pickup', 'delivery_price' => 0, 'estimated_delay' => '48h'];
        $this->postJson('/api/mobile/v1/vendor/shop/delivery/zones', array_replace($zone, ['estimated_delay' => '']))->assertUnprocessable();
        $this->postJson('/api/mobile/v1/vendor/shop/delivery/zones', $zone)->assertCreated();
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
        $item = $this->item($shop, 'ovanie'); $snapshot = $item->getRawOriginal();
        $this->postJson(self::ENDPOINT, $this->switchData('seller'))->assertOk()->assertJsonPath('shop.logistics_type', 'seller');
        $this->assertTrue($shop->sellerDeliveryProfile()->first()->is_enabled);
        $this->assertSame($snapshot, $item->fresh()->getRawOriginal());
    }

    public function test_round_trip_restores_previous_prices_without_changing_order_modes(): void
    {
        $shop = $this->shop('seller'); $this->configure($shop);
        $old = $this->item($shop, 'seller');
        $this->postJson(self::ENDPOINT, $this->switchData('ovanie'))->assertOk();
        $new = $this->item($shop->fresh(), 'ovanie');
        $this->postJson(self::ENDPOINT, $this->switchData('seller'))->assertOk();
        $this->assertSame('seller', $old->fresh()->delivery_provider);
        $this->assertSame('ovanie', $new->fresh()->delivery_provider);
        $this->assertSame(2500.0, (float) $shop->sellerDeliveryZones()->first()->delivery_price);
    }

    public function test_legacy_missing_mode_is_frozen_before_the_shop_changes(): void
    {
        $shop = $this->shop('seller'); $this->configure($shop);
        $item = $this->item($shop, '');
        $this->postJson(self::ENDPOINT, $this->switchData('ovanie'))->assertOk();
        $this->assertSame('seller', $item->fresh()->delivery_provider);
        $this->assertSame('seller', $item->fresh()->delivery_mode);
        $this->assertSame('in_transit', $item->fresh()->delivery_status);
    }

    public function test_stale_changes_and_preparation_endpoint_cannot_bypass_validation(): void
    {
        $shop = $this->shop();
        $this->postJson(self::ENDPOINT, $this->switchData('ovanie'))->assertUnprocessable();
        $this->postJson('/api/mobile/v1/vendor/shop/preparation-settings', [
            'logistics_type' => 'seller', 'processing_time' => '24_48h', 'days' => [1], 'start' => '08:00', 'end' => '18:00',
        ])->assertUnprocessable();
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
    }

    public function test_settings_use_authenticated_shop_and_web_exposes_both_actions(): void
    {
        $other = $this->shop('seller'); $this->configure($other);
        $shop = $this->shop(); $this->configure($shop);
        $this->postJson(self::ENDPOINT, $this->switchData('seller') + ['shop_id' => $other->id])->assertOk();
        $this->assertSame('seller', $shop->fresh()->logistics_type);
        $this->assertSame('seller', $other->fresh()->logistics_type);
        $this->actingAs($shop->user, 'web')->get(route('vendor.delivery.index'))->assertOk()
            ->assertSee('Changer de mode logistique')->assertSee('Modifier mes informations logistiques');
        $this->get(route('vendor.delivery.mode'))->assertOk()->assertSee('Point de repère')->assertSee('location_confirmed');
    }

    public function test_web_ovanie_hides_saved_rates_and_requires_confirmation_for_activation(): void
    {
        $shop = $this->shop(); $this->configure($shop);
        $this->actingAs($shop->user, 'web');
        $this->get(route('vendor.delivery.index'))->assertOk()->assertSee('OVANIE Logistics')->assertDontSee('2 500');
        $this->get(route('vendor.delivery.edit'))->assertOk()->assertSee('Préparer ma propre logistique');
        $data = $this->switchData('seller'); unset($data['confirmed']);
        $this->post(route('vendor.delivery.mode.update'), $data)->assertSessionHasErrors('confirmed');
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
        $this->post(route('vendor.delivery.mode.update'), $this->switchData('seller'))->assertRedirect(route('vendor.delivery.index'));
        $this->assertSame('seller', $shop->fresh()->logistics_type);
    }

    public function test_edit_location_updates_existing_shop_without_touching_orders(): void
    {
        $shop = $this->shop(); $item = $this->item($shop, 'ovanie');
        $snapshot = $item->getRawOriginal();
        $data = array_replace($this->switchData('ovanie'), [
            'expected_logistics_type' => 'ovanie', 'address' => 'Nouvelle adresse boutique',
            'confirmed' => false,
        ]);
        $this->postJson(self::ENDPOINT, $data)->assertOk();
        $this->assertSame('Nouvelle adresse boutique', $shop->fresh()->address);
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
        $this->assertSame($snapshot, $item->fresh()->getRawOriginal());
        $this->assertDatabaseCount('shops', 1);
    }

    public function test_incomplete_saved_active_zone_blocks_activation_even_when_another_zone_is_complete(): void
    {
        $shop = $this->shop(); $this->configure($shop);
        $shop->sellerDeliveryZones()->create(['commune' => 'Abobo', 'delivery_price' => 1500, 'estimated_delay' => '', 'is_active' => true]);
        $this->postJson(self::ENDPOINT, $this->switchData('seller'))->assertUnprocessable()->assertJsonValidationErrors('logistics_type');
        $this->assertSame('ovanie', $shop->fresh()->logistics_type);
        $this->assertFalse($shop->sellerDeliveryProfile()->first()->is_enabled);
    }

    public function test_inaccurate_or_stale_gps_is_rejected_without_overwriting_stored_location(): void
    {
        $shop = $this->shop('seller');
        $before = $shop->fresh()->getRawOriginal();
        foreach ([
            ['geo_accuracy' => 5000], ['geo_accuracy' => 0], ['geo_accuracy' => null],
            ['geo_captured_at' => now()->subHour()->toIso8601String()],
            ['geo_source' => 'manual_map'],
        ] as $invalid) {
            $this->postJson(self::ENDPOINT, array_replace($this->switchData('ovanie'), $invalid))->assertUnprocessable();
            $this->assertSame($before, $shop->fresh()->getRawOriginal());
        }
        $this->postJson(self::ENDPOINT, $this->switchData('ovanie'))->assertOk();
        $this->assertSame('browser_gps', $shop->fresh()->geo_source);
        $this->assertSame(12.0, $shop->fresh()->geo_accuracy);
    }

    public function test_existing_reliable_location_can_be_kept_but_cannot_be_reused_for_another_address(): void
    {
        $shop = $this->shop('ovanie');
        $shop->update(['geo_source' => 'browser_gps', 'geo_accuracy' => 10]);
        $data = $this->switchData('ovanie');
        $data['expected_logistics_type'] = 'ovanie';
        unset($data['geo_source'], $data['geo_accuracy'], $data['geo_captured_at']);
        $this->postJson(self::ENDPOINT, array_replace($data, ['address' => 'Autre boutique']))->assertUnprocessable();
        $this->postJson(self::ENDPOINT, $data)->assertOk();
        $this->assertSame(10.0, $shop->fresh()->geo_accuracy);
    }

    public function test_logistics_form_keeps_coordinates_hidden(): void
    {
        $shop = $this->shop('seller');
        $this->actingAs($shop->user, 'web')->get(route('vendor.delivery.mode'))->assertOk()
            ->assertSee('type="hidden" id="latitude"', false)
            ->assertSee('type="hidden" id="longitude"', false)
            ->assertDontSee('Latitude GPS')->assertDontSee('Longitude GPS');
    }

    public function test_new_capture_cannot_be_saved_with_old_commune_or_another_coordinate(): void
    {
        $shop = $this->shop('seller');
        foreach ([['commune' => 'Yopougon'], ['district' => 'Attoban'], ['latitude' => 5.4], ['location_token' => '']] as $change) {
            $this->postJson(self::ENDPOINT, array_replace($this->switchData('ovanie'), $change))->assertUnprocessable();
            $this->assertSame('seller', $shop->fresh()->logistics_type);
        }
    }

    public function test_position_resolution_uses_fresh_coordinates_and_never_opening_address(): void
    {
        $shop = $this->shop('seller');
        $shop->update(['commune' => 'Yopougon', 'district' => 'Ancien quartier', 'address' => 'Ancienne adresse']);
        $this->mock(\App\Services\Geo\GeocodingService::class)->shouldReceive('reverse')->once()
            ->with(5.37, -3.98, false)->andReturn([
                'latitude' => 5.37, 'longitude' => -3.98, 'address_quality' => 'detailed',
                'display_name' => 'Rue nouvelle, Angré, Cocody', 'address' => ['road' => 'Rue nouvelle'],
                'resolved_location' => ['commune' => 'Cocody', 'quartier' => 'Angré'],
            ]);
        $this->postJson('/api/mobile/v1/vendor/shop/logistics-location', [
            'latitude' => 5.37, 'longitude' => -3.98, 'geo_accuracy' => 10, 'geo_captured_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('address', 'Rue nouvelle')->assertJsonPath('commune', 'Cocody')
            ->assertJsonPath('district', 'Angré')->assertJsonStructure(['location_token']);
        $this->assertSame('Ancienne adresse', $shop->fresh()->address);
    }

    public function test_failed_reverse_geocoding_does_not_return_old_shop_details(): void
    {
        $this->shop('seller');
        $this->mock(\App\Services\Geo\GeocodingService::class)->shouldReceive('reverse')
            ->andReturn(['address_quality' => 'coordinates_only', 'display_name' => 'Position actuelle détectée']);
        $this->postJson('/api/mobile/v1/vendor/shop/logistics-location', [
            'latitude' => 5.37, 'longitude' => -3.98, 'geo_accuracy' => 10, 'geo_captured_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('location')->assertJsonMissingPath('location_token');
    }

    public function test_manual_map_point_requires_explicit_selection_and_remains_distinct_from_gps(): void
    {
        $shop = $this->shop('seller');
        $capture = ['latitude' => 5.37, 'longitude' => -3.98, 'geo_source' => 'manual_map',
            'geo_accuracy' => null, 'geo_captured_at' => now()->toIso8601String()];
        $this->postJson('/api/mobile/v1/vendor/shop/logistics-location', $capture)->assertUnprocessable();
        $resolved = $this->postJson('/api/mobile/v1/vendor/shop/logistics-location', $capture + ['map_position_confirmed' => true])
            ->assertOk()->json();
        $data = array_replace($this->switchData('ovanie'), $capture, ['location_token' => $resolved['location_token']]);
        $this->postJson(self::ENDPOINT, $data)->assertOk();
        $this->assertSame('manual_map', $shop->fresh()->geo_source);
        $this->assertNull($shop->fresh()->geo_accuracy);
    }
    public function test_approximate_preview_fills_address_without_authorizing_activation(): void
    {
        $shop = $this->shop('seller');
        $before = $shop->address;
        $capture = ['latitude' => 5.37, 'longitude' => -3.98, 'geo_source' => 'browser_gps',
            'geo_accuracy' => 1000, 'geo_captured_at' => now()->toIso8601String(), 'preview_only' => true];
        $this->postJson('/api/mobile/v1/vendor/shop/logistics-location', $capture)
            ->assertOk()->assertJsonPath('commune', 'Cocody')->assertJsonPath('location_token', null);
        $this->postJson(self::ENDPOINT, array_replace($this->switchData('ovanie'), $capture, ['location_token' => '']))
            ->assertUnprocessable();
        $this->assertSame($before, $shop->fresh()->address);
        $this->assertSame('seller', $shop->fresh()->logistics_type);
    }
}
