@extends('admin.layouts.app')

@section('title', 'Catalogue de références | Admin OVANIE')
@section('page-title', 'Catalogue de références')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_catalogue.css') }}">
@endpush

@section('content')
@php
    $cleanText = static function (?string $value, string $fallback = 'Non renseigné'): string {
        $value = trim((string) $value);
        if ($value === '') return $fallback;
        $value = preg_replace('/\?{2,}|�/u', '', $value) ?? $value;
        return \Illuminate\Support\Str::squish($value) ?: $fallback;
    };
    $hasTextIssue = static fn (?string $value): bool => (bool) preg_match('/\?{2,}|�|Ã|Â/u', (string) $value);
@endphp

<div class="reference-workspace">
    <header class="reference-hero">
        <div class="reference-hero-copy">
            <span class="reference-eyebrow">Référentiel produit OVANIE</span>
            <h2>Centralisez les fiches techniques des produits</h2>
            <p>Une référence technique regroupe les informations communes d’un produit. Les boutiques conservent leurs propres prix, stocks et disponibilités.</p>
        </div>
        <div class="reference-hero-actions reference-hero-actions-inline">
            <a href="{{ route('admin.products.index') }}" class="reference-button secondary">
                <span aria-hidden="true">▦</span>
                Offres des boutiques
            </a>
            <a href="{{ route('admin.master-products.create') }}" class="reference-button primary">
                <span aria-hidden="true">＋</span>
                Nouvelle référence
            </a>
        </div>
    </header>

    <section class="reference-kpis" aria-label="Indicateurs du catalogue">
        <article class="reference-kpi">
            <span class="reference-kpi-icon blue">R</span>
            <div><small>Références techniques</small><strong>{{ number_format($stats['references_total'], 0, ',', ' ') }}</strong><span>{{ number_format($stats['references_active'], 0, ',', ' ') }} active(s)</span></div>
        </article>
        <article class="reference-kpi">
            <span class="reference-kpi-icon green">O</span>
            <div><small>Offres déjà rattachées</small><strong>{{ number_format($stats['linked_offers'], 0, ',', ' ') }}</strong><span>Produits de boutiques reliés</span></div>
        </article>
        <article class="reference-kpi">
            <span class="reference-kpi-icon orange">À</span>
            <div><small>Produits à rattacher</small><strong>{{ number_format($stats['unlinked_products'], 0, ',', ' ') }}</strong><span>Nécessitent une fiche commune</span></div>
        </article>
        <article class="reference-kpi">
            <span class="reference-kpi-icon red">!</span>
            <div><small>Références incomplètes</small><strong>{{ number_format($stats['references_incomplete'], 0, ',', ' ') }}</strong><span>Poids ou dimensions manquants</span></div>
        </article>
    </section>

    <nav class="reference-tabs" aria-label="Navigation du catalogue">
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'references', 'references_page' => null]) }}" class="reference-tab {{ $activeTab === 'references' ? 'active' : '' }}">
            Références techniques
            <span>{{ number_format($stats['references_total'], 0, ',', ' ') }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'products', 'products_page' => null]) }}" class="reference-tab {{ $activeTab === 'products' ? 'active' : '' }}">
            Produits à rattacher
            <span>{{ number_format($stats['unlinked_products'], 0, ',', ' ') }}</span>
        </a>
    </nav>

    @if($activeTab === 'references')
        <section class="reference-panel">
            <div class="reference-panel-heading">
                <div>
                    <h3>Références techniques</h3>
                    <p>Recherchez, contrôlez et mettez à jour les fiches partagées entre les boutiques.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.master-products.index') }}" class="reference-filterbar">
                <input type="hidden" name="tab" value="references">
                <label class="reference-search-field">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="reference_search" value="{{ request('reference_search') }}" placeholder="Nom, marque, SKU ou référence fabricant">
                </label>
                <select name="reference_category" aria-label="Filtrer par catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) request('reference_category') === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="reference_logistics" aria-label="Filtrer les données logistiques">
                    <option value="all">Toutes les données logistiques</option>
                    <option value="complete" @selected(request('reference_logistics') === 'complete')>Données complètes</option>
                    <option value="incomplete" @selected(request('reference_logistics') === 'incomplete')>Données à compléter</option>
                </select>
                <select name="reference_state" aria-label="Filtrer par état">
                    <option value="all">Tous les états</option>
                    <option value="active" @selected(request('reference_state') === 'active')>Actives</option>
                    <option value="inactive" @selected(request('reference_state') === 'inactive')>Inactives</option>
                </select>
                <button type="submit" class="reference-button primary compact">Rechercher</button>
                @if(request()->hasAny(['reference_search','reference_category','reference_logistics','reference_state']))
                    <a href="{{ route('admin.master-products.index', ['tab' => 'references']) }}" class="reference-reset">Réinitialiser</a>
                @endif
            </form>

            @forelse($masterProducts as $master)
                @php
                    $logisticsComplete = (float) $master->weight_kg > 0
                        && (float) $master->length_cm > 0
                        && (float) $master->width_cm > 0
                        && (float) $master->height_cm > 0;
                @endphp
                <article class="reference-row-card">
                    <div class="reference-row-main">
                        <span class="reference-monogram">{{ mb_strtoupper(mb_substr($cleanText($master->name, 'R'), 0, 1)) }}</span>
                        <div class="reference-row-copy">
                            <div class="reference-row-titleline">
                                <h4>{{ $cleanText($master->name, 'Référence sans nom') }}</h4>
                                <span class="status-pill {{ $master->is_active ? 'success' : 'neutral' }}">{{ $master->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <p>{{ $cleanText($master->brand, 'Marque non renseignée') }} · {{ $cleanText($master->sku ?: $master->reference, 'Sans identifiant') }}</p>
                            <div class="reference-meta-pills">
                                <span>{{ $master->category?->name ?: 'Catégorie non définie' }}</span>
                                <span>{{ $master->unit ?: 'Unité non définie' }}</span>
                                <span>{{ $master->packaging ?: 'Conditionnement non renseigné' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="reference-row-data">
                        <div><small>Logistique</small><strong class="{{ $logisticsComplete ? 'text-success' : 'text-warning' }}">{{ $logisticsComplete ? 'Complète' : 'À compléter' }}</strong><span>{{ $master->weight_kg ? number_format((float) $master->weight_kg, 2, ',', ' ').' kg' : 'Poids manquant' }}</span></div>
                        <div><small>Offres liées</small><strong>{{ number_format($master->products_count, 0, ',', ' ') }}</strong><span>boutique{{ $master->products_count > 1 ? 's' : '' }}</span></div>
                    </div>
                    <div class="reference-row-actions">
                        <a href="{{ route('admin.master-products.edit', $master) }}" class="reference-button secondary compact">Modifier</a>
                    </div>
                </article>
            @empty
                <div class="reference-empty-state">
                    <span class="reference-empty-icon">◇</span>
                    <h4>Aucune référence ne correspond à votre recherche</h4>
                    <p>Créez la première référence technique ou transformez un produit existant en fiche commune.</p>
                    <div>
                        <a href="{{ route('admin.master-products.create') }}" class="reference-button primary">Créer une référence</a>
                        <a href="{{ route('admin.master-products.index', ['tab' => 'products']) }}" class="reference-button secondary">Voir les produits à rattacher</a>
                    </div>
                </div>
            @endforelse

            {{ $masterProducts->links('admin.partials.pagination-fr') }}
        </section>
    @else
        <section class="reference-panel">
            <div class="reference-panel-heading split">
                <div>
                    <h3>Produits à rattacher</h3>
                    <p>Transformez les offres existantes en références techniques communes sans modifier leurs prix ni leurs stocks.</p>
                </div>
                <span class="reference-panel-count">{{ number_format($unlinkedProducts->total(), 0, ',', ' ') }} résultat(s)</span>
            </div>

            <form method="GET" action="{{ route('admin.master-products.index') }}" class="reference-filterbar product-filters">
                <input type="hidden" name="tab" value="products">
                <label class="reference-search-field">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="product_search" value="{{ request('product_search') }}" placeholder="Produit, marque, boutique ou SKU">
                </label>
                <select name="product_shop" aria-label="Filtrer par boutique">
                    <option value="">Toutes les boutiques</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" @selected((int) request('product_shop') === (int) $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                <select name="product_category" aria-label="Filtrer par catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) request('product_category') === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="product_quality" aria-label="Filtrer par qualité des données">
                    <option value="all">Toutes les fiches</option>
                    <option value="ready" @selected(request('product_quality') === 'ready')>Prêtes à transformer</option>
                    <option value="incomplete" @selected(request('product_quality') === 'incomplete')>Données incomplètes</option>
                    <option value="text_issue" @selected(request('product_quality') === 'text_issue')>Texte à corriger</option>
                </select>
                <button type="submit" class="reference-button primary compact">Rechercher</button>
                @if(request()->hasAny(['product_search','product_shop','product_category','product_quality']))
                    <a href="{{ route('admin.master-products.index', ['tab' => 'products']) }}" class="reference-reset">Réinitialiser</a>
                @endif
            </form>

            <div class="unlinked-product-grid">
                @forelse($unlinkedProducts as $product)
                    @php
                        $logisticsComplete = (float) $product->weight_kg > 0
                            && (float) $product->length_cm > 0
                            && (float) $product->width_cm > 0
                            && (float) $product->height_cm > 0;
                        $textIssue = $hasTextIssue($product->name) || $hasTextIssue($product->shop?->name) || $hasTextIssue($product->category?->name);
                        $displayName = $cleanText($product->name, 'Produit sans nom');
                    @endphp
                    <article class="unlinked-product-card">
                        <div class="unlinked-product-media">
                            @if($product->thumb_image_url)
                                <img src="{{ $product->thumb_image_url }}" alt="{{ $displayName }}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                <span class="unlinked-product-fallback" style="display:none">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                            @else
                                <span class="unlinked-product-fallback" style="display:grid">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="unlinked-product-body">
                            <div class="unlinked-product-topline">
                                <span class="offer-number">Offre #{{ $product->id }}</span>
                                @if($textIssue)<span class="status-pill danger">Texte à corriger</span>@endif
                            </div>
                            <h4 title="{{ $product->name }}">{{ $displayName }}</h4>
                            <p class="unlinked-shop">{{ $cleanText($product->shop?->name, 'Boutique non disponible') }}</p>
                            <div class="unlinked-product-tags">
                                <span>{{ $cleanText($product->category?->name, 'Sans catégorie') }}</span>
                                <span>{{ $product->unit ?: 'Unité absente' }}</span>
                                <span>{{ $product->stock ?? 0 }} en stock</span>
                            </div>
                            <div class="unlinked-quality">
                                <div><small>Données logistiques</small><strong class="{{ $logisticsComplete ? 'text-success' : 'text-warning' }}">{{ $logisticsComplete ? 'Prêtes' : 'À compléter' }}</strong></div>
                                <div><small>Poids</small><strong>{{ (float) $product->weight_kg > 0 ? number_format((float) $product->weight_kg, 2, ',', ' ').' kg' : 'Non renseigné' }}</strong></div>
                            </div>
                        </div>
                        <div class="unlinked-product-actions">
                            <a href="{{ route('admin.master-products.create', ['source_product_id' => $product->id]) }}" class="reference-button primary compact">Créer la référence</a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="reference-button secondary compact">Corriger l’offre</a>
                        </div>
                    </article>
                @empty
                    <div class="reference-empty-state wide">
                        <span class="reference-empty-icon success">✓</span>
                        <h4>Aucun produit à rattacher</h4>
                        <p>Toutes les offres correspondant à ces filtres possèdent déjà une référence technique.</p>
                    </div>
                @endforelse
            </div>

            {{ $unlinkedProducts->links('admin.partials.pagination-fr') }}
        </section>
    @endif
</div>
@endsection
