<section class="ovh-hero" aria-labelledby="ovh-hero-title">
    <div class="ovh-container">
        <div class="ovh-hero__panel">
            <div class="ovh-hero__content">
                <span class="ovh-hero__eyebrow">
                    <i data-lucide="hard-hat"></i>
                    La marketplace du bâtiment en Côte d’Ivoire
                </span>

                <h1 id="ovh-hero-title">Votre chantier commence ici avec OVANIE</h1>
                <p>
                    Matériaux, équipements et solutions pour construire, rénover et équiper vos projets,
                    avec un parcours d’achat pensé pour les réalités du chantier.
                </p>

                <div class="ovh-hero__actions">
                    <a href="{{ $catalogUrl }}" class="ovh-button ovh-button--primary ovh-button--large">
                        Acheter maintenant
                        <i data-lucide="arrow-right"></i>
                    </a>
                    <a href="{{ $devisUrl }}" class="ovh-button ovh-button--ghost-light ovh-button--large">
                        <i data-lucide="file-text"></i>
                        Demander un devis
                    </a>
                </div>

                <div class="ovh-hero__proof">
                    <span><i data-lucide="shield-check"></i> Paiement sécurisé</span>
                    <span><i data-lucide="truck"></i> Livraison chantier</span>
                    <span><i data-lucide="headphones"></i> Assistance OVANIE</span>
                </div>
            </div>

            <div class="ovh-hero__visual" aria-hidden="true">
                <div class="ovh-hero__glow"></div>
                <img src="{{ asset('storage/logos/' . rawurlencode('hero img.png')) }}" alt="" fetchpriority="high">
                <div class="ovh-hero__floating-card ovh-hero__floating-card--top">
                    <i data-lucide="badge-check"></i>
                    <div><strong>Produits sélectionnés</strong><span>Pour vos besoins BTP</span></div>
                </div>
                <div class="ovh-hero__floating-card ovh-hero__floating-card--bottom">
                    <i data-lucide="map-pinned"></i>
                    <div><strong>Livraison optimisée</strong><span>Selon l’adresse chantier</span></div>
                </div>
            </div>
        </div>
    </div>
</section>
