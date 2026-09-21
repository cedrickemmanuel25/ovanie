@extends('layouts.guest')

@section('title', 'Carrières | OVANIE')
@section('meta_description', 'Rejoignez OVANIE et contribuez au développement d’une plateforme BTP utile, fiable et performante au service de toute la Côte d’Ivoire.')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/company-pages.css') }}?v={{ file_exists(public_path('css/company-pages.css')) ? filemtime(public_path('css/company-pages.css')) : 1 }}">
@endsection

@section('content')
<div class="company-page company-page--careers">
    <section class="career-hero">
        <div class="company-shell career-hero__inner">
            <div class="career-hero__copy">
                <span class="career-pill">Carrières OVANIE</span>
                <h1>Construisez avec nous<br>le futur du <span>commerce</span> BTP</h1>
                <p>
                    Chez OVANIE, nous connectons les professionnels, simplifions les chantiers et bâtissons chaque jour
                    une plateforme utile, fiable et performante au service de toute la Côte d’Ivoire.
                </p>
                <div class="company-actions">
                    <a href="{{ route('contact.index') }}?sujet=candidature" class="company-btn company-btn--orange">
                        Envoyer une candidature <i data-lucide="send"></i>
                    </a>
                    <a href="{{ route('about') }}" class="company-btn company-btn--ghost">
                        Découvrir OVANIE <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="career-hero__art" aria-hidden="true">
                <span class="career-ring career-ring--1"></span>
                <span class="career-ring career-ring--2"></span>
                <span class="career-ring career-ring--3"></span>
                <span class="career-dots career-dots--left"></span>
                <span class="career-dots career-dots--right"></span>
            </div>
        </div>
    </section>

    <div class="company-shell career-main">
        <section class="career-intro-grid">
            <article class="company-panel career-why">
                <span class="company-kicker">Pourquoi nous rejoindre</span>
                <h2>Participer à un projet concret, connecté au terrain</h2>
                <p>
                    Rejoindre OVANIE, c’est intégrer une équipe engagée qui relève des défis concrets pour les professionnels du BTP.
                    Vous évoluerez dans un environnement stimulant, au cœur de l’innovation et de l’impact terrain.
                </p>
                <div class="career-outline-icon"><i data-lucide="users"></i></div>
            </article>

            <article class="company-panel company-panel--navy career-spirit">
                <span class="company-kicker">L’esprit OVANIE</span>
                <h2>Responsabilité, efficacité, service</h2>
                <ul class="career-spirit-list">
                    <li>Nous assumons nos engagements</li>
                    <li>Nous agissons avec efficacité</li>
                    <li>Nous plaçons le client au centre</li>
                    <li>Nous visons l’amélioration continue</li>
                    <li>Nous avançons ensemble avec respect</li>
                </ul>
                <span class="career-dots career-dots--panel"></span>
            </article>
        </section>

        <section class="career-section career-section--domains">
            <div class="career-section-heading career-section-heading--center">
                <span class="company-kicker">Domaines de carrière</span>
                <h2>Des métiers complémentaires autour d’une même plateforme</h2>
                <p>Quelle que soit votre expertise, vous trouverez chez OVANIE un terrain pour agir, apprendre et grandir.</p>
            </div>

            <div class="career-domain-grid">
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="trending-up"></i></div>
                    <div><h3>Commercial & partenariats</h3><p>Développer notre réseau, créer des partenariats durables et accompagner nos vendeurs vers la réussite.</p></div>
                </article>
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="arrow-left-right"></i></div>
                    <div><h3>Logistique & opérations</h3><p>Assurer des livraisons fiables, optimisées et ponctuelles sur toute l’étendue du territoire.</p></div>
                </article>
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="target"></i></div>
                    <div><h3>Service client & support</h3><p>Accompagner nos utilisateurs avec réactivité, écoute et professionnalisme à chaque étape.</p></div>
                </article>
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="command"></i></div>
                    <div><h3>Produit & technologie</h3><p>Concevoir, améliorer et sécuriser une plateforme performante, simple et évolutive.</p></div>
                </article>
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="sparkles"></i></div>
                    <div><h3>Marketing & communication</h3><p>Faire connaître OVANIE, valoriser nos solutions et renforcer notre image de marque.</p></div>
                </article>
                <article class="career-domain-card">
                    <div class="company-round-icon"><i data-lucide="landmark"></i></div>
                    <div><h3>Administration & finance</h3><p>Soutenir la croissance avec rigueur, transparence et une gestion efficace des ressources.</p></div>
                </article>
            </div>
        </section>

        <section class="career-section career-section--qualities">
            <div class="career-section-heading career-section-heading--center">
                <span class="company-kicker">Ce que nous apprécions</span>
                <h2>Les qualités qui comptent chez OVANIE</h2>
            </div>

            <div class="career-quality-grid">
                <article class="career-quality-card"><div class="company-round-icon"><i data-lucide="check"></i></div><h3>Sens du client</h3><p>Vous comprenez les besoins réels du terrain et placez la satisfaction au cœur de vos actions.</p></article>
                <article class="career-quality-card"><div class="company-round-icon"><i data-lucide="circle-alert"></i></div><h3>Rigueur</h3><p>Vous êtes organisé(e), fiable et attentif(ve) aux détails dans tout ce que vous entreprenez.</p></article>
                <article class="career-quality-card"><div class="company-round-icon"><i data-lucide="arrow-left-right"></i></div><h3>Esprit d’équipe</h3><p>Vous collaborez avec bienveillance, partagez vos connaissances et avancez ensemble.</p></article>
                <article class="career-quality-card"><div class="company-round-icon"><i data-lucide="plus"></i></div><h3>Initiative</h3><p>Vous proposez, innovez et prenez des initiatives pour aller plus loin, chaque jour.</p></article>
            </div>
        </section>

        <section class="career-section career-section--process">
            <div class="career-section-heading career-section-heading--center">
                <span class="company-kicker">Candidature</span>
                <h2>Comment se déroule une candidature ?</h2>
            </div>

            <div class="career-process-grid">
                <article class="career-process-card">
                    <div class="career-process-number">1</div>
                    <h3>Envoyez votre profil</h3>
                    <p>Remplissez notre formulaire en ligne et joignez votre CV ainsi qu’une lettre de motivation.</p>
                </article>
                <article class="career-process-card">
                    <div class="career-process-number">2</div>
                    <h3>Étude de la candidature</h3>
                    <p>Notre équipe RH analyse votre profil et vous contacte si votre candidature est sélectionnée.</p>
                </article>
                <article class="career-process-card">
                    <div class="career-process-number">3</div>
                    <h3>Échange avec l’équipe</h3>
                    <p>Un ou plusieurs entretiens pour mieux vous connaître et échanger sur vos motivations.</p>
                </article>
                <article class="career-process-card">
                    <div class="career-process-number">4</div>
                    <h3>Suite du processus</h3>
                    <p>Les prochaines étapes vous seront communiquées selon le poste et votre parcours.</p>
                </article>
            </div>

            <div class="career-security-note">
                <span class="career-security-icon"><i data-lucide="shield-check"></i></span>
                <p><strong>Important :</strong> OVANIE ne demande aucun paiement à aucune étape du processus de recrutement.<br>Méfiez-vous des arnaques et signalez toute tentative suspecte.</p>
            </div>
        </section>

        <section class="company-bottom-cta company-bottom-cta--career">
            <div class="company-bottom-cta__icon"><i data-lucide="users"></i></div>
            <div class="company-bottom-cta__copy">
                <h2>Vous pensez pouvoir apporter<br>quelque chose à OVANIE ?</h2>
                <p>Rejoignez-nous et construisons ensemble l’avenir du commerce BTP en Côte d’Ivoire.</p>
            </div>
            <a href="{{ route('contact.index') }}?sujet=candidature" class="company-btn company-btn--orange">
                Candidature spontanée <i data-lucide="send"></i>
            </a>
        </section>
    </div>
</div>
@endsection
