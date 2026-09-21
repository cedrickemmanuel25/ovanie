@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'url' => null,
    'linkLabel' => 'Voir tout',
    'theme' => 'light',
])

<div class="ovh-section-heading ovh-section-heading--{{ $theme }}">
    <div class="ovh-section-heading__copy">
        @if($eyebrow)
            <span class="ovh-eyebrow">{{ $eyebrow }}</span>
        @endif
        <h2>{{ $title }}</h2>
        @if($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @if($url)
        <a href="{{ $url }}" class="ovh-section-link">
            <span>{{ $linkLabel }}</span>
            <i data-lucide="arrow-right"></i>
        </a>
    @endif
</div>
