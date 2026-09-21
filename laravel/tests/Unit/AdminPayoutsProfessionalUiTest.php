<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminPayoutsProfessionalUiTest extends TestCase
{
    public function test_payout_controller_supports_filters_summary_and_pagination(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/Admin/VendorPayoutController.php');

        $this->assertStringContainsString("'q' =>", $controller);
        $this->assertStringContainsString("'shop_id' =>", $controller);
        $this->assertStringContainsString("'status' =>", $controller);
        $this->assertStringContainsString("'ready_amount'", $controller);
        $this->assertStringContainsString('paginate(12)', $controller);
    }

    public function test_payout_view_uses_professional_cards_and_french_labels(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/resources/views/admin/payouts/index.blade.php');

        $this->assertStringContainsString('Pilotez les reversements aux boutiques', $view);
        $this->assertStringContainsString('Total net vendeur', $view);
        $this->assertStringContainsString('Prêts à payer', $view);
        $this->assertStringContainsString('Gérer le reversement', $view);
        $this->assertStringContainsString('Marquer en échec', $view);
        $this->assertStringNotContainsString('class="table', $view);
        $this->assertStringNotContainsString('Paye</button>', $view);
    }

    public function test_payout_styles_prevent_horizontal_table_overflow(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root . '/public/admin/css/admin_payouts.css');

        $this->assertStringContainsString('.pay-payout-card', $css);
        $this->assertStringContainsString('.pay-financial-grid', $css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
        $this->assertStringNotContainsString('table-responsive', $css);
    }
}
