@extends('admin.layouts.app')

@section('title', 'Gestion des utilisateurs | Admin OVANIE')
@section('page-title', 'Gestion des utilisateurs')

@php
    /*
     |--------------------------------------------------------------------------
     | Sécurité de rendu
     |--------------------------------------------------------------------------
     | La page doit normalement être alimentée par AdminUserController@index.
     | Ce fallback évite toutefois une erreur 500 si une ancienne route Closure
     | charge encore directement la vue sans lui transmettre ses variables.
     */
    $internalRoles = $internalRoles ?? config('staff.internal_roles', ['logistique', 'support', 'commercial']);

    $roleLabels = $roleLabels ?? [
        'client' => 'Client',
        'vendor' => 'Vendeur',
        'admin' => 'Administrateur',
        'logistique' => 'Logistique',
        'support' => 'Support',
        'commercial' => 'Commercial',
    ];

    if (!isset($users)) {
        $fallbackQuery = \App\Models\User::query();

        if (method_exists(\App\Models\User::class, 'shop')) {
            $fallbackQuery->with('shop');
        }

        if (method_exists(\App\Models\User::class, 'staffProfile')) {
            $fallbackQuery->with('staffProfile');
        }

        if (method_exists(\App\Models\User::class, 'orders')) {
            $fallbackQuery->withCount('orders');
        }

        if ($search = trim((string) request('q'))) {
            $fallbackQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = request('role')) {
            if ($role === 'internal') {
                $fallbackQuery->where(function ($query) use ($internalRoles) {
                    $query->whereIn('role', $internalRoles)->orWhere('is_admin', true);
                });
            } else {
                $fallbackQuery->where('role', $role);
            }
        }

        if ($status = request('status')) {
            $fallbackQuery->where('status', $status);
        }

        match (request('sort', 'recent')) {
            'oldest' => $fallbackQuery->oldest('created_at'),
            'name' => $fallbackQuery->orderBy('name'),
            default => $fallbackQuery->latest('created_at'),
        };

        $users = $fallbackQuery->paginate(15)->withQueryString();
    }

    $stats = $stats ?? [
        'total' => \App\Models\User::query()->count(),
        'active' => \App\Models\User::query()->where('status', 'active')->count(),
        'clients' => \App\Models\User::query()->where('role', 'client')->count(),
        'vendors' => \App\Models\User::query()->where('role', 'vendor')->count(),
        'internal' => \App\Models\User::query()
            ->where(function ($query) use ($internalRoles) {
                $query->whereIn('role', $internalRoles)->orWhere('is_admin', true);
            })
            ->count(),
    ];

    $formMode = old('form_mode', 'create');
    $formUserId = old('user_id');
    $mustOpenForm = $errors->any() && old('form_context') === 'user-form';
    $currentAdminId = (int) auth('admin')->id();

    $statusLabels = [
        'active' => 'Actif',
        'suspended' => 'Suspendu',
    ];

    $userFormOldData = [
        'mode' => $formMode,
        'user_id' => $formUserId,
        'name' => old('name'),
        'email' => old('email'),
        'phone' => old('phone'),
        'role' => old('role'),
        'status' => old('status'),
    ];
@endphp

