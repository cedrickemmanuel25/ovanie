<section class="ovh-section ovh-section--eco" aria-labelledby="ovh-eco-title">
    <div class="ovh-container">
        <div class="ovh-eco-layout">
            <div class="ovh-eco-copy">
                <span class="ovh-eyebrow ovh-eyebrow--eco"><i data-lucide="leaf"></i> Construire autrement</span>
                <h2 id="ovh-eco-title">Matériaux écologiques</h2>
                <p>
                    Découvrez des solutions pour une construction plus durable, plus performante et mieux adaptée aux nouveaux besoins du bâtiment.
                </p>
                <a href="{{ route('catalog.index', ['category' => 'materiaux-ecologiques']) }}" class="ovh-button ovh-button--eco">
                    Découvrir la sélection
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

            <div class="ovh-eco-highlights">
                @foreach([
                    ['recycle', 'Matériaux valorisés', 'Réduire l’impact grâce au réemploi et au recyclage.'],
                    ['thermometer-sun', 'Confort thermique', 'Des solutions pensées pour mieux maîtriser la chaleur.'],
                    ['activity', 'Performance durable', 'Construire avec une vision long terme.'],
                ] as [$icon, $title, $text])
                    <div class="ovh-eco-highlight">
                        <span><i data-lucide="{{ $icon }}"></i></span>
                        <div><strong>{{ $title }}</strong><p>{{ $text }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>

        @if(collect($ecoProducts)->isNotEmpty())
            <div class="ovh-eco-products">
                <x-home.product-rail :products="$ecoProducts" :favorite-product-ids="$favoriteProductIds" />
            </div>
        @endif
    </div>
</section>
