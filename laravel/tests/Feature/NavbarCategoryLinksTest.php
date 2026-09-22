<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demande utilisateur : la barre de navigation principale débordait dès
 * qu'il y avait trop de catégories dynamiques à côté du bouton IA, du menu
 * "Aide & infos" et du bouton "Vendre sur OVANIE" (mesuré avec un rendu réel
 * de la barre : au-delà de 4 catégories, ça dépasse déjà un écran de
 * portable 1280px). L'utilisateur a choisi de retirer les catégories de
 * cette barre - elles restent listées en entier via "Toutes les
 * catégories" - et d'y afficher à la place des liens qui n'y apparaissaient
 * pas encore : Comment acheter, Conditions vendeurs, OVANIE Pro,
 * Partenaires & Fournisseurs et Garantie acheteur.
 */
class NavbarCategoryLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_primary_nav_shows_the_requested_links_instead_of_categories(): void
    {
        Category::create([
            'name' => 'Catégorie qui ne doit plus apparaître ici',
            'slug' => 'categorie-absente-navbar-' . uniqid(),
            'status' => 'actif',
            'sort_order' => 0,
        ]);

        $navHtml = $this->extractPrimaryNavHtml($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Comment acheter', $navHtml);
        $this->assertStringContainsString('Conditions vendeurs', $navHtml);
        $this->assertStringContainsString('OVANIE Pro', $navHtml);
        $this->assertStringContainsString('Partenaires &amp; Fournisseurs', $navHtml);
        $this->assertStringContainsString('Garantie acheteur', $navHtml);
        $this->assertStringNotContainsString('Catégorie qui ne doit plus apparaître ici', $navHtml);
    }

    public function test_the_ai_trigger_still_comes_first_in_the_primary_nav(): void
    {
        $navHtml = $this->extractPrimaryNavHtml(
            $this->actingAs(\App\Models\User::factory()->create(['role' => 'client']))
                ->get('/')->assertOk()->getContent()
        );

        $this->assertLessThan(
            strpos($navHtml, 'Comment acheter'),
            strpos($navHtml, 'ovn-ai-link')
        );
    }

    private function extractPrimaryNavHtml(string $html): string
    {
        $start = strpos($html, '<nav class="ovn-primary-nav"');
        $end = strpos($html, '</nav>', $start) + strlen('</nav>');

        return substr($html, $start, $end - $start);
    }
}
