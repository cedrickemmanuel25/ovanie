@if(collect($topAds)->isNotEmpty())
<section class="ovh-section ovh-section--tight" aria-label="Campagnes OVANIE">
    <div class="ovh-container">
        <div class="ovh-campaign-grid">
            @foreach(collect($topAds)->take(3) as $ad)
                @php
                    $adUrl = $ad->button_url ?: $ad->link ?: $catalogUrl;
                    $media = $ad->media_url ?? null;
                @endphp
                <a href="{{ $adUrl }}" class="ovh-campaign-card">
                    @if($media)
                        <img src="{{ $media }}" alt="{{ $ad->title ?: 'Campagne OVANIE' }}" loading="lazy">
                    @endif
                    <div class="ovh-campaign-card__overlay"></div>
                    <div class="ovh-campaign-card__copy">
                        @if($ad->subtitle)<span>{{ $ad->subtitle }}</span>@endif
                        <strong>{{ $ad->title ?: 'Découvrez nos offres' }}</strong>
                        @if($ad->text)<p>{{ $ad->text }}</p>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
