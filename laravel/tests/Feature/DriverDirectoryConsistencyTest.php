<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Driver\DriverAuthController;
use App\Http\Controllers\LogisticsDirectoryController;
use App\Models\DeliveryDriver;
use App\Models\DriverLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverDirectoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_can_open_the_document_submitted_from_mobile(): void
    {
        Storage::fake('public');
        $driver = DeliveryDriver::create(['name' => 'Document mobile', 'phone' => '0701020309']);
        $path = 'drivers/onboarding/'.$driver->id.'/registration.jpg';
        Storage::disk('public')->put($path, 'test-document');
        $driver->update(['profile' => ['documents' => ['Carte grise' => ['path' => $path]]]]);
        $response = app(LogisticsDirectoryController::class)->document($driver, 'registration');
        $this->assertSame(Storage::disk('public')->path($path), $response->getFile()->getPathname());
        $driver->update(['profile' => ['documents' => ['Carte grise' => ['path' => 'drivers/onboarding/999/registration.jpg']]]]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LogisticsDirectoryController::class)->document($driver, 'registration');
    }

    public function test_pending_driver_is_offline_unrated_and_has_a_review_link(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur sans missions', 'phone' => '0701020304',
            'onboarding_status' => 'pending_review', 'is_active' => false,
            'is_online' => true, // Historical OTP login flag.
        ])->fresh();
        $this->assertNull($driver->rating);
        $html = app(LogisticsDirectoryController::class)->drivers(Request::create('/'))
            ->with('errors', new ViewErrorBag)->render();
        $this->assertMatchesRegularExpression('/En ligne<\/span>\s*<div class="ops-kpi-value">0/', $html);
        $this->assertMatchesRegularExpression('/Hors ligne<\/span>\s*<div class="ops-kpi-value">1/', $html);
        $this->assertStringContainsString('Pas encore noté', $html);
        $this->assertStringContainsString(route('logistics.drivers.review', $driver), $html);
        $this->assertStringContainsString('Examiner le dossier', $html);
    }

    public function test_online_but_unavailable_driver_is_not_counted_as_offline(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur connecté', 'phone' => '0701020305',
            'onboarding_status' => 'active', 'is_active' => true,
            'is_online' => true, 'status' => 'Indisponible', 'rating' => 4.8,
        ]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 5.3484,
            'longitude' => -4.0267,
            'accuracy' => 15,
            'recorded_at' => now(),
        ]);
        $html = app(LogisticsDirectoryController::class)->drivers(Request::create('/'))
            ->with('errors', new ViewErrorBag)->render();
        $this->assertMatchesRegularExpression('/En ligne<\/span>\s*<div class="ops-kpi-value">1/', $html);
        $this->assertMatchesRegularExpression('/Hors ligne<\/span>\s*<div class="ops-kpi-value">0/', $html);
        $this->assertStringContainsString('4,8', $html);
    }

    public function test_placeholder_cleanup_preserves_operational_ratings(): void
    {
        $pending = DeliveryDriver::create(['name' => 'Dossier', 'phone' => '0701020306',
            'rating' => 4.5, 'onboarding_status' => 'pending_review', 'is_active' => false]);
        $active = DeliveryDriver::create(['name' => 'Actif', 'phone' => '0701020307',
            'rating' => 4.5, 'onboarding_status' => 'active', 'is_active' => true]);
        $migration = require database_path('migrations/2026_09_14_000001_remove_default_delivery_driver_rating.php');
        $migration->up();
        $this->assertNull($pending->fresh()->rating);
        $this->assertSame(4.5, $active->fresh()->rating);
    }

    public function test_otp_does_not_make_unapproved_driver_online_and_approval_does_not_rate_them(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Inscription', 'phone' => '0701020308',
            'onboarding_status' => 'pending_review', 'is_active' => false,
            'otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(10),
        ]);
        $response = app(DriverAuthController::class)->verify(Request::create('/', 'POST', [
            'phone' => $driver->phone, 'otp' => '123456',
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($driver->fresh()->is_online);
        app(LogisticsDirectoryController::class)->approveDriver(Request::create('/', 'POST'), $driver->fresh());
        $this->assertSame('active', $driver->fresh()->onboarding_status);
        $this->assertTrue($driver->fresh()->is_active);
        $this->assertFalse($driver->fresh()->is_online);
        $this->assertNull($driver->fresh()->rating);
        $driver->forceFill(['is_online' => true])->save();
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $driver);
        app(DriverAuthController::class)->logout($request);
        $this->assertFalse($driver->fresh()->is_online);
    }
}
