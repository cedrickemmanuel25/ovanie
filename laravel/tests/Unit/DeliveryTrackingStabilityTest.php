<?php

namespace Tests\Unit;

use App\Services\Geo\DriverTrackingService;
use App\Services\Geo\RoutingService;
use App\Services\SellerDeliverySupervisionService;
use ReflectionClass;
use Tests\TestCase;

class DeliveryTrackingStabilityTest extends TestCase
{
    public function test_an_inaccurate_fix_keeps_the_last_reliable_position(): void
    {
        config(['delivery.gps_max_accuracy_m' => 100]);

        $result = $this->stabilize(
            DriverTrackingService::class,
            latitude: 5.37,
            longitude: -4.01,
            accuracy: 250,
            speed: null,
            previousLatitude: 5.36,
            previousLongitude: -4.00,
        );

        $this->assertSame([5.36, -4.00], $result);
    }

    public function test_missing_speed_does_not_freeze_a_real_seller_movement(): void
    {
        $result = $this->stabilize(
            SellerDeliverySupervisionService::class,
            latitude: 5.3602,
            longitude: -4.00,
            accuracy: 50,
            speed: null,
            previousLatitude: 5.36,
            previousLongitude: -4.00,
        );

        $this->assertSame([5.3602, -4.00], $result);
    }

    public function test_client_map_only_opens_for_real_live_deliveries_and_uses_operational_bounds(): void
    {
        $view = file_get_contents(resource_path('views/client/orders/tracking.blade.php'));

        $this->assertStringContainsString('Boolean(item.map_visible)', $view);
        $this->assertStringContainsString('const driverMarkers = new Map()', $view);
        $this->assertStringContainsString('OvanieDeliveryMap.fitOperationalBounds', $view);
        $this->assertStringContainsString('renderWorldCopies:false', $view);
        $this->assertStringContainsString("item.tracking_mode === 'gps'", $view);
    }

