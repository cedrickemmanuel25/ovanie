@extends('layouts.staff')

@section('title', 'Créer un client | Commercial OVANIE')

@section('inline_styles')
@include('commercial.accounts.partials.professional-form-styles')
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Créer un client</h1>
        <p class="page-subtitle">Créez le compte acheteur avec uniquement ses informations d’identité, de contact et de connexion.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('commercial.clients.index') }}"><i data-lucide="arrow-left"></i>Retour aux clients</a>
    </div>
</div>

<div class="ocf-page ocf-client">
    <form method="POST" action="{{ route('commercial.clients.store') }}" class="ocf-card ocf-form" id="clientCreateForm" novalidate>
        @csrf

        <div class="ocf-card__head">
            <div class="ocf-card__head-main">
                <span class="ocf-card__icon"><i data-lucide="user-round-plus"></i></span>
                <div>
                    <h2>Nouveau compte client</h2>
                    <p>Les champs ci-dessous concernent uniquement le client. Aucune information de boutique, de logistique ou de paiement vendeur n’est demandée.</p>
                </div>
            </div>
            <span class="ocf-badge">Compte acheteur</span>
        </div>

        <div class="ocf-body">
            @if($errors->any())
                <div class="ocf-errors" role="alert">
                    <strong>Le compte n’a pas été créé.</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="ocf-section" aria-labelledby="clientIdentityTitle">
                <div class="ocf-section__title">
                    <span class="ocf-section__number">1</span>
                    <div>
                        <h3 id="clientIdentityTitle">Identité et contact</h3>
                        <p>Renseignez les coordonnées réelles communiquées par le client.</p>
                    </div>
                </div>

                <div class="ocf-grid">
                    <div class="ocf-field">
                        <label for="first_name">Prénom <span class="ocf-required">*</span></label>
                        <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" placeholder="Ex. Aïcha" required>
                        @error('first_name')<span class="ocf-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ocf-field">
                        <label for="last_name">Nom <span class="ocf-required">*</span></label>
                        <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" placeholder="Ex. Kouassi" required>
                        @error('last_name')<span class="ocf-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ocf-field">
                        <label for="email">Adresse e-mail <span class="ocf-required">*</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="nom@exemple.com" required>
                        @error('email')<span class="ocf-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ocf-field">
                        <label for="phone">Téléphone <span class="ocf-required">*</span></label>
                        <div class="ocf-phone">
                            <select name="phone_country" aria-label="Indicatif téléphonique" required>
                                <option value="+225" @selected(old('phone_country', '+225') === '+225')>CI +225</option>
                                <option value="+221" @selected(old('phone_country') === '+221')>SN +221</option>
                                <option value="+223" @selected(old('phone_country') === '+223')>ML +223</option>
                                <option value="+226" @selected(old('phone_country') === '+226')>BF +226</option>
                                <option value="+233" @selected(old('phone_country') === '+233')>GH +233</option>
                                <option value="+224" @selected(old('phone_country') === '+224')>GN +224</option>
                            </select>
                            <input id="phone" name="phone" inputmode="numeric" autocomplete="tel" value="{{ old('phone') }}" placeholder="07 00 00 00 00" required>
                        </div>
                        @error('phone')<span class="ocf-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </section>

            <div class="ocf-client-divider"></div>

            <section class="ocf-section" aria-labelledby="clientAccessTitle">
                <div class="ocf-section__title">
                    <span class="ocf-section__number">2</span>
                    <div>
                        <h3 id="clientAccessTitle">Accès au compte</h3>
                        <p>Définissez un mot de passe temporaire que le client pourra utiliser pour sa première connexion.</p>
                    </div>
                </div>

                <div class="ocf-grid">
                    <div class="ocf-field">
                        <label for="password">Mot de passe temporaire <span class="ocf-required">*</span></label>
                        <div class="ocf-password">
                            <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="8 caractères minimum" required>
                            <button type="button" data-toggle-password="password" aria-label="Afficher le mot de passe"><i data-lucide="eye"></i></button>
                        </div>
                        <span class="ocf-help">Utilisez au moins 8 caractères.</span>
                        @error('password')<span class="ocf-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ocf-field">
                        <label for="password_confirmation">Confirmation <span class="ocf-required">*</span></label>
                        <div class="ocf-password">
                            <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Répétez le mot de passe" required>
                            <button type="button" data-toggle-password="password_confirmation" aria-label="Afficher la confirmation"><i data-lucide="eye"></i></button>
                        </div>
                        <span class="ocf-help" id="passwordMatch">Les deux mots de passe doivent être identiques.</span>
                    </div>
                </div>
            </section>

            <div class="ocf-actions">
                <span class="ocf-help">Les champs marqués d’un astérisque sont obligatoires.</span>
                <div class="ocf-actions__right">
                    <a class="btn" href="{{ route('commercial.clients.index') }}">Annuler</a>
                    <button class="btn btn-orange" type="submit"><i data-lucide="user-round-check"></i>Créer le client</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('clientCreateForm');
    if (!form) return;

    const phone = document.getElementById('phone');
    phone?.addEventListener('input', () => {
        const digits = phone.value.replace(/\D/g, '').slice(0, 10);
        phone.value = digits.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
    });

    const password = document.getElementById('password');
    const confirmation = document.getElementById('password_confirmation');
    const match = document.getElementById('passwordMatch');

    const checkPassword = () => {
        const different = Boolean(confirmation?.value) && password?.value !== confirmation.value;
        confirmation?.setCustomValidity(different ? 'Les mots de passe ne correspondent pas.' : '');
        if (match) {
            match.textContent = different
                ? 'Les mots de passe ne correspondent pas.'
                : 'Les deux mots de passe doivent être identiques.';
            match.style.color = different ? '#be123c' : '';
        }
    };

    password?.addEventListener('input', checkPassword);
    confirmation?.addEventListener('input', checkPassword);

    form.querySelectorAll('[data-toggle-password]').forEach(button => {
        button.addEventListener('click', () => {
            const field = document.getElementById(button.dataset.togglePassword);
            if (!field) return;
            field.type = field.type === 'password' ? 'text' : 'password';
            button.innerHTML = field.type === 'password'
                ? '<i data-lucide="eye"></i>'
                : '<i data-lucide="eye-off"></i>';
            window.lucide?.createIcons();
        });
    });

    form.addEventListener('submit', event => {
        checkPassword();
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
})();
</script>
@endpush
