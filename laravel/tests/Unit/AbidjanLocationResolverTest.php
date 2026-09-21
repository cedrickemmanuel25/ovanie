<?php

namespace Tests\Unit;

use App\Services\Geo\AbidjanLocationResolver;
use Tests\TestCase;

class AbidjanLocationResolverTest extends TestCase
{
    private AbidjanLocationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AbidjanLocationResolver::class);
    }

    public function test_it_resolves_known_quarters_without_requiring_a_quarter_delivery_zone(): void
    {
        $this->assertSame('Cocody', $this->resolver->detectCommune('Riviera Palmeraie, Abidjan'));
        $this->assertSame('Yopougon', $this->resolver->detectCommune('Niangon Nord, Abidjan'));
        $this->assertSame('Port-Bouët', $this->resolver->detectCommune('Vridi, Abidjan'));
    }

    public function test_longest_location_token_wins_over_plateau_name(): void
    {
        $this->assertSame('Cocody', $this->resolver->detectCommune('Deux Plateaux Vallon, Abidjan'));
    }


    public function test_unknown_quarter_name_is_not_mistaken_for_a_commune(): void
    {
        $this->assertNull($this->resolver->detectCommune('Plateau Dokui, Abidjan'));
    }

    public function test_commune_aliases_are_canonicalized_for_pricing(): void
    {
        $this->assertSame('attecoube', $this->resolver->communeSlug('Attecoubet'));
        $this->assertSame('port-bouet', $this->resolver->communeSlug('Port Bouët'));
        $this->assertSame('adjame', $this->resolver->communeSlug('Adjamé'));
    }

    public function test_generic_abidjan_is_kept_as_covered_zone_when_commune_is_missing(): void
    {
        $geo = $this->resolver->enrich([
            'display_name' => 'Rue sans nom, Abidjan, Côte d’Ivoire',
            'address' => [
                'neighbourhood' => 'Quartier Fictif Inexistant Absolu',
                'city' => 'Abidjan',
            ],
        ]);

        $this->assertTrue($geo['resolved_location']['is_abidjan']);
        $this->assertSame('abidjan', $geo['resolved_location']['zone']);
        $this->assertNull($geo['resolved_location']['commune']);
        $this->assertSame('abidjan_generic', $geo['resolved_location']['confidence']);
    }
}
