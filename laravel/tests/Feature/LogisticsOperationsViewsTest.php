<?php

namespace Tests\Feature;

use App\Models\DeliveryTour;
use App\Models\DeliveryTourStop;
use App\ViewModels\LogisticsOperationsData;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\OperationsReferenceData;
use Tests\TestCase;

class LogisticsOperationsViewsTest extends TestCase
{
    public function test_supervision_pages_render_real_model_fields_and_share_the_navigation(): void
    {
        foreach(['logistics.dashboard','logistics.shipments-details','logistics.shipments-assign','logistics.active-deliveries'] as $view){
            $data=$view==='logistics.dashboard'?\Tests\Support\SupervisionReferenceData::dashboard():\Tests\Support\SupervisionReferenceData::make();
            $html=view($view,$data+['errors'=>new ViewErrorBag()])->render();
            $this->assertStringContainsString('ops-topbar',$html);
            $this->assertStringContainsString($view==='logistics.active-deliveries'?'SHP-4CWUIWYW':'SHP-YU1PMJVJ',$html);
            $this->assertStringContainsString('Expéditions</a>',$html);
            $this->assertStringNotContainsString('Import de missions</a>',$html);
        }
        $data=\Tests\Support\SupervisionReferenceData::make();
        $html=view('logistics.shipments-assign',$data+['errors'=>new ViewErrorBag()])->render();
        $this->assertStringContainsString('name="driver_id"',$html);
        $this->assertStringContainsString('name="estimated_delivery_at"',$html);
        $this->assertStringContainsString('name="vehicle_plate"',$html);
        $this->assertStringContainsString(route('logistics.shipments.assign',$data['rep']),$html);
    }

    public function test_rebuilt_pages_render_the_reference_content_and_forms(): void
    {
        $data=OperationsReferenceData::make();
        foreach (['logistics.shipments','logistics.tours.index','logistics.tours.create','logistics.tours.show'] as $view) {
            $html=view($view,['operationsData'=>$data,'errors'=>new ViewErrorBag()])->render();
            $this->assertStringContainsString('OVANIE', $html);
            $this->assertStringContainsString('Koffi Alain', $html);
            $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        }
        $create=view('logistics.tours.create',['operationsData'=>$data,'errors'=>new ViewErrorBag()])->render();
        $this->assertStringContainsString('name="mission_items[]"', $create);
        $this->assertStringContainsString(route('logistics.tours.store'), $create);
        $missions=view('logistics.shipments',['operationsData'=>$data,'errors'=>new ViewErrorBag()])->render();
        $this->assertStringContainsString('maxlength="500"', $missions);
        $this->assertStringContainsString('name="notify_driver"', $missions);
        $this->assertStringContainsString('<dialog', $missions);
    }

    public function test_empty_tours_do_not_display_fictitious_missions(): void
    {
        $html=view('logistics.tours.index',['tours'=>collect(),'selected'=>null,'stats'=>['today'=>0,'in_progress'=>0,'planned'=>0,'late'=>0,'deliveries_total'=>0],'errors'=>new ViewErrorBag()])->render();
        $this->assertStringContainsString('Aucune tournée.', $html);
        $this->assertStringNotContainsString('TRN-2025-001', $html);
        $this->assertStringNotContainsString('Koffi Alain', $html);
    }

    public function test_empty_deliveries_do_not_fall_back_to_reference_missions(): void
    {
        $data=\Tests\Support\SupervisionReferenceData::make();
        $data['missions']=new \Illuminate\Pagination\LengthAwarePaginator([],0,8);
        $data['stats']=['active'=>0,'route'=>0,'delayed'=>0,'drivers_active'=>0];
        $html=view('logistics.active-deliveries',$data+['errors'=>new ViewErrorBag()])->render();
        $this->assertStringContainsString('Aucune mission ne correspond aux filtres.',$html);
        $this->assertStringNotContainsString('SHP-4CWUIWYW',$html);
    }

    public function test_progress_counts_grouped_stops_instead_of_order_items(): void
    {
        $tour=new DeliveryTour(['reference'=>'TEST','status'=>'planned','vehicle_code'=>'tricycle']);
        $tour->id=42;
        $tour->setRelation('driver',null);
        $stops=collect([[1,'done'],[1,'done'],[2,'pending']])->map(function($values,$index){
            $stop=new DeliveryTourStop(['sequence'=>$values[0],'status'=>$values[1],'commune'=>'Commune '.$values[0],'order_item_id'=>$index+1]);
            $stop->setRelation('orderItem',null);
            return $stop;
        });
        $tour->setRelation('stops',$stops);
        $data=LogisticsOperationsData::tour($tour);
        $this->assertSame(2,$data['total']);
        $this->assertSame(1,$data['done']);
        $this->assertSame(50,$data['percent']);
        $this->assertNull($data['distance']);
    }
}