@section('content')
<main class="users-admin-page">
    <section class="users-hero">
        <div class="users-hero__content">
            <p class="users-eyebrow">COMPTES ET ACCÈS</p>
            <h1>Gestion des utilisateurs</h1>
            <p>
                Consultez les comptes clients, vendeurs et administrateurs, contrôlez leur statut
                et accédez séparément à la gestion de l’équipe interne.
            </p>
        </div>

        <div class="users-hero__actions">
            @if(Route::has('admin.staff.index'))
                <a href="{{ route('admin.staff.index') }}" class="users-btn users-btn--secondary">
                    Équipe interne
                </a>
            @endif
            <button type="button" class="users-btn users-btn--primary" id="openCreateUserModal">
                + Ajouter un utilisateur
            </button>
        </div>
    </section>

    @if(session('success'))
        <div class="users-alert users-alert--success">{{ session('success') }}</div>
    @endif

    @if($errors->any() && old('form_context') !== 'user-form')
        <div class="users-alert users-alert--error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="users-stats-grid">
        <article class="users-stat-card">
            <span class="users-stat-card__icon users-stat-card__icon--blue">U</span>
            <div>
                <span class="users-stat-card__label">Total utilisateurs</span>
                <strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong>
                <small>Tous les comptes enregistrés</small>
            </div>
        </article>

        <article class="users-stat-card">
            <span class="users-stat-card__icon users-stat-card__icon--green">✓</span>
            <div>
                <span class="users-stat-card__label">Comptes actifs</span>
                <strong>{{ number_format($stats['active'], 0, ',', ' ') }}</strong>
                <small>Accès autorisé à la plateforme</small>
            </div>
        </article>

        <article class="users-stat-card">
            <span class="users-stat-card__icon users-stat-card__icon--orange">C</span>
            <div>
                <span class="users-stat-card__label">Clients</span>
                <strong>{{ number_format($stats['clients'], 0, ',', ' ') }}</strong>
                <small>Comptes acheteurs</small>
            </div>
        </article>

        <article class="users-stat-card">
            <span class="users-stat-card__icon users-stat-card__icon--purple">V</span>
            <div>
                <span class="users-stat-card__label">Vendeurs</span>
                <strong>{{ number_format($stats['vendors'], 0, ',', ' ') }}</strong>
                <small>Comptes liés aux boutiques</small>
            </div>
        </article>

        <article class="users-stat-card">
            <span class="users-stat-card__icon users-stat-card__icon--slate">I</span>
            <div>
                <span class="users-stat-card__label">Équipe interne</span>
                <strong>{{ number_format($stats['internal'], 0, ',', ' ') }}</strong>
                <small>Admin, Support, Commercial, Logistique</small>
            </div>
        </article>
    </section>

    <section class="users-filter-card">
        <div class="users-section-heading">
            <div>
                <p class="users-eyebrow">RECHERCHE ET FILTRES</p>
                <h2>Retrouver un utilisateur</h2>
            </div>
            @if(request()->hasAny(['q', 'role', 'status', 'sort']))
                <a href="{{ route('admin.users.index') }}" class="users-reset-link">Réinitialiser</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="users-filter-form">
            <div class="users-field users-field--search">
                <label for="q">Nom, email ou téléphone</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Ex. Kouamé, client@ovanie.com...">
            </div>

            <div class="users-field">
                <label for="role">Profil</label>
                <select id="role" name="role">
                    <option value="">Tous les profils</option>
                    <option value="client" @selected(request('role') === 'client')>Clients</option>
                    <option value="vendor" @selected(request('role') === 'vendor')>Vendeurs</option>
                    <option value="admin" @selected(request('role') === 'admin')>Administrateurs</option>
                    <option value="internal" @selected(request('role') === 'internal')>Équipe interne</option>
                </select>
            </div>

            <div class="users-field">
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(request('status') === 'active')>Actif</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspendu</option>
                </select>
            </div>

            <div class="users-field">
                <label for="sort">Classement</label>
                <select id="sort" name="sort">
                    <option value="recent" @selected(request('sort', 'recent') === 'recent')>Plus récents</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                    <option value="name" @selected(request('sort') === 'name')>Nom A–Z</option>
                </select>
            </div>

            <button type="submit" class="users-btn users-btn--filter">Rechercher</button>
        </form>
    </section>

    <section class="users-list-card">
        <div class="users-list-card__header">
            <div>
                <p class="users-eyebrow">ANNUAIRE DES COMPTES</p>
                <h2>Utilisateurs enregistrés</h2>
                <span>{{ $users->total() }} résultat(s)</span>
            </div>
        </div>

        <div class="users-table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Contact</th>
                        <th>Profil</th>
                        <th>Statut</th>
                        <th>Activité</th>
                        <th>Inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    @php
                        $isInternal = in_array($user->role, $internalRoles, true);
                        $isAdminAccount = (bool) $user->is_admin || $user->role === 'admin';
                        $roleKey = $isAdminAccount ? 'admin' : $user->role;
                        $roleLabel = $roleLabels[$roleKey] ?? ucfirst((string) $roleKey);
                        $displayName = trim((string) ($user->name ?: $user->full_name)) ?: 'Utilisateur sans nom';
                        $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                    @endphp
                    <tr>
                        <td data-label="Utilisateur">
                            <div class="users-person">
                                <div class="users-avatar">{{ $initial }}</div>
                                <div class="users-person__text">
                                    <strong>{{ $displayName }}</strong>
                                    <span>#{{ $user->id }}</span>
                                    @if($user->id === $currentAdminId)
                                        <em>Compte actuel</em>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td data-label="Contact">
                            <div class="users-contact">
                                <strong>{{ $user->email }}</strong>
                                <span>{{ $user->phone ?: 'Téléphone non renseigné' }}</span>
                            </div>
                        </td>

                        <td data-label="Profil">
                            <span class="users-badge users-badge--role users-badge--{{ $roleKey }}">{{ $roleLabel }}</span>
                            @if($user->shop)
                                <span class="users-cell-note">{{ $user->shop->name ?? 'Boutique liée' }}</span>
                            @elseif($isInternal && $user->staffProfile?->job_title)
                                <span class="users-cell-note">{{ $user->staffProfile->job_title }}</span>
                            @endif
                        </td>

                        <td data-label="Statut">
                            <span class="users-badge {{ $user->status === 'active' ? 'users-badge--active' : 'users-badge--suspended' }}">
                                {{ $statusLabels[$user->status] ?? ucfirst((string) $user->status) }}
                            </span>
                        </td>

                        <td data-label="Activité">
                            @if($user->role === 'client')
                                <strong class="users-activity-value">{{ (int) ($user->orders_count ?? 0) }} commande(s)</strong>
                            @elseif($user->role === 'vendor')
                                <strong class="users-activity-value">{{ $user->shop ? 'Boutique enregistrée' : 'Aucune boutique' }}</strong>
                            @elseif($isInternal)
                                <strong class="users-activity-value">Compte interne</strong>
                            @else
                                <strong class="users-activity-value">Administration</strong>
                            @endif
                        </td>

                        <td data-label="Inscription">
                            <strong class="users-date">{{ optional($user->created_at)->format('d/m/Y') ?: '—' }}</strong>
                            <span class="users-cell-note">{{ optional($user->created_at)->format('H:i') ?: '' }}</span>
                        </td>

                        <td data-label="Actions">
                            <div class="users-row-actions">
                                @if($isInternal && Route::has('admin.staff.edit'))
                                    <a href="{{ route('admin.staff.edit', $user) }}" class="users-action-btn">Gérer</a>
                                @elseif(!$isInternal)
                                    <button
                                        type="button"
                                        class="users-action-btn js-edit-user"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ e($displayName) }}"
                                        data-user-email="{{ e($user->email) }}"
                                        data-user-phone="{{ e($user->phone ?? '') }}"
                                        data-user-role="{{ $isAdminAccount ? 'admin' : $user->role }}"
                                        data-user-status="{{ $user->status }}"
                                        data-update-url="{{ Route::has('admin.users.update') ? route('admin.users.update', $user) : url('/admin/users/'.$user->id) }}"
                                    >Modifier</button>
                                @endif

                                @if($user->role === 'client' && $user->id !== $currentAdminId)
                                    <form method="POST" action="{{ Route::has('admin.users.destroy') ? route('admin.users.destroy', $user) : url('/admin/users/'.$user->id) }}" onsubmit="return confirm('Anonymiser et suspendre définitivement ce compte client ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="users-action-btn users-action-btn--danger">Anonymiser</button>
                                    </form>
                                @elseif($user->id === $currentAdminId)
                                    <span class="users-action-label">Session actuelle</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="users-empty-state">
                                <div>👤</div>
                                <h3>Aucun utilisateur trouvé</h3>
                                <p>Modifiez les filtres ou ajoutez un nouveau compte.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="users-pagination">
                {{ $users->links() }}
            </div>
        @endif
    </section>
