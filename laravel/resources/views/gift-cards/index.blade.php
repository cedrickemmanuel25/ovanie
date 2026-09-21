@extends('layouts.guest')

@section('title', 'Cartes OVANIE — Bons d’achat, cartes cadeau et cartes virtuelles')
@section('meta_description', 'Choisissez entre Bon d’Achat, Carte Cadeau et Carte Virtuelle OVANIE.')

@push('styles')
<style>
    :root{--orange:var(--ov-orange,#ff6a00);--orange-2:#ff7b21;--navy:var(--ov-night,#020b1c)}
    .ov-page .ov-container,.gift-pay-page .ov-container{width:min(100% - 48px,1460px);margin:0 auto}
    .ov-breadcrumb{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:22px 0 12px;color:#64748b;font-size:13px}
    .ov-breadcrumb a{color:#64748b;text-decoration:none}.ov-breadcrumb a:hover{color:var(--orange)}.ov-breadcrumb strong{color:var(--orange)}
    .landing-hero{padding:70px 0 62px;background:radial-gradient(circle at 8% 8%,rgba(255,90,0,.12),transparent 26%),linear-gradient(180deg,#fffaf7 0%,#fff 75%)}
    .landing-copy{max-width:870px;margin:0 auto;text-align:center}
    .landing-kicker{display:inline-flex;align-items:center;gap:8px;border:1px solid #ffd7c1;background:#fff;color:var(--orange);padding:9px 16px;border-radius:999px;font-size:12px;font-weight:900;letter-spacing:.7px;text-transform:uppercase}
    .landing-copy h1{margin:24px 0 16px;font-size:clamp(42px,5vw,72px);line-height:1.02;letter-spacing:-2.2px;color:#07152d}
    .landing-copy h1 span{color:var(--orange)}
    .landing-copy p{margin:0 auto;color:#667085;font-size:19px;line-height:1.75;max-width:820px}
    .gift-help-links{margin:25px auto 0;display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
    .gift-help-link{min-height:45px;padding:0 17px;display:inline-flex;align-items:center;gap:9px;border:1px solid #dbe4ef;border-radius:10px;background:#fff;color:#0a244d;text-decoration:none;font-size:12px;font-weight:850;box-shadow:0 7px 18px rgba(6,26,58,.055);transition:.18s ease}
    .gift-help-link svg{width:19px;height:19px;fill:none;stroke:#ff5d00;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.gift-help-link:hover{transform:translateY(-2px);border-color:#ffb98f}.gift-help-link.is-primary{border-color:#ff5d00;background:#ff5d00;color:#fff}.gift-help-link.is-primary svg{stroke:#fff}
    .choice-grid{margin-top:48px;display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
    .choice-card{position:relative;background:#fff;border:1px solid #e7ebf1;border-radius:22px;padding:34px 30px 30px;text-align:left;text-decoration:none;box-shadow:0 16px 42px rgba(6,26,58,.07);transition:.25s ease;overflow:hidden;min-height:300px;display:flex;flex-direction:column}
    .choice-card::after{content:"";position:absolute;right:-34px;bottom:-48px;width:150px;height:150px;border-radius:50%;background:rgba(255,90,0,.055)}
    .choice-card:hover{transform:translateY(-6px);border-color:#ffc9aa;box-shadow:0 22px 52px rgba(6,26,58,.12)}
    .choice-icon{width:62px;height:62px;border-radius:17px;background:linear-gradient(145deg,var(--orange),var(--orange-2));color:#fff;display:grid;place-items:center;box-shadow:0 12px 25px rgba(255,90,0,.22)}
    .choice-icon svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:1.8}
    .choice-card h2{font-size:28px;margin:24px 0 10px;color:#08162f}
    .choice-card p{margin:0;color:#667085;line-height:1.65;font-size:16px;max-width:320px}
    .choice-bottom{margin-top:auto;padding-top:28px;display:flex;justify-content:space-between;align-items:center;gap:14px;color:var(--orange);font-weight:900;font-size:14px}
    .choice-count{background:#fff6f1;border:1px solid #ffd7c1;color:#d74700;border-radius:999px;padding:8px 11px;font-size:12px}
    .landing-trust{margin-top:34px;display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e6ebf2;border-radius:18px;background:#fff;overflow:hidden;box-shadow:0 12px 32px rgba(6,26,58,.05)}
    .trust-item{padding:22px;display:flex;gap:12px;align-items:center;border-right:1px solid #e6ebf2}
    .trust-item:last-child{border-right:0}.trust-icon{width:42px;height:42px;border-radius:12px;background:#eef5ff;color:#0c63d8;display:grid;place-items:center;flex:none}.trust-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8}.trust-item strong{display:block;font-size:14px}.trust-item small{display:block;color:#7a8495;margin-top:3px;line-height:1.4}
    @media(max-width:960px){.choice-grid{grid-template-columns:1fr}.choice-card{min-height:auto}.landing-trust{grid-template-columns:repeat(2,1fr)}.trust-item:nth-child(2){border-right:0}.trust-item:nth-child(-n+2){border-bottom:1px solid #e6ebf2}}
    @media(max-width:600px){.landing-hero{padding:46px 0}.landing-copy h1{letter-spacing:-1.2px}.landing-copy p{font-size:16px}.landing-trust{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:10px;scroll-snap-type:x mandatory;padding-bottom:6px}.trust-item{flex:0 0 78%;scroll-snap-align:start;border-right:0!important}}
</style>
@endpush

@section('content')
<section class="ov-page">
    <section class="landing-hero">
        <div class="ov-container">
            <div class="landing-copy">
                <span class="landing-kicker">Univers Cartes OVANIE</span>
                <h1>Choisissez votre solution <span>OVANIE</span></h1>
                <p>Commencez par choisir le type de carte dont vous avez besoin. Une nouvelle page s’ouvrira ensuite avec uniquement les cartes de cette catégorie.</p>
                <nav class="gift-help-links" aria-label="Informations sur les cartes OVANIE">
                    <a class="gift-help-link is-primary" href="{{ route('gift-cards.how-it-works') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M10 9a2.3 2.3 0 1 1 3.7 1.8c-1 .7-1.7 1.2-1.7 2.7M12 17h.01"></path></svg>Comment ça marche ?</a>
                    <a class="gift-help-link" href="{{ route('gift-cards.terms') }}"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"></path><path d="M15 3v4h4M9 11h6M9 15h6"></path></svg>Conditions d’utilisation</a>
                </nav>
            </div>

            <div class="choice-grid">
                @foreach($categories as $item)
                    <a class="choice-card" href="{{ route('gift-cards.category', $item['key']) }}">
                        <span class="choice-icon" aria-hidden="true">
                            @if($item['icon'] === 'ticket')
                                <svg viewBox="0 0 24 24"><path d="M3 7.5A2.5 2.5 0 0 0 5.5 10 2.5 2.5 0 0 0 3 12.5V17a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4.5A2.5 2.5 0 0 0 18.5 10 2.5 2.5 0 0 0 21 7.5V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2.5Z"></path><path d="M12 6v8"></path></svg>
                            @elseif($item['icon'] === 'gift')
                                <svg viewBox="0 0 24 24"><path d="M3 9h18v12H3z"></path><path d="M12 9v12M2 5h20v4H2z"></path><path d="M12 5H7.5a2.5 2.5 0 1 1 2.2-3.7L12 5Zm0 0h4.5a2.5 2.5 0 1 0-2.2-3.7L12 5Z"></path></svg>
                            @else
                                <svg viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"></rect><path d="M2.5 9.5h19M6 15h4"></path></svg>
                            @endif
                        </span>
                        <h2>{{ $item['title'] }}</h2>
                        <p>{{ $item['description'] }}</p>
                        <div class="choice-bottom">
                            <span>Découvrir les cartes →</span>
                            <span class="choice-count">{{ $item['count'] }} {{ $item['count'] > 1 ? 'cartes' : 'carte' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="landing-trust">
                <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"></path><path d="m9 12 2 2 4-4"></path></svg></span><div><strong>Paiement sécurisé</strong><small>Transactions protégées OVANIE</small></div></div>
                <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path><path d="M7 8h10M7 12h6M7 16h8"></path></svg></span><div><strong>Code unique</strong><small>Généré après paiement confirmé</small></div></div>
                <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M4 13a8 8 0 0 1 16 0"></path><path d="M4 13v5h3v-5H4Zm13 0v5h3v-5h-3ZM9 20h6"></path></svg></span><div><strong>Assistance 7j/7</strong><small>Avant et après votre achat</small></div></div>
                <div class="trust-item"><span class="trust-icon"><svg viewBox="0 0 24 24"><path d="M3 6h18v12H3z"></path><path d="M7 10h10M7 14h6"></path></svg></span><div><strong>Solde consultable</strong><small>Depuis votre espace client</small></div></div>
            </div>
        </div>
    </section>
</section>
@endsection
