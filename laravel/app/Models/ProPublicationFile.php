<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Photo (publique) ou document (privé) joint à une publication. */
class ProPublicationFile extends Model
{
    protected $fillable = ['pro_publication_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size', 'is_main', 'sort_order'];

    protected $casts = ['is_main' => 'boolean'];

    public function publication()
    {
        return $this->belongsTo(ProPublication::class, 'pro_publication_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->type === 'photo' ? Storage::disk($this->disk)->url($this->path) : null;
    }
}
