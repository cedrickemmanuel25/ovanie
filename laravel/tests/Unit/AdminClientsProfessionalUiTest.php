<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminClientsProfessionalUiTest extends TestCase
{
    public function test_client_pages_use_dedicated_professional_views(): void
    {
        $base = dirname(__DIR__, 2);
        $index = file_get_contents($base.'/resources/views/admin/clients/index.blade.php');
        $show = file_get_contents($base.'/resources/views/admin/clients/show.blade.php');
        $edit = file_get_contents($base.'/resources/views/admin/clients/edit.blade.php');
        $css = file_get_contents($base.'/public/admin/css/admin_clients.css');

        self::assertStringContainsString('Gestion des clients', $index);
        self::assertStringContainsString('Clients acheteurs', $index);
        self::assertStringContainsString('Créés par un commercial', $index);
        self::assertStringContainsString('Commandes récentes', $show);
        self::assertStringContainsString('Traçabilité', $show);
        self::assertStringContainsString('Anonymiser et suspendre', $show);
        self::assertStringContainsString('État du compte', $edit);
        self::assertStringContainsString('.ac-client-row', $css);
        self::assertStringNotContainsString('Investissements', $show);
    }

    public function test_controller_applies_filters_and_real_activity_metrics(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Admin/AdminClientController.php');

        self::assertStringContainsString("withCount([", $controller);
        self::assertStringContainsString("withSum([", $controller);
        self::assertStringContainsString("whereHas('orders'", $controller);
        self::assertStringContainsString("created_by_commercial_id", $controller);
        self::assertStringContainsString("secondary_phone", $controller);
    }
}
