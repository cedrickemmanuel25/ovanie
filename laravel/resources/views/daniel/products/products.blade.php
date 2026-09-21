@extends('layouts.vendor')

@section('title', 'Ajouter un produit | OVANIE')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/add_product.css') }}">
@endsection

@section('content')
<div class="vd-breadcrumb">
    <i data-lucide="home"></i>
    <i data-lucide="chevron-right"></i>
    <span>Produits</span>
    <i data-lucide="chevron-right"></i>
    <span>Ajouter</span>
    <i data-lucide="chevron-right"></i>
    <strong>Produit simple</strong>
</div>

<h1 class="vd-title">Ajouter un produit</h1>
<p class="vd-subtitle">Remplissez les informations de base de votre produit.</p>

@if(session('error'))<div class="form-alert error">{{ session('error') }}</div>@endif
@if(session('success'))<div class="form-alert success">{{ session('success') }}</div>@endif
@if ($errors->any())
    <div class="form-alert error">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<section class="product-wizard">
    <aside class="wizard-steps">
        <div class="wizard-step-marker active"><span>1</span><strong>Informations produit</strong></div>
        <div class="wizard-step-marker"><span>2</span><strong>Variantes & prix</strong></div>
        <div class="wizard-step-marker"><span>3</span><strong>Spécifications & livraison</strong></div>
    </aside>

    <div class="wizard-panel vd-card">
        <form id="addProductForm" method="POST" action="{{ route('daniel.products.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="single">

            <div class="wizard-step active" id="step-1">
                <h2>Images du produit <span>*</span></h2>
                <div class="image-upload-grid">
                    @foreach (['Image principale' => 'Recommandée', 'Image 2' => 'Optionnelle', 'Image 3' => 'Optionnelle', 'Image 4' => 'Optionnelle', 'Image 5' => 'Optionnelle'] as $label => $hint)
                        <label class="image-upload-box">
                            <input type="file" name="images[]" accept="image/*" class="d-none image-input">
                            <span class="plus-icon">+</span>
                            <strong>{{ $label }}</strong>
                            <small>{{ $hint }}</small>
                            <span class="image-preview"></span>
                        </label>
                    @endforeach
                </div>
                <p class="helper-text">Formats acceptés : JPG, PNG, WebP. Taille max : 5 Mo par image.</p>

                <div class="form-group">
                    <label>Nom du produit <span>*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Ex. : Casque Bluetooth sans fil" required maxlength="255">
                    <small>0 / 255</small>
                </div>

                <div class="form-group">
                    <label>Catégorie <span>*</span></label>
                    <select name="category_id" required>
                        <option value="">Sélectionnez une catégorie</option>
                        @foreach($categories ?? [] as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Description <span>*</span></label>
                    <textarea name="description" rows="5" placeholder="Décrivez votre produit en détail : caractéristiques, avantages, utilisation..." required>{{ old('description') }}</textarea>
                    <small>0 / 2000</small>
                </div>

                <div class="wizard-footer">
                    <span></span>
                    <button type="button" class="vd-btn primary next-step">Suivant <i data-lucide="chevron-right"></i></button>
                </div>
            </div>

            <div class="wizard-step" id="step-2">
                <h2>Variantes & prix</h2>
                <div class="form-grid two">
                    <div class="form-group"><label>Prix (FCFA) <span>*</span></label><input type="number" name="price" value="{{ old('price') }}" min="0" required></div>
                    <div class="form-group"><label>Prix promo</label><input type="number" name="promo_price" value="{{ old('promo_price') }}" min="0"></div>
                    <div class="form-group"><label>Stock disponible <span>*</span></label><input type="number" name="stock" value="{{ old('stock') }}" min="0" required></div>
                    <div class="form-group"><label>Prix de livraison de base</label><input type="number" name="transport" value="{{ old('transport') }}" min="0"></div>
                    <div class="form-group"><label>Type de vente <span>*</span></label>
                        <select name="sale_type" required>
                            <option value="Vente normale" @selected(old('sale_type') == 'Vente normale')>Vente normale</option>
                            <option value="Vente flash" @selected(old('sale_type') == 'Vente flash')>Vente flash</option>
                            <option value="Black Friday" @selected(old('sale_type') == 'Black Friday')>Black Friday</option>
                            <option value="Promo spéciale" @selected(old('sale_type') == 'Promo spéciale')>Promo spéciale</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Fin Flash Sale</label><input type="datetime-local" name="flash_end" value="{{ old('flash_end') }}"></div>
                </div>
                <div class="wizard-footer">
                    <button type="button" class="vd-btn prev-step">Précédent</button>
                    <button type="button" class="vd-btn primary next-step">Suivant <i data-lucide="chevron-right"></i></button>
                </div>
            </div>

            <div class="wizard-step" id="step-3">
                <h2>Spécifications & livraison</h2>
                <div class="form-grid two">
                    <div class="form-group"><label>Poids (kg)</label><input type="number" name="weight" min="0" step="0.01" placeholder="Ex. : 2"></div>
                    <div class="form-group"><label>Type de colis</label>
                        <select name="package_type">
                            <option value="Léger (Tarif normal)">Léger (Tarif normal)</option>
                            <option value="Moyen">Moyen</option>
                            <option value="Lourd (Surtaxe)">Lourd (Surtaxe)</option>
                            <option value="Volumineux">Volumineux</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Longueur</label><input type="text" name="length" placeholder="Ex. : 50 cm"></div>
                    <div class="form-group"><label>Fragile ?</label><select name="is_fragile"><option value="Non">Non</option><option value="Oui">Oui</option></select></div>
                </div>
                <div class="form-group">
                    <label>Détails techniques</label>
                    <textarea name="technical_details" rows="5" placeholder="Ex. : puissance, couleur, garantie...">{{ old('technical_details') }}</textarea>
                </div>
                <label class="fast-delivery-box">
                    <input type="checkbox" name="fast_delivery" value="1" @checked(old('fast_delivery'))>
                    <span>Activer la livraison rapide (Abidjan 3 jours, intérieur 7 jours)</span>
                </label>
                <div class="wizard-footer">
                    <button type="button" class="vd-btn prev-step">Précédent</button>
                    <button type="submit" class="vd-btn primary">Soumettre le produit</button>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset('js/add_product.js') }}"></script>
@endsection
