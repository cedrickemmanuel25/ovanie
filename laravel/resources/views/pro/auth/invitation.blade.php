@extends('pro.auth.access')
@section('title', 'Invitation collaborateur')
@section('content')
@php
    $stateMessages = [
        'invalid' => ['link', 'Invitation introuvable', 'Ce lien n’est pas valide. Vérifiez qu’il est complet ou demandez une nouvelle invitation à l’administrateur de l’entreprise.'],
        'expired' => ['clock', 'Invitation expirée', 'Ce lien a expiré. Demandez à l’administrateur de l’entreprise de vous renvoyer l’invitation.'],
        'accepted' => ['check', 'Invitation déjà utilisée', 'Cette invitation a déjà été acceptée. Connectez-vous pour accéder à l’entreprise.'],
        'refused' => ['x', 'Invitation refusée', 'Vous avez refusé cette invitation. Elle ne peut plus être utilisée.'],
        'cancelled' => ['x', 'Invitation annulée', 'L’entreprise a annulé cette invitation.'],
    ];
@endphp

@if($state !== 'pending')
    @php
        [$icon, $heading, $text] = $stateMessages[$state] ?? $stateMessages['invalid'];
    @endphp
    <section class="oa-card oa-state" aria-labelledby="state-title">
        <span class="oa-state__icon {{ $state === 'accepted' ? 'oa-state__icon--success' : 'oa-state__icon--error' }}">@proicon($icon, 'oa-ico')</span>
        <h1 class="oa-title oa-title--md" id="state-title">{{ $heading }}</h1>
        <p class="oa-lead oa-lead--sm">{{ $text }}</p>
        <a class="oa-btn oa-btn--primary oa-btn--block" href="{{ $user ? route('pro.account') : route('pro.login') }}">{{ $user ? 'Aller à mon espace' : 'Se connecter' }} @proicon('chevron-right', 'oa-ico')</a>
    </section>
