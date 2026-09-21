<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\DriverLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_flag_alone_does_not_make_driver_online_without_recent_gps(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur GPS',
            'phone' => '0700000991',
            'is_active' => true,
            'is_online' => true,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
            'status' => 'Disponible',
        ])->fresh();

        $this->assertFalse($driver->is_online);
    }

    public function test_recent_gps_heartbeat_makes_driver_online_and_stale_gps_makes_him_offline(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur présence',
            'phone' => '0700000992',
            'is_active' => true,
            'is_online' => true,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
            'status' => 'Indisponible',
        ]);

        $location = DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 5.3484,
            'longitude' => -4.0267,
            'accuracy' => 10,
            'recorded_at' => now(),
        ]);

        $this->assertTrue($driver->fresh()->is_online);

        $location->forceFill([
            'recorded_at' => now()->subSeconds((int) config('delivery.driver_presence_online_seconds', 120) + 5),
        ])->save();

        $this->assertFalse($driver->fresh()->is_online);
    }

    public function test_presence_endpoint_records_gps_and_offline_endpoint_disconnects_driver(): void
    {
        $driver = DeliveryDriver::create([
            'name' => 'Livreur mobile',
            'phone' => '0700000993',
            'is_active' => true,
            'is_online' => false,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
            'status' => 'Disponible',
        ]);

        Sanctum::actingAs($driver);

        $this->postJson('/api/driver/presence', [
            'latitude' => 5.3484,
            'longitude' => -4.0267,
            'accuracy' => 12,
            'battery_level' => 75,
        ])->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('availability', 'Disponible');

        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
        ]);
        $this->assertTrue($driver->fresh()->is_online);

        $this->postJson('/api/driver/presence/offline')
            ->assertOk()
            ->assertJsonPath('online', false);

        $this->assertFalse($driver->fresh()->is_online);
    }
}
