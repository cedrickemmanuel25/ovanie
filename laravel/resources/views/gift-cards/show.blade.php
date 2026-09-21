@extends('layouts.guest')

@section('title', $presentation['title'].' - OVANIE')
@section('meta_description', $presentation['summary'])

@push('styles')
<style>
    .gc-detail{padding:0 0 56px;background:linear-gradient(#fff,#f8fafc);color:#071a3d}
    .gc-wrap{width:min(100% - 48px,1460px);margin:0 auto}
    .gc-crumb{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:22px 0 14px;color:#718096;font-size:12px}
    .gc-crumb a{color:inherit;text-decoration:none}.gc-crumb strong{color:#183b70}
    .gc-guide-links{margin:0 0 14px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.gc-guide-link{min-height:40px;padding:0 14px;display:inline-flex;align-items:center;gap:8px;border:1px solid #dbe4ef;border-radius:9px;background:#fff;color:#0a244d;text-decoration:none;font-size:11px;font-weight:850;box-shadow:0 5px 15px rgba(5,36,83,.045)}.gc-guide-link:hover{border-color:#ffad7c;color:#f05a00}.gc-guide-link svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.gc-guide-link.is-primary{border-color:#0a3774;background:#0a3774;color:#fff}
    .gc-hero{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(390px,.85fr);gap:18px;align-items:start}
    .gc-card,.gc-buy,.gc-panel,.gc-trust{border:1px solid #dfe7f0;border-radius:15px;background:#fff;box-shadow:0 8px 26px rgba(5,36,83,.055)}
    .gc-showcase{display:grid;grid-template-columns:minmax(330px,1fr) minmax(330px,.9fr);gap:22px;padding:22px}
    .gc-media{min-height:350px;display:grid;place-items:center;overflow:hidden;border-radius:13px;background:radial-gradient(circle at 50% 45%,#fff,#f1f5fb)}
    .gc-media img{width:100%;height:100%;max-height:380px;object-fit:contain;filter:drop-shadow(0 18px 20px rgba(5,36,83,.12))}
    .gc-copy{padding:14px 4px;display:flex;flex-direction:column;justify-content:center}
    .gc-pill{align-self:flex-start;padding:6px 10px;border-radius:6px;background:#e8f8ee;color:#108943;font-size:10px;font-weight:850}
    .gc-copy h1{margin:18px 0 10px;font-size:clamp(28px,3vw,42px);line-height:1.08;letter-spacing:-.8px}
    .gc-copy>p{margin:0 0 17px;color:#405372;font-size:14px;line-height:1.55}
    .gc-benefits{display:grid;gap:12px;margin:0;padding:0;list-style:none}.gc-benefits li{display:flex;gap:9px;align-items:flex-start;font-size:12px}.gc-check{width:18px;height:18px;flex:0 0 auto;display:grid;place-items:center;border-radius:50%;background:#0b9e4b;color:#fff;font-size:11px;font-weight:900}
    .gc-rating{margin-top:22px;padding-top:17px;border-top:1px solid #e8edf3;color:#ff6a00;font-weight:900}.gc-rating span{margin-left:9px;color:#233553;font-size:12px}
    .gc-facts{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e3e9f1;border-radius:11px;overflow:hidden}
    .gc-fact{min-height:62px;padding:12px 14px;display:flex;gap:9px;align-items:center;border-right:1px solid #e3e9f1}.gc-fact:last-child{border:0}.gc-fact b{display:block;font-size:11px}.gc-fact small{display:block;margin-top:3px;color:#748198;font-size:9px}
    .gc-icon{width:31px;height:31px;display:grid;place-items:center;border-radius:9px;background:#edf4ff;color:#076bf0}.gc-icon svg{width:18px;height:18px}
    .gc-svg{fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .gc-buy{padding:22px;align-self:start}.gc-buy h2{margin:0 0 4px;font-size:20px}.gc-buy>p{margin:0 0 16px;color:#65748b;font-size:11px}
    .gc-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.gc-amount{position:relative;min-height:63px;padding:10px 7px;display:grid;place-items:center;border:1px solid #dce4ee;border-radius:9px;color:#0b2148;text-decoration:none;text-align:center;font-weight:900;font-size:14px}.gc-amount small{display:block;margin-top:2px;color:#64748b;font-size:9px}.gc-amount:hover,.gc-amount.is-active{border-color:#076bf0;box-shadow:0 0 0 1px #076bf0;background:#f6f9ff}.gc-amount.is-active::after{content:'✓';position:absolute;right:-5px;top:-6px;width:20px;height:20px;display:grid;place-items:center;border-radius:50%;background:#076bf0;color:#fff;font-size:11px}
    .gc-summary{margin-top:18px;padding-top:16px;border-top:1px solid #e4eaf2}.gc-summary h3{margin:0 0 9px;font-size:13px}.gc-line{display:flex;justify-content:space-between;gap:15px;padding:7px 0;font-size:11px}.gc-line.total{margin-top:5px;padding-top:13px;border-top:1px solid #e6ebf2;font-weight:900}.gc-line.total strong{color:#076bf0;font-size:19px}
    .gc-buy-btn{width:100%;min-height:50px;margin-top:12px;display:flex;align-items:center;justify-content:center;gap:9px;border-radius:8px;background:linear-gradient(90deg,#ff5700,#ff7200);color:#fff;text-decoration:none;font-size:14px;font-weight:900;box-shadow:0 9px 19px rgba(255,87,0,.2)}.gc-buy-btn svg{width:20px;height:20px}
    .gc-secure{text-align:center;margin:11px 0 0;color:#53647e;font-size:10px}
    .gc-grid{margin-top:18px;display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.gc-panel{padding:20px}.gc-panel h2{margin:0 0 17px;font-size:17px}
    .gc-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}.gc-step{text-align:center;position:relative}.gc-step:not(:last-child)::after{content:'→';position:absolute;right:-13px;top:27px;color:#b6c9e7;font-size:22px}.gc-step i{width:55px;height:55px;margin:0 auto 9px;display:grid;place-items:center;border-radius:50%;background:#eaf2ff;color:#076bf0;font-style:normal}.gc-step i svg{width:27px;height:27px}.gc-step b{display:block;font-size:11px}.gc-step p{margin:5px 0 0;color:#66758c;font-size:9px;line-height:1.45}
    .gc-rules{display:grid;gap:13px}.gc-rule{display:grid;grid-template-columns:37px 1fr;gap:10px;align-items:start}.gc-rule i{width:35px;height:35px;display:grid;place-items:center;border-radius:50%;background:#edf4ff;color:#076bf0;font-style:normal}.gc-rule i svg{width:19px;height:19px}.gc-rule b{display:block;font-size:11px}.gc-rule p{margin:3px 0 0;color:#637189;font-size:9px;line-height:1.45}
    .gc-faq{margin-top:18px}.gc-faq details{border-bottom:1px solid #e5ebf2;padding:10px 0}.gc-faq summary{cursor:pointer;font-size:11px;font-weight:750}.gc-faq p{color:#65748b;font-size:10px;line-height:1.55}
    .gc-trust{margin-top:18px;display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden}.gc-trust div{padding:14px 18px;border-right:1px solid #e3e9f1}.gc-trust div:last-child{border:0}.gc-trust b{display:block;font-size:11px}.gc-trust small{color:#6d7b90;font-size:9px}
    @media(max-width:1100px){.gc-hero,.gc-showcase,.gc-grid{grid-template-columns:1fr}.gc-buy{order:2}.gc-showcase{grid-template-columns:1fr 1fr}.gc-steps{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:700px){.gc-wrap{width:min(100% - 28px,650px)}.gc-showcase{grid-template-columns:1fr;padding:14px}.gc-media{min-height:260px}.gc-amounts{grid-template-columns:repeat(2,1fr)}.gc-facts,.gc-trust{grid-template-columns:repeat(2,1fr)}.gc-fact:nth-child(2){border-right:0}.gc-fact:nth-child(-n+2){border-bottom:1px solid #e3e9f1}.gc-trust div:nth-child(2){border-right:0}.gc-trust div:nth-child(-n+2){border-bottom:1px solid #e3e9f1}}
</style>
@endpush

@section('content')
<main class="gc-detail">
    <svg width="0" height="0" aria-hidden="true" style="position:absolute">
        <symbol id="gc-cart" viewBox="0 0 24 24"><path d="M3 4h2l2.2 10h10.6L20 7H6"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></symbol>
        <symbol id="gc-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></symbol>
        <symbol id="gc-bag" viewBox="0 0 24 24"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></symbol>
        <symbol id="gc-card" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 15h5"/></symbol>
        <symbol id="gc-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="gc-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.7 3 8.4 7 10 4-1.6 7-5.3 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></symbol>
        <symbol id="gc-refresh" viewBox="0 0 24 24"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M18 12a7 7 0 0 0-12-4l-2 4M6 12a7 7 0 0 0 12 4l2-4"/></symbol>
        <symbol id="gc-layers" viewBox="0 0 24 24"><path d="m12 3 8 4-8 4-8-4 8-4Z"/><path d="m4 12 8 4 8-4M4 17l8 4 8-4"/></symbol>
    </svg>
    <div class="gc-wrap">
        <nav class="gc-crumb" aria-label="Fil d’Ariane">
            <a href="{{ route('home') }}">Accueil</a><span>›</span>
            <a href="{{ route('gift-cards.index') }}">Cartes OVANIE</a><span>›</span>
            <a href="{{ route('gift-cards.category', $presentation['group']) }}">{{ $presentation['type'] }}</a><span>›</span>
            <strong>{{ $presentation['title'] }}</strong>
        </nav>
        <nav class="gc-guide-links" aria-label="Aide sur les cartes OVANIE">
            <a class="gc-guide-link is-primary" href="{{ route('gift-cards.how-it-works') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M10 9a2.3 2.3 0 1 1 3.7 1.8c-1 .7-1.7 1.2-1.7 2.7M12 17h.01"></path></svg>Comment ça marche ?</a>
            <a class="gc-guide-link" href="{{ route('gift-cards.terms') }}"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"></path><path d="M15 3v4h4M9 11h6M9 15h6"></path></svg>Conditions d’utilisation</a>
        </nav>

        <section class="gc-hero">
            <article class="gc-card gc-showcase">
                <div class="gc-media"><img src="{{ asset($giftCardProduct->image_path) }}" alt="{{ $presentation['title'] }}"></div>
                <div class="gc-copy">
                    <span class="gc-pill">✓ Idéal pour vos achats OVANIE</span>
                    <h1>{{ $presentation['title'] }}</h1>
                    <p>{{ $presentation['summary'] }}</p>
                    <ul class="gc-benefits">
                        <li><span class="gc-check">✓</span>Valable sur les produits disponibles sur OVANIE</li>
                        <li><span class="gc-check">✓</span>Utilisable en une ou plusieurs fois selon le solde</li>
                        <li><span class="gc-check">✓</span>Code sécurisé généré après confirmation du paiement</li>
                        <li><span class="gc-check">✓</span>Solde restant conservé jusqu’à expiration</li>
                    </ul>
                    <div class="gc-rating">★★★★★ <span>Carte officielle OVANIE</span></div>
                </div>
                <div class="gc-facts">
                    <div class="gc-fact"><span class="gc-icon"><svg class="gc-svg"><use href="#gc-shield"/></svg></span><span><b>Utilisable sur OVANIE</b><small>Catalogue public éligible</small></span></div>
                    <div class="gc-fact"><span class="gc-icon"><svg class="gc-svg"><use href="#gc-layers"/></svg></span><span><b>Plusieurs montants</b><small>{{ $amountChoices->count() }} choix disponibles</small></span></div>
                    <div class="gc-fact"><span class="gc-icon"><svg class="gc-svg"><use href="#gc-mail"/></svg></span><span><b>Code envoyé rapidement</b><small>Après paiement confirmé</small></span></div>
                    <div class="gc-fact"><span class="gc-icon"><svg class="gc-svg"><use href="#gc-refresh"/></svg></span><span><b>Solde conservé</b><small>Jusqu’à la date d’expiration</small></span></div>
                </div>
            </article>

            <aside class="gc-buy">
                <h2>Choisissez le montant</h2>
                <p>Sélectionnez la valeur de votre {{ mb_strtolower($presentation['type']) }}.</p>
                <div class="gc-amounts">
                    @foreach($amountChoices as $choice)
                        @php $choiceProduct = $choice['product']; @endphp
                        <a class="gc-amount {{ $choiceProduct->is($giftCardProduct) ? 'is-active' : '' }}" href="{{ route('gift-cards.show', $choiceProduct) }}">
                            <span>{{ number_format((float)$choiceProduct->activation_price,0,',',' ') }}<small>FCFA</small></span>
                        </a>
                    @endforeach
                </div>
                <div class="gc-summary">
                    <h3>Résumé</h3>
                    <div class="gc-line"><span>Montant de la carte</span><strong>{{ number_format((float)$giftCardProduct->activation_price,0,',',' ') }} FCFA</strong></div>
                    <div class="gc-line"><span>Frais d’activation</span><strong>Inclus</strong></div>
                    <div class="gc-line total"><span>Total à payer</span><strong>{{ number_format((float)$giftCardProduct->activation_price,0,',',' ') }} FCFA</strong></div>
                </div>
                <a class="gc-buy-btn" href="{{ route('gift-cards.payment.show', $giftCardProduct) }}"><svg class="gc-svg"><use href="#gc-cart"/></svg>{{ $giftCardProduct->is_rechargeable ? 'Activer cette carte' : 'Ajouter au panier' }}</a>
                <p class="gc-secure">Transaction sécurisée par OVANIE</p>
            </aside>
        </section>

        <section class="gc-grid">
            <article class="gc-panel">
                <h2>Comment utiliser votre carte ?</h2>
                <div class="gc-steps">
                    <div class="gc-step"><i><svg class="gc-svg"><use href="#gc-cart"/></svg></i><b>Choisissez la carte</b><p>Sélectionnez le montant adapté à votre besoin.</p></div>
                    <div class="gc-step"><i><svg class="gc-svg"><use href="#gc-mail"/></svg></i><b>Recevez votre code</b><p>Le code et le PIN sont générés après paiement.</p></div>
                    <div class="gc-step"><i><svg class="gc-svg"><use href="#gc-bag"/></svg></i><b>Faites vos achats</b><p>Ajoutez les produits souhaités au panier.</p></div>
                    <div class="gc-step"><i><svg class="gc-svg"><use href="#gc-card"/></svg></i><b>Utilisez le solde</b><p>Appliquez le code pendant le checkout.</p></div>
                </div>
                <div class="gc-faq">
                    <details><summary>La carte est-elle valable sur tous les produits ?</summary><p>Elle s’applique aux produits éligibles disponibles sur la marketplace OVANIE.</p></details>
                    <details><summary>Puis-je utiliser le solde en plusieurs fois ?</summary><p>Oui, le solde non consommé reste disponible jusqu’à expiration.</p></details>
                    <details><summary>Comment recevoir le code ?</summary><p>Il est généré après confirmation du paiement et devient disponible dans votre espace client.</p></details>
                </div>
            </article>
            <aside class="gc-panel">
                <h2>Informations importantes</h2>
                <div class="gc-rules">
                    <div class="gc-rule"><i><svg class="gc-svg"><use href="#gc-clock"/></svg></i><div><b>Validité</b><p>{{ $giftCardProduct->validity_days ? $giftCardProduct->validity_days.' jours à compter de l’activation.' : $giftCardProduct->validity_months.' mois à compter de l’activation.' }}</p></div></div>
                    <div class="gc-rule"><i><svg class="gc-svg"><use href="#gc-card"/></svg></i><div><b>Utilisation</b><p>La carte peut être utilisée en une ou plusieurs fois jusqu’à épuisement du solde.</p></div></div>
                    <div class="gc-rule"><i><svg class="gc-svg"><use href="#gc-layers"/></svg></i><div><b>Solde restant</b><p>Le montant non utilisé est conservé et réutilisable pendant sa validité.</p></div></div>
                    <div class="gc-rule"><i><svg class="gc-svg"><use href="#gc-shield"/></svg></i><div><b>Sécurité</b><p>Code unique et sécurisé, accessible uniquement après paiement confirmé.</p></div></div>
                </div>
            </aside>
        </section>

        <section class="gc-trust">
            <div><b>Paiement sécurisé</b><small>Transactions protégées</small></div>
            <div><b>Disponibilité rapide</b><small>Après confirmation</small></div>
            <div><b>Support client</b><small>Assistance OVANIE</small></div>
            <div><b>Satisfaction garantie</b><small>Parcours fiable et sécurisé</small></div>
        </section>
    </div>
</main>
@endsection
