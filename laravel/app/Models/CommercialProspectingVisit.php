<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialProspectingVisit extends Model
{
    protected $fillable = ['prospect_id','mission_id','commercial_id','session_id','outcome','notes','visited_at','next_follow_up_at','latitude','longitude'];
    protected $casts = ['visited_at'=>'datetime','next_follow_up_at'=>'datetime','latitude'=>'decimal:7','longitude'=>'decimal:7'];
    public function mission(){ return $this->belongsTo(CommercialProspectingMission::class, 'mission_id'); }
    public function prospect(){ return $this->belongsTo(CommercialProspect::class, 'prospect_id'); }
    public function commercial(){ return $this->belongsTo(User::class, 'commercial_id'); }
    public function session(){ return $this->belongsTo(CommercialProspectingSession::class, 'session_id'); }
}
