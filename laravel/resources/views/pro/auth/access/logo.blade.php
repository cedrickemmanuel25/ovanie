{{-- Logo OVANIE PRO. Variables : $size (sm|md|lg), $light (bool : version texte blanche pour fond sombre). --}}
@if(!empty($light))
    <span class="oa-logo oa-logo--{{ $size ?? 'md' }} oa-logo--light" aria-label="OVANIE PRO" role="img">
        <span class="oa-logo__word" aria-hidden="true">OV<span class="oa-logo__a">Λ</span>NIE</span>
        <span class="oa-logo__pro" aria-hidden="true">PRO</span>
    </span>
@else
    <img class="oa-logo-img oa-logo-img--{{ $size ?? 'md' }}" src="{{ asset('ovanie-pro/logo-web.png') }}" alt="OVANIE PRO" width="632" height="220">
@endif
