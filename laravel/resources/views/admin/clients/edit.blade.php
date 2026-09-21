@extends('admin.layouts.app')

@section('title', 'Modifier un client | Administration OVANIE')
@section('page-title', 'Modifier un client')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_clients.css') }}">
@endpush

@section('content')
@php
    $nameParts = preg_split('/\s+/', trim($client->name ?? ''), 2);
    $defaultFirstName = $client->first_name ?: ($nameParts[0] ?? '');
    $defaultLastName = $client->last_name ?: ($nameParts[1] ?? '');
@endphp

<div class="ac-page ac-edit-page">
    <section class="ac-page-head ac-page-head-actions">
        <div>
            <span class="ac-eyebrow">Compte client #{{ $client->id }}</span>
            <h2>Modifier la fiche client</h2>
            <p>Mettez à jour uniquement les informations administratives vérifiées.</p>
        </div>
        <div class="ac-head-actions">
            <a class="ac-btn ac-btn-secondary" href="{{ route('admin.clients.show', $client->id) }}">Retour à la fiche</a>
            <a class="ac-btn ac-btn-secondary" href="{{ route('admin.clients.index') }}">Liste des clients</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="ac-alert ac-alert-error">
            <strong>Le formulaire contient des erreurs.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="ac-edit-layout" action="{{ route('admin.clients.update', $client->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="ac-edit-main">
            <section class="ac-panel ac-form-section">
                <div class="ac-section-head"><div><span class="ac-section-number">1</span><div><h3>Identité du client</h3><p>Nom affiché dans les commandes et les échanges OVANIE.</p></div></div></div>
                <div class="ac-form-grid">
                    <label><span>Prénom ou nom usuel <b>*</b></span><input type="text" name="first_name" value="{{ old('first_name', $defaultFirstName) }}" maxlength="100" required></label>
                    <label><span>Nom de famille</span><input type="text" name="last_name" value="{{ old('last_name', $defaultLastName) }}" maxlength="100"></label>
                </div>
            </section>

            <section class="ac-panel ac-form-section">
                <div class="ac-section-head"><div><span class="ac-section-number">2</span><div><h3>Coordonnées</h3><p>Informations utilisées pour la connexion et le suivi des commandes.</p></div></div></div>
                <div class="ac-form-grid">
                    <label><span>Adresse e-mail <b>*</b></span><input type="email" name="email" value="{{ old('email', $client->email) }}" maxlength="255" required></label>
                    <label><span>Téléphone principal</span><input type="tel" name="phone" value="{{ old('phone', $client->phone) }}" maxlength="50" placeholder="Ex. 07 00 00 00 00"></label>
                    <label><span>Téléphone secondaire</span><input type="tel" name="secondary_phone" value="{{ old('secondary_phone', $client->secondary_phone) }}" maxlength="50"></label>
                    <label><span>Ville</span><input type="text" name="city" value="{{ old('city', $client->city) }}" maxlength="120" placeholder="Ex. Abidjan"></label>
                </div>
            </section>

            <section class="ac-panel ac-form-section">
                <div class="ac-section-head"><div><span class="ac-section-number">3</span><div><h3>État du compte</h3><p>Le statut détermine si le client peut accéder à son espace.</p></div></div></div>
                <div class="ac-status-choice-grid">
                    @foreach ([
                        'active' => ['Actif', 'Le client peut se connecter et commander.'],
                        'inactive' => ['Inactif', 'Le compte reste enregistré mais l’accès est désactivé.'],
                        'blocked' => ['Bloqué', 'Accès refusé à la suite d’un contrôle administratif.'],
                        'suspended' => ['Suspendu', 'Compte temporairement suspendu.'],
                    ] as $value => [$label, $description])
                        <label class="ac-status-choice">
                            <input type="radio" name="status" value="{{ $value }}" @checked(old('status', $client->status ?: 'active') === $value) required>
                            <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="ac-edit-side">
            <section class="ac-panel ac-compact-panel">
                <h3>Résumé du compte</h3>
                <dl class="ac-data-list">
                    <div><dt>Identifiant</dt><dd>#{{ $client->id }}</dd></div>
                    <div><dt>Inscription</dt><dd>{{ $client->created_at?->format('d/m/Y à H:i') }}</dd></div>
                    <div><dt>Origine</dt><dd>{{ $client->commercialCreator?->name ?: 'Création directe' }}</dd></div>
                    <div><dt>Dernière modification</dt><dd>{{ $client->updated_at?->format('d/m/Y à H:i') }}</dd></div>
                </dl>
            </section>
            <div class="ac-edit-note"><strong>Important</strong><p>Le mot de passe et les moyens de paiement ne sont jamais affichés dans ce formulaire.</p></div>
        </aside>

        <footer class="ac-form-footer">
            <div class="ac-form-footer-copy">
                <span class="ac-form-footer-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                </span>
                <div>
                    <strong>Mise à jour du compte client</strong>
                    <small>Les modifications seront enregistrées dans la fiche administrative du client.</small>
                </div>
            </div>
            <div class="ac-form-footer-actions">
                <a class="ac-btn ac-btn-secondary" href="{{ route('admin.clients.show', $client->id) }}">Annuler</a>
                <button class="ac-btn ac-btn-primary" type="submit">Enregistrer les modifications</button>
            </div>
        </footer>
    </form>
</div>
@endsection
