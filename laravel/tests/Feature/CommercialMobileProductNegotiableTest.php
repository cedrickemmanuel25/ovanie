<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialMobileProductNegotiableTest extends TestCase
{
    use RefreshDatabase;

    private function makeCommercialWithDraftProduct(): array
    {
        $commercial = User::factory()->create(['role' => 'commercial', 'status' => 'active']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'created_by_commercial_id' => $commercial->id,
            'name' => 'Boutique test', 'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat-' . uniqid(), 'status' => 'actif']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit brouillon', 'slug' => 'produit-brouillon-' . uniqid(),
            'description' => null, 'price' => 0, 'stock' => 0,
            'status' => 'draft', 'is_active' => false,
        ]);

        return [$commercial, $product];
    }

    public function test_negotiable_offers_are_saved_when_all_three_are_provided(): void
    {
        [$commercial, $product] = $this->makeCommercialWithDraftProduct();

        $response = $this->actingAs($commercial, 'sanctum')->postJson(
            "/api/mobile/v1/commercial/products/{$product->id}/steps/2",
            [
                'price' => 10000,
                'is_negotiable' => '1',
                'price_p1' => 9500,
                'price_p2' => 9000,
                'price_p3' => 8500,
            ]
        );

        $response->assertOk();
        $product->refresh();
        $this->assertTrue((bool) $product->is_negotiable);
        $this->assertSame(9500, (int) $product->price_p1);
        $this->assertSame(9000, (int) $product->price_p2);
        $this->assertSame(8500, (int) $product->price_p3);
    }

    public function test_is_negotiable_stays_false_when_an_offer_is_missing(): void
    {
        [$commercial, $product] = $this->makeCommercialWithDraftProduct();

        $response = $this->actingAs($commercial, 'sanctum')->postJson(
            "/api/mobile/v1/commercial/products/{$product->id}/steps/2",
            [
                'price' => 10000,
                'is_negotiable' => '1',
                'price_p1' => 9500,
                'price_p2' => 9000,
            ]
        );

        $response->assertOk();
        $this->assertFalse((bool) $product->refresh()->is_negotiable);
    }

    public function test_edit_payload_exposes_negotiable_fields(): void
    {
        [$commercial, $product] = $this->makeCommercialWithDraftProduct();
        $product->update(['is_negotiable' => true, 'price_p1' => 950, 'price_p2' => 900, 'price_p3' => 850, 'price' => 1000]);

        $response = $this->actingAs($commercial, 'sanctum')->getJson("/api/mobile/v1/commercial/products/{$product->id}/edit");

        $response->assertOk();
        $response->assertJsonPath('product.is_negotiable', true);
        $response->assertJsonPath('product.price_p3', 850);
    }
}
