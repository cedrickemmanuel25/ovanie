@extends('admin.layouts.app')

@section('title', 'Modifier un produit | Administration OVANIE')
@section('page-title', 'Modifier un produit')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_product_edit.css') }}">
@endpush

@section('content')
@php
    $currentStatus = old('status', $product->status ?: ($product->is_active ? 'actif' : 'inactive'));
    $currentAvailability = old(
        'availability_status',
        $product->availability_status ?: ((int) $product->stock > 0 ? 'in_stock' : 'out_of_stock')
    );
    $currentState = old('product_state', $product->product_state ?: 'new');
    $currentUnit = old('unit', $product->unit ?: 'unité');
    $currentMainImage = old('main_image', optional($product->images->firstWhere('is_main', true))->id);
    $logisticsComplete = (float) $product->weight_kg > 0
        && (float) $product->length_cm > 0
        && (float) $product->width_cm > 0
        && (float) $product->height_cm > 0;
    $statusLabels = [
        'draft' => 'Brouillon',
        'actif' => 'Publié',
        'pending_logistics' => 'Logistique incomplète',
        'inactive' => 'Désactivé',
        'archived' => 'Archivé',
    ];
@endphp

<div class="product-edit-page">
    <header class="product-edit-heading">
        <div>
            <span class="page-kicker">Catalogue produits</span>
            <h2>Modifier le produit</h2>
            <p>
                Mettez à jour les informations commerciales, logistiques et les médias de cette offre.
            </p>
        </div>

        <div class="product-edit-heading-actions">
            <a href="{{ route('admin.products.index') }}" class="secondary-action">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></svg>
                Retour aux produits
            </a>
            <a href="{{ url('/admin/products/' . $product->getRouteKey()) }}" class="secondary-action" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                Voir la fiche
            </a>
        </div>
    </header>

    <section class="product-context-card">
        <div class="product-context-image">
            <img src="{{ $product->card_image_url }}" alt="{{ $product->name }}">
        </div>
        <div class="product-context-copy">
            <div class="product-context-title-row">
                <div>
                    <span>Offre #{{ $product->id }}</span>
                    <h3>{{ $product->name }}</h3>
                </div>
                <span class="status-chip status-{{ $currentStatus }}">
                    {{ $statusLabels[$currentStatus] ?? ucfirst($currentStatus) }}
                </span>
            </div>
            <div class="product-context-meta">
                <span>
                    <strong>Boutique</strong>
                    {{ $product->shop?->name ?? 'Boutique non renseignée' }}
                </span>
                <span>
                    <strong>Vendeur</strong>
                    {{ $product->shop?->user?->name ?? 'Non renseigné' }}
                </span>
                <span>
                    <strong>Catégorie</strong>
                    {{ $product->category?->name ?? 'Non classé' }}
                </span>
                <span>
                    <strong>Référence technique</strong>
                    {{ $product->masterProduct?->name ?? 'Non rattachée' }}
                </span>
            </div>
        </div>
    </section>

    <div class="product-edit-layout">
        <form
            method="POST"
            action="{{ route('admin.products.update', $product) }}"
            enctype="multipart/form-data"
            class="product-edit-form"
            id="productEditForm"
        >
            @csrf
            @method('PUT')

            <section class="edit-panel" id="identification">
                <div class="panel-heading">
                    <span class="panel-number">1</span>
                    <div>
                        <h3>Identification du produit</h3>
                        <p>Nom, marque, classement et descriptions visibles dans le catalogue.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-wide">
                        <label for="name">Nom du produit <span>*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required maxlength="255">
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="category_id">Catégorie <span>*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === (int) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="brand">Marque</label>
                        <input id="brand" name="brand" type="text" value="{{ old('brand', $product->brand) }}" maxlength="120">
                    </div>

                    <div class="form-field">
                        <label for="sku">SKU ou référence interne</label>
                        <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" maxlength="120">
                    </div>

                    <div class="form-field">
                        <label for="type">Type de produit</label>
                        <input id="type" name="type" type="text" value="{{ old('type', $product->type) }}" maxlength="120">
                    </div>

                    <div class="form-field">
                        <label for="product_state">État du produit <span>*</span></label>
                        <select id="product_state" name="product_state" required>
                            <option value="new" @selected($currentState === 'new')>Neuf</option>
                            <option value="used" @selected($currentState === 'used')>Occasion</option>
                            <option value="refurbished" @selected($currentState === 'refurbished')>Reconditionné</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="sale_type">Type de vente <span>*</span></label>
                        <select id="sale_type" name="sale_type" required>
                            @foreach(['Vente normale', 'Vente flash', 'Black Friday', 'Promo spéciale'] as $saleType)
                                <option value="{{ $saleType }}" @selected(old('sale_type', $product->sale_type ?: 'Vente normale') === $saleType)>
                                    {{ $saleType }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field form-field-wide">
                        <label for="short_description">Description courte</label>
                        <textarea id="short_description" name="short_description" rows="3" maxlength="500">{{ old('short_description', $product->short_description) }}</textarea>
                        <small class="field-help">Résumé affiché sur les cartes et dans les résultats de recherche.</small>
                    </div>

                    <div class="form-field form-field-wide">
                        <label for="description">Description détaillée <span>*</span></label>
                        <textarea id="description" name="description" rows="7" required>{{ old('description', $product->description) }}</textarea>
                        @error('description')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field form-field-wide">
                        <label for="technical_details">Détails techniques</label>
                        <textarea id="technical_details" name="technical_details" rows="5">{{ old('technical_details', $product->technical_details) }}</textarea>
                    </div>
                </div>
            </section>

            <section class="edit-panel" id="commerce">
                <div class="panel-heading">
                    <span class="panel-number">2</span>
                    <div>
                        <h3>Prix, stock et conditionnement</h3>
                        <p>Informations propres à cette boutique et à cette offre commerciale.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="price">Prix normal (FCFA) <span>*</span></label>
                        <input id="price" name="price" type="number" min="1" step="1" value="{{ old('price', $product->price) }}" required>
                        @error('price')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="promo_price">Prix promotionnel (FCFA)</label>
                        <input id="promo_price" name="promo_price" type="number" min="0" step="1" value="{{ old('promo_price', $product->promo_price) }}">
                        @error('promo_price')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="stock">Stock disponible <span>*</span></label>
                        <input id="stock" name="stock" type="number" min="0" step="1" value="{{ old('stock', $product->stock) }}" required>
                    </div>

                    <div class="form-field">
                        <label for="availability_status">Disponibilité <span>*</span></label>
                        <select id="availability_status" name="availability_status" required>
                            <option value="in_stock" @selected($currentAvailability === 'in_stock')>En stock</option>
                            <option value="out_of_stock" @selected($currentAvailability === 'out_of_stock')>Rupture de stock</option>
                            <option value="on_order" @selected($currentAvailability === 'on_order')>Sur commande</option>
                            <option value="preorder" @selected($currentAvailability === 'preorder')>Précommande</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="unit">Unité de vente <span>*</span></label>
                        <select id="unit" name="unit" required>
                            @foreach(['unité', 'sac', 'carton', 'lot', 'palette', 'kilogramme', 'tonne', 'mètre', 'mètre carré', 'mètre cube', 'litre'] as $unit)
                                <option value="{{ $unit }}" @selected($currentUnit === $unit)>{{ ucfirst($unit) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="unit_label">Libellé personnalisé</label>
                        <input id="unit_label" name="unit_label" type="text" value="{{ old('unit_label', $product->unit_label) }}" placeholder="Ex. sac de 50 kg">
                    </div>

                    <div class="form-field">
                        <label for="min_order_quantity">Quantité minimale <span>*</span></label>
                        <input id="min_order_quantity" name="min_order_quantity" type="number" min="1" step="1" value="{{ old('min_order_quantity', $product->min_order_quantity ?: 1) }}" required>
                    </div>

                    <div class="form-field">
                        <label for="packaging">Conditionnement</label>
                        <input id="packaging" name="packaging" type="text" value="{{ old('packaging', $product->packaging) }}" placeholder="Ex. carton de 12 unités">
                    </div>
                </div>
            </section>

            <section class="edit-panel" id="logistique">
                <div class="panel-heading">
                    <span class="panel-number">3</span>
                    <div>
                        <h3>Données logistiques</h3>
                        <p>Ces données déterminent le véhicule, les frais de livraison et les besoins de manutention.</p>
                    </div>
                    <span class="logistics-state {{ $logisticsComplete ? 'is-ready' : 'is-incomplete' }}">
                        {{ $logisticsComplete ? 'Données complètes' : 'À compléter' }}
                    </span>
                </div>

                <div class="form-grid logistics-grid">
                    <div class="form-field">
                        <label for="weight_kg">Poids par unité (kg) <span>*</span></label>
                        <input id="weight_kg" name="weight_kg" type="number" min="0.01" step="0.01" value="{{ old('weight_kg', $product->weight_kg ?: $product->weight) }}" required>
                    </div>
                    <div class="form-field">
                        <label for="length_cm">Longueur (cm) <span>*</span></label>
                        <input id="length_cm" name="length_cm" type="number" min="0.1" step="0.1" value="{{ old('length_cm', $product->length_cm) }}" required>
                    </div>
                    <div class="form-field">
                        <label for="width_cm">Largeur (cm) <span>*</span></label>
                        <input id="width_cm" name="width_cm" type="number" min="0.1" step="0.1" value="{{ old('width_cm', $product->width_cm) }}" required>
                    </div>
                    <div class="form-field">
                        <label for="height_cm">Hauteur (cm) <span>*</span></label>
                        <input id="height_cm" name="height_cm" type="number" min="0.1" step="0.1" value="{{ old('height_cm', $product->height_cm) }}" required>
                    </div>
                    <div class="form-field volume-field">
                        <label for="volumePreview">Volume calculé</label>
                        <div class="calculated-value"><strong id="volumePreview">{{ number_format((float) $product->volume_m3, 6, ',', ' ') }}</strong> m³</div>
                        <small class="field-help">Recalculé automatiquement lors de l’enregistrement.</small>
                    </div>
                </div>

                <div class="choice-row">
                    <fieldset class="choice-group">
                        <legend>Produit fragile <span>*</span></legend>
                        <label class="choice-card">
                            <input type="radio" name="fragile" value="0" @checked((string) old('fragile', (int) $product->fragile) === '0') required>
                            <span><strong>Non</strong><small>Manipulation standard.</small></span>
                        </label>
                        <label class="choice-card">
                            <input type="radio" name="fragile" value="1" @checked((string) old('fragile', (int) $product->fragile) === '1') required>
                            <span><strong>Oui</strong><small>Précautions de transport.</small></span>
                        </label>
                    </fieldset>

                    <fieldset class="choice-group">
                        <legend>Déchargement requis <span>*</span></legend>
                        <label class="choice-card">
                            <input type="radio" name="requires_unloading" value="0" @checked((string) old('requires_unloading', (int) $product->requires_unloading) === '0') required>
                            <span><strong>Non</strong><small>Remise simple au client.</small></span>
                        </label>
                        <label class="choice-card">
                            <input type="radio" name="requires_unloading" value="1" @checked((string) old('requires_unloading', (int) $product->requires_unloading) === '1') required>
                            <span><strong>Oui</strong><small>Main-d’œuvre ou matériel nécessaire.</small></span>
                        </label>
                    </fieldset>
                </div>

                <div class="form-field form-field-wide" id="unloadingField">
                    <label for="unloading_instructions">Instructions de déchargement</label>
                    <textarea id="unloading_instructions" name="unloading_instructions" rows="4" placeholder="Ex. prévoir deux personnes et un transpalette">{{ old('unloading_instructions', $product->unloading_instructions) }}</textarea>
                    @error('unloading_instructions')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </section>

            <section class="edit-panel" id="publication">
                <div class="panel-heading">
                    <span class="panel-number">4</span>
                    <div>
                        <h3>Publication et opérations commerciales</h3>
                        <p>Contrôlez la visibilité de l’offre sans modifier les informations privées du vendeur.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="status">État de l’offre <span>*</span></label>
                        <select id="status" name="status" required>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Visibilité publique</label>
                        <input type="hidden" name="is_active" value="0">
                        <label class="switch-line">
                            <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $product->is_active))>
                            <span class="switch-control"></span>
                            <span><strong>Produit visible</strong><small>Disponible dans le catalogue public si les autres règles sont respectées.</small></span>
                        </label>
                    </div>

                    <div class="form-field">
                        <label>Livraison rapide</label>
                        <input type="hidden" name="fast_delivery" value="0">
                        <label class="switch-line">
                            <input type="checkbox" name="fast_delivery" value="1" @checked((bool) old('fast_delivery', $product->fast_delivery))>
                            <span class="switch-control"></span>
                            <span><strong>Option activée</strong><small>À utiliser uniquement lorsque la capacité logistique est confirmée.</small></span>
                        </label>
                    </div>

                    <div class="form-field">
                        <label>Commission réglée</label>
                        <input type="hidden" name="commission_paid" value="0">
                        <label class="switch-line">
                            <input type="checkbox" name="commission_paid" value="1" @checked((bool) old('commission_paid', $product->commission_paid))>
                            <span class="switch-control"></span>
                            <span><strong>Commission marquée comme payée</strong><small>Indicateur administratif interne.</small></span>
                        </label>
                    </div>

                    <div class="form-field">
                        <label>Protection des contacts</label>
                        <input type="hidden" name="lock_contacts" value="0">
                        <label class="switch-line">
                            <input type="checkbox" name="lock_contacts" value="1" @checked((bool) old('lock_contacts', $product->lock_contacts))>
                            <span class="switch-control"></span>
                            <span><strong>Contacts masqués</strong><small>Empêche l’exposition directe des coordonnées du vendeur.</small></span>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="flash_start_at">Début de la vente flash</label>
                        <input id="flash_start_at" name="flash_start_at" type="datetime-local" value="{{ old('flash_start_at', optional($product->flash_start_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="form-field">
                        <label for="flash_end">Fin de la vente flash</label>
                        <input id="flash_end" name="flash_end" type="datetime-local" value="{{ old('flash_end', optional($product->flash_end)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="form-field">
                        <label for="bf_start">Début de l’opération Black Friday</label>
                        <input id="bf_start" name="bf_start" type="datetime-local" value="{{ old('bf_start', optional($product->bf_start)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="form-field">
                        <label for="bf_end">Fin de l’opération Black Friday</label>
                        <input id="bf_end" name="bf_end" type="datetime-local" value="{{ old('bf_end', optional($product->bf_end)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>
            </section>

            <section class="edit-panel" id="images">
                <div class="panel-heading">
                    <span class="panel-number">5</span>
                    <div>
                        <h3>Images du produit</h3>
                        <p>Choisissez l’image principale, retirez les images obsolètes ou ajoutez de nouvelles photos.</p>
                    </div>
                    <span class="media-counter">{{ $product->images->count() }} image(s)</span>
                </div>

                @if($product->images->isNotEmpty())
                    <div class="existing-images-grid">
                        @foreach($product->images as $image)
                            <article class="existing-image-card">
                                <div class="existing-image-preview">
                                    <img src="{{ $image->card_url }}" alt="Image du produit {{ $product->name }}">
                                    @if($image->is_main)
                                        <span class="main-image-badge">Image actuelle</span>
                                    @endif
                                </div>
                                <div class="existing-image-controls">
                                    <label>
                                        <input type="radio" name="main_image" value="{{ $image->id }}" @checked((int) $currentMainImage === (int) $image->id)>
                                        Image principale
                                    </label>
                                    <label class="remove-image-control">
                                        <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" @checked(in_array($image->id, (array) old('remove_images', []), true))>
                                        Retirer cette image
                                    </label>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="empty-media-state">Aucune image n’est actuellement associée à ce produit.</div>
                @endif

                <div class="upload-zone">
                    <input id="imagesUpload" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                    <label for="imagesUpload">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14a2 2 0 0 0 2-2v-3"/><path d="M3 15v3a2 2 0 0 0 2 2"/></svg>
                        <strong>Ajouter de nouvelles images</strong>
                        <span>JPG, PNG ou WEBP · 5 Mo maximum par image · 8 images maximum</span>
                    </label>
                    <div id="newImagesPreview" class="new-images-preview" aria-live="polite"></div>
                </div>
            </section>

            <footer class="product-form-actions">
                <div class="product-form-actions-copy">
                    <span class="product-form-actions-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                    </span>
                    <div>
                        <strong>Dernière modification</strong>
                        <span>{{ optional($product->updated_at)->locale('fr')->translatedFormat('d F Y à H:i') }}</span>
                    </div>
                </div>
                <div class="product-form-actions-buttons">
                    <a href="{{ route('admin.products.index') }}" class="secondary-action">Annuler</a>
                    <button type="submit" class="primary-action" id="saveProductButton">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
                        Enregistrer les modifications
                    </button>
                </div>
            </footer>
        </form>

        <aside class="product-edit-sidebar">
            <section class="side-panel">
                <h3>Contrôle de la fiche</h3>
                <div class="control-list">
                    <div class="control-item {{ $product->name && $product->description ? 'is-valid' : 'is-warning' }}">
                        <span></span>
                        <div><strong>Informations générales</strong><small>{{ $product->name && $product->description ? 'Renseignées' : 'À compléter' }}</small></div>
                    </div>
                    <div class="control-item {{ (float) $product->price > 0 ? 'is-valid' : 'is-warning' }}">
                        <span></span>
                        <div><strong>Prix et stock</strong><small>{{ (float) $product->price > 0 ? 'Prix valide' : 'Prix manquant' }}</small></div>
                    </div>
                    <div class="control-item {{ $logisticsComplete ? 'is-valid' : 'is-warning' }}">
                        <span></span>
                        <div><strong>Livraison</strong><small>{{ $logisticsComplete ? 'Calcul possible' : 'Données incomplètes' }}</small></div>
                    </div>
                    <div class="control-item {{ $product->images->isNotEmpty() ? 'is-valid' : 'is-warning' }}">
                        <span></span>
                        <div><strong>Médias</strong><small>{{ $product->images->isNotEmpty() ? $product->images->count() . ' image(s)' : 'Aucune image' }}</small></div>
                    </div>
                </div>
            </section>

            <section class="side-panel">
                <h3>Informations de l’offre</h3>
                <dl class="offer-details">
                    <div><dt>Identifiant</dt><dd>#{{ $product->id }}</dd></div>
                    <div><dt>Créée le</dt><dd>{{ optional($product->created_at)->locale('fr')->translatedFormat('d/m/Y H:i') }}</dd></div>
                    <div><dt>Prix actuel</dt><dd>{{ number_format((float) $product->price, 0, ',', ' ') }} FCFA</dd></div>
                    <div><dt>Stock</dt><dd>{{ number_format((int) $product->stock, 0, ',', ' ') }}</dd></div>
                    <div><dt>Vues</dt><dd>{{ number_format((int) $product->views, 0, ',', ' ') }}</dd></div>
                    <div><dt>Ventes</dt><dd>{{ number_format((int) $product->sales, 0, ',', ' ') }}</dd></div>
                </dl>
            </section>

            <section class="side-panel danger-panel">
                <h3>Zone sensible</h3>
                <p>La suppression est définitive et retire également les images associées à cette offre.</p>
                <button type="button" class="danger-action" id="openDeleteDialog">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                    Supprimer le produit
                </button>
            </section>
        </aside>
    </div>
</div>

<dialog class="delete-dialog" id="deleteProductDialog">
    <form method="dialog" class="delete-dialog-card">
        <div class="delete-dialog-icon">!</div>
        <h3>Supprimer définitivement ce produit ?</h3>
        <p>Cette action supprimera l’offre et ses images. Elle ne peut pas être annulée.</p>
        <div class="delete-dialog-actions">
            <button type="submit" class="secondary-action">Conserver le produit</button>
            <button type="button" class="danger-action" id="confirmDeleteProduct">Confirmer la suppression</button>
        </div>
    </form>
</dialog>

<form method="POST" action="{{ route('admin.products.destroy', $product) }}" id="deleteProductForm" hidden>
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('productEditForm');
    const saveButton = document.getElementById('saveProductButton');
    const lengthInput = document.getElementById('length_cm');
    const widthInput = document.getElementById('width_cm');
    const heightInput = document.getElementById('height_cm');
    const volumePreview = document.getElementById('volumePreview');
    const unloadingField = document.getElementById('unloadingField');
    const unloadingInstructions = document.getElementById('unloading_instructions');
    const stockInput = document.getElementById('stock');
    const availabilitySelect = document.getElementById('availability_status');
    const uploadInput = document.getElementById('imagesUpload');
    const preview = document.getElementById('newImagesPreview');
    const dialog = document.getElementById('deleteProductDialog');

    const calculateVolume = () => {
        const length = Number(lengthInput.value || 0);
        const width = Number(widthInput.value || 0);
        const height = Number(heightInput.value || 0);
        const volume = (length * width * height) / 1000000;
        volumePreview.textContent = volume.toLocaleString('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        });
    };

    const syncUnloading = () => {
        const required = form.querySelector('input[name="requires_unloading"]:checked')?.value === '1';
        unloadingField.classList.toggle('is-hidden', !required);
        unloadingInstructions.required = required;
        if (!required) unloadingInstructions.value = '';
    };

    [lengthInput, widthInput, heightInput].forEach(input => input.addEventListener('input', calculateVolume));
    form.querySelectorAll('input[name="requires_unloading"]').forEach(input => input.addEventListener('change', syncUnloading));

    stockInput.addEventListener('input', () => {
        if (Number(stockInput.value || 0) === 0) availabilitySelect.value = 'out_of_stock';
    });

    uploadInput.addEventListener('change', () => {
        preview.replaceChildren();
        const files = [...uploadInput.files].slice(0, 8);

        files.forEach(file => {
            const item = document.createElement('div');
            item.className = 'new-image-item';
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = file.name;
            const name = document.createElement('span');
            name.textContent = file.name;
            item.append(image, name);
            preview.appendChild(item);
        });
    });

    form.addEventListener('submit', () => {
        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="button-spinner"></span> Enregistrement en cours…';
    });

    document.getElementById('openDeleteDialog')?.addEventListener('click', () => dialog.showModal());
    document.getElementById('confirmDeleteProduct')?.addEventListener('click', () => {
        document.getElementById('deleteProductForm').submit();
    });

    calculateVolume();
    syncUnloading();
})();
</script>
@endpush
