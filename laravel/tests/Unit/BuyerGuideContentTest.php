<?php

namespace Tests\Unit;

use Tests\TestCase;

class BuyerGuideContentTest extends TestCase
{
    public function test_buyer_guide_contains_no_unverified_volume_metrics(): void
    {
        $view = file_get_contents(resource_path('views/layouts/_navbar.blade.php'));

        foreach (['10 000+', '1 200+', '35 000+', '25 000+'] as $metric) {
            $this->assertStringNotContainsString($metric, $view);
        }

        foreach (['Produits', 'Boutiques', 'Commandes', 'Clients', '98%', 'Taux de satisfaction'] as $label) {
            $this->assertStringContainsString($label, $view);
        }
    }

    public function test_contact_strip_keeps_commercial_left_brand_center_and_assistance_right(): void
    {
        $view = file_get_contents(resource_path('views/layouts/_navbar.blade.php'));

        $commercial = strpos($view, '>Service client & commercial<');
        $brand = strpos($view, '>Faites des profits avec nous<');
        $assistance = strpos($view, '>Assistance</div>', $brand ?: 0);

        $this->assertNotFalse($commercial);
        $this->assertNotFalse($brand);
        $this->assertNotFalse($assistance);
        $this->assertLessThan($brand, $commercial);
        $this->assertLessThan($assistance, $brand);
        $this->assertStringNotContainsString('Service Client 7j/7', $view);
        $this->assertStringNotContainsString('Service Commercial 6j/7', $view);
        $this->assertStringContainsString('0161780101 / 0161780000', $view);
        $this->assertSame(4, substr_count($view, 'buyer-modal-stat__value">+ de'));
        $this->assertStringContainsString('white-space: nowrap;', $view);
    }
}
