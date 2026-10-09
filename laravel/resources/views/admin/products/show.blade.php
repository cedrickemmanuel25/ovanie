@extends($catalogueLayout ?? 'admin.layouts.app')
@includeWhen(! ($isAdminSpace ?? true), 'support.partials.catalogue-scope')

@section('title', 'Voir un produit | ' . ($spaceLabel ?? 'Administration OVANIE'))
@section('page-title', 'Fiche produit')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_product_show.css') }}">
@endpush

@section('content')
@php
    $status = strtolower((string) ($product->status ?? ''));
    $statusLabels = [
        'actif' => 'Publié',
        'active' => 'Publié',
        'approved' => 'Publié',
        'published' => 'Publié',
        'draft' => 'Brouillon',
        'brouillon' => 'Brouillon',
        'pending_logistics' => 'Logistique incomplète',
        'inactive' => 'Désactivé',
        'archived' => 'Archivé',
    ];
    $statusLabel = $statusLabels[$status] ?? ucfirst($status ?: 'Non défini');
    $isCommercialCapture = (bool) data_get($product->product_attributes, 'commercial_capture', false);
    $needsAdminCompletion = (bool) data_get($product->product_attributes, 'admin_completion_required', false);
    $mainCategory = $product->category?->parent ?: ($product->category?->parent_id === null ? $product->category : null);
    $subCategory = $product->category?->parent_id ? $product->category : null;
    $logisticsComplete = (float) $product->weight_kg > 0
        && (float) $product->length_cm > 0
        && (float) $product->width_cm > 0
        && (float) $product->height_cm > 0;
    $mainImage = $product->images->firstWhere('is_main', true) ?: $product->images->first();
@endphp