</main>

<dialog class="users-modal" id="userFormModal" data-must-open="{{ $mustOpenForm ? '1' : '0' }}">
    <div class="users-modal__shell">
        <div class="users-modal__header">
            <div>
                <p class="users-eyebrow">GESTION DU COMPTE</p>
                <h2 id="userModalTitle">Ajouter un utilisateur</h2>
                <p id="userModalSubtitle">Créez un compte client, vendeur ou administrateur.</p>
            </div>
            <button type="button" class="users-modal__close" id="closeUserModal" aria-label="Fermer">×</button>
        </div>

        @if($errors->any() && old('form_context') === 'user-form')
            <div class="users-alert users-alert--error users-modal__alert">
                <strong>Le formulaire contient des erreurs :</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ Route::has('admin.users.store') ? route('admin.users.store') : url('/admin/users') }}"
            id="userAdminForm"
            data-store-url="{{ Route::has('admin.users.store') ? route('admin.users.store') : url('/admin/users') }}"
            data-mode="{{ $formMode }}"
            data-user-id="{{ $formUserId }}"
        >
            @csrf
            <input type="hidden" name="_method" id="userFormMethod" value="POST">
            <input type="hidden" name="form_context" value="user-form">
            <input type="hidden" name="form_mode" id="userFormMode" value="{{ $formMode }}">
            <input type="hidden" name="user_id" id="userFormId" value="{{ $formUserId }}">

            <div class="users-form-grid">
                <div class="users-field users-field--full">
                    <label for="user_name">Nom complet *</label>
                    <input type="text" name="name" id="user_name" value="{{ old('name') }}" required autocomplete="name">
                </div>

                <div class="users-field">
                    <label for="user_email">Adresse e-mail *</label>
                    <input type="email" name="email" id="user_email" value="{{ old('email') }}" required autocomplete="email">
                </div>

                <div class="users-field">
                    <label for="user_phone">Téléphone</label>
                    <input type="text" name="phone" id="user_phone" value="{{ old('phone') }}" placeholder="Ex. 07 00 00 00 00" autocomplete="tel">
                </div>

                <div class="users-field">
                    <label for="user_role">Profil *</label>
                    <select name="role" id="user_role" required>
                        <option value="client" @selected(old('role', 'client') === 'client')>Client</option>
                        <option value="vendor" @selected(old('role') === 'vendor')>Vendeur</option>
                        <option value="admin" @selected(old('role') === 'admin')>Administrateur</option>
                    </select>
                    <small>Les comptes Support, Commercial et Logistique se créent dans « Équipe interne ».</small>
                </div>

                <div class="users-field">
                    <label for="user_status">Statut *</label>
                    <select name="status" id="user_status" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>Actif</option>
                        <option value="suspended" @selected(old('status') === 'suspended')>Suspendu</option>
                    </select>
                </div>

                <div class="users-field">
                    <label for="user_password">Mot de passe <span id="passwordRequiredMark">*</span></label>
                    <input type="password" name="password" id="user_password" autocomplete="new-password">
                    <small id="passwordHelp">8 caractères minimum, avec lettres et chiffres.</small>
                </div>

                <div class="users-field">
                    <label for="user_password_confirmation">Confirmer le mot de passe <span id="passwordConfirmationRequiredMark">*</span></label>
                    <input type="password" name="password_confirmation" id="user_password_confirmation" autocomplete="new-password">
                </div>
            </div>

            <div class="users-form-actions">
                <button type="button" class="users-btn users-btn--secondary" id="cancelUserModal">Annuler</button>
                <button type="submit" class="users-btn users-btn--primary" id="saveUserButton">Créer le compte</button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_users.css') }}">
@endpush

@push('scripts')
<script>
window.ovanieUserFormOldData = {!! json_encode(
    $userFormOldData,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) !!};
</script>
<script src="{{ asset('admin/js/admin_users.js') }}"></script>
@endpush
