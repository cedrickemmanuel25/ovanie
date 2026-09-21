@extends('admin.layouts.app')

@section('title', 'Pubs accueil')

@section('content')
@php
    $activeAds = $ads->where('is_active', true)->count();
    $imageAds = $ads->where('type', 'image')->count();
    $videoAds = $ads->where('type', 'video')->count();
@endphp

<main class="marketing-page">
    <section class="marketing-hero">
        <div>
            <p class="marketing-eyebrow">PUBLICITÉS D’ACCUEIL</p>
            <h1 class="marketing-title">Pubs accueil</h1>
            <p class="marketing-subtitle">
                Gérez les contenus image et vidéo qui s’affichent sur la page d’accueil OVANIE,
                avec un suivi clair de l’ordre, du statut et de la diffusion.
            </p>
        </div>

        <div class="marketing-hero__actions">
            @if(Route::has('admin.banners.index'))
                <a href="{{ route('admin.banners.index') }}" class="marketing-btn marketing-btn--secondary">Voir les bannières</a>
            @endif
            <a href="{{ route('admin.home-ads.create') }}" class="marketing-btn marketing-btn--primary">+ Ajouter une pub</a>
        </div>
    </section>

    @if(session('success'))
        <div class="marketing-alert marketing-alert--success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="marketing-alert marketing-alert--error">Une erreur est survenue. Vérifiez les champs du formulaire.</div>
    @endif

    <section class="marketing-stats-grid">
        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Total pubs</span>
            <strong class="marketing-stat-card__value">{{ $ads->count() }}</strong>
            <span class="marketing-stat-card__meta">Toutes les publicités enregistrées</span>
        </article>
        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Actives</span>
            <strong class="marketing-stat-card__value">{{ $activeAds }}</strong>
            <span class="marketing-stat-card__meta">Diffusées sur l’accueil</span>
        </article>
        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Images</span>
            <strong class="marketing-stat-card__value">{{ $imageAds }}</strong>
            <span class="marketing-stat-card__meta">Formats statiques</span>
        </article>
        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Vidéos</span>
            <strong class="marketing-stat-card__value">{{ $videoAds }}</strong>
            <span class="marketing-stat-card__meta">Formats dynamiques</span>
        </article>
    </section>

    <section class="marketing-panel">
        <div class="marketing-panel__header marketing-panel__header--space">
            <div>
                <p class="marketing-panel__eyebrow">BIBLIOTHÈQUE DES PUBS</p>
                <h2 class="marketing-panel__title">Liste des publicités</h2>
                <p class="marketing-panel__subtitle">Recherchez une publicité, filtrez-la et mettez à jour son ordre d’affichage.</p>
            </div>
        </div>

        <div class="marketing-toolbar">
            <div class="marketing-toolbar__search">
                <input type="text" id="adsTableSearch" placeholder="Rechercher une pub par titre ou texte...">
            </div>

            <div class="marketing-toolbar__filters">
                <button type="button" class="marketing-btn marketing-btn--secondary marketing-btn--sm is-active" id="filterAllBtn">Toutes</button>
                <button type="button" class="marketing-btn marketing-btn--secondary marketing-btn--sm" id="filterActiveBtn">Actives</button>
                <button type="button" class="marketing-btn marketing-btn--secondary marketing-btn--sm" id="filterVideoBtn">Vidéos</button>
            </div>
        </div>

        @if($ads->count())
            <div class="homeads-list" id="adsCardsContainer">
                @foreach($ads as $ad)
                    <article class="homeads-card" draggable="true" data-id="{{ $ad->id }}" data-title="{{ strtolower($ad->title ?? '') }}" data-text="{{ strtolower($ad->text ?? '') }}" data-type="{{ $ad->type }}" data-status="{{ $ad->is_active ? 'active' : 'inactive' }}">
                        <div class="homeads-card__order">
                            <button type="button" class="homeads-card__drag" title="Déplacer">⋮⋮</button>
                            <span class="homeads-card__order-badge">{{ $ad->sort_order }}</span>
                        </div>

                        <div class="homeads-card__media">
                            @if($ad->type === 'image')
                                <img src="{{ asset('storage/' . $ad->media) }}" alt="{{ $ad->title ?: 'Publicité image' }}">
                            @else
                                <video muted playsinline preload="metadata">
                                    <source src="{{ asset('storage/' . $ad->media) }}">
                                </video>
                            @endif
                        </div>

                        <div class="homeads-card__content">
                            <div class="homeads-card__chips">
                                <span class="marketing-chip {{ $ad->type === 'video' ? 'marketing-chip--video' : 'marketing-chip--image' }}">{{ $ad->type === 'video' ? 'Vidéo' : 'Image' }}</span>
                                <span class="marketing-chip {{ $ad->is_active ? 'marketing-chip--success' : 'marketing-chip--muted' }}">{{ $ad->is_active ? 'Active' : 'Inactive' }}</span>
                                @if($ad->placement)
                                    <span class="marketing-chip marketing-chip--light">{{ str_replace('_', ' ', $ad->placement) }}</span>
                                @endif
                            </div>

                            <h3>{{ $ad->title ?: 'Publicité sans titre' }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($ad->text ?: 'Aucun texte descriptif.', 150) }}</p>

                            <div class="homeads-card__meta">
                                <div><span>Durée</span><strong>{{ $ad->duration }} ms</strong></div>
                                <div><span>Diffusion</span><strong>{{ $ad->starts_at ? $ad->starts_at->format('d/m/Y H:i') : 'Immédiate' }}</strong></div>
                                <div><span>Lien</span><strong>{{ $ad->link ? \Illuminate\Support\Str::limit($ad->link, 36) : 'Aucun' }}</strong></div>
                            </div>
                        </div>

                        <div class="homeads-card__actions">
                            <a href="{{ route('admin.home-ads.edit', $ad) }}" class="marketing-btn marketing-btn--ghost marketing-btn--sm">Modifier</a>
                            <form action="{{ route('admin.home-ads.destroy', $ad) }}" method="POST" onsubmit="return confirm('Supprimer cette publicité ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="marketing-btn marketing-btn--danger marketing-btn--sm">Supprimer</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="marketing-reorder-bar">
                <div id="adsReorderStatus">Glissez-déposez les cartes pour modifier l’ordre d’affichage.</div>
                <button type="button" class="marketing-btn marketing-btn--primary" id="saveAdsOrderBtn">Enregistrer l’ordre</button>
            </div>
        @else
            <div class="marketing-empty-state">
                <div class="marketing-empty-state__icon">🎬</div>
                <h3>Aucune publicité accueil</h3>
                <p>Ajoutez une publicité image ou vidéo pour démarrer la diffusion sur la page d’accueil.</p>
                <a href="{{ route('admin.home-ads.create') }}" class="marketing-btn marketing-btn--primary">Créer une pub</a>
            </div>
        @endif
    </section>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_marketing_media.css') }}">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('adsTableSearch');
    const cardsContainer = document.getElementById('adsCardsContainer');
    const cards = () => Array.from(document.querySelectorAll('.homeads-card'));
    const filterAllBtn = document.getElementById('filterAllBtn');
    const filterActiveBtn = document.getElementById('filterActiveBtn');
    const filterVideoBtn = document.getElementById('filterVideoBtn');
    const saveOrderBtn = document.getElementById('saveAdsOrderBtn');
    const reorderStatus = document.getElementById('adsReorderStatus');

    let currentFilter = 'all';
    let draggedCard = null;

    const filterButtons = [filterAllBtn, filterActiveBtn, filterVideoBtn].filter(Boolean);

    function updateButtons(activeButton) {
        filterButtons.forEach(btn => btn.classList.remove('is-active'));
        activeButton?.classList.add('is-active');
    }

    function applyFilters() {
        const query = (searchInput?.value || '').toLowerCase().trim();

        cards().forEach(card => {
            const title = card.dataset.title || '';
            const text = card.dataset.text || '';
            const type = card.dataset.type || '';
            const status = card.dataset.status || '';

            const matchesSearch = !query || title.includes(query) || text.includes(query);
            const matchesFilter =
                currentFilter === 'all' ? true :
                currentFilter === 'active' ? status === 'active' :
                currentFilter === 'video' ? type === 'video' : true;

            card.style.display = matchesSearch && matchesFilter ? '' : 'none';
        });
    }

    function updateOrderBadges() {
        cards().forEach((card, index) => {
            const badge = card.querySelector('.homeads-card__order-badge');
            if (badge) badge.textContent = index;
        });
    }

    function setStatus(message, type = '') {
        if (!reorderStatus) return;
        reorderStatus.textContent = message;
        reorderStatus.classList.remove('is-success', 'is-error');
        if (type) reorderStatus.classList.add(type);
    }

    function attachDnD(card) {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            card.classList.add('is-dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            cards().forEach(item => item.classList.remove('is-drag-over'));
        });

        card.addEventListener('dragover', event => {
            event.preventDefault();
            if (!draggedCard || draggedCard === card) return;
            card.classList.add('is-drag-over');
        });

        card.addEventListener('dragleave', () => {
            card.classList.remove('is-drag-over');
        });

        card.addEventListener('drop', event => {
            event.preventDefault();
            card.classList.remove('is-drag-over');
            if (!draggedCard || draggedCard === card || !cardsContainer) return;

            const allCards = cards();
            const draggedIndex = allCards.indexOf(draggedCard);
            const targetIndex = allCards.indexOf(card);

            if (draggedIndex < targetIndex) {
                cardsContainer.insertBefore(draggedCard, card.nextSibling);
            } else {
                cardsContainer.insertBefore(draggedCard, card);
            }

            updateOrderBadges();
            setStatus('Ordre modifié. N’oubliez pas d’enregistrer.', 'is-success');
        });
    }

    filterAllBtn?.addEventListener('click', () => { currentFilter = 'all'; updateButtons(filterAllBtn); applyFilters(); });
    filterActiveBtn?.addEventListener('click', () => { currentFilter = 'active'; updateButtons(filterActiveBtn); applyFilters(); });
    filterVideoBtn?.addEventListener('click', () => { currentFilter = 'video'; updateButtons(filterVideoBtn); applyFilters(); });
    searchInput?.addEventListener('input', applyFilters);

    saveOrderBtn?.addEventListener('click', async () => {
        const payload = cards().map((card, index) => ({
            id: Number(card.dataset.id),
            sort_order: index,
        }));

        try {
            const response = await fetch("{{ route('admin.home-ads.reorder') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ items: payload })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Impossible d’enregistrer l’ordre.');
            }

            setStatus(result.message || 'Ordre enregistré avec succès.', 'is-success');
        } catch (error) {
            setStatus(error.message || 'Une erreur est survenue.', 'is-error');
        }
    });

    cards().forEach(attachDnD);
    applyFilters();
});
</script>
@endpush
