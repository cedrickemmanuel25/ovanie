<?php

namespace Tests\Feature;

use App\Models\BusinessOffer;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCatalogSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_ovanie_product_does_not_automatically_appear_in_business_catalog(): void
    {
        $this->classicProduct('Produit OVANIE classique uniquement');

        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertDontSee('Produit OVANIE classique uniquement');
    }

    public function test_published_business_offer_appears(): void
    {
        BusinessOffer::query()->create([
            'title' => 'Ciment professionnel par palette',
            'minimum_quantity' => 50,
            'unit' => 'sac',
            'professional_price' => 7200,
            'lead_time' => '3 jours ouvrés',
            'status' => 'published',
        ]);

        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertSee('Ciment professionnel par palette')
            ->assertSee('3 jours ouvrés');
    }

    public function test_draft_or_disabled_offer_does_not_appear(): void
    {
        foreach (['draft' => 'Offre brouillon privée', 'disabled' => 'Offre désactivée privée'] as $status => $title) {
            BusinessOffer::query()->create([
                'title' => $title,
                'minimum_quantity' => 10,
                'unit' => 'palette',
                'professional_price' => 100000,
                'lead_time' => '7 jours',
                'status' => $status,
            ]);
        }

        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertDontSee('Offre brouillon privée')
            ->assertDontSee('Offre désactivée privée');
    }

    public function test_empty_catalog_displays_professional_empty_state_without_fake_products(): void
    {
        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertSee('Aucune offre professionnelle disponible')
            ->assertDontSee('Carrelage premium')
            ->assertDontSee('Ciment CPJ 45');
    }

    public function test_classic_ovanie_catalog_continues_to_work(): void
    {
        $this->classicProduct('Produit catalogue OVANIE actif');

        $this->get(route('catalog.index'))->assertOk();
        $this->assertDatabaseHas('products', ['name' => 'Produit catalogue OVANIE actif']);
    }

    private function classicProduct(string $name): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Boutique classique',
            'slug' => 'boutique-classique-' . uniqid(),
            'seller_type' => 'particulier',
            'city' => 'Abidjan',
            'commune' => 'Cocody',
            'address' => 'Cocody',
            'identity_type' => 'cni',
            'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Catégorie classique ' . uniqid(),
            'slug' => 'categorie-classique-' . uniqid(),
            'status' => 'actif',
        ]);

        return Product::query()->create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'produit-classique-' . uniqid(),
            'price' => 10000,
            'stock' => 10,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }
}
