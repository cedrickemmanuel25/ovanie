<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    public const DEPARTMENT_SUPPORT = 'support';
    public const DEPARTMENT_COMMERCIAL = 'commercial';
    public const DEPARTMENT_LOGISTICS = 'logistique';

    protected $fillable = [
        'user_id', 'department', 'employee_code', 'job_title', 'manager_id',
        'phone_extension', 'permissions', 'is_active', 'last_seen_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }


    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
