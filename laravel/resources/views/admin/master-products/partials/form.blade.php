@php
    $attributes = old('product_attributes', $masterProduct->product_attributes ?: [['label'=>'','value'=>'','unit'=>'']]);
    $fragileValue = old('fragile', is_null($masterProduct->fragile) ? '' : (int)$masterProduct->fragile);
    $unloadingValue = old('requires_unloading', is_null($masterProduct->requires_unloading) ? '' : (int)$masterProduct->requires_unloading);
@endphp
<form method="POST" action="{{ $action }}" class="catalogue-form" id="masterProductForm">
    @csrf
    @if(!empty($sourceProduct))<input type="hidden" name="source_product_id" value="{{ $sourceProduct->id }}">@endif
    @if($method !== 'POST') @method($method) @endif

    <section class="catalogue-form-section">
        <div class="catalogue-form-title"><span class="catalogue-form-step">1</span><div><h3>Identification de la référence</h3><p>Ces informations permettent de distinguer précisément les variantes d’un même produit.</p></div></div>
        <div class="catalogue-grid">
            <div class="catalogue-field"><label for="mp_name">Nom de la référence *</label><input id="mp_name" name="name" required value="{{ old('name',$masterProduct->name) }}" placeholder="Ex. Ciment Bélier Classic CPJ 32,5 — sac de 50 kg"></div>
            <div class="catalogue-field"><label for="mp_brand">Marque</label><input id="mp_brand" name="brand" value="{{ old('brand',$masterProduct->brand) }}" placeholder="Ex. Bélier"></div>
            <div class="catalogue-field"><label for="mp_reference">Référence fabricant</label><input id="mp_reference" name="reference" value="{{ old('reference',$masterProduct->reference) }}"></div>
            <div class="catalogue-field"><label for="mp_sku">SKU OVANIE</label><input id="mp_sku" name="sku" value="{{ old('sku',$masterProduct->sku) }}" placeholder="Ex. CIM-BELIER-325-50"></div>
            <div class="catalogue-field"><label for="mp_category">Catégorie</label><select id="mp_category" name="category_id"><option value="">Sélectionner une catégorie</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)old('category_id',$masterProduct->category_id)===(int)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="catalogue-field"><label for="mp_unit">Unité de vente</label><select id="mp_unit" name="unit"><option value="">Sélectionner une unité</option>@foreach(['piece'=>'Unité','sac'=>'Sac','tonne'=>'Tonne','kg'=>'Kilogramme','m3'=>'Mètre cube','m2'=>'Mètre carré','ml'=>'Mètre linéaire','palette'=>'Palette','rouleau'=>'Rouleau','seau'=>'Seau','carton'=>'Carton','paquet'=>'Paquet','barre'=>'Barre','bidon'=>'Bidon','litre'=>'Litre'] as $value=>$label)<option value="{{ $value }}" @selected(old('unit',$masterProduct->unit)===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="catalogue-field"><label for="mp_packaging">Conditionnement</label><input id="mp_packaging" name="packaging" value="{{ old('packaging',$masterProduct->packaging) }}" placeholder="Ex. sac de 50 kg"></div>
            <div class="catalogue-field"><label for="mp_color">Couleur ou finition</label><input id="mp_color" name="color" value="{{ old('color',$masterProduct->color) }}"></div>
            <div class="catalogue-field"><label for="mp_grade">Classe ou grade</label><input id="mp_grade" name="grade" value="{{ old('grade',$masterProduct->grade) }}" placeholder="Ex. CPJ 32,5"></div>
            <div class="catalogue-field"><label for="mp_standard">Norme</label><input id="mp_standard" name="standard" value="{{ old('standard',$masterProduct->standard) }}"></div>
        </div>
    </section>

    <section class="catalogue-form-section">
        <div class="catalogue-form-title"><span class="catalogue-form-step">2</span><div><h3>Descriptions et caractéristiques</h3><p>Renseignez uniquement des informations vérifiées qui pourront être partagées entre les boutiques.</p></div></div>
        <div class="catalogue-grid">
            <div class="catalogue-field full"><label for="mp_short">Description courte</label><input id="mp_short" name="short_description" maxlength="500" value="{{ old('short_description',$masterProduct->short_description) }}"></div>
            <div class="catalogue-field full"><label for="mp_description">Description détaillée</label><textarea id="mp_description" name="description">{{ old('description',$masterProduct->description) }}</textarea></div>
            <div class="catalogue-field full"><label for="mp_technical">Détails techniques</label><textarea id="mp_technical" name="technical_details">{{ old('technical_details',$masterProduct->technical_details) }}</textarea></div>
            <div class="catalogue-field full"><span>Caractéristiques structurées</span><div id="masterAttributes">@foreach($attributes as $index=>$attribute)<div class="attribute-row"><input name="product_attributes[{{ $index }}][label]" value="{{ $attribute['label']??'' }}" placeholder="Caractéristique"><input name="product_attributes[{{ $index }}][value]" value="{{ $attribute['value']??'' }}" placeholder="Valeur"><input name="product_attributes[{{ $index }}][unit]" value="{{ $attribute['unit']??'' }}" placeholder="Unité"><button type="button" class="attribute-remove" data-remove-attribute>×</button></div>@endforeach</div><button type="button" class="catalogue-btn" id="addMasterAttribute">Ajouter une caractéristique</button></div>
        </div>
    </section>

    <section class="catalogue-form-section">
        <div class="catalogue-form-title"><span class="catalogue-form-step">3</span><div><h3>Données logistiques</h3><p>Le poids et les dimensions permettent de calculer le véhicule, la distance et le coût de livraison.</p></div></div>
        <div class="catalogue-grid">
            <div class="catalogue-field"><label for="mp_weight">Poids en kilogrammes</label><input id="mp_weight" type="number" min="0" step="0.001" name="weight_kg" value="{{ old('weight_kg',$masterProduct->weight_kg) }}"></div>
            <div class="catalogue-field"><span>Volume calculé</span><div class="catalogue-volume"><span>Volume</span><strong><span id="masterVolume">{{ old('volume_m3',$masterProduct->volume_m3 ?: 0) }}</span> m³</strong></div><input type="hidden" name="volume_m3" id="masterVolumeInput" value="{{ old('volume_m3',$masterProduct->volume_m3) }}"></div>
            <div class="catalogue-field"><label for="mp_length">Longueur en cm</label><input id="mp_length" class="master-dimension" type="number" min="0" step="0.01" name="length_cm" value="{{ old('length_cm',$masterProduct->length_cm) }}"></div>
            <div class="catalogue-field"><label for="mp_width">Largeur en cm</label><input id="mp_width" class="master-dimension" type="number" min="0" step="0.01" name="width_cm" value="{{ old('width_cm',$masterProduct->width_cm) }}"></div>
            <div class="catalogue-field"><label for="mp_height">Hauteur en cm</label><input id="mp_height" class="master-dimension" type="number" min="0" step="0.01" name="height_cm" value="{{ old('height_cm',$masterProduct->height_cm) }}"></div>
            <div class="catalogue-field"><span>Produit fragile</span><div class="catalogue-choice-grid"><label class="catalogue-choice"><input type="radio" name="fragile" value="1" @checked((string)$fragileValue==='1')><span><strong>Oui</strong><small>Manipulation protégée</small></span></label><label class="catalogue-choice"><input type="radio" name="fragile" value="0" @checked((string)$fragileValue==='0')><span><strong>Non</strong><small>Transport standard</small></span></label></div></div>
            <div class="catalogue-field full"><span>Déchargement requis</span><div class="catalogue-choice-grid"><label class="catalogue-choice"><input type="radio" name="requires_unloading" value="1" @checked((string)$unloadingValue==='1')><span><strong>Oui</strong><small>Prévoir une aide ou un équipement</small></span></label><label class="catalogue-choice"><input type="radio" name="requires_unloading" value="0" @checked((string)$unloadingValue==='0')><span><strong>Non</strong><small>Déchargement simple</small></span></label></div></div>
            <div class="catalogue-field full" id="masterUnloadingBox"><label for="mp_unloading">Instructions de déchargement</label><textarea id="mp_unloading" name="unloading_instructions">{{ old('unloading_instructions',$masterProduct->unloading_instructions) }}</textarea></div>
        </div>
    </section>

    <section class="catalogue-form-section">
        <div class="catalogue-form-title"><span class="catalogue-form-step">4</span><div><h3>Disponibilité de la référence</h3><p>Une référence inactive reste enregistrée mais n’est plus proposée dans l’ajout rapide.</p></div></div>
        <label class="catalogue-choice"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$masterProduct->exists ? $masterProduct->is_active : true))><span><strong>Référence active</strong><small>Autoriser son utilisation par les équipes OVANIE.</small></span></label>
    </section>

    <footer class="catalogue-form-actions">
        <div class="catalogue-form-actions-copy">
            <span class="catalogue-action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
            </span>
            <div>
                <strong>{{ $masterProduct->exists ? 'Mise à jour de la référence' : 'Nouvelle référence technique' }}</strong>
                <small>Vérifiez les informations techniques et logistiques avant l’enregistrement.</small>
            </div>
        </div>
        <div class="catalogue-form-actions-buttons">
            <a href="{{ route('admin.master-products.index') }}" class="catalogue-btn">Annuler</a>
            <button type="submit" class="catalogue-btn primary">{{ $masterProduct->exists ? 'Enregistrer les modifications' : 'Créer la référence' }}</button>
        </div>
    </footer>
</form>
@push('scripts')
<script>
(()=>{const dimensions=[...document.querySelectorAll('.master-dimension')],volume=document.getElementById('masterVolume'),volumeInput=document.getElementById('masterVolumeInput'),list=document.getElementById('masterAttributes');function calculate(){const [l,w,h]=dimensions.map(i=>parseFloat(i.value)||0),v=l*w*h/1000000;volume.textContent=v.toFixed(6);volumeInput.value=v.toFixed(6)}dimensions.forEach(i=>i.addEventListener('input',calculate));calculate();document.getElementById('addMasterAttribute')?.addEventListener('click',()=>{const index=list.children.length,row=document.createElement('div');row.className='attribute-row';row.innerHTML=`<input name="product_attributes[${index}][label]" placeholder="Caractéristique"><input name="product_attributes[${index}][value]" placeholder="Valeur"><input name="product_attributes[${index}][unit]" placeholder="Unité"><button type="button" class="attribute-remove" data-remove-attribute>×</button>`;list.appendChild(row)});list?.addEventListener('click',e=>{if(e.target.matches('[data-remove-attribute]')&&list.children.length>1)e.target.closest('.attribute-row').remove()})})();
</script>
@endpush
