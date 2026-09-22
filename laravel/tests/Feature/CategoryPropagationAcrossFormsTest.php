<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminProductController;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Question de l'utilisateur : une catégorie/sous-catégorie créée dans
 * l'admin est-elle bien enregistrée en base et affichée sur toutes les
 * pages concernées, ou est-ce cassé ? Ce test couvre les formulaires où
 * une catégorie doit être sélectionnable : ouverture de boutique (vendeur),
 * ajout de produit (vendeur) et ajout de produit (admin).
 */
class CategoryPropagationAcrossFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_created_root_category_is_selectable_when_opening_a_shop(): void
    {
        Category::create([
            'name' => 'Catégorie boutique fraîche',
            'slug' => 'categorie-boutique-fraiche-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $vendor = User::factory()->create(['role' => 'vendor']);

        $html = $this->actingAs($vendor)->get(route('open-shop'))->assertOk()->getContent();

        $this->assertStringContainsString('Catégorie boutique fraîche', $html);
    }

    public function test_a_freshly_created_category_and_subcategory_are_selectable_when_adding_a_product_as_vendor(): void
    {
        $parent = Category::create([
            'name' => 'Catégorie produit fraîche',
            'slug' => 'categorie-produit-fraiche-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);
        Category::create([
            'name' => 'Sous-catégorie produit fraîche',
            'slug' => 'sous-categorie-produit-fraiche-' . uniqid(),
            'parent_id' => $parent->id,
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $vendor = User::factory()->create(['role' => 'vendor']);
        Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique catégorie fraîche',
            'slug' => 'boutique-categorie-fraiche-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-CATPROP-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);

        $html = $this->actingAs($vendor)->get(route('vendor.add_product', ['type' => 'single']))->assertOk()->getContent();

        $this->assertStringContainsString('Catégorie produit fraîche', $html);
        $this->assertStringContainsString('Sous-catégorie produit fraîche', $html);
    }

    public function test_a_freshly_created_category_and_subcategory_are_selectable_when_adding_a_product_as_admin(): void
    {
        $parent = Category::create([
            'name' => 'Catégorie admin fraîche',
            'slug' => 'categorie-admin-fraiche-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);
        Category::create([
            'name' => 'Sous-catégorie admin fraîche',
            'slug' => 'sous-categorie-admin-fraiche-' . uniqid(),
            'parent_id' => $parent->id,
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        // Route admin protégée par le guard "admin" (distinct du guard "web").
        // On appelle directement le contrôleur, comme AdminProductDeleteTest,
        // en partageant errors comme le ferait le middleware ShareErrorsFromSession.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = (new AdminProductController())->create()->render();

        $this->assertStringContainsString('Catégorie admin fraîche', $html);
        $this->assertStringContainsString('Sous-catégorie admin fraîche', $html);
    }
}
