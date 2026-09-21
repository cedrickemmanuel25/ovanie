<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialProspect extends Model
{
    protected $fillable = ['mission_id','area_id','commune_id','quarter_id','locality_id','discovered_by_commercial_id','vendor_user_id','shop_id','business_name','category','contact_name','phone','whatsapp','address','landmark','latitude','longitude','status','potential','notes','last_visited_at','next_follow_up_at','converted_at'];
    protected $casts = ['latitude'=>'decimal:7','longitude'=>'decimal:7','last_visited_at'=>'datetime','next_follow_up_at'=>'datetime','converted_at'=>'datetime'];

    public function mission(){ return $this->belongsTo(CommercialProspectingMission::class, 'mission_id'); }
    public function area(){ return $this->belongsTo(CommercialProspectingArea::class, 'area_id'); }
    public function commune(){ return $this->belongsTo(AbidjanCommune::class); }
    public function quarter(){ return $this->belongsTo(AbidjanQuarter::class); }
    public function locality(){ return $this->belongsTo(AbidjanLocality::class); }
    public function commercial(){ return $this->belongsTo(User::class, 'discovered_by_commercial_id'); }
    public function vendor(){ return $this->belongsTo(User::class, 'vendor_user_id'); }
    public function shop(){ return $this->belongsTo(Shop::class); }
    public function visits(){ return $this->hasMany(CommercialProspectingVisit::class, 'prospect_id')->latest('visited_at'); }
}
