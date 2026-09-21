<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CheckoutGpsCoverageRegressionTest extends TestCase
{
    public function test_checkout_resolves_gps_before_validation_and_pricing(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/CheckoutController.php');
        $view = file_get_contents(__DIR__ . '/../../resources/views/checkout.blade.php');
        $migration = file_get_contents(__DIR__ . '/../../database/migrations/2026_07_20_090000_fix_abidjan_checkout_commune_coverage.php');

        $applyPosition = strpos($controller, 'app(CheckoutAddressResolver::class)->applyToRequest($request);');
        $geoPosition = strpos($controller, '$this->resolveCheckoutGeoIfPossible($request);', $applyPosition);
        $validationPosition = strpos($controller, '$request->validate([', $applyPosition);

        $this->assertNotFalse($applyPosition);
        $this->assertNotFalse($geoPosition);
        $this->assertNotFalse($validationPosition);
        $this->assertLessThan($validationPosition, $geoPosition);
        $this->assertStringContainsString("data.resolved_location || {}", $view);
        $this->assertStringContainsString("'destination_commune' => 'attecoube'", $migration);
    }
    public function test_checkout_exposes_confirmable_map_pin_fallback(): void
    {
        $view = file_get_contents(__DIR__ . '/../../resources/views/checkout.blade.php');
        $service = file_get_contents(__DIR__ . '/../../app/Services/Geo/DeliveryCoordinateService.php');

        $this->assertStringContainsString('adjustDeliveryPinBtn', $view);
        $this->assertStringContainsString('confirmDeliveryPinBtn', $view);
        $this->assertStringContainsString('map_pin_confirmed', $view);
        $this->assertStringContainsString("'browser_live'", $service);
        $this->assertStringContainsString("'mobile_live_gps'", $service);
        $this->assertStringContainsString("'mobile_map_pin'", $service);
    }

}
