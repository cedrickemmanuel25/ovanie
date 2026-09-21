<?php

namespace Tests\Unit;

use App\Models\Shop;
use App\Services\CommercialShopLocationService;
use Tests\TestCase;

class CommercialShopLocationServiceTest extends TestCase
{
    public function test_ovanie_shop_can_be_created_without_fake_coordinates_for_later_confirmation(): void
    {
        $payload = app(CommercialShopLocationService::class)->creationPayload([
            'logistics_type' => 'ovanie',
            'location_mode' => 'later',
        ]);

        $this->assertNull($payload['latitude']);
        $this->assertNull($payload['longitude']);
        $this->assertSame(Shop::GEO_STATUS_VERIFICATION_REQUIRED, $payload['geo_status']);
        $this->assertSame(Shop::LOGISTICS_INCOMPLETE, $payload['logistics_status']);
    }

    public function test_reliable_browser_gps_makes_ovanie_pickup_location_ready(): void
    {
        $payload = app(CommercialShopLocationService::class)->creationPayload([
            'logistics_type' => 'ovanie',
            'location_mode' => 'gps_now',
            'latitude' => 5.3599517,
            'longitude' => -4.0082563,
            'geo_accuracy' => 18.4,
            'geo_source' => 'browser_gps',
        ]);

        $this->assertSame(5.3599517, $payload['latitude']);
        $this->assertSame(-4.0082563, $payload['longitude']);
        $this->assertSame(Shop::GEO_STATUS_RELIABLE, $payload['geo_status']);
        $this->assertSame(Shop::LOGISTICS_READY, $payload['logistics_status']);
        $this->assertSame('exact', $payload['geo_precision']);
    }

    public function test_low_accuracy_gps_is_saved_but_does_not_activate_logistics(): void
    {
        $payload = app(CommercialShopLocationService::class)->creationPayload([
            'logistics_type' => 'ovanie',
            'location_mode' => 'gps_now',
            'latitude' => 5.36,
            'longitude' => -4.01,
            'geo_accuracy' => 240,
            'geo_source' => 'browser_gps',
        ]);

        $this->assertSame(Shop::GEO_STATUS_REVIEW_RECOMMENDED, $payload['geo_status']);
        $this->assertSame(Shop::LOGISTICS_INCOMPLETE, $payload['logistics_status']);
        $this->assertSame('low', $payload['geo_precision']);
    }

    public function test_seller_logistics_never_receives_fake_ovanie_coordinates(): void
    {
        $payload = app(CommercialShopLocationService::class)->creationPayload([
            'logistics_type' => 'seller',
            'location_mode' => 'gps_now',
            'latitude' => 5.36,
            'longitude' => -4.01,
        ]);

        $this->assertNull($payload['latitude']);
        $this->assertSame(Shop::LOGISTICS_INCOMPLETE, $payload['logistics_status']);
    }
}
