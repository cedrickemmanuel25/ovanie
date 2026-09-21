<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductImageDataSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_response_contains_only_required_image_information(): void
    {
        [$product, $image] = $this->productWithImage();

        $this->getJson('/api/product-images')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $image->id)
            ->assertJsonPath('0.order', 3)
            ->assertJsonPath('0.is_main', true)
            ->assertJsonPath('0.alt', null)
            ->assertJsonPath('0.product_public_id', $product->slug)
            ->assertJsonStructure([['id', 'url', 'order', 'is_main', 'alt', 'product_public_id']]);
    }

    public function test_no_internal_product_vendor_or_logistics_field_is_returned(): void
    {
        [, $image] = $this->productWithImage();
        $content = $this->getJson("/api/product-images/{$image->id}")->assertOk()->getContent();

        foreach (['shop_id', 'vendor_id', 'user_id', 'address', 'commune', 'latitude', 'longitude',
            'delivery_provider', 'fulfillment_type', 'boost', 'publication'] as $forbidden) {
            $this->assertStringNotContainsString('"' . $forbidden . '"', $content);
        }
    }

    public function test_complete_product_relation_is_never_serialized(): void
    {
        [, $image] = $this->productWithImage();

        $this->getJson("/api/product-images/{$image->id}")
            ->assertOk()
            ->assertJsonMissingPath('product')
            ->assertJsonMissingPath('product.name')
            ->assertJsonMissingPath('product.price');
    }

    public function test_unknown_image_returns_not_found_without_server_error(): void
    {
        $this->getJson('/api/product-images/999999')->assertNotFound();
    }

    public function test_catalog_and_product_page_continue_to_display_product_images(): void
    {
        [$product, $image] = $this->productWithImage();

        $product->load('images');
        $this->assertCount(1, $product->images);
        $this->assertSame($image->id, $product->images->first()->id);
        $this->assertStringContainsString('public-test.jpg', $product->images->first()->public_url);
    }

    private function productWithImage(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique publique',
            'slug' => 'boutique-publique-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Catalogue ' . uniqid(), 'slug' => 'catalogue-' . uniqid(), 'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit public image', 'slug' => 'produit-public-image-' . uniqid(),
            'description' => 'Produit visible', 'price' => 1500, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
        ]);
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/public-test.jpg',
            'sort_order' => 3,
            'is_main' => true,
        ]);

        return [$product, $image];
    }
}
