{{-- Badge « Compte simple » + étapes. Variables : $step (1-3), $kind (offer|need). --}}
<div class="op-topline">
    <span class="op-badge">@proicon('user', 'oa-ico') Compte simple OVANIE PRO</span>
    <ol class="op-steps" aria-label="Étapes de publication">
        @foreach(['Compte', $kind === 'offer' ? 'Votre offre' : 'Votre besoin', 'Vérification & publication'] as $i => $label)
            <li class="{{ $i + 1 < $step ? 'is-done' : ($i + 1 === $step ? 'is-current' : '') }}" @if($i + 1 === $step) aria-current="step" @endif>
                <span class="op-steps__dot">@if($i + 1 < $step) @proicon('check', 'oa-ico') @else {{ $i + 1 }} @endif</span>
                <span class="op-steps__label">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</div>
