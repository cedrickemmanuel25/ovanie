<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsPilotageNotification extends Model
{
    protected $guarded = [];
    protected $casts = ['occurred_at'=>'datetime','is_read'=>'boolean','meta'=>'array'];
}
