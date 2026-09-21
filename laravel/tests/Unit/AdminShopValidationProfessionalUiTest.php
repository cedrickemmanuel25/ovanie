<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminShopValidationProfessionalUiTest extends TestCase
{
    public function test_product_sidebar_sticks_as_one_group_without_overlapping_cards(): void
    {
        $css = file_get_contents(public_path('admin/css/admin_product_edit.css'));

        $this->assertStringContainsString('.product-edit-sidebar{display:grid;gap:14px;position:sticky', $css);
        $this->assertStringContainsString('.side-panel-sticky{position:static}', $css);
        $this->assertStringContainsString('max-height:calc(100vh - 112px)', $css);
        $this->assertStringContainsString('overflow-y:auto', $css);
    }

    public function test_shop_list_uses_a_dedicated_professional_french_interface(): void
    {
        $view = file_get_contents(resource_path('views/admin/shops/index.blade.php'));

        $this->assertStringContainsString('Validation des boutiques vendeurs', $view);
        $this->assertStringContainsString('Examiner le dossier', $view);
        $this->assertStringContainsString('Position GPS manquante', $view);
        $this->assertStringContainsString('Logistique incomplète', $view);
        $this->assertStringNotContainsString('ucfirst($shop->status)', $view);
        $this->assertStringNotContainsString('N/A', $view);
    }

    public function test_shop_validation_page_translates_statuses_and_secures_documents(): void
    {
        $view = file_get_contents(resource_path('views/admin/shops/show.blade.php'));

        $this->assertStringContainsString('Décision administrative', $view);
        $this->assertStringContainsString('Documents du dossier vendeur', $view);
        $this->assertStringContainsString('Approuver et activer', $view);
        $this->assertStringContainsString('Rejeter et notifier', $view);
        $this->assertStringContainsString('Non renseigné', $view);
        $this->assertStringNotContainsString('Approved', $view);
        $this->assertStringNotContainsString('N/A', $view);
        $this->assertStringNotContainsString("asset('storage/", $view);
    }

    public function test_shop_controller_preloads_relations_and_activity_counts(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminShopController.php'));

        $this->assertStringContainsString("->withCount('products')", $controller);
        $this->assertStringContainsString("->loadCount(['products', 'orders'])", $controller);
        $this->assertStringContainsString("'sellerDeliveryProfile'", $controller);
        $this->assertStringContainsString("'sellerDeliveryZones'", $controller);
    }
}
