<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendorWithProduct(): array
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
            'name' => 'Produit test', 'slug' => 'produit-test-' . uniqid(),
            'description' => 'x', 'price' => 1000, 'stock' => 5,
            'status' => 'approved', 'is_active' => true,
        ]);

        return [$vendor, $shop, $product];
    }

    public function test_vendor_can_see_reviews_for_their_own_products(): void
    {
        [$vendor, , $product] = $this->makeVendorWithProduct();
        $client = User::factory()->create(['role' => 'client']);

        Review::query()->create([
            'product_id' => $product->id, 'user_id' => $client->id,
            'rating' => 4, 'comment' => 'Très bon produit, livraison rapide.',
        ]);

        $response = $this->actingAs($vendor)->get(route('vendor.reviews.index'));

        $response->assertOk();
        $response->assertSee('Très bon produit, livraison rapide.');
        $response->assertSee($client->name);
    }

    public function test_vendor_can_see_an_already_replied_review(): void
    {
        [$vendor, , $product] = $this->makeVendorWithProduct();
        $client = User::factory()->create(['role' => 'client']);

        $review = Review::query()->create([
            'product_id' => $product->id, 'user_id' => $client->id,
            'rating' => 2, 'comment' => 'Livraison en retard.',
        ]);
        $review->vendor_reply = 'Toutes nos excuses, nous améliorons nos délais.';
        $review->vendor_replied_at = now();
        $review->save();

        $response = $this->actingAs($vendor)->get(route('vendor.reviews.index'));

        $response->assertOk();
        $response->assertSee('Toutes nos excuses, nous améliorons nos délais.');
    }

    public function test_vendor_cannot_see_reviews_for_another_shops_product(): void
    {
        [$vendor] = $this->makeVendorWithProduct();
        [, , $otherProduct] = $this->makeVendorWithProduct();
        $client = User::factory()->create(['role' => 'client']);

        Review::query()->create([
            'product_id' => $otherProduct->id, 'user_id' => $client->id,
            'rating' => 5, 'comment' => 'Avis sur un produit qui ne vous appartient pas.',
        ]);

        $response = $this->actingAs($vendor)->get(route('vendor.reviews.index'));

        $response->assertOk();
        $response->assertDontSee('Avis sur un produit qui ne vous appartient pas.');
    }

    public function test_vendor_can_reply_to_a_review_on_their_product(): void
    {
        [$vendor, , $product] = $this->makeVendorWithProduct();
        $client = User::factory()->create(['role' => 'client']);

        $review = Review::query()->create([
            'product_id' => $product->id, 'user_id' => $client->id,
            'rating' => 3, 'comment' => 'Correct.',
        ]);

        $response = $this->actingAs($vendor)->post(route('vendor.reviews.reply', $review), [
            'reply' => 'Merci pour votre retour, nous restons à votre écoute.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Merci pour votre retour, nous restons à votre écoute.', $review->fresh()->vendor_reply);
        $this->assertNotNull($review->fresh()->vendor_replied_at);
    }

    public function test_vendor_cannot_reply_to_a_review_on_another_shops_product(): void
    {
        [$vendor] = $this->makeVendorWithProduct();
        [, , $otherProduct] = $this->makeVendorWithProduct();
        $client = User::factory()->create(['role' => 'client']);

        $review = Review::query()->create([
            'product_id' => $otherProduct->id, 'user_id' => $client->id,
            'rating' => 2, 'comment' => 'Pas terrible.',
        ]);

        $response = $this->actingAs($vendor)->post(route('vendor.reviews.reply', $review), [
            'reply' => 'Tentative de réponse non autorisée.',
        ]);

        $response->assertNotFound();
        $this->assertNull($review->fresh()->vendor_reply);
    }
}
