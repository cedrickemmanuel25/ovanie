@extends('layouts.vendor')

@section('title', 'Conditions générales d’utilisation | OVANIE')

@section('styles')
<style>
    :root {
        --cgu-navy: #0a2a62;
        --cgu-blue: #0d5ee8;
        --cgu-text: #172f5f;
        --cgu-muted: #6b7e9f;
        --cgu-border: #dfe7f1;
        --cgu-soft: #f7f9fc;
        --cgu-card: #ffffff;
        --cgu-shadow: 0 14px 36px rgba(16, 42, 90, .08);
    }

    .cgu-page {
        width: 100%;
        max-width: 1380px;
        margin: 0 auto;
        padding: 28px 30px 56px;
        color: var(--cgu-text);
    }

    .cgu-header {
        margin-bottom: 28px;
    }

    .cgu-header h1 {
        margin: 0;
        color: var(--cgu-navy);
        font-size: clamp(34px, 3.1vw, 52px);
        line-height: 1.06;
        letter-spacing: -1.5px;
        font-weight: 900;
    }

    .cgu-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 14px 22px;
        margin-top: 14px;
    }

    .cgu-version {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 14px;
        border-radius: 999px;
        background: #eaf2ff;
        color: #1d56b3;
        font-size: 12px;
        font-weight: 800;
    }

    .cgu-updated {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        color: #425a80;
        font-size: 13px;
        font-weight: 600;
    }

    .cgu-updated svg {
        width: 17px;
        height: 17px;
        color: #4b648a;
    }

    .cgu-layout {
        display: grid;
        grid-template-columns: 292px minmax(0, 1fr);
        gap: 24px;
        align-items: start;
    }

    .cgu-toc {
        position: sticky;
        top: 92px;
        max-height: calc(100vh - 118px);
        overflow: hidden;
        border: 1px solid var(--cgu-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: var(--cgu-shadow);
    }

    .cgu-search {
        padding: 16px 16px 12px;
        background: #fff;
    }

    .cgu-search-wrap {
        position: relative;
    }

    .cgu-search-wrap svg {
        position: absolute;
        left: 13px;
        top: 50%;
        width: 17px;
        height: 17px;
        transform: translateY(-50%);
        color: #7386a6;
        pointer-events: none;
    }

    .cgu-search-input {
        width: 100%;
        height: 44px;
        padding: 0 14px 0 40px;
        border: 1px solid #cfdae9;
        border-radius: 7px;
        background: #fff;
        color: var(--cgu-navy);
        font: inherit;
        font-size: 12px;
        outline: none;
        transition: .18s ease;
    }

    .cgu-search-input:focus {
        border-color: var(--cgu-blue);
        box-shadow: 0 0 0 3px rgba(13, 94, 232, .08);
    }

    .cgu-nav {
        max-height: calc(100vh - 196px);
        overflow-y: auto;
        padding: 0 10px 14px;
        scrollbar-width: thin;
        scrollbar-color: #cbd6e6 transparent;
    }

    .cgu-nav::-webkit-scrollbar { width: 6px; }
    .cgu-nav::-webkit-scrollbar-thumb { background: #cbd6e6; border-radius: 999px; }

    .cgu-nav-link {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 9px;
        min-height: 42px;
        padding: 10px 11px;
        border-radius: 7px;
        color: #1f4d91;
        font-size: 12px;
        line-height: 1.35;
        font-weight: 650;
        text-decoration: none;
        transition: .18s ease;
    }

    .cgu-nav-link:hover {
        background: #f3f7fd;
        color: var(--cgu-blue);
    }

    .cgu-nav-link.is-active {
        background: #eef5ff;
        color: #104ea8;
        font-weight: 850;
        box-shadow: inset 0 0 0 1px #cfe0fb;
    }

    .cgu-nav-link.is-active::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 7px;
        bottom: 7px;
        width: 3px;
        border-radius: 999px;
        background: var(--cgu-blue);
    }

    .cgu-nav-index {
        flex: 0 0 auto;
        min-width: 20px;
        color: inherit;
        font-weight: 800;
    }

    .cgu-content-card {
        border: 1px solid var(--cgu-border);
        border-radius: 12px;
        background: var(--cgu-card);
        box-shadow: var(--cgu-shadow);
        overflow: hidden;
    }

    .cgu-content-inner {
        padding: 28px 34px 36px;
    }

    .cgu-section {
        scroll-margin-top: 100px;
        padding: 0 0 26px;
        margin: 0 0 26px;
        border-bottom: 1px solid #dce5f0;
    }

    .cgu-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: 0;
    }

    .cgu-section-title {
        display: flex;
        align-items: center;
        gap: 13px;
        margin: 0 0 18px;
        color: var(--cgu-navy);
        font-size: clamp(19px, 1.55vw, 26px);
        line-height: 1.2;
        font-weight: 900;
    }

    .cgu-section-number,
    .cgu-preamble-icon {
        width: 33px;
        height: 33px;
        flex: 0 0 33px;
        display: grid;
        place-items: center;
        border: 2px solid #1d4a8b;
        border-radius: 50%;
        color: #0d3d7d;
        font-size: 14px;
        font-weight: 900;
    }

    .cgu-preamble-icon {
        border: 0;
        background: #e9f2ff;
        color: var(--cgu-blue);
    }

    .cgu-preamble-icon svg {
        width: 19px;
        height: 19px;
    }

    .cgu-section p {
        margin: 0 0 12px;
        color: #1f2f4a;
        font-size: 14px;
        line-height: 1.66;
        font-weight: 500;
    }

    .cgu-section p:last-child { margin-bottom: 0; }

    .cgu-section ul {
        margin: 10px 0 0;
        padding-left: 22px;
    }

    .cgu-section li {
        margin: 7px 0;
        color: #1f2f4a;
        font-size: 14px;
        line-height: 1.55;
    }

    .cgu-section strong {
        color: #172f5f;
        font-weight: 850;
    }

    .cgu-note {
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid #dce8f8;
        border-radius: 8px;
        background: #f7faff;
        color: #31517f;
        font-size: 12.5px;
        line-height: 1.55;
    }

    .cgu-empty-search {
        display: none;
        padding: 18px 14px;
        color: #7182a0;
        font-size: 12px;
        text-align: center;
    }

    .cgu-empty-search.is-visible { display: block; }

    @media (max-width: 1100px) {
        .cgu-page { padding: 24px 20px 48px; }
        .cgu-layout { grid-template-columns: 250px minmax(0, 1fr); gap: 18px; }
        .cgu-content-inner { padding: 24px 26px 30px; }
    }

    @media (max-width: 860px) {
        .cgu-layout { grid-template-columns: 1fr; }
        .cgu-toc {
            position: relative;
            top: auto;
            max-height: none;
        }
        .cgu-nav {
            max-height: 280px;
        }
    }

    @media (max-width: 640px) {
        .cgu-page { padding: 18px 14px 36px; }
        .cgu-header h1 { font-size: 32px; letter-spacing: -.8px; }
        .cgu-content-inner { padding: 20px 18px 24px; }
        .cgu-section-title { align-items: flex-start; font-size: 20px; }
        .cgu-section p,
        .cgu-section li { font-size: 13.5px; }
    }
