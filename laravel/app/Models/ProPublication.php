<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Offre ou besoin publié par un compte simple OVANIE PRO. */
class ProPublication extends Model
{
    public const KINDS = ['offer' => 'offre', 'need' => 'besoin'];

    protected $guarded = ['id', 'pro_user_id', 'kind', 'status', 'published_at'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'min_order_quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'price_negotiable' => 'boolean',
        'price_on_request' => 'boolean',
        'quantity_to_define' => 'boolean',
        'alternatives_accepted' => 'boolean',
        'expires_on' => 'date',
        'needed_by' => 'date',
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(ProUser::class, 'pro_user_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function files()
    {
        return $this->hasMany(ProPublicationFile::class)->orderByDesc('is_main')->orderBy('sort_order');
    }

    public function photos()
    {
        return $this->files()->where('type', 'photo');
    }

    public function documents()
    {
        return $this->files()->where('type', 'document');
    }

    /** Annonces visibles du public : publiées et pas encore arrivées à échéance. */
    public function scopeVisible($query)
    {
        return $query->where('status', 'published')
            ->where(function ($open) {
                $open->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString());
            });
    }

    /** Référence lisible, à citer pour une mise en relation : OFF-00012 ou BES-00012. */
    public function getPublicRefAttribute(): string
    {
        return ($this->kind === 'need' ? 'BES-' : 'OFF-').str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** Prix d'une offre ou budget d'un besoin, en clair. */
    public function getAmountLabelAttribute(): string
    {
        $format = fn ($value) => number_format((float) $value, 0, ',', ' ').' '.($this->currency === 'XOF' || ! $this->currency ? 'FCFA' : $this->currency);

        if ($this->kind === 'need') {
            if ($this->budget_min !== null && $this->budget_max !== null) {
                return $format($this->budget_min).' à '.$format($this->budget_max);
            }
            if ($this->budget_max !== null || $this->budget_min !== null) {
                return $format($this->budget_max ?? $this->budget_min);
            }

            return 'Budget à discuter';
        }

        if ($this->price !== null && ! $this->price_on_request) {
            return $format($this->price).($this->price_negotiable ? ' (négociable)' : '');
        }

        return 'Prix sur demande';
    }

    /** Quantité proposée ou recherchée, avec son unité. */
    public function getQuantityLabelAttribute(): string
    {
        if ($this->quantity_to_define || $this->quantity === null) {
            return 'À définir';
        }

        $unit = (string) (config('ovanie_pro.publication.units')[$this->unit] ?? $this->unit ?? '');
        $quantity = rtrim(rtrim(number_format((float) $this->quantity, 2, ',', ' '), '0'), ',');

        return trim($quantity.' '.mb_strtolower($unit));
    }

    /** offre | besoin : segment d'URL. */
    public function getSlugKindAttribute(): string
    {
        return self::KINDS[$this->kind] ?? 'offre';
    }

    public function getMainPhotoUrlAttribute(): ?string
    {
        $photo = $this->photos->first();

        return $photo ? Storage::disk($photo->disk)->url($photo->path) : null;
    }
}
