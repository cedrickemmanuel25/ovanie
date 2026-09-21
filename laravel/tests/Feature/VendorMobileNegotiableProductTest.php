<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorMobileNegotiableProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_product_endpoint_exposes_negotiable_offer_fields(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat-' . uniqid(), 'status' => 'actif']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit négociable', 'slug' => 'produit-negociable-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 5,
            'status' => 'approved', 'is_active' => true,
            'is_negotiable' => true, 'price_p1' => 9500, 'price_p2' => 9000, 'price_p3' => 8500,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')->getJson("/api/mobile/v1/vendor/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('product.is_negotiable', true);
        $response->assertJsonPath('product.price_p1', 9500);
        $response->assertJsonPath('product.price_p2', 9000);
        $response->assertJsonPath('product.price_p3', 8500);
    }

    public function test_mobile_product_endpoint_reports_false_for_a_fixed_price_product(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat-' . uniqid(), 'status' => 'actif']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit prix fixe', 'slug' => 'produit-fixe-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 5,
            'status' => 'approved', 'is_active' => true,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')->getJson("/api/mobile/v1/vendor/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('product.is_negotiable', false);
    }
}
