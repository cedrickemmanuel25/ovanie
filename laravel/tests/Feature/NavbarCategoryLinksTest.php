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
 * pas encore. Puis l'utilisateur a signalé un espace vide entre ces liens et
 * le bouton "Vendre sur OVANIE" sur son écran (large) : on a complété avec
 * d'autres liens déjà existants sur le site (Cartes cadeaux, Suivi de
 * commande, Centre d'aide, FAQ, Qui sommes-nous) pour remplir la ligne à
 * 1920px. En dessous d'environ 1500px de large, la ligne défile
 * horizontalement plutôt que de tout faire tenir (déjà le cas avant, via
 * overflow-x: auto) - remplir un grand écran sans rien couper sur un plus
 * petit n'est pas possible avec un seul jeu de liens de taille fixe. La
 * navigation a ensuite été allégée : Centre d’aide, FAQ et Qui sommes-nous
 * restent accessibles ailleurs sur le site mais ne doivent plus encombrer la
 * navbar ni coincer le bouton "Vendre sur OVANIE".
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
        $this->assertStringContainsString('Cartes cadeaux', $navHtml);
        $this->assertStringContainsString('Suivi de commande', $navHtml);
        $this->assertStringNotContainsString('Centre d’aide', $navHtml);
        $this->assertStringNotContainsString('>FAQ<', $navHtml);
        $this->assertStringNotContainsString('Qui sommes-nous', $navHtml);
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
