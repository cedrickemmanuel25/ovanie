@extends('layouts.staff')

@section('title', 'Vendeurs et boutiques | Commercial OVANIE')

@section('inline_styles')
    .vendor-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}
    .vendor-kpi.success .kpi-icon{background:#ecfdf5;color:#059669}.vendor-kpi.warning .kpi-icon{background:#fff7ed;color:#ea580c}
    .vendor-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) 220px auto auto;gap:10px;margin:16px 0}
    .vendor-toolbar input,.vendor-toolbar select{height:42px;border:1px solid #dbe3ed;border-radius:9px;background:#fff;padding:0 12px;color:#233d62;font-size:10.5px;outline:none}
    .vendor-toolbar input:focus,.vendor-toolbar select:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.08)}
    .shop-list{display:grid;gap:14px}
    .shop-card{overflow:hidden;border:1px solid #dfe7f0;border-radius:14px;background:#fff;box-shadow:0 8px 26px rgba(16,38,79,.045)}
    .shop-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:18px 20px;border-bottom:1px solid #edf1f6;background:linear-gradient(180deg,#fff 0%,#fbfcfe 100%)}
    .shop-identity{display:flex;align-items:flex-start;gap:12px;min-width:0}.shop-avatar{width:46px;height:46px;display:grid;place-items:center;flex:0 0 46px;border-radius:12px;background:#fff1e8;color:#f97316}.shop-avatar svg{width:21px}
    .shop-title{min-width:0}.shop-title h2{margin:0;color:#10264f;font-size:14px;line-height:1.3}.shop-owner{display:flex;flex-wrap:wrap;gap:5px 12px;margin-top:5px;color:#728096;font-size:9.4px}.shop-owner span{display:inline-flex;align-items:center;gap:5px}.shop-owner svg{width:13px}
    .shop-state{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;font-size:9px;font-weight:850;white-space:nowrap}.shop-state.ready{background:#eafaf2;color:#047857}.shop-state.pending{background:#fff7e6;color:#9a5700}.shop-state.blocked{background:#fff0ef;color:#c52d2d}
    .shop-card-body{display:grid;grid-template-columns:1.2fr 1fr .8fr;gap:0;padding:0 20px}
    .shop-panel{padding:18px 18px 18px 0}.shop-panel+.shop-panel{padding-left:18px;border-left:1px solid #edf1f6}.shop-panel-label{display:flex;align-items:center;gap:7px;margin-bottom:10px;color:#64748b;font-size:8.5px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.shop-panel-label svg{width:15px;color:#174b8f}
    .shop-primary{color:#17345f;font-size:10.5px;font-weight:800;line-height:1.45}.shop-secondary{margin-top:4px;color:#7b889b;font-size:9.2px;line-height:1.5}
    .status-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.status-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 8px;border-radius:999px;font-size:8.4px;font-weight:850}.status-chip svg{width:13px}.status-chip.success{background:#eafaf2;color:#047857}.status-chip.warning{background:#fff7e6;color:#9a5700}.status-chip.danger{background:#fff0ef;color:#c52d2d}.status-chip.info{background:#eef4ff;color:#075ee8}
    .activity-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.activity-item{padding:10px;border-radius:10px;background:#f7f9fc}.activity-item strong{display:block;color:#10264f;font-size:16px;line-height:1}.activity-item span{display:block;margin-top:5px;color:#8190a3;font-size:8.5px}
    .missing-note{margin-top:10px;padding:9px 10px;border-radius:9px;background:#fff8ed;color:#8c4a0a;font-size:8.8px;line-height:1.45}
    .shop-card-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 20px;border-top:1px solid #edf1f6;background:#fbfcfe}.action-main,.action-secondary{display:flex;flex-wrap:wrap;gap:7px}.shop-card-actions .btn{min-height:36px;padding:0 11px;font-size:9px}
    .vendor-empty{padding:58px 24px;text-align:center}.vendor-empty-icon{width:48px;height:48px;display:grid;place-items:center;margin:0 auto 12px;border-radius:13px;background:#eef4ff;color:#174b8f}.vendor-empty-icon svg{width:23px}.vendor-empty h3{margin:0;color:#10264f;font-size:13px}.vendor-empty p{max-width:420px;margin:6px auto 0;color:#7d899b;font-size:9.7px;line-height:1.55}
    .pagination-row{padding-top:14px}
    @media(max-width:1120px){.vendor-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.vendor-toolbar{grid-template-columns:1fr 200px auto}.vendor-toolbar .reset{grid-column:1/-1;justify-self:start}.shop-card-body{grid-template-columns:1fr 1fr}.shop-panel.activity{grid-column:1/-1;border-left:0;border-top:1px solid #edf1f6;padding-left:0}.activity-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media(max-width:760px){.vendor-kpis{grid-template-columns:1fr 1fr}.vendor-toolbar{grid-template-columns:1fr}.vendor-toolbar input,.vendor-toolbar select,.vendor-toolbar .btn{width:100%}.shop-card-head{flex-direction:column;padding:16px}.shop-card-body{grid-template-columns:1fr;padding:0 16px}.shop-panel,.shop-panel+.shop-panel,.shop-panel.activity{padding:15px 0;border-left:0;border-top:1px solid #edf1f6}.shop-panel:first-child{border-top:0}.activity-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.shop-card-actions{align-items:stretch;flex-direction:column;padding:13px 16px}.action-main,.action-secondary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.shop-card-actions .btn{width:100%}.page-actions .btn{width:100%}}
    @media(max-width:480px){.vendor-kpis{grid-template-columns:1fr}.action-main,.action-secondary,.activity-grid{grid-template-columns:1fr}.shop-owner{display:grid;gap:5px}.shop-state{white-space:normal}}
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Vendeurs et boutiques</h1>
        <p class="page-subtitle">Gérez les boutiques de votre portefeuille, leur localisation, leur préparation logistique et leurs produits.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-orange" href="{{ route('commercial.vendors.create') }}"><i data-lucide="store"></i>Créer un vendeur et sa boutique</a>
    </div>
</div>

<div class="grid kpi-grid vendor-kpis">
    <article class="kpi-card vendor-kpi">
        <div class="kpi-head"><span class="kpi-label">Boutiques suivies</span><span class="kpi-icon"><i data-lucide="store"></i></span></div>
        <div class="kpi-value">{{ $stats['total'] }}</div>
        <div class="kpi-foot">Boutiques de votre portefeuille.</div>
    </article>
    <article class="kpi-card vendor-kpi success">
        <div class="kpi-head"><span class="kpi-label">Prêtes pour la logistique</span><span class="kpi-icon"><i data-lucide="badge-check"></i></span></div>
        <div class="kpi-value">{{ $stats['logistics_ready'] }}</div>
        <div class="kpi-foot">Configuration logistique complète.</div>
    </article>
    <article class="kpi-card vendor-kpi warning">
        <div class="kpi-head"><span class="kpi-label">GPS à enregistrer</span><span class="kpi-icon"><i data-lucide="map-pin-off"></i></span></div>
        <div class="kpi-value">{{ $stats['gps_missing'] }}</div>
        <div class="kpi-foot">Boutiques OVANIE Logistics à localiser.</div>
    </article>
    <article class="kpi-card vendor-kpi">
        <div class="kpi-head"><span class="kpi-label">Boutiques avec produits</span><span class="kpi-icon"><i data-lucide="package-check"></i></span></div>
        <div class="kpi-value">{{ $stats['with_products'] }}</div>
        <div class="kpi-foot">Au moins un produit enregistré.</div>
    </article>
</div>

<form class="vendor-toolbar" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom de boutique, vendeur, commune ou téléphone…">
    <select name="readiness">
        <option value="">Tous les états</option>
        <option value="ready" @selected(request('readiness')==='ready')>Logistique prête</option>
        <option value="gps_missing" @selected(request('readiness')==='gps_missing')>GPS à enregistrer</option>
        <option value="kyc_pending" @selected(request('readiness')==='kyc_pending')>Identité à vérifier</option>
        <option value="no_products" @selected(request('readiness')==='no_products')>Sans produit</option>
    </select>
    <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
    @if(request()->hasAny(['q','readiness']))
        <a class="btn reset" href="{{ route('commercial.vendors.index') }}"><i data-lucide="rotate-ccw"></i>Réinitialiser</a>
    @endif
</form>

@if($shops->isEmpty())
    <section class="card vendor-empty">
        <span class="vendor-empty-icon"><i data-lucide="store"></i></span>
        <h3>Aucune boutique trouvée</h3>
        <p>Modifiez les critères de recherche ou créez un nouveau vendeur et sa boutique.</p>
    </section>
@else
    <section class="shop-list">
        @foreach($shops as $shop)
            @php
                $readiness = $shop->commercial_readiness ?? [];
                $locationReady = (bool) ($readiness['location_ready'] ?? false);
                $publicationReady = (bool) ($readiness['publication_ready'] ?? false);
                $missing = collect($readiness['missing'] ?? [])->filter();
                $phoneRaw = (string) ($shop->user?->phone ?: $shop->whatsapp);
                $phone = preg_replace('/\D+/', '', $phoneRaw);
                $stateClass = $publicationReady ? 'ready' : ($locationReady ? 'pending' : 'blocked');
                $stateLabel = $publicationReady ? 'Boutique prête' : ($locationReady ? 'Configuration à terminer' : 'Position à enregistrer');
            @endphp

            <article class="shop-card">
                <header class="shop-card-head">
                    <div class="shop-identity">
                        <span class="shop-avatar"><i data-lucide="store"></i></span>
                        <div class="shop-title">
                            <h2>{{ $shop->name }}</h2>
                            <div class="shop-owner">
                                <span><i data-lucide="user-round"></i>{{ $shop->user?->name ?: 'Responsable non renseigné' }}</span>
                                @if($shop->user?->email || $shop->business_email)<span><i data-lucide="mail"></i>{{ $shop->user?->email ?: $shop->business_email }}</span>@endif
                                @if($phoneRaw)<span><i data-lucide="phone"></i>{{ $phoneRaw }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <span class="shop-state {{ $stateClass }}"><i data-lucide="{{ $publicationReady ? 'circle-check-big' : ($locationReady ? 'clock-3' : 'map-pin-off') }}"></i>{{ $stateLabel }}</span>
                </header>

                <div class="shop-card-body">
                    <section class="shop-panel">
                        <div class="shop-panel-label"><i data-lucide="map-pin"></i>Localisation</div>
                        <div class="shop-primary">{{ collect([$shop->district, $shop->commune])->filter()->implode(', ') ?: 'Localisation à compléter' }}</div>
                        <div class="shop-secondary">{{ collect([$shop->city, $shop->region])->filter()->unique()->implode(' · ') ?: 'Ville non renseignée' }}</div>
                        @if($shop->landmark)<div class="shop-secondary">Repère : {{ $shop->landmark }}</div>@endif
                        <div class="status-row">
                            <span class="status-chip {{ $locationReady ? 'success' : 'danger' }}"><i data-lucide="map-pin-check"></i>{{ $locationReady ? 'GPS enregistré' : 'GPS manquant' }}</span>
                            <span class="status-chip {{ $shop->kyc_status === 'verified' ? 'success' : 'warning' }}"><i data-lucide="shield-check"></i>{{ $shop->kyc_status === 'verified' ? 'Identité vérifiée' : 'Identité en attente' }}</span>
                        </div>
                    </section>

                    <section class="shop-panel">
                        <div class="shop-panel-label"><i data-lucide="truck"></i>Logistique</div>
                        <div class="shop-primary">{{ $shop->logistics_mode_label }}</div>
                        <div class="shop-secondary">{{ $shop->logistics_status === 'ready' ? 'Configuration prête' : 'Configuration à compléter' }}</div>
                        <div class="shop-secondary">{{ $shop->geo_status_label }}</div>
                        <div class="status-row">
                            <span class="status-chip {{ $publicationReady ? 'success' : 'warning' }}"><i data-lucide="shopping-bag"></i>{{ $publicationReady ? 'Publication autorisée' : 'Publication en attente' }}</span>
                        </div>
                        @if($missing->isNotEmpty())
                            <div class="missing-note">À compléter : {{ $missing->take(4)->implode(', ') }}</div>
                        @endif
                    </section>

                    <section class="shop-panel activity">
                        <div class="shop-panel-label"><i data-lucide="chart-no-axes-column"></i>Activité</div>
                        <div class="activity-grid">
                            <div class="activity-item"><strong>{{ $shop->products_count }}</strong><span>Produits</span></div>
                            <div class="activity-item"><strong>{{ $shop->order_items_count }}</strong><span>Lignes commandées</span></div>
                        </div>
                    </section>
                </div>

                <footer class="shop-card-actions">
                    <div class="action-main">
                        @if($shop->usesOvanieLogistics())
                            <a class="btn {{ $locationReady ? '' : 'btn-orange' }}" href="{{ route('commercial.vendors.location.edit', $shop) }}"><i data-lucide="map-pin-check"></i>{{ $locationReady ? 'Vérifier la position' : 'Enregistrer le GPS' }}</a>
                        @endif
                        <a class="btn" href="{{ route('commercial.products.quick.create', ['shop_id' => $shop->id]) }}"><i data-lucide="package-plus"></i>Ajouter des produits</a>
                    </div>
                    <div class="action-secondary">
                        <a class="btn" href="{{ route('commercial.products.index', ['shop_id' => $shop->id]) }}"><i data-lucide="package"></i>Voir les produits</a>
                        @if($phone)<a class="btn" href="tel:+{{ $phone }}"><i data-lucide="phone"></i>Appeler</a>@endif
                    </div>
                </footer>
            </article>
        @endforeach
    </section>

    @if($shops->hasPages())
        <div class="pagination-row">
            <span>{{ $shops->firstItem() }}–{{ $shops->lastItem() }} sur {{ $shops->total() }}</span>
            <div class="pagination-actions">
                @if($shops->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $shops->previousPageUrl() }}">Précédent</a>@endif
                @if($shops->hasMorePages())<a class="btn" href="{{ $shops->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif
            </div>
        </div>
    @endif
@endif
@endsection
