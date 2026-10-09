<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShopConfigurationMissionRoutesTest extends TestCase
{
    public function test_shop_configuration_mission_routes_are_registered(): void
    {
        foreach ([
            'support.configuration-missions.index',
            'commercial.configuration-missions.index',
            'commercial.configuration-missions.show',
            'commercial.configuration-missions.start',
            'commercial.configuration-missions.logistics',
            'commercial.configuration-missions.payout',
            'commercial.configuration-missions.products-done',
            'commercial.configuration-missions.complete',
            'admin.shop-configuration-missions.index',
            'admin.shop-configuration-missions.store',
            'admin.shop-configuration-missions.cancel',
            'support.configuration-missions.legacy-configure',
            'support.configuration-missions.legacy-configuration',
            'commercial.configuration-missions.legacy-configure',
            'commercial.configuration-missions.legacy-configuration',
            'admin.shop-configuration-missions.legacy-configure',
            'admin.shop-configuration-missions.legacy-configuration',
            'admin.shop-configuration-missions.legacy-shops-configure',
            'staff.configuration-missions.legacy',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route absente : {$routeName}");
        }
    }
    public function test_admin_configuration_mission_urls_are_not_captured_as_shop_ids(): void
    {
        $canonical = Route::getRoutes()->match(Request::create('/admin/shops/configuration-missions', 'GET'));
        $legacy = Route::getRoutes()->match(Request::create('/admin/shops/configure-mission', 'GET'));

        $this->assertSame('admin.shop-configuration-missions.index', $canonical->getName());
        $this->assertSame('admin.shop-configuration-missions.legacy-shops-configure', $legacy->getName());
    }

}
