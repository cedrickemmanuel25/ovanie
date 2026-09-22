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

    public function test_the_static_gift_card_link_no_longer_duplicates_a_real_category(): void
    {
        // Bug rapporté par l'utilisateur : un lien statique "Cartes OVANIE"
        // et une vraie catégorie "Cartes & Bons OVANIE" apparaissaient tous
        // les deux dans la barre, l'un juste après l'autre - la barre ne
        // doit plus contenir ce lien statique en plus des vraies catégories.
        Category::create([
            'name' => 'Cartes & Bons OVANIE',
            'slug' => 'cartes-et-bons-ovanie-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $navHtml = $this->extractPrimaryNavHtml($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Cartes &amp; Bons OVANIE', $navHtml);
        $this->assertSame(1, substr_count($navHtml, 'Cartes'));
    }

    public function test_only_4_dynamic_categories_show_in_the_primary_nav(): void
    {
        // Vérifié avec un rendu réel de la barre (voir l'historique de ce
        // fichier) : au-delà de 4 catégories affichées, le bouton IA et le
        // menu "Aide & infos" débordent déjà sur un écran de portable
        // 1280px. Le menu "Toutes les catégories" liste toujours tout.
        foreach (range(1, 6) as $i) {
            Category::create([
                'name' => "Catégorie limite {$i}",
                'slug' => "categorie-limite-{$i}-" . uniqid(),
                'status' => 'actif',
                'sort_order' => $i,
            ]);
        }

        $navHtml = $this->extractPrimaryNavHtml($this->get('/')->assertOk()->getContent());

        $shown = 0;
        foreach (range(1, 6) as $i) {
            if (str_contains($navHtml, "Catégorie limite {$i}")) {
                $shown++;
            }
        }

        $this->assertSame(4, $shown);
    }

    private function extractPrimaryNavHtml(string $html): string
    {
        $start = strpos($html, '<nav class="ovn-primary-nav"');
        $end = strpos($html, '</nav>', $start) + strlen('</nav>');

        return substr($html, $start, $end - $start);
    }
}
