<?php
namespace Tests\Feature;

use App\Models\{DeliveryAssignment,DeliveryDriver,DeliveryTour,DriverLocation,Order};
use Database\Seeders\{LogisticsTrackingSeeder,LogisticsToursSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsDemoSeedersTest extends TestCase
{
    use RefreshDatabase;
    public function test_seeders_can_be_repeated_without_deleting_existing_records(): void
    {
        $existing=DeliveryDriver::create(['name'=>'Existing driver','phone'=>'0009999999','email'=>'existing@example.test','is_active'=>true]);
        $this->seed(LogisticsTrackingSeeder::class);$this->seed(LogisticsToursSeeder::class);
        $counts=[Order::count(),DeliveryAssignment::count(),DriverLocation::count(),DeliveryTour::count()];
        $this->seed(LogisticsTrackingSeeder::class);$this->seed(LogisticsToursSeeder::class);
        $this->assertSame($counts,[Order::count(),DeliveryAssignment::count(),DriverLocation::count(),DeliveryTour::count()]);
        $this->assertDatabaseHas('delivery_drivers',['id'=>$existing->id,'name'=>'Existing driver']);
        $this->assertSame(6,DeliveryTour::where('reference','like','TRN-DEMO-%')->count());
        $this->assertSame(6,DeliveryAssignment::where('mission_number','like','SHP-DEMO-TRACK-%')->count());
    }
}
