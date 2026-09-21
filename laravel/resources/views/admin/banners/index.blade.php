@extends('admin.layouts.app')

@section('title', 'Bannières publicitaires')

@section('content')
@php
    $zoneLabels = [
        'home_top' => 'Accueil - haut',
        'home_middle' => 'Accueil - milieu',
        'home_bottom' => 'Accueil - bas',
        'category_page' => 'Page catégorie',
    ];
@endphp

<main class="marketing-page">
    <section class="marketing-hero">
        <div>
            <p class="marketing-eyebrow">MARKETING VISUEL</p>
            <h1 class="marketing-title">Bannières publicitaires</h1>
            <p class="marketing-subtitle">
                Centralisez les visuels promotionnels diffusés sur la plateforme, pilotez les zones d’affichage
                et gardez une vue rapide sur les bannières actives.
            </p>
        </div>

        <div class="marketing-hero__actions">
            @if(Route::has('admin.home-ads.index'))
                <a href="{{ route('admin.home-ads.index') }}" class="marketing-btn marketing-btn--secondary">
                    Voir les pubs accueil
                </a>
            @endif
            <a href="{{ route('admin.banners.create') }}" class="marketing-btn marketing-btn--primary">
                + Ajouter une bannière
            </a>
        </div>
    </section>

    @if(session('success'))
        <div class="marketing-alert marketing-alert--success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="marketing-alert marketing-alert--error">Certaines informations doivent être corrigées.</div>
    @endif

    <section class="marketing-stats-grid">
        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Total des bannières</span>
            <strong class="marketing-stat-card__value">{{ $banners->count() }}</strong>
            <span class="marketing-stat-card__meta">Toutes zones confondues</span>
        </article>

        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Bannières actives</span>
            <strong class="marketing-stat-card__value">{{ $banners->where('is_active', true)->count() }}</strong>
            <span class="marketing-stat-card__meta">Affichées actuellement</span>
        </article>

        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Bannières inactives</span>
            <strong class="marketing-stat-card__value">{{ $banners->where('is_active', false)->count() }}</strong>
            <span class="marketing-stat-card__meta">En attente de diffusion</span>
        </article>

        <article class="marketing-stat-card">
            <span class="marketing-stat-card__label">Zones utilisées</span>
            <strong class="marketing-stat-card__value">{{ $banners->pluck('zone')->filter()->unique()->count() }}</strong>
            <span class="marketing-stat-card__meta">Emplacements configurés</span>
        </article>
    </section>

    <section class="marketing-panel">
        <div class="marketing-panel__header">
            <div>
                <p class="marketing-panel__eyebrow">GESTION DES BANNIÈRES</p>
                <h2 class="marketing-panel__title">Catalogue des bannières</h2>
                <p class="marketing-panel__subtitle">Prévisualisez chaque visuel, son lien cible et son emplacement.</p>
            </div>
        </div>

        @if($banners->count())
            <div class="banner-grid">
                @foreach($banners as $banner)
                    <article class="banner-card">
                        <div class="banner-card__preview">
                            <img src="{{ asset('storage/' . $banner->image) }}" alt="Bannière {{ $banner->id }}">
                        </div>

                        <div class="banner-card__body">
                            <div class="banner-card__topline">
                                <span class="marketing-chip marketing-chip--light">
                                    {{ $zoneLabels[$banner->zone] ?? ($banner->zone ?: 'Zone non définie') }}
                                </span>
                                <span class="marketing-chip {{ $banner->is_active ? 'marketing-chip--success' : 'marketing-chip--muted' }}">
                                    {{ $banner->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <h3 class="banner-card__title">Bannière #{{ $banner->id }}</h3>

                            <div class="banner-card__meta-list">
                                <div class="banner-card__meta-item">
                                    <span>Position</span>
                                    <strong>{{ $banner->position ?? 1 }}</strong>
                                </div>
                                <div class="banner-card__meta-item banner-card__meta-item--full">
                                    <span>Lien</span>
                                    @if($banner->link)
                                        <a href="{{ $banner->link }}" target="_blank" rel="noopener">
                                            {{ \Illuminate\Support\Str::limit($banner->link, 60) }}
                                        </a>
                                    @else
                                        <strong>Aucun lien défini</strong>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="banner-card__footer">
                            @if($banner->link)
                                <a href="{{ $banner->link }}" target="_blank" rel="noopener" class="marketing-btn marketing-btn--ghost marketing-btn--sm">
                                    Ouvrir le lien
                                </a>
                            @else
                                <span class="marketing-note">Pas de redirection configurée</span>
                            @endif

                            <form method="POST" action="{{ route('admin.banners.destroy', $banner->id) }}" onsubmit="return confirm('Supprimer cette bannière ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="marketing-btn marketing-btn--danger marketing-btn--sm">Supprimer</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="marketing-empty-state">
                <div class="marketing-empty-state__icon">🖼️</div>
                <h3>Aucune bannière enregistrée</h3>
                <p>Commencez par ajouter une bannière pour alimenter les espaces publicitaires de la plateforme.</p>
                <a href="{{ route('admin.banners.create') }}" class="marketing-btn marketing-btn--primary">Créer une bannière</a>
            </div>
        @endif
    </section>
</main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_marketing_media.css') }}">
@endpush
