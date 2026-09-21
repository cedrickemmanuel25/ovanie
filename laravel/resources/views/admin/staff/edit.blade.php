@extends('admin.layouts.app')
@section('title', 'Modifier un collaborateur | Admin OVANIE')
@section('page-title', 'Modifier le collaborateur')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_staff.css') }}">@endpush

@section('content')
<div class="staff-page">
    <section class="staff-hero">
        <div>
            <h2>{{ $staff->name ?: $staff->email }}</h2>
            <p>Modifiez le profil, le rôle, le statut et les permissions. Les tickets, activités, commandes et livraisons associés restent conservés.</p>
        </div>
        <span class="staff-badge status-{{ $staff->status }}">{{ ucfirst($staff->status) }}</span>
    </section>

    <form class="staff-form-card" method="POST" action="{{ route('admin.staff.update', $staff) }}">
        @csrf @method('PUT')
        @include('admin.staff._form')
    </form>

    <div class="staff-security-grid">
        <section class="staff-form-card">
            <div class="staff-card-heading">
                <h3>Réinitialiser le mot de passe</h3>
                <p>Définissez un nouveau mot de passe temporaire et transmettez-le au collaborateur par un canal sécurisé.</p>
            </div>
            <form method="POST" action="{{ route('admin.staff.password', $staff) }}" class="staff-inline-form">
                @csrf @method('PATCH')
                <div class="staff-form-group">
                    <label for="reset_password">Nouveau mot de passe *</label>
                    <input id="reset_password" type="password" name="password" required autocomplete="new-password">
                </div>
                <div class="staff-form-group">
                    <label for="reset_password_confirmation">Confirmation *</label>
                    <input id="reset_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
                <div class="staff-inline-actions">
                    <button class="staff-btn staff-btn-primary" type="submit">Réinitialiser le mot de passe</button>
                </div>
            </form>
        </section>

        <section class="staff-form-card">
            <div class="staff-card-heading">
                <h3>Informations de sécurité</h3>
                <p>Dernière activité interne : {{ $staff->staffProfile?->last_seen_at?->format('d/m/Y H:i') ?: 'Jamais' }}</p>
                <p>Dernière connexion : {{ $staff->latestInternalLoginLog?->created_at?->format('d/m/Y H:i') ?: 'Jamais' }}</p>
                <p>Adresse IP : {{ $staff->latestInternalLoginLog?->ip_address ?: 'Non disponible' }}</p>
            </div>
            <a class="staff-btn" href="{{ route('admin.staff.login-logs', ['q' => $staff->email]) }}">Voir les journaux</a>
        </section>
    </div>

    <section class="staff-form-card staff-danger-zone">
        <div class="staff-card-heading">
            <h3>Supprimer le compte</h3>
            <p>La suppression retire définitivement l’accès. Les références métier utilisant ce collaborateur sont conservées ou désaffectées selon leurs relations.</p>
        </div>
        <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}" onsubmit="return confirm('Supprimer définitivement ce compte collaborateur ?');">
            @csrf @method('DELETE')
            <button class="staff-btn staff-btn-danger" type="submit">Supprimer définitivement</button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
@include('admin.staff._form-script')
@endpush
