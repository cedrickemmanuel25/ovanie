@extends('pro.auth.access')
@section('title', 'Créez votre compte')
@include('pro.publication.partials.setup')
@section('content')
@php
    $isOffer = $kind === 'offer';
    $countryFlags = [
        'Côte d’Ivoire' => 'ci', 'Sénégal' => 'sn', 'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Ghana' => 'gh', 'Guinée' => 'gn',
        'Togo' => 'tg', 'Bénin' => 'bj', 'Niger' => 'ne', 'Cameroun' => 'cm', 'Nigeria' => 'ng', 'Maroc' => 'ma',
        'France' => 'fr', 'Chine' => 'cn', 'Turquie' => 'tr', 'Émirats arabes unis' => 'ae',
    ];
    $codeFlags = collect($phoneCodes)->mapWithKeys(fn ($label, $code) => [$code => strtolower(strtok($label, ' '))]);
    $country = old('residence_country', 'Côte d’Ivoire');
    $code = old('phone_country', '+225');
    $flagBase = asset('ovanie-pro/flags');
@endphp
<div class="op-layout">
    <form method="POST" action="{{ route('pro.publish.register', ['type' => $type]) }}" class="op-main" novalidate>
        @csrf
        @include('pro.publication.partials.topline', ['step' => 1, 'kind' => $kind])
        <h1 class="op-title">Créez votre compte</h1>
        <p class="op-lead op-lead--sm">{{ $isOffer
            ? 'Ces informations vous permettront de publier votre offre et de suivre les réponses reçues.'
            : 'Créez votre compte pour publier votre besoin et recevoir les propositions des professionnels intéressés.' }}</p>

        @include('pro.auth.access.alerts', ['except' => ['last_name', 'first_name', 'residence_country', 'phone', 'email', 'preferred_language', 'password', 'terms']])

        <section class="op-card">
            <h2 class="op-section"><span>1</span>Vos informations</h2>
            <div class="op-grid op-grid--2">
                <div class="op-field @error('last_name') is-invalid @enderror">
                    <label for="last_name">Nom <em>*</em></label>
                    <input class="op-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name" placeholder="Ex. Diarra">
                    @error('last_name')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('first_name') is-invalid @enderror">
                    <label for="first_name">Prénoms <em>*</em></label>
                    <input class="op-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" placeholder="Ex. Moussa Amadou">
                    @error('first_name')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('residence_country') is-invalid @enderror">
                    <label for="residence_country">Pays de résidence <em>*</em></label>
                    <div class="op-select op-select--flag" data-op-flag-select>
                        <img src="{{ isset($countryFlags[$country]) ? $flagBase . '/' . $countryFlags[$country] . '.svg' : asset('ovanie-pro/flags/ci.svg') }}" alt="" data-flag-img>
                        <select id="residence_country" name="residence_country" required data-flag-base="{{ $flagBase }}">
                            @foreach($countries as $c)
                                <option value="{{ $c }}" data-flag="{{ $countryFlags[$c] ?? '' }}" @selected($country === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('residence_country')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('phone') is-invalid @enderror">
                    <label for="phone">Téléphone <em>*</em></label>
                    <div class="op-phone">
                        <span class="op-phone__code" data-op-flag-select>
                            <img src="{{ $flagBase . '/' . ($codeFlags[$code] ?? 'ci') . '.svg' }}" alt="" data-flag-img>
                            <select name="phone_country" aria-label="Indicatif" data-flag-base="{{ $flagBase }}">
                                @foreach($phoneCodes as $c => $label)
                                    <option value="{{ $c }}" data-flag="{{ $codeFlags[$c] }}" @selected($code === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </span>
                        <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') ? \Illuminate\Support\Str::after(old('phone'), $code) : '' }}" required maxlength="20" autocomplete="tel-national" placeholder="07 12 34 56 78">
                    </div>
                    @error('phone')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('email') is-invalid @enderror">
                    <label for="email">Adresse e-mail <em>*</em></label>
                    <div class="op-icon-input">@proicon('mail', 'oa-ico')<input class="op-input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="Ex. moussa.diarra@email.com"></div>
                    @error('email')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('preferred_language') is-invalid @enderror">
                    <label for="preferred_language">Langue préférée <em>*</em></label>
                    <div class="op-icon-input op-select">@proicon('globe', 'oa-ico')
                        <select id="preferred_language" name="preferred_language" required>
                            @foreach($languages as $c => $label)<option value="{{ $c }}" @selected(old('preferred_language', 'fr') === $c)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="op-card">
            <h2 class="op-section"><span>2</span>Sécurité du compte</h2>
            <div class="op-grid op-grid--2">
                <div class="op-field @error('password') is-invalid @enderror">
                    <label for="password">Mot de passe <em>*</em></label>
                    @include('pro.auth.access.password', ['id' => 'password', 'autocomplete' => 'new-password', 'rules' => true, 'placeholder' => 'Créez un mot de passe sécurisé'])
                    <div class="oa-strength op-strength" data-oa-strength><span></span><span></span><span></span><span></span><em data-oa-strength-label></em></div>
                    @error('password')<p class="op-error">{{ $message }}</p>@else<p class="op-hint">Utilisez au moins 8 caractères avec lettres, chiffres et caractère spécial.</p>@enderror
                </div>
                <div class="op-field">
                    <label for="password_confirmation">Confirmez votre mot de passe <em>*</em></label>
                    @include('pro.auth.access.password', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'placeholder' => 'Répétez votre mot de passe'])
                    <p class="op-error" data-oa-match hidden>Les deux mots de passe ne correspondent pas.</p>
                </div>
            </div>
        </section>

        <section class="op-card">
            <h2 class="op-section"><span>3</span>Conditions d’utilisation</h2>
            <label class="op-check @error('terms') is-invalid @enderror">
                <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                <span>J’accepte les <a href="{{ route('terms') }}" target="_blank" rel="noopener">Conditions générales d’utilisation</a> et la <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Politique de confidentialité</a> d’OVANIE PRO. <em>*</em></span>
            </label>
            @error('terms')<p class="op-error">{{ $message }}</p>@enderror
            <p class="op-privacy">@proicon('lock', 'oa-ico') Vos coordonnées personnelles ne seront pas affichées {{ $isOffer ? 'dans votre offre publique' : 'publiquement dans votre demande' }}.</p>

            <div class="op-actions">
                <a class="oa-btn oa-btn--outline" href="{{ route('pro.publish.start', ['type' => $type]) }}">@proicon('chevron-left', 'oa-ico') Retour</a>
                <div class="op-actions__end">
                    <button class="oa-btn oa-btn--primary" type="submit">Créer mon compte et continuer @proicon('arrow-right', 'oa-ico')</button>
                    <span class="op-header-login">Vous avez déjà un compte ? <a href="{{ route('pro.login') }}">Se connecter</a></span>
                </div>
            </div>
        </section>
    </form>

    <aside class="op-side op-side--account">
        @if($isOffer)
            @include('pro.auth.access.hero', [
                'class' => 'op-hero',
                'title' => 'Publiez simplement.<br>Gérez vos réponses<br>depuis <em>un seul compte.</em>',
                'text' => null,
                'benefits' => [
                    ['user', 'Un compte unique', 'Accédez à votre espace Annonceur avec une seule connexion.'],
                    ['file', 'Gérez toutes vos offres', 'Publiez, modifiez et suivez vos offres facilement.'],
                    ['send', 'Recevez les réponses des professionnels', 'Échangez avec des acheteurs, entreprises et partenaires intéressés.'],
                ],
            ])
            @include('pro.publication.partials.tip', ['text' => 'Vous n’avez pas besoin de créer immédiatement un profil professionnel complet pour publier une offre.'])
        @else
            @include('pro.auth.access.hero', [
                'class' => 'op-hero',
                'title' => 'Trouvez les produits,<br>équipements ou services<br>dont vous <em>avez besoin.</em>',
                'text' => null,
                'benefits' => [
                    ['file', 'Décrivez votre besoin', 'Expliquez précisément ce que vous recherchez.'],
                    ['file-search', 'Recevez des propositions', 'Obtenez des réponses de professionnels qualifiés.'],
                    ['users', 'Échangez avec des professionnels', 'Comparez, posez vos questions et trouvez la meilleure solution.'],
                ],
            ])
            @include('pro.publication.partials.tip', ['text' => '<b>Un compte simple suffit pour publier un besoin.</b><br>Les RFQ et appels d’offres avancés restent disponibles avec un profil professionnel OVANIE PRO.'])
        @endif
    </aside>
</div>
@endsection
