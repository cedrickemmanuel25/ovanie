<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WaveRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_redirection_uses_nonexistent_orders_show_route(): void
    {
        $this->assertFalse(Route::has('orders.show'));
        $this->assertTrue(Route::has('client.orders.show'));
    }

    public function test_client_is_redirected_to_own_order(): void
    {
        [$client, $order, $payment] = $this->wavePayment();

        $this->actingAs($client)
            ->get(route('wave.callback', ['reference' => $payment->reference]))
            ->assertRedirect(route('client.orders.show', $order));
    }

    public function test_client_cannot_access_another_clients_order(): void
    {
        [, , $payment] = $this->wavePayment();
        $otherClient = User::factory()->create();

        $this->actingAs($otherClient)
            ->get(route('wave.callback', ['reference' => $payment->reference]))
            ->assertRedirect(route('client.orders'))
            ->assertSessionHas('error', 'Paiement ou commande introuvable');
    }

    public function test_unknown_reference_does_not_cause_server_error(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)
            ->get(route('wave.callback', ['reference' => 'WAVE-INCONNUE']))
            ->assertRedirect(route('client.orders'))
            ->assertSessionHas('error', 'Paiement ou commande introuvable');
    }

    public function test_redirect_destinations_really_exist(): void
    {
        $this->assertTrue(Route::has('client.orders'));
        $this->assertTrue(Route::has('client.orders.show'));

        $client = User::factory()->create();
        $this->actingAs($client)->get(route('client.orders'))->assertOk();
    }

    private function wavePayment(): array
    {
        $client = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Boutique Redirect Wave',
            'slug' => 'boutique-redirect-wave-' . uniqid(),
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
            'name' => 'Redirect Wave ' . uniqid(),
            'slug' => 'redirect-wave-' . uniqid(),
            'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit Redirect Wave',
            'slug' => 'produit-redirect-wave-' . uniqid(),
            'price' => 1000,
            'stock' => 1,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-' . uniqid(),
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
            'reference' => 'WAVE-' . uniqid(),
        ]);

        return [$client, $order, $payment];
    }
}
