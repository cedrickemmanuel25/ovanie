@props(['banners' => collect(), 'label' => 'Promotion OVANIE'])

@if(collect($banners)->isNotEmpty())
<section class="ovh-section ovh-section--tight" aria-label="{{ $label }}">
    <div class="ovh-container">
        <div class="ovh-banner-strip">
            @foreach(collect($banners)->take(2) as $banner)
                @php
                    $bannerImage = $banner->image ?? null;
                    if ($bannerImage && !\Illuminate\Support\Str::startsWith($bannerImage, ['http://', 'https://'])) {
                        $bannerImage = asset(\Illuminate\Support\Str::startsWith($bannerImage, 'storage/') ? $bannerImage : 'storage/' . $bannerImage);
                    }
                @endphp
                <a href="{{ $banner->link ?: $catalogUrl }}" class="ovh-banner-strip__item">
                    @if($bannerImage)
                        <img src="{{ $bannerImage }}" alt="{{ $label }}" loading="lazy">
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
