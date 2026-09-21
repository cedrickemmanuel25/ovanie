@extends('admin.layouts.app')

@section('title', 'Promotions | Administration OVANIE')
@section('page-title', 'Promotions')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_promotions.css') }}">
@endpush

@section('content')
@php
    $hasFilters = request()->filled('q') || request()->filled('type') || request()->filled('status') || request()->filled('sort');
@endphp

<div class="promo-page">
    <header class="promo-hero">
        <div class="promo-hero-copy">
            <span class="promo-kicker">ANIMATION COMMERCIALE</span>
            <h2>Gestion des promotions</h2>
            <p>
                Programmez les remises, sélectionnez les produits concernés et suivez leur période de diffusion
                sans modifier les prix de base des boutiques.
            </p>
        </div>

        <div class="promo-hero-actions">
            <a href="{{ route('admin.products.index') }}" class="promo-btn promo-btn-secondary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 7V5h8v2" /></svg>
                Voir les produits
            </a>
            <a href="{{ route('admin.promotions.create') }}" class="promo-btn promo-btn-primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Nouvelle promotion
            </a>
        </div>
    </header>

    <section class="promo-stats" aria-label="Résumé des promotions">
        <article class="promo-stat-card">
            <div class="promo-stat-icon is-blue">P</div>
            <div>
                <span>Promotions enregistrées</span>
                <strong>{{ number_format($summary['total'], 0, ',', ' ') }}</strong>
                <small>Toutes les campagnes</small>
            </div>
        </article>

        <article class="promo-stat-card">
            <div class="promo-stat-icon is-green">✓</div>
            <div>
                <span>Actives maintenant</span>
                <strong>{{ number_format($summary['active'], 0, ',', ' ') }}</strong>
                <small>Visibles pendant leur période</small>
            </div>
        </article>

        <article class="promo-stat-card">
            <div class="promo-stat-icon is-orange">◷</div>
            <div>
                <span>Programmées</span>
                <strong>{{ number_format($summary['upcoming'], 0, ',', ' ') }}</strong>
                <small>Démarrent prochainement</small>
            </div>
        </article>

        <article class="promo-stat-card">
            <div class="promo-stat-icon is-purple">▦</div>
            <div>
                <span>Produits concernés</span>
                <strong>{{ number_format($summary['linked_products'], 0, ',', ' ') }}</strong>
                <small>Produits reliés à une promotion</small>
            </div>
        </article>
    </section>

    <section class="promo-filter-card">
        <div class="promo-section-heading">
            <div>
                <span class="promo-section-kicker">RECHERCHE ET FILTRES</span>
                <h3>Retrouver une promotion</h3>
            </div>
            @if($hasFilters)
                <a href="{{ route('admin.promotions.index') }}" class="promo-reset-link">Réinitialiser</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.promotions.index') }}" class="promo-filter-form">
            <div class="promo-field promo-search-field">
                <label for="q">Nom ou code</label>
                <div class="promo-input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Ex. BLACK-FRIDAY">
                </div>
            </div>

            <div class="promo-field">
                <label for="type">Type de remise</label>
                <select id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="percent" @selected(request('type') === 'percent')>Pourcentage</option>
                    <option value="fixed" @selected(request('type') === 'fixed')>Montant fixe</option>
                </select>
            </div>

            <div class="promo-field">
                <label for="status">État</label>
                <select id="status" name="status">
                    <option value="">Tous les états</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="upcoming" @selected(request('status') === 'upcoming')>Programmée</option>
                    <option value="expired" @selected(request('status') === 'expired')>Terminée</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Désactivée</option>
                </select>
            </div>

            <div class="promo-field">
                <label for="sort">Classement</label>
                <select id="sort" name="sort">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Plus récentes</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciennes</option>
                    <option value="start_asc" @selected(request('sort') === 'start_asc')>Début le plus proche</option>
                    <option value="end_asc" @selected(request('sort') === 'end_asc')>Fin la plus proche</option>
                    <option value="value_desc" @selected(request('sort') === 'value_desc')>Remise la plus forte</option>
                </select>
            </div>

            <button type="submit" class="promo-search-button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                Rechercher
            </button>
        </form>
    </section>

    <section class="promo-list-card">
        <div class="promo-section-heading promo-list-heading">
            <div>
                <span class="promo-section-kicker">CAMPAGNES PROMOTIONNELLES</span>
                <h3>Promotions enregistrées</h3>
                <p>{{ number_format($promotions->total(), 0, ',', ' ') }} résultat(s)</p>
            </div>
        </div>

        @if($promotions->isEmpty())
            <div class="promo-empty-state">
                <div class="promo-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 7V5h8v2M8 13h8" /></svg>
                </div>
                <h4>{{ $hasFilters ? 'Aucune promotion ne correspond aux filtres.' : 'Aucune promotion enregistrée.' }}</h4>
                <p>
                    {{ $hasFilters
                        ? 'Modifiez les critères ou réinitialisez les filtres.'
                        : 'Créez une campagne, définissez sa période puis sélectionnez les produits concernés.' }}
                </p>
                @unless($hasFilters)
                    <a href="{{ route('admin.promotions.create') }}" class="promo-btn promo-btn-primary">Créer la première promotion</a>
                @endunless
            </div>
        @else
            <div class="promo-list">
                @foreach($promotions as $promotion)
                    <article class="promo-card">
                        <div class="promo-card-main">
                            <div class="promo-card-title">
                                <div class="promo-code-icon">%</div>
                                <div>
                                    <div class="promo-title-line">
                                        <h4>{{ $promotion->code }}</h4>
                                        <span class="promo-status promo-status-{{ $promotion->status_tone }}">{{ $promotion->status_label }}</span>
                                    </div>
                                    <p>{{ $promotion->type_label }}</p>
                                </div>
                            </div>

                            <div class="promo-value-block">
                                <span>Avantage accordé</span>
                                <strong>{{ $promotion->value_label }}</strong>
                            </div>

                            <div class="promo-period-block">
                                <div>
                                    <span>Début</span>
                                    <strong>{{ $promotion->starts_at?->locale('fr')->translatedFormat('d F Y à H:i') }}</strong>
                                </div>
                                <div>
                                    <span>Fin</span>
                                    <strong>{{ $promotion->ends_at?->locale('fr')->translatedFormat('d F Y à H:i') }}</strong>
                                </div>
                            </div>

                            <div class="promo-usage-block">
                                <div>
                                    <span>Produits</span>
                                    <strong>{{ number_format($promotion->products_count, 0, ',', ' ') }}</strong>
                                </div>
                                <div>
                                    <span>Commandes</span>
                                    <strong>{{ number_format($promotion->orders_count, 0, ',', ' ') }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="promo-card-actions">
                            <form action="{{ route('admin.promotions.toggle', $promotion) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="promo-action-button promo-action-toggle">
                                    {{ $promotion->is_active ? 'Désactiver' : 'Activer' }}
                                </button>
                            </form>

                            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="promo-action-button promo-action-edit">Modifier</a>

                            <form action="{{ route('admin.promotions.destroy', $promotion) }}" method="POST" onsubmit="return confirm('Supprimer définitivement cette promotion ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="promo-action-button promo-action-delete">Supprimer</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($promotions->hasPages())
                <div class="promo-pagination">
                    <p>
                        Affichage de {{ $promotions->firstItem() }} à {{ $promotions->lastItem() }}
                        sur {{ $promotions->total() }} résultat(s)
                    </p>
                    <div class="promo-pagination-links">
                        @if($promotions->onFirstPage())
                            <span class="is-disabled">Précédent</span>
                        @else
                            <a href="{{ $promotions->previousPageUrl() }}">Précédent</a>
                        @endif

                        @foreach($promotions->getUrlRange(max(1, $promotions->currentPage() - 2), min($promotions->lastPage(), $promotions->currentPage() + 2)) as $page => $url)
                            @if($page === $promotions->currentPage())
                                <span class="is-current">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($promotions->hasMorePages())
                            <a href="{{ $promotions->nextPageUrl() }}">Suivant</a>
                        @else
                            <span class="is-disabled">Suivant</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </section>
</div>
@endsection
