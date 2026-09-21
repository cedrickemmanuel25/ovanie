@extends('layouts.guest')
@section('title', 'Comment ça marche ? - Cartes OVANIE')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gift-card-guides.css') }}?v={{ filemtime(public_path('css/gift-card-guides.css')) }}">@endpush
@section('content')
<main class="gpg"><div class="gpg-wrap">
    <nav class="gpg-crumb"><a href="{{ route('home') }}">Accueil</a><span>›</span><a href="{{ route('gift-cards.index') }}">Cartes OVANIE</a><span>›</span><strong>Comment ça marche</strong></nav>
    <header class="gpg-hero"><span class="gpg-pill">GUIDE CARTES OVANIE</span><h1>Comment ça marche ?</h1><p>Les bons d’achat, cartes cadeaux et cartes virtuelles OVANIE sont simples à acheter et faciles à utiliser sur la marketplace.</p></header>
    <h2 class="gpg-title">Les 5 étapes pour utiliser votre carte <span style="color:#ff5d00">OVANIE</span></h2>
    @php $steps=[['Acheter','Choisissez une carte et un montant adapté à votre besoin.','cart'],['Recevoir son code','Après paiement, le code et le PIN apparaissent dans votre espace client.','mail'],['Ajouter ses produits','Parcourez le catalogue et ajoutez les produits souhaités au panier.','bag'],['Utiliser le code','Saisissez le code et le PIN pendant le checkout.','card'],['Conserver le solde','Le montant restant demeure disponible jusqu’à expiration.','refresh']]; @endphp
    <section class="gpg-steps">@foreach($steps as $index=>$step)<article class="gpg-step"><span class="gpg-number">{{ $index+1 }}</span><i class="gpg-icon"><svg><use href="#guide-{{ $step[2] }}"/></svg></i><h3>{{ $step[0] }}</h3><p>{{ $step[1] }}</p></article>@endforeach</section>
    <h2 class="gpg-title">Ce qu’il faut savoir</h2>
    @php $infos=[['shield','Paiement sécurisé OVANIE','Toutes vos transactions sont protégées.'],['screen','Utilisable sur OVANIE','Valable sur les produits éligibles disponibles.'],['wallet','Paiement mixte possible','Complétez le montant avec un autre moyen de paiement.'],['key','Code personnel','Chaque carte dispose d’un code et d’un PIN confidentiel.'],['clock','Solde restant conservé','Le solde non utilisé reste disponible jusqu’à expiration.'],['headset','Assistance OVANIE','Notre équipe vous accompagne en cas de besoin.']]; @endphp
    <section class="gpg-info">@foreach($infos as $info)<article class="gpg-info-card"><i><svg class="gpg-svg"><use href="#guide-{{ $info[0] }}"/></svg></i><div><b>{{ $info[1] }}</b><p>{{ $info[2] }}</p></div></article>@endforeach</section>
    <section class="gpg-cta"><div><h2>Prêt à profiter de la liberté OVANIE ?</h2><p>Choisissez la carte adaptée à vos achats ou à votre cadeau.</p></div><div class="gpg-actions"><a class="gpg-btn" href="{{ route('gift-cards.index') }}">Voir les cartes OVANIE</a><a class="gpg-btn alt" href="{{ route('gift-cards.terms') }}">Lire les conditions</a></div></section>
</div></main>
@include('gift-cards.partials.guide-icons')
@endsection
