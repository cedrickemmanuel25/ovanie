@extends('layouts.client')
@section('title', 'Tableau de bord')
@section('content')
@inject('clientOrderStatus', 'App\Services\ClientOrderStatusService')
<section class="cs-page-head">
    <div><h1>Tableau de bord</h1><p>Bienvenue {{ $client->first_name ?: $client->name }}, voici un aperçu de votre activité.</p></div>
</section>

<div class="cs-kpis">
    @foreach([
        ['value'=>$activeOrdersCount,'label'=>'Commandes en cours','route'=>route('client.orders',['status'=>'pending']),'tone'=>'green'],
        ['value'=>$deliveredOrdersCount,'label'=>'Commandes livrées','route'=>route('client.orders',['status'=>'completed']),'tone'=>'blue'],
        ['value'=>$favoritesCount,'label'=>'Produits favoris','route'=>route('client.favorites'),'tone'=>'rose'],
        ['value'=>number_format($loyaltyPoints,0,',',' ').' pts','label'=>'Points de fidélité','route'=>route('client.vouchers'),'tone'=>'gold'],
    ] as $kpi)
    <article class="cs-kpi {{ $kpi['tone'] }}"><span class="cs-kpi-icon">●</span><div><strong>{{ $kpi['value'] }}</strong><p>{{ $kpi['label'] }}</p><a href="{{ $kpi['route'] }}">Voir →</a></div></article>
    @endforeach
</div>

<section class="cs-card">
    <div class="cs-section-head"><div><h2>Mes dernières commandes</h2><p>Les dernières commandes enregistrées sur votre compte.</p></div><a href="{{ route('client.orders') }}">Voir toutes les commandes →</a></div>
    <div class="cs-table-wrap">
        <table class="cs-table">
            <thead><tr><th>N° commande</th><th>Date</th><th>Produits</th><th>Total</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($recentOrders as $order)
                <tr>
                    <td><a class="cs-link" href="{{ route('client.orders.show',$order) }}">#{{ $order->order_number }}</a></td>
                    <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                    <td><div class="cs-product-stack">@foreach($order->items->take(3) as $item)<img src="{{ $item->product?->main_image_url }}" alt="">@endforeach<span>{{ $order->items->count() }} article(s)</span></div></td>
                    <td><strong>{{ number_format($order->total_amount,0,',',' ') }} FCFA</strong></td>
                    <td>@include('client.partials.status',['status'=>$order->status])</td>
                    <td>@if($clientOrderStatus->canTrack($order))<a class="cs-btn small outline" href="{{ route('client.orders.tracking',$order) }}">Suivre</a>@else<a class="cs-btn small outline" href="{{ route('client.orders.show',$order) }}">Détails</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="cs-empty"><h3>Aucune commande</h3><p>Vos prochaines commandes apparaîtront ici.</p><a class="cs-btn" href="{{ route('catalog.index') }}">Découvrir le catalogue</a></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="cs-dashboard-bottom">
    <section class="cs-card">
        <div class="cs-section-head"><h2>Vos favoris</h2><a href="{{ route('client.favorites') }}">Voir tous les favoris →</a></div>
        <div class="cs-mini-products">
            @forelse($favoriteProducts as $product)
                <article><img src="{{ $product->main_image_url }}" alt=""><h3>{{ $product->name }}</h3><strong>{{ number_format($product->final_price,0,',',' ') }} FCFA</strong><small>Livraison disponible via OVANIE</small></article>
            @empty
                <div class="cs-empty"><p>Aucun favori enregistré.</p></div>
            @endforelse
        </div>
    </section>
    <aside>
        <section class="cs-card">
            <div class="cs-section-head"><h2>Adresse principale</h2><a href="{{ route('client.addresses') }}">Modifier →</a></div>
            @if($defaultAddress)
                <strong>{{ $defaultAddress->recipient_name ?: $client->name }}</strong>
                <p>{{ $defaultAddress->address }}<br>@if($defaultAddress->quartier){{ $defaultAddress->quartier }}<br>@endif{{ $defaultAddress->commune }}, {{ $defaultAddress->city }}<br>{{ $defaultAddress->phone }}</p>
            @else
                <div class="cs-empty"><p>Aucune adresse enregistrée.</p><a href="{{ route('client.addresses') }}">Ajouter une adresse</a></div>
            @endif
        </section>
        <section class="cs-card cs-points"><h2>Points fidélité</h2><strong>{{ number_format($loyaltyPoints,0,',',' ') }} pts</strong><p>Utilisez vos points disponibles au checkout lorsque la commande est éligible.</p><a class="cs-btn small outline" href="{{ route('client.vouchers') }}">Voir mes avantages</a></section>
    </aside>
</div>
@endsection
