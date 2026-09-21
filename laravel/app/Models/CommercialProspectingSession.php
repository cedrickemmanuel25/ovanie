<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialProspectingSession extends Model
{
    protected $fillable = ['area_id','commercial_id','status','started_at','ended_at','start_latitude','start_longitude','notes'];
    protected $casts = ['started_at'=>'datetime','ended_at'=>'datetime','start_latitude'=>'decimal:7','start_longitude'=>'decimal:7'];
    public function area(){ return $this->belongsTo(CommercialProspectingArea::class, 'area_id'); }
    public function commercial(){ return $this->belongsTo(User::class, 'commercial_id'); }
}
