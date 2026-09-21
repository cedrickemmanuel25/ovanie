@extends('admin.layouts.app')

@section('title', 'Produits | Administration OVANIE')
@section('page-title', 'Produits')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_products.css') }}">
@endpush

@section('content')
@php
    $activeStatuses = ['actif', 'active', 'approved', 'published'];
@endphp

<div class="admin-products-page">
    <section class="products-heading">
        <div>
            <span class="page-kicker">Catalogue marketplace</span>
            <h2>Gestion des produits</h2>
            <p>Contrôlez la publication, le stock, les informations logistiques et l’origine de chaque offre.</p>
        </div>

        <div class="products-heading-actions">
            <a href="{{ route('admin.master-products.index') }}" class="secondary-action">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg>
                Catalogue de références
            </a>
            <a href="{{ route('admin.products.create') }}" class="primary-action">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Ajouter un produit
            </a>
        </div>
    </section>

    <section class="products-summary" aria-label="Résumé des produits">
        <article class="summary-card summary-total">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7l8-4 8 4-8 4-8-4zM4 7v10l8 4 8-4V7M12 11v10"/></svg>
            </div>
            <div>
                <span>Produits enregistrés</span>
                <strong>{{ number_format((int) ($summary['total'] ?? 0), 0, ',', ' ') }}</strong>
                <small>Toutes les offres des boutiques</small>
            </div>
        </article>

        <article class="summary-card summary-published">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
            </div>
            <div>
                <span>Produits publiés</span>
                <strong>{{ number_format((int) ($summary['published'] ?? 0), 0, ',', ' ') }}</strong>
                <small>Actifs dans le catalogue</small>
            </div>
        </article>

        <article class="summary-card summary-stock">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4zM8 10h8M12 6v12"/></svg>
            </div>
            <div>
                <span>Ruptures de stock</span>
                <strong>{{ number_format((int) ($summary['out_of_stock'] ?? 0), 0, ',', ' ') }}</strong>
                <small>Produits indisponibles</small>
            </div>
        </article>

        <article class="summary-card summary-logistics">
            <div class="summary-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 4.7L2.7 18a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 4.7a2 2 0 00-3.4 0z"/></svg>
            </div>
            <div>
                <span>Logistique incomplète</span>
                <strong>{{ number_format((int) ($summary['incomplete_logistics'] ?? 0), 0, ',', ' ') }}</strong>
                <small>Poids ou dimensions à compléter</small>
            </div>
        </article>
    </section>

    <form method="GET" action="{{ route('admin.products.index') }}" class="product-filter-card">
        <div class="filter-field filter-search">
            <label for="search">Recherche</label>
            <div class="search-input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Nom, marque, SKU, boutique ou vendeur"
                >
            </div>
        </div>

        <div class="filter-field">
            <label for="shop_id">Boutique</label>
            <select id="shop_id" name="shop_id">
                <option value="">Toutes les boutiques</option>
                @foreach($shops as $shop)
                    <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>
                        {{ $shop->name }} ({{ $shop->products_count }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="category_id">Catégorie</label>
            <select id="category_id" name="category_id">
                <option value="">Toutes les catégories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-field">
            <label for="status">État</label>
            <select id="status" name="status">
                <option value="">Tous les états</option>
                <option value="active" @selected(request('status') === 'active')>Publié</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Non publié</option>
                <option value="out_of_stock" @selected(request('status') === 'out_of_stock')>Rupture de stock</option>
                <option value="draft" @selected(request('status') === 'draft')>Brouillon</option>
                <option value="pending_logistics" @selected(request('status') === 'pending_logistics')>En attente logistique</option>
                <option value="incomplete_logistics" @selected(request('status') === 'incomplete_logistics')>Données logistiques manquantes</option>
                <option value="archived" @selected(request('status') === 'archived')>Archivé</option>
            </select>
        </div>

        <div class="filter-field">
            <label for="logistics_type">Mode logistique</label>
            <select id="logistics_type" name="logistics_type">
                <option value="">Tous les modes</option>
                <option value="ovanie" @selected(request('logistics_type') === 'ovanie')>OVANIE Logistics</option>
                <option value="seller" @selected(request('logistics_type') === 'seller')>Logistique vendeur</option>
            </select>
        </div>

        <div class="filter-field">
            <label for="sort">Classement</label>
            <select id="sort" name="sort">
                <option value="latest" @selected(request('sort', 'latest') === 'latest')>Plus récents</option>
                <option value="name" @selected(request('sort') === 'name')>Nom A à Z</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Prix croissant</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Prix décroissant</option>
                <option value="stock_asc" @selected(request('sort') === 'stock_asc')>Stock croissant</option>
                <option value="stock_desc" @selected(request('sort') === 'stock_desc')>Stock décroissant</option>
            </select>
        </div>

        <div class="filter-actions">
            <a href="{{ route('admin.products.index') }}" class="filter-reset">Réinitialiser</a>
            <button type="submit" class="filter-submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                Rechercher
            </button>
        </div>
    </form>

    <section class="product-table-card">
        <header class="products-panel-header">
            <div>
                <span class="panel-kicker">Résultats</span>
                <h3>{{ number_format($products->total(), 0, ',', ' ') }} produit(s)</h3>
            </div>
            <p>Page {{ $products->currentPage() }} sur {{ max(1, $products->lastPage()) }}</p>
        </header>

        <div class="products-list">
            @forelse($products as $product)
                @php
                    $rawStatus = strtolower((string) ($product->status ?? ''));
                    $isArchived = $rawStatus === 'archived' || !empty($product->archived_at);
                    $isOutOfStock = (int) $product->stock <= 0;
                    $isDraft = in_array($rawStatus, ['draft', 'brouillon'], true);
                    $isPendingLogistics = in_array($rawStatus, ['pending_logistics', 'incomplete', 'logistics_incomplete'], true);
                    $isPublished = !$isArchived
                        && !$isDraft
                        && !$isPendingLogistics
                        && (bool) $product->is_active
                        && in_array($rawStatus, $activeStatuses, true);

                    $statusLabel = match (true) {
                        $isArchived => 'Archivé',
                        $isDraft => 'Brouillon',
                        $isPendingLogistics => 'En attente logistique',
                        $isOutOfStock && $isPublished => 'Publié · rupture',
                        $isPublished => 'Publié',
                        default => 'Non publié',
                    };

                    $statusClass = match (true) {
                        $isArchived => 'archived',
                        $isDraft => 'draft',
                        $isPendingLogistics => 'warning',
                        $isOutOfStock && $isPublished => 'stockout',
                        $isPublished => 'active',
                        default => 'inactive',
                    };

                    $weight = (float) ($product->weight_kg ?: $product->weight ?: 0);
                    $length = (float) ($product->length_cm ?: 0);
                    $width = (float) ($product->width_cm ?: 0);
                    $height = (float) ($product->height_cm ?: 0);
                    $volume = (float) ($product->volume_m3 ?: 0);
                    $metricsComplete = $weight > 0 && $length > 0 && $width > 0 && $height > 0 && $volume > 0;

                    $logisticsType = $product->shop?->logistics_type === 'seller' ? 'seller' : 'ovanie';
                    $logisticsLabel = $logisticsType === 'seller' ? 'Logistique vendeur' : 'OVANIE Logistics';
                    $shopLogisticsReady = in_array((string) $product->shop?->logistics_status, ['ready', 'approved'], true);
                @endphp

                <article class="product-row">
                    <div class="product-identity">
                        <a href="{{ route('product.show', $product) }}" class="product-thumb" target="_blank" rel="noopener" aria-label="Voir {{ $product->name }}">
                            <img src="{{ $product->card_image_url }}" alt="{{ $product->name }}" loading="lazy">
                        </a>
                        <div>
                            <div class="product-name-line">
                                <a href="{{ route('product.show', $product) }}" target="_blank" rel="noopener">{{ $product->name }}</a>
                                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>
                            <p>{{ $product->brand ?: 'Marque non renseignée' }}</p>
                            <div class="identity-tags">
                                <span>#{{ $product->id }}</span>
                                <span>{{ $product->sku ?: 'Sans SKU' }}</span>
                                <span>{{ $product->category?->name ?: 'Sans catégorie' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="product-detail-grid">
                        <div class="detail-block shop-cell">
                            <span class="detail-label">Boutique</span>
                            <strong>{{ $product->shop?->name ?: 'Boutique supprimée' }}</strong>
                            <small>{{ $product->shop?->user?->name ?: $product->shop?->user?->email ?: 'Vendeur non renseigné' }}</small>
                        </div>

                        <div class="detail-block price-stock-cell">
                            <span class="detail-label">Prix et stock</span>
                            <strong>{{ number_format((float) $product->price, 0, ',', ' ') }} FCFA</strong>
                            @if($product->promo_price && $product->promo_price < $product->price)
                                <small>Promotion : {{ number_format((float) $product->promo_price, 0, ',', ' ') }} FCFA</small>
                            @endif
                            <small class="{{ $isOutOfStock ? 'danger-text' : '' }}">
                                {{ number_format((int) $product->stock, 0, ',', ' ') }} en stock
                            </small>
                        </div>

                        <div class="detail-block transport-cell">
                            <span class="detail-label">Logistique</span>
                            <span class="logistics-badge {{ $logisticsType }}">{{ $logisticsLabel }}</span>
                            <small>{{ $weight > 0 ? number_format($weight, 2, ',', ' ') . ' kg' : 'Poids manquant' }}</small>
                            @if(!$metricsComplete)
                                <em>Données produit incomplètes</em>
                            @elseif(!$shopLogisticsReady)
                                <em>Configuration boutique à vérifier</em>
                            @else
                                <small class="success-text">Prêt pour le calcul de livraison</small>
                            @endif
                        </div>

                        <div class="detail-block date-cell">
                            <span class="detail-label">Ajouté le</span>
                            <strong>{{ optional($product->created_at)->format('d/m/Y') ?: '—' }}</strong>
                            <small>{{ optional($product->created_at)->format('H:i') ?: '' }}</small>
                        </div>
                    </div>

                    <div class="row-actions">
                        <a href="{{ route('admin.products.edit', $product) }}" class="row-action edit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
                            Modifier
                        </a>
                        <a href="{{ route('product.show', $product) }}" class="row-action view" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            Voir
                        </a>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Supprimer définitivement ce produit ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action delete">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M8 7l1 13h6l1-13"/></svg>
                                Supprimer
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7l8-4 8 4-8 4-8-4zM4 7v10l8 4 8-4V7M12 11v10"/></svg>
                    </div>
                    <h3>Aucun produit trouvé</h3>
                    <p>Modifiez les filtres ou ajoutez un nouveau produit au catalogue.</p>
                    <a href="{{ route('admin.products.create') }}" class="primary-action">Ajouter un produit</a>
                </div>
            @endforelse
        </div>

        @if($products->hasPages())
            <div class="pagination-wrap">
                {{ $products->links('admin.partials.pagination-fr') }}
            </div>
        @endif
    </section>
</div>
@endsection
