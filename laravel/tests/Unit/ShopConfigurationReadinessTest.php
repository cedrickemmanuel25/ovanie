<?php

namespace Tests\Unit;

use App\Models\Shop;
use Tests\TestCase;

class ShopConfigurationReadinessTest extends TestCase
{
    public function test_complete_configuration_matches_existing_configuration_ready_rule(): void
    {
        $shop = new Shop([
            'logistics_type' => 'ovanie',
            'logistics_status' => Shop::LOGISTICS_READY,
            'logistics_confirmed_at' => now(),
            'address' => 'Rue des artisans',
            'commune' => 'Cocody',
            'district' => 'Angré',
            'latitude' => 5.37,
            'longitude' => -3.98,
            'geo_status' => Shop::GEO_STATUS_RELIABLE,
            'payment_mode' => Shop::PAYMENT_WEEKLY,
            'mm_operator' => 'wave',
            'mm_number' => '0102030405',
            'mm_holder' => 'Vendeur Test',
            'payout_configured_at' => now(),
        ]);

        $this->assertTrue($shop->configurationReady());
        $this->assertTrue($shop->hasCompleteConfiguration());
    }

    public function test_incomplete_configuration_is_reported_as_incomplete(): void
    {
        $shop = new Shop([
            'logistics_type' => 'ovanie',
            'logistics_status' => Shop::LOGISTICS_INCOMPLETE,
            'payment_mode' => Shop::PAYMENT_WEEKLY,
        ]);

        $this->assertFalse($shop->configurationReady());
        $this->assertFalse($shop->hasCompleteConfiguration());
    }
}
