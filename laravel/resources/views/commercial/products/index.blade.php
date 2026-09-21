@extends('layouts.staff')

@section('title', 'Produits des boutiques | Commercial OVANIE')

@section('content')
<style>
.products-page{max-width:1380px;margin:0 auto;color:#17345f}
.products-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}
.products-page-header h1{margin:0;color:#102f5b;font-size:26px;letter-spacing:-.03em}.products-page-header p{margin:7px 0 0;color:#64748b;font-size:12px;line-height:1.55}
.products-header-actions{display:flex;gap:9px;flex-wrap:wrap}.products-action{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:43px;padding:0 14px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:11px;font-weight:850;text-decoration:none}.products-action:hover{border-color:#f97316;color:#d95d0b}.products-action.primary{border-color:#f97316;background:#f97316;color:#fff;box-shadow:0 8px 18px rgba(249,115,22,.17)}
.products-workspace{display:grid;grid-template-columns:285px minmax(0,1fr);gap:17px;align-items:start}
.shop-panel,.product-panel{background:#fff;border:1px solid #e1e8f0;border-radius:15px;box-shadow:0 12px 34px rgba(15,23,42,.045);overflow:hidden}.shop-panel{position:sticky;top:84px}
.panel-title{padding:17px 18px;border-bottom:1px solid #edf1f5}.panel-title h2{margin:0;color:#17345f;font-size:14px}.panel-title p{margin:5px 0 0;color:#7b899c;font-size:10px}
.shop-search{position:relative;margin-top:12px}.shop-search svg{position:absolute;left:12px;top:12px;width:17px;height:17px;color:#8492a5}.shop-search input{width:100%;height:41px;border:1px solid #d8e1eb;border-radius:9px;padding:0 11px 0 37px;color:#17345f;font-size:11px;outline:none}.shop-search input:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.1)}
.shop-list{max-height:calc(100vh - 235px);overflow:auto;padding:9px}.shop-link{display:flex;align-items:center;gap:10px;margin-bottom:4px;padding:10px;border-radius:10px;color:#294364;text-decoration:none;transition:.15s}.shop-link:hover{background:#f8fafc}.shop-link.active{background:#fff3e9;color:#d95d0b}.shop-icon{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:10px;background:#eef4ff;color:#174985}.shop-link.active .shop-icon{background:#fff}.shop-copy{min-width:0;flex:1}.shop-copy strong,.shop-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.shop-copy strong{font-size:11px}.shop-copy span{margin-top:3px;color:#8793a5;font-size:8.5px}.shop-count{padding:4px 7px;border-radius:999px;background:#edf2f7;color:#52657f;font-size:8px;font-weight:900}.shop-link.active .shop-count{background:#fff;color:#d95d0b}.shop-hidden{display:none!important}
.product-panel-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:17px 18px;border-bottom:1px solid #edf1f5}.product-panel-header h2{margin:0;color:#17345f;font-size:16px}.product-panel-header p{margin:5px 0 0;color:#7b899c;font-size:10px}.product-panel-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.product-search{padding:13px 18px;border-bottom:1px solid #edf1f5;background:#fbfcfe}.product-search form{display:flex;gap:9px}.product-search input{flex:1;min-width:0;height:42px;border:1px solid #d8e1eb;border-radius:9px;padding:0 12px;color:#17345f;font-size:11px;outline:none}.product-search input:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.1)}
.product-list{padding:4px 18px}.product-row{display:grid;grid-template-columns:minmax(260px,1.6fr) 120px 80px 150px 94px;gap:15px;align-items:center;padding:14px 0;border-bottom:1px solid #edf1f5}.product-row:last-child{border-bottom:0}.product-main{display:flex;align-items:center;gap:12px;min-width:0}.product-image{display:grid;place-items:center;flex:0 0 62px;width:62px;height:62px;border:1px solid #edf1f5;border-radius:10px;background:#f8fafc;overflow:hidden;color:#9aa6b7}.product-image img{width:100%;height:100%;object-fit:contain}.product-copy{min-width:0}.product-copy strong,.product-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.product-copy strong{color:#203c64;font-size:11.5px}.product-copy span{margin-top:4px;color:#8793a5;font-size:8.8px}.product-source{display:inline-flex!important;width:max-content;margin-top:6px!important;padding:4px 7px;border-radius:999px;background:#eef4ff;color:#1d4ed8!important;font-size:7.5px!important;font-weight:900}.product-source.field{background:#fff7ed;color:#c2410c!important}.product-cell label{display:none;color:#8793a5;font-size:8px}.product-cell strong{color:#294364;font-size:10.5px}.product-status{display:inline-flex;align-items:center;min-height:25px;padding:0 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:8px;font-weight:900}.product-status.published{background:#dcfce7;color:#15803d}.product-status.pending{background:#fff7ed;color:#c2410c}.product-status.incomplete{background:#fef3c7;color:#92400e}.product-status.out{background:#fee2e2;color:#b91c1c}.product-status.archived{background:#e2e8f0;color:#475569}
.edit-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 11px;border:1px solid #d8e2ed;border-radius:9px;background:#fff;color:#17345f;font-size:9.5px;font-weight:850;text-decoration:none}.edit-button:hover{border-color:#f97316;color:#d95d0b}
.products-empty{padding:70px 20px;text-align:center;color:#7b899c}.products-empty-icon{display:grid;place-items:center;width:54px;height:54px;margin:0 auto 13px;border-radius:14px;background:#eef4ff;color:#174985}.products-empty h3{margin:0;color:#294364;font-size:15px}.products-empty p{max-width:440px;margin:7px auto 15px;font-size:10.5px;line-height:1.55}.pagination-wrap{padding:13px 18px;border-top:1px solid #edf1f5}.mobile-shop-title{display:none}
@media(max-width:1080px){.products-workspace{grid-template-columns:245px minmax(0,1fr)}.product-row{grid-template-columns:minmax(220px,1.5fr) 105px 70px 130px}.product-action-cell{display:none}}
@media(max-width:820px){.products-page-header{flex-direction:column}.products-header-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.products-workspace{grid-template-columns:1fr}.shop-panel{position:static}.shop-list{max-height:250px}.product-panel-header{flex-direction:column}.product-panel-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.product-row{grid-template-columns:1fr 1fr;gap:10px}.product-main{grid-column:1/-1}.product-cell label{display:block;margin-bottom:3px}.product-action-cell{display:block;text-align:right}.mobile-shop-title{display:block}}
@media(max-width:520px){.products-page-header h1{font-size:23px}.products-header-actions,.product-panel-actions{grid-template-columns:1fr}.product-search form{flex-direction:column}.product-search .products-action{width:100%}.product-row{padding:15px 0}.product-image{width:56px;height:56px;flex-basis:56px}}
</style>

<div class="products-page">
    <header class="products-page-header">
        <div>
            <h1>Produits des boutiques</h1>
            <p>Le parcours terrain permet de rechercher un produit OVANIE ou de photographier rapidement un produit absent.</p>
        </div>
        @if($selectedShop)
            <div class="products-header-actions">
                <a class="products-action primary" href="{{ route('commercial.products.quick.create', ['shop_id' => $selectedShop->id]) }}"><i data-lucide="scan-search"></i>Ajout terrain</a>
                <a class="products-action" href="{{ route('commercial.products.create', ['shop_id' => $selectedShop->id]) }}"><i data-lucide="clipboard-list"></i>Formulaire complet</a>
            </div>
        @endif
    </header>

    <div class="products-workspace">
        <aside class="shop-panel">
            <div class="panel-title">
                <h2>Boutiques gérées</h2>
                <p>{{ $shops->count() }} boutique(s) attribuée(s)</p>
                <div class="shop-search"><i data-lucide="search"></i><input id="shopSearch" type="search" placeholder="Rechercher une boutique…"></div>
            </div>
            <nav class="shop-list" aria-label="Boutiques gérées">
                @forelse($shops as $shop)
                    <a class="shop-link {{ $selectedShop?->id === $shop->id ? 'active' : '' }}"
                       href="{{ route('commercial.products.index', ['shop_id' => $shop->id]) }}"
                       data-shop-name="{{ Illuminate\Support\Str::lower($shop->name . ' ' . $shop->user?->name . ' ' . $shop->user?->email) }}">
                        <span class="shop-icon"><i data-lucide="store"></i></span>
                        <span class="shop-copy"><strong>{{ $shop->name }}</strong><span>{{ $shop->user?->name ?: $shop->user?->email }}</span></span>
                        <span class="shop-count">{{ $shop->products_count }}</span>
                    </a>
                @empty
                    <div class="products-empty" style="padding:28px 12px">Aucune boutique attribuée.</div>
                @endforelse
            </nav>
        </aside>

        <section class="product-panel">
            @if($selectedShop)
                <div class="product-panel-header">
                    <div><h2>{{ $selectedShop->name }}</h2><p>{{ $selectedShop->user?->name ?: $selectedShop->user?->email }} · {{ $products->total() }} produit(s)</p></div>
                    <div class="product-panel-actions">
                        <a class="products-action primary" href="{{ route('commercial.products.quick.create', ['shop_id' => $selectedShop->id]) }}"><i data-lucide="plus"></i>Ajouter sur le terrain</a>
                        <a class="products-action" href="{{ route('commercial.products.create', ['shop_id' => $selectedShop->id]) }}"><i data-lucide="file-plus-2"></i>Créer une fiche complète</a>
                    </div>
                </div>

                <div class="product-search">
                    <form method="GET" action="{{ route('commercial.products.index') }}">
                        <input type="hidden" name="shop_id" value="{{ $selectedShop->id }}">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher par nom, référence, marque ou statut…">
                        <button class="products-action" type="submit"><i data-lucide="search"></i>Rechercher</button>
                    </form>
                </div>

                @if($products->isEmpty())
                    <div class="products-empty">
                        <span class="products-empty-icon"><i data-lucide="package-open"></i></span>
                        <h3>Aucun produit dans cette boutique</h3>
                        <p>Commencez par l’ajout terrain : la plateforme vérifiera automatiquement si chaque produit existe déjà dans OVANIE.</p>
                        <a class="products-action primary" href="{{ route('commercial.products.quick.create', ['shop_id' => $selectedShop->id]) }}">Ajouter le premier produit</a>
                    </div>
                @else
                    <div class="product-list">
                        @foreach($products as $product)
                            @php
                                $image = $product->images->sortByDesc(fn ($item) => (int) ($item->is_main || $item->is_primary))->first();
                                $needsCompletion = ! $product->master_product_id && (
                                    (float) $product->weight_kg <= 0 ||
                                    (float) $product->length_cm <= 0 ||
                                    (float) $product->width_cm <= 0 ||
                                    (float) $product->height_cm <= 0 ||
                                    blank($product->description)
                                );
                                $state = match (true) {
                                    $product->is_archived => ['Archivé', 'archived'],
                                    $needsCompletion => ['À compléter', 'incomplete'],
                                    (int) $product->stock <= 0 => ['Rupture de stock', 'out'],
                                    $product->status === 'pending_logistics' => ['Logistique incomplète', 'pending'],
                                    $product->status === 'actif' && $product->is_active => ['Publié', 'published'],
                                    $product->status === 'draft' => ['Brouillon', ''],
                                    default => ['Inactif', ''],
                                };
                            @endphp
                            <article class="product-row">
                                <div class="product-main">
                                    <span class="product-image">@if($image)<img src="{{ $image->card_url }}" alt="{{ $product->name }}">@else<i data-lucide="package"></i>@endif</span>
                                    <span class="product-copy">
                                        <strong>{{ $product->name ?: 'Brouillon sans nom' }}</strong>
                                        <span>{{ $product->sku ?: 'Sans référence' }} · {{ $product->brand ?: 'Marque non renseignée' }}</span>
                                        <span class="product-source {{ $product->master_product_id ? '' : 'field' }}">{{ $product->master_product_id ? 'Catalogue OVANIE' : 'Saisie terrain' }}</span>
                                    </span>
                                </div>
                                <div class="product-cell"><label>Prix</label><strong>{{ $product->price !== null ? number_format((float) $product->price, 0, ',', ' ') . ' FCFA' : 'Non renseigné' }}</strong></div>
                                <div class="product-cell"><label>Stock</label><strong>{{ (int) $product->stock }}</strong></div>
                                <div class="product-cell"><label>Statut</label><span class="product-status {{ $state[1] }}">{{ $state[0] }}</span></div>
                                <div class="product-action-cell"><a class="edit-button" href="{{ route('commercial.products.edit', $product) }}"><i data-lucide="pencil"></i>Modifier</a></div>
                            </article>
                        @endforeach
                    </div>

                    @if($products->hasPages())
                        <div class="pagination-wrap">{{ $products->links() }}</div>
                    @endif
                @endif
            @else
                <div class="products-empty"><span class="products-empty-icon"><i data-lucide="store"></i></span><h3>Aucune boutique disponible</h3><p>Créez ou attribuez d’abord une boutique à ce compte Commercial.</p></div>
            @endif
        </section>
    </div>
</div>

<script>
document.getElementById('shopSearch')?.addEventListener('input', event => {
    const value = event.target.value.trim().toLocaleLowerCase('fr');
    document.querySelectorAll('[data-shop-name]').forEach(row => {
        row.classList.toggle('shop-hidden', !row.dataset.shopName.includes(value));
    });
});
</script>
@endsection
