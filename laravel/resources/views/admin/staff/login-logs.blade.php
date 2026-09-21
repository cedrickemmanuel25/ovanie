@extends('admin.layouts.app')

@section('title', 'Journaux de connexion | Admin OVANIE')
@section('page-title', 'Journaux de connexion')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_staff.css') }}">
@endpush

@section('content')
<div class="staff-page">
    <section class="staff-hero">
        <div>
            <h2>Historique des accès internes</h2>
            <p>Connexions réussies, tentatives refusées et déconnexions du personnel Administration, Logistique, Support et Commercial.</p>
        </div>
        <a class="staff-btn" href="{{ route('admin.staff.index') }}">Retour aux collaborateurs</a>
    </section>

    <form class="staff-filter" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, e-mail ou adresse IP…">
        <select name="role">
            <option value="">Tous les rôles</option>
            <option value="admin" @selected(request('role') === 'admin')>Administration</option>
            <option value="logistique" @selected(request('role') === 'logistique')>Logistique</option>
            <option value="support" @selected(request('role') === 'support')>Support</option>
            <option value="commercial" @selected(request('role') === 'commercial')>Commercial</option>
        </select>
        <select name="event">
            <option value="">Tous les événements</option>
            <option value="login_success" @selected(request('event') === 'login_success')>Connexion réussie</option>
            <option value="login_failed" @selected(request('event') === 'login_failed')>Connexion refusée</option>
            <option value="logout" @selected(request('event') === 'logout')>Déconnexion</option>
        </select>
        <button class="staff-btn" type="submit">Filtrer</button>
        @if(request()->hasAny(['q','role','event']))
            <a class="staff-btn" href="{{ route('admin.staff.login-logs') }}">Réinitialiser</a>
        @endif
    </form>

    <section class="staff-card">
        <div class="staff-table-wrap">
            <table class="staff-table staff-log-table">
                <thead>
                    <tr><th>Date</th><th>Collaborateur</th><th>Rôle</th><th>Événement</th><th>Adresse IP</th><th>Navigateur / appareil</th></tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    @php
                        $eventLabel = match($log->event) {
                            'login_success' => 'Connexion réussie',
                            'login_failed' => 'Connexion refusée',
                            'logout' => 'Déconnexion',
                            default => $log->event,
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $log->created_at->format('d/m/Y H:i:s') }}</strong></td>
                        <td>
                            <strong>{{ $log->user?->name ?: 'Compte non identifié' }}</strong>
                            <span class="staff-cell-sub">{{ $log->attempted_email ?: $log->user?->email ?: '—' }}</span>
                        </td>
                        <td><span class="staff-badge {{ $log->role }}">{{ $log->role ?: '—' }}</span></td>
                        <td><span class="staff-badge event-{{ $log->event }}">{{ $eventLabel }}</span></td>
                        <td>{{ $log->ip_address ?: '—' }}</td>
                        <td class="staff-user-agent">{{ $log->user_agent ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="staff-empty">Aucun journal de connexion disponible.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="staff-pagination">{{ $logs->links() }}</div>@endif
    </section>
</div>
@endsection
