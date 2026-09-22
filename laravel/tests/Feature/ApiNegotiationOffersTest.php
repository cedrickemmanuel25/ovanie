<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Négociation guidée en 3 offres réelles, côté API mobile (app client).
 * Même comportement que le Web (voir ProductNegotiationUiTest), servi via
 * routes/api.php + auth:sanctum : GET /api/products/{slug}/negotiation-offers,
 * POST /api/products/{slug}/negotiation-offers/accept, POST /api/cart/add-negotiated.
 */
class ApiNegotiationOffersTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(bool $negotiable): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique négociation mobile',
            'slug' => 'boutique-negociation-mobile-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-NEGM-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);
        $category = Category::create([
            'name' => 'Négociation mobile', 'slug' => 'negociation-mobile-' . uniqid(), 'status' => 'actif',
        ]);

        return Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit négociable mobile', 'slug' => 'produit-negociable-mobile-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
            'is_negotiable' => $negotiable,
            'price_p1' => $negotiable ? 9500 : null,
            'price_p2' => $negotiable ? 9000 : null,
            'price_p3' => $negotiable ? 8500 : null,
        ]);
    }

    public function test_a_guest_cannot_fetch_offers_on_the_api(): void
    {
        $product = $this->makeProduct(negotiable: true);

        $response = $this->getJson("/api/products/{$product->slug}/negotiation-offers");

        $response->assertUnauthorized();
    }

    public function test_an_authenticated_buyer_receives_the_offers_via_the_api(): void
    {
        $product = $this->makeProduct(negotiable: true);
        $buyer = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($buyer);

        $response = $this->getJson("/api/products/{$product->slug}/negotiation-offers");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'offers' => [9500, 9000, 8500],
        ]);
    }

    public function test_a_buyer_can_accept_an_offer_and_add_it_to_the_cart_via_the_api(): void
    {
        $product = $this->makeProduct(negotiable: true);
        $buyer = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($buyer);

        $accept = $this->postJson("/api/products/{$product->slug}/negotiation-offers/accept", [
            'proposed_price' => 9500,
        ]);
        $accept->assertOk();
        $accept->assertJsonPath('accepted', true);
        $negotiationId = $accept->json('negotiation_id');

        $cart = $this->postJson('/api/cart/add-negotiated', [
            'product_id' => $product->id,
            'negotiated_price' => 9500,
            'negotiation_id' => $negotiationId,
            'quantity' => 1,
        ]);

        $cart->assertOk();
        $cart->assertJsonPath('success', true);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'price' => 9500,
            'price_source' => 'negotiated',
        ]);
    }

    public function test_the_api_refuses_a_non_negotiable_product(): void
    {
        $product = $this->makeProduct(negotiable: false);
        $buyer = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($buyer);

        $response = $this->getJson("/api/products/{$product->slug}/negotiation-offers");

        $response->assertForbidden();
    }
}
