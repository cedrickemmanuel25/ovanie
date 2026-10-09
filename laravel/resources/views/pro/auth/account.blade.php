@extends('pro.auth.layout')
@section('title', 'Mon compte')
@section('shell', 'pa-shell--xl')
@section('content')
@php
    $statusTags = [
        'draft' => ['Brouillon', ''], 'pending' => ['En attente de vérification', 'pa-tag--amber'],
        'in_progress' => ['Vérification en cours', 'pa-tag--blue'], 'additional_info_requested' => ['Compléments demandés', 'pa-tag--amber'],
        'verified' => ['Vérifié', 'pa-tag--green'], 'refused' => ['Refusé', 'pa-tag--red'], 'suspended' => ['Suspendu', 'pa-tag--red'],
    ];
    $profileStatus = $statusTags[$profile?->organization?->verification_status ?? $profile?->status ?? 'draft'] ?? [$profile?->status, ''];
@endphp

<div class="pa-head">
    <p class="pa-kicker">Mon compte</p>
    <h1 class="pa-title">Bonjour {{ $user->first_name ?: $user->full_name }}</h1>
    <p class="pa-sub">Gérez vos informations, votre profil professionnel, vos espaces et vos entreprises.</p>
</div>

@include('pro.auth.partials.alerts', ['bag' => null])
@if(session('invitation_link'))
    <div class="pa-alert pa-alert--info">Lien d’invitation<div class="pa-copy"><input class="pa-input" id="invite-link" value="{{ session('invitation_link') }}" readonly><button class="pa-btn pa-btn--sm" type="button" data-copy="invite-link">Copier</button></div></div>
@endif

