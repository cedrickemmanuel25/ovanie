<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un visiteur non connecté doit pouvoir parcourir le catalogue, consulter une
 * fiche produit et constituer son panier sans compte. L'authentification
 * n'est exigée qu'au moment de passer commande (voir routes/web.php,
 * GuestCartService et CartController).
 */
class GuestCartTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Boutique visiteur',
            'slug' => 'boutique-visiteur-' . uniqid(),
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'district' => 'Abidjan',
            'address' => 'Cocody',
            'latitude' => 5.359952,
            'longitude' => -4.008256,
            'identity_type' => 'cni',
            'identity_number' => 'CI-GUEST-' . uniqid(),
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'ovanie',
            'logistics_status' => 'ready',
            'mm_operator' => 'wave',
            'mm_number' => '0101010101',
        ]);
        $category = Category::create([
            'name' => 'Materiaux visiteur',
            'slug' => 'materiaux-visiteur-' . uniqid(),
            'status' => 'actif',
        ]);

        return Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit visiteur',
            'slug' => 'produit-visiteur-' . uniqid(),
            'price' => 2500,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_a_guest_can_view_the_cart_page_without_authentication(): void
    {
        $this->get(route('cart.index'))->assertOk();
    }

    public function test_a_guest_can_add_a_product_to_the_session_cart(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('cart.add', $product->id), ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('cart.apiCart'))
            ->assertOk()
            ->assertJsonPath('item_count', 2);
    }

    public function test_a_guest_can_update_and_remove_items_in_the_session_cart(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('cart.add', $product->id), ['quantity' => 1])->assertOk();
        $this->postJson(route('cart.update', $product->id), ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('item_quantity', 3);

        $this->postJson(route('cart.remove', $product->id))->assertOk();
        $this->getJson(route('cart.apiCart'))->assertJsonPath('item_count', 0);
    }

    public function test_the_cart_count_endpoint_works_without_authentication(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('cart.add', $product->id), ['quantity' => 4])->assertOk();

        $this->getJson(route('cart.count'))
            ->assertOk()
            ->assertJsonPath('count', 4);
    }

    public function test_a_guest_cart_merges_into_the_account_cart_on_login(): void
    {
        $product = $this->makeProduct();
        $client = User::factory()->create(['role' => 'client', 'password' => bcrypt('password')]);

        $this->postJson(route('cart.add', $product->id), ['quantity' => 2])->assertOk();

        $this->post(route('login.web'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame([], session('ovanie_guest_cart', []));
    }

    public function test_checkout_still_requires_authentication_for_a_guest(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
    }

    /**
     * Régression : app/Http/Middleware/Authenticate.php faisait un simple
     * redirect()->route('login') au lieu de redirect()->guest(...), donc
     * l'URL visée n'était jamais mémorisée (session url.intended) et
     * redirect()->intended() dans UserAuthController renvoyait toujours vers
     * le tableau de bord, même quand l'utilisateur venait de /checkout.
     */
    public function test_a_guest_redirected_to_login_from_checkout_returns_there_after_login(): void
    {
        $client = User::factory()->create(['role' => 'client', 'password' => bcrypt('password')]);

        $this->get(route('checkout.index'))->assertRedirect(route('login'));

        $this->post(route('login.web'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('checkout.index'));
    }

    public function test_a_guest_redirected_to_login_from_a_post_checkout_action_returns_to_the_previous_page(): void
    {
        $product = $this->makeProduct();
        $client = User::factory()->create(['role' => 'client', 'password' => bcrypt('password')]);

        $this->postJson(route('cart.add', $product->id), ['quantity' => 1])->assertOk();

        $this->withHeaders(['referer' => route('cart.index')])
            ->post(route('checkout.selection'))
            ->assertRedirect(route('login'));

        $this->post(route('login.web'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('cart.index'));
    }

    public function test_calculator_add_to_cart_works_without_authentication(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('calculator.addToCart'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->assertJsonPath('success', true);
    }
}
