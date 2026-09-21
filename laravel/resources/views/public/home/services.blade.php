<section class="ovh-section ovh-section--services" aria-labelledby="ovh-services-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Pourquoi OVANIE"
            title="Une marketplace pensée pour les réalités du chantier"
            description="Une expérience d’achat qui relie produits, logistique, paiement et accompagnement."
        />

        <div class="ovh-service-grid">
            @foreach([
                ['shield-check', 'Protection acheteur', 'Un parcours de commande structuré et des paiements sécurisés.'],
                ['map-pinned', 'Adresse chantier', 'La livraison est calculée selon le véritable point de destination.'],
                ['route', 'Logistique organisée', 'Le système prépare les groupes de livraison et les véhicules adaptés.'],
                ['headphones', 'Assistance OVANIE', 'Une équipe pour accompagner les clients dans leur parcours.'],
                ['calculator', 'Outils chantier', 'Des calculateurs et services pour mieux préparer les besoins.'],
                ['store', 'Écosystème vendeur', 'Des boutiques professionnelles connectées à la marketplace.'],
            ] as [$icon, $title, $text])
                <article class="ovh-service-card">
                    <span><i data-lucide="{{ $icon }}"></i></span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
