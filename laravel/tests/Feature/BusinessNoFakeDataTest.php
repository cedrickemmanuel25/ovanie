<?php

namespace Tests\Feature;

use App\Models\BusinessOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessNoFakeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_fake_data_never_appears(): void
    {
        $response = $this->get(route('catalog.business'))->assertOk();

        foreach ([
            '+ 2 500 projets',
            'Villa R+1',
            'Cocody Riviera',
            'Progression',
            '45%',
            '+ de 10 000 produits',
            'Réponse en moins de 24h',
            'BLACK FRIDAY',
            'OFFRES FLASH',
            '02 : 45 : 37',
        ] as $fakeContent) {
            $response->assertDontSee($fakeContent, false);
        }
    }

    public function test_empty_list_displays_professional_empty_state(): void
    {
        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertSee('Aucune offre professionnelle disponible')
            ->assertSee('Les offres publiées apparaîtront ici.');
    }

    public function test_no_fake_product_or_review_is_generated(): void
    {
        $response = $this->get(route('catalog.business'))->assertOk();

        foreach (['Carrelage premium', 'Ciment CPJ 45', 'Fer à béton HA12', '★★★★★', 'Ajouter au panier'] as $fakeContent) {
            $response->assertDontSee($fakeContent, false);
        }
    }

    public function test_real_backend_offer_remains_visible(): void
    {
        BusinessOffer::query()->create([
            'title' => 'Acier chantier en lot réel',
            'minimum_quantity' => 120,
            'unit' => 'barre',
            'professional_price' => 3150,
            'lead_time' => '5 jours ouvrés',
            'status' => 'published',
        ]);

        $this->get(route('catalog.business'))
            ->assertOk()
            ->assertSee('Acier chantier en lot réel')
            ->assertSee('120 barre')
            ->assertSee('3 150 FCFA / barre')
            ->assertSee('5 jours ouvrés');
    }

    public function test_no_visible_action_uses_empty_hash_link(): void
    {
        $content = $this->get(route('catalog.business'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="#"', $content);
        $this->assertStringNotContainsString("href='#'", $content);
    }
}
