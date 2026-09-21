@extends('layouts.guest')

@section('title', 'Conditions générales d’utilisation | OVANIE')
@section('meta_description', 'Consultez les Conditions Générales d’Utilisation de la marketplace OVANIE.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cgu.css') }}?v={{ file_exists(public_path('css/cgu.css')) ? filemtime(public_path('css/cgu.css')) : time() }}">
@endpush

@section('content')
<main class="cgu-page" id="cguPage">
    <div class="cgu-shell">
        <nav class="cgu-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ route('home') }}">Accueil</a>
            <i data-lucide="chevron-right"></i>
            <a href="#">Aide & informations</a>
            <i data-lucide="chevron-right"></i>
            <span>Conditions générales d’utilisation</span>
        </nav>

        <header class="cgu-hero">
            <div class="cgu-hero__copy">
                <h1>Conditions générales d’utilisation</h1>
                <div class="cgu-meta">
                    <span class="cgu-version">Version 2026</span>
                    <span class="cgu-update"><i data-lucide="calendar-days"></i> Dernière mise à jour : 8 juillet 2026</span>
                </div>
            </div>

            <label class="cgu-document-search" for="cguDocumentSearch">
                <i data-lucide="search"></i>
                <input id="cguDocumentSearch" type="search" placeholder="Rechercher dans ce document…" autocomplete="off">
            </label>
        </header>

        <div class="cgu-layout">
            <aside class="cgu-sidebar">
                <div class="cgu-summary-card">
                    <h2>Sommaire</h2>

                    <label class="cgu-article-search" for="cguArticleSearch">
                        <input id="cguArticleSearch" type="search" placeholder="Rechercher un article…" autocomplete="off">
                        <i data-lucide="search"></i>
                    </label>

                    <nav class="cgu-summary-nav" id="cguSummaryNav" aria-label="Sommaire des CGU">
                        <a href="#article-1" data-article-link="article-1" class="is-active">Article 1 — Objet</a>
                        <a href="#article-3" data-article-link="article-3">Article 3 — Définitions</a>
                        <a href="#article-6" data-article-link="article-6">Article 6 — Fonctionnement de la marketplace</a>
                        <a href="#article-7" data-article-link="article-7">Article 7 — Commandes</a>
                        <a href="#article-8" data-article-link="article-8">Article 8 — Prix</a>
                        <a href="#article-10" data-article-link="article-10">Article 10 — Livraison</a>
                    </nav>
                </div>

                <div class="cgu-help-card">
                    <div class="cgu-help-line">
                        <span class="cgu-help-icon"><i data-lucide="headphones"></i></span>
                        <div>
                            <strong>Besoin d’aide ?</strong>
                            <span>Notre équipe est à votre écoute.</span>
                            <a href="{{ Route::has('contact.index') ? route('contact.index') : url('/contact') }}">Nous contacter <i data-lucide="arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="cgu-help-divider"></div>

                    <div class="cgu-acceptance-note">
                        <i data-lucide="shield-check"></i>
                        <p>En utilisant OVANIE, vous acceptez les présentes Conditions Générales d’Utilisation.</p>
                    </div>
                </div>
            </aside>

            <article class="cgu-document" id="cguDocument">
                <section class="cgu-article" id="article-1" data-article-title="Article 1 Objet">
                    <h2>ARTICLE 1 : OBJET</h2>
                    <p>Les présentes Conditions Générales d’Utilisation régissent l’accès et l’utilisation de la plateforme : <strong>www.ovanie.com</strong>.</p>
                    <p>La plateforme permet aux acheteurs, vendeurs, fournisseurs, prestataires et professionnels de publier, consulter, acheter ou vendre des biens et services via une place de marché numérique spécialisée dans le bâtiment, les travaux publics, les équipements techniques et les services associés.</p>
                    <p>L’utilisation du site implique l’acceptation pleine et entière des présentes CGU.</p>
                </section>

                <section class="cgu-article" id="article-3" data-article-title="Article 3 Définitions">
                    <h2>ARTICLE 3 : DÉFINITIONS</h2>

                    <div class="cgu-definition-grid">
                        <div class="cgu-definition-row">
                            <span class="cgu-definition-icon"><i data-lucide="circle-user-round"></i></span>
                            <strong>Acheteur</strong>
                            <p>Toute personne physique ou morale effectuant une commande sur la plateforme.</p>
                        </div>
                        <div class="cgu-definition-row">
                            <span class="cgu-definition-icon"><i data-lucide="store"></i></span>
                            <strong>Vendeur</strong>
                            <p>Toute personne physique ou morale proposant des produits ou services sur la plateforme.</p>
                        </div>
                        <div class="cgu-definition-row">
                            <span class="cgu-definition-icon"><i data-lucide="users-round"></i></span>
                            <strong>Utilisateur</strong>
                            <p>Toute personne naviguant ou utilisant les services OVANIE.</p>
                        </div>
                        <div class="cgu-definition-row">
                            <span class="cgu-definition-icon"><i data-lucide="monitor"></i></span>
                            <strong>Marketplace</strong>
                            <p>Infrastructure numérique permettant la mise en relation entre acheteurs et vendeurs.</p>
                        </div>
                    </div>
                </section>

                <section class="cgu-article" id="article-6" data-article-title="Article 6 Fonctionnement de la marketplace">
                    <h2>ARTICLE 6 : FONCTIONNEMENT DE LA MARKETPLACE</h2>
                    <p>OVANIE agit principalement comme intermédiaire technique.</p>
                    <p>Sauf indication contraire :</p>
                    <ul>
                        <li>OVANIE n’est pas le fabricant des produits ;</li>
                        <li>OVANIE n’est pas partie au contrat conclu entre l’acheteur et le vendeur ;</li>
                        <li>chaque vendeur demeure responsable des produits proposés ;</li>
                        <li>chaque vendeur garantit la légalité de ses offres.</li>
                    </ul>
                    <p>OVANIE peut toutefois intervenir comme vendeur direct sur certaines offres identifiées comme telles.</p>
                </section>

                <section class="cgu-article" id="article-7" data-article-title="Article 7 Commandes">
                    <h2>ARTICLE 7 : COMMANDES</h2>
                    <p>Toute commande validée constitue un engagement ferme de l’acheteur.</p>
                    <p>La commande devient définitive après :</p>
                    <ul>
                        <li>validation du panier ;</li>
                        <li>acceptation des présentes CGU ;</li>
                        <li>confirmation du paiement lorsque celui-ci est exigé.</li>
                    </ul>
                    <p>OVANIE peut annuler toute commande présentant un risque de fraude.</p>
                </section>

                <section class="cgu-article" id="article-8" data-article-title="Article 8 Prix">
                    <h2>ARTICLE 8 : PRIX</h2>
                    <p>Les prix affichés sont indiqués en Franc CFA sauf mention contraire.</p>
                    <p>Les prix peuvent inclure ou non :</p>
                    <ul>
                        <li>les frais de livraison ;</li>
                        <li>les taxes applicables ;</li>
                        <li>les frais de traitement.</li>
                    </ul>
                    <p>Le prix applicable est celui affiché au moment de la validation de la commande.</p>
                </section>

                <section class="cgu-article" id="article-10" data-article-title="Article 10 Livraison">
                    <h2>ARTICLE 10 : LIVRAISON</h2>
                    <p>Les délais communiqués sont indicatifs.</p>
                    <p>OVANIE ou le vendeur s’engage à mettre en œuvre les moyens raisonnables nécessaires pour assurer la livraison.</p>
                    <p>La responsabilité d’OVANIE ne pourra être engagée en cas :</p>
                    <ul>
                        <li>de force majeure ;</li>
                        <li>d’informations erronées fournies par le client ;</li>
                        <li>d’événements indépendants de sa volonté.</li>
                    </ul>
                </section>

                <div class="cgu-no-results" id="cguNoResults" hidden>
                    <i data-lucide="search-x"></i>
                    <strong>Aucun résultat trouvé</strong>
                    <span>Essayez un autre mot-clé.</span>
                </div>
            </article>
        </div>
    </div>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('js/cgu.js') }}?v={{ file_exists(public_path('js/cgu.js')) ? filemtime(public_path('js/cgu.js')) : time() }}" defer></script>
@endpush
