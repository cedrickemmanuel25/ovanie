<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OvanieProCatalogCanonicalTest extends TestCase
{
    public function test_main_catalog_keeps_ovanie_domain_route(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('https://ovanie.com/catalog', 'GET')
        );

        $this->assertSame('catalog.index', $route->getName());
    }

    public function test_pro_catalog_redirects_permanently_to_main_catalog(): void
    {
        $response = $this->get('https://pro.ovanie.com/catalog?sort=popular&search=ciment');

        $response->assertStatus(301);
        $response->assertRedirect('https://ovanie.com/catalog?sort=popular&search=ciment');
    }

    public function test_pro_catalog_category_redirect_preserves_category_and_query_string(): void
    {
        $response = $this->get('https://pro.ovanie.com/catalog/materiaux-gros-oeuvres?sort=recent');

        $response->assertStatus(301);
        $response->assertRedirect('https://ovanie.com/catalog/materiaux-gros-oeuvres?sort=recent');
    }
}
