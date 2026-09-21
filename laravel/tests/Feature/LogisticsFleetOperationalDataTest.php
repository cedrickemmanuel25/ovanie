<?php

namespace Tests\Feature;

use App\Http\Controllers\LogisticsFleetController;
use App\Models\LogisticsFleetVehicle;
use App\Models\DeliveryDriver;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LogisticsFleetOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_vehicle_uses_all_zones_and_ovanie_capacities(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur réel', 'phone' => '0700000199', 'zone' => 'Abobo',
            'is_active' => true, 'onboarding_status' => 'active',
            'profile' => ['plate' => 'REAL-171', 'zones' => ['Abobo', 'Cocody', 'Yopougon'],
                'documents' => ['Carte grise' => ['path' => 'drivers/onboarding/1/registration.pdf']]],
        ]);
        $vehicle = LogisticsFleetVehicle::create([
            'code' => 'LIV-REAL-171', 'registration' => 'REAL-171', 'vehicle_type' => 'tricycle',
            'zone' => 'Abobo', 'capacity_kg' => 0, 'mileage_km' => 0,
            'meta' => ['driver_id' => $driver->id, 'source' => 'driver_profile'],
        ]);
        $controller = app(LogisticsFleetController::class);
        $list = $controller->index(Request::create('/'));
        $this->assertSame(['Abobo', 'Cocody', 'Yopougon'], $list->getData()['allVehicles']->first()->resolved_zones);
        $this->assertContains('Cocody', $list->getData()['zones']);
        $html = $list->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('250 kg', $html);
        foreach ([['zone' => 'Cocody'], ['q' => 'Yopougon']] as $filter) {
            $this->assertSame(1, $controller->index(Request::create('/', 'GET', $filter))->getData()['vehicles']->total());
        }
        $detail = $controller->show($vehicle)->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('Abobo, Cocody, Yopougon', $detail);
        $this->assertStringContainsString('250 kg', $detail);
        $this->assertStringNotContainsString('0 km', $detail);
        $this->assertStringContainsString(route('logistics.drivers.document', [$driver, 'registration']), $detail);
        $export = $controller->index(Request::create('/', 'GET', ['export' => 'csv', 'zone' => 'Cocody']));
        ob_start();
        $export->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Abobo, Cocody, Yopougon', $csv);
        $this->assertSame('250', str_getcsv(explode("\n", trim($csv))[1])[6]);
        $vehicle->update(['capacity_kg' => 200, 'mileage_km' => 1234]);
        $detail = $controller->show($vehicle)->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString('200 kg', $detail);
        foreach (['<dt>Marque', '<dt>Modèle', '<dt>Année', '<dt>Kilométrage', '<dt>Mise en service', 'Maintenance enregistrée', 'État maintenance'] as $absent) {
            $this->assertStringNotContainsString($absent, $detail);
        }
        $vehicle->update(['registration' => 'OTHER-172', 'zone' => 'Plateau', 'mileage_km' => 0,
            'meta' => ['driver_id' => $driver->id, 'created_from' => 'logistics_fleet_form', 'mileage_recorded' => true]]);
        $this->assertSame(['Plateau'], $vehicle->serviceZones($driver));
        $detail = $controller->show($vehicle)->with('errors', new ViewErrorBag)->render();
        $this->assertStringNotContainsString(route('logistics.drivers.document', [$driver, 'registration']), $detail);
    }

    public function test_capacities_follow_the_ovanie_vehicle_catalog(): void
    {
        foreach (['moto' => 20, 'Tricycle' => 250, 'pickup' => 1000, 'camion_3t' => 3000, 'Camion 10T' => 10000] as $type => $capacity) {
            $vehicle = new LogisticsFleetVehicle(['vehicle_type' => $type, 'capacity_kg' => 0]);
            $this->assertSame((float) $capacity, $vehicle->capacityKg());
            $this->assertGreaterThan(0, $vehicle->volumeM3());
        }
        $this->assertNull((new LogisticsFleetVehicle(['vehicle_type' => 'inconnu']))->capacityKg());
    }

    public function test_legacy_demo_fleet_rows_are_excluded_from_operational_fleet(): void
    {
        LogisticsFleetVehicle::create([
            'code' => 'FLT-001',
            'registration' => '3301-AB01',
            'vehicle_type' => 'Moto',
            'brand' => 'Yamaha',
            'model' => 'YBR 125',
            'capacity_kg' => 20,
            'status' => 'available',
        ]);

        LogisticsFleetVehicle::create([
            'code' => 'FLT-9001',
            'registration' => 'REAL-9001',
            'vehicle_type' => 'Pickup',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'capacity_kg' => 1000,
            'status' => 'available',
            'meta' => ['created_from' => 'logistics_fleet_form'],
        ]);

        $view = app(LogisticsFleetController::class)->index(Request::create('/logistique/flotte'));

        $this->assertSame(1, $view->getData()['counts']['total']);
        $this->assertSame('REAL-9001', $view->getData()['allVehicles']->first()->registration);
    }
}
