@php
    $seasonTitle = data_get($seasonSale, 'title', 'Des offres pour avancer sur vos travaux');
    $seasonSubtitle = data_get($seasonSale, 'subtitle', 'Profitez de sélections et promotions sur les matériaux et équipements du moment.');
    $seasonButton = data_get($seasonSale, 'button_text', 'Voir les offres');
    $seasonUrl = data_get($seasonSale, 'button_url') ?: $flashUrl;
    $seasonMedia = data_get($seasonSale, 'media_url') ?: data_get($seasonSale, 'mediaUrl');
@endphp

<section class="ovh-section ovh-section--tight">
    <div class="ovh-container">
        <div class="ovh-season-banner">
            <div class="ovh-season-banner__copy">
                <span class="ovh-eyebrow ovh-eyebrow--light">Offres du moment</span>
                <h2>{{ $seasonTitle }}</h2>
                <p>{{ $seasonSubtitle }}</p>
                <a href="{{ $seasonUrl }}" class="ovh-button ovh-button--white">
                    {{ $seasonButton }}
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
            <div class="ovh-season-banner__art" aria-hidden="true">
                @if($seasonMedia)
                    <img src="{{ $seasonMedia }}" alt="" loading="lazy">
                @else
                    <i data-lucide="badge-percent"></i>
                @endif
            </div>
        </div>
    </div>
</section>
