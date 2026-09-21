<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array']);Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8017');
require_once dirname(__DIR__).'/Support/SupervisionReferenceData.php';require_once dirname(__DIR__).'/Support/TrackingReferenceData.php';
foreach(['tracking'=>['logistics.tracking','logistics.tracking'],'tracking-mission'=>['logistics.tracking-mission','logistics.tracking.mission'],'tracking-driver'=>['logistics.tracking-driver','logistics.tracking.driver']] as $name=>[$view,$routeName]){
    $request=Illuminate\Http\Request::create('http://127.0.0.1:8017/preview/'.$name.'.html');$route=clone app('router')->getRoutes()->getByName($routeName);$route->bind($request);$request->setRouteResolver(fn()=>$route);app()->instance('request',$request);
    $data=Tests\Support\TrackingReferenceData::make();
    if($name==='tracking'){
        $groups=collect();foreach(['Abobo','Port-Bouët','Yopougon','Plateau','Anyama','Treichville','Cocody'] as $index=>$zone){$g=$data['group'];$item=clone $g['representative'];$item->id=$index+1;$order=clone $g['order'];$order->delivery_commune=$zone;$g['order']=$order;$g['representative']=$item;$g['items']=collect([$item]);$g['mission_number']='SHP-'.['4CWUIWYW','ZYMZLUZF','YU1PMJVJ','ULE0PKYT','VD91K06','QMSKR6HG','DEMO007'][$index];$groups->push($g);}$data['activeGroups']=$groups;
    }
    $html=view($view,$data+['errors'=>new Illuminate\Support\ViewErrorBag()])->render();
    $html=str_replace('</body>','<script src="/qa/check-tracking.js"></script></body>',$html);
    file_put_contents(storage_path('app/operations-visual/'.$name.'.html'),$html);echo $name." rendered\n";
}
