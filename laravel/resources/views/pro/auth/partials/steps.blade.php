{{-- Progression du parcours PRO : 1 Compte, 2 Profil professionnel, 3 Informations métier, 4 Fonctions. --}}
<ol class="pa-steps" aria-label="Progression">
    @foreach(['Compte', 'Profil professionnel', 'Informations métier', 'Fonctions'] as $i => $label)
        <li class="{{ $i + 1 < $current ? 'is-done' : ($i + 1 === $current ? 'is-current' : '') }}" @if($i + 1 === $current) aria-current="step" @endif>
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
