<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection=config('database.default');
echo json_encode(['environment'=>app()->environment(),'connection'=>$connection,'host'=>config('database.connections.'.$connection.'.host'),'trackingDemoMissions'=>App\Models\DeliveryAssignment::where('mission_number','like','SHP-DEMO-TRACK-%')->count(),'demoTours'=>App\Models\DeliveryTour::where('reference','like','TRN-DEMO-%')->count()],JSON_PRETTY_PRINT).PHP_EOL;
