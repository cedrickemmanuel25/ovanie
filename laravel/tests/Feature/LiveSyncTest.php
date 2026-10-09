<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\LiveSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_only_receives_public_topics(): void
    {
        $this->getJson('/api/live-sync')
            ->assertOk()
            ->assertJsonStructure(['topics' => ['catalog'], 'origins', 'poll_after_ms'])
            ->assertJsonMissingPath('topics.cart');
    }

    public function test_cart_change_from_mobile_is_visible_to_the_web_session(): void
    {
        [$client, , , $product] = $this->catalog();

        $before = $this->actingAs($client)->getJson('/live-sync')->assertOk()->json('topics.cart');

        Sanctum::actingAs($client);
        $this->withHeader('X-Ovanie-Client', 'mobile-app-123')
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $after = $this->actingAs($client)->getJson('/live-sync')->assertOk();
        $this->assertNotSame($before, $after->json('topics.cart'));
        $this->assertSame('mobile-app-123', $after->json('origins.cart'));
    }

    public function test_web_cart_page_declares_its_live_sync_topics(): void
    {
        [$client] = $this->catalog();

        $this->actingAs($client)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('id="ovanie-live-sync-config"', false)
            ->assertSee('js/live-sync.js', false)
            ->assertSee('"topics":["cart"]', false);
    }

    public function test_vendor_sees_shop_topic_and_product_watch_ignores_view_counters(): void
    {
        [, $vendor, , $product] = $this->catalog();

        $watch = fn () => $this->actingAs($vendor)
            ->getJson('/live-sync?watch=product:'.$product->id)
            ->assertOk();

        $initial = $watch();
        $this->assertArrayHasKey('shop', $initial->json('topics'));

        $product->forceFill(['views' => 42])->save();
        $this->assertSame($initial->json('topics')['product:'.$product->id], $watch()->json('topics')['product:'.$product->id]);

        $product->update(['price' => 1500]);
        $updated = $watch();
        $this->assertNotSame($initial->json('topics')['product:'.$product->id], $updated->json('topics')['product:'.$product->id]);
        $this->assertNotSame($initial->json('topics.shop'), $updated->json('topics.shop'));
    }

    public function test_driver_accepting_a_mission_notifies_logistics_but_gps_pings_do_not(): void
    {
        [$client, $vendor, $shop, $product] = $this->catalog();
        $order = \App\Models\Order::create([
            'order_number' => 'CMD-SYNC-1',
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => 'confirmed',
            'total_amount' => 1000,
        ]);
        $item = \App\Models\OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 1000, 'subtotal' => 1000,
        ]);
        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur Sync',
            'phone' => '0700009999',
            'status' => 'Disponible',
            'is_active' => true,
        ]);
        $assignment = DeliveryAssignment::query()->create([
            'mission_number' => 'MIS-SYNC-1',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'driver_id' => $driver->id,
            'status' => 'offered',
        ]);
        $versions = fn () => LiveSync::versions(['ops', "driver.{$driver->id}.missions"]);

        // Signal GPS : position et heure du dernier signal uniquement.
        $before = $versions();
        $driver->forceFill(['latitude' => 5.35, 'longitude' => -4.0, 'last_seen_at' => now()])->save();
        $this->assertSame($before, $versions());

        // Acceptation : même écriture que DriverMissionService::accept, dans une transaction.
        DB::transaction(fn () => $assignment->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save());
        $after = $versions();
        $this->assertNotSame($before['ops']['v'], $after['ops']['v']);
        $this->assertNotSame($before["driver.{$driver->id}.missions"]['v'], $after["driver.{$driver->id}.missions"]['v']);

        // Passage « En livraison » : visible par la logistique.
        $driver->forceFill(['status' => 'En livraison'])->save();
        $this->assertNotSame($after['ops']['v'], $versions()['ops']['v']);
    }

    public function test_vendor_is_notified_when_driver_accepts_even_if_order_has_no_shop_id(): void
    {
        [$client, $vendor, $shop, $product] = $this->catalog();
        // Commandes mobiles : orders.shop_id vide, la boutique est sur l'article.
        $order = \App\Models\Order::create([
            'order_number' => 'CMD-SYNC-2',
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'confirmed',
            'total_amount' => 1000,
        ]);
        $item = \App\Models\OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 1000, 'subtotal' => 1000,
        ]);
        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur Vendeur', 'phone' => '0700008888', 'status' => 'Disponible', 'is_active' => true,
        ]);
        $assignment = DeliveryAssignment::query()->create([
            'mission_number' => 'MIS-SYNC-2', 'order_id' => $order->id, 'order_item_id' => $item->id,
            'driver_id' => $driver->id, 'status' => 'offered',
        ]);

        $vendorTopic = fn () => $this->actingAs($vendor)->getJson('/live-sync')->assertOk()->json('topics.shop');
        $before = $vendorTopic();

        DB::transaction(fn () => $assignment->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save());

        $this->assertNotSame($before, $vendorTopic(), 'Le vendeur doit voir la mission réservée sans actualiser.');
    }

    /**
     * @return array{0: User, 1: User, 2: Shop, 3: Product}
     */
    private function catalog(): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Boutique sync',
            'slug' => 'boutique-sync',
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'district' => 'Abidjan',
            'address' => 'Cocody',
            'latitude' => 5.359952,
            'longitude' => -4.008256,
            'identity_type' => 'cni',
            'identity_number' => 'CI-SYNC-001',
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'ovanie',
            'logistics_status' => 'ready',
            'mm_operator' => 'wave',
            'mm_number' => '0101010101',
        ]);
        $category = Category::create(['name' => 'Sync', 'slug' => 'sync', 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit sync',
            'slug' => 'produit-sync',
            'price' => 1000,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);

        return [$client, $vendor, $shop, $product];
    }
}
