@extends('layouts.client')
@section('title', 'Paramètres du compte')
@section('content')
@php
    $isSocialAccount = filled($client->google_id) || filled($client->facebook_id);
@endphp

<section class="cs-page-head">
    <div>
        <h1>Paramètres du compte</h1>
        <p>Gérez votre profil, vos préférences et votre confidentialité.</p>
    </div>
</section>

<div class="cs-settings" data-tabs>
    <nav class="cs-tabs">
        <button class="active" data-tab="profile">Profil</button>
        <button data-tab="notifications">Notifications</button>
        <button data-tab="region">Langue & Région</button>
        <button data-tab="referral">Parrainage</button>
    </nav>

    <div class="cs-settings-grid" data-tab-panel="profile">
        <form method="POST" action="{{ route('client.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <section class="cs-card">
                <h2>Informations personnelles</h2>

                <div class="cs-profile-photo">
                    @if($client->avatar)
                        <img src="{{ asset('storage/'.$client->avatar) }}" alt="Photo de profil">
                    @else
                        <span>{{ strtoupper(substr($client->name ?: 'C', 0, 1)) }}</span>
                    @endif
                    <label class="cs-btn small outline">
                        Modifier
                        <input hidden type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                    </label>
                </div>

                <div class="cs-form-grid">
                    <label>Prénom
                        <input name="first_name" value="{{ old('first_name', $client->first_name) }}">
                    </label>
                    <label>Nom
                        <input name="last_name" value="{{ old('last_name', $client->last_name) }}">
                    </label>
                    <label>Email
                        <input type="email" name="email" value="{{ old('email', $client->email) }}" required>
                    </label>
                    <label>Téléphone principal
                        <input name="phone" value="{{ old('phone', $client->phone) }}">
                    </label>
                    <label>Téléphone secondaire
                        <input name="secondary_phone" value="{{ old('secondary_phone', $client->secondary_phone) }}">
                    </label>
                    <label>Date de naissance
                        <input type="date" name="birth_date" value="{{ old('birth_date', $client->birth_date?->format('Y-m-d')) }}">
                    </label>
                    <label>Genre
                        <select name="gender">
                            <option value="">Ne pas préciser</option>
                            <option value="homme" @selected($client->gender === 'homme')>Homme</option>
                            <option value="femme" @selected($client->gender === 'femme')>Femme</option>
                            <option value="non_precise" @selected($client->gender === 'non_precise')>Non précisé</option>
                        </select>
                    </label>
                    <label>Ville
                        <input name="city" value="{{ old('city', $client->city ?: 'Abidjan') }}">
                    </label>
                </div>

                <h3>Type de compte</h3>
                <div class="cs-radio-cards">
                    @foreach([
                        'particulier' => 'Particulier — Achats personnels',
                        'professionnel' => 'Professionnel — Entreprise BTP',
                        'artisan' => 'Artisan — Indépendant',
                    ] as $key => $label)
                        <label>
                            <input type="radio" name="account_type" value="{{ $key }}" @checked(($client->account_type ?: 'particulier') === $key)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <button class="cs-btn full" type="submit">Sauvegarder les modifications</button>
            </section>
        </form>

        <aside>
            <section class="cs-card">
                <h2>Modifier mon mot de passe</h2>
                <p>Utilisez le mot de passe temporaire communiqué lors de la création du compte.</p>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')
                    <label>Mot de passe actuel
                        <input type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <label>Nouveau mot de passe
                        <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                    </label>
                    <label>Confirmer le nouveau mot de passe
                        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                    </label>
                    @error('current_password', 'updatePassword')<p class="cs-error">{{ $message }}</p>@enderror
                    @error('password', 'updatePassword')<p class="cs-error">{{ $message }}</p>@enderror
                    <button class="cs-btn full" type="submit">Modifier mon mot de passe</button>
                </form>
            </section>

            <section class="cs-card">
                <h2>Statut du compte</h2>
                <p class="ok">Compte actif</p>
                <p>Email : Renseigné</p>
                <p>Téléphone : {{ $client->phone ? 'Renseigné' : 'Non renseigné' }}</p>
                <p>Membre depuis : {{ $client->created_at?->translatedFormat('F Y') }}</p>
                <span class="cs-badge warning">Client Régulier</span>
            </section>

            <section class="cs-card danger-zone">
                <h2>Suppression du compte</h2>
                <p>Action permanente et irréversible. La suppression est bloquée lorsqu’un paiement, une commande, un solde ou un retour est encore en cours.</p>

                @if($isSocialAccount)
                    <form method="POST" action="{{ route('client.settings.deletion-code') }}" class="mb-3">
                        @csrf
                        <p>Pour un compte Google ou Facebook, un code de sécurité à usage unique est obligatoire avant toute suppression.</p>
                        <button class="cs-btn outline full" type="submit">Envoyer le code de sécurité</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('client.settings.delete') }}" onsubmit="return confirm('Supprimer définitivement votre compte ?')">
                    @csrf
                    @method('DELETE')

                    @if($isSocialAccount)
                        <label>Code de sécurité reçu par e-mail
                            <input type="text" name="deletion_code" value="{{ old('deletion_code') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                        </label>
                    @else
                        <label>Mot de passe actuel
                            <input type="password" name="password" placeholder="Mot de passe actuel" autocomplete="current-password" required>
                        </label>
                    @endif

                    <label>Confirmation finale
                        <input type="text" name="delete_confirmation" placeholder="Saisissez SUPPRIMER" autocomplete="off" required>
                    </label>

                    <button class="cs-btn danger full" type="submit">Supprimer mon compte</button>
                </form>
            </section>
        </aside>
    </div>

    <section class="cs-card" data-tab-panel="notifications" hidden>
        <h2>Notifications</h2>
        <form method="POST" action="{{ route('client.notifications.preferences') }}">
            @csrf
            @include('client.partials.notification-preferences')
            <button class="cs-btn">Enregistrer</button>
        </form>
    </section>

    <section class="cs-card" data-tab-panel="region" hidden>
        <h2>Langue & Région</h2>
        <form method="POST" action="{{ route('client.settings.update') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="first_name" value="{{ $client->first_name }}">
            <input type="hidden" name="last_name" value="{{ $client->last_name }}">
            <input type="hidden" name="email" value="{{ $client->email }}">
            <input type="hidden" name="account_type" value="{{ $client->account_type ?: 'particulier' }}">

            <div class="cs-form-grid">
                <label>Langue
                    <select name="locale">
                        <option value="fr" @selected($client->locale === 'fr')>Français</option>
                        <option value="en" @selected($client->locale === 'en')>English</option>
                    </select>
                </label>
                <label>Devise
                    <select name="currency">
                        <option value="XOF" @selected($client->currency === 'XOF')>FCFA</option>
                        <option value="EUR" @selected($client->currency === 'EUR')>EUR</option>
                    </select>
                </label>
                <label>Fuseau horaire
                    <input name="timezone" value="{{ $client->timezone ?: 'Africa/Abidjan' }}">
                </label>
                <label>Format de date
                    <select name="date_format">
                        <option value="d/m/Y" @selected($client->date_format === 'd/m/Y')>JJ/MM/AAAA</option>
                        <option value="m/d/Y" @selected($client->date_format === 'm/d/Y')>MM/JJ/AAAA</option>
                    </select>
                </label>
            </div>

            <button class="cs-btn">Enregistrer</button>
        </form>
    </section>

    <section class="cs-card" data-tab-panel="referral" hidden>
        <h2>Parrainage</h2>
        <p>Code personnel</p>
        <input readonly value="{{ $client->referral_code ?: 'OVANIE-'.$client->id }}">
        <p>Lien de parrainage</p>
        <input readonly value="{{ url('/register?ref='.($client->referral_code ?: $client->id)) }}">
        <div class="cs-empty"><p>Les statistiques de parrainage apparaîtront ici.</p></div>
    </section>
</div>
@endsection
