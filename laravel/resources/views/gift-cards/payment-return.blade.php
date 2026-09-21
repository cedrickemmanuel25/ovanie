@extends('layouts.guest')

@section('title', 'Paiement carte OVANIE')
@section('meta_description', 'Confirmation du paiement de votre carte OVANIE.')

@push('styles')
<style>
    .gift-result-page{padding:48px 0 72px;background:#f4f6f9;min-height:64vh}
    .gift-result-wrap{width:min(100% - 48px,760px);margin:0 auto}
    .gift-result-card{background:#fff;border:1px solid #e5eaf1;border-radius:22px;padding:34px;box-shadow:0 18px 48px rgba(2,11,28,.09);text-align:center}
    .gift-result-card h1{margin:0 0 14px;font-size:34px;line-height:1.15;color:#0b1730}
    .gift-result-card h1.ok{color:#087f3f}.gift-result-card h1.bad{color:#b42318}.gift-result-card h1.pending{color:#a15c00}
    .gift-result-card p{color:#64748b;line-height:1.7}
    .gift-result-code{font:900 20px ui-monospace,SFMono-Regular,Menlo,monospace;background:#f1f5f9;border:1px solid #e2e8f0;padding:13px 16px;border-radius:11px;color:#0f172a;word-break:break-all}
    .gift-result-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:24px}
    .gift-result-btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 20px;border-radius:11px;background:#ff6a00;color:#fff;text-decoration:none;font-weight:900}
    .gift-result-btn.secondary{background:#07152d}
    @media(max-width:600px){.gift-result-page{padding:28px 0 50px}.gift-result-wrap{width:min(100% - 28px,760px)}.gift-result-card{padding:24px 18px}.gift-result-actions{display:grid}.gift-result-btn{width:100%}}
</style>
@endpush

@section('content')
<section class="gift-result-page">
    <div class="gift-result-wrap">
        <div class="gift-result-card">
            <h1 class="{{ $state === 'completed' ? 'ok' : ($state === 'cancelled' || $state === 'invalid' ? 'bad' : 'pending') }}">
                {{ $state === 'completed' ? 'Paiement confirmé' : ($state === 'pending' ? 'Paiement en vérification' : 'Paiement non confirmé') }}
            </h1>

            <p>{{ $message }}</p>

            @if($giftCard && $state === 'completed')
                <h2>{{ $giftCard->product?->name }}</h2>
                <p>Solde : <strong>{{ number_format((float) $giftCard->availableBalance(), 0, ',', ' ') }} FCFA</strong></p>

                @if($canReveal ?? false)
                    <p class="gift-result-code">{{ $giftCard->code }}</p>
                    <p>PIN : <strong>{{ $giftCard->plainPin() ?: '••••' }}</strong></p>
                @else
                    <p>Connectez-vous au compte acheteur pour afficher le code et le PIN de la carte.</p>
                @endif
            @endif

            <div class="gift-result-actions">
                <a class="gift-result-btn" href="{{ route('gift-cards.index') }}">Cartes OVANIE</a>
                @auth
                    <a class="gift-result-btn secondary" href="{{ route('client.vouchers') }}">Mes cartes</a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
