{{--
    Téléphone avec indicatif et drapeau. La valeur complète (+225…) est enregistrée dans le champ caché $name.
    Variables : $name, $value, $phoneCodes, $id, $prefixIcon (facultatif).
--}}
@php
    $full = (string) old($name, $value);
    $code = '+225';
    foreach (collect(array_keys($phoneCodes))->sortByDesc(fn ($c) => strlen($c)) as $c) {
        if ($full !== '' && str_starts_with($full, $c)) { $code = $c; break; }
    }
    $number = $full !== '' && str_starts_with($full, $code) ? substr($full, strlen($code)) : ltrim($full, '+');
    $flags = collect($phoneCodes)->mapWithKeys(fn ($label, $c) => [$c => strtolower(strtok($label, ' '))]);
@endphp
<div class="pb-phone {{ $errors->has($name) ? 'is-invalid' : '' }}" data-pb-phone>
    <input type="hidden" name="{{ $name }}" value="{{ $full }}" data-pb-phone-value>
    @if(!empty($prefixIcon))<span class="pb-phone__prefix">@proicon($prefixIcon, 'oa-ico')</span>@endif
    <span class="pb-phone__code" data-pb-flag>
        <img src="{{ asset('ovanie-pro/flags/' . ($flags[$code] ?? 'ci') . '.svg') }}" alt="" data-flag-img>
        <select aria-label="Indicatif" data-flag-base="{{ asset('ovanie-pro/flags') }}" data-pb-phone-code>
            @foreach($phoneCodes as $c => $label)<option value="{{ $c }}" data-flag="{{ $flags[$c] }}" @selected($code === $c)>{{ $c }}</option>@endforeach
        </select>
    </span>
    <input type="tel" inputmode="tel" id="{{ $id }}" @if(!empty($required)) data-required="1" @endif value="{{ trim($number) }}" maxlength="20" placeholder="07 12 34 56 78" data-pb-phone-number>
</div>
