<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPilotageSnapshot extends Model
{
    protected $guarded = [];
    protected $casts = [
        'period_start'=>'date','period_end'=>'date','kpis'=>'array','delivery_evolution'=>'array','cost_distribution'=>'array',
        'punctuality_evolution'=>'array','driver_performance'=>'array','top_zones'=>'array','latest_incidents'=>'array','latest_returns'=>'array','key_indicators'=>'array',
    ];
}
