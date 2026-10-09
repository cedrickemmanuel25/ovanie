@php
    $variantProduct = $product ?? null;
    $variantRows = collect(old('variants', $variantProduct?->variants?->map(fn($v) => [
        'id' => $v->id,
        'type' => $v->type,
        'value' => $v->value,
        'color_hex' => $v->color_hex,
        'stock' => $v->stock,
        'price_override' => $v->price_override,
        'is_active' => $v->is_active ? 1 : 0,
        'image_url' => $v->card_url,
    ])->values()->all() ?? []));
    $hasVariants = old('has_variants', $variantRows->isNotEmpty() ? 1 : 0);
@endphp

<section class="ov-variant-editor" data-variant-editor>
    <div class="ov-variant-head">
        <div>
            <strong>Modèles / couleurs</strong>
            <p>Activez cette option si le même produit existe en plusieurs couleurs ou modèles avec les mêmes caractéristiques techniques.</p>
        </div>
        <label class="ov-switch-line">
            <input type="hidden" name="has_variants" value="0">
            <input type="checkbox" name="has_variants" value="1" data-variant-toggle @checked((bool)$hasVariants)>
            <span>Ce produit a plusieurs modèles</span>
        </label>
    </div>

    <div class="ov-variant-body" data-variant-body @if(!$hasVariants) hidden @endif>
        <div class="ov-variant-note">
            Les poids, dimensions, description et informations logistiques restent communs. Chaque variante possède son propre stock et sa propre image.
        </div>
        <div data-variant-list>
            @foreach($variantRows as $index => $variant)
                <div class="ov-variant-row" data-variant-row>
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ data_get($variant,'id') }}">
                    <div class="ov-variant-grid">
                        <label><span>Type</span><select name="variants[{{ $index }}][type]"><option value="Couleur" @selected(data_get($variant,'type','Couleur')==='Couleur')>Couleur</option><option value="Modèle" @selected(data_get($variant,'type')==='Modèle')>Modèle</option><option value="Finition" @selected(data_get($variant,'type')==='Finition')>Finition</option></select></label>
                        <label><span>Nom *</span><input name="variants[{{ $index }}][value]" value="{{ data_get($variant,'value') }}" placeholder="Ex. Bleu, Gris mat..." required></label>
                        <label><span>Stock *</span><input type="number" min="0" name="variants[{{ $index }}][stock]" value="{{ data_get($variant,'stock',0) }}" required></label>
                        <label><span>Couleur visuelle</span><input type="color" name="variants[{{ $index }}][color_hex]" value="{{ data_get($variant,'color_hex') ?: '#1E5AA8' }}"></label>
                        <label class="ov-variant-image"><span>Image de cette variante *</span><input type="file" name="variant_images[{{ $index }}]" accept="image/jpeg,image/png,image/webp" @required(!data_get($variant,'image_url'))>@if(data_get($variant,'image_url'))<img src="{{ data_get($variant,'image_url') }}" alt="{{ data_get($variant,'value') }}">@endif</label>
                        <label><span>Prix spécifique (optionnel)</span><input type="number" min="0" step="1" name="variants[{{ $index }}][price_override]" value="{{ data_get($variant,'price_override') }}" placeholder="Sinon prix du produit"></label>
                    </div>
                    <input type="hidden" name="variants[{{ $index }}][is_active]" value="1">
                    <button type="button" class="ov-variant-remove" data-variant-remove>Supprimer cette variante</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="ov-variant-add" data-variant-add>+ Ajouter une couleur / un modèle</button>
    </div>
</section>

