<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Identité publique OVANIE : uniquement Client ou Vendeur.
 *
 * Le rôle est la frontière d'authentification. Un identifiant public ne peut
 * donc jamais être rechargé par le guard du personnel interne.
 */
class PublicUser extends User
{
    protected $table = 'users';

    /**
     * Les modèles InternalUser/PublicUser partagent la table `users`.
     * Sans cette surcharge, Eloquent déduit `internal_user_id` ou
     * `public_user_id` pour les relations héritées de User.
     */
    public function getForeignKey(): string
    {
        return 'user_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('public-account', function (Builder $query) {
            $query->whereIn('role', ['client', 'vendor']);
        });
    }
}
