<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminCommissionsProfessionalUiTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_commission_controller_loads_real_records_filters_and_summary(): void
    {
        $controller = file_get_contents($this->root . '/app/Http/Controllers/Admin/AdminCommissionController.php');

        self::assertStringContainsString('Commission::query()', $controller);
        self::assertStringContainsString("->paginate(20)", $controller);
        self::assertStringContainsString("'pending_amount'", $controller);
        self::assertStringContainsString("'paid_amount'", $controller);
        self::assertStringContainsString('applyFilters', $controller);
    }

    public function test_commission_page_is_server_rendered_and_fully_french(): void
    {
        $view = file_get_contents($this->root . '/resources/views/admin/commissions/index.blade.php');

        self::assertStringContainsString('Suivi des commissions OVANIE', $view);
        self::assertStringContainsString('Affiner l’historique', $view);
        self::assertStringContainsString('Aucune commission enregistrée', $view);
        self::assertStringContainsString('@foreach($commissions as $commission)', $view);
        self::assertStringNotContainsString('commissionTable', $view);
        self::assertStringNotContainsString('admin_commissions.js', $view);
    }

    public function test_commission_styles_include_responsive_cards_without_horizontal_table_scroll(): void
    {
        $css = file_get_contents($this->root . '/public/admin/css/admin_commissions.css');

        self::assertStringContainsString('.comm-stats', $css);
        self::assertStringContainsString('.comm-filter-form', $css);
        self::assertStringContainsString('.comm-empty-state', $css);
        self::assertStringContainsString('@media (max-width: 1100px)', $css);
        self::assertStringContainsString('content: attr(data-label)', $css);
    }
}
