{{--
    Ville : menu déroulant des villes du pays choisi, avec « Autre ville… » pour une saisie libre.
    La valeur envoyée est dans le champ caché $name. Variables : $name, $label, $values, $countryField (nom du champ pays).
--}}
@php
    $cityValue = old($name, $values[$name] ?? '');
@endphp
<div class="pb-field @error($name) is-invalid @enderror" data-pb-city data-country-field="{{ $countryField }}">
    <label for="f-{{ $name }}">{{ $label }}&nbsp;<span class="oa-req">*</span></label>
    <input type="hidden" name="{{ $name }}" value="{{ $cityValue }}" data-required="1" data-pb-city-value>
    <div class="pb-select" data-pb-city-wrap><select id="f-{{ $name }}" data-pb-city-select><option value="">Sélectionnez une ville</option></select></div>
    <input class="pb-input fm-city-other" type="text" maxlength="120" placeholder="Saisissez le nom de la ville" aria-label="{{ $label }} (saisie libre)" data-pb-city-other hidden>
    @error($name)<p class="pb-error">{{ $message }}</p>@enderror
</div>
