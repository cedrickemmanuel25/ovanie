@extends($catalogueLayout ?? 'admin.layouts.app')
@includeWhen(! ($isAdminSpace ?? true), 'support.partials.catalogue-scope')
@section('title', 'Ajouter un produit | ' . ($spaceLabel ?? 'Administration OVANIE'))
@section('page-title', 'Ajouter un produit')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_catalogue.css') }}">@endpush
@section('content')
<div class="admin-catalogue-page catalogue-form-shell">
    <header class="catalogue-heading">
        <div><span class="catalogue-kicker">Assistance vendeur</span><h2>Ajouter un produit pour une boutique</h2><p>{{ ($isAdminSpace ?? true) ? 'L’administration' : 'Le Support' }} peut publier ou compléter une fiche à la place d’un vendeur qui a besoin d’aide. Le produit reste toujours rattaché à sa boutique et l’intervention OVANIE est tracée.</p></div>
        <a href="{{ route(($productRoutes ?? 'admin.products') . '.index') }}" class="catalogue-btn">Retour aux produits</a>
    </header>

    @if($selectedShop)
        <div style="margin:0 0 18px;padding:14px 16px;border:1px solid #cfe0f4;border-radius:14px;background:#f7fbff;display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div><strong style="display:block;color:#0b2d55;">Produit créé pour {{ $selectedShop->name }}</strong><span style="color:#64748b;font-size:13px;">Vendeur : {{ $selectedShop->user?->name ?: $selectedShop->user?->email ?: 'Non renseigné' }}</span></div>
            @if($isAdminSpace ?? true)<a href="{{ route('admin.shops.show',$selectedShop) }}" class="catalogue-btn">Voir la boutique</a>@endif
        </div>
    @endif

    <form method="POST" action="{{ route(($productRoutes ?? 'admin.products') . '.store') }}" enctype="multipart/form-data" class="catalogue-form" id="adminProductForm">
        @csrf
        <section class="catalogue-form-section">
            <div class="catalogue-form-title"><span class="catalogue-form-step">1</span><div><h3>Produit et boutique</h3><p>Identifiez clairement l’offre et la boutique qui la commercialise.</p></div></div>
            <div class="catalogue-grid">
                <div class="catalogue-field"><label for="shop_id">Boutique *</label><select id="shop_id" name="shop_id" required><option value="">Sélectionner une boutique</option>@foreach($shops as $shop)<option value="{{ $shop->id }}" @selected((int)old('shop_id', $selectedShopId)===(int)$shop->id)>{{ $shop->name }} — {{ $shop->user?->name ?: $shop->user?->email ?: 'vendeur' }}</option>@endforeach</select></div>
                <div class="catalogue-field">
                    <label for="main_category_id">Catégorie principale *</label>
                    <select id="main_category_id" name="main_category_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        @foreach($mainCategories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('main_category_id') === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('main_category_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="catalogue-field">
                    <label for="category_id">Sous-catégorie *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Sélectionnez d’abord une catégorie</option>
                        @foreach($subCategories as $subcategory)
                            <option value="{{ $subcategory->id }}" data-parent="{{ $subcategory->parent_id }}" @selected((int) old('category_id') === (int) $subcategory->id)>
                                {{ $subcategory->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="catalogue-help">Seules les sous-catégories de la catégorie choisie sont affichées.</small>
                    @error('category_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="catalogue-field"><label for="name">Nom du produit *</label><input id="name" name="name" required value="{{ old('name') }}"></div>
                <div class="catalogue-field"><label for="brand">Marque</label><input id="brand" name="brand" value="{{ old('brand') }}"></div>
                <div class="catalogue-field"><label for="sale_type">Type de vente *</label><select id="sale_type" name="sale_type" required><option value="">Sélectionner</option>@foreach(['Vente normale'=>'Vente normale','Vente flash'=>'Vente flash','Black Friday'=>'Black Friday','Promo spéciale'=>'Promotion spéciale'] as $value=>$label)<option value="{{ $value }}" @selected(old('sale_type')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="catalogue-field full"><label for="description">Description détaillée *</label><textarea id="description" name="description" required>{{ old('description') }}</textarea></div>
            </div>
        </section>

        <section class="catalogue-form-section">
            <div class="catalogue-form-title"><span class="catalogue-form-step">2</span><div><h3>Prix, stock et conditionnement</h3><p>Les informations commerciales restent propres à la boutique sélectionnée.</p></div></div>
            <div class="catalogue-grid">
                <div class="catalogue-field"><label for="price">Prix de vente en FCFA *</label><input id="price" type="number" min="1" step="1" name="price" required value="{{ old('price') }}"></div>
                <div class="catalogue-field"><label for="promo_price">Prix promotionnel</label><input id="promo_price" type="number" min="0" step="1" name="promo_price" value="{{ old('promo_price') }}"></div>
                <div class="catalogue-field"><label for="stock">Stock disponible *</label><input id="stock" type="number" min="0" step="1" name="stock" required value="{{ old('stock',0) }}"></div>
                <div class="catalogue-field"><label for="min_order_quantity">Quantité minimale *</label><input id="min_order_quantity" type="number" min="1" step="1" name="min_order_quantity" required value="{{ old('min_order_quantity',1) }}"></div>
                <div class="catalogue-field"><label for="unit">Unité de vente *</label><select id="unit" name="unit" required><option value="">Sélectionner une unité</option>@foreach(['piece'=>'Unité','sac'=>'Sac','tonne'=>'Tonne','kg'=>'Kilogramme','m3'=>'Mètre cube','m2'=>'Mètre carré','ml'=>'Mètre linéaire','palette'=>'Palette','rouleau'=>'Rouleau','seau'=>'Seau','carton'=>'Carton','paquet'=>'Paquet','barre'=>'Barre','bidon'=>'Bidon','litre'=>'Litre'] as $value=>$label)<option value="{{ $value }}" @selected(old('unit')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="catalogue-field"><label for="packaging">Conditionnement</label><input id="packaging" name="packaging" value="{{ old('packaging') }}" placeholder="Ex. sac de 50 kg"></div>
                <div class="catalogue-field"><label for="price_p1">Prix plafond de négociation</label><input id="price_p1" type="number" min="0" step="1" name="price_p1" value="{{ old('price_p1') }}"></div>
                <div class="catalogue-field"><label for="price_p2">Prix de contre-offre</label><input id="price_p2" type="number" min="0" step="1" name="price_p2" value="{{ old('price_p2') }}"></div>
                <div class="catalogue-field"><label for="price_p3">Prix plancher</label><input id="price_p3" type="number" min="0" step="1" name="price_p3" value="{{ old('price_p3') }}"></div>
            </div>
        </section>

        <section class="catalogue-form-section">
            <div class="catalogue-form-title"><span class="catalogue-form-step">3</span><div><h3>Poids et dimensions</h3><p>Ces données sont obligatoires pour déterminer le véhicule et calculer la livraison.</p></div></div>
            <div class="catalogue-grid">
                <div class="catalogue-field"><label for="weight_kg">Poids par unité en kg *</label><input id="weight_kg" type="number" min="0.01" step="0.001" name="weight_kg" required value="{{ old('weight_kg') }}"></div>
                <div class="catalogue-field"><span>Volume calculé</span><div class="catalogue-volume"><span>Volume</span><strong><span id="productVolume">0.000000</span> m³</strong></div></div>
                <div class="catalogue-field"><label for="length_cm">Longueur en cm *</label><input id="length_cm" class="product-dimension" type="number" min="0.1" step="0.01" name="length_cm" required value="{{ old('length_cm') }}"></div>
                <div class="catalogue-field"><label for="width_cm">Largeur en cm *</label><input id="width_cm" class="product-dimension" type="number" min="0.1" step="0.01" name="width_cm" required value="{{ old('width_cm') }}"></div>
                <div class="catalogue-field"><label for="height_cm">Hauteur en cm *</label><input id="height_cm" class="product-dimension" type="number" min="0.1" step="0.01" name="height_cm" required value="{{ old('height_cm') }}"></div>
                <div class="catalogue-field full"><span>Produit fragile *</span><div class="catalogue-choice-grid"><label class="catalogue-choice"><input type="radio" name="fragile" value="1" required @checked(old('fragile')==='1')><span><strong>Oui</strong><small>Manipulation protégée nécessaire</small></span></label><label class="catalogue-choice"><input type="radio" name="fragile" value="0" required @checked(old('fragile','0')==='0')><span><strong>Non</strong><small>Transport standard</small></span></label></div></div>
                <div class="catalogue-field full"><span>Déchargement requis *</span><div class="catalogue-choice-grid"><label class="catalogue-choice"><input type="radio" name="requires_unloading" value="1" required @checked(old('requires_unloading')==='1')><span><strong>Oui</strong><small>Prévoir une aide ou un équipement</small></span></label><label class="catalogue-choice"><input type="radio" name="requires_unloading" value="0" required @checked(old('requires_unloading','0')==='0')><span><strong>Non</strong><small>Déchargement simple</small></span></label></div></div>
                <div class="catalogue-field full" id="unloadingBox"><label for="unloading_instructions">Instructions de déchargement</label><textarea id="unloading_instructions" name="unloading_instructions">{{ old('unloading_instructions') }}</textarea></div>
            </div>
        </section>

        @include('components.product-variants-editor', ['product' => null])

        <section class="catalogue-form-section">
            <div class="catalogue-form-title"><span class="catalogue-form-step">4</span><div><h3>Promotions, images et médias</h3><p>Ajoutez les promotions utiles et plusieurs photos professionnelles du produit.</p></div></div>
            <div class="catalogue-grid">
                <div class="catalogue-field"><label for="flash_end">Fin de la vente flash</label><input id="flash_end" type="datetime-local" name="flash_end" value="{{ old('flash_end') }}"></div>
                <div class="catalogue-field"><label for="bf_start">Début du Black Friday</label><input id="bf_start" type="datetime-local" name="bf_start" value="{{ old('bf_start') }}"></div>
                <div class="catalogue-field"><label for="bf_end">Fin du Black Friday</label><input id="bf_end" type="datetime-local" name="bf_end" value="{{ old('bf_end') }}"></div>
                <div class="catalogue-field"><span>Livraison rapide</span><label class="catalogue-choice"><input type="checkbox" name="fast_delivery" value="1" @checked(old('fast_delivery'))><span><strong>Activer</strong><small>Afficher la disponibilité d’une livraison accélérée.</small></span></label></div>
                <div class="catalogue-field full">
                    <span>Images et médias du produit *</span>
                    <label class="catalogue-upload">
                        <input type="file" name="images[]" id="productImages" multiple accept="image/jpeg,image/png,image/webp" required data-max-images="20">
                        <strong>Ajouter plusieurs photos du produit</strong>
                        <span>Jusqu’à 20 images · JPG, PNG ou WEBP · 5 Mo maximum par image · OVANIE normalise automatiquement les photos en format carré professionnel.</span>
                    </label>
                    <div id="selectedFiles" class="catalogue-help" aria-live="polite">Aucune photo sélectionnée.</div>
                    @error('images')<small class="field-error">{{ $message }}</small>@enderror
                    @error('images.*')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </section>

        <div class="catalogue-form-actions"><a href="{{ $selectedShop && ($isAdminSpace ?? true) ? route('admin.shops.show',$selectedShop) : route(($productRoutes ?? 'admin.products') . '.index') }}" class="catalogue-btn">Annuler</a><button class="catalogue-btn primary" type="submit">Ajouter pour le vendeur</button></div>
    </form>
</div>
@endsection
@push('scripts')
<script>
(()=>{
    const mainCategory = document.getElementById('main_category_id');
    const subCategory = document.getElementById('category_id');

    function syncSubcategories() {
        if (!mainCategory || !subCategory) return;

        const mainId = String(mainCategory.value || '');
        const previousValue = String(subCategory.value || '');
        let visible = 0;

        [...subCategory.options].forEach((option, index) => {
            if (index === 0) return;
            const matches = mainId !== '' && String(option.dataset.parent || '') === mainId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches) visible++;
        });

        const selected = [...subCategory.options].find(option => option.value === previousValue && !option.disabled);
        if (!selected) subCategory.value = '';

        subCategory.options[0].textContent = !mainId
            ? 'Sélectionnez d’abord une catégorie'
            : visible > 0
                ? 'Sélectionner une sous-catégorie'
                : 'Aucune sous-catégorie disponible';
        subCategory.disabled = !mainId || visible === 0;
    }

    mainCategory?.addEventListener('change', () => {
        subCategory.value = '';
        syncSubcategories();
    });
    syncSubcategories();

    const dims=[...document.querySelectorAll('.product-dimension')],out=document.getElementById('productVolume');
    function calc(){const [l,w,h]=dims.map(i=>parseFloat(i.value)||0);out.textContent=(l*w*h/1000000).toFixed(6)}
    dims.forEach(i=>i.addEventListener('input',calc));calc();

    const images=document.getElementById('productImages'),files=document.getElementById('selectedFiles');
    images?.addEventListener('change',()=>{
        const max=parseInt(images.dataset.maxImages || '20',10);
        const count=images.files.length;
        if(count>max){
            images.value='';
            files.textContent=`Vous pouvez sélectionner jusqu’à ${max} images à la fois.`;
            files.style.color='#b42318';
            return;
        }
        files.style.color='';
        files.textContent=count?`${count} image${count>1?'s':''} sélectionnée${count>1?'s':''} sur ${max}.`:'Aucune photo sélectionnée.';
    });

    const box=document.getElementById('unloadingBox'),instruction=document.getElementById('unloading_instructions');
    function sync(){const required=document.querySelector('input[name="requires_unloading"]:checked')?.value==='1';box.style.display=required?'grid':'none';instruction.required=required;if(!required)instruction.value=''}
    document.querySelectorAll('input[name="requires_unloading"]').forEach(i=>i.addEventListener('change',sync));sync();
})();
</script>
@endpush
