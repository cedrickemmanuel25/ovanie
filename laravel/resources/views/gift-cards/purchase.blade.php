@extends('gift-cards.layout')

@section('title', $presentation['title'].' - OVANIE')
@section('meta_description', $presentation['summary'])

@push('styles')
<style>
    .purchase-wrap{padding-bottom:54px}
    .purchase-main{display:grid;grid-template-columns:minmax(0,1.16fr) minmax(360px,.78fr);gap:28px;align-items:start}
    .card-panel,.buy-panel,.info-panel{background:#fff;border:1px solid #e5eaf0;border-radius:18px;box-shadow:0 14px 36px rgba(6,26,58,.07)}
    .card-panel{overflow:hidden}
    .card-visual{background:linear-gradient(145deg,#f7f8fa,#eef2f6);padding:20px;border-bottom:1px solid #e8edf3}
    .card-visual img{width:100%;display:block;object-fit:contain;border-radius:12px;max-height:560px}
    .card-content{padding:26px 28px 28px}
    .type-pill{display:inline-flex;align-items:center;gap:7px;color:#e65000;background:#fff3eb;border:1px solid #ffd4bc;border-radius:999px;padding:8px 12px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.4px}
    .card-content h1{margin:17px 0 9px;font-size:clamp(28px,3vw,42px);line-height:1.1;letter-spacing:-1px;color:#07152d}
    .summary{color:#667085;line-height:1.7;margin:0;max-width:760px}
    .big-price{font-size:36px;font-weight:950;color:var(--orange);margin:22px 0}
    .facts{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid #e6ebf1;border-radius:14px;overflow:hidden}
    .fact{padding:17px 18px;display:flex;gap:12px;align-items:center;min-height:86px;border-right:1px solid #e6ebf1;border-bottom:1px solid #e6ebf1}
    .fact:nth-child(2n){border-right:0}.fact:nth-last-child(-n+2){border-bottom:0}
    .fact-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;background:#fff1e8;color:var(--orange);flex:none}.fact-icon.green{background:#eaf8f0;color:#0f9d58}.fact-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.8}
    .fact small{display:block;color:#7b8797;margin-bottom:3px}.fact strong{display:block;color:#12203a;font-size:13px;line-height:1.4}
    .buy-panel{padding:28px;position:sticky;top:18px}
    .buy-panel h2{font-size:31px;margin:0 0 8px;color:#07152d}.buy-intro{color:#667085;line-height:1.7;margin:0 0 22px}
    .divider{height:1px;background:#e6ebf1;margin:22px 0}
    .label-title{font-weight:800;font-size:14px;margin-bottom:10px;display:block}
    .choice{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid #dfe5ed;border-radius:11px;overflow:hidden;margin-bottom:18px}
    .choice label{position:relative;display:flex;align-items:center;justify-content:center;gap:9px;padding:16px 14px;cursor:pointer;font-weight:800;color:#24344f;background:#fff}.choice label+label{border-left:1px solid #dfe5ed}.choice input{accent-color:var(--orange)}
    .choice label:has(input:checked){background:#fff8f4;color:#07152d;box-shadow:inset 0 0 0 1px var(--orange)}
    .personal-note{padding:15px;border-radius:11px;background:#f5f9ff;border:1px solid #dce9fb;color:#42526b;line-height:1.6;font-size:13px;margin-bottom:16px}
    .field{display:block;margin-bottom:14px}.field span{display:block;font-weight:800;font-size:13px;margin-bottom:8px}.field input,.field textarea{width:100%;border:1px solid #dce3ec;border-radius:10px;background:#fff;padding:13px 14px;outline:none;color:#14213b}.field input:focus,.field textarea:focus{border-color:#ff9d68;box-shadow:0 0 0 3px rgba(255,90,0,.08)}.field textarea{resize:vertical;min-height:120px}
    .pay-btn{width:100%;border:0;border-radius:10px;background:linear-gradient(90deg,#ff5200,#ff6b00);color:#fff;font-weight:900;font-size:18px;padding:17px 18px;cursor:pointer;box-shadow:0 12px 24px rgba(255,90,0,.20)}.pay-btn:hover{filter:brightness(.96)}
    .secure-line{display:flex;justify-content:center;align-items:center;gap:7px;color:#536079;font-size:12px;margin-top:15px}.secure-line span{width:8px;height:8px;border-radius:50%;background:#12a05c}
    .alert{padding:12px 14px;border-radius:10px;background:#fff2f2;border:1px solid #ffd2d2;color:#9f1d1d;margin-bottom:15px;font-size:13px}
    .details-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:24px;margin-top:28px}
    .info-panel{padding:28px}.info-panel h2{font-size:25px;margin:0 0 8px;color:#07152d}.info-panel .intro{color:#6a7688;line-height:1.7;margin:0 0 20px}
    .steps{display:grid;gap:0}.step{display:grid;grid-template-columns:52px 1fr;gap:16px;padding:16px 0;border-bottom:1px solid #edf1f5}.step:last-child{border-bottom:0}.step-number{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;background:var(--orange);color:#fff;font-weight:900;font-size:17px}.step h3{margin:0 0 4px;font-size:15px}.step p{margin:0;color:#667085;font-size:13px;line-height:1.55}
    .rules{display:grid;gap:10px}.rule{display:flex;gap:10px;align-items:flex-start;padding:13px 14px;border:1px solid #e6ebf1;border-radius:10px;background:#fbfcfd;color:#46556c;font-size:13px;line-height:1.55}.check{color:#0f9d58;font-size:18px;font-weight:900;line-height:1}.rule strong{color:#18253c}
    .help{margin-top:15px;background:#06234c;color:#fff;border-radius:12px;padding:17px 18px}.help strong{display:block;margin-bottom:4px}.help span{font-size:12px;color:#d6e0ef;line-height:1.6;display:block}
    .trust-row{margin-top:24px;display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e4e9f0;border-radius:14px;background:#fff;overflow:hidden}.trust-row>div{padding:18px;display:flex;gap:10px;align-items:center;border-right:1px solid #e4e9f0}.trust-row>div:last-child{border-right:0}.trust-row svg{width:26px;height:26px;stroke:#07244e;fill:none;stroke-width:1.7}.trust-row strong{display:block;font-size:12px}.trust-row small{display:block;color:#7c8798;font-size:10px;margin-top:2px;line-height:1.4}
    @media(max-width:1050px){.purchase-main,.details-grid{grid-template-columns:1fr}.buy-panel{position:static}.trust-row{grid-template-columns:repeat(2,1fr)}.trust-row>div:nth-child(2){border-right:0}.trust-row>div:nth-child(-n+2){border-bottom:1px solid #e4e9f0}}
    @media(max-width:650px){.card-content,.buy-panel,.info-panel{padding:20px}.facts{grid-template-columns:1fr}.fact{border-right:0!important}.fact:nth-last-child(-n+2){border-bottom:1px solid #e6ebf1}.fact:last-child{border-bottom:0}.choice{grid-template-columns:1fr}.choice label+label{border-left:0;border-top:1px solid #dfe5ed}.trust-row{grid-template-columns:1fr}.trust-row>div{border-right:0!important;border-bottom:1px solid #e4e9f0}.trust-row>div:last-child{border-bottom:0}}
</style>
@endpush

@section('content')
<main class="ov-page purchase-wrap">
    <div class="ov-container">
        <div class="ov-breadcrumb">
            <a href="{{ route('home') }}">Accueil</a><span>›</span>
            <a href="{{ route('gift-cards.index') }}">Cartes OVANIE</a><span>›</span>
            <a href="{{ route('gift-cards.category', $presentation['group']) }}">{{ $presentation['type'] }}</a><span>›</span>
            <strong>{{ $presentation['title'] }}</strong>
        </div>

        <section class="purchase-main">
            <article class="card-panel">
                <div class="card-visual"><img src="{{ asset($product->image_path) }}" alt="{{ $presentation['title'] }}"></div>
                <div class="card-content">
                    <span class="type-pill">{{ $presentation['type'] }} · {{ $presentation['accent'] }}</span>
                    <h1>{{ $presentation['title'] }}</h1>
                    <p class="summary">{{ $presentation['summary'] }}</p>
                    <div class="big-price">{{ number_format((float)$product->activation_price,0,',',' ') }} FCFA</div>

                    <div class="facts">
                        <div class="fact"><span class="fact-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18"></path></svg></span><div><small>Validité</small><strong>{{ $product->validity_days ? $product->validity_days.' jours à partir de l’activation' : $product->validity_months.' mois à partir de l’activation' }}</strong></div></div>
                        <div class="fact"><span class="fact-icon"><svg viewBox="0 0 24 24"><path d="M3 5h2l2 10h10l2-7H6"></path><circle cx="9" cy="20" r="1"></circle><circle cx="17" cy="20" r="1"></circle></svg></span><div><small>Utilisation</small><strong>En une ou plusieurs fois sur OVANIE</strong></div></div>
                        <div class="fact"><span class="fact-icon green"><svg viewBox="0 0 24 24"><path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"></path><path d="m9 12 2 2 4-4"></path></svg></span><div><small>Disponibilité</small><strong>Immédiatement après paiement confirmé</strong></div></div>
                        <div class="fact"><span class="fact-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 9h18M7 14h5"></path></svg></span><div><small>Format</small><strong>{{ $product->is_rechargeable ? 'Carte virtuelle personnelle rechargeable' : 'Carte digitale OVANIE' }}</strong></div></div>
                    </div>
                </div>
            </article>

            <aside class="buy-panel">
                <h2>{{ $product->is_rechargeable ? 'Activer cette carte' : 'Acheter cette carte' }}</h2>
                <p class="buy-intro">Le code réel, le PIN et le solde sont générés automatiquement après confirmation du paiement. Les codes imprimés sur le visuel sont uniquement décoratifs.</p>
                <div class="divider"></div>

                @if(session('error'))<div class="alert">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

                <form method="POST" action="{{ route('gift-cards.purchase.store', $product) }}">
                    @csrf
                    @if($product->is_rechargeable)
                        <input type="hidden" name="for_me" value="1">
                        <span class="label-title">Cette carte est personnelle</span>
                        <div class="personal-note">Elle sera rattachée automatiquement à votre compte OVANIE. Vous pourrez consulter son solde et la recharger depuis « Mes cartes ».</div>
                    @else
                        <span class="label-title">Cette carte est</span>
                        <div class="choice">
                            <label><input type="radio" name="for_me" value="1" checked data-for-me> Pour moi</label>
                            <label><input type="radio" name="for_me" value="0" data-for-gift> Pour offrir</label>
                        </div>
                    @endif

                    <div id="recipientFields" hidden>
                        <label class="field"><span>Nom du bénéficiaire</span><input name="recipient_name" value="{{ old('recipient_name') }}" autocomplete="name" placeholder="Nom complet"></label>
                        <label class="field"><span>Téléphone du bénéficiaire</span><input name="recipient_phone" value="{{ old('recipient_phone') }}" autocomplete="tel" placeholder="Ex. 07 00 00 00 00"></label>
                        <label class="field"><span>E-mail du bénéficiaire</span><input type="email" name="recipient_email" value="{{ old('recipient_email') }}" autocomplete="email" placeholder="nom@exemple.com"></label>
                    </div>

                    @unless($product->is_rechargeable)
                        <label class="field"><span>Message personnalisé (facultatif)</span><textarea name="personal_message" maxlength="500" placeholder="Exemple : Joyeux anniversaire ! Profite bien de ta carte OVANIE.">{{ old('personal_message') }}</textarea></label>
                    @endunless

                    <button class="pay-btn" type="submit">{{ $product->is_rechargeable ? 'Activer' : 'Payer' }} {{ number_format((float)$product->activation_price,0,',',' ') }} FCFA</button>
                    <div class="secure-line"><span></span>Paiement sécurisé OVANIE</div>
                </form>
            </aside>
        </section>

        <section class="details-grid">
            <article class="info-panel">
                <h2>Comment utiliser cette carte sur OVANIE ?</h2>
                <p class="intro">Après paiement, la carte est ajoutée à votre espace client et son code sécurisé devient disponible.</p>
                <div class="steps">
                    <div class="step"><div class="step-number">1</div><div><h3>Recevez votre code et votre PIN</h3><p>OVANIE génère automatiquement des identifiants uniques après confirmation du paiement.</p></div></div>
                    <div class="step"><div class="step-number">2</div><div><h3>Ajoutez vos produits au panier</h3><p>Faites vos achats normalement sur la marketplace puis passez au checkout.</p></div></div>
                    <div class="step"><div class="step-number">3</div><div><h3>Appliquez votre carte au paiement</h3><p>Saisissez le code et le PIN dans la zone « Carte OVANIE » du checkout.</p></div></div>
                    <div class="step"><div class="step-number">4</div><div><h3>Payez seulement le complément</h3><p>Le solde de la carte est déduit automatiquement. Si nécessaire, vous réglez uniquement le montant restant.</p></div></div>
                </div>
            </article>

            <aside class="info-panel">
                <h2>À savoir avant d’acheter</h2>
                <p class="intro">Les règles essentielles pour utiliser votre carte correctement.</p>
                <div class="rules">
                    <div class="rule"><span class="check">✓</span><div><strong>Solde conservé :</strong> le montant non utilisé reste disponible jusqu’à la date d’expiration.</div></div>
                    <div class="rule"><span class="check">✓</span><div><strong>Paiement mixte :</strong> si la commande dépasse le solde de la carte, le complément peut être payé avec un autre moyen de paiement disponible.</div></div>
                    <div class="rule"><span class="check">✓</span><div><strong>Code personnel :</strong> le code réel n’est jamais celui visible sur l’image commerciale.</div></div>
                    <div class="rule"><span class="check">✓</span><div><strong>Compte client :</strong> après paiement, retrouvez la carte, son solde et son historique dans « Mes cartes ».</div></div>
                    @if($product->is_rechargeable)
                        <div class="rule"><span class="check">✓</span><div><strong>Recharge :</strong> {{ $product->max_total_recharge ? 'recharges cumulées jusqu’à '.number_format((float)$product->max_total_recharge,0,',',' ').' FCFA.' : 'recharges cumulées illimitées.' }}</div></div>
                    @else
                        <div class="rule"><span class="check">✓</span><div><strong>Non rechargeable :</strong> la carte reste utilisable jusqu’à épuisement du solde ou expiration.</div></div>
                    @endif
                </div>
                <div class="help"><strong>Où retrouver la carte ?</strong><span>Ouvrez votre espace client OVANIE puis « Mes cartes ». Vous pourrez y consulter le solde, l’historique et les informations d’utilisation.</span></div>
            </aside>
        </section>

        <div class="trust-row">
            <div><svg viewBox="0 0 24 24"><path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"></path></svg><span><strong>Paiement sécurisé</strong><small>Transactions protégées</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M4 13a8 8 0 0 1 16 0"></path><path d="M4 13v5h3v-5H4Zm13 0v5h3v-5h-3Z"></path></svg><span><strong>Service client réactif</strong><small>Assistance 7j/7</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M12 2 15 6l5 1-2 5 2 5-5 1-3 4-3-4-5-1 2-5-2-5 5-1 3-4Z"></path></svg><span><strong>Plateforme de confiance</strong><small>Écosystème OVANIE</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg><span><strong>Disponible immédiatement</strong><small>Après paiement confirmé</small></span></div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const gift = document.querySelector('[data-for-gift]');
    const me = document.querySelector('[data-for-me]');
    const fields = document.getElementById('recipientFields');
    function sync(){ if (!fields) return; fields.hidden = !(gift && gift.checked); }
    gift?.addEventListener('change', sync); me?.addEventListener('change', sync); sync();
})();
</script>
@endpush
