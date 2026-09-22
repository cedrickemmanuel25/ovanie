<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande utilisateur : quand on crée une catégorie principale dans l'admin,
 * elle doit apparaître automatiquement dans la barre de navigation
 * principale, sans modification de code ni redéploiement.
 */
class NavbarCategoryLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_created_root_category_appears_in_the_main_navbar(): void
    {
        Category::create([
            'name' => 'Catégorie fraîchement créée',
            'slug' => 'categorie-fraichement-creee-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Catégorie fraîchement créée', $html);
    }

    public function test_an_inactive_category_does_not_appear_in_the_navbar(): void
    {
        Category::create([
            'name' => 'Catégorie inactive navbar',
            'slug' => 'categorie-inactive-navbar-' . uniqid(),
            'status' => 'inactif',
            'sort_order' => 0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Catégorie inactive navbar', $html);
    }

    public function test_a_subcategory_is_not_shown_directly_in_the_top_level_navbar(): void
    {
        $parent = Category::create([
            'name' => 'Catégorie parente navbar',
            'slug' => 'categorie-parente-navbar-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        Category::create([
            'name' => 'Sous-catégorie navbar unique',
            'slug' => 'sous-categorie-navbar-' . uniqid(),
            'parent_id' => $parent->id,
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Catégorie parente navbar', $html);
        $this->assertStringNotContainsString('Sous-catégorie navbar unique', $html);
    }
}
