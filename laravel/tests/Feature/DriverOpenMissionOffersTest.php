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
use App\Services\LogisticsShipmentWorkflowService;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverOpenMissionOffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_becoming_available_receives_open_missions_once(): void
    {
        $item = $this->openOvanieItem();
        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur Test',
            'phone' => '0700001111',
            'status' => 'Indisponible',
            'vehicle' => 'moto',
            'is_active' => true,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);
        $workflow = app(LogisticsShipmentWorkflowService::class);

        $this->assertSame(0, $workflow->offerOpenMissionsToDriver($driver), 'Un livreur indisponible ne reçoit rien.');

        $driver->forceFill(['status' => 'Disponible'])->save();
        $this->assertSame(1, $workflow->offerOpenMissionsToDriver($driver->fresh()));
        $this->assertSame(0, $workflow->offerOpenMissionsToDriver($driver->fresh()), 'Pas de doublon.');

        $offer = DeliveryAssignment::query()->where('driver_id', $driver->id)->sole();
        $this->assertSame('offered', $offer->status);
        $this->assertSame($item->id, (int) $offer->order_item_id);
    }

    public function test_mission_reserved_by_another_driver_is_not_offered(): void
    {
        $item = $this->openOvanieItem();
        $other = DeliveryDriver::query()->create([
            'name' => 'Autre', 'phone' => '0700002222', 'status' => 'Disponible', 'vehicle' => 'moto',
            'is_active' => true, 'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);
        DeliveryAssignment::query()->create([
            'mission_number' => 'OVL-TEST', 'order_id' => $item->order_id, 'order_item_id' => $item->id,
            'driver_id' => $other->id, 'status' => 'accepted',
        ]);
        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur Test', 'phone' => '0700001111', 'status' => 'Disponible', 'vehicle' => 'moto',
            'is_active' => true, 'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);

        $this->assertSame(0, app(LogisticsShipmentWorkflowService::class)->offerOpenMissionsToDriver($driver));
    }

    private function openOvanieItem(): OrderItem
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique OVANIE', 'slug' => 'boutique-ovanie-test',
            'seller_type' => 'particulier', 'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.36, 'longitude' => -3.99, 'identity_type' => 'cni',
            'identity_number' => 'CI-OFFER-001', 'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
            'mm_operator' => 'wave', 'mm_number' => '0101010101',
        ]);
        $category = Category::create(['name' => 'Offres', 'slug' => 'offres', 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Petit colis', 'slug' => 'petit-colis', 'price' => 1000, 'stock' => 10,
            'weight' => 2, 'status' => 'approved', 'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CMD-OFFER-1', 'client_id' => $client->id, 'vendor_id' => $vendor->id,
            'shop_id' => $shop->id, 'product_id' => $product->id, 'status' => 'confirmed', 'total_amount' => 2000,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 1000, 'subtotal' => 1000,
        ]);
        $item->forceFill([
            'delivery_provider' => OrderWorkflowService::PROVIDER_OVANIE,
            'delivery_status' => OrderWorkflowService::DELIVERY_PENDING,
            'delivery_price' => 1000,
            'vendor_visible_at' => now(),
        ])->save();

        return $item->fresh();
    }
}
