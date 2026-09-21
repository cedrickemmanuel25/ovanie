<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_add_update_and_delete_an_image_of_own_product(): void
    {
        Storage::fake('public');
        [$vendor, $product] = $this->product();

        $created = $this->actingAs($vendor)->postJson('/api/product-images', [
            'product_id' => $product->id,
            'path' => UploadedFile::fake()->image('produit.jpg'),
            'is_main' => true,
        ])->assertCreated();
        $image = ProductImage::findOrFail($created->json('id'));
        Storage::disk('public')->assertExists($image->path);

        $this->actingAs($vendor)->patchJson("/api/product-images/{$image->id}", [
            'is_main' => false,
        ])->assertOk()->assertJsonPath('is_main', false);

        $path = $image->path;
        $this->actingAs($vendor)->deleteJson("/api/product-images/{$image->id}")->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_vendor_cannot_act_on_another_vendors_product_or_image(): void
    {
        Storage::fake('public');
        [$owner, $product] = $this->product();
        [$attacker] = $this->product();
        $image = $this->image($product, 'products/private.jpg');
        Storage::disk('public')->put($image->path, 'private');

        $this->actingAs($attacker)->postJson('/api/product-images', [
            'product_id' => $product->id,
            'path' => UploadedFile::fake()->image('attaque.jpg'),
        ])->assertForbidden();
        $this->actingAs($attacker)->patchJson("/api/product-images/{$image->id}", ['is_main' => true])->assertForbidden();
        $this->actingAs($attacker)->deleteJson("/api/product-images/{$image->id}")->assertForbidden();

        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'product_id' => $product->id]);
    }

    public function test_product_id_cannot_be_changed_to_move_an_image(): void
    {
        [$vendor, $product] = $this->product();
        [, $otherProduct] = $this->product();
        $image = $this->image($product);

        $this->actingAs($vendor)->patchJson("/api/product-images/{$image->id}", [
            'product_id' => $otherProduct->id,
        ])->assertUnprocessable();
        $this->assertSame($product->id, $image->fresh()->product_id);
    }

    public function test_forbidden_attempt_never_deletes_the_stored_file(): void
    {
        Storage::fake('public');
        [, $product] = $this->product();
        [$attacker] = $this->product();
        $image = $this->image($product, 'products/protected.jpg');
        Storage::disk('public')->put($image->path, 'protected');

        $this->actingAs($attacker)->deleteJson("/api/product-images/{$image->id}")->assertForbidden();
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_only_owner_can_change_the_main_image(): void
    {
        [$owner, $product] = $this->product();
        [$other] = $this->product();
        $image = $this->image($product);

        $this->actingAs($other)->patchJson("/api/product-images/{$image->id}", ['is_main' => true])->assertForbidden();
        $this->assertFalse($image->fresh()->is_main);
        $this->actingAs($owner)->patchJson("/api/product-images/{$image->id}", ['is_main' => true])->assertOk();
        $this->assertTrue($image->fresh()->is_main);
    }

    public function test_authorized_administrator_keeps_global_access(): void
    {
        [, $product] = $this->product();
        $image = $this->image($product);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->patchJson("/api/product-images/{$image->id}", ['is_main' => true])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/product-images/{$image->id}")->assertOk();
    }

    public function test_unauthenticated_user_is_refused_and_unknown_image_is_not_found(): void
    {
        [, $product] = $this->product();

        $this->postJson('/api/product-images', ['product_id' => $product->id])->assertUnauthorized();
        $this->patchJson('/api/product-images/999999', ['is_main' => true])->assertUnauthorized();
        $this->deleteJson('/api/product-images/999999')->assertUnauthorized();

        $vendor = $product->shop->user;
        $this->actingAs($vendor)->deleteJson('/api/product-images/999999')->assertNotFound();
    }

    private function product(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique image',
            'slug' => 'boutique-image-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Cocody',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Images ' . uniqid(), 'slug' => 'images-' . uniqid(), 'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit image', 'slug' => 'produit-image-' . uniqid(),
            'price' => 1000, 'stock' => 2, 'status' => 'approved', 'is_active' => true,
        ]);

        return [$vendor, $product];
    }

    private function image(Product $product, string $path = 'products/existing.jpg'): ProductImage
    {
        return ProductImage::query()->create([
            'product_id' => $product->id, 'path' => $path, 'is_main' => false,
        ]);
    }
}
