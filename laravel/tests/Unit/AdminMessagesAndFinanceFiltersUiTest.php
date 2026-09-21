<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminMessagesAndFinanceFiltersUiTest extends TestCase
{
    public function test_finance_filter_forms_are_constrained_inside_their_cards(): void
    {
        $root = dirname(__DIR__, 2);
        $commissionCss = file_get_contents($root . '/public/admin/css/admin_commissions.css');
        $payoutCss = file_get_contents($root . '/public/admin/css/admin_payouts.css');

        self::assertStringContainsString('grid-template-columns: repeat(12, minmax(0, 1fr))', $commissionCss);
        self::assertStringContainsString('.comm-filter-form > .comm-search-button', $commissionCss);
        self::assertStringContainsString('grid-template-columns: repeat(12, minmax(0, 1fr))', $payoutCss);
        self::assertStringContainsString('.pay-filter-form > .pay-search-button', $payoutCss);
    }

    public function test_messages_page_uses_a_dedicated_professional_interface(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/Admin/SubmissionController.php');
        $view = file_get_contents($root . '/resources/views/admin/submissions/index.blade.php');
        $css = file_get_contents($root . '/public/admin/css/admin_messages.css');

        self::assertStringContainsString("paginate(12)", $controller);
        self::assertStringContainsString('->distinct()', $controller);
        self::assertStringContainsString('Messages reçus depuis le site', $view);
        self::assertStringContainsString('Retrouver un message', $view);
        self::assertStringContainsString('Répondre par e-mail', $view);
        self::assertStringContainsString('.msg-card', $css);
        self::assertStringNotContainsString('background-color: #007bff', $view);
    }
}
