@extends('admin.layouts.app')
@section('title', 'Créer une référence | Admin OVANIE')
@section('page-title', 'Nouvelle référence')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_catalogue.css') }}">@endpush
@section('content')
<div class="admin-catalogue-page catalogue-form-shell">
    <header class="catalogue-heading">
        <div>
            <span class="catalogue-kicker">Catalogue OVANIE</span>
            <h2>{{ $sourceProduct ? 'Créer une référence depuis une offre' : 'Créer une référence technique' }}</h2>
            <p>{{ $sourceProduct ? 'Les données disponibles ont été reprises depuis l’offre sélectionnée. Vérifiez-les avant l’enregistrement.' : 'Enregistrez une seule fois les informations communes qui seront réutilisées par toutes les boutiques vendant ce produit.' }}</p>
        </div>
        <a href="{{ route('admin.master-products.index', ['tab' => $sourceProduct ? 'products' : 'references']) }}" class="catalogue-btn">Retour au catalogue</a>
    </header>

    @if($sourceProduct)
        <div class="source-product-banner">
            <div>
                <strong>Offre source #{{ $sourceProduct->id }}</strong>
                <span>{{ $sourceProduct->name }} · {{ $sourceProduct->shop?->name ?: 'Boutique non disponible' }}</span>
            </div>
        </div>
    @endif

    @include('admin.master-products.partials.form', [
        'action' => route('admin.master-products.store'),
        'method' => 'POST',
        'masterProduct' => $masterProduct,
        'categories' => $categories,
        'sourceProduct' => $sourceProduct,
    ])
</div>
@endsection
