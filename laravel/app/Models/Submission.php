<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = ['name', 'email', 'message'];
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
