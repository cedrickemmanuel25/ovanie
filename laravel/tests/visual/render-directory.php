<?php
// Read-only rendering against the explicitly scoped local demo records.
require dirname(__DIR__,2).'/vendor/autoload.php';$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local'))exit('Local only.');
config(['session.driver'=>'array']);Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8017');
$directory=app(App\Http\Controllers\LogisticsDirectoryController::class);$incidents=app(App\Http\Controllers\DeliveryIncidentController::class);
$driver=App\Models\DeliveryDriver::where('email','directory-driver-9@demo.ovanie.invalid')->firstOrFail();$incident=App\Models\DeliveryIncident::where('reported_by_type','demo-directory')->where('reported_by_id',1)->firstOrFail();$return=App\Models\ReturnModel::where('order_reference','CMD-DEMO-RETURN-041')->firstOrFail();
$pages=['drivers'=>'logistics.drivers','driver-profile'=>'logistics.drivers.show','driver-create'=>'logistics.drivers','driver-access'=>'logistics.drivers.show','incidents'=>'logistics.incidents.index','incident-detail'=>'logistics.incidents.show','incident-create'=>'logistics.incidents.index','returns'=>'logistics.returns','return-detail'=>'logistics.returns.details','return-collect'=>'logistics.returns.details'];
foreach($pages as $name=>$routeName){$request=Illuminate\Http\Request::create('http://127.0.0.1:8017/preview/'.$name.'.html');$route=clone app('router')->getRoutes()->getByName($routeName);$route->bind($request);$request->setRouteResolver(fn()=>$route);app()->instance('request',$request);
    $view=match($name){'drivers','driver-create'=>$directory->drivers($request),'driver-profile','driver-access'=>$directory->driver($driver),'incidents','incident-create'=>$incidents->index($request),'incident-detail'=>$incidents->show($incident),'returns'=>$directory->returns($request),default=>app(App\Http\Controllers\LogisticsController::class)->returnDetails($return,app(App\Services\ReturnRefundService::class))};
    $html=$view->with('errors',new Illuminate\Support\ViewErrorBag())->render();
    if(in_array($name,['driver-create','driver-access','incident-create','return-collect']))$html=str_replace('</body>','<script>document.addEventListener("DOMContentLoaded",()=>document.getElementById("'.$name.'").showModal());</script></body>',$html);
    $html=str_replace('</body>','<script src="/qa/check-directory.js"></script></body>',$html);file_put_contents(storage_path('app/operations-visual/'.$name.'.html'),$html);echo $name." rendered\n";
}
