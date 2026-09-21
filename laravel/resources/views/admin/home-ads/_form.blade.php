@csrf
@if(isset($homeAd))
    @method('PUT')
@endif

<div class="marketing-form-layout">
    <section class="marketing-form-card">
        <div class="marketing-form-card__header">
            <div>
                <p class="marketing-panel__eyebrow">PARAMÉTRAGE</p>
                <h2 class="marketing-form-card__title">Informations de la publicité</h2>
            </div>
        </div>

        <div class="marketing-form-grid">
            <div class="marketing-field">
                <label for="type">Type *</label>
                <select name="type" id="type">
                    <option value="image" {{ old('type', $homeAd->type ?? 'image') === 'image' ? 'selected' : '' }}>Image</option>
                    <option value="video" {{ old('type', $homeAd->type ?? '') === 'video' ? 'selected' : '' }}>Vidéo</option>
                </select>
                @error('type') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="placement">Emplacement sur l’accueil</label>
                <select name="placement" id="placement">
                    <option value="">Bande publicitaire générale</option>
                    <option value="home_top" {{ old('placement', $homeAd->placement ?? '') === 'home_top' ? 'selected' : '' }}>Haut de page</option>
                    <option value="home_middle" {{ old('placement', $homeAd->placement ?? '') === 'home_middle' ? 'selected' : '' }}>Milieu de page</option>
                    <option value="home_bottom" {{ old('placement', $homeAd->placement ?? '') === 'home_bottom' ? 'selected' : '' }}>Bas de page</option>
                    <option value="season_sale" {{ old('placement', $homeAd->placement ?? '') === 'season_sale' ? 'selected' : '' }}>Soldes de saison</option>
                </select>
                <small>Cette information permet de router le contenu vers la bonne zone.</small>
                @error('placement') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="sort_order">Ordre d’affichage</label>
                <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $homeAd->sort_order ?? 0) }}">
                <small>Plus le nombre est petit, plus la publicité apparaît tôt.</small>
                @error('sort_order') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="duration">Durée (ms) *</label>
                <input type="number" name="duration" id="duration" min="1000" max="30000" value="{{ old('duration', $homeAd->duration ?? 5000) }}">
                <small>Exemple : 5000 = 5 secondes.</small>
                @error('duration') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label for="title">Titre</label>
                <input type="text" name="title" id="title" value="{{ old('title', $homeAd->title ?? '') }}" placeholder="Ex : Offre spéciale construction">
                @error('title') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label for="subtitle">Sous-titre</label>
                <input type="text" name="subtitle" id="subtitle" value="{{ old('subtitle', $homeAd->subtitle ?? '') }}" placeholder="Ex : Jusqu’à -40% sur une sélection">
                @error('subtitle') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label for="text">Texte</label>
                <textarea name="text" id="text" placeholder="Ex : Découvrez nos offres premium pour bâtir votre avenir.">{{ old('text', $homeAd->text ?? '') }}</textarea>
                @error('text') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label for="link">Lien de redirection</label>
                <input type="url" name="link" id="link" value="{{ old('link', $homeAd->link ?? '') }}" placeholder="https://www.ovanie.com/">
                <small>Le clic sur la publicité redirigera l’utilisateur vers cette URL.</small>
                @error('link') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="button_text">Texte du bouton</label>
                <input type="text" name="button_text" id="button_text" value="{{ old('button_text', $homeAd->button_text ?? '') }}" placeholder="Voir les offres">
                @error('button_text') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="button_url">Lien du bouton</label>
                <input type="url" name="button_url" id="button_url" value="{{ old('button_url', $homeAd->button_url ?? '') }}" placeholder="https://www.ovanie.com/catalog">
                @error('button_url') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="starts_at">Début de diffusion</label>
                <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at', isset($homeAd->starts_at) ? optional($homeAd->starts_at)->format('Y-m-d\TH:i') : '') }}">
                @error('starts_at') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field">
                <label for="ends_at">Fin de diffusion</label>
                <input type="datetime-local" name="ends_at" id="ends_at" value="{{ old('ends_at', isset($homeAd->ends_at) ? optional($homeAd->ends_at)->format('Y-m-d\TH:i') : '') }}">
                @error('ends_at') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label for="media">Image ou vidéo {{ isset($homeAd) ? '' : '*' }}</label>
                <input type="file" name="media" id="media" accept="image/*,video/*" {{ isset($homeAd) ? '' : 'required' }}>
                <small>Formats recommandés : JPG, PNG, WEBP, MP4, WEBM ou MOV.</small>
                @error('media') <div class="marketing-error">{{ $message }}</div> @enderror
            </div>

            <div class="marketing-field marketing-field--full">
                <label class="marketing-switch">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $homeAd->is_active ?? true) ? 'checked' : '' }}>
                    <span class="marketing-switch__slider"></span>
                    <span class="marketing-switch__label">Activer cette publicité sur la page d’accueil</span>
                </label>
            </div>
        </div>

        <div class="marketing-form-actions">
            <a href="{{ route('admin.home-ads.index') }}" class="marketing-btn marketing-btn--ghost">Annuler</a>
            <button type="submit" class="marketing-btn marketing-btn--primary">Enregistrer la publicité</button>
        </div>
    </section>

    <aside class="marketing-side-card">
        <h3>Aperçu en direct</h3>

        <div class="marketing-live-preview">
            <div class="marketing-live-preview__media" id="livePreviewMedia">
                @if(isset($homeAd) && $homeAd->media)
                    @if($homeAd->type === 'video')
                        <video controls muted playsinline>
                            <source src="{{ asset('storage/' . $homeAd->media) }}">
                        </video>
                    @else
                        <img src="{{ asset('storage/' . $homeAd->media) }}" alt="{{ $homeAd->title ?: 'Aperçu publicité' }}">
                    @endif
                @else
                    <div class="marketing-live-preview__placeholder">Aperçu du visuel</div>
                @endif
            </div>

            <div class="marketing-live-preview__overlay">
                <span class="marketing-chip marketing-chip--light" id="previewTypeBadge">{{ strtoupper(old('type', $homeAd->type ?? 'image')) }}</span>
                <h4 id="previewTitle">{{ old('title', $homeAd->title ?? 'Titre de votre publicité') }}</h4>
                <p id="previewText">{{ old('text', $homeAd->text ?? 'Votre texte de publicité apparaîtra ici.') }}</p>
            </div>
        </div>

        <div class="marketing-side-card__list">
            <div>
                <strong>Durée</strong>
                <p id="previewDuration">{{ old('duration', $homeAd->duration ?? 5000) }} ms</p>
            </div>
            <div>
                <strong>Ordre</strong>
                <p id="previewOrder">{{ old('sort_order', $homeAd->sort_order ?? 0) }}</p>
            </div>
            <div>
                <strong>Lien</strong>
                <p id="previewLink">{{ old('link', $homeAd->link ?? 'Aucun lien renseigné') ?: 'Aucun lien renseigné' }}</p>
            </div>
        </div>
    </aside>
</div>
