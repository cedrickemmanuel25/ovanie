<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    // Champs assignables en masse
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    // Champs cachés pour les arrays / JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Si tu veux caster certains champs
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
