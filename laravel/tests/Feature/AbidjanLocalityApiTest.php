<?php

namespace Tests\Feature;

use Tests\TestCase;

class AbidjanLocalityApiTest extends TestCase
{
    public function test_public_communes_endpoint_returns_the_registry(): void
    {
        $this->getJson('/geo/abidjan/communes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(13, 'communes');
    }

    public function test_quarters_endpoint_filters_by_commune_and_search(): void
    {
        $this->getJson('/geo/abidjan/quartiers?commune=Cocody&q=angre')
            ->assertOk()
            ->assertJsonPath('commune', 'Cocody')
            ->assertJsonStructure(['localities', 'quarters'])
            ->assertJsonFragment(['name' => 'Angré']);
    }

    public function test_locality_endpoint_accepts_aliases_without_accents(): void
    {
        $this->getJson('/geo/abidjan/quartiers?commune=Abobo&q=pk18')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'PK 18',
                'type_label' => 'Quartier',
            ]);
    }
}
