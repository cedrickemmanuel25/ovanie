<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver'=>'array']);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8017');
require_once dirname(__DIR__).'/Support/SupervisionReferenceData.php';
foreach(['dashboard'=>['logistics.dashboard','logistics.dashboard'],'mission'=>['logistics.shipments-details','logistics.shipments.details'],'assign'=>['logistics.shipments-assign','logistics.shipments.assign-page'],'deliveries'=>['logistics.active-deliveries','logistics.active-deliveries'],'map'=>['logistics.active-deliveries','logistics.active-deliveries']] as $name=>[$view,$routeName]){
    $request=Illuminate\Http\Request::create('http://127.0.0.1:8017/'.($name==='map'?'?view=map':''));
    $route=clone app('router')->getRoutes()->getByName($routeName);$route->bind($request);$request->setRouteResolver(fn()=>$route);app()->instance('request',$request);
    $data=$name==='dashboard'?Tests\Support\SupervisionReferenceData::dashboard():Tests\Support\SupervisionReferenceData::make();
    if($name==='assign'){
        $extra=clone $data['drivers']->first();$extra->id=40;$extra->name='Koffi Alain';
        $data['drivers']->push($extra);
    }
    $html=view($view,$data+['errors'=>new Illuminate\Support\ViewErrorBag()])->render();
    $html=str_replace('</body>','<script src="/qa/check-supervision.js"></script></body>',$html);
    file_put_contents(storage_path('app/operations-visual/'.$name.'.html'),$html);echo $name." rendered\n";
}
