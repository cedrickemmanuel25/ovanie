@php
    $selectedIds = collect(old('product_ids', $selectedProducts ?? []))->map(fn ($id) => (int) $id);
    $isEdit = $promotion->exists;
@endphp

<div class="promo-editor-layout">
    <div class="promo-editor-main">
        <section class="promo-form-card">
            <div class="promo-form-heading">
                <span class="promo-step">1</span>
                <div>
                    <h3>Informations de la promotion</h3>
                    <p>Le code est affiché dans l’administration et peut être utilisé comme code promotionnel.</p>
                </div>
            </div>

            <div class="promo-form-grid">
                <div class="promo-field promo-field-wide">
                    <label for="code">Nom ou code de la promotion <span>*</span></label>
                    <input type="text" id="code" name="code" value="{{ old('code', $promotion->code) }}" maxlength="50" placeholder="Ex. BLACK-FRIDAY-2026" required>
                    <small>Les espaces sont automatiquement remplacés par des tirets.</small>
                </div>

                <div class="promo-field">
                    <label for="type">Type de remise <span>*</span></label>
                    <select id="type" name="type" required>
                        <option value="percent" @selected(old('type', $promotion->type) === 'percent')>Pourcentage</option>
                        <option value="fixed" @selected(old('type', $promotion->type) === 'fixed')>Montant fixe en FCFA</option>
                    </select>
                </div>

                <div class="promo-field">
                    <label for="value">Valeur de la remise <span>*</span></label>
                    <div class="promo-value-input">
                        <input type="number" id="value" name="value" value="{{ old('value', $promotion->value) }}" min="0.01" step="0.01" required>
                        <span id="promotionValueUnit">{{ old('type', $promotion->type) === 'fixed' ? 'FCFA' : '%' }}</span>
                    </div>
                </div>

                <label class="promo-switch-field promo-field-wide">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $promotion->is_active))>
                    <span class="promo-switch-control"></span>
                    <span>
                        <strong>Promotion activée</strong>
                        <small>Elle sera appliquée uniquement pendant la période définie.</small>
                    </span>
                </label>
            </div>
        </section>

        <section class="promo-form-card">
            <div class="promo-form-heading">
                <span class="promo-step">2</span>
                <div>
                    <h3>Période de diffusion</h3>
                    <p>La date de fin doit être postérieure à la date de début.</p>
                </div>
            </div>

            <div class="promo-form-grid">
                <div class="promo-field">
                    <label for="starts_at">Début <span>*</span></label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', optional($promotion->starts_at)->format('Y-m-d\TH:i')) }}" required>
                </div>

                <div class="promo-field">
                    <label for="ends_at">Fin <span>*</span></label>
                    <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', optional($promotion->ends_at)->format('Y-m-d\TH:i')) }}" required>
                </div>
            </div>
        </section>

        <section class="promo-form-card">
            <div class="promo-form-heading promo-product-heading">
                <span class="promo-step">3</span>
                <div>
                    <h3>Produits concernés</h3>
                    <p>Sélectionnez les offres auxquelles la remise doit s’appliquer.</p>
                </div>
                <span class="promo-selection-count"><strong id="selectedProductsCount">{{ $selectedIds->count() }}</strong> sélectionné(s)</span>
            </div>

            <div class="promo-product-tools">
                <div class="promo-input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                    <input type="search" id="promotionProductSearch" placeholder="Rechercher un produit, une boutique ou une catégorie">
                </div>
                <button type="button" class="promo-small-button" id="selectVisibleProducts">Sélectionner les visibles</button>
                <button type="button" class="promo-small-button" id="clearProductSelection">Tout désélectionner</button>
            </div>

            <div class="promo-product-grid" id="promotionProductGrid">
                @forelse($products as $product)
                    @php
                        $searchText = mb_strtolower(trim(($product->name ?? '') . ' ' . ($product->brand ?? '') . ' ' . ($product->shop?->name ?? '') . ' ' . ($product->category?->name ?? '')));
                    @endphp
                    <label class="promo-product-option" data-search="{{ $searchText }}">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked($selectedIds->contains($product->id))>
                        <span class="promo-product-check">✓</span>
                        <span class="promo-product-image">
                            @if($product->card_image_url)
                                <img src="{{ $product->card_image_url }}" alt="">
                            @else
                                <span>{{ strtoupper(mb_substr($product->name ?? 'P', 0, 1)) }}</span>
                            @endif
                        </span>
                        <span class="promo-product-copy">
                            <strong>{{ $product->name }}</strong>
                            <small>{{ $product->shop?->name ?? 'Boutique non renseignée' }}</small>
                            <em>{{ $product->category?->name ?? 'Sans catégorie' }} · {{ number_format((float) $product->price, 0, ',', ' ') }} FCFA</em>
                        </span>
                    </label>
                @empty
                    <div class="promo-products-empty">Aucun produit n’est disponible.</div>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="promo-editor-sidebar">
        <section class="promo-side-card">
            <h3>Résumé de la campagne</h3>
            <dl>
                <div><dt>État</dt><dd>{{ $promotion->exists ? $promotion->status_label : 'Nouvelle' }}</dd></div>
                <div><dt>Type</dt><dd id="promotionTypeSummary">{{ old('type', $promotion->type) === 'fixed' ? 'Montant fixe' : 'Pourcentage' }}</dd></div>
                <div><dt>Produits</dt><dd id="promotionProductsSummary">{{ $selectedIds->count() }}</dd></div>
                @if($promotion->exists)
                    <div><dt>Créée le</dt><dd>{{ $promotion->created_at?->locale('fr')->translatedFormat('d F Y') }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="promo-side-card promo-info-card">
            <h3>Règle de fonctionnement</h3>
            <p>
                La promotion ne modifie pas le prix normal du produit. Elle s’applique seulement si elle est active
                et si la date actuelle est comprise entre le début et la fin.
            </p>
        </section>
    </aside>
</div>

<footer class="promo-save-bar">
    <div class="promo-save-copy">
        <span class="promo-save-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
        </span>
        <div>
            <span>{{ $isEdit ? 'Mise à jour de la campagne' : 'Nouvelle promotion' }}</span>
            <strong>{{ $isEdit ? optional($promotion->updated_at)->locale('fr')->translatedFormat('d F Y à H:i') : 'Les données seront enregistrées en base après validation.' }}</strong>
        </div>
    </div>
    <div class="promo-save-actions">
        <a href="{{ route('admin.promotions.index') }}" class="promo-btn promo-btn-secondary">Annuler</a>
        <button type="submit" class="promo-btn promo-btn-primary">{{ $isEdit ? 'Enregistrer les modifications' : 'Créer la promotion' }}</button>
    </div>
</footer>
