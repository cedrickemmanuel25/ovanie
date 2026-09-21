<?php

namespace Tests\Unit;

use Tests\TestCase;

class OpenShopBackgroundGpsTest extends TestCase
{
    public function test_ovanie_logistics_requires_an_exact_gps_source_server_side(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ShopController.php'));

        $this->assertStringContainsString("'device_gps'", $controller);
        $this->assertStringContainsString('La position GPS exacte de la boutique doit être capturée pour OVANIE Logistics.', $controller);
        $this->assertStringContainsString("Rule::requiredIf(fn () => \$request->input('logistics_type') === 'ovanie')", $controller);
    }

    public function test_web_onboarding_captures_gps_without_adding_a_visible_control(): void
    {
        $view = file_get_contents(resource_path('views/open-shop.blade.php'));
        $script = file_get_contents(public_path('js/open-shop.js'));

        $this->assertStringNotContainsString('id="useLocationButton"', $view);
        $this->assertStringContainsString('captureOvanieGpsInBackground', $script);
        $this->assertStringContainsString("storeCoordinates(lng, lat, 'browser_gps'", $script);
    }
}
