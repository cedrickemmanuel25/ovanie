{{-- Champ généré depuis config/ovanie_pro_forms.php. Variables : $name, $field, $value, $countries. --}}
@php
    $id = 'f_' . $name;
    $old = old($name, $value);
    $required = $field['required'];
    $width = ($field['width'] ?? 'half') === 'full' || in_array($field['type'], ['textarea', 'checkboxes'], true) ? 'pa-full' : '';
@endphp
<div class="pa-field {{ $width }} @error($name) has-error @enderror @error($name . '.*') has-error @enderror">
    @if(in_array($field['type'], ['radio', 'checkboxes'], true))
        <span class="pa-label">{{ $field['label'] }}@if($required)<span class="pa-req">*</span>@else <span class="pa-opt">(facultatif)</span>@endif</span>
    @else
        <label class="pa-label" for="{{ $id }}">{{ $field['label'] }}@if($required)<span class="pa-req">*</span>@else <span class="pa-opt">(facultatif)</span>@endif</label>
    @endif

    @switch($field['type'])
        @case('textarea')
            <textarea class="pa-textarea" id="{{ $id }}" name="{{ $name }}" maxlength="3000" @required($required) placeholder="{{ $field['placeholder'] ?? '' }}">{{ $old }}</textarea>
            @break
        @case('select')
            <select class="pa-select" id="{{ $id }}" name="{{ $name }}" @required($required)>
                <option value="">Choisir…</option>
                @foreach($field['options'] as $key => $label)
                    <option value="{{ $key }}" @selected((string) $old === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
            @break
        @case('country')
            <div class="pa-control">@proicon('map-pin')
                <select class="pa-select" id="{{ $id }}" name="{{ $name }}" @required($required)>
                    <option value="">Choisir…</option>
                    @foreach($countries as $country)
                        <option value="{{ $country }}" @selected($old === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </div>
            @break
        @case('radio')
            <div class="pa-pills">
                @foreach($field['options'] as $key => $label)
                    <label class="pa-pill"><input type="radio" name="{{ $name }}" value="{{ $key }}" @required($required) @checked((string) $old === (string) $key)><span>{{ $label }}</span></label>
                @endforeach
            </div>
            @break
        @case('checkboxes')
            <div class="pa-pills">
                @foreach($field['options'] as $key => $label)
                    <label class="pa-pill"><input type="checkbox" name="{{ $name }}[]" value="{{ $key }}" @checked(in_array($key, (array) $old, true))><span>{{ $label }}</span></label>
                @endforeach
            </div>
            @break
        @case('number')
        @case('year')
            <input class="pa-input" id="{{ $id }}" name="{{ $name }}" type="number" inputmode="numeric" value="{{ $old }}" @required($required)
                   min="{{ $field['type'] === 'year' ? 1800 : 0 }}" @if($field['type'] === 'year') max="{{ date('Y') }}" placeholder="Ex. {{ date('Y') - 5 }}" @endif>
            @break
        @default
            @php($typeIcon = ['email' => 'mail', 'tel' => 'phone', 'url' => 'globe'][$field['type']] ?? null)
            <div class="pa-control">
                @if($typeIcon) @proicon($typeIcon) @endif
                <input class="pa-input" id="{{ $id }}" name="{{ $name }}" type="{{ in_array($field['type'], ['email', 'tel', 'url'], true) ? $field['type'] : 'text' }}"
                       value="{{ $old }}" @required($required) maxlength="{{ $field['type'] === 'text' ? 180 : 255 }}" placeholder="{{ $field['placeholder'] ?? ($field['type'] === 'tel' ? '+225 07 00 00 00 00' : '') }}"
                       @if($field['type'] === 'tel') inputmode="tel" @endif>
            </div>
    @endswitch

    @error($name)<p class="pa-error">{{ $message }}</p>@enderror
    @error($name . '.*')<p class="pa-error">{{ $message }}</p>@enderror
    @if(!empty($field['help']))<p class="pa-help">{{ $field['help'] }}</p>@endif
</div>
