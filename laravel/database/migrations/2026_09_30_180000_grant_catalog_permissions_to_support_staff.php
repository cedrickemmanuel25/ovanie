<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le Support Center peut désormais ajouter et modifier les produits (sans suppression).
 * Les agents support existants ont une liste de permissions enregistrée : on y ajoute
 * catalog.products.read et catalog.products.write. L'administration peut les retirer au cas par cas.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['catalog.products.read', 'catalog.products.write'];

    public function up(): void
    {
        $this->update(fn (array $permissions) => array_values(array_unique(array_merge($permissions, self::PERMISSIONS))));
    }

    public function down(): void
    {
        $this->update(fn (array $permissions) => array_values(array_diff($permissions, self::PERMISSIONS)));
    }

    private function update(callable $transform): void
    {
        if (! Schema::hasTable('staff_profiles') || ! Schema::hasColumn('staff_profiles', 'permissions')) {
            return;
        }

        $profiles = DB::table('staff_profiles')
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->where('users.role', 'support')
            ->whereNotNull('staff_profiles.permissions')
            ->select('staff_profiles.id', 'staff_profiles.permissions')
            ->get();

        foreach ($profiles as $profile) {
            $permissions = json_decode((string) $profile->permissions, true);
            if (! is_array($permissions)) {
                continue;
            }
            DB::table('staff_profiles')->where('id', $profile->id)->update([
                'permissions' => json_encode($transform($permissions)),
            ]);
        }
    }
};
