@extends('pro.auth.access')
@section('title', 'Mot de passe oublié')
@php
    $sent = !empty($challenge);
@endphp
@section('container_class', $sent ? 'oa-layout-forgot is-sent' : 'oa-layout-forgot')
@section('content')
<section class="oa-card oa-split oa-split--forgot" aria-labelledby="forgot-title">
    <div class="oa-pane">
        <span class="oa-badge-icon">@proicon('lock', 'oa-ico')</span>
        <h1 class="oa-title oa-title--lg" id="forgot-title">Réinitialiser votre<br>mot de passe</h1>
        <p class="oa-lead oa-lead--sm">Entrez l’adresse e-mail ou le numéro associé à votre compte.</p>

        @include('pro.auth.access.alerts', ['except' => ['identifier']])

        <form method="POST" action="{{ route('pro.password.email') }}" class="oa-form" novalidate>
            @csrf
            <div class="oa-field @error('identifier') is-invalid @enderror">
                <label class="oa-label" for="identifier">E-mail ou téléphone <span class="oa-req">*</span></label>
                <div class="oa-control">
                    @proicon('user', 'oa-ico oa-control__icon')
                    <input class="oa-input" id="identifier" name="identifier" value="{{ old('identifier') }}" required maxlength="255"
                           autocomplete="username" @unless($sent) autofocus @endunless placeholder="Ex. contact@monentreprise.ci ou +225 07 12 34 56 78">
                </div>
                @error('identifier')<p class="oa-error">@proicon('alert', 'oa-ico'){{ $message }}</p>@enderror
            </div>
            <button class="oa-btn oa-btn--primary oa-btn--block oa-btn--lg" type="submit">Recevoir les instructions @proicon('chevron-right', 'oa-ico')</button>
        </form>

        <hr class="oa-divider">
        <p class="oa-center"><a class="oa-link oa-link--strong" href="{{ route('pro.login') }}">@proicon('chevron-left', 'oa-ico') Retour à la connexion</a></p>

        <div class="oa-note">
            @proicon('info', 'oa-ico')
            <span>Nous vous enverrons un code sécurisé pour réinitialiser votre mot de passe si ces informations correspondent à un compte OVANIE PRO.</span>
        </div>
    </div>

    @include('pro.auth.access.hero', [
        'class' => 'oa-hero--forgot',
        'title' => 'Une plateforme<br>professionnelle<br><em>et sécurisée</em>',
        'text' => 'Vos données sont protégées et traitées en toute confidentialité selon les standards internationaux de sécurité.',
        'benefits' => [
            ['shield', 'Connexion sécurisée et chiffrée'],
            ['file', 'Protection de vos données personnelles'],
            ['users', 'Une équipe locale à votre écoute'],
        ],
        'footer' => 'pro.auth.access.hero-brand',
    ])
</section>

@if($sent)
    <aside class="oa-card oa-sent" aria-labelledby="sent-title">
        <div class="oa-sent__art" aria-hidden="true">
            <span class="oa-sent__mail">@proicon('mail', 'oa-ico')</span>
            <span class="oa-sent__check">@proicon('check', 'oa-ico')</span>
        </div>
        <h2 class="oa-title oa-title--md oa-center" id="sent-title">Instructions envoyées</h2>
        <div class="oa-sent__confirm">
            <span class="oa-round-check">@proicon('check', 'oa-ico')</span>
            <span>Si ces informations correspondent à un compte, vous recevrez les instructions nécessaires pour réinitialiser votre mot de passe.</span>
        </div>
        <hr class="oa-divider">
        <h3 class="oa-subhead">Que faire ensuite ?</h3>
        <ul class="oa-next">
            <li><span class="oa-next__icon oa-next__icon--orange">@proicon('mail', 'oa-ico')</span><span>Vérifiez votre boîte e-mail, vos SMS et vos courriers indésirables (spam).</span></li>
            <li><span class="oa-next__icon">@proicon('key', 'oa-ico')</span><span>Saisissez le code de vérification reçu pour définir un nouveau mot de passe.</span></li>
            <li><span class="oa-next__icon">@proicon('clock', 'oa-ico')</span><span>Le code est valable pendant une durée limitée pour des raisons de sécurité.</span></li>
        </ul>
        <a class="oa-btn oa-btn--primary oa-btn--block" href="{{ route('pro.password.reset', ['challenge' => $challenge]) }}">Saisir mon code @proicon('chevron-right', 'oa-ico')</a>
        <a class="oa-btn oa-btn--outline oa-btn--block" href="{{ route('pro.login') }}">@proicon('chevron-left', 'oa-ico') Retour à la connexion</a>
    </aside>
@endif
@endsection
