@php
    $editing = isset($product) && $product;
    $value = fn ($key, $default = '') => old($key, $editing ? data_get($product, $key, $default) : $default);
    $selectedShopId = (int) $value('shop_id', request('shop_id'));
    $workspaceShop = $shops->firstWhere('id', $selectedShopId);
    $attrs = old('attributes', $editing ? ($product->product_attributes ?: []) : []);
    $attrs = count($attrs) ? $attrs : [['label' => '', 'value' => '', 'unit' => '']];
    $isNegotiable = old('is_negotiable', ($editing && $product->is_negotiable) ? '1' : '0') === '1';
@endphp

<style>
.product-form-page{max-width:1180px;margin:0 auto;color:#17345f}
.product-form-heading{margin-bottom:16px}
.product-form-eyebrow{display:flex;align-items:center;gap:7px;margin-bottom:7px;color:#f97316;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.product-form-heading h1{margin:0;color:#102f5b;font-size:30px;line-height:1.15;letter-spacing:-.025em}
.product-form-heading p{max-width:780px;margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.55}
.product-wizard{position:relative}
.wizard-progress{height:4px;margin:0 6px 12px;border-radius:999px;background:#e8edf3;overflow:hidden}
.wizard-progress-bar{height:100%;width:20%;border-radius:inherit;background:linear-gradient(90deg,#f97316,#fb923c);transition:width .22s ease}
.wizard-steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:18px}
.wizard-step{display:flex;align-items:center;gap:10px;min-height:66px;border:1px solid #dfe7f0;background:#fff;border-radius:13px;padding:11px 12px;text-align:left;color:#748197;cursor:pointer;transition:.16s;box-shadow:0 6px 18px rgba(15,23,42,.025)}
.wizard-step:hover{border-color:#f4a261;background:#fffaf5}
.wizard-step-number{display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;border-radius:9px;background:#eff6ff;color:#174985;font-size:12px;font-weight:900}
.wizard-step-copy b{display:block;color:#18345f;font-size:12px;margin-bottom:3px}
.wizard-step-copy span{display:block;font-size:10px;line-height:1.25}
.wizard-step.active{border:2px solid #f97316;padding:10px 11px;background:linear-gradient(135deg,#fff7ed,#fff)}
.wizard-step.active .wizard-step-number{background:#f97316;color:#fff}
.wizard-step.completed .wizard-step-number{background:#173e79;color:#fff}
.wizard-panel{display:none;padding:24px;background:#fff;border:1px solid #e1e8f0;border-radius:16px;box-shadow:0 12px 34px rgba(15,23,42,.05)}
.wizard-panel.active{display:block}
.panel-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #edf1f6}
.panel-icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:11px;background:#eff6ff;color:#174985}
.panel-title{margin:0;color:#17345f;font-size:19px}
.panel-help{margin:5px 0 0;color:#718096;font-size:12px;line-height:1.5}
.form-grid-product{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.span-2{grid-column:1/-1}
.product-form-page .form-group label{display:block;margin-bottom:7px;color:#294364;font-size:12px;font-weight:850}
.product-form-page .form-group input:not([type=radio]):not([type=checkbox]),.product-form-page .form-group select,.product-form-page .form-group textarea{width:100%;border:1px solid #d6e0eb;border-radius:10px;background:#fff;color:#18345f;font-size:14px;outline:none;box-sizing:border-box;transition:border-color .16s,box-shadow .16s}
.product-form-page .form-group input:not([type=radio]):not([type=checkbox]),.product-form-page .form-group select{height:48px;padding:0 13px}
.product-form-page .form-group textarea{min-height:120px;padding:12px 13px;resize:vertical;line-height:1.5}
.product-form-page .form-group input:focus,.product-form-page .form-group select:focus,.product-form-page .form-group textarea:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.required:after{content:' *';color:#dc2626}
.radio-row{display:flex;gap:10px;min-height:48px;align-items:center}
.radio-option{position:relative;display:flex!important;align-items:center;gap:8px;min-height:46px;margin:0!important;padding:0 14px;border:1px solid #dbe3ed;border-radius:10px;background:#fff;color:#405570!important;font-size:13px!important;cursor:pointer}
.radio-option input[type=radio]{width:17px!important;height:17px!important;accent-color:#f97316}
.radio-option:has(input:checked){border-color:#f97316;background:#fff7ed;color:#c75a0d!important}
.attributes-head{display:grid;grid-template-columns:1fr 1.4fr .7fr 38px;gap:8px;margin-bottom:7px;color:#718096;font-size:10px;font-weight:850;text-transform:uppercase}
.attributes-row{display:grid;grid-template-columns:1fr 1.4fr .7fr 38px;gap:8px;margin-bottom:9px}
.attributes-row input{height:44px!important}
.small-icon-button{display:grid;place-items:center;width:38px;height:44px;border:0;border-radius:9px;background:#fee2e2;color:#b91c1c;font-size:18px;cursor:pointer}
.secondary-action{display:inline-flex;align-items:center;gap:7px;min-height:42px;padding:0 13px;border:1px solid #d8e2ed;border-radius:9px;background:#fff;color:#17345f;font-size:12px;font-weight:850;cursor:pointer}
.volume-box{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:48px;padding:11px 13px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;color:#1e40af}
.volume-box strong{font-size:12px}.volume-box span{font-size:18px;font-weight:900}
.logistics-note{grid-column:1/-1;display:flex;align-items:flex-start;gap:10px;padding:12px 13px;border:1px solid #cfe0ff;border-radius:10px;background:#f5f9ff;color:#466182;font-size:11px;line-height:1.5}
.photo-upload-box{padding:18px;border:1.5px dashed #b7c6d8;border-radius:13px;background:#fbfdff}
.photo-actions{display:flex;gap:10px;flex-wrap:wrap}
.photo-trigger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:0 15px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:13px;font-weight:850;cursor:pointer}
.photo-trigger.primary{border-color:#f97316;background:#f97316;color:#fff}
.photo-trigger input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.photo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:15px}
.photo-card{position:relative;background:#fff;border:1px solid #dbe3ed;border-radius:12px;padding:8px}
.photo-card img{display:block;width:100%;aspect-ratio:1;object-fit:contain;background:#f8fafc;border-radius:9px}
.photo-meta{display:flex;align-items:center;justify-content:space-between;gap:7px;margin-top:8px;color:#52657f;font-size:10px}
.photo-meta label{display:flex!important;align-items:center;gap:5px;margin:0!important;font-size:10px!important}
.photo-meta input[type=radio],.photo-meta input[type=checkbox]{width:15px!important;height:15px!important;accent-color:#f97316}
.existing-remove{color:#b91c1c!important}
.submit-note{margin:10px 0 0;color:#7c8798;font-size:10px;line-height:1.45}
.wizard-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;padding:15px 17px;background:#fff;border:1px solid #e1e8f0;border-radius:14px;box-shadow:0 9px 24px rgba(15,23,42,.04)}
.wizard-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:0 16px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:13px;font-weight:850;text-decoration:none;cursor:pointer}
.wizard-button.primary{border-color:#f97316;background:#f97316;color:#fff;box-shadow:0 8px 18px rgba(249,115,22,.18)}
.final-actions{display:flex;gap:9px;flex-wrap:wrap;margin-left:auto}
.error-summary{margin-bottom:16px;padding:13px 15px;border:1px solid #fecaca;border-radius:12px;background:#fef2f2;color:#b91c1c;font-size:12px}
@media(max-width:900px){.wizard-steps{display:flex;overflow:auto;padding-bottom:4px}.wizard-step{min-width:190px}.photo-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.product-form-heading h1{font-size:25px}.product-form-heading p{font-size:12px}.form-grid-product{grid-template-columns:1fr}.span-2,.logistics-note{grid-column:auto}.wizard-panel{padding:18px 15px}.photo-grid{grid-template-columns:repeat(2,1fr)}.attributes-head{display:none}.attributes-row{grid-template-columns:1fr 1fr}.attributes-row input:nth-child(3){grid-column:1/-1}.attributes-row .small-icon-button{grid-column:2;grid-row:2;justify-self:end}.wizard-actions{align-items:stretch;flex-direction:column}.wizard-actions .wizard-button,.final-actions,.final-actions .wizard-button{width:100%}.final-actions{flex-direction:column;margin-left:0}.photo-actions{flex-direction:column}.photo-trigger{width:100%}}
</style>

<div class="product-form-page">
    <header class="product-form-heading">
        <div class="product-form-eyebrow"><i data-lucide="clipboard-list"></i> Création détaillée</div>
        <h1>{{ $editing ? 'Modifier le produit' : 'Créer une nouvelle fiche produit' }}</h1>
        <p>Utilisez ce formulaire lorsqu’un produit n’existe pas encore dans le catalogue maître OVANIE ou lorsque ses informations techniques doivent être renseignées manuellement.</p>
    </header>

    @include('commercial.products.partials.mode-navigation', [
        'activeMode' => 'full',
        'workspaceShopId' => $selectedShopId ?: null,
        'workspaceShop' => $workspaceShop,
    ])

    @if($errors->any())
        <div class="error-summary"><strong>Le formulaire contient une erreur :</strong> {{ $errors->first() }}</div>
    @endif

    <div class="product-wizard">
        <div class="wizard-progress"><div class="wizard-progress-bar" id="wizardProgress"></div></div>
        <div class="wizard-steps" role="tablist">
            @foreach([
                ['Informations', 'Boutique et identité'],
                ['Prix et vente', 'Tarifs et stock'],
                ['Détails techniques', 'Usage et caractéristiques'],
                ['Logistique', 'Poids et dimensions'],
                ['Photos', 'Images du produit'],
            ] as $i => $step)
                <button type="button" class="wizard-step {{ $i === 0 ? 'active' : '' }}" data-go="{{ $i }}">
                    <span class="wizard-step-number">{{ $i + 1 }}</span>
                    <span class="wizard-step-copy"><b>{{ $step[0] }}</b><span>{{ $step[1] }}</span></span>
                </button>
            @endforeach
        </div>

        <form id="productForm" method="POST" action="{{ $editing ? route('commercial.products.update', $product) : route('commercial.products.store') }}" enctype="multipart/form-data">
            @csrf
            @if($editing) @method('PUT') @endif

            <section class="wizard-panel active" data-panel="0">
                <div class="panel-heading">
                    <span class="panel-icon"><i data-lucide="info"></i></span>
                    <div><h2 class="panel-title">Informations générales</h2><p class="panel-help">Identifiez clairement le produit et la boutique qui le vend.</p></div>
                </div>
                <div class="form-grid-product">
                    <div class="form-group"><label class="required">Boutique</label><select name="shop_id"><option value="">Choisir une boutique</option>@foreach($shops as $shop)<option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }} — {{ $shop->user?->name ?: $shop->user?->email }}</option>@endforeach</select></div>
                    <div class="form-group"><label class="required">Catégorie</label><select name="category_id" id="commCategory"><option value="">Sélectionner</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($value('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select><p id="commCategorySuggestedHint" style="display:none;margin:4px 0 0;color:#64748b;font-size:11px;">Catégorie suggérée automatiquement à partir du nom du produit. Modifiable si besoin.</p></div>
                    <div class="form-group"><label class="required">Nom du produit</label><input name="name" id="commName" value="{{ $value('name') }}"></div>
                    <div class="form-group"><label>Marque</label><input name="brand" value="{{ $value('brand') }}"></div>
                    <div class="form-group"><label>Type de produit</label><input name="type" value="{{ $value('type') }}"></div>
                    <div class="form-group"><label>Description courte</label><input name="short_description" maxlength="500" value="{{ $value('short_description') }}"></div>
                    <div class="form-group span-2"><label class="required">Description détaillée</label><textarea name="description" rows="6">{{ $value('description') }}</textarea></div>
                </div>
            </section>

            <section class="wizard-panel" data-panel="1">
                <div class="panel-heading">
                    <span class="panel-icon"><i data-lucide="badge-dollar-sign"></i></span>
                    <div><h2 class="panel-title">Prix et conditions de vente</h2><p class="panel-help">Définissez les conditions commerciales appliquées par cette boutique.</p></div>
                </div>
                <div class="form-grid-product">
                    <div class="form-group"><label class="required">Prix FCFA</label><input type="number" min="0" step="1" name="price" value="{{ $value('price') }}"></div>
                    <div class="form-group"><label>Prix promotionnel</label><input type="number" min="0" step="1" name="promo_price" value="{{ $value('promo_price') }}"></div>
                    <div class="form-group"><label>Stock</label><input type="number" min="0" name="stock" value="{{ $value('stock', 0) }}"></div>
                    <div class="form-group"><label class="required">Unité de vente</label><select name="unit"><option value="">Sélectionner</option>@foreach(['piece'=>'Pièce','sac'=>'Sac','kg'=>'Kilogramme','tonne'=>'Tonne','m2'=>'m²','m3'=>'m³','litre'=>'Litre','carton'=>'Carton','palette'=>'Palette','rouleau'=>'Rouleau','seau'=>'Seau','paquet'=>'Paquet','barre'=>'Barre','bidon'=>'Bidon'] as $key=>$label)<option value="{{ $key }}" @selected($value('unit') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Conditionnement</label><input name="packaging" value="{{ $value('packaging') }}" placeholder="Ex. carton de 12"></div>
                    <div class="form-group"><label class="required">Quantité minimale</label><input type="number" min="0.01" step="0.01" name="min_order_quantity" value="{{ $value('min_order_quantity', 1) }}"></div>
                    <div class="form-group"><label>Quantité par conditionnement</label><input type="number" min="0.01" step="0.01" name="units_per_package" value="{{ $value('units_per_package') }}"></div>
                    <div class="form-group"><label>Délai d’approvisionnement</label><input name="supply_delay" value="{{ $value('supply_delay') }}" placeholder="Ex. 48 heures"></div>
                    <div class="form-group span-2">
                        <label>Prix négociable</label>
                        <div class="radio-row">
                            <label class="radio-option"><input type="radio" name="is_negotiable" value="1" @checked($isNegotiable)> Oui, accepter les propositions</label>
                            <label class="radio-option"><input type="radio" name="is_negotiable" value="0" @checked(! $isNegotiable)> Non, prix fixe</label>
                        </div>
                        <input type="hidden" name="price_p1" id="priceP1" value="{{ $value('price_p1') }}">
                        <input type="hidden" name="price_p2" id="priceP2" value="{{ $value('price_p2') }}">
                        <input type="hidden" name="price_p3" id="priceP3" value="{{ $value('price_p3') }}">

                        <div id="negotiationOffers" style="display:none;margin-top:10px;">
                            <p style="margin:0 0 8px;color:#64748b;font-size:11px;">Le client ne voit jamais ces montants directement : il propose un prix et OVANIE accepte automatiquement s’il atteint l’une de ces offres.</p>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                                <div class="volume-box"><strong>1ère offre (-5%)</strong><div><span id="offerPreviewP1">0</span> FCFA</div></div>
                                <div class="volume-box"><strong>2ème offre (-10%)</strong><div><span id="offerPreviewP2">0</span> FCFA</div></div>
                                <div class="volume-box"><strong>3ème offre (-15%)</strong><div><span id="offerPreviewP3">0</span> FCFA</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="wizard-panel" data-panel="2">
                <div class="panel-heading">
                    <span class="panel-icon"><i data-lucide="settings-2"></i></span>
                    <div><h2 class="panel-title">Détails techniques</h2><p class="panel-help">Ajoutez les informations qui permettront au client de comparer et de choisir le produit.</p></div>
                </div>
                <div class="form-grid-product">
                    <div class="form-group span-2"><label>Usage recommandé</label><input name="usage_area" value="{{ $value('usage_area') }}"></div>
                    <div class="form-group span-2"><label>Détails techniques</label><textarea name="technical_details" rows="6">{{ $value('technical_details') }}</textarea></div>
                    <div class="form-group span-2">
                        <label>Caractéristiques complémentaires</label>
                        <div class="attributes-head"><span>Libellé</span><span>Valeur</span><span>Unité</span><span></span></div>
                        <div id="attributes">
                            @foreach($attrs as $i => $attribute)
                                <div class="attributes-row">
                                    <input name="attributes[{{ $i }}][label]" value="{{ $attribute['label'] ?? '' }}" placeholder="Ex. Puissance">
                                    <input name="attributes[{{ $i }}][value]" value="{{ $attribute['value'] ?? '' }}" placeholder="Ex. 200">
                                    <input name="attributes[{{ $i }}][unit]" value="{{ $attribute['unit'] ?? '' }}" placeholder="Ex. W">
                                    <button type="button" class="small-icon-button remove-attribute" aria-label="Supprimer">×</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="secondary-action" id="addAttribute"><i data-lucide="plus"></i>Ajouter une caractéristique</button>
                    </div>
                </div>
            </section>

            <section class="wizard-panel" data-panel="3">
                <div class="panel-heading">
                    <span class="panel-icon"><i data-lucide="truck"></i></span>
                    <div><h2 class="panel-title">Informations logistiques</h2><p class="panel-help">Ces données sont indispensables pour calculer le véhicule et le prix de livraison.</p></div>
                </div>
                <div class="form-grid-product">
                    <div class="logistics-note"><i data-lucide="info"></i><span>Renseignez les dimensions d’une unité vendue. Le volume est recalculé automatiquement par le serveur lors de l’enregistrement.</span></div>
                    <div class="form-group"><label class="required">Poids (kg)</label><input type="number" min="0" step="0.001" name="weight_kg" value="{{ $value('weight_kg') }}"></div>
                    <div class="volume-box"><strong>Volume calculé</strong><div><span id="volumePreview">{{ $value('volume_m3', 0) }}</span> m³</div></div>
                    @foreach(['length_cm'=>'Longueur (cm)','width_cm'=>'Largeur (cm)','height_cm'=>'Hauteur (cm)'] as $key=>$label)
                        <div class="form-group"><label class="required">{{ $label }}</label><input class="dimension" type="number" min="0" step="0.01" name="{{ $key }}" value="{{ $value($key) }}"></div>
                    @endforeach
                    <div class="form-group"><label class="required">Produit fragile</label><div class="radio-row"><label class="radio-option"><input type="radio" name="fragile" value="1" @checked((string) $value('fragile') === '1')> Oui</label><label class="radio-option"><input type="radio" name="fragile" value="0" @checked((string) $value('fragile', '0') === '0')> Non</label></div></div>
                    <div class="form-group"><label class="required">Déchargement requis</label><div class="radio-row"><label class="radio-option"><input type="radio" name="requires_unloading" value="1" @checked((string) $value('requires_unloading') === '1')> Oui</label><label class="radio-option"><input type="radio" name="requires_unloading" value="0" @checked((string) $value('requires_unloading', '0') === '0')> Non</label></div></div>
                    <div class="form-group span-2" id="unloadingBox"><label>Instructions de déchargement</label><textarea name="unloading_instructions" rows="4" placeholder="Ex. prévoir deux manutentionnaires ou un chariot élévateur">{{ $value('unloading_instructions') }}</textarea></div>
                </div>
            </section>

            <section class="wizard-panel" data-panel="4">
                <div class="panel-heading">
                    <span class="panel-icon"><i data-lucide="images"></i></span>
                    <div><h2 class="panel-title">Photos du produit</h2><p class="panel-help">JPG, PNG ou WEBP · 5 Mo maximum par photo · 8 photos maximum. HEIC et HEIF ne sont pas acceptés.</p></div>
                </div>
                <div class="photo-upload-box">
                    <div class="photo-actions">
                        <label class="photo-trigger primary"><i data-lucide="camera"></i>Prendre une photo<input id="cameraInput" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"></label>
                        <label class="photo-trigger"><i data-lucide="images"></i>Choisir dans la galerie<input id="galleryInput" type="file" accept="image/jpeg,image/png,image/webp" multiple></label>
                        <input id="imagesInput" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" hidden>
                    </div>
                    <div class="photo-grid" id="photoGrid">
                        @if($editing)
                            @foreach($product->images as $image)
                                <article class="photo-card existing-photo" data-id="{{ $image->id }}">
                                    <img src="{{ $image->thumb_url }}" alt="">
                                    <div class="photo-meta">
                                        <label><input type="radio" name="main_existing_image_id" value="{{ $image->id }}" @checked($image->is_main || $image->is_primary)> Principale</label>
                                        <label class="existing-remove"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> Supprimer</label>
                                    </div>
                                </article>
                            @endforeach
                        @endif
                    </div>
                    <p class="submit-note">Les images sont automatiquement normalisées en carré 1:1 avec fond clair, puis générées en 1200 × 1200, 800 × 800 et 400 × 400 px.</p>
                </div>
            </section>

            <div class="wizard-actions">
                <button type="button" class="wizard-button" id="previousStep"><i data-lucide="arrow-left"></i>Précédent</button>
                <button type="button" class="wizard-button primary" id="nextStep">Suivant<i data-lucide="arrow-right"></i></button>
                <div class="final-actions" hidden>
                    <button type="submit" name="intent" value="draft" class="wizard-button"><i data-lucide="save"></i>Enregistrer comme brouillon</button>
                    <button type="submit" name="intent" value="publish" class="wizard-button primary"><i data-lucide="badge-check"></i>Publier le produit</button>
                    <a class="wizard-button" href="{{ route('commercial.products.index', ['shop_id' => $selectedShopId ?: null]) }}">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    let step = 0;
    let files = [];
    let attrIndex = {{ count($attrs) }};
    const panels = [...document.querySelectorAll('[data-panel]')];
    const tabs = [...document.querySelectorAll('[data-go]')];
    const prev = document.getElementById('previousStep');
    const next = document.getElementById('nextStep');
    const final = document.querySelector('.final-actions');
    const form = document.getElementById('productForm');
    const actual = document.getElementById('imagesInput');
    const grid = document.getElementById('photoGrid');
    const progress = document.getElementById('wizardProgress');

    function show(index) {
        step = Math.max(0, Math.min(4, index));
        panels.forEach((panel, i) => panel.classList.toggle('active', i === step));
        tabs.forEach((tab, i) => {
            tab.classList.toggle('active', i === step);
            tab.classList.toggle('completed', i < step);
        });
        prev.style.visibility = step ? 'visible' : 'hidden';
        next.hidden = step === 4;
        final.hidden = step !== 4;
        progress.style.width = `${(step + 1) * 20}%`;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    tabs.forEach(tab => tab.addEventListener('click', () => show(Number(tab.dataset.go))));
    prev.addEventListener('click', () => show(step - 1));
    next.addEventListener('click', () => show(step + 1));
    show(0);

    function syncPhotos() {
        const transfer = new DataTransfer();
        files.forEach(file => transfer.items.add(file));
        actual.files = transfer.files;
        grid.querySelectorAll('.new-photo').forEach(element => element.remove());

        files.forEach((file, index) => {
            const card = document.createElement('article');
            card.className = 'photo-card new-photo';
            const url = URL.createObjectURL(file);
            card.innerHTML = `<img src="${url}" alt=""><div class="photo-meta"><label><input type="radio" name="main_new_image_index" value="${index}"> Principale</label><button type="button" class="small-icon-button" data-remove="${index}" aria-label="Supprimer">×</button></div>`;
            grid.append(card);
        });

        grid.querySelectorAll('[data-remove]').forEach(button => {
            button.addEventListener('click', () => {
                files.splice(Number(button.dataset.remove), 1);
                syncPhotos();
            });
        });
    }

    function addPhotos(fileList) {
        for (const file of fileList) {
            if (files.length >= 8) {
                alert('8 photos maximum.');
                break;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                alert('Format non pris en charge. Utilisez JPG, PNG ou WEBP.');
                continue;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert(`La photo ${file.name} dépasse 5 Mo.`);
                continue;
            }
            files.push(file);
        }
        syncPhotos();
    }

    document.getElementById('cameraInput').addEventListener('change', event => {
        addPhotos(event.target.files);
        event.target.value = '';
    });
    document.getElementById('galleryInput').addEventListener('change', event => {
        addPhotos(event.target.files);
        event.target.value = '';
    });

    function calculateVolume() {
        const dimensions = [...document.querySelectorAll('.dimension')].map(input => parseFloat(input.value) || 0);
        document.getElementById('volumePreview').textContent = dimensions.every(Boolean)
            ? ((dimensions[0] * dimensions[1] * dimensions[2]) / 1000000).toFixed(6)
            : '0';
    }
    document.querySelectorAll('.dimension').forEach(input => input.addEventListener('input', calculateVolume));
    calculateVolume();

    function toggleUnloading() {
        const required = document.querySelector('input[name="requires_unloading"]:checked')?.value === '1';
        const box = document.getElementById('unloadingBox');
        const textarea = box.querySelector('textarea');
        box.style.display = required ? '' : 'none';
        textarea.required = required;
        if (!required) textarea.value = '';
    }
    document.querySelectorAll('input[name="requires_unloading"]').forEach(input => input.addEventListener('change', toggleUnloading));
    toggleUnloading();

    function updateNegotiationOffers() {
        const price = Number(document.querySelector('input[name="price"]')?.value || 0);
        const negotiable = document.querySelector('input[name="is_negotiable"]:checked')?.value === '1';
        const offersBox = document.getElementById('negotiationOffers');
        if (offersBox) offersBox.style.display = negotiable ? '' : 'none';
        if (!negotiable || !price) return;
        const p1 = document.getElementById('priceP1');
        const p2 = document.getElementById('priceP2');
        const p3 = document.getElementById('priceP3');
        // Toujours recalculés à partir du prix courant (sinon un prix modifié
        // après activation de la négociation peut laisser des seuils >= au
        // nouveau prix et faire échouer la validation price_p1 < price).
        const offer1 = Math.max(1, Math.round(price * .95));
        const offer2 = Math.max(1, Math.round(price * .90));
        const offer3 = Math.max(1, Math.round(price * .85));
        if (p1) p1.value = offer1;
        if (p2) p2.value = offer2;
        if (p3) p3.value = offer3;
        const fmt = (n) => new Intl.NumberFormat('fr-FR').format(n);
        const o1 = document.getElementById('offerPreviewP1');
        const o2 = document.getElementById('offerPreviewP2');
        const o3 = document.getElementById('offerPreviewP3');
        if (o1) o1.textContent = fmt(offer1);
        if (o2) o2.textContent = fmt(offer2);
        if (o3) o3.textContent = fmt(offer3);
    }
    document.querySelector('input[name="price"]')?.addEventListener('input', updateNegotiationOffers);
    document.querySelectorAll('input[name="is_negotiable"]').forEach(input => input.addEventListener('change', updateNegotiationOffers));
    updateNegotiationOffers();

    // Suggestion IA de catégorie à partir du nom du produit : le commercial
    // n'a plus besoin de la choisir lui-même, mais reste toujours libre de la
    // corriger (voir AiProductCategorySuggester côté serveur, qui ne choisit
    // jamais une catégorie hors de celles qui existent réellement).
    (function () {
        const categorySelect = document.getElementById('commCategory');
        const nameInput = document.getElementById('commName');
        const hint = document.getElementById('commCategorySuggestedHint');
        const suggestUrl = @json(\Illuminate\Support\Facades\Route::has('commercial.products.suggestCategory') ? route('commercial.products.suggestCategory') : null);
        if (!categorySelect || !nameInput || !suggestUrl) return;

        let categoryChosenManually = categorySelect.value !== '';
        let timer = null;

        categorySelect.addEventListener('change', () => {
            categoryChosenManually = true;
            if (hint) hint.style.display = 'none';
        });

        async function suggestCategory() {
            if (categoryChosenManually) return;
            const name = nameInput.value.trim();
            if (name.length < 3) return;

            try {
                const response = await fetch(suggestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name }),
                });
                if (!response.ok || categoryChosenManually) return;

                const payload = await response.json();
                const suggestion = payload?.suggestion;
                if (!suggestion || !suggestion.category_id) return;

                categorySelect.value = String(suggestion.subcategory_id || suggestion.category_id);
                if (hint) hint.style.display = '';
            } catch (_) {
                // Échec silencieux : le commercial garde la sélection manuelle du formulaire.
            }
        }

        nameInput.addEventListener('input', () => {
            if (categoryChosenManually) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(suggestCategory, 700);
        });
    })();

    document.getElementById('addAttribute').addEventListener('click', () => {
        document.getElementById('attributes').insertAdjacentHTML('beforeend', `<div class="attributes-row"><input name="attributes[${attrIndex}][label]" placeholder="Ex. Puissance"><input name="attributes[${attrIndex}][value]" placeholder="Ex. 200"><input name="attributes[${attrIndex}][unit]" placeholder="Ex. W"><button type="button" class="small-icon-button remove-attribute" aria-label="Supprimer">×</button></div>`);
        attrIndex++;
    });

    document.getElementById('attributes').addEventListener('click', event => {
        if (event.target.classList.contains('remove-attribute')) {
            event.target.closest('.attributes-row')?.remove();
        }
    });

    form.addEventListener('submit', event => {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = true;
        });
    });
})();
</script>
