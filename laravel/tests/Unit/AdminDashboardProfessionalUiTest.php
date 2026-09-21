<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminDashboardProfessionalUiTest extends TestCase
{
    public function test_dashboard_uses_a_professional_operational_structure(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString('admin/css/admin_dashboard.css', $view);
        $this->assertStringContainsString('Actions requises', $view);
        $this->assertStringContainsString('Évolution des ventes', $view);
        $this->assertStringContainsString('Activité commerciale', $view);
        $this->assertStringContainsString('Boutiques à traiter', $view);
        $this->assertStringContainsString('Répartition financière', $view);
    }

    public function test_dashboard_translates_operational_statuses(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));

        $this->assertStringContainsString("'pending', 'en_attente', 'waiting' => 'En attente'", $view);
        $this->assertStringContainsString("'cash_on_delivery', 'cod' => 'Paiement à la livraison'", $view);
        $this->assertStringContainsString("'paydunya', 'online', 'card' => 'Paiement en ligne'", $view);
    }

    public function test_dashboard_service_provides_real_operational_data(): void
    {
        $service = file_get_contents(app_path('Services/AdminDashboardService.php'));

        foreach ([
            'salesTrend',
            'actionsRequired',
            'shopsNeedingAttention',
            'commercialActivity',
            'shopsMissingGpsCount',
        ] as $method) {
            $this->assertStringContainsString('function ' . $method, $service);
        }

        $this->assertStringContainsString('created_by_commercial_id', $service);
        $this->assertStringContainsString('managed_by_commercial_id', $service);
        $this->assertStringNotContainsString('fake', strtolower($service));
    }

    public function test_dashboard_forces_french_for_dynamic_dates(): void
    {
        $view = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $service = file_get_contents(app_path('Services/AdminDashboardService.php'));

        $this->assertStringContainsString("locale('fr')->translatedFormat('F Y')", $view);
        $this->assertStringContainsString("locale('fr')->diffForHumans()", $view);
        $this->assertStringContainsString("locale('fr')->translatedFormat('d M')", $service);
        $this->assertStringNotContainsString("now()->translatedFormat('F Y')", $view);
    }
}
