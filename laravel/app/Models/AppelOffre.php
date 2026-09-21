<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppelOffre extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'services' => '[]',
    ];

    protected $table = 'appel_offres';

    protected $fillable = [
        'user_id',
        'company_id',
        'secteur',
        'category', // ✅ AJOUTE ÇA
        'services',
        'prenom',
        'nom',
        'email',
        'telephone',
        'pays',
        'ville',
        'image',
        'budget',
        'delai',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'services' => 'array', // Pour que le JSON soit automatiquement converti en tableau PHP
        'budget' => 'decimal:2',
        'delai' => 'integer',
    ];

    public function secteurRelation()
    {
        return $this->belongsTo(ActivitySector::class, 'secteur');
    }
    public function commercialLeads()
    {
        return $this->hasMany(CommercialLead::class);
    }
}
