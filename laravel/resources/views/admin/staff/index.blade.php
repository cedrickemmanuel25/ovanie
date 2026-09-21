@extends('admin.layouts.app')

@section('title', 'Équipe interne | Admin OVANIE')
@section('page-title', 'Équipe interne')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_staff.css') }}">
@endpush

@section('content')
<div class="staff-page">
    <section class="staff-hero">
        <div>
            <h2>Comptes Logistique, Support et Commercial</h2>
            <p>Gérez tous les collaborateurs depuis une seule architecture : rôles, permissions, statut, profil et historique de connexion.</p>
        </div>
        <div class="staff-hero-actions">
            <a class="staff-btn" href="{{ route('admin.staff.login-logs') }}">Journaux de connexion</a>
            <a class="staff-btn staff-btn-primary" href="{{ route('admin.staff.create') }}">+ Nouveau collaborateur</a>
        </div>
    </section>

    <form class="staff-filter" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, email, téléphone, matricule ou fonction…">
        <select name="role">
            <option value="">Tous les espaces</option>
            <option value="logistique" @selected(request('role') === 'logistique')>Logistique</option>
            <option value="support" @selected(request('role') === 'support')>Support</option>
            <option value="commercial" @selected(request('role') === 'commercial')>Commercial</option>
        </select>
        <select name="status">
            <option value="">Tous les statuts</option>
            <option value="active" @selected(request('status') === 'active')>Actif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactif</option>
            <option value="suspended" @selected(request('status') === 'suspended')>Suspendu</option>
        </select>
        <button class="staff-btn" type="submit">Filtrer</button>
        @if(request()->hasAny(['q','role','status']))
            <a class="staff-btn" href="{{ route('admin.staff.index') }}">Réinitialiser</a>
        @endif
    </form>

    <section class="staff-card">
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Collaborateur</th>
                        <th>Matricule</th>
                        <th>Espace</th>
                        <th>Fonction</th>
                        <th>Responsable</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($staffMembers as $member)
                    @php
                        $profile = $member->staffProfile;
                        $lastLogin = $member->latestInternalLoginLog;
                        $roleLabel = match($member->role) {
                            'logistique' => 'Logistique',
                            'support' => 'Support',
                            'commercial' => 'Commercial',
                            default => ucfirst($member->role),
                        };
                        $statusLabel = match($member->status) {
                            'active' => 'Actif',
                            'inactive' => 'Inactif',
                            'suspended' => 'Suspendu',
                            default => ucfirst((string) $member->status),
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="staff-person">
                                <div class="staff-initial">{{ mb_strtoupper(mb_substr($member->name ?: 'O', 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $member->name ?: 'Sans nom' }}</strong>
                                    <span>{{ $member->email }}</span>
                                    @if($member->phone)<span>{{ $member->phone }}</span>@endif
                                </div>
                            </div>
                        </td>
                        <td>{{ $profile?->employee_code ?: '—' }}</td>
                        <td><span class="staff-badge {{ $member->role }}">{{ $roleLabel }}</span></td>
                        <td>{{ $profile?->job_title ?: '—' }}</td>
                        <td>{{ $profile?->manager?->name ?: '—' }}</td>
                        <td><span class="staff-badge status-{{ $member->status }}">{{ $statusLabel }}</span></td>
                        <td>
                            @if($lastLogin && $lastLogin->event === 'login_success')
                                <strong>{{ $lastLogin->created_at->format('d/m/Y H:i') }}</strong>
                                <span class="staff-cell-sub">{{ $lastLogin->ip_address ?: 'IP inconnue' }}</span>
                            @else
                                <span class="staff-cell-sub">Jamais connecté</span>
                            @endif
                        </td>
                        <td>
                            <div class="staff-actions">
                                <a class="staff-btn staff-btn-small" href="{{ route('admin.staff.edit', $member) }}">Gérer</a>
                                <form method="POST" action="{{ route('admin.staff.status', $member) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'inactive' : 'active' }}">
                                    <button class="staff-btn staff-btn-small" type="submit">
                                        {{ $member->status === 'active' ? 'Désactiver' : 'Activer' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="staff-empty">Aucun compte Logistique, Support ou Commercial n’a encore été créé.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($staffMembers->hasPages())
            <div class="staff-pagination">{{ $staffMembers->links() }}</div>
        @endif
    </section>
</div>
@endsection
