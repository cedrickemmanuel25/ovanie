<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaveWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_without_signature_is_rejected(): void
    {
        $this->postJson(route('wave.webhook'), $this->payload())
            ->assertStatus(503);
    }

    public function test_webhook_with_fake_signature_is_rejected(): void
    {
        $this->postJson(route('wave.webhook'), $this->payload(), [
            'X-Wave-Signature' => 'sha256=fake',
        ])->assertStatus(503);
    }

    public function test_payload_modified_after_signature_is_rejected(): void
    {
        $secret = 'test-webhook-secret';
        $signedBody = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $signedBody, $secret);

        $modified = $this->payload();
        $modified['status'] = 'failed';

        $this->withHeader('X-Wave-Signature', 'sha256=' . $signature)
            ->postJson(route('wave.webhook'), $modified)
            ->assertStatus(503);
    }

    public function test_invalid_webhook_does_not_modify_payment_or_order(): void
    {
        [$order, $payment] = $this->wavePayment();
        $order->refresh();
        $payment->refresh();
        $orderBefore = $order->getAttributes();
        $paymentBefore = $payment->getAttributes();

        $this->postJson(route('wave.webhook'), $this->payload($payment), [
            'X-Wave-Signature' => 'sha256=fake',
        ])->assertStatus(503);

        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
    }

    public function test_same_event_cannot_be_processed_twice(): void
    {
        $payload = $this->payload();

        $this->postJson(route('wave.webhook'), $payload)->assertStatus(503);
        $this->postJson(route('wave.webhook'), $payload)->assertStatus(503);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_incomplete_payload_does_not_cause_server_error(): void
    {
        $this->postJson(route('wave.webhook'), [])->assertStatus(503);
    }

    private function payload(?Payment $payment = null): array
    {
        return [
            'event_id' => 'evt-wave-001',
            'reference' => $payment?->reference ?? 'WAVE-TEST',
            'status' => 'success',
            'transaction_id' => 'txn-wave-001',
        ];
    }

    private function wavePayment(): array
    {
        $client = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Boutique Webhook Wave',
            'slug' => 'boutique-webhook-wave',
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'address' => 'Cocody',
            'identity_type' => 'cni',
            'identity_number' => 'CI-WAVE-WEBHOOK',
            'status' => 'approved',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Webhook Wave',
            'slug' => 'webhook-wave',
            'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit Webhook Wave',
            'slug' => 'produit-webhook-wave',
            'price' => 1000,
            'stock' => 1,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-WAVE-WEBHOOK',
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'payment_method' => 'wave',
            'payment_status' => 'pending',
            'total_amount' => 1000,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $client->id,
            'method' => Payment::METHOD_WAVE,
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'WAVE-WEBHOOK-TEST',
        ]);

        return [$order, $payment];
    }
}
