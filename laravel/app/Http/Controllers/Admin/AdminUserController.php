<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    /**
     * Affiche les comptes utilisateurs avec filtres, statistiques et pagination.
     */
    public function index(Request $request): View
    {
        $internalRoles = config('staff.internal_roles', ['logistique', 'support', 'commercial']);

        $query = User::query()
            ->with(['shop', 'staffProfile'])
            ->withCount('orders');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            if ($role === 'internal') {
                $query->where(function ($builder) use ($internalRoles) {
                    $builder->whereIn('role', $internalRoles)
                        ->orWhere('is_admin', true);
                });
            } else {
                $query->where('role', $role);
            }
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        match ($request->query('sort', 'recent')) {
            'oldest' => $query->oldest('created_at'),
            'name' => $query->orderBy('name'),
            default => $query->latest('created_at'),
        };

        $users = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', 'active')->count(),
            'clients' => User::query()->where('role', 'client')->count(),
            'vendors' => User::query()->where('role', 'vendor')->count(),
            'internal' => User::query()
                ->where(function ($builder) use ($internalRoles) {
                    $builder->whereIn('role', $internalRoles)
                        ->orWhere('is_admin', true);
                })
                ->count(),
        ];

        $roleLabels = [
            'client' => 'Client',
            'vendor' => 'Vendeur',
            'admin' => 'Administrateur',
            'logistique' => 'Logistique',
            'support' => 'Support',
            'commercial' => 'Commercial',
        ];

        return view('admin.users.index', compact(
            'users',
            'stats',
            'roleLabels',
            'internalRoles'
        ));
    }

    /**
     * Crée un compte depuis l'administration.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['client', 'vendor', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['role'],
            'status' => $validated['status'],
            'is_admin' => $validated['role'] === 'admin',
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Le compte utilisateur a été créé avec succès.');
    }

    /**
     * Met à jour un compte non géré par l'espace Équipe interne.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $internalRoles = config('staff.internal_roles', ['logistique', 'support', 'commercial']);

        if (in_array($user->role, $internalRoles, true)) {
            return back()->withErrors([
                'user' => 'Ce collaborateur doit être modifié depuis la page Équipe interne.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['client', 'vendor', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ((int) auth('admin')->id() === (int) $user->id && $validated['status'] !== 'active') {
            return back()->withErrors([
                'status' => 'Vous ne pouvez pas suspendre votre propre compte administrateur.',
            ]);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['role'],
            'status' => $validated['status'],
            'is_admin' => $validated['role'] === 'admin',
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Le compte utilisateur a été mis à jour.');
    }

    /**
     * Anonymise uniquement les comptes clients. Les vendeurs et comptes internes
     * doivent être suspendus afin de préserver les historiques métier.
     */
    public function destroy(User $user, AccountDeletionService $deletionService): RedirectResponse
    {
        if ((int) auth('admin')->id() === (int) $user->id) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        if ($user->role !== 'client') {
            return back()->withErrors([
                'user' => 'Seuls les comptes clients peuvent être anonymisés depuis cette page. Suspendez les autres comptes.',
            ]);
        }

        $deletionService->anonymize($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Le compte client a été anonymisé et suspendu.');
    }
}
