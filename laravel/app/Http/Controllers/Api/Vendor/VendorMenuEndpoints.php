<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Models\{Review, OrderItem, ReturnModel, Dispute};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

trait VendorMenuEndpoints
{
    private function menuCases(Request $request, string $model) {
        $shop = $this->shopFor($request);
        // A dossier assigned to another shop must never leak through a mixed order.
        return $model::query()->where(function($q) use ($shop) {
            $q->where('shop_id', $shop->id)->orWhere(function($legacy) use ($shop) {
                $legacy->whereNull('shop_id')->whereHas('orderItem', fn($i)=>$this->visibleVendorItems($i, $shop));
            });
        });
    }
    public function menuOverview(Request $request) {
        $shop=$this->shopFor($request);
        return response()->json(['shop'=>$this->shopPayload($shop), 'user'=>$this->userPayload($request->user()), 'counts'=>[
            'orders'=>OrderItem::query()->where(fn($q)=>$this->visibleVendorItems($q,$shop))->whereIn('vendor_status',['pending','accepted','preparing'])->distinct()->count('order_id'),
            'notifications'=>$request->user()->unreadNotifications()->count(),
            'returns'=>$this->menuCases($request,ReturnModel::class)->whereIn('status',['pending','accepted'])->count(),
            'disputes'=>$this->menuCases($request,Dispute::class)->whereNotIn('status',['resolved','closed','rejected'])->count(),
        ]]);
    }
    public function personalProfile(Request $request) {
        $u=$request->user();
        return response()->json(['user'=>$u->only(['id','name','phone','email','whatsapp_phone','city','role','status','created_at']) + ['avatar_url'=>$u->avatar ? Storage::disk('public')->url($u->avatar) : null], 'address'=>$u->addresses()->orderByDesc('is_default')->first()?->only(['city','commune','quartier','country','address']), 'shop'=>$this->shopPayload($this->shopFor($request))]);
    }
    public function updatePersonalProfile(Request $request) {
        $u=$request->user();
        $data=$request->validate(['name'=>['sometimes','required','string','max:255'], 'phone'=>['sometimes','required','string','max:30',Rule::unique('users','phone')->ignore($u->id)], 'email'=>['sometimes','required','email','max:255',Rule::unique('users','email')->ignore($u->id)],'whatsapp_phone'=>['nullable','string','max:30'],'city'=>['nullable','string','max:100']]);
        if (isset($data['email']) && $data['email']!==$u->email) $u->email_verified_at=null;
        if (isset($data['whatsapp_phone']) && $data['whatsapp_phone']!==$u->whatsapp_phone) $u->whatsapp_verified_at=null;
        $u->fill($data)->save();
        return $this->personalProfile($request);
    }
    public function savePresentation(Request $request) {
        $shop=$this->shopFor($request);
        $d=$request->validate(['name'=>['required','string','max:255',Rule::unique('shops','name')->ignore($shop->id)],'description'=>['nullable','string','max:700'],'main_category'=>['nullable','string','max:100'],'whatsapp'=>['nullable','string','max:30'],'business_email'=>['nullable','email','max:255'],'business_phone'=>['nullable','string','max:30'],'logo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:3072'],'cover'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120']]);
        $presentation=json_decode($shop->getRawOriginal('mobile_presentation') ?? '{}',true) ?: [];
        $presentation['business_phone']=$d['business_phone']??null;
        if($request->hasFile('cover')) $presentation['cover_url']=Storage::disk('public')->url($request->file('cover')->store('shops/covers','public'));
        if($request->hasFile('logo')) $shop->logo=$request->file('logo')->store('shops/logos','public');
        $shop->fill(collect($d)->except(['logo','cover','business_phone'])->all());
        $shop->mobile_presentation=json_encode($presentation);
        $shop->save();
        return response()->json(['shop'=>$this->shopPayload($shop->fresh())]);
    }
    public function savePreparationSettings(Request $request) {
        $shop=$this->shopFor($request);
        $d=$request->validate(['logistics_type'=>['sometimes',Rule::in([$shop->logistics_type ?: 'ovanie'])],'processing_time'=>['required',Rule::in(['lt24h','24_48h','3_5j','7j_plus'])],'days'=>['required','array','min:1'],'days.*'=>['integer','between:1,7','distinct'],'start'=>['required','date_format:H:i'],'end'=>['required','date_format:H:i','after:start']]);
        $p=json_decode($shop->getRawOriginal('mobile_presentation')??'{}',true)?:[];
        $p['preparation']=collect($d)->only(['days','start','end'])->all();
        $shop->mobile_presentation=json_encode($p);
        $shop->processing_time=$d['processing_time'];
        $shop->save();
        app(\App\Services\SellerLogisticsValidator::class)->synchronizeShopStatus($shop->refresh());
        return response()->json(['shop'=>$this->shopPayload($shop->fresh())]);
    }
    public function reviews(Request $request) {
        $shop=$this->shopFor($request);
        $items=Review::whereHas('product',fn($q)=>$q->where('shop_id',$shop->id))->with(['user:id,name','product:id,name'])->latest()->get();
        $distribution=[];for($i=1;$i<=5;$i++)$distribution[$i]=$items->where('rating',$i)->count();
        return response()->json(['data'=>$items->map(fn($r)=>['id'=>$r->id,'rating'=>$r->rating,'comment'=>$r->comment,'client_name'=>$r->user?->name,'product_name'=>$r->product?->name,'created_at'=>$r->created_at,'reply'=>$r->vendor_reply,'replied_at'=>$r->vendor_replied_at]),'summary'=>['count'=>$items->count(),'average'=>$items->avg('rating'),'distribution'=>$distribution,'replied'=>$items->filter(fn($r)=>filled($r->vendor_reply))->count(),'this_month'=>$items->filter(fn($r)=>$r->created_at?->isCurrentMonth())->count()]]);
    }
    public function replyReview(Request $request, Review $review) {
        $shop=$this->shopFor($request);abort_unless((int)$review->product?->shop_id===(int)$shop->id,404);
        $d=$request->validate(['reply'=>['required','string','max:2000']]);
        $review->vendor_reply=$d['reply'];$review->vendor_replied_at=now();$review->save();
        return response()->json(['message'=>'RÃ©ponse enregistrÃ©e.']);
    }
    public function statistics(Request $request) {
        $request->validate(['from'=>['required','date_format:Y-m-d'],'to'=>['required','date_format:Y-m-d','after_or_equal:from']]);
        $from=Carbon::parse($request->input('from'))->startOfDay();$to=Carbon::parse($request->input('to'))->endOfDay();
        abort_if($from->diffInDays($to)>366,422,'SÃ©lectionnez une pÃ©riode de 366 jours maximum.');
        $shop=$this->shopFor($request);
        $base=OrderItem::query()->where(fn($q)=>$this->visibleVendorItems($q,$shop));
        $period=fn($a,$b)=>(clone $base)->whereHas('order',fn($q)=>$q->whereBetween('created_at',[$a,$b]))->with(['order.payments','product'])->get();
        $items=$period($from,$to);$days=(int)$from->diffInDays($to)+1;
        $previous=$period($from->copy()->subDays($days),$from->copy()->subSecond());
        $paid=fn($rows)=>$rows->filter(fn($i)=>$i->vendor_status!=='cancelled' && (in_array($i->order?->payment_status,['paid','escrow_held','released_to_vendor']) || $i->order?->payments->whereIn('status',['paid','success','completed','escrow_held','released_to_vendor'])->isNotEmpty()));
        $amount=fn($rows)=>round($rows->sum(fn($i)=>(float)($i->subtotal??($i->price*$i->quantity))),2);
        $sales=$paid($items);$old=$paid($previous);$total=$amount($sales);$oldTotal=$amount($old);
        $count=$items->pluck('order_id')->unique()->count();$oldCount=$previous->pluck('order_id')->unique()->count();
        $avg=$sales->isEmpty()?0:$total/$sales->pluck('order_id')->unique()->count();$oldAvg=$old->isEmpty()?0:$oldTotal/$old->pluck('order_id')->unique()->count();
        $growth=fn($a,$b)=>$b>0?round(($a-$b)/$b*100,1):null;
        $series=[];for($date=$from->copy();$date<=$to;$date->addDay()){$key=$date->toDateString();$rows=$items->filter(fn($i)=>$i->order?->created_at?->toDateString()===$key);$series[]=['date'=>$key,'amount'=>$amount($paid($rows)),'orders'=>$rows->pluck('order_id')->unique()->count()];}
        $statuses=$items->groupBy('order_id')->map(fn($rows)=>$this->vendorOrderStatus($rows))->countBy();
        $top=$sales->groupBy('product_id')->map(function($rows)use($amount){$p=$rows->first()->product;return ['id'=>$p?->id,'name'=>$p?->name,'image_url'=>$p?$this->productPayload($p)['image_url']??null:null,'quantity'=>$rows->sum('quantity'),'amount'=>$amount($rows)];})->sortByDesc('amount')->values();
        return response()->json(['revenue'=>$total,'orders'=>$count,'average'=>$avg,'conversion'=>null,'growth'=>['revenue'=>$growth($total,$oldTotal),'orders'=>$growth($count,$oldCount),'average'=>$growth($avg,$oldAvg)],'series'=>$series,'statuses'=>$statuses,'top_products'=>$top]);
    }
    private function caseSummary($m): array {
        $p=$m->orderItem?->product;
        return ['product_name'=>$p?->name??$m->product_name, 'client_name'=>$m->client?->name??$m->client_name, 'amount'=>$m->refund_amount??$m->orderItem?->subtotal, 'image_url'=>$p?($this->productPayload($p)['image_url']??null):null];
    }
    public function returnDetail(Request $request, int $caseId) {return $this->caseDetail($request,$caseId,false);}
    public function disputeDetail(Request $request, int $caseId) {return $this->caseDetail($request,$caseId,true);}
    private function caseDetail(Request $request,int $id,bool $dispute) {
        $m=$this->menuCases($request,$dispute?Dispute::class:ReturnModel::class)->with(['order','orderItem.product','client'])->findOrFail($id);
        $item=$m->orderItem;$product=$item?->product;
        $data=$dispute?$this->disputePayload($m):$this->returnPayload($m);
        $data['client']=['name'=>$m->client?->name??$m->client_name,'phone'=>$m->client?->phone,'city'=>$m->client?->city];
        $data['product']=['id'=>$product?->id,'name'=>$product?->name??$m->product_name,'image_url'=>$product?($this->productPayload($product)['image_url']??null):null,'quantity'=>$m->quantity??$item?->quantity,'price'=>$item?->price,'amount'=>$dispute?$item?->subtotal:$m->refund_amount];
        $data['reason']=$m->reason;$data['response']=$dispute?$m->response:$m->vendor_response;
        $data['timeline']=[];
        foreach(['created_at'=>'Demande crÃ©Ã©e','accepted_at'=>'Retour validÃ©','rejected_at'=>'Retour refusÃ©','responded_at'=>'RÃ©ponse vendeur envoyÃ©e','escalated_at'=>'Dossier transmis Ã  OVANIE','refund_prepared_at'=>'Remboursement prÃ©parÃ©','refunded_at'=>'Remboursement effectuÃ©','resolved_at'=>'Dossier rÃ©solu'] as $field=>$label){if($m->$field)$data['timeline'][]=['label'=>$label,'date'=>$m->$field];}
        $data['attachments']=[];
        if(!$dispute && filled($m->photo_proof))$data['attachments'][]=Storage::disk('public')->url($m->photo_proof);
        $data['can_decide']=!$dispute && $m->status==='pending';
        return response()->json(['case'=>$data]);
    }
    public function notificationDetail(Request $request,string $notification) {
        $item=$request->user()->notifications()->whereKey($notification)->firstOrFail();
        return response()->json(['notification'=>$this->notificationPayload($item)]);
    }
}
