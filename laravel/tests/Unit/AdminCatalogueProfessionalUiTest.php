<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminCatalogueProfessionalUiTest extends TestCase
{
    public function test_admin_catalogue_pages_use_professional_french_views(): void
    {
        $products = file_get_contents(resource_path('views/admin/products/index.blade.php'));
        $create = file_get_contents(resource_path('views/admin/products/create.blade.php'));
        $masters = file_get_contents(resource_path('views/admin/master-products/index.blade.php'));
        $pagination = file_get_contents(resource_path('views/admin/partials/pagination-fr.blade.php'));

        $this->assertStringContainsString("pagination-fr", $products);
        $this->assertStringContainsString('Affichage de', $pagination);
        $this->assertStringContainsString('Précédent', $pagination);
        $this->assertStringContainsString('Suivant', $pagination);
        $this->assertStringContainsString('Poids par unité en kg', $create);
        $this->assertStringContainsString('Catalogue de références OVANIE', $masters);
        $this->assertStringNotContainsString('Showing', $pagination);
        $this->assertStringNotContainsString('Creer un produit maitre', $masters);
    }
}
