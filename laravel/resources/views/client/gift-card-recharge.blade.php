@extends('layouts.client')
@section('title', 'Recharger ma carte OVANIE')
@section('content')
<section class="cs-page-head"><div><h1>Recharger {{ $giftCard->product?->name }}</h1><p>Solde actuel : {{ number_format($giftCard->availableBalance(),0,',',' ') }} FCFA</p></div><a class="cs-btn outline" href="{{ route('client.gift-cards.show',$giftCard) }}">Retour</a></section>
<section class="cs-card" style="max-width:650px"><form method="POST" action="{{ route('client.gift-cards.recharge',$giftCard) }}">@csrf<label style="display:block;font-weight:800;margin-bottom:8px">Montant de recharge</label><input type="number" name="amount" min="5000" step="1000" value="{{ old('amount',50000) }}" style="width:100%;padding:13px;border:1px solid #d7dbe0;border-radius:8px;font-size:18px"><p>Minimum : 5 000 FCFA. @if($giftCard->product?->max_total_recharge) Plafond cumulé : {{ number_format((float)$giftCard->product->max_total_recharge,0,',',' ') }} FCFA. @else Recharges cumulées illimitées. @endif</p><button class="cs-btn" type="submit">Continuer vers le paiement</button></form></section>
@endsection
