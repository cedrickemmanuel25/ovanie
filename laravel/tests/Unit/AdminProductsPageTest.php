<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminProductsPageTest extends TestCase
{
    public function test_products_view_uses_products_and_not_vendor_payouts(): void
    {
        $view = file_get_contents(__DIR__ . '/../../resources/views/admin/products/index.blade.php');

        $this->assertStringContainsString('@forelse($products as $product)', $view);
        $this->assertStringContainsString('Gestion des produits', $view);
        $this->assertStringContainsString('Logistique incomplète', $view);
        $this->assertStringNotContainsString('$payouts', $view);
        $this->assertStringNotContainsString('Reversements vendeurs', $view);
    }

    public function test_products_controller_provides_the_expected_view_data(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/AdminProductController.php');

        $this->assertStringContainsString("'products',", $controller);
        $this->assertStringContainsString("'categories',", $controller);
        $this->assertStringContainsString("'shops',", $controller);
        $this->assertStringContainsString("'summary'", $controller);
        $this->assertStringContainsString('countProductsWithIncompleteLogistics', $controller);
    }

    public function test_products_page_has_a_dedicated_responsive_stylesheet(): void
    {
        $css = file_get_contents(__DIR__ . '/../../public/admin/css/admin_products.css');

        $this->assertStringContainsString('.admin-products-page', $css);
        $this->assertStringContainsString('.products-summary', $css);
        $this->assertStringContainsString('.product-row', $css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
    }
}
