<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\WavePaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WaveTransactionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_during_processing_rolls_back_payment_and_order(): void
    {
        [$order, $payment] = $this->wavePayment();
        Payment::updated(function (): void {
            throw new RuntimeException('Erreur simulée');
        });

        try {
            $this->processor()->process($this->event($payment));
            $this->fail('Le traitement aurait dû échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Erreur simulée', $exception->getMessage());
        }

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->transaction_id);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_two_identical_calls_produce_only_one_processing(): void
    {
        [, $payment] = $this->wavePayment();
        $updates = 0;
        Payment::updated(function () use (&$updates): void {
            $updates++;
        });

        $this->assertSame('processed', $this->processor()->process($this->event($payment)));
        $this->assertSame('already_processed', $this->processor()->process($this->event($payment)));
        $this->assertSame(1, $updates);
    }

    public function test_confirmed_payment_is_not_processed_again(): void
    {
        [$order, $payment] = $this->wavePayment([
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'status' => Payment::STATUS_SUCCESS,
            'transaction_id' => 'txn-original',
        ]);
        $paymentBefore = $payment->fresh()->getAttributes();
        $orderBefore = $order->fresh()->getAttributes();

        $result = $this->processor()->process($this->event($payment, 'txn-replay'));

        $this->assertSame('already_processed', $result);
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
    }

    public function test_order_and_payment_remain_consistent(): void
    {
        [$order, $payment] = $this->wavePayment();

        $this->processor()->process($this->event($payment));

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_duplicate_event_creates_no_duplicate_financial_or_logistics_side_effect(): void
    {
        [, $payment, $product] = $this->wavePayment();
        $stockBefore = $product->stock;

        $this->processor()->process($this->event($payment));
        $this->processor()->process($this->event($payment));

        $this->assertSame($stockBefore, $product->fresh()->stock);
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseCount('commissions', 0);
        $this->assertDatabaseCount('vendor_payouts', 0);
        $this->assertDatabaseCount('delivery_assignments', 0);
    }

    private function processor(): WavePaymentProcessor
    {
        return app(WavePaymentProcessor::class);
    }

    private function event(Payment $payment, string $transactionId = 'txn-wave-unique'): array
    {
        return [
            'event_id' => 'evt-wave-unique',
            'reference' => $payment->reference,
            'transaction_id' => $transactionId,
            'status' => 'success',
        ];
    }

    private function wavePayment(array $state = []): array
    {
        $client = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Boutique Transaction Wave',
            'slug' => 'boutique-transaction-wave-' . uniqid(),
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'address' => 'Cocody',
            'identity_type' => 'cni',
            'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Transaction Wave ' . uniqid(),
            'slug' => 'transaction-wave-' . uniqid(),
            'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit Transaction Wave',
            'slug' => 'produit-transaction-wave-' . uniqid(),
            'price' => 1000,
            'stock' => 5,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-' . uniqid(),
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => $state['order_status'] ?? 'pending',
            'payment_method' => 'wave',
            'payment_status' => $state['payment_status'] ?? 'pending',
            'total_amount' => 1000,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $client->id,
            'method' => Payment::METHOD_WAVE,
            'amount' => 1000,
            'status' => $state['status'] ?? Payment::STATUS_PENDING,
            'transaction_id' => $state['transaction_id'] ?? null,
            'reference' => 'WAVE-' . uniqid(),
        ]);

        return [$order, $payment, $product];
    }
}
