<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StaffWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->whereIn('role', config('staff.managed_roles', ['logistique', 'support', 'commercial']))
            ->orderBy('id')
            ->each(function (User $user): void {
                $department = $user->role;
                $prefix = match ($department) {
                    'logistique' => 'LOG',
                    'support' => 'SUP',
                    'commercial' => 'COM',
                    default => 'STA',
                };
                $jobTitle = match ($department) {
                    'logistique' => 'Agent logistique',
                    'support' => 'Agent support',
                    'commercial' => 'Conseiller commercial',
                    default => 'Collaborateur OVANIE',
                };

                $user->staffProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'department' => $department,
                        'employee_code' => $user->staffProfile?->employee_code
                            ?: $prefix.'-'.Str::padLeft((string) $user->id, 5, '0'),
                        'job_title' => $user->staffProfile?->job_title ?: $jobTitle,
                        'permissions' => array_values(array_unique(array_merge(
                            $user->staffProfile?->permissions ?? [],
                            config('staff.role_permissions.'.$department, [])
                        ))),
                        'is_active' => $user->status === 'active',
                    ]
                );
            });

        $this->call(SupportAiCenterSeeder::class);
    }
}
