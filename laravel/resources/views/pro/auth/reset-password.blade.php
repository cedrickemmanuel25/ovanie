@extends('pro.auth.access')
@section('title', 'Nouveau mot de passe')
@section('content')
@if($state === 'success')
    <section class="oa-card oa-state" aria-labelledby="state-title">
        <span class="oa-state__icon oa-state__icon--success">@proicon('check', 'oa-ico')</span>
        <h1 class="oa-title oa-title--md" id="state-title">Votre mot de passe a été modifié !</h1>
        <p class="oa-lead oa-lead--sm">Vous pouvez maintenant vous connecter à votre compte OVANIE PRO avec votre nouveau mot de passe.</p>
        <div class="oa-note">@proicon('info', 'oa-ico')<span>Pour votre sécurité, toutes les sessions actives sur d’autres appareils ont été déconnectées.</span></div>
        <a class="oa-btn oa-btn--primary oa-btn--block" href="{{ route('pro.login') }}">Se connecter @proicon('chevron-right', 'oa-ico')</a>
        <hr class="oa-divider">
        <p class="oa-center"><a class="oa-link oa-link--strong" href="{{ route('pro.login') }}">@proicon('chevron-left', 'oa-ico') Retour à la connexion</a></p>
    </section>
@elseif($state === 'invalid')
    <section class="oa-card oa-state" aria-labelledby="state-title">
        <span class="oa-state__icon oa-state__icon--error">@proicon('link', 'oa-ico')</span>
        <h1 class="oa-title oa-title--md" id="state-title">Lien de réinitialisation invalide</h1>
        <p class="oa-lead oa-lead--sm">Cette demande n’est pas reconnue ou n’est plus disponible. Faites une nouvelle demande pour recevoir un code.</p>
        <a class="oa-btn oa-btn--primary oa-btn--block" href="{{ route('pro.password.request') }}">Faire une nouvelle demande @proicon('chevron-right', 'oa-ico')</a>
        <hr class="oa-divider">
        <p class="oa-center"><a class="oa-link oa-link--strong" href="{{ route('pro.login') }}">@proicon('chevron-left', 'oa-ico') Retour à la connexion</a></p>
    </section>