</style>
@endsection

@section('content')
@php
    $sections = [
        ['id' => 'preambule', 'index' => '•', 'label' => 'Préambule'],
        ['id' => 'article-1', 'index' => '1.', 'label' => 'Article 1 — Objet'],
        ['id' => 'article-2', 'index' => '2.', 'label' => 'Article 2 — Définitions'],
        ['id' => 'article-3', 'index' => '3.', 'label' => 'Article 3 — Acceptation des CGU'],
        ['id' => 'article-4', 'index' => '4.', 'label' => 'Article 4 — Conditions d’accès'],
        ['id' => 'article-5', 'index' => '5.', 'label' => 'Article 5 — Création de compte'],
        ['id' => 'article-6', 'index' => '6.', 'label' => 'Article 6 — Obligations du vendeur'],
        ['id' => 'article-7', 'index' => '7.', 'label' => 'Article 7 — Mise en ligne des produits'],
        ['id' => 'article-8', 'index' => '8.', 'label' => 'Article 8 — Commandes et exécution'],
        ['id' => 'article-9', 'index' => '9.', 'label' => 'Article 9 — Paiements et reversements'],
        ['id' => 'article-10', 'index' => '10.', 'label' => 'Article 10 — Livraison'],
        ['id' => 'article-11', 'index' => '11.', 'label' => 'Article 11 — Responsabilités'],
        ['id' => 'article-12', 'index' => '12.', 'label' => 'Article 12 — Suspension / résiliation'],
        ['id' => 'article-13', 'index' => '13.', 'label' => 'Article 13 — Données personnelles'],
        ['id' => 'article-14', 'index' => '14.', 'label' => 'Article 14 — Droit applicable'],
    ];
