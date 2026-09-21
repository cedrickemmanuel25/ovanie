<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_api_routes_exist_and_are_protected(): void
    {
        foreach ([
            ['GET', 'api/cart'],
            ['POST', 'api/cart'],
            ['PUT', 'api/cart/{cartItem}'],
            ['DELETE', 'api/cart/{cartItem}'],
        ] as [$method, $uri]) {
            $route = $this->findRoute($method, $uri);
            $this->assertNotNull($route, "Route {$method} {$uri} introuvable.");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "Route {$method} {$uri} doit être protégée.");
        }
    }

    public function test_adding_a_product_to_the_cart_removes_it_from_favorites(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Boutique panier',
            'slug' => 'boutique-panier',
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'district' => 'Abidjan',
            'address' => 'Cocody',
            'latitude' => 5.359952,
            'longitude' => -4.008256,
            'identity_type' => 'cni',
            'identity_number' => 'CI-CART-001',
            'status' => 'approved',
            'is_active' => true,
            'logistics_type' => 'ovanie',
            'logistics_status' => 'ready',
            'mm_operator' => 'wave',
            'mm_number' => '0101010101',
        ]);
        $category = Category::create([
            'name' => 'Materiaux panier',
            'slug' => 'materiaux-panier',
            'status' => 'actif',
        ]);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Produit favori',
            'slug' => 'produit-favori',
            'price' => 1000,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);

        Favorite::create(['user_id' => $client->id, 'product_id' => $product->id]);

        $this->actingAs($client)
            ->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('is_favorite', false);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $client->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    private function findRoute(string $method, string $uri): mixed
    {
        return collect(Route::getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    }
}
