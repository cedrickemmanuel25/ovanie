<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Driver\DriverOnboardingController;
use App\Http\Controllers\LogisticsDirectoryController;
use App\Http\Controllers\LogisticsFleetController;
use App\Models\AbidjanCommune;
use App\Models\DeliveryDriver;
use App\Models\LogisticsFleetVehicle;
use App\Models\LogisticsTerritoryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DriverRegistrationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_uploads_reach_review_profile_and_fleet(): void
    {
        Storage::fake('public');
        $driver = DeliveryDriver::create(['name' => 'Mobile Réel', 'phone' => '0700000101', 'is_active' => false]);
        $commune = AbidjanCommune::firstOrCreate(['code' => 'TEST'], ['name' => 'Zone réelle', 'slug' => 'zone-reelle', 'is_active' => true]);
        $zone = LogisticsTerritoryZone::create(['code' => 'TEST-ZONE', 'name' => 'Territoire réel', 'region' => 'Abidjan', 'is_active' => true]);
        $zone->communes()->attach($commune);
        $request = Request::create('/', 'POST', [
            'vehicle' => 'tricycle', 'plate' => 'REAL-171',
            'availability_days' => ['lundi', 'samedi'], 'zone_ids' => [$commune->id],
        ], [], [
            'photo' => UploadedFile::fake()->image('profile.png'),
            'vehicle_photo' => UploadedFile::fake()->image('vehicle.png'),
            'plate_photo' => UploadedFile::fake()->image('plate.png'),
            'vehicle_registration_document' => UploadedFile::fake()->create('registration.pdf', 1, 'application/pdf'),
            'supporting_documents' => [UploadedFile::fake()->image('support.png')],
        ]);
        $request->setUserResolver(fn () => $driver);
        app(DriverOnboardingController::class)->submit($request);
        $driver->refresh();
        foreach (['vehicle_photo', 'plate_photo'] as $field) Storage::disk('public')->assertExists($driver->profile[$field]);
        Storage::disk('public')->assertExists($driver->profile['supporting_documents'][0]);
        $this->assertSame(['lundi', 'samedi'], $driver->profile['availability_days']);
        $controller = app(LogisticsDirectoryController::class);
        $review = $controller->driverReview($driver)->with('errors', new ViewErrorBag)->render();
        foreach (['Date de naissance', '<dt>Année', '<dt>Capacité', 'Code sécurisé'] as $absent) $this->assertStringNotContainsString($absent, $review);
        $this->assertStringContainsString('Photo de la plaque', $review);
        $this->assertStringContainsString('Justificatif supplémentaire 1', $review);
        $this->assertStringContainsString(Storage::disk('public')->url($driver->vehiclePhotoPath()), $review);
        $this->assertSame(200, $controller->document($driver, 'supporting-0')->getStatusCode());
        $profile = $controller->driver($driver)->with('errors', new ViewErrorBag)->render();
        $this->assertStringContainsString(Storage::disk('public')->url($driver->vehiclePhotoPath()), $profile);
        $this->assertStringNotContainsString('Générer', $profile);
        $controller->approveDriver(Request::create('/', 'POST'), $driver);
        $vehicle = LogisticsFleetVehicle::where('registration', 'REAL-171')->firstOrFail();
        $this->assertSame($driver->vehiclePhotoPath(), $vehicle->meta['photo_path']);
        $this->assertSame(250, (int) $vehicle->capacity_kg);
        $this->assertSame(1.5, (float) $vehicle->volume_m3);
        $fleet = app(LogisticsFleetController::class)->show($vehicle);
        $this->assertSame(Storage::disk('public')->url($driver->vehiclePhotoPath()), $fleet->getData()['vehicleImage']);
        $index = app(LogisticsFleetController::class)->index(Request::create('/'));
        $this->assertSame($driver->vehiclePhotoPath(), $index->getData()['allVehicles']->first()->resolved_photo_path);
    }

    public function test_legacy_photo_is_available_without_modifying_existing_data(): void
    {
        $driver = new DeliveryDriver(['profile' => ['vehicle_photos' => ['vehicle.jpg', 'plate.jpg']]]);
        $this->assertSame('vehicle.jpg', $driver->vehiclePhotoPath());
        $other = new LogisticsFleetVehicle(['registration' => 'OTHER', 'meta' => ['photo_path' => 'own.jpg']]);
        $driver->id = 123;
        $this->assertSame('own.jpg', $other->photoPathForDriver($driver));
    }

    public function test_legacy_access_cannot_activate_or_generate_a_password(): void
    {
        $driver = DeliveryDriver::create(['name' => 'Invité', 'phone' => '0700000102', 'is_active' => false]);
        try {
            app(LogisticsDirectoryController::class)->access(Request::create('/', 'POST'), $driver);
            $this->fail('Legacy access must be disabled.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        $this->assertFalse($driver->fresh()->is_active);
        $this->assertNull($driver->fresh()->password);
        $this->expectException(HttpException::class);
        app(LogisticsDirectoryController::class)->updateDriver(Request::create('/', 'PATCH', ['toggle_active' => 1]), $driver);
    }
}
