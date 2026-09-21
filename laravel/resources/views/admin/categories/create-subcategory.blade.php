@extends('admin.layouts.app')

@section('title', 'Nouvelle sous-catégorie | Administration OVANIE')
@section('page-title', 'Nouvelle sous-catégorie')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_categories.css') }}?v={{ file_exists(public_path('admin/css/admin_categories.css')) ? filemtime(public_path('admin/css/admin_categories.css')) : time() }}">
@endpush

@section('content')
<div class="category-admin-page category-form-page">
    <header class="category-form-header category-form-header-compact">
        <div>
            <span class="category-eyebrow">SOUS-CATÉGORIE</span>
            <h2>Créer un classement détaillé</h2>
            <p>Rattachez ce classement à une catégorie principale existante afin de faciliter la recherche et l’ajout des produits.</p>
        </div>
        <a class="category-btn category-btn-secondary" href="{{ route('admin.categories.index') }}">← Retour aux catégories</a>
    </header>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="category-professional-form">
        @csrf
        <input type="hidden" name="category_type" value="subcategory">
        @include('admin.categories.partials.form', ['categoryType' => 'subcategory'])

        <footer class="category-form-footer">
            <div class="category-form-footer-copy">
                <span class="category-form-footer-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
                </span>
                <div>
                    <strong>Sous-catégorie</strong>
                    <small>Le classement sera rattaché à la catégorie principale sélectionnée.</small>
                </div>
            </div>
            <div class="category-form-footer-actions">
                <a href="{{ route('admin.categories.index') }}" class="category-btn category-btn-secondary">Annuler</a>
                <button type="submit" class="category-btn category-btn-primary">Enregistrer la sous-catégorie</button>
            </div>
        </footer>
    </form>
</div>
@endsection