<div class="admin-product-show-page">
    <section class="product-show-header">
        <div>
            <span class="page-kicker">Gestion des produits</span>
            <h2>{{ $product->name }}</h2>
            <div class="header-meta">
                <span class="status-pill">{{ $statusLabel }}</span>
                <span>Produit #{{ $product->id }}</span>
            </div>
        </div>

        <div class="header-actions">
            <a href="{{ route(($productRoutes ?? 'admin.products') . '.index') }}" class="secondary-action">Retour aux produits</a>
            <a href="{{ route(($productRoutes ?? 'admin.products') . '.edit', $product) }}" class="primary-action">
                {{ $needsAdminCompletion ? 'Compléter la fiche' : 'Modifier le produit' }}
            </a>
        </div>
    </section>

    @if($needsAdminCompletion || !$logisticsComplete || !$subCategory || $product->images->isEmpty())
        <section class="completion-alert">
            <div class="completion-alert-icon">!</div>
            <div>
                <strong>Cette fiche nécessite encore une vérification administrative.</strong>
                <p>
                    @if($needsAdminCompletion)Informations commerciales à compléter. @endif
                    @if(!$subCategory)Sous-catégorie à renseigner. @endif
                    @if(!$logisticsComplete)Poids ou dimensions logistiques à compléter. @endif
                    @if($product->images->isEmpty())Image produit manquante. @endif
                </p>
            </div>
            <a href="{{ route(($productRoutes ?? 'admin.products') . '.edit', $product) }}">Compléter maintenant</a>
        </section>
    @endif

    <div class="product-show-grid">
        <main class="product-show-main">
            <section class="detail-card media-card">
                <div class="card-heading">
                    <h3>Présentation du produit</h3>
                </div>

                @if($product->images->isNotEmpty())
                    <div class="product-media-layout">
                        <div class="main-media">
                            <img src="{{ $mainImage?->card_url }}" alt="{{ $product->name }}">
                        </div>
                        @if($product->images->count() > 1)
                            <div class="media-thumbnails">
                                @foreach($product->images as $image)
                                    <img src="{{ $image->card_url }}" alt="Image {{ $loop->iteration }} de {{ $product->name }}">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="empty-media">Aucune image n’est associée à ce produit.</div>
                @endif
            </section>

            <section class="detail-card">
                <div class="card-heading"><h3>Informations générales</h3></div>
                <div class="detail-grid">
                    <div><span>Marque</span><strong>{{ $product->brand ?: 'Non renseignée' }}</strong></div>
                    <div><span>État</span><strong>{{ match($product->product_state) {'used' => 'Occasion', 'refurbished' => 'Reconditionné', default => 'Neuf'} }}</strong></div>
                    <div><span>Catégorie principale</span><strong>{{ $mainCategory?->name ?: 'À renseigner' }}</strong></div>
                    <div><span>Sous-catégorie</span><strong>{{ $subCategory?->name ?: 'À renseigner' }}</strong></div>
                    <div><span>Type de vente</span><strong>{{ $product->sale_type ?: 'Non renseigné' }}</strong></div>
                    <div><span>Unité</span><strong>{{ $product->unit ?: 'Non renseignée' }}</strong></div>
                </div>

                <div class="text-block">
                    <span>Description</span>
                    <p>{{ $product->description ?: 'Aucune description.' }}</p>
                </div>

                @if($product->technical_details)
                    <div class="text-block">
                        <span>Détails techniques</span>
                        <p>{{ $product->technical_details }}</p>
                    </div>
                @endif
            </section>

            @if($product->variants->isNotEmpty())
                <section class="detail-card">
                    <div class="card-heading"><h3>Modèles / couleurs</h3></div>
                    <div class="detail-grid">
                        @foreach($product->variants as $variant)
                            <div style="display:flex;gap:10px;align-items:center">
                                @if($variant->card_url)<img src="{{ $variant->card_url }}" alt="{{ $variant->label }}" style="width:46px;height:46px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px">@endif
                                <span><small>{{ $variant->type }}</small><strong style="display:block">{{ $variant->value }}</strong><small>{{ $variant->stock }} en stock</small></span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="detail-card">
                <div class="card-heading"><h3>Logistique du produit</h3></div>
                <div class="detail-grid logistics-details">
                    <div><span>Poids</span><strong>{{ $product->weight_kg ? number_format((float) $product->weight_kg, 2, ',', ' ') . ' kg' : 'À compléter' }}</strong></div>
                    <div><span>Longueur</span><strong>{{ $product->length_cm ? number_format((float) $product->length_cm, 1, ',', ' ') . ' cm' : 'À compléter' }}</strong></div>
                    <div><span>Largeur</span><strong>{{ $product->width_cm ? number_format((float) $product->width_cm, 1, ',', ' ') . ' cm' : 'À compléter' }}</strong></div>
                    <div><span>Hauteur</span><strong>{{ $product->height_cm ? number_format((float) $product->height_cm, 1, ',', ' ') . ' cm' : 'À compléter' }}</strong></div>
                    <div><span>Volume</span><strong>{{ $product->volume_m3 ? number_format((float) $product->volume_m3, 6, ',', ' ') . ' m³' : 'À calculer' }}</strong></div>
                    <div><span>Fragile</span><strong>{{ $product->fragile ? 'Oui' : 'Non' }}</strong></div>
                    <div><span>Déchargement requis</span><strong>{{ $product->requires_unloading ? 'Oui' : 'Non' }}</strong></div>
                    <div><span>État logistique</span><strong class="{{ $logisticsComplete ? 'text-success' : 'text-warning' }}">{{ $logisticsComplete ? 'Complet' : 'À compléter' }}</strong></div>
                </div>
                @if($product->requires_unloading && $product->unloading_instructions)
                    <div class="text-block">
                        <span>Instructions de déchargement</span>
                        <p>{{ $product->unloading_instructions }}</p>
                    </div>
                @endif
            </section>
        </main>

        <aside class="product-show-sidebar">
            <section class="detail-card financial-card">
                <div class="card-heading"><h3>Prix & stock</h3></div>
                <div class="price-value">{{ number_format((float) $product->price, 0, ',', ' ') }} <small>FCFA</small></div>
                @if($product->promo_price)
                    <div class="promo-value">Promo : {{ number_format((float) $product->promo_price, 0, ',', ' ') }} FCFA</div>
                @endif
                <dl class="side-details">
                    <div><dt>Stock</dt><dd>{{ number_format((int) $product->stock, 0, ',', ' ') }}</dd></div>
                    <div><dt>Quantité minimale</dt><dd>{{ number_format((int) ($product->min_order_quantity ?: 1), 0, ',', ' ') }}</dd></div>
                    <div><dt>Disponibilité</dt><dd>{{ $product->availability_status ?: 'Non définie' }}</dd></div>
                </dl>
            </section>

            <section class="detail-card">
                <div class="card-heading"><h3>Boutique vendeur</h3></div>
                <dl class="side-details">
                    <div><dt>Boutique</dt><dd>{{ $product->shop?->name ?: 'Non renseignée' }}</dd></div>
                    <div><dt>Vendeur</dt><dd>{{ $product->shop?->user?->name ?: 'Non renseigné' }}</dd></div>
                    <div><dt>Mode logistique</dt><dd>{{ $product->shop?->logistics_type === 'seller' ? 'Logistique vendeur' : 'OVANIE Logistics' }}</dd></div>
                </dl>
                @if($product->shop)
                    @if($isAdminSpace ?? true)<a class="inline-link" href="{{ route('admin.shops.show', $product->shop) }}">Ouvrir la boutique</a>@endif
                @endif
            </section>

            <section class="detail-card">
                <div class="card-heading"><h3>Suivi administratif</h3></div>
                <dl class="side-details">
                    <div><dt>Créé le</dt><dd>{{ optional($product->created_at)->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Mis à jour le</dt><dd>{{ optional($product->updated_at)->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Création commerciale</dt><dd>{{ $isCommercialCapture ? 'Oui' : 'Non' }}</dd></div>
                    <div><dt>Fiche à compléter</dt><dd>{{ $needsAdminCompletion ? 'Oui' : 'Non' }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
@endsection
