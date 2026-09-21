<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialProspectingMissionQuarter extends Model
{
    protected $fillable = [
        'mission_id',
        'quarter_id',
        'updated_by_commercial_id',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mission()
    {
        return $this->belongsTo(CommercialProspectingMission::class, 'mission_id');
    }

    public function quarter()
    {
        return $this->belongsTo(AbidjanQuarter::class, 'quarter_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_commercial_id');
    }
}
