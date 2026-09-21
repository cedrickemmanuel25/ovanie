<?php
// php tests/visual/render-operations.php — no database writes or public preview routes.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array','app.url'=>'http://127.0.0.1:8017']);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8017');
Illuminate\Support\Facades\URL::forceScheme('http');
require_once dirname(__DIR__).'/Support/OperationsReferenceData.php';
$data = Tests\Support\OperationsReferenceData::make();
$dir = storage_path('app/operations-visual');
if (!is_dir($dir)) mkdir($dir,0777,true);
foreach (['operations'=>'logistics.shipments','tours'=>'logistics.tours.index','create'=>'logistics.tours.create','detail'=>'logistics.tours.show','assignment'=>'logistics.shipments'] as $name=>$view) {
    $routeName = ['operations'=>'logistics.shipments','assignment'=>'logistics.shipments','tours'=>'logistics.tours.index','create'=>'logistics.tours.create','detail'=>'logistics.tours.show'][$name];
    $referenceRoute = clone app('router')->getRoutes()->getByName($routeName);
    $referenceRoute->bind(Illuminate\Http\Request::create('/'.str_replace('{tour}', '1', $referenceRoute->uri())));
    request()->setRouteResolver(fn() => $referenceRoute);
    $pageData = $data;
    if($name==='detail') { $pageData['selected']['departure']='08:00'; $pageData['selected']['elapsed']=85; }
    if($name==='assignment') {
        $pageData['drivers']=[array_replace($data['drivers'][0],['name'=>'Diallo Seydou','initials'=>'DS','vehicle'=>'Moto Yamaha (3301-AB01)','reviews'=>127])];
        $pageData['missions']=[$data['missions'][3],$data['missions'][2],$data['missions'][1],$data['missions'][0],$data['missions'][4],$data['missions'][5],array_replace($data['missions'][0],['id'=>7,'reference'=>'SHP-V7D9IK06'])];
    }
    $html=view($view,['operationsData'=>$pageData,'errors'=>new Illuminate\Support\ViewErrorBag(),'incidentsOpenCount'=>3,'driversOnlineCount'=>20,'driversTotalCount'=>30,'driversBusyCount'=>7,'driversAvailableCount'=>13,'driversOfflineCount'=>10,'pickupScheduledAt'=>now()->format('Y-m-d').'T10:45','deliveryScheduledAt'=>now()->format('Y-m-d').'T11:16'])->render();
    if($name==='create')$html=str_replace('<details class="ops-nav-menu">','<details class="ops-nav-menu" open>',$html);
    if($name==='assignment')$html=str_replace('</body>','<script type="application/json" id="ops-assignment-auto">{"missionId":4,"driverId":1}</script></body>',$html);
    $html=str_replace('</body>','<script src="/qa/check-operations.js"></script></body>',$html);
    file_put_contents($dir.'/'.$name.'.html',$html);
    echo $name." rendered\n";
}