@else
    @php
        $expired = $state === 'expired';
        $codeError = $errors->first('code');
    @endphp
    <section class="oa-card oa-split oa-split--reset {{ $expired ? 'is-expired' : '' }}" aria-labelledby="reset-title">
        <div class="oa-pane">
            <span class="oa-badge-icon">@proicon('lock', 'oa-ico')</span>
            <h1 class="oa-title oa-title--lg" id="reset-title">Définir un nouveau<br>mot de passe</h1>
            <p class="oa-lead oa-lead--sm">Votre compte est presque prêt. Créez un nouveau mot de passe pour finaliser la réinitialisation.</p>

            @include('pro.auth.access.alerts', ['except' => ['code']])

            <form method="POST" action="{{ route('pro.password.update') }}" class="oa-form" novalidate
                  data-oa-reset data-verify-url="{{ route('pro.password.verify') }}" data-challenge="{{ $challenge }}">
                @csrf
                <input type="hidden" name="challenge" value="{{ $challenge }}">
                <input type="hidden" name="code" value="" data-oa-code-value>

                {{-- 1. Code de vérification --}}
                <div class="oa-step">
                    <span class="oa-step__num">1</span>
                    <div class="oa-step__body">
                        <p class="oa-step__title">Code de vérification <span class="oa-muted">(obligatoire)</span></p>
                        <p class="oa-step__help">Entrez le code reçu par e-mail ou par SMS.</p>
                        <div class="oa-code-row">
                            <div class="oa-code {{ $expired || $codeError ? 'is-error' : '' }}" data-oa-code role="group" aria-label="Code de vérification à 6 chiffres">
                                @for($i = 0; $i < 6; $i++)
                                    <input class="oa-code__digit" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" maxlength="1"
                                           aria-label="Chiffre {{ $i + 1 }}" @if($i === 0 && ! $expired) autofocus @endif @disabled($expired)>
                                @endfor
                            </div>

                            @if($expired)
                                <div class="oa-code-status is-expired">
                                    <span class="oa-code-status__title">@proicon('clock', 'oa-ico') Code expiré</span>
                                    <small>Ce code n’est plus valide. Demandez un nouveau code pour continuer.</small>
                                    <button class="oa-btn oa-btn--outline-orange oa-btn--sm" type="submit" form="oa-resend">Renvoyer un nouveau code</button>
                                </div>
                            @else
                                <div class="oa-code-status" data-oa-code-status data-state="{{ $codeError ? 'invalid' : 'idle' }}">
                                    <span class="oa-round-check">@proicon('check', 'oa-ico')</span>
                                    <span class="oa-code-status__text">
                                        <strong data-oa-code-title>{{ $codeError ? 'Code incorrect' : 'Code à 6 chiffres' }}</strong>
                                        <small data-oa-code-text>{{ $codeError ?: 'Saisissez le code reçu pour continuer.' }}</small>
                                    </span>
                                </div>
                            @endif
                        </div>
                        @if($expired)
                            <p class="oa-error">@proicon('alert', 'oa-ico')Ce code est expiré. Veuillez demander un nouveau code.</p>
                        @else
                            <p class="oa-step__help oa-step__help--after">Vous n’avez rien reçu ? <button class="oa-link-btn" type="submit" form="oa-resend">Renvoyer un code</button></p>
                        @endif
                    </div>
                </div>

                <div class="oa-reset-grid" data-oa-password-steps @if($expired) aria-disabled="true" @endif>
                    <div>
                        {{-- 2. Nouveau mot de passe --}}
                        <div class="oa-step">
                            <span class="oa-step__num">2</span>
                            <div class="oa-step__body">
                                <label class="oa-step__title" for="password">Nouveau mot de passe</label>
                                @include('pro.auth.access.password', ['id' => 'password', 'autocomplete' => 'new-password', 'rules' => true, 'placeholder' => 'Nouveau mot de passe'])
                                <div class="oa-strength" data-oa-strength><span></span><span></span><span></span><span></span><em data-oa-strength-label></em></div>
                                @error('password')<p class="oa-error">@proicon('alert', 'oa-ico'){{ $message }}</p>@enderror
                            </div>
                        </div>
                        {{-- 3. Confirmation --}}
                        <div class="oa-step">
                            <span class="oa-step__num">3</span>
                            <div class="oa-step__body">
                                <label class="oa-step__title" for="password_confirmation">Confirmez le nouveau mot de passe</label>
                                @include('pro.auth.access.password', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'placeholder' => 'Confirmez le mot de passe'])
                                <p class="oa-error" data-oa-match hidden>@proicon('alert', 'oa-ico')Les deux mots de passe ne correspondent pas.</p>
                            </div>
                        </div>
                    </div>
                    @include('pro.auth.access.password-rules')
                </div>

                <div class="oa-note">
                    @proicon('info', 'oa-ico')
                    <span>Pour votre sécurité, ne partagez jamais votre mot de passe.<br>Utilisez un mot de passe unique pour votre compte OVANIE PRO.</span>
                </div>

                <div class="oa-actions">
                    <a class="oa-btn oa-btn--outline" href="{{ route('pro.password.request') }}">@proicon('chevron-left', 'oa-ico') Retour</a>
                    <button class="oa-btn oa-btn--primary" type="submit" data-oa-reset-submit @disabled($expired)>Modifier mon mot de passe @proicon('chevron-right', 'oa-ico')</button>
                </div>
            </form>

            @if($challenge)
                <form method="POST" action="{{ route('pro.password.resend') }}" id="oa-resend" hidden>@csrf
                    <input type="hidden" name="challenge" value="{{ $challenge }}">
                </form>
            @endif
        </div>

        @include('pro.auth.access.hero', [
            'class' => 'oa-hero--reset',
            'title' => 'Sécurisez<br>votre compte<br><em>OVANIE PRO</em>',
            'text' => 'Un mot de passe robuste protège vos informations, vos projets et vos opportunités d’affaires.',
            'benefits' => [
                ['shield', 'Compte sécurisé et confidentiel'],
                ['file', 'Protection de vos données personnelles'],
                ['users', 'Accès sécurisé aux opportunités B2B'],
            ],
        ])
    </section>
@endif
@endsection
