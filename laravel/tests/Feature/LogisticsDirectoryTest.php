<?php

namespace Tests\Feature;

use App\Http\Controllers\DeliveryIncidentController;
use App\Http\Controllers\LogisticsDirectoryController;
use App\Models\DeliveryDriver;
use App\Models\DriverLocation;
use App\Models\ReturnModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class LogisticsDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_views_render_without_demo_seeders(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur Réel',
            'phone' => '0009999',
            'email' => 'livreur-reel@example.test',
            'vehicle' => 'Moto',
            'zone' => 'Marcory',
            'is_active' => true,
        ]);

        DeliveryDriver::create([
            'name' => 'Ancien Livreur Démo',
            'phone' => '0008888',
            'email' => 'ancien@demo.ovanie.invalid',
            'vehicle' => 'Moto',
            'zone' => 'Cocody',
            'is_active' => true,
        ]);

        $directory = app(LogisticsDirectoryController::class);
        $incidentController = app(DeliveryIncidentController::class);

        $driverList = $directory->drivers(Request::create('/logistique/livreurs'));
        $driverDetail = $directory->driver($driver);
        $returns = $directory->returns(Request::create('/logistique/retours'));
        $incidents = $incidentController->index(Request::create('/logistique/incidents'));

        foreach ([$driverList, $driverDetail, $returns, $incidents] as $view) {
            $html = $view->with('errors', new ViewErrorBag)->render();
            $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        }

        $this->assertSame(1, $driverList->getData()['drivers']->total());
        $this->assertSame('Livreur Réel', $driverList->getData()['drivers']->first()->name);
        $this->assertSame(0, $returns->getData()['returns']->total());
        $this->assertSame(0, $incidents->getData()['incidents']->total());
    }

    public function test_profile_filter_uses_only_operational_drivers(): void
    {
        $adama = DeliveryDriver::create([
            'name' => 'Adama Koné',
            'phone' => '0001111',
            'email' => 'adama@example.test',
            'zone' => 'Marcory',
            'vehicle' => 'Moto',
            'status' => 'Disponible',
            'is_active' => true,
            'is_online' => true,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
        ]);
        DriverLocation::create([
            'driver_id' => $adama->id,
            'latitude' => 5.303,
            'longitude' => -3.984,
            'accuracy' => 12,
            'recorded_at' => now(),
        ]);
        DeliveryDriver::create([
            'name' => 'Autre Livreur',
            'phone' => '0002222',
            'email' => 'autre@example.test',
            'zone' => 'Cocody',
            'vehicle' => 'Tricycle',
            'status' => 'Disponible',
            'is_active' => true,
        ]);

        $controller = app(LogisticsDirectoryController::class);
        $request = Request::create('/logistique/livreurs', 'GET', ['q' => 'Adama', 'status' => 'Disponible']);
        $view = $controller->drivers($request);

        $this->assertSame(1, $view->getData()['drivers']->total());
        $this->assertSame('Adama Koné', $view->getData()['drivers']->first()->name);
    }

    public function test_driver_creation_and_access_generate_a_hashed_pin(): void
    {
        $controller = app(LogisticsDirectoryController::class);
        $controller->storeDriver(Request::create('/logistique/livreurs', 'POST', [
            'name' => 'Test Livreur',
            'phone' => '000123456',
            'email' => 'driver@example.test',
            'zone' => 'Marcory',
            'vehicle' => 'Moto Yamaha',
            'status' => 'Disponible',
            'plate' => 'TEST-001',
        ]));

        $driver = DeliveryDriver::where('phone', '000123456')->firstOrFail();
        $this->assertSame('TEST-001', $driver->profile['plate']);
        $this->assertFalse($driver->is_online);

        $controller->access(Request::create('/', 'POST', [
            'phone' => $driver->phone,
            'email' => $driver->email,
        ]), $driver);

        $pin = session('driver_access_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
        $this->assertTrue(Hash::check($pin, $driver->fresh()->password));
        $this->assertFalse($driver->fresh()->must_change_password);
    }

    public function test_collection_links_the_selected_driver_without_refunding(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur Collecte',
            'phone' => '0003333',
            'email' => 'collecte@example.test',
            'zone' => 'Cocody',
            'vehicle' => 'Tricycle',
            'status' => 'Disponible',
            'is_active' => true,
        ]);

        $return = ReturnModel::create([
            'order_reference' => 'CMD-TEST-RETURN-001',
            'product_name' => 'Produit réel test',
            'reason' => 'Produit endommagé',
            'request_date' => today(),
            'status' => ReturnModel::STATUS_PENDING,
            'quantity' => 1,
            'return_type' => 'return',
            'logistics_status' => ReturnModel::LOGISTICS_PENDING_PICKUP,
            'meta' => [],
        ]);

        app(LogisticsDirectoryController::class)->planReturn(
            Request::create('/', 'POST', [
                'driver_id' => $driver->id,
                'date' => today()->format('Y-m-d'),
                'slot' => '14:00 - 16:00',
                'address' => 'Cocody',
                'destination' => 'Point retour OVANIE',
                'note' => 'Appeler avant la collecte',
            ]),
            $return
        );

        $return->refresh();
        $this->assertSame(ReturnModel::STATUS_ACCEPTED, $return->status);
        $this->assertSame(ReturnModel::LOGISTICS_PICKUP_PLANNED, $return->logistics_status);
        $this->assertSame($driver->id, $return->meta['collection']['driver_id']);
        $this->assertNull($return->refunded_at);
    }
}
