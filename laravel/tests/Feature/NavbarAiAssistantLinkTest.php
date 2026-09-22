<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande utilisateur : ajouter un accès "IA" dans la navbar, juste avant
 * "Matériaux", visible uniquement pour un client connecté (comme Rufus
 * chez Amazon), qui ouvre un panneau coulissant depuis la gauche (comme
 * "Alexa for shopping" chez Amazon) plutôt qu'une page dédiée. Le site
 * dispose déjà d'un assistant IA complet (SupportAiOrchestrator) : ce
 * panneau réutilise ses mêmes endpoints /api/support/chat/*, sans dupliquer
 * cette logique.
 */
class NavbarAiAssistantLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeProductPageUrl(): string
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique navbar-ia',
            'slug' => 'boutique-navbar-ia-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-NAVIA-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);
        $category = Category::create(['name' => 'Navbar IA', 'slug' => 'navbar-ia-' . uniqid(), 'status' => 'actif']);
        $product = Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit navbar IA', 'slug' => 'produit-navbar-ia-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
        ]);

        return route('product.show', $product->slug);
    }

    public function test_a_guest_never_sees_the_ai_trigger_or_panel(): void
    {
        $html = $this->get($this->makeProductPageUrl())->getContent();

        $this->assertStringNotContainsString('ovn-ai-link', $html);
        $this->assertStringNotContainsString('data-ai-panel-trigger', $html);
        $this->assertStringNotContainsString('ovaiPanel', $html);
    }

    public function test_a_logged_in_client_sees_the_ai_trigger_right_before_materiaux(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $html = $this->actingAs($client)->get($this->makeProductPageUrl())->getContent();

        $this->assertStringContainsString('ovn-ai-link', $html);
        $this->assertStringContainsString('data-ai-panel-trigger', $html);

        // Le panneau (avatar N'Nan + le bon nom) doit être rendu sur la page.
        $this->assertStringContainsString('ovaiPanel', $html);
        $this->assertStringContainsString('images/ai-assistant/n-nan.webp', $html);
        $this->assertStringContainsString('N’Nan', $html);

        // Le déclencheur "IA" doit précéder "Matériaux" dans le HTML rendu.
        $aiPosition = strpos($html, 'ovn-ai-link');
        $materiauxPosition = strpos($html, 'Matériaux');
        $this->assertNotFalse($aiPosition);
        $this->assertNotFalse($materiauxPosition);
        $this->assertLessThan($materiauxPosition, $aiPosition);
    }

    public function test_visiting_the_old_assistance_url_redirects_to_home_with_the_panel_open(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->get('/assistance')
            ->assertRedirect(route('home', ['ai' => 1]));
    }
}
