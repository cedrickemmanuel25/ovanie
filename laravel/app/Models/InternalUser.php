<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Identité interne OVANIE : Administration, Logistique, Support ou Commercial.
 */
class InternalUser extends User
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
        static::addGlobalScope('internal-account', function (Builder $query) {
            $query->whereIn(
                'role',
                config('staff.internal_roles', ['admin', 'logistique', 'support', 'commercial'])
            );
        });
    }
}
