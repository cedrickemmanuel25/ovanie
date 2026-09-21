<section class="ovh-section ovh-section--business" aria-labelledby="ovh-business-title">
    <div class="ovh-container">
        <div class="ovh-business-card">
            <div class="ovh-business-card__copy">
                <span class="ovh-business-card__brand">OVANIE <strong>Pro</strong></span>
                <h2 id="ovh-business-title">Vous préparez un chantier important ?</h2>
                <p>
                    Pour les achats en volume, demandes de prix, devis fournisseurs et appels d’offres,
                    accédez à l’espace conçu pour les besoins professionnels et les grands chantiers.
                </p>
                <div class="ovh-business-card__actions">
                    <a href="{{ $businessUrl }}" class="ovh-button ovh-button--white">
                        Accéder à OVANIE Pro
                        <i data-lucide="arrow-right"></i>
                    </a>
                    <a href="{{ $appelOffreUrl }}" class="ovh-button ovh-button--ghost-light">
                        Publier un appel d’offres
                    </a>
                </div>
            </div>

            <div class="ovh-business-card__features">
                @foreach([
                    ['boxes', 'Achats en volume'],
                    ['file-search', 'Demandes de prix'],
                    ['files', 'Devis fournisseurs'],
                    ['briefcase-business', 'Appels d’offres'],
                ] as [$icon, $label])
                    <div><span><i data-lucide="{{ $icon }}"></i></span><strong>{{ $label }}</strong></div>
                @endforeach
            </div>
        </div>
    </div>
</section>
