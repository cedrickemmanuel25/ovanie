@extends('layouts.guest')

@section('title', 'Guide acheteur — Garantie commerciale OVANIE')
@section('meta_description', 'Découvrez la garantie commerciale OVANIE, les produits couverts, les exclusions, la procédure de réclamation et les engagements de protection de l’acheteur.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/buyer-guide.css') }}?v={{ file_exists(public_path('css/buyer-guide.css')) ? filemtime(public_path('css/buyer-guide.css')) : time() }}">
@endpush

@section('content')
<main class="buyer-guide-page" id="buyerGuidePage">
    <div class="buyer-guide-shell">
        <nav class="buyer-guide-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ route('home') }}">Accueil</a>
            <i data-lucide="chevron-right"></i>
            <span>Guides</span>
            <i data-lucide="chevron-right"></i>
            <span>Garantie commerciale OVANIE</span>
        </nav>

        <div class="buyer-guide-layout">
            <aside class="buyer-guide-sidebar">
                <section class="buyer-guide-summary-card">
                    <h2>Sommaire</h2>
                    <nav class="buyer-guide-summary" id="buyerGuideSummary" aria-label="Sommaire du guide acheteur">
                        <a href="#guide-object" class="is-active"><span>1.</span> Objet de la garantie</a>
                        <a href="#guide-covered"><span>2.</span> Produits couverts</a>
                        <a href="#guide-excluded"><span>3.</span> Produits non couverts</a>
                        <a href="#guide-delivery-control"><span>4.</span> Contrôle à la livraison</a>
                        <a href="#guide-conform"><span>5.</span> Garantie “Produit conforme ou remboursé”</a>
                        <a href="#guide-delivery"><span>6.</span> Garantie de livraison</a>
                        <a href="#guide-delay"><span>7.</span> Délai de traitement</a>
                        <a href="#guide-general"><span>8.</span> Dispositions générales</a>
                        <a href="#guide-contact"><span>9.</span> Contact et assistance</a>
                    </nav>
                </section>

                <section class="buyer-guide-commitment-card">
                    <span class="buyer-guide-commitment-icon"><i data-lucide="shield-check"></i></span>
                    <div>
                        <h3>Notre engagement</h3>
                        <p>OVANIE s’engage à vous offrir une expérience d’achat sûre, transparente et équitable.</p>
                    </div>
                </section>
            </aside>

            <div class="buyer-guide-main">
                <section class="buyer-guide-hero" id="guide-object" data-guide-section>
                    <div class="buyer-guide-hero__copy">
                        <a href="#guide-covered" class="buyer-guide-hero__eyebrow">
                            <i data-lucide="shield-check"></i>
                            Consulter la garantie acheteur
                        </a>
                        <h1>Guide acheteur —<br>Garantie commerciale OVANIE</h1>
                        <strong>Acheter en toute confiance sur OVANIE.</strong>
                        <p>Cette garantie commerciale protège tous les acheteurs sur la marketplace OVANIE, dans les conditions décrites ci-dessous.</p>
                    </div>

                    <div class="buyer-guide-hero__visual" aria-hidden="true">
                        <div class="buyer-guide-hero__circle"></div>
                        <div class="buyer-guide-shield"><i data-lucide="shield-check"></i></div>
                        <div class="buyer-guide-material buyer-guide-material--block"></div>
                        <div class="buyer-guide-material buyer-guide-material--bag">CIMENT<br><small>CPJ 42.5</small></div>
                        <div class="buyer-guide-material buyer-guide-material--bars"><span></span><span></span><span></span><span></span></div>
                    </div>
                </section>

                <div class="buyer-guide-duo">
                    <section class="buyer-guide-card buyer-guide-card--success" id="guide-covered" data-guide-section>
                        <h2>2. Produits couverts</h2>
                        <p>La garantie commerciale OVANIE s’applique notamment aux produits suivants :</p>
                        <ul class="buyer-guide-check-list buyer-guide-check-list--success">
                            <li>Matériaux de construction neufs et authentiques</li>
                            <li>Produits conformes à la description et aux photos publiées</li>
                            <li>Produits livrés dans leur emballage d’origine intact</li>
                            <li>Produits non endommagés lors de la livraison</li>
                            <li>Produits respectant les normes en vigueur en Côte d’Ivoire</li>
                        </ul>
                        <div class="buyer-guide-note buyer-guide-note--success">
                            <i data-lucide="circle-check-big"></i>
                            Cette garantie s’applique à toutes les commandes passées sur OVANIE.
                        </div>
                    </section>

                    <section class="buyer-guide-card buyer-guide-card--danger" id="guide-excluded" data-guide-section>
                        <h2>3. Produits non couverts</h2>
                        <p>La garantie commerciale OVANIE ne s’applique pas aux :</p>
                        <ul class="buyer-guide-check-list buyer-guide-check-list--danger">
                            <li>Produits consommés après livraison ou déjà utilisés</li>
                            <li>Produits endommagés par une mauvaise utilisation du client</li>
                            <li>Produits modifiés, réparés ou altérés après livraison</li>
                            <li>Produits périssables ou consommables déjà acceptés</li>
                            <li>Achats effectués en dehors de la plateforme OVANIE</li>
                        </ul>
                        <div class="buyer-guide-note buyer-guide-note--danger">
                            <i data-lucide="info"></i>
                            Ces exclusions garantissent une utilisation équitable et responsable de la garantie.
                        </div>
                    </section>
                </div>

                <section class="buyer-guide-card buyer-guide-card--steps" id="guide-delivery-control" data-guide-section>
                    <h2>4. Contrôle à la livraison</h2>
                    <p>En cas d’anomalie, l’acheteur doit suivre impérativement les étapes ci-dessous :</p>

                    <div class="buyer-guide-steps">
                        <article class="buyer-guide-step-card">
                            <span class="buyer-guide-step-number">1</span>
                            <div class="buyer-guide-step-icon"><i data-lucide="handshake"></i></div>
                            <div>
                                <h3>Signaler le problème au livreur</h3>
                                <p>Vérifiez l’état du colis devant le livreur et signalez toute anomalie avant de valider la réception.</p>
                            </div>
                        </article>

                        <article class="buyer-guide-step-card">
                            <span class="buyer-guide-step-number">2</span>
                            <div class="buyer-guide-step-icon"><i data-lucide="camera"></i></div>
                            <div>
                                <h3>Prendre des photographies</h3>
                                <p>Prenez des photos claires du produit, de l’emballage et du problème constaté.</p>
                            </div>
                        </article>

                        <article class="buyer-guide-step-card">
                            <span class="buyer-guide-step-number">3</span>
                            <div class="buyer-guide-step-icon"><i data-lucide="file-text"></i></div>
                            <div>
                                <h3>Déposer une réclamation</h3>
                                <p>Envoyez votre réclamation depuis votre espace client dans un délai maximum de 72 heures après livraison.</p>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="buyer-guide-card buyer-guide-card--solutions" id="guide-conform" data-guide-section>
                    <h2>5. Garantie “Produit conforme ou remboursé”</h2>
                    <p>Si le produit reçu ne correspond pas à la description ou présente un défaut, OVANIE s’engage à vous proposer une solution adaptée.</p>
                    <h3>OVANIE pourra, selon le cas :</h3>

                    <div class="buyer-guide-solutions-grid">
                        <article class="buyer-guide-solution buyer-guide-solution--green">
                            <span><i data-lucide="package-check"></i></span>
                            <div><strong>Remplacer</strong><p>Nous vous envoyons un produit identique conforme.</p></div>
                        </article>
                        <article class="buyer-guide-solution buyer-guide-solution--orange">
                            <span><i data-lucide="repeat-2"></i></span>
                            <div><strong>Échanger</strong><p>Vous pouvez échanger contre un autre produit de valeur équivalente.</p></div>
                        </article>
                        <article class="buyer-guide-solution buyer-guide-solution--blue">
                            <span><i data-lucide="wallet-cards"></i></span>
                            <div><strong>Rembourser</strong><p>Remboursement intégral ou partiel selon le mode de paiement initial.</p></div>
                        </article>
                        <article class="buyer-guide-solution buyer-guide-solution--purple">
                            <span><i data-lucide="gift"></i></span>
                            <div><strong>Bon d’achat</strong><p>Un bon d’achat utilisable sur la plateforme OVANIE.</p></div>
                        </article>
                    </div>
                </section>

                <div class="buyer-guide-duo buyer-guide-duo--lower">
                    <section class="buyer-guide-card" id="guide-delivery" data-guide-section>
                        <h2>6. Garantie de livraison</h2>
                        <ul class="buyer-guide-check-list buyer-guide-check-list--blue">
                            <li>Transporteurs qualifiés et suivi de commande</li>
                            <li>Information du client à chaque étape importante</li>
                            <li>Assistance en cas d’incident ou de retard</li>
                            <li>Compensation commerciale possible lorsque le retard est imputable à OVANIE ou au transporteur partenaire</li>
                        </ul>
                    </section>

                    <section class="buyer-guide-card" id="guide-delay" data-guide-section>
                        <h2>7. Délai de traitement</h2>
                        <div class="buyer-guide-delay-row"><strong>24 à 72 h</strong><span>Accusé de réception de la réclamation</span></div>
                        <div class="buyer-guide-delay-row"><strong>7 à 14 jours</strong><span>Résolution complète du dossier</span></div>
                        <p class="buyer-guide-muted">Des délais supplémentaires peuvent être nécessaires pour certains produits importés.</p>
                    </section>
                </div>

                <section class="buyer-guide-card" id="guide-general" data-guide-section>
                    <h2>8. Dispositions générales</h2>
                    <div class="buyer-guide-general-grid">
                        <div>
                            <h3>Produits reconditionnés</h3>
                            <p>Les produits reconditionnés bénéficient d’une garantie spécifique indiquée sur leur fiche produit et couvrant les défauts techniques non causés par l’utilisateur.</p>
                        </div>
                        <div>
                            <h3>Produits importés</h3>
                            <p>Les produits expédiés depuis l’étranger bénéficient d’un suivi logistique et d’une assistance dédiée. Les délais peuvent varier selon les formalités douanières.</p>
                        </div>
                        <div>
                            <h3>Limitation de responsabilité</h3>
                            <p>OVANIE ne saurait être tenue responsable des dommages indirects, de la mauvaise utilisation ou des informations erronées fournies par l’acheteur.</p>
                        </div>
                    </div>
                </section>

                <section class="buyer-guide-contact" id="guide-contact" data-guide-section>
                    <div class="buyer-guide-contact__icon"><i data-lucide="headphones"></i></div>
                    <div>
                        <h2>9. Contact et assistance</h2>
                        <p>Pour toute question sur la garantie ou une réclamation, notre équipe support vous accompagne.</p>
                    </div>
                    <div class="buyer-guide-contact__actions">
                        <a href="{{ Route::has('contact.index') ? route('contact.index') : url('/contact') }}">Contacter OVANIE <i data-lucide="arrow-right"></i></a>
                        <a href="tel:{{ config('public_contact.phone_e164', '+2250161780000') }}">{{ config('public_contact.phone_display', '01 61 78 00 00') }}</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('js/buyer-guide.js') }}?v={{ file_exists(public_path('js/buyer-guide.js')) ? filemtime(public_path('js/buyer-guide.js')) : time() }}" defer></script>
@endpush
