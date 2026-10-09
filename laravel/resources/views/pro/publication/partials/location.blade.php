{{-- Pays (drapeau), ville / zone, précision facultative. Variables : $country, $countryFlags, $flagBase, $countries, $v, $detailLabel, $detailPlaceholder. --}}
<div class="op-grid op-grid--3">
    <div class="op-field @error('country') is-invalid @enderror">
        <label for="country">Pays <em>*</em></label>
        <div class="op-select op-select--flag" data-op-flag-select>
            <img src="{{ $flagBase . '/' . ($countryFlags[$country] ?? 'ci') . '.svg' }}" alt="" data-flag-img>
            <select id="country" name="country" data-flag-base="{{ $flagBase }}" data-preview="country">
                @foreach($countries as $c)<option value="{{ $c }}" data-flag="{{ $countryFlags[$c] ?? '' }}" @selected($country === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        @error('country')<p class="op-error">{{ $message }}</p>@enderror
    </div>
    <div class="op-field @error('city') is-invalid @enderror">
        <label for="city">Ville / zone <em>*</em></label>
        <input class="op-input" id="city" name="city" value="{{ $v('city') }}" maxlength="120" placeholder="Ex. Abidjan" data-preview="city">
        @error('city')<p class="op-error">{{ $message }}</p>@enderror
    </div>
    <div class="op-field">
        <label for="location_detail">{{ $detailLabel }} <small>(facultatif)</small></label>
        <input class="op-input" id="location_detail" name="location_detail" value="{{ $v('location_detail') }}" maxlength="180" placeholder="{{ $detailPlaceholder }}" data-preview="detail">
    </div>
</div>
<p class="op-note-line">@proicon('info', 'oa-ico op-blue') Votre adresse personnelle exacte ne sera pas affichée publiquement.</p>
