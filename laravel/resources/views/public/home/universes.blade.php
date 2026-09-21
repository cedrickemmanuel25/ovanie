<section class="ovh-section ovh-section--surface" aria-labelledby="ovh-universes-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Univers métier"
            title="Trouvez les solutions selon votre projet"
            description="Entrez par votre besoin : construire, rénover, équiper ou gagner en autonomie énergétique."
        />

        <div class="ovh-universe-grid">
            @foreach([
                ['title' => 'Construire', 'text' => 'Ciment, béton, fer, agrégats et solutions de gros œuvre.', 'image' => 'gros œuvre & maçonnerie.png', 'slug' => 'ciment-beton', 'icon' => 'building-2'],
                ['title' => 'Rénover', 'text' => 'Carrelage, peinture, revêtements et matériaux de finition.', 'image' => 'finition & déco.png', 'slug' => 'carrelage', 'icon' => 'paint-roller'],
                ['title' => 'Équiper le chantier', 'text' => 'Outillage, machines et équipements professionnels.', 'image' => 'equipement de chantier.png', 'slug' => 'outillage', 'icon' => 'drill'],
                ['title' => 'Énergie & autonomie', 'text' => 'Solutions solaires, électricité et équipements autonomes.', 'image' => 'énergie & autonomie.png', 'slug' => 'solaire', 'icon' => 'sun-medium'],
            ] as $universe)
                <a href="{{ route('catalog.index', ['category' => $universe['slug']]) }}" class="ovh-universe-card">
                    <div class="ovh-universe-card__image">
                        <img src="{{ asset('storage/logos/' . rawurlencode($universe['image'])) }}" alt="{{ $universe['title'] }}" loading="lazy">
                    </div>
                    <div class="ovh-universe-card__overlay"></div>
                    <div class="ovh-universe-card__content">
                        <span><i data-lucide="{{ $universe['icon'] }}"></i></span>
                        <h3>{{ $universe['title'] }}</h3>
                        <p>{{ $universe['text'] }}</p>
                        <strong>Explorer <i data-lucide="arrow-right"></i></strong>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
