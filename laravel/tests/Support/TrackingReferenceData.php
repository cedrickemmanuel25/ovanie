<?php
namespace Tests\Support;

use App\Models\{DeliveryAssignment,DriverLocation,Shipment};

final class TrackingReferenceData
{
    public static function make(): array
    {
        $base=SupervisionReferenceData::make();$group=$base['missions']->items()[0];$driver=$base['drivers']->first();$driver->name='Koffi Alain';$driver->vehicle='Tricycle TR-2456';$driver->last_seen_at=now();
        $location=new DriverLocation(['latitude'=>5.34,'longitude'=>-4.01,'accuracy'=>8,'speed'=>28,'battery_level'=>78,'recorded_at'=>now()]);$driver->setRelation('currentLocation',$location);
        $assignment=new DeliveryAssignment(['mission_number'=>$group['mission_number'],'status'=>'in_transit','estimated_delivery_at'=>now()->subMinutes(28),'picked_up_at'=>now()->subMinutes(45),'started_at'=>now()->subMinutes(40),'accepted_at'=>now()->subMinutes(50)]);$assignment->id=1;$assignment->setRelation('driver',$driver);$assignment->setRelation('latestLocation',$location);
        $group['representative']->setRelation('latestDeliveryAssignment',$assignment);$group['driver_name']=$driver->name;$group['driver_id']=$driver->id;
        $shipment=new Shipment(['route_geometry'=>json_encode(['type'=>'LineString','coordinates'=>[[-4,5.309],[-4.004,5.322],[-4.01,5.34],[-4.012,5.36],[-4.02,5.42]]])]);$group['representative']->setRelation('shipment',$shipment);
        $events=[['at'=>now(),'label'=>'Position mise à jour — Cocody'],['at'=>now()->subMinutes(6),'label'=>'Le chauffeur a quitté la boutique (Marcory)'],['at'=>now()->subMinutes(9),'label'=>'Mission démarrée'],['at'=>now()->subMinutes(12),'label'=>'Affectation confirmée']];
        return ['group'=>$group,'rep'=>$group['representative'],'order'=>$group['order'],'driver'=>$driver,'assignment'=>$assignment,'activeAssignment'=>$assignment,'events'=>$events,'recentEvents'=>$events,'activeGroups'=>collect([$group]),'lateGroups'=>collect([$group]),'driversOnline'=>15,'driversTotal'=>18,'deliveredToday'=>24,'incidentsOpenCount'=>1,'recentIncidents'=>collect(),'todayMissionsCount'=>3,'driverPerformance'=>['distance'=>42,'onlineMinutes'=>258,'onTimePercent'=>100],'allDrivers'=>collect([$driver]),'missions'=>collect([$group])];
    }
}