    public function test_a_corrupted_route_geometry_cannot_force_a_country_wide_zoom(): void
    {
        $reflection = new ReflectionClass(RoutingService::class);
        $method = $reflection->getMethod('routeIsUsable');
        $method->setAccessible(true);

        $usable = $method->invoke($this->app->make(RoutingService::class), [
            'success' => true,
            'distance_km' => 1.2,
            'route_geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [-4.0082, 5.3599],
                    [-3.99, 5.37],
                    [-7.55, 9.50],
                    [-3.98, 5.38],
                ],
            ],
        ], 5.3599, -4.0082, 5.38, -3.98);

        $this->assertFalse($usable);
    }

    public function test_delivery_views_reject_outlier_geometry_before_fitting_bounds(): void
    {
        $client = file_get_contents(resource_path('views/client/orders/tracking.blade.php'));
        $seller = file_get_contents(resource_path('views/seller-driver/mission.blade.php'));

        $this->assertStringContainsString('routeDistance > (straight * 9) + 3', $client);
        // The seller view calls usableRouteGeometry with the incoming geometry argument (not the stored currentGeometry)
        $this->assertStringContainsString('usableRouteGeometry(geometry,', $seller);
    }

    public function test_tracking_uses_project_markers_and_only_draws_the_remaining_road_route(): void
    {
        $component = file_get_contents(public_path('js/ovanie-delivery-map.js'));
        $seller = file_get_contents(resource_path('views/seller-driver/mission.blade.php'));
        $client = file_get_contents(resource_path('views/client/orders/tracking.blade.php'));
        $logistics = file_get_contents(resource_path('views/logistics/tracking.blade.php'));

        foreach (['moto.png', 'Tricycle.png', 'Pickup.png', 'Camion%203T.png', 'Camion%2010T.png', 'Boutique.png', 'Position%20client.png'] as $asset) {
            $this->assertStringContainsString($asset, $component);
        }
        $this->assertStringContainsString('function remainingRoute(', $component);
        $this->assertStringContainsString('OvanieDeliveryMap.remainingRoute', $seller);
        $this->assertStringContainsString('OvanieDeliveryMap.remainingRoute', $client);
        $this->assertStringContainsString('OvanieDeliveryMap.remainingRoute', $logistics);
        $this->assertStringContainsString('positionCacheKey', $seller);
    }

    public function test_seller_mission_automatically_follows_reroutes_and_detects_arrival(): void
    {
        $view = file_get_contents(resource_path('views/seller-driver/mission.blade.php'));
        $service = file_get_contents(app_path('Services/SellerDeliverySupervisionService.php'));

        $this->assertStringContainsString('followVehicle(initial)', $view);
        $this->assertStringContainsString('resumeCameraFollow(false)', $view);
        $this->assertStringContainsString("data.mission_status==='arrived'", $view);
        $this->assertStringNotContainsString('Je suis arrivé chez le client', $view);
        $this->assertStringContainsString('detectAutomaticArrival', $service);
        $this->assertStringContainsString('route_deviation_threshold_m', $service);
        $this->assertStringContainsString('arrival_confirmation_fixes', $service);
        $this->assertStringContainsString('routeState.snapped ? routeState.geometry : null', $view);
        $this->assertStringContainsString('animate:false', $view);
        $this->assertStringContainsString("'eta_minutes' => 0", $service);
    }

    public function test_route_deviation_is_measured_against_the_full_road_geometry(): void
    {
        $reflection = new ReflectionClass(SellerDeliverySupervisionService::class);
        $method = $reflection->getMethod('distanceToRouteMeters');
        $method->setAccessible(true);
        $service = $this->app->make(SellerDeliverySupervisionService::class);
        $geometry = [
            'type' => 'LineString',
            'coordinates' => [
                [-3.97210, 5.34590],
                [-3.97150, 5.34630],
                [-3.97090, 5.34670],
            ],
        ];

        $onRoute = $method->invoke($service, $geometry, 5.34630, -3.97150);
        $offRoute = $method->invoke($service, $geometry, 5.34730, -3.97150);

        $this->assertLessThan(2, $onRoute);
        $this->assertGreaterThan(45, $offRoute);
    }

    public function test_vendor_ready_message_distinguishes_seller_delivery_from_ovanie_logistics(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/VendorOrderController.php'));

        $this->assertStringContainsString('$hasOvanieLogistics', $controller);
        $this->assertStringContainsString('Commande prête pour enlèvement ou livraison.', $controller);
    }


    public function test_tracking_api_consolidates_real_delivery_loads_instead_of_legacy_shipments(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/ShipmentTrackingController.php'));
        $consolidation = file_get_contents(app_path('Services/OvanieShipmentConsolidationService.php'));

        $this->assertStringContainsString('$consolidation->groups($ovanieItems)', $controller);
        $this->assertStringContainsString("':mission:' . \$missionNumber", $consolidation);
        $this->assertStringContainsString("['code' => 'moto'", $consolidation);
        $this->assertStringContainsString("['code' => 'tricycle'", $consolidation);
    }

    public function test_routes_follow_the_real_mission_phase(): void
    {
        $tracking = file_get_contents(app_path('Http/Controllers/LogisticsTrackingController.php'));
        $routing = file_get_contents(app_path('Services/Geo/MissionRoutingService.php'));

        $this->assertStringContainsString("['accepted', 'collecting']", $tracking);
        $this->assertStringContainsString("'include_destination' => \$deliveryLegActive", $tracking);
        $this->assertStringContainsString("'require_driver' => true", $tracking);
        $this->assertStringContainsString('$includeDestination', $routing);
    }

    private function stabilize(
        string $service,
        float $latitude,
        float $longitude,
        ?float $accuracy,
        ?float $speed,
        float $previousLatitude,
        float $previousLongitude,
    ): array {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('stabilizedCoordinates');
        $method->setAccessible(true);

        return $method->invoke(
            $this->app->make($service),
            $latitude,
            $longitude,
            $accuracy,
            $speed,
            $previousLatitude,
            $previousLongitude,
        );
    }
}
