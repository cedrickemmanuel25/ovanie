{{--
    Panneau bleu nuit avec photo chantier. Variables :
    $title (HTML autorisé : <em> = orange), $text, $benefits [[icon, title, text?]], $class, $footer (vue facultative).
--}}
<aside class="oa-hero {{ $class ?? '' }}">
    <div class="oa-hero__photo" aria-hidden="true"></div>
    <div class="oa-hero__content">
        <span class="oa-hero__dash" aria-hidden="true"></span>
        <h2 class="oa-hero__title">{!! $title !!}</h2>
        @if(!empty($text))<p class="oa-hero__text">{{ $text }}</p>@endif
        <ul class="oa-hero__benefits">
            @foreach($benefits as $benefit)
                <li>
                    <span class="oa-hero__icon">@proicon($benefit[0], 'oa-ico')</span>
                    <span><strong>{{ $benefit[1] }}</strong>@if(!empty($benefit[2]))<small>{{ $benefit[2] }}</small>@endif</span>
                </li>
            @endforeach
        </ul>
        @isset($footer)
            <div class="oa-hero__footer">@include($footer)</div>
        @endisset
    </div>
</aside>
