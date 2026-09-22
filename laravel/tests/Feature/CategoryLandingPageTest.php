<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande utilisateur : chaque catégorie doit avoir sa propre page à sa
 * propre URL, avec la photo importée dans l'admin affichée dans la bannière
 * - pas seulement les 7 catégories historiques dont le texte/l'image était
 * codé en dur dans routes/web.php, ProductController::categoryPage() et
 * catalog/index.blade.php.
 */
class CategoryLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_created_category_gets_its_own_dedicated_page(): void
    {
        $parent = Category::create([
            'name' => 'Quincaillerie industrielle',
            'slug' => 'quincaillerie-industrielle',
            'description' => 'Vis, boulons, chevilles et fixations pour tous vos chantiers.',
            'status' => 'actif',
        ]);
        Category::create([
            'name' => 'Vis et boulons',
            'slug' => 'vis-et-boulons',
            'parent_id' => $parent->id,
            'status' => 'actif',
        ]);

        $html = $this->get(route('categories.show', $parent->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Quincaillerie industrielle', $html);
        $this->assertStringContainsString('Vis, boulons, chevilles et fixations pour tous vos chantiers.', $html);
        $this->assertStringContainsString('Vis et boulons', $html);
    }

    public function test_the_uploaded_category_photo_shows_in_the_page_banner(): void
    {
        $category = Category::create([
            'name' => 'Sanitaires premium',
            'slug' => 'sanitaires-premium',
            'image_path' => 'categories/sanitaires-premium.webp',
            'status' => 'actif',
        ]);

        $html = $this->get(route('categories.show', $category->slug))->assertOk()->getContent();

        $this->assertStringContainsString('storage/categories/sanitaires-premium.webp', $html);
    }

    public function test_an_inactive_category_has_no_public_page(): void
    {
        $category = Category::create([
            'name' => 'Catégorie désactivée',
            'slug' => 'categorie-desactivee',
            'status' => 'inactif',
        ]);

        $this->get(route('categories.show', $category->slug))->assertNotFound();
    }

    public function test_an_unknown_slug_still_returns_404(): void
    {
        $this->get(route('categories.show', 'ceci-n-existe-pas'))->assertNotFound();
    }

    public function test_the_7_legacy_category_pages_keep_working_unchanged(): void
    {
        $this->get(route('categories.show', 'electricite-plomberie'))
            ->assertOk()
            ->assertSee('Électricité &amp; Plomberie', false);
    }
}
