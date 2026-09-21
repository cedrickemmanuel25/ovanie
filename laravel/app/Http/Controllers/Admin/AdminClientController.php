<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminClientController extends Controller
{
    /**
     * Affiche le portefeuille clients de l'administration.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked', 'suspended'])],
            'source' => ['nullable', Rule::in(['commercial', 'direct'])],
            'sort' => ['nullable', Rule::in(['recent', 'oldest', 'name', 'orders'])],
        ]);

        $query = User::query()
            ->where('role', 'client')
            ->with(['commercialCreator:id,name,email'])
            ->withCount([
                'orders as orders_count' => fn (Builder $orders) => $orders->operational(),
            ])
            ->withSum([
                'orders as total_spent' => fn (Builder $orders) => $orders
                    ->operational()
                    ->where('status', '!=', 'cancelled'),
            ], 'total_amount');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $clientQuery) use ($search) {
                $clientQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('secondary_phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (($filters['source'] ?? null) === 'commercial') {
            $query->whereNotNull('created_by_commercial_id');
        }

        if (($filters['source'] ?? null) === 'direct') {
            $query->whereNull('created_by_commercial_id');
        }

        match ($filters['sort'] ?? 'recent') {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('name')->orderBy('email'),
            'orders' => $query->orderByDesc('orders_count')->latest('id'),
            default => $query->latest(),
        };

        $clients = $query
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => User::query()->where('role', 'client')->count(),
            'active' => User::query()->where('role', 'client')->where('status', 'active')->count(),
            'buyers' => User::query()
                ->where('role', 'client')
                ->whereHas('orders', fn (Builder $orders) => $orders->operational())
                ->count(),
            'new_this_month' => User::query()
                ->where('role', 'client')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        return view('admin.clients.index', compact('clients', 'stats'));
    }

    /**
     * Affiche la fiche complète d'un client.
     */
    public function show(int $id)
    {
        $client = User::query()
            ->where('role', 'client')
            ->with([
                'commercialCreator:id,name,email',
                'addresses' => fn ($addresses) => $addresses
                    ->orderByDesc('is_default')
                    ->latest(),
                'paymentMethods' => fn ($methods) => $methods->defaultFirst(),
            ])
            ->withCount(['favorites', 'addresses'])
            ->findOrFail($id);

        $operationalOrders = $client->orders()->operational();

        $recentOrders = (clone $operationalOrders)
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get();

        $stats = [
            'orders' => (clone $operationalOrders)->count(),
            'spent' => (float) (clone $operationalOrders)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'addresses' => (int) $client->addresses_count,
            'favorites' => (int) $client->favorites_count,
        ];

        $lastOrderAt = (clone $operationalOrders)->max('created_at');

        return view('admin.clients.show', compact(
            'client',
            'recentOrders',
            'stats',
            'lastOrderAt'
        ));
    }

    /**
     * Affiche le formulaire de modification d'un client.
     */
    public function edit(int $id)
    {
        $client = User::query()
            ->where('role', 'client')
            ->with('commercialCreator:id,name,email')
            ->findOrFail($id);

        return view('admin.clients.edit', compact('client'));
    }

    /**
     * Met à jour les informations administratives d'un client.
     */
    public function update(Request $request, int $id)
    {
        $client = User::query()
            ->where('role', 'client')
            ->findOrFail($id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked', 'suspended'])],
        ], [
            'first_name.required' => 'Le prénom ou le nom usuel est obligatoire.',
            'email.required' => 'L’adresse e-mail est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'status.required' => 'Le statut du compte est obligatoire.',
            'status.in' => 'Le statut sélectionné n’est pas autorisé.',
        ]);

        $fullName = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));

        $client->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'secondary_phone' => $validated['secondary_phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.clients.show', $client->id)
            ->with('success', 'La fiche client a été mise à jour.');
    }

    /**
     * Anonymise et suspend le compte conformément au service de suppression.
     */
    public function destroy(int $id, AccountDeletionService $accountDeletion)
    {
        $client = User::query()
            ->where('role', 'client')
            ->findOrFail($id);

        $accountDeletion->assertCanSelfDelete($client);
        $accountDeletion->anonymize($client);

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Le compte client a été anonymisé et suspendu.');
    }
}
