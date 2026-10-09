@extends('pro.auth.access')
@section('title', 'Connexion')
@section('header_action')
    <a class="oa-header__link" href="{{ route('pro.register.choice') }}">@proicon('user', 'oa-ico')<span>Créer un compte</span></a>
@endsection
@section('content')
<section class="oa-card oa-split oa-split--login" aria-labelledby="login-title">
    <div class="oa-pane oa-pane--login">
        <h1 class="oa-title oa-title--xl oa-login-title" id="login-title"><span>Connectez-vous à</span>@include('pro.auth.access.logo', ['size' => 'lg'])</h1>
        <p class="oa-lead">Accédez à votre espace professionnel.</p>

        @include('pro.auth.access.alerts', ['except' => ['identifier']])

        <form method="POST" action="{{ route('pro.login.submit') }}" class="oa-form" novalidate>
            @csrf
            <div class="oa-field @error('identifier') is-invalid @enderror">
                <label class="oa-label" for="identifier">E-mail, téléphone ou identifiant <span class="oa-req">*</span></label>
                <div class="oa-control">
                    @proicon('user', 'oa-ico oa-control__icon')
                    <input class="oa-input" id="identifier" name="identifier" value="{{ old('identifier') }}" required maxlength="255"
                           autocomplete="username" autofocus placeholder="Ex. contact@monentreprise.ci, +225 07 12 34 56 78">
                </div>
                @error('identifier')<p class="oa-error">@proicon('alert', 'oa-ico'){{ $message }}</p>@enderror
            </div>

            <div class="oa-field">
                <label class="oa-label" for="password">Mot de passe <span class="oa-req">*</span></label>
                @include('pro.auth.access.password', ['id' => 'password', 'placeholder' => 'Votre mot de passe'])
            </div>

            <div class="oa-row oa-row--between">
                <label class="oa-check"><input type="checkbox" name="remember" value="1" @checked(old('remember', true))><span>Se souvenir de moi</span></label>
                <a class="oa-link oa-link--underline" href="{{ route('pro.password.request') }}">Mot de passe oublié ?</a>
            </div>

            <button class="oa-btn oa-btn--primary oa-btn--block oa-btn--lg" type="submit">Se connecter @proicon('chevron-right', 'oa-ico')</button>
        </form>

        <div class="oa-or"><span>OU</span></div>

        <div class="oa-soft-box oa-center">
            <p>Vous n’avez pas encore de compte ?</p>
            <a class="oa-btn oa-btn--outline" href="{{ route('pro.register.choice') }}">Créer un compte @proicon('chevron-right', 'oa-ico')</a>
        </div>

        <p class="oa-secure-note">
            <span>@proicon('shield', 'oa-ico') Connexion sécurisée</span>
            <small>Vos données sont protégées et confidentielles.</small>
        </p>
    </div>

    @include('pro.auth.access.hero', [
        'class' => 'oa-hero--login',
        'title' => 'La plateforme B2B<br><em>du secteur BTP</em><br>en Afrique de l’Ouest.',
        'text' => 'Connectez-vous pour accéder à vos projets, vos opportunités, vos fournisseurs et tous vos outils professionnels, réunis dans un seul espace sécurisé.',
        'benefits' => [
            ['handshake', 'Trouvez des partenaires fiables'],
            ['file', 'Recevez et répondez à des appels d’offres'],
            ['cart', 'Achetez vos matériaux et équipements'],
            ['chart', 'Développez votre activité dans toute la région'],
        ],
        'footer' => 'pro.auth.access.stats',
    ])
</section>
@endsection
