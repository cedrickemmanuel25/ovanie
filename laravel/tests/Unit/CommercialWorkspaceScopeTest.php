<?php

namespace Tests\Unit;

use Tests\TestCase;

class CommercialWorkspaceScopeTest extends TestCase
{
    public function test_orders_are_scoped_to_the_commercial_clients_and_shops(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialRecordController.php')
        );

        $this->assertStringContainsString("whereHas('client'", $controller);
        $this->assertStringContainsString("orWhereHas('items.shop'", $controller);
        $this->assertStringContainsString('created_by_commercial_id', $controller);
        $this->assertStringContainsString('managed_by_commercial_id', $controller);
        $this->assertStringNotContainsString(
            "Order::with('client')->operational()->latest()",
            $controller
        );
    }

    public function test_vendor_order_and_client_modules_use_dedicated_views(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialRecordController.php')
        );

        $this->assertStringContainsString("view('commercial.vendors.index'", $controller);
        $this->assertStringContainsString("view('commercial.orders.index'", $controller);
        $this->assertStringContainsString("view('commercial.clients.index'", $controller);
        $this->assertFileExists(resource_path('views/commercial/vendors/location.blade.php'));
        $this->assertFileExists(resource_path('views/commercial/clients/index.blade.php'));
    }

    public function test_vendor_creation_persists_real_geolocation_fields(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialAccountController.php')
        );

        $vendorForm = file_get_contents(
            resource_path('views/commercial/vendors/create.blade.php')
        );

        $locationPicker = file_get_contents(
            resource_path('views/commercial/vendors/partials/location-picker.blade.php')
        );

        $this->assertStringContainsString(
            "@include('commercial.vendors.partials.location-picker'",
            $vendorForm
        );

        foreach (['latitude', 'longitude', 'geo_accuracy', 'geo_source', 'location_confirmed'] as $field) {
            $this->assertStringContainsString($field, $controller);
            $this->assertStringContainsString('name="'.$field.'"', $locationPicker);
        }

        $this->assertStringContainsString("'landmark' => ['nullable'", $controller);
        $this->assertStringContainsString('Utiliser ma position actuelle', $locationPicker);
    }

    public function test_clients_are_scoped_to_the_current_commercial(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Commercial/CommercialRecordController.php')
        );

        $this->assertStringContainsString("where('role', 'client')", $controller);
        $this->assertStringContainsString("where('created_by_commercial_id', $commercialId)", $controller);
    }
}
