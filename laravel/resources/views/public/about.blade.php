@extends('layouts.guest')

@section('title', 'Qui sommes-nous ? | OVANIE')
@section('meta_description', 'Découvrez OVANIE, la marketplace ivoirienne dédiée aux matériaux et équipements de construction, pensée pour les clients, vendeurs, professionnels et chantiers.')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/company-pages.css') }}?v={{ file_exists(public_path('css/company-pages.css')) ? filemtime(public_path('css/company-pages.css')) : 1 }}">
@endsection

@section('content')
<div class="company-page company-page--about">
    <div class="company-shell company-about-main">
        <section class="company-top-grid">
            <article class="company-panel company-panel--about-intro">
                <span class="company-kicker">Notre raison d’être</span>
                <h1>Une expérience<br>d’achat BTP plus fluide</h1>
                <div class="company-orange-line"></div>
                <p>
                    OVANIE est la marketplace ivoirienne dédiée aux matériaux et équipements de construction.
                    Nous connectons acheteurs, vendeurs et professionnels autour d’une plateforme simple, fiable
                    et performante pour acheter, vendre et livrer partout en Côte d’Ivoire.
                </p>
                <p>
                    Notre mission : simplifier chaque étape du parcours BTP grâce à la technologie, la transparence
                    et un engagement fort pour la satisfaction client.
                </p>
                <div class="company-origin">
                    <span class="company-origin__flag">CI</span>
                    <strong>Fièrement conçu en Côte d’Ivoire</strong>
                </div>
            </article>

            <article class="company-panel company-panel--navy company-practice">
                <span class="company-kicker">OVANIE en pratique</span>
                <h2>Une plateforme, plusieurs besoins</h2>

                <div class="company-practice-grid">
                    <div class="company-practice-card">
                        <div class="company-practice-title">
                            <span class="company-mini-icon"><i data-lucide="users"></i></span>
                            <strong>Clients</strong>
                        </div>
                        <p>Trouvez facilement les bons produits au meilleur prix et faites-vous livrer sur vos chantiers.</p>
                    </div>

                    <div class="company-practice-card">
                        <div class="company-practice-title">
                            <span class="company-mini-icon"><i data-lucide="store"></i></span>
                            <strong>Vendeurs</strong>
                        </div>
                        <p>Développez votre activité, touchez plus de clients et gérez vos ventes efficacement.</p>
                    </div>

                    <div class="company-practice-card">
                        <div class="company-practice-title">
                            <span class="company-mini-icon"><i data-lucide="hard-hat"></i></span>
                            <strong>Chantiers</strong>
                        </div>
                        <p>Suivez vos besoins, comparez les offres et recevez vos matériaux où vous en avez besoin.</p>
                    </div>

                    <div class="company-practice-card">
                        <div class="company-practice-title">
                            <span class="company-mini-icon"><i data-lucide="briefcase-business"></i></span>
                            <strong>Professionnels</strong>
                        </div>
                        <p>Accédez à des outils, services et partenaires pour faire grandir vos projets en toute sérénité.</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="company-section company-section--ecosystem">
            <div class="company-section-heading">
                <span class="company-kicker">Ce que nous construisons</span>
                <h2>Un écosystème centré sur les besoins réels du bâtiment</h2>
                <p>
                    OVANIE réunit produits, services et partenaires pour accompagner tous les acteurs du BTP dans leurs projets,
                    de la simple rénovation aux grands chantiers.
                </p>
            </div>

            <div class="company-feature-grid company-feature-grid--4">
                <article class="company-feature-card">
                    <div class="company-round-icon"><i data-lucide="search"></i></div>
                    <h3>Catalogue BTP</h3>
                    <p>Un large choix de matériaux et équipements pour tous types de projets, sélectionnés auprès de vendeurs de confiance.</p>
                </article>
                <article class="company-feature-card">
                    <div class="company-round-icon"><i data-lucide="shield-check"></i></div>
                    <h3>Achat sécurisé</h3>
                    <p>Paiement en ligne 100% sécurisé, protection des données et transparence à chaque étape de votre commande.</p>
                </article>
                <article class="company-feature-card">
                    <div class="company-round-icon"><i data-lucide="truck"></i></div>
                    <h3>Livraison adaptée</h3>
                    <p>Livraison rapide et flexible partout en Côte d’Ivoire, avec suivi en temps réel jusqu’à destination.</p>
                </article>
                <article class="company-feature-card">
                    <div class="company-round-icon company-round-icon--business">B</div>
                    <h3>OVANIE Pro</h3>
                    <p>Des solutions dédiées aux entreprises et professionnels : devis, commandes récurrentes et comptes personnalisés.</p>
                </article>
            </div>
        </section>

        <section class="company-split-grid">
            <article class="company-audience company-audience--client">
                <span class="company-kicker">Pour les clients</span>
                <h2>Acheter avec plus de clarté</h2>
                <ul class="company-check-list">
                    <li>Choisissez en toute confiance parmi des milliers de produits.</li>
                    <li>Comparez facilement les prix et les vendeurs.</li>
                    <li>Profitez de conseils et d’informations fiables.</li>
                    <li>Suivez vos commandes en temps réel.</li>
                    <li>Bénéficiez d’un service client réactif et humain.</li>
                    <li>Payez en ligne en toute sécurité.</li>
                </ul>
                <i class="company-watermark" data-lucide="shopping-cart"></i>
            </article>

            <article class="company-audience company-audience--vendor">
                <span class="company-kicker">Pour les vendeurs & professionnels</span>
                <h2>Développer son activité</h2>
                <ul class="company-check-list">
                    <li>Augmentez votre visibilité auprès de milliers d’acheteurs.</li>
                    <li>Gérez vos produits, stocks et commandes simplement.</li>
                    <li>Profitez d’outils performants pour piloter votre activité.</li>
                    <li>Recevez vos paiements en toute sécurité.</li>
                    <li>Bénéficiez d’un accompagnement dédié.</li>
                    <li>Développez votre réseau et vos opportunités.</li>
                </ul>
                <i class="company-watermark" data-lucide="store"></i>
            </article>
        </section>

        <section class="company-section company-section--principles">
            <div class="company-section-heading">
                <span class="company-kicker">Nos principes</span>
                <h2>La confiance se construit dans chaque étape du parcours</h2>
            </div>

            <div class="company-principles-grid">
                <article class="company-principle-card">
                    <div class="company-step-number">1</div>
                    <div>
                        <h3>Clarté</h3>
                        <p>Des informations claires, des prix transparents et des conditions compréhensibles pour des décisions en toute confiance.</p>
                    </div>
                    <i class="company-principle-watermark" data-lucide="shield"></i>
                </article>
                <article class="company-principle-card">
                    <div class="company-step-number">2</div>
                    <div>
                        <h3>Fiabilité</h3>
                        <p>Des partenaires vérifiés, des produits de qualité et un engagement fort pour la satisfaction de nos clients.</p>
                    </div>
                    <i class="company-principle-watermark" data-lucide="badge-check"></i>
                </article>
                <article class="company-principle-card">
                    <div class="company-step-number">3</div>
                    <div>
                        <h3>Proximité</h3>
                        <p>Une équipe locale à l’écoute, des solutions adaptées au marché ivoirien et un accompagnement de proximité.</p>
                    </div>
                    <i class="company-principle-watermark" data-lucide="users"></i>
                </article>
            </div>
        </section>

        <section class="company-bottom-cta company-bottom-cta--about">
            <div class="company-cta-decoration company-cta-decoration--crane">
                <i data-lucide="construction"></i>
            </div>
            <div class="company-bottom-cta__content">
                <h2>Vous souhaitez travailler avec OVANIE ?</h2>
                <p>Rejoignez notre écosystème et construisons ensemble l’avenir du BTP en Côte d’Ivoire.</p>
                <div class="company-actions company-actions--center">
                    <a href="{{ route('open-shop') }}" class="company-btn company-btn--orange">
                        Vendre sur OVANIE <i data-lucide="arrow-right"></i>
                    </a>
                    <a href="{{ route('contact.index') }}" class="company-btn company-btn--ghost">
                        Contacter OVANIE <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
