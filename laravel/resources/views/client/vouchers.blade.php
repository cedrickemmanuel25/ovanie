@extends('layouts.client')
@section('title', 'Cartes cadeaux & avantages OVANIE')
@push('styles')
<style>
.gc-head-actions{display:flex;gap:10px;flex-wrap:wrap}.gc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.gc-stat{background:#fff;border:1px solid #e6e8ec;border-radius:16px;padding:16px}.gc-stat span{display:block;color:#64748b;font-size:13px}.gc-stat strong{display:block;margin-top:6px;font-size:24px;color:#111827}.gc-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.gc-wallet{background:#fff;border:1px solid #e6e8ec;border-radius:18px;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,.05)}.gc-wallet img{width:100%;aspect-ratio:16/9;object-fit:cover;background:#eee}.gc-wallet-body{padding:18px}.gc-wallet-top{display:flex;justify-content:space-between;gap:12px}.gc-wallet h2{margin:0 0 8px}.gc-balance{font-size:29px;font-weight:900;color:#f36600}.gc-code{font-family:monospace;background:#f4f5f7;padding:9px;border-radius:8px;word-break:break-all}.gc-meta{color:#69707a;font-size:14px}.gc-status{padding:5px 9px;border-radius:99px;background:#eef7ef;color:#137a31;font-weight:800;font-size:12px;height:max-content}.gc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.gc-actions a{padding:9px 12px;border-radius:8px;text-decoration:none;font-weight:800;background:#15181c;color:#fff}.gc-actions a.orange{background:#f36600}.gc-sections{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;margin-top:20px}@media(max-width:900px){.gc-stats{grid-template-columns:repeat(2,1fr)}.gc-sections{grid-template-columns:1fr}}@media(max-width:760px){.gc-cards{grid-template-columns:1fr}}@media(max-width:520px){.gc-stats{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
@php
    $giftBalance = $cards->sum(fn($card) => $card->availableBalance());
@endphp
<section class="cs-page-head">
    <div>
        <h1>Cartes cadeaux & avantages OVANIE</h1>
        <p>Consultez vos cartes, leur solde, leurs mouvements et vos points de fidélité.</p>
    </div>
    <div class="gc-head-actions">
        <a class="cs-btn outline" href="{{ route('gift-cards.index') }}">Acheter une carte</a>
        <a class="cs-btn" href="{{ route('cart.index') }}">Utiliser au checkout</a>
    </div>
</section>

@if(session('success'))<div class="cs-alert success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="cs-alert error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="cs-alert error">{{ $errors->first() }}</div>@endif

<div class="gc-stats">
    <article class="gc-stat"><span>Mes cartes</span><strong>{{ number_format($cards->count(),0,',',' ') }}</strong></article>
    <article class="gc-stat"><span>Solde cartes disponible</span><strong>{{ number_format($giftBalance,0,',',' ') }} F</strong></article>
    <article class="gc-stat"><span>Points fidélité</span><strong>{{ number_format($points,0,',',' ') }} pts</strong></article>
    <article class="gc-stat"><span>Réduction fidélité disponible</span><strong>{{ number_format($availableDiscount,0,',',' ') }} F</strong></article>
</div>

<section class="cs-card">
    <div class="cs-section-head"><div><h2>Mes cartes OVANIE</h2><p>Le code et le PIN permettent l’utilisation au checkout.</p></div><a href="{{ route('gift-cards.index') }}">Voir le catalogue</a></div>
    <div class="gc-cards">
    @forelse($cards as $card)
        <article class="gc-wallet">
            @if($card->product?->image_path)<img src="{{ asset($card->product->image_path) }}" alt="{{ $card->product->name }}">@endif
            <div class="gc-wallet-body">
                <div class="gc-wallet-top">
                    <div><h2>{{ $card->product?->name ?: 'Carte OVANIE' }}</h2><div class="gc-balance">{{ number_format($card->availableBalance(),0,',',' ') }} FCFA</div></div>
                    <span class="gc-status">{{ strtoupper($card->status) }}</span>
                </div>
                <p class="gc-code">{{ $card->code }}</p>
                <p class="gc-meta">Expire le {{ $card->expires_at?->format('d/m/Y') ?: '—' }} · Solde réservé : {{ number_format((float)$card->reserved_balance,0,',',' ') }} FCFA</p>
                <div class="gc-actions">
                    <a href="{{ route('client.gift-cards.show',$card) }}">Détails & PIN</a>
                    @if($card->product?->is_rechargeable && (int)$card->owner_user_id === (int)auth()->id())
                        <a class="orange" href="{{ route('client.gift-cards.recharge.form',$card) }}">Recharger</a>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <section class="cs-card"><h2>Aucune carte</h2><p>Vous n’avez pas encore de carte cadeau OVANIE.</p><a class="cs-btn" href="{{ route('gift-cards.index') }}">Acheter une carte</a></section>
    @endforelse
    </div>
</section>

<div class="gc-sections">
    <section class="cs-card">
        <div class="cs-section-head"><h2>Historique des points</h2></div>
        @forelse($loyaltyTransactions as $transaction)
            <div class="cs-payment-row"><span class="operator">PTS</span><div><strong>{{ $transaction->description ?: ucfirst($transaction->type) }}</strong><p>{{ $transaction->reference }} · {{ $transaction->created_at?->format('d/m/Y H:i') }}</p></div><b>{{ $transaction->points >= 0 ? '+' : '' }}{{ number_format($transaction->points,0,',',' ') }} pts</b></div>
        @empty
            <div class="cs-empty"><p>Aucun mouvement de points pour le moment.</p></div>
        @endforelse
    </section>
    <section class="cs-card">
        <div class="cs-section-head"><h2>Commandes récentes</h2><a href="{{ route('client.orders') }}">Voir tout</a></div>
        @forelse($recentOrders as $order)
            <div class="cs-payment-row"><span class="operator">{{ strtoupper(substr($order->status,0,3)) }}</span><div><strong>#{{ $order->order_number }}</strong><p>{{ $order->created_at?->format('d/m/Y') }}</p></div><b>{{ number_format((float)$order->total_amount,0,',',' ') }} FCFA</b></div>
        @empty
            <div class="cs-empty"><p>Aucune commande récente.</p></div>
        @endforelse
    </section>
</div>
@endsection
