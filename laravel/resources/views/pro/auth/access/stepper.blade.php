{{-- Étapes du parcours professionnel. Variable : $current (1-4). Les étapes précédentes sont terminées. --}}
<ol class="oa-stepper pb-stepper" aria-label="Progression de l’inscription">
    @foreach(['Compte', 'Profil professionnel', 'Informations métier', 'Fonctions'] as $i => $label)
        @php
            $n = $i + 1;
        @endphp
        <li class="{{ $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '') }}" @if($n === $current) aria-current="step" @endif>
            <span class="oa-stepper__dot">@if($n < $current)@proicon('check', 'oa-ico')@else{{ $n }}@endif</span>
            <span class="oa-stepper__label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