@else
    @php
        $organization = $invitation->organization;
        $emailKnown = (bool) $invitation->email;
        $flags = collect($phoneCodes)->mapWithKeys(fn ($label, $code) => [$code => strtolower(strtok($label, ' '))]);
        $currentCode = old('phone_country', $phoneCode);
    @endphp
    <div class="oa-invite">
        {{-- Colonne 1 : l'invitation --}}
        <section class="oa-card oa-invite__details" aria-labelledby="org-title">
            <div class="oa-invite__org">
                <span class="oa-invite__org-icon">@proicon('mail-users', 'oa-ico')</span>
                <div>
                    <h1 class="oa-invite__org-name" id="org-title">{{ $organization->display_name }}</h1>
                    <p>vous invite à rejoindre son équipe sur OVANIE PRO</p>
                </div>
            </div>

            <div class="oa-note">
                @proicon('info', 'oa-ico')
                <span>Vous avez été invité à collaborer avec cette entreprise sur OVANIE PRO. Rejoignez son équipe pour accéder aux projets, aux opportunités et aux outils de collaboration.</span>
            </div>

            <h2 class="oa-subhead">Détails de l’invitation</h2>
            <dl class="oa-details">
                <div class="oa-details__row">
                    @proicon('building', 'oa-ico')
                    <div><dt>Entreprise</dt><dd>{{ $organization->display_name }}</dd></div>
                </div>
                <div class="oa-details__row">
                    @proicon('shield-user', 'oa-ico')
                    <div>
                        <dt>Permission attribuée</dt>
                        <dd><span class="oa-tag oa-tag--orange oa-tag--lg">{{ $permissionLabel }}</span></dd>
                        @if($permissionDescription)<dd class="oa-details__help">{{ $permissionDescription }}</dd>@endif
                    </div>
                </div>
                <div class="oa-details__row">
                    @proicon('user', 'oa-ico')
                    <div>
                        <dt>Invité par</dt>
                        <dd>{{ $inviterName ?: 'Un administrateur de l’entreprise' }}</dd>
                        @if($inviterTitle)<dd class="oa-details__help">{{ $inviterTitle }}</dd>@endif
                        <dd class="oa-details__help">le {{ $invitation->created_at?->locale('fr')->translatedFormat('j F Y') }}</dd>
                    </div>
                </div>
            </dl>

            <div class="oa-note oa-note--orange">
                @proicon('info', 'oa-ico')
                <div>
                    <strong>À propos de cette invitation</strong>
                    <span>En rejoignant cette entreprise, vous utiliserez votre compte OVANIE PRO pour accéder aux espaces et fonctionnalités selon la permission qui vous a été attribuée. Cette invitation expire le {{ $invitation->expires_at?->locale('fr')->translatedFormat('j F Y à H\hi') }}.</span>
                </div>
            </div>
        </section>

        {{-- Colonne 2 : accepter --}}
        <section class="oa-card oa-invite__accept" aria-labelledby="accept-title">
            <span class="oa-badge-icon oa-badge-icon--orange">@proicon('user-plus', 'oa-ico')</span>
            <h2 class="oa-title oa-title--md" id="accept-title">Acceptez l’invitation</h2>
            <p class="oa-lead oa-lead--sm">Rejoignez l’équipe et commencez à collaborer dès maintenant.</p>

            @include('pro.auth.access.alerts', ['except' => ['last_name', 'first_name', 'phone', 'email', 'password', 'terms']])

            @if($user)
                @if($wrongAccount)
                    <div class="oa-alert oa-alert--warn">@proicon('alert', 'oa-ico')<span>Vous êtes connecté avec <strong>{{ $user->email }}</strong>, mais cette invitation est destinée à un autre compte. Déconnectez-vous puis connectez-vous avec le bon compte.</span></div>
                    <button class="oa-btn oa-btn--primary oa-btn--block" type="submit" form="oa-logout">Changer de compte</button>
                @else
                    <div class="oa-panel">
                        <span class="oa-panel__icon">@proicon('user', 'oa-ico')</span>
                        <div>
                            <strong>Connecté en tant que {{ $user->full_name }}</strong>
                            <span>{{ $user->email }} · L’entreprise sera ajoutée à votre compte existant.</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('pro.invitation.accept', ['token' => $token]) }}" class="oa-actions">
                        @csrf
                        <button class="oa-btn oa-btn--outline" type="submit" formaction="{{ route('pro.invitation.refuse', ['token' => $token]) }}"
                                data-oa-confirm="Refuser cette invitation ? Le lien ne pourra plus être utilisé.">@proicon('x', 'oa-ico') Refuser</button>
                        <button class="oa-btn oa-btn--primary" type="submit">@proicon('check', 'oa-ico') Accepter l’invitation</button>
                    </form>
                @endif
            @else
                <div class="oa-panel">
                    <span class="oa-panel__icon">@proicon('user-plus', 'oa-ico')</span>
                    <div>
                        <strong>Vous avez déjà un compte OVANIE PRO ?</strong>
                        <span>Connectez-vous avec votre compte existant pour accepter cette invitation et rejoindre directement l’équipe.</span>
                        <a class="oa-btn oa-btn--primary oa-btn--block" href="{{ route('pro.login') }}">@proicon('arrow-right', 'oa-ico') Se connecter et accepter</a>
                    </div>
                </div>

                @if($existingAccount)
                    <div class="oa-note">@proicon('info', 'oa-ico')<span>Un compte OVANIE PRO existe déjà pour {{ $invitation->email ?: $invitation->phone }}. Connectez-vous pour accepter l’invitation.</span></div>
                    <form method="POST" action="{{ route('pro.invitation.refuse', ['token' => $token]) }}" class="oa-mt">@csrf
                        <button class="oa-btn oa-btn--outline oa-btn--block" type="submit" data-oa-confirm="Refuser cette invitation ? Le lien ne pourra plus être utilisé.">@proicon('x', 'oa-ico') Refuser l’invitation</button>
                    </form>
                @else
                    <div class="oa-or"><span>ou</span></div>

                    <form method="POST" action="{{ route('pro.invitation.accept', ['token' => $token]) }}" class="oa-panel oa-panel--form" novalidate>
                        @csrf
                        <div class="oa-panel__head">
                            <span class="oa-panel__icon">@proicon('user', 'oa-ico')</span>
                            <div><strong>Vous n’avez pas encore de compte ?</strong><span>Créez un compte pour accepter cette invitation.</span></div>
                        </div>

                        <div class="oa-grid-2">
                            <div class="oa-field @error('last_name') is-invalid @enderror">
                                <label class="oa-label" for="last_name">Nom <span class="oa-req">*</span></label>
                                <input class="oa-input oa-input--plain" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name" placeholder="Ex. Diarra">
                                @error('last_name')<p class="oa-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="oa-field @error('first_name') is-invalid @enderror">
                                <label class="oa-label" for="first_name">Prénoms <span class="oa-req">*</span></label>
                                <input class="oa-input oa-input--plain" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" placeholder="Ex. Moussa Amadou">
                                @error('first_name')<p class="oa-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if($emailKnown)
                            <div class="oa-field @error('phone') is-invalid @enderror">
                                <label class="oa-label" for="phone">Téléphone <span class="oa-req">*</span> <span class="oa-label__hint">· identifiant : {{ $invitation->email }}</span></label>
                                <div class="oa-phone" data-oa-phone>
                                    <span class="oa-phone__country">
                                        <img src="{{ asset('ovanie-pro/flags/' . ($flags[$currentCode] ?? 'ci') . '.svg') }}" alt="" data-oa-flag data-flag-base="{{ asset('ovanie-pro/flags') }}">
                                        <select name="phone_country" aria-label="Indicatif du pays" data-oa-phone-code>
                                            @foreach($phoneCodes as $code => $label)
                                                <option value="{{ $code }}" data-flag="{{ $flags[$code] }}" @selected($currentCode === $code)>{{ $code }}</option>
                                            @endforeach
                                        </select>
                                        @proicon('chevron-down', 'oa-ico')
                                    </span>
                                    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') ? \Illuminate\Support\Str::after(old('phone'), $currentCode) : '' }}" required maxlength="20" autocomplete="tel-national" placeholder="07 12 34 56 78">
                                </div>
                                @error('phone')<p class="oa-error">{{ $message }}</p>@enderror
                            </div>
                        @else
                            <div class="oa-field @error('email') is-invalid @enderror">
                                <label class="oa-label" for="email">E-mail <span class="oa-req">*</span></label>
                                <div class="oa-control">
                                    @proicon('mail', 'oa-ico oa-control__icon')
                                    <input class="oa-input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="Ex. moussa.diarra@monentreprise.ci">
                                </div>
                                @error('email')<p class="oa-error">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        <div class="oa-grid-2">
                            <div class="oa-field @error('password') is-invalid @enderror">
                                <label class="oa-label" for="password">Mot de passe <span class="oa-req">*</span></label>
                                @include('pro.auth.access.password', ['id' => 'password', 'autocomplete' => 'new-password', 'rules' => true, 'placeholder' => 'Au moins 8 caractères'])
                            </div>
                            <div class="oa-field">
                                <label class="oa-label" for="password_confirmation">Confirmer le mot de passe <span class="oa-req">*</span></label>
                                @include('pro.auth.access.password', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'placeholder' => 'Répétez votre mot de passe'])
                            </div>
                        </div>
                        @error('password')<p class="oa-error">{{ $message }}</p>@enderror

                        @include('pro.auth.access.password-rules', ['class' => 'oa-rules--compact'])

                        <div class="oa-field @error('terms') is-invalid @enderror">
                            <label class="oa-check oa-check--terms">
                                <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                                <span>J’accepte les <a href="{{ route('terms') }}" target="_blank" rel="noopener">Conditions générales d’utilisation</a> et la <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Politique de confidentialité</a>. <span class="oa-req">*</span></span>
                            </label>
                            @error('terms')<p class="oa-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="oa-actions">
                            <button class="oa-btn oa-btn--outline" type="submit" formaction="{{ route('pro.invitation.refuse', ['token' => $token]) }}" formnovalidate
                                    data-oa-confirm="Refuser cette invitation ? Le lien ne pourra plus être utilisé.">@proicon('x', 'oa-ico') Refuser</button>
                            <button class="oa-btn oa-btn--primary" type="submit">@proicon('check', 'oa-ico') Accepter l’invitation</button>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        {{-- Colonne 3 : panneau --}}
        @include('pro.auth.access.hero', [
            'class' => 'oa-hero--invite',
            'title' => 'Collaborez sur<br>des projets <em>BTP</em><br>dans toute<br><em>l’Afrique de l’Ouest</em>.',
            'text' => null,
            'benefits' => [
                ['users', 'Une collaboration simplifiée', 'Rejoignez votre équipe et travaillez sur les mêmes projets.'],
                ['file', 'Des outils professionnels', 'Accédez aux projets, documents et communications de l’entreprise.'],
                ['chart', 'Plus d’opportunités', 'Participez à la croissance du secteur BTP en Afrique de l’Ouest.'],
            ],
            'footer' => 'pro.auth.access.hero-secure',
        ])
    </div>
@endif
@endsection
