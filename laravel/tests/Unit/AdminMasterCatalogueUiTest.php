<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminMasterCatalogueUiTest extends TestCase
{
    public function test_catalogue_page_has_professional_tabs_filters_and_product_cards(): void
    {
        $view = file_get_contents(resource_path('views/admin/master-products/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Admin/MasterProductController.php'));

        $this->assertStringContainsString('Références techniques', $view);
        $this->assertStringContainsString('Produits à rattacher', $view);
        $this->assertStringContainsString('reference-filterbar', $view);
        $this->assertStringContainsString('unlinked-product-card', $view);
        $this->assertStringContainsString('source_product_id', $controller);
        $this->assertStringContainsString("paginate(12, ['*'], 'products_page')", $controller);
        $this->assertStringContainsString("paginate(15, ['*'], 'references_page')", $controller);
    }
}
