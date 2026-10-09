@extends('pro.auth.access')
@section('title', 'Créons votre compte')
@include('pro.auth.access.business-setup')
@section('content')
@php
    $flagCodes = (array) config('ovanie_pro.flags', []);
    $flagBase = asset('ovanie-pro/flags');
    $country = old('residence_country', 'Côte d’Ivoire');
    $code = old('phone_country', '+225');
    $codeFlags = collect($phoneCodes)->mapWithKeys(fn ($label, $c) => [$c => strtolower(strtok($label, ' '))]);
    $number = old('phone') ? \Illuminate\Support\Str::after(old('phone'), $code) : '';
    $fieldErrors = ['last_name', 'first_name', 'residence_country', 'phone', 'email', 'preferred_language', 'password', 'terms'];
@endphp
<form method="POST" action="{{ route('pro.register.submit', ['accountType' => $accountType]) }}" class="pb-page pb-page--register" novalidate data-pb-register>
    @csrf
    @if($profile)<input type="hidden" name="profile" value="{{ $profile }}">@endif
    <div class="pb-main">
        @include('pro.auth.access.stepper', ['current' => 1])
        <h1 class="pb-title">Créons votre compte</h1>
        <p class="pb-lead">Ces informations concernent la personne qui utilisera OVANIE PRO.<br>Les informations de votre entreprise seront demandées ensuite.</p>

        @include('pro.auth.access.alerts', ['except' => $fieldErrors])

        <section class="pb-card pb-reg">
            <div class="pb-sec"><span class="pb-round">@proicon('user', 'oa-ico')</span><div><h2>Identité</h2><p>Renseignez vos informations personnelles.</p></div></div>
            <div class="pb-grid pb-grid--3">
                <div class="pb-field @error('last_name') is-invalid @enderror">
                    <label for="last_name">Nom&nbsp;<span class="oa-req">*</span></label>
                    <input class="pb-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name" placeholder="Ex. Diarra">
                    @error('last_name')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('first_name') is-invalid @enderror">
                    <label for="first_name">Prénoms&nbsp;<span class="oa-req">*</span></label>
                    <input class="pb-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" placeholder="Ex. Moussa Amadou">
                    @error('first_name')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('residence_country') is-invalid @enderror">
                    <label for="residence_country">Pays de résidence&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-select pb-select--flag" data-pb-flag>
                        <img src="{{ isset($flagCodes[$country]) ? $flagBase . '/' . $flagCodes[$country] . '.svg' : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' }}" alt="" data-flag-img @unless(isset($flagCodes[$country])) hidden @endunless>
                        <select id="residence_country" name="residence_country" required data-flag-base="{{ $flagBase }}">
                            @foreach($countries as $c)<option value="{{ $c }}" data-flag="{{ $flagCodes[$c] ?? '' }}" @selected($country === $c)>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    @error('residence_country')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="pb-sec pb-sec--divider"><span class="pb-round">@proicon('phone', 'oa-ico')</span><div><h2>Coordonnées</h2><p>Ces coordonnées vous permettront de vous connecter et de recevoir les notifications importantes.</p></div></div>
            <div class="pb-grid pb-grid--3">
                <div class="pb-field @error('phone') is-invalid @enderror">
                    <label for="phone">Téléphone&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-phone">
                        <span class="pb-phone__code" data-pb-flag>
                            <img src="{{ $flagBase . '/' . ($codeFlags[$code] ?? 'ci') . '.svg' }}" alt="" data-flag-img>
                            <select name="phone_country" aria-label="Indicatif" data-flag-base="{{ $flagBase }}">
                                @foreach($phoneCodes as $c => $label)<option value="{{ $c }}" data-flag="{{ $codeFlags[$c] }}" @selected($code === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </span>
                        <input type="tel" inputmode="tel" id="phone" name="phone" value="{{ $number }}" required maxlength="20" autocomplete="tel-national" placeholder="07 12 34 56 78">
                    </div>
                    @error('phone')<p class="pb-error">{{ $message }}</p>@enderror
                </div>
                <div class="pb-field @error('email') is-invalid @enderror">
                    <label for="email">Adresse e-mail&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-icon-input">@proicon('mail', 'oa-ico')<input class="pb-input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="exemple@domaine.com"></div>
                    @error('email')<p class="pb-error">{{ $message }}</p>@else<p class="pb-help">Cette adresse sera utilisée pour vous connecter.</p>@enderror
                </div>
                <div class="pb-field @error('preferred_language') is-invalid @enderror">
                    <label for="preferred_language">Langue préférée&nbsp;<span class="oa-req">*</span></label>
                    <div class="pb-select pb-select--icon">@proicon('globe', 'oa-ico')
                        <select id="preferred_language" name="preferred_language" required>
                            @foreach($languages as $c => $label)<option value="{{ $c }}" @selected(old('preferred_language', 'fr') === $c)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="pb-reg__security pb-sec--divider">
                <div>
                    <div class="pb-sec"><span class="pb-round">@proicon('lock', 'oa-ico')</span><div><h2>Sécurité</h2><p>Choisissez un mot de passe robuste pour sécuriser votre compte.</p></div></div>
                    <div class="pb-grid pb-grid--2">
                        <div class="pb-field @error('password') is-invalid @enderror">
                            <label for="password">Mot de passe&nbsp;<span class="oa-req">*</span></label>
                            @include('pro.auth.access.password', ['id' => 'password', 'autocomplete' => 'new-password', 'rules' => true, 'placeholder' => 'Au moins 8 caractères'])
                            <div class="oa-strength pb-strength" data-oa-strength><span></span><span></span><span></span><span></span><em data-oa-strength-label></em></div>
                            @error('password')<p class="pb-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="pb-field">
                            <label for="password_confirmation">Confirmer le mot de passe&nbsp;<span class="oa-req">*</span></label>
                            @include('pro.auth.access.password', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'placeholder' => 'Répétez votre mot de passe'])
                            <p class="pb-error" data-oa-match hidden>Les deux mots de passe ne correspondent pas.</p>
                        </div>
                    </div>
                </div>
                @include('pro.auth.access.password-rules', ['class' => 'pb-reg__rules'])
            </div>

            <label class="pb-terms @error('terms') is-invalid @enderror">
                <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                <span>J’accepte les <a href="{{ route('terms') }}" target="_blank" rel="noopener">Conditions générales d’utilisation</a> et la <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Politique de confidentialité</a>.&nbsp;<span class="oa-req">*</span></span>
            </label>
            @error('terms')<p class="pb-error">{{ $message }}</p>@enderror

            <div class="pb-info pb-reg__note">@proicon('lock', 'oa-ico')<span><b>Vos informations personnelles sont distinctes des informations légales de votre entreprise.</b><br>Vous pourrez renseigner les informations de votre entreprise à l’étape suivante.</span></div>

            <div class="pb-reg__actions">
                <a class="oa-btn oa-btn--outline" href="{{ route('pro.register.choice') }}">@proicon('chevron-left', 'oa-ico') Retour</a>
                <button class="oa-btn oa-btn--primary" type="submit">Continuer @proicon('chevron-right', 'oa-ico')</button>
            </div>
            <p class="pb-reg__foot">Votre compte est créé lorsque vous cliquez sur « Continuer ». Vous pourrez ensuite enregistrer et reprendre plus tard.</p>
        </section>
    </div>

    <aside class="pb-side pb-side--register">
        @include('pro.auth.access.hero', [
            'class' => 'op-hero pb-reg-hero',
            'title' => 'Rejoignez le réseau<br><em>B2B de référence</em><br>du secteur BTP<br>en Afrique de l’Ouest.',
            'text' => null,
            'benefits' => [
                ['users', 'Des opportunités d’affaires qualifiées'],
                ['chart', 'Un réseau de professionnels fiables'],
                ['shield', 'Une plateforme sécurisée et adaptée au marché local'],
            ],
        ])
        <div class="pb-card pb-reg-side">
            <div class="pb-reg-side__one">
                <span class="pb-round">@proicon('lock', 'oa-ico')</span>
                <div><strong>Un seul compte pour toutes vos activités OVANIE PRO.</strong><span>Acheter, vendre, trouver des fournisseurs, répondre à des RFQ, proposer des services ou développer votre activité.</span></div>
            </div>
            <div class="pb-reg-side__map">
                <img src="{{ asset('ovanie-pro/access/africa-map.png') }}" alt="Carte de l’Afrique de l’Ouest" width="264" height="295" loading="lazy">
                <ul>
                    @foreach(['Côte d’Ivoire', 'Sénégal', 'Mali', 'Burkina Faso', 'Guinée', 'Togo', 'Bénin', 'Niger'] as $c)<li>@proicon('check', 'oa-ico'){{ $c }}</li>@endforeach
                    <li class="pb-reg-side__more">et plus encore…</li>
                </ul>
            </div>
        </div>
    </aside>
</form>
@endsection
