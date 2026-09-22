<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Services\AiProductCategorySuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le vendeur ou le commercial ne devrait plus avoir à choisir la catégorie/
 * sous-catégorie à la main une fois le nom du produit saisi : le système la
 * devine via l'IA (jamais une catégorie inventée - toujours choisie parmi
 * celles qui existent réellement), tout en restant modifiable si la
 * suggestion se trompe.
 */
class AiProductCategorySuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCatalog(): array
    {
        $gros = Category::create(['name' => 'Matériaux gros œuvre', 'slug' => 'gros-oeuvre-' . uniqid(), 'status' => 'actif']);
        $ciment = Category::create(['name' => 'Ciment', 'slug' => 'ciment-' . uniqid(), 'status' => 'actif', 'parent_id' => $gros->id]);
        $elec = Category::create(['name' => 'Électricité', 'slug' => 'electricite-' . uniqid(), 'status' => 'actif']);

        return [$gros, $ciment, $elec];
    }

    public function test_suggestion_is_null_when_the_feature_is_disabled(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => false]);
        config(['services.openai.key' => 'test-key']);
        [$gros, $ciment] = $this->makeCatalog();

        $suggester = app(AiProductCategorySuggester::class);
        $this->assertFalse($suggester->isEnabled());
        $this->assertNull($suggester->suggest('Sac de ciment 50kg'));
    }

    public function test_suggestion_is_null_when_no_openai_key_is_configured(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => null]);

        $suggester = app(AiProductCategorySuggester::class);
        $this->assertFalse($suggester->isEnabled());
        $this->assertNull($suggester->suggest('Sac de ciment 50kg'));
    }

    public function test_a_valid_subcategory_suggestion_from_openai_is_accepted(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => 'test-key']);
        [$gros, $ciment] = $this->makeCatalog();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category_id' => $ciment->id])]],
                ],
            ], 200),
        ]);

        $suggester = app(AiProductCategorySuggester::class);
        $result = $suggester->suggest('Sac de ciment CPA 42.5 - 50 kg');

        $this->assertSame(['category_id' => $gros->id, 'subcategory_id' => $ciment->id], $result);
    }

    public function test_a_root_category_suggestion_has_no_subcategory(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => 'test-key']);
        [$gros, $ciment, $elec] = $this->makeCatalog();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category_id' => $elec->id])]],
                ],
            ], 200),
        ]);

        $suggester = app(AiProductCategorySuggester::class);
        $result = $suggester->suggest('Disjoncteur 20A');

        $this->assertSame(['category_id' => $elec->id, 'subcategory_id' => null], $result);
    }

    public function test_an_id_outside_the_real_catalog_is_rejected_rather_than_trusted(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => 'test-key']);
        $this->makeCatalog();

        Http::fake([
            // L'IA "invente" un identifiant qui n'existe pas dans la base.
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category_id' => 999999])]],
                ],
            ], 200),
        ]);

        $suggester = app(AiProductCategorySuggester::class);
        $this->assertNull($suggester->suggest('Produit inhabituel'));
    }

    public function test_an_openai_failure_degrades_gracefully_to_no_suggestion(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => 'test-key']);
        $this->makeCatalog();

        Http::fake(['api.openai.com/*' => Http::response('Service unavailable', 500)]);

        $suggester = app(AiProductCategorySuggester::class);
        $this->assertNull($suggester->suggest('Sac de ciment 50kg'));
    }

    public function test_the_vendor_endpoint_returns_the_suggestion_as_json(): void
    {
        config(['product-categorization.ai_suggestion_enabled' => true]);
        config(['services.openai.key' => 'test-key']);
        [$gros, $ciment] = $this->makeCatalog();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['category_id' => $ciment->id])]],
                ],
            ], 200),
        ]);

        $vendor = User::factory()->create(['role' => 'vendor']);
        Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique suggestion',
            'slug' => 'boutique-suggestion-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'landmark' => 'Pharmacie du carrefour',
            'latitude' => 5.35, 'longitude' => -4.01, 'geo_status' => 'verified',
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);

        $this->actingAs($vendor)
            ->postJson(route('vendor.products.suggestCategory'), ['name' => 'Sac de ciment 50kg'])
            ->assertOk()
            ->assertJson(['suggestion' => ['category_id' => $gros->id, 'subcategory_id' => $ciment->id]]);
    }
}
