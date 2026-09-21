@extends('driver.layouts.app')
@section('title', 'Mon profil | OVANIE Livreur')
@section('page-title', 'Mon profil')

@section('content')
<div class="driver-page-heading">
    <div>
        <div class="driver-breadcrumb"><span>Espace livreur</span><i data-lucide="chevron-right"></i><strong>Mon profil</strong></div>
        <h1>Mon profil</h1>
        <p>Gérez votre disponibilité, vos coordonnées et votre code d’accès.</p>
    </div>
</div>

<div class="driver-profile-layout">
    <aside class="driver-profile-summary">
        <div class="driver-profile-cover"></div>
        <div class="driver-profile-summary__body">
            <div class="driver-profile-avatar">{{ $driver->initials }}</div>
            <h2>{{ $driver->name }}</h2>
            <p>{{ $driver->vehicle ?: 'Véhicule non renseigné' }}</p>

            <span class="driver-availability driver-availability--{{ strtolower($driver->status ?: 'disponible') }}">
                <i data-lucide="circle"></i>
                {{ $driver->status ?: 'Disponible' }}
            </span>

            <div class="driver-profile-facts">
                <div><i data-lucide="phone"></i><span><small>Téléphone</small><strong>{{ $driver->phone }}</strong></span></div>
                <div><i data-lucide="map-pin"></i><span><small>Zone</small><strong>{{ $driver->zone ?: 'À confirmer' }}</strong></span></div>
                <div><i data-lucide="truck"></i><span><small>Véhicule</small><strong>{{ $driver->vehicle ?: 'À confirmer' }}</strong></span></div>
            </div>
        </div>
    </aside>

    <section class="driver-panel driver-profile-form-panel">
        <div class="driver-panel__header">
            <div>
                <h2>Informations du compte</h2>
                <p>Les informations d’identité sont gérées par le responsable logistique.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('driver.profile.update') }}" class="driver-profile-form">
            @csrf
            @method('PATCH')

            <div class="driver-form-grid">
                <div class="driver-form-group">
                    <label>Nom complet</label>
                    <div class="driver-input-control driver-input-control--disabled">
                        <span class="driver-input-icon"><i data-lucide="user-round"></i></span>
                        <input value="{{ $driver->name }}" disabled>
                    </div>
                </div>
                <div class="driver-form-group">
                    <label>Téléphone de connexion</label>
                    <div class="driver-input-control driver-input-control--disabled">
                        <span class="driver-input-icon"><i data-lucide="phone"></i></span>
                        <input value="{{ $driver->phone }}" disabled>
                    </div>
                </div>
                <div class="driver-form-group">
                    <label>Adresse e-mail</label>
                    <div class="driver-input-control">
                        <span class="driver-input-icon"><i data-lucide="mail"></i></span>
                        <input type="email" name="email" value="{{ old('email', $driver->email) }}" placeholder="livreur@exemple.com">
                    </div>
                </div>
                <div class="driver-form-group">
                    <label>Disponibilité</label>
                    <div class="driver-input-control">
                        <span class="driver-input-icon"><i data-lucide="activity"></i></span>
                        <select name="availability">
                            <option value="Disponible" @selected(old('availability', $driver->status) === 'Disponible')>Disponible</option>
                            <option value="Indisponible" @selected(old('availability', $driver->status) === 'Indisponible')>Indisponible</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="driver-form-section">
                <div class="driver-form-section__head">
                    <span><i data-lucide="key-round"></i></span>
                    <div><h3>Modifier le code d’accès</h3><p>Laissez les champs vides pour conserver votre code actuel.</p></div>
                </div>
                <div class="driver-form-grid driver-form-grid--three">
                    <div class="driver-form-group">
                        <label>Code actuel</label>
                        <input type="password" name="current_pin" inputmode="numeric" maxlength="6" autocomplete="current-password" placeholder="••••••">
                    </div>
                    <div class="driver-form-group">
                        <label>Nouveau code</label>
                        <input type="password" name="new_pin" inputmode="numeric" maxlength="6" autocomplete="new-password" placeholder="6 chiffres">
                    </div>
                    <div class="driver-form-group">
                        <label>Confirmer le code</label>
                        <input type="password" name="new_pin_confirmation" inputmode="numeric" maxlength="6" autocomplete="new-password" placeholder="6 chiffres">
                    </div>
                </div>
            </div>

            <div class="driver-form-actions">
                <button class="driver-btn driver-btn--primary" type="submit">
                    <i data-lucide="save"></i>
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </section>
</div>
@endsection
