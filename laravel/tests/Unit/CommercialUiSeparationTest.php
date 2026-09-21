<?php

namespace Tests\Unit;

use Tests\TestCase;

class CommercialUiSeparationTest extends TestCase
{
    public function test_client_and_vendor_forms_are_physically_separated(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialAccountController.php')
        );

        $clientForm = file_get_contents(
            resource_path('views/commercial/clients/create.blade.php')
        );

        $vendorForm = file_get_contents(
            resource_path('views/commercial/vendors/create.blade.php')
        );

        $this->assertStringContainsString("view('commercial.clients.create'", $controller);
        $this->assertStringContainsString("view('commercial.vendors.create'", $controller);
        $this->assertStringContainsString("route('commercial.clients.store')", $clientForm);
        $this->assertStringContainsString("route('commercial.vendors.store')", $vendorForm);

        $this->assertStringNotContainsString('shopName', $clientForm);
        $this->assertStringNotContainsString('identityNumber', $clientForm);
        $this->assertStringNotContainsString('main_category', $clientForm);

        $this->assertStringContainsString('shopName', $vendorForm);
        $this->assertStringContainsString('identityNumber', $vendorForm);
        $this->assertStringContainsString('main_category', $vendorForm);
    }

    public function test_commercial_does_not_accept_terms_on_behalf_of_users(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialAccountController.php')
        );

        $clientForm = file_get_contents(
            resource_path('views/commercial/clients/create.blade.php')
        );

        $vendorForm = file_get_contents(
            resource_path('views/commercial/vendors/create.blade.php')
        );

        $this->assertStringNotContainsString("'terms' => ['accepted']", $controller);
        $this->assertStringNotContainsString('name="terms"', $clientForm);
        $this->assertStringNotContainsString('name="terms"', $vendorForm);
        $this->assertStringNotContainsString('Je confirme que le client', $clientForm);
        $this->assertStringNotContainsString('Je confirme que le vendeur', $vendorForm);
    }

    public function test_client_module_uses_a_dedicated_professional_view(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialRecordController.php')
        );

        $view = file_get_contents(
            resource_path('views/commercial/clients/index.blade.php')
        );

        $this->assertStringContainsString("view('commercial.clients.index'", $controller);
        $this->assertStringContainsString('class="client-stats"', $view);
        $this->assertStringContainsString('class="client-card"', $view);
        $this->assertStringContainsString('orders_count', $view);
        $this->assertStringNotContainsString('Données synchronisées avec les modules', $view);
        $this->assertStringNotContainsString('<table', $view);
    }
}
