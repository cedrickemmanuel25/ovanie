@php
    $categoryIcons = [
        'ciment-beton' => 'blocks',
        'fer-metaux' => 'construction',
        'carrelage' => 'grid-3x3',
        'peinture' => 'paint-roller',
        'isolation' => 'layers-3',
        'electricite' => 'zap',
        'plomberie' => 'pipette',
        'bois' => 'trees',
        'outillage' => 'wrench',
        'solaire' => 'sun',
        'quincaillerie' => 'hammer',
        'materiaux-ecologiques' => 'leaf',
    ];
@endphp

<section class="ovh-section ovh-section--surface" aria-labelledby="ovh-categories-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Explorer"
            title="Achetez par catégorie"
            description="Accédez rapidement aux principaux univers du bâtiment, de la finition et de l’énergie."
            :url="$catalogUrl"
            link-label="Toutes les catégories"
        />

        <div class="ovh-category-grid">
            @forelse(collect($categories)->take(12) as $category)
                @php($icon = $categoryIcons[$category->slug] ?? 'package-search')
                <a href="{{ route('catalog.index', ['category' => $category->slug]) }}" class="ovh-category-card {{ $category->slug === 'materiaux-ecologiques' ? 'ovh-category-card--eco' : '' }}">
                    <span class="ovh-category-card__icon"><i data-lucide="{{ $icon }}"></i></span>
                    <strong>{{ $category->name }}</strong>
                    <span>Découvrir <i data-lucide="arrow-right"></i></span>
                </a>
            @empty
                <a href="{{ $catalogUrl }}" class="ovh-category-card">
                    <span class="ovh-category-card__icon"><i data-lucide="layout-grid"></i></span>
                    <strong>Voir le catalogue</strong>
                    <span>Découvrir <i data-lucide="arrow-right"></i></span>
                </a>
            @endforelse
        </div>
    </div>
</section>
