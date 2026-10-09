{{--
    Sélection multiple sous forme d'étiquettes. La valeur est enregistrée dans un champ caché
    (séparateur « , »). Variables : $name, $value, $placeholder, $suggestions (liste), $required, $icon.
--}}
@php
    $items = collect(preg_split('/\s*[,\n]\s*/', (string) old($name, $value)))->filter()->values();
    $listId = 'pb-list-' . $name;
@endphp
<div class="pb-tags {{ $errors->has($name) ? 'is-invalid' : '' }}" data-pb-tags data-required="{{ ! empty($required) ? '1' : '0' }}">
    <input type="hidden" name="{{ $name }}" value="{{ $items->implode(', ') }}" data-pb-tags-value>
    <div class="pb-tags__search" @if(!empty($bare)) hidden @endif>
        @proicon($icon ?? 'search', 'oa-ico')
        <input type="text" list="{{ $listId }}" placeholder="{{ $placeholder }}" data-pb-tags-input autocomplete="off" aria-label="{{ $placeholder }}">
        @proicon('chevron-down', 'oa-ico pb-tags__chev')
    </div>
    <datalist id="{{ $listId }}">@foreach($suggestions ?? [] as $s)<option value="{{ $s }}">@endforeach</datalist>
    <div class="pb-tags__list" data-pb-tags-list>
        @foreach($items as $item)
            <span class="pb-chip">{{ $item }}<button type="button" aria-label="Retirer {{ $item }}">×</button></span>
        @endforeach
    </div>
    @error($name)<p class="pb-error">{{ $message }}</p>@enderror
</div>
