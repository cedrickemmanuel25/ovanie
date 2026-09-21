<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorPayoutAdjustment;
use App\Services\OrderWorkflowService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndCommerceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_payment_delivery_reception_payout_and_refund_flow(): void
    {
        config()->set('paydunya.allow_unsigned_webhooks_in_testing', true);

        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $logistics = User::factory()->create(['role' => 'logistique', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Boutique E2E',
            'slug' => 'boutique-e2e',
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'address' => 'Cocody',
            'identity_type' => 'cni',
            'identity_number' => 'CI-TEST-001',
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'ovanie',
            'mm_operator' => 'wave',
            'mm_number' => '0101010101',
        ]);

        $category = Category::create([
            'name' => 'Materiaux',
            'slug' => 'materiaux',
            'status' => 'actif',
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Ciment E2E',
            'slug' => 'ciment-e2e',
            'price' => 100000,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'OVN-E2E-001',
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'payment_method' => PaymentService::METHOD_PAYDUNYA,
            'payment_status' => 'pending',
            'subtotal' => 100000,
            'delivery_fee' => 5000,
            'total_amount' => 105000,
            'phone' => '0101010101',
            'address' => 'Abidjan',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
            'price' => 100000,
            'subtotal' => 100000,
            'delivery_provider' => OrderWorkflowService::PROVIDER_OVANIE,
            'delivery_status' => OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
            'reception_status' => 'waiting',
            'payout_status' => 'not_ready',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $client->id,
            'method' => PaymentService::METHOD_PAYDUNYA,
            'amount' => 105000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'PAY-E2E-001',
        ]);

        $this->postJson(route('paydunya.webhook'), [
            'data' => [
                'token' => $payment->reference,
                'status' => 'completed',
                'transaction_id' => 'TX-E2E-001',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_ESCROW_HELD,
        ]);
        $this->assertDatabaseHas('vendor_payouts', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'status' => VendorPayout::STATUS_BLOCKED,
        ]);

        $workflow = app(OrderWorkflowService::class);
        $workflow->setDeliveryStatus($item->refresh(), OrderWorkflowService::DELIVERY_ASSIGNED, $logistics, 'logistics');
        $workflow->setDeliveryStatus($item->refresh(), OrderWorkflowService::DELIVERY_PICKED_UP, $logistics, 'logistics');
        $workflow->setDeliveryStatus($item->refresh(), OrderWorkflowService::DELIVERY_IN_TRANSIT, $logistics, 'logistics');
        $workflow->setDeliveryStatus(
            $item->refresh(),
            OrderWorkflowService::DELIVERY_DELIVERED,
            $logistics,
            'logistics',
            extra: ['delivery_proof_id' => 1]
        );
        $workflow->confirmClientReception($item->refresh(), $client);

        $this->assertDatabaseHas('vendor_payouts', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'status' => VendorPayout::STATUS_PENDING,
        ]);

        $return = ReturnModel::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'order_reference' => $order->order_number,
            'product_name' => $product->name,
            'reason' => 'Produit non conforme au besoin du chantier.',
            'request_date' => now()->toDateString(),
            'status' => ReturnModel::STATUS_ACCEPTED,
            'logistics_status' => ReturnModel::LOGISTICS_REFUND_PENDING,
            'refund_amount' => 100000,
        ]);

        $this->actingAs($logistics, 'admin')
            ->from(route('logistics.returns'))
            ->patch(route('logistics.returns.approve', $return), [
                'refund_reference' => 'REF-E2E-001',
                'refund_method' => 'mobile_money',
            ])
            ->assertRedirect(route('logistics.returns'));

        $this->assertDatabaseHas('returns', [
            'id' => $return->id,
            'status' => ReturnModel::STATUS_REFUNDED,
        ]);
        $this->assertDatabaseHas('vendor_payout_adjustments', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'return_id' => $return->id,
            'type' => 'refund_deduction',
        ]);

        $adjustment = VendorPayoutAdjustment::where('return_id', $return->id)->firstOrFail();
        $this->assertSame(100000.0, (float) $adjustment->amount);
        $this->assertGreaterThanOrEqual(3, DB::table('notifications')->count());
    }
}
