{{-- Champ mot de passe avec icône cadenas et bouton œil. Variables : $id, $name, $label, $autocomplete, $meter (bool), $placeholder. --}}
<div class="pa-control pa-pass">
    @proicon('lock')
    <input class="pa-input" id="{{ $id }}" name="{{ $name ?? $id }}" type="password" required
           @if(($autocomplete ?? '') === 'new-password') minlength="8" @endif
           autocomplete="{{ $autocomplete ?? 'current-password' }}" placeholder="{{ $placeholder ?? '' }}" @if(!empty($autofocus)) autofocus @endif>
    <button type="button" class="pa-eye" data-toggle-password="{{ $id }}" aria-pressed="false" aria-label="Afficher le mot de passe">
        @proicon('eye', 'pa-ico pa-ico--on')@proicon('eye-off', 'pa-ico pa-ico--off')
    </button>
</div>
@if(!empty($meter))
    <div class="pa-meter" data-meter-for="{{ $id }}" data-level="0"><span></span><span></span><span></span><span></span></div>
@endif
