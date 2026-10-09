{{--
    Zone de dépôt d'un justificatif. Variables : $type (clé du document), $doc (définition du schéma),
    $existing (collection des fichiers déjà fournis), $title, $hint, $help, $badge (bool : afficher le statut).
--}}
@php
    $multiple = ! empty($doc['multiple']);
    $media = ! empty($doc['media']);
    $key = 'documents.' . $type;
    $hasError = $errors->has($key) || $errors->has($key . '.*');
    $provided = $existing->isNotEmpty();
    $accept = $media ? '.mp4,.mov' : ($type === 'factory_photos' ? '.jpg,.jpeg,.png,.webp' : '.pdf,.jpg,.jpeg,.png,.webp');
@endphp
<div class="pb-drop {{ $hasError ? 'is-invalid' : '' }} {{ $provided ? 'is-provided' : '' }}" data-pb-drop data-required="{{ $doc['required'] && ! $provided ? '1' : '0' }}" data-label="{{ $title }}">
    @if($title !== null)
        <div class="pb-drop__head">
            <span><b>{{ $title }}@if($doc['required'])&nbsp;<span class="oa-req">*</span>@endif</b>@if(!empty($hint))<small>{{ $hint }}</small>@endif</span>
            @if($badge ?? true)
                @if($provided)
                    <span class="pb-badge pb-badge--ok">@proicon('check', 'oa-ico') Fourni</span>
                @elseif($doc['required'])
                    <span class="pb-badge pb-badge--todo">@proicon('clock', 'oa-ico') À fournir</span>
                @else
                    <span class="pb-badge">@proicon('clock', 'oa-ico') Facultatif</span>
                @endif
            @endif
        </div>
    @endif
    <label class="pb-drop__zone">
        <input type="file" name="{{ $multiple ? "documents[$type][]" : "documents[$type]" }}" accept="{{ $accept }}" @if($multiple) multiple @endif data-pb-file>
        <span class="pb-drop__icon">@proicon('upload', 'oa-ico')</span>
        <span class="pb-drop__text" data-pb-file-label>
            @if($provided)
                {{ $existing->pluck('original_name')->filter()->take(2)->join(', ') }}{{ $existing->count() > 2 ? ' +' . ($existing->count() - 2) : '' }}
            @else
                Glissez-déposez {{ $multiple ? 'vos fichiers' : 'votre fichier' }} ici<br>ou cliquez pour sélectionner
            @endif
        </span>
    </label>
    <p class="pb-drop__help">{{ $help ?? ($media ? 'MP4, MOV. Max 20 Mo par fichier.' : 'PDF, JPG, PNG. Max 10 Mo.') }}</p>
    @error($key)<p class="pb-error">{{ $message }}</p>@enderror
    @error($key . '.*')<p class="pb-error">{{ $message }}</p>@enderror
</div>
