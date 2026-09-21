<?php
namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Tests\Support\TrackingReferenceData;
use Tests\TestCase;

class LogisticsTrackingViewsTest extends TestCase
{
    public function test_tracking_pages_link_to_the_selected_mission_and_driver(): void
    {
        foreach(['logistics.tracking','logistics.tracking-mission','logistics.tracking-driver'] as $view){
            $data=TrackingReferenceData::make();$data['driver']->profile=['plate'=>'AA-1234-GC','vehicle_color'=>'Bleu'];$html=view($view,$data+['errors'=>new ViewErrorBag()])->render();
            $this->assertStringContainsString('Koffi Alain',$html);
            $this->assertStringContainsString('Centre de suivi GPS',$html);
            $this->assertStringContainsString('data-tracking-map',$html);
            $this->assertStringContainsString(route('logistics.tracking.driver',['driver'=>$data['driver']->id]),$html);
            $this->assertStringNotContainsString('cdn.tailwindcss.com',$html);
            $this->assertStringNotContainsString('Batterie',$html);
        }
    }
    public function test_unavailable_tracking_has_explicit_empty_states(): void
    {
        $data=['group'=>null,'driver'=>null,'errors'=>new ViewErrorBag()];
        $this->assertStringContainsString('Aucune mission à suivre',view('logistics.tracking-mission',$data)->render());
        $this->assertStringContainsString('Aucun livreur actif à suivre',view('logistics.tracking-driver',$data)->render());
    }
    public function test_old_gps_is_not_presented_as_a_good_live_signal(): void
    {
        $driver=TrackingReferenceData::make()['driver'];$driver->currentLocation->recorded_at=now()->subHour();
        $data=\App\ViewModels\LogisticsTrackingData::driver($driver);
        $this->assertFalse($data['fresh']);$this->assertSame('Hors ligne',$data['quality']);
    }
}
