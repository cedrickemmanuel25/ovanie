<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaveCallbackSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_success_does_not_validate_a_payment(): void
    {
        [$user, $order, $payment] = $this->wavePayment();

        $this->actingAs($user)
            ->get(route('wave.callback', ['reference' => $payment->reference, 'status' => 'success']))
            ->assertRedirect(route('client.orders.show', $order))
            ->assertSessionHas('error', 'Le paiement Wave est temporairement indisponible');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_fake_reference_does_not_cause_a_server_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('wave.callback', ['reference' => 'FAUSSE-REFERENCE']))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'Paiement introuvable');
    }

    public function test_a_user_cannot_view_another_users_payment(): void
    {
        [, , $payment] = $this->wavePayment();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('wave.callback', ['reference' => $payment->reference]))
            ->assertForbidden();
    }

    public function test_callback_does_not_modify_payment_or_order(): void
    {
        [$user, $order, $payment] = $this->wavePayment();
        $payment->refresh();
        $order->refresh();
        $paymentBefore = $payment->getAttributes();
        $orderBefore = $order->getAttributes();

        $this->actingAs($user)->get(route('wave.callback', [
            'reference' => $payment->reference,
            'status' => 'success',
            'success' => '1',
            'transaction_id' => 'FORGED-TRANSACTION',
        ]));

        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
    }

    private function wavePayment(): array
    {
        $user = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Boutique Wave',
            'slug' => 'boutique-wave-' . uniqid(),
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
            'name' => 'Wave ' . uniqid(),
            'slug' => 'wave-' . uniqid(),
            'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit Wave',
            'slug' => 'produit-wave-' . uniqid(),
            'price' => 1000,
            'stock' => 1,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-' . uniqid(),
            'client_id' => $user->id,
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
            'user_id' => $user->id,
            'method' => Payment::METHOD_WAVE,
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
            'reference' => 'WAVE-' . uniqid(),
        ]);

        return [$user, $order, $payment];
    }
}
