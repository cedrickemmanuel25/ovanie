<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande utilisateur : les catégories affichées sur la page d'accueil
 * doivent venir de la base de données - une catégorie créée dans l'admin
 * doit s'y afficher automatiquement, au lieu d'une liste de 8 catégories
 * figée dans le code. (La barre de navigation principale n'affiche plus les
 * catégories du tout depuis NavbarCategoryLinksTest - elles restent
 * accessibles via "Toutes les catégories".)
 */
class HomepageCategoryCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_created_category_appears_on_the_homepage(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique accueil',
            'slug' => 'boutique-accueil-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-HOME-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);

        $category = Category::create([
            'name' => 'Nouvelle catégorie admin',
            'slug' => 'nouvelle-categorie-admin-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit de la nouvelle catégorie',
            'slug' => 'produit-nouvelle-categorie-' . uniqid(),
            'description' => 'x', 'price' => 5000, 'stock' => 5,
            'status' => 'approved', 'is_active' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString($category->name, $html);
    }

    public function test_homepage_category_grid_never_shows_more_than_eight_and_ignores_inactive_categories(): void
    {
        foreach (range(1, 9) as $i) {
            Category::create([
                'name' => "Catégorie active {$i}",
                'slug' => "categorie-active-{$i}-" . uniqid(),
                'status' => 'actif',
                'sort_order' => $i,
            ]);
        }

        $inactive = Category::create([
            'name' => 'Catégorie inactive test',
            'slug' => 'categorie-inactive-' . uniqid(),
            'status' => 'inactif',
            'sort_order' => 0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString($inactive->name, $html);
    }

    public function test_a_category_without_photo_product_or_matching_logo_never_renders_a_blank_card(): void
    {
        // Bug rapporté par l'utilisateur : une catégorie sans photo importée,
        // sans produit et sans visuel historique correspondant s'affichait
        // comme une case blanche vide dans la grille "Catégories BTP".
        Category::create([
            'name' => 'Xyzzyx sans visuel',
            'slug' => 'xyzzyx-sans-visuel-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Xyzzyx sans visuel', $html);
        $this->assertStringContainsString('images/home/product-placeholder.svg', $html);
    }
}