@endphp

<div class="cgu-page">
    <header class="cgu-header">
        <h1>Conditions générales d’utilisation</h1>
        <div class="cgu-meta">
            <span class="cgu-version">Version 2026</span>
            <span class="cgu-updated">
                <i data-lucide="calendar-days"></i>
                Dernière mise à jour : 8 juillet 2026
            </span>
        </div>
    </header>

    <div class="cgu-layout">
        <aside class="cgu-toc" aria-label="Sommaire des CGU">
            <div class="cgu-search">
                <div class="cgu-search-wrap">
                    <i data-lucide="search"></i>
                    <input
                        type="search"
                        id="cguSearch"
                        class="cgu-search-input"
                        placeholder="Rechercher dans les CGU"
                        autocomplete="off"
                    >
                </div>
            </div>

            <nav class="cgu-nav" id="cguNav">
                @foreach($sections as $section)
                    <a href="#{{ $section['id'] }}" class="cgu-nav-link {{ $loop->first ? 'is-active' : '' }}" data-cgu-link data-search="{{ mb_strtolower($section['label']) }}">
                        <span class="cgu-nav-index">{{ $section['index'] }}</span>
                        <span>{{ $section['label'] }}</span>
                    </a>
                @endforeach
                <div class="cgu-empty-search" id="cguEmptySearch">Aucun article ne correspond à votre recherche.</div>
            </nav>
        </aside>

        <article class="cgu-content-card">
            <div class="cgu-content-inner">
                <section class="cgu-section" id="preambule" data-cgu-section>
                    <h2 class="cgu-section-title">
                        <span class="cgu-preamble-icon"><i data-lucide="info"></i></span>
                        Préambule
                    </h2>
                    <p>Les présentes Conditions Générales d’Utilisation (CGU) régissent l’accès et l’utilisation de l’espace vendeur OVANIE, marketplace ivoirienne dédiée à la commercialisation de matériaux de construction, d’équipements et de services liés au BTP entre professionnels.</p>
                    <p>En créant un compte vendeur ou en utilisant la Plateforme, le vendeur reconnaît avoir pris connaissance des présentes CGU et les accepter sans réserve.</p>
                </section>

                <section class="cgu-section" id="article-1" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">1</span>Article 1 — Objet</h2>
                    <p>Les présentes CGU définissent les conditions d’utilisation de la Plateforme OVANIE par les vendeurs professionnels.</p>
                    <p>Elles précisent notamment les règles applicables à la publication des offres, à la gestion des commandes, aux livraisons et aux reversements des sommes dues aux vendeurs.</p>
                </section>

                <section class="cgu-section" id="article-2" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">2</span>Article 2 — Définitions</h2>
                    <p>Aux fins des présentes CGU, les termes ci-dessous auront la signification suivante :</p>
                    <ul>
                        <li><strong>Vendeur :</strong> toute personne physique ou morale inscrite sur la Plateforme afin de proposer à la vente des produits ou services.</li>
                        <li><strong>Client :</strong> toute personne achetant un produit ou un service via la Plateforme.</li>
                        <li><strong>Plateforme :</strong> le site et les services numériques OVANIE.</li>
                        <li><strong>Produit :</strong> tout matériau de construction, équipement ou article proposé à la vente.</li>
                        <li><strong>Commande :</strong> la demande d’achat d’un ou plusieurs produits passée par un client et acceptée conformément au parcours OVANIE.</li>
                        <li><strong>Reversement :</strong> le paiement au vendeur des sommes qui lui sont dues, après déduction des frais et commissions applicables.</li>
                    </ul>
                </section>

                <section class="cgu-section" id="article-3" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">3</span>Article 3 — Acceptation des CGU</h2>
                    <p>La création d’un compte vendeur et l’utilisation de la Plateforme valent acceptation pleine et entière des présentes CGU.</p>
                    <p>Le vendeur s’engage à respecter les présentes CGU ainsi que l’ensemble des lois et réglementations applicables à son activité.</p>
                </section>

                <section class="cgu-section" id="article-4" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">4</span>Article 4 — Conditions d’accès</h2>
                    <p>L’accès à l’espace vendeur est réservé aux personnes physiques ou morales légalement habilitées à exercer une activité commerciale.</p>
                    <p>Pour ouvrir et utiliser un compte vendeur, le demandeur doit notamment :</p>
                    <ul>
                        <li>être majeur et disposer de la capacité juridique pour contracter ;</li>
                        <li>fournir des informations exactes, complètes et à jour ;</li>
                        <li>fournir les justificatifs d’identité et, lorsqu’ils sont demandés, les documents liés à son activité professionnelle ;</li>
                        <li>disposer d’un moyen de reversement valide pour recevoir les sommes dues.</li>
                    </ul>
                    <p>OVANIE peut demander des informations complémentaires lorsqu’elles sont nécessaires à la sécurité de la Plateforme, au traitement d’un paiement ou au respect d’obligations réglementaires.</p>
                </section>

                <section class="cgu-section" id="article-5" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">5</span>Article 5 — Création de compte</h2>
                    <p>Le vendeur doit fournir des informations complètes, exactes et à jour lors de la création de son compte et de sa boutique.</p>
                    <p>Il est seul responsable de la confidentialité de ses identifiants et de toute activité effectuée depuis son compte.</p>
                    <p>Le vendeur s’engage à informer OVANIE sans délai en cas d’accès non autorisé, de perte de ses identifiants ou de suspicion de fraude.</p>
                </section>

                <section class="cgu-section" id="article-6" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">6</span>Article 6 — Obligations du vendeur</h2>
                    <p>Le vendeur s’engage à utiliser la Plateforme de manière professionnelle, loyale et conforme aux présentes CGU.</p>
                    <ul>
                        <li>maintenir à jour les prix, stocks et caractéristiques de ses produits ;</li>
                        <li>répondre aux commandes et demandes clients dans des délais raisonnables ;</li>
                        <li>ne pas publier d’informations trompeuses, incomplètes ou susceptibles d’induire le client en erreur ;</li>
                        <li>respecter les engagements annoncés en matière de préparation, de disponibilité et de livraison.</li>
                    </ul>
                </section>

                <section class="cgu-section" id="article-7" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">7</span>Article 7 — Mise en ligne des produits</h2>
                    <p>Chaque fiche produit doit présenter des informations fiables, suffisamment précises et cohérentes avec le produit réellement disponible.</p>
                    <p>Le vendeur reste responsable des visuels, descriptions, prix, informations techniques, unités de vente, conditionnements, stocks et données logistiques qu’il renseigne.</p>
                    <div class="cgu-note">OVANIE peut suspendre ou masquer une fiche produit manifestement incorrecte, trompeuse, non conforme ou présentant un risque pour les clients ou la Plateforme.</div>
                </section>

                <section class="cgu-section" id="article-8" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">8</span>Article 8 — Commandes et exécution</h2>
                    <p>Le vendeur doit traiter les commandes qui lui sont attribuées conformément au statut de la commande et aux procédures prévues dans son espace vendeur.</p>
                    <p>Il doit préparer les produits commandés, signaler toute difficulté réelle et respecter les délais annoncés. Les changements de statut doivent correspondre à la situation réelle de la commande.</p>
                </section>

                <section class="cgu-section" id="article-9" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">9</span>Article 9 — Paiements et reversements</h2>
                    <p>Les montants dus au vendeur sont calculés sur la base des ventes validées, après application des commissions, frais de traitement, ajustements, remboursements ou autres éléments applicables.</p>
                    <p>Les reversements sont effectués selon le calendrier et le moyen de paiement configurés dans l’espace vendeur, sous réserve des contrôles nécessaires et de l’absence de blocage lié à un litige ou à une anomalie.</p>
                </section>

                <section class="cgu-section" id="article-10" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">10</span>Article 10 — Livraison</h2>
                    <p>Le vendeur doit fournir les données physiques exactes nécessaires à l’organisation de la livraison, notamment le poids, les dimensions, le volume, la fragilité et les besoins de déchargement.</p>
                    <p>Lorsque le vendeur gère sa propre logistique, il doit maintenir une grille tarifaire et des capacités de transport cohérentes avec ses moyens réels.</p>
                    <p>Lorsque la livraison est prise en charge par le dispositif logistique OVANIE, le vendeur doit préparer la commande et la remettre selon les instructions opérationnelles communiquées.</p>
                </section>

                <section class="cgu-section" id="article-11" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">11</span>Article 11 — Responsabilités</h2>
                    <p>Le vendeur est responsable des informations qu’il publie, de la conformité des produits qu’il met en vente et du respect de ses engagements commerciaux.</p>
                    <p>OVANIE fournit une infrastructure de mise en relation, de commande, de paiement et d’organisation opérationnelle. La responsabilité de chaque acteur demeure appréciée selon son rôle effectif dans l’opération concernée.</p>
                </section>

                <section class="cgu-section" id="article-12" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">12</span>Article 12 — Suspension / résiliation</h2>
                    <p>OVANIE peut restreindre, suspendre ou résilier l’accès à certaines fonctionnalités en cas de fraude, de manquements répétés, de risque de sécurité ou de violation grave des présentes CGU.</p>
                    <p>Lorsque la situation le permet, le vendeur peut être invité à fournir des explications ou documents avant toute décision définitive.</p>
                </section>

                <section class="cgu-section" id="article-13" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">13</span>Article 13 — Données personnelles</h2>
                    <p>Les données personnelles collectées dans le cadre de l’utilisation de l’espace vendeur sont traitées pour la gestion du compte, des commandes, des paiements, de la sécurité, du support et des obligations légales applicables.</p>
                    <p>Le vendeur s’engage à utiliser les informations auxquelles il accède uniquement pour l’exécution des opérations autorisées dans le cadre de la Plateforme.</p>
                </section>

                <section class="cgu-section" id="article-14" data-cgu-section>
                    <h2 class="cgu-section-title"><span class="cgu-section-number">14</span>Article 14 — Droit applicable</h2>
                    <p>Les présentes CGU sont régies par le droit applicable en Côte d’Ivoire.</p>
                    <p>En cas de difficulté, les parties sont invitées à rechercher une solution amiable avant toute procédure contentieuse, sans préjudice des droits reconnus à chacune d’elles.</p>
                </section>
            </div>
        </article>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('cguSearch');
    const links = Array.from(document.querySelectorAll('[data-cgu-link]'));
    const sections = Array.from(document.querySelectorAll('[data-cgu-section]'));
    const empty = document.getElementById('cguEmptySearch');

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    searchInput?.addEventListener('input', () => {
        const term = searchInput.value.trim().toLocaleLowerCase('fr');
        let visible = 0;

        links.forEach((link) => {
            const haystack = link.dataset.search || '';
            const matches = !term || haystack.includes(term);
            link.hidden = !matches;
            if (matches) visible += 1;
        });

        empty?.classList.toggle('is-visible', visible === 0);
    });

    const setActive = (id) => {
        links.forEach((link) => {
            link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
        });
    };

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            const visibleEntries = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            if (visibleEntries.length) {
                setActive(visibleEntries[0].target.id);
            }
        }, {
            rootMargin: '-100px 0px -65% 0px',
            threshold: [0, 0.1, 0.25]
        });

        sections.forEach((section) => observer.observe(section));
    }
});
</script>
@endsection
