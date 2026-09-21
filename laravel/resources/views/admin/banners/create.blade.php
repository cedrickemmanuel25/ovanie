@extends('admin.layouts.app')

@section('title', 'Ajouter une bannière')

@section('content')
@php
    $zoneOptions = [
        'home_top' => 'Accueil - haut',
        'home_middle' => 'Accueil - milieu',
        'home_bottom' => 'Accueil - bas',
        'category_page' => 'Page catégorie',
    ];
@endphp

<main class="marketing-page">
    <section class="marketing-hero marketing-hero--compact">
        <div>
            <p class="marketing-eyebrow">MARKETING VISUEL</p>
            <h1 class="marketing-title">Ajouter une bannière</h1>
            <p class="marketing-subtitle">
                Importez un visuel, définissez sa zone d’affichage et configurez rapidement sa diffusion.
            </p>
        </div>

        <div class="marketing-hero__actions">
            <a href="{{ route('admin.banners.index') }}" class="marketing-btn marketing-btn--secondary">Retour aux bannières</a>
        </div>
    </section>

    @if($errors->any())
        <div class="marketing-alert marketing-alert--error">Veuillez corriger les champs signalés avant d’enregistrer.</div>
    @endif

    <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="marketing-form-layout">
        @csrf

        <section class="marketing-form-card">
            <div class="marketing-form-card__header">
                <div>
                    <p class="marketing-panel__eyebrow">FICHE DE CRÉATION</p>
                    <h2 class="marketing-form-card__title">Paramètres de la bannière</h2>
                </div>
            </div>

            <div class="marketing-form-grid">
                <div class="marketing-field marketing-field--full">
                    <label for="image">Image de la bannière *</label>
                    <input type="file" name="image" id="image" accept="image/*" required>
                    <small>Formats recommandés : JPG, PNG ou WEBP — 2 Mo maximum.</small>
                    @error('image') <div class="marketing-error">{{ $message }}</div> @enderror
                </div>

                <div class="marketing-field marketing-field--full">
                    <label for="link">Lien de redirection</label>
                    <input type="url" name="link" id="link" value="{{ old('link') }}" placeholder="https://www.ovanie.com/...">
                    @error('link') <div class="marketing-error">{{ $message }}</div> @enderror
                </div>

                <div class="marketing-field">
                    <label for="zone">Zone d’affichage *</label>
                    <select name="zone" id="zone" required>
                        @foreach($zoneOptions as $value => $label)
                            <option value="{{ $value }}" {{ old('zone', 'home_top') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('zone') <div class="marketing-error">{{ $message }}</div> @enderror
                </div>

                <div class="marketing-field">
                    <label for="position">Position d’affichage</label>
                    <input type="number" name="position" id="position" min="1" value="{{ old('position', 1) }}">
                    <small>1 = affichage prioritaire.</small>
                    @error('position') <div class="marketing-error">{{ $message }}</div> @enderror
                </div>

                <div class="marketing-field marketing-field--full">
                    <label class="marketing-switch">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="marketing-switch__slider"></span>
                        <span class="marketing-switch__label">Activer immédiatement cette bannière</span>
                    </label>
                </div>
            </div>

            <div class="marketing-form-actions">
                <a href="{{ route('admin.banners.index') }}" class="marketing-btn marketing-btn--ghost">Annuler</a>
                <button type="submit" class="marketing-btn marketing-btn--primary">Enregistrer la bannière</button>
            </div>
        </section>

        <aside class="marketing-side-card">
            <h3>Aperçu</h3>
            <div class="marketing-upload-preview" id="bannerPreviewBox">
                <span id="bannerPreviewPlaceholder">Votre visuel apparaîtra ici</span>
                <img src="" alt="Aperçu bannière" id="bannerPreviewImage" hidden>
            </div>

            <div class="marketing-side-card__list">
                <div>
                    <strong>Bonnes pratiques</strong>
                    <p>Utilisez un visuel lisible, léger et adapté à la zone d’affichage choisie.</p>
                </div>
                <div>
                    <strong>Activation</strong>
                    <p>Une bannière inactive reste enregistrée mais n’est pas diffusée sur la plateforme.</p>
                </div>
                <div>
                    <strong>Lien</strong>
                    <p>Ajoutez une URL complète pour rediriger l’utilisateur vers une page cible.</p>
                </div>
            </div>
        </aside>
    </form>
</main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_marketing_media.css') }}">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('image');
    const image = document.getElementById('bannerPreviewImage');
    const placeholder = document.getElementById('bannerPreviewPlaceholder');

    input?.addEventListener('change', function (event) {
        const file = event.target.files?.[0];
        if (!file) return;

        const url = URL.createObjectURL(file);
        image.src = url;
        image.hidden = false;
        placeholder.hidden = true;
    });
});
</script>
@endpush
