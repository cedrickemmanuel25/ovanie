<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRequest extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'sector',
        'budget',
        'status',
        'parent_id', // il faut ajouter ce champ dans ta table pour gérer la hiérarchie
        'category',  // ajouté
        'type',      // ajouté
    ];

    /**
     * Relation vers l'utilisateur (client ou vendeur) qui a fait la demande
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sous-demandes (relation récursive)
     */
    public function subRequests()
    {
        return $this->hasMany(BusinessRequest::class, 'parent_id');
    }

    /**
     * Demande parente (relation récursive inverse)
     */
    public function parentRequest()
    {
        return $this->belongsTo(BusinessRequest::class, 'parent_id');
    }
    public function commercialLeads()
    {
        return $this->hasMany(CommercialLead::class);
    }
}
