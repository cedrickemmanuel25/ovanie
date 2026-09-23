@extends('layouts.client')

@section('title', 'Suivi de livraison')

@push('styles')
<link href="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.css" rel="stylesheet">
<link href="{{ asset('css/ovanie-delivery-map.css') }}?v={{ @filemtime(public_path('css/ovanie-delivery-map.css')) ?: '20260717-3' }}" rel="stylesheet">
<style>
:root {
    --ov-navy:#082b5c;
    --ov-blue:#1d5fff;
    --ov-green:#0d9f72;
    --ov-orange:#f97316;
    --ov-ink:#10213d;
    --ov-muted:#6f7f95;
    --ov-line:#e3e9f1;
    --ov-surface:#ffffff;
    --ov-soft:#f5f8fc;
}
.ov-track-shell{max-width:1180px;margin:0 auto;padding:24px 18px 52px;display:flex;flex-direction:column;gap:14px;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ov-ink)}
.ov-track-top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.ov-track-top nav{display:flex;align-items:center;gap:7px;color:#8090a6;font-size:12px}.ov-track-top nav a{color:#087a5a;font-weight:850}.ov-track-actions{display:flex;gap:8px}.ov-track-link{min-height:38px;display:inline-flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid #d6dfeb;border-radius:10px;background:#fff;color:#35465e;font-size:12px;font-weight:850;box-shadow:0 3px 10px rgba(15,32,59,.04)}.ov-track-link.primary{border-color:var(--ov-green);background:var(--ov-green);color:#fff}
.ov-status-card,.ov-delivery-switcher,.ov-map-stage,.ov-info-item,.ov-otp-card{background:var(--ov-surface);border:1px solid var(--ov-line);box-shadow:0 10px 32px rgba(15,32,59,.055)}
.ov-status-card{border-radius:18px;padding:19px 22px}.ov-status-card__top{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}.ov-status-card__eyebrow{display:block;margin-bottom:5px;color:#087b5a;font-size:10px;font-weight:950;letter-spacing:.11em;text-transform:uppercase}.ov-status-card h1{margin:0;font-size:23px;line-height:1.2;font-weight:950;letter-spacing:-.025em}.ov-eta{margin:7px 0 0;color:#718198;font-size:12.5px}.ov-eta strong{color:var(--ov-ink)}
.ov-status-pill{display:inline-flex;align-items:center;gap:8px;min-height:36px;padding:0 13px;border-radius:999px;border:1px solid #cfdefd;background:#eff5ff;color:#1754d4;font-size:11.5px;font-weight:900}.ov-status-pill::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor;box-shadow:0 0 0 5px color-mix(in srgb,currentColor 12%,transparent)}.ov-status-pill.pending{color:#a96605;background:#fff8e7;border-color:#f7dfaa}.ov-status-pill.delivered{color:#087a55;background:#ecfdf5;border-color:#b7ead7}.ov-status-pill.problem{color:#c22a2a;background:#fff1f1;border-color:#ffd0d0}
.ov-timeline{display:flex;align-items:flex-start;margin-top:20px}.ov-step{flex:0 0 110px;display:flex;flex-direction:column;align-items:center;gap:7px}.ov-step-dot{width:31px;height:31px;border-radius:50%;display:grid;place-items:center;border:2px solid #dce4ef;background:#fff;color:#9aa7b9;font-size:12px;font-weight:900;transition:.2s}.ov-step-dot.done{background:var(--ov-green);border-color:var(--ov-green);color:#fff}.ov-step-dot.active{background:var(--ov-blue);border-color:var(--ov-blue);color:#fff;box-shadow:0 0 0 6px rgba(29,95,255,.11)}.ov-step-label{font-size:10.5px;font-weight:850;color:#9aa8ba;white-space:nowrap}.ov-step-label.done{color:#087a55}.ov-step-label.active{color:#1754d4}.ov-connector{flex:1;height:2px;margin-top:15px;background:#e3e9f1;min-width:28px}.ov-connector.done{background:var(--ov-green)}
.ov-otp-card{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 20px;border-color:#b8e9d8;border-radius:16px;background:linear-gradient(135deg,#effcf7,#fbfffd)}.ov-otp-copy strong{display:block;color:#087a55;font-size:14px;font-weight:950}.ov-otp-copy span{display:block;margin-top:5px;color:#61766e;font-size:11px;line-height:1.5}.ov-otp-codes{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}.ov-otp-code{min-width:145px;padding:10px 14px;border:1px dashed #48b993;border-radius:12px;background:#fff;text-align:center}.ov-otp-code b{display:block;color:var(--ov-navy);font-size:23px;font-weight:950;letter-spacing:.2em;font-variant-numeric:tabular-nums}.ov-otp-code small{display:block;margin-top:4px;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#7c8b84;font-size:9px}
.ov-delivery-switcher{display:grid;grid-template-columns:minmax(190px,.72fr) minmax(0,2.8fr);gap:14px;align-items:center;border-radius:16px;padding:13px 15px}.ov-delivery-switcher[hidden]{display:none}.ov-delivery-switcher__meta strong{display:block;font-size:13.5px;font-weight:950}.ov-delivery-switcher__meta>span{display:block;margin-top:4px;font-size:10.5px;color:#7d8ca1}.ov-delivery-summary{display:flex;gap:7px;margin-top:8px;flex-wrap:wrap}.ov-delivery-summary-pill{min-height:24px;display:inline-flex!important;align-items:center;padding:0 8px;border-radius:999px;background:#edf4ff;color:#1d5fff!important;font-size:9.5px!important;font-weight:900}.ov-delivery-summary-pill.is-done{background:#eafaf3;color:#087a55!important}.ov-groups{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}.ov-group-btn{flex:0 0 168px;min-height:62px;display:grid;grid-template-columns:34px minmax(0,1fr);gap:9px;align-items:center;border:1px solid #dfe6ef;border-radius:12px;background:#fff;padding:9px 10px;text-align:left;cursor:pointer;transition:.18s}.ov-group-btn:hover{transform:translateY(-1px);border-color:#9ab8f4;box-shadow:0 7px 17px rgba(15,32,59,.07)}.ov-group-btn.active{border-color:var(--ov-blue);background:#f3f7ff;box-shadow:0 0 0 3px rgba(29,95,255,.09)}.ov-group-number{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#edf1f6;color:#43536b;font-size:12px;font-weight:950}.ov-group-btn.active .ov-group-number{background:var(--ov-blue);color:#fff}.ov-group-copy{min-width:0}.ov-group-copy strong{display:block;font-size:11.5px;font-weight:950}.ov-group-status{display:flex;align-items:center;gap:6px;margin-top:4px;color:#718097;font-size:9.5px;font-weight:850;white-space:nowrap}.ov-group-status::before{content:"";width:6px;height:6px;border-radius:50%;background:#a4afbd}.ov-group-status.is-live{color:#1754d4}.ov-group-status.is-live::before{background:var(--ov-blue);box-shadow:0 0 0 3px rgba(29,95,255,.1)}.ov-group-status.is-done{color:#087a55}.ov-group-status.is-done::before{background:#10b981}.ov-group-status.is-problem{color:#bd2727}.ov-group-status.is-problem::before{background:#ef4444}.ov-group-product{display:block;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#98a4b5;font-size:9px;font-style:normal}
.ov-map-stage{position:relative;height:500px;overflow:hidden;border-radius:18px;background:#eef2f6}.ov-map-stage::before{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;box-shadow:inset 0 0 0 1px rgba(255,255,255,.35)}#ov-tracking-map{position:absolute;inset:0;opacity:0;transition:opacity .2s}.ov-map-stage.has-location #ov-tracking-map{opacity:1}.ov-map-empty{position:absolute;inset:0;z-index:5;display:grid;place-items:center;padding:34px;text-align:center;background:radial-gradient(circle at 50% 35%,#fff 0,#f7f9fc 45%,#eef2f6 100%);color:#6d7d92}.ov-map-empty>div{max-width:520px;padding:26px 30px;border:1px solid #e0e7f0;border-radius:18px;background:rgba(255,255,255,.9);box-shadow:0 14px 34px rgba(15,32,59,.08)}.ov-map-empty strong{display:block;margin-bottom:7px;color:#17263d;font-size:16px;font-weight:950}.ov-map-empty p{margin:0;font-size:11.5px;line-height:1.65}.ov-map-stage.has-location .ov-map-empty{display:none}
.ov-map-live-summary{position:absolute;top:16px;right:16px;z-index:7;min-width:190px;padding:10px 13px;border:1px solid rgba(221,229,239,.96);border-radius:13px;background:rgba(255,255,255,.96);box-shadow:0 12px 28px rgba(15,32,59,.14);backdrop-filter:blur(12px)}.ov-map-live-summary[hidden]{display:none}.ov-map-live-summary strong{display:flex;align-items:center;gap:8px;font-size:11.5px;font-weight:950}.ov-map-live-summary strong::before{content:"";width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 0 5px rgba(16,185,129,.12)}.ov-map-live-summary small{display:block;margin-top:4px;color:#74839a;font-size:9.5px;font-weight:750}
.ov-recenter-card{position:absolute;top:16px;left:16px;z-index:7;width:235px;min-height:70px;display:grid;grid-template-columns:40px minmax(0,1fr);gap:11px;align-items:center;padding:11px 12px;border:1px solid rgba(221,229,239,.96);border-radius:14px;background:rgba(255,255,255,.96);color:var(--ov-ink);box-shadow:0 12px 28px rgba(15,32,59,.14);text-align:left;cursor:pointer;backdrop-filter:blur(12px)}.ov-recenter-card[hidden]{display:none}.ov-recenter-card__icon{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;background:#eff4ff;color:var(--ov-blue)}.ov-recenter-card strong{display:block;font-size:11.5px;font-weight:950}.ov-recenter-card span span{display:block;margin-top:3px;color:#728198;font-size:9.5px;line-height:1.4}.ov-recenter-card.is-needed{border-color:#9ab8f4;box-shadow:0 13px 32px rgba(29,95,255,.18)}
.ov-courier-card{position:absolute;z-index:8;left:50%;bottom:16px;transform:translateX(-50%);width:min(1040px,calc(100% - 32px));min-height:88px;display:grid;grid-template-columns:minmax(180px,1.1fr) minmax(120px,.7fr) minmax(210px,1.35fr) minmax(130px,.75fr) minmax(170px,1fr) 48px;align-items:center;padding:12px 14px;border:1px solid rgba(224,231,240,.95);border-radius:16px;background:rgba(255,255,255,.97);box-shadow:0 16px 38px rgba(15,32,59,.2);backdrop-filter:blur(14px)}.ov-courier-card[hidden]{display:none}.ov-courier-person{display:flex;align-items:center;gap:11px;min-width:0}.ov-courier-avatar{width:48px;height:48px;flex:0 0 48px;border:3px solid #fff;border-radius:50%;display:grid;place-items:center;overflow:hidden;background:#1769e8;color:#fff;box-shadow:0 7px 18px rgba(23,105,232,.25);font-size:14px;font-weight:950}.ov-courier-avatar img{width:100%;height:100%;object-fit:cover}.ov-courier-copy{min-width:0}.ov-courier-copy>strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px;font-weight:950}.ov-courier-rating{display:flex;align-items:center;gap:4px;margin-top:4px;color:#f97316;font-size:10px;font-weight:850}.ov-courier-rating small{margin-left:6px;color:#718198;font-size:9px}.ov-float-block{min-width:0;padding-left:14px;border-left:1px solid #e7ecf2}.ov-float-label{display:block;color:#91a0b3;font-size:8.5px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.ov-float-value{display:block;margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#17263d;font-size:11.5px;font-weight:950}.ov-float-sub{display:block;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#7a899e;font-size:8.8px}.ov-progress-copy{display:flex;align-items:center;justify-content:space-between;gap:8px}.ov-progress-copy strong{color:#1d5fff;font-size:10.5px}.ov-route-progress{height:5px;margin-top:8px;border-radius:999px;background:#e8eef7;overflow:hidden}.ov-route-progress span{display:block;height:100%;width:0;border-radius:inherit;background:linear-gradient(90deg,#1d5fff,#5d8cff);transition:width .35s}.ov-call-button{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:var(--ov-green);color:#fff;box-shadow:0 8px 18px rgba(13,159,114,.25)}.ov-call-button[hidden]{display:none}
.ov-info-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ov-info-item{min-height:122px;border-radius:16px;padding:17px;display:grid;grid-template-columns:42px minmax(0,1fr);gap:12px;align-items:start}.ov-info-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#eef4ff;color:#1d5fff}.ov-info-item:nth-child(3) .ov-info-icon{background:#fff1e9;color:#f97316}.ov-info-item:nth-child(4) .ov-info-icon{background:#eafaf3;color:#0d9f72}.ov-info-label{color:#8a99ad;font-size:8.5px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.ov-info-value{margin-top:7px;color:#13233d;font-size:13.5px;font-weight:950;line-height:1.35}.ov-info-help{margin-top:6px;color:#8b99ac;font-size:9.5px;line-height:1.45}
@media(max-width:1080px){.ov-courier-card{grid-template-columns:1.15fr .8fr 1.2fr .8fr 46px}.ov-order-block{display:none}.ov-info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:820px){.ov-track-shell{padding:18px 12px 42px}.ov-delivery-switcher{grid-template-columns:1fr}.ov-map-stage{height:590px}.ov-courier-card{grid-template-columns:1fr 1fr;bottom:12px;gap:11px}.ov-courier-person{grid-column:1/-1}.ov-call-button{position:absolute;right:14px;top:14px}.ov-float-block{border-left:0;padding-left:0}.ov-progress-block{grid-column:1/-1}.ov-map-live-summary{top:94px}}
@media(max-width:640px){.ov-track-shell{padding:14px 9px 34px}.ov-status-card{padding:16px 14px}.ov-step{flex-basis:72px}.ov-step-label{font-size:9px}.ov-map-stage{height:620px;border-radius:16px}.ov-recenter-card{width:auto;right:12px;left:12px;top:12px;min-height:62px}.ov-map-live-summary{top:84px;left:12px;right:auto;min-width:170px}.ov-courier-card{width:calc(100% - 20px)}.ov-info-grid{grid-template-columns:1fr}.ov-group-btn{flex-basis:154px}.ov-otp-card{align-items:stretch;flex-direction:column;padding:15px}.ov-otp-codes{justify-content:stretch}.ov-otp-code{flex:1 1 100%}}
</style>
@endpush

@section('content')
<div class="ov-track-shell">
    <div class="ov-track-top">
        <nav aria-label="Fil d’Ariane">
            <a href="{{ route('client.orders') }}">Mes commandes</a>
            <span>›</span>
            <a href="{{ route('client.orders.show', $order) }}">Commande #{{ $order->order_number }}</a>
            <span>›</span>
            <span>Suivi</span>
        </nav>
        <div class="ov-track-actions">
            <a class="ov-track-link" href="{{ route('client.orders.show', $order) }}">Voir les articles</a>
            <a class="ov-track-link primary" href="{{ Route::has('home') ? route('home') : url('/') }}">Accueil</a>
        </div>
    </div>

    <section class="ov-status-card">
        <div class="ov-status-card__top">
            <div>
                <span class="ov-status-card__eyebrow">Commande #{{ $order->order_number }}</span>
                <h1>Suivi de livraison</h1>
                <p id="ov-eta" class="ov-eta">Mise à jour du suivi…</p>
            </div>
            <span id="ov-status" class="ov-status-pill"><span id="ov-status-text">En cours</span></span>
        </div>

        <div class="ov-timeline" id="ov-timeline">
            <div class="ov-step"><div class="ov-step-dot" id="step-pending">✓</div><div class="ov-step-label" id="label-pending">Confirmée</div></div>
            <div class="ov-connector" id="conn-1"></div>
            <div class="ov-step"><div class="ov-step-dot" id="step-pickup">2</div><div class="ov-step-label" id="label-pickup">Enlèvement</div></div>
            <div class="ov-connector" id="conn-2"></div>
            <div class="ov-step"><div class="ov-step-dot" id="step-transit">3</div><div class="ov-step-label" id="label-transit">En route</div></div>
            <div class="ov-connector" id="conn-3"></div>
            <div class="ov-step"><div class="ov-step-dot" id="step-delivered">4</div><div class="ov-step-label" id="label-delivered">Livré</div></div>
        </div>
    </section>

    @if($deliveryOtpGroups->isNotEmpty())
        <section class="ov-otp-card" aria-label="Code de confirmation de livraison">
            <div class="ov-otp-copy">
                <strong>Votre code de remise</strong>
                <span>Communiquez ce code au livreur uniquement après avoir reçu et vérifié tous vos articles.</span>
            </div>
            <div class="ov-otp-codes">
                @foreach($deliveryOtpGroups as $otpGroup)
                    <div class="ov-otp-code">
                        <b>{{ $otpGroup['code'] }}</b>
                        @if(count($otpGroup['products']))
                            <small title="{{ implode(', ', $otpGroup['products']) }}">{{ implode(', ', $otpGroup['products']) }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="ov-delivery-switcher" id="ov-groups-card" hidden>
        <div class="ov-delivery-switcher__meta">
            <strong>Livraisons de la commande</strong>
            <span id="ov-selected-delivery-label">Sélectionnez la livraison à suivre.</span>
            <div class="ov-delivery-summary">
                <span class="ov-delivery-summary-pill" id="ov-delivery-active-count">0 en cours</span>
                <span class="ov-delivery-summary-pill is-done" id="ov-delivery-done-count">0 livrée</span>
            </div>
        </div>
        <div class="ov-groups" id="ov-groups" role="tablist" aria-label="Livraisons de la commande"></div>
    </section>

    <section class="ov-map-stage" id="ov-map-wrap">
        <div id="ov-tracking-map"></div>

        <div class="ov-map-live-summary" id="ov-map-live-summary" hidden>
            <strong id="ov-map-live-count">0 livreur en direct</strong>
            <small id="ov-map-live-selected">Aucune livraison sélectionnée</small>
        </div>

        <button type="button" class="ov-recenter-card" id="ov-map-recenter" hidden>
            <span class="ov-recenter-card__icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            </span>
            <span><strong>Recentrer la livraison</strong><span>Revenir sur le véhicule et l’itinéraire en temps réel.</span></span>
        </button>

        <div class="ov-map-empty" id="ov-map-empty">
            <div>
                <strong id="ov-map-empty-title">La carte sera disponible au départ de la livraison</strong>
                <p id="ov-map-empty-text">Vous pourrez suivre la progression dès qu’une position de livraison sera enregistrée.</p>
            </div>
        </div>

        <div class="ov-courier-card" id="ov-courier-card" hidden>
            <div class="ov-courier-person">
                <div class="ov-courier-avatar" id="ov-driver-avatar"><span id="ov-driver-initials">OV</span></div>
                <div class="ov-courier-copy">
                    <strong id="ov-driver-name">Votre livreur</strong>
                    <span class="ov-courier-rating"><span>★</span><span id="ov-driver-rating">—</span><small id="ov-driver-signal">Position en direct</small></span>
                </div>
            </div>

            <div class="ov-float-block">
                <span class="ov-float-label">Véhicule</span>
                <span class="ov-float-value" id="ov-vehicle-name">Véhicule de livraison</span>
                <span class="ov-float-sub" id="ov-vehicle-plate">—</span>
            </div>

            <div class="ov-float-block ov-progress-block">
                <div class="ov-progress-copy"><span class="ov-float-label" style="margin:0">Trajet restant</span><strong id="ov-distance-live">—</strong></div>
                <div class="ov-route-progress"><span id="ov-route-progress-bar"></span></div>
                <span class="ov-float-sub" id="ov-route-provider">Itinéraire routier en cours de calcul</span>
            </div>

            <div class="ov-float-block">
                <span class="ov-float-label">Arrivée estimée</span>
                <span class="ov-float-value" id="ov-live-eta">—</span>
                <span class="ov-float-sub" id="ov-live-eta-window">—</span>
            </div>

            <div class="ov-float-block ov-order-block">
                <span class="ov-float-label">Votre commande</span>
                <span class="ov-float-value" id="ov-order-number">#{{ $order->order_number }}</span>
                <span class="ov-float-sub" id="ov-delivery-name">Articles de la commande</span>
            </div>

            <a class="ov-call-button" id="ov-call-driver" href="#" hidden aria-label="Appeler le livreur">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z"/></svg>
            </a>
        </div>
    </section>

    <section class="ov-info-grid">
        <div class="ov-info-item">
            <span class="ov-info-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div><div class="ov-info-label">Dernière mise à jour</div><div class="ov-info-value" id="ov-last-update">—</div><div class="ov-info-help">Actualisation automatique du suivi.</div></div>
        </div>
        <div class="ov-info-item">
            <span class="ov-info-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
            <div><div class="ov-info-label">Adresse de livraison</div><div class="ov-info-value" id="ov-destination">—</div><div class="ov-info-help">Destination enregistrée au checkout.</div></div>
        </div>
        <div class="ov-info-item">
            <span class="ov-info-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5h4"/></svg></span>
            <div><div class="ov-info-label">Arrivée estimée</div><div class="ov-info-value" id="ov-eta-value">—</div><div class="ov-info-help">Calculée à partir de l’itinéraire routier.</div></div>
        </div>
        <div class="ov-info-item">
            <span class="ov-info-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.5a7 7 0 0 1 14 0M8 12.5a4 4 0 0 1 8 0M12 13v.01"/></svg></span>
            <div><div class="ov-info-label">État du suivi</div><div class="ov-info-value" id="ov-signal">Connexion…</div><div class="ov-info-help" id="ov-route-quality">En attente de l’itinéraire.</div></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js"></script>
<script src="{{ asset('js/ovanie-delivery-map.js') }}?v={{ @filemtime(public_path('js/ovanie-delivery-map.js')) ?: '20260718-4' }}"></script>
<script>
(() => {
    const mapboxToken = @js(config('geo.mapbox.public_token'));
    const mapboxStyle = @js(config('geo.mapbox.style_url', 'mapbox://styles/mapbox/streets-v12'));
    const defaultCenter = [Number(@js(config('geo.default_lng', -4.008256))), Number(@js(config('geo.default_lat', 5.359952)))];
    const apiEndpoint = @js(route('client.orders.tracking.data', $order));
    const minClientZoom = Number(@js(config('delivery.client_map_min_zoom', 11)));
    const maxClientSpanKm = Number(@js(config('delivery.client_map_max_span_km', 35)));

    let map = null;
    let mapReady = false;
    let destinationMarker = null;
    let selectedTrackingKey = null;
    let lastOrderData = null;
    let lastSelectedData = null;
    let cameraLocked = false;
    let fittedSignature = null;
    let groupsSignature = null;
    let routeRequestController = null;
    let pendingRouteKey = null;
    const driverMarkers = new Map();
    const browserRouteCache = new Map();

    function bootMap() {
        const host = document.getElementById('ov-tracking-map');
        if (!host) return;
        if (!mapboxToken || !window.mapboxgl || !window.OvanieDeliveryMap) {
            setMapMessage('Carte temporairement indisponible', 'Le service cartographique doit être configuré avant la mise en production.');
            return;
        }

        mapboxgl.accessToken = mapboxToken;
        map = new mapboxgl.Map({
            container:'ov-tracking-map',
            style:mapboxStyle,
            center:defaultCenter,
            zoom:Math.max(minClientZoom, 12),
            minZoom:minClientZoom,
            maxZoom:18.5,
            pitch:0,
            bearing:0,
            dragRotate:false,
            pitchWithRotate:false,
            renderWorldCopies:false,
            fadeDuration:0,
            attributionControl:true,
            cooperativeGestures:true,
        });
        OvanieDeliveryMap.applyMapConstraints(map, { minZoom:minClientZoom, maxZoom:18.5 });
        map.addControl(new mapboxgl.NavigationControl({ showCompass:false, visualizePitch:false }), 'bottom-right');

        ['dragstart','zoomstart','rotatestart','pitchstart'].forEach(eventName => {
            map.on(eventName, event => {
                if (!event.originalEvent) return;
                cameraLocked = true;
                document.getElementById('ov-map-recenter')?.classList.add('is-needed');
            });
        });
        document.getElementById('ov-map-recenter')?.addEventListener('click', () => fitCurrentDelivery(true));
        map.on('load', () => {
            mapReady = true;
            ensureRouteSource();
            refreshTracking();
        });
    }

    async function refreshTracking() {
        try {
            const response = await fetch(apiEndpoint, {
                headers:{ 'Accept':'application/json' },
                credentials:'same-origin',
                cache:'no-store',
            });
            if (!response.ok) {
                setText('ov-signal', 'Mise à jour indisponible');
                setMapMessage('Impossible de charger le suivi', response.status === 401 || response.status === 403
                    ? 'Reconnectez-vous à votre espace client.'
                    : 'Une nouvelle tentative sera effectuée automatiquement.');
                hideLiveMap();
                return;
            }

            const data = await response.json();
            const shipments = Array.isArray(data.shipments) ? data.shipments : [];
            lastOrderData = data;
            renderGroups(shipments);
            const selected = selectedShipment(shipments) || data;
            lastSelectedData = selected;
            updateUI(selected);
            updateMapOverview(shipments, selected);
        } catch (error) {
            setText('ov-signal', 'Connexion momentanément interrompue');
            setText('ov-route-quality', 'Le suivi reprendra automatiquement.');
        }
    }

    function renderGroups(shipments) {
        const card = document.getElementById('ov-groups-card');
        const host = document.getElementById('ov-groups');
        if (!card || !host || !Array.isArray(shipments) || shipments.length <= 1) {
            if (card) card.hidden = true;
            if (shipments.length === 1) selectedTrackingKey = trackingKey(shipments[0]);
            return;
        }

        card.hidden = false;
        if (!selectedTrackingKey || !shipments.some(item => trackingKey(item) === selectedTrackingKey)) {
            const preferred = shipments.find(item => item.map_visible)
                || shipments.find(item => !isFinished(item.delivery_status))
                || shipments[0];
            selectedTrackingKey = trackingKey(preferred);
        }

        const doneCount = shipments.filter(item => isFinished(item.delivery_status)).length;
        const activeCount = shipments.filter(item => !isFinished(item.delivery_status) && item.delivery_status !== 'pending').length;
        setText('ov-delivery-active-count', `${activeCount} en cours`);
        setText('ov-delivery-done-count', `${doneCount} livrée${doneCount > 1 ? 's' : ''}`);

        const selectedIndex = Math.max(0, shipments.findIndex(item => trackingKey(item) === selectedTrackingKey));
        setText('ov-selected-delivery-label', `Livraison ${selectedIndex + 1} sur ${shipments.length} sélectionnée`);

        const signature = shipments.map(item => [
            trackingKey(item), item.delivery_status, item.delivery?.label || '', item.vehicle?.vehicle_code || '', Boolean(item.map_visible)
        ].join(':')).join('|');
        if (signature !== groupsSignature) {
            groupsSignature = signature;
            host.innerHTML = '';
            shipments.forEach((shipment, index) => {
                const key = trackingKey(shipment);
                const meta = statusMeta(shipment.delivery_status || 'pending');
                const statusClass = isFinished(shipment.delivery_status)
                    ? 'is-done'
                    : (['problem','failed','delivery_failed','cancelled'].includes(shipment.delivery_status)
                        ? 'is-problem'
                        : (shipment.map_visible || ['picked_up','in_transit','in_delivery','late'].includes(shipment.delivery_status) ? 'is-live' : ''));
                const button = document.createElement('button');
                button.type = 'button';
                button.role = 'tab';
                button.dataset.trackingKey = key;
                button.className = 'ov-group-btn';
                button.innerHTML = `
                    <span class="ov-group-number">${index + 1}</span>
                    <span class="ov-group-copy">
                        <strong>${escapeHtml(shipment.delivery_label || `Livraison ${index + 1}`)}</strong>
                        <span class="ov-group-status ${statusClass}">${escapeHtml(meta.label)}</span>
                        <em class="ov-group-product">${escapeHtml(shipment.delivery?.label || 'Articles de la commande')}</em>
                    </span>`;
                button.addEventListener('click', () => selectTrackingKey(key));
                host.appendChild(button);
            });
        }

        host.querySelectorAll('.ov-group-btn').forEach(button => {
            const active = button.dataset.trackingKey === selectedTrackingKey;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function selectTrackingKey(key) {
        if (!lastOrderData || !Array.isArray(lastOrderData.shipments)) return;
        selectedTrackingKey = String(key);
        cameraLocked = false;
        fittedSignature = null;
        document.getElementById('ov-map-recenter')?.classList.remove('is-needed');
        renderGroups(lastOrderData.shipments);
        const selected = selectedShipment(lastOrderData.shipments) || lastOrderData;
        lastSelectedData = selected;
        updateUI(selected);
        updateMapOverview(lastOrderData.shipments, selected);
    }

    function selectedShipment(shipments) {
        if (!Array.isArray(shipments) || shipments.length === 0) return null;
        return shipments.find(item => trackingKey(item) === selectedTrackingKey)
            || shipments.find(item => item.map_visible)
            || shipments.find(item => !isFinished(item.delivery_status))
            || shipments[0];
    }

    function updateUI(data) {
        const status = data.delivery_status || 'pending';
        const meta = statusMeta(status);
        const pill = document.getElementById('ov-status');
        setText('ov-status-text', meta.label);
        if (pill) pill.className = 'ov-status-pill ' + meta.cls;

        const routeMinutes = numeric(data.route?.duration_minutes);
        const eta = document.getElementById('ov-eta');
        if (data.map_visible && routeMinutes) eta.innerHTML = `Arrivée estimée dans <strong>${Math.max(1, Math.round(routeMinutes))} min</strong>`;
        else if (data.eta) eta.innerHTML = `Arrivée estimée : <strong>${formatDate(data.eta)}</strong>`;
        else eta.textContent = etaMessage(data.tracking_phase, status);

        if (data.map_visible) {
            setMapMessage('Position du livreur en cours de chargement', 'La carte se met à jour automatiquement.');
        } else if (data.tracking_phase === 'waiting_assignment') {
            setMapMessage('Préparation de votre livraison', 'Aucun livreur n’est encore affecté. La carte s’activera uniquement après l’affectation, la collecte des articles et le départ vers votre adresse.');
        } else if (data.tracking_phase === 'waiting_start' || data.tracking_phase === 'to_pickup') {
            setMapMessage('Collecte des articles en cours', 'Le trajet du livreur vers les points de collecte reste réservé à l’équipe logistique. Votre suivi en direct commencera après le chargement.');
        } else if (['picked_up','in_transit','in_delivery','late','problem'].includes(status)) {
            setMapMessage('Mise à jour de la position', 'La livraison a démarré. La carte apparaîtra dès qu’une position GPS récente sera reçue.');
        } else {
            setMapMessage('Suivi cartographique non requis', 'Les informations de progression restent disponibles dans les étapes ci-dessus.');
        }

        const etaText = data.map_visible && routeMinutes
            ? `Dans ${Math.max(1, Math.round(routeMinutes))} min`
            : (data.eta ? formatDate(data.eta) : 'À confirmer');
        setText('ov-eta-value', etaText);
        setText('ov-destination', data.destination?.label || 'Adresse enregistrée');
        setText('ov-last-update', data.last_update ? formatDate(data.last_update) : 'Aucune mise à jour');
        setText('ov-signal', clientSignalLabel(data));
        setText('ov-route-quality', data.map_visible && roadGeometry(data.route?.geometry, data.driver?.location, data.destination)
            ? `Itinéraire routier ${providerLabel(data.route?.provider)}.`
            : routeQualityMessage(data));

        updateCourierCard(data);
        updateTimeline(status);
    }

    function updateCourierCard(data) {
        const card = document.getElementById('ov-courier-card');
        const recenter = document.getElementById('ov-map-recenter');
        const location = data.driver?.location;
        const visible = Boolean(data.map_visible) && validPt(location?.latitude, location?.longitude);
        if (!card) return;
        card.hidden = !visible;
        if (recenter) recenter.hidden = !visible;
        if (!visible) return;

        const name = data.driver?.name || 'Livreur OVANIE';
        setText('ov-driver-name', name);
        const avatar = document.getElementById('ov-driver-avatar');
        if (avatar) {
            avatar.innerHTML = data.driver?.avatar_url
                ? `<img src="${escapeAttribute(data.driver.avatar_url)}" alt="${escapeAttribute(name)}">`
                : `<span>${escapeHtml(initials(name))}</span>`;
        }
        setText('ov-driver-rating', numeric(data.driver?.rating) ? Number(data.driver.rating).toFixed(1) : 'OVANIE');
        setText('ov-driver-signal', data.signal_status === 'active' ? 'Position en direct' : 'Position récente');

        const code = data.vehicle?.vehicle_code;
        const vehicleName = data.vehicle?.driver_vehicle || data.vehicle?.vehicle_label || vehicleLabel(code);
        const plate = data.vehicle?.vehicle_plate || data.driver?.vehicle || 'Véhicule identifié';
        setText('ov-vehicle-name', vehicleName || vehicleLabel(code));
        setText('ov-vehicle-plate', plate);

        const distance = numeric(data.route?.distance_km);
        setText('ov-distance-live', distance !== null ? formatDistance(distance) : 'Calcul…');
        setText('ov-route-provider', roadGeometry(data.route?.geometry, location, data.destination)
            ? `${providerLabel(data.route?.provider)} · trajet suivant les rues`
            : 'Itinéraire routier en cours de calcul');
        const bar = document.getElementById('ov-route-progress-bar');
        if (bar) bar.style.width = `${statusProgress(data.delivery_status)}%`;

        const minutes = numeric(data.route?.duration_minutes);
        if (minutes) {
            setText('ov-live-eta', `Dans ${Math.max(1, Math.round(minutes))} min`);
            setText('ov-live-eta-window', etaWindow(minutes));
        } else if (data.eta) {
            setText('ov-live-eta', formatTime(data.eta));
            setText('ov-live-eta-window', formatDate(data.eta));
        } else {
            setText('ov-live-eta', 'À confirmer');
            setText('ov-live-eta-window', 'Calcul en cours');
        }

        setText('ov-order-number', '#' + (data.order_number || @js($order->order_number)));
        setText('ov-delivery-name', data.delivery?.label || 'Articles de la commande');
        const call = document.getElementById('ov-call-driver');
        const phone = String(data.driver?.phone || '').trim();
        if (call) {
            call.hidden = phone === '';
            call.href = phone ? `tel:${phone.replace(/[^+\d]/g, '')}` : '#';
        }
    }

    function updateMapOverview(shipments, selected) {
        if (!mapReady || !map || !window.OvanieDeliveryMap) return;
        const stage = document.getElementById('ov-map-wrap');
        const liveShipments = (Array.isArray(shipments) ? shipments : [])
            .filter(item => Boolean(item.map_visible)
                && item.tracking_mode === 'gps'
                && validPt(item.driver?.location?.latitude, item.driver?.location?.longitude));

        if (!liveShipments.length) {
            hideLiveMap();
            return;
        }

        if (!selected?.map_visible) {
            selected = liveShipments[0];
            selectedTrackingKey = trackingKey(selected);
            lastSelectedData = selected;
            renderGroups(shipments);
            updateUI(selected);
        }

        const wasVisible = stage?.classList.contains('has-location');
        stage?.classList.add('has-location');
        if (!wasVisible) map.resize();

        const activeKeys = new Set();
        const selectedDestination = selected.destination;
        const selectedGpsPoint = [Number(selected.driver.location.longitude), Number(selected.driver.location.latitude)];
        const selectedGeometry = roadGeometry(selected.route?.geometry, selected.driver?.location, selectedDestination)
            ? selected.route.geometry
            : null;
        const selectedRouteState = selectedGeometry
            ? OvanieDeliveryMap.remainingRoute(selectedGeometry, selectedGpsPoint, 250)
            : { point:selectedGpsPoint, geometry:null, snapped:false };
        liveShipments.forEach((shipment, index) => {
            const key = trackingKey(shipment);
            activeKeys.add(key);
            const isSelected = key === selectedTrackingKey;
            const gpsPoint = [Number(shipment.driver.location.longitude), Number(shipment.driver.location.latitude)];
            const point = isSelected ? selectedRouteState.point : gpsPoint;
            const number = shipment.delivery_number || index + 1;
            let marker = driverMarkers.get(key);
            marker = OvanieDeliveryMap.upsertMarker(marker, map, point, 'vehicle', {
                label:`Livraison ${number} · ${shipment.driver?.name || 'Livreur OVANIE'} · ${vehicleLabel(shipment.vehicle?.vehicle_code)}`,
                badgeText:vehicleLabel(shipment.vehicle?.vehicle_code),
                vehicleCode:vehicleMarkerCode(shipment),
                number:String(number),
                heading:shipment.driver.location.heading,
                stale:shipment.signal_status !== 'active',
                offline:false,
                selected:isSelected,
                muted:!isSelected,
                animate:Boolean(marker),
                showBadge:true,
            });
            if (!marker.__ovTrackingBound) {
                marker.getElement().addEventListener('click', event => {
                    event.preventDefault();
                    event.stopPropagation();
                    selectTrackingKey(marker.__ovTrackingKey);
                });
                marker.__ovTrackingBound = true;
            }
            marker.__ovTrackingKey = key;
            driverMarkers.set(key, marker);
        });
        driverMarkers.forEach((marker, key) => {
            if (activeKeys.has(key)) return;
            marker.remove();
            driverMarkers.delete(key);
        });

        const destination = selectedDestination;
        if (validPt(destination?.latitude, destination?.longitude)) {
            destinationMarker = OvanieDeliveryMap.upsertMarker(
                destinationMarker,
                map,
                [Number(destination.longitude), Number(destination.latitude)],
                'destination',
                { label:'Votre adresse de livraison', color:'#082b5c', animate:false }
            );
        } else if (destinationMarker) {
            destinationMarker.remove();
            destinationMarker = null;
        }

        const geometry = selectedRouteState.geometry;
        drawRoute(geometry);
        if (!geometry && selected.map_visible && validPt(destination?.latitude, destination?.longitude)) {
            requestBrowserRoadRoute(selected);
        }

        const liveSummary = document.getElementById('ov-map-live-summary');
        if (liveSummary) liveSummary.hidden = false;
        setText('ov-map-live-count', `${liveShipments.length} livreur${liveShipments.length > 1 ? 's' : ''} en direct`);
        setText('ov-map-live-selected', `${selected.delivery_label || 'Livraison sélectionnée'} · ${vehicleLabel(selected.vehicle?.vehicle_code)}`);

        const fitSignature = [selectedTrackingKey, selected.last_update, selected.route?.distance_km, liveShipments.length].join('|');
        if (!cameraLocked && fittedSignature !== fitSignature) {
            fittedSignature = fitSignature;
            fitCurrentDelivery(false);
        }
    }

    function hideLiveMap() {
        document.getElementById('ov-map-wrap')?.classList.remove('has-location');
        document.getElementById('ov-courier-card')?.setAttribute('hidden', 'hidden');
        document.getElementById('ov-map-recenter')?.setAttribute('hidden', 'hidden');
        document.getElementById('ov-map-live-summary')?.setAttribute('hidden', 'hidden');
        driverMarkers.forEach(marker => marker.remove());
        driverMarkers.clear();
        if (destinationMarker) { destinationMarker.remove(); destinationMarker = null; }
        drawRoute(null);
        fittedSignature = null;
    }

    async function requestBrowserRoadRoute(selected) {
        const driver = selected.driver?.location;
        const destination = selected.destination;
        if (!mapboxToken || !validPt(driver?.latitude, driver?.longitude) || !validPt(destination?.latitude, destination?.longitude)) return;
        const key = [
            trackingKey(selected),
            Number(driver.longitude).toFixed(5), Number(driver.latitude).toFixed(5),
            Number(destination.longitude).toFixed(5), Number(destination.latitude).toFixed(5),
        ].join(':');
        if (browserRouteCache.has(key)) {
            applyBrowserRoute(browserRouteCache.get(key), selected);
            return;
        }
        if (pendingRouteKey === key) return;
        if (routeRequestController) routeRequestController.abort();
        routeRequestController = new AbortController();
        pendingRouteKey = key;
        const coordinates = `${Number(driver.longitude).toFixed(6)},${Number(driver.latitude).toFixed(6)};${Number(destination.longitude).toFixed(6)},${Number(destination.latitude).toFixed(6)}`;
        const url = `https://api.mapbox.com/directions/v5/mapbox/driving-traffic/${coordinates}?access_token=${encodeURIComponent(mapboxToken)}&overview=full&geometries=geojson&steps=false&alternatives=false&continue_straight=true`;
        try {
            const response = await fetch(url, { signal:routeRequestController.signal, cache:'no-store' });
            if (!response.ok) return;
            const payload = await response.json();
            const route = payload?.routes?.[0];
            if (!roadGeometry(route?.geometry, driver, destination)) return;
            const browserRoute = {
                geometry:route.geometry,
                provider:'mapbox',
                distance_km:Number.isFinite(Number(route.distance)) ? Number(route.distance) / 1000 : null,
                duration_minutes:Number.isFinite(Number(route.duration)) ? Math.max(1, Math.round(Number(route.duration) / 60)) : null,
            };
            browserRouteCache.set(key, browserRoute);
            applyBrowserRoute(browserRoute, selected);
        } catch (error) {
            if (error?.name !== 'AbortError') setText('ov-route-quality', 'Le calcul routier sera relancé automatiquement.');
        } finally {
            if (pendingRouteKey === key) pendingRouteKey = null;
        }
    }

    function applyBrowserRoute(route, selected) {
        if (!route?.geometry || trackingKey(selected) !== selectedTrackingKey) return;
        selected.route = { ...(selected.route || {}), ...route };
        drawRoute(route.geometry);
        setText('ov-route-quality', 'Itinéraire routier Mapbox Directions.');
        setText('ov-route-provider', 'Mapbox Directions · trajet suivant les rues');
        if (numeric(route.distance_km) !== null) setText('ov-distance-live', formatDistance(route.distance_km));
        if (numeric(route.duration_minutes) !== null) {
            const minutes = Math.max(1, Math.round(route.duration_minutes));
            setText('ov-live-eta', `Dans ${minutes} min`);
            setText('ov-live-eta-window', etaWindow(minutes));
            setText('ov-eta-value', `Dans ${minutes} min`);
        }
    }

    function ensureRouteSource() {
        if (!map || !map.isStyleLoaded() || map.getSource('ov-route')) return;
        map.addSource('ov-route', { type:'geojson', data:{ type:'FeatureCollection', features:[] } });
        map.addLayer({
            id:'ov-route-glow', type:'line', source:'ov-route',
            layout:{ 'line-cap':'round', 'line-join':'round' },
            paint:{ 'line-color':'#1d5fff', 'line-width':13, 'line-opacity':.15, 'line-blur':4 },
        });
        map.addLayer({
            id:'ov-route-casing', type:'line', source:'ov-route',
            layout:{ 'line-cap':'round', 'line-join':'round' },
            paint:{ 'line-color':'#ffffff', 'line-width':8.5, 'line-opacity':.96 },
        });
        map.addLayer({
            id:'ov-route-line', type:'line', source:'ov-route',
            layout:{ 'line-cap':'round', 'line-join':'round' },
            paint:{ 'line-color':'#1d5fff', 'line-width':5, 'line-opacity':1 },
        });
    }

    function drawRoute(geometry) {
        if (!map || !map.isStyleLoaded()) return;
        ensureRouteSource();
        const source = map.getSource('ov-route');
        if (!source) return;
        if (!geometry || geometry.type !== 'LineString' || !Array.isArray(geometry.coordinates) || geometry.coordinates.length < 2) {
            source.setData({ type:'FeatureCollection', features:[] });
            return;
        }
        source.setData({ type:'Feature', properties:{}, geometry });
    }

    function fitCurrentDelivery(force = false) {
        if (!map || !lastSelectedData?.map_visible) return;
        if (!force && cameraLocked) return;
        const driver = lastSelectedData.driver?.location;
        const destination = lastSelectedData.destination;
        if (!validPt(driver?.latitude, driver?.longitude)) return;

        const points = [[Number(driver.longitude), Number(driver.latitude)]];
        if (validPt(destination?.latitude, destination?.longitude)) {
            points.push([Number(destination.longitude), Number(destination.latitude)]);
        }
        const geometry = lastSelectedData.route?.geometry;
        if (roadGeometry(geometry, driver, destination)) {
            const coords = geometry.coordinates;
            const step = Math.max(1, Math.floor(coords.length / 40));
            for (let i = 0; i < coords.length; i += step) points.push(coords[i]);
            points.push(coords[coords.length - 1]);
        }

        cameraLocked = false;
        document.getElementById('ov-map-recenter')?.classList.remove('is-needed');
        OvanieDeliveryMap.fitOperationalBounds(map, points, {
            maxSpanKm:maxClientSpanKm,
            longTripZoom:12.2,
            singleZoom:14.2,
            maxFitZoom:15.7,
            padding:{ top:88, right:62, bottom:150, left:62 },
            duration:force ? 550 : 420,
        });
    }

    function roadGeometry(geometry, driver, destination) {
        if (!geometry || geometry.type !== 'LineString' || !Array.isArray(geometry.coordinates)) return false;
        const coordinates = geometry.coordinates.filter(point => Array.isArray(point) && point.length >= 2 && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1])));
        if (coordinates.length < 2 || coordinates.length !== geometry.coordinates.length) return false;
        if (coordinates.some(point => !validPt(point[1], point[0]))) return false;
        if (!validPt(driver?.latitude,driver?.longitude) || !validPt(destination?.latitude,destination?.longitude)) return coordinates.length >= 4;
        const straight = distanceKm(Number(driver.latitude),Number(driver.longitude),Number(destination.latitude),Number(destination.longitude));
        if (straight >= .15 && coordinates.length < 4) return false;
        let routeDistance = 0;
        for (let i=1;i<coordinates.length;i++) routeDistance += distanceKm(Number(coordinates[i-1][1]),Number(coordinates[i-1][0]),Number(coordinates[i][1]),Number(coordinates[i][0]));
        if (straight > .1 && routeDistance > (straight * 9) + 3) return false;
        const first = coordinates[0];
        const last = coordinates[coordinates.length - 1];
        const startGap = distanceKm(Number(driver.latitude),Number(driver.longitude),Number(first[1]),Number(first[0]));
        const endGap = distanceKm(Number(destination.latitude),Number(destination.longitude),Number(last[1]),Number(last[0]));
        return (straight <= .05 || routeDistance >= straight * .90) && startGap <= 2 && endGap <= 2;
    }

    function updateTimeline(status) {
        const steps = {
            pending:[1,0,0,0], preparing:[1,0,0,0], waiting_driver:[1,0,0,0], driver_reserved:[1,1,0,0], ready_for_pickup:[1,1,0,0],
            assigned:[1,1,0,0], collecting:[1,1,1,0], picked_up:[1,1,1,0], in_transit:[1,1,1,0], in_delivery:[1,1,1,0], arrived:[1,1,1,0],
            late:[1,1,1,0], problem:[1,1,1,0], failed:[1,1,1,0], delivery_failed:[1,1,1,0],
            delivered:[1,1,1,1], completed:[1,1,1,1], returned:[1,1,1,1], not_required:[1,1,1,1],
        };
        const names = ['pending','pickup','transit','delivered'];
        const done = steps[status] || [1,0,0,0];
        const active = done.lastIndexOf(1);
        names.forEach((name,index) => {
            const dot = document.getElementById('step-' + name);
            const label = document.getElementById('label-' + name);
            if (!dot || !label) return;
            dot.className = 'ov-step-dot' + (done[index] ? (index === active && index < 3 ? ' active' : ' done') : '');
            label.className = 'ov-step-label' + (done[index] ? (index === active && index < 3 ? ' active' : ' done') : '');
            dot.textContent = done[index] && index < active ? '✓' : String(index + 1);
        });
        [1,2,3].forEach(index => document.getElementById('conn-' + index)?.classList.toggle('done', Boolean(done[index])));
    }

    function statusMeta(status) {
        const statuses = {
            pending:{label:'Commande confirmée',cls:'pending'}, preparing:{label:'Préparation vendeur',cls:'pending'},
            waiting_driver:{label:'Recherche d’un livreur',cls:'pending'}, driver_reserved:{label:'Livreur réservé',cls:'in-transit'},
            ready_for_pickup:{label:'Prête pour collecte',cls:'in-transit'}, assigned:{label:'Livreur réservé',cls:'in-transit'},
            collecting:{label:'Collecte en cours',cls:'in-transit'}, picked_up:{label:'Chargement terminé',cls:'in-transit'}, in_transit:{label:'En route vers vous',cls:'in-transit'}, arrived:{label:'Livreur arrivé',cls:'in-transit'},
            in_delivery:{label:'En cours de livraison',cls:'in-transit'}, late:{label:'Livraison retardée',cls:'problem'},
            delivered:{label:'Livrée',cls:'delivered'}, completed:{label:'Livrée',cls:'delivered'},
            problem:{label:'Incident en traitement',cls:'problem'}, failed:{label:'Échec de livraison',cls:'problem'},
            delivery_failed:{label:'Échec de livraison',cls:'problem'}, cancelled:{label:'Livraison annulée',cls:'problem'},
            returned:{label:'Commande retournée',cls:'problem'}, not_required:{label:'Aucune livraison requise',cls:'delivered'},
        };
        return statuses[status] || {label:'Suivi en cours',cls:'in-transit'};
    }

    function etaMessage(phase, status) {
        if (phase === 'waiting_assignment') return 'OVANIE recherche un livreur partenaire compatible.';
        if (phase === 'waiting_vendor') return 'Un livreur a réservé la mission et attend la fin de préparation des vendeurs.';
        if (phase === 'waiting_pickup') return 'Tous les vendeurs sont prêts. La collecte va pouvoir commencer.';
        if (phase === 'waiting_start' || phase === 'to_pickup') return 'La collecte est en cours. Le suivi GPS client commencera après le départ vers votre adresse.';
        if (phase === 'arrived_customer') return 'Votre livreur est arrivé. Vérifiez tous vos articles avant de communiquer votre code.';
        if (['picked_up','in_transit','in_delivery'].includes(status)) return 'Calcul de l’heure d’arrivée en cours.';
        return 'L’heure d’arrivée sera affichée dès sa confirmation.';
    }
    function routeQualityMessage(data) {
        if (data.tracking_phase === 'waiting_assignment') return 'Recherche automatique d’un livreur en cours.';
        if (data.tracking_phase === 'waiting_vendor') return 'Mission réservée · préparation vendeur en cours.';
        if (data.tracking_phase === 'waiting_pickup') return 'Vendeurs prêts · collecte à démarrer.';
        if (data.tracking_phase === 'waiting_start' || data.tracking_phase === 'to_pickup') return 'Collecte en cours · trajet vers les boutiques masqué au client.';
        if (data.tracking_phase === 'arrived_customer') return 'Livreur arrivé à votre adresse.';
        if (['picked_up','in_transit','in_delivery'].includes(data.delivery_status)) return 'En attente d’une position GPS récente.';
        return 'Aucun trajet actif.';
    }
    function clientSignalLabel(data) {
        if (data.map_visible && data.signal_status === 'active') return 'Position en direct';
        if (data.map_visible) return 'Position récente';
        if (data.tracking_phase === 'waiting_assignment') return 'Recherche d’un livreur';
        if (data.tracking_phase === 'waiting_vendor') return 'Livreur réservé';
        if (data.tracking_phase === 'waiting_pickup') return 'Prête pour collecte';
        if (data.tracking_phase === 'waiting_start' || data.tracking_phase === 'to_pickup') return 'Collecte en cours';
        if (data.tracking_phase === 'arrived_customer') return 'Livreur arrivé';
        if (data.tracking_mode === 'manual') return 'Suivi par étapes';
        return 'Mise à jour en attente';
    }
    function statusProgress(status){return({pending:10,preparing:18,waiting_driver:24,driver_reserved:32,ready_for_pickup:40,assigned:32,collecting:52,picked_up:60,in_transit:78,in_delivery:86,arrived:94,late:72,problem:68,delivered:100,completed:100}[status]||16)}
    function providerLabel(provider){return({mapbox:'Mapbox Directions',osrm:'OSRM',tomtom:'TomTom Routing'}[String(provider||'').toLowerCase()]||'routier')}
    function vehicleLabel(code){return({moto:'Moto',tricycle:'Tricycle',pickup:'Pickup',camion_3t:'Camion 3T',truck_3t:'Camion 3T',camion_10t:'Camion 10T',truck_10t:'Camion 10T',special:'Transport spécialisé'}[String(code||'').toLowerCase()]||'Véhicule de livraison')}
    function vehicleMarkerCode(item){return item?.vehicle?.driver_vehicle||item?.vehicle?.vehicle_type||item?.vehicle?.vehicle_code||item?.vehicle?.vehicle_label||'vehicle'}
    function trackingKey(item){return String(item?.tracking_key ?? item?.shipment_id ?? item?.order_item_id ?? item?.order_id ?? 'active')}
    function isFinished(status){return['delivered','completed','cancelled','returned','not_required'].includes(status)}
    function formatDistance(value){return Number(value)<1?`${Math.max(1,Math.round(Number(value)*1000))} m restants`:`${Number(value).toLocaleString('fr-FR',{maximumFractionDigits:1})} km restants`}
    function etaWindow(minutes){const from=new Date(Date.now()+Number(minutes)*60000),to=new Date(from.getTime()+6*60000);return`${from.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})} – ${to.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}`}
    function formatDate(iso){try{return new Date(iso).toLocaleString('fr-FR',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}catch(_){return iso}}
    function formatTime(iso){try{return new Date(iso).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}catch(_){return'—'}}
    function initials(name){return String(name||'OV').trim().split(/\s+/).slice(0,2).map(part=>part.charAt(0).toUpperCase()).join('')||'OV'}
    function numeric(value){return value!==null&&value!==''&&Number.isFinite(Number(value))?Number(value):null}
    function validPt(lat,lng){lat=Number(lat);lng=Number(lng);return Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=4&&lat<=11.2&&lng>=-9&&lng<=-2&&(lat!==0||lng!==0)}
    function distanceKm(lat1,lng1,lat2,lng2){const r=6371,p1=lat1*Math.PI/180,p2=lat2*Math.PI/180,dp=(lat2-lat1)*Math.PI/180,dl=(lng2-lng1)*Math.PI/180,a=Math.sin(dp/2)**2+Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;return r*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a))}
    function setMapMessage(title,text){setText('ov-map-empty-title',title);setText('ov-map-empty-text',text)}
    function setText(id,value){const element=document.getElementById(id);if(element)element.textContent=value}
    function escapeHtml(value){return String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]))}
    function escapeAttribute(value){return escapeHtml(value).replace(/`/g,'&#096;')}

    bootMap();
    refreshTracking();
    setInterval(refreshTracking, Number(@json(config('delivery.client_poll_seconds', 12))) * 1000);
})();
</script>
@endpush
