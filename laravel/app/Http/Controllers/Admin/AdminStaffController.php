<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternalLoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $roles = config('staff.managed_roles', ['logistique', 'support', 'commercial']);

        $query = User::query()
            ->with(['staffProfile.manager', 'latestInternalLoginLog'])
            ->whereIn('role', $roles)
            ->latest();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('staffProfile', fn ($profile) => $profile
                        ->where('employee_code', 'like', "%{$q}%")
                        ->orWhere('job_title', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return view('admin.staff.index', [
            'staffMembers' => $query->paginate(25)->withQueryString(),
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        return view('admin.staff.create', [
            'managers' => $this->managers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateStaff($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => $data['status'],
                'is_admin' => 0,
            ]);

            $user->staffProfile()->create([
                'department' => $data['role'],
                'employee_code' => $data['employee_code'],
                'job_title' => $data['job_title'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'phone_extension' => $data['phone_extension'] ?? null,
                'is_active' => $data['status'] === 'active',
                'permissions' => $this->normalizedPermissions($data),
            ]);
        });

        return redirect()->route('admin.staff.index')->with('success', 'Compte collaborateur créé.');
    }

    public function edit(User $staff)
    {
        $this->ensureManagedStaff($staff);
        $staff->load(['staffProfile', 'latestInternalLoginLog']);

        return view('admin.staff.edit', [
            'staff' => $staff,
            'managers' => $this->managers($staff->id),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->ensureManagedStaff($staff);
        $data = $this->validateStaff($request, $staff);

        DB::transaction(function () use ($staff, $data) {
            $staff->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'role' => $data['role'],
                'status' => $data['status'],
            ]);

            $staff->staffProfile()->updateOrCreate(['user_id' => $staff->id], [
                'department' => $data['role'],
                'employee_code' => $data['employee_code'],
                'job_title' => $data['job_title'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'phone_extension' => $data['phone_extension'] ?? null,
                'is_active' => $data['status'] === 'active',
                'permissions' => $this->normalizedPermissions($data),
            ]);
        });

        return redirect()->route('admin.staff.index')->with('success', 'Compte collaborateur mis à jour.');
    }

    public function updateStatus(Request $request, User $staff)
    {
        $this->ensureManagedStaff($staff);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        DB::transaction(function () use ($staff, $data) {
            $staff->update(['status' => $data['status']]);
            $staff->staffProfile()->updateOrCreate(
                ['user_id' => $staff->id],
                [
                    'department' => $staff->role,
                    'employee_code' => $staff->staffProfile?->employee_code ?: $this->generatedEmployeeCode($staff),
                    'is_active' => $data['status'] === 'active',
                    'permissions' => $staff->staffProfile?->permissions
                        ?? config('staff.role_permissions.'.$staff->role, []),
                ]
            );
        });

        return back()->with('success', 'Statut du compte mis à jour.');
    }

    public function resetPassword(Request $request, User $staff)
    {
        $this->ensureManagedStaff($staff);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $staff->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => null,
        ])->save();

        return back()->with('success', 'Mot de passe réinitialisé avec succès.');
    }

    public function destroy(User $staff)
    {
        $this->ensureManagedStaff($staff);

        DB::transaction(function () use ($staff) {
            $staff->delete();
        });

        return redirect()->route('admin.staff.index')->with('success', 'Compte collaborateur supprimé.');
    }

    public function loginLogs(Request $request)
    {
        $logs = InternalLoginLog::query()
            ->with('user:id,name,email,role')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->query('event')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->query('role')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->query('q'));
                $query->where(function ($builder) use ($q) {
                    $builder->where('attempted_email', 'like', "%{$q}%")
                        ->orWhere('ip_address', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%"));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.staff.login-logs', compact('logs'));
    }

    private function validateStaff(Request $request, ?User $staff = null): array
    {
        $profileId = $staff?->staffProfile?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:40', Rule::unique('users', 'phone')->ignore($staff?->id)],
            'role' => ['required', Rule::in(config('staff.managed_roles', []))],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'employee_code' => ['required', 'string', 'max:40', Rule::unique('staff_profiles', 'employee_code')->ignore($profileId)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$staff?->id]))],
            'phone_extension' => ['nullable', 'string', 'max:20'],
            'permissions_present' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('staff.permissions', [])))],
            'password' => $staff
                ? ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()]
                : ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);
    }

    private function managers(?int $exceptId = null)
    {
        return User::query()
            ->where(function ($query) {
                $query->where('is_admin', true)
                    ->orWhereIn('role', config('staff.internal_roles', []));
            })
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function ensureManagedStaff(User $staff): void
    {
        abort_unless(in_array($staff->role, config('staff.managed_roles', []), true), 404);
    }

    private function normalizedPermissions(array $data): array
    {
        $allowed = config('staff.role_permissions.'.$data['role'], []);
        $selected = array_key_exists('permissions_present', $data)
            ? array_values(array_unique($data['permissions'] ?? []))
            : $allowed;

        return array_values(array_intersect($selected, $allowed));
    }

    private function generatedEmployeeCode(User $staff): string
    {
        $prefix = match ($staff->role) {
            'logistique' => 'LOG',
            'support' => 'SUP',
            'commercial' => 'COM',
            default => 'STA',
        };

        return $prefix.'-'.str_pad((string) $staff->id, 5, '0', STR_PAD_LEFT);
    }
}
