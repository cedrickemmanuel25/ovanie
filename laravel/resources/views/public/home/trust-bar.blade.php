<section class="ovh-trust-bar" aria-label="Garanties OVANIE">
    <div class="ovh-container ovh-trust-bar__grid">
        @foreach([
            ['truck', 'Livraison chantier', 'Des solutions adaptées au poids et au volume'],
            ['shield-check', 'Paiement sécurisé', 'Un parcours protégé pour chaque commande'],
            ['badge-check', 'Produits contrôlés', 'Des informations produit structurées'],
            ['headphones', 'Assistance client', 'Une équipe disponible pour vous accompagner'],
        ] as [$icon, $title, $text])
            <div class="ovh-trust-item">
                <span class="ovh-trust-item__icon"><i data-lucide="{{ $icon }}"></i></span>
                <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
            </div>
        @endforeach
    </div>
</section>
