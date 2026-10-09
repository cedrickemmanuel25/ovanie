<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Document de vérification d'une organisation OVANIE PRO (privé par défaut, RG-09). */
class ProDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'pro_organization_id',
        'uploaded_by_pro_user_id',
        'document_type',
        'label',
        'path',
        'original_name',
        'mime_type',
        'size',
        'status',
    ];

    public function organization()
    {
        return $this->belongsTo(ProOrganization::class, 'pro_organization_id');
    }

    public function uploader()
    {
        return $this->belongsTo(ProUser::class, 'uploaded_by_pro_user_id');
    }
}
