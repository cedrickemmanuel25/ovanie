<?php
namespace Tests\Support;

use App\Models\{Order,OrderItem,Product,Shop,DeliveryDriver};
use Illuminate\Pagination\LengthAwarePaginator;

/** In-memory models for visual verification; no production data or database writes. */
final class SupervisionReferenceData
{
    public static function make(): array
    {
        $shop=new Shop();$shop->forceFill(['id'=>1,'name'=>'KONE ELECTRO & PLOMBERIE','commune'=>'Marcory','address'=>'Boulevard Principal, Rue 14, Marcory','whatsapp'=>'+2250703003003','latitude'=>5.309,'longitude'=>-4.000]);
        $groups=collect();
        foreach ([['SHP-YU1PMJVJ','ORD-MPGPLIYK','Edwige KOUAME','Yopougon','ready_for_pickup',14,'Moto',5.336,-4.062],['SHP-4CWUIWYW','ORD-SSHUOZUL','Serge GNANGNAN','Abobo','in_transit',60,'Tricycle',5.42,-4.02],['SHP-ZYMZLUZF','ORD-8V6NJINM','Véronique GBEKE','Port-Bouët','in_transit',60,'Tricycle',5.26,-3.93]] as $i=>$values) {
            [$reference,$number,$client,$destination,$status,$weight,$vehicle,$lat,$lng]=$values;
            $order=new Order();$order->forceFill(['id'=>$i+1,'order_number'=>$number,'delivery_commune'=>$destination,'customer_name'=>$client,'delivery_latitude'=>$lat,'delivery_longitude'=>$lng,'payment_method'=>'Wave','payment_status'=>'paid','created_at'=>now()->setTime(10,14)]);
            $product=new Product();$product->forceFill(['id'=>1,'name'=>'Câble électrique 2.5mm² (100m)','weight_kg'=>12]);$product->setRelation('shop',$shop);
            $item=new OrderItem();$item->forceFill(['id'=>$i+1,'order_id'=>$i+1,'quantity'=>$i?5:1,'logistics_weight_kg'=>$weight,'created_at'=>now()->setTime(10,16)]);$item->setRelation('product',$product);$item->setRelation('order',$order);$item->setRelation('shipment',null);$item->setRelation('latestDeliveryAssignment',null);
            $lines=collect([$item]);
            if(!$i){$second=clone $item;$second->id=10;$second->quantity=4;$second->logistics_weight_kg=2;$part=new Product();$part->forceFill(['name'=>'Disjoncteur bipolaire 32A','weight_kg'=>.5]);$part->setRelation('shop',$shop);$second->setRelation('product',$part);$lines->push($second);$item->logistics_weight_kg=12;}
            $groups->push(['order'=>$order,'representative'=>$item,'items'=>$lines,'shops'=>collect([$shop]),'pickup_stops'=>collect([['shop'=>$shop]]),'mission_number'=>$reference,'client_name'=>$client,'weight'=>$weight,'volume'=>0,'article_count'=>$lines->sum('quantity'),'vehicle_label'=>$vehicle,'vehicle_code'=>strtolower($vehicle),'status'=>$status,'driver_name'=>null,'driver_id'=>null,'is_delayed'=>$i>0,'eta_at'=>$i?now()->subMinutes($i===1?28:42):null,'eta_label'=>'11:45']);
        }
        $drivers=collect();
        foreach([['Diallo Seydou','Marcory',4.8,7,31],['Diallo Seydou','Attécoubé',4.7,14,39],['Traoré Brice','Marcory',4.5,18,34]] as $i=>$values){$driver=new DeliveryDriver();$driver->forceFill(['id'=>$i+1,'name'=>$values[0],'zone'=>$values[1],'rating'=>$values[2],'vehicle'=>$i===2?'Moto Tokyo (5567-GJ01)':'Moto Yamaha (3301-AB01)','is_online'=>true,'status'=>$i===2?'En livraison':'Disponible']);$driver->compatible=true;$driver->busy=$i===2;$driver->eta_collecte_min=$values[3];$driver->eta_client_min=$values[4];$drivers->push($driver);}
        $group=$groups->first();
        return ['group'=>$group,'shipmentGroup'=>$group,'rep'=>$group['representative'],'item'=>$group['representative'],'order'=>$group['order'],'drivers'=>$drivers,'activeDrivers'=>$drivers,'bestDriverId'=>1,'minimumPickupValue'=>now()->format('Y-m-d').'T08:00','pickupScheduledAt'=>now()->format('Y-m-d').'T10:45','deliveryScheduledAt'=>now()->format('Y-m-d').'T11:16','plannedAssignment'=>null,'deliveryAddress'=>'Yopougon, Abidjan','missions'=>new LengthAwarePaginator($groups->slice(1)->values(),2,8,1,['path'=>route('logistics.active-deliveries')]),'zones'=>collect(['Abobo','Port-Bouët']),'stats'=>['active'=>2,'route'=>2,'delayed'=>2,'drivers_active'=>15,'retards'=>4],'ovanieShops'=>[['name'=>$shop->name,'lat'=>5.309,'lng'=>-4.000]],'missionsToAssign'=>$groups->take(1),'missionsEnRoute'=>$groups->slice(1),'readyToAssignCount'=>2,'incidentsOpenCount'=>1,'deliveredTodayCount'=>5,'driversOnlineCount'=>15,'driversTotalCount'=>30,'driversBusyCount'=>7,'driversAvailableCount'=>13,'driversOfflineCount'=>10,'recentIncidents'=>collect(),'recentReturns'=>collect()];
    }
    public static function dashboard(): array
    {
        $data=self::make();$data['drivers']=$data['drivers']->map(fn($d)=>['name'=>$d->name,'vehicle'=>$d->vehicle,'zone'=>$d->zone,'status'=>$d->status]);return $data;
    }
}
