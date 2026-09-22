<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug rapporté par l'utilisateur : un produit marqué "négociable" par le
 * vendeur (is_negotiable + price_p1/p2/p3) n'affichait aucune indication sur
 * la fiche produit publique, et le client n'avait aucun moyen de proposer un
 * prix - alors que NegotiationController::store() et
 * CartController::addNegotiatedToCart() existaient déjà côté serveur.
 */
class ProductNegotiationUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(bool $negotiable): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique négociation',
            'slug' => 'boutique-negociation-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-NEG-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);
        $category = Category::create([
            'name' => 'Négociation', 'slug' => 'negociation-' . uniqid(), 'status' => 'actif',
        ]);

        return Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit négociable', 'slug' => 'produit-negociable-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
            'is_negotiable' => $negotiable,
            'price_p1' => $negotiable ? 9500 : null,
            'price_p2' => $negotiable ? 9000 : null,
            'price_p3' => $negotiable ? 8500 : null,
        ]);
    }

    public function test_a_negotiable_product_shows_the_negotiation_ui(): void
    {
        $product = $this->makeProduct(negotiable: true);

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('ov-negotiable-badge', $html);
        $this->assertStringContainsString('data-negotiate-box', $html);
        $this->assertStringContainsString('data-negotiate-trigger', $html);
        $this->assertStringContainsString('data-is-negotiable="1"', $html);

        // "Demander un devis" est remplacé par le bouton "Négocier" (retour
        // utilisateur : ce n'était pas assez professionnel).
        $this->assertStringNotContainsString('Demander un devis', $html);

        // Les seuils vendeur ne doivent jamais fuiter dans le HTML public.
        $this->assertStringNotContainsString('9500', $html);
        $this->assertStringNotContainsString('9000', $html);
        $this->assertStringNotContainsString('8500', $html);
    }

    public function test_a_fixed_price_product_shows_no_negotiation_ui(): void
    {
        $product = $this->makeProduct(negotiable: false);

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('ov-negotiable-badge', $html);
        $this->assertStringNotContainsString('data-negotiate-box', $html);
        $this->assertStringNotContainsString('data-negotiate-trigger', $html);
        $this->assertStringNotContainsString('Demander un devis', $html);
        $this->assertStringContainsString('data-is-negotiable="0"', $html);
    }

    public function test_the_json_product_detail_exposes_is_negotiable_but_never_the_thresholds(): void
    {
        $product = $this->makeProduct(negotiable: true);

        $response = $this->getJson(route('product.show', $product->slug));

        $response->assertOk();
        $response->assertJsonPath('data.is_negotiable', true);
        $response->assertJsonMissingPath('data.price_p1');
        $response->assertJsonMissingPath('data.price_p2');
        $response->assertJsonMissingPath('data.price_p3');
    }
}
