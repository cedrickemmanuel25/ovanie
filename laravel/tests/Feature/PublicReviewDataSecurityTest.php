<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewDataSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_response_contains_review_data_and_anonymized_author(): void
    {
        [$review] = $this->review();

        $this->getJson("/api/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Très bon produit')
            ->assertJsonPath('data.author.display_name', 'Daniel K.')
            ->assertJsonStructure(['data' => ['id', 'rating', 'comment', 'date', 'verified_purchase', 'author']]);
    }

    public function test_no_personal_or_internal_account_field_is_returned(): void
    {
        [$review] = $this->review();
        $content = $this->getJson("/api/reviews/{$review->id}")->assertOk()->getContent();

        foreach (['email', 'phone', 'telephone', 'whatsapp', 'address', 'birth_date', 'user_id',
            'role', 'status', 'loyalty_points', 'preferences', 'product_id'] as $field) {
            $this->assertStringNotContainsString('"' . $field . '"', $content);
        }
    }

    public function test_complete_user_model_is_never_serialized(): void
    {
        [$review] = $this->review();

        $this->getJson("/api/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.product')
            ->assertJsonMissingPath('data.author.email');
    }

    public function test_unknown_review_returns_not_found_without_server_error(): void
    {
        $this->getJson('/api/reviews/999999')->assertNotFound();
    }

    public function test_reviews_remain_available_for_product_pages(): void
    {
        [$review, $product] = $this->review();

        $this->getJson('/api/reviews?product_id=' . $product->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.comment', 'Très bon produit');
        $this->assertSame($review->id, $product->reviews()->firstOrFail()->id);
    }

    public function test_author_can_still_update_and_delete_own_review(): void
    {
        [$review, , $author] = $this->review();

        $this->actingAs($author)->putJson("/api/reviews/{$review->id}", [
            'rating' => 4, 'comment' => 'Avis actualisé',
        ])->assertOk()->assertJsonPath('data.rating', 4);

        $this->actingAs($author)->deleteJson("/api/reviews/{$review->id}")->assertOk();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    private function review(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $author = User::factory()->create([
            'first_name' => 'Daniel', 'last_name' => 'Kouassi', 'name' => 'Daniel Kouassi',
            'email' => uniqid() . '@private.test', 'phone' => '0700000000',
            'whatsapp_phone' => '0500000000', 'birth_date' => '1990-01-01',
            'city' => 'Cocody Riviera', 'role' => 'client', 'loyalty_points' => 900,
        ]);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique avis',
            'slug' => 'boutique-avis-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Cocody',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Avis ' . uniqid(), 'slug' => 'avis-' . uniqid(), 'status' => 'actif',
        ]);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit évalué', 'slug' => 'produit-evalue-' . uniqid(),
            'price' => 2500, 'stock' => 5, 'status' => 'approved', 'is_active' => true,
        ]);
        $review = Review::query()->create([
            'product_id' => $product->id, 'user_id' => $author->id,
            'rating' => 5, 'comment' => 'Très bon produit',
        ]);

        return [$review, $product, $author];
    }
}
