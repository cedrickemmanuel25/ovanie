<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DriverMissionService;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DriverAutomaticArrivalTest extends TestCase
{
    use RefreshDatabase;

    // Boutique à Cocody.
    private const SHOP_LAT = 5.3600;
    private const SHOP_LNG = -3.9900;

    public function test_arrival_at_shop_is_detected_by_gps_only_when_on_site(): void
    {
        [$driver, $mission] = $this->collectingMission();
        $service = app(DriverMissionService::class);

        // À environ 1,1 km : rien ne se passe.
        $this->assertNull($service->detectAutomaticArrival($driver, $mission, self::SHOP_LAT + 0.01, self::SHOP_LNG, 10));
        $this->assertFalse($this->arrived());

        // À environ 40 m : arrivée enregistrée automatiquement.
        $this->assertSame('pickup_arrived', $service->detectAutomaticArrival($driver, $mission, self::SHOP_LAT + 0.00036, self::SHOP_LNG, 10));
        $this->assertTrue($this->arrived());
    }

    public function test_manual_arrival_is_refused_when_gps_is_far_from_the_shop(): void
    {
        [$driver, $mission] = $this->collectingMission();
        $driver->locations()->create([
            'latitude' => self::SHOP_LAT + 0.02,
            'longitude' => self::SHOP_LNG,
            'accuracy' => 10,
            'recorded_at' => now(),
        ]);

        $stopId = (string) collect(app(DriverMissionService::class)->detail($driver->fresh(), $mission)['pickup_stops'])->first()['id'];

        $this->expectException(ValidationException::class);
        app(DriverMissionService::class)->completePickup($driver->fresh(), $mission, $stopId, 'arrived');
    }

    private function arrived(): bool
    {
        return filled(data_get(DeliveryAssignment::query()->first()?->meta, 'pickup_arrived_at'));
    }

    /**
     * @return array{0: DeliveryDriver, 1: string}
     */
    private function collectingMission(): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique GPS', 'slug' => 'boutique-gps',
            'seller_type' => 'particulier', 'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => self::SHOP_LAT, 'longitude' => self::SHOP_LNG, 'identity_type' => 'cni',
            'identity_number' => 'CI-GPS-001', 'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
            'mm_operator' => 'wave', 'mm_number' => '0101010101',
        ]);
        $category = Category::create(['name' => 'GPS', 'slug' => 'gps', 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Colis GPS', 'slug' => 'colis-gps', 'price' => 1000, 'stock' => 10,
            'weight' => 2, 'status' => 'approved', 'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CMD-GPS-1', 'client_id' => $client->id, 'vendor_id' => $vendor->id,
            'product_id' => $product->id, 'status' => 'confirmed', 'total_amount' => 2000,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 1000, 'subtotal' => 1000,
        ]);
        $item->forceFill([
            'delivery_provider' => OrderWorkflowService::PROVIDER_OVANIE,
            'delivery_status' => OrderWorkflowService::DELIVERY_ASSIGNED,
            'vendor_status' => 'ready',
            'vendor_visible_at' => now(),
        ])->save();

        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur GPS', 'phone' => '0700003333', 'status' => 'En livraison', 'vehicle' => 'moto',
            'is_active' => true, 'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);
        DeliveryAssignment::query()->create([
            'mission_number' => 'OVL-GPS-01', 'order_id' => $order->id, 'order_item_id' => $item->id,
            'driver_id' => $driver->id, 'status' => 'collecting', 'accepted_at' => now(), 'started_at' => now(),
            'meta' => ['mission_number' => 'OVL-GPS-01'],
        ]);

        return [$driver->fresh(), 'OVL-GPS-01'];
    }
}
