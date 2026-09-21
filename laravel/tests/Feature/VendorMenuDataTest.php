<?php
namespace Tests\Feature;
use App\Models\{User,Shop,Product,Category,Review,ReturnModel,Dispute,Order,OrderItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class VendorMenuDataTest extends TestCase {
 use RefreshDatabase;
 private function vendor(): array {
  $u=User::factory()->create(['role'=>'vendor']);
  $s=Shop::create(['user_id'=>$u->id,'name'=>'Boutique '.uniqid(),'slug'=>'shop-'.uniqid(),'seller_type'=>'particulier','city'=>'Abidjan','commune'=>'Cocody','address'=>'Cocody','identity_type'=>'cni','identity_number'=>uniqid(),'status'=>'approved','is_active'=>true]);
  $c=Category::create(['name'=>'Cat '.uniqid(),'slug'=>uniqid(),'status'=>'actif']);
  $p=Product::create(['shop_id'=>$s->id,'vendor_id'=>$u->id,'category_id'=>$c->id,'name'=>'Produit réel','slug'=>uniqid(),'price'=>2500,'stock'=>5,'status'=>'approved','is_active'=>true]);
  return [$u,$s,$p];
 }
 public function test_reviews_are_scoped_and_reply_persists_only_for_owner(): void {
  [$u,$s,$p]=$this->vendor();[$other,$otherShop,$otherProduct]=$this->vendor();
  $r=Review::create(['product_id'=>$p->id,'user_id'=>$other->id,'rating'=>5,'comment'=>'Avis réel']);
  $foreign=Review::create(['product_id'=>$otherProduct->id,'user_id'=>$u->id,'rating'=>1,'comment'=>'Privé autre boutique']);
  $this->actingAs($u,'sanctum')->getJson('/api/mobile/v1/vendor/reviews')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('summary.average',5);
  $this->postJson('/api/mobile/v1/vendor/reviews/'.$r->id.'/reply',['reply'=>'Merci'])->assertOk();
  $this->assertSame('Merci',$r->fresh()->vendor_reply);
  $this->postJson('/api/mobile/v1/vendor/reviews/'.$foreign->id.'/reply',['reply'=>'Interdit'])->assertNotFound();
 }
 public function test_shop_presentation_and_profile_preserve_protected_fields(): void {
  [$u,$s]=$this->vendor();$this->actingAs($u,'sanctum');
  $this->postJson('/api/mobile/v1/vendor/shop/presentation',['name'=>'Nouveau nom','description'=>'Description réelle','business_phone'=>'0701234567','main_category'=>'quincaillerie'])->assertOk();
  $this->assertSame('Nouveau nom',$s->fresh()->name);
  $this->assertSame('0701234567',json_decode($s->fresh()->mobile_presentation,true)['business_phone']);
  $this->patchJson('/api/mobile/v1/vendor/profile',['name'=>'Titulaire réel','role'=>'admin'])->assertOk();
  $this->assertSame('vendor',$u->fresh()->role);
  $this->assertSame('Titulaire réel',$u->fresh()->name);
 }
 public function test_foreign_cases_and_notifications_cannot_be_read(): void {
  [$u]=$this->vendor();[$other,$shop]=$this->vendor();
  $r=ReturnModel::create(['shop_id'=>$shop->id,'client_id'=>$other->id,'reason'=>'Test','status'=>'pending','order_reference'=>'REF','product_name'=>'Test','request_date'=>now()]);
  $this->actingAs($u,'sanctum')->getJson('/api/mobile/v1/vendor/returns/'.$r->id)->assertNotFound();
  $this->getJson('/api/mobile/v1/vendor/returns')->assertOk()->assertJsonCount(0,'data');
  $id=(string)\Illuminate\Support\Str::uuid();
  DB::table('notifications')->insert(['id'=>$id,'type'=>'test','notifiable_type'=>User::class,'notifiable_id'=>$other->id,'data'=>json_encode(['title'=>'Privé']),'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/api/mobile/v1/vendor/notifications/'.$id)->assertNotFound();
  $this->postJson('/api/mobile/v1/vendor/notifications/'.$id.'/read')->assertNotFound();
 }
 public function test_empty_statistics_do_not_invent_conversion_or_growth(): void {
  [$u]=$this->vendor();$this->actingAs($u,'sanctum')->getJson('/api/mobile/v1/vendor/statistics?from=2026-09-01&to=2026-09-07')->assertOk()->assertJsonPath('orders',0)->assertJsonPath('conversion',null)->assertJsonPath('growth.revenue',null)->assertJsonCount(7,'series');
 }
 public function test_statistics_use_paid_visible_shop_items_and_correct_period(): void {
  [$u,$shop,$product]=$this->vendor();[$other,$otherShop,$otherProduct]=$this->vendor();
  $order=Order::create(['order_number'=>'CMD-TEST-1','client_id'=>$other->id,'status'=>'confirmed','payment_status'=>'paid','total_amount'=>99999]);
  $order->created_at='2026-09-03 10:00:00';$order->save();
  OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'shop_id'=>$shop->id,'quantity'=>2,'price'=>2500,'subtotal'=>5000,'vendor_status'=>'pending','vendor_visible_at'=>now()]);
  OrderItem::create(['order_id'=>$order->id,'product_id'=>$otherProduct->id,'shop_id'=>$otherShop->id,'quantity'=>10,'price'=>9000,'subtotal'=>90000,'vendor_status'=>'pending','vendor_visible_at'=>now()]);
  OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'shop_id'=>$shop->id,'quantity'=>1,'price'=>100,'subtotal'=>100,'vendor_status'=>'pending','vendor_visible_at'=>null]);
  $this->actingAs($u,'sanctum')->getJson('/api/mobile/v1/vendor/statistics?from=2026-09-01&to=2026-09-07')->assertOk()->assertJsonPath('revenue',5000)->assertJsonPath('orders',1)->assertJsonPath('average',5000)->assertJsonPath('top_products.0.quantity',2)->assertJsonPath('series.2.amount',5000);
 }
 public function test_preparation_schedule_persists_and_rejects_invalid_times(): void {
  [$u,$shop]=$this->vendor();$this->actingAs($u,'sanctum');
  $data=['processing_time'=>'24_48h','days'=>[1,2,3],'start'=>'08:00','end'=>'18:00'];
  $this->postJson('/api/mobile/v1/vendor/shop/preparation-settings',$data)->assertOk();
  $this->assertSame([1,2,3],json_decode($shop->fresh()->mobile_presentation,true)['preparation']['days']);
  $this->postJson('/api/mobile/v1/vendor/shop/preparation-settings',array_replace($data,['end'=>'07:00']))->assertUnprocessable();
 }

}
