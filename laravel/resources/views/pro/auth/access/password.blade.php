{{-- Champ mot de passe : cadenas, bouton œil. Variables : $id, $name, $placeholder, $autocomplete, $rules (bool : relié à la checklist). --}}
<div class="oa-control oa-control--password">
    @proicon('lock', 'oa-ico oa-control__icon')
    <input class="oa-input" id="{{ $id }}" name="{{ $name ?? $id }}" type="password" required
           autocomplete="{{ $autocomplete ?? 'current-password' }}" placeholder="{{ $placeholder ?? '' }}"
           @if(!empty($rules)) data-oa-rules-input @endif @if(!empty($autofocus)) autofocus @endif>
    <button class="oa-eye" type="button" data-oa-toggle="{{ $id }}" aria-pressed="false" aria-label="Afficher le mot de passe">
        @proicon('eye', 'oa-ico oa-eye__on')@proicon('eye-off', 'oa-ico oa-eye__off')
    </button>
</div>
