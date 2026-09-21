@extends('layouts.guest')

@section('title', 'Vendre sur OVANIE — Développez votre activité')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/business-pages.css') }}">
@endpush

@section('content')
@php
    $applyUrl = route('open-shop');
    $benefits = [
        ['target', 'Boostez vos ventes', 'Touchez chaque jour des clients professionnels et particuliers actifs.'],
        ['megaphone', 'Gagnez en visibilité', 'Présentez vos produits sur la marketplace BTP de référence.'],
        ['shield-check', 'Vendez en toute sécurité', 'Profitez de paiements encadrés et d’une assistance dédiée.'],
        ['gauge', 'Simplifiez votre activité', 'Gérez produits, commandes et livraisons depuis votre espace vendeur.'],
        ['chart-no-axes-combined', 'Développez votre marque', 'Construisez votre réputation et fidélisez durablement vos clients.'],
    ];
    $steps = [
        ['store', 'Ouvrez votre boutique', 'Renseignez gratuitement les informations de votre entreprise.'],
        ['package-plus', 'Ajoutez vos produits', 'Publiez photos, prix, stocks et informations techniques.'],
        ['shopping-cart', 'Recevez des commandes', 'Les clients commandent en ligne en toute confiance.'],
        ['truck', 'Préparez et expédiez', 'Choisissez la solution logistique adaptée à votre activité.'],
        ['wallet-cards', 'Recevez vos paiements', 'Consultez vos transactions depuis votre espace vendeur.'],
    ];
@endphp
<main class="bp-page">
    <div class="bp-shell">
        <nav class="bp-breadcrumb"><a href="{{ route('home') }}">Accueil</a><span>›</span><strong>Vendre sur OVANIE</strong></nav>

        <section class="bp-hero" style="background-image:url('{{ asset('images/business-pages/sell-on-ovanie-hero.png') }}')">
            <div class="bp-hero__content">
                <h1>Développez votre business<br>avec <span class="bp-orange">OVANIE</span></h1>
                <p class="bp-hero__lead">La marketplace BTP en Côte d’Ivoire. Vendez davantage avec un espace professionnel simple et sécurisé.</p>
                <div class="bp-hero__features">
                    <div class="bp-mini"><i data-lucide="users"></i><div><strong>Clients actifs</strong>Audience BTP</div></div>
                    <div class="bp-mini"><i data-lucide="chart-no-axes-combined"></i><div><strong>Visibilité</strong>Produits valorisés</div></div>
                    <div class="bp-mini"><i data-lucide="shield-check"></i><div><strong>Paiements</strong>Transactions suivies</div></div>
                    <div class="bp-mini"><i data-lucide="headphones"></i><div><strong>Support</strong>Équipe dédiée</div></div>
                </div>
                <div class="bp-actions"><a class="bp-btn bp-btn--orange" href="{{ $applyUrl }}">Ouvrir ma boutique <span>→</span></a><a class="bp-btn bp-btn--outline" href="#fonctionnement">En savoir plus</a></div>
            </div>
            <aside class="bp-hero__stats"><b>OVANIE</b><p>une marketplace dédiée au BTP</p><span>Catégories BTP spécialisées</span><span>Solutions de livraison en Côte d’Ivoire</span><span>Paiements suivis et sécurisés</span></aside>
        </section>

        <h2 class="bp-title">Pourquoi vendre sur OVANIE ?</h2>
        <section class="bp-grid bp-grid--5">
            @foreach($benefits as [$icon,$title,$text])
                <article class="bp-card"><div class="bp-card__icon"><i data-lucide="{{ $icon }}"></i></div><h3>{{ $title }}</h3><p>{{ $text }}</p></article>
            @endforeach
        </section>

        <h2 id="fonctionnement" class="bp-title">Comment ça fonctionne ?</h2>
        <section class="bp-process">
            @foreach($steps as $index => [$icon,$title,$text])
                <article class="bp-step"><div class="bp-step__icon"><span class="bp-step__number">{{ $index + 1 }}</span><i data-lucide="{{ $icon }}"></i></div><h3>{{ $title }}</h3><p>{{ $text }}</p></article>
            @endforeach
        </section>

        <section class="bp-info-grid">
            <article class="bp-info"><i data-lucide="percent"></i><div><h3>Commissions transparentes</h3><p>Une tarification claire présentée avant l’activation de votre boutique.</p><ul><li>Aucun frais d’inscription</li><li>Aucun abonnement mensuel imposé</li></ul></div></article>
            <article class="bp-info"><i data-lucide="wallet-cards"></i><div><h3>Paiements vendeur suivis</h3><p>Vos revenus et l’historique de vos transactions restent accessibles dans votre tableau de bord.</p><ul><li>Suivi détaillé des transactions</li><li>Versements encadrés</li></ul></div></article>
            <article class="bp-info"><i data-lucide="truck"></i><div><h3>Logistique simplifiée</h3><p>Choisissez la solution qui correspond à vos produits et à votre organisation.</p><ul><li>Livraison organisée avec OVANIE</li><li>Transport propre possible</li></ul></div></article>
            <article class="bp-info"><i data-lucide="clipboard-list"></i><div><h3>Gestion des commandes</h3><ul><li>Suivi en temps réel</li><li>Gestion des stocks et expéditions</li><li>Statistiques de vente</li></ul></div></article>
            <article class="bp-info"><i data-lucide="badge-check"></i><div><h3>Conditions pour vendre</h3><ul><li>Documents légaux requis</li><li>Produits conformes à la réglementation</li><li>Engagement qualité</li></ul></div></article>
            <article class="bp-info bp-info--cta"><h3>Prêt à développer votre activité ?</h3><p>Créez votre boutique et rejoignez les professionnels présents sur OVANIE.</p><a class="bp-btn bp-btn--orange" href="{{ $applyUrl }}">Ouvrir ma boutique →</a></article>
        </section>

        <section class="bp-trust"><div class="bp-trust__lead"><i data-lucide="shield-check"></i><div><h3>OVANIE, votre partenaire de confiance</h3><p>Des outils, de la visibilité et une équipe pour accompagner votre croissance.</p></div></div><div class="bp-trust__item"><i data-lucide="lock-keyhole"></i>Plateforme sécurisée</div><div class="bp-trust__item"><i data-lucide="headphones"></i>Support dédié</div><div class="bp-trust__item"><i data-lucide="chart-no-axes-combined"></i>Croissance suivie</div><div class="bp-trust__item"><i data-lucide="users"></i>Partenaire durable</div></section>
    </div>
</main>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded',()=>window.lucide?.createIcons());</script>
@endpush
