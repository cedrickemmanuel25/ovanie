<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_only_disputes_for_own_orders(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->dispute($client);
        $this->dispute($other);

        $response = $this->actingAs($client)->getJson('/api/disputes')->assertOk();
        $response->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_vendor_sees_only_disputes_linked_to_their_shop_items(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        $mine = $this->dispute(User::factory()->create(), $vendor);
        $this->dispute(User::factory()->create(), $otherVendor);

        $response = $this->actingAs($vendor)->getJson('/api/disputes')->assertOk();
        $response->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_user_cannot_view_or_modify_another_users_dispute(): void
    {
        $dispute = $this->dispute(User::factory()->create());
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->getJson("/api/disputes/{$dispute->id}")->assertForbidden();
        $this->actingAs($attacker)->putJson("/api/disputes/{$dispute->id}", ['reason' => 'attaque'])->assertForbidden();
    }

    public function test_client_and_vendor_cannot_physically_delete_a_dispute(): void
    {
        $client = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $dispute = $this->dispute($client, $vendor);

        $this->actingAs($client)->deleteJson("/api/disputes/{$dispute->id}")->assertForbidden();
        $this->actingAs($vendor)->deleteJson("/api/disputes/{$dispute->id}")->assertForbidden();
        $this->assertDatabaseHas('disputes', ['id' => $dispute->id, 'deleted_at' => null]);
    }

    public function test_internal_notes_are_never_returned_to_external_users(): void
    {
        $client = User::factory()->create();
        $dispute = $this->dispute($client, null, ['internal_notes' => 'Note strictement interne']);

        $response = $this->actingAs($client)->getJson("/api/disputes/{$dispute->id}")->assertOk();
        $response->assertJsonMissing(['internal_notes' => 'Note strictement interne']);
        $this->assertStringNotContainsString('Note strictement interne', $response->getContent());
    }

    public function test_support_and_admin_keep_authorized_global_access(): void
    {
        $dispute = $this->dispute(User::factory()->create());
        $support = User::factory()->create(['role' => 'support', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($support)->getJson("/api/disputes/{$dispute->id}")->assertOk();
        $this->actingAs($support)->putJson("/api/disputes/{$dispute->id}", [
            'status' => 'resolved', 'internal_notes' => 'Résolu par le support',
        ])->assertOk()->assertJsonPath('status', 'resolved');
        $this->actingAs($admin)->getJson('/api/disputes')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_unknown_and_forbidden_resources_never_return_server_errors(): void
    {
        $user = User::factory()->create();
        $otherDispute = $this->dispute(User::factory()->create());

        $this->actingAs($user)->getJson('/api/disputes/999999')->assertNotFound();
        $this->actingAs($user)->getJson("/api/disputes/{$otherDispute->id}")->assertForbidden();
    }

    private function dispute(User $client, ?User $vendor = null, array $overrides = []): Dispute
    {
        $vendor ??= User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique litige',
            'slug' => 'boutique-litige-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Cocody',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Litiges ' . uniqid(), 'slug' => 'litiges-' . uniqid(), 'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit litigieux', 'slug' => 'produit-litige-' . uniqid(),
            'price' => 10000, 'stock' => 1, 'status' => 'approved', 'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-' . uniqid(),
            'client_id' => $client->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 10000,
        ]);

        return Dispute::query()->create(array_merge([
            'order_id' => $order->id,
            'client_id' => $client->id,
            'vendor_id' => $vendor?->id,
            'shop_id' => $shop->id,
            'order_reference' => $order->order_number,
            'client_name' => $client->name,
            'reason' => 'Article non conforme',
            'status' => 'open',
        ], $overrides));
    }
}
