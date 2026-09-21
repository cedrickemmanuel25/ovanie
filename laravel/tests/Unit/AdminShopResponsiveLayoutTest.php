<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminShopResponsiveLayoutTest extends TestCase
{
    #[Test]
    public function shop_validation_decision_area_uses_the_professional_action_layout(): void
    {
        $view = file_get_contents(resource_path('views/admin/shops/show.blade.php'));

        $this->assertStringContainsString('decision-status-bar', $view);
        $this->assertStringContainsString('decision-actions-list', $view);
        $this->assertStringContainsString('Demander des corrections', $view);
        $this->assertStringContainsString('Motif du rejet', $view);
        $this->assertStringContainsString('Retour aux boutiques', $view);
        $this->assertStringContainsString('Télécharger le dossier', $view);
    }

    #[Test]
    public function shop_list_filters_and_cards_are_structured_without_horizontal_overflow(): void
    {
        $view = file_get_contents(resource_path('views/admin/shops/index.blade.php'));
        $css = file_get_contents(public_path('admin/css/admin_shops.css'));

        $this->assertStringContainsString('shops-filter-main', $view);
        $this->assertStringContainsString('shops-filter-options', $view);
        $this->assertStringContainsString('shops-filter-actions', $view);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
    }

    #[Test]
    public function shop_detail_sidebar_uses_page_scrolling_instead_of_an_internal_scrollbar(): void
    {
        $css = file_get_contents(public_path('admin/css/admin_shops.css'));

        $this->assertStringContainsString('.shop-validation-sidebar {', $css);
        $this->assertStringContainsString('max-height: none', $css);
        $this->assertStringContainsString('overflow: visible', $css);
    }
}
