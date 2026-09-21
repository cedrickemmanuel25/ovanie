<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('shops')) {
            return;
        }

        // Toute identité propriétaire d'une boutique existante est une identité
        // vendeur. Elle conserve malgré tout l'accès aux fonctionnalités client.
        DB::table('users')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('shops')
                    ->whereColumn('shops.user_id', 'users.id');
            })
            ->where(function ($query) {
                $query->whereNull('is_admin')->orWhere('is_admin', false);
            })
            ->whereNotIn('role', config('staff.internal_roles', ['admin', 'logistique', 'support', 'commercial']))
            ->update(['role' => 'vendor']);
    }

    public function down(): void
    {
        // Pas de rétro-conversion automatique : impossible de déterminer de façon
        // fiable quels anciens propriétaires de boutique étaient historiquement clients.
    }
};
