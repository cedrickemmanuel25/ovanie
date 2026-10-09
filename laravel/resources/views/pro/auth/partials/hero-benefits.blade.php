@php
    $variant = $variant ?? 'login';
    $items = $items ?? [];
@endphp
<section class="opx-hero opx-hero--{{ $variant }}" aria-label="OVANIE PRO">
    <img class="opx-hero__photo" src="{{ asset($image ?? 'images/ovanie-pro/auth/professional-worker.png') }}" alt="Professionnel du BTP sur un chantier">
    <div class="opx-hero__veil"></div>
    <div class="opx-hero__content">
        <span class="opx-hero__dash"></span>
        <h2>{!! $title !!}</h2>
        @if(!empty($text))<p class="opx-hero__lead">{{ $text }}</p>@endif
        @if($items)
            <div class="opx-benefits">
                @foreach($items as $item)
                    <div class="opx-benefit">
                        <span class="opx-benefit__icon">@proicon($item['icon'], 'opx-icon')</span>
                        <div>
                            @if(!empty($item['title']))<strong>{{ $item['title'] }}</strong>@endif
                            <span>{{ $item['text'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @if(!empty($bottomPartial))
            @include($bottomPartial)
        @endif
    </div>
</section>
