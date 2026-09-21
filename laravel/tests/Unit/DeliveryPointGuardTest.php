<?php

namespace Tests\Unit;

use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\DeliveryPointGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class DeliveryPointGuardTest extends TestCase
{
    public function test_live_gps_above_fifteen_meters_is_rejected(): void
    {
        $request = Request::create('/checkout', 'POST', [
            'delivery_destination_type' => 'home',
            'delivery_latitude' => 5.35,
            'delivery_longitude' => -4.01,
            'delivery_geo_accuracy' => 100,
            'delivery_geo_source' => 'mobile_live_gps',
        ]);

        $this->expectException(ValidationException::class);
        (new DeliveryPointGuard())->validateOperationalPoint($request);
    }

    public function test_precise_live_gps_is_accepted(): void
    {
        $request = Request::create('/checkout', 'POST', [
            'delivery_destination_type' => 'home',
            'delivery_latitude' => 5.35,
            'delivery_longitude' => -4.01,
            'delivery_geo_accuracy' => 8.5,
            'delivery_geo_source' => 'browser_live',
        ]);

        (new DeliveryPointGuard())->validateOperationalPoint($request);
        $this->addToAssertionCount(1);
    }

    public function test_confirmed_map_pin_does_not_require_fake_gps_accuracy(): void
    {
        $request = Request::create('/checkout', 'POST', [
            'delivery_destination_type' => 'home',
            'delivery_latitude' => 5.3501234,
            'delivery_longitude' => -4.0123456,
            'delivery_geo_source' => 'mobile_map_pin',
        ]);

        (new DeliveryPointGuard())->validateOperationalPoint($request);
        $this->addToAssertionCount(1);
    }

    public function test_new_checkout_sources_are_operational_for_logistics(): void
    {
        $service = new DeliveryCoordinateService();

        $this->assertTrue($service->sourceIsOperational('browser_live'));
        $this->assertTrue($service->sourceIsOperational('map_pin_confirmed'));
        $this->assertTrue($service->sourceIsOperational('mobile_live_gps'));
        $this->assertTrue($service->sourceIsOperational('mobile_map_pin'));
    }
}
