{{--
    Socle de compte commun (ACC-02), réutilisé par l'inscription et l'invitation (ACC-14).
    Variables : $countries, $languages, $phoneCodes, $lockedEmail (facultatif), $phoneCode, $phoneNumber (facultatifs).
--}}
<div class="pa-grid">
    <div class="pa-field @error('last_name') has-error @enderror">
        <label class="pa-label" for="last_name">Nom<span class="pa-req">*</span></label>
        <div class="pa-control">@proicon('user')<input class="pa-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name" placeholder="Kouassi"></div>
        @error('last_name')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field @error('first_name') has-error @enderror">
        <label class="pa-label" for="first_name">Prénoms<span class="pa-req">*</span></label>
        <div class="pa-control">@proicon('user')<input class="pa-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" placeholder="Yao Emmanuel"></div>
        @error('first_name')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field pa-full @error('email') has-error @enderror">
        <label class="pa-label" for="email">E-mail professionnel<span class="pa-req">*</span></label>
        @if(!empty($lockedEmail))
            <div class="pa-control">@proicon('mail')<input class="pa-input" id="email" type="email" value="{{ $lockedEmail }}" readonly></div>
            <p class="pa-help">Adresse fixée par l’invitation.</p>
        @else
            <div class="pa-control">@proicon('mail')<input class="pa-input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="nom@entreprise.com"></div>
        @endif
        @error('email')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field pa-full @error('phone') has-error @enderror">
        <label class="pa-label" for="phone">Téléphone<span class="pa-req">*</span></label>
        <div class="pa-phone">
            <select name="phone_country" aria-label="Indicatif">
                @foreach($phoneCodes as $code => $label)
                    <option value="{{ $code }}" @selected(old('phone_country', $phoneCode ?? '+225') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') ? \Illuminate\Support\Str::after(old('phone'), old('phone_country', '+225')) : ($phoneNumber ?? '') }}" required maxlength="20" placeholder="07 00 00 00 00" autocomplete="tel-national">
        </div>
        @error('phone')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field @error('residence_country') has-error @enderror">
        <label class="pa-label" for="residence_country">Pays de résidence<span class="pa-req">*</span></label>
        <div class="pa-control">@proicon('map-pin')
            <select class="pa-select" id="residence_country" name="residence_country" required>
                <option value="">Choisir…</option>
                @foreach($countries as $country)
                    <option value="{{ $country }}" @selected(old('residence_country', 'Côte d’Ivoire') === $country)>{{ $country }}</option>
                @endforeach
            </select>
        </div>
        @error('residence_country')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field @error('preferred_language') has-error @enderror">
        <label class="pa-label" for="preferred_language">Langue<span class="pa-req">*</span></label>
        <div class="pa-control">@proicon('globe')
            <select class="pa-select" id="preferred_language" name="preferred_language" required>
                @foreach($languages as $code => $label)
                    <option value="{{ $code }}" @selected(old('preferred_language', 'fr') === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @error('preferred_language')<p class="pa-error">{{ $message }}</p>@enderror
    </div>
    <div class="pa-field @error('password') has-error @enderror">
        <label class="pa-label" for="password">Mot de passe<span class="pa-req">*</span></label>
        @include('pro.auth.partials.password', ['id' => 'password', 'autocomplete' => 'new-password', 'meter' => true])
        @error('password')<p class="pa-error">{{ $message }}</p>@else<p class="pa-help">8 caractères minimum, lettres et chiffres.</p>@enderror
    </div>
    <div class="pa-field">
        <label class="pa-label" for="password_confirmation">Confirmation<span class="pa-req">*</span></label>
        @include('pro.auth.partials.password', ['id' => 'password_confirmation', 'autocomplete' => 'new-password'])
    </div>
</div>
<div class="pa-field @error('terms') has-error @enderror">
    <label class="pa-check">
        <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
        <span>J’accepte les <a href="{{ route('terms') }}" target="_blank" rel="noopener">conditions d’utilisation</a> et la <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">politique de confidentialité</a> d’OVANIE Pro.</span>
    </label>
    @error('terms')<p class="pa-error">{{ $message }}</p>@enderror
</div>
