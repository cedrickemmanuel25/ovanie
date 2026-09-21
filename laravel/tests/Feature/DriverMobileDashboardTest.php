<?php

namespace Tests\Feature;

use App\Models\DeliveryDriver;
use App\Models\LogisticsFleetVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverMobileDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_driver_dashboard_uses_real_fleet_vehicle_without_fake_missions(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('drivers/onboarding/1/vehicle.jpg', 'real-photo-bytes');

        $driver = DeliveryDriver::create([
            'name' => 'Yao Kouakou',
            'first_name' => 'Yao',
            'last_name' => 'Kouakou',
            'phone' => '0700000888',
            'is_active' => true,
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
            'status' => 'Disponible',
            'vehicle' => 'tricycle',
            'zone' => 'Abobo',
            'profile' => [
                'plate' => 'AA-1234-GC',
                'vehicle_photo' => 'drivers/onboarding/1/vehicle.jpg',
                'vehicle_color' => 'Bleu',
                'vehicle_color_hex' => '#245DB6',
            ],
        ]);

        $fleet = LogisticsFleetVehicle::create([
            'code' => 'LIV-'.$driver->id.'-TEST',
            'registration' => 'AA-1234-GC',
            'vehicle_type' => 'tricycle',
            'driver_name' => $driver->name,
            'meta' => [
                'driver_id' => $driver->id,
                'source' => 'driver_profile',
                'photo_path' => 'drivers/onboarding/1/vehicle.jpg',
                'vehicle_color' => 'Bleu',
                'vehicle_color_hex' => '#245DB6',
            ],
        ]);

        $profile = $driver->profile;
        $profile['fleet_vehicle_id'] = $fleet->id;
        $driver->update(['profile' => $profile]);

        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/dashboard')
            ->assertOk()
            ->assertJsonPath('driver.first_name', 'Yao')
            ->assertJsonPath('driver.vehicle', 'Tricycle')
            ->assertJsonPath('driver.vehicle_plate', 'AA-1234-GC')
            ->assertJsonPath('driver.vehicle_color', 'Bleu')
            ->assertJsonPath('driver.vehicle_color_hex', '#245DB6')
            ->assertJsonPath('driver.vehicle_photo_is_real', true)
            ->assertJsonPath('driver.fleet_vehicle_code', 'LIV-'.$driver->id.'-TEST')
            ->assertJsonPath('availability.status', 'Disponible')
            ->assertJsonPath('availability.can_receive_missions', true)
            ->assertJsonPath('priority_mission', null)
            ->assertJsonPath('current_mission', null)
            ->assertJsonPath('next_mission', null)
            ->assertJsonPath('summary.missions_today', 0)
            ->assertJsonPath('summary.completed_today', 0);
    }
}
