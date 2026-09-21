<?php
// Read-only verification of the explicitly named demo records and their detail views.
require dirname(__DIR__,2).'/vendor/autoload.php';$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local'))exit('Local verification only.');
$controller=app(App\Http\Controllers\LogisticsController::class);$consolidation=app(App\Services\OvanieShipmentConsolidationService::class);
$assignment=App\Models\DeliveryAssignment::where('mission_number','SHP-DEMO-TRACK-001')->firstOrFail();
$request=Illuminate\Http\Request::create('/logistique/tracking');
foreach(['tracking'=>$controller->tracking($request,$consolidation),'tracking-mission'=>$controller->trackingMission($request,$consolidation,$assignment->orderItem),'tracking-driver'=>$controller->trackingDriver($request,$consolidation,$assignment->driver),'tours'=>app(App\Http\Controllers\LogisticsTourController::class)->index($request)] as $name=>$view){
    $html=$view->with('errors',new Illuminate\Support\ViewErrorBag())->render();echo $name.': rendered ('.strlen($html).' bytes)'.PHP_EOL;
}
echo 'Mission: '.route('logistics.shipments.details',$assignment->order_item_id).PHP_EOL;
echo 'Tracking: '.route('logistics.tracking.mission',['item'=>$assignment->order_item_id]).PHP_EOL;
$tour=App\Models\DeliveryTour::where('reference','TRN-DEMO-001')->firstOrFail();echo 'Tour: '.route('logistics.tours.show',$tour).PHP_EOL;
$html=app(App\Http\Controllers\LogisticsTourController::class)->show($tour)->with('errors',new Illuminate\Support\ViewErrorBag())->render();echo 'Tour detail: rendered ('.strlen($html).' bytes)'.PHP_EOL;
$deliveries=$controller->activeDeliveries($request,$consolidation)->getData()['missions'];
if($deliveries->perPage()!==2||$deliveries->count()>2)throw new RuntimeException('Delivery pagination must limit the list to two missions.');
echo 'Delivery pagination: '.$deliveries->count().' / '.$deliveries->total().' missions, '.$deliveries->perPage().' per page'.PHP_EOL;
