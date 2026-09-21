<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialProspectingArea extends Model
{
    protected $fillable = ['commune_id','quarter_id','locality_id','priority','potential_score','target_types','status','last_prospected_at','next_recommended_at','notes','is_active'];
    protected $casts = ['target_types'=>'array','last_prospected_at'=>'datetime','next_recommended_at'=>'datetime','is_active'=>'boolean','potential_score'=>'integer'];

    public function commune(){ return $this->belongsTo(AbidjanCommune::class); }
    public function quarter(){ return $this->belongsTo(AbidjanQuarter::class); }
    public function locality(){ return $this->belongsTo(AbidjanLocality::class); }
    public function prospects(){ return $this->hasMany(CommercialProspect::class, 'area_id'); }
    public function sessions(){ return $this->hasMany(CommercialProspectingSession::class, 'area_id'); }
}