<div class="pa-account">
    <nav>
        <ul class="pa-nav" style="position:sticky;top:84px">
            <li><a href="#profil">@proicon('briefcase') Profil professionnel</a></li>
            <li><a href="#espaces">@proicon('store') Espaces de travail</a></li>
            <li><a href="#entreprises">@proicon('building') Mes entreprises</a></li>
            <li><a href="#identite">@proicon('user') Informations personnelles</a></li>
            <li><a href="#securite">@proicon('lock') Sécurité</a></li>
        </ul>
    </nav>

    <div>
        {{-- Profil professionnel (ACC-19 : n'afficher que ce qui manque) --}}
        <section class="pa-card" id="profil">
            <div class="pa-row">
                <div>
                    <h2 class="pa-h2">Profil professionnel</h2>
                    <p class="pa-section__intro">{{ $profile ? $profileLabel . ' · ' . ($profile->entity_type === 'independent' ? 'Indépendant' : 'Entreprise') : 'Aucun profil professionnel pour le moment.' }}</p>
                </div>
                @if($profile)<span class="pa-tag {{ $profileStatus[1] }}">{{ $profileStatus[0] }}</span>@endif
            </div>

            @if(! $profile)
                <p class="pa-help" style="margin-bottom:14px">Activez un profil PRO pour acheter, vendre ou répondre aux appels d’offres entre professionnels.</p>
                <a class="pa-btn" href="{{ route('pro.register.qualification') }}">Activer un profil professionnel</a>
            @else
                @if($missing)
                    <div class="pa-alert pa-alert--warn">
                        <strong>{{ count($missing) }} information(s) à compléter</strong> avant vérification :
                        {{ implode(', ', array_slice($missing, 0, 6)) }}{{ count($missing) > 6 ? '…' : '' }}
                    </div>
                @elseif($profile->status === 'draft')
                    <div class="pa-alert pa-alert--info">Votre dossier est complet : il ne reste qu’à le soumettre.</div>
                @endif
                @if($profile->organization)
                    <dl class="pa-dl" style="margin-bottom:16px">
                        <dt>{{ $profile->entity_type === 'independent' ? 'Activité' : 'Entreprise' }}</dt><dd>{{ $profile->organization->display_name }}</dd>
                        <dt>Pays d’activité</dt><dd>{{ $profile->activity_country ?: '—' }}</dd>
                        <dt>Documents fournis</dt><dd>{{ $profile->organization->documents->where('status', '!=', 'replaced')->count() }}</dd>
                    </dl>
                @endif
                <div class="pa-inline">
                    @if($profile->entity_type)
                        <a class="pa-btn pa-btn--sm" href="{{ route('pro.register.professional.form', ['profile' => $profile->profile_key]) }}">{{ $missing || $profile->status === 'draft' ? 'Compléter mon profil' : 'Modifier mon profil' }}</a>
                    @endif
                    <a class="pa-btn pa-btn--ghost pa-btn--sm" href="{{ route('pro.register.qualification') }}">Changer de profil ou de statut</a>
                </div>
            @endif
        </section>

        {{-- Espaces de travail (ACC-12) --}}
        <section class="pa-card" id="espaces">
            <div class="pa-row">
                <div><h2 class="pa-h2">Espaces de travail</h2><p class="pa-section__intro">Cumulables : activez uniquement ceux dont vous avez besoin.</p></div>
                <a class="pa-btn pa-btn--ghost pa-btn--sm" href="{{ route('pro.roles.edit') }}">Gérer mes espaces</a>
            </div>
            <div class="pa-inline">
                @forelse($activeRoles as $role)
                    <span class="pa-tag pa-tag--blue">{{ $roles[$role]['label'] ?? $role }}</span>
                @empty
                    <span class="pa-help" style="margin:0">Aucun espace activé.</span>
                @endforelse
            </div>
        </section>

        {{-- Entreprises (ACC-13 / ACC-14) --}}
        <section class="pa-card" id="entreprises">
            <h2 class="pa-h2">Mes entreprises</h2>
            <p class="pa-section__intro" style="margin-bottom:14px">Organisations que vous avez créées ou rejointes sur invitation.</p>
            @if($memberships->isEmpty())
                <div class="pa-empty">Vous ne faites partie d’aucune entreprise. Elle sera créée avec votre profil professionnel, ou rejointe via une invitation.</div>
            @else
                <div class="pa-items">
                    @foreach($memberships as $membership)
                        @php($org = $membership->organization)
                        <div class="pa-item">
                            <div class="pa-item__body">
                                <span class="pa-item__title">{{ $org?->display_name ?? $membership->organization_name }}</span>
                                <span class="pa-item__meta">{{ $permissions[$membership->permission] ?? $membership->permission }} · {{ $membership->status === 'active' ? 'Accès actif' : 'Accès désactivé' }}</span>
                            </div>
                            @if($org)<span class="pa-tag {{ ($statusTags[$org->verification_status] ?? ['', ''])[1] }}">{{ $org->verification_label }}</span>@endif
                            @if($org && $membership->status === 'active' && $membership->permission === 'administrator')
                                <div class="pa-item__actions"><a class="pa-btn pa-btn--light pa-btn--sm" href="{{ route('pro.team.index', $org) }}">Collaborateurs</a></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Informations personnelles --}}
        <section class="pa-card" id="identite">
            <h2 class="pa-h2">Informations personnelles</h2>
            <p class="pa-section__intro" style="margin-bottom:14px">Ces informations vous identifient personnellement, indépendamment de votre entreprise.</p>
            @include('pro.auth.partials.alerts', ['bag' => 'identity'])
            <form method="POST" action="{{ route('pro.account.update') }}#identite">
                @csrf @method('PUT')
                @php($e = $errors->getBag('identity'))
                <div class="pa-grid">
                    <div class="pa-field {{ $e->has('last_name') ? 'has-error' : '' }}">
                        <label class="pa-label" for="acc_last_name">Nom</label>
                        <input class="pa-input" id="acc_last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100">
                    </div>
                    <div class="pa-field {{ $e->has('first_name') ? 'has-error' : '' }}">
                        <label class="pa-label" for="acc_first_name">Prénoms</label>
                        <input class="pa-input" id="acc_first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100">
                    </div>
                    <div class="pa-field {{ $e->has('email') ? 'has-error' : '' }}">
                        <label class="pa-label" for="acc_email">E-mail</label>
                        <input class="pa-input" id="acc_email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255">
                    </div>
                    <div class="pa-field {{ $e->has('phone') ? 'has-error' : '' }}">
                        <label class="pa-label" for="acc_phone">Téléphone</label>
                        <div class="pa-phone">
                            <select name="phone_country" aria-label="Indicatif">
                                @foreach($phoneCodes as $code => $label)<option value="{{ $code }}" @selected(old('phone_country', $phoneCode) === $code)>{{ $label }}</option>@endforeach
                            </select>
                            <input id="acc_phone" name="phone" type="tel" value="{{ old('phone') ? \Illuminate\Support\Str::after(old('phone'), old('phone_country', '+225')) : $phoneNumber }}" required maxlength="20">
                        </div>
                    </div>
                    <div class="pa-field">
                        <label class="pa-label" for="acc_country">Pays de résidence</label>
                        <select class="pa-select" id="acc_country" name="residence_country" required>
                            @foreach($countries as $country)<option value="{{ $country }}" @selected(old('residence_country', $user->residence_country) === $country)>{{ $country }}</option>@endforeach
                        </select>
                    </div>
                    <div class="pa-field">
                        <label class="pa-label" for="acc_language">Langue</label>
                        <select class="pa-select" id="acc_language" name="preferred_language" required>
                            @foreach($languages as $code => $label)<option value="{{ $code }}" @selected(old('preferred_language', $user->preferred_language ?: 'fr') === $code)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="pa-btn" type="submit">Enregistrer</button>
            </form>
        </section>

        {{-- Sécurité : mot de passe et sessions --}}
        <section class="pa-card" id="securite">
            <h2 class="pa-h2">Mot de passe</h2>
            <p class="pa-section__intro" style="margin-bottom:14px">Changer de mot de passe déconnecte vos autres appareils.</p>
            @include('pro.auth.partials.alerts', ['bag' => 'password'])
            <form method="POST" action="{{ route('pro.account.password') }}#securite">
                @csrf @method('PUT')
                <div class="pa-grid">
                    <div class="pa-field pa-full">
                        <label class="pa-label" for="current_password">Mot de passe actuel</label>
                        @include('pro.auth.partials.password', ['id' => 'current_password'])
                    </div>
                    <div class="pa-field">
                        <label class="pa-label" for="new_password">Nouveau mot de passe</label>
                        @include('pro.auth.partials.password', ['id' => 'new_password', 'name' => 'password', 'autocomplete' => 'new-password', 'meter' => true])
                    </div>
                    <div class="pa-field">
                        <label class="pa-label" for="new_password_confirmation">Confirmation</label>
                        @include('pro.auth.partials.password', ['id' => 'new_password_confirmation', 'name' => 'password_confirmation', 'autocomplete' => 'new-password'])
                    </div>
                </div>
                <button class="pa-btn" type="submit">Modifier le mot de passe</button>
            </form>

            <div class="pa-section" style="margin-top:24px">
                <h2 class="pa-h2">Sessions</h2>
                <div class="pa-item" style="margin:12px 0 16px">
                    <div class="pa-item__body">
                        <span class="pa-item__title">{{ $session['agent'] }}</span>
                        <span class="pa-item__meta">Session actuelle · adresse IP {{ $session['ip'] }}</span>
                    </div>
                    <span class="pa-tag pa-tag--green">Active</span>
                </div>
                @include('pro.auth.partials.alerts', ['bag' => 'sessions'])
                <form method="POST" action="{{ route('pro.account.sessions') }}#securite" class="pa-inline">
                    @csrf
                    <div style="flex:1;min-width:220px">@include('pro.auth.partials.password', ['id' => 'session_password', 'placeholder' => 'Confirmez avec votre mot de passe'])</div>
                    <button class="pa-btn pa-btn--ghost" type="submit">Déconnecter mes autres appareils</button>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
