<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ClientPaymentMethod extends Model
{
    /**
     * Opérateurs Mobile Money autorisés (must match ClientAccountController validation).
     */
    public const OPERATORS = ['orange', 'mtn', 'wave', 'moov', 'card'];

    /**
     * Libellés des opérateurs pour l'affichage.
     */
    public const OPERATOR_LABELS = [
        'orange' => 'Orange Money',
        'mtn'    => 'MTN MoMo',
        'wave'   => 'Wave',
        'moov'   => 'Moov Money',
        'card'   => 'Carte bancaire',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'operator',
        'account_name',
        'phone',
        'last_used_at',
        'is_default',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_default'   => 'boolean',
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
    ];

    /* =====================================================================
       RELATIONS
    ===================================================================== */

    /**
     * Propriétaire du moyen de paiement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* =====================================================================
       SCOPES
    ===================================================================== */

    /**
     * Retourne d'abord le moyen de paiement par défaut.
     */
    public function scopeDefaultFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->latest();
    }

    /**
     * Filtrer par opérateur.
     */
    public function scopeForOperator(Builder $query, string $operator): Builder
    {
        return $query->where('operator', $operator);
    }

    /* =====================================================================
       ACCESSORS / HELPERS
    ===================================================================== */

    /**
     * Libellé lisible de l'opérateur (ex: "Orange Money").
     */
    public function getOperatorLabelAttribute(): string
    {
        return self::OPERATOR_LABELS[$this->operator] ?? ucfirst($this->operator);
    }

    /**
     * Numéro masqué pour l'affichage (ex: "07 XX XX 00").
     */
    public function getMaskedPhoneAttribute(): string
    {
        return preg_replace('/(\d{2})\d+(\d{2})/', '$1 XX XX $2', $this->phone ?? '');
    }

    /**
     * Marque ce moyen de paiement comme utilisé maintenant.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Définit ce moyen de paiement comme celui par défaut
     * (et désactive les autres pour le même utilisateur).
     */
    public function setAsDefault(): void
    {
        static::where('user_id', $this->user_id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
