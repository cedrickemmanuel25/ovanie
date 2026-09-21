@php
    $editing = isset($staff);
@endphp
<div class="staff-form-grid">
    <div class="staff-form-group">
        <label for="name">Nom complet *</label>
        <input id="name" name="name" value="{{ old('name', $staff->name ?? '') }}" required autocomplete="name">
    </div>

    <div class="staff-form-group">
        <label for="email">Adresse e-mail professionnelle *</label>
        <input id="email" type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" required autocomplete="email">
    </div>

    <div class="staff-form-group">
        <label for="phone">Téléphone</label>
        <input id="phone" name="phone" value="{{ old('phone', $staff->phone ?? '') }}" autocomplete="tel">
    </div>

    <div class="staff-form-group">
        <label for="role">Espace métier *</label>
        <select id="role" name="role" required>
            <option value="logistique" @selected(old('role', $staff->role ?? '') === 'logistique')>Logistique</option>
            <option value="support" @selected(old('role', $staff->role ?? 'support') === 'support')>Support</option>
            <option value="commercial" @selected(old('role', $staff->role ?? '') === 'commercial')>Commercial</option>
        </select>
    </div>

    <div class="staff-form-group">
        <label for="status">Statut du compte *</label>
        <select id="status" name="status" required>
            <option value="active" @selected(old('status', $staff->status ?? 'active') === 'active')>Actif</option>
            <option value="inactive" @selected(old('status', $staff->status ?? '') === 'inactive')>Inactif</option>
            <option value="suspended" @selected(old('status', $staff->status ?? '') === 'suspended')>Suspendu</option>
        </select>
        <span class="staff-form-help">Seul le statut Actif autorise la connexion au portail interne.</span>
    </div>

    <div class="staff-form-group">
        <label for="employee_code">Matricule *</label>
        <input id="employee_code" name="employee_code" value="{{ old('employee_code', $staff->staffProfile?->employee_code ?? '') }}" placeholder="SUP-0001" required>
    </div>

    <div class="staff-form-group">
        <label for="job_title">Fonction</label>
        <input id="job_title" name="job_title" value="{{ old('job_title', $staff->staffProfile?->job_title ?? '') }}" placeholder="Agent logistique, Agent support, Chargé d’affaires…">
    </div>

    <div class="staff-form-group">
        <label for="manager_id">Responsable hiérarchique</label>
        <select id="manager_id" name="manager_id">
            <option value="">Aucun responsable</option>
            @foreach($managers as $manager)
                <option value="{{ $manager->id }}" @selected((string) old('manager_id', $staff->staffProfile?->manager_id ?? '') === (string) $manager->id)>
                    {{ $manager->name ?: $manager->email }} — {{ ucfirst($manager->role) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="staff-form-group">
        <label for="phone_extension">Extension téléphonique</label>
        <input id="phone_extension" name="phone_extension" value="{{ old('phone_extension', $staff->staffProfile?->phone_extension ?? '') }}">
    </div>

    @unless($editing)
        <div class="staff-form-group">
            <label for="password">Mot de passe *</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            <span class="staff-form-help">Minimum 8 caractères, avec majuscule, minuscule et chiffre.</span>
        </div>
        <div class="staff-form-group">
            <label for="password_confirmation">Confirmation du mot de passe *</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
    @endunless

    <div class="staff-form-group full">
        <label>Permissions métier</label>
        <input type="hidden" name="permissions_present" value="1">
        @php
            $selectedPermissions = old(
                'permissions',
                $staff->staffProfile?->permissions
                    ?? config('staff.role_permissions.' . ($staff->role ?? 'support'), [])
            );
            $rolePermissions = config('staff.role_permissions', []);
            $permissionLabels = config('staff.permissions', []);
        @endphp
        <div class="permission-grid">
            @foreach($rolePermissions as $permissionRole => $permissions)
                <div class="permission-group" data-permission-role="{{ $permissionRole }}">
                    <strong>
                        {{ match($permissionRole) {
                            'logistique' => 'Permissions Logistique',
                            'support' => 'Permissions Support',
                            'commercial' => 'Permissions Commerciales',
                            default => ucfirst($permissionRole),
                        } }}
                    </strong>
                    @foreach($permissions as $permission)
                        <label class="permission-option">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                            <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
        <span class="staff-form-help">Les permissions incompatibles avec le rôle sélectionné sont rejetées côté serveur.</span>
    </div>
</div>

<div class="staff-form-actions">
    <a class="staff-btn" href="{{ route('admin.staff.index') }}">Annuler</a>
    <button class="staff-btn staff-btn-primary" type="submit">
        {{ $editing ? 'Enregistrer les modifications' : 'Créer le compte' }}
    </button>
</div>
