@section('styles')
<style>
    :root{--ov-navy:#0b1736;--ov-blue:#1a56e8;--ov-orange:#ff5a00;--ov-line:#d5dbe7;--ov-bg:#f3f4f6;--ov-muted:#64748b;--ov-green:#0f9f5f;--ov-red:#c81e1e}
    .azp,.azp *{box-sizing:border-box}.azp{max-width:1440px;margin:0 auto;padding:18px 22px 36px;color:var(--ov-navy);font-family:inherit}.azp a{text-decoration:none}.azp-top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:14px}.azp-breadcrumb{display:flex;align-items:center;gap:8px;color:#526274;font-size:13px;font-weight:800}.azp-breadcrumb strong{color:#111827}.azp-top-actions{display:flex;align-items:center;gap:10px}.azp-btn{border:1px solid #cfd7e6;background:#fff;color:#111827;border-radius:8px;min-height:42px;padding:10px 15px;font-size:14px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.15s;white-space:nowrap}.azp-btn:hover{border-color:#9aa6b9;box-shadow:0 4px 14px rgba(15,23,42,.08)}.azp-btn.primary{border-color:#f97316;background:#ff5a00;color:#fff;box-shadow:0 10px 22px rgba(255,90,0,.18)}.azp-btn.blue{background:var(--ov-blue);border-color:var(--ov-blue);color:#fff}.azp-btn.ghost{background:#f8fafc}.azp-btn.danger{color:var(--ov-red)}.azp-page-title{background:#fff;border:1px solid var(--ov-line);border-radius:12px;margin-bottom:16px;box-shadow:0 8px 22px rgba(15,23,42,.05);overflow:hidden}.azp-title-main{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:22px 24px;border-bottom:1px solid #edf1f7;background:linear-gradient(180deg,#fff,#fbfcff)}.azp-title-main h1{margin:0;color:#111827;font-size:28px;line-height:1.1;font-weight:1000;letter-spacing:-.035em}.azp-title-main p{margin:8px 0 0;color:#526274;font-size:14px;line-height:1.45;font-weight:720}.azp-status-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid #fed7aa;background:#fff7ed;color:#c2410c;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:1000}.azp-search-line{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:16px 24px;background:#f8fafc}.azp-search-fake{display:flex;align-items:center;gap:12px;border:1px solid #cfd7e6;background:#fff;border-radius:8px;min-height:46px;padding:0 14px;color:#64748b;font-weight:800}.azp-search-fake i{width:19px;height:19px;color:#334155}.azp-layout{display:grid;grid-template-columns:248px minmax(0,1fr)316px;gap:16px;align-items:start}.azp-menu{position:sticky;top:92px;background:#fff;border:1px solid var(--ov-line);border-radius:12px;box-shadow:0 8px 22px rgba(15,23,42,.05);overflow:hidden}.azp-menu-head{padding:15px 16px;border-bottom:1px solid #edf1f7;background:#fbfcff}.azp-menu-head strong{display:block;color:#111827;font-size:14px;font-weight:1000}.azp-menu-head small{display:block;margin-top:3px;color:#64748b;font-weight:750}.azp-menu a{display:flex;align-items:center;gap:10px;padding:13px 16px;color:#334155;font-size:13px;font-weight:900;border-left:4px solid transparent;border-bottom:1px solid #f1f4f8}.azp-menu a i{width:18px;height:18px;color:#607089}.azp-menu a:hover,.azp-menu a.is-active{background:#fff7ed;border-left-color:var(--ov-orange);color:#c2410c}.azp-main{min-width:0}.azp-card{background:#fff;border:1px solid var(--ov-line);border-radius:12px;margin-bottom:16px;box-shadow:0 8px 22px rgba(15,23,42,.05);overflow:hidden}.azp-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:18px 20px;border-bottom:1px solid #edf1f7;background:#fff}.azp-card-title{display:flex;gap:13px;align-items:flex-start}.azp-card-icon{width:38px;height:38px;border-radius:8px;background:#eff6ff;color:var(--ov-blue);display:grid;place-items:center;flex:0 0 38px}.azp-card-icon.orange{background:#fff7ed;color:#ea580c}.azp-card-title h2{margin:0;color:#111827;font-size:19px;font-weight:1000;letter-spacing:-.02em}.azp-card-title p{margin:5px 0 0;color:#64748b;font-size:13px;line-height:1.45;font-weight:700}.azp-required{color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:1000;white-space:nowrap}.azp-body{padding:20px}.azp-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.azp-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.azp-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.azp-field{display:flex;flex-direction:column;gap:7px;min-width:0}.azp-field.full{grid-column:1/-1}.azp-field label{display:flex;align-items:center;gap:6px;color:#111827;font-size:13px;font-weight:1000}.azp-star{color:var(--ov-orange)}.azp-hint{color:#64748b;font-size:12px;line-height:1.42;font-weight:700}.azp-input,.azp-select,.azp-textarea{width:100%;border:1px solid #cfd7e6;border-radius:8px;background:#fff;color:#111827;padding:12px 13px;font-size:14px;font-weight:750;outline:none;transition:.15s}.azp-input::placeholder,.azp-textarea::placeholder{color:#8b97a8}.azp-textarea{min-height:118px;resize:vertical;line-height:1.5}.azp-input:focus,.azp-select:focus,.azp-textarea:focus{border-color:#ff9900;box-shadow:0 0 0 3px rgba(255,153,0,.22)}.azp-money{position:relative}.azp-money .azp-input{padding-right:66px}.azp-suffix{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#64748b;font-size:11px;font-weight:1000}.azp-divider{border:0;border-top:1px solid #edf1f7;margin:18px 0}.azp-mini-title{margin:0 0 12px;color:#111827;font-size:16px;font-weight:1000}.azp-option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.azp-option{position:relative;display:flex;gap:12px;align-items:flex-start;border:1px solid #cfd7e6;background:#f8fafc;border-radius:10px;padding:15px;cursor:pointer;transition:.15s}.azp-option input{margin-top:3px;accent-color:#ff5a00}.azp-option strong{display:block;color:#111827;font-size:14px;font-weight:1000}.azp-option small{display:block;color:#64748b;font-size:12px;line-height:1.4;margin-top:4px;font-weight:720}.azp-option:has(input:checked){border-color:#ff9900;background:#fff7ed;box-shadow:0 0 0 3px rgba(255,153,0,.13)}.azp-offer-box{border:1px solid #bfdbfe;background:#eff6ff;border-radius:10px;padding:14px}.azp-offer-box h3{margin:0 0 8px;color:#1e3a8a;font-size:15px;font-weight:1000}.azp-offer-box p{margin:0;color:#1e3a8a;font-size:12.5px;line-height:1.45;font-weight:750}.azp-offer-box.orange{border-color:#fed7aa;background:#fff7ed}.azp-offer-box.orange h3,.azp-offer-box.orange p{color:#9a3412}.azp-offer-box.green{border-color:#bbf7d0;background:#ecfdf5}.azp-offer-box.green h3,.azp-offer-box.green p{color:#065f46}.azp-calcs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}.azp-calc{border:1px solid #dbe3ee;background:#fbfcff;border-radius:10px;padding:14px}.azp-calc span{display:block;color:#64748b;font-size:11px;font-weight:1000;letter-spacing:.04em;text-transform:uppercase}.azp-calc strong{display:block;margin-top:6px;color:#111827;font-size:21px;font-weight:1000}.azp-calc.blue strong{color:var(--ov-blue)}.azp-calc.green strong{color:var(--ov-green)}.azp-table-tools{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.azp-row-list{display:grid;gap:10px}.azp-attribute-row{display:grid;grid-template-columns:1fr 1.4fr 110px 42px;gap:10px;align-items:center}.azp-zone-row{display:grid;grid-template-columns:1fr 1fr 130px 125px 42px;gap:10px;align-items:center}.azp-icon-btn{width:42px;height:42px;display:grid;place-items:center;border:1px solid #cfd7e6;background:#fff;border-radius:8px;color:#64748b;cursor:pointer}.azp-icon-btn:hover{background:#f8fafc}.azp-icon-btn.danger{color:#dc2626}.azp-add{border:1px dashed #94a3b8;background:#f8fafc;color:#1d4ed8;border-radius:8px;padding:10px 13px;font-size:13px;font-weight:1000;cursor:pointer}.azp-hidden{display:none!important}.azp-delay-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.azp-delay{display:block;border:1px solid #cfd7e6;background:#fff;border-radius:10px;padding:13px;text-align:center;cursor:pointer}.azp-delay input{accent-color:#ff5a00}.azp-delay strong{display:block;margin-top:5px;color:#111827;font-size:18px;font-weight:1000}.azp-delay small{display:block;color:#64748b;font-size:12px;font-weight:800}.azp-delay:has(input:checked){border-color:#ff9900;background:#fff7ed;box-shadow:0 0 0 3px rgba(255,153,0,.13)}.azp-check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.azp-upload-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.azp-upload{border:1px dashed #94a3b8;background:#f8fafc;border-radius:12px;min-height:152px;padding:18px;display:grid;place-items:center;text-align:center;color:#475569;font-weight:850}.azp-upload strong{display:block;color:#111827;margin-bottom:6px}.azp-upload input{max-width:100%;margin-top:12px}.azp-previews{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-top:12px}.azp-previews img{width:100%;height:92px;border:1px solid #dbe3ee;border-radius:8px;object-fit:cover}.azp-video-preview video{width:100%;max-height:260px;margin-top:12px;border-radius:10px;background:#020617}.azp-current-media{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}.azp-current-media img{width:84px;height:84px;border:1px solid #dbe3ee;border-radius:8px;object-fit:cover}.azp-side{position:sticky;top:92px;display:grid;gap:14px}.azp-side-card{background:#fff;border:1px solid var(--ov-line);border-radius:12px;padding:16px;box-shadow:0 8px 22px rgba(15,23,42,.05)}.azp-side-card h3{margin:0 0 8px;color:#111827;font-size:17px;font-weight:1000}.azp-side-card p{margin:0;color:#64748b;font-size:13px;line-height:1.45;font-weight:720}.azp-product-preview{display:flex;gap:12px;align-items:center;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:12px;margin-top:12px}.azp-preview-img{width:62px;height:62px;border-radius:8px;background:linear-gradient(135deg,#dbeafe,#fff7ed);display:grid;place-items:center;color:var(--ov-blue);flex:0 0 62px;overflow:hidden}.azp-preview-img img{width:100%;height:100%;object-fit:cover}.azp-product-preview strong{display:block;color:#111827;font-size:14px;font-weight:1000}.azp-product-preview small{display:block;color:#64748b;font-size:12px;font-weight:750;margin-top:3px}.azp-progress{height:10px;background:#e5eaf2;border-radius:999px;overflow:hidden;margin:12px 0}.azp-progress span{display:block;height:100%;width:0;background:linear-gradient(90deg,#10b981,#1a56e8);transition:.2s}.azp-side-list{display:grid;gap:10px;margin-top:12px}.azp-side-list div{display:flex;justify-content:space-between;gap:12px;color:#64748b;font-size:13px;font-weight:850}.azp-side-list strong{color:#111827}.azp-sticky-actions{display:grid;gap:10px;margin-top:12px}.azp-note-list{margin:12px 0 0;padding:0;list-style:none;display:grid;gap:10px}.azp-note-list li{display:flex;gap:8px;color:#475569;font-size:12.5px;font-weight:750;line-height:1.42}.azp-note-list i{width:16px;height:16px;color:#10b981;flex:0 0 16px}.azp-alert{border-radius:10px;padding:13px 15px;margin-bottom:14px;font-weight:800;line-height:1.45}.azp-alert.danger{background:#fff1f2;border:1px solid #fecdd3;color:#991b1b}.azp-alert.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46}@media(max-width:1200px){.azp-layout{grid-template-columns:220px 1fr}.azp-side{grid-column:1/-1;position:static;grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:860px){.azp{padding:14px}.azp-layout{grid-template-columns:1fr}.azp-menu{position:static}.azp-side{grid-template-columns:1fr}.azp-title-main,.azp-top{flex-direction:column;align-items:flex-start}.azp-search-line{grid-template-columns:1fr}.azp-grid-2,.azp-grid-3,.azp-grid-4,.azp-option-grid,.azp-calcs,.azp-attribute-row,.azp-zone-row,.azp-delay-grid,.azp-check-grid,.azp-upload-grid{grid-template-columns:1fr}.azp-previews{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
@endsection

@section('content')
@php
    use Illuminate\Support\Facades\Route;
    use App\Models\Product;

    $isEdit = isset($product) && $product;
    $safeRoute = function (array $names, string $fallback, $params = null) {
        foreach ($names as $name) {
            if (Route::has($name)) {
                return $params !== null ? route($name, $params) : route($name);
            }
        }
        return url($fallback);
    };
    $shop = auth()->user()?->shop;
    $actionUrl = $isEdit
        ? $safeRoute(['vendor.products.update'], '/vendeur/products/' . ($product->id ?? '') , $product)
        : $safeRoute(['vendor.products.store'], '/vendeur/products');
    $productsUrl = $safeRoute(['vendor.products'], '/vendeur/products');
    $shopUrl = $safeRoute(['vendor.shop.profile'], '/vendeur/shop-profile');
    $shopName = $shop?->name ?: (auth()->user()?->name ?: 'Ma boutique');
    $shopCity = $shop?->city ?: ($shop?->commune ?: 'Abidjan');
    $shopCountry = $shop?->country ?: "Côte d'Ivoire";
    $shopLogistics = 'ovanie';
    $field = fn($name, $default = null) => old($name, $isEdit ? data_get($product, $name, $default) : $default);
    $defaultDeliveryMode = 'ovanie';
    $memberSince = $shop?->created_at ? $shop->created_at->translatedFormat('d M. Y') : now()->translatedFormat('d M. Y');
    $publishedProducts = 0;
    try { if ($shop) { $publishedProducts = Product::where('shop_id', $shop->id)->notArchived()->count(); } } catch (\Throwable $e) { $publishedProducts = 0; }
    $units = $units ?? ['sac','tonne','m3','m2','ml','piece','palette','rouleau','seau','carton','paquet','barre','bidon','kg','litre'];
    $unitLabels = ['sac'=>'Sac','tonne'=>'Tonne','m3'=>'M³','m2'=>'M²','ml'=>'Mètre linéaire','piece'=>'Pièce','palette'=>'Palette','rouleau'=>'Rouleau','seau'=>'Seau','carton'=>'Carton','paquet'=>'Paquet','barre'=>'Barre','bidon'=>'Bidon','kg'=>'Kg','litre'=>'Litre'];
    $stateLabels = ['new'=>'Neuf','reconditioned'=>'Reconditionné','used'=>'Occasion'];
    $availabilityLabels = ['in_stock'=>'En stock immédiat','on_order'=>'Disponible sous commande','preorder'=>'Précommande','out_of_stock'=>'Rupture temporaire'];
    $handlingOptions = ['Manutentionnaires','Camion benne','Camion grue','Chariot élévateur','Produit palettisé','Produit en vrac'];
    $attributesRaw = old('product_attributes', $isEdit ? ($product->product_attributes ?? []) : []);
    if (is_string($attributesRaw)) { $attributesRaw = json_decode($attributesRaw, true) ?: []; }
    $oldAttributes = collect($attributesRaw)->filter(fn($row) => is_array($row));
    if ($oldAttributes->isEmpty()) {
        $oldAttributes = collect([
            ['label' => 'Format / diamètre / puissance', 'value' => '', 'unit' => ''],
            ['label' => 'Norme / classe / certification', 'value' => '', 'unit' => ''],
            ['label' => 'Couleur / finition / matière', 'value' => '', 'unit' => ''],
        ]);
    }
    $selectedHandling = collect(old('handling_options', $isEdit ? ($product->handling_options ?? []) : []));
    if ($selectedHandling->isEmpty() && $isEdit && is_string($product->handling_options ?? null)) { $selectedHandling = collect(json_decode($product->handling_options, true) ?: []); }
    $negotiable = old('is_negotiable', $isEdit ? (int) $product->is_negotiable : 0);
    $titleText = $isEdit ? 'Modifier la fiche produit' : 'Créer une nouvelle fiche produit';
@endphp

<main class="azp" id="azpPage">
    <div class="azp-top">
        <div class="azp-breadcrumb"><span>Catalogue</span><i data-lucide="chevron-right"></i><strong>{{ $titleText }}</strong></div>
        <div class="azp-top-actions"><a href="{{ $productsUrl }}" class="azp-btn ghost"><i data-lucide="arrow-left"></i> Retour au catalogue</a></div>
    </div>

    <section class="azp-page-title">
        <div class="azp-title-main">
            <div>
                <h1>{{ $titleText }}</h1>
                <p>Formulaire inspiré Seller Central : informations vitales, offre, négociation, livraison, conformité, images et vidéo.</p>
            </div>
            <span class="azp-status-pill"><i data-lucide="shield-check"></i> OVANIE Seller Central</span>
        </div>
        <div class="azp-search-line">
            <div class="azp-search-fake"><i data-lucide="search"></i><span>Avant de créer : vérifiez que le produit n’existe pas déjà avec son nom, référence, marque ou code.</span></div>
            <button class="azp-btn" type="button" data-scroll-to="identity"><i data-lucide="plus-circle"></i> Créer la fiche</button>
        </div>
    </section>

    @if ($errors->any())
        <div class="azp-alert danger"><strong>Veuillez corriger :</strong><ul style="margin:8px 0 0 18px;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if(session('success'))<div class="azp-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="azp-alert danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ $actionUrl }}" enctype="multipart/form-data" class="azp-layout" id="azpForm">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="transport" value="{{ $field('transport', 0) }}">
        <input type="hidden" name="price_p1" id="azpPriceP1" value="{{ $field('price_p1') }}">
        <input type="hidden" name="price_p2" id="azpPriceP2" value="{{ $field('price_p2') }}">

        <nav class="azp-menu" aria-label="Sections fiche produit">
            <div class="azp-menu-head"><strong>Sections de la fiche</strong><small>Comme une page Seller Central</small></div>
            <a href="#identity" class="is-active"><i data-lucide="badge-info"></i> Informations vitales</a>
            <a href="#description"><i data-lucide="list-checks"></i> Description</a>
            <a href="#offer"><i data-lucide="badge-dollar-sign"></i> Offre de vente</a>
            <a href="#negotiation"><i data-lucide="messages-square"></i> Négociation</a>
            <a href="#details"><i data-lucide="ruler"></i> Détails BTP</a>
            <a href="#fulfillment"><i data-lucide="truck"></i> Livraison</a>
            <a href="#compliance"><i data-lucide="shield-alert"></i> Sécurité</a>
            <a href="#media"><i data-lucide="image-plus"></i> Images & vidéo</a>
        </nav>

        <div class="azp-main">
            <section class="azp-card" id="identity">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon"><i data-lucide="badge-info"></i></span><div><h2>Informations vitales du produit</h2><p>Les champs qui identifient clairement votre produit sur OVANIE.</p></div></div><span class="azp-required">Obligatoire</span></header>
                <div class="azp-body">
                    <div class="azp-grid-2">
                        <div class="azp-field"><label>Nom du produit <span class="azp-star">*</span></label><input class="azp-input" name="name" id="azpName" value="{{ $field('name') }}" required placeholder="Ex : Ciment CPJ 42.5 - Sac 50 kg"></div>
                        <div class="azp-field"><label>Référence vendeur / SKU</label><input class="azp-input" name="sku" value="{{ $field('sku') }}" placeholder="Ex : CIM-CPJ-42-50KG"><small class="azp-hint">Référence interne pour retrouver le produit dans vos commandes.</small></div>
                        <div class="azp-field"><label>Catégorie <span class="azp-star">*</span></label><select class="azp-select" name="category_id" required><option value="">Choisir une catégorie</option>@foreach($categories ?? [] as $category)<option value="{{ $category->id }}" @selected((string) $field('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                        <div class="azp-field"><label>Marque</label><input class="azp-input" name="brand" value="{{ $field('brand') }}" placeholder="Ex : Lafarge, Schneider, Total Tools"></div>
                        <div class="azp-field"><label>Fabricant / origine</label><input class="azp-input" name="origin_country" value="{{ $field('origin_country', "Côte d'Ivoire") }}" placeholder="Pays d’origine ou fabricant"></div>
                        <div class="azp-field"><label>État du produit</label><select class="azp-select" name="product_state">@foreach($stateLabels as $key => $label)<option value="{{ $key }}" @selected($field('product_state','new') === $key)>{{ $label }}</option>@endforeach</select></div>
                        <div class="azp-field"><label>Statut commercial</label><select class="azp-select" name="sale_type" required><option value="normal" @selected(in_array(strtolower((string) $field('sale_type','normal')), ['normal','vente normale']))>Vente normale</option><option value="promo" @selected(strtolower((string) $field('sale_type')) === 'promo')>Promotion</option><option value="vente flash" @selected(strtolower((string) $field('sale_type')) === 'vente flash')>Vente flash</option></select><small class="azp-hint">Black Friday et boost restent pilotés depuis OVANIE ou depuis le catalogue.</small></div>
                        <div class="azp-field"><label>Type produit interne</label><input class="azp-input" name="type" value="{{ $field('type') }}" placeholder="Optionnel"></div>
                    </div>
                </div>
            </section>

            <section class="azp-card" id="description">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon"><i data-lucide="list-checks"></i></span><div><h2>Description et présentation client</h2><p>Un bon titre et une bonne description augmentent la conversion.</p></div></div><span class="azp-required">Visible fiche produit</span></header>
                <div class="azp-body">
                    <div class="azp-field full"><label>Description courte</label><input class="azp-input" name="short_description" value="{{ $field('short_description') }}" maxlength="255" placeholder="Résumé affiché dans les cartes produit"></div>
                    <div class="azp-field full" style="margin-top:16px"><label>Description détaillée <span class="azp-star">*</span></label><textarea class="azp-textarea" name="description" id="azpDescription" required placeholder="Décrivez les usages, avantages, normes, conditions de vente, précautions et contenu du produit.">{{ $field('description') }}</textarea></div>
                </div>
            </section>

            <section class="azp-card" id="offer">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon orange"><i data-lucide="badge-dollar-sign"></i></span><div><h2>Offre de vente</h2><p>Prix, stock, unité et disponibilité réelle.</p></div></div><span class="azp-required">Prix & stock</span></header>
                <div class="azp-body">
                    <div class="azp-grid-3">
                        <div class="azp-field"><label>Prix vendeur FCFA <span class="azp-star">*</span></label><div class="azp-money"><input class="azp-input" id="azpPrice" type="number" name="price" value="{{ $field('price') }}" min="1" step="1" required><span class="azp-suffix">FCFA</span></div></div>
                        <div class="azp-field"><label>Prix promotionnel</label><div class="azp-money"><input class="azp-input" type="number" name="promo_price" value="{{ $field('promo_price') }}" min="0" step="1"><span class="azp-suffix">FCFA</span></div></div>
                        <div class="azp-field"><label>Stock disponible <span class="azp-star">*</span></label><input class="azp-input" id="azpStock" type="number" name="stock" value="{{ $field('stock', 0) }}" min="0" step="1" required></div>
                        <div class="azp-field"><label>Unité de vente <span class="azp-star">*</span></label><select class="azp-select" name="unit" required>@foreach($units as $unit)<option value="{{ $unit }}" @selected($field('unit','piece') === $unit)>{{ $unitLabels[$unit] ?? ucfirst($unit) }}</option>@endforeach</select></div>
                        <div class="azp-field"><label>Libellé personnalisé</label><input class="azp-input" name="unit_label" value="{{ $field('unit_label') }}" placeholder="Ex : Sac 50 kg, carton 1,44 m²"></div>
                        <div class="azp-field"><label>Quantité minimum <span class="azp-star">*</span></label><input class="azp-input" type="number" name="min_order_quantity" value="{{ $field('min_order_quantity', 1) }}" min="1" step="1" required></div>
                        <div class="azp-field"><label>Disponibilité</label><select class="azp-select" name="availability_status">@foreach($availabilityLabels as $key => $label)<option value="{{ $key }}" @selected($field('availability_status','in_stock') === $key)>{{ $label }}</option>@endforeach</select></div>
                        <div class="azp-field"><label>Délai d’approvisionnement</label><input class="azp-input" name="supply_delay" value="{{ $field('supply_delay') }}" placeholder="Ex : 24h, 48h, 3 jours, sur commande"></div>
                        <div class="azp-field"><label>Conditionnement</label><input class="azp-input" name="packaging" value="{{ $field('packaging') }}" placeholder="Ex : palette 64 sacs, carton 12 pièces"></div>
                    </div>
                    <div class="azp-calcs"><div class="azp-calc"><span>Commission OVANIE estimée</span><strong id="azpCommission">0 FCFA</strong></div><div class="azp-calc blue"><span>Prix client estimé</span><strong id="azpClientPrice">0 FCFA</strong></div><div class="azp-calc green"><span>Net vendeur estimé</span><strong id="azpSellerNet">0 FCFA</strong></div></div>
                    <div class="azp-offer-box" style="margin-top:14px"><h3>Calcul indicatif</h3><p>Le calcul est basé sur une commission estimée de 5%. Le montant final peut varier selon les règles OVANIE, promotions et statut vendeur.</p></div>
                </div>
            </section>

            <section class="azp-card" id="negotiation">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon orange"><i data-lucide="messages-square"></i></span><div><h2>Négociation OVANIE</h2><p>Permettez au client de proposer un prix. Le bloc n’apparaît pas si cette option est désactivée.</p></div></div><span class="azp-required">Optionnel</span></header>
                <div class="azp-body">
                    <input type="hidden" name="delivery_mode" value="ovanie">
                    <div id="azpOvaniePanel" style="margin-top:16px"><div class="azp-offer-box green"><h3>OVANIE Logistics obligatoire</h3><p>OVANIE organise toute la livraison. Renseignez uniquement les poids, volumes, contraintes et informations de retrait utiles au calcul.</p></div></div>
                    <hr class="azp-divider"><div class="azp-grid-4"><div class="azp-field"><label>Poids kg</label><input class="azp-input" type="number" name="weight_kg" value="{{ $field('weight_kg') }}" min="0" step="0.01"></div><div class="azp-field"><label>Longueur cm</label><input id="azpLength" class="azp-input" type="number" name="length_cm" value="{{ $field('length_cm') }}" min="0" step="0.01"></div><div class="azp-field"><label>Largeur cm</label><input id="azpWidth" class="azp-input" type="number" name="width_cm" value="{{ $field('width_cm') }}" min="0" step="0.01"></div><div class="azp-field"><label>Hauteur cm</label><input id="azpHeight" class="azp-input" type="number" name="height_cm" value="{{ $field('height_cm') }}" min="0" step="0.01"></div><div class="azp-field"><label>Volume m³</label><input id="azpVolume" class="azp-input" type="number" name="volume_m3" value="{{ $field('volume_m3') }}" min="0" step="0.0001"></div><div class="azp-field"><label>Ville retrait</label><input class="azp-input" name="pickup_city" value="{{ $field('pickup_city',$shopCity) }}"></div><div class="azp-field"><label>Commune retrait</label><input class="azp-input" name="pickup_commune" value="{{ $field('pickup_commune',$shop?->commune) }}"></div><div class="azp-field"><label>Adresse / repère</label><input class="azp-input" name="pickup_address" value="{{ $field('pickup_address',$shop?->address) }}"></div></div>
                </div>
            </section>

            <section class="azp-card" id="compliance">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon orange"><i data-lucide="shield-alert"></i></span><div><h2>Sécurité, manutention et conformité</h2><p>Informations utiles pour le client, la livraison et la protection OVANIE.</p></div></div><span class="azp-required">Conformité</span></header>
                <div class="azp-body">
                    <div class="azp-check-grid"><label class="azp-option"><input type="checkbox" name="fragile" value="1" @checked($field('fragile'))><span><strong>Produit fragile</strong><small>Carrelage, verre, sanitaire, éclairage, peinture.</small></span></label><label class="azp-option"><input type="checkbox" name="requires_unloading" value="1" @checked($field('requires_unloading'))><span><strong>Déchargement requis</strong><small>Prévoir une aide au déchargement chantier.</small></span></label><label class="azp-option"><input type="checkbox" name="fast_delivery" value="1" @checked($field('fast_delivery'))><span><strong>Produit prêt rapidement</strong><small>Disponible pour une prise en charge rapide.</small></span></label>@foreach($handlingOptions as $option)<label class="azp-option"><input type="checkbox" name="handling_options[]" value="{{ $option }}" @checked($selectedHandling->contains($option))><span><strong>{{ $option }}</strong><small>Information utile pour organiser le transport.</small></span></label>@endforeach</div>
                    <div class="azp-field full" style="margin-top:16px"><label>Détails de déchargement</label><input class="azp-input" name="unloading_instructions" value="{{ $field('unloading_instructions') }}" placeholder="Ex : prévoir 2 manutentionnaires, chariot élévateur ou camion grue"></div>
                </div>
            </section>

            <section class="azp-card" id="media">
                <header class="azp-card-head"><div class="azp-card-title"><span class="azp-card-icon"><i data-lucide="image-plus"></i></span><div><h2>Images et vidéo produit</h2><p>La vidéo est optionnelle. Si elle est absente, aucun bloc vidéo n’apparaît sur la fiche produit.</p></div></div><span class="azp-required">Médias</span></header>
                <div class="azp-body">
                    @if($isEdit && $product->images?->count())<div class="azp-current-media">@foreach($product->images as $image)<img src="{{ asset('storage/'.$image->path) }}" alt="Image produit existante">@endforeach</div>@endif
                    <div class="azp-upload-grid"><label class="azp-upload"><span><strong>Images produit {{ $isEdit ? '' : '*' }}</strong><small>{{ $isEdit ? 'Ajoutez de nouvelles images si nécessaire.' : 'Minimum 1 image. La première image devient principale.' }}</small><input id="azpImages" type="file" name="images[]" accept="image/*" multiple {{ $isEdit ? '' : 'required' }}></span></label><label class="azp-upload"><span><strong>Vidéo produit optionnelle</strong><small>MP4, MOV, WEBM ou M4V · 50 MB maximum</small><input id="azpVideo" type="file" name="product_video" accept="video/mp4,video/webm,video/quicktime,video/x-m4v"></span></label></div>
                    <div id="azpImagePreview" class="azp-previews"></div><div id="azpVideoPreview" class="azp-video-preview"></div>
                    <div class="azp-grid-2" style="margin-top:16px"><div class="azp-field"><label>Lien vidéo externe</label><input class="azp-input" type="url" name="product_video_url" value="{{ $field('product_video_url') }}" placeholder="https://youtube.com/... ou lien MP4"><small class="azp-hint">Optionnel. La vidéo uploadée reste prioritaire.</small></div>@if($isEdit && (($product->product_video_path ?? null) || ($product->product_video_url ?? null)))<label class="azp-option"><input type="checkbox" name="remove_product_video" value="1"><span><strong>Supprimer la vidéo actuelle</strong><small>La fiche produit n’affichera plus de bloc vidéo.</small></span></label>@else<div class="azp-offer-box"><h3>Affichage automatique</h3><p>La fiche produit affiche la section vidéo uniquement si une vidéo ou un lien est renseigné.</p></div>@endif</div>
                </div>
            </section>
        </div>

        <aside class="azp-side">
            <article class="azp-side-card"><h3>Résumé de publication</h3><p>Suivez la complétude avant de publier la fiche.</p><div class="azp-product-preview"><div class="azp-preview-img" id="azpPreviewImage"><i data-lucide="package"></i></div><div><strong id="azpPreviewName">{{ $field('name','Nouveau produit') ?: 'Nouveau produit' }}</strong><small id="azpPreviewPrice">{{ $field('price') ? number_format((float)$field('price'),0,',',' ') . ' FCFA' : 'Prix non renseigné' }}</small></div></div><div class="azp-progress"><span id="azpProgressBar"></span></div><div class="azp-side-list"><div><span>Complétude</span><strong id="azpProgressText">0%</strong></div><div><span>Livraison</span><strong id="azpDeliveryText">OVANIE</strong></div><div><span>Négociation</span><strong id="azpNegotiationText">{{ (string)$negotiable === '1' ? 'Oui' : 'Non' }}</strong></div></div><div class="azp-sticky-actions"><button type="submit" class="azp-btn primary"><i data-lucide="check-circle"></i> {{ $isEdit ? 'Mettre à jour' : 'Publier le produit' }}</button><a href="{{ $productsUrl }}" class="azp-btn ghost">Annuler</a></div></article>
            <article class="azp-side-card"><h3>{{ $shopName }}</h3><p>{{ $shopCity }} · {{ $shopCountry }}</p><div class="azp-side-list"><div><span>Produits publiés</span><strong>{{ $publishedProducts }}</strong></div><div><span>Membre depuis</span><strong>{{ $memberSince }}</strong></div><div><span>Logistique boutique</span><strong>OVANIE</strong></div></div><a href="{{ $shopUrl }}" class="azp-btn blue" style="width:100%;margin-top:14px">Profil boutique</a></article>
            <article class="azp-side-card"><h3>RÃ¨gles Ã  respecter</h3><ul class="azp-note-list"><li><i data-lucide="check"></i>OVANIE Logistics organise la livraison.</li><li><i data-lucide="check"></i>Le vendeur vend uniquement le produit.</li><li><i data-lucide="check"></i>NÃ©gociation visible seulement si activÃ©e.</li><li><i data-lucide="check"></i>VidÃ©o visible seulement si renseignÃ©e.</li></ul></article>
        </aside>
    </form>
</main>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const $ = (s, r=document) => r.querySelector(s); const $$ = (s, r=document) => Array.from(r.querySelectorAll(s)); const form = $('#azpForm');
    const fmt = n => (Number(n || 0)).toLocaleString('fr-FR') + ' FCFA';
    const price = $('#azpPrice'), minPrice = $('#azpMinPrice'), stock = $('#azpStock');
    function calcMoney(){ const p = Number(price?.value || 0); const c = Math.round(p * .05); $('#azpCommission').textContent = fmt(c); $('#azpClientPrice').textContent = fmt(p + c); $('#azpSellerNet').textContent = fmt(p); $('#azpPreviewPrice').textContent = p ? fmt(p) : 'Prix non renseigné'; if($('#azpPriceP1')) $('#azpPriceP1').value = p || ''; if($('#azpPriceP2')) $('#azpPriceP2').value = minPrice?.value ? Math.round((p + Number(minPrice.value || 0))/2) : ''; }
    function toggleNegotiation(){ const enabled = $('input[name="is_negotiable"]:checked')?.value === '1'; $('#azpNegotiationPanel')?.classList.toggle('azp-hidden', !enabled); $('#azpNegotiationText').textContent = enabled ? 'Oui' : 'Non'; if(!enabled && minPrice) minPrice.value = ''; calcMoney(); updateScore(); }
    function deliveryLabel(){ return 'OVANIE'; }
    function toggleDelivery(){ $('#azpOvaniePanel')?.classList.remove('azp-hidden'); $('#azpDeliveryText').textContent = 'OVANIE'; updateScore(); }
    function calcVolume(){ const l = Number($('#azpLength')?.value || 0), w = Number($('#azpWidth')?.value || 0), h = Number($('#azpHeight')?.value || 0); if(l && w && h && $('#azpVolume')) $('#azpVolume').value = ((l*w*h)/1000000).toFixed(4); }
    function updateScore(){ const required = ['input[name="name"]','select[name="category_id"]','input[name="price"]','input[name="stock"]','select[name="unit"]','input[name="min_order_quantity"]','textarea[name="description"]']; let done = 0; required.forEach(s => { if($(s)?.value?.trim()) done++; }); if($('#azpImages')?.files?.length || document.querySelector('.azp-current-media img')) done++; const score = Math.round((done/(required.length+1))*100); $('#azpProgressBar').style.width = score + '%'; $('#azpProgressText').textContent = score + '%'; $('#azpPreviewName').textContent = $('#azpName')?.value || 'Nouveau produit'; }
    function refreshIcons(){ if(window.lucide) window.lucide.createIcons(); }
    function addAttribute(){ const list = $('#azpAttributes'); const i = list.children.length; list.insertAdjacentHTML('beforeend', `<div class="azp-attribute-row"><input class="azp-input" name="product_attributes[${i}][label]" placeholder="Ex : Diamètre"><input class="azp-input" name="product_attributes[${i}][value]" placeholder="Ex : 12"><input class="azp-input" name="product_attributes[${i}][unit]" placeholder="mm"><button type="button" class="azp-icon-btn danger" data-remove-row><i data-lucide="trash-2"></i></button></div>`); refreshIcons(); }
    $('#azpImages')?.addEventListener('change', e => { const box = $('#azpImagePreview'); box.innerHTML = ''; Array.from(e.target.files || []).slice(0,5).forEach((file, idx) => { const img = document.createElement('img'); img.src = URL.createObjectURL(file); box.appendChild(img); if(idx === 0) $('#azpPreviewImage').innerHTML = '', $('#azpPreviewImage').appendChild(img.cloneNode()); }); updateScore(); });
    $('#azpVideo')?.addEventListener('change', e => { const box = $('#azpVideoPreview'); box.innerHTML = ''; const file = e.target.files?.[0]; if(file){ const video = document.createElement('video'); video.controls = true; video.src = URL.createObjectURL(file); box.appendChild(video); }});
    document.addEventListener('click', e => { if(e.target.closest('[data-add-attribute]')) addAttribute(); if(e.target.closest('[data-remove-row]')) e.target.closest('[data-remove-row]').closest('[class$="-row"]')?.remove(); const scroll = e.target.closest('[data-scroll-to]'); if(scroll){ const target = document.getElementById(scroll.dataset.scrollTo); if(target) target.scrollIntoView({behavior:'smooth',block:'start'}); }});
    $$('input[name="is_negotiable"]').forEach(el => el.addEventListener('change', toggleNegotiation)); $$('input[name="delivery_mode"]').forEach(el => el.addEventListener('change', toggleDelivery)); [price,minPrice,stock,$('#azpName'),$('#azpDescription')].forEach(el => el?.addEventListener('input', () => { calcMoney(); updateScore(); })); [$('#azpLength'),$('#azpWidth'),$('#azpHeight')].forEach(el => el?.addEventListener('input', calcVolume));
    const sections = ['identity','description','offer','negotiation','details','fulfillment','compliance','media'].map(id => document.getElementById(id)).filter(Boolean); window.addEventListener('scroll', () => { let current = sections[0]?.id; sections.forEach(sec => { if(sec.getBoundingClientRect().top < 160) current = sec.id; }); $$('.azp-menu a').forEach(a => a.classList.toggle('is-active', a.getAttribute('href') === '#' + current)); }, {passive:true});
    calcMoney(); toggleNegotiation(); toggleDelivery(); updateScore(); refreshIcons();
});
</script>
@endsection
