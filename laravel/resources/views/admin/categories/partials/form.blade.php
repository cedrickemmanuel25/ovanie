@php
    $isSubcategory = ($categoryType ?? ($category->parent_id ? 'subcategory' : 'category')) === 'subcategory';
    $selectedParent = old('parent_id', $category->parent_id);
@endphp

<div class="category-form-layout">
    <div class="category-form-main">
        @if($isSubcategory)
            <section class="category-form-card category-parent-selection-card">
                <div class="category-form-card-heading">
                    <span>1</span>
                    <div>
                        <h3>Catégorie principale de rattachement</h3>
                        <p>Choisissez l’univers dans lequel cette sous-catégorie sera affichée.</p>
                    </div>
                </div>

                <div class="category-field category-field-highlighted">
                    <label for="parent_id">Catégorie principale <em>*</em></label>
                    <select id="parent_id" name="parent_id" required>
                        <option value="">Sélectionner une catégorie principale</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" @selected((string) $selectedParent === (string) $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                    <small>Seules les catégories principales peuvent recevoir des sous-catégories.</small>
                    @error('parent_id')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>
            </section>
        @endif

        <section class="category-form-card">
            <div class="category-form-card-heading">
                <span>{{ $isSubcategory ? '2' : '1' }}</span>
                <div>
                    <h3>{{ $isSubcategory ? 'Informations de la sous-catégorie' : 'Identité de la catégorie principale' }}</h3>
                    <p>{{ $isSubcategory
                        ? 'Le nom sera proposé dans les formulaires produits après sélection de la catégorie principale.'
                        : 'Le nom représentera un univers général du catalogue et pourra contenir plusieurs sous-catégories.' }}</p>
                </div>
            </div>

            <div class="category-form-grid">
                <div class="category-field is-wide">
                    <label for="name">Nom <em>*</em></label>
                    <input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $category->name) }}" placeholder="{{ $isSubcategory ? 'Ex. Disjoncteurs modulaires' : 'Ex. Électricité et plomberie' }}">
                    <small>Le slug d’URL est généré automatiquement et reste unique à ce niveau.</small>
                    @error('name')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>

                <div class="category-field">
                    <label for="icon">Icône facultative</label>
                    <input id="icon" name="icon" type="text" maxlength="80" value="{{ old('icon', $category->icon) }}" placeholder="Ex. plug-zap">
                    <small>Nom d’une icône utilisée par votre interface, sans espace.</small>
                    @error('icon')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>

                <div class="category-field is-wide">
                    <label for="image">Photo de la catégorie</label>
                    @if($category->image_url)
                        <div class="category-field-current-image">
                            <img src="{{ $category->image_url }}" alt="{{ $category->name }}">
                            <label class="category-field-checkbox">
                                <input type="checkbox" name="remove_image" value="1">
                                Supprimer la photo actuelle
                            </label>
                        </div>
                    @endif
                    <input id="image" name="image" type="file" accept="image/*">
                    <small>Utilisée sur la page d’accueil et partout où la catégorie est affichée avec une vignette. Format image, 4 Mo max.</small>
                    @error('image')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>

                <div class="category-field">
                    <label for="sort_order">Ordre d’affichage</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
                    <small>Les valeurs les plus faibles s’affichent en premier.</small>
                    @error('sort_order')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>

                <div class="category-field is-wide">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5" maxlength="1200" placeholder="{{ $isSubcategory ? 'Précisez les produits qui doivent être classés dans cette sous-catégorie…' : 'Décrivez l’univers de produits couvert par cette catégorie principale…' }}">{{ old('description', $category->description) }}</textarea>
                    <small>Cette description aide l’administration à conserver une arborescence cohérente.</small>
                    @error('description')<p class="category-field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="category-form-card">
            <div class="category-form-card-heading">
                <span>{{ $isSubcategory ? '3' : '2' }}</span>
                <div>
                    <h3>Disponibilité sur la plateforme</h3>
                    <p>Choisissez si cet élément peut immédiatement être utilisé dans les formulaires et le catalogue.</p>
                </div>
            </div>

            <div class="category-status-options">
                <label>
                    <input type="radio" name="status" value="actif" @checked(old('status', $category->status ?: 'actif') === 'actif')>
                    <span>
                        <strong>Active</strong>
                        <small>Disponible dans les formulaires produits, les filtres et les pages concernées.</small>
                    </span>
                </label>
                <label>
                    <input type="radio" name="status" value="inactif" @checked(old('status', $category->status) === 'inactif')>
                    <span>
                        <strong>Inactive</strong>
                        <small>Conservée dans la base mais masquée des nouveaux parcours de classement.</small>
                    </span>
                </label>
            </div>
            @error('status')<p class="category-field-error">{{ $message }}</p>@enderror
        </section>
    </div>

    <aside class="category-form-sidebar">
        <section class="category-side-card category-side-card-primary">
            <span class="category-side-badge">{{ $isSubcategory ? 'SOUS-CATÉGORIE' : 'CATÉGORIE PRINCIPALE' }}</span>
            <h3>{{ $isSubcategory ? 'Rôle de ce classement' : 'Rôle de cet univers' }}</h3>
            <p>{{ $isSubcategory
                ? 'Une sous-catégorie précise le type de produit à l’intérieur d’un univers principal. Elle améliore la recherche, les filtres et l’ajout rapide.'
                : 'Une catégorie principale structure un grand univers du catalogue. Elle doit rester générale et accueillir des sous-catégories plus précises.' }}</p>
        </section>

        <section class="category-side-card">
            <h3>Diffusion automatique</h3>
            <ul>
                <li><span>✓</span> Enregistrement dans la table <code>categories</code></li>
                <li><span>✓</span> Formulaires d’ajout et de modification produit</li>
                <li><span>✓</span> Filtres du catalogue et recherche</li>
                <li><span>✓</span> Menus alimentés depuis la base</li>
                <li><span>✓</span> Catalogue de références techniques</li>
            </ul>
        </section>

        <section class="category-side-card is-warning">
            <h3>Protection des données</h3>
            <p>Un élément utilisé par des produits, des références techniques ou des sous-catégories ne peut pas être supprimé. Il peut être désactivé sans perdre les données existantes.</p>
        </section>
    </aside>
</div>
