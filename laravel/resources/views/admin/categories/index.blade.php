@extends('admin.layouts.app')

@section('title', 'Gestion des catégories | Administration OVANIE')
@section('page-title', 'Gestion des catégories')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_categories.css') }}?v={{ file_exists(public_path('admin/css/admin_categories.css')) ? filemtime(public_path('admin/css/admin_categories.css')) : time() }}">
@endpush

@section('content')
<div class="category-admin-page">
    <header class="category-page-header">
        <div>
            <span class="category-eyebrow">STRUCTURE DU CATALOGUE</span>
            <h2>Catégories et sous-catégories</h2>
            <p>Organisez les univers produits. Toute catégorie active est enregistrée en base et devient disponible dans les formulaires, le catalogue et les menus alimentés par la base.</p>
        </div>

        <div class="category-header-actions category-header-actions-inline">
            <a class="category-btn category-btn-secondary" href="{{ route('admin.categories.create', ['type' => 'subcategory']) }}">
                <span>＋</span> Ajouter une sous-catégorie
            </a>
            <a class="category-btn category-btn-primary" href="{{ route('admin.categories.create') }}">
                <span>＋</span> Nouvelle catégorie
            </a>
        </div>
    </header>

    <section class="category-kpis" aria-label="Indicateurs des catégories">
        <article class="category-kpi">
            <span class="category-kpi-icon is-blue">▦</span>
            <div><small>Total</small><strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong><p>Catégories enregistrées</p></div>
        </article>
        <article class="category-kpi">
            <span class="category-kpi-icon is-orange">◆</span>
            <div><small>Univers principaux</small><strong>{{ number_format($stats['roots'], 0, ',', ' ') }}</strong><p>Catégories de premier niveau</p></div>
        </article>
        <article class="category-kpi">
            <span class="category-kpi-icon is-violet">↳</span>
            <div><small>Sous-catégories</small><strong>{{ number_format($stats['children'], 0, ',', ' ') }}</strong><p>Classements détaillés</p></div>
        </article>
        <article class="category-kpi">
            <span class="category-kpi-icon is-green">✓</span>
            <div><small>Actives</small><strong>{{ number_format($stats['active'], 0, ',', ' ') }}</strong><p>Disponibles sur la plateforme</p></div>
        </article>
        <article class="category-kpi">
            <span class="category-kpi-icon is-slate">▣</span>
            <div><small>Produits classés</small><strong>{{ number_format($stats['assigned_products'], 0, ',', ' ') }}</strong><p>{{ number_format($stats['without_category'], 0, ',', ' ') }} produit(s) sans catégorie</p></div>
        </article>
    </section>

    <form class="category-filter-bar" method="GET" action="{{ route('admin.categories.index') }}">
        <label class="category-search-field">
            <span aria-hidden="true">⌕</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher une catégorie ou une sous-catégorie…">
        </label>

        <select name="type" aria-label="Filtrer par niveau">
            <option value="">Tous les niveaux</option>
            <option value="principales" @selected($type === 'principales')>Catégories principales</option>
            <option value="sous-categories" @selected($type === 'sous-categories')>Sous-catégories</option>
        </select>

        <select name="status" aria-label="Filtrer par état">
            <option value="">Tous les états</option>
            <option value="actif" @selected($status === 'actif')>Actives</option>
            <option value="inactif" @selected($status === 'inactif')>Inactives</option>
        </select>

        <button class="category-btn category-btn-dark" type="submit">Rechercher</button>
        @if($search !== '' || $status !== '' || $type !== '')
            <a class="category-filter-reset" href="{{ route('admin.categories.index') }}">Réinitialiser</a>
        @endif
    </form>

    <section class="category-tree-section">
        <div class="category-section-heading">
            <div>
                <h3>Arborescence du catalogue</h3>
                <p>Les produits peuvent être rattachés à une catégorie principale ou, de préférence, à une sous-catégorie précise.</p>
            </div>
            <span>{{ $categories->count() }} univers affiché(s)</span>
        </div>

        <div class="category-tree-list">
            @forelse($categories as $category)
                <article class="category-parent-card">
                    <div class="category-parent-main">
                        <div class="category-parent-icon">
                            @if($category->image_url)
                                <img src="{{ $category->image_url }}" alt="{{ $category->name }}">
                            @else
                                {{ $category->icon ? mb_strtoupper(mb_substr($category->icon, 0, 1)) : mb_strtoupper(mb_substr($category->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="category-parent-copy">
                            <div class="category-title-row">
                                <h4>{{ $category->name }}</h4>
                                <span class="category-status {{ $category->status === 'actif' ? 'is-active' : 'is-inactive' }}">{{ $category->status_label }}</span>
                            </div>
                            <p>{{ $category->description ?: 'Aucune description renseignée.' }}</p>
                            <div class="category-meta-row">
                                <span>{{ $category->products_count }} produit(s) directement rattaché(s)</span>
                                <span>{{ $category->children_count }} sous-catégorie(s)</span>
                                <span>Ordre : {{ $category->sort_order }}</span>
                                <code>{{ $category->slug }}</code>
                            </div>
                        </div>

                        <div class="category-card-actions">
                            <a href="{{ route('admin.categories.create', ['type' => 'subcategory', 'parent_id' => $category->id]) }}" class="category-action-link is-primary">Ajouter une sous-catégorie</a>
                            <a href="{{ route('admin.categories.edit', $category) }}" class="category-action-link">Modifier</a>
                            <form method="POST" action="{{ route('admin.categories.status', $category) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $category->status === 'actif' ? 'inactif' : 'actif' }}">
                                <button type="submit" class="category-action-link is-button">{{ $category->status === 'actif' ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="category-children-area">
                        <div class="category-children-heading">
                            <strong>Sous-catégories</strong>
                            <span>{{ $category->children->count() }} affichée(s)</span>
                        </div>

                        @forelse($category->children as $child)
                            <div class="category-child-row">
                                <span class="category-child-connector" aria-hidden="true">↳</span>
                                <div class="category-child-copy">
                                    <div>
                                        <strong>{{ $child->name }}</strong>
                                        <span class="category-status {{ $child->status === 'actif' ? 'is-active' : 'is-inactive' }}">{{ $child->status_label }}</span>
                                    </div>
                                    <p>{{ $child->description ?: 'Sous-catégorie sans description.' }}</p>
                                </div>
                                <div class="category-child-metrics">
                                    <span>{{ $child->products_count }} produit(s)</span>
                                    <span>{{ $child->master_products_count }} référence(s)</span>
                                </div>
                                <div class="category-child-actions">
                                    <a href="{{ route('admin.categories.edit', $child) }}">Modifier</a>
                                    <form method="POST" action="{{ route('admin.categories.status', $child) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $child->status === 'actif' ? 'inactif' : 'actif' }}">
                                        <button type="submit">{{ $child->status === 'actif' ? 'Désactiver' : 'Activer' }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="category-empty-children">
                                <span>Cette catégorie ne contient pas encore de sous-catégorie.</span>
                                <a href="{{ route('admin.categories.create', ['type' => 'subcategory', 'parent_id' => $category->id]) }}">Créer la première</a>
                            </div>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="category-empty-state">
                    <span>▦</span>
                    <h3>Aucune catégorie trouvée</h3>
                    <p>Créez une catégorie principale pour commencer à structurer le catalogue OVANIE.</p>
                    <a class="category-btn category-btn-primary" href="{{ route('admin.categories.create') }}">Créer une catégorie</a>
                </div>
            @endforelse
        </div>
    </section>

    <aside class="category-sync-note">
        <div class="category-sync-icon">✓</div>
        <div>
            <strong>Synchronisation automatique</strong>
            <p>Les formulaires produits, le catalogue public et les menus alimentés par la base utilisent directement la table <code>categories</code>. Après création ou modification, les caches concernés sont automatiquement supprimés.</p>
        </div>
    </aside>
</div>
@endsection
