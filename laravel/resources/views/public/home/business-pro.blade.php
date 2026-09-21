        <section class="ov-business" aria-label="OVANIE Pro">
            <img class="ov-business__background"
                 src="{{ asset('images/home/ovanie-business.png') }}"
                 alt=""
                 aria-hidden="true"
                 loading="lazy"
                 decoding="async">
            <div class="ov-business__overlay" aria-hidden="true"></div>

            <div class="ov-business__copy">
                <span class="ov-eyebrow">Pour les professionnels du BTP</span>
                <h2>OVANIE Pro</h2>
                <p>L’espace dédié aux professionnels du BTP : demandes de devis, achats en gros, suivi de commandes et conditions avantageuses pour vos chantiers.</p>

                <ul>
                    <li><span>✓</span> Devis sur-mesure</li>
                    <li><span>✓</span> Tarifs préférentiels</li>
                    <li><span>✓</span> Facturation & suivi dédiés</li>
                </ul>

                <a href="{{ $businessUrl }}" class="ov-btn ov-btn--white">Découvrir OVANIE Pro <span>→</span></a>
            </div>

            <div class="ov-business__cards">
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"></path><path d="M15 3v4h4M9 11h6M9 15h6"></path></svg></span>
                    <div><strong>Demande de devis</strong><small>Rapide et personnalisée</small></div>
                </a>
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="7" r="3"></circle><circle cx="17" cy="7" r="3"></circle><circle cx="12" cy="17" r="3"></circle><path d="M9.5 9 11 14M14.5 9 13 14"></path></svg></span>
                    <div><strong>Achats en gros</strong><small>Tarifs adaptés aux volumes</small></div>
                </a>
                <a href="{{ $businessUrl }}" class="ov-business-card">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v17H7zM9 2h6v4H9z"></path><path d="M10 11h4M10 15h4"></path></svg></span>
                    <div><strong>Gestion de chantier</strong><small>Commandes & livraisons</small></div>
                </a>
            </div>
        </section>
