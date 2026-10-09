<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverPayout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DriverPayoutService;
use App\Services\LogisticsShipmentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPayoutAfterDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['driver_payouts.execution_mode' => 'simulation', 'driver_payouts.cash_limit' => 50000]);
    }

    public function test_online_paid_delivery_is_transferred_right_after_delivery(): void
    {
        [$driver, $assignments] = $this->deliveredMission(3242, 2756);
        $service = app(DriverPayoutService::class);
        $service->saveAccount($driver, 'wave', '0701990010');

        $payout = $service->recordDeliveredMission($driver->fresh(), 'OVL-PAY-01', $assignments, 0);

        $this->assertSame(DriverPayout::STATUS_PAID, $payout->status);
        $this->assertSame(2756.0, $payout->transfer_amount);
        // Idempotent : une seconde confirmation ne crée pas de second versement.
        $service->recordDeliveredMission($driver->fresh(), 'OVL-PAY-01', $assignments, 0);
        $this->assertSame(1, DriverPayout::count());
    }

    public function test_payout_waits_for_mobile_money_account_then_leaves(): void
    {
        [$driver, $assignments] = $this->deliveredMission(3242, 2756);
        $service = app(DriverPayoutService::class);

        $payout = $service->recordDeliveredMission($driver, 'OVL-PAY-01', $assignments, 0);
        $this->assertSame(DriverPayout::STATUS_BLOCKED, $payout->status);

        $service->saveAccount($driver, 'orange', '+225 07 01 99 00 10');
        $service->retryForDriver($driver->fresh());
        $this->assertSame(DriverPayout::STATUS_PAID, $payout->fresh()->status);
        $this->assertSame('0701990010', $payout->fresh()->phone);
    }

    public function test_cash_on_delivery_keeps_net_and_records_debt_until_limit(): void
    {
        [$driver, $assignments] = $this->deliveredMission(3242, 2756);
        $service = app(DriverPayoutService::class);

        $payout = $service->recordDeliveredMission($driver, 'OVL-PAY-01', $assignments, 60000);

        $this->assertSame(DriverPayout::STATUS_KEPT_IN_CASH, $payout->status);
        $this->assertSame(0.0, $payout->transfer_amount);
        $this->assertSame(57244.0, $service->cashDue($driver));
        $this->assertTrue($service->cashLimitReached($driver));
        $this->assertFalse(app(LogisticsShipmentWorkflowService::class)->isDriverAvailable($driver->fresh()));

        $this->assertSame(57244.0, $service->settleCash($driver));
        $this->assertSame(0.0, $service->cashDue($driver));
        $this->assertTrue(app(LogisticsShipmentWorkflowService::class)->isDriverAvailable($driver->fresh()));
    }

    public function test_logistics_tour_follows_real_delivery_progress(): void
    {
        [$driver] = $this->deliveredMission(1000, 850);
        $item = OrderItem::query()->firstOrFail();
        $item->forceFill(['delivery_status' => 'assigned'])->save();
        $tour = \App\Models\DeliveryTour::create([
            'reference' => 'TRN-TEST-0001', 'driver_id' => $driver->id, 'status' => 'planned',
            'tour_date' => now()->toDateString(), 'departure_time' => '10:00',
        ]);
        foreach (['pickup', 'delivery'] as $i => $type) {
            \App\Models\DeliveryTourStop::create([
                'delivery_tour_id' => $tour->id, 'order_item_id' => $item->id, 'sequence' => $i + 1,
                'stop_key' => "$type:{$item->id}", 'stop_type' => $type, 'label' => $type, 'status' => 'pending',
            ]);
        }

        $item->forceFill(['delivery_status' => 'picked_up'])->save();
        $this->assertSame('in_progress', $tour->fresh()->status);

        $item->forceFill(['delivery_status' => 'delivered'])->save();
        $this->assertSame('done', $tour->fresh()->status);
        $this->assertNotNull($tour->fresh()->completed_at);
    }

    /**
     * @return array{0: DeliveryDriver, 1: \Illuminate\Support\Collection<int, DeliveryAssignment>}
     */
    private function deliveredMission(float $gross, float $net): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique Paie', 'slug' => 'boutique-paie',
            'seller_type' => 'particulier', 'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'identity_type' => 'cni', 'identity_number' => 'CI-PAY-001',
            'status' => 'approved', 'is_active' => true, 'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
            'mm_operator' => 'wave', 'mm_number' => '0101010101',
        ]);
        $category = Category::create(['name' => 'Paie', 'slug' => 'paie', 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Colis', 'slug' => 'colis-paie', 'price' => 1000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CMD-PAY-1', 'client_id' => $client->id, 'vendor_id' => $vendor->id,
            'product_id' => $product->id, 'status' => 'paid', 'total_amount' => 5000,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
            'quantity' => 1, 'price' => 1000, 'subtotal' => 1000,
        ]);
        $driver = DeliveryDriver::query()->create([
            'name' => 'Livreur Paie', 'phone' => '0700004444', 'status' => 'Disponible', 'vehicle' => 'moto',
            'is_active' => true, 'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);
        $assignment = DeliveryAssignment::query()->create([
            'mission_number' => 'OVL-PAY-01', 'order_id' => $order->id, 'order_item_id' => $item->id,
            'driver_id' => $driver->id, 'status' => 'delivered', 'delivered_at' => now(),
            'price_amount' => $gross, 'driver_commission_percent' => 15, 'driver_net_amount' => $net,
            'meta' => ['mission_number' => 'OVL-PAY-01'],
        ]);

        return [$driver->fresh(), collect([$assignment])];
    }
}
