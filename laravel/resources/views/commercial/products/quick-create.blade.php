@extends('layouts.staff')

@section('title', 'Ajout terrain | Commercial OVANIE')

@section('content')
<style>
.field-page{max-width:1320px;margin:0 auto;color:#17345f}
.field-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}
.field-header-copy{min-width:0}
.field-eyebrow{display:flex;align-items:center;gap:7px;margin-bottom:7px;color:#f97316;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.field-header h1{margin:0;color:#102f5b;font-size:28px;line-height:1.16;letter-spacing:-.03em}
.field-header p{max-width:780px;margin:8px 0 0;color:#64748b;font-size:13px;line-height:1.6}
.field-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.58fr);gap:18px;align-items:start}
.field-main{display:grid;gap:16px}.field-side{display:grid;gap:16px;position:sticky;top:84px}
.field-card{background:#fff;border:1px solid #e1e8f0;border-radius:16px;box-shadow:0 12px 34px rgba(15,23,42,.045);overflow:hidden}
.field-card-body{padding:20px}.field-card-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
.field-step{display:grid;place-items:center;flex:0 0 35px;width:35px;height:35px;border-radius:10px;background:#173e79;color:#fff;font-size:14px;font-weight:900}
.field-card-head h2{margin:0;color:#17345f;font-size:17px;line-height:1.3}.field-card-head p{margin:4px 0 0;color:#718096;font-size:11px;line-height:1.5}
.shop-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}
.field-group label{display:block;margin-bottom:7px;color:#294364;font-size:11px;font-weight:850}
.field-group input,.field-group select{width:100%;height:47px;border:1px solid #d6e0eb;border-radius:10px;padding:0 13px;background:#fff;color:#18345f;font-size:13px;outline:none;transition:.16s}
.field-group input:focus,.field-group select:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.action-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:45px;padding:0 15px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:12px;font-weight:850;text-decoration:none;cursor:pointer;transition:.16s}
.action-button:hover{border-color:#f97316;color:#d95d0b}.action-button.primary{border-color:#f97316;background:#f97316;color:#fff;box-shadow:0 8px 18px rgba(249,115,22,.16)}.action-button.primary:hover{background:#e9650d;color:#fff}.action-button.navy{border-color:#173e79;background:#173e79;color:#fff}.action-button[disabled]{opacity:.55;cursor:not-allowed}
.catalog-toolbar{display:grid;grid-template-columns:minmax(0,1fr) 190px auto;gap:10px;margin-bottom:10px;align-items:end}
.search-box{position:relative;display:flex;align-items:center}.search-box>svg{position:absolute;left:15px;width:20px;height:20px;color:#7b8ba1;pointer-events:none}.search-box input{width:100%;height:52px;border:1px solid #cfdbe8;border-radius:12px;padding:0 102px 0 46px;color:#17345f;font-size:14px;outline:none}.search-box input:focus{border-color:#f97316;box-shadow:0 0 0 4px rgba(249,115,22,.1)}.search-box input::placeholder{color:#96a2b3}
.search-voice{position:absolute;right:7px;display:none;align-items:center;justify-content:center;width:38px;height:38px;border:0;border-radius:9px;background:#eef4ff;color:#174985;cursor:pointer}.search-voice.visible{display:flex}.search-voice.listening{background:#fff1f2;color:#dc2626;animation:pulseVoice 1s infinite}@keyframes pulseVoice{50%{transform:scale(1.05)}}
.catalog-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.catalog-actions .action-button{flex:1}
.catalog-help{display:flex;align-items:flex-start;gap:9px;margin-top:12px;padding:11px 12px;border:1px solid #cfe0f2;border-radius:11px;background:#f7fbff;color:#526985;font-size:10.5px;line-height:1.5}.catalog-help svg{flex:0 0 17px;width:17px;height:17px;color:#174985}
.search-state{min-height:23px;margin:10px 0 5px;color:#718096;font-size:11px}
.catalog-results{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-height:480px;overflow:auto;padding:2px 3px 3px 0}
.catalog-result{position:relative;display:grid;grid-template-columns:72px minmax(0,1fr);gap:12px;align-items:center;width:100%;padding:11px;border:1px solid #e0e7ef;border-radius:12px;background:#fff;text-align:left;cursor:pointer;transition:.16s}.catalog-result:hover,.catalog-result.active{border-color:#f97316;background:#fffaf5;box-shadow:0 8px 20px rgba(15,23,42,.055)}
.catalog-result-image{display:grid;place-items:center;width:72px;height:72px;border-radius:10px;background:#f8fafc;border:1px solid #edf1f5;overflow:hidden;color:#9aa7b8}.catalog-result-image img{width:100%;height:100%;object-fit:contain}.catalog-result-copy{min-width:0}.catalog-result-copy strong{display:block;color:#19385f;font-size:12px;line-height:1.35}.catalog-result-copy span{display:block;margin-top:4px;color:#7d8a9d;font-size:9.5px;line-height:1.4}.catalog-badge{display:inline-flex!important;align-items:center;gap:5px;width:max-content;margin-top:7px!important;padding:5px 7px;border-radius:999px;background:#dcfce7;color:#15803d!important;font-size:8px!important;font-weight:900}.catalog-badge.online{background:#e8f1ff;color:#174985!important}.catalog-badge.incomplete{background:#fff7ed;color:#c2410c!important}.catalog-offer-badge{position:absolute;right:8px;top:8px;padding:4px 6px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:7.5px;font-weight:900}
.empty-result{display:none;margin-top:12px;padding:18px;border:1px dashed #f6b77c;border-radius:13px;background:#fffaf5;text-align:center}.empty-result.visible{display:block}.empty-result-icon{display:grid;place-items:center;width:45px;height:45px;margin:0 auto 10px;border-radius:12px;background:#fff0e4;color:#f97316}.empty-result h3{margin:0;color:#17345f;font-size:15px}.empty-result p{max-width:540px;margin:7px auto 13px;color:#718096;font-size:11px;line-height:1.55}
.selected-panel{display:none;margin-top:15px;border:1px solid #cfe0f2;border-radius:14px;background:linear-gradient(135deg,#f8fbff,#fff);overflow:hidden}.selected-panel.visible{display:block}.selected-summary{display:grid;grid-template-columns:94px minmax(0,1fr);gap:15px;align-items:center;padding:15px;border-bottom:1px solid #e2eaf3}.selected-picture{display:grid;place-items:center;width:94px;height:94px;border:1px solid #e1e8ef;border-radius:11px;background:#fff;color:#9aa7b8;overflow:hidden}.selected-picture img{width:100%;height:100%;object-fit:contain}.selected-summary h3{margin:0;color:#17345f;font-size:17px;line-height:1.3}.selected-origin{display:inline-flex;align-items:center;width:max-content;margin-bottom:7px;padding:5px 8px;border-radius:999px;background:#eaf2ff;color:#174985;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.selected-origin.master{background:#dcfce7;color:#15803d}.selected-summary p{margin:5px 0 0;color:#718096;font-size:10.5px;line-height:1.45}.technical-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.technical-tag{padding:5px 8px;border:1px solid #dce5ef;border-radius:999px;background:#fff;color:#536985;font-size:9px;font-weight:750}.selected-warning{display:none;margin:12px 15px 0;padding:10px 11px;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;color:#9a4310;font-size:10px;line-height:1.5}.selected-warning.visible{display:block}.selected-warning.info{border-color:#bfdbfe;background:#eff6ff;color:#1e4f85}
.offer-form{padding:15px}.offer-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.offer-grid .full{grid-column:1/-1}.availability-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
.photo-choice{display:grid;grid-template-columns:1fr 1fr;gap:9px}.photo-option{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:11px;font-weight:850;cursor:pointer}.photo-option input[type=file]{position:absolute;width:1px;height:1px;opacity:0}.photo-preview{display:none;align-items:center;gap:10px;margin-top:9px;padding:8px;border:1px solid #dce5ef;border-radius:10px;background:#fff}.photo-preview.visible{display:flex}.photo-preview img{width:52px;height:52px;border-radius:8px;object-fit:contain;background:#f8fafc}.photo-preview span{color:#52657f;font-size:10px}.catalog-image-choice{display:none;align-items:center;gap:7px;margin-top:10px;color:#52657f;font-size:10px}.catalog-image-choice.visible{display:flex}.catalog-image-choice input{width:16px;height:16px;accent-color:#f97316}
.offer-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:14px;padding-top:14px;border-top:1px solid #e8edf3}.offer-actions .action-button{min-width:165px}
.unknown-card{display:none}.unknown-card.visible{display:block}.unknown-banner{display:flex;align-items:flex-start;gap:11px;margin-bottom:15px;padding:12px 13px;border:1px solid #fed7aa;border-radius:11px;background:#fff7ed;color:#9a4310;font-size:10.5px;line-height:1.5}.unknown-banner svg{flex:0 0 18px}
.unknown-photo{display:grid;grid-template-columns:150px minmax(0,1fr);gap:13px;align-items:center;margin-bottom:15px;padding:12px;border:1px dashed #b7c6d8;border-radius:12px;background:#fbfdff}.unknown-photo-preview{display:grid;place-items:center;width:150px;aspect-ratio:1;border-radius:11px;background:#f1f5f9;color:#8391a5;overflow:hidden}.unknown-photo-preview img{display:none;width:100%;height:100%;object-fit:contain}.unknown-photo-preview.has-image img{display:block}.unknown-photo-preview.has-image svg,.unknown-photo-preview.has-image span{display:none}.unknown-photo-preview span{margin-top:6px;font-size:9px}.unknown-photo-actions{display:grid;gap:9px}.unknown-photo-actions p{margin:0;color:#718096;font-size:10px;line-height:1.5}.unknown-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.unknown-grid .full{grid-column:1/-1}.unknown-actions{display:flex;justify-content:space-between;gap:9px;margin-top:15px;padding-top:14px;border-top:1px solid #edf1f5}
.summary-card{padding:18px}.summary-kpi{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border:1px solid #cfe0f2;border-radius:13px;background:#f6faff}.summary-kpi span{display:block;color:#6e7e94;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.summary-kpi strong{display:block;margin-top:5px;color:#173e79;font-size:26px}.summary-kpi-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:#173e79;color:#fff}
.side-title{margin:18px 0 5px;color:#17345f;font-size:15px}.side-text{margin:0;color:#718096;font-size:10.5px;line-height:1.55}.steps-list{display:grid;gap:11px;margin-top:13px}.step-item{display:grid;grid-template-columns:29px minmax(0,1fr);gap:10px;align-items:start}.step-item-number{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:#fff1e7;color:#e9650d;font-size:10px;font-weight:900}.step-item strong{display:block;color:#294364;font-size:10.5px}.step-item span{display:block;margin-top:3px;color:#7c899c;font-size:9.5px;line-height:1.45}
.recent-list{display:grid;gap:8px;margin-top:12px}.recent-item{display:grid;grid-template-columns:46px minmax(0,1fr);gap:9px;align-items:center;padding:8px;border:1px solid #e6ebf1;border-radius:10px;background:#fff}.recent-image{display:grid;place-items:center;width:46px;height:46px;border-radius:8px;background:#f8fafc;overflow:hidden;color:#a0aaba}.recent-image img{width:100%;height:100%;object-fit:contain}.recent-copy{min-width:0}.recent-copy strong,.recent-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recent-copy strong{color:#294364;font-size:10px}.recent-copy span{margin-top:3px;color:#8491a3;font-size:8.5px}.recent-status{display:inline-flex!important;width:max-content;margin-top:5px!important;padding:3px 6px;border-radius:999px;background:#f1f5f9;color:#475569!important;font-size:7.5px!important;font-weight:850}.recent-status.published{background:#dcfce7;color:#15803d!important}.recent-status.pending{background:#fff7ed;color:#c2410c!important}
.scanner-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(4,20,48,.72)}.scanner-modal.visible{display:flex}.scanner-dialog{width:min(520px,100%);padding:17px;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.3)}.scanner-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.scanner-head h3{margin:0;font-size:16px}.scanner-close{display:grid;place-items:center;width:38px;height:38px;border:0;border-radius:9px;background:#f1f5f9;color:#17345f;cursor:pointer}.scanner-video-wrap{position:relative;overflow:hidden;border-radius:13px;background:#0f172a;aspect-ratio:4/3}.scanner-video-wrap video{width:100%;height:100%;object-fit:cover}.scanner-frame{position:absolute;inset:26% 10%;border:2px solid #f97316;border-radius:12px;box-shadow:0 0 0 999px rgba(15,23,42,.35)}.scanner-message{margin:11px 0 0;color:#64748b;font-size:10.5px;line-height:1.5}
@media(max-width:1050px){.field-layout{grid-template-columns:1fr}.field-side{position:static;grid-template-columns:1fr 1fr}}
@media(max-width:760px){.field-header h1{font-size:24px}.field-header p{font-size:11.5px}.field-card-body{padding:16px}.catalog-toolbar{grid-template-columns:1fr}.catalog-results{grid-template-columns:1fr;max-height:none}.field-side{grid-template-columns:1fr}.offer-grid,.unknown-grid,.availability-row{grid-template-columns:1fr}.offer-grid .full,.unknown-grid .full{grid-column:auto}.shop-row{grid-template-columns:1fr}.shop-row .action-button{width:100%}.photo-choice{grid-template-columns:1fr}.offer-actions,.unknown-actions{flex-direction:column}.offer-actions .action-button,.unknown-actions .action-button{width:100%}.unknown-photo{grid-template-columns:110px minmax(0,1fr)}.unknown-photo-preview{width:110px}.selected-summary{grid-template-columns:76px minmax(0,1fr)}.selected-picture{width:76px;height:76px}}
@media(max-width:460px){.field-card-head{gap:9px}.field-step{width:31px;height:31px;flex-basis:31px}.catalog-result{grid-template-columns:62px minmax(0,1fr)}.catalog-result-image{width:62px;height:62px}.unknown-photo{grid-template-columns:1fr}.unknown-photo-preview{width:100%;max-width:210px;margin:0 auto}.catalog-actions{display:grid}.selected-summary{grid-template-columns:1fr}.selected-picture{width:100%;height:auto;aspect-ratio:1;max-height:210px}.offer-actions{position:sticky;bottom:0;background:#fff;padding-bottom:2px}}
</style>

<div class="field-page">
    <header class="field-header">
        <div class="field-header-copy">
            <div class="field-eyebrow"><i data-lucide="map-pin-check"></i> Travail sur le terrain</div>
            <h1>Ajouter les produits de la boutique</h1>
            <p>Recherchez le produit dans les fiches techniques OVANIE et dans les produits déjà en ligne. Le système ne récupère jamais le prix ni le stock d’une autre boutique.</p>
        </div>
    </header>

    @include('commercial.products.partials.mode-navigation', [
        'activeMode' => 'quick',
        'workspaceShopId' => $selectedShop?->id,
        'workspaceShop' => $selectedShop,
    ])

    <div class="field-layout">
        <main class="field-main">
            <section class="field-card">
                <div class="field-card-body">
                    <div class="field-card-head">
                        <span class="field-step">1</span>
                        <div><h2>Choisir la boutique</h2><p>La boutique reste sélectionnée après chaque ajout.</p></div>
                    </div>

                    @if($shops->isEmpty())
                        <div class="unknown-banner"><i data-lucide="triangle-alert"></i><span>Aucune boutique n’est attribuée à votre compte Commercial. Créez ou attribuez d’abord une boutique.</span></div>
                    @else
                        <form class="shop-row" method="GET" action="{{ route('commercial.products.quick.create') }}">
                            <div class="field-group">
                                <label for="shopSelector">Boutique concernée</label>
                                <select id="shopSelector" name="shop_id" required>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}" @selected($selectedShop?->id === $shop->id)>
                                            {{ $shop->name }} — {{ $shop->user?->name ?: $shop->user?->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="action-button" type="submit"><i data-lucide="refresh-cw"></i>Appliquer</button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="field-card" id="catalogSection">
                <div class="field-card-body">
                    <div class="field-card-head">
                        <span class="field-step">2</span>
                        <div><h2>Rechercher un produit</h2><p>Aucune liste n’est chargée automatiquement. Saisissez au moins 2 caractères, puis lancez la recherche.</p></div>
                    </div>

                    <div class="catalog-toolbar">
                        <div class="search-box">
                            <i data-lucide="search"></i>
                            <input id="catalogSearch" type="search" autocomplete="off" placeholder="Ex. nom complet, marque, SKU ou code-barres…" @disabled(!$selectedShop)>
                            <button class="search-voice" id="voiceButton" type="button" title="Rechercher avec la voix" aria-label="Rechercher avec la voix"><i data-lucide="mic"></i></button>
                        </div>
                        <div class="field-group">
                            <select id="categoryFilter" aria-label="Filtrer par catégorie" @disabled(!$selectedShop)>
                                <option value="">Toutes les catégories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="action-button primary" id="searchButton" type="button" @disabled(!$selectedShop)>
                            <i data-lucide="search"></i>Rechercher
                        </button>
                    </div>

                    <div class="catalog-actions">
                        <button class="action-button navy" id="scanButton" type="button" @disabled(!$selectedShop)><i data-lucide="scan-line"></i>Scanner le code-barres</button>
                        <button class="action-button" id="unknownDirectButton" type="button" @disabled(!$selectedShop)><i data-lucide="camera"></i>Produit absent : prendre une photo</button>
                    </div>

                    <div class="catalog-help">
                        <i data-lucide="badge-check"></i>
                        <span><strong>Catalogue maître OVANIE :</strong> il contient une seule fiche technique commune par référence de produit. La recherche consulte aussi les produits déjà en ligne non encore rattachés, sans révéler le prix, le stock ou la boutique d’un autre vendeur.</span>
                    </div>

                    <div class="search-state" id="searchState">Saisissez au moins 2 caractères. Aucun produit ne s’affiche avant votre recherche.</div>
                    <div class="catalog-results" id="catalogResults"></div>

                    <div class="empty-result" id="emptyResult">
                        <span class="empty-result-icon"><i data-lucide="package-search"></i></span>
                        <h3>Ce produit n’a pas été trouvé dans OVANIE</h3>
                        <p>Aucune fiche technique ni aucun produit actif en ligne ne correspond. Photographiez-le et enregistrez-le en brouillon pour le compléter plus tard.</p>
                        <button class="action-button primary" id="openUnknownButton" type="button"><i data-lucide="camera"></i>Photographier ce produit</button>
                    </div>

                    <div class="selected-panel" id="selectedPanel">
                        <div class="selected-summary">
                            <div class="selected-picture" id="selectedPicture"><i data-lucide="package"></i></div>
                            <div>
                                <span class="selected-origin" id="selectedOrigin"></span><h3 id="selectedName"></h3>
                                <p id="selectedMeta"></p>
                                <div class="technical-tags" id="technicalTags"></div>
                            </div>
                        </div>
                        <div class="selected-warning" id="selectedWarning"></div>

                        <form class="offer-form" id="offerForm" method="POST" action="{{ route('commercial.products.quick.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                            <input type="hidden" name="catalog_source_type" id="catalogSourceType">
                            <input type="hidden" name="catalog_source_id" id="catalogSourceId">
                            <input type="hidden" name="intent" id="offerIntent" value="draft">

                            <div class="offer-grid">
                                <div class="field-group">
                                    <label for="offerPrice">Prix de vente (FCFA) *</label>
                                    <input id="offerPrice" name="price" type="number" min="1" step="1" required>
                                </div>
                                <div class="field-group">
                                    <label for="offerStock">Stock disponible *</label>
                                    <input id="offerStock" name="stock" type="number" min="0" step="1" required value="0">
                                </div>
                                <div class="field-group">
                                    <label for="offerPromoPrice">Prix promotionnel</label>
                                    <input id="offerPromoPrice" name="promo_price" type="number" min="1" step="1">
                                </div>
                                <div class="field-group">
                                    <label for="offerMinimum">Quantité minimale *</label>
                                    <input id="offerMinimum" name="min_order_quantity" type="number" min="1" step="1" required value="1">
                                </div>
                                <div class="field-group full">
                                    <label for="offerAvailability">Disponibilité</label>
                                    <select id="offerAvailability" name="availability_status" required>
                                        <option value="in_stock">En stock</option>
                                        <option value="on_order">Disponible sur commande</option>
                                        <option value="preorder">Précommande</option>
                                        <option value="out_of_stock">Rupture de stock</option>
                                    </select>
                                </div>
                                <div class="field-group full">
                                    <label>Photo de la boutique (facultatif)</label>
                                    <div class="photo-choice">
                                        <label class="photo-option"><i data-lucide="camera"></i>Prendre une photo<input id="offerCamera" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"></label>
                                        <label class="photo-option"><i data-lucide="images"></i>Choisir dans la galerie<input id="offerGallery" type="file" accept="image/jpeg,image/png,image/webp"></label>
                                    </div>
                                    <input id="offerPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                                    <div class="photo-preview" id="offerPhotoPreview"><img alt="Aperçu"><span></span></div>
                                    <label class="catalog-image-choice" id="catalogImageChoice"><input type="checkbox" name="use_catalog_image" value="1" checked> Utiliser l’image technique déjà disponible</label>
                                </div>
                            </div>

                            <div class="offer-actions">
                                <button class="action-button" type="submit" data-intent="draft"><i data-lucide="save"></i>Enregistrer en brouillon</button>
                                <button class="action-button primary" id="publishButton" type="submit" data-intent="publish"><i data-lucide="check-circle-2"></i>Enregistrer et publier</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="field-card unknown-card" id="unknownCard">
                <div class="field-card-body">
                    <div class="field-card-head">
                        <span class="field-step">3</span>
                        <div><h2>Produit absent : saisie express</h2><p>Demandez seulement les informations simples au vendeur. Les données techniques seront complétées plus tard.</p></div>
                    </div>

                    <div class="unknown-banner"><i data-lucide="shield-alert"></i><span>Ce produit sera enregistré en <strong>brouillon « À compléter »</strong>. Il ne sera pas visible et ne pourra pas être acheté avant l’ajout du poids, des dimensions et de la description complète.</span></div>

                    <form id="unknownForm" method="POST" action="{{ route('commercial.products.quick.unknown.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">

                        <div class="unknown-photo">
                            <div class="unknown-photo-preview" id="unknownPhotoPreview"><i data-lucide="camera"></i><span>Photo obligatoire</span><img alt="Aperçu du produit"></div>
                            <div class="unknown-photo-actions">
                                <label class="action-button primary"><i data-lucide="camera"></i>Prendre la photo<input id="unknownCamera" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" hidden></label>
                                <label class="action-button"><i data-lucide="images"></i>Choisir dans la galerie<input id="unknownGallery" type="file" accept="image/jpeg,image/png,image/webp" hidden></label>
                                <input id="unknownPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required hidden>
                                <p>La photo sera automatiquement mise au format carré 1200, 800 et 400 px.</p>
                            </div>
                        </div>

                        <div class="unknown-grid">
                            <div class="field-group full">
                                <label for="unknownName">Nom du produit *</label>
                                <input id="unknownName" name="name" required maxlength="255" placeholder="Ex. Robinet chromé, ciment 50 kg…">
                            </div>
                            <div class="field-group">
                                <label for="unknownCategory">Catégorie *</label>
                                <select id="unknownCategory" name="category_id" required>
                                    <option value="">Choisir</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="unknownBrand">Marque</label>
                                <input id="unknownBrand" name="brand" maxlength="150" placeholder="Facultatif">
                            </div>
                            <div class="field-group">
                                <label for="unknownPrice">Prix de vente (FCFA) *</label>
                                <input id="unknownPrice" name="price" type="number" min="1" step="1" required>
                            </div>
                            <div class="field-group">
                                <label for="unknownStock">Stock disponible *</label>
                                <input id="unknownStock" name="stock" type="number" min="0" step="1" required value="0">
                            </div>
                            <div class="field-group">
                                <label for="unknownUnit">Unité de vente *</label>
                                <select id="unknownUnit" name="unit" required>
                                    <option value="">Choisir</option>
                                    <option value="piece">Pièce</option><option value="sac">Sac</option><option value="kg">Kilogramme</option><option value="tonne">Tonne</option><option value="m2">m²</option><option value="m3">m³</option><option value="litre">Litre</option><option value="carton">Carton</option><option value="palette">Palette</option><option value="rouleau">Rouleau</option><option value="seau">Seau</option><option value="paquet">Paquet</option><option value="barre">Barre</option><option value="bidon">Bidon</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="unknownMinimum">Quantité minimale *</label>
                                <input id="unknownMinimum" name="min_order_quantity" type="number" min="1" step="1" required value="1">
                            </div>
                        </div>

                        <div class="unknown-actions">
                            <button class="action-button" id="closeUnknownButton" type="button"><i data-lucide="arrow-up"></i>Revenir à la recherche</button>
                            <button class="action-button primary" type="submit"><i data-lucide="save"></i>Enregistrer et passer au suivant</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>

        <aside class="field-side">
            <section class="field-card summary-card">
                <div class="summary-kpi">
                    <div><span>Ajoutés aujourd’hui</span><strong>{{ $addedToday }}</strong></div>
                    <span class="summary-kpi-icon"><i data-lucide="package-check"></i></span>
                </div>
                <h2 class="side-title">Parcours recommandé</h2>
                <p class="side-text">La plateforme cherche automatiquement dans les fiches OVANIE et les produits déjà en ligne.</p>
                <div class="steps-list">
                    <div class="step-item"><span class="step-item-number">1</span><div><strong>Rechercher ou photographier</strong><span>La plateforme cherche dans les deux sources disponibles.</span></div></div>
                    <div class="step-item"><span class="step-item-number">2</span><div><strong>Saisir prix et stock</strong><span>Le prix et le stock des autres boutiques restent strictement privés.</span></div></div>
                    <div class="step-item"><span class="step-item-number">3</span><div><strong>Passer au produit suivant</strong><span>Un produit absent reste dans « À compléter » sans bloquer la tournée.</span></div></div>
                </div>
            </section>

            <section class="field-card summary-card">
                <h2 class="side-title" style="margin-top:0">Derniers produits</h2>
                <p class="side-text">Produits enregistrés récemment dans {{ $selectedShop?->name ?: 'la boutique' }}.</p>
                <div class="recent-list">
                    @forelse($recentProducts as $product)
                        @php
                            $image = $product->images->sortByDesc(fn ($item) => (int) ($item->is_main || $item->is_primary))->first();
                            $status = match (true) {
                                $product->status === 'actif' && $product->is_active => ['Publié', 'published'],
                                $product->status === 'pending_logistics' => ['Logistique à terminer', 'pending'],
                                default => ['À compléter', ''],
                            };
                        @endphp
                        <a class="recent-item" href="{{ route('commercial.products.edit', $product) }}">
                            <span class="recent-image">@if($image)<img src="{{ $image->thumb_url }}" alt="">@else<i data-lucide="package"></i>@endif</span>
                            <span class="recent-copy"><strong>{{ $product->name ?: 'Produit sans nom' }}</strong><span>{{ $product->price !== null ? number_format((float) $product->price, 0, ',', ' ') . ' FCFA' : 'Prix non renseigné' }}</span><span class="recent-status {{ $status[1] }}">{{ $status[0] }}</span></span>
                        </a>
                    @empty
                        <p class="side-text" style="padding:18px 0;text-align:center">Aucun produit ajouté récemment.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>

<div class="scanner-modal" id="scannerModal" aria-hidden="true">
    <div class="scanner-dialog">
        <div class="scanner-head"><h3>Scanner le code-barres</h3><button class="scanner-close" id="scannerClose" type="button" aria-label="Fermer"><i data-lucide="x"></i></button></div>
        <div class="scanner-video-wrap"><video id="scannerVideo" playsinline muted></video><div class="scanner-frame"></div></div>
        <p class="scanner-message" id="scannerMessage">Placez le code-barres au centre du cadre.</p>
    </div>
</div>

<script>
(() => {
    const selectedShopId = @json($selectedShop?->id);
    const searchEndpoint = @json(route('commercial.products.quick.search'));
    const resultsBox = document.getElementById('catalogResults');
    const searchInput = document.getElementById('catalogSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const searchState = document.getElementById('searchState');
    const emptyResult = document.getElementById('emptyResult');
    const selectedPanel = document.getElementById('selectedPanel');
    const unknownCard = document.getElementById('unknownCard');
    const catalogSourceType = document.getElementById('catalogSourceType');
    const catalogSourceId = document.getElementById('catalogSourceId');
    const publishButton = document.getElementById('publishButton');
    let searchTimer = null;
    let searchController = null;
    let currentResults = [];
    let scannerStream = null;
    let scannerLoop = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    }

    function formatNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? new Intl.NumberFormat('fr-FR').format(number) : '—';
    }

    function resetSearch(message = 'Saisissez au moins 2 caractères. Aucun produit ne s’affiche avant votre recherche.') {
        searchController?.abort();
        searchController = null;
        currentResults = [];
        resultsBox.innerHTML = '';
        emptyResult.classList.remove('visible');
        selectedPanel.classList.remove('visible');
        catalogSourceType.value = '';
        catalogSourceId.value = '';
        searchState.textContent = message;
    }

    function setLoading(message = 'Recherche dans OVANIE…') {
        searchState.textContent = message;
        resultsBox.innerHTML = '';
        emptyResult.classList.remove('visible');
        selectedPanel.classList.remove('visible');
    }

    async function loadCatalog() {
        if (!selectedShopId) return;

        const query = searchInput.value.trim();
        const categoryId = categoryFilter.value;

        if (query.length === 0) {
            resetSearch();
            return;
        }

        if (query.length < 2) {
            resetSearch('Saisissez au moins 2 caractères pour lancer la recherche.');
            return;
        }

        searchController?.abort();
        searchController = new AbortController();

        setLoading(`Recherche de « ${query} » dans les fiches techniques OVANIE et les produits déjà en ligne…`);

        const url = new URL(searchEndpoint, window.location.origin);
        url.searchParams.set('shop_id', selectedShopId);
        url.searchParams.set('q', query);
        if (categoryId) url.searchParams.set('category_id', categoryId);

        try {
            const response = await fetch(url.toString(), {
                signal: searchController.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstError = Object.values(payload.errors || {}).flat()[0];
                throw new Error(firstError || payload.message || 'La recherche est momentanément indisponible.');
            }

            currentResults = Array.isArray(payload.data) ? payload.data : [];
            renderResults(currentResults, query);
        } catch (error) {
            if (error.name === 'AbortError') return;

            currentResults = [];
            searchState.textContent = error.message || 'Impossible de rechercher maintenant.';
            resultsBox.innerHTML = '';
            emptyResult.classList.remove('visible');
        } finally {
            searchController = null;
        }
    }

    function renderResults(items, query) {
        resultsBox.innerHTML = '';
        selectedPanel.classList.remove('visible');
        catalogSourceType.value = '';
        catalogSourceId.value = '';

        if (!items.length) {
            searchState.textContent = query ? 'Aucune fiche technique ni aucun produit actif en ligne ne correspond à cette recherche.' : 'Aucune référence disponible dans cette catégorie.';
            emptyResult.classList.add('visible');
            return;
        }

        emptyResult.classList.remove('visible');
        searchState.textContent = `${items.length} résultat(s) affiché(s), maximum 20. Affinez le nom, la marque ou la référence si nécessaire.`;

        items.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'catalog-result';
            button.dataset.referenceKey = item.key;
            const image = item.image_url
                ? `<img src="${escapeHtml(item.image_url)}" alt="">`
                : '<i data-lucide="package"></i>';
            const code = item.sku || item.reference || 'Sans référence';
            const detail = [item.brand, item.category, item.packaging].filter(Boolean).join(' · ');
            const isOnlineSource = item.source_type === 'marketplace';
            const badgeClass = item.is_complete ? (isOnlineSource ? ' online' : '') : ' incomplete';
            const badgeText = item.is_complete
                ? (item.origin_label || 'Fiche technique OVANIE')
                : `${item.origin_label || 'Référence existante'} · fiche à compléter`;
            const existing = item.existing_offer ? '<span class="catalog-offer-badge">Déjà dans cette boutique</span>' : '';

            button.innerHTML = `
                ${existing}
                <span class="catalog-result-image">${image}</span>
                <span class="catalog-result-copy">
                    <strong>${escapeHtml(item.name)}</strong>
                    <span>${escapeHtml(detail || code)}</span>
                    <span>${escapeHtml(code)}</span>
                    <span class="catalog-badge${badgeClass}">${badgeText}</span>
                </span>`;
            button.addEventListener('click', () => selectProduct(item, button));
            resultsBox.appendChild(button);
        });

        if (window.lucide) lucide.createIcons();
    }

    function selectProduct(item, button) {
        document.querySelectorAll('.catalog-result.active').forEach(row => row.classList.remove('active'));
        button.classList.add('active');
        catalogSourceType.value = item.source_type || 'master';
        catalogSourceId.value = item.source_id;
        const selectedOrigin = document.getElementById('selectedOrigin');
        selectedOrigin.textContent = item.origin_label || 'Référence disponible';
        selectedOrigin.className = `selected-origin ${item.source_type === 'master' ? 'master' : ''}`;
        document.getElementById('selectedName').textContent = item.name || 'Produit OVANIE';
        document.getElementById('selectedMeta').textContent = [item.brand, item.category, item.sku || item.reference].filter(Boolean).join(' · ');

        const picture = document.getElementById('selectedPicture');
        picture.innerHTML = item.image_url ? `<img src="${escapeHtml(item.image_url)}" alt="">` : '<i data-lucide="package"></i>';

        const tags = [
            item.unit ? `Unité : ${item.unit}` : null,
            Number(item.weight_kg) > 0 ? `Poids : ${formatNumber(item.weight_kg)} kg` : null,
            Number(item.length_cm) > 0 ? `${formatNumber(item.length_cm)} × ${formatNumber(item.width_cm)} × ${formatNumber(item.height_cm)} cm` : null,
        ].filter(Boolean);
        document.getElementById('technicalTags').innerHTML = tags.map(tag => `<span class="technical-tag">${escapeHtml(tag)}</span>`).join('');

        const warning = document.getElementById('selectedWarning');
        warning.classList.remove('info');
        if (item.is_complete) {
            publishButton.disabled = false;
            if (item.source_type === 'marketplace') {
                warning.classList.add('visible', 'info');
                warning.textContent = 'Ce produit est déjà en ligne mais n’était pas encore relié au catalogue maître. À l’enregistrement, OVANIE créera automatiquement sa fiche technique commune. Le prix et le stock que vous saisissez appartiendront uniquement à la boutique sélectionnée.';
            } else {
                warning.classList.remove('visible');
                warning.textContent = '';
            }
        } else {
            warning.classList.add('visible');
            warning.textContent = `Cette référence doit encore être complétée : ${(item.missing_fields || []).join(', ')}. Vous pouvez l’enregistrer en brouillon, mais pas la publier.`;
            publishButton.disabled = true;
        }

        const existing = item.existing_offer || {};
        document.getElementById('offerPrice').value = existing.price ?? '';
        document.getElementById('offerPromoPrice').value = existing.promo_price ?? '';
        document.getElementById('offerStock').value = existing.stock ?? 0;
        document.getElementById('offerMinimum').value = existing.min_order_quantity ?? 1;
        document.getElementById('offerAvailability').value = existing.availability_status || 'in_stock';

        const catalogChoice = document.getElementById('catalogImageChoice');
        catalogChoice.classList.toggle('visible', Boolean(item.has_catalog_image));
        catalogChoice.querySelector('input').checked = Boolean(item.has_catalog_image);

        selectedPanel.classList.add('visible');
        unknownCard.classList.remove('visible');
        selectedPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        if (window.lucide) lucide.createIcons();
    }

    function openUnknown() {
        selectedPanel.classList.remove('visible');
        unknownCard.classList.add('visible');
        const query = searchInput.value.trim();
        if (query && !document.getElementById('unknownName').value) {
            document.getElementById('unknownName').value = query;
        }
        unknownCard.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function setSingleFile(sourceInput, targetInput, preview, previewImage, labelElement = null) {
        const file = sourceInput.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('La photo ne doit pas dépasser 5 Mo.');
            sourceInput.value = '';
            return;
        }
        const transfer = new DataTransfer();
        transfer.items.add(file);
        targetInput.files = transfer.files;
        previewImage.src = URL.createObjectURL(file);
        preview.classList.add('visible');
        preview.classList.add('has-image');
        if (labelElement) labelElement.textContent = `${file.name} · ${(file.size / 1024).toFixed(0)} Ko`;
    }

    document.getElementById('searchButton')?.addEventListener('click', loadCatalog);

    categoryFilter?.addEventListener('change', () => {
        if (searchInput.value.trim().length >= 2) {
            loadCatalog();
        } else {
            resetSearch('Choisissez d’abord un produit à rechercher, puis utilisez la catégorie comme filtre.');
        }
    });

    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            resetSearch(query.length === 0
                ? 'Saisissez au moins 2 caractères. Aucun produit ne s’affiche avant votre recherche.'
                : 'Saisissez au moins 2 caractères pour lancer la recherche.');
            return;
        }

        searchTimer = setTimeout(loadCatalog, 450);
    });

    searchInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(searchTimer);
            loadCatalog();
        }
    });

    document.getElementById('unknownDirectButton')?.addEventListener('click', openUnknown);
    document.getElementById('openUnknownButton')?.addEventListener('click', openUnknown);
    document.getElementById('closeUnknownButton')?.addEventListener('click', () => {
        unknownCard.classList.remove('visible');
        document.getElementById('catalogSection').scrollIntoView({behavior: 'smooth'});
    });

    const offerPhoto = document.getElementById('offerPhoto');
    const offerPreview = document.getElementById('offerPhotoPreview');
    const offerPreviewImage = offerPreview.querySelector('img');
    const offerPreviewLabel = offerPreview.querySelector('span');
    ['offerCamera', 'offerGallery'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', event => setSingleFile(event.target, offerPhoto, offerPreview, offerPreviewImage, offerPreviewLabel));
    });

    const unknownPhoto = document.getElementById('unknownPhoto');
    const unknownPreview = document.getElementById('unknownPhotoPreview');
    const unknownPreviewImage = unknownPreview.querySelector('img');
    ['unknownCamera', 'unknownGallery'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', event => setSingleFile(event.target, unknownPhoto, unknownPreview, unknownPreviewImage));
    });

    document.querySelectorAll('#offerForm [data-intent]').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('offerIntent').value = button.dataset.intent || 'draft';
        });
    });

    ['offerForm', 'unknownForm'].forEach(id => {
        document.getElementById(id)?.addEventListener('submit', function(event) {
            if (this.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            this.dataset.submitting = '1';
            this.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = true);
        });
    });

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const voiceButton = document.getElementById('voiceButton');
    if (SpeechRecognition && voiceButton) {
        voiceButton.classList.add('visible');
        voiceButton.addEventListener('click', () => {
            const recognition = new SpeechRecognition();
            recognition.lang = 'fr-FR';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            voiceButton.classList.add('listening');
            searchState.textContent = 'Parlez maintenant…';
            recognition.onresult = event => {
                searchInput.value = event.results[0][0].transcript;
                loadCatalog();
            };
            recognition.onerror = () => searchState.textContent = 'La recherche vocale n’a pas compris. Vous pouvez écrire ou scanner.';
            recognition.onend = () => voiceButton.classList.remove('listening');
            recognition.start();
        });
    }

    const scannerModal = document.getElementById('scannerModal');
    const scannerVideo = document.getElementById('scannerVideo');
    const scannerMessage = document.getElementById('scannerMessage');

    async function closeScanner() {
        if (scannerLoop) cancelAnimationFrame(scannerLoop);
        scannerLoop = null;
        scannerStream?.getTracks().forEach(track => track.stop());
        scannerStream = null;
        scannerVideo.srcObject = null;
        scannerModal.classList.remove('visible');
        scannerModal.setAttribute('aria-hidden', 'true');
    }

    async function openScanner() {
        if (!('BarcodeDetector' in window)) {
            alert('Le scan automatique n’est pas disponible sur ce téléphone. Écrivez le nom ou la référence du produit.');
            searchInput.focus();
            return;
        }
        try {
            const supported = await BarcodeDetector.getSupportedFormats();
            const detector = new BarcodeDetector({formats: supported.filter(format => ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code'].includes(format))});
            scannerStream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            scannerVideo.srcObject = scannerStream;
            await scannerVideo.play();
            scannerModal.classList.add('visible');
            scannerModal.setAttribute('aria-hidden', 'false');
            scannerMessage.textContent = 'Placez le code-barres au centre du cadre.';

            const detect = async () => {
                if (!scannerStream) return;
                try {
                    const codes = await detector.detect(scannerVideo);
                    if (codes.length) {
                        searchInput.value = codes[0].rawValue;
                        await closeScanner();
                        loadCatalog();
                        return;
                    }
                } catch (error) {}
                scannerLoop = requestAnimationFrame(detect);
            };
            detect();
        } catch (error) {
            await closeScanner();
            alert('La caméra ne peut pas être ouverte. Autorisez la caméra ou recherchez le produit par son nom.');
        }
    }

    document.getElementById('scanButton')?.addEventListener('click', openScanner);
    document.getElementById('scannerClose')?.addEventListener('click', closeScanner);
    scannerModal?.addEventListener('click', event => { if (event.target === scannerModal) closeScanner(); });

    if (selectedShopId) {
        resetSearch();
        searchInput?.focus();
    }
})();
</script>
@endsection
