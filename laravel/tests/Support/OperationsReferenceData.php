<?php

namespace Tests\Support;

/** Reference content for visual QA only; never injected into production pages. */
final class OperationsReferenceData
{
    public static function make(): array
    {
        $driver = ['id'=>1,'name'=>'Koffi Alain','initials'=>'KA','rating'=>'4,8','phone'=>'+2250102030405','vehicle'=>'TR-2456','zone'=>'Marcory','online'=>true,'available'=>true,'compatible'=>true,'pickupEta'=>7,'clientEta'=>31,'reviews'=>128];
        $stops = [];
        foreach ([['Yopougon','Livraison',3,'09:00','done','09:10',5.3364,-4.0620],['Marcory','Collecte',1,'09:30','done','09:45',5.309,-4.000],['Treichville','Livraison',1,'10:30','in_progress',null,5.293,-3.984],['Koumassi','Collecte',1,'11:30','pending',null,5.281,-3.965],['Port-Bouët','Livraison',1,'12:30','pending',null,5.260,-3.930]] as $i=>$s) {
            $stops[] = ['sequence'=>$i+1,'label'=>$s[0],'type'=>$s[1],'missions'=>$s[2],'time'=>$s[3],'status'=>$s[4],'completedTime'=>$s[5],'lat'=>$s[6],'lng'=>$s[7]];
        }
        $missions = [];
        foreach ([['SHP-4CWUIWYW','Abobo',60,'Tricycle','in_transit'],['SHP-ZYMZLUZF','Port-Bouët',60,'Tricycle','in_transit'],['SHP-ULE0PKYT','Plateau',200,'Tricycle','ready_for_pickup'],['SHP-YU1PMJVJ','Yopougon',14,'Moto','ready_for_pickup'],['SHP-QMSKR6HG','Treichville',76,'Tricycle','delivered'],['SHP-B8X7PL9M','Koumassi',35,'Tricycle','ready_for_pickup']] as $i=>$m) {
            $missions[] = ['id'=>$i+1,'itemIds'=>[$i+1],'reference'=>$m[0],'order'=>'ORD-MPGPLIYK','destination'=>$m[1],'client'=>'Edwige KOUAME','weight'=>$m[2],'volume'=>[.4,.4,1.3,.1,.6,.2][$i],'productsCount'=>[5,5,12,2,4,1][$i],'vehicle'=>$m[3],'vehicleCode'=>$m[3]==='Moto'?'moto':'tricycle','status'=>$m[4],'driver'=>'Koffi Alain','driverId'=>1,'products'=>['1 × Câble électrique 2.5mm² (100m)','+ 4 × Disjoncteur bipolaire 32A'],'pickup'=>'KONE ELECTRO & PLOMBERIE','pickupZone'=>'Marcory','lat'=>$stops[min($i,4)]['lat'],'lng'=>$stops[min($i,4)]['lng'],'assignUrl'=>'/visual-only/assign','assignmentUrl'=>'/visual-only/assignment','detailsUrl'=>'/visual-only/mission'];
        }
        $selected = ['id'=>1,'reference'=>'TRN-2025-001','status'=>'in_progress','driver'=>$driver,'vehicle'=>'Tricycle','plate'=>'TR-2456','date'=>'2026-09-10','departure'=>'08:15','zone'=>'Abidjan Sud','communes'=>'Yopougon, Marcory, Koumassi','stops'=>$stops,'done'=>3,'total'=>5,'percent'=>60,'weight'=>420,'volume'=>2.8,'products'=>28,'capacityWeight'=>450,'capacityVolume'=>3,'distance'=>42,'duration'=>150,'elapsed'=>135,'remaining'=>65,'fuel'=>8,'optimized'=>true,'detailsUrl'=>'/preview/detail.html','selectUrl'=>'/preview/tours.html','trackingUrl'=>'/visual-only/tracking','missions'=>array_map(fn($m,$i)=>['reference'=>$m['reference'],'destination'=>['Yopougon','Yopougon','Marcory','Treichville','Koumassi'][$i],'weight'=>$m['weight'],'status'=>['done','done','done','in_progress','pending'][$i],'detailsUrl'=>$m['detailsUrl']],array_slice($missions,0,5),range(0,4))];
        $routeCoordinates = [];
        foreach ($stops as $index => $stop) {
            if ($index > 0) {
                $previous = $stops[$index - 1];
                $routeCoordinates[] = [$previous['lng'] * .65 + $stop['lng'] * .35, $previous['lat'] * .8 + $stop['lat'] * .2];
                $routeCoordinates[] = [$previous['lng'] * .4 + $stop['lng'] * .6, $previous['lat'] * .35 + $stop['lat'] * .65];
            }
            $routeCoordinates[] = [$stop['lng'], $stop['lat']];
        }
        $selected['routeSegments'] = [['type'=>'LineString','coordinates'=>$routeCoordinates]];
        $tours = [$selected];
        foreach ([['Diomandé Issa','4,6','Moto','MT-1123',4,'Cocody, Riviera, Bingerville','planned','10:30'],['Yao Stéphane','4,7','Pickup','PK-7789',6,'Abidjan Centre, Plateau, Treichville','in_progress','09:10'],['Konan Martial','4,5','Tricycle','TR-3366',4,'Port-Bouët, Vridi','late','08:00'],['Coulibaly Amadou','4,8','Camion 3T','CM-8877',6,'Anyama, Abobo','done','07:45'],["N’Guessan Paul",'4,6','Moto','MT-4455',3,'Songon, Yopougon','done','08:20']] as $i=>$t) {
            $tours[] = array_replace($selected,['id'=>$i+2,'reference'=>'TRN-2025-00'.($i+2),'driver'=>array_replace($driver,['id'=>$i+2,'name'=>$t[0],'rating'=>$t[1]]),'vehicle'=>$t[2],'plate'=>$t[3],'total'=>$t[4],'communes'=>$t[5],'status'=>$t[6],'departure'=>$t[7]]);
        }
        return ['missions'=>$missions,'drivers'=>[$driver],'tours'=>$tours,'selected'=>$selected,'stats'=>['today'=>6,'in_progress'=>4,'planned'=>1,'late'=>1,'deliveries_total'=>28,'todayDelta'=>'+2 vs hier','deliveriesDelta'=>'+12 %'],'counts'=>['all'=>9,'to_assign'=>2,'route'=>2,'done'=>5],'plan'=>$selected,'vehicle'=>'Tricycle','gain'=>'35 min','reduction'=>'-8 km','fill'=>'84 %','weight'=>420,'loadPercent'=>93,'alertTitle'=>'Aucun incident majeur','alertText'=>'1 arrêt en cours à Treichville.'];
    }
}
