@extends('layouts.client')
@section('title', 'Mes favoris')
@section('content')
<section class="cs-page-head"><div><h1>Mes favoris</h1><p>{{ $counts['all'] }} produits sauvegardés</p></div></section>
<form class="cs-toolbar" method="GET"><input name="search" value="{{ request('search') }}" placeholder="Rechercher dans mes favoris..."><button class="cs-btn">Rechercher</button><a class="cs-btn outline" href="{{ route('client.favorites') }}">Réinitialiser</a></form>
<nav class="cs-tabs">@foreach([''=>['Tous',$counts['all']], 'stock'=>['En stock',$counts['stock']], 'promo'=>['Promo',$counts['promo']]] as $key=>$tab)<a class="{{ request('filter','')===$key?'active':'' }}" href="{{ route('client.favorites',array_filter(['filter'=>$key,'search'=>request('search')])) }}">{{ $tab[0] }} ({{ $tab[1] }})</a>@endforeach</nav>
<div class="cs-product-grid">
    @forelse($products as $product)
    <article class="cs-product-card">
        <form method="POST" action="{{ route('client.favorites.toggle',$product) }}" class="cs-card-remove">@csrf @method('DELETE')<button title="Retirer">×</button></form>
        @if($product->promo_price && $product->promo_price < $product->price)<span class="cs-promo">PROMO -{{ round(100-(($product->promo_price/$product->price)*100)) }}%</span>@endif
        <div class="cs-product-img"><img src="{{ $product->main_image_url }}" alt="{{ $product->name }}"></div>
        <h3>{{ $product->name }}</h3>
        <p class="cs-price">{{ number_format($product->final_price,0,',',' ') }} FCFA @if($product->promo_price)<del>{{ number_format($product->price,0,',',' ') }}</del>@endif</p>
        <small>★ ★ ★ ★ ☆ ({{ $product->reviews_count ?? $product->reviews->count() }} avis)</small>
        <small>Expedie via OVANIE</small>
        @if((int) $product->stock > 0)
            <form method="POST" action="{{ route('cart.add',$product->id) }}">@csrf<button class="cs-btn outline full">Ajouter au panier</button></form>
        @else
            <a class="cs-btn outline full" href="{{ route('product.show', $product) }}">Voir le produit</a>
        @endif
    </article>
    @empty
        <div class="cs-empty wide"><h3>Aucun favori</h3><p>Ajoutez des produits à vos favoris depuis le catalogue.</p><a class="cs-btn" href="{{ route('catalog.index') }}">Voir le catalogue</a></div>
    @endforelse
</div>
<div class="cs-pagination">{{ $products->links() }}</div>
@endsection