<template data-variant-template>
    <div class="ov-variant-row" data-variant-row>
        <div class="ov-variant-grid">
            <label><span>Type</span><select data-name="type"><option>Couleur</option><option>Modèle</option><option>Finition</option></select></label>
            <label><span>Nom *</span><input data-name="value" placeholder="Ex. Bleu, Gris mat..." required></label>
            <label><span>Stock *</span><input type="number" min="0" value="0" data-name="stock" required></label>
            <label><span>Couleur visuelle</span><input type="color" value="#1E5AA8" data-name="color_hex"></label>
            <label class="ov-variant-image"><span>Image de cette variante *</span><input type="file" accept="image/jpeg,image/png,image/webp" data-file-name required></label>
            <label><span>Prix spécifique (optionnel)</span><input type="number" min="0" step="1" data-name="price_override" placeholder="Sinon prix du produit"></label>
        </div>
        <input type="hidden" value="1" data-name="is_active">
        <button type="button" class="ov-variant-remove" data-variant-remove>Supprimer cette variante</button>
    </div>
</template>

@once
<style>
.ov-variant-editor{margin:20px 0;padding:18px;border:1px solid #dbe4f0;border-radius:16px;background:#fff}.ov-variant-head{display:flex;gap:18px;justify-content:space-between;align-items:flex-start}.ov-variant-head strong{font-size:16px;color:#102a56}.ov-variant-head p{margin:5px 0 0;color:#64748b;font-size:13px;max-width:680px}.ov-switch-line{display:flex;align-items:center;gap:8px;font-weight:800;color:#102a56;white-space:nowrap}.ov-variant-body{margin-top:16px}.ov-variant-note{padding:11px 13px;border-radius:10px;background:#f5f8fc;color:#52627a;font-size:12px;margin-bottom:12px}.ov-variant-row{padding:14px;border:1px solid #e2e8f0;border-radius:13px;background:#fbfdff;margin-bottom:10px}.ov-variant-grid{display:grid;grid-template-columns:1fr 1.5fr .7fr .65fr 1.5fr 1.1fr;gap:10px}.ov-variant-grid label{display:flex;flex-direction:column;gap:5px}.ov-variant-grid label span{font-size:11px;font-weight:800;color:#475569}.ov-variant-grid input,.ov-variant-grid select{min-height:40px;border:1px solid #cbd5e1;border-radius:9px;padding:0 10px;background:#fff}.ov-variant-grid input[type=color]{padding:4px}.ov-variant-image img{width:44px;height:44px;object-fit:contain;background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-top:4px}.ov-variant-add{border:0;border-radius:10px;background:#102a56;color:#fff;padding:10px 14px;font-weight:800;cursor:pointer}.ov-variant-remove{border:0;background:transparent;color:#b42318;font-weight:800;font-size:12px;padding:8px 0 0;cursor:pointer}@media(max-width:980px){.ov-variant-grid{grid-template-columns:1fr 1fr 1fr}.ov-variant-head{flex-direction:column}.ov-switch-line{white-space:normal}}@media(max-width:620px){.ov-variant-grid{grid-template-columns:1fr}.ov-variant-editor{padding:14px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-variant-editor]').forEach(editor=>{const toggle=editor.querySelector('[data-variant-toggle]'),body=editor.querySelector('[data-variant-body]'),list=editor.querySelector('[data-variant-list]'),tpl=editor.parentElement.querySelector('[data-variant-template]')||document.querySelector('[data-variant-template]');const renumber=()=>{[...list.querySelectorAll('[data-variant-row]')].forEach((row,i)=>{row.querySelectorAll('[name^="variants["]').forEach(el=>el.name=el.name.replace(/variants\[\d+\]/,`variants[${i}]`));const file=row.querySelector('input[type=file]');if(file)file.name=`variant_images[${i}]`;row.querySelectorAll('[data-name]').forEach(el=>el.name=`variants[${i}][${el.dataset.name}]`);const df=row.querySelector('[data-file-name]');if(df)df.name=`variant_images[${i}]`;});};const add=()=>{const node=tpl.content.firstElementChild.cloneNode(true);list.appendChild(node);renumber();};toggle?.addEventListener('change',()=>{body.hidden=!toggle.checked;if(toggle.checked&&!list.children.length)add();});editor.addEventListener('click',e=>{if(e.target.matches('[data-variant-add]'))add();if(e.target.matches('[data-variant-remove]')){e.target.closest('[data-variant-row]')?.remove();renumber();}});renumber();});});
</script>
@endonce
