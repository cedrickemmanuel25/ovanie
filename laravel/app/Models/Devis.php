<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Devis extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'activites' => '[]',
    ];

    protected $table = 'devis';

    protected $fillable = [
        'user_id',
        'company_id',
        'secteur',
        'category', // ✅ AJOUTE ÇA
        'activites', // ajouté
        'prenom',
        'nom',
        'email',
        'telephone',
        'pays',
        'ville',
        'image_path',
        'budget',
        'projet',
        'message',
    ];

    protected $casts = [
        'activites' => 'array', // permet de stocker/recevoir JSON automatiquement
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function commercialLeads()
    {
        return $this->hasMany(CommercialLead::class);
    }
}
