{{--
    Champ du formulaire « Informations métier ».
    Variables : $name, $label, $values ; facultatif : $type (text|email|url|number|date|select|year|country|textarea),
    $required, $optional, $placeholder, $options, $icon, $class, $default, $help, $empty (libellé de l'option vide).
--}}
@php
    $type = $type ?? 'text';
    $val = old($name, $values[$name] ?? ($default ?? ''));
    $id = 'f-' . $name;
    $req = ! empty($required);
    $flags = (array) config('ovanie_pro.flags', []);
@endphp
<div class="pb-field {{ $class ?? '' }} @error($name) is-invalid @enderror">
    <label for="{{ $id }}">{{ $label }}@if($req)&nbsp;<span class="oa-req">*</span>@endif @if(!empty($optional))<small>(facultatif)</small>@endif</label>
    @if($type === 'country')
        <div class="pb-select pb-select--flag" data-pb-flag>
            <img src="{{ isset($flags[$val]) ? asset('ovanie-pro/flags/' . $flags[$val] . '.svg') : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' }}" alt="" data-flag-img @unless(isset($flags[$val])) hidden @endunless>
            <select id="{{ $id }}" name="{{ $name }}" data-flag-base="{{ asset('ovanie-pro/flags') }}" @if($req) data-required="1" @endif>
                <option value="">Sélectionnez un pays</option>
                @foreach($options as $c)<option value="{{ $c }}" data-flag="{{ $flags[$c] ?? '' }}" @selected($val === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
    @elseif($type === 'select' || $type === 'year')
        <div class="pb-select {{ !empty($icon) ? 'pb-select--icon' : '' }}">
            @if(!empty($icon))@proicon($icon, 'oa-ico')@endif
            <select id="{{ $id }}" name="{{ $name }}" @if($req) data-required="1" @endif>
                <option value="">{{ $empty ?? ($type === 'year' ? 'Sélectionnez l’année' : 'Sélectionnez') }}</option>
                @if($type === 'year')
                    @for($y = (int) date('Y'); $y >= 1950; $y--)<option value="{{ $y }}" @selected((string) $val === (string) $y)>{{ $y }}</option>@endfor
                @else
                    @foreach($options as $k => $l)
                        @php $optionValue = is_int($k) ? $l : $k; @endphp
                        <option value="{{ $optionValue }}" @selected((string) $val === (string) $optionValue)>{{ $l }}</option>
                    @endforeach
                    @if($val !== '' && $val !== null && ! collect($options)->contains(fn ($l, $k) => (string) (is_int($k) ? $l : $k) === (string) $val))
                        <option value="{{ $val }}" selected>{{ $val }}</option>
                    @endif
                @endif
            </select>
        </div>
    @elseif($type === 'textarea')
        <textarea class="pb-input pb-textarea" id="{{ $id }}" name="{{ $name }}" rows="2" placeholder="{{ $placeholder ?? '' }}" @if($req) data-required="1" @endif>{{ $val }}</textarea>
    @else
        <div class="{{ !empty($icon) ? 'pb-icon-input' : '' }}">
            @if(!empty($icon))@proicon($icon, 'oa-ico')@endif
            <input class="pb-input" id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'number' ? 'text' : $type }}" @if($type === 'number') inputmode="decimal" @endif value="{{ $val }}" placeholder="{{ $placeholder ?? '' }}" @if($req) data-required="1" @endif>
        </div>
    @endif
    @if(!empty($help))<p class="pb-help">{{ $help }}</p>@endif
    @error($name)<p class="pb-error">{{ $message }}</p>@enderror
</div>
